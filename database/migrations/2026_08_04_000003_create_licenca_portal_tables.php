<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licenca_portal_clientes')) {
            Schema::create('licenca_portal_clientes', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('nome', 180);
                $table->string('documento', 30)->nullable()->index();
                $table->string('email', 160)->nullable();
                $table->string('telefone', 40)->nullable();
                $table->string('status', 30)->default('ativo')->index();
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('licenca_portal_planos')) {
            Schema::create('licenca_portal_planos', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('codigo', 80)->unique();
                $table->string('nome', 120);
                $table->unsignedInteger('preco_centavos')->default(0);
                $table->unsignedInteger('max_lojas')->nullable();
                $table->unsignedInteger('max_usuarios')->nullable();
                $table->json('modulos_json')->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('licenca_portal_licencas')) {
            Schema::create('licenca_portal_licencas', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('cliente_id')->index();
                $table->unsignedInteger('plano_id')->index();
                $table->string('chave', 120)->unique();
                $table->string('status', 30)->default('ativa')->index();
                $table->date('vence_em')->nullable()->index();
                $table->string('instancia_id', 120)->nullable()->index();
                $table->dateTime('ultimo_check_em')->nullable();
                $table->unsignedInteger('tolerancia_offline_dias')->default(7);
                $table->string('mensagem', 500)->nullable();
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('licenca_portal_validacao_logs')) {
            Schema::create('licenca_portal_validacao_logs', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('licenca_id')->nullable()->index();
                $table->string('chave_mascarada', 120)->nullable();
                $table->string('instancia_id', 120)->nullable();
                $table->string('ip', 80)->nullable();
                $table->string('status', 40)->nullable();
                $table->mediumText('payload')->nullable();
                $table->mediumText('resposta')->nullable();
                $table->mediumText('erro')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('licenca_portal_validacao_logs');
        Schema::dropIfExists('licenca_portal_licencas');
        Schema::dropIfExists('licenca_portal_planos');
        Schema::dropIfExists('licenca_portal_clientes');
    }
};
