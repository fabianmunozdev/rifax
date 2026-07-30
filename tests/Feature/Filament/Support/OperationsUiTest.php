<?php

namespace Tests\Feature\Filament\Support;

use App\Filament\Support\OperationsUi;
use Tests\TestCase;

class OperationsUiTest extends TestCase
{
    public function test_purchase_and_payment_statuses_use_operator_friendly_labels(): void
    {
        $this->assertSame('Payment submitted', OperationsUi::purchaseStatusLabel('payment_submitted'));
        $this->assertSame('Under review', OperationsUi::purchaseStatusLabel('under_review'));
        $this->assertSame('Under review', OperationsUi::paymentStatusLabel('pending_review'));
        $this->assertSame('No payment yet', OperationsUi::paymentStatusLabel('none'));
    }

    public function test_whatsapp_statuses_and_intents_use_consistent_copy_and_colors(): void
    {
        $this->assertSame('Not sent', OperationsUi::whatsappMessageStatusLabel('not_sent'));
        $this->assertSame('warning', OperationsUi::whatsappMessageStatusColor('queued'));
        $this->assertSame('Sent', OperationsUi::whatsappProviderStatusLabel('sent'));
        $this->assertSame('Winner notification', OperationsUi::whatsappIntentLabel('raffle_winner_notification'));
        $this->assertSame('info', OperationsUi::whatsappIntentColor('ticket_delivery'));
    }

    public function test_ticket_attention_labels_and_colors_are_shared(): void
    {
        $this->assertSame('No delivery attempt', OperationsUi::ticketAttentionReasonLabel('without_delivery'));
        $this->assertSame('Pending provider delivery', OperationsUi::ticketAttentionReasonLabel('awaiting_delivery'));
        $this->assertSame('warning', OperationsUi::ticketAttentionReasonColor('delivered_not_read'));
        $this->assertSame('danger', OperationsUi::ticketAttentionReasonColor('failed'));
    }
}
