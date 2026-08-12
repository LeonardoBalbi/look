<?php

namespace App\Http\Controllers;

use App\Models\LicencaPortalCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LicencaClienteAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::guard('licenca_cliente')->check()
            ? redirect()->route('licenca-cliente.portal')
            : view('licenca_cliente.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'email'], 'senha' => ['required', 'string']]);
        $email = strtolower($dados['email']);
        $chave = 'license-client-login:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            return back()->withErrors(['email' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($chave).' segundos.'])->onlyInput('email');
        }

        $cliente = LicencaPortalCliente::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'ativo')
            ->where('portal_ativo', true)
            ->first();
        if (! $cliente || ! $cliente->senha || ! Hash::check($dados['senha'], $cliente->senha)) {
            RateLimiter::hit($chave, 60);

            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        RateLimiter::clear($chave);
        Auth::guard('licenca_cliente')->login($cliente);
        $request->session()->regenerate();
        $cliente->update(['ultimo_login_em' => now(), 'atualizado_em' => now()]);

        return redirect()->intended(route('licenca-cliente.portal'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('licenca_cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('licenca-cliente.login');
    }

    public function solicitarSenha(): View
    {
        return view('licenca_cliente.forgot-password');
    }

    public function enviarRecuperacao(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($dados['email']);
        if (LicencaPortalCliente::query()->whereRaw('LOWER(email) = ?', [$email])->where('status', 'ativo')->where('portal_ativo', true)->exists()) {
            Password::broker('licenca_clientes')->sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Se o e-mail estiver liberado, enviaremos as instruções de acesso.');
    }

    public function redefinirSenha(Request $request, string $token): View
    {
        return view('licenca_cliente.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function salvarNovaSenha(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'senha' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $status = Password::broker('licenca_clientes')->reset([
            'email' => strtolower($dados['email']),
            'password' => $dados['senha'],
            'password_confirmation' => $dados['senha'],
            'token' => $dados['token'],
        ], function (LicencaPortalCliente $cliente, string $senha): void {
            $cliente->senha = Hash::make($senha);
            $cliente->atualizado_em = now();
            $cliente->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'O link é inválido ou expirou. Solicite outro.']);
        }

        return redirect()->route('licenca-cliente.login')->with('status', 'Senha cadastrada. Você já pode entrar.');
    }
}
