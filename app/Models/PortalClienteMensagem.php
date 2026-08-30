<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalClienteMensagem extends BaseModel
{
    protected $table = 'portal_cliente_mensagens';

    protected $casts = [
        'enviada_em' => 'datetime',
        'lida_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
