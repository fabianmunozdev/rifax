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
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'rifax-test-token'),
        'webhook_app_secret' => env('WHATSAPP_WEBHOOK_APP_SECRET', 'rifax-test-app-secret'),
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

];
