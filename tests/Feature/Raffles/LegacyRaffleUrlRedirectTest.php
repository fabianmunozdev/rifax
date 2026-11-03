<?php

namespace Tests\Feature\Raffles;

use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRaffleUrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_english_urls_redirect_permanently_to_spanish_urls(): void
    {
        Raffle::factory()->published()->create(['slug' => 'rifa-moto']);

        $this->get('/raffles/rifa-moto/number-picker?quantity=2&utm_source=meta')
            ->assertStatus(301)
            ->assertRedirect('/rifa/rifa-moto?quantity=2&utm_source=meta');

        $this->get('/tickets/abc123')
            ->assertStatus(301)
            ->assertRedirect('/boleto/abc123');

        $this->get('/rifa/rifa-moto')->assertOk();
    }
}
