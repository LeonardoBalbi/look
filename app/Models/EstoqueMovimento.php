<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstoqueMovimento extends BaseModel
{
    protected $table = 'estoque_movimentos';

    protected $casts = [
        'quantidade' => 'decimal:2',
        'valor_unitario' => 'decimal:2',
        'movimentado_em' => 'datetime',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(EstoqueProduto::class, 'produto_id');
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }
}
