<?php

namespace App\Actions\WhatsApp\Bot;

use Illuminate\Support\Str;

/**
 * Detección de comandos y palabras clave del cliente (MENU, ASESOR, CANCELAR...).
 */
class BotKeywords
{
    public function expandButtonPayloadToText(string $payload): string
    {
        $payload = trim($payload);

        if ($payload === '') {
            return '';
        }

        $alias = [
            'main_menu' => 'MENU',
            'cancel_purchase' => 'CANCELAR',
            'buy_now' => '1',
            'menu_1' => '1',
            'menu_2' => '2',
            'menu_3' => '3',
            'menu_4' => '4',
            'menu_5' => '5',
            'menu_6' => '6',
            'menu_7' => '7',
            'replace_proof' => 'REEMPLAZAR',
            'expired_new_purchase' => '1',
            'expired_menu' => 'MENU',
            'raffle_continue' => '1',
            'raffle_back' => 'MENU',
            'payment_menu' => 'MENU',
            'payment_help' => '7',
            'under_review_replace' => 'REEMPLAZAR',
            'under_review_menu' => 'MENU',
            'privacy_accept' => 'ACEPTO',
            'privacy_reject' => 'NO ACEPTO',
            'mode_manual' => '1',
            'mode_random' => '2',
        ];

        if (isset($alias[$payload])) {
            return $alias[$payload];
        }

        if (Str::startsWith($payload, 'paid_menu')) {
            return 'MENU';
        }

        if (Str::startsWith($payload, 'menu_')) {
            $digit = substr($payload, 5);
            if (ctype_digit($digit)) {
                return $digit;
            }
        }

        if (Str::startsWith($payload, 'raffle_')) {
            $rest = substr($payload, 7);
            if (ctype_digit($rest)) {
                return $rest;
            }
        }

        if (Str::startsWith($payload, 'view_ticket:')) {
            return 'MENU';
        }

        if (Str::startsWith($payload, 'ticket_web:')) {
            return '3';
        }

        return $payload;
    }

    public function extractPickerIntentToken(string $text): ?string
    {
        if (! preg_match('/(?:^|[\s:>-])PICKER\s+([A-Z0-9]+)(?:$|\b)/', $text, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    public function normalizeKeywordText(string $text): string
    {
        $normalized = Str::upper(trim($text));

        if (function_exists('normalizer_normalize')) {
            $decomposed = normalizer_normalize($normalized, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $normalized = (string) preg_replace('/\p{Mn}/u', '', $decomposed);
            }
        }

        $withoutPunct = (string) preg_replace('/[^A-Z0-9Ñ\s]+/u', ' ', $normalized);
        $withoutPunct = (string) preg_replace('/\s+/u', ' ', $withoutPunct);

        return trim($withoutPunct);
    }

    /**
     * @param  list<string>  $keywords
     */
    public function containsAnyKeyword(string $normText, array $keywords): bool
    {
        if ($normText === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (str_contains($normText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function isMenuCommand(string $normText): bool
    {
        return $normText === 'MENU'
            || $this->containsAnyKeyword($normText, ['MENU', 'VOLVER AL MENU', 'INICIO', 'REGRESAR AL INICIO']);
    }

    public function isHumanAgentRequest(string $normText): bool
    {
        return $this->containsAnyKeyword($normText, [
            'ASESOR', 'AGENTE', 'HUMANO', 'PERSONA REAL', 'HABLAR CON ALGUIEN',
            'HABLAR CON UNA PERSONA', 'HABLAR CON SOPORTE', 'OPERADOR',
        ]);
    }

    public function isCancelCommand(string $normText): bool
    {
        return $normText === 'CANCELAR'
            || $this->containsAnyKeyword($normText, [
                'CANCELAR', 'CANCELA', 'CANCELO', 'ANULAR', 'ANULA', 'ANULO',
                'CANCELAR COMPRA', 'ANULAR COMPRA', 'CANCELAR RESERVA', 'LIBERAR NUMEROS',
            ]);
    }

    public function isReplaceCommand(string $normText): bool
    {
        return $normText === 'REEMPLAZAR'
            || $this->containsAnyKeyword($normText, [
                'REEMPLAZAR', 'REEMPLAZA', 'REEMPLAZO', 'CAMBIAR COMPROBANTE', 'CAMBIO COMPROBANTE',
                'NUEVO COMPROBANTE', 'OTRO COMPROBANTE', 'CORREGIR COMPROBANTE',
            ]);
    }

    public function isFaqShortcut(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        return $this->containsAnyKeyword($normText, ['AYUDA', 'CONDICIONES', 'PAGOS', 'METODOS DE PAGO', 'SORTEO']);
    }

    public function isGreeting(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        return $this->containsAnyKeyword($normText, [
            'HOLA', 'BUENAS', 'BUEN DIA', 'BUENOS DIAS', 'BUENAS TARDES', 'BUENAS NOCHES', 'HELLO', 'QUE TAL',
        ]);
    }

    public function isRepurchaseShortcut(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        if ($normText === '1') {
            return true;
        }

        return $this->containsAnyKeyword($normText, [
            'COMPRAR', 'QUIERO COMPRAR', 'COMPRAR DE NUEVO', 'VOLVER A COMPRAR',
            'NUEVA COMPRA', 'OTRA VEZ', 'OTRA COMPRA', 'PARTICIPAR DE NUEVO', 'QUIERO OTRO',
        ]);
    }
}
