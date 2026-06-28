<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends BaseModel
{
    protected $table = 'contratos';

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'valor_contratado' => 'decimal:2',
        'cobranca_automatica' => 'boolean',
        'proxima_cobranca_em' => 'date',
        'ultima_cobranca_gerada_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function motocicleta(): BelongsTo
    {
        return $this->belongsTo(Motocicleta::class);
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
