<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Str;

final class PickerAuthToken
{
    public const DEFAULT_TTL_MINUTES = 10;

    /**
     * @return non-empty-string
     */
    public static function generate(Customer $customer, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): string
    {
        if ($ttlMinutes < 1) {
            $ttlMinutes = self::DEFAULT_TTL_MINUTES;
        }

        $expiresAt = time() + ($ttlMinutes * 60);
        $nonce = Str::random(12);
        $payload = $customer->getKey().'|'.$expiresAt.'|'.$nonce;
        $encoded = self::base64UrlEncode($payload);
        $signature = self::base64UrlEncode((string) hash_hmac('sha256', $encoded, (string) config('app.key'), true));

        return $encoded.'.'.$signature;
    }

    public static function verify(string $token): ?Customer
    {
        $token = trim($token);
        if ($token === '' || ! str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token, 2) + [null, null];
        if (! is_string($encoded) || ! is_string($signature) || $encoded === '' || $signature === '') {
            return null;
        }

        $expected = self::base64UrlEncode((string) hash_hmac('sha256', $encoded, (string) config('app.key'), true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = self::base64UrlDecode($encoded);
        if ($payload === '' || ! str_contains($payload, '|')) {
            return null;
        }

        [$customerId, $expiresAt, $nonce] = array_pad(explode('|', $payload, 3), 3, null);

        if (! ctype_digit((string) $customerId) || ! ctype_digit((string) $expiresAt) || ! is_string($nonce) || $nonce === '') {
            return null;
        }

        if ((int) $expiresAt < time()) {
            return null;
        }

        $customer = Customer::query()->find((int) $customerId);
        if (! $customer instanceof Customer) {
            return null;
        }

        return $customer;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod !== 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }

        $decoded = base64_decode($padded, true);

        return is_string($decoded) ? $decoded : '';
    }
}
