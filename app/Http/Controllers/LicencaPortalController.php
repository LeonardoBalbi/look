<?php

namespace App\Http\Controllers;

use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPagamento;
use App\Models\LicencaPortalPlano;
use App\Models\LicencaPortalValidacaoLog;
use App\Models\User;
use App\Services\LicencaPagamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LicencaPortalController extends Controller
{
    public function __construct(private readonly LicencaPagamentoService $pagamentosService) {}

    public function index(Request $request): View
    {
        $this->autorizarPortal($request->user());

        return view('licencas_portal.index', [
            'clientes' => LicencaPortalCliente::query()->withCount('licencas')->latest('id')->get(),
            'planos' => LicencaPortalPlano::query()->withCount('licencas')->orderBy('nome')->get(),
            'licencas' => LicencaPortalLicenca::query()->with('cliente', 'plano')->withCount('pagamentos')->latest('id')->get(),
            'pagamentos' => LicencaPortalPagamento::query()->with('cliente', 'licenca', 'plano')->latest('id')->limit(100)->get(),
            'logs' => LicencaPortalValidacaoLog::query()->with('licenca.cliente')->latest('id')->limit(20)->get(),
            'apiUrl' => url('/api/licencas-portal'),
            'webhookUrl' => url('/api/licencas-portal/webhooks/pagamentos/{gateway}'),
            'clienteEdit' => $request->integer('cliente_edit') ? LicencaPortalCliente::findOrFail($request->integer('cliente_edit')) : null,
            'planoEdit' => $request->integer('plano_edit') ? LicencaPortalPlano::findOrFail($request->integer('plano_edit')) : null,
            'licencaEdit' => $request->integer('licenca_edit') ? LicencaPortalLicenca::findOrFail($request->integer('licenca_edit')) : null,
        ]);
    }

    public function salvarCliente(Request $request): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $cliente = $request->integer('id')
            ? LicencaPortalCliente::findOrFail($request->integer('id'))
            : new LicencaPortalCliente;

        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'nome' => ['required', 'string', 'max:180'],
            'documento' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['ativo', 'bloqueado', 'cancelado'])],
        ]);

        $cliente->fill(collect($dados)->except('id')->all() + ['atualizado_em' => now()]);
        $cliente->save();

        return redirect(url('/licencas-portal#clientes'))->with('success', 'Cliente salvo no portal.');
    }

    public function salvarPlano(Request $request): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $plano = $request->integer('id')
            ? LicencaPortalPlano::findOrFail($request->integer('id'))
            : new LicencaPortalPlano;

        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'codigo' => ['required', 'string', 'max:80', Rule::unique('licenca_portal_planos', 'codigo')->ignore($plano->id)],
            'nome' => ['required', 'string', 'max:120'],
            'preco' => ['nullable', 'numeric', 'min:0'],
            'max_lojas' => ['nullable', 'integer', 'min:1'],
            'max_usuarios' => ['nullable', 'integer', 'min:1'],
            'modulos' => ['nullable', 'string', 'max:1000'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $plano->fill([
            'codigo' => Str::slug($dados['codigo'], '_'),
            'nome' => $dados['nome'],
            'preco_centavos' => (int) round(((float) ($dados['preco'] ?? 0)) * 100),
            'max_lojas' => $dados['max_lojas'] ?? null,
            'max_usuarios' => $dados['max_usuarios'] ?? null,
            'modulos_json' => $this->normalizarModulos($dados['modulos'] ?? ''),
            'ativo' => $request->boolean('ativo'),
            'atualizado_em' => now(),
        ]);
        $plano->save();

        return redirect(url('/licencas-portal#planos'))->with('success', 'Plano salvo no portal.');
    }

    public function salvarLicenca(Request $request): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $licenca = $request->integer('id')
            ? LicencaPortalLicenca::findOrFail($request->integer('id'))
            : new LicencaPortalLicenca;

        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'cliente_id' => ['required', 'exists:licenca_portal_clientes,id'],
            'plano_id' => ['required', 'exists:licenca_portal_planos,id'],
            'chave' => ['nullable', 'string', 'max:120', Rule::unique('licenca_portal_licencas', 'chave')->ignore($licenca->id)],
            'status' => ['required', Rule::in(['ativa', 'trial', 'teste', 'bloqueada', 'vencida', 'pendente'])],
            'vence_em' => ['nullable', 'date'],
            'tolerancia_offline_dias' => ['required', 'integer', 'min:1', 'max:60'],
            'renovacao_automatica' => ['nullable', 'boolean'],
            'meses_por_renovacao' => ['nullable', 'integer', 'min:1', 'max:24'],
            'desvincular_instancia' => ['nullable', 'boolean'],
            'mensagem' => ['nullable', 'string', 'max:500'],
        ]);

        $licenca->fill(collect($dados)->except(['id', 'chave', 'desvincular_instancia'])->all() + [
            'chave' => blank($dados['chave'] ?? null) ? ($licenca->chave ?: $this->gerarChave()) : strtoupper((string) $dados['chave']),
            'renovacao_automatica' => $request->boolean('renovacao_automatica'),
            'meses_por_renovacao' => $dados['meses_por_renovacao'] ?? $licenca->meses_por_renovacao ?? 1,
            'atualizado_em' => now(),
        ]);
        if ($request->boolean('desvincular_instancia')) {
            $licenca->instancia_id = null;
        }
        $licenca->save();

        return redirect(url('/licencas-portal#licencas'))->with('success', 'Licenca salva no portal.');
    }

    public function alternarBloqueio(Request $request, LicencaPortalLicenca $licenca): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $bloquear = $licenca->status !== 'bloqueada';
        $licenca->update([
            'status' => $bloquear ? 'bloqueada' : 'ativa',
            'mensagem' => $bloquear ? 'Licença bloqueada pela administração comercial.' : 'Licença liberada pela administração comercial.',
            'atualizado_em' => now(),
        ]);

        return redirect(url('/licencas-portal#licencas'))
            ->with('success', $bloquear ? 'Licença bloqueada.' : 'Licença desbloqueada.');
    }

    public function renovar(Request $request, LicencaPortalLicenca $licenca): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $dados = $request->validate([
            'meses' => ['required', 'integer', 'min:1', 'max:24'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'plano_id' => ['nullable', 'exists:licenca_portal_planos,id'],
        ]);

        $pagamento = LicencaPortalPagamento::create([
            'licenca_id' => $licenca->id,
            'cliente_id' => $licenca->cliente_id,
            'plano_id' => $dados['plano_id'] ?? $licenca->plano_id,
            'gateway' => 'manual',
            'referencia_externa' => 'MANUAL-'.strtoupper(Str::random(16)),
            'valor_centavos' => (int) round(((float) ($dados['valor'] ?? 0)) * 100),
            'status' => 'pago',
            'meses_renovacao' => (int) $dados['meses'],
            'vencimento' => today(),
            'pago_em' => now(),
            'atualizado_em' => now(),
        ]);
        $this->pagamentosService->confirmar($pagamento, ['origem' => 'renovacao_manual'], true);

        return redirect(url('/licencas-portal#licencas'))->with('success', 'Licença renovada e pagamento registrado.');
    }

    public function salvarPagamento(Request $request): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $dados = $request->validate([
            'licenca_id' => ['required', 'exists:licenca_portal_licencas,id'],
            'plano_id' => ['nullable', 'exists:licenca_portal_planos,id'],
            'gateway' => ['required', Rule::in(['manual', 'asaas', 'pagbank', 'mercadopago', 'stripe', 'outro'])],
            'referencia_externa' => ['nullable', 'string', 'max:160', 'unique:licenca_portal_pagamentos,referencia_externa'],
            'valor' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['pendente', 'pago', 'cancelado', 'estornado', 'falhou'])],
            'meses_renovacao' => ['required', 'integer', 'min:1', 'max:24'],
            'vencimento' => ['nullable', 'date'],
            'link_pagamento' => ['nullable', 'url', 'max:1000'],
        ]);
        $licenca = LicencaPortalLicenca::findOrFail($dados['licenca_id']);
        $pagamento = LicencaPortalPagamento::create([
            'licenca_id' => $licenca->id,
            'cliente_id' => $licenca->cliente_id,
            'plano_id' => $dados['plano_id'] ?? $licenca->plano_id,
            'gateway' => $dados['gateway'],
            'referencia_externa' => ($dados['referencia_externa'] ?? null) ?: 'PORTAL-'.strtoupper(Str::random(16)),
            'valor_centavos' => (int) round(((float) $dados['valor']) * 100),
            'status' => $dados['status'],
            'meses_renovacao' => $dados['meses_renovacao'],
            'vencimento' => $dados['vencimento'] ?? null,
            'link_pagamento' => $dados['link_pagamento'] ?? null,
            'pago_em' => $dados['status'] === 'pago' ? now() : null,
            'atualizado_em' => now(),
        ]);
        if ($pagamento->status === 'pago') {
            $this->pagamentosService->confirmar($pagamento, ['origem' => 'portal_administrativo'], true);
        }

        return redirect(url('/licencas-portal#pagamentos'))->with('success', 'Pagamento registrado.');
    }

    public function confirmarPagamento(Request $request, LicencaPortalPagamento $pagamento): RedirectResponse
    {
        $this->autorizarPortal($request->user());
        $this->pagamentosService->confirmar($pagamento, ['origem' => 'confirmacao_manual'], true);

        return redirect(url('/licencas-portal#pagamentos'))->with('success', 'Pagamento confirmado e licença renovada.');
    }

    public function webhookPagamento(Request $request, string $gateway): JsonResponse
    {
        $tokenConfigurado = (string) config('services.license_payments.webhook_token');
        $tokenRecebido = (string) ($request->bearerToken() ?: $request->header('X-License-Webhook-Token'));
        if ($tokenConfigurado === '' || ! hash_equals($tokenConfigurado, $tokenRecebido)) {
            return response()->json(['ok' => false, 'mensagem' => 'Webhook não autorizado.'], 401);
        }

        $pagamento = $this->pagamentosService->processarWebhook(Str::slug($gateway, '_'), $request->all());
        if (! $pagamento) {
            return response()->json(['ok' => false, 'mensagem' => 'Referência de pagamento não encontrada.'], 404);
        }

        return response()->json([
            'ok' => true,
            'pagamento_id' => $pagamento->id,
            'status' => $pagamento->status,
            'licenca_status' => $pagamento->licenca?->status,
            'vence_em' => $pagamento->licenca?->vence_em?->format('Y-m-d'),
        ]);
    }

    public function validar(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'license_key' => ['required', 'string', 'max:120'],
            'instance_id' => ['nullable', 'string', 'max:120'],
            'app' => ['nullable', 'string', 'max:80'],
            'version' => ['nullable', 'string', 'max:80'],
            'empresa_documento' => ['nullable', 'string', 'max:30'],
            'uso' => ['nullable', 'array'],
        ]);

        $licenca = LicencaPortalLicenca::query()
            ->with('cliente', 'plano')
            ->where('chave', strtoupper((string) $payload['license_key']))
            ->first();

        if (! $licenca) {
            $resposta = ['status' => 'bloqueada', 'mensagem' => 'Licenca nao encontrada.'];
            $this->registrarLog(null, $payload, $resposta, 'nao_encontrada', $request->ip());

            return response()->json($resposta, 404);
        }

        [$status, $mensagem] = $this->statusLicenca($licenca, $payload);

        if (blank($licenca->instancia_id) && filled($payload['instance_id'] ?? null)) {
            $licenca->instancia_id = (string) $payload['instance_id'];
        }

        $licenca->ultimo_check_em = now();
        $licenca->atualizado_em = now();
        $licenca->save();

        $plano = $licenca->plano;
        $resposta = [
            'status' => $status,
            'plano' => $plano?->codigo,
            'empresa' => $licenca->cliente?->nome,
            'max_lojas' => $plano?->max_lojas,
            'max_usuarios' => $plano?->max_usuarios,
            'modulos' => $plano?->modulos_json ?: [],
            'vence_em' => $licenca->vence_em?->format('Y-m-d'),
            'tolerancia_offline_dias' => $licenca->tolerancia_offline_dias,
            'mensagem' => $mensagem,
        ];

        $this->registrarLog($licenca, $payload, $resposta, $status, $request->ip());

        return response()->json($resposta);
    }

    private function statusLicenca(LicencaPortalLicenca $licenca, array $payload): array
    {
        if (! $licenca->plano?->ativo) {
            return ['bloqueada', 'Plano inativo no portal.'];
        }

        if ($licenca->cliente?->status !== 'ativo') {
            return ['bloqueada', 'Cliente bloqueado ou cancelado no portal.'];
        }

        if (filled($licenca->instancia_id) && filled($payload['instance_id'] ?? null) && $licenca->instancia_id !== $payload['instance_id']) {
            return ['bloqueada', 'Licenca vinculada a outra instalacao.'];
        }

        if ($licenca->vence_em && $licenca->vence_em->isPast()) {
            return ['vencida', 'Licenca vencida em '.$licenca->vence_em->format('d/m/Y').'.'];
        }

        if (! in_array($licenca->status, ['ativa', 'trial', 'teste'], true)) {
            return [$licenca->status, $licenca->mensagem ?: 'Licenca sem liberacao ativa.'];
        }

        return [$licenca->status, $licenca->mensagem ?: 'Licenca liberada pelo portal.'];
    }

    private function registrarLog(?LicencaPortalLicenca $licenca, array $payload, array $resposta, string $status, ?string $ip): void
    {
        LicencaPortalValidacaoLog::create([
            'licenca_id' => $licenca?->id,
            'chave_mascarada' => $this->mascararChave((string) ($payload['license_key'] ?? '')),
            'instancia_id' => $payload['instance_id'] ?? null,
            'ip' => $ip,
            'status' => $status,
            'payload' => json_encode($this->mascararPayload($payload), JSON_UNESCAPED_UNICODE),
            'resposta' => json_encode($resposta, JSON_UNESCAPED_UNICODE),
            'criado_em' => now(),
        ]);
    }

    private function normalizarModulos(string $modulos): array
    {
        return collect(explode(',', $modulos))
            ->map(fn (string $item) => Str::slug(trim($item), '_'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function gerarChave(): string
    {
        do {
            $prefixo = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) config('branding.license_prefix', 'RENTAL'))) ?: 'RENTAL';
            $chave = $prefixo.'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (LicencaPortalLicenca::query()->where('chave', $chave)->exists());

        return $chave;
    }

    private function mascararPayload(array $payload): array
    {
        if (isset($payload['license_key'])) {
            $payload['license_key'] = $this->mascararChave((string) $payload['license_key']);
        }

        return $payload;
    }

    private function mascararChave(string $chave): string
    {
        return $chave === '' ? '' : substr($chave, 0, 7).'***'.substr($chave, -4);
    }

    private function autorizarPortal(User $user): void
    {
        abort_unless($user->isSuperAdmin(), 403, 'Acesso restrito à administração comercial da plataforma.');
    }
}
