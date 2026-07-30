<?php

namespace Tests\Feature\Filament\Tickets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Purchase;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_and_regenerate_actions_are_disabled_for_non_paid_tickets(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $ticket = $this->makeTicket(
            Purchase::factory()->create([
                'status' => 'payment_submitted',
            ]),
        );

        $this->assertTrue(TicketResource::makeResendWhatsappAction()->record($ticket)->isDisabled());
        $this->assertTrue(TicketResource::makeRegenerateAssetsAction()->record($ticket)->isDisabled());
    }

    public function test_resend_and_regenerate_actions_are_enabled_for_paid_tickets(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $ticket = $this->makeTicket(
            Purchase::factory()->paid()->create(),
        );

        $this->assertFalse(TicketResource::makeResendWhatsappAction()->record($ticket)->isDisabled());
        $this->assertFalse(TicketResource::makeRegenerateAssetsAction()->record($ticket)->isDisabled());
    }

    public function test_purchase_status_color_handles_active_review_states(): void
    {
        /** @var Closure(?string): string $statusColor */
        $statusColor = Closure::bind(
            static fn (?string $state): string => TicketResource::statusColor($state),
            null,
            TicketResource::class,
        );

        $this->assertSame('warning', $statusColor('payment_submitted'));
        $this->assertSame('warning', $statusColor('under_review'));
        $this->assertSame('info', $statusColor('reserved'));
    }

    private function makeTicket(Purchase $purchase): Ticket
    {
        return Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => 'TK-'.$purchase->id,
            'verification_token' => 'token-'.$purchase->id,
            'public_url' => 'https://rifax.test/tickets/'.$purchase->id,
            'image_path' => 'tickets/'.$purchase->id.'.svg',
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ]);
    }
}
