<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'crm_etapa')) {
                $table->string('crm_etapa', 40)->default('contrato_ativo')->after('status');
            }
            if (! Schema::hasColumn('clientes', 'crm_ultimo_contato_em')) {
                $table->dateTime('crm_ultimo_contato_em')->nullable()->after('crm_etapa');
            }
        });

        Schema::create('crm_notas', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('cliente_id')->index();
            $table->unsignedInteger('usuario_id')->nullable()->index();
            $table->string('tipo', 40)->default('nota');
            $table->text('texto');
            $table->dateTime('criado_em')->useCurrent();
        });

        Schema::create('crm_tarefas', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('cliente_id')->index();
            $table->unsignedInteger('usuario_id')->nullable()->index();
            $table->string('titulo', 180);
            $table->string('tipo', 40)->default('follow_up');
            $table->string('status', 30)->default('aberta');
            $table->dateTime('prazo_em')->nullable()->index();
            $table->text('observacao')->nullable();
            $table->dateTime('concluido_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tarefas');
        Schema::dropIfExists('crm_notas');

        Schema::table('clientes', function (Blueprint $table): void {
            if (Schema::hasColumn('clientes', 'crm_ultimo_contato_em')) {
                $table->dropColumn('crm_ultimo_contato_em');
            }
            if (Schema::hasColumn('clientes', 'crm_etapa')) {
                $table->dropColumn('crm_etapa');
            }
        });
    }
};
