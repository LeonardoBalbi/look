<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome', 140);
            $table->string('email', 160)->unique();
            $table->string('senha', 255);
            $table->string('perfil', 40)->default('atendente');
            $table->unsignedInteger('loja_id')->nullable();
            $table->string('status', 30)->default('ativo');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
