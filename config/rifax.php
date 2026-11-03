<?php

return [
    'payments' => [
        'review_timeout_hours' => (int) env('PAYMENT_REVIEW_TIMEOUT_HOURS', 48),
    ],
    'purchases' => [
        // Tope de números sin pagar que un cliente puede tener apartados en una
        // misma rifa (evita que una sola persona bloquee todo el talonario).
        // Cada rifa puede definir el suyo; este es el valor por defecto.
        'max_held_numbers_per_customer' => (int) env('RIFAX_MAX_HELD_NUMBERS_PER_CUSTOMER', 100),
    ],
    'handoff' => [
        // Si ningún agente responde en este tiempo, el bot retoma el chat
        // al siguiente mensaje del cliente (evita clientes "atrapados").
        'idle_release_minutes' => (int) env('HANDOFF_IDLE_RELEASE_MINUTES', 720),
    ],
];
