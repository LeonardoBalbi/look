<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'whatsapp' => [
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
    ],
];
