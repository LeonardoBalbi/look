<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cobranca_id')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('tipo', 60)->nullable();
            $table->text('mensagem')->nullable();
            $table->string('status', 50)->default('pendente');
            $table->integer('http_code')->nullable();
            $table->mediumText('resposta_api')->nullable();
            $table->mediumText('erro')->nullable();
            $table->dateTime('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
