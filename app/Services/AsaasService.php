<?php

namespace App\Services;

use App\Models\AsaasConfig;
use App\Models\AsaasLog;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Pagamento;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AsaasService
{
    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function config(): AsaasConfig
    {
        return AsaasConfig::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'ativo' => true,
                'webhook_token' => 'locx_asaas_webhook_token',
                'webhook_url' => route('locx.webhook-asaas'),
            ]
        );
    }

    public function testar(): array
    {
        $config = $this->config();

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma chamada externa foi feita.'];
        }

        if (! $config->api_key) {
            return ['ok' => false, 'http_code' => 0, 'erro' => 'API Key Asaas nao configurada.'];
        }

        $response = $this->request('GET', '/myAccount');

        if ($response->successful()) {
            return [
                'ok' => true,
                'http_code' => $response->status(),
                'mensagem' => 'A API Asaas respondeu e aceitou a autenticacao.',
            ];
        }

        return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
    }

    public function criarPix(Cobranca $cobranca): array
    {
        $cobranca->loadMissing('cliente');
        $config = $this->config();
        $valor = $this->calculator->valorAtualizado(
            $cobranca->valor_principal,
            $cobranca->valor_pago,
            $cobranca->vencimento
        );

        if (! $config->ativo) {
            return ['ok' => false, 'erro' => 'Integracao Asaas inativa.'];
        }

        if ($config->modo === 'demo') {
            $pix = '00020126580014BR.GOV.BCB.PIX0136LOCX-ASAAS-DEMO-COBRANCA-'.$cobranca->id
                .'520400005303986540'.number_format($valor, 2, '.', '')
                .'5802BR5904LOCX6009MANGARATIBA62070503***6304DEMO';

            $cobranca->update([
                'pix_copia_cola' => $pix,
                'pix_qrcode' => $pix,
                'asaas_id' => 'DEMO-'.$cobranca->id,
                'asaas_status' => 'DEMO',
                'asaas_payload' => 'PIX demo gerado pelo LocX',
                'atualizado_em' => now(),
            ]);
            $this->log($cobranca->id, 'criar_pix', 'demo', 200, 'demo', $pix);

            return ['ok' => true, 'demo' => true, 'pix' => $pix];
        }

        if (! $config->api_key) {
            return ['ok' => false, 'http_code' => 0, 'erro' => 'API Key Asaas nao configurada.'];
        }

        $documento = preg_replace('/\D+/', '', (string) $cobranca->cliente->cpf);
        if (! $this->documentoValido($documento)) {
            return ['ok' => false, 'erro' => 'O CPF/CNPJ do cliente e invalido. Edite o cadastro do cliente e informe um CPF ou CNPJ real.'];
        }
        if ($valor <= 0) {
            return ['ok' => false, 'erro' => 'O valor da cobranca precisa ser maior que zero.'];
        }

        $cliente = $this->clienteAsaas($cobranca->cliente, $documento);
        if (! ($cliente['ok'] ?? false)) {
            return $cliente;
        }

        $reference = 'LOCX-COBRANCA-'.$cobranca->id;
        $dueDate = $cobranca->vencimento->isPast() ? today() : $cobranca->vencimento;
        $payload = [
            'customer' => $cliente['customer_id'],
            'billingType' => 'PIX',
            'value' => round($valor, 2),
            'dueDate' => $dueDate->format('Y-m-d'),
            'description' => 'Cobranca LocX #'.$cobranca->id,
            'externalReference' => $reference,
        ];

        $payment = $this->request('POST', '/payments', $payload);
        $this->log(
            $cobranca->id,
            'criar_cobranca',
            $payment->successful() ? 'enviado' : 'erro',
            $payment->status(),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $payment->body(),
            $payment->successful() ? null : $payment->body()
        );

        if (! $payment->successful()) {
            return ['ok' => false, 'http_code' => $payment->status(), 'erro' => $payment->body()];
        }

        $paymentJson = $payment->json();
        $paymentId = $paymentJson['id'] ?? null;
        if (! $paymentId) {
            return ['ok' => false, 'http_code' => $payment->status(), 'erro' => 'Asaas nao retornou o ID da cobranca.'];
        }

        $qrCode = $this->request('GET', '/payments/'.$paymentId.'/pixQrCode');
        $this->log(
            $cobranca->id,
            'pix_qrcode',
            $qrCode->successful() ? 'recebido' : 'erro',
            $qrCode->status(),
            null,
            $qrCode->body(),
            $qrCode->successful() ? null : $qrCode->body()
        );

        if (! $qrCode->successful()) {
            return ['ok' => false, 'http_code' => $qrCode->status(), 'erro' => $qrCode->body()];
        }

        $qrJson = $qrCode->json();
        $pix = $qrJson['payload'] ?? '';
        $imagem = isset($qrJson['encodedImage']) ? 'data:image/png;base64,'.$qrJson['encodedImage'] : $pix;

        $cobranca->update([
            'pix_copia_cola' => $pix,
            'pix_qrcode' => $imagem,
            'asaas_id' => $paymentId,
            'asaas_status' => $paymentJson['status'] ?? 'PENDING',
            'asaas_payload' => json_encode(['payment' => $paymentJson, 'pixQrCode' => $qrJson], JSON_UNESCAPED_UNICODE),
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'pix' => $pix, 'payment_id' => $paymentId];
    }

    public function validarWebhook(?string $token): bool
    {
        $esperado = (string) $this->config()->webhook_token;

        return $esperado === '' || hash_equals($esperado, (string) $token);
    }

    public function processarWebhook(string $raw): array
    {
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return ['ok' => false, 'erro' => 'JSON invalido'];
        }

        $payment = $json['payment'] ?? [];
        $paymentId = $payment['id'] ?? '';
        $status = $payment['status'] ?? $json['event'] ?? '';
        $reference = $payment['externalReference'] ?? '';
        $cobrancaId = preg_match('/LOCX-COBRANCA-(\d+)/', $reference, $match)
            ? (int) $match[1]
            : null;
        $cobranca = $cobrancaId
            ? Cobranca::find($cobrancaId)
            : Cobranca::where('asaas_id', $paymentId)->first();

        $this->log($cobranca?->id, 'webhook', $status ?: 'recebido', 200, $raw, 'webhook recebido');
        if (! $cobranca) {
            return ['ok' => false, 'erro' => 'Cobranca nao localizada'];
        }

        if (in_array(strtoupper($status), ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            $valor = (float) ($payment['value'] ?? $payment['netValue'] ?? 0);
            $this->baixar($cobranca, $valor, 'ASAAS_'.$status);

            return ['ok' => true, 'baixado' => true];
        }

        $cobranca->update(['asaas_status' => $status, 'atualizado_em' => now()]);

        return ['ok' => true, 'baixado' => false, 'status' => $status];
    }

    private function clienteAsaas(Cliente $cliente, string $documento): array
    {
        if ($cliente->asaas_customer_id) {
            return ['ok' => true, 'customer_id' => $cliente->asaas_customer_id];
        }

        $busca = $this->request('GET', '/customers', null, ['cpfCnpj' => $documento, 'limit' => 1]);
        if ($busca->successful() && ($customerId = data_get($busca->json(), 'data.0.id'))) {
            $cliente->update(['asaas_customer_id' => $customerId]);

            return ['ok' => true, 'customer_id' => $customerId];
        }

        $payload = array_filter([
            'name' => $cliente->nome ?: 'Cliente LocX',
            'cpfCnpj' => $documento,
            'email' => $cliente->email,
            'phone' => $cliente->telefone,
            'mobilePhone' => $cliente->whatsapp ?: $cliente->telefone,
            'externalReference' => 'LOCX-CLIENTE-'.$cliente->id,
            'notificationDisabled' => true,
        ], fn ($valor) => $valor !== null && $valor !== '');

        $response = $this->request('POST', '/customers', $payload);
        $this->log(
            null,
            'criar_cliente',
            $response->successful() ? 'enviado' : 'erro',
            $response->status(),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $response->body(),
            $response->successful() ? null : $response->body()
        );

        if (! $response->successful()) {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
        }

        $customerId = $response->json('id');
        $cliente->update(['asaas_customer_id' => $customerId]);

        return ['ok' => true, 'customer_id' => $customerId];
    }

    private function documentoValido(string $documento): bool
    {
        return match (strlen($documento)) {
            11 => $this->cpfValido($documento),
            14 => $this->cnpjValido($documento),
            default => false,
        };
    }

    private function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digito = 9; $digito < 11; $digito++) {
            $soma = 0;
            for ($i = 0; $i < $digito; $i++) {
                $soma += (int) $cpf[$i] * (($digito + 1) - $i);
            }
            $verificador = ((10 * $soma) % 11) % 10;
            if ($verificador !== (int) $cpf[$digito]) {
                return false;
            }
        }

        return true;
    }

    private function cnpjValido(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ([12, 13] as $indice => $tamanho) {
            $soma = 0;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$indice][$i];
            }
            $resto = $soma % 11;
            $verificador = $resto < 2 ? 0 : 11 - $resto;
            if ($verificador !== (int) $cnpj[$tamanho]) {
                return false;
            }
        }

        return true;
    }

    private function baixar(Cobranca $cobranca, float $valor, string $statusAsaas): void
    {
        DB::transaction(function () use ($cobranca, $valor, $statusAsaas): void {
            if (Pagamento::where('cobranca_id', $cobranca->id)->where('comprovante', 'like', 'Asaas %')->exists()) {
                $cobranca->update(['asaas_status' => $statusAsaas, 'atualizado_em' => now()]);

                return;
            }

            $valor = $valor > 0 ? $valor : max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
            $novoPago = (float) $cobranca->valor_pago + $valor;
            $status = $novoPago >= (float) $cobranca->valor_principal ? 'paga' : 'parcial';

            Pagamento::create([
                'cobranca_id' => $cobranca->id,
                'valor' => $valor,
                'forma' => 'pix',
                'pago_em' => now(),
                'comprovante' => 'Asaas '.$statusAsaas,
            ]);
            $cobranca->update([
                'valor_pago' => $novoPago,
                'status' => $status,
                'asaas_status' => $statusAsaas,
                'whatsapp_status' => $status === 'paga' ? 'conciliado' : $cobranca->whatsapp_status,
                'atualizado_em' => now(),
            ]);

            if ($status === 'paga') {
                app(CrmAutomationService::class)->fecharTarefasDeCobranca($cobranca->fresh('cliente'));
            }
        });
    }

    private function request(string $method, string $path, ?array $payload = null, array $query = []): Response
    {
        $config = $this->config();
        $base = $config->ambiente === 'producao'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';

        return Http::acceptJson()
            ->withHeaders(['access_token' => $config->api_key])
            ->timeout(40)
            ->send($method, $base.$path, array_filter([
                'query' => $query ?: null,
                'json' => $payload,
            ]));
    }

    private function log(
        ?int $cobrancaId,
        string $tipo,
        string $status,
        ?int $httpCode = null,
        ?string $payload = null,
        ?string $resposta = null,
        ?string $erro = null
    ): void {
        AsaasLog::create([
            'cobranca_id' => $cobrancaId,
            'tipo' => $tipo,
            'status' => $status,
            'http_code' => $httpCode,
            'payload' => $payload,
            'resposta_api' => $resposta,
            'erro' => $erro,
            'criado_em' => now(),
        ]);
    }
}
