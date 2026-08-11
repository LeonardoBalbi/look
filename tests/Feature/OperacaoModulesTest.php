<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Loja;
use App\Models\Motocicleta;
use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperacaoModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_admin_usa_manutencao_estoque_e_multas(): void
    {
        $usuario = User::where('email', 'admin@example.com')->firstOrFail();
        $cliente = Cliente::create([
            'loja_id' => 1,
            'nome' => 'Cliente Operacao',
            'whatsapp' => '21977777777',
            'email' => 'operacao@example.com',
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::create([
            'loja_id' => 1,
            'modelo' => 'CG 160',
            'placa' => 'RIO1A23',
            'status_operacional' => 'disponivel',
        ]);
        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => 1,
            'data_inicio' => today(),
            'valor_contratado' => 500,
            'forma_cobranca' => 'semanal',
            'status' => 'ativo',
        ]);

        $this->actingAs($usuario)->get('/?page=manutencao')->assertOk()->assertSee('Ordem de Serviço');
        $this->actingAs($usuario)->get('/?page=estoque')->assertOk()->assertSee('Produto');
        $this->actingAs($usuario)->get('/?page=multas')->assertOk()->assertSee('Multa');

        $this->actingAs($usuario)->post('/manutencao/ordens', [
            'loja_id' => 1,
            'motocicleta_id' => $moto->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'corretiva',
            'titulo' => 'Trocar relação',
            'status' => 'aberta',
            'prioridade' => 'normal',
            'custo_previsto' => 180,
        ])->assertRedirect('/?page=manutencao');

        $this->assertDatabaseHas('ordens_servico', [
            'motocicleta_id' => $moto->id,
            'titulo' => 'Trocar relação',
            'status' => 'aberta',
        ]);
        $this->assertDatabaseHas('motocicletas', [
            'id' => $moto->id,
            'status_operacional' => 'manutencao',
        ]);

        $this->actingAs($usuario)->post('/estoque/produtos', [
            'loja_id' => 1,
            'nome' => 'Pneu traseiro',
            'sku' => 'PNEU-TR',
            'unidade' => 'un',
            'estoque_minimo' => 2,
            'custo_unitario' => 180,
            'status' => 'ativo',
        ])->assertRedirect('/?page=estoque');

        $produtoId = (int) DB::table('estoque_produtos')->where('sku', 'PNEU-TR')->value('id');
        $this->actingAs($usuario)->post('/estoque/movimentos', [
            'produto_id' => $produtoId,
            'loja_id' => 1,
            'tipo' => 'entrada',
            'quantidade' => 4,
            'valor_unitario' => 180,
            'origem' => 'Compra',
        ])->assertRedirect('/?page=estoque');

        $this->assertDatabaseHas('estoque_movimentos', [
            'produto_id' => $produtoId,
            'tipo' => 'entrada',
            'quantidade' => 4,
        ]);

        $this->actingAs($usuario)->post('/multas', [
            'loja_id' => 1,
            'motocicleta_id' => $moto->id,
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'auto_infracao' => 'A123',
            'orgao' => 'Detran',
            'valor' => 195.23,
            'vencimento' => today()->addDays(10)->format('Y-m-d'),
            'ocorrida_em' => today()->format('Y-m-d'),
            'status' => 'aberta',
        ])->assertRedirect('/?page=multas');

        $this->assertDatabaseHas('multas_transito', [
            'motocicleta_id' => $moto->id,
            'cliente_id' => $cliente->id,
            'auto_infracao' => 'A123',
            'status' => 'aberta',
        ]);
    }

    public function test_admin_cria_e_edita_loja_pelo_painel(): void
    {
        $usuario = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($usuario)->get('/?page=lojas')
            ->assertOk()
            ->assertSee('Nova loja')
            ->assertSee('Controle por Loja');

        $this->actingAs($usuario)->post('/lojas', [
            'nome' => 'Loja Recreio',
            'cidade' => 'Rio de Janeiro',
            'status' => 'ativa',
        ])->assertRedirect();

        $loja = Loja::where('nome', 'Loja Recreio')->firstOrFail();
        $this->assertSame('Rio de Janeiro', $loja->cidade);
        $this->assertSame('ativa', $loja->status);

        $this->actingAs($usuario)->get('/?page=lojas&edit='.$loja->id)
            ->assertOk()
            ->assertSee('Editar loja')
            ->assertSee('Configurar bancos desta loja');

        $this->actingAs($usuario)->post('/lojas', [
            'id' => $loja->id,
            'nome' => 'Loja Recreio Prime',
            'cidade' => 'Rio de Janeiro',
            'status' => 'inativa',
        ])->assertRedirect();

        $this->assertDatabaseHas('lojas', [
            'id' => $loja->id,
            'nome' => 'Loja Recreio Prime',
            'cidade' => 'Rio de Janeiro',
            'status' => 'inativa',
        ]);
    }
}
