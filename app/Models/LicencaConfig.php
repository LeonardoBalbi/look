<?php

namespace App\Models;

use Illuminate\Support\Str;

class LicencaConfig extends BaseModel
{
    protected $table = 'licenca_config';

    protected $casts = [
        'ativo' => 'boolean',
        'licenca_chave' => 'encrypted',
        'modulos_json' => 'array',
        'ultimo_payload' => 'array',
        'vence_em' => 'date',
        'ultima_validacao_em' => 'datetime',
        'ultima_validacao_ok_em' => 'datetime',
        'proxima_validacao_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public static function atual(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'local',
                'status' => 'local',
                'instancia_id' => (string) Str::uuid(),
                'tolerancia_offline_dias' => 7,
                'ativo' => true,
            ]
        );
    }
}
