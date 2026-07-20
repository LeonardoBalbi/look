<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PortalAtendimento extends BaseModel
{
    protected $table = 'portal_atendimentos';

    protected $casts = [
        'criado_em' => 'datetime',
        'lido_em' => 'datetime',
        'assumido_em' => 'datetime',
        'encerrado_em' => 'datetime',
        'ultima_mensagem_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendente_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(PortalAtendimentoMensagem::class, 'portal_atendimento_id')->orderBy('id');
    }

    public function ultimaMensagem(): HasOne
    {
        return $this->hasOne(PortalAtendimentoMensagem::class, 'portal_atendimento_id')->latestOfMany('id');
    }

    public function ultimaMensagemCliente(): HasOne
    {
        return $this->hasOne(PortalAtendimentoMensagem::class, 'portal_atendimento_id')
            ->where('remetente', 'cliente')
            ->latestOfMany('id');
    }
}
