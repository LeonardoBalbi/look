<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteAuthController;
use App\Http\Controllers\ClientePortalController;
use App\Http\Controllers\LocxController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('locx.login');
    Route::post('/login', [AuthController::class, 'store'])->name('locx.login.store');
});

Route::middleware('guest:cliente')->group(function (): void {
    Route::get('/portal/login', [ClienteAuthController::class, 'create'])->name('cliente.login');
    Route::post('/portal/login', [ClienteAuthController::class, 'store'])->name('cliente.login.store');
});

Route::middleware('auth:cliente')->group(function (): void {
    Route::get('/portal', [ClientePortalController::class, 'index'])->name('cliente.portal');
    Route::get('/portal/chat/sync', [ClientePortalController::class, 'syncChat'])->name('cliente.chat.sync');
    Route::post('/portal/chat', [ClientePortalController::class, 'storeChat'])->name('cliente.chat.store');
    Route::post('/portal/chat/encerrar', [ClientePortalController::class, 'closeChat'])->name('cliente.chat.close');
    Route::post('/portal/logout', [ClienteAuthController::class, 'destroy'])->name('cliente.logout');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [LocxController::class, 'index'])->name('locx.index');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('locx.logout');
    Route::post('/clientes', [LocxController::class, 'salvarCliente'])->name('locx.clientes.salvar');
    Route::post('/motos', [LocxController::class, 'salvarMoto'])->name('locx.motos.salvar');
    Route::post('/contratos', [LocxController::class, 'salvarContrato'])->name('locx.contratos.salvar');
    Route::post('/manutencao/ordens', [LocxController::class, 'salvarOrdemServico'])->name('locx.manutencao.ordens.salvar');
    Route::post('/estoque/produtos', [LocxController::class, 'salvarEstoqueProduto'])->name('locx.estoque.produtos.salvar');
    Route::post('/estoque/movimentos', [LocxController::class, 'salvarEstoqueMovimento'])->name('locx.estoque.movimentos.salvar');
    Route::post('/multas', [LocxController::class, 'salvarMulta'])->name('locx.multas.salvar');
    Route::post('/crm/clientes/{cliente}', [LocxController::class, 'salvarCrmCliente'])->name('locx.crm.cliente');
    Route::post('/crm/notas', [LocxController::class, 'salvarCrmNota'])->name('locx.crm.notas.salvar');
    Route::post('/crm/tarefas', [LocxController::class, 'salvarCrmTarefa'])->name('locx.crm.tarefas.salvar');
    Route::post('/crm/tarefas/{tarefa}/concluir', [LocxController::class, 'concluirCrmTarefa'])->name('locx.crm.tarefas.concluir');
    Route::get('/crm/portal-atendimentos/inbox/sync', [LocxController::class, 'syncPortalInbox'])->name('locx.crm.portal-atendimentos.inbox-sync');
    Route::get('/crm/clientes/{cliente}/portal-atendimentos/sync', [LocxController::class, 'syncPortalAtendimentosCliente'])->name('locx.crm.portal-atendimentos.cliente-sync');
    Route::get('/crm/portal-atendimentos/{atendimento}/sync', [LocxController::class, 'syncPortalAtendimento'])->name('locx.crm.portal-atendimentos.sync');
    Route::post('/crm/portal-atendimentos/{atendimento}/responder', [LocxController::class, 'responderPortalAtendimento'])->name('locx.crm.portal-atendimentos.responder');
    Route::post('/crm/portal-atendimentos/{atendimento}/acao', [LocxController::class, 'acaoPortalAtendimento'])->name('locx.crm.portal-atendimentos.acao');
    Route::post('/crm/portal-atendimentos/{atendimento}/lido', [LocxController::class, 'marcarPortalAtendimentoLido'])->name('locx.crm.portal-atendimentos.lido');
    Route::post('/cobrancas', [LocxController::class, 'salvarCobranca'])->name('locx.cobrancas.salvar');
    Route::post('/pagamentos', [LocxController::class, 'salvarPagamento'])->name('locx.pagamentos.salvar');
    Route::post('/pix/conciliar', [LocxController::class, 'conciliarPix'])->name('locx.pix.conciliar');
    Route::post('/cobrancas/{cobranca}/pix', [LocxController::class, 'gerarPix'])->name('locx.cobrancas.pix');
    Route::post('/cobrancas/{cobranca}/whatsapp', [LocxController::class, 'enviarWhatsApp'])->name('locx.cobrancas.whatsapp');
    Route::post('/cobrancas/{cobranca}/telegram', [LocxController::class, 'enviarTelegram'])->name('locx.cobrancas.telegram');
    Route::post('/configuracoes/whatsapp', [LocxController::class, 'salvarWhatsApp'])->name('locx.whatsapp.salvar');
    Route::post('/configuracoes/whatsapp/testar', [LocxController::class, 'testarWhatsApp'])->name('locx.whatsapp.testar');
    Route::post('/configuracoes/telegram', [LocxController::class, 'salvarTelegram'])->name('locx.telegram.salvar');
    Route::post('/configuracoes/telegram/testar', [LocxController::class, 'testarTelegram'])->name('locx.telegram.testar');
    Route::post('/configuracoes/telegram/webhook', [LocxController::class, 'configurarWebhookTelegram'])->name('locx.telegram.webhook');
    Route::post('/cobrancas/campanhas', [LocxController::class, 'criarCampanhaCobranca'])->name('locx.cobrancas.campanhas.criar');
    Route::post('/cobrancas/campanhas/{campanha}/executar', [LocxController::class, 'executarCampanhaCobranca'])->name('locx.cobrancas.campanhas.executar');
    Route::post('/cobrancas/campanhas/{campanha}/cancelar', [LocxController::class, 'cancelarCampanhaCobranca'])->name('locx.cobrancas.campanhas.cancelar');
    Route::post('/configuracoes/pagbank', [LocxController::class, 'salvarPagBank'])->name('locx.pagbank.salvar');
    Route::post('/configuracoes/asaas', [LocxController::class, 'salvarAsaas'])->name('locx.asaas.salvar');
    Route::post('/configuracoes/sicoob', [LocxController::class, 'salvarSicoob'])->name('locx.sicoob.salvar');
    Route::post('/configuracoes/itau', [LocxController::class, 'salvarItau'])->name('locx.itau.salvar');
    Route::post('/configuracoes/gateway-pix', [LocxController::class, 'salvarGatewayPix'])->name('locx.gateway-pix.salvar');
    Route::post('/look/modulos', [LocxController::class, 'salvarLookModulo'])->name('locx.look-modulos.salvar');
    Route::post('/usuarios', [LocxController::class, 'salvarUsuario'])->name('locx.usuarios.salvar');
    Route::post('/usuarios/perfis', [LocxController::class, 'salvarUsuarioPerfil'])->name('locx.usuarios.perfis.salvar');
});

Route::match(['get', 'post'], '/webhooks/whatsapp', [WebhookController::class, 'whatsapp'])->name('locx.webhook-whatsapp');
Route::post('/webhooks/telegram', [WebhookController::class, 'telegram'])->name('locx.webhook-telegram');
Route::post('/webhooks/pagbank', [WebhookController::class, 'pagBank'])->name('locx.webhook-pagbank');
Route::post('/webhooks/asaas', [WebhookController::class, 'asaas'])->name('locx.webhook-asaas');
Route::post('/webhooks/sicoob', [WebhookController::class, 'sicoob'])->name('locx.webhook-sicoob');
Route::post('/webhooks/itau', [WebhookController::class, 'itau'])->name('locx.webhook-itau');

Route::get('/locx', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/index.php', fn () => redirect()->route('locx.index', request()->query(), 301));
Route::get('/locx/login.php', fn () => redirect()->route('locx.login', status: 301));
Route::get('/locx/logout.php', fn () => redirect()->route('locx.login', status: 301));
Route::match(['get', 'post'], '/locx/webhooks/whatsapp.php', [WebhookController::class, 'whatsapp']);
Route::post('/locx/webhooks/telegram.php', [WebhookController::class, 'telegram']);
Route::post('/locx/webhooks/pagbank.php', [WebhookController::class, 'pagBank']);
Route::post('/locx/webhooks/asaas.php', [WebhookController::class, 'asaas']);
Route::post('/locx/webhooks/sicoob.php', [WebhookController::class, 'sicoob']);
Route::post('/locx/webhooks/itau.php', [WebhookController::class, 'itau']);
