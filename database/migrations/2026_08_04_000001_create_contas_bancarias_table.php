<?php

use App\Services\AsaasService;
use App\Services\ItauService;
use App\Services\SicoobService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contas_bancarias')) {
            Schema::create('contas_bancarias', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('loja_id')->nullable()->index();
                $table->string('provedor', 30)->index();
                $table->string('nome_conta', 120)->nullable();
                $table->string('agencia', 40)->nullable();
                $table->string('conta', 60)->nullable();
                $table->string('chave_pix', 180)->nullable();
                $table->string('modo', 20)->default('demo');
                $table->string('ambiente', 20)->default('sandbox');
                $table->text('api_key')->nullable();
                $table->string('client_id', 255)->nullable();
                $table->text('client_secret')->nullable();
                $table->text('access_token')->nullable();
                $table->dateTime('token_expires_at')->nullable();
                $table->string('api_base_url', 500)->nullable();
                $table->string('token_url', 500)->nullable();
                $table->string('cert_path', 500)->nullable();
                $table->string('key_path', 500)->nullable();
                $table->string('webhook_url', 500)->nullable();
                $table->string('webhook_token', 160)->nullable();
                $table->string('merchant_reference', 80)->nullable();
                $table->text('credenciais_json')->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->boolean('padrao')->default(false)->index();
                $table->dateTime('atualizado_em')->nullable();
                $table->timestamp('criado_em')->useCurrent();
            });
        }

        Schema::table('cobrancas', function (Blueprint $table): void {
            if (! Schema::hasColumn('cobrancas', 'conta_bancaria_id')) {
                $table->unsignedInteger('conta_bancaria_id')->nullable()->after('loja_id')->index();
            }
            if (! Schema::hasColumn('cobrancas', 'gateway_usado')) {
                $table->string('gateway_usado', 30)->nullable()->after('conta_bancaria_id')->index();
            }
        });

        $this->migrarConfiguracaoGlobal();
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table): void {
            if (Schema::hasColumn('cobrancas', 'gateway_usado')) {
                $table->dropColumn('gateway_usado');
            }
            if (Schema::hasColumn('cobrancas', 'conta_bancaria_id')) {
                $table->dropColumn('conta_bancaria_id');
            }
        });

        Schema::dropIfExists('contas_bancarias');
    }

    private function migrarConfiguracaoGlobal(): void
    {
        if (Schema::hasTable('pagbank_config') && ($config = DB::table('pagbank_config')->where('id', 1)->first())) {
            DB::table('contas_bancarias')->updateOrInsert(
                ['loja_id' => null, 'provedor' => 'pagbank'],
                [
                    'nome_conta' => 'PagBank central',
                    'modo' => $config->modo ?? 'demo',
                    'ambiente' => $config->ambiente ?? 'sandbox',
                    'client_id' => $config->client_id ?? null,
                    'client_secret' => $config->client_secret ?? null,
                    'access_token' => $config->access_token ?? null,
                    'webhook_url' => $config->webhook_url ?? null,
                    'merchant_reference' => $config->merchant_reference ?? 'RENTAL',
                    'ativo' => (bool) ($config->ativo ?? true),
                    'padrao' => true,
                    'atualizado_em' => now(),
                ]
            );
        }

        if (Schema::hasTable('asaas_config') && ($config = DB::table('asaas_config')->where('id', 1)->first())) {
            DB::table('contas_bancarias')->updateOrInsert(
                ['loja_id' => null, 'provedor' => 'asaas'],
                [
                    'nome_conta' => 'Asaas central',
                    'modo' => $config->modo ?? 'demo',
                    'ambiente' => $config->ambiente ?? 'sandbox',
                    'api_key' => $config->api_key ?? null,
                    'webhook_url' => $config->webhook_url ?? null,
                    'webhook_token' => $config->webhook_token ?? AsaasService::DEFAULT_WEBHOOK_TOKEN,
                    'ativo' => (bool) ($config->ativo ?? true),
                    'padrao' => false,
                    'atualizado_em' => now(),
                ]
            );
        }

        if (Schema::hasTable('sicoob_config') && ($config = DB::table('sicoob_config')->where('id', 1)->first())) {
            DB::table('contas_bancarias')->updateOrInsert(
                ['loja_id' => null, 'provedor' => 'sicoob'],
                [
                    'nome_conta' => 'Sicoob central',
                    'modo' => $config->modo ?? 'demo',
                    'ambiente' => $config->ambiente ?? 'sandbox',
                    'client_id' => $config->client_id ?? null,
                    'client_secret' => $config->client_secret ?? null,
                    'chave_pix' => $config->chave_pix ?? null,
                    'api_base_url' => $config->api_base_url ?? SicoobService::DEFAULT_API_BASE_URL,
                    'token_url' => $config->token_url ?? SicoobService::DEFAULT_TOKEN_URL,
                    'cert_path' => $config->cert_path ?? null,
                    'key_path' => $config->key_path ?? null,
                    'access_token' => $config->access_token ?? null,
                    'token_expires_at' => $config->token_expires_at ?? null,
                    'webhook_url' => $config->webhook_url ?? null,
                    'webhook_token' => $config->webhook_token ?? SicoobService::DEFAULT_WEBHOOK_TOKEN,
                    'ativo' => (bool) ($config->ativo ?? true),
                    'padrao' => false,
                    'atualizado_em' => now(),
                ]
            );
        }

        if (Schema::hasTable('itau_config') && ($config = DB::table('itau_config')->where('id', 1)->first())) {
            DB::table('contas_bancarias')->updateOrInsert(
                ['loja_id' => null, 'provedor' => 'itau'],
                [
                    'nome_conta' => 'Itau central',
                    'modo' => $config->modo ?? 'demo',
                    'ambiente' => $config->ambiente ?? 'producao',
                    'client_id' => $config->client_id ?? null,
                    'client_secret' => $config->client_secret ?? null,
                    'chave_pix' => $config->chave_pix ?? null,
                    'api_base_url' => $config->api_base_url ?? ItauService::DEFAULT_API_BASE_URL,
                    'token_url' => $config->token_url ?? ItauService::DEFAULT_TOKEN_URL,
                    'cert_path' => $config->cert_path ?? null,
                    'key_path' => $config->key_path ?? null,
                    'access_token' => $config->access_token ?? null,
                    'token_expires_at' => $config->token_expires_at ?? null,
                    'webhook_url' => $config->webhook_url ?? null,
                    'webhook_token' => $config->webhook_token ?? ItauService::DEFAULT_WEBHOOK_TOKEN,
                    'ativo' => (bool) ($config->ativo ?? true),
                    'padrao' => false,
                    'atualizado_em' => now(),
                ]
            );
        }

        if (Schema::hasTable('pix_gateway_config') && ($gateway = DB::table('pix_gateway_config')->where('id', 1)->value('gateway'))) {
            DB::table('contas_bancarias')->whereNull('loja_id')->update(['padrao' => false]);
            DB::table('contas_bancarias')->whereNull('loja_id')->where('provedor', $gateway)->update(['padrao' => true]);
        }
    }
};
