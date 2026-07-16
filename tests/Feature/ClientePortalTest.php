<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Loja;
use App\Models\Motocicleta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_liberado_acessa_portal_e_ve_suas_faturas(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro', 'cidade' => 'Mangaratiba']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Portal',
            'cpf' => '12345678900',
            'email' => 'cliente@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::create([
            'loja_id' => $loja->id,
            'modelo' => 'CG 160',
            'placa' => 'ABC1D23',
            'status_operacional' => 'alugada',
        ]);
        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => $loja->id,
            'data_inicio' => today(),
            'valor_contratado' => 500,
            'forma_cobranca' => 'semanal',
            'status' => 'ativo',
        ]);
        Cobranca::create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => $loja->id,
            'vencimento' => today(),
            'valor_principal' => 500,
            'valor_atualizado' => 500,
            'valor_pago' => 0,
            'status' => 'aberta',
            'pix_copia_cola' => 'PIX-COPIA-E-COLA',
        ]);

        $this->post('/portal/login', [
            'email' => 'cliente@locx.test',
            'senha' => '123456',
        ])->assertRedirect('/portal');

        $this->assertAuthenticatedAs($cliente, 'cliente');
        $this->get('/portal')
            ->assertOk()
            ->assertSee('Fatura #1')
            ->assertSee('PIX-COPIA-E-COLA')
            ->assertSee('ABC1D23');
    }

    public function test_cliente_sem_portal_liberado_nao_autentica(): void
    {
        Cliente::create([
            'nome' => 'Cliente Bloqueado',
            'email' => 'bloqueado@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => false,
            'status' => 'ativo',
        ]);

        $this->post('/portal/login', [
            'email' => 'bloqueado@locx.test',
            'senha' => '123456',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('cliente');
    }
}
