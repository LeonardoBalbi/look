<?php

namespace Tests\Feature;

use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalPlano;
use App\Models\User;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicencaPortalAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
    }

    public function test_admin_cria_cliente_plano_licenca_e_api_valida(): void
    {
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();

        $this->actingAs($admin)
            ->get('/licencas-portal')
            ->assertOk()
            ->assertSee('Portal de licencas LocX')
            ->assertSee('/api/licencas-portal');

        $licenca = $this->criarLicencaPeloPortal($admin);

        $this->postJson('/api/licencas-portal/validar-licenca', [
            'license_key' => $licenca->chave,
            'instance_id' => 'instancia-teste',
            'app' => 'locx',
            'version' => 'teste',
            'uso' => [
                'lojas' => 4,
                'usuarios' => 2,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ativa')
            ->assertJsonPath('plano', 'profissional')
            ->assertJsonPath('max_lojas', 5)
            ->assertJsonPath('max_usuarios', 20)
            ->assertJsonPath('modulos.0', 'pix');

        $this->assertDatabaseHas('licenca_portal_licencas', [
            'id' => $licenca->id,
            'instancia_id' => 'instancia-teste',
        ]);
        $this->assertDatabaseHas('licenca_portal_validacao_logs', [
            'licenca_id' => $licenca->id,
            'status' => 'ativa',
        ]);
    }

    public function test_api_recusa_licenca_de_outra_instancia(): void
    {
        $admin = User::where('email', 'admin@locx.com.br')->firstOrFail();
        $licenca = $this->criarLicencaPeloPortal($admin);

        $this->postJson('/api/licencas-portal/validar-licenca', [
            'license_key' => $licenca->chave,
            'instance_id' => 'instancia-teste',
            'app' => 'locx',
        ])->assertOk();

        $this->postJson('/api/licencas-portal/validar-licenca', [
            'license_key' => $licenca->chave,
            'instance_id' => 'outra-instancia',
            'app' => 'locx',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'bloqueada')
            ->assertJsonPath('mensagem', 'Licenca vinculada a outra instalacao.');
    }

    private function criarLicencaPeloPortal(User $admin): LicencaPortalLicenca
    {
        $this->actingAs($admin)
            ->post('/licencas-portal/clientes', [
                'nome' => 'Locadora Teste',
                'documento' => '00000000000100',
                'email' => 'cliente@example.com',
                'telefone' => '21999999999',
                'status' => 'ativo',
            ])
            ->assertRedirect();

        $cliente = LicencaPortalCliente::firstOrFail();

        $this->actingAs($admin)
            ->post('/licencas-portal/planos', [
                'codigo' => 'profissional',
                'nome' => 'Profissional',
                'preco' => '199.90',
                'max_lojas' => 5,
                'max_usuarios' => 20,
                'modulos' => 'pix, whatsapp, multi_loja, crm',
                'ativo' => '1',
            ])
            ->assertRedirect();

        $plano = LicencaPortalPlano::firstOrFail();

        $this->actingAs($admin)
            ->post('/licencas-portal/licencas', [
                'cliente_id' => $cliente->id,
                'plano_id' => $plano->id,
                'status' => 'ativa',
                'vence_em' => now()->addMonth()->format('Y-m-d'),
                'tolerancia_offline_dias' => 7,
                'mensagem' => 'Licenca liberada.',
            ])
            ->assertRedirect();

        return LicencaPortalLicenca::with('plano', 'cliente')->firstOrFail();
    }
}
