<?php

use App\Http\Controllers\N8nController;
use App\Http\Controllers\LicencaPortalController;
use Illuminate\Support\Facades\Route;

Route::post('/licencas-portal/validar-licenca', [LicencaPortalController::class, 'validar'])
    ->name('licencas-portal.validar');
Route::post('/licencas-portal/webhooks/pagamentos/{gateway}', [LicencaPortalController::class, 'webhookPagamento'])
    ->where('gateway', '[A-Za-z0-9_-]+')
    ->middleware('throttle:60,1')
    ->name('licencas-portal.pagamentos.webhook');

Route::prefix('n8n')->group(function (): void {
    Route::get('/status', [N8nController::class, 'status']);
    Route::get('/automacoes/pendentes', [N8nController::class, 'pendentes']);
    Route::post('/automacoes/{evento}/executar', [N8nController::class, 'executar']);
});
