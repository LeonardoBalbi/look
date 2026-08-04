<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\ContaBancaria;
use App\Models\ItauConfig;
use App\Models\ItauLog;
use App\Models\Pagamento;
use App\Support\PixQrCode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ItauService
{
    public const DEFAULT_TOKEN_URL = 'https://sts.itau.com.br/api/oauth/token';

    public const DEFAULT_API_BASE_URL = 'https://secure.api.itau/pix_recebimentos/v2';

    public const DEFAULT_WEBHOOK_TOKEN = 'locx_itau_webhook_token';

    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function config(?int $lojaId = null)
    {
        $conta = ContaBancaria::config('itau', $lojaId);
        $legado = $conta->loja_id ? null : ItauConfig::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'demo',
                'ambiente' => 'producao',
                'ativo' => true,
                'api_base_url' => self::DEFAULT_API_BASE_URL,
                'token_url' => self::DEFAULT_TOKEN_URL,
                'webhook_token' => self::DEFAULT_WEBHOOK_TOKEN,
                'webhook_url' => route('locx.webhook-itau', ['token' => self::DEFAULT_WEBHOOK_TOKEN]),
            ]
        );

        if ($legado) {
            $conta->update([
                'modo' => $legado->modo,
                'ambiente' => $legado->ambiente,
                'client_id' => $legado->client_id,
                'client_secret' => $legado->client_secret,
                'chave_pix' => $legado->chave_pix,
                'api_base_url' => $legado->api_base_url ?: self::DEFAULT_API_BASE_URL,
                'token_url' => $legado->token_url ?: self::DEFAULT_TOKEN_URL,
                'cert_path' => $legado->cert_path,
                'key_path' => $legado->key_path,
                'access_token' => $legado->access_token,
                'token_expires_at' => $legado->token_expires_at,
                'webhook_url' => $legado->webhook_url,
                'webhook_token' => $legado->webhook_token ?: self::DEFAULT_WEBHOOK_TOKEN,
                'ativo' => $legado->ativo,
                'atualizado_em' => now(),
            ]);
        }

        return $conta->fresh();
    }

    public function testar(?int $lojaId = null): array
    {
        $config = $this->config($lojaId);

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma chamada externa foi feita.'];
        }

        $erro = $this->validarConfig($config);
        if ($erro) {
            return ['ok' => false, 'http_code' => 0, 'erro' => $erro];
        }

        $token = $this->accessToken($config);
        if (! ($token['ok'] ?? false)) {
            return $token;
        }

        $txid = 'LOCXTESTE'.now()->format('YmdHis');
        $response = $this->request('GET', '/cob/'.$txid, config: $config);

        if (in_array($response->status(), [200, 400, 404], true)) {
            return [
                'ok' => true,
                'http_code' => $response->status(),
                'mensagem' => 'A API Itau respondeu e aceitou a autenticacao.',
            ];
        }

        return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
    }

    public function criarPix(Cobranca $cobranca): array
    {
        $cobranca->loadMissing('cliente');
        $config = $this->config((int) $cobranca->loja_id);
        $valor = $this->calculator->valorAtualizado(
            $cobranca->valor_principal,
            $cobranca->valor_pago,
            $cobranca->vencimento
        );

        if (! $config->ativo) {
            return ['ok' => false, 'erro' => 'Integracao Itau inativa.'];
        }

        $txid = $this->txid($cobranca);

        if ($config->modo === 'demo') {
            $pix = '00020126580014BR.GOV.BCB.PIX0136LOCX-ITAU-DEMO-COBRANCA-'.$cobranca->id
                .'520400005303986540'.number_format($valor, 2, '.', '')
                .'5802BR5904LOCX6009MANGARATIBA62070503***6304DEMO';

            $cobranca->update([
                'pix_copia_cola' => $pix,
                'pix_qrcode' => PixQrCode::dataUri($pix),
                'itau_txid' => 'DEMO-'.$txid,
                'itau_status' => 'DEMO',
                'itau_payload' => 'PIX demo gerado pelo LocX',
                'conta_bancaria_id' => $config->id,
                'gateway_usado' => 'itau',
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
            return ['ok' => false, 'erro' => 'O valor da cobranca precisa ser maior que zero.'];
        }

        $documento = preg_replace('/\D+/', '', (string) $cobranca->cliente->cpf);
        if (! in_array(strlen($documento), [11, 14], true)) {
            return ['ok' => false, 'erro' => 'O cliente precisa ter CPF ou CNPJ para gerar PIX Itau.'];
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

        $response = $this->request('PUT', '/cob/'.$txid, $payload, config: $config);
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
        $pix = (string) ($json['pixCopiaECola'] ?? data_get($json, 'loc.payload') ?? data_get($json, 'qrcode') ?? '');
        $cobranca->update([
            'pix_copia_cola' => $pix,
            'pix_qrcode' => PixQrCode::dataUri($pix),
            'itau_txid' => $json['txid'] ?? $txid,
            'itau_status' => $json['status'] ?? 'ATIVA',
            'itau_payload' => $response->body(),
            'conta_bancaria_id' => $config->id,
            'gateway_usado' => 'itau',
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'pix' => $pix, 'txid' => $json['txid'] ?? $txid];
    }

    public function validarWebhook(?string $token): bool
    {
        $esperado = (string) $this->config()->webhook_token;
        if ($esperado === '') {
            return true;
        }
        if (hash_equals($esperado, (string) $token)) {
            return true;
        }

        return ContaBancaria::query()
            ->where('provedor', 'itau')
            ->where('webhook_token', (string) $token)
            ->exists();
    }

    public function processarWebhook(string $raw): array
    {
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return ['ok' => false, 'erro' => 'JSON invalido'];
        }

        $pix = data_get($json, 'pix.0', $json);
        $txid = (string) ($pix['txid'] ?? $json['txid'] ?? '');
        $endToEndId = (string) ($pix['endToEndId'] ?? '');
        $valor = (float) ($pix['valor'] ?? $json['valor'] ?? 0);
        $cobranca = $txid ? Cobranca::where('itau_txid', $txid)->first() : null;

        $this->log($cobranca?->id, 'webhook', $txid ? 'recebido' : 'sem_txid', 200, $raw, 'webhook recebido');
        if (! $cobranca) {
            return ['ok' => true, 'ignorado' => true, 'mensagem' => 'Cobranca nao localizada'];
        }

        $this->baixar($cobranca, $valor, 'ITAU_'.($endToEndId ?: 'PIX_RECEBIDO'));

        return ['ok' => true, 'baixado' => true];
    }

    public function conciliarCobranca(Cobranca $cobranca): array
    {
        $config = $this->config((int) $cobranca->loja_id);
        if (! $config->ativo || $config->modo !== 'api') {
            return ['ok' => false, 'erro' => 'Integracao Itau inativa ou em modo demo.'];
        }

        if (! $cobranca->itau_txid || str_starts_with((string) $cobranca->itau_txid, 'DEMO-')) {
            return ['ok' => false, 'erro' => 'Cobranca sem TXID Itau valido.'];
        }

        $response = $this->request('GET', '/cob/'.$cobranca->itau_txid, config: $config);
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
            $this->baixar($cobranca, $valor, 'ITAU_'.$status);

            return ['ok' => true, 'baixado' => true, 'status' => $status];
        }

        $cobranca->update(['itau_status' => $status, 'atualizado_em' => now()]);

        return ['ok' => true, 'baixado' => false, 'status' => $status];
    }

    private function accessToken(?ContaBancaria $config = null): array
    {
        $config ??= $this->config();
        if ($config->access_token && $config->token_expires_at && $config->token_expires_at->isFuture()) {
            return ['ok' => true, 'token' => $config->access_token];
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
        ];

        $response = $this->httpWithCertificate($config)
            ->asForm()
            ->acceptJson()
            ->post($config->token_url ?: self::DEFAULT_TOKEN_URL, $payload);
        $this->log(null, 'oauth_token', $response->successful() ? 'ok' : 'erro', $response->status(), 'grant_type=client_credentials&client_id='.$config->client_id, $response->body(), $response->successful() ? null : $response->body());

        if (! $response->successful()) {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => $response->body()];
        }

        $token = (string) $response->json('access_token');
        if ($token === '') {
            return ['ok' => false, 'http_code' => $response->status(), 'erro' => 'Itau nao retornou access_token.'];
        }

        $config->update([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 300) - 30)),
            'atualizado_em' => now(),
        ]);

        return ['ok' => true, 'token' => $token];
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
        ?ContaBancaria $config = null
    ): Response
    {
        $config ??= $this->config();
        $token = $this->accessToken($config);
        if (! ($token['ok'] ?? false)) {
            return new Response(new \GuzzleHttp\Psr7\Response((int) (($token['http_code'] ?? 0) ?: 599), [], (string) ($token['erro'] ?? 'Falha OAuth Itau')));
        }

        $base = rtrim((string) ($config->api_base_url ?: self::DEFAULT_API_BASE_URL), '/');

        return $this->httpWithCertificate($config)
            ->acceptJson()
            ->withToken($token['token'])
            ->send($method, $base.'/'.ltrim($path, '/'), array_filter([
                'query' => $query ?: null,
                'json' => $payload,
            ]));
    }

    private function httpWithCertificate(?ContaBancaria $config = null): PendingRequest
    {
        $config ??= $this->config();
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

    private function validarConfig($config): ?string
    {
        if (! $config->client_id) {
            return 'Client ID Itau nao configurado.';
        }
        if (! $config->client_secret) {
            return 'Client Secret Itau nao configurado.';
        }
        if (! $config->chave_pix) {
            return 'Chave PIX Itau nao configurada.';
        }
        if (! $config->token_url || ! $config->api_base_url) {
            return 'URLs da API Itau nao configuradas.';
        }
        if (! $config->cert_path || ! is_file((string) $config->cert_path)) {
            return 'Certificado .crt Itau nao encontrado no caminho configurado.';
        }
        if (! $config->key_path || ! is_file((string) $config->key_path)) {
            return 'Chave privada .key Itau nao encontrada no caminho configurado.';
        }

        return null;
    }

    private function txid(Cobranca $cobranca): string
    {
        return 'LOCX'.str_pad((string) $cobranca->id, 21, '0', STR_PAD_LEFT);
    }

    private function baixar(Cobranca $cobranca, float $valor, string $statusItau): void
    {
        $pagamento = null;

        DB::transaction(function () use ($cobranca, $valor, $statusItau, &$pagamento): void {
            if (Pagamento::where('cobranca_id', $cobranca->id)->where('comprovante', 'like', 'Itau %')->exists()) {
                $cobranca->update(['itau_status' => $statusItau, 'atualizado_em' => now()]);

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
                'comprovante' => 'Itau '.$statusItau,
            ]);
            $cobranca->update([
                'valor_pago' => $novoPago,
                'status' => $status,
                'itau_status' => $statusItau,
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
        ItauLog::create([
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
