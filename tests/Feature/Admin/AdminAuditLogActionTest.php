<?php

namespace Tests\Feature\Admin;

use App\Actions\Conversations\HardResetConversationAction;
use App\Actions\Conversations\SoftResetConversationAction;
use App\Actions\Payments\RejectPaymentAction;
use App\Models\AdminAuditLog;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reject_payment_writes_an_admin_audit_log(): void
    {
        $reviewer = User::factory()->create();
        $purchase = Purchase::factory()->create([
            'status' => 'payment_submitted',
        ]);
        $payment = Payment::factory()->for($purchase)->pendingReview()->create();

        ConversationState::factory()->for($purchase->customer)->create([
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
        ]);

        app(RejectPaymentAction::class)->execute($payment, 'Transfer proof is not readable.', $reviewer);

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'payment.rejected',
            'action' => 'reject',
            'user_id' => $reviewer->id,
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_conversation_resets_write_audit_logs(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $secondCustomer = Customer::factory()->create();

        $softResetConversation = ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_select_numbers',
            'requested_quantity' => 3,
            'selection_mode' => 'manual',
            'selected_numbers_json' => [['number' => '0001']],
            'context_expires_at' => now()->addMinutes(10),
        ]);

        app(SoftResetConversationAction::class)->execute($softResetConversation, 'Support took over.', $actor);

        $hardResetConversation = ConversationState::factory()->for($secondCustomer)->create([
            'status' => 'purchase_payment_instructions',
            'metadata_json' => [
                'follow_up_required' => true,
            ],
        ]);

        app(HardResetConversationAction::class)->execute($hardResetConversation, 'Restart the conversation from scratch.', $actor);

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'conversation.soft_reset',
            'action' => 'soft_reset',
            'user_id' => $actor->id,
            'auditable_type' => ConversationState::class,
            'auditable_id' => $softResetConversation->id,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'conversation.hard_reset',
            'action' => 'hard_reset',
            'user_id' => $actor->id,
            'auditable_type' => ConversationState::class,
            'auditable_id' => $hardResetConversation->id,
        ]);

        $this->assertSame(2, AdminAuditLog::query()->count());
    }
}
