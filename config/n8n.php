<?php

return [
    'enabled' => env('N8N_AUTOMATIONS_ENABLED', false),
    'token' => env('N8N_API_TOKEN'),
    'max_attempts' => (int) env('N8N_MAX_ATTEMPTS', 3),
    'payment_lookback_days' => (int) env('N8N_PAYMENT_LOOKBACK_DAYS', 7),
];
