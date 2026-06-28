<?php

namespace App\Models;

class PixGatewayConfig extends BaseModel
{
    protected $table = 'pix_gateway_config';

    protected $casts = ['atualizado_em' => 'datetime'];
}
