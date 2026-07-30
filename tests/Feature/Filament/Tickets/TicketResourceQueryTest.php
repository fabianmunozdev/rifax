<?php

namespace Tests\Feature\Filament\Tickets;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_retry_count_and_latest_attempt_for_the_ticket(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Panel',
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Tu boleto esta disponible.',
            'payload_json' => [
                'ticket_id' => $ticket->id,
                'meta_error' => [
                    'message' => 'Meta temporary outage',
                    'status' => 503,
                ],
            ],
            'status' => 'failed',
            'provider_created_at' => now(),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Reintento del boleto.',
            'payload_json' => [
                'ticket_id' => $ticket->id,
                'retry_of_message_id' => 999,
            ],
            'status' => 'queued',
            'provider_created_at' => now()->addSecond(),
        ]);

        $resourceRecord = TicketResource::getEloquentQuery()->findOrFail($ticket->id);

        $this->assertSame('queued', $resourceRecord->last_whatsapp_message_status);
        $this->assertSame('document', $resourceRecord->last_whatsapp_message_type);
        $this->assertSame(1, (int) $resourceRecord->whatsapp_retry_count);
        $this->assertNull($resourceRecord->last_whatsapp_error_summary);
        $this->assertNotNull($resourceRecord->last_whatsapp_message_at);
    }

    public function test_it_exposes_the_latest_whatsapp_failure_summary_for_the_ticket(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Panel Error',
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Tu boleto esta disponible.',
            'payload_json' => [
                'ticket_id' => $ticket->id,
                'meta_error' => [
                    'message' => 'Meta temporary outage',
                    'status' => 503,
                ],
            ],
            'status' => 'failed',
            'provider_created_at' => now(),
        ]);

        $resourceRecord = TicketResource::getEloquentQuery()->findOrFail($ticket->id);

        $this->assertSame('failed', $resourceRecord->last_whatsapp_message_status);
        $this->assertSame('Meta temporary outage', $resourceRecord->last_whatsapp_error_summary);
    }

    public function test_it_exposes_winner_notification_metadata_for_the_latest_ticket_message(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Ganador Panel',
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Tu numero fue ganador.',
            'payload_json' => [
                'ticket_id' => $ticket->id,
                'raffle_id' => $raffle->id,
                'intent' => 'raffle_winner_notification',
                'winning_number' => '0002',
            ],
            'status' => 'queued',
            'provider_created_at' => now(),
        ]);

        $resourceRecord = TicketResource::getEloquentQuery()->findOrFail($ticket->id);

        $this->assertSame('template', $resourceRecord->last_whatsapp_message_type);
        $this->assertSame('raffle_winner_notification', $resourceRecord->last_whatsapp_intent);
        $this->assertSame('0002', $resourceRecord->last_whatsapp_winning_number);
    }
}
