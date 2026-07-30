<?php

namespace App\Actions\Payments;

use App\Actions\WhatsApp\DownloadWhatsappMediaToStorageAction;
use App\Models\ConversationState;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Purchase;
use App\Models\WhatsappMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitPaymentProofAction
{
    public function __construct(
        protected DownloadWhatsappMediaToStorageAction $downloadWhatsappMediaToStorageAction,
    ) {
    }

    /**
     * @param  array<mixed>|null  $metadata
     */
    public function execute(
        Purchase $purchase,
        WhatsappMessage $whatsappMessage,
        string $storagePath,
        ?string $originalFilename = null,
        ?string $mimeType = null,
        ?int $fileSize = null,
        ?array $metadata = null,
    ): Payment {
        $storageDisk = 'public';
        $mediaId = (string) Arr::get($whatsappMessage->payload_json, 'image.id', '');
        $downloadMeta = null;
        $downloadError = null;

        if (! app()->environment('testing') && $mediaId !== '') {
            try {
                $downloadMeta = $this->downloadWhatsappMediaToStorageAction->execute(
                    mediaId: $mediaId,
                    disk: $storageDisk,
                    path: $storagePath,
                );

                $mimeType = $mimeType ?: ($downloadMeta['mime_type'] ?? null);
                $fileSize = $fileSize ?: ($downloadMeta['file_size'] ?? null);
            } catch (\RuntimeException $exception) {
                $downloadError = $exception->getMessage();
            }
        }

        $metadata = array_merge($metadata ?? [], array_filter([
            'whatsapp_media_id' => $mediaId !== '' ? $mediaId : null,
            'whatsapp_media' => is_array($downloadMeta) ? ($downloadMeta['meta'] ?? null) : null,
            'whatsapp_media_download_error' => $downloadError,
        ], fn (mixed $value): bool => $value !== null));

        return DB::transaction(function () use ($purchase, $whatsappMessage, $storagePath, $originalFilename, $mimeType, $fileSize, $metadata): Payment {
            /** @var Purchase $lockedPurchase */
            $lockedPurchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! in_array($lockedPurchase->status, ['reserved', 'rejected'], true)) {
                throw new InvalidArgumentException('The purchase is not ready to receive a payment proof.');
            }

            if ($whatsappMessage->customer_id !== $lockedPurchase->customer_id) {
                throw new InvalidArgumentException('The WhatsApp message does not belong to the purchase customer.');
            }

            $raffle = $lockedPurchase->raffle()->first();

            if ($raffle !== null && $raffle->hasDrawStarted()) {
                throw new InvalidArgumentException('The raffle draw time has already been reached. Payment proofs can no longer be received for this raffle.');
            }

            $payment = Payment::query()->create([
                'purchase_id' => $lockedPurchase->id,
                'status' => 'pending_review',
                'expected_amount' => $lockedPurchase->total_amount,
                'proof_received_at' => now(),
                'review_due_at' => now()->addHours((int) config('rifax.payments.review_timeout_hours', 48)),
                'proof_channel' => 'whatsapp',
                'metadata_json' => $metadata,
            ]);

            PaymentProof::query()->create([
                'payment_id' => $payment->id,
                'whatsapp_message_id' => $whatsappMessage->id,
                'storage_disk' => 'public',
                'storage_path' => $storagePath,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'uploaded_at' => now(),
                'metadata_json' => $metadata,
            ]);

            $lockedPurchase->forceFill([
                'status' => 'payment_submitted',
                'proof_submitted_at' => now(),
                'reserved_until' => null,
            ])->save();

            if ($lockedPurchase->reservation_id !== null) {
                $lockedPurchase->reservation()
                    ->lockForUpdate()
                    ->update([
                        'expires_at' => now()->addDays(7),
                    ]);
            }

            ConversationState::query()
                ->where('purchase_id', $lockedPurchase->id)
                ->update([
                    'status' => 'purchase_under_review',
                    'payment_id' => $payment->id,
                    'last_inbound_message_id' => $whatsappMessage->id,
                    'last_user_message_at' => now(),
                ]);

            return $payment->load('proofs');
        });
    }
}
