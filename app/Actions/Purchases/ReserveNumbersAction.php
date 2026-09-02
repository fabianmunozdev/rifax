<?php

namespace App\Actions\Purchases;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseNumber;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReserveNumbersAction
{
    /**
     * @param  list<string>  $requestedNumbers
     * @param  array<string, mixed>  $metadata
     */
    public function execute(Customer $customer, Raffle $raffle, array $requestedNumbers, string $selectionMode = 'manual', array $metadata = []): Purchase
    {
        if (! $raffle->salesAreOpen()) {
            throw new InvalidArgumentException('This raffle is no longer accepting reservations because the draw time has been reached.');
        }

        $requestedNumbers = array_values($requestedNumbers);
        $duplicates = collect($requestedNumbers)
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->values()
            ->all();

        if ($duplicates !== []) {
            throw new InvalidArgumentException('Duplicate raffle numbers are not allowed in the same purchase.');
        }

        $digits = $raffle->normalizedNumberDigits();
        $invalidLengthNumbers = collect($requestedNumbers)
            ->filter(fn (string $number): bool => strlen($number) !== $digits)
            ->values()
            ->all();

        if ($invalidLengthNumbers !== []) {
            throw new InvalidArgumentException('One or more raffle numbers do not match the configured number length.');
        }

        $quantity = count($requestedNumbers);

        if (! in_array($selectionMode, ['manual', 'random'], true)) {
            throw new InvalidArgumentException('The selection mode is invalid.');
        }

        if ($quantity < $raffle->min_numbers_per_purchase) {
            throw new InvalidArgumentException('The requested quantity is below the raffle minimum.');
        }

        return DB::transaction(function () use ($customer, $raffle, $requestedNumbers, $quantity, $selectionMode, $metadata): Purchase {
            $previousState = ConversationState::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'whatsapp')
                ->with(['reservation:id,status,customer_id,purchase_id', 'purchase:id,status,reservation_id,customer_id'])
                ->first();

            $prevReservation = $previousState?->reservation;
            $prevPurchase = $previousState?->purchase;

            if ($prevReservation !== null && $prevReservation->customer_id === $customer->id && $prevReservation->status === 'active') {
                $prevReservation->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ])->save();
            }

            if ($prevPurchase !== null
                && $prevPurchase->customer_id === $customer->id
                && in_array($prevPurchase->status, ['reserved', 'rejected'], true)
            ) {
                $releasedPrev = 0;
                foreach ($prevPurchase->numbers as $prevNumber) {
                    if ($prevNumber->raffleNumber?->status === 'reserved') {
                        $prevNumber->raffleNumber->forceFill([
                            'status' => 'available',
                            'reserved_until' => null,
                        ])->save();
                        $releasedPrev++;
                    }
                }
                PurchaseNumber::query()->where('purchase_id', $prevPurchase->id)->delete();
                $prevPurchase->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'reserved_until' => null,
                    'metadata_json' => array_merge($prevPurchase->metadata_json ?? [], [
                        'replaced_by_new_reservation_at' => now()->toIso8601String(),
                        'released_numbers_count' => $releasedPrev,
                    ]),
                ])->save();

                Payment::query()
                    ->where('purchase_id', $prevPurchase->id)
                    ->where('status', 'pending_review')
                    ->update([
                        'status' => 'customer_cancelled',
                        'reviewed_at' => now(),
                        'review_due_at' => null,
                    ]);
            }

            $numbers = RaffleNumber::query()
                ->where('raffle_id', $raffle->id)
                ->whereIn('number', $requestedNumbers)
                ->lockForUpdate()
                ->get()
                ->keyBy('number');

            if ($numbers->count() !== $quantity) {
                throw new InvalidArgumentException('One or more raffle numbers do not exist.');
            }

            $unavailableNumbers = $numbers
                ->filter(fn (RaffleNumber $raffleNumber) => $raffleNumber->status !== 'available')
                ->keys()
                ->values()
                ->all();

            if ($unavailableNumbers !== []) {
                throw new InvalidArgumentException('One or more raffle numbers are not available.');
            }

            $expiresAt = now()->addMinutes($raffle->reservation_timeout_minutes);
            $unitPrice = number_format((float) $raffle->price_per_number, 2, '.', '');
            $totalAmount = number_format(((float) $raffle->price_per_number) * $quantity, 2, '.', '');

            $selectedNumbersPayload = array_map(
                fn (string $number): array => ['number' => $number, 'source' => $selectionMode],
                $requestedNumbers,
            );
            $normalizedMetadata = $this->normalizeMetadata($metadata);

            $reservation = Reservation::query()->create([
                'customer_id' => $customer->id,
                'raffle_id' => $raffle->id,
                'status' => 'active',
                'quantity' => $quantity,
                'selection_mode' => $selectionMode,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'currency' => 'COP',
                'expires_at' => $expiresAt,
                'numbers_snapshot_json' => $selectedNumbersPayload,
                'metadata_json' => $normalizedMetadata !== [] ? $normalizedMetadata : null,
            ]);

            $purchase = Purchase::query()->create([
                'customer_id' => $customer->id,
                'raffle_id' => $raffle->id,
                'reservation_id' => $reservation->id,
                'status' => 'reserved',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'currency' => 'COP',
                'raffle_title_snapshot' => $raffle->title,
                'payment_instructions_snapshot' => $this->buildPaymentInstructionsSnapshot(),
                'reserved_until' => $expiresAt,
                'metadata_json' => $normalizedMetadata !== [] ? $normalizedMetadata : null,
            ]);

            foreach ($numbers as $raffleNumber) {
                $raffleNumber->forceFill([
                    'status' => 'reserved',
                    'reserved_until' => $expiresAt,
                ])->save();

                PurchaseNumber::query()->create([
                    'purchase_id' => $purchase->id,
                    'raffle_number_id' => $raffleNumber->id,
                    'number' => $raffleNumber->number,
                ]);
            }

            ConversationState::query()->updateOrCreate([
                'customer_id' => $customer->id,
                'channel' => 'whatsapp',
            ], [
                'status' => 'purchase_payment_instructions',
                'current_raffle_id' => $raffle->id,
                'requested_quantity' => $quantity,
                'selection_mode' => $selectionMode,
                'selected_numbers_json' => $selectedNumbersPayload,
                'reservation_id' => $reservation->id,
                'purchase_id' => $purchase->id,
                'payment_id' => null,
                'context_expires_at' => $expiresAt,
                'last_bot_message_at' => now(),
                'metadata_json' => $normalizedMetadata !== [] ? $normalizedMetadata : null,
            ]);

            return $purchase->load(['reservation', 'numbers.raffleNumber']);
        });
    }

    /**
     * @return list<array{name: string, instructions: string|null, account_holder: string|null, account_reference: string|null, details: array<mixed>|null}>
     */
    protected function buildPaymentInstructionsSnapshot(): array
    {
        return PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentMethod $paymentMethod): array => [
                'name' => $paymentMethod->name,
                'instructions' => $paymentMethod->instructions,
                'account_holder' => $paymentMethod->account_holder,
                'account_reference' => $paymentMethod->account_reference,
                'details' => $paymentMethod->details_json,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function normalizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->filter(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null && $value !== '';
            })
            ->all();
    }
}
