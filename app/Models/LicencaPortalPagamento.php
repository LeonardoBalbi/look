<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicencaPortalPagamento extends BaseModel
{
    protected $table = 'licenca_portal_pagamentos';

    protected $casts = [
        'vencimento' => 'date',
        'pago_em' => 'datetime',
        'renovado_em' => 'datetime',
        'atualizado_em' => 'datetime',
        'criado_em' => 'datetime',
    ];

    public function licenca(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalLicenca::class, 'licenca_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalCliente::class, 'cliente_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalPlano::class, 'plano_id');
    }
}
