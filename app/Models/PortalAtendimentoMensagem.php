<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAtendimentoMensagem extends BaseModel
{
    protected $table = 'portal_atendimento_mensagens';

    protected $casts = [
        'criado_em' => 'datetime',
    ];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(PortalAtendimento::class, 'portal_atendimento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
