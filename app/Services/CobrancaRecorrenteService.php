<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Contrato;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CobrancaRecorrenteService
{
    public function __construct(
        private readonly PixGatewayService $pixGateway,
        private readonly WhatsAppService $whatsApp,
        private readonly EmailCobrancaService $emailCobranca,
    ) {}

    public function gerar(
        ?CarbonInterface $ate = null,
        bool $dryRun = false,
        bool $gerarPix = false,
        bool $enviarWhatsApp = false,
        bool $enviarEmail = false,
        int $maxPorContrato = 12,
    ): array {
        $ate = Carbon::parse($ate ?: today())->startOfDay();
        $maxPorContrato = max(1, $maxPorContrato);
        $resultado = [
            'dry_run' => $dryRun,
            'ate' => $ate->format('Y-m-d'),
            'contratos' => 0,
            'criadas' => 0,
            'existentes' => 0,
            'pix_gerados' => 0,
            'whatsapp_enviados' => 0,
            'emails_enviados' => 0,
            'limitados' => 0,
            'itens' => [],
            'erros' => [],
        ];

        Contrato::query()
            ->with('cliente')
            ->where('status', 'ativo')
            ->where('cobranca_automatica', true)
            ->orderBy('id')
            ->chunkById(100, function (Collection $contratos) use (
                $ate,
                $dryRun,
                $gerarPix,
                $enviarWhatsApp,
                $enviarEmail,
                $maxPorContrato,
                &$resultado
            ): void {
                foreach ($contratos as $contrato) {
                    $resultado['contratos']++;
                    $this->processarContrato($contrato, $ate, $dryRun, $gerarPix, $enviarWhatsApp, $enviarEmail, $maxPorContrato, $resultado);
                }
            });

        return $resultado;
    }

    private function processarContrato(
        Contrato $contrato,
        CarbonInterface $ate,
        bool $dryRun,
        bool $gerarPix,
        bool $enviarWhatsApp,
        bool $enviarEmail,
        int $maxPorContrato,
        array &$resultado,
    ): void {
        $vencimento = $this->primeiroVencimento($contrato);
        $geradasContrato = 0;

        while ($vencimento->lte($ate) && $this->dentroDaVigencia($contrato, $vencimento)) {
            if ($geradasContrato >= $maxPorContrato) {
                $resultado['limitados']++;
                $resultado['erros'][] = "Contrato #{$contrato->id} atingiu o limite de {$maxPorContrato} cobrancas nesta execucao.";
                break;
            }

            $existente = Cobranca::query()
                ->where('contrato_id', $contrato->id)
                ->whereDate('vencimento', $vencimento)
                ->first();

            if ($existente) {
                $resultado['existentes']++;
                $this->avancarProximaCobranca($contrato, $vencimento, $dryRun);
                $vencimento = $this->proximoVencimento($vencimento, $contrato->forma_cobranca);
                continue;
            }

            $item = [
                'contrato_id' => $contrato->id,
                'cliente' => $contrato->cliente?->nome,
                'vencimento' => $vencimento->format('Y-m-d'),
                'valor' => (float) $contrato->valor_contratado,
            ];
            $resultado['itens'][] = $item;

            if ($dryRun) {
                $resultado['criadas']++;
                $geradasContrato++;
                $vencimento = $this->proximoVencimento($vencimento, $contrato->forma_cobranca);
                continue;
            }

            $cobranca = Cobranca::query()->create([
                'contrato_id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'loja_id' => $contrato->loja_id,
                'vencimento' => $vencimento,
                'valor_principal' => $contrato->valor_contratado,
                'valor_atualizado' => $contrato->valor_contratado,
                'valor_pago' => 0,
                'status' => 'aberta',
                'whatsapp_status' => 'pendente',
            ]);
            $resultado['criadas']++;
            $geradasContrato++;

            if ($gerarPix) {
                $pix = $this->pixGateway->criarPix($cobranca);
                if ($pix['ok'] ?? false) {
                    $resultado['pix_gerados']++;
                    $cobranca->refresh();
                } else {
                    $resultado['erros'][] = "Cobranca #{$cobranca->id}: ".($pix['erro'] ?? 'PIX nao gerado.');
                }
            }

            if ($enviarWhatsApp) {
                $whats = $this->whatsApp->enviarCobranca($cobranca);
                if ($whats['ok'] ?? false) {
                    $resultado['whatsapp_enviados']++;
                } else {
                    $resultado['erros'][] = "Cobranca #{$cobranca->id}: ".($whats['erro'] ?? 'WhatsApp nao enviado.');
                }
            }

            if ($enviarEmail) {
                $email = $this->emailCobranca->enviarCobranca($cobranca);
                if ($email['ok'] ?? false) {
                    $resultado['emails_enviados']++;
                } else {
                    $resultado['erros'][] = "Cobranca #{$cobranca->id}: ".($email['erro'] ?? 'E-mail nao enviado.');
                }
            }

            $this->avancarProximaCobranca($contrato, $vencimento, false);
            $vencimento = $this->proximoVencimento($vencimento, $contrato->forma_cobranca);
        }
    }

    private function primeiroVencimento(Contrato $contrato): Carbon
    {
        if ($contrato->proxima_cobranca_em) {
            return $contrato->proxima_cobranca_em->copy()->startOfDay();
        }

        $ultimoVencimento = Cobranca::query()
            ->where('contrato_id', $contrato->id)
            ->max('vencimento');

        if ($ultimoVencimento) {
            return $this->proximoVencimento(Carbon::parse($ultimoVencimento), $contrato->forma_cobranca);
        }

        return $contrato->data_inicio->copy()->startOfDay();
    }

    private function proximoVencimento(CarbonInterface $data, string $forma): Carbon
    {
        $data = Carbon::parse($data)->startOfDay();

        return match ($forma) {
            'quinzenal' => $data->addDays(15),
            'mensal' => $data->addMonthNoOverflow(),
            default => $data->addWeek(),
        };
    }

    private function dentroDaVigencia(Contrato $contrato, CarbonInterface $vencimento): bool
    {
        return ! $contrato->data_fim || $vencimento->lte($contrato->data_fim);
    }

    private function avancarProximaCobranca(Contrato $contrato, CarbonInterface $vencimento, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $contrato->update([
            'proxima_cobranca_em' => $this->proximoVencimento($vencimento, $contrato->forma_cobranca),
            'ultima_cobranca_gerada_em' => now(),
        ]);
    }
}
