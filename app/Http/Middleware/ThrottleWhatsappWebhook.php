<?php

namespace App\Http\Middleware;

use App\Actions\Admin\RecordAdminAuditAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleWhatsappWebhook
{
    public function __construct(
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $maxAttempts = (int) config('services.whatsapp.webhook_rate_limit_max_attempts', 120);
        $decaySeconds = (int) config('services.whatsapp.webhook_rate_limit_decay_seconds', 60);
        $key = $this->rateLimitKey($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            $this->recordRateLimitEvent($request, $maxAttempts, $decaySeconds, $retryAfter, $key);

            return response()->json([
                'message' => 'Too Many Requests',
                'retry_after_seconds' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }

    protected function rateLimitKey(Request $request): string
    {
        return 'whatsapp-webhook:'.$request->ip();
    }

    protected function recordRateLimitEvent(
        Request $request,
        int $maxAttempts,
        int $decaySeconds,
        int $retryAfter,
        string $key,
    ): void {
        $logKey = $key.':audit:rate_limited';

        if (! Cache::add($logKey, true, $decaySeconds)) {
            return;
        }

        $this->recordAdminAuditAction->execute(
            event: 'webhook.rate_limited',
            action: 'rate_limit',
            context: [
                'path' => $request->path(),
                'method' => $request->method(),
                'max_attempts' => $maxAttempts,
                'decay_seconds' => $decaySeconds,
                'retry_after_seconds' => $retryAfter,
            ],
        );
    }
}
