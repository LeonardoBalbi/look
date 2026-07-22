<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_campanhas', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('criado_por')->nullable()->index();
            $table->string('nome', 180);
            $table->string('publico', 40)->default('todos_abertos');
            $table->json('lojas_json')->nullable();
            $table->json('canais_json');
            $table->string('estrategia', 30)->default('todos');
            $table->text('mensagem');
            $table->string('status', 30)->default('rascunho')->index();
            $table->dateTime('agendado_para')->nullable()->index();
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('total_processados')->default(0);
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_falhas')->default(0);
            $table->dateTime('processado_em')->nullable();
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('cobranca_campanha_itens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('campanha_id')->index();
            $table->unsignedInteger('cobranca_id')->index();
            $table->unsignedInteger('cliente_id')->index();
            $table->string('status', 30)->default('pendente')->index();
            $table->json('canais_json');
            $table->json('resultados_json')->nullable();
            $table->mediumText('erro')->nullable();
            $table->dateTime('processando_em')->nullable()->index();
            $table->dateTime('processado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->unique(['campanha_id', 'cobranca_id'], 'campanha_cobranca_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_campanha_itens');
        Schema::dropIfExists('cobranca_campanhas');
    }
};
