<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseNumber;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_active_published_raffles_on_the_public_landing(): void
    {
        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'whatsapp_bot_phone' => '+573009998877',
            'support_phone' => '+573001112233',
            'help_message' => 'Compra tus numeros desde la landing publica.',
            'primary_color' => '#123456',
            'secondary_color' => '#112233',
            'accent_color' => '#ff6600',
            'terms_url' => 'https://rifax.test/terminos',
            'privacy_policy_url' => 'https://rifax.test/privacidad',
            'currency_code' => 'COP',
            'default_locale' => 'es',
        ]);

        PaymentMethod::query()->create([
            'name' => 'Nequi',
            'slug' => 'nequi',
            'status' => 'active',
            'instructions' => 'Paga a este numero.',
            'account_holder' => 'Rifax',
            'account_reference' => '3001112233',
            'sort_order' => 1,
            'is_visible' => true,
        ]);

        ContentEntry::query()->create([
            'type' => 'faq_fixed',
            'key' => 'faq.public.payment.proof',
            'title' => 'Como envio el comprobante?',
            'category' => 'payments',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => null,
            'body_text' => 'Envialo directamente por WhatsApp y quedara en revision.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 10,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
            'published_at' => now(),
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Landing',
            'slug' => 'rifa-landing',
            'description' => 'Una rifa publica para la landing.',
            'price_per_number' => 10000,
            'is_featured' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0000', 'status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0001', 'status' => 'reserved']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002', 'status' => 'paid']);

        $winnerCustomer = Customer::factory()->create([
            'name' => 'Ana Perez',
            'phone' => '+573009998877',
            'wa_id' => '573009998877',
        ]);

        $closedRaffle = Raffle::factory()->create([
            'title' => 'Rifa Cerrada',
            'slug' => 'rifa-cerrada',
            'status' => 'closed',
            'result_number' => '0042',
            'result_published_at' => now()->subDay(),
            'lottery_name' => 'Loteria de Bogota',
            'lottery_draw_number' => '7788',
            'lottery_reference_url' => 'https://rifax.test/resultados/rifa-cerrada',
        ]);

        $winnerNumber = RaffleNumber::factory()->for($closedRaffle)->create([
            'number' => '0042',
            'status' => 'winner',
        ]);

        $winnerPurchase = Purchase::factory()->paid()->create([
            'customer_id' => $winnerCustomer->id,
            'raffle_id' => $closedRaffle->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'total_amount' => 10000,
            'raffle_title_snapshot' => $closedRaffle->title,
        ]);

        PurchaseNumber::query()->create([
            'purchase_id' => $winnerPurchase->id,
            'raffle_number_id' => $winnerNumber->id,
            'number' => '0042',
        ]);

        $response = $this->get('/?utm_source=meta&utm_medium=social&utm_campaign=lanzamiento');

        $response->assertOk()
            ->assertSee('Rifa destacada')
            ->assertSee('Descubre la rifa destacada del momento')
            ->assertSee('Rifa Landing')
            ->assertSee('Una rifa publica para la landing.')
            ->assertSee('Elegir numeros ahora')
            ->assertSee('Como Funciona')
            ->assertSee('Informacion Operativa')
            ->assertSee('Preguntas Frecuentes')
            ->assertSee('Como envio el comprobante?')
            ->assertSee('Envialo directamente por WhatsApp y quedara en revision.')
            ->assertSee('Los metodos de pago visibles se confirman por WhatsApp')
            ->assertSee('Metodos visibles:')
            ->assertSee('Nequi: 3001112233')
            ->assertSee('Tus numeros se reservan por tiempo limitado mientras completas el pago.')
            ->assertSee('Si envias el comprobante antes del sorteo, la compra queda en revision')
            ->assertSee('Las compras pendientes deben resolverse antes de publicar el resultado final dentro de la plataforma.')
            ->assertSee('Ganadores Recientes')
            ->assertSee('Rifa Cerrada')
            ->assertSee('0042')
            ->assertSee('Ana P. · ****8877')
            ->assertSee('https://rifax.test/resultados/rifa-cerrada', escape: false)
            ->assertSee('https://rifax.test/terminos', escape: false)
            ->assertSee('https://rifax.test/privacidad', escape: false)
            ->assertSee('Seleccionar numeros')
            ->assertSee('Comprar por WhatsApp')
            ->assertSee('data-testid="mobile-sticky-cta"', escape: false)
            ->assertSee('Elegir numeros')
            ->assertSee('WhatsApp')
            ->assertSee('https://wa.me/573009998877?text=', escape: false)
            ->assertSee('https://wa.me/573001112233?text=PAGOS', escape: false)
            ->assertSee('/raffles/rifa-landing/number-picker?quantity=1&amp;source=landing_featured&amp;utm_source=meta&amp;utm_medium=social&amp;utm_campaign=lanzamiento', escape: false)
            ->assertSee('/raffles/rifa-landing/number-picker?quantity=1&amp;source=landing_sticky&amp;utm_source=meta&amp;utm_medium=social&amp;utm_campaign=lanzamiento', escape: false);
    }

    public function test_it_hides_published_raffles_when_their_draw_time_has_already_started(): void
    {
        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'whatsapp_bot_phone' => '+573001112244',
            'support_phone' => '+573001112233',
            'currency_code' => 'COP',
            'default_locale' => 'es',
        ]);

        $expiredRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Vencida',
            'slug' => 'rifa-vencida',
            'draw_date' => now()->subMinute()->toDateString(),
            'draw_time' => now()->subMinute()->format('H:i:s'),
        ]);

        $openRaffle = Raffle::factory()->published()->featured()->create([
            'title' => 'Rifa Vigente',
            'slug' => 'rifa-vigente',
            'draw_date' => now()->addDay()->toDateString(),
            'draw_time' => '18:00:00',
        ]);

        RaffleNumber::factory()->for($expiredRaffle)->create(['number' => '0000', 'status' => 'available']);
        RaffleNumber::factory()->for($openRaffle)->create(['number' => '0000', 'status' => 'available']);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Rifa Vigente')
            ->assertDontSee('Rifa Vencida');
    }

    public function test_it_renders_filter_tabs_when_multiple_raffles_exist(): void
    {
        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'whatsapp_bot_phone' => '+573001112244',
            'support_phone' => '+573001112233',
            'currency_code' => 'COP',
            'default_locale' => 'es',
        ]);

        $firstRaffle = Raffle::factory()->published()->featured()->create([
            'title' => 'Rifa Uno',
            'slug' => 'rifa-uno',
        ]);

        $secondRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Dos',
            'slug' => 'rifa-dos',
        ]);

        $thirdRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Tres',
            'slug' => 'rifa-tres',
        ]);

        RaffleNumber::factory()->for($firstRaffle)->create(['number' => '0000', 'status' => 'available']);
        RaffleNumber::factory()->for($secondRaffle)->create(['number' => '0000', 'status' => 'available']);
        RaffleNumber::factory()->for($thirdRaffle)->create(['number' => '0000', 'status' => 'available']);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Mas Rifas Activas')
            ->assertSee('Rifa destacada')
            ->assertSee('Todas')
            ->assertSee('Rifa Dos')
            ->assertSee('Rifa Tres')
            ->assertSee('Comprar esta rifa por WhatsApp')
            ->assertSee('data-filter="rifa-dos"', escape: false)
            ->assertSee('data-filter="rifa-tres"', escape: false);
    }

    public function test_it_renders_an_empty_state_when_no_active_raffles_exist(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('No hay rifas activas por ahora')
            ->assertSee('Rifas Disponibles');
    }
}
