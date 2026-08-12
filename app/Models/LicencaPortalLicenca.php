<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicencaPortalLicenca extends BaseModel
{
    protected $table = 'licenca_portal_licencas';

    protected $casts = [
        'vence_em' => 'date',
        'ultimo_check_em' => 'datetime',
        'atualizado_em' => 'datetime',
        'renovacao_automatica' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalCliente::class, 'cliente_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalPlano::class, 'plano_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LicencaPortalValidacaoLog::class, 'licenca_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(LicencaPortalPagamento::class, 'licenca_id');
    }
}
