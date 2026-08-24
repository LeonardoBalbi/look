<?php

return [
    'license_enabled' => env('RENTAL_LICENSE_ENABLED', false),
    'licenca' => [
        'timeout' => (int) env('RENTAL_LICENCA_TIMEOUT', 10),
        'cache_horas' => (int) env('RENTAL_LICENCA_CACHE_HORAS', 12),
    ],
    'recorrencia' => [
        'ativa' => env('RENTAL_RECORRENCIA_ATIVA', true),
        'gerar_pix' => env('RENTAL_RECORRENCIA_GERAR_PIX', false),
        'enviar_whatsapp' => env('RENTAL_RECORRENCIA_ENVIAR_WHATSAPP', false),
        'enviar_email' => env('RENTAL_RECORRENCIA_ENVIAR_EMAIL', false),
        'enviar_telegram' => env('RENTAL_RECORRENCIA_ENVIAR_TELEGRAM', false),
        'dias_antecedencia' => (int) env('RENTAL_RECORRENCIA_DIAS_ANTECEDENCIA', 0),
        'max_por_contrato' => (int) env('RENTAL_RECORRENCIA_MAX_POR_CONTRATO', 12),
        'horario' => env('RENTAL_RECORRENCIA_HORARIO', '07:00'),
    ],
    'telegram' => [
        'espelhar_automacoes_whatsapp' => env('RENTAL_TELEGRAM_ESPELHAR_AUTOMACOES_WHATSAPP', true),
    ],
    'crm' => [
        'automacoes_ativas' => env('RENTAL_CRM_AUTOMACOES_ATIVAS', true),
        'automacoes_horario' => env('RENTAL_CRM_AUTOMACOES_HORARIO', '07:15'),
    ],
    'pix' => [
        'conciliacao_ativa' => env('RENTAL_PIX_CONCILIACAO_ATIVA', true),
        'conciliacao_limite' => (int) env('RENTAL_PIX_CONCILIACAO_LIMITE', 50),
    ],
    'gateway_verify_ssl' => env('RENTAL_GATEWAY_VERIFY_SSL', true),
];
