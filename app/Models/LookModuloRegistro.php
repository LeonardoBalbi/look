<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookModuloRegistro extends BaseModel
{
    protected $table = 'look_modulo_registros';

    protected $casts = [
        'valor' => 'float',
        'vencimento' => 'date',
        'criado_em' => 'datetime',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
