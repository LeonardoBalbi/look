<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Loja extends BaseModel
{
    protected $table = 'lojas';

    public function motocicletas(): HasMany
    {
        return $this->hasMany(Motocicleta::class);
    }
}
