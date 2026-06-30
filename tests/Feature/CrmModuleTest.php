<?php

namespace Tests\Feature;

use App\Mail\PagamentoConfirmadoMail;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\CrmTarefa;
use App\Models\Motocicleta;
use App\Models\User;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
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

        $this->actingAs($usuario)->withSession(['_token' => 'token-teste'])->post('/crm/notas', [
            '_token' => 'token-teste',
            'cliente_id' => $cliente->id,
            'tipo' => 'ligacao',
            'texto' => 'Cliente pediu reenvio do PIX.',
        ])->assertRedirect('/?page=crm&cliente='.$cliente->id);

        $this->assertDatabaseHas('crm_notas', [
            'cliente_id' => $cliente->id,
            'tipo' => 'ligacao',
            'texto' => 'Cliente pediu reenvio do PIX.',
        ]);

        $this->actingAs($usuario)->withSession(['_token' => 'token-teste'])->post('/crm/tarefas', [
            '_token' => 'token-teste',
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

    public function test_sincronizacao_crm_cria_tarefas_para_cobrancas_em_atraso(): void
    {
        $cobranca = $this->cobrancaVencida(3);

        Artisan::call('locx:sincronizar-crm');

        $this->assertDatabaseHas('crm_tarefas', [
            'cliente_id' => $cobranca->cliente_id,
            'cobranca_id' => $cobranca->id,
            'titulo' => 'Cobrar cliente',
            'chave' => 'cobranca_atraso_1:'.$cobranca->id,
            'status' => 'aberta',
        ]);
        $this->assertDatabaseHas('crm_tarefas', [
            'cliente_id' => $cobranca->cliente_id,
            'cobranca_id' => $cobranca->id,
            'titulo' => 'Avisar gerente sobre atraso',
            'chave' => 'cobranca_atraso_3:'.$cobranca->id,
            'status' => 'aberta',
        ]);

        Artisan::call('locx:sincronizar-crm');

        $this->assertSame(2, CrmTarefa::where('cobranca_id', $cobranca->id)->count());
        $this->assertDatabaseHas('clientes', [
            'id' => $cobranca->cliente_id,
            'crm_etapa' => 'recuperacao',
        ]);
    }

    public function test_pagamento_confirmado_fecha_tarefa_de_cobranca_no_crm(): void
    {
        Mail::fake();
        $usuario = User::where('email', 'admin@locx.com.br')->firstOrFail();
        $cobranca = $this->cobrancaVencida(1);

        Artisan::call('locx:sincronizar-crm');

        $this->actingAs($usuario)->withSession(['_token' => 'token-teste'])->post('/pagamentos', [
            '_token' => 'token-teste',
            'cobranca_id' => $cobranca->id,
            'valor' => '250.00',
            'forma' => 'pix',
        ])->assertRedirect('/?page=financeiro');

        $this->assertDatabaseHas('cobrancas', [
            'id' => $cobranca->id,
            'status' => 'paga',
        ]);
        $this->assertDatabaseHas('crm_tarefas', [
            'cobranca_id' => $cobranca->id,
            'titulo' => 'Cobrar cliente',
            'status' => 'concluida',
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => $cobranca->cliente_id,
            'crm_etapa' => 'contrato_ativo',
        ]);
        Mail::assertSent(PagamentoConfirmadoMail::class, fn (PagamentoConfirmadoMail $mail) => $mail->hasTo($cobranca->cliente->email)
            && $mail->pagamento->cobranca_id === $cobranca->id);
    }

    private function cobrancaVencida(int $dias): Cobranca
    {
        $cliente = Cliente::create([
            'loja_id' => 1,
            'nome' => 'Cliente Atrasado '.$dias,
            'whatsapp' => '2198888888'.$dias,
            'email' => 'atrasado'.$dias.'@example.com',
            'status' => 'ativo',
            'crm_etapa' => 'contrato_ativo',
        ]);
        $moto = Motocicleta::create([
            'loja_id' => 1,
            'modelo' => 'Start '.$dias,
            'status_operacional' => 'alugada',
        ]);
        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => 1,
            'data_inicio' => today()->subMonth(),
            'valor_contratado' => 250,
            'forma_cobranca' => 'semanal',
            'status' => 'ativo',
        ]);

        return Cobranca::create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => 1,
            'vencimento' => today()->subDays($dias),
            'valor_principal' => 250,
            'valor_atualizado' => 250,
            'valor_pago' => 0,
            'status' => 'aberta',
            'whatsapp_status' => 'pendente',
        ]);
    }
}
