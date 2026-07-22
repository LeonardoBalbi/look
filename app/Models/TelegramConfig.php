<?php

namespace App\Models;

class TelegramConfig extends BaseModel
{
    protected $table = 'telegram_config';

    protected $casts = [
        'ativo' => 'boolean',
        'atualizado_em' => 'datetime',
        'criado_em' => 'datetime',
    ];
}
