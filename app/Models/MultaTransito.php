<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultaTransito extends BaseModel
{
    protected $table = 'multas_transito';

    protected $casts = [
        'valor' => 'decimal:2',
        'vencimento' => 'date',
        'ocorrida_em' => 'date',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function motocicleta(): BelongsTo
    {
        return $this->belongsTo(Motocicleta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
