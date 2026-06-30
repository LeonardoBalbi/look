<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocxController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('locx.login');
    Route::post('/login', [AuthController::class, 'store'])->name('locx.login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [LocxController::class, 'index'])->name('locx.index');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('locx.logout');
    Route::post('/clientes', [LocxController::class, 'salvarCliente'])->name('locx.clientes.salvar');
    Route::post('/motos', [LocxController::class, 'salvarMoto'])->name('locx.motos.salvar');
    Route::post('/contratos', [LocxController::class, 'salvarContrato'])->name('locx.contratos.salvar');
    Route::post('/crm/clientes/{cliente}', [LocxController::class, 'salvarCrmCliente'])->name('locx.crm.cliente');
    Route::post('/crm/notas', [LocxController::class, 'salvarCrmNota'])->name('locx.crm.notas.salvar');
    Route::post('/crm/tarefas', [LocxController::class, 'salvarCrmTarefa'])->name('locx.crm.tarefas.salvar');
    Route::post('/crm/tarefas/{tarefa}/concluir', [LocxController::class, 'concluirCrmTarefa'])->name('locx.crm.tarefas.concluir');
    Route::post('/cobrancas', [LocxController::class, 'salvarCobranca'])->name('locx.cobrancas.salvar');
    Route::post('/pagamentos', [LocxController::class, 'salvarPagamento'])->name('locx.pagamentos.salvar');
    Route::post('/pix/conciliar', [LocxController::class, 'conciliarPix'])->name('locx.pix.conciliar');
    Route::post('/cobrancas/{cobranca}/pix', [LocxController::class, 'gerarPix'])->name('locx.cobrancas.pix');
    Route::post('/cobrancas/{cobranca}/whatsapp', [LocxController::class, 'enviarWhatsApp'])->name('locx.cobrancas.whatsapp');
    Route::post('/configuracoes/whatsapp', [LocxController::class, 'salvarWhatsApp'])->name('locx.whatsapp.salvar');
    Route::post('/configuracoes/whatsapp/testar', [LocxController::class, 'testarWhatsApp'])->name('locx.whatsapp.testar');
    Route::post('/configuracoes/pagbank', [LocxController::class, 'salvarPagBank'])->name('locx.pagbank.salvar');
    Route::post('/configuracoes/asaas', [LocxController::class, 'salvarAsaas'])->name('locx.asaas.salvar');
    Route::post('/configuracoes/gateway-pix', [LocxController::class, 'salvarGatewayPix'])->name('locx.gateway-pix.salvar');
    Route::post('/usuarios', [LocxController::class, 'salvarUsuario'])->name('locx.usuarios.salvar');
});

Route::match(['get', 'post'], '/webhooks/whatsapp', [WebhookController::class, 'whatsapp'])->name('locx.webhook-whatsapp');
Route::post('/webhooks/pagbank', [WebhookController::class, 'pagBank'])->name('locx.webhook-pagbank');
Route::post('/webhooks/asaas', [WebhookController::class, 'asaas'])->name('locx.webhook-asaas');

Route::get('/locx', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/index.php', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/login.php', fn () => redirect()->route('locx.login', status: 301));
Route::get('/locx/logout.php', fn () => redirect()->route('locx.login', status: 301));
Route::match(['get', 'post'], '/locx/webhooks/whatsapp.php', [WebhookController::class, 'whatsapp']);
Route::post('/locx/webhooks/pagbank.php', [WebhookController::class, 'pagBank']);
Route::post('/locx/webhooks/asaas.php', [WebhookController::class, 'asaas']);
