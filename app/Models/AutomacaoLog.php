<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomacaoLog extends BaseModel
{
    protected $table = 'automacao_logs';

    protected $casts = [
        'payload' => 'array',
        'executado_em' => 'datetime',
        'criado_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class);
    }
}
