<?php

namespace Tests\Feature\Security;

use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Regresiones de la auditoría de seguridad (run-1).
 */
class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_payment_proofs_use_the_private_disk_and_an_unpredictable_name(): void
    {
        $path = PaymentProof::newStoragePath(42);

        $this->assertSame('local', PaymentProof::STORAGE_DISK);
        $this->assertMatchesRegularExpression('#^whatsapp-proofs/42-[A-Za-z0-9]{32}\.jpg$#', $path);
        $this->assertNotSame($path, PaymentProof::newStoragePath(42));
    }

    public function test_payment_proof_route_requires_a_panel_user_with_payment_access(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('whatsapp-proofs/1-abc.jpg', 'fake-image');

        $proof = PaymentProof::query()->create([
            'payment_id' => Payment::factory()->create()->id,
            'storage_disk' => 'local',
            'storage_path' => 'whatsapp-proofs/1-abc.jpg',
            'uploaded_at' => now(),
        ]);

        $url = route('admin.payment-proofs.show', $proof);

        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs(User::factory()->create(['role' => null]))->get($url)->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get($url)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_meta_webhook_rejects_everything_when_the_app_secret_is_not_configured(): void
    {
        config()->set('services.whatsapp.webhook_app_secret', '');
        config()->set('services.whatsapp.webhook_verify_token', '');

        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'rifax-test-app-secret'),
        ], $body)->assertForbidden();

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=&hub_challenge=1')->assertForbidden();
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=rifax-test-token&hub_challenge=1')->assertForbidden();
    }

    public function test_a_customer_cannot_hold_more_unpaid_numbers_than_the_raffle_maximum(): void
    {
        $raffle = Raffle::factory()->published()->create(['max_numbers_per_customer' => 3]);
        foreach (['0001', '0002', '0003', '0004'] as $number) {
            RaffleNumber::factory()->for($raffle)->create(['number' => $number, 'status' => 'available']);
        }
        $customer = Customer::factory()->create();

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002', '0003']);
        $this->assertCount(3, $purchase->numbers);

        $this->expectException(InvalidArgumentException::class);
        app(ReserveNumbersAction::class)->execute(Customer::factory()->create(), $raffle->fresh(), ['0001', '0002', '0003', '0004']);
    }

    public function test_the_global_default_maximum_applies_when_the_raffle_has_none(): void
    {
        config()->set('rifax.purchases.max_held_numbers_per_customer', 2);

        $raffle = Raffle::factory()->published()->create(['max_numbers_per_customer' => null]);

        $this->assertSame(2, $raffle->maxHeldNumbersPerCustomer());
    }
}
