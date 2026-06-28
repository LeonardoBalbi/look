<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\PixGatewayConfig;

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
}
