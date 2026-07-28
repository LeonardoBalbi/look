<?php

namespace App\Models;

class ItauLog extends BaseModel
{
    protected $table = 'itau_logs';

    protected $casts = [
        'criado_em' => 'datetime',
    ];
}
