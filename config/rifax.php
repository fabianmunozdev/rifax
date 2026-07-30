<?php

return [
    'payments' => [
        'review_timeout_hours' => (int) env('PAYMENT_REVIEW_TIMEOUT_HOURS', 48),
    ],
];
