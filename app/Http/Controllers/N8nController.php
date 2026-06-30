<?php

namespace App\Http\Controllers;

use App\Models\AutomacaoLog;
use App\Services\AutomacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nController extends Controller
{
    public function pendentes(Request $request, AutomacaoService $service): JsonResponse
    {
        $this->autorizar($request);
        $tipo = $request->query('tipo');

        return response()->json([
            'enabled' => (bool) config('n8n.enabled'),
            'eventos' => config('n8n.enabled') ? $service->pendentes($tipo)->values() : [],
        ]);
    }

    public function executar(Request $request, AutomacaoLog $evento, AutomacaoService $service): JsonResponse
    {
        $this->autorizar($request);
        abort_unless(config('n8n.enabled'), 503, 'Automações n8n desativadas.');

        $resultado = $service->executar($evento);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    public function status(Request $request): JsonResponse
    {
        $this->autorizar($request);

        return response()->json([
            'ok' => true,
            'enabled' => (bool) config('n8n.enabled'),
            'timezone' => config('app.timezone'),
            'tipos' => AutomacaoService::TIPOS,
        ]);
    }

    private function autorizar(Request $request): void
    {
        $esperado = (string) config('n8n.token');
        $recebido = (string) $request->bearerToken();

        abort_unless($esperado !== '' && hash_equals($esperado, $recebido), 401, 'Token n8n inválido.');
    }
}
