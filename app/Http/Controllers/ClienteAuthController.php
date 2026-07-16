<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClienteAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::guard('cliente')->check()
            ? redirect()->route('cliente.portal')
            : view('cliente.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $cliente = Cliente::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($dados['email'])])
            ->where('portal_ativo', true)
            ->whereIn('status', ['ativo', 'inadimplente'])
            ->first();

        if (! $cliente || ! $cliente->senha || ! Hash::check($dados['senha'], $cliente->senha)) {
            return back()
                ->withErrors(['email' => 'E-mail ou senha invalidos, ou portal nao liberado para este cliente.'])
                ->onlyInput('email');
        }

        Auth::guard('cliente')->login($cliente);
        $cliente->update(['ultimo_login_em' => now()]);
        $request->session()->regenerate();

        return redirect()->intended(route('cliente.portal'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cliente.login');
    }
}
