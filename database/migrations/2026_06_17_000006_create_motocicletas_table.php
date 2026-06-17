<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motocicletas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loja_id');
            $table->string('modelo', 120);
            $table->string('marca', 80)->nullable();
            $table->integer('ano')->nullable();
            $table->string('placa', 15)->nullable();
            $table->string('renavam', 40)->nullable();
            $table->string('chassi', 80)->nullable();
            $table->date('data_aquisicao')->nullable();
            $table->string('seguro', 120)->nullable();
            $table->string('rastreador', 120)->nullable();
            $table->string('status_operacional', 30)->default('disponivel');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motocicletas');
    }
};
