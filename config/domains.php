<?php

return [
    'base_domain' => env('LOCX_BASE_DOMAIN', 'locx.com.br'),
    'site_url' => rtrim((string) env('LOCX_SITE_URL', 'https://locx.com.br'), '/'),
    'admin_url' => rtrim((string) env('LOCX_ADMIN_URL', 'https://admin.locx.com.br'), '/'),
];
