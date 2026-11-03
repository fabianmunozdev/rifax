<?php

namespace Tests\Feature\Raffles;

use App\Actions\Raffles\PublishEarlyPrizeResultAction;
use App\Filament\Resources\Raffles\Pages\EditRaffle;
use App\Filament\Resources\Raffles\RelationManagers\EarlyPrizesRelationManager;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\PurchaseNumber;
use App\Models\Raffle;
use App\Models\RaffleEarlyPrize;
use App\Models\RaffleNumber;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class EarlyPrizesTest extends TestCase
{
    use RefreshDatabase;

    private function raffle(): Raffle
    {
        return Raffle::factory()->published()->featured()->create([
            'title' => 'Moto 2027',
            'slug' => 'moto-2027',
            'number_digits' => 4,
            'draw_date' => now()->addMonth()->toDateString(),
            'draw_time' => '21:00:00',
        ]);
    }

    private function prize(Raffle $raffle, array $attributes = []): RaffleEarlyPrize
    {
        return $raffle->earlyPrizes()->create(array_merge([
            'title' => 'Bono de $500.000',
            'description' => 'Se entrega en efectivo.',
            'lottery_name' => 'Lotería de Medellín',
            'draw_date' => now()->addWeek()->toDateString(),
            'draw_time' => '22:30',
        ], $attributes));
    }

    private function sell(Raffle $raffle, string $number, string $status = 'paid', string $name = 'Ana Pérez'): Purchase
    {
        $raffleNumber = RaffleNumber::factory()->for($raffle)->create([
            'number' => $number,
            'status' => $status === 'paid' ? 'paid' : 'reserved',
        ]);
        $customer = Customer::factory()->create(['name' => $name, 'phone' => '+573001118877']);
        $purchase = Purchase::factory()->create([
            'raffle_id' => $raffle->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'quantity' => 1,
        ]);
        PurchaseNumber::query()->create([
            'purchase_id' => $purchase->id,
            'raffle_number_id' => $raffleNumber->id,
            'number' => $number,
        ]);

        return $purchase;
    }

    public function test_the_paid_number_matching_the_last_digits_wins_and_keeps_playing_for_the_main_prize(): void
    {
        $raffle = $this->raffle();
        $prize = $this->prize($raffle);
        $purchase = $this->sell($raffle, '4821');

        // La lotería publica 5 cifras; la rifa es de 4: se toman las últimas 4.
        $result = app(PublishEarlyPrizeResultAction::class)->execute($prize, '94821');

        $this->assertSame('4821', $result->result_number);
        $this->assertSame('94821', $result->lottery_result);
        $this->assertSame($purchase->id, $result->winner_purchase_id);
        $this->assertSame(RaffleEarlyPrize::STATUS_WON, $result->status());
        $this->assertSame('Ana P. · ****8877', $result->publicWinnerLabel());
        // Sigue jugando: el número no pasa a "winner" ni cambia la rifa.
        $this->assertDatabaseHas('raffle_numbers', ['raffle_id' => $raffle->id, 'number' => '4821', 'status' => 'paid']);
        $this->assertSame('published', $raffle->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'raffle.early_prize_result_published']);
    }

    public function test_an_unsold_number_leaves_the_prize_void(): void
    {
        $raffle = $this->raffle();
        $prize = $this->prize($raffle);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0007', 'status' => 'available']);

        $result = app(PublishEarlyPrizeResultAction::class)->execute($prize, '7');

        $this->assertSame('0007', $result->result_number);
        $this->assertNull($result->winner_purchase_id);
        $this->assertSame(RaffleEarlyPrize::STATUS_VOID, $result->status());
    }

    public function test_it_refuses_while_the_winning_number_has_a_pending_purchase(): void
    {
        $raffle = $this->raffle();
        $prize = $this->prize($raffle);
        $this->sell($raffle, '1234', 'under_review');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('compra pendiente');

        app(PublishEarlyPrizeResultAction::class)->execute($prize, '1234');
    }

    public function test_a_result_cannot_be_published_twice(): void
    {
        $raffle = $this->raffle();
        $prize = $this->prize($raffle);
        app(PublishEarlyPrizeResultAction::class)->execute($prize, '1111');

        $this->expectException(InvalidArgumentException::class);
        app(PublishEarlyPrizeResultAction::class)->execute($prize->fresh(), '2222');
    }

    public function test_early_prizes_are_shown_on_the_raffle_page_and_the_landing(): void
    {
        CompanySetting::query()->create(['trade_name' => 'Rifax', 'whatsapp_bot_phone' => '+573001112244', 'currency_code' => 'COP', 'default_locale' => 'es']);
        $raffle = $this->raffle();
        RaffleNumber::factory()->for($raffle)->create(['number' => '0000', 'status' => 'available']);
        $this->prize($raffle, ['title' => 'Bono de $500.000']);
        $won = $this->prize($raffle, ['title' => 'Celular', 'draw_date' => now()->addDays(3)->toDateString()]);
        $this->sell($raffle, '4821');
        app(PublishEarlyPrizeResultAction::class)->execute($won, '4821');

        $this->get('/rifa/moto-2027')
            ->assertOk()
            ->assertSee('Premios anticipados (2)')
            ->assertSee('Próximo: Bono de $500.000')
            ->assertSee('Además, 2 premios anticipados antes del sorteo')
            ->assertSee('Lotería de Medellín')
            ->assertSee('Ganó el 4821: Ana P. · ****8877')
            ->assertSee('Por jugar');

        $this->get('/')
            ->assertOk()
            ->assertSee('Premios anticipados')
            ->assertSee('Bono de $500.000')
            ->assertSee('Celular');
    }

    public function test_admin_creates_an_early_prize_that_must_play_before_the_main_prize(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $raffle = $this->raffle();

        Livewire::test(EarlyPrizesRelationManager::class, ['ownerRecord' => $raffle, 'pageClass' => EditRaffle::class])
            ->callAction(\Filament\Actions\Testing\TestAction::make(CreateAction::class)->table(), [
                'title' => 'Bono tardío',
                'lottery_name' => 'Lotería de Bogotá',
                'draw_date' => now()->addMonths(2)->toDateString(),
            ])
            ->assertHasFormErrors(['draw_date']);

        Livewire::test(EarlyPrizesRelationManager::class, ['ownerRecord' => $raffle, 'pageClass' => EditRaffle::class])
            ->callAction(\Filament\Actions\Testing\TestAction::make(CreateAction::class)->table(), [
                'title' => 'Bono de $200.000',
                'lottery_name' => 'Lotería de Bogotá',
                'draw_date' => now()->addWeek()->toDateString(),
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('raffle_early_prizes', ['raffle_id' => $raffle->id, 'title' => 'Bono de $200.000']);
    }
}
