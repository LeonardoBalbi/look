<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLog extends BaseModel
{
    protected $table = 'telegram_logs';

    protected $casts = [
        'criado_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
