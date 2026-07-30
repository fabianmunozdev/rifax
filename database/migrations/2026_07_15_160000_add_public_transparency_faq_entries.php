<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $entries = [
            [
                'key' => 'faq.public.official.result.source',
                'title' => 'Quien publica el numero ganador?',
                'category' => 'draws',
                'body_text' => 'El numero ganador oficial lo publica la loteria externa correspondiente. Rifax toma ese resultado oficial para identificar al ganador dentro de la plataforma y comunicarlo por WhatsApp.',
                'priority' => 20,
                'notes' => 'FAQ publica sobre la fuente oficial del numero ganador.',
            ],
            [
                'key' => 'faq.public.draw.cutoff',
                'title' => 'Que pasa si pago o envio el comprobante despues de la hora del sorteo?',
                'category' => 'payments',
                'body_text' => 'Para participar, el comprobante debe enviarse antes de la hora del sorteo. Cuando llega la hora programada, la rifa deja de aceptar nuevas compras, reservas o comprobantes tardios.',
                'priority' => 21,
                'notes' => 'FAQ publica sobre cierre comercial y comprobantes fuera de tiempo.',
            ],
        ];

        foreach ($entries as $entry) {
            DB::table('content_entries')->updateOrInsert(
                [
                    'key' => $entry['key'],
                    'locale' => 'es',
                    'channel' => 'whatsapp',
                ],
                [
                    'type' => 'faq_fixed',
                    'title' => $entry['title'],
                    'category' => $entry['category'],
                    'status' => 'published',
                    'trigger_intent' => null,
                    'body_text' => $entry['body_text'],
                    'variables_json' => json_encode([]),
                    'fallback_text' => null,
                    'priority' => $entry['priority'],
                    'is_ai_eligible' => false,
                    'is_public' => true,
                    'notes' => $entry['notes'],
                    'published_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('content_entries')
            ->whereIn('key', [
                'faq.public.official.result.source',
                'faq.public.draw.cutoff',
            ])
            ->where('locale', 'es')
            ->where('channel', 'whatsapp')
            ->delete();
    }
};
