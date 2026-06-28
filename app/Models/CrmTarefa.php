<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmTarefa extends BaseModel
{
    protected $table = 'crm_tarefas';

    protected $casts = [
        'prazo_em' => 'datetime',
        'concluido_em' => 'datetime',
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
