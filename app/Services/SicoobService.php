<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Models\SicoobConfig;
use App\Models\SicoobLog;
use App\Support\PixQrCode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SicoobService
{
    public const DEFAULT_TOKEN_URL = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';

    public const DEFAULT_API_BASE_URL = 'https://api.sicoob.com.br/pix/api/v2';

    public const DEFAULT_WEBHOOK_TOKEN = 'locx_sicoob_webhook_token';

    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function config(): SicoobConfig
    {
        return SicoobConfig::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'ativo' => true,
                'api_base_url' => self::DEFAULT_API_BASE_URL,
                'token_url' => self::DEFAULT_TOKEN_URL,
                'webhook_token' => self::DEFAULT_WEBHOOK_TOKEN,
                'webhook_url' => route('locx.webhook-sicoob', ['token' => self::DEFAULT_WEBHOOK_TOKEN]),
            ]
        );
    }

    public function testar(): array
    {
        $config = $this->config();

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma chamada externa foi feita.'];
        }

        $erro = $this->validarConfig($config);
        if ($erro) {
            return ['ok' => false, 'http_code' => 0, 'erro' => $erro];
        }

        $token = $this->accessToken();
        if (! ($token['ok'] ?? false)) {
            return $token;
        }

        $txid = 'LOCXTESTE'.now()->format('YmdHis');
        $response = $this->request('GET', '/cob/'.$txid);

        if (in_array($response->status(), [200, 400, 404], true)) {
            return [
                'ok' => true,
                'http_code' => $response->status(),
                'mensagem' => 'A API Sicoob respondeu e aceitou a autenticação.',
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
            return ['ok' => false, 'erro' => 'Integração Sicoob inativa.'];
        }

        $txid = $this->txid($cobranca);

        if ($config->modo === 'demo') {
            $pix = '00020126580014BR.GOV.BCB.PIX0136LOCX-SICOOB-DEMO-COBRANCA-'.$cobranca->id
                .'520400005303986540'.number_format($valor, 2, '.', '')
                .'5802BR5904LOCX6009MANGARATIBA62070503***6304DEMO';

            $cobranca->update([
                'pix_copia_cola' => $pix,
                'pix_qrcode' => PixQrCode::dataUri($pix),
                'sicoob_txid' => 'DEMO-'.$txid,
                'sicoob_status' => 'DEMO',
                'sicoob_payload' => 'PIX demo gerado pelo LocX',
                'atualizado_em' => now(),
            ]);
            $this->log($cobranca->id, 'criar_pix', 'demo', 200, 'demo', $pix);

            return ['ok' => true, 'demo' => true, 'pix' => $pix, 'txid' => 'DEMO-'.$txid];
        }

        $erro = $this->validarConfig($config);
        if ($erro) {
            return ['ok' => false, 'http_code' => 0, 'erro' => $erro];
        }
        if ($valor <= 0) {
            return ['ok' => false, 'erro' => 'O valor da cobrança precisa ser maior que zero.'];
        }

        $documento = preg_replace('/\D+/', '', (string) $cobranca->cliente->cpf);
        if (! in_array(strlen($documento), [11, 14], true)) {
            return ['ok' => false, 'erro' => 'O cliente precisa ter CPF ou CNPJ para gerar PIX Sicoob.'];
        }
        $campoDocumento = strlen($documento) === 14 ? 'cnpj' : 'cpf';
        $payload = [
            'calendario' => ['expiracao' => 604800],
            'devedor' => array_filter([
                $campoDocumento => $documento,
                'nome' => $cobranca->cliente->nome ?: 'Cliente LocX',
            ]),
            'valor' => ['original' => number_format($valor, 2, '.', '')],
            'chave' => $config->chave_pix,
            'solicitacaoPagador' => 'Cobranca LocX #'.$cobranca->id,
            'infoAdicionais' => [[
                'nome' => 'referencia',
                'valor' => 'LOCX-COBRANCA-'.$cobranca->id,
            ]],
        ];

        $response = $this->request('PUT', '/cob/'.$txid, $payload);
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
        $pix = (string) ($json['pixCopiaECola'] ?? data_get($json, 'loc.payload') ?? '');
        $cobranca->update([
            'pix_copia_cola' => $pix,
            'pix_qrcode' => PixQrCode::dataUri($pix),
            'sicoob_txid' => $json['txid'] ?? $txid,
            'sicoob_status' => $json['status'] ?? 'ATIVA',
            'sicoob_payload' => $response->body(),
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'pix' => $pix, 'txid' => $json['txid'] ?? $txid];
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
            return ['ok' => false, 'erro' => 'JSON inválido'];
        }

        $pix = data_get($json, 'pix.0', $json);
        $txid = (string) ($pix['txid'] ?? $json['txid'] ?? '');
        $endToEndId = (string) ($pix['endToEndId'] ?? '');
        $valor = (float) ($pix['valor'] ?? $json['valor'] ?? 0);
        $cobranca = $txid ? Cobranca::where('sicoob_txid', $txid)->first() : null;

        $this->log($cobranca?->id, 'webhook', $txid ? 'recebido' : 'sem_txid', 200, $raw, 'webhook recebido');
        if (! $cobranca) {
            return ['ok' => true, 'ignorado' => true, 'mensagem' => 'Cobrança não localizada'];
        }

        $this->baixar($cobranca, $valor, 'SICOOB_'.($endToEndId ?: 'PIX_RECEBIDO'));

        return ['ok' => true, 'baixado' => true];
    }

    public function conciliarCobranca(Cobranca $cobranca): array
    {
        $config = $this->config();
        if (! $config->ativo || $config->modo !== 'api') {
            return ['ok' => false, 'erro' => 'Integração Sicoob inativa ou em modo demo.'];
        }

        if (! $cobranca->sicoob_txid || str_starts_with((string) $cobranca->sicoob_txid, 'DEMO-')) {
            return ['ok' => false, 'erro' => 'Cobrança sem TXID Sicoob válido.'];
        }

        $response = $this->request('GET', '/cob/'.$cobranca->sicoob_txid);
        $json = $response->json();
        $status = (string) ($json['status'] ?? '');
        $this->log(
            $cobranca->id,
            'conciliar_pix',
            $response->successful() ? ($status ?: 'recebido') : 'erro',
            $response->status(),
            null,
            $response->body(),
            $response->successful() ? null : $response->body()
        );

        if (! $response->successful()) {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
        }

        if (strtoupper($status) === 'CONCLUIDA' || filled(data_get($json, 'pix.0'))) {
            $valor = (float) (data_get($json, 'pix.0.valor') ?? data_get($json, 'valor.original') ?? 0);
            $this->baixar($cobranca, $valor, 'SICOOB_'.$status);

            return ['ok' => true, 'baixado' => true, 'status' => $status];
        }

        $cobranca->update(['sicoob_status' => $status, 'atualizado_em' => now()]);

        return ['ok' => true, 'baixado' => false, 'status' => $status];
    }

    private function accessToken(): array
    {
        $config = $this->config();
        if ($config->access_token && $config->token_expires_at && $config->token_expires_at->isFuture()) {
            return ['ok' => true, 'token' => $config->access_token];
        }

        $request = $this->httpWithCertificate()->asForm()->acceptJson();
        if ($config->client_secret) {
            $request = $request->withBasicAuth((string) $config->client_id, (string) $config->client_secret);
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $config->client_id,
            'scope' => 'cob.write cob.read pix.read webhook.write webhook.read',
        ];

        $response = $request->post($config->token_url ?: self::DEFAULT_TOKEN_URL, $payload);
        $this->log(null, 'oauth_token', $response->successful() ? 'ok' : 'erro', $response->status(), http_build_query($payload), $response->body(), $response->successful() ? null : $response->body());

        if (! $response->successful()) {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
        }

        $token = (string) $response->json('access_token');
        if ($token === '') {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => 'Sicoob não retornou access_token.'];
        }

        $config->update([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 300) - 30)),
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'token' => $token];
    }

    private function request(string $method, string $path, ?array $payload = null, array $query = []): Response
    {
        $token = $this->accessToken();
        if (! ($token['ok'] ?? false)) {
            return new Response(new \GuzzleHttp\Psr7\Response((int) (($token['http_code'] ?? 0) ?: 599), [], (string) ($token['erro'] ?? 'Falha OAuth Sicoob')));
        }

        $config = $this->config();
        $base = rtrim((string) ($config->api_base_url ?: self::DEFAULT_API_BASE_URL), '/');

        return $this->httpWithCertificate()
            ->acceptJson()
            ->withToken($token['token'])
            ->send($method, $base.'/'.ltrim($path, '/'), array_filter([
                'query' => $query ?: null,
                'json' => $payload,
            ]));
    }

    private function httpWithCertificate(): PendingRequest
    {
        $config = $this->config();
        $request = Http::timeout(40);

        $options = [];
        if ($config->cert_path) {
            $options['cert'] = $config->cert_path;
        }
        if ($config->key_path) {
            $options['ssl_key'] = $config->key_path;
        }
        if (! config('locx.gateway_verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        return $options ? $request->withOptions($options) : $request;
    }

    private function validarConfig(SicoobConfig $config): ?string
    {
        if (! $config->client_id) {
            return 'Client ID Sicoob não configurado.';
        }
        if (! $config->chave_pix) {
            return 'Chave PIX Sicoob não configurada.';
        }
        if (! $config->token_url || ! $config->api_base_url) {
            return 'URLs da API Sicoob não configuradas.';
        }
        if (! $config->cert_path || ! is_file((string) $config->cert_path)) {
            return 'Certificado PEM Sicoob não encontrado no caminho configurado.';
        }

        return null;
    }

    private function txid(Cobranca $cobranca): string
    {
        return 'LOCX'.str_pad((string) $cobranca->id, 21, '0', STR_PAD_LEFT);
    }

    private function baixar(Cobranca $cobranca, float $valor, string $statusSicoob): void
    {
        $pagamento = null;

        DB::transaction(function () use ($cobranca, $valor, $statusSicoob, &$pagamento): void {
            if (Pagamento::where('cobranca_id', $cobranca->id)->where('comprovante', 'like', 'Sicoob %')->exists()) {
                $cobranca->update(['sicoob_status' => $statusSicoob, 'atualizado_em' => now()]);

                return;
            }

            $valor = $valor > 0 ? $valor : max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
            $novoPago = (float) $cobranca->valor_pago + $valor;
            $status = $novoPago >= (float) $cobranca->valor_principal ? 'paga' : 'parcial';

            $pagamento = Pagamento::create([
                'cobranca_id' => $cobranca->id,
                'valor' => $valor,
                'forma' => 'pix',
                'pago_em' => now(),
                'comprovante' => 'Sicoob '.$statusSicoob,
            ]);
            $cobranca->update([
                'valor_pago' => $novoPago,
                'status' => $status,
                'sicoob_status' => $statusSicoob,
                'whatsapp_status' => $status === 'paga' ? 'conciliado' : $cobranca->whatsapp_status,
                'telegram_status' => $status === 'paga' ? 'conciliado' : $cobranca->telegram_status,
                'atualizado_em' => now(),
            ]);

            if ($status === 'paga') {
                app(CrmAutomationService::class)->fecharTarefasDeCobranca($cobranca->fresh('cliente'));
            }
        });

        if ($pagamento) {
            app(EmailPagamentoService::class)->enviarConfirmacao(
                $pagamento->fresh('cobranca.cliente', 'cobranca.contrato.motocicleta')
            );
        }
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
        SicoobLog::create([
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
