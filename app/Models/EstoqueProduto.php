<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstoqueProduto extends BaseModel
{
    protected $table = 'estoque_produtos';

    protected $casts = [
        'estoque_minimo' => 'decimal:2',
        'custo_unitario' => 'decimal:2',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(EstoqueMovimento::class, 'produto_id');
    }

    public function saldoAtual(): float
    {
        return (float) $this->movimentos()
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'saida' THEN -quantidade ELSE quantidade END), 0) as saldo")
            ->value('saldo');
    }
}
