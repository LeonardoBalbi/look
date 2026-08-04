<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licenca_config')) {
            Schema::create('licenca_config', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('modo', 20)->default('local');
                $table->string('status', 30)->default('local');
                $table->string('plano', 80)->nullable();
                $table->string('empresa_nome', 180)->nullable();
                $table->string('empresa_documento', 30)->nullable();
                $table->string('instancia_id', 80)->nullable()->unique();
                $table->text('licenca_chave')->nullable();
                $table->string('api_url', 500)->nullable();
                $table->unsignedInteger('max_lojas')->nullable();
                $table->unsignedInteger('max_usuarios')->nullable();
                $table->json('modulos_json')->nullable();
                $table->date('vence_em')->nullable();
                $table->unsignedInteger('tolerancia_offline_dias')->default(7);
                $table->dateTime('ultima_validacao_em')->nullable();
                $table->dateTime('ultima_validacao_ok_em')->nullable();
                $table->dateTime('proxima_validacao_em')->nullable();
                $table->mediumText('ultimo_payload')->nullable();
                $table->string('mensagem', 500)->nullable();
                $table->boolean('ativo')->default(true);
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('licenca_validacao_logs')) {
            Schema::create('licenca_validacao_logs', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('licenca_config_id')->nullable()->index();
                $table->string('tipo', 40)->default('online');
                $table->string('status', 40)->nullable();
                $table->integer('http_code')->nullable();
                $table->mediumText('payload')->nullable();
                $table->mediumText('resposta')->nullable();
                $table->mediumText('erro')->nullable();
                $table->dateTime('criado_em')->useCurrent();
            });
        }

        DB::table('licenca_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'local',
            'status' => 'local',
            'instancia_id' => (string) Str::uuid(),
            'tolerancia_offline_dias' => 7,
            'ativo' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('licenca_validacao_logs');
        Schema::dropIfExists('licenca_config');
    }
};
