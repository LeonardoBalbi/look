<?php

return [
    'asaas' => ['webhook_token' => env('ASAAS_WEBHOOK_TOKEN')],
    'sicoob' => ['webhook_token' => env('SICOOB_WEBHOOK_TOKEN')],
    'itau' => ['webhook_token' => env('ITAU_WEBHOOK_TOKEN')],
    'license_payments' => [
        'webhook_token' => env('LICENSE_PAYMENT_WEBHOOK_TOKEN'),
    ],
    'whatsapp' => [
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'evolution_timeout' => (int) env('WHATSAPP_EVOLUTION_TIMEOUT', 15),
    ],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
];
