<?php

namespace Tests\Feature;

use App\Mail\CobrancaCriadaMail;
use App\Models\AsaasConfig;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Motocicleta;
use App\Models\PixGatewayConfig;
use App\Models\User;
use App\Services\EmailCobrancaService;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CobrancaRecorrenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
    }

    public function test_dry_run_nao_cria_cobranca_nem_chama_api(): void
    {
        Http::fake();
        $this->contrato();

        $code = Artisan::call('locx:gerar-cobrancas-recorrentes', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $code);
        $this->assertDatabaseCount('cobrancas', 0);
        Http::assertNothingSent();
        $this->assertStringContainsString('SIMULACAO', Artisan::output());
    }

    public function test_cria_cobranca_recorrente_sem_duplicar(): void
    {
        $contrato = $this->contrato(['forma_cobranca' => 'semanal']);

        $primeiraExecucao = Artisan::call('locx:gerar-cobrancas-recorrentes');
        $segundaExecucao = Artisan::call('locx:gerar-cobrancas-recorrentes');

        $this->assertSame(0, $primeiraExecucao);
        $this->assertSame(0, $segundaExecucao);
        $this->assertDatabaseCount('cobrancas', 1);
        $cobranca = Cobranca::query()->firstOrFail();
        $this->assertSame($contrato->id, $cobranca->contrato_id);
        $this->assertSame($contrato->cliente_id, $cobranca->cliente_id);
        $this->assertSame($contrato->loja_id, $cobranca->loja_id);
        $this->assertTrue($cobranca->vencimento->isSameDay(today()));
        $this->assertEquals(500, (float) $cobranca->valor_principal);
        $this->assertSame('aberta', $cobranca->status);
        $this->assertEquals(today()->addWeek()->format('Y-m-d'), $contrato->fresh()->proxima_cobranca_em->format('Y-m-d'));
    }

    public function test_gerar_pix_em_demo_nao_chama_api_externa(): void
    {
        Http::fake();
        PixGatewayConfig::query()->updateOrCreate(['id' => 1], ['gateway' => 'asaas']);
        AsaasConfig::query()->updateOrCreate(['id' => 1], [
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => true,
        ]);
        $contrato = $this->contrato();

        $code = Artisan::call('locx:gerar-cobrancas-recorrentes', [
            '--gerar-pix' => true,
        ]);

        $this->assertSame(0, $code);
        $cobranca = Cobranca::query()->where('contrato_id', $contrato->id)->firstOrFail();
        $this->assertStringContainsString('LOCX-ASAAS-DEMO-COBRANCA-'.$cobranca->id, $cobranca->pix_copia_cola);
        $this->assertSame('DEMO-'.$cobranca->id, $cobranca->asaas_id);
        Http::assertNothingSent();
    }

    public function test_envia_email_da_cobranca_sem_smtp_real(): void
    {
        Mail::fake();
        $contrato = $this->contrato();

        $code = Artisan::call('locx:gerar-cobrancas-recorrentes', [
            '--enviar-email' => true,
        ]);

        $this->assertSame(0, $code);
        $cobranca = Cobranca::query()->where('contrato_id', $contrato->id)->firstOrFail();
        Mail::assertSent(CobrancaCriadaMail::class, fn (CobrancaCriadaMail $mail) => $mail->hasTo('cliente@example.com')
            && $mail->cobranca->is($cobranca));
    }

    public function test_criacao_manual_envia_email_com_pix_para_cliente(): void
    {
        Mail::fake();
        Http::fake();
        PixGatewayConfig::query()->updateOrCreate(['id' => 1], ['gateway' => 'asaas']);
        AsaasConfig::query()->updateOrCreate(['id' => 1], [
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => true,
        ]);
        $contrato = $this->contrato();
        $usuario = User::where('email', 'admin@locx.com.br')->firstOrFail();

        $this->actingAs($usuario)
            ->withSession(['_token' => 'token-teste'])
            ->post('/cobrancas', [
                '_token' => 'token-teste',
                'contrato_id' => $contrato->id,
                'vencimento' => today()->format('Y-m-d'),
                'valor_principal' => 500,
            ])
            ->assertRedirect('/?page=financeiro');

        $cobranca = Cobranca::query()->where('contrato_id', $contrato->id)->firstOrFail();
        $this->assertStringContainsString('LOCX-ASAAS-DEMO-COBRANCA-'.$cobranca->id, $cobranca->pix_copia_cola);
        Mail::assertSent(CobrancaCriadaMail::class, fn (CobrancaCriadaMail $mail) => $mail->hasTo('cliente@example.com')
            && $mail->cobranca->is($cobranca));
    }

    public function test_falha_smtp_nao_interrompe_fluxo_da_cobranca(): void
    {
        $contrato = $this->contrato();
        $cobranca = Cobranca::query()->create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $contrato->cliente_id,
            'loja_id' => $contrato->loja_id,
            'vencimento' => today(),
            'valor_principal' => 500,
            'valor_atualizado' => 500,
            'valor_pago' => 0,
            'status' => 'aberta',
            'pix_copia_cola' => 'PIX-TESTE',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('cliente@example.com')
            ->andThrow(new RuntimeException('535 Incorrect authentication data'));

        $resultado = app(EmailCobrancaService::class)->enviarCobranca($cobranca);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('535 Incorrect authentication data', $resultado['erro']);
    }

    private function contrato(array $dados = []): Contrato
    {
        $cliente = Cliente::query()->create([
            'loja_id' => 1,
            'nome' => 'Cliente Recorrente',
            'cpf' => '12345678909',
            'whatsapp' => '21999999999',
            'email' => 'cliente@example.com',
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::query()->create([
            'loja_id' => 1,
            'modelo' => 'Moto Recorrente',
            'placa' => 'REC1D23',
            'status_operacional' => 'alugada',
        ]);

        return Contrato::query()->create(array_merge([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => 1,
            'data_inicio' => today(),
            'valor_contratado' => 500,
            'forma_cobranca' => 'semanal',
            'cobranca_automatica' => true,
            'proxima_cobranca_em' => today(),
            'status' => 'ativo',
        ], $dados));
    }
}
