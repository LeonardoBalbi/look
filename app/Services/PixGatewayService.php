<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\PixGatewayConfig;
use Throwable;

class PixGatewayService
{
    public function __construct(
        private readonly AsaasService $asaas,
        private readonly PagBankService $pagBank,
    ) {}

    public function config(): PixGatewayConfig
    {
        return PixGatewayConfig::query()->firstOrCreate(
            ['id' => 1],
            ['gateway' => 'pagbank']
        );
    }

    public function salvar(string $gateway): PixGatewayConfig
    {
        return PixGatewayConfig::query()->updateOrCreate(
            ['id' => 1],
            ['gateway' => $gateway, 'atualizado_em' => now()]
        );
    }

    public function criarPix(Cobranca $cobranca): array
    {
        return match ($this->config()->gateway) {
            'asaas' => $this->asaas->criarPix($cobranca),
            default => $this->pagBank->criarPix($cobranca),
        };
    }

    public function nomeGateway(): string
    {
        return $this->config()->gateway === 'asaas' ? 'Asaas' : 'PagBank';
    }

    public function conciliarPendentes(int $limite = 50): array
    {
        $resultado = [
            'analisadas' => 0,
            'baixadas' => 0,
            'pendentes' => 0,
            'erros' => [],
        ];

        Cobranca::query()
            ->where('status', '<>', 'paga')
            ->where(function ($query): void {
                $query
                    ->where(function ($asaas): void {
                        $asaas->whereNotNull('asaas_id')->where('asaas_id', 'not like', 'DEMO-%');
                    })
                    ->orWhere(function ($pagbank): void {
                        $pagbank->whereNotNull('pagbank_order_id')->where('pagbank_order_id', 'not like', 'DEMO-%');
                    });
            })
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->each(function (Cobranca $cobranca) use (&$resultado): void {
                $resultado['analisadas']++;

                try {
                    $consulta = $cobranca->asaas_id && ! str_starts_with((string) $cobranca->asaas_id, 'DEMO-')
                        ? $this->asaas->conciliarCobranca($cobranca)
                        : $this->pagBank->conciliarCobranca($cobranca);

                    if ($consulta['baixado'] ?? false) {
                        $resultado['baixadas']++;
                    } elseif ($consulta['ok'] ?? false) {
                        $resultado['pendentes']++;
                    } else {
                        $resultado['erros'][] = "Cobranca #{$cobranca->id}: ".($consulta['erro'] ?? 'falha desconhecida');
                    }
                } catch (Throwable $exception) {
                    $resultado['erros'][] = "Cobranca #{$cobranca->id}: ".$exception->getMessage();
                }
            });

        return $resultado;
    }
}
