<?php

namespace Database\Seeders;

use App\Services\AsaasService;
use App\Services\SicoobService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocxInitialSeeder extends Seeder
{
    public function run(): void
    {
        $hash = password_hash('123456', PASSWORD_DEFAULT);

        DB::table('lojas')->insertOrIgnore([
            ['id' => 1, 'nome' => 'Barra da Tijuca', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 2, 'nome' => 'Campo Grande', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 3, 'nome' => 'Bangu', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 4, 'nome' => 'Itaguaí', 'cidade' => 'Itaguaí', 'status' => 'ativa'],
        ]);

        DB::table('usuarios')->updateOrInsert(
            ['email' => 'admin@locx.com.br'],
            [
                'id' => 1,
                'nome' => 'Administrador LocX',
                'senha' => $hash,
                'perfil' => 'administrador_geral',
                'loja_id' => null,
                'status' => 'ativo',
            ]
        );

        $modulos = [
            'dashboard', 'crm', 'clientes', 'motos', 'contratos', 'financeiro', 'cobrancas',
            'manutencao', 'estoque', 'multas', 'inadimplencia', 'pix', 'bancos', 'pagbank', 'asaas', 'sicoob', 'whatsapp', 'relatorios', 'lojas',
            'usuarios', 'configuracoes',
        ];
        $acoes = ['visualizar', 'criar', 'editar', 'excluir'];

        foreach ($modulos as $modulo) {
            foreach ($acoes as $acao) {
                DB::table('usuario_permissoes')->updateOrInsert(
                    ['usuario_id' => 1, 'modulo' => $modulo, 'acao' => $acao],
                    ['usuario_id' => 1, 'modulo' => $modulo, 'acao' => $acao]
                );
            }
        }

        foreach ([1, 2, 3, 4] as $lojaId) {
            DB::table('usuario_lojas')->updateOrInsert(
                ['usuario_id' => 1, 'loja_id' => $lojaId],
                ['usuario_id' => 1, 'loja_id' => $lojaId]
            );
        }

        DB::table('whatsapp_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'verify_token' => 'locx_webhook_token',
            'ativo' => 1,
        ]);

        DB::table('pagbank_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'merchant_reference' => 'LOCX',
        ]);

        DB::table('asaas_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'webhook_token' => AsaasService::DEFAULT_WEBHOOK_TOKEN,
        ]);

        if (Schema::hasTable('sicoob_config')) {
            DB::table('sicoob_config')->insertOrIgnore([
                'id' => 1,
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'ativo' => 1,
                'api_base_url' => SicoobService::DEFAULT_API_BASE_URL,
                'token_url' => SicoobService::DEFAULT_TOKEN_URL,
                'webhook_token' => SicoobService::DEFAULT_WEBHOOK_TOKEN,
            ]);
        }

        DB::table('pix_gateway_config')->insertOrIgnore([
            'id' => 1,
            'gateway' => 'pagbank',
        ]);
    }
}
