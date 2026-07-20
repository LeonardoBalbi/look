<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\PortalAtendimento;
use App\Models\PortalAtendimentoMensagem;
use App\Services\CobrancaCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientePortalController extends Controller
{
    private const CHAT_INACTIVITY_MINUTES = 15;

    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function index(Request $request): View
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente')->loadMissing(
            'loja',
            'contratos.motocicleta',
            'cobrancas.contrato.motocicleta',
            'cobrancas.pagamentos',
            'multasTransito.motocicleta',
            'portalAtendimentos.mensagens'
        );

        $cobrancas = $cliente->cobrancas
            ->sortBy(fn (Cobranca $cobranca) => $cobranca->vencimento?->timestamp ?? 0)
            ->values();

        foreach ($cobrancas as $cobranca) {
            if ($cobranca->status !== 'paga') {
                $valorAtualizado = $this->calculator->valorAtualizado(
                    $cobranca->valor_principal,
                    $cobranca->valor_pago,
                    $cobranca->vencimento
                );
                $status = $cobranca->vencimento->isPast() ? 'atrasada' : $cobranca->status;

                if (abs($valorAtualizado - (float) $cobranca->valor_atualizado) > 0.01 || $status !== $cobranca->status) {
                    $cobranca->update([
                        'valor_atualizado' => $valorAtualizado,
                        'status' => $status,
                        'atualizado_em' => now(),
                    ]);
                    $cobranca->valor_atualizado = $valorAtualizado;
                    $cobranca->status = $status;
                }
            }
        }

        $abertas = $cobrancas->where('status', '!=', 'paga');
        $pagas = $cobrancas->where('status', 'paga');
        $saldoAberto = $abertas->sum(fn (Cobranca $cobranca) => max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago));
        $saldoAtrasado = $abertas
            ->filter(fn (Cobranca $cobranca) => $cobranca->vencimento?->isPast())
            ->sum(fn (Cobranca $cobranca) => max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago));

        $pagamentos = DB::table('pagamentos as p')
            ->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
            ->where('c.cliente_id', $cliente->id)
            ->select('p.*', 'c.id as cobranca_numero')
            ->orderByDesc('p.pago_em')
            ->limit(12)
            ->get();

        $notificacoes = collect();

        $abertas->take(5)->each(function (Cobranca $cobranca) use ($notificacoes): void {
            $saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
            $notificacoes->push([
                'tipo' => $cobranca->vencimento->isPast() ? 'danger' : 'warn',
                'titulo' => $cobranca->vencimento->isPast() ? 'Debito em atraso' : 'Fatura em aberto',
                'texto' => 'Cobranca #'.$cobranca->id.' com saldo de '.\App\Support\Locx::moeda($saldo).' e vencimento em '.$cobranca->vencimento->format('d/m/Y').'.',
            ]);
        });

        $cliente->multasTransito
            ->whereIn('status', ['aberta', 'em_recurso'])
            ->take(3)
            ->each(fn ($multa) => $notificacoes->push([
                'tipo' => 'warn',
                'titulo' => 'Multa pendente',
                'texto' => ($multa->motocicleta?->placa ?: 'Moto').' - '.\App\Support\Locx::moeda($multa->valor).' - vencimento '.($multa->vencimento?->format('d/m/Y') ?: 'sem data').'.',
            ]));

        if ($notificacoes->isEmpty()) {
            $notificacoes->push([
                'tipo' => 'ok',
                'titulo' => 'Tudo em dia',
                'texto' => 'Nao ha debitos ou notificacoes pendentes no momento.',
            ]);
        }

        $cliente->portalAtendimentos
            ->filter(fn (PortalAtendimento $atendimento) => $this->atendimentoExpirouPorInatividade($atendimento))
            ->each(fn (PortalAtendimento $atendimento) => $this->encerrarPorInatividade($atendimento));

        $cliente->load('portalAtendimentos.mensagens');
        $atendimentosPortal = $cliente->portalAtendimentos->sortByDesc('id')->take(10)->values();
        $chatAtendimento = $cliente->portalAtendimentos
            ->sortByDesc('id')
            ->first(fn (PortalAtendimento $atendimento) => in_array($atendimento->status, ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'], true)
                && ! $this->atendimentoExpirouPorInatividade($atendimento));
        $ultimoAtendimentoEncerrado = $cliente->portalAtendimentos
            ->sortByDesc('id')
            ->first(fn (PortalAtendimento $atendimento) => $atendimento->status === 'fechado');
        $labelsAssuntos = array_values($this->chatAssuntos());
        $chatMensagens = $chatAtendimento?->mensagens
            ->sortBy('id')
            ->reject(fn (PortalAtendimentoMensagem $mensagem) => $mensagem->remetente === 'cliente'
                && in_array($mensagem->mensagem, $labelsAssuntos, true))
            ->take(-8)
            ->values() ?? collect();

        return view('cliente.portal', [
            'cliente' => $cliente,
            'cobrancas' => $cobrancas->sortByDesc('id')->values(),
            'cobrancasAbertas' => $abertas->sortBy('vencimento')->values(),
            'cobrancasPagas' => $pagas->sortByDesc('vencimento')->values(),
            'contratos' => $cliente->contratos->sortByDesc('id')->values(),
            'pagamentos' => $pagamentos,
            'notificacoes' => $notificacoes,
            'atendimentosPortal' => $atendimentosPortal,
            'chatAtendimento' => $chatAtendimento,
            'ultimoAtendimentoEncerrado' => $ultimoAtendimentoEncerrado,
            'chatMensagens' => $chatMensagens,
            'chatAssuntos' => $this->chatAssuntos(),
            'saldoAberto' => $saldoAberto,
            'saldoAtrasado' => $saldoAtrasado,
        ]);
    }

    public function storeChat(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');
        $dados = $request->validate([
            'atendimento_id' => ['nullable', 'integer'],
            'assunto' => ['nullable', 'string', 'max:60'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
            'client_token' => ['nullable', 'string', 'max:80'],
            'modo' => ['nullable', 'in:continuar,novo'],
        ], [
            'mensagem.max' => 'A mensagem deve ter no maximo 2000 caracteres.',
        ]);

        $mensagemDigitada = trim((string) ($dados['mensagem'] ?? ''));
        $assuntoRecebido = (string) ($dados['assunto'] ?? '');
        $clientToken = trim((string) ($dados['client_token'] ?? ''));
        $modo = (string) ($dados['modo'] ?? '');

        if ($modo === 'continuar' && $mensagemDigitada === '') {
            $mensagemDigitada = 'Quero continuar este atendimento.';
        }

        if (! array_key_exists($assuntoRecebido, $this->chatAssuntos())) {
            $assuntoRecebido = '';
        }

        if ($mensagemDigitada === '' && $assuntoRecebido === '') {
            return $this->responderChatVazio($request);
        }

        if ($mensagemDigitada !== '' && Str::length($mensagemDigitada) < 2) {
            return $this->responderChatOrientacao($request, 'Pode me mandar um pouco mais de detalhe? Assim eu consigo direcionar melhor para a loja.');
        }

        if ($clientToken !== '' && ($mensagemExistente = $this->localizarMensagemPorToken($cliente, $clientToken))) {
            return $this->responderReenvioChat($request, $mensagemExistente);
        }

        try {
            $resultado = DB::transaction(function () use ($cliente, $dados, $mensagemDigitada, $assuntoRecebido, $clientToken, $modo): array {
                $atendimento = null;

                if ($modo === 'novo') {
                    PortalAtendimento::query()
                        ->where('cliente_id', $cliente->id)
                        ->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'])
                        ->lockForUpdate()
                        ->get()
                        ->each(fn (PortalAtendimento $aberto) => $aberto->update([
                            'status' => 'fechado',
                            'encerrado_em' => now(),
                            'atualizado_em' => now(),
                        ]));
                } elseif (! empty($dados['atendimento_id'])) {
                    $atendimento = PortalAtendimento::query()
                        ->where('cliente_id', $cliente->id)
                        ->whereKey($dados['atendimento_id'])
                        ->lockForUpdate()
                        ->first();
                }

                if (! $atendimento && $modo !== 'novo' && ($mensagemDigitada !== '' || $assuntoRecebido !== '')) {
                    $atendimento = PortalAtendimento::query()
                        ->where('cliente_id', $cliente->id)
                        ->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }

                if ($atendimento && in_array($atendimento->status, ['fechado', 'cancelado'], true)) {
                    if ($modo === 'continuar' && $atendimento->status === 'fechado') {
                        $atendimento->update([
                            'status' => 'em_atendimento',
                            'encerrado_em' => null,
                            'atualizado_em' => now(),
                        ]);
                    } else {
                        $atendimento = null;
                    }
                }

                if ($atendimento && $modo !== 'continuar' && $this->atendimentoExpirouPorInatividade($atendimento)) {
                    $this->encerrarPorInatividade($atendimento);
                    $atendimento = null;
                }

                $temAtendenteHumano = $atendimento && $this->atendimentoTemHumano($atendimento);
                $assuntoInferido = $mensagemDigitada !== ''
                    ? $this->inferirAssunto($mensagemDigitada)
                    : ($assuntoRecebido ?: 'outro');

                if ($atendimento) {
                    $assunto = $atendimento->assunto;

                    if (! $temAtendenteHumano && $assuntoRecebido !== '' && $mensagemDigitada === '') {
                        $assunto = $assuntoRecebido;
                    } elseif (! $temAtendenteHumano && $assuntoInferido !== 'outro') {
                        $assunto = $assuntoInferido;
                    }
                } else {
                    $assunto = $assuntoInferido;
                }

                $mensagemCliente = $mensagemDigitada !== ''
                    ? $mensagemDigitada
                    : ($this->chatAssuntos()[$assunto] ?? 'Falar com a loja');
                $prioridade = in_array($assunto, ['debito', 'pagamento', 'moto_parada'], true) ? 'alta' : 'normal';
                $mensagemEhOpcao = in_array($mensagemCliente, $this->chatAssuntos(), true);
                $deveGravarMensagemCliente = ! ($temAtendenteHumano && $mensagemDigitada === '' && $mensagemEhOpcao);

                if (! $atendimento) {
                    $atendimento = PortalAtendimento::create([
                        'cliente_id' => $cliente->id,
                        'loja_id' => $cliente->loja_id,
                        'assistente' => 'Lau',
                        'assunto' => $assunto,
                        'prioridade' => $prioridade,
                        'status' => 'novo',
                        'mensagem' => $mensagemCliente,
                        'ultima_mensagem_em' => now(),
                        'atualizado_em' => now(),
                    ]);
                } else {
                    $atendimento->update([
                        'assunto' => $temAtendenteHumano ? $atendimento->assunto : $assunto,
                        'prioridade' => $temAtendenteHumano ? $atendimento->prioridade : $prioridade,
                        'status' => $temAtendenteHumano ? 'aguardando_humano' : 'em_atendimento',
                        'mensagem' => $deveGravarMensagemCliente ? $mensagemCliente : $atendimento->mensagem,
                        'ultima_mensagem_em' => $deveGravarMensagemCliente ? now() : $atendimento->ultima_mensagem_em,
                        'atualizado_em' => now(),
                        'encerrado_em' => null,
                    ]);
                }

                $mensagemDoCliente = null;
                if ($deveGravarMensagemCliente) {
                    $mensagemDoCliente = PortalAtendimentoMensagem::create([
                        'portal_atendimento_id' => $atendimento->id,
                        'remetente' => 'cliente',
                        'client_token' => $clientToken !== '' ? $clientToken : null,
                        'mensagem' => $mensagemCliente,
                    ]);
                }

                $mensagemDoBot = null;
                $resposta = [
                    'humano' => (bool) $temAtendenteHumano,
                    'texto' => null,
                    'mostrar_opcoes' => false,
                    'encerrar' => false,
                ];

                if (! $temAtendenteHumano && $mensagemDoCliente) {
                    $resposta = array_merge($resposta, $this->respostaAutomatica($cliente, $atendimento, $mensagemCliente));
                    $mensagemDoBot = PortalAtendimentoMensagem::create([
                        'portal_atendimento_id' => $atendimento->id,
                        'remetente' => 'bot',
                        'mensagem' => $resposta['texto'],
                    ]);

                    $atendimento->update([
                        'status' => $resposta['encerrar']
                            ? 'fechado'
                            : ($resposta['humano']
                                ? 'aguardando_humano'
                                : ($resposta['mostrar_opcoes'] ? 'novo' : 'em_atendimento')),
                        'resposta' => $resposta['texto'],
                        'ultima_mensagem_em' => now(),
                        'atualizado_em' => now(),
                        'encerrado_em' => $resposta['encerrar'] ? now() : null,
                    ]);
                }

                $cliente->update(['crm_ultimo_contato_em' => now()]);
                $atendimento->refresh();

                return [
                    'atendimento' => $atendimento,
                    'mensagem_cliente' => $mensagemDoCliente,
                    'mensagem_bot' => $mensagemDoBot,
                    'resposta' => $resposta,
                ];
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($clientToken !== '' && ($mensagemExistente = $this->localizarMensagemPorToken($cliente, $clientToken))) {
                return $this->responderReenvioChat($request, $mensagemExistente);
            }

            throw $exception;
        }

        /** @var PortalAtendimento $atendimento */
        $atendimento = $resultado['atendimento'];
        /** @var PortalAtendimentoMensagem|null $mensagemDoCliente */
        $mensagemDoCliente = $resultado['mensagem_cliente'];
        /** @var PortalAtendimentoMensagem|null $mensagemDoBot */
        $mensagemDoBot = $resultado['mensagem_bot'];
        $resposta = $resultado['resposta'];
        $humano = $this->atendimentoTemHumano($atendimento);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'atendimento_id' => $atendimento->id,
                'assunto' => $atendimento->assunto,
                'assunto_label' => $this->chatAssuntos()[$atendimento->assunto] ?? 'Atendimento',
                'status' => $atendimento->status,
                'humano' => $humano,
                'mostrar_opcoes' => (bool) ($resposta['mostrar_opcoes'] ?? false),
                'encerrado' => in_array($atendimento->status, ['fechado', 'cancelado'], true),
                'inatividade_minutos' => self::CHAT_INACTIVITY_MINUTES,
                'mensagens' => array_values(array_filter([
                    $mensagemDoCliente ? $this->formatarMensagemChat($mensagemDoCliente) : null,
                    $mensagemDoBot ? $this->formatarMensagemChat($mensagemDoBot) : null,
                ])),
            ]);
        }

        return redirect(route('cliente.portal').'#chat')
            ->with('chat_success', $humano
                ? 'O Lau chamou a loja para continuar este atendimento.'
                : 'O Lau respondeu sua mensagem. Voce pode continuar a conversa.');
    }

    public function syncChat(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');
        $atendimentoId = $request->integer('atendimento_id');

        if (! $atendimentoId) {
            return response()->json(['ok' => true, 'mensagens' => []]);
        }

        $atendimento = PortalAtendimento::query()
            ->where('cliente_id', $cliente->id)
            ->whereKey($atendimentoId)
            ->firstOrFail();

        if (in_array($atendimento->status, ['fechado', 'cancelado'], true)) {
            return response()->json([
                'ok' => true,
                'atendimento_id' => $atendimento->id,
                'status' => $atendimento->status,
                'humano' => false,
                'encerrado' => true,
                'mostrar_opcoes' => true,
                'mensagens' => [],
            ]);
        }

        if ($this->atendimentoExpirouPorInatividade($atendimento)) {
            $mensagem = $this->encerrarPorInatividade($atendimento);

            return response()->json([
                'ok' => true,
                'atendimento_id' => $atendimento->id,
                'status' => $atendimento->status,
                'humano' => false,
                'encerrado_por_inatividade' => true,
                'inatividade_minutos' => self::CHAT_INACTIVITY_MINUTES,
                'mensagens' => $mensagem && $mensagem->id > $request->integer('after_id')
                    ? [$this->formatarMensagemChat($mensagem)]
                    : [],
            ]);
        }

        $mensagens = $atendimento->mensagens()
            ->where('id', '>', $request->integer('after_id'))
            ->get()
            ->reject(fn (PortalAtendimentoMensagem $mensagem) => $this->mensagemEhOpcaoCliente($mensagem))
            ->map(fn (PortalAtendimentoMensagem $mensagem) => $this->formatarMensagemChat($mensagem))
            ->values();

        return response()->json([
            'ok' => true,
            'atendimento_id' => $atendimento->id,
            'status' => $atendimento->status,
            'humano' => $this->atendimentoTemHumano($atendimento),
            'mostrar_opcoes' => $atendimento->status === 'novo',
            'encerrado_por_inatividade' => false,
            'inatividade_minutos' => self::CHAT_INACTIVITY_MINUTES,
            'mensagens' => $mensagens,
        ]);
    }

    public function closeChat(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');
        $atendimento = null;

        if ($request->integer('atendimento_id')) {
            $atendimento = PortalAtendimento::query()
                ->where('cliente_id', $cliente->id)
                ->whereKey($request->integer('atendimento_id'))
                ->first();
        }

        if ($atendimento) {
            $atendimento->update([
                'status' => 'fechado',
                'encerrado_em' => now(),
                'atualizado_em' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function formatarMensagemChat(PortalAtendimentoMensagem $mensagem): array
    {
        return [
            'id' => $mensagem->id,
            'remetente' => $mensagem->remetente,
            'client_token' => $mensagem->client_token,
            'remetente_nome' => $mensagem->remetente_nome,
            'mensagem' => $mensagem->mensagem,
            'hora' => $mensagem->criado_em?->format('H:i') ?? now()->format('H:i'),
            'criado_em' => $mensagem->criado_em?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    private function localizarMensagemPorToken(Cliente $cliente, string $clientToken): ?PortalAtendimentoMensagem
    {
        return PortalAtendimentoMensagem::query()
            ->where('client_token', $clientToken)
            ->where('remetente', 'cliente')
            ->whereHas('atendimento', fn ($query) => $query->where('cliente_id', $cliente->id))
            ->with('atendimento')
            ->first();
    }

    private function responderReenvioChat(Request $request, PortalAtendimentoMensagem $mensagemCliente): RedirectResponse|JsonResponse
    {
        $atendimento = $mensagemCliente->atendimento;
        $proximaMensagemClienteId = $atendimento->mensagens()
            ->where('remetente', 'cliente')
            ->where('id', '>', $mensagemCliente->id)
            ->min('id');

        $mensagensQuery = $atendimento->mensagens()
            ->where('id', '>=', $mensagemCliente->id)
            ->orderBy('id');

        if ($proximaMensagemClienteId) {
            $mensagensQuery->where('id', '<', $proximaMensagemClienteId);
        }

        $mensagens = $mensagensQuery
            ->limit(3)
            ->get()
            ->map(fn (PortalAtendimentoMensagem $mensagem) => $this->formatarMensagemChat($mensagem))
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'duplicado' => true,
                'atendimento_id' => $atendimento->id,
                'assunto' => $atendimento->assunto,
                'assunto_label' => $this->chatAssuntos()[$atendimento->assunto] ?? 'Atendimento',
                'status' => $atendimento->status,
                'humano' => $this->atendimentoTemHumano($atendimento),
                'mostrar_opcoes' => $atendimento->status === 'novo' || in_array($atendimento->status, ['fechado', 'cancelado'], true),
                'encerrado' => in_array($atendimento->status, ['fechado', 'cancelado'], true),
                'inatividade_minutos' => self::CHAT_INACTIVITY_MINUTES,
                'mensagens' => $mensagens,
            ]);
        }

        return redirect(route('cliente.portal').'#chat')
            ->with('chat_success', 'Sua mensagem ja foi recebida. A conversa continua abaixo.');
    }

    private function responderChatVazio(Request $request): RedirectResponse|JsonResponse
    {
        return $this->responderChatOrientacao(
            $request,
            'Oi. Para eu te ajudar melhor, escolha uma opcao abaixo ou escreva em uma frase o que voce precisa. Se for algo especifico, eu chamo um atendente da loja.'
        );
    }

    private function responderChatOrientacao(Request $request, string $texto): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'atendimento_id' => null,
                'status' => 'triagem',
                'humano' => false,
                'mostrar_opcoes' => true,
                'mensagens' => [[
                    'id' => null,
                    'remetente' => 'bot',
                    'mensagem' => $texto,
                    'hora' => now()->format('H:i'),
                    'criado_em' => now()->toIso8601String(),
                ]],
            ]);
        }

        return redirect(route('cliente.portal').'#chat')->with('chat_success', $texto);
    }

    private function atendimentoTemHumano(PortalAtendimento $atendimento): bool
    {
        return in_array($atendimento->status, ['aguardando_humano', 'respondido'], true)
            || ($atendimento->atendente_id && $atendimento->status === 'em_atendimento')
            || $atendimento->mensagens()->where('remetente', 'humano')->exists();
    }

    private function atendimentoExpirouPorInatividade(PortalAtendimento $atendimento): bool
    {
        if (in_array($atendimento->status, ['fechado', 'cancelado'], true)) {
            return false;
        }

        $ultimaMensagem = $atendimento->relationLoaded('mensagens')
            ? $atendimento->mensagens->sortByDesc('id')->first()
            : $atendimento->mensagens()->latest('id')->first();

        if (! $ultimaMensagem || $ultimaMensagem->remetente === 'cliente') {
            return false;
        }

        return $ultimaMensagem->criado_em
            && $ultimaMensagem->criado_em->lte(now()->subMinutes(self::CHAT_INACTIVITY_MINUTES));
    }

    private function encerrarPorInatividade(PortalAtendimento $atendimento): ?PortalAtendimentoMensagem
    {
        if (in_array($atendimento->status, ['fechado', 'cancelado'], true)) {
            return null;
        }

        $texto = 'Como ficou sem retorno por alguns minutos, encerrei este atendimento. Quando precisar, abra uma nova conversa por aqui.';
        $mensagem = $atendimento->mensagens()
            ->where('remetente', 'bot')
            ->where('mensagem', $texto)
            ->latest('id')
            ->first();

        if (! $mensagem) {
            $mensagem = PortalAtendimentoMensagem::create([
                'portal_atendimento_id' => $atendimento->id,
                'remetente' => 'bot',
                'mensagem' => $texto,
            ]);
        }

        $atendimento->update([
            'status' => 'fechado',
            'resposta' => $texto,
            'encerrado_em' => now(),
            'ultima_mensagem_em' => now(),
            'atualizado_em' => now(),
        ]);

        return $mensagem;
    }

    private function mensagemEhOpcaoCliente(PortalAtendimentoMensagem $mensagem): bool
    {
        return $mensagem->remetente === 'cliente'
            && in_array($mensagem->mensagem, $this->chatAssuntos(), true);
    }

    private function inferirAssunto(string $mensagem): string
    {
        $texto = Str::lower(Str::ascii($mensagem));

        return match (true) {
            str_contains($texto, 'atendente') || str_contains($texto, 'humano') || str_contains($texto, 'pessoa') || str_contains($texto, 'loja') => 'outro',
            str_contains($texto, 'pix') || str_contains($texto, 'pag') || str_contains($texto, 'comprovante') => 'pagamento',
            str_contains($texto, 'debito') || str_contains($texto, 'divida') || str_contains($texto, 'boleto') || str_contains($texto, 'fatura') => 'debito',
            str_contains($texto, 'contrato') || str_contains($texto, 'renov') || str_contains($texto, 'cancel') => 'contrato',
            str_contains($texto, 'moto') || str_contains($texto, 'manut') || str_contains($texto, 'troca') || str_contains($texto, 'quebrou') => 'moto_parada',
            str_contains($texto, 'document') || str_contains($texto, 'cnh') || str_contains($texto, 'cpf') => 'documentos',
            str_contains($texto, 'multa') || str_contains($texto, 'infracao') => 'multa',
            default => 'outro',
        };
    }

    private function respostaAutomatica(Cliente $cliente, PortalAtendimento $atendimento, string $mensagem): array
    {
        $cliente->loadMissing('cobrancas');
        $abertas = $cliente->cobrancas->where('status', '!=', 'paga');
        $saldoAberto = $abertas->sum(fn (Cobranca $cobranca) => max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago));
        $proxima = $abertas->sortBy('vencimento')->first();
        $primeiroNome = Str::before($cliente->nome, ' ') ?: 'tudo bem';
        $texto = Str::lower(Str::ascii(trim($mensagem)));
        $quantidadeRespostasBot = $atendimento->mensagens()->where('remetente', 'bot')->count();
        $ultimaRespostaBot = (string) $atendimento->mensagens()
            ->where('remetente', 'bot')
            ->latest('id')
            ->value('mensagem');

        $querHumano = Str::contains($texto, ['atendente', 'humano', 'pessoa', 'falar com alguem', 'falar com a loja', 'quero falar']);
        $querNegociar = Str::contains($texto, ['negociar', 'negociacao', 'parcelar', 'parcelamento', 'desconto', 'acordo']);
        $querEncerrar = Str::contains($texto, ['encerrar', 'finalizar atendimento', 'pode fechar', 'resolvido', 'ja resolveu']);
        $agradeceu = preg_match('/\b(obrigad[oa]?|valeu|agradeco|agradecido)\b/', $texto) === 1;
        $saudacaoCurta = preg_match('/^(oi|ola|bom dia|boa tarde|boa noite|e ai|tudo bem)[!,. ]*$/', $texto) === 1;

        if ($querEncerrar) {
            return [
                'humano' => false,
                'mostrar_opcoes' => true,
                'encerrar' => true,
                'texto' => "Certo, {$primeiroNome}. Atendimento encerrado. Quando precisar, e so iniciar uma nova conversa por aqui.",
            ];
        }

        if ($agradeceu && ! $querHumano) {
            return [
                'humano' => false,
                'mostrar_opcoes' => true,
                'encerrar' => true,
                'texto' => "Por nada, {$primeiroNome}. Fico por aqui. Se precisar de outra coisa, escolha uma opcao ou escreva sua duvida.",
            ];
        }

        if ($saudacaoCurta) {
            return [
                'humano' => false,
                'mostrar_opcoes' => true,
                'encerrar' => false,
                'texto' => "Oi, {$primeiroNome}! Estou por aqui. Voce pode perguntar sobre fatura, PIX, contrato, moto, documentos ou multa.",
            ];
        }

        if ($querHumano) {
            return [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => "Claro, {$primeiroNome}. Vou chamar um atendente da loja e manter todo o contexto desta conversa para voce nao precisar repetir.",
            ];
        }

        $resposta = match ($atendimento->assunto) {
            'debito' => $this->respostaAutomaticaDebito($primeiroNome, $texto, $saldoAberto, $proxima, $querNegociar, $quantidadeRespostasBot),
            'pagamento' => [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => "Certo, {$primeiroNome}. Pode enviar o comprovante, o horario do PIX ou o nome de quem pagou. Vou manter a conversa aberta para um atendente conferir.",
            ],
            'contrato' => [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => 'Entendi. Me diga se e renovacao, cancelamento ou duvida sobre contrato. Um atendente da loja continua por aqui com o historico da conversa.',
            ],
            'moto_parada' => [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => 'Vamos agilizar isso. Envie a placa, sua localizacao e diga se a moto ainda liga. Vou acionar a loja para acompanhar por aqui.',
            ],
            'documentos' => [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => 'Qual documento voce precisa atualizar ou consultar? Pode escrever CNH, CPF, comprovante ou contrato, e a loja segue daqui.',
            ],
            'multa' => [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => 'Pode mandar numero da multa, data ou placa, se tiver. Vou chamar um atendente para analisar e responder por aqui.',
            ],
            default => $quantidadeRespostasBot === 0
                ? [
                    'humano' => false,
                    'mostrar_opcoes' => true,
                    'encerrar' => false,
                    'texto' => "Entendi, {$primeiroNome}. Para eu direcionar certo: sua duvida e sobre fatura, pagamento, contrato, moto, documentos ou multa?",
                ]
                : [
                    'humano' => true,
                    'mostrar_opcoes' => false,
                    'encerrar' => false,
                    'texto' => "Ainda nao consegui identificar com seguranca o que voce precisa, {$primeiroNome}. Vou chamar a loja e enviar todo o historico desta conversa.",
                ],
        };

        if (! $resposta['humano'] && $ultimaRespostaBot !== '' && trim($ultimaRespostaBot) === trim($resposta['texto'])) {
            $resposta['texto'] = "Para nao repetir a mesma informacao, {$primeiroNome}, me diga qual ponto voce quer detalhar ou peça para falar com um atendente.";
            $resposta['mostrar_opcoes'] = true;
        }

        return $resposta;
    }

    private function respostaAutomaticaDebito(
        string $primeiroNome,
        string $texto,
        float $saldoAberto,
        ?Cobranca $proxima,
        bool $querNegociar,
        int $quantidadeRespostasBot
    ): array {
        if ($saldoAberto <= 0) {
            return [
                'humano' => false,
                'mostrar_opcoes' => true,
                'encerrar' => false,
                'texto' => "Boa noticia, {$primeiroNome}: nao encontrei debito em aberto agora. Se algo nao bater com o que voce esta vendo, escreva o numero da fatura ou peça um atendente.",
            ];
        }

        if ($querNegociar) {
            return [
                'humano' => true,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => "Entendi, {$primeiroNome}. Vou chamar um atendente para verificar as opcoes de negociacao do saldo de ".\App\Support\Locx::moeda($saldoAberto).'.',
            ];
        }

        if (Str::contains($texto, ['vencimento', 'vence', 'venceu', 'data'])) {
            return [
                'humano' => false,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => $proxima
                    ? "A proxima fatura em aberto e a #{$proxima->id}, com vencimento em ".$proxima->vencimento?->format('d/m/Y').'.'
                    : 'Nao encontrei uma data de vencimento disponivel agora.',
            ];
        }

        if (Str::contains($texto, ['pix', 'pagar', 'pagamento', 'qr code', 'copia e cola', 'chave'])) {
            return [
                'humano' => false,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => $proxima
                    ? "Para pagar, abra a fatura #{$proxima->id} no portal e use o botao de copiar PIX. O saldo atual e ".\App\Support\Locx::moeda($saldoAberto).'.'
                    : 'Nao encontrei uma fatura aberta para gerar o pagamento agora.',
            ];
        }

        if (Str::contains($texto, ['valor', 'quanto', 'saldo', 'total'])) {
            return [
                'humano' => false,
                'mostrar_opcoes' => false,
                'encerrar' => false,
                'texto' => "Seu saldo em aberto neste momento e ".\App\Support\Locx::moeda($saldoAberto).'.',
            ];
        }

        return [
            'humano' => false,
            'mostrar_opcoes' => false,
            'encerrar' => false,
            'texto' => $quantidadeRespostasBot === 0
                ? "Vi aqui, {$primeiroNome}: seu saldo em aberto e ".\App\Support\Locx::moeda($saldoAberto).".\nFatura: #".$proxima?->id."\nVencimento: ".$proxima?->vencimento?->format('d/m/Y')."\nVoce quer saber o valor, o vencimento, como pagar ou falar sobre negociacao?"
                : 'Posso detalhar o valor, o vencimento, o PIX ou chamar a loja para negociar. Qual desses pontos voce precisa?',
        ];
    }

    private function chatAssuntos(): array
    {
        return [
            'debito' => 'Debito/fatura',
            'pagamento' => 'PIX/comprovante',
            'contrato' => 'Contrato',
            'moto_parada' => 'Moto/manutencao',
            'documentos' => 'Documentos',
            'multa' => 'Multa',
            'outro' => 'Falar com a loja',
        ];
    }
}
