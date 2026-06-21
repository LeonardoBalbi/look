<?php

use App\Http\Controllers\N8nController;
use Illuminate\Support\Facades\Route;

Route::prefix('n8n')->group(function (): void {
    Route::get('/status', [N8nController::class, 'status']);
    Route::get('/automacoes/pendentes', [N8nController::class, 'pendentes']);
    Route::post('/automacoes/{evento}/executar', [N8nController::class, 'executar']);
});
