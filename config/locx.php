<?php

return [
    'license_enabled' => env('LOCX_LICENSE_ENABLED', false),
    'recorrencia' => [
        'ativa' => env('LOCX_RECORRENCIA_ATIVA', false),
        'gerar_pix' => env('LOCX_RECORRENCIA_GERAR_PIX', false),
        'enviar_whatsapp' => env('LOCX_RECORRENCIA_ENVIAR_WHATSAPP', false),
        'dias_antecedencia' => (int) env('LOCX_RECORRENCIA_DIAS_ANTECEDENCIA', 0),
        'max_por_contrato' => (int) env('LOCX_RECORRENCIA_MAX_POR_CONTRATO', 12),
        'horario' => env('LOCX_RECORRENCIA_HORARIO', '07:00'),
    ],
];
