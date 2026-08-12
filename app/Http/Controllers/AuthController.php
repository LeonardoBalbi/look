<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('rental.index') : view('rental.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);
        $limiterKey = 'staff-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return back()->withErrors([
                'email' => 'Muitas tentativas de acesso. Aguarde '.RateLimiter::availableIn($limiterKey).' segundos.',
            ])->onlyInput('email');
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($credentials['email'])])
            ->where('status', 'ativo')
            ->first();

        if (! $user || ! Hash::check($credentials['senha'], $user->senha)) {
            RateLimiter::hit($limiterKey, 60);

            return back()
                ->withErrors(['email' => 'E-mail ou senha inválidos.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($limiterKey);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('rental.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rental.login');
    }

    public function solicitarSenha(): View
    {
        return view('rental.forgot-password');
    }

    public function enviarRecuperacao(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($dados['email']);
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->where('status', 'ativo')->exists()) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Se o e-mail estiver cadastrado e ativo, enviaremos as instruções de recuperação.');
    }

    public function redefinirSenha(Request $request, string $token): View
    {
        return view('rental.reset-password', [
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

        $status = Password::broker()->reset([
            'email' => strtolower($dados['email']),
            'password' => $dados['senha'],
            'password_confirmation' => $dados['senha'],
            'token' => $dados['token'],
        ], function (User $user, string $senha): void {
            $user->senha = Hash::make($senha);
            $user->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'O link é inválido ou expirou. Solicite uma nova recuperação.',
            ]);
        }

        return redirect()->route('rental.login')->with('status', 'Senha redefinida. Você já pode entrar.');
    }
}
