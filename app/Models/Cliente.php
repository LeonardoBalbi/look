<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Cliente extends Authenticatable
{
    protected $table = 'clientes';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['senha'];

    protected $casts = [
        'crm_ultimo_contato_em' => 'datetime',
        'portal_ativo' => 'boolean',
        'ultimo_login_em' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function portalAtendimentos(): HasMany
    {
        return $this->hasMany(PortalAtendimento::class);
    }

    public function crmNotas(): HasMany
    {
        return $this->hasMany(CrmNota::class);
    }

    public function crmTarefas(): HasMany
    {
        return $this->hasMany(CrmTarefa::class);
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
