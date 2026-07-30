<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function postSignedWhatsappWebhook(array $payload): TestResponse
    {
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw new \RuntimeException('Unable to encode WhatsApp webhook payload for testing.');
        }

        $signature = hash_hmac(
            'sha256',
            $content,
            (string) config('services.whatsapp.webhook_app_secret'),
        );

        return $this->call(
            method: 'POST',
            uri: '/api/webhooks/whatsapp',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$signature,
            ],
            content: $content,
        );
    }
}
