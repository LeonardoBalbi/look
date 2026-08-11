<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $response;
        }

        try {
            if (! Schema::hasTable('audit_logs')) {
                return $response;
            }

            $staff = $request->user();
            $customer = $request->user('cliente');
            $actor = $staff ?: $customer;

            DB::table('audit_logs')->insert([
                'actor_type' => $staff ? 'usuario' : ($customer ? 'cliente' : 'sistema'),
                'actor_id' => $actor?->getAuthIdentifier(),
                'action' => (string) ($request->route()?->getName() ?: $request->path()),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'ip' => $request->ip(),
                'http_status' => $response->getStatusCode(),
                'metadata' => json_encode([
                    'input_fields' => collect(array_keys($request->except([
                        'senha', 'senha_portal', 'password', 'token', 'access_token', 'api_key',
                        'client_secret', 'webhook_token', 'evolution_api_key', 'licenca_chave',
                    ])))->values()->all(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // A auditoria não deve impedir a operação principal em caso de indisponibilidade do log.
        }

        return $response;
    }
}
