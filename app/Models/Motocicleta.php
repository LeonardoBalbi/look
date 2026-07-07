<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motocicleta extends BaseModel
{
    protected $table = 'motocicletas';

    protected $casts = ['data_aquisicao' => 'date'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function multasTransito(): HasMany
    {
        return $this->hasMany(MultaTransito::class);
    }
}
