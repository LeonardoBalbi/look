<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Models\PagbankConfig;
use App\Models\PagbankLog;
use App\Support\PixQrCode;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PagBankService
{
    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function config(): PagbankConfig
    {
        return PagbankConfig::query()->firstOrCreate(
            ['id' => 1],
            ['modo' => 'demo', 'ambiente' => 'sandbox', 'ativo' => true, 'merchant_reference' => 'LOCX']
        );
    }

    public function testar(): array
    {
        $config = $this->config();

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma chamada externa foi feita.'];
        }

        if (! $config->access_token) {
            return ['ok' => false, 'http_code' => 0, 'erro' => 'Access Token PagBank não configurado.'];
        }

        $response = $this->request('GET', '/orders', null, [
            'charge_id' => 'CHAR_00000000-0000-0000-0000-000000000000',
        ]);

        if (in_array($response->status(), [200, 400, 404], true)) {
            return [
                'ok' => true,
                'http_code' => $response->status(),
                'mensagem' => 'A API PagBank respondeu e aceitou a autenticação.',
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
            return ['ok' => false, 'erro' => 'Integração PagBank inativa.'];
        }

        if ($config->modo === 'demo') {
            $pix = '00020126580014BR.GOV.BCB.PIX0136LOCX-DEMO-COBRANCA-'.$cobranca->id
                .'520400005303986540'.number_format($valor, 2, '.', '')
                .'5802BR5904LOCX6009MANGARATIBA62070503***6304DEMO';

            $cobranca->update([
                'pix_copia_cola' => $pix,
                'pix_qrcode' => PixQrCode::dataUri($pix),
                'pagbank_order_id' => 'DEMO-'.$cobranca->id,
                'pagbank_status' => 'DEMO',
                'pagbank_payload' => 'PIX demo gerado pelo LocX',
                'atualizado_em' => now(),
            ]);
            $this->log($cobranca->id, 'criar_pix', 'demo', 200, 'demo', $pix);

            return ['ok' => true, 'demo' => true, 'pix' => $pix];
        }

        $documento = preg_replace('/\D+/', '', (string) $cobranca->cliente->cpf);
        if (! in_array(strlen($documento), [11, 14], true)) {
            return ['ok' => false, 'erro' => 'O cliente precisa ter CPF ou CNPJ válido.'];
        }
        if (! filter_var($cobranca->cliente->email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'erro' => 'O cliente precisa ter um e-mail válido.'];
        }
        if ($valor <= 0) {
            return ['ok' => false, 'erro' => 'O valor da cobrança precisa ser maior que zero.'];
        }

        $reference = 'LOCX-COBRANCA-'.$cobranca->id;
        $centavos = (int) round($valor * 100);
        $payload = [
            'reference_id' => $reference,
            'customer' => [
                'name' => $cobranca->cliente->nome ?: 'Cliente LocX',
                'email' => $cobranca->cliente->email,
                'tax_id' => $documento,
            ],
            'items' => [[
                'reference_id' => (string) $cobranca->id,
                'name' => 'Cobrança LocX #'.$cobranca->id,
                'quantity' => 1,
                'unit_amount' => $centavos,
            ]],
            'qr_codes' => [[
                'amount' => ['value' => $centavos],
                'expiration_date' => now()->addDays(7)->toIso8601String(),
            ]],
            'notification_urls' => [$config->webhook_url ?: route('locx.webhook-pagbank')],
        ];

        $response = $this->request('POST', '/orders', $payload, [], [
            'x-idempotency-key' => hash('sha256', 'locx|'.$reference.'|'.$centavos),
        ]);
        $this->log(
            $cobranca->id,
            'criar_pix',
            $response->successful() ? 'enviado' : 'erro',
            $response->status(),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $response->body(),
            $response->successful() ? null : $response->body()
        );

        if (! $response->successful()) {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
        }

        $json = $response->json();
        $qr = data_get($json, 'qr_codes.0.text', '');
        $qrImage = collect(data_get($json, 'qr_codes.0.links', []))
            ->first(fn ($link) => ($link['rel'] ?? '') === 'QRCODE.PNG');

        $cobranca->update([
            'pix_copia_cola' => $qr,
            'pix_qrcode' => PixQrCode::dataUri($qr, $qrImage['href'] ?? null),
            'pagbank_order_id' => $json['id'] ?? null,
            'pagbank_status' => $json['status'] ?? 'CREATED',
            'pagbank_payload' => $response->body(),
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'pix' => $qr, 'order_id' => $json['id'] ?? null];
    }

    public function validarAssinatura(string $raw, ?string $assinatura): bool
    {
        $config = $this->config();
        if ($config->modo === 'demo') {
            return true;
        }

        if (! $config->access_token || ! $assinatura) {
            return false;
        }

        return hash_equals(
            hash('sha256', $config->access_token.'-'.$raw),
            strtolower(trim($assinatura))
        );
    }

    public function processarWebhook(string $raw): array
    {
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return ['ok' => false, 'erro' => 'JSON inválido'];
        }

        $reference = $json['reference_id'] ?? '';
        $orderId = $json['id'] ?? data_get($json, 'order.id', '');
        $status = $json['status'] ?? data_get($json, 'charges.0.status', '');
        $cobrancaId = preg_match('/LOCX-COBRANCA-(\d+)/', $reference, $match)
            ? (int) $match[1]
            : null;
        $cobranca = $cobrancaId
            ? Cobranca::find($cobrancaId)
            : Cobranca::where('pagbank_order_id', $orderId)->first();

        $this->log($cobranca?->id, 'webhook', $status ?: 'recebido', 200, $raw, 'webhook recebido');
        if (! $cobranca) {
            return ['ok' => false, 'erro' => 'Cobrança não localizada'];
        }

        if (in_array(strtoupper($status), ['PAID', 'AVAILABLE', 'AUTHORIZED', 'COMPLETED', 'RECEIVED'], true)) {
            $valor = ((float) (data_get($json, 'charges.0.amount.value') ?? data_get($json, 'qr_codes.0.amount.value') ?? 0)) / 100;
            $this->baixar($cobranca, $valor, 'PAGBANK_'.$status);

            return ['ok' => true, 'baixado' => true];
        }

        $cobranca->update(['pagbank_status' => $status, 'atualizado_em' => now()]);

        return ['ok' => true, 'baixado' => false, 'status' => $status];
    }

    private function baixar(Cobranca $cobranca, float $valor, string $statusPagbank): void
    {
        DB::transaction(function () use ($cobranca, $valor, $statusPagbank): void {
            if (Pagamento::where('cobranca_id', $cobranca->id)->where('comprovante', 'like', 'PagBank %')->exists()) {
                $cobranca->update(['pagbank_status' => $statusPagbank, 'atualizado_em' => now()]);

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
                'comprovante' => 'PagBank '.$statusPagbank,
            ]);
            $cobranca->update([
                'valor_pago' => $novoPago,
                'status' => $status,
                'pagbank_status' => $statusPagbank,
                'whatsapp_status' => $status === 'paga' ? 'conciliado' : $cobranca->whatsapp_status,
                'atualizado_em' => now(),
            ]);

            if ($status === 'paga') {
                app(CrmAutomationService::class)->fecharTarefasDeCobranca($cobranca->fresh('cliente'));
            }
        });
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
        array $headers = []
    ): Response {
        $config = $this->config();
        $base = $config->ambiente === 'producao'
            ? 'https://api.pagseguro.com'
            : 'https://sandbox.api.pagseguro.com';
        $request = Http::acceptJson()->withToken($config->access_token)->withHeaders($headers)->timeout(40);

        return $request->send($method, $base.$path, array_filter([
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
        PagbankLog::create([
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
