<?php

namespace App\Models;

use App\Notifications\LicencaClienteResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LicencaPortalCliente extends Authenticatable
{
    use Notifiable;

    protected $table = 'licenca_portal_clientes';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['senha'];

    protected $casts = [
        'portal_ativo' => 'boolean',
        'ultimo_login_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LicencaClienteResetPasswordNotification((string) $token));
    }

    public function licencas(): HasMany
    {
        return $this->hasMany(LicencaPortalLicenca::class, 'cliente_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(LicencaPortalPagamento::class, 'cliente_id');
    }
}
