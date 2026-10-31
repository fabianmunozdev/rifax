<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;

/**
 * Valida X-Twilio-Signature: base64(HMAC-SHA1(url + params ordenados, auth_token)).
 *
 * La URL debe ser exactamente la que Twilio llamó. Detrás de un proxy, si
 * la URL de Laravel no coincide, se puede fijar la base con TWILIO_WEBHOOK_BASE_URL.
 */
class TwilioWebhookSignature
{
    public function isValid(Request $request): bool
    {
        if (! config('services.twilio.validate_signature', true)) {
            return true;
        }

        $token = (string) config('services.twilio.auth_token');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        if ($token === '' || $signature === '') {
            return false;
        }

        $expected = self::sign($token, $this->publicUrl($request), $request->post());

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function sign(string $authToken, string $url, array $params): string
    {
        ksort($params);

        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.(is_array($value) ? implode('', $value) : $value);
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    protected function publicUrl(Request $request): string
    {
        $base = rtrim((string) config('services.twilio.webhook_base_url'), '/');

        if ($base === '') {
            return $request->fullUrl();
        }

        $query = $request->getQueryString();

        return $base.'/'.ltrim($request->path(), '/').($query ? '?'.$query : '');
    }
}
