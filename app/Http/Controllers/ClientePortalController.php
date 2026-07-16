<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Services\CobrancaCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientePortalController extends Controller
{
    public function __construct(private readonly CobrancaCalculator $calculator) {}

    public function index(Request $request): View
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente')->loadMissing(
            'loja',
            'contratos.motocicleta',
            'cobrancas.contrato.motocicleta',
            'cobrancas.pagamentos',
            'multasTransito.motocicleta'
        );

        $cobrancas = $cliente->cobrancas
            ->sortBy(fn (Cobranca $cobranca) => $cobranca->vencimento?->timestamp ?? 0)
            ->values();

        foreach ($cobrancas as $cobranca) {
            if ($cobranca->status !== 'paga') {
                $valorAtualizado = $this->calculator->valorAtualizado(
                    $cobranca->valor_principal,
                    $cobranca->valor_pago,
                    $cobranca->vencimento
                );
                $status = $cobranca->vencimento->isPast() ? 'atrasada' : $cobranca->status;

                if (abs($valorAtualizado - (float) $cobranca->valor_atualizado) > 0.01 || $status !== $cobranca->status) {
                    $cobranca->update([
                        'valor_atualizado' => $valorAtualizado,
                        'status' => $status,
                        'atualizado_em' => now(),
                    ]);
                    $cobranca->valor_atualizado = $valorAtualizado;
                    $cobranca->status = $status;
                }
            }
        }

        $abertas = $cobrancas->where('status', '!=', 'paga');
        $pagas = $cobrancas->where('status', 'paga');
        $saldoAberto = $abertas->sum(fn (Cobranca $cobranca) => max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago));
        $saldoAtrasado = $abertas
            ->filter(fn (Cobranca $cobranca) => $cobranca->vencimento?->isPast())
            ->sum(fn (Cobranca $cobranca) => max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago));

        $pagamentos = DB::table('pagamentos as p')
            ->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
            ->where('c.cliente_id', $cliente->id)
            ->select('p.*', 'c.id as cobranca_numero')
            ->orderByDesc('p.pago_em')
            ->limit(12)
            ->get();

        $notificacoes = collect();

        $abertas->take(5)->each(function (Cobranca $cobranca) use ($notificacoes): void {
            $saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
            $notificacoes->push([
                'tipo' => $cobranca->vencimento->isPast() ? 'danger' : 'warn',
                'titulo' => $cobranca->vencimento->isPast() ? 'Debito em atraso' : 'Fatura em aberto',
                'texto' => 'Cobranca #'.$cobranca->id.' com saldo de '.\App\Support\Locx::moeda($saldo).' e vencimento em '.$cobranca->vencimento->format('d/m/Y').'.',
            ]);
        });

        $cliente->multasTransito
            ->whereIn('status', ['aberta', 'em_recurso'])
            ->take(3)
            ->each(fn ($multa) => $notificacoes->push([
                'tipo' => 'warn',
                'titulo' => 'Multa pendente',
                'texto' => ($multa->motocicleta?->placa ?: 'Moto').' - '.\App\Support\Locx::moeda($multa->valor).' - vencimento '.($multa->vencimento?->format('d/m/Y') ?: 'sem data').'.',
            ]));

        if ($notificacoes->isEmpty()) {
            $notificacoes->push([
                'tipo' => 'ok',
                'titulo' => 'Tudo em dia',
                'texto' => 'Nao ha debitos ou notificacoes pendentes no momento.',
            ]);
        }

        return view('cliente.portal', [
            'cliente' => $cliente,
            'cobrancas' => $cobrancas->sortByDesc('id')->values(),
            'cobrancasAbertas' => $abertas->sortBy('vencimento')->values(),
            'cobrancasPagas' => $pagas->sortByDesc('vencimento')->values(),
            'contratos' => $cliente->contratos->sortByDesc('id')->values(),
            'pagamentos' => $pagamentos,
            'notificacoes' => $notificacoes,
            'saldoAberto' => $saldoAberto,
            'saldoAtrasado' => $saldoAtrasado,
        ]);
    }
}
