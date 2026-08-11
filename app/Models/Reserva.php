<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends BaseModel
{
    protected $table = 'reservas';

    protected $casts = [
        'retirada_em' => 'datetime',
        'devolucao_em' => 'datetime',
        'valor_estimado' => 'decimal:2',
        'caucao' => 'decimal:2',
        'criado_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function motocicleta(): BelongsTo
    {
        return $this->belongsTo(Motocicleta::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
