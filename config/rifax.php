<?php

return [
    'payments' => [
        'review_timeout_hours' => (int) env('PAYMENT_REVIEW_TIMEOUT_HOURS', 48),
    ],
    'handoff' => [
        // Si ningún agente responde en este tiempo, el bot retoma el chat
        // al siguiente mensaje del cliente (evita clientes "atrapados").
        'idle_release_minutes' => (int) env('HANDOFF_IDLE_RELEASE_MINUTES', 720),
    ],
];
