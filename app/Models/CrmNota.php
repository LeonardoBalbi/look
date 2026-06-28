<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNota extends BaseModel
{
    protected $table = 'crm_notas';

    protected $casts = [
        'criado_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
