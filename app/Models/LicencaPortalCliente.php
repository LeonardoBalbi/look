<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class LicencaPortalCliente extends BaseModel
{
    protected $table = 'licenca_portal_clientes';

    public function licencas(): HasMany
    {
        return $this->hasMany(LicencaPortalLicenca::class, 'cliente_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(LicencaPortalPagamento::class, 'cliente_id');
    }
}
