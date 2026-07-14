<?php

namespace App\Models;

class SicoobConfig extends BaseModel
{
    protected $table = 'sicoob_config';

    protected $casts = [
        'ativo' => 'boolean',
        'token_expires_at' => 'datetime',
        'atualizado_em' => 'datetime',
    ];
}
