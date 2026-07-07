<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServico extends BaseModel
{
    protected $table = 'ordens_servico';

    protected $casts = [
        'custo_previsto' => 'decimal:2',
        'custo_final' => 'decimal:2',
        'aberto_em' => 'datetime',
        'previsto_em' => 'date',
        'concluido_em' => 'datetime',
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
}
