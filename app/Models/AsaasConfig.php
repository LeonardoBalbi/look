<?php

namespace App\Models;

class AsaasConfig extends BaseModel
{
    protected $table = 'asaas_config';

    protected $casts = ['ativo' => 'boolean', 'atualizado_em' => 'datetime'];
}
