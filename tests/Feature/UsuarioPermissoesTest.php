<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Models\UsuarioPermissao;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioPermissoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_super_admin_cria_perfil_e_administrador_geral_atribui_ao_usuario(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($superAdmin)->post('/usuarios/perfis', [
            'nome' => 'Supervisor de Patio',
            'codigo' => 'supervisor_patio',
            'descricao' => 'Cuida da frota e da operacao da loja.',
            'status' => 'ativo',
            'perms' => [
                'motos' => ['visualizar' => '1', 'editar' => '1'],
                'manutencao' => ['visualizar' => '1', 'criar' => '1'],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->post('/usuarios/perfis', [
            'nome' => 'Perfil Bloqueado',
            'codigo' => 'perfil_bloqueado',
            'status' => 'ativo',
            'perms' => ['usuarios' => ['editar' => '1']],
        ])->assertForbidden();

        $this->actingAs($admin)->post('/usuarios', [
            'nome' => 'Super Indevido',
            'email' => 'super.indevido@rental.test',
            'senha' => '123456',
            'perfil' => 'super_admin',
            'status' => 'ativo',
        ])->assertForbidden();

        $this->actingAs($admin)->post('/usuarios', [
            'nome' => 'Supervisor Padrao',
            'email' => 'supervisor.padrao@rental.test',
            'senha' => '123456',
            'perfil' => 'supervisor_patio',
            'status' => 'ativo',
        ])->assertRedirect('/?page=usuarios');

        $usuario = User::where('email', 'supervisor.padrao@rental.test')->firstOrFail();

        $this->assertTrue($usuario->pode('motos', 'editar'));
        $this->assertTrue($usuario->pode('manutencao', 'criar'));
        $this->assertFalse($usuario->pode('usuarios', 'editar'));
        $this->assertFalse($usuario->pode('financeiro', 'editar'));

        $this->actingAs($admin)
            ->get('/?page=usuarios')
            ->assertOk()
            ->assertDontSee('Super Admin Rental')
            ->assertDontSee('superadmin@example.com')
            ->assertDontSee('Permissoes por modulo')
            ->assertDontSee('Salvar perfil');

        $this->actingAs($admin)
            ->get('/?page=usuarios&edit='.$superAdmin->id)
            ->assertNotFound();
    }

    public function test_apenas_super_admin_salva_permissionamento_de_usuarios(): void
    {
        $diretor = User::create([
            'nome' => 'Diretor',
            'email' => 'diretor@rental.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'diretor',
            'status' => 'ativo',
        ]);
        foreach (['visualizar', 'criar', 'editar'] as $acao) {
            UsuarioPermissao::create([
                'usuario_id' => $diretor->id,
                'modulo' => 'usuarios',
                'acao' => $acao,
            ]);
        }

        $this->actingAs($diretor)->post('/usuarios', [
            'nome' => 'Tentativa',
            'email' => 'tentativa@rental.test',
            'senha' => '123456',
            'perfil' => 'atendente',
            'status' => 'ativo',
        ])->assertForbidden();

        $this->actingAs($diretor)
            ->get('/?page=usuarios')
            ->assertForbidden();
    }

    public function test_atendente_abre_modulos_de_consulta_sem_acoes_proibidas(): void
    {
        $atendente = User::create([
            'nome' => 'Atendente',
            'email' => 'atendente@rental.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'atendente',
            'status' => 'ativo',
        ]);

        $this->actingAs($atendente)
            ->get('/?page=dashboard')
            ->assertOk()
            ->assertSee('page=motos', false)
            ->assertDontSee('page=usuarios', false)
            ->assertDontSee('page=financeiro', false);

        $this->actingAs($atendente)
            ->get('/?page=motos')
            ->assertOk()
            ->assertSee('Frota Cadastrada')
            ->assertDontSee('Salvar Moto');

        $this->actingAs($atendente)
            ->get('/?page=cobrancas')
            ->assertOk()
            ->assertSee('Este perfil consulta cobran')
            ->assertDontSee('Gerar cobran')
            ->assertDontSee('Nova campanha');
    }

    public function test_loja_principal_libera_motos_no_novo_contrato_do_atendente(): void
    {
        $perfil = UsuarioPerfil::where('codigo', 'atendente')->firstOrFail();
        $perfil->permissoes()->create([
            'modulo' => 'motos',
            'acao' => 'criar',
        ]);
        $perfil->permissoes()->create([
            'modulo' => 'contratos',
            'acao' => 'criar',
        ]);

        Motocicleta::create([
            'loja_id' => 1,
            'marca' => 'Honda',
            'modelo' => 'Honda CG 160 Fan Flex',
            'placa' => 'LOC1234',
            'status_operacional' => 'disponivel',
        ]);
        $motoForaDoAcesso = Motocicleta::create([
            'loja_id' => 2,
            'marca' => 'Yamaha',
            'modelo' => 'Yamaha YBR 150 Factor Flex',
            'placa' => 'FORA999',
            'status_operacional' => 'disponivel',
        ]);
        $cliente = Cliente::create([
            'loja_id' => 1,
            'nome' => 'Cliente Loja 1',
            'status' => 'ativo',
        ]);

        $atendente = User::create([
            'nome' => 'Atendente Loja',
            'email' => 'atendente.loja@rental.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'atendente',
            'loja_id' => 1,
            'status' => 'ativo',
        ]);

        $this->assertSame([1], $atendente->lojaIdsPermitidas());

        $this->actingAs($atendente)
            ->get('/?page=motos')
            ->assertOk()
            ->assertSee('Nova')
            ->assertSee('Barra da Tijuca')
            ->assertDontSee('Campo Grande');

        $this->actingAs($atendente)
            ->post('/motos', [
                'loja_id' => 2,
                'marca' => 'Honda',
                'modelo' => 'Honda CG 160 Fan Flex',
                'placa' => 'BLOQ222',
                'status_operacional' => 'disponivel',
            ])
            ->assertForbidden();

        $this->actingAs($atendente)
            ->get('/?page=contratos')
            ->assertOk()
            ->assertSee('Novo Contrato')
            ->assertSee('LOC1234')
            ->assertDontSee('FORA999')
            ->assertDontSee('Campo Grande');

        $this->actingAs($atendente)
            ->post('/contratos', [
                'cliente_id' => $cliente->id,
                'motocicleta_id' => $motoForaDoAcesso->id,
                'loja_id' => 2,
                'data_inicio' => today()->format('Y-m-d'),
                'valor_contratado' => '500.00',
                'forma_cobranca' => 'semanal',
                'status' => 'ativo',
            ])
            ->assertForbidden();
    }

    public function test_administrador_geral_obedece_permissoes_do_perfil(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $perfil = UsuarioPerfil::where('codigo', 'administrador_geral')->firstOrFail();

        $perfil->permissoes()->where('modulo', 'configuracoes')->delete();

        $this->assertFalse($admin->fresh()->pode('configuracoes'));
        $this->assertTrue($admin->fresh()->pode('usuarios'));

        $this->actingAs($admin->fresh())
            ->get('/?page=dashboard')
            ->assertOk()
            ->assertDontSee('Configurações');

        $this->actingAs($admin->fresh())
            ->get('/?page=configuracoes')
            ->assertForbidden();

        $this->actingAs($admin->fresh())
            ->post('/configuracoes/gateway-pix', ['gateway' => 'pagbank'])
            ->assertForbidden();
    }
}
