<?php

use App\Models\Cliente;
use App\Models\LicencaPortalCliente;
use App\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'cliente' => [
            'driver' => 'session',
            'provider' => 'clientes',
        ],
        'licenca_cliente' => [
            'driver' => 'session',
            'provider' => 'licenca_clientes',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        'clientes' => [
            'driver' => 'eloquent',
            'model' => Cliente::class,
        ],
        'licenca_clientes' => [
            'driver' => 'eloquent',
            'model' => LicencaPortalCliente::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'licenca_clientes' => [
            'provider' => 'licenca_clientes',
            'table' => 'licenca_cliente_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
