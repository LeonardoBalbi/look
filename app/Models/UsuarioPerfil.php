<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class UsuarioPerfil extends BaseModel
{
    protected $table = 'usuario_perfis';

    public function permissoes(): HasMany
    {
        return $this->hasMany(UsuarioPerfilPermissao::class, 'perfil_id');
    }
}
