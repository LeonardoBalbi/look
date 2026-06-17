<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_lojas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('usuario_id');
            $table->unsignedInteger('loja_id');
            $table->unique(['usuario_id', 'loja_id'], 'uk_usuario_loja');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_lojas');
    }
};
