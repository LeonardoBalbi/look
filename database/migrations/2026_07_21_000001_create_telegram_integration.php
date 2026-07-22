<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_config', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('modo', 20)->default('demo');
            $table->text('bot_token')->nullable();
            $table->string('bot_username', 120)->nullable();
            $table->string('webhook_secret', 255)->nullable();
            $table->string('parse_mode', 20)->nullable();
            $table->text('template_cobranca')->nullable();
            $table->boolean('ativo')->default(true);
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });

        Schema::create('telegram_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('cobranca_id')->nullable()->index();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->string('telegram_update_id', 80)->nullable()->unique();
            $table->string('chat_id', 80)->nullable()->index();
            $table->string('username', 120)->nullable();
            $table->string('tipo', 60)->nullable();
            $table->text('mensagem')->nullable();
            $table->string('status', 50)->default('pendente');
            $table->integer('http_code')->nullable();
            $table->string('telegram_message_id', 80)->nullable();
            $table->mediumText('resposta_api')->nullable();
            $table->mediumText('erro')->nullable();
            $table->dateTime('criado_em')->useCurrent();
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('telegram_chat_id', 80)->nullable()->unique()->after('whatsapp');
            $table->string('telegram_username', 120)->nullable()->after('telegram_chat_id');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_username');
            $table->boolean('telegram_notificacoes')->default(true)->after('telegram_link_token');
            $table->dateTime('telegram_vinculado_em')->nullable()->after('telegram_notificacoes');
        });

        Schema::table('portal_atendimentos', function (Blueprint $table): void {
            $table->string('canal', 30)->default('portal')->index()->after('assistente');
            $table->string('canal_chat_id', 120)->nullable()->after('canal');
        });
    }

    public function down(): void
    {
        Schema::table('portal_atendimentos', function (Blueprint $table): void {
            $table->dropIndex(['canal']);
            $table->dropColumn(['canal', 'canal_chat_id']);
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropUnique(['telegram_link_token']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'telegram_link_token',
                'telegram_notificacoes',
                'telegram_vinculado_em',
            ]);
        });

        Schema::dropIfExists('telegram_logs');
        Schema::dropIfExists('telegram_config');
    }
};
