<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommercialReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('branding.product_name', 'Gestor Comercial');
        config()->set('branding.store_name', 'Moto Exemplo');
        config()->set('branding.use_locx_logo', false);
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_identidade_da_loja_e_produto_sao_independentes(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Moto Exemplo')
            ->assertSee('Gestor Comercial')
            ->assertDontSee('LocX')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_reserva_impede_conflito_da_mesma_moto(): void
    {
        $usuario = User::where('email', 'admin@example.com')->firstOrFail();
        $cliente = Cliente::create(['loja_id' => 1, 'nome' => 'Cliente Reserva', 'status' => 'ativo']);
        $moto = Motocicleta::create([
            'loja_id' => 1,
            'modelo' => 'Honda CG 160 Start',
            'placa' => 'RES1A23',
            'status_operacional' => 'disponivel',
        ]);

        $dados = [
            'loja_id' => 1,
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'retirada_em' => now()->addDay()->format('Y-m-d H:i:s'),
            'devolucao_em' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'valor_estimado' => 300,
            'caucao' => 200,
            'origem' => 'whatsapp',
            'status' => 'confirmada',
        ];

        $this->actingAs($usuario)->post(route('rental.reservas.salvar'), $dados)
            ->assertRedirect('/painel?page=reservas');
        $this->assertDatabaseHas('reservas', ['motocicleta_id' => $moto->id, 'status' => 'confirmada']);

        $this->actingAs($usuario)->from('/painel?page=reservas')->post(route('rental.reservas.salvar'), $dados)
            ->assertRedirect('/painel?page=reservas')
            ->assertSessionHasErrors('motocicleta_id');
        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental.reservas.salvar', 'actor_id' => $usuario->id]);
    }

    public function test_documentos_de_cliente_sao_gravados_no_disco_privado(): void
    {
        Storage::fake('local');
        $usuario = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($usuario)->post(route('rental.clientes.salvar'), [
            'loja_id' => 1,
            'nome' => 'Cliente Documento',
            'status' => 'ativo',
            'foto_documento' => UploadedFile::fake()->image('cnh.jpg'),
        ])->assertRedirect('/painel?page=clientes');

        $cliente = Cliente::where('nome', 'Cliente Documento')->firstOrFail();
        Storage::disk('local')->assertExists($cliente->foto_documento);

        $this->actingAs($usuario)
            ->get(route('rental.clientes.documentos.baixar', [$cliente, 'foto_documento']))
            ->assertOk();
    }
}
