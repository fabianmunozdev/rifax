<?php

namespace Database\Seeders;

use App\Enums\PanelRole;
use App\Models\CompanySetting;
use App\Models\ContentEntry;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@rifax.test',
        ], [
            'name' => 'Rifax Admin',
            'password' => Hash::make('password'),
            'role' => PanelRole::Admin->value,
            'is_active' => true,
            'preferred_locale' => 'es',
        ]);

        User::query()->updateOrCreate([
            'email' => 'operator@rifax.test',
        ], [
            'name' => 'Rifax Operator',
            'password' => Hash::make('password'),
            'role' => PanelRole::Operator->value,
            'is_active' => true,
            'preferred_locale' => 'es',
        ]);

        User::query()->updateOrCreate([
            'email' => 'finance@rifax.test',
        ], [
            'name' => 'Rifax Finance',
            'password' => Hash::make('password'),
            'role' => PanelRole::Finance->value,
            'is_active' => true,
            'preferred_locale' => 'es',
        ]);

        User::query()->updateOrCreate([
            'email' => 'support@rifax.test',
        ], [
            'name' => 'Rifax Support',
            'password' => Hash::make('password'),
            'role' => PanelRole::Support->value,
            'is_active' => true,
            'preferred_locale' => 'es',
        ]);

        CompanySetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'whatsapp_bot_phone' => '+573009876543',
            'support_phone' => '+573001234567',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Escribe MENU para volver al inicio o pide ayuda si necesitas soporte humano.',
        ]);

        PaymentMethod::query()->updateOrCreate([
            'slug' => 'bank_transfer',
        ], [
            'name' => 'Transferencia bancaria',
            'status' => 'active',
            'instructions' => 'Realiza la transferencia y envia el comprobante por este mismo chat.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => 'Cuenta corriente 123456789',
            'details_json' => [
                'bank_name' => 'Banco Demo',
                'account_type' => 'corriente',
                'account_number' => '123456789',
            ],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        PaymentMethod::query()->updateOrCreate([
            'slug' => 'nequi',
        ], [
            'name' => 'Nequi',
            'status' => 'active',
            'instructions' => 'Envia el pago a la linea indicada y comparte el comprobante por WhatsApp.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '3001234567',
            'details_json' => [
                'wallet' => 'Nequi',
                'phone' => '3001234567',
            ],
            'sort_order' => 20,
            'is_visible' => true,
        ]);

        $raffle = Raffle::query()->updateOrCreate([
            'slug' => 'rifa-demo',
        ], [
            'title' => 'Rifa Demo Rifax',
            'description' => 'Rifa base para desarrollo y pruebas locales del MVP.',
            'status' => 'published',
            'min_numbers_per_purchase' => 1,
            'lottery_name' => 'Loteria de Bogota',
            'lottery_text' => 'Loteria',
            'lottery_draw_number' => '0001',
            'draw_date' => now()->addDays(15)->toDateString(),
            'draw_time' => '21:30:00',
            'lottery_reference_url' => 'https://www.loteriadebogota.com/',
            'price_per_number' => 10000,
            'reservation_timeout_minutes' => 15,
        ]);

        foreach (range(1, 100) as $number) {
            RaffleNumber::query()->updateOrCreate([
                'raffle_id' => $raffle->id,
                'number' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ], [
                'status' => 'available',
                'reserved_until' => null,
            ]);
        }

        $customer = Customer::query()->updateOrCreate([
            'phone' => '+573009999999',
        ], [
            'name' => 'Cliente Demo',
            'wa_id' => '573009999999',
            'last_interaction_at' => now(),
        ]);

        ConversationState::query()->updateOrCreate([
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
        ], [
            'status' => 'main_menu',
            'current_raffle_id' => $raffle->id,
            'last_user_message_at' => now(),
            'last_bot_message_at' => now(),
            'metadata_json' => [
                'seeded' => true,
            ],
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'system.menu.welcome',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'system_message',
            'title' => 'Bienvenida principal',
            'category' => 'purchase_flow',
            'status' => 'published',
            'trigger_intent' => 'main_menu',
            'body_text' => 'Bienvenido a Rifax. Responde con la opcion que necesitas o escribe MENU para volver aqui.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 10,
            'is_ai_eligible' => false,
            'notes' => 'Mensaje principal del menu inicial.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.payment.methods',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Metodos de pago',
            'category' => 'payments',
            'status' => 'published',
            'trigger_intent' => 'payment_methods',
            'body_text' => 'Puedes pagar usando los metodos habilitados por la empresa. Te enviaremos las instrucciones exactas dentro del flujo de compra.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 100,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => 'Respuesta FAQ fija para metodos de pago.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.draw.date',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_parametrized',
            'title' => 'Fecha del sorteo',
            'category' => 'draws',
            'status' => 'published',
            'trigger_intent' => 'draw_date',
            'body_text' => 'El sorteo de {raffle_title} se realiza el {draw_date} a las {draw_time} usando como referencia {lottery_name} #{lottery_draw_number}.',
            'variables_json' => ['raffle_title', 'draw_date', 'draw_time', 'lottery_name', 'lottery_draw_number'],
            'fallback_text' => 'Ahora mismo no tengo disponible la fecha exacta del sorteo. Escribe MENU o solicita ayuda.',
            'priority' => 100,
            'is_ai_eligible' => false,
            'notes' => 'Respuesta parametrizada del sorteo.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.terms.conditions',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Condiciones de la rifa',
            'category' => 'purchase_flow',
            'status' => 'published',
            'trigger_intent' => 'terms_conditions',
            'body_text' => 'Estas son las condiciones principales de la rifa:'.PHP_EOL
                .'- La compra se realiza por este chat.'.PHP_EOL
                .'- Los numeros se reservan por tiempo limitado.'.PHP_EOL
                .'- El pago se confirma manualmente.'.PHP_EOL
                .'- El boleto se envia cuando el pago es aprobado.'.PHP_EOL.PHP_EOL
                .'Si deseas comprar, responde 1. Si deseas volver al menu, escribe MENU.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 90,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => 'Condiciones base del flujo MVP.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.public.official.result.source',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Quien publica el numero ganador?',
            'category' => 'draws',
            'status' => 'published',
            'trigger_intent' => null,
            'body_text' => 'El numero ganador oficial lo publica la loteria externa correspondiente. Rifax toma ese resultado oficial para identificar al ganador dentro de la plataforma y comunicarlo por WhatsApp.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 20,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => 'FAQ publica sobre la fuente oficial del numero ganador.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.public.draw.cutoff',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Que pasa si pago o envio el comprobante despues de la hora del sorteo?',
            'category' => 'payments',
            'status' => 'published',
            'trigger_intent' => null,
            'body_text' => 'Para participar, el comprobante debe enviarse antes de la hora del sorteo. Cuando llega la hora programada, la rifa deja de aceptar nuevas compras, reservas o comprobantes tardios.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 21,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => 'FAQ publica sobre cierre comercial y comprobantes fuera de tiempo.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.help.support',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Ayuda y soporte',
            'category' => 'support',
            'status' => 'published',
            'trigger_intent' => 'help_support',
            'body_text' => 'Puedo ayudarte con:'.PHP_EOL
                .'1. Condiciones de la rifa'.PHP_EOL
                .'2. Metodos de pago'.PHP_EOL
                .'3. Estado de tu compra'.PHP_EOL
                .'4. Hablar con soporte',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 90,
            'is_ai_eligible' => false,
            'is_public' => true,
            'notes' => 'Ayuda base del canal WhatsApp.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'faq.upcoming.raffles',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'faq_fixed',
            'title' => 'Proximas rifas',
            'category' => 'raffles',
            'status' => 'published',
            'trigger_intent' => 'upcoming_raffles',
            'body_text' => 'Pronto compartiremos las proximas rifas disponibles. Escribe MENU para volver.',
            'variables_json' => [],
            'fallback_text' => null,
            'priority' => 80,
            'is_ai_eligible' => false,
            'notes' => 'Placeholder FAQ para proximas rifas.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'template.payment.approved.ticket',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'template_bridge',
            'title' => 'Pago aprobado fuera de ventana',
            'category' => 'payments',
            'status' => 'published',
            'trigger_intent' => 'payment_approved_ticket',
            'body_text' => 'Hola {customer_name}, tu pago para {raffle_title} fue aprobado.'.PHP_EOL.PHP_EOL.'Tu boleto esta disponible con codigo {ticket_code}.'.PHP_EOL.'Accede aqui: {ticket_url}',
            'variables_json' => [
                'template_name' => 'payment_approved_ticket',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'ticket_code', 'ticket_url'],
            ],
            'fallback_text' => 'Tu pago fue aprobado y pronto recibiras novedades por este medio.',
            'priority' => 120,
            'is_ai_eligible' => false,
            'notes' => 'Bridge inicial para salida por plantilla mientras se implementa la entrega del boleto real.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'template.raffle.winner.notification',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'template_bridge',
            'title' => 'Ganador de rifa fuera de ventana',
            'category' => 'raffles',
            'status' => 'published',
            'trigger_intent' => 'raffle_winner_notification',
            'body_text' => 'Hola {customer_name}, tu numero {winning_number} fue ganador en {raffle_title}.'.PHP_EOL.PHP_EOL.'Boleto: {ticket_code}'.PHP_EOL.'Consulta tu enlace: {ticket_url}',
            'variables_json' => [
                'template_name' => 'raffle_winner_notification',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'winning_number', 'ticket_code', 'ticket_url'],
            ],
            'fallback_text' => 'Tu numero fue reportado como ganador. Un asesor te compartira los siguientes pasos por este medio.',
            'priority' => 130,
            'is_ai_eligible' => false,
            'notes' => 'Bridge de notificacion al ganador cuando la conversacion ya salio de la ventana de 24 horas.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'template.purchase.payment.reminder',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'template_bridge',
            'title' => 'Recordatorio de pago pendiente',
            'category' => 'campaigns',
            'status' => 'published',
            'trigger_intent' => 'purchase_payment_reminder',
            'body_text' => 'Hola {customer_name}, tu compra para {raffle_title} sigue pendiente. Comparte tu comprobante antes de {reservation_expires_at} para conservar tus numeros.',
            'variables_json' => [
                'template_name' => 'purchase_payment_reminder',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'reservation_expires_at'],
            ],
            'fallback_text' => 'Tu compra sigue pendiente de pago. Comparte tu comprobante por este chat para continuar.',
            'priority' => 140,
            'is_ai_eligible' => false,
            'notes' => 'Campana operativa inicial para compras reservadas o rechazadas.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'template.raffle.draw.reminder',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'template_bridge',
            'title' => 'Recordatorio de sorteo',
            'category' => 'campaigns',
            'status' => 'published',
            'trigger_intent' => 'raffle_draw_reminder',
            'body_text' => 'Hola {customer_name}, te recordamos que {raffle_title} se juega el {draw_date} a las {draw_time}. Conserva tu boleto {ticket_code}.',
            'variables_json' => [
                'template_name' => 'raffle_draw_reminder',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'draw_date', 'draw_time', 'ticket_code'],
            ],
            'fallback_text' => 'Te recordamos que tu rifa se jugara pronto. Conserva tu boleto y sigue el resultado oficial.',
            'priority' => 140,
            'is_ai_eligible' => false,
            'notes' => 'Campana operativa inicial para avisar sorteos proximos a compradores pagados.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        ContentEntry::query()->updateOrCreate([
            'key' => 'template.upcoming.raffle.announcement',
            'locale' => 'es',
            'channel' => 'whatsapp',
        ], [
            'type' => 'template_bridge',
            'title' => 'Anuncio de rifa disponible',
            'category' => 'campaigns',
            'status' => 'published',
            'trigger_intent' => 'upcoming_raffle_announcement',
            'body_text' => 'Hola {customer_name}, ya esta disponible {raffle_title}. El sorteo sera el {draw_date} a las {draw_time}. Responde MENU para iniciar tu compra.',
            'variables_json' => [
                'template_name' => 'upcoming_raffle_announcement',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'draw_date', 'draw_time'],
            ],
            'fallback_text' => 'Ya esta disponible una nueva rifa y puedes iniciar tu compra por este chat.',
            'priority' => 140,
            'is_ai_eligible' => false,
            'notes' => 'Campana operativa inicial para reactivar clientes existentes con nuevas rifas.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);
    }
}
