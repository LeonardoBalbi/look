<?php

namespace Tests\Feature;

use App\Models\AutomacaoLog;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Motocicleta;
use App\Services\AutomacaoService;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class N8nAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
        config([
            'n8n.enabled' => true,
            'n8n.token' => 'token-de-teste',
        ]);
    }

    public function test_api_exige_bearer_token(): void
    {
        $this->getJson('/api/n8n/status')->assertUnauthorized();

        $this->withToken('token-de-teste')
            ->getJson('/api/n8n/status')
            ->assertOk()
            ->assertJsonPath('enabled', true);
    }

    public function test_lembrete_e_criado_uma_unica_vez_e_executado_em_demo(): void
    {
        $cliente = Cliente::create([
            'loja_id' => 1,
            'nome' => 'Cliente Teste',
            'whatsapp' => '21999999999',
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::create([
            'loja_id' => 1,
            'modelo' => 'Moto Teste',
            'placa' => 'ABC1D23',
            'status_operacional' => 'alugada',
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
        $cobranca = Cobranca::create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => 1,
            'vencimento' => today()->addDays(3),
            'valor_principal' => 500,
            'valor_atualizado' => 500,
            'valor_pago' => 0,
            'status' => 'aberta',
            'pix_copia_cola' => 'PIX-TESTE',
        ]);

        $response = $this->withToken('token-de-teste')->getJson(
            '/api/n8n/automacoes/pendentes?tipo='.AutomacaoService::LEMBRETE_3_DIAS
        );
        $response->assertOk()->assertJsonCount(1, 'eventos');

        $this->withToken('token-de-teste')->getJson(
            '/api/n8n/automacoes/pendentes?tipo='.AutomacaoService::LEMBRETE_3_DIAS
        )->assertJsonCount(1, 'eventos');

        $evento = AutomacaoLog::where('cobranca_id', $cobranca->id)->firstOrFail();
        $this->withToken('token-de-teste')
            ->postJson('/api/n8n/automacoes/'.$evento->id.'/executar')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('automacao_logs', [
            'id' => $evento->id,
            'status' => 'concluido',
            'tentativas' => 1,
        ]);
    }
}
