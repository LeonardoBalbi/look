<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cobranca extends BaseModel
{
    protected $table = 'cobrancas';

    protected $casts = [
        'vencimento' => 'date',
        'valor_principal' => 'decimal:2',
        'valor_atualizado' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'atualizado_em' => 'datetime',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
