<?php

namespace App\Models;

class PagbankConfig extends BaseModel
{
    protected $table = 'pagbank_config';

    protected $casts = ['ativo' => 'boolean', 'atualizado_em' => 'datetime'];
}
