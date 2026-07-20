<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_atendimentos', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('cliente_id')->index();
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->string('assistente', 40)->default('Lau');
            $table->string('assunto', 60);
            $table->string('prioridade', 20)->default('normal');
            $table->string('status', 30)->default('novo');
            $table->text('mensagem');
            $table->text('resposta')->nullable();
            $table->dateTime('lido_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_atendimentos');
    }
};
