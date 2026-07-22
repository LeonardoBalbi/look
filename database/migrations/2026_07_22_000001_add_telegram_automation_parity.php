<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table): void {
            if (! Schema::hasColumn('cobrancas', 'telegram_status')) {
                $table->string('telegram_status', 30)->default('pendente')->after('whatsapp_status');
            }
        });

        Schema::table('telegram_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('telegram_config', 'template_lembrete')) {
                $table->text('template_lembrete')->nullable()->after('template_cobranca');
            }
            if (! Schema::hasColumn('telegram_config', 'template_vencimento')) {
                $table->text('template_vencimento')->nullable()->after('template_lembrete');
            }
            if (! Schema::hasColumn('telegram_config', 'template_pagamento')) {
                $table->text('template_pagamento')->nullable()->after('template_vencimento');
            }
            if (! Schema::hasColumn('telegram_config', 'template_gerente')) {
                $table->text('template_gerente')->nullable()->after('template_pagamento');
            }
            if (! Schema::hasColumn('telegram_config', 'gerente_chat_id')) {
                $table->string('gerente_chat_id', 80)->nullable()->after('template_gerente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_config', function (Blueprint $table): void {
            $colunas = collect([
                'template_lembrete',
                'template_vencimento',
                'template_pagamento',
                'template_gerente',
                'gerente_chat_id',
            ])->filter(fn (string $coluna) => Schema::hasColumn('telegram_config', $coluna))->all();

            if ($colunas) {
                $table->dropColumn($colunas);
            }
        });

        Schema::table('cobrancas', function (Blueprint $table): void {
            if (Schema::hasColumn('cobrancas', 'telegram_status')) {
                $table->dropColumn('telegram_status');
            }
        });
    }
};
