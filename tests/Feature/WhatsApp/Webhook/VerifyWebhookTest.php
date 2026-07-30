<?php

namespace Tests\Feature\WhatsApp\Webhook;

use Tests\TestCase;

class VerifyWebhookTest extends TestCase
{
    public function test_it_returns_the_challenge_when_the_verify_token_matches(): void
    {
        config()->set('services.whatsapp.webhook_verify_token', 'rifax-test-token');

        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=rifax-test-token&hub_challenge=12345');

        $response->assertOk();
        $response->assertSeeText('12345');
    }

    public function test_it_rejects_the_verification_when_the_token_is_invalid(): void
    {
        config()->set('services.whatsapp.webhook_verify_token', 'rifax-test-token');

        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=invalid-token&hub_challenge=12345');

        $response->assertForbidden();
    }
}
