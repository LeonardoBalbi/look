<?php

namespace Tests\Feature;

use App\Mail\PagamentoConfirmadoMail;
use App\Models\AsaasConfig;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Motocicleta;
use App\Models\User;
use App\Services\AutomacaoService;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PixConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
        Mail::fake();
    }

    public function test_conciliacao_asaas_baixa_pix_pago_sem_webhook_e_nao_duplica(): void
    {
        config([
            'n8n.enabled' => true,
            'n8n.token' => 'token-de-teste',
        ]);
        AsaasConfig::query()->updateOrCreate(['id' => 1], [
            'modo' => 'api',
            'ambiente' => 'producao',
            'ativo' => true,
            'api_key' => 'token-teste',
        ]);
        $cobranca = $this->cobranca(['asaas_id' => 'pay_ok']);

        Http::fake([
            'https://api.asaas.com/v3/payments/pay_ok' => Http::response([
                'id' => 'pay_ok',
                'status' => 'RECEIVED',
                'value' => 50.00,
            ]),
        ]);

        $primeiraExecucao = Artisan::call('locx:conciliar-pix');
        $segundaExecucao = Artisan::call('locx:conciliar-pix');

        $this->assertSame(0, $primeiraExecucao);
        $this->assertSame(0, $segundaExecucao);
        $this->assertDatabaseHas('cobrancas', [
            'id' => $cobranca->id,
            'valor_pago' => 50.00,
            'status' => 'paga',
            'asaas_status' => 'ASAAS_RECEIVED',
            'whatsapp_status' => 'conciliado',
        ]);
        $this->assertDatabaseCount('pagamentos', 1);
        $this->assertDatabaseHas('pagamentos', [
            'cobranca_id' => $cobranca->id,
            'valor' => 50.00,
            'forma' => 'pix',
            'comprovante' => 'Asaas ASAAS_RECEIVED',
        ]);
        Mail::assertSent(PagamentoConfirmadoMail::class, 1);
        Mail::assertSent(PagamentoConfirmadoMail::class, fn (PagamentoConfirmadoMail $mail) => $mail->hasTo('cliente-pix@example.com')
            && $mail->pagamento->cobranca_id === $cobranca->id);

        $this->withToken('token-de-teste')
            ->getJson('/api/n8n/automacoes/pendentes?tipo='.AutomacaoService::PAGAMENTO_CONFIRMADO)
            ->assertOk()
            ->assertJsonCount(1, 'eventos')
            ->assertJsonPath('eventos.0.tipo', AutomacaoService::PAGAMENTO_CONFIRMADO)
            ->assertJsonPath('eventos.0.cobranca_id', $cobranca->id);
    }

    public function test_botao_conciliar_pix_baixa_pagamento_ja_confirmado_no_gateway(): void
    {
        AsaasConfig::query()->updateOrCreate(['id' => 1], [
            'modo' => 'api',
            'ambiente' => 'producao',
            'ativo' => true,
            'api_key' => 'token-teste',
        ]);
        $cobranca = $this->cobranca(['asaas_id' => 'pay_ok_botao']);

        Http::fake([
            'https://api.asaas.com/v3/payments/pay_ok_botao' => Http::response([
                'id' => 'pay_ok_botao',
                'status' => 'RECEIVED',
                'value' => 50.00,
            ]),
        ]);

        $usuario = User::where('email', 'admin@locx.com.br')->firstOrFail();

        $this->actingAs($usuario)
            ->withSession(['_token' => 'token-teste'])
            ->post('/pix/conciliar', ['page' => 'pix', '_token' => 'token-teste'])
            ->assertRedirect('/?page=pix');

        $this->assertDatabaseHas('cobrancas', [
            'id' => $cobranca->id,
            'valor_pago' => 50.00,
            'status' => 'paga',
            'asaas_status' => 'ASAAS_RECEIVED',
            'whatsapp_status' => 'conciliado',
        ]);
        $this->assertDatabaseHas('pagamentos', [
            'cobranca_id' => $cobranca->id,
            'valor' => 50.00,
            'forma' => 'pix',
            'comprovante' => 'Asaas ASAAS_RECEIVED',
        ]);
        Mail::assertSent(PagamentoConfirmadoMail::class, 1);
    }

    private function cobranca(array $dados = []): Cobranca
    {
        $cliente = Cliente::query()->create([
            'loja_id' => 1,
            'nome' => 'Cliente PIX',
            'cpf' => '12345678909',
            'whatsapp' => '21999999999',
            'email' => 'cliente-pix@example.com',
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::query()->create([
            'loja_id' => 1,
            'modelo' => 'Moto PIX',
            'placa' => 'PIX1D23',
            'status_operacional' => 'alugada',
        ]);
        $contrato = Contrato::query()->create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => 1,
            'data_inicio' => today(),
            'valor_contratado' => 50,
            'forma_cobranca' => 'semanal',
            'cobranca_automatica' => true,
            'proxima_cobranca_em' => today(),
            'status' => 'ativo',
        ]);

        return Cobranca::query()->create(array_merge([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => 1,
            'vencimento' => today(),
            'valor_principal' => 50,
            'valor_atualizado' => 50,
            'valor_pago' => 0,
            'status' => 'aberta',
            'whatsapp_status' => 'pendente',
            'pix_copia_cola' => 'PIX-TESTE',
            'asaas_status' => 'PENDING',
        ], $dados));
    }
}
