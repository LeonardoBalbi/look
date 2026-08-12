<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('licenca_portal_licencas', 'renovacao_automatica')) {
            Schema::table('licenca_portal_licencas', function (Blueprint $table): void {
                $table->boolean('renovacao_automatica')->default(false)->after('tolerancia_offline_dias');
            });
        }
        if (! Schema::hasColumn('licenca_portal_licencas', 'meses_por_renovacao')) {
            Schema::table('licenca_portal_licencas', function (Blueprint $table): void {
                $table->unsignedInteger('meses_por_renovacao')->default(1)->after('renovacao_automatica');
            });
        }

        if (! Schema::hasTable('licenca_portal_pagamentos')) {
            Schema::create('licenca_portal_pagamentos', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('licenca_id')->index();
                $table->unsignedInteger('cliente_id')->index();
                $table->unsignedInteger('plano_id')->nullable()->index();
                $table->string('gateway', 40)->default('manual')->index();
                $table->string('referencia_externa', 160)->nullable()->unique();
                $table->unsignedInteger('valor_centavos')->default(0);
                $table->string('status', 30)->default('pendente')->index();
                $table->unsignedInteger('meses_renovacao')->default(1);
                $table->date('vencimento')->nullable()->index();
                $table->dateTime('pago_em')->nullable();
                $table->dateTime('renovado_em')->nullable();
                $table->string('link_pagamento', 1000)->nullable();
                $table->mediumText('payload_gateway')->nullable();
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email', 190)->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('licenca_portal_pagamentos');
        if (Schema::hasColumn('licenca_portal_licencas', 'renovacao_automatica')) {
            Schema::table('licenca_portal_licencas', function (Blueprint $table): void {
                $table->dropColumn('renovacao_automatica');
            });
        }
        if (Schema::hasColumn('licenca_portal_licencas', 'meses_por_renovacao')) {
            Schema::table('licenca_portal_licencas', function (Blueprint $table): void {
                $table->dropColumn('meses_por_renovacao');
            });
        }
    }
};
