<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Support\Locx;
use Illuminate\Http\Client\ConnectionException;
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
                'template_language' => 'pt_BR',
                'template_lembrete' => 'locx_lembrete_vencimento',
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
            return [
                'ok' => true,
                'demo' => true,
                'mensagem' => 'Modo demo ativo: nenhuma credencial ou mensagem foi validada na Meta.',
            ];
        }
        if (! $config->waba_id || ! $config->phone_number_id || ! $config->access_token) {
            return [
                'ok' => false,
                'erro' => 'Informe o WABA ID, o Phone Number ID e o Access Token permanente da Meta.',
            ];
        }

        try {
            $response = $this->request('GET', $config->phone_number_id, null, [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'erro' => 'Não foi possível conectar à Meta: '.$exception->getMessage()];
        }

        if ($response->successful()) {
            $template = $this->validarTemplate();
            if (! ($template['ok'] ?? false)) {
                return $template;
            }

            return [
                'ok' => true,
                'mensagem' => 'Conexão e template validados com '
                    .($response->json('verified_name') ?: 'a conta WhatsApp').'.',
            ];
        }

        return $this->falhaDaMeta($response);
    }

    public function enviarCobranca(Cobranca $cobranca): array
    {
        $cobranca->loadMissing('cliente', 'contrato.motocicleta');
        $config = $this->config();
        $telefone = $this->normalizarTelefone($cobranca->cliente->whatsapp);
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

        if (! $telefone) {
            return $this->registrarFalha($cobranca, '', $mensagem, 'Telefone inválido');
        }
        if (! $config->ativo) {
            return $this->registrarFalha($cobranca, $telefone, $mensagem, 'WhatsApp API inativa');
        }
        if ($config->modo === 'demo') {
            $this->log($cobranca, $telefone, $mensagem, 'demo', 200, 'Envio simulado');
            $cobranca->update(['whatsapp_status' => 'demo']);

            return [
                'ok' => true,
                'demo' => true,
                'mensagem' => 'Envio simulado. Nenhuma mensagem foi enviada à Meta.',
            ];
        }
        if (! $config->phone_number_id || ! $config->access_token || ! $config->template_cobranca) {
            return $this->registrarFalha(
                $cobranca,
                $telefone,
                $mensagem,
                'Informe o Phone Number ID, o Access Token permanente e o template de cobrança.'
            );
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefone,
            'type' => 'template',
            'template' => [
                'name' => $config->template_cobranca,
                'language' => ['code' => $config->template_language ?: 'pt_BR'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'parameter_name' => 'customer_name',
                            'text' => $cobranca->cliente->nome,
                        ],
                        [
                            'type' => 'text',
                            'parameter_name' => 'vehicle_plate',
                            'text' => $placa ?: 'não informada',
                        ],
                        [
                            'type' => 'text',
                            'parameter_name' => 'days_overdue',
                            'text' => (string) $dias,
                        ],
                        [
                            'type' => 'text',
                            'parameter_name' => 'updated_balance',
                            'text' => Locx::moeda($saldo),
                        ],
                        [
                            'type' => 'text',
                            'parameter_name' => 'pix_code',
                            'text' => $cobranca->pix_copia_cola ?: 'não disponível',
                        ],
                    ],
                ]],
            ],
        ];
        try {
            $response = $this->request('POST', $config->phone_number_id.'/messages', $payload);
        } catch (ConnectionException $exception) {
            return $this->registrarFalha(
                $cobranca,
                $telefone,
                $mensagem,
                'Não foi possível conectar à Meta: '.$exception->getMessage()
            );
        }

        $ok = $response->successful();
        $falha = $ok ? null : $this->falhaDaMeta($response);
        $this->log(
            $cobranca,
            $telefone,
            $mensagem,
            $ok ? 'enviado' : 'erro',
            $response->status(),
            $response->body(),
            $falha['erro'] ?? null
        );

        if ($ok) {
            $cobranca->update(['whatsapp_status' => 'enviado']);
        }

        return $ok
            ? [
                'ok' => true,
                'http_code' => $response->status(),
                'mensagem' => 'Mensagem aceita pela Meta para envio.',
            ]
            : $falha;
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

        if (strlen($numero) === 10 || strlen($numero) === 11) {
            $numero = '55'.$numero;
        }

        return strlen($numero) >= 12 && strlen($numero) <= 15 ? $numero : '';
    }

    private function validarTemplate(): array
    {
        $config = $this->config();

        try {
            $response = $this->request('GET', $config->waba_id.'/message_templates', null, [
                'fields' => 'id,name,status,language,category',
                'name' => $config->template_cobranca,
                'limit' => 100,
            ]);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'erro' => 'Não foi possível consultar os templates na Meta: '.$exception->getMessage()];
        }

        if (! $response->successful()) {
            return $this->falhaDaMeta($response);
        }

        $idioma = $config->template_language ?: 'pt_BR';
        $template = collect($response->json('data', []))->first(
            fn (array $item) => ($item['name'] ?? null) === $config->template_cobranca
                && ($item['language'] ?? null) === $idioma
        );

        if (! $template) {
            return [
                'ok' => false,
                'erro' => "O template {$config->template_cobranca} não existe no idioma {$idioma}.",
            ];
        }

        if (($template['status'] ?? null) !== 'APPROVED') {
            return [
                'ok' => false,
                'erro' => "O template {$config->template_cobranca} está com status "
                    .($template['status'] ?? 'desconhecido').' na Meta.',
            ];
        }

        return ['ok' => true];
    }

    private function falhaDaMeta(Response $response): array
    {
        $code = (int) $response->json('error.code');
        $details = $response->json('error.error_data.details');
        $message = $response->json('error.message');

        $erro = match ($code) {
            190 => 'Access Token da Meta expirado ou inválido. Gere e salve um token permanente.',
            132001 => $details
                ?: 'O nome do template ou o idioma configurado não existe na conta da Meta.',
            default => $details ?: $message ?: 'A Meta recusou a solicitação.',
        };

        return [
            'ok' => false,
            'http_code' => $response->status(),
            'meta_code' => $code ?: null,
            'erro' => $erro,
        ];
    }

    private function registrarFalha(Cobranca $cobranca, string $telefone, string $mensagem, string $erro): array
    {
        $this->log($cobranca, $telefone, $mensagem, 'erro', null, null, $erro);

        return ['ok' => false, 'erro' => $erro];
    }

    private function log(
        Cobranca $cobranca,
        string $telefone,
        string $mensagem,
        string $status,
        ?int $httpCode = null,
        ?string $resposta = null,
        ?string $erro = null
    ): void {
        WhatsappLog::create([
            'cobranca_id' => $cobranca->id,
            'cliente_id' => $cobranca->cliente_id,
            'telefone' => $telefone,
            'tipo' => 'cobranca_inadimplencia',
            'mensagem' => $mensagem,
            'status' => $status,
            'http_code' => $httpCode,
            'resposta_api' => $resposta,
            'erro' => $erro,
            'criado_em' => now(),
        ]);
    }
}
