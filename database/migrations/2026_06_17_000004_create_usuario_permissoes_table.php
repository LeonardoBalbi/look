<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_permissoes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('usuario_id');
            $table->string('modulo', 40);
            $table->string('acao', 30);
            $table->unique(['usuario_id', 'modulo', 'acao'], 'uk_usuario_perm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_permissoes');
    }
};
