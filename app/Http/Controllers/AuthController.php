<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
}
