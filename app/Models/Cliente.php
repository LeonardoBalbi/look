<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends BaseModel
{
    protected $table = 'clientes';

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }
}
