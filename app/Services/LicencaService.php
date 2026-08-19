<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\LicencaConfig;
use App\Models\LicencaValidacaoLog;
use App\Models\Loja;
use App\Models\Motocicleta;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class LicencaService
{
    public function config(): LicencaConfig
    {
        return LicencaConfig::atual();
    }

    public function resumo(): array
    {
        $config = $this->config();
        $status = $this->statusLocal($config);

        return [
            'config' => $config,
            'status' => $status,
            'uso' => $this->usoLocal(),
        ];
    }

    public function bloqueioParaAcao(string $modulo, string $acao): ?string
    {
        if (! $this->controleAtivo() || in_array($modulo, ['dashboard', 'configuracoes'], true)) {
            return null;
        }

        $config = $this->config();
        if (! $this->moduloLiberado($modulo, $config)) {
            return 'Modulo nao liberado para esta licenca.';
        }

        $status = $this->statusLocal($config);
        $codigo = (string) ($status['codigo'] ?? '');
        if (! in_array($codigo, ['ativa', 'trial', 'teste'], true)) {
            return $status['mensagem'] ?? 'Licenca sem autorizacao para novas operacoes.';
        }

        return null;
    }

    public function bloqueioParaCriarRecurso(string $recurso): ?string
    {
        if (! $this->controleAtivo()) {
            return null;
        }

        $config = $this->config();
        $status = $this->statusLocal($config);
        if (! in_array((string) ($status['codigo'] ?? ''), ['ativa', 'trial', 'teste'], true)) {
            return $status['mensagem'] ?? 'Licenca sem autorizacao para novas operacoes.';
        }

        return match ($recurso) {
            'lojas' => config('installation.single_store') && Loja::query()->exists()
                ? 'Esta instalação possui uma única loja.'
                : ($config->max_lojas && Loja::query()->count() >= (int) $config->max_lojas
                    ? 'Limite de lojas da licenca atingido.'
                    : null),
            'usuarios' => $config->max_usuarios && User::query()->count() >= (int) $config->max_usuarios
                ? 'Limite de usuarios da licenca atingido.'
                : null,
            default => null,
        };
    }

    public function validarOnline(): array
    {
        $config = $this->config();
        if ($config->modo !== 'online' || ! $config->ativo) {
            return ['ok' => true, 'status' => 'local', 'mensagem' => 'Validacao online desativada.'];
        }

        if (blank($config->api_url) || blank($config->licenca_chave)) {
            return ['ok' => false, 'status' => 'pendente', 'erro' => 'Informe a URL da API e a chave da licenca.'];
        }

        $payload = $this->payload($config);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('rental.licenca.timeout', 10))
                ->post(rtrim((string) $config->api_url, '/').'/validar-licenca', $payload);

            $json = $response->json();
            $this->log($config, 'online', $response->successful() ? 'recebido' : 'erro', $response->status(), $this->mascararPayload($payload), $json ?: $response->body());

            if (! $response->successful() || ! is_array($json)) {
                return ['ok' => false, 'status' => 'erro', 'erro' => $response->body() ?: 'Resposta invalida da API de licencas.'];
            }

            $this->atualizarComResposta($config, $json);

            return ['ok' => true, 'status' => (string) ($json['status'] ?? 'ativa'), 'resposta' => $json];
        } catch (Throwable $exception) {
            $this->log($config, 'online', 'erro', null, $this->mascararPayload($payload), null, $exception->getMessage());

            return ['ok' => false, 'status' => 'offline', 'erro' => $exception->getMessage()];
        }
    }

    public function controleAtivo(): bool
    {
        $config = $this->config();

        return $config->modo === 'online' && $config->ativo;
    }

    public function salvarConfig(array $dados): LicencaConfig
    {
        $config = $this->config();
        if (blank($dados['licenca_chave'] ?? null)) {
            unset($dados['licenca_chave']);
        }

        $config->update($dados + ['atualizado_em' => now()]);

        return $config->fresh();
    }

    private function atualizarComResposta(LicencaConfig $config, array $json): void
    {
        $config->update([
            'status' => (string) ($json['status'] ?? 'ativa'),
            'plano' => $json['plano'] ?? $json['plan'] ?? $config->plano,
            'empresa_nome' => $json['empresa'] ?? $json['empresa_nome'] ?? $config->empresa_nome,
            'max_lojas' => $json['max_lojas'] ?? $config->max_lojas,
            'max_usuarios' => $json['max_usuarios'] ?? $config->max_usuarios,
            'modulos_json' => $json['modulos'] ?? $json['modules'] ?? $config->modulos_json,
            'vence_em' => $json['vence_em'] ?? $json['expires_at'] ?? $config->vence_em,
            'tolerancia_offline_dias' => $json['tolerancia_offline_dias'] ?? $json['grace_days'] ?? $config->tolerancia_offline_dias,
            'ultima_validacao_em' => now(),
            'ultima_validacao_ok_em' => now(),
            'proxima_validacao_em' => now()->addHours((int) config('rental.licenca.cache_horas', 12)),
            'ultimo_payload' => $json,
            'mensagem' => $json['mensagem'] ?? $json['message'] ?? null,
            'atualizado_em' => now(),
        ]);
    }

    private function statusLocal(LicencaConfig $config): array
    {
        if ($config->modo !== 'online' || ! $config->ativo) {
            return ['codigo' => 'local', 'classe' => 'info', 'mensagem' => 'Licenca online desativada.'];
        }

        if ($config->status === 'bloqueada') {
            return ['codigo' => 'bloqueada', 'classe' => 'danger', 'mensagem' => $config->mensagem ?: 'Licenca bloqueada pelo portal.'];
        }

        if ($config->vence_em && $config->vence_em->isPast()) {
            return ['codigo' => 'vencida', 'classe' => 'danger', 'mensagem' => 'Licenca vencida em '.$config->vence_em->format('d/m/Y').'.'];
        }

        if ($config->ultima_validacao_ok_em) {
            $limite = $config->ultima_validacao_ok_em->copy()->addDays((int) $config->tolerancia_offline_dias);
            if ($limite->isPast()) {
                return ['codigo' => 'sem_validacao', 'classe' => 'danger', 'mensagem' => 'Sem validacao online dentro da tolerancia.'];
            }
        } elseif (! in_array((string) $config->status, ['ativa', 'trial', 'teste'], true)) {
            return ['codigo' => 'pendente', 'classe' => 'warn', 'mensagem' => 'Licenca online ainda nao validada.'];
        }

        return ['codigo' => $config->status ?: 'pendente', 'classe' => 'ok', 'mensagem' => $config->mensagem ?: 'Licenca em acompanhamento.'];
    }

    private function moduloLiberado(string $modulo, LicencaConfig $config): bool
    {
        $modulos = collect($config->modulos_json ?: [])
            ->map(fn ($item) => strtolower((string) $item))
            ->filter()
            ->values();

        if ($modulos->isEmpty()) {
            return true;
        }

        if ($modulos->contains(strtolower($modulo))) {
            return true;
        }

        $aliases = [
            'pix' => ['financeiro', 'contas', 'cobrancas', 'inadimplencia', 'bancos', 'pagbank', 'asaas', 'sicoob', 'itau'],
            'multi_loja' => ['lojas'],
            'multi-loja' => ['lojas'],
            'pagamentos' => ['financeiro', 'contas', 'cobrancas', 'inadimplencia', 'bancos', 'pagbank', 'asaas', 'sicoob', 'itau'],
        ];

        foreach ($aliases as $chave => $modulosAlias) {
            if ($modulos->contains($chave) && in_array($modulo, $modulosAlias, true)) {
                return true;
            }
        }

        return false;
    }

    private function payload(LicencaConfig $config): array
    {
        return [
            'license_key' => $config->licenca_chave,
            'instance_id' => $config->instancia_id,
            'app' => config('branding.product_id'),
            'version' => config('app.version', 'local'),
            'empresa_documento' => $config->empresa_documento,
            'uso' => $this->usoLocal(),
        ];
    }

    private function usoLocal(): array
    {
        return [
            'lojas' => Loja::query()->count(),
            'usuarios' => User::query()->count(),
            'motos' => Motocicleta::query()->count(),
            'cobrancas' => Cobranca::query()->count(),
        ];
    }

    private function mascararPayload(array $payload): array
    {
        if (isset($payload['license_key'])) {
            $payload['license_key'] = '***';
        }

        return $payload;
    }

    private function log(
        LicencaConfig $config,
        string $tipo,
        string $status,
        ?int $httpCode = null,
        array|string|null $payload = null,
        array|string|null $resposta = null,
        ?string $erro = null
    ): void {
        LicencaValidacaoLog::create([
            'licenca_config_id' => $config->id,
            'tipo' => $tipo,
            'status' => $status,
            'http_code' => $httpCode,
            'payload' => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload,
            'resposta' => is_array($resposta) ? json_encode($resposta, JSON_UNESCAPED_UNICODE) : $resposta,
            'erro' => $erro,
            'criado_em' => now(),
        ]);
    }
}
