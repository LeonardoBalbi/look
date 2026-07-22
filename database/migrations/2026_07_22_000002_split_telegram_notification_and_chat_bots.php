<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('telegram_config', 'atendimento_bot_token')) {
                $table->text('atendimento_bot_token')->nullable()->after('bot_username');
            }
            if (! Schema::hasColumn('telegram_config', 'atendimento_bot_username')) {
                $table->string('atendimento_bot_username', 120)->nullable()->after('atendimento_bot_token');
            }
            if (! Schema::hasColumn('telegram_config', 'atendimento_webhook_secret')) {
                $table->string('atendimento_webhook_secret', 255)->nullable()->after('webhook_secret');
            }
        });

        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'telegram_atendimento_chat_id')) {
                $table->string('telegram_atendimento_chat_id', 80)->nullable()->unique()->after('telegram_vinculado_em');
            }
            if (! Schema::hasColumn('clientes', 'telegram_atendimento_username')) {
                $table->string('telegram_atendimento_username', 120)->nullable()->after('telegram_atendimento_chat_id');
            }
            if (! Schema::hasColumn('clientes', 'telegram_atendimento_link_token')) {
                $table->string('telegram_atendimento_link_token', 64)->nullable()->unique()->after('telegram_atendimento_username');
            }
            if (! Schema::hasColumn('clientes', 'telegram_atendimento_vinculado_em')) {
                $table->dateTime('telegram_atendimento_vinculado_em')->nullable()->after('telegram_atendimento_link_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (Schema::hasColumn('clientes', 'telegram_atendimento_link_token')) {
                $table->dropUnique(['telegram_atendimento_link_token']);
            }
            if (Schema::hasColumn('clientes', 'telegram_atendimento_chat_id')) {
                $table->dropUnique(['telegram_atendimento_chat_id']);
            }

            $colunas = collect([
                'telegram_atendimento_chat_id',
                'telegram_atendimento_username',
                'telegram_atendimento_link_token',
                'telegram_atendimento_vinculado_em',
            ])->filter(fn (string $coluna) => Schema::hasColumn('clientes', $coluna))->all();

            if ($colunas) {
                $table->dropColumn($colunas);
            }
        });

        Schema::table('telegram_config', function (Blueprint $table): void {
            $colunas = collect([
                'atendimento_bot_token',
                'atendimento_bot_username',
                'atendimento_webhook_secret',
            ])->filter(fn (string $coluna) => Schema::hasColumn('telegram_config', $coluna))->all();

            if ($colunas) {
                $table->dropColumn($colunas);
            }
        });
    }
};
