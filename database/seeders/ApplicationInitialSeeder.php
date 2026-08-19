<?php

namespace Database\Seeders;

use App\Services\AsaasService;
use App\Services\ItauService;
use App\Services\SicoobService;
use App\Support\RentalSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicationInitialSeeder extends Seeder
{
    public function run(): void
    {
        $senhaInicial = (string) env('INITIAL_ADMIN_PASSWORD', app()->environment('production') ? '' : '123456');
        if ($senhaInicial === '') {
            throw new \RuntimeException('Defina INITIAL_ADMIN_PASSWORD antes de executar o seeder em produção.');
        }

        $adminEmail = (string) env('INITIAL_ADMIN_EMAIL', 'admin@example.com');
        $superAdminEmail = (string) env('INITIAL_SUPER_ADMIN_EMAIL', 'superadmin@example.com');
        $hash = password_hash($senhaInicial, PASSWORD_DEFAULT);

        $lojasIniciais = config('installation.single_store') || app()->environment('production') ? [
            ['id' => 1, 'nome' => config('branding.store_name'), 'cidade' => env('STORE_CITY'), 'status' => 'ativa'],
        ] : [
            ['id' => 1, 'nome' => 'Barra da Tijuca', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 2, 'nome' => 'Campo Grande', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 3, 'nome' => 'Bangu', 'cidade' => 'Rio de Janeiro', 'status' => 'ativa'],
            ['id' => 4, 'nome' => 'Itaguaí', 'cidade' => 'Itaguaí', 'status' => 'ativa'],
        ];
        DB::table('lojas')->insertOrIgnore($lojasIniciais);

        DB::table('usuarios')->updateOrInsert(
            ['email' => $superAdminEmail],
            [
                'nome' => 'Super Administrador',
                'senha' => $hash,
                'perfil' => 'super_admin',
                'loja_id' => null,
                'status' => 'ativo',
            ]
        );

        DB::table('usuarios')->updateOrInsert(
            ['email' => $adminEmail],
            [
                'nome' => 'Administrador',
                'senha' => $hash,
                'perfil' => 'administrador_geral',
                'loja_id' => null,
                'status' => 'ativo',
            ]
        );
        $adminId = (int) DB::table('usuarios')->where('email', $adminEmail)->value('id');

        $modulos = [
            'dashboard', 'crm', 'clientes', 'motos', 'contratos', 'financeiro', 'cobrancas',
            'manutencao', 'estoque', 'multas', 'inadimplencia', 'pix', 'bancos', 'pagbank', 'asaas', 'sicoob', 'itau', 'whatsapp', 'relatorios', 'lojas',
            'usuarios', 'configuracoes',
        ];
        $acoes = ['visualizar', 'criar', 'editar', 'excluir'];

        if (Schema::hasTable('usuario_perfis')) {
            foreach (RentalSupport::PERFIS as $codigo => $nome) {
                DB::table('usuario_perfis')->updateOrInsert(
                    ['codigo' => $codigo],
                    [
                        'codigo' => $codigo,
                        'nome' => $nome,
                        'descricao' => RentalSupport::perfilDescricao($codigo),
                        'sistema' => true,
                        'status' => 'ativo',
                    ]
                );
                $perfilId = (int) DB::table('usuario_perfis')->where('codigo', $codigo)->value('id');
                DB::table('usuario_perfil_permissoes')->where('perfil_id', $perfilId)->delete();
                foreach (RentalSupport::perfilPermissoesPadrao($codigo) as $modulo => $permissoes) {
                    foreach (array_keys($permissoes) as $acao) {
                        DB::table('usuario_perfil_permissoes')->updateOrInsert(
                            ['perfil_id' => $perfilId, 'modulo' => $modulo, 'acao' => $acao],
                            ['perfil_id' => $perfilId, 'modulo' => $modulo, 'acao' => $acao]
                        );
                    }
                }
            }
        }

        foreach ($modulos as $modulo) {
            foreach ($acoes as $acao) {
                DB::table('usuario_permissoes')->updateOrInsert(
                    ['usuario_id' => $adminId, 'modulo' => $modulo, 'acao' => $acao],
                    ['usuario_id' => $adminId, 'modulo' => $modulo, 'acao' => $acao]
                );
            }
        }

        foreach (collect($lojasIniciais)->pluck('id') as $lojaId) {
            DB::table('usuario_lojas')->updateOrInsert(
                ['usuario_id' => $adminId, 'loja_id' => $lojaId],
                ['usuario_id' => $adminId, 'loja_id' => $lojaId]
            );
        }

        DB::table('whatsapp_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'verify_token' => RentalSupport::webhookToken('whatsapp'),
            'ativo' => 1,
        ]);

        DB::table('pagbank_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'merchant_reference' => config('branding.merchant_reference'),
        ]);

        DB::table('asaas_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'webhook_token' => RentalSupport::webhookToken('asaas'),
        ]);

        if (Schema::hasTable('sicoob_config')) {
            DB::table('sicoob_config')->insertOrIgnore([
                'id' => 1,
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'ativo' => 1,
                'api_base_url' => SicoobService::DEFAULT_API_BASE_URL,
                'token_url' => SicoobService::DEFAULT_TOKEN_URL,
                'webhook_token' => RentalSupport::webhookToken('sicoob'),
            ]);
        }

        if (Schema::hasTable('itau_config')) {
            DB::table('itau_config')->insertOrIgnore([
                'id' => 1,
                'modo' => 'demo',
                'ambiente' => 'producao',
                'ativo' => 1,
                'api_base_url' => ItauService::DEFAULT_API_BASE_URL,
                'token_url' => ItauService::DEFAULT_TOKEN_URL,
                'webhook_token' => RentalSupport::webhookToken('itau'),
            ]);
        }

        DB::table('pix_gateway_config')->insertOrIgnore([
            'id' => 1,
            'gateway' => 'pagbank',
        ]);
    }
}
