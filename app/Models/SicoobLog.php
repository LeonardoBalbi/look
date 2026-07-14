<?php

namespace App\Models;

class SicoobLog extends BaseModel
{
    protected $table = 'sicoob_logs';

    protected $casts = ['criado_em' => 'datetime'];
}
