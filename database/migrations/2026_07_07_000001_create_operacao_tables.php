<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->unsignedInteger('motocicleta_id')->nullable()->index();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->string('tipo', 30)->default('corretiva');
            $table->string('titulo', 180);
            $table->text('descricao')->nullable();
            $table->string('status', 30)->default('aberta')->index();
            $table->string('prioridade', 30)->default('normal');
            $table->decimal('custo_previsto', 10, 2)->default(0);
            $table->decimal('custo_final', 10, 2)->default(0);
            $table->dateTime('aberto_em')->nullable();
            $table->date('previsto_em')->nullable();
            $table->dateTime('concluido_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('estoque_produtos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->string('nome', 180);
            $table->string('sku', 80)->nullable()->index();
            $table->string('grupo', 80)->nullable();
            $table->string('unidade', 20)->default('un');
            $table->decimal('estoque_minimo', 10, 2)->default(0);
            $table->decimal('custo_unitario', 10, 2)->default(0);
            $table->string('status', 30)->default('ativo')->index();
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('estoque_movimentos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('produto_id')->index();
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->string('tipo', 30);
            $table->decimal('quantidade', 10, 2);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            $table->string('origem', 120)->nullable();
            $table->text('observacao')->nullable();
            $table->dateTime('movimentado_em');
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('multas_transito', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->unsignedInteger('motocicleta_id')->nullable()->index();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->unsignedInteger('contrato_id')->nullable()->index();
            $table->string('auto_infracao', 80)->nullable();
            $table->string('orgao', 120)->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('valor', 10, 2)->default(0);
            $table->date('vencimento')->nullable();
            $table->date('ocorrida_em')->nullable();
            $table->string('status', 30)->default('aberta')->index();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multas_transito');
        Schema::dropIfExists('estoque_movimentos');
        Schema::dropIfExists('estoque_produtos');
        Schema::dropIfExists('ordens_servico');
    }
};
