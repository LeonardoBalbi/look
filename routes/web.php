<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocxController;

Route::get('/', fn () => redirect()->route('locx.index'));

Route::prefix('locx')->group(function () {
    Route::any('/', [LocxController::class, 'index']);
    Route::any('/index.php', [LocxController::class, 'index'])->name('locx.index');
    Route::any('/login.php', [LocxController::class, 'login'])->name('locx.login');
    Route::any('/logout.php', [LocxController::class, 'logout'])->name('locx.logout');
    Route::any('/webhooks/pagbank.php', [LocxController::class, 'webhookPagbank'])->name('locx.webhook-pagbank');
    Route::any('/webhooks/whatsapp.php', [LocxController::class, 'webhookWhatsapp'])->name('locx.webhook-whatsapp');
});
