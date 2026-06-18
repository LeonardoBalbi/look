<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocxController;

Route::any('/', [LocxController::class, 'index'])->name('locx.index');
Route::any('/login', [LocxController::class, 'login'])->name('locx.login');
Route::any('/logout', [LocxController::class, 'logout'])->name('locx.logout');
Route::any('/webhooks/pagbank', [LocxController::class, 'webhookPagbank'])->name('locx.webhook-pagbank');
Route::any('/webhooks/whatsapp', [LocxController::class, 'webhookWhatsapp'])->name('locx.webhook-whatsapp');

// Compatibilidade com links antigos publicados antes das URLs amigáveis.
Route::get('/locx', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/index.php', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/login.php', fn () => redirect()->route('locx.login', status: 301));
Route::get('/locx/logout.php', fn () => redirect()->route('locx.logout', status: 301));
Route::any('/locx/webhooks/pagbank.php', [LocxController::class, 'webhookPagbank']);
Route::any('/locx/webhooks/whatsapp.php', [LocxController::class, 'webhookWhatsapp']);
