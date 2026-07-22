<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\PortalAtendimento;
use App\Models\PortalAtendimentoMensagem;
use App\Models\TelegramConfig;
use App\Models\TelegramLog;
use App\Support\Locx;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramService
{
    public function config(): TelegramConfig
    {
        return TelegramConfig::query()->firstOrCreate(
            ['id' => 1],
            [
                'modo' => 'demo',
                'ativo' => true,
                'webhook_secret' => Str::random(48),
                'atendimento_webhook_secret' => Str::random(48),
                'parse_mode' => null,
                'template_lembrete' => "Lembrete de vencimento\n\nOla, {cliente}.\n\nSua cobranca #{cobranca_id} vence em {vencimento}.\nValor: {saldo}\n\n{pix}",
                'template_vencimento' => "Cobranca vencendo hoje\n\nOla, {cliente}.\n\nSua cobranca #{cobranca_id} vence hoje.\nValor: {saldo}\n\n{pix}",
                'template_pagamento' => "Pagamento confirmado\n\nOla, {cliente}.\n\nRecebemos seu pagamento de {valor_pago} em {data_pagamento}.\nCobranca: #{cobranca_id}.",
                'template_gerente' => "Aviso ao gerente\n\nCliente: {cliente}\nMoto: {placa}\nDias em atraso: {dias_atraso}\nSaldo: {saldo}\nTelefone: {telefone_cliente}",
                'template_cobranca' => "🔔 Aviso de cobrança\n\nOlá, {cliente}.\n\nCobrança #{cobranca_id}\nVencimento: {vencimento}\nSaldo atualizado: {saldo}\n\n{pix}\n\nAcesse o portal para consultar os detalhes.",
            ]
        );
    }

    public function testar(string $bot = 'notificacoes'): array
    {
        $config = $this->config();
        $token = $this->botToken($bot);

        if (! $config->ativo) {
            return ['ok' => false, 'erro' => 'Telegram está inativo.'];
        }

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo ativo. Nenhuma mensagem foi enviada ao Telegram.'];
        }

        if (! $token) {
            return ['ok' => false, 'erro' => 'Informe o token do bot criado no BotFather.'];
        }

        try {
            $response = $this->request('getMe', [], $bot);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'erro' => 'Não foi possível conectar ao Telegram: '.$exception->getMessage()];
        }

        if (! $response->successful() || ! $response->json('ok')) {
            return $this->falha($response);
        }

        $username = (string) $response->json('result.username');
        if ($bot === 'atendimento' && $username && $username !== $config->atendimento_bot_username) {
            $config->update(['atendimento_bot_username' => $username, 'atualizado_em' => now()]);
        } elseif ($username && $username !== $config->bot_username) {
            $config->update(['bot_username' => $username, 'atualizado_em' => now()]);
        }

        return ['ok' => true, 'mensagem' => 'Bot @'.$username.' conectado com sucesso.'];
    }

    public function testarTodos(): array
    {
        $notificacoes = $this->testar('notificacoes');
        $config = $this->config();

        if (! $config->atendimento_bot_token) {
            return $notificacoes;
        }

        $atendimento = $this->testar('atendimento');
        $ok = ($notificacoes['ok'] ?? false) && ($atendimento['ok'] ?? false);

        return [
            'ok' => $ok,
            'mensagem' => 'Notificacoes: '.($notificacoes['mensagem'] ?? $notificacoes['erro'] ?? 'falha')
                .' Atendimento: '.($atendimento['mensagem'] ?? $atendimento['erro'] ?? 'falha'),
            'notificacoes' => $notificacoes,
            'atendimento' => $atendimento,
        ];
    }

    public function configurarWebhook(?string $url = null, string $bot = 'notificacoes'): array
    {
        $config = $this->config();
        $token = $this->botToken($bot);
        $secret = $this->webhookSecret($bot);

        if ($config->modo === 'demo') {
            return ['ok' => true, 'demo' => true, 'mensagem' => 'Modo demo: webhook não foi registrado no Telegram.'];
        }

        if (! $token || ! $secret) {
            return ['ok' => false, 'erro' => 'Informe o token do bot e o segredo do webhook.'];
        }

        $url ??= route('locx.webhook-telegram');

        try {
            $response = $this->request('setWebhook', [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
                'drop_pending_updates' => false,
            ], $bot);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'erro' => 'Não foi possível configurar o webhook: '.$exception->getMessage()];
        }

        return ($response->successful() && $response->json('ok'))
            ? ['ok' => true, 'mensagem' => 'Webhook do Telegram configurado em '.$url.'.']
            : $this->falha($response);
    }

    public function configurarWebhooks(?string $url = null): array
    {
        $notificacoes = $this->configurarWebhook($url, 'notificacoes');
        $config = $this->config();

        if (! $config->atendimento_bot_token) {
            return $notificacoes;
        }
        if (! $config->atendimento_webhook_secret) {
            $config->update(['atendimento_webhook_secret' => Str::random(48), 'atualizado_em' => now()]);
            $config->refresh();
        }

        $atendimento = $this->configurarWebhook($url, 'atendimento');
        $ok = ($notificacoes['ok'] ?? false) && ($atendimento['ok'] ?? false);

        return [
            'ok' => $ok,
            'mensagem' => 'Notificacoes: '.($notificacoes['mensagem'] ?? $notificacoes['erro'] ?? 'falha')
                .' Atendimento: '.($atendimento['mensagem'] ?? $atendimento['erro'] ?? 'falha'),
            'notificacoes' => $notificacoes,
            'atendimento' => $atendimento,
        ];
    }

    public function validarWebhook(?string $secret): bool
    {
        return (bool) $this->tipoWebhook($secret);
    }

    public function tipoWebhook(?string $secret): ?string
    {
        $config = $this->config();
        $segredos = [
            'notificacoes' => (string) $config->webhook_secret,
            'atendimento' => (string) ($config->atendimento_webhook_secret ?: $config->webhook_secret),
        ];

        foreach ($segredos as $tipo => $esperado) {
            if ($esperado !== '' && is_string($secret) && hash_equals($esperado, $secret)) {
                return $tipo;
            }
        }

        return null;
    }

    public function garantirLinkToken(Cliente $cliente): string
    {
        if (! $cliente->telegram_link_token) {
            $cliente->update(['telegram_link_token' => Str::random(40)]);
            $cliente->refresh();
        }

        return (string) $cliente->telegram_link_token;
    }

    public function linkUrl(Cliente $cliente): ?string
    {
        $username = trim((string) $this->config()->bot_username, '@ ');
        if ($username === '') {
            return null;
        }

        return 'https://t.me/'.$username.'?start=locx_'.$this->garantirLinkToken($cliente);
    }

    public function garantirAtendimentoLinkToken(Cliente $cliente): string
    {
        if (! $cliente->telegram_atendimento_link_token) {
            $cliente->update(['telegram_atendimento_link_token' => Str::random(40)]);
            $cliente->refresh();
        }

        return (string) $cliente->telegram_atendimento_link_token;
    }

    public function atendimentoLinkUrl(Cliente $cliente): ?string
    {
        $config = $this->config();
        $username = trim((string) $config->atendimento_bot_username, '@ ');
        if ($username === '' || ! $config->atendimento_bot_token) {
            return null;
        }

        return 'https://t.me/'.$username.'?start=locx_chat_'.$this->garantirAtendimentoLinkToken($cliente);
    }

    public function enviarCobranca(Cobranca $cobranca, ?string $mensagem = null): array
    {
        $cobranca->loadMissing('cliente');
        $cliente = $cobranca->cliente;
        $saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
        $pix = $cobranca->pix_copia_cola
            ? "PIX copia e cola:\n".$cobranca->pix_copia_cola
            : 'PIX ainda não disponível.';

        $mensagem ??= $this->renderizarTemplate((string) $this->config()->template_cobranca, [
            'cliente' => $cliente?->nome ?: 'cliente',
            'cobranca_id' => (string) $cobranca->id,
            'vencimento' => $cobranca->vencimento?->format('d/m/Y') ?: '-',
            'valor' => Locx::moeda($cobranca->valor_principal),
            'saldo' => Locx::moeda($saldo),
            'pix' => $pix,
            'link_portal' => route('cliente.login'),
        ]);

        if (! $cliente?->telegram_notificacoes) {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Cliente desativou notificações pelo Telegram.');
        }

        return $this->enviarTexto(
            (string) $cliente->telegram_chat_id,
            $mensagem,
            $cobranca,
            $cliente,
            'cobranca'
        );
    }

    public function enviarTexto(
        string $chatId,
        string $mensagem,
        ?Cobranca $cobranca = null,
        ?Cliente $cliente = null,
        string $tipo = 'mensagem',
        string $bot = 'notificacoes'
    ): array {
        $config = $this->config();
        $token = $this->botToken($bot);
        $chatId = trim($chatId);
        $mensagem = trim(Str::limit($mensagem, 4000, '…'));

        if ($chatId === '') {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Cliente ainda não vinculou o Telegram.');
        }
        if ($mensagem === '') {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Mensagem vazia.');
        }
        if (! $config->ativo) {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Telegram está inativo.');
        }

        if ($config->modo === 'demo') {
            $this->log($cobranca, $cliente, $chatId, $mensagem, 'demo', 200, 'Envio simulado.', null, null, $tipo);
            $this->atualizarStatusCobranca($cobranca, 'demo');

            return ['ok' => true, 'demo' => true, 'mensagem' => 'Envio simulado pelo Telegram.'];
        }

        if (! $token) {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Token do bot não configurado.');
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $mensagem,
                'disable_web_page_preview' => true,
            ];
            if ($config->parse_mode) {
                $payload['parse_mode'] = $config->parse_mode;
            }
            $response = $this->request('sendMessage', $payload, $bot);
        } catch (ConnectionException $exception) {
            return $this->registrarFalha($cobranca, $cliente, $mensagem, 'Não foi possível conectar ao Telegram: '.$exception->getMessage());
        }

        $ok = $response->successful() && (bool) $response->json('ok');
        $erro = $ok ? null : ($response->json('description') ?: $response->body());
        $messageId = $response->json('result.message_id');

        $this->log(
            $cobranca,
            $cliente,
            $chatId,
            $mensagem,
            $ok ? 'enviado' : 'erro',
            $response->status(),
            $response->body(),
            $erro,
            $messageId ? (string) $messageId : null,
            $tipo
        );
        $this->atualizarStatusCobranca($cobranca, $ok ? 'enviado' : 'erro');

        return $ok
            ? ['ok' => true, 'http_code' => $response->status(), 'message_id' => $messageId, 'mensagem' => 'Mensagem enviada pelo Telegram.']
            : ['ok' => false, 'http_code' => $response->status(), 'erro' => $erro ?: 'Telegram recusou a mensagem.'];
    }

    public function processarWebhook(array $payload, string $webhookTipo = 'notificacoes'): array
    {
        $message = data_get($payload, 'message');
        if (! is_array($message)) {
            return ['ok' => true, 'ignorado' => true];
        }

        $updateId = trim((string) data_get($payload, 'update_id', ''));
        if ($updateId !== '' && TelegramLog::query()->where('telegram_update_id', $updateId)->exists()) {
            return ['ok' => true, 'duplicado' => true];
        }

        $chatId = (string) data_get($message, 'chat.id', '');
        $texto = trim((string) data_get($message, 'text', data_get($message, 'caption', '')));
        $username = (string) data_get($message, 'from.username', '');
        $nome = trim((string) data_get($message, 'from.first_name', '').' '.(string) data_get($message, 'from.last_name', ''));

        if ($chatId === '') {
            return ['ok' => true, 'ignorado' => true];
        }

        if ($webhookTipo === 'atendimento') {
            return $this->processarWebhookAtendimento($payload, $message, $chatId, $texto, $username, $nome);
        }

        if (preg_match('/^\/start(?:@\w+)?\s+locx_([A-Za-z0-9_-]{20,64})$/', $texto, $match)) {
            $cliente = Cliente::query()->where('telegram_link_token', $match[1])->first();
            if (! $this->registrarEntrada($payload, $message, $cliente, 'entrada_vinculacao')) {
                return ['ok' => true, 'duplicado' => true];
            }

            if (! $cliente) {
                $this->enviarTexto($chatId, 'Este link de vinculação é inválido ou expirou. Gere um novo link no Portal do Cliente.');
                return ['ok' => false, 'erro' => 'Token de vinculação inválido.'];
            }

            Cliente::query()
                ->where('telegram_chat_id', $chatId)
                ->where('id', '<>', $cliente->id)
                ->update([
                    'telegram_chat_id' => null,
                    'telegram_username' => null,
                    'telegram_vinculado_em' => null,
                ]);

            $cliente->update([
                'telegram_chat_id' => $chatId,
                'telegram_username' => $username ?: null,
                'telegram_notificacoes' => true,
                'telegram_vinculado_em' => now(),
            ]);

            $this->enviarTexto(
                $chatId,
                '✅ Telegram vinculado com sucesso ao cadastro de '.$cliente->nome.'. Você poderá receber cobranças, PIX e lembretes por este bot.',
                null,
                $cliente,
                'vinculacao'
            );

            return ['ok' => true, 'vinculado' => true, 'cliente_id' => $cliente->id];
        }

        $cliente = Cliente::query()->where('telegram_chat_id', $chatId)->first();
        if (! $this->registrarEntrada($payload, $message, $cliente, 'entrada_notificacoes')) {
            return ['ok' => true, 'duplicado' => true];
        }

        $this->enviarTexto(
            $chatId,
            $cliente
                ? 'Este bot e usado apenas para notificacoes. Para falar com a equipe, use o bot de atendimento no Portal do Cliente.'
                : 'Seu Telegram ainda nao esta vinculado para notificacoes. Entre no Portal do Cliente e toque em Vincular Telegram.',
            null,
            $cliente,
            'aviso_bot_notificacoes'
        );

        return ['ok' => true, 'notificacoes_apenas' => true];
    }

    private function processarWebhookAtendimento(array $payload, array $message, string $chatId, string $texto, string $username, string $nome): array
    {
        if (preg_match('/^\/start(?:@\w+)?\s+locx_chat_([A-Za-z0-9_-]{20,64})$/', $texto, $match)) {
            $cliente = Cliente::query()->where('telegram_atendimento_link_token', $match[1])->first();
            if (! $this->registrarEntrada($payload, $message, $cliente, 'entrada_vinculacao_atendimento')) {
                return ['ok' => true, 'duplicado' => true];
            }

            if (! $cliente) {
                $this->enviarTexto($chatId, 'Este link de atendimento e invalido ou expirou. Gere um novo link no Portal do Cliente.', null, null, 'vinculacao_atendimento', 'atendimento');
                return ['ok' => false, 'erro' => 'Token de vinculacao de atendimento invalido.'];
            }

            Cliente::query()
                ->where('telegram_atendimento_chat_id', $chatId)
                ->where('id', '<>', $cliente->id)
                ->update([
                    'telegram_atendimento_chat_id' => null,
                    'telegram_atendimento_username' => null,
                    'telegram_atendimento_vinculado_em' => null,
                ]);

            $cliente->update([
                'telegram_atendimento_chat_id' => $chatId,
                'telegram_atendimento_username' => $username ?: null,
                'telegram_atendimento_vinculado_em' => now(),
            ]);

            $this->enviarTexto(
                $chatId,
                'Telegram de atendimento vinculado com sucesso ao cadastro de '.$cliente->nome.'. Digite sua mensagem para falar com a equipe.',
                null,
                $cliente,
                'vinculacao_atendimento',
                'atendimento'
            );

            return ['ok' => true, 'vinculado_atendimento' => true, 'cliente_id' => $cliente->id];
        }

        $cliente = Cliente::query()->where('telegram_atendimento_chat_id', $chatId)->first();
        if (! $this->registrarEntrada($payload, $message, $cliente, 'entrada_atendimento')) {
            return ['ok' => true, 'duplicado' => true];
        }

        if (! $cliente) {
            $this->enviarTexto($chatId, 'Seu Telegram ainda nao esta vinculado ao atendimento. Entre no Portal do Cliente e toque em Vincular atendimento Telegram.', null, null, 'nao_vinculado_atendimento', 'atendimento');
            return ['ok' => true, 'nao_vinculado_atendimento' => true];
        }

        if ($texto === '/start') {
            $this->enviarTexto($chatId, 'Ola, '.$cliente->nome.'! Seu atendimento ja esta vinculado. Digite sua mensagem para falar com a equipe.', null, $cliente, 'boas_vindas', 'atendimento');
            return ['ok' => true];
        }

        DB::transaction(function () use ($cliente, $chatId, $texto, $nome): void {
            $agora = now();
            $atendimento = PortalAtendimento::query()
                ->where('cliente_id', $cliente->id)
                ->where('canal', 'telegram')
                ->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $atendimento) {
                $atendimento = PortalAtendimento::create([
                    'cliente_id' => $cliente->id,
                    'loja_id' => $cliente->loja_id,
                    'assistente' => 'Lau',
                    'canal' => 'telegram',
                    'canal_chat_id' => $chatId,
                    'assunto' => 'Telegram',
                    'prioridade' => 'normal',
                    'status' => 'aguardando_humano',
                    'mensagem' => $texto ?: '[arquivo ou midia recebida]',
                    'ultima_mensagem_em' => $agora,
                    'atualizado_em' => $agora,
                ]);
            }

            PortalAtendimentoMensagem::create([
                'portal_atendimento_id' => $atendimento->id,
                'remetente' => 'cliente',
                'remetente_nome' => $nome ?: $cliente->nome,
                'mensagem' => $texto ?: '[arquivo ou midia recebida]',
            ]);

            $atendimento->update([
                'status' => 'aguardando_humano',
                'canal_chat_id' => $chatId,
                'lido_em' => null,
                'ultima_mensagem_em' => $agora,
                'atualizado_em' => $agora,
                'encerrado_em' => null,
            ]);
        });

        return ['ok' => true, 'atendimento' => true];
    }

    public function renderizarTemplate(string $template, array $dados): string
    {
        $substituicoes = [];
        foreach ($dados as $chave => $valor) {
            $substituicoes['{'.$chave.'}'] = (string) $valor;
        }

        return strtr($template, $substituicoes);
    }

    private function registrarEntrada(array $payload, array $message, ?Cliente $cliente, string $tipo): bool
    {
        $updateId = trim((string) data_get($payload, 'update_id', ''));
        $dados = [
            'cliente_id' => $cliente?->id,
            'chat_id' => (string) data_get($message, 'chat.id', ''),
            'username' => (string) data_get($message, 'from.username', '') ?: null,
            'tipo' => $tipo,
            'mensagem' => trim((string) data_get($message, 'text', data_get($message, 'caption', ''))) ?: '[arquivo ou mídia recebida]',
            'status' => 'recebido',
            'resposta_api' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'criado_em' => now(),
        ];

        if ($updateId === '') {
            TelegramLog::create($dados);
            return true;
        }

        $log = TelegramLog::query()->firstOrCreate(
            ['telegram_update_id' => $updateId],
            $dados
        );

        return $log->wasRecentlyCreated;
    }

    private function request(string $method, array $payload = [], string $bot = 'notificacoes'): Response
    {
        $config = $this->config();
        $token = $this->botToken($bot);
        $request = Http::acceptJson()->timeout(25);
        if (! config('locx.gateway_verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        return $request->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
    }

    private function botToken(string $bot): ?string
    {
        $config = $this->config();

        if ($bot === 'atendimento') {
            return $config->atendimento_bot_token ?: $config->bot_token;
        }

        return $config->bot_token;
    }

    private function webhookSecret(string $bot): ?string
    {
        $config = $this->config();

        if ($bot === 'atendimento') {
            return $config->atendimento_webhook_secret ?: $config->webhook_secret;
        }

        return $config->webhook_secret;
    }

    private function falha(Response $response): array
    {
        return [
            'ok' => false,
            'http_code' => $response->status(),
            'erro' => $response->json('description') ?: $response->body() ?: 'Telegram recusou a solicitação.',
        ];
    }

    private function registrarFalha(?Cobranca $cobranca, ?Cliente $cliente, string $mensagem, string $erro): array
    {
        $this->log($cobranca, $cliente, (string) $cliente?->telegram_chat_id, $mensagem, 'erro', null, null, $erro);
        $this->atualizarStatusCobranca($cobranca, 'erro');

        return ['ok' => false, 'erro' => $erro];
    }

    private function atualizarStatusCobranca(?Cobranca $cobranca, string $status): void
    {
        if ($cobranca) {
            $cobranca->update(['telegram_status' => $status]);
        }
    }

    private function log(
        ?Cobranca $cobranca,
        ?Cliente $cliente,
        string $chatId,
        string $mensagem,
        string $status,
        ?int $httpCode = null,
        ?string $resposta = null,
        ?string $erro = null,
        ?string $messageId = null,
        string $tipo = 'mensagem'
    ): void {
        TelegramLog::create([
            'cobranca_id' => $cobranca?->id,
            'cliente_id' => $cliente?->id ?: $cobranca?->cliente_id,
            'chat_id' => $chatId ?: null,
            'username' => $cliente?->telegram_username,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'status' => $status,
            'http_code' => $httpCode,
            'telegram_message_id' => $messageId,
            'resposta_api' => $resposta,
            'erro' => $erro,
            'criado_em' => now(),
        ]);
    }
}
