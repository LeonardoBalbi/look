<?php

namespace App\Models;

class ItauConfig extends BaseModel
{
    protected $table = 'itau_config';

    protected $casts = [
        'ativo' => 'boolean',
        'token_expires_at' => 'datetime',
        'atualizado_em' => 'datetime',
    ];
}
