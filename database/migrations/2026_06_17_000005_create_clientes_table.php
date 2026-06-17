<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loja_id')->nullable();
            $table->string('nome', 180);
            $table->string('cpf', 20)->nullable()->unique();
            $table->string('rg', 30)->nullable();
            $table->string('cnh', 40)->nullable();
            $table->text('endereco')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('foto_cliente', 255)->nullable();
            $table->string('foto_documento', 255)->nullable();
            $table->string('comprovante_residencia', 255)->nullable();
            $table->string('status', 30)->default('ativo');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
