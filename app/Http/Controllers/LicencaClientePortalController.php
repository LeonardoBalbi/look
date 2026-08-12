<?php

namespace App\Http\Controllers;

use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPagamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LicencaClientePortalController extends Controller
{
    public function index(Request $request): View
    {
        /** @var LicencaPortalCliente $cliente */
        $cliente = $request->user('licenca_cliente');

        return view('licenca_cliente.portal', [
            'cliente' => $cliente,
            'licencas' => $cliente->licencas()->with('plano')->latest('id')->get(),
            'pagamentos' => $cliente->pagamentos()->with('licenca', 'plano')->latest('id')->limit(100)->get(),
        ]);
    }

    public function solicitarRenovacao(Request $request): RedirectResponse
    {
        /** @var LicencaPortalCliente $cliente */
        $cliente = $request->user('licenca_cliente');
        $dados = $request->validate([
            'licenca_id' => ['required', 'integer'],
            'meses' => ['required', 'integer', 'min:1', 'max:24'],
        ]);
        $licenca = LicencaPortalLicenca::query()
            ->with('plano')
            ->where('cliente_id', $cliente->id)
            ->findOrFail($dados['licenca_id']);

        $pendente = LicencaPortalPagamento::query()
            ->where('cliente_id', $cliente->id)
            ->where('licenca_id', $licenca->id)
            ->where('status', 'pendente')
            ->first();
        if ($pendente) {
            return back()->with('status', 'Já existe uma cobrança pendente. Aguarde o link de pagamento ou fale com o suporte.');
        }

        LicencaPortalPagamento::create([
            'licenca_id' => $licenca->id,
            'cliente_id' => $cliente->id,
            'plano_id' => $licenca->plano_id,
            'gateway' => 'manual',
            'referencia_externa' => 'REQUEST-'.strtoupper(Str::random(16)),
            'valor_centavos' => ((int) ($licenca->plano?->preco_centavos ?? 0)) * (int) $dados['meses'],
            'status' => 'pendente',
            'meses_renovacao' => (int) $dados['meses'],
            'vencimento' => today()->addDays(5),
            'atualizado_em' => now(),
        ]);

        return back()->with('success', 'Renovação solicitada. O link de pagamento aparecerá aqui assim que for emitido.');
    }
}
