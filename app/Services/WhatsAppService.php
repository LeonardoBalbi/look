<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Support\Locx;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function config(): WhatsappConfig
    {
        return WhatsappConfig::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'demo',
                'ativo' => true,
                'verify_token' => 'locx_webhook_token',
                'template_cobranca' => 'locx_cobranca_atraso',
                'template_lembrete' => 'locx_lembrete_vencimento',
                'template_vencimento' => 'locx_vencimento_pix',
                'template_pagamento' => 'locx_pagamento_confirmado',
                'template_gerente' => 'locx_aviso_gerente',
                'template_bloqueio' => 'locx_aviso_bloqueio',
            ]
        );
    }

    public function graphVersion(): string
    {
        $version = (string) config('services.whatsapp.graph_version', 'v25.0');

        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v25.0';
    }

    public function testar(): array
    {
        $config = $this->config();
        if (! $config->ativo) {
            return ['ok' => false, 'erro' => 'WhatsApp API inativa.'];
        }
        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma chamada externa foi feita.'];
        }
        if (! $config->phone_number_id || ! $config->access_token) {
            return ['ok' => false, 'erro' => 'Informe o Phone Number ID e o Access Token da Meta.'];
        }

        $response = $this->request('GET', $config->phone_number_id, null, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating',
        ]);
        if ($response->successful()) {
            return [
                'ok' => true,
                'mensagem' => 'Conexão validada com '.($response->json('verified_name') ?: 'conta WhatsApp').'.',
            ];
        }

        return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
    }

    public function enviarCobranca(Cobranca $cobranca): array
    {
        $cobranca->loadMissing('cliente', 'contrato.motocicleta');
        $config = $this->config();
        $dias = $this->calculator->diasAtrasoAteDomingo($cobranca->vencimento);
        $saldo = $this->calculator->valorAtualizado(
            $cobranca->valor_principal,
            $cobranca->valor_pago,
            $cobranca->vencimento
        );
        $placa = $cobranca->contrato?->motocicleta?->placa;
        $mensagem = "Olá, {$cobranca->cliente->nome}. Identificamos uma pendência no seu contrato LocX"
            .($placa ? " referente à moto {$placa}" : '')
            .".\n\nDias em atraso: {$dias}\nSaldo atualizado: ".Locx::moeda($saldo)
            .($cobranca->pix_copia_cola ? "\n\nPIX copia e cola:\n{$cobranca->pix_copia_cola}" : '')
            ."\n\nRegularize o pagamento para evitar bloqueio e recolhimento da motocicleta.";

        $resultado = $this->enviarTemplate(
            telefone: $cobranca->cliente->whatsapp,
            template: $config->template_cobranca,
            parametros: [
                'customer_name' => $cobranca->cliente->nome,
                'vehicle_plate' => $placa ?: 'não informada',
                'days_overdue' => (string) $dias,
                'updated_balance' => Locx::moeda($saldo),
                'pix_code' => $cobranca->pix_copia_cola ?: 'não disponível',
            ],
            mensagem: $mensagem,
            tipo: 'cobranca_inadimplencia',
            cobranca: $cobranca,
        );

        if ($resultado['ok'] ?? false) {
            $cobranca->update(['whatsapp_status' => ! empty($resultado['demo']) ? 'demo' : 'enviado']);
        }

        return $resultado;
    }

    public function enviarTemplate(
        ?string $telefone,
        string $template,
        array $parametros,
        string $mensagem,
        string $tipo,
        ?Cobranca $cobranca = null,
    ): array {
        $config = $this->config();
        $telefone = $this->normalizarTelefone($telefone);

        if (! $telefone) {
            $this->log($cobranca, '', $mensagem, 'erro', null, null, 'Telefone inválido', $tipo);

            return ['ok' => false, 'erro' => 'Telefone inválido'];
        }
        if (! $config->ativo) {
            $this->log($cobranca, $telefone, $mensagem, 'erro', null, null, 'WhatsApp API inativa', $tipo);

            return ['ok' => false, 'erro' => 'WhatsApp API inativa'];
        }
        if ($config->modo === 'demo') {
            $this->log($cobranca, $telefone, $mensagem, 'demo', 200, 'Envio simulado', null, $tipo);

            return ['ok' => true, 'demo' => true];
        }
        if (! $config->phone_number_id || ! $config->access_token || ! $template) {
            $erro = 'Phone Number ID, Access Token ou template não configurado.';
            $this->log($cobranca, $telefone, $mensagem, 'erro', null, null, $erro, $tipo);

            return ['ok' => false, 'erro' => $erro];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => 'pt_BR'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => collect($parametros)
                        ->map(fn ($valor, $nome) => [
                            'type' => 'text',
                            'parameter_name' => $nome,
                            'text' => (string) $valor,
                        ])
                        ->values()
                        ->all(),
                ]],
            ],
        ];
        $response = $this->request('POST', $config->phone_number_id.'/messages', $payload);
        $ok = $response->successful();
        $this->log(
            $cobranca,
            $telefone,
            $mensagem,
            $ok ? 'enviado' : 'erro',
            $response->status(),
            $response->body(),
            $ok ? null : $response->body(),
            $tipo
        );

        return [
            'ok' => $ok,
            'http_code' => $response->status(),
            'erro' => $ok ? null : $response->body(),
        ];
    }

    public function registrarWebhook(string $raw): void
    {
        WhatsappLog::create([
            'tipo' => 'webhook',
            'mensagem' => 'Webhook recebido',
            'status' => 'recebido',
            'resposta_api' => $raw,
            'criado_em' => now(),
        ]);
    }

    private function request(string $method, string $path, ?array $payload = null, array $query = []): Response
    {
        $config = $this->config();

        return Http::acceptJson()
            ->withToken($config->access_token)
            ->timeout(30)
            ->send($method, 'https://graph.facebook.com/'.$this->graphVersion().'/'.ltrim($path, '/'), array_filter([
                'query' => $query ?: null,
                'json' => $payload,
            ]));
    }

    private function normalizarTelefone(?string $telefone): string
    {
        $numero = preg_replace('/\D+/', '', (string) $telefone);

        return $numero && strlen($numero) <= 11 ? '55'.$numero : $numero;
    }

    private function log(
        ?Cobranca $cobranca,
        string $telefone,
        string $mensagem,
        string $status,
        ?int $httpCode = null,
        ?string $resposta = null,
        ?string $erro = null,
        string $tipo = 'cobranca_inadimplencia',
    ): void {
        WhatsappLog::create([
            'cobranca_id' => $cobranca?->id,
            'cliente_id' => $cobranca?->cliente_id,
            'telefone' => $telefone,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'status' => $status,
            'http_code' => $httpCode,
            'resposta_api' => $resposta,
            'erro' => $erro,
            'criado_em' => now(),
        ]);
    }
}
