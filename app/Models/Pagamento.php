<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends BaseModel
{
    protected $table = 'pagamentos';

    protected $casts = [
        'valor' => 'decimal:2',
        'pago_em' => 'datetime',
    ];

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
