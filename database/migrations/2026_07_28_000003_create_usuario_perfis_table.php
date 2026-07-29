<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_perfis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 60)->unique();
            $table->string('nome', 120);
            $table->string('descricao', 255)->nullable();
            $table->boolean('sistema')->default(false);
            $table->string('status', 30)->default('ativo');
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('usuario_perfil_permissoes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('perfil_id');
            $table->string('modulo', 40);
            $table->string('acao', 30);
            $table->unique(['perfil_id', 'modulo', 'acao'], 'uk_usuario_perfil_perm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_perfil_permissoes');
        Schema::dropIfExists('usuario_perfis');
    }
};
