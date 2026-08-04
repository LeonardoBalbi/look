<?php

use App\Http\Controllers\N8nController;
use App\Http\Controllers\LicencaPortalController;
use Illuminate\Support\Facades\Route;

Route::post('/licencas-portal/validar-licenca', [LicencaPortalController::class, 'validar'])
    ->name('licencas-portal.validar');

Route::prefix('n8n')->group(function (): void {
    Route::get('/status', [N8nController::class, 'status']);
    Route::get('/automacoes/pendentes', [N8nController::class, 'pendentes']);
    Route::post('/automacoes/{evento}/executar', [N8nController::class, 'executar']);
});
