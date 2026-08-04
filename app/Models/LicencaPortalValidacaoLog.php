<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicencaPortalValidacaoLog extends BaseModel
{
    protected $table = 'licenca_portal_validacao_logs';

    public function licenca(): BelongsTo
    {
        return $this->belongsTo(LicencaPortalLicenca::class, 'licenca_id');
    }
}
