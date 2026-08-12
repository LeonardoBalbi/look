<?php

namespace App\Services;

use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPagamento;
use Illuminate\Support\Facades\DB;

class LicencaPagamentoService
{
    public function confirmar(LicencaPortalPagamento $pagamento, array $payload = [], bool $forcarRenovacao = false): LicencaPortalPagamento
    {
        return DB::transaction(function () use ($pagamento, $payload, $forcarRenovacao): LicencaPortalPagamento {
            $pagamento = LicencaPortalPagamento::query()->lockForUpdate()->findOrFail($pagamento->id);
            $pagamento->status = 'pago';
            $pagamento->pago_em ??= now();
            $pagamento->payload_gateway = $payload
                ? json_encode($payload, JSON_UNESCAPED_UNICODE)
                : $pagamento->payload_gateway;
            $pagamento->atualizado_em = now();
            $pagamento->save();

            $licenca = LicencaPortalLicenca::query()->lockForUpdate()->findOrFail($pagamento->licenca_id);
            if (! $pagamento->renovado_em && ($forcarRenovacao || $licenca->renovacao_automatica)) {
                $base = $licenca->vence_em && $licenca->vence_em->isFuture()
                    ? $licenca->vence_em->copy()
                    : today();
                $licenca->plano_id = $pagamento->plano_id ?: $licenca->plano_id;
                $licenca->vence_em = $base->addMonthsNoOverflow(max(1, (int) $pagamento->meses_renovacao));
                $licenca->status = 'ativa';
                $licenca->mensagem = 'Licença renovada após confirmação do pagamento.';
                $licenca->atualizado_em = now();
                $licenca->save();

                $pagamento->renovado_em = now();
                $pagamento->save();
            }

            return $pagamento->fresh(['licenca', 'cliente', 'plano']);
        });
    }

    public function processarWebhook(string $gateway, array $payload): ?LicencaPortalPagamento
    {
        $referencia = (string) ($payload['referencia_externa']
            ?? $payload['external_reference']
            ?? $payload['reference']
            ?? $payload['payment_id']
            ?? $payload['id']
            ?? '');
        if ($referencia === '') {
            return null;
        }

        $pagamento = LicencaPortalPagamento::query()
            ->where('referencia_externa', $referencia)
            ->first();
        if (! $pagamento) {
            return null;
        }

        $statusRecebido = strtolower((string) ($payload['status'] ?? $payload['event'] ?? 'pendente'));
        $status = match ($statusRecebido) {
            'paid', 'pago', 'received', 'recebido', 'confirmed', 'confirmado', 'payment_confirmed', 'payment_received' => 'pago',
            'canceled', 'cancelled', 'cancelado' => 'cancelado',
            'refunded', 'estornado' => 'estornado',
            'failed', 'falhou', 'recusado' => 'falhou',
            default => 'pendente',
        };

        $pagamento->gateway = $gateway;
        $pagamento->status = $status;
        $pagamento->payload_gateway = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $pagamento->atualizado_em = now();
        $pagamento->save();

        return $status === 'pago'
            ? $this->confirmar($pagamento, $payload)
            : $pagamento->fresh(['licenca', 'cliente', 'plano']);
    }
}
