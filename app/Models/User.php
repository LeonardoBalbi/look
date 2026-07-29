<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [
        'nome',
        'email',
        'senha',
        'perfil',
        'loja_id',
        'status',
    ];

    protected $hidden = ['senha'];

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function perfilAcesso(): BelongsTo
    {
        return $this->belongsTo(UsuarioPerfil::class, 'perfil', 'codigo');
    }

    public function lojas(): BelongsToMany
    {
        return $this->belongsToMany(Loja::class, 'usuario_lojas', 'usuario_id', 'loja_id');
    }

    public function permissoes(): HasMany
    {
        return $this->hasMany(UsuarioPermissao::class, 'usuario_id');
    }

    public function isSuperAdmin(): bool
    {
        return in_array(strtolower((string) $this->perfil), [
            'super_admin',
            'admin',
            'administrador',
        ], true);
    }

    public function isAdministradorGeral(): bool
    {
        return strtolower((string) $this->perfil) === 'administrador_geral';
    }

    public function podeGerenciarUsuarios(): bool
    {
        return $this->isSuperAdmin() || $this->isAdministradorGeral();
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isAdministradorGeral() || strtolower((string) $this->perfil) === 'diretor';
    }

    public function pode(string $modulo, string $acao = 'visualizar'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->relationLoaded('perfilAcesso')) {
            if ($this->perfilAcesso) {
                if ($this->perfilAcesso->status !== 'ativo') {
                    return false;
                }

                if ($this->perfilAcesso->relationLoaded('permissoes')) {
                    return $this->perfilAcesso->permissoes->contains(
                        fn (UsuarioPerfilPermissao $permissao) => $permissao->modulo === $modulo
                            && $permissao->acao === $acao
                    );
                }

                return $this->perfilAcesso->permissoes()
                    ->where('modulo', $modulo)
                    ->where('acao', $acao)
                    ->exists();
            }
        } else {
            $perfil = UsuarioPerfil::query()
                ->where('codigo', $this->perfil)
                ->first();

            if ($perfil) {
                if ($perfil->status !== 'ativo') {
                    return false;
                }

                return $perfil->permissoes()
                    ->where('modulo', $modulo)
                    ->where('acao', $acao)
                    ->exists();
            }
        }

        if ($this->isAdministradorGeral()) {
            return true;
        }

        if ($this->relationLoaded('permissoes')) {
            $temPermissao = $this->permissoes->contains(
                fn (UsuarioPermissao $permissao) => $permissao->modulo === $modulo
                    && $permissao->acao === $acao
            );
            if ($temPermissao) {
                return true;
            }
        } elseif ($this->permissoes()
            ->where('modulo', $modulo)
            ->where('acao', $acao)
            ->exists()) {
            return true;
        }

        return false;
    }

    public function lojaIdsPermitidas(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        $ids = $this->relationLoaded('lojas')
            ? $this->lojas->pluck('id')
            : $this->lojas()->pluck('lojas.id');

        return $ids
            ->push($this->loja_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
