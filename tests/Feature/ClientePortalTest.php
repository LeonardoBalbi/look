<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Loja;
use App\Models\Motocicleta;
use App\Models\PortalAtendimento;
use App\Models\PortalAtendimentoMensagem;
use App\Models\User;
use App\Models\UsuarioPermissao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_liberado_acessa_portal_e_ve_suas_faturas(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro', 'cidade' => 'Mangaratiba']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Portal',
            'cpf' => '12345678900',
            'email' => 'cliente@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);
        $moto = Motocicleta::create([
            'loja_id' => $loja->id,
            'modelo' => 'CG 160',
            'placa' => 'ABC1D23',
            'status_operacional' => 'alugada',
        ]);
        $contrato = Contrato::create([
            'cliente_id' => $cliente->id,
            'motocicleta_id' => $moto->id,
            'loja_id' => $loja->id,
            'data_inicio' => today(),
            'valor_contratado' => 500,
            'forma_cobranca' => 'semanal',
            'status' => 'ativo',
        ]);
        Cobranca::create([
            'contrato_id' => $contrato->id,
            'cliente_id' => $cliente->id,
            'loja_id' => $loja->id,
            'vencimento' => today(),
            'valor_principal' => 500,
            'valor_atualizado' => 500,
            'valor_pago' => 0,
            'status' => 'aberta',
            'pix_copia_cola' => 'PIX-COPIA-E-COLA',
        ]);

        $this->post('/portal/login', [
            'email' => 'cliente@locx.test',
            'senha' => '123456',
        ])->assertRedirect('/portal');

        $this->assertAuthenticatedAs($cliente, 'cliente');
        $this->get('/portal')
            ->assertOk()
            ->assertSee('Fatura #1')
            ->assertSee('PIX-COPIA-E-COLA')
            ->assertSee('ABC1D23')
            ->assertSee('Lau');

        $this->post('/portal/chat', [
            'mensagem' => 'Enviei o pagamento via PIX e preciso confirmar a baixa.',
        ])->assertRedirect('/portal#chat');

        $this->assertDatabaseHas('portal_atendimentos', [
            'cliente_id' => $cliente->id,
            'loja_id' => $loja->id,
            'assistente' => 'Lau',
            'assunto' => 'pagamento',
            'prioridade' => 'alta',
            'status' => 'aguardando_humano',
        ]);
        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'remetente' => 'cliente',
            'mensagem' => 'Enviei o pagamento via PIX e preciso confirmar a baixa.',
        ]);
        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'remetente' => 'bot',
        ]);

        $this->assertSame(1, PortalAtendimento::where('cliente_id', $cliente->id)->count());

        $admin = User::create([
            'nome' => 'Admin',
            'email' => 'admin@locx.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'administrador_geral',
            'status' => 'ativo',
        ]);
        $atendimento = PortalAtendimento::where('cliente_id', $cliente->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('locx.crm.portal-atendimentos.responder', $atendimento), [
                'mensagem' => 'Pagamento localizado. A baixa sera feita ainda hoje.',
            ])
            ->assertRedirect(route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id]));

        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'humano',
            'mensagem' => 'Pagamento localizado. A baixa sera feita ainda hoje.',
        ]);
        $this->assertDatabaseHas('portal_atendimentos', [
            'id' => $atendimento->id,
            'status' => 'respondido',
        ]);

        $ultimaMensagemAntesDaLoja = $atendimento->mensagens()->where('remetente', '<>', 'humano')->max('id');
        $this->actingAs($cliente, 'cliente')
            ->getJson(route('cliente.chat.sync', [
                'atendimento_id' => $atendimento->id,
                'after_id' => $ultimaMensagemAntesDaLoja,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('humano', true)
            ->assertJsonPath('mensagens.0.remetente', 'humano')
            ->assertJsonPath('mensagens.0.mensagem', 'Pagamento localizado. A baixa sera feita ainda hoje.');

        $botsAntes = $atendimento->mensagens()->where('remetente', 'bot')->count();
        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'assunto' => 'pagamento',
                'mensagem' => 'Obrigado, fico no aguardo da baixa.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonMissing(['mensagem' => 'Entendi. Pode escrever sua duvida aqui mesmo. Um atendente da loja vai continuar a conversa por este chat.']);

        $this->assertSame($botsAntes, $atendimento->mensagens()->where('remetente', 'bot')->count());
        $this->assertDatabaseHas('portal_atendimentos', [
            'id' => $atendimento->id,
            'status' => 'aguardando_humano',
        ]);

        $this->assertDatabaseHas('portal_atendimentos', [
            'id' => $atendimento->id,
            'assunto' => 'pagamento',
        ]);

        $ultimaMensagemAntesDoCliente = $atendimento->mensagens()->max('id');
        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'mensagem' => 'Ainda nao apareceu como pago no portal.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'Ainda nao apareceu como pago no portal.');

        $this->actingAs($admin)
            ->getJson(route('locx.crm.portal-atendimentos.sync', [
                'atendimento' => $atendimento,
                'after_id' => $ultimaMensagemAntesDoCliente,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'aguardando_humano')
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'Ainda nao apareceu como pago no portal.');

        $this->actingAs($admin)
            ->getJson(route('locx.crm.portal-atendimentos.cliente-sync', $cliente))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('atendimentos.0.id', $atendimento->id)
            ->assertJsonPath('atendimentos.0.ultima_mensagem_id', $atendimento->mensagens()->max('id'));

        $botsAntesRespostaSemId = $atendimento->mensagens()->where('remetente', 'bot')->count();
        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'mensagem' => 'Consigo responder por aqui mesmo?',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('atendimento_id', $atendimento->id)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'Consigo responder por aqui mesmo?')
            ->assertJsonMissing(['remetente' => 'bot']);

        $this->assertSame($botsAntesRespostaSemId, $atendimento->mensagens()->where('remetente', 'bot')->count());

        $ultimaMensagemAntesDoClienteSemIdComAssunto = $atendimento->mensagens()->max('id');
        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'assunto' => 'pagamento',
                'mensagem' => 'Mensagem digitada com assunto antigo preso.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('atendimento_id', $atendimento->id)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'Mensagem digitada com assunto antigo preso.')
            ->assertJsonMissing(['remetente' => 'bot']);

        $this->actingAs($admin)
            ->getJson(route('locx.crm.portal-atendimentos.sync', [
                'atendimento' => $atendimento,
                'after_id' => $ultimaMensagemAntesDoClienteSemIdComAssunto,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'Mensagem digitada com assunto antigo preso.');

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'assunto' => 'contrato',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assunto', 'pagamento')
            ->assertJsonMissing(['remetente' => 'bot'])
            ->assertJsonMissing(['mensagem' => 'Contrato']);

        $this->assertSame(1, PortalAtendimento::where('cliente_id', $cliente->id)->count());
        $this->assertSame($botsAntes, $atendimento->mensagens()->where('remetente', 'bot')->count());
        $this->assertDatabaseMissing('portal_atendimento_mensagens', [
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'cliente',
            'mensagem' => 'Contrato',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->getJson(route('cliente.chat.sync', [
                'atendimento_id' => $atendimento->id,
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonMissing(['mensagem' => 'Falar com a loja'])
            ->assertJsonMissing(['mensagem' => 'Contrato']);

        $this->actingAs($cliente, 'cliente')
            ->postJson(route('cliente.chat.close'), ['atendimento_id' => $atendimento->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('portal_atendimentos', [
            'id' => $atendimento->id,
            'status' => 'fechado',
        ]);
    }

    public function test_atendente_com_permissao_crm_criar_responde_chat_do_portal(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Chat',
            'email' => 'chat@locx.test',
            'status' => 'ativo',
        ]);
        $atendimento = PortalAtendimento::create([
            'cliente_id' => $cliente->id,
            'loja_id' => $loja->id,
            'assistente' => 'Lau',
            'assunto' => 'outro',
            'prioridade' => 'normal',
            'status' => 'aguardando_humano',
            'mensagem' => 'Preciso falar com a loja.',
        ]);
        $atendente = User::create([
            'nome' => 'Atendente',
            'email' => 'atendente@locx.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'atendente',
            'loja_id' => $loja->id,
            'status' => 'ativo',
        ]);
        $atendente->lojas()->attach($loja->id);
        UsuarioPermissao::create([
            'usuario_id' => $atendente->id,
            'modulo' => 'crm',
            'acao' => 'criar',
        ]);
        UsuarioPermissao::create([
            'usuario_id' => $atendente->id,
            'modulo' => 'crm',
            'acao' => 'visualizar',
        ]);

        $this->actingAs($atendente)
            ->post(route('locx.crm.portal-atendimentos.responder', $atendimento), [
                'mensagem' => 'Boa tarde, vou continuar seu atendimento por aqui.',
            ])
            ->assertRedirect(route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id]));

        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'humano',
            'mensagem' => 'Boa tarde, vou continuar seu atendimento por aqui.',
        ]);

        $this->actingAs($atendente)
            ->getJson(route('locx.crm.portal-atendimentos.sync', [
                'atendimento' => $atendimento,
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mensagens.0.remetente', 'humano')
            ->assertJsonPath('mensagens.0.mensagem', 'Boa tarde, vou continuar seu atendimento por aqui.');

        $this->actingAs($atendente)
            ->get(route('locx.index', ['page' => 'crm']))
            ->assertOk()
            ->assertSee('Clientes com atendimento')
            ->assertSee('Cliente Chat')
            ->assertSee('Boa tarde, vou continuar seu atendimento por aqui.');

        $this->actingAs($atendente)
            ->getJson(route('locx.crm.portal-atendimentos.inbox-sync'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['assinatura']);
    }

    public function test_chat_expira_por_inatividade_do_cliente_e_proxima_mensagem_abre_novo_atendimento(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Inativo',
            'email' => 'inativo@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);
        $atendimento = PortalAtendimento::create([
            'cliente_id' => $cliente->id,
            'loja_id' => $loja->id,
            'assistente' => 'Lau',
            'assunto' => 'documentos',
            'prioridade' => 'normal',
            'status' => 'em_atendimento',
            'mensagem' => 'Documentos',
        ]);
        PortalAtendimentoMensagem::create([
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'bot',
            'mensagem' => 'Qual documento voce precisa atualizar ou consultar?',
            'criado_em' => now()->subMinutes(16),
        ]);

        $this->actingAs($cliente, 'cliente')
            ->getJson(route('cliente.chat.sync', [
                'atendimento_id' => $atendimento->id,
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'fechado')
            ->assertJsonPath('encerrado_por_inatividade', true)
            ->assertJsonPath('mensagens.0.remetente', 'bot')
            ->assertJsonPath('mensagens.0.mensagem', 'Como ficou sem retorno por alguns minutos, encerrei este atendimento. Quando precisar, abra uma nova conversa por aqui.');

        $this->assertDatabaseHas('portal_atendimentos', [
            'id' => $atendimento->id,
            'status' => 'fechado',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'mensagem' => 'Quero enviar minha CNH agora.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assunto', 'documentos')
            ->assertJsonPath('status', 'aguardando_humano')
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.1.remetente', 'bot');

        $this->assertSame(2, PortalAtendimento::where('cliente_id', $cliente->id)->count());
        $novoAtendimento = PortalAtendimento::where('cliente_id', $cliente->id)->latest('id')->firstOrFail();
        $this->assertNotSame($atendimento->id, $novoAtendimento->id);
    }

    public function test_interacao_do_chat_envia_opcao_recebe_lau_e_resposta_do_atendente(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Interacao',
            'email' => 'interacao@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);
        $atendente = User::create([
            'nome' => 'Atendente',
            'email' => 'resposta@locx.test',
            'senha' => Hash::make('123456'),
            'perfil' => 'administrador_geral',
            'status' => 'ativo',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', ['assunto' => 'pagamento'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assunto', 'pagamento')
            ->assertJsonPath('status', 'aguardando_humano')
            ->assertJsonPath('humano', true)
            ->assertJsonPath('mensagens.0.remetente', 'cliente')
            ->assertJsonPath('mensagens.0.mensagem', 'PIX/comprovante')
            ->assertJsonPath('mensagens.1.remetente', 'bot')
            ->assertJsonPath('mensagens.1.mensagem', 'Certo, Cliente. Pode enviar o comprovante, o horario do PIX ou o nome de quem pagou. Vou manter a conversa aberta para um atendente conferir.');

        $atendimento = PortalAtendimento::where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'cliente',
            'mensagem' => 'PIX/comprovante',
        ]);
        $this->assertDatabaseHas('portal_atendimento_mensagens', [
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'bot',
            'mensagem' => 'Certo, Cliente. Pode enviar o comprovante, o horario do PIX ou o nome de quem pagou. Vou manter a conversa aberta para um atendente conferir.',
        ]);

        $ultimoIdAntesAtendente = $atendimento->mensagens()->max('id');
        $this->actingAs($atendente)
            ->postJson(route('locx.crm.portal-atendimentos.responder', $atendimento), [
                'mensagem' => 'Recebido. Pode enviar o comprovante por aqui.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'respondido')
            ->assertJsonPath('mensagens.0.remetente', 'humano')
            ->assertJsonPath('mensagens.0.mensagem', 'Recebido. Pode enviar o comprovante por aqui.');

        $this->actingAs($cliente, 'cliente')
            ->getJson(route('cliente.chat.sync', [
                'atendimento_id' => $atendimento->id,
                'after_id' => $ultimoIdAntesAtendente,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('humano', true)
            ->assertJsonPath('mensagens.0.remetente', 'humano')
            ->assertJsonPath('mensagens.0.mensagem', 'Recebido. Pode enviar o comprovante por aqui.');
    }

    public function test_lau_coleta_dados_de_moto_parada_antes_de_chamar_loja(): void
    {
        $loja = Loja::create(['nome' => 'Loja Centro']);
        $cliente = Cliente::create([
            'loja_id' => $loja->id,
            'nome' => 'Cliente Moto',
            'email' => 'moto@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', ['assunto' => 'moto_parada'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assunto', 'moto_parada')
            ->assertJsonPath('status', 'em_atendimento')
            ->assertJsonPath('humano', false)
            ->assertJsonPath('mensagens.1.remetente', 'bot')
            ->assertJsonPath('mensagens.1.mensagem', 'Me envie a placa da moto para eu localizar o contrato. Se puder, mande tambem sua localizacao e diga se a moto ainda liga.');

        $atendimento = PortalAtendimento::where('cliente_id', $cliente->id)->firstOrFail();

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'mensagem' => 'kmn5B60',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'em_atendimento')
            ->assertJsonPath('humano', false)
            ->assertJsonPath('mensagens.1.remetente', 'bot')
            ->assertJsonPath('mensagens.1.mensagem', 'Recebi a placa KMN5B60. Agora me mande sua localizacao e diga se a moto ainda liga.');

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'mensagem' => 'sim',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'em_atendimento')
            ->assertJsonPath('humano', false)
            ->assertJsonPath('mensagens.1.remetente', 'bot')
            ->assertJsonPath('mensagens.1.mensagem', 'Recebi. Falta so sua localizacao para a loja saber onde acionar o atendimento.');

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'atendimento_id' => $atendimento->id,
                'mensagem' => 'praca',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'aguardando_humano')
            ->assertJsonPath('humano', true)
            ->assertJsonPath('mensagens.1.remetente', 'bot')
            ->assertJsonPath('mensagens.1.mensagem', 'Recebi a placa KMN5B60, a localizacao e que a moto ainda liga. Vou acionar a loja para acompanhar por aqui.');
    }

    public function test_chat_vazio_orienta_cliente_sem_erro_de_validacao(): void
    {
        $cliente = Cliente::create([
            'nome' => 'Cliente Sem Texto',
            'email' => 'semtexto@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->postJson('/portal/chat', [
                'assunto' => '',
                'mensagem' => '',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'triagem')
            ->assertJsonPath('mostrar_opcoes', true)
            ->assertJsonPath('mensagens.0.remetente', 'bot')
            ->assertJsonMissing(['message' => 'Digite sua mensagem ou escolha um assunto.'])
            ->assertJsonMissing(['mensagem' => 'Digite sua mensagem ou escolha um assunto.']);

        $this->assertSame(0, PortalAtendimento::where('cliente_id', $cliente->id)->count());
    }

    public function test_chat_fechado_existente_nao_reabre_como_conversa_ativa(): void
    {
        $cliente = Cliente::create([
            'nome' => 'Cliente Historico',
            'email' => 'historico@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => true,
            'status' => 'ativo',
        ]);
        $atendimento = PortalAtendimento::create([
            'cliente_id' => $cliente->id,
            'assistente' => 'Lau',
            'assunto' => 'pagamento',
            'prioridade' => 'normal',
            'status' => 'fechado',
            'mensagem' => 'PIX/comprovante',
        ]);
        PortalAtendimentoMensagem::create([
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'bot',
            'mensagem' => 'Atendimento encerrado.',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->get('/portal')
            ->assertOk()
            ->assertSee('name="atendimento_id" value=""', false);

        $this->actingAs($cliente, 'cliente')
            ->getJson(route('cliente.chat.sync', [
                'atendimento_id' => $atendimento->id,
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'fechado')
            ->assertJsonPath('encerrado', true)
            ->assertJsonPath('mostrar_opcoes', true);
    }

    public function test_cliente_sem_portal_liberado_nao_autentica(): void
    {
        Cliente::create([
            'nome' => 'Cliente Bloqueado',
            'email' => 'bloqueado@locx.test',
            'senha' => Hash::make('123456'),
            'portal_ativo' => false,
            'status' => 'ativo',
        ]);

        $this->post('/portal/login', [
            'email' => 'bloqueado@locx.test',
            'senha' => '123456',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('cliente');
    }
}
