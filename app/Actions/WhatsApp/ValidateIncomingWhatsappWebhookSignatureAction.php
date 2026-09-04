<?php

namespace App\Actions\WhatsApp;

use App\Actions\Admin\RecordAdminAuditAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ValidateIncomingWhatsappWebhookSignatureAction
{
    public function __construct(
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.webhook_app_secret');
        $expectedHeader = (string) config('services.whatsapp.webhook_signature_header', 'X-Hub-Signature-256');
        $signature = trim((string) $request->header($expectedHeader, ''));

        if ($secret === '' || $signature === '' || ! str_starts_with($signature, 'sha256=')) {
            $this->logRejectedAttempt($request, $expectedHeader, 'missing_or_malformed_signature');

            return false;
        }

        $providedHash = substr($signature, strlen('sha256='));

        if ($providedHash === '') {
            $this->logRejectedAttempt($request, $expectedHeader, 'missing_hash_value');

            return false;
        }

        $expectedHash = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedHash, $providedHash)) {
            $this->logRejectedAttempt($request, $expectedHeader, 'signature_mismatch');

            return false;
        }

        return true;
    }

    protected function logRejectedAttempt(Request $request, string $header, string $reason): void
    {
        $signatureHeader = (string) $request->header($header, '');
        $rawContent = (string) $request->getContent();
        $snippet = mb_substr($rawContent, 0, 1500);

        Log::warning('Rejected WhatsApp webhook request due to invalid signature.', [
            'reason' => $reason,
            'expected_header' => $header,
            'provided_header_value' => $signatureHeader,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_length' => strlen($rawContent),
            'content_snippet' => $snippet === '' ? null : $snippet,
            'content_hash_sha256' => $rawContent === '' ? null : hash('sha256', $rawContent),
            'query' => $request->query(),
        ]);

        $cacheKey = 'whatsapp-webhook:signature-rejected:'.$request->ip().':'.$reason;

        if (! Cache::add($cacheKey, true, 60)) {
            return;
        }

        $this->recordAdminAuditAction->execute(
            event: 'webhook.signature_rejected',
            action: 'reject_signature',
            context: [
                'reason' => $reason,
                'path' => $request->path(),
                'method' => $request->method(),
                'expected_header' => $header,
            ],
        );
    }
}
