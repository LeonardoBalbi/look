<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sicoob_config')) {
            Schema::create('sicoob_config', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('modo', 20)->default('demo');
                $table->string('ambiente', 20)->default('sandbox');
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

        if (! Schema::hasTable('sicoob_logs')) {
            Schema::create('sicoob_logs', function (Blueprint $table): void {
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
            if (! Schema::hasColumn('cobrancas', 'sicoob_txid')) {
                $table->string('sicoob_txid', 80)->nullable()->after('pagbank_payload');
            }
            if (! Schema::hasColumn('cobrancas', 'sicoob_status')) {
                $table->string('sicoob_status', 60)->nullable()->after('sicoob_txid');
            }
            if (! Schema::hasColumn('cobrancas', 'sicoob_payload')) {
                $table->mediumText('sicoob_payload')->nullable()->after('sicoob_status');
            }
        });

        DB::table('sicoob_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'api_base_url' => 'https://api.sicoob.com.br/pix/api/v2',
            'token_url' => 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token',
            'webhook_token' => 'rental_sicoob_webhook_token',
        ]);
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table): void {
            foreach (['sicoob_payload', 'sicoob_status', 'sicoob_txid'] as $coluna) {
                if (Schema::hasColumn('cobrancas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        Schema::dropIfExists('sicoob_logs');
        Schema::dropIfExists('sicoob_config');
    }
};
