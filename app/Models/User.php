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

    public function lojas(): BelongsToMany
    {
        return $this->belongsToMany(Loja::class, 'usuario_lojas', 'usuario_id', 'loja_id');
    }

    public function permissoes(): HasMany
    {
        return $this->hasMany(UsuarioPermissao::class, 'usuario_id');
    }

    public function isAdmin(): bool
    {
        return in_array(strtolower((string) $this->perfil), [
            'administrador_geral',
            'diretor',
            'admin',
            'administrador',
        ], true);
    }

    public function pode(string $modulo, string $acao = 'visualizar'): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->relationLoaded('permissoes')) {
            return $this->permissoes->contains(
                fn (UsuarioPermissao $permissao) => $permissao->modulo === $modulo
                    && $permissao->acao === $acao
            );
        }

        return $this->permissoes()
            ->where('modulo', $modulo)
            ->where('acao', $acao)
            ->exists();
    }

    public function lojaIdsPermitidas(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        $ids = $this->relationLoaded('lojas')
            ? $this->lojas->pluck('id')
            : $this->lojas()->pluck('lojas.id');

        return $ids->map(fn ($id) => (int) $id)->all();
    }
}
