<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('itau_config')) {
            Schema::create('itau_config', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('modo', 20)->default('demo');
                $table->string('ambiente', 20)->default('producao');
                $table->string('client_id', 255)->nullable();
                $table->text('client_secret')->nullable();
                $table->string('chave_pix', 180)->nullable();
                $table->string('api_base_url', 500)->nullable();
                $table->string('token_url', 500)->nullable();
                $table->string('cert_path', 500)->nullable();
                $table->string('key_path', 500)->nullable();
                $table->text('access_token')->nullable();
                $table->dateTime('token_expires_at')->nullable();
                $table->string('webhook_url', 500)->nullable();
                $table->string('webhook_token', 160)->nullable();
                $table->boolean('ativo')->default(true);
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        if (! Schema::hasTable('itau_logs')) {
            Schema::create('itau_logs', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('cobranca_id')->nullable();
                $table->string('tipo', 60)->nullable();
                $table->string('status', 80)->nullable();
                $table->integer('http_code')->nullable();
                $table->mediumText('payload')->nullable();
                $table->mediumText('resposta_api')->nullable();
                $table->mediumText('erro')->nullable();
                $table->dateTime('criado_em')->useCurrent();
            });
        }

        Schema::table('cobrancas', function (Blueprint $table): void {
            if (! Schema::hasColumn('cobrancas', 'itau_txid')) {
                $table->string('itau_txid', 80)->nullable()->after('sicoob_payload');
            }
            if (! Schema::hasColumn('cobrancas', 'itau_status')) {
                $table->string('itau_status', 60)->nullable()->after('itau_txid');
            }
            if (! Schema::hasColumn('cobrancas', 'itau_payload')) {
                $table->mediumText('itau_payload')->nullable()->after('itau_status');
            }
        });

        DB::table('itau_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'producao',
            'ativo' => 1,
            'api_base_url' => 'https://secure.api.itau/pix_recebimentos/v2',
            'token_url' => 'https://sts.itau.com.br/api/oauth/token',
            'webhook_token' => 'locx_itau_webhook_token',
        ]);

        if (Schema::hasTable('usuario_permissoes')) {
            foreach (['visualizar', 'criar', 'editar', 'excluir'] as $acao) {
                DB::table('usuario_permissoes')->updateOrInsert(
                    ['usuario_id' => 1, 'modulo' => 'itau', 'acao' => $acao],
                    ['usuario_id' => 1, 'modulo' => 'itau', 'acao' => $acao]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table): void {
            foreach (['itau_payload', 'itau_status', 'itau_txid'] as $coluna) {
                if (Schema::hasColumn('cobrancas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        Schema::dropIfExists('itau_logs');
        Schema::dropIfExists('itau_config');
        DB::table('usuario_permissoes')->where('modulo', 'itau')->delete();
    }
};
