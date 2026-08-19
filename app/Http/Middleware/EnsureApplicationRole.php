<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = (string) config('application_role.role', 'store');

        if ($role === 'combined') {
            return $next($request);
        }

        if ($role === 'store' && $this->isLicenseServerPath($request)) {
            abort(404);
        }

        if ($role === 'license_server') {
            if ($request->is('/')) {
                return redirect()->route('licencas-portal.index');
            }

            if (! $this->isLicenseServerPath($request) && ! $this->isSharedAccessPath($request)) {
                abort(404);
            }
        }

        return $next($request);
    }

    private function isLicenseServerPath(Request $request): bool
    {
        return $request->is('licencas-portal*')
            || $request->is('minha-licenca*')
            || $request->is('api/licencas-portal*');
    }

    private function isSharedAccessPath(Request $request): bool
    {
        return $request->is('login')
            || $request->is('logout')
            || $request->is('esqueci-senha')
            || $request->is('redefinir-senha*')
            || $request->is('up');
    }
}
