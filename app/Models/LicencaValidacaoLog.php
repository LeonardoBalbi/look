<?php

namespace App\Models;

class LicencaValidacaoLog extends BaseModel
{
    protected $table = 'licenca_validacao_logs';

    protected $casts = [
        'criado_em' => 'datetime',
    ];
}
