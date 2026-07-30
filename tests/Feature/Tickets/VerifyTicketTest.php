<?php

namespace Tests\Feature\Tickets;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifyTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_public_ticket_payload_without_exposing_customer_data(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create([
            'name' => 'Cliente Privado',
            'phone' => '+573001112233',
        ]);
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Verificable',
            'lottery_name' => 'Loteria Demo',
            'lottery_draw_number' => '1234',
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

        $whatsappMessage = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $whatsappMessage,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        $response = $this->getJson('/api/tickets/'.$ticket->code.'/verify');

        $response->assertOk()
            ->assertJsonPath('ticket.code', $ticket->code)
            ->assertJsonPath('ticket.status', 'valid')
            ->assertJsonPath('ticket.image_url', asset('storage/'.$ticket->image_path))
            ->assertJsonPath('raffle.title', 'Rifa Verificable')
            ->assertJsonPath('numbers.0', '0001')
            ->assertJsonMissing(['name' => 'Cliente Privado'])
            ->assertJsonMissing(['phone' => '+573001112233']);

        $publicResponse = $this->getJson('/tickets/'.$ticket->verification_token);

        $publicResponse->assertOk()
            ->assertJsonPath('ticket.code', $ticket->code);

        $publicPageResponse = $this->get('/tickets/'.$ticket->verification_token);

        $publicPageResponse->assertOk()
            ->assertSee($ticket->code)
            ->assertSee('Boleto validado')
            ->assertSee('Abrir imagen del boleto')
            ->assertDontSee('Cliente Privado')
            ->assertDontSee('+573001112233');
    }
}
