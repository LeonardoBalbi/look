<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('motocicleta_id');
            $table->unsignedInteger('loja_id');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->decimal('valor_contratado', 10, 2);
            $table->string('forma_cobranca', 30)->default('semanal');
            $table->string('assinatura_digital', 255)->nullable();
            $table->string('status', 30)->default('ativo');
            $table->text('historico_alteracoes')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
