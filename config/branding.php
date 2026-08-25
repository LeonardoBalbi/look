<?php

return [
    /* Nome comercial do produto de software. Não deve ser o nome da locadora. */
    'product_name' => env('APP_NAME', 'Gestor de Locações'),

    /* Identidade da empresa que utiliza esta instalação. */
    'store_name' => env('STORE_NAME', 'LocX Aluguel de Motos'),
    'store_legal_name' => env('STORE_LEGAL_NAME'),
    'store_document' => env('STORE_DOCUMENT'),
    'use_locx_logo' => env('BRAND_USE_LOCX_LOGO', false),

    /* Contatos exibidos na documentação e nas telas de ajuda. */
    'support_email' => env('SUPPORT_EMAIL'),
    'support_phone' => env('SUPPORT_PHONE'),

    /* Identificadores técnicos neutros para integrações externas. */
    'integration_prefix' => env('INTEGRATION_PREFIX', 'RENTAL'),
    'license_prefix' => env('LICENSE_PREFIX', 'RENTAL'),
    'product_id' => env('PRODUCT_ID', 'rental-management'),
    'merchant_reference' => env('MERCHANT_REFERENCE', 'RENTAL'),
];
