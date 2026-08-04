<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class LicencaPortalPlano extends BaseModel
{
    protected $table = 'licenca_portal_planos';

    protected $casts = [
        'ativo' => 'boolean',
        'modulos_json' => 'array',
        'atualizado_em' => 'datetime',
    ];

    public function licencas(): HasMany
    {
        return $this->hasMany(LicencaPortalLicenca::class, 'plano_id');
    }
}
