<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends BaseModel
{
    protected $table = 'whatsapp_logs';

    protected $casts = ['criado_em' => 'datetime'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
