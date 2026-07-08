<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('look_modulo_registros', function (Blueprint $table) {
            $table->increments('id');
            $table->string('modulo', 40)->index();
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->string('titulo', 180);
            $table->string('pessoa', 180)->nullable();
            $table->string('documento', 120)->nullable();
            $table->string('telefone', 40)->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->date('vencimento')->nullable()->index();
            $table->string('status', 40)->default('aberto')->index();
            $table->text('descricao')->nullable();
            $table->unsignedInteger('usuario_id')->nullable()->index();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('look_modulo_registros');
    }
};
