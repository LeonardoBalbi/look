<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends BaseModel
{
    protected $table = 'clientes';

    protected $casts = [
        'crm_ultimo_contato_em' => 'datetime',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function crmNotas(): HasMany
    {
        return $this->hasMany(CrmNota::class);
    }

    public function crmTarefas(): HasMany
    {
        return $this->hasMany(CrmTarefa::class);
    }
}
