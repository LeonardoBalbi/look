<?php

namespace App\Services;

use App\Models\ContaBancaria;
use App\Models\Cobranca;
use App\Models\PixGatewayConfig;
use Throwable;

class PixGatewayService
{
    public function __construct(
        private readonly AsaasService $asaas,
        private readonly PagBankService $pagBank,
        private readonly SicoobService $sicoob,
        private readonly ItauService $itau,
    ) {}

    public function config(?int $lojaId = null): PixGatewayConfig
    {
        if ($lojaId) {
            return new PixGatewayConfig([
                'gateway' => $this->gatewayParaLoja($lojaId),
                'atualizado_em' => now(),
            ]);
        }

        $config = PixGatewayConfig::query()->firstOrCreate(
            ['id' => 1],
            ['gateway' => 'pagbank']
        );

        $contaPadrao = ContaBancaria::query()->whereNull('loja_id')->where('padrao', true)->first();
        if (! $contaPadrao || $contaPadrao->provedor !== $config->gateway) {
            ContaBancaria::definirPadrao($config->gateway);
        }

        return $config;
    }

    public function salvar(string $gateway, ?int $lojaId = null): PixGatewayConfig
    {
        if ($lojaId) {
            ContaBancaria::definirPadrao($gateway, $lojaId);

            return $this->config($lojaId);
        }

        ContaBancaria::definirPadrao($gateway);

        return PixGatewayConfig::query()->updateOrCreate(
            ['id' => 1],
            ['gateway' => $gateway, 'atualizado_em' => now()]
        );
    }

    public function criarPix(Cobranca $cobranca): array
    {
        $gateway = $this->gatewayParaLoja((int) $cobranca->loja_id);
        $resultado = match ($gateway) {
            'asaas' => $this->asaas->criarPix($cobranca),
            'sicoob' => $this->sicoob->criarPix($cobranca),
            'itau' => $this->itau->criarPix($cobranca),
            default => $this->pagBank->criarPix($cobranca),
        };

        $resultado['gateway'] = $gateway;

        return $resultado;
    }

    public function nomeGateway(?int $lojaId = null): string
    {
        return match ($this->gatewayParaLoja($lojaId)) {
            'asaas' => 'Asaas',
            'sicoob' => 'Sicoob',
            'itau' => 'Itau',
            default => 'PagBank',
        };
    }

    public function gatewayParaLoja(?int $lojaId = null): string
    {
        return ContaBancaria::gatewayPadrao($lojaId, $this->config()->gateway);
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
                    })
                    ->orWhere(function ($sicoob): void {
                        $sicoob->whereNotNull('sicoob_txid')->where('sicoob_txid', 'not like', 'DEMO-%');
                    })
                    ->orWhere(function ($itau): void {
                        $itau->whereNotNull('itau_txid')->where('itau_txid', 'not like', 'DEMO-%');
                    });
            })
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->each(function (Cobranca $cobranca) use (&$resultado): void {
                $resultado['analisadas']++;

                try {
                    $consulta = match (true) {
                        $cobranca->asaas_id && ! str_starts_with((string) $cobranca->asaas_id, 'DEMO-') => $this->asaas->conciliarCobranca($cobranca),
                        $cobranca->sicoob_txid && ! str_starts_with((string) $cobranca->sicoob_txid, 'DEMO-') => $this->sicoob->conciliarCobranca($cobranca),
                        $cobranca->itau_txid && ! str_starts_with((string) $cobranca->itau_txid, 'DEMO-') => $this->itau->conciliarCobranca($cobranca),
                        default => $this->pagBank->conciliarCobranca($cobranca),
                    };

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
