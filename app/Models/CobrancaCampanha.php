<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobrancaCampanha extends BaseModel
{
    protected $table = 'cobranca_campanhas';

    protected $casts = [
        'lojas_json' => 'array',
        'canais_json' => 'array',
        'agendado_para' => 'datetime',
        'processado_em' => 'datetime',
        'atualizado_em' => 'datetime',
        'criado_em' => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(CobrancaCampanhaItem::class, 'campanha_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
