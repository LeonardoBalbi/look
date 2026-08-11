<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteAuthController;
use App\Http\Controllers\ClientePortalController;
use App\Http\Controllers\LicencaPortalController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('rental.login');
    Route::post('/login', [AuthController::class, 'store'])->name('rental.login.store');
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
    Route::get('/', [RentalController::class, 'index'])->name('rental.index');
    Route::get('/licencas-portal', [LicencaPortalController::class, 'index'])->name('licencas-portal.index');
    Route::post('/licencas-portal/clientes', [LicencaPortalController::class, 'salvarCliente'])->name('licencas-portal.clientes.salvar');
    Route::post('/licencas-portal/planos', [LicencaPortalController::class, 'salvarPlano'])->name('licencas-portal.planos.salvar');
    Route::post('/licencas-portal/licencas', [LicencaPortalController::class, 'salvarLicenca'])->name('licencas-portal.licencas.salvar');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('rental.logout');
    Route::post('/clientes', [RentalController::class, 'salvarCliente'])->name('rental.clientes.salvar');
    Route::post('/reservas', [RentalController::class, 'salvarReserva'])->name('rental.reservas.salvar');
    Route::get('/clientes/{cliente}/documentos/{campo}', [RentalController::class, 'baixarDocumentoCliente'])
        ->whereIn('campo', ['foto_cliente', 'foto_documento', 'comprovante_residencia'])
        ->name('rental.clientes.documentos.baixar');
    Route::post('/motos', [RentalController::class, 'salvarMoto'])->name('rental.motos.salvar');
    Route::post('/contratos', [RentalController::class, 'salvarContrato'])->name('rental.contratos.salvar');
    Route::post('/manutencao/ordens', [RentalController::class, 'salvarOrdemServico'])->name('rental.manutencao.ordens.salvar');
    Route::post('/estoque/produtos', [RentalController::class, 'salvarEstoqueProduto'])->name('rental.estoque.produtos.salvar');
    Route::post('/estoque/movimentos', [RentalController::class, 'salvarEstoqueMovimento'])->name('rental.estoque.movimentos.salvar');
    Route::post('/multas', [RentalController::class, 'salvarMulta'])->name('rental.multas.salvar');
    Route::post('/crm/clientes/{cliente}', [RentalController::class, 'salvarCrmCliente'])->name('rental.crm.cliente');
    Route::post('/crm/notas', [RentalController::class, 'salvarCrmNota'])->name('rental.crm.notas.salvar');
    Route::post('/crm/tarefas', [RentalController::class, 'salvarCrmTarefa'])->name('rental.crm.tarefas.salvar');
    Route::post('/crm/tarefas/{tarefa}/concluir', [RentalController::class, 'concluirCrmTarefa'])->name('rental.crm.tarefas.concluir');
    Route::get('/crm/portal-atendimentos/inbox/sync', [RentalController::class, 'syncPortalInbox'])->name('rental.crm.portal-atendimentos.inbox-sync');
    Route::get('/crm/clientes/{cliente}/portal-atendimentos/sync', [RentalController::class, 'syncPortalAtendimentosCliente'])->name('rental.crm.portal-atendimentos.cliente-sync');
    Route::get('/crm/portal-atendimentos/{atendimento}/sync', [RentalController::class, 'syncPortalAtendimento'])->name('rental.crm.portal-atendimentos.sync');
    Route::post('/crm/portal-atendimentos/{atendimento}/responder', [RentalController::class, 'responderPortalAtendimento'])->name('rental.crm.portal-atendimentos.responder');
    Route::post('/crm/portal-atendimentos/{atendimento}/acao', [RentalController::class, 'acaoPortalAtendimento'])->name('rental.crm.portal-atendimentos.acao');
    Route::post('/crm/portal-atendimentos/{atendimento}/lido', [RentalController::class, 'marcarPortalAtendimentoLido'])->name('rental.crm.portal-atendimentos.lido');
    Route::post('/cobrancas', [RentalController::class, 'salvarCobranca'])->name('rental.cobrancas.salvar');
    Route::post('/pagamentos', [RentalController::class, 'salvarPagamento'])->name('rental.pagamentos.salvar');
    Route::post('/pix/conciliar', [RentalController::class, 'conciliarPix'])->name('rental.pix.conciliar');
    Route::post('/cobrancas/{cobranca}/pix', [RentalController::class, 'gerarPix'])->name('rental.cobrancas.pix');
    Route::post('/cobrancas/{cobranca}/whatsapp', [RentalController::class, 'enviarWhatsApp'])->name('rental.cobrancas.whatsapp');
    Route::post('/cobrancas/{cobranca}/telegram', [RentalController::class, 'enviarTelegram'])->name('rental.cobrancas.telegram');
    Route::post('/configuracoes/whatsapp', [RentalController::class, 'salvarWhatsApp'])->name('rental.whatsapp.salvar');
    Route::post('/configuracoes/whatsapp/testar', [RentalController::class, 'testarWhatsApp'])->name('rental.whatsapp.testar');
    Route::post('/configuracoes/telegram', [RentalController::class, 'salvarTelegram'])->name('rental.telegram.salvar');
    Route::post('/configuracoes/telegram/testar', [RentalController::class, 'testarTelegram'])->name('rental.telegram.testar');
    Route::post('/configuracoes/telegram/webhook', [RentalController::class, 'configurarWebhookTelegram'])->name('rental.telegram.webhook');
    Route::post('/cobrancas/campanhas', [RentalController::class, 'criarCampanhaCobranca'])->name('rental.cobrancas.campanhas.criar');
    Route::post('/cobrancas/campanhas/{campanha}/executar', [RentalController::class, 'executarCampanhaCobranca'])->name('rental.cobrancas.campanhas.executar');
    Route::post('/cobrancas/campanhas/{campanha}/cancelar', [RentalController::class, 'cancelarCampanhaCobranca'])->name('rental.cobrancas.campanhas.cancelar');
    Route::post('/configuracoes/pagbank', [RentalController::class, 'salvarPagBank'])->name('rental.pagbank.salvar');
    Route::post('/configuracoes/asaas', [RentalController::class, 'salvarAsaas'])->name('rental.asaas.salvar');
    Route::post('/configuracoes/sicoob', [RentalController::class, 'salvarSicoob'])->name('rental.sicoob.salvar');
    Route::post('/configuracoes/itau', [RentalController::class, 'salvarItau'])->name('rental.itau.salvar');
    Route::post('/configuracoes/gateway-pix', [RentalController::class, 'salvarGatewayPix'])->name('rental.gateway-pix.salvar');
    Route::post('/configuracoes/licenca', [RentalController::class, 'salvarLicenca'])->name('rental.licenca.salvar');
    Route::post('/configuracoes/licenca/testar', [RentalController::class, 'testarLicenca'])->name('rental.licenca.testar');
    Route::post('/look/modulos', [RentalController::class, 'salvarLookModulo'])->name('rental.look-modulos.salvar');
    Route::post('/lojas', [RentalController::class, 'salvarLoja'])->name('rental.lojas.salvar');
    Route::post('/usuarios', [RentalController::class, 'salvarUsuario'])->name('rental.usuarios.salvar');
    Route::post('/usuarios/perfis', [RentalController::class, 'salvarUsuarioPerfil'])->name('rental.usuarios.perfis.salvar');
});

Route::match(['get', 'post'], '/webhooks/whatsapp', [WebhookController::class, 'whatsapp'])->name('rental.webhook-whatsapp');
Route::post('/webhooks/telegram', [WebhookController::class, 'telegram'])->name('rental.webhook-telegram');
Route::post('/webhooks/pagbank', [WebhookController::class, 'pagBank'])->name('rental.webhook-pagbank');
Route::post('/webhooks/asaas', [WebhookController::class, 'asaas'])->name('rental.webhook-asaas');
Route::post('/webhooks/sicoob', [WebhookController::class, 'sicoob'])->name('rental.webhook-sicoob');
Route::post('/webhooks/itau', [WebhookController::class, 'itau'])->name('rental.webhook-itau');

Route::get('/locx', fn () => redirect()->route('rental.index', request()->query(), 301));
Route::get('/locx/index.php', fn () => redirect()->route('rental.index', request()->query(), 301));
Route::get('/locx/login.php', fn () => redirect()->route('rental.login', status: 301));
Route::get('/locx/logout.php', fn () => redirect()->route('rental.login', status: 301));
Route::match(['get', 'post'], '/locx/webhooks/whatsapp.php', [WebhookController::class, 'whatsapp']);
Route::post('/locx/webhooks/telegram.php', [WebhookController::class, 'telegram']);
Route::post('/locx/webhooks/pagbank.php', [WebhookController::class, 'pagBank']);
Route::post('/locx/webhooks/asaas.php', [WebhookController::class, 'asaas']);
Route::post('/locx/webhooks/sicoob.php', [WebhookController::class, 'sicoob']);
Route::post('/locx/webhooks/itau.php', [WebhookController::class, 'itau']);
