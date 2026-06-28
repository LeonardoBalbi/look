<?php

namespace App\Models;

class AsaasLog extends BaseModel
{
    protected $table = 'asaas_logs';

    protected $casts = ['criado_em' => 'datetime'];
}
