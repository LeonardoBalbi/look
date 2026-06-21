<?php

namespace App\Http\Controllers;

use App\Services\PagBankService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function pagBank(Request $request, PagBankService $service): JsonResponse
    {
        $raw = $request->getContent();
        if (! $service->validarAssinatura($raw, $request->header('X-Authenticity-Token'))) {
            return response()->json(['ok' => false, 'erro' => 'Assinatura inválida.'], 401);
        }

        $resultado = $service->processarWebhook($raw);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 400);
    }

    public function whatsapp(Request $request, WhatsAppService $service): Response
    {
        if ($request->isMethod('get')) {
            $config = $service->config();
            $mode = $request->query('hub.mode', $request->query('hub_mode'));
            $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
            $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));
            $valido = $mode === 'subscribe'
                && hash_equals((string) $config->verify_token, (string) $token);

            return response($valido ? (string) $challenge : 'Token inválido', $valido ? 200 : 403);
        }

        $service->registrarWebhook($request->getContent());

        return response('EVENT_RECEIVED');
    }
}
