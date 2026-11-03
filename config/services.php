<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        // Proveedor de transporte: 'meta' (Cloud API directa) o 'twilio'.
        'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        // Sin valor por defecto: si faltan, el webhook de Meta rechaza todo (falla cerrado).
        'webhook_verify_token' => (string) env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        'webhook_app_secret' => (string) env('WHATSAPP_WEBHOOK_APP_SECRET', ''),
        'webhook_signature_header' => env('WHATSAPP_WEBHOOK_SIGNATURE_HEADER', 'X-Hub-Signature-256'),
        'webhook_rate_limit_max_attempts' => (int) env('WHATSAPP_WEBHOOK_RATE_LIMIT_MAX_ATTEMPTS', 120),
        'webhook_rate_limit_decay_seconds' => (int) env('WHATSAPP_WEBHOOK_RATE_LIMIT_DECAY_SECONDS', 60),
        'send_enabled' => env('WHATSAPP_SEND_ENABLED', false),
        'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT_SECONDS', 10),
        'retry_attempts' => (int) env('WHATSAPP_RETRY_ATTEMPTS', 3),
        'retry_backoff_seconds' => (int) env('WHATSAPP_RETRY_BACKOFF_SECONDS', 60),
        'default_template_language' => env('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'es_CO'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        // Opcional: API Key en lugar del Auth Token para llamadas salientes.
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        // Número remitente de WhatsApp (E.164) o un Messaging Service.
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'status_callback_url' => env('TWILIO_STATUS_CALLBACK_URL'),
        'webhook_base_url' => env('TWILIO_WEBHOOK_BASE_URL'),
        'validate_signature' => (bool) env('TWILIO_VALIDATE_SIGNATURE', true),
        // {"nombre_plantilla_meta":"HXxxxxxxxx"} para plantillas aprobadas.
        'content_sid_map' => json_decode((string) env('TWILIO_CONTENT_SID_MAP', '{}'), true) ?: [],
        'content_language' => env('TWILIO_CONTENT_LANGUAGE', 'es'),
        'api_base_url' => env('TWILIO_API_BASE_URL', 'https://api.twilio.com'),
        'content_api_base_url' => env('TWILIO_CONTENT_API_BASE_URL', 'https://content.twilio.com'),
    ],

];
