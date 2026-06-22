<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Motocicleta;
use App\Models\WhatsappConfig;
use App\Services\WhatsAppService;
use Database\Seeders\LocxInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocxInitialSeeder::class);
    }

    public function test_modo_demo_nao_chama_a_meta_e_informa_que_o_envio_foi_simulado(): void
    {
        Http::fake();

        $resultado = app(WhatsAppService::class)->enviarCobranca($this->cobranca());

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['demo']);
        $this->assertStringContainsString('simulado', $resultado['mensagem']);
        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_logs', ['status' => 'demo']);
    }

    public function test_erro_de_token_expirado_e_exibido_de_forma_clara(): void
    {
        $this->configurarOficial();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Session has expired', 'code' => 190],
            ], 401),
        ]);

        $resultado = app(WhatsAppService::class)->testar();

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('expirado ou inválido', $resultado['erro']);
    }

    public function test_validacao_confere_nome_idioma_e_aprovacao_do_template(): void
    {
        $this->configurarOficial();
        Http::fake([
            '*/1114788988392842*' => Http::response([
                'id' => '1114788988392842',
                'verified_name' => 'LocX',
            ]),
            '*/3521532264662957/message_templates*' => Http::response([
                'data' => [[
                    'id' => 'template-id',
                    'name' => 'locx_cobranca_atraso',
                    'language' => 'pt_BR',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                ]],
            ]),
        ]);

        $resultado = app(WhatsAppService::class)->testar();

        $this->assertTrue($resultado['ok']);
        $this->assertStringContainsString('template validados', $resultado['mensagem']);
    }

    public function test_envio_oficial_usa_o_idioma_configurado(): void
    {
        $this->configurarOficial(['template_language' => 'en_US']);
        Http::fake([
            '*/messages' => Http::response([
                'messages' => [['id' => 'wamid.123']],
            ]),
        ]);

        $resultado = app(WhatsAppService::class)->enviarCobranca($this->cobranca());

        $this->assertTrue($resultado['ok']);
        Http::assertSent(fn ($request) => $request['template']['language']['code'] === 'en_US'
            && $request['template']['name'] === 'locx_cobranca_atraso');
    }

    private function configurarOficial(array $dados = []): void
    {
        WhatsappConfig::query()->findOrFail(1)->update(array_merge([
            'modo' => 'oficial',
            'ativo' => true,
            'waba_id' => '3521532264662957',
            'phone_number_id' => '1114788988392842',
            'access_token' => 'token-permanente',
            'template_cobranca' => 'locx_cobranca_atraso',
            'template_language' => 'pt_BR',
        ], $dados));
    }

    private function cobranca(): Cobranca
    {
        $cliente = Cliente::query()->create([
            'loja_id' => 1,
            'nome' => 'Cliente Teste',
            'whatsapp' => '21999999999',
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::query()->create([
            'loja_id' => 1,
            'modelo' => 'Moto Teste',
            'placa' => 'ABC1D23',
            'status_operacional' => 'alugada',
        ]);
        $contrato = Contrato::query()->create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => 1,
            'data_inicio' => today(),
            'valor_contratado' => 500,
            'forma_cobranca' => 'semanal',
            'status' => 'ativo',
        ]);

        return Cobranca::query()->create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => 1,
            'vencimento' => today()->subDay(),
            'valor_principal' => 500,
            'valor_atualizado' => 500,
            'valor_pago' => 0,
            'status' => 'atrasada',
            'pix_copia_cola' => 'PIX-TESTE',
        ]);
    }
}
