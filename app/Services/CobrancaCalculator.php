<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CobrancaCalculator
{
    public function diasAtrasoAteDomingo(string|CarbonInterface $vencimento): int
    {
        $vencimento = Carbon::parse($vencimento)->startOfDay();
        $hoje = today();

        if ($hoje->lessThanOrEqualTo($vencimento)) {
            return 0;
        }

        $limite = $vencimento->copy();
        while ($limite->dayOfWeek !== CarbonInterface::SUNDAY) {
            $limite->addDay();
        }
        $fim = $hoje->min($limite);

        return max(0, $vencimento->diffInDays($fim));
    }

    public function valorAtualizado(
        float|int|string $principal,
        float|int|string|null $pago,
        string|CarbonInterface $vencimento
    ): float {
        $principal = (float) $principal;
        $pago = (float) $pago;
        $saldo = max(0, $principal - $pago);
        $dias = $this->diasAtrasoAteDomingo($vencimento);

        if ($dias === 0) {
            return round($saldo, 2);
        }

        return $pago > 0
            ? round($saldo * (1.10 ** $dias), 2)
            : round($principal * (1 + 0.10 * $dias), 2);
    }
}
