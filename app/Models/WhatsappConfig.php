<?php

namespace App\Models;

class WhatsappConfig extends BaseModel
{
    protected $table = 'whatsapp_config';

    protected $casts = ['ativo' => 'boolean', 'atualizado_em' => 'datetime'];
}
