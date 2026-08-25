<?php

$stores = collect(explode(',', (string) env('LOCX_PUBLIC_STORES', 'barra|Barra da Tijuca')))
    ->map(function (string $store): ?array {
        [$slug, $name] = array_pad(explode('|', $store, 2), 2, null);
        $slug = strtolower(trim((string) $slug));
        $name = trim((string) $name);

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return ['slug' => $slug, 'name' => $name !== '' ? $name : ucfirst($slug)];
    })
    ->filter()
    ->values()
    ->all();

return [
    'base_domain' => env('LOCX_BASE_DOMAIN', 'locx.com.br'),
    'site_url' => rtrim((string) env('LOCX_SITE_URL', 'https://locx.com.br'), '/'),
    'admin_url' => rtrim((string) env('LOCX_ADMIN_URL', 'https://admin.locx.com.br'), '/'),
    'stores' => $stores,
];
