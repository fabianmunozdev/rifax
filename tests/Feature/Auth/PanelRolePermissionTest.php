<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\ContentEntries\ContentEntryResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\ConversationState;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_configuration_raffles_audit_and_users(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(AdminAuditLogResource::canViewAny());
        $this->assertTrue(CompanySettingResource::canViewAny());
        $this->assertTrue(PaymentMethodResource::canCreate());
        $this->assertTrue(ContentEntryResource::canCreate());
        $this->assertTrue(RaffleResource::canCreate());
    }

    public function test_support_can_manage_content_and_conversations_but_cannot_manage_configuration_or_review_payments(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $payment = Payment::factory()->pendingReview()->create();
        $purchase = Purchase::factory()->pendingPayment()->create();

        $this->assertTrue(ContentEntryResource::canCreate());
        $this->assertTrue(CustomerResource::canViewAny());
        $this->assertTrue(ConversationResource::canViewAny());
        $this->assertTrue(WhatsappMessageResource::canViewAny());
        $this->assertFalse(CompanySettingResource::canViewAny());
        $this->assertFalse(PaymentMethodResource::canViewAny());
        $this->assertTrue(PaymentResource::makeApproveAction()->record($payment)->isHidden());
        $this->assertFalse(PurchaseResource::makeSendPaymentReminderAction()->record($purchase)->isHidden());
    }

    public function test_finance_can_review_payments_but_cannot_manage_conversations_or_configuration(): void
    {
        $this->actingAs(User::factory()->finance()->create());

        $payment = Payment::factory()->pendingReview()->create();
        $purchase = Purchase::factory()->pendingPayment()->create();
        $conversation = ConversationState::factory()->create();

        $this->assertTrue(PaymentResource::canViewAny());
        $this->assertFalse(PaymentResource::makeApproveAction()->record($payment)->isHidden());
        $this->assertFalse(PaymentResource::makeRejectAction()->record($payment)->isHidden());
        $this->assertFalse(PurchaseResource::makeSendPaymentReminderAction()->record($purchase)->isHidden());
        $this->assertFalse(ContentEntryResource::canViewAny());
        $this->assertTrue(ConversationResource::makeSoftResetAction()->record($conversation)->isHidden());
    }

    public function test_operator_can_manage_ticket_delivery_but_cannot_review_payments_or_manage_configuration(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $purchase = Purchase::factory()->paid()->create();
        $ticket = Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => 'TK-'.$purchase->id,
            'verification_token' => 'token-'.$purchase->id,
            'public_url' => 'https://rifax.test/tickets/'.$purchase->id,
            'image_path' => 'tickets/'.$purchase->id.'.svg',
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ]);
        $payment = Payment::factory()->pendingReview()->for($purchase)->create();
        $raffle = $purchase->raffle;

        $this->assertTrue(TicketResource::canViewAny());
        $this->assertFalse(TicketResource::makeResendWhatsappAction()->record($ticket)->isHidden());
        $this->assertFalse(TicketResource::makeRegenerateAssetsAction()->record($ticket)->isHidden());
        $this->assertTrue(PaymentResource::makeApproveAction()->record($payment)->isHidden());
        $this->assertFalse(RaffleResource::makeSendDrawReminderAction()->record($raffle)->isHidden());
        $this->assertFalse(RaffleResource::makeSendUpcomingAnnouncementAction()->record($raffle)->isHidden());
        $this->assertFalse(CompanySettingResource::canViewAny());
    }
}
