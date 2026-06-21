<?php

namespace Tests\Unit;

use App\Services\CobrancaCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CobrancaCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_aplica_juros_simples_sem_pagamento_ate_domingo(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->assertSame(130.0, (new CobrancaCalculator)->valorAtualizado(100, 0, '2026-06-17'));
    }

    public function test_aplica_juros_compostos_sobre_saldo_quando_ha_pagamento_parcial(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->assertSame(106.48, (new CobrancaCalculator)->valorAtualizado(100, 20, '2026-06-17'));
    }

    public function test_trata_pagamento_nulo_como_zero(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->assertSame(130.0, (new CobrancaCalculator)->valorAtualizado(100, null, '2026-06-17'));
    }
}
