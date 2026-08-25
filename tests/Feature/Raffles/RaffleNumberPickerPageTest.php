<?php

namespace Tests\Feature\Raffles;

use App\Models\CompanySetting;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePickerIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaffleNumberPickerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_public_number_picker_for_a_published_raffle(): void
    {
        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'whatsapp_bot_phone' => '+573009994455',
            'support_phone' => '+573001112233',
            'currency_code' => 'COP',
            'default_locale' => 'es',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Web',
            'slug' => 'rifa-web',
            'number_digits' => 3,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '001',
            'status' => 'available',
        ]);
        RaffleNumber::factory()->for($raffle)->create([
            'number' => '002',
            'status' => 'reserved',
        ]);
        RaffleNumber::factory()->for($raffle)->create([
            'number' => '543',
            'status' => 'available',
        ]);
        RaffleNumber::factory()->for($raffle)->create([
            'number' => '700',
            'status' => 'paid',
        ]);
        RaffleNumber::factory()->for($raffle)->create([
            'number' => '999',
            'status' => 'winner',
        ]);

        $response = $this->get('/raffles/rifa-web/number-picker?quantity=2&source=landing_featured&utm_source=facebook&utm_campaign=rifa-julio');

        $response->assertOk()
            ->assertSee('Rifa Web')
            ->assertSee('001')
            ->assertSee('543')
            ->assertSee('002')
            ->assertSee('700')
            ->assertSee('999')
            ->assertSee('Reservado')
            ->assertSee('Pagado')
            ->assertSee('Ganador')
            ->assertSee('Disponibles ahora:')
            ->assertSee('Disponibles ahora: 2')
            ->assertSee('573009994455')
            ->assertSee('0 seleccionados de 2', escape: false)
            ->assertSee('Continuar compra por WhatsApp')
            ->assertSee('Se abrira un mensaje listo para enviar. No necesitas editarlo.')
            ->assertSee('/raffles/rifa-web/number-picker/intents?source=landing_featured&amp;utm_source=facebook&amp;utm_campaign=rifa-julio', escape: false);
    }

    public function test_it_returns_not_found_for_a_non_published_raffle(): void
    {
        $raffle = Raffle::factory()->create([
            'slug' => 'rifa-oculta',
            'status' => 'draft',
        ]);

        $response = $this->get('/raffles/'.$raffle->slug.'/number-picker');

        $response->assertNotFound();
    }

    public function test_it_returns_not_found_for_a_raffle_after_the_draw_time(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'slug' => 'rifa-cerrada-por-hora',
            'draw_date' => now()->subMinute()->toDateString(),
            'draw_time' => now()->subMinute()->format('H:i:s'),
        ]);

        $response = $this->get('/raffles/'.$raffle->slug.'/number-picker');

        $response->assertNotFound();
    }

    public function test_it_returns_number_chunks_for_incremental_loading(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'slug' => 'rifa-feed',
            'number_digits' => 3,
        ]);

        foreach (range(1, 30) as $number) {
            RaffleNumber::factory()->for($raffle)->create([
                'number' => str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'status' => $number % 7 === 0 ? 'reserved' : 'available',
            ]);
        }

        $response = $this->getJson('/raffles/rifa-feed/number-picker/numbers?per_page=24');

        $response->assertOk()
            ->assertJsonCount(24, 'items')
            ->assertJsonPath('items.0.number', '001')
            ->assertJsonPath('items.0.status_label', 'Disponible')
            ->assertJsonPath('items.6.number', '007')
            ->assertJsonPath('items.6.status_label', 'Reservado')
            ->assertJsonPath('next_cursor', '024');

        $nextResponse = $this->getJson('/raffles/rifa-feed/number-picker/numbers?per_page=24&cursor=024');

        $nextResponse->assertOk()
            ->assertJsonCount(6, 'items')
            ->assertJsonPath('items.0.number', '025')
            ->assertJsonPath('next_cursor', null);
    }

    public function test_it_returns_json_validation_errors_for_invalid_number_chunk_params(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'slug' => 'rifa-feed-invalida',
        ]);

        $response = $this->get('/raffles/'.$raffle->slug.'/number-picker/numbers?per_page=5');

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Los parametros de carga de numeros no son validos.')
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_creates_a_picker_intent_for_available_numbers(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'slug' => 'rifa-intent',
            'number_digits' => 3,
            'min_numbers_per_purchase' => 2,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '001',
            'status' => 'available',
        ]);
        RaffleNumber::factory()->for($raffle)->create([
            'number' => '543',
            'status' => 'available',
        ]);

        $response = $this
            ->withHeader('referer', 'https://rifax.test/?utm_source=facebook')
            ->postJson('/raffles/rifa-intent/number-picker/intents?source=landing_featured&utm_source=facebook&utm_campaign=rifa-julio', [
                'quantity' => 2,
                'selected_numbers' => ['001', '543'],
                'trace' => [
                    'source' => 'landing_featured',
                    'referrer_url' => 'https://rifax.test/?utm_source=facebook',
                    'picker_page_url' => 'https://rifax.test/raffles/rifa-intent/number-picker?quantity=2&source=landing_featured&utm_source=facebook&utm_campaign=rifa-julio',
                    'utm_source' => 'facebook',
                    'utm_campaign' => 'rifa-julio',
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'whatsapp_message', 'expires_at']);

        $this->assertDatabaseHas('raffle_picker_intents', [
            'raffle_id' => $raffle->id,
            'quantity' => 2,
        ]);

        $intent = RafflePickerIntent::query()->latest('id')->firstOrFail();

        $this->assertSame('landing_featured', $intent->source);
        $this->assertSame(['001', '543'], $intent->selected_numbers_json);
        $this->assertSame('https://rifax.test/?utm_source=facebook', $intent->metadata_json['referrer_url'] ?? null);
        $this->assertSame('facebook', $intent->metadata_json['utm_source'] ?? null);
        $this->assertSame('rifa-julio', $intent->metadata_json['utm_campaign'] ?? null);
        $this->assertStringContainsString('Hola, quiero continuar con mi seleccion visual', $response->json('whatsapp_message'));
        $this->assertStringContainsString("Codigo de seleccion: PICKER {$intent->token}", $response->json('whatsapp_message'));
        $this->assertStringContainsString('mantener este mensaje sin modificar', Strtolower($response->json('whatsapp_message')));
        $this->assertNull($intent->consumed_at);
    }
}
