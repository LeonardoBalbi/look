<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
    }

    public function test_admin_acessa_crm_e_registra_nota_e_tarefa(): void
    {
        $usuario = User::where('email', 'admin@locx.com.br')->firstOrFail();
        $cliente = Cliente::create([
            'loja_id' => 1,
            'nome' => 'Cliente CRM',
            'whatsapp' => '21999999999',
            'email' => 'cliente.crm@example.com',
            'status' => 'ativo',
            'crm_etapa' => 'lead',
        ]);

        $this->actingAs($usuario)
            ->get('/?page=crm&cliente='.$cliente->id)
            ->assertOk()
            ->assertSee('Clientes no CRM')
            ->assertSee('Cliente CRM');

        $this->actingAs($usuario)->post('/crm/notas', [
            'cliente_id' => $cliente->id,
            'tipo' => 'ligacao',
            'texto' => 'Cliente pediu reenvio do PIX.',
        ])->assertRedirect('/?page=crm&cliente='.$cliente->id);

        $this->assertDatabaseHas('crm_notas', [
            'cliente_id' => $cliente->id,
            'tipo' => 'ligacao',
            'texto' => 'Cliente pediu reenvio do PIX.',
        ]);

        $this->actingAs($usuario)->post('/crm/tarefas', [
            'cliente_id' => $cliente->id,
            'titulo' => 'Confirmar pagamento',
            'tipo' => 'cobranca',
            'prazo_em' => now()->addDay()->format('Y-m-d H:i:s'),
            'observacao' => 'Verificar retorno do cliente.',
        ])->assertRedirect('/?page=crm&cliente='.$cliente->id);

        $this->assertDatabaseHas('crm_tarefas', [
            'cliente_id' => $cliente->id,
            'titulo' => 'Confirmar pagamento',
            'status' => 'aberta',
        ]);
    }
}
