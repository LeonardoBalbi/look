<?php

namespace Tests\Feature;

use App\Models\LicencaConfig;
use App\Models\Loja;
use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicencaPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_admin_configura_e_valida_licenca_online(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->get('/painel?page=configuracoes')
            ->assertOk()
            ->assertSee('Licença de '.config('branding.product_name'));

        $this->actingAs($admin)
            ->post('/configuracoes/licenca', [
                'modo' => 'online',
                'ativo' => '1',
                'empresa_nome' => 'Moto Facil',
                'empresa_documento' => '00000000000100',
                'api_url' => 'https://licencas.example.com/api',
                'licenca_chave' => 'RENTAL-TESTE-123',
                'tolerancia_offline_dias' => 5,
            ])
            ->assertRedirect('/painel?page=configuracoes');

        Http::fake([
            'https://licencas.example.com/api/validar-licenca' => Http::response([
                'status' => 'ativa',
                'plano' => 'profissional',
                'empresa' => 'Moto Facil',
                'max_lojas' => 5,
                'max_usuarios' => 20,
                'modulos' => ['pix', 'whatsapp', 'multi_loja'],
                'vence_em' => now()->addMonth()->format('Y-m-d'),
                'tolerancia_offline_dias' => 5,
                'mensagem' => 'Licenca ativa.',
            ]),
        ]);

        $this->actingAs($admin)
            ->post('/configuracoes/licenca/testar')
            ->assertRedirect('/painel?page=configuracoes');

        Http::assertSent(fn ($request) => $request->url() === 'https://licencas.example.com/api/validar-licenca'
            && $request['license_key'] === 'RENTAL-TESTE-123'
            && $request['app'] === config('branding.product_id')
            && data_get($request->data(), 'uso.lojas') >= 4);

        $config = LicencaConfig::atual();
        $this->assertSame('ativa', $config->status);
        $this->assertSame('profissional', $config->plano);
        $this->assertSame(5, (int) $config->max_lojas);
        $this->assertSame(20, (int) $config->max_usuarios);
        $this->assertNotNull($config->ultima_validacao_ok_em);
        $this->assertDatabaseHas('licenca_validacao_logs', [
            'licenca_config_id' => $config->id,
            'status' => 'recebido',
            'http_code' => 200,
        ]);
    }

    public function test_licenca_bloqueada_impede_visualizar_e_salvar_modulos_operacionais(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'bloqueada',
            'mensagem' => 'Pagamento pendente no portal.',
        ]);

        $this->actingAs($admin)
            ->get('/painel?page=lojas')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/painel?page=dashboard')
            ->assertOk()
            ->assertSee('Pagamento pendente no portal.');

        $this->actingAs($admin)
            ->post('/lojas', [
                'nome' => 'Nova Loja',
                'cidade' => 'Rio de Janeiro',
                'status' => 'ativa',
            ])
            ->assertForbidden();
    }

    public function test_limite_de_lojas_da_licenca_impede_nova_loja(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'ativa',
            'max_lojas' => Loja::query()->count(),
            'ultima_validacao_ok_em' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/lojas', [
                'nome' => 'Loja Excedente',
                'cidade' => 'Rio de Janeiro',
                'status' => 'ativa',
            ])
            ->assertForbidden();
    }

    public function test_modulo_fora_do_plano_fica_bloqueado(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'ativa',
            'modulos_json' => ['pix'],
            'ultima_validacao_ok_em' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/painel?page=bancos')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/painel?page=whatsapp')
            ->assertForbidden();
    }

    public function test_super_admin_da_loja_tambem_obedece_a_licenca(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'bloqueada',
            'modulos_json' => ['pix'],
            'mensagem' => 'Licença restrita para usuários comerciais.',
        ]);

        $this->actingAs($superAdmin)
            ->get('/painel?page=whatsapp')
            ->assertForbidden();
    }

    public function test_comando_valida_licenca_online(): void
    {
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'api_url' => 'https://licencas.example.com/api',
            'licenca_chave' => 'RENTAL-COMANDO-123',
        ]);

        Http::fake([
            'https://licencas.example.com/api/validar-licenca' => Http::response([
                'status' => 'ativa',
                'plano' => 'basico',
                'mensagem' => 'Licenca ativa pelo comando.',
            ]),
        ]);

        $this->artisan('rental:validar-licenca --json')
            ->assertExitCode(0);

        $this->assertSame('ativa', LicencaConfig::atual()->status);
    }
}
