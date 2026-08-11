<?php

namespace App\Http\Controllers;

use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPlano;
use App\Models\LicencaPortalValidacaoLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LicencaPortalController extends Controller
{
    public function index(Request $request): View
    {
        $this->autorizarPortal($request->user());

        return view('licencas_portal.index', [
            'clientes' => LicencaPortalCliente::query()->withCount('licencas')->latest('id')->get(),
            'planos' => LicencaPortalPlano::query()->withCount('licencas')->orderBy('nome')->get(),
            'licencas' => LicencaPortalLicenca::query()->with('cliente', 'plano')->latest('id')->get(),
            'logs' => LicencaPortalValidacaoLog::query()->with('licenca.cliente')->latest('id')->limit(20)->get(),
            'apiUrl' => url('/api/licencas-portal'),
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
            'mensagem' => ['nullable', 'string', 'max:500'],
        ]);

        $licenca->fill(collect($dados)->except(['id', 'chave'])->all() + [
            'chave' => blank($dados['chave'] ?? null) ? ($licenca->chave ?: $this->gerarChave()) : strtoupper((string) $dados['chave']),
            'atualizado_em' => now(),
        ]);
        $licenca->save();

        return redirect(url('/licencas-portal#licencas'))->with('success', 'Licenca salva no portal.');
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
