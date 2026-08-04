<?php

namespace Tests\Feature;

use App\Models\LicencaConfig;
use App\Models\Loja;
use App\Models\User;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicencaPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
    }

    public function test_admin_configura_e_valida_licenca_online(): void
    {
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();

        $this->actingAs($admin)
            ->get('/?page=configuracoes')
            ->assertOk()
            ->assertSee('Licenca LocX Cloud');

        $this->actingAs($admin)
            ->post('/configuracoes/licenca', [
                'modo' => 'online',
                'ativo' => '1',
                'empresa_nome' => 'Moto Facil',
                'empresa_documento' => '00000000000100',
                'api_url' => 'https://licencas.example.com/api',
                'licenca_chave' => 'LOCX-TESTE-123',
                'tolerancia_offline_dias' => 5,
            ])
            ->assertRedirect('/?page=configuracoes');

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
            ->assertRedirect('/?page=configuracoes');

        Http::assertSent(fn ($request) => $request->url() === 'https://licencas.example.com/api/validar-licenca'
            && $request['license_key'] === 'LOCX-TESTE-123'
            && $request['app'] === 'locx'
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

    public function test_licenca_bloqueada_permite_visualizar_mas_impede_salvar(): void
    {
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'bloqueada',
            'mensagem' => 'Pagamento pendente no portal.',
        ]);

        $this->actingAs($admin)
            ->get('/?page=lojas')
            ->assertOk();

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
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();
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
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'status' => 'ativa',
            'modulos_json' => ['pix'],
            'ultima_validacao_ok_em' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/?page=bancos')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/?page=whatsapp')
            ->assertForbidden();
    }

    public function test_comando_valida_licenca_online(): void
    {
        LicencaConfig::atual()->update([
            'modo' => 'online',
            'ativo' => true,
            'api_url' => 'https://licencas.example.com/api',
            'licenca_chave' => 'LOCX-COMANDO-123',
        ]);

        Http::fake([
            'https://licencas.example.com/api/validar-licenca' => Http::response([
                'status' => 'ativa',
                'plano' => 'basico',
                'mensagem' => 'Licenca ativa pelo comando.',
            ]),
        ]);

        $this->artisan('locx:validar-licenca --json')
            ->assertExitCode(0);

        $this->assertSame('ativa', LicencaConfig::atual()->status);
    }
}
