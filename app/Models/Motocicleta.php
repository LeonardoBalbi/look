<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Motocicleta extends BaseModel
{
    protected $table = 'motocicletas';

    protected $casts = ['data_aquisicao' => 'date'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }
}
