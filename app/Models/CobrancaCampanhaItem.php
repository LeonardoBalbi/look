<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaCampanhaItem extends BaseModel
{
    protected $table = 'cobranca_campanha_itens';

    protected $casts = [
        'canais_json' => 'array',
        'resultados_json' => 'array',
        'processando_em' => 'datetime',
        'processado_em' => 'datetime',
        'criado_em' => 'datetime',
    ];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(CobrancaCampanha::class, 'campanha_id');
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
