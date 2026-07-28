<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaCampanha;
use App\Models\CobrancaCampanhaItem;
use App\Models\Contrato;
use App\Models\CrmNota;
use App\Models\CrmTarefa;
use App\Models\EstoqueMovimento;
use App\Models\EstoqueProduto;
use App\Models\ItauConfig;
use App\Models\LookModuloRegistro;
use App\Models\Loja;
use App\Models\MultaTransito;
use App\Models\Motocicleta;
use App\Models\OrdemServico;
use App\Models\Pagamento;
use App\Models\PagbankConfig;
use App\Models\PortalAtendimento;
use App\Models\PortalAtendimentoMensagem;
use App\Models\User;
use App\Models\UsuarioPermissao;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Models\TelegramConfig;
use App\Models\TelegramLog;
use App\Services\AsaasService;
use App\Services\CobrancaCalculator;
use App\Services\CobrancaCampanhaService;
use App\Services\CrmAutomationService;
use App\Services\EmailCobrancaService;
use App\Services\EmailPagamentoService;
use App\Services\ItauService;
use App\Services\PagBankService;
use App\Services\PixGatewayService;
use App\Services\SicoobService;
use App\Services\WhatsAppService;
use App\Services\TelegramService;
use App\Support\Locx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocxController extends Controller
{
    public function __construct(
        private readonly CobrancaCalculator $calculator,
        private readonly PagBankService $pagBank,
        private readonly AsaasService $asaas,
        private readonly SicoobService $sicoob,
        private readonly ItauService $itau,
        private readonly PixGatewayService $pixGateway,
        private readonly WhatsAppService $whatsApp,
        private readonly TelegramService $telegram,
        private readonly CobrancaCampanhaService $campanhasCobranca,
        private readonly CrmAutomationService $crmAutomation,
        private readonly EmailCobrancaService $emailCobranca,
        private readonly EmailPagamentoService $emailPagamento,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('permissoes', 'lojas');
        $page = array_key_exists($request->string('page')->toString(), Locx::MODULOS)
            ? $request->string('page')->toString()
            : 'dashboard';
        abort_unless($user->pode($page), 403, 'Acesso negado para este módulo.');

        $data = [
            'page' => $page,
            'pages' => Locx::MODULOS,
            'acoes' => Locx::ACOES,
            'user' => $user,
            'lojas' => Loja::query()->orderBy('nome')->get(),
        ];

        return view('locx.index', array_merge($data, match ($page) {
            'dashboard' => $this->dashboard($user),
            'reservas', 'contas', 'documentos' => $this->lookModulo($page, $user),
            'crm' => $this->crm($request, $user),
            'clientes' => $this->clientes($request),
            'motos' => $this->motos($request, $user),
            'contratos' => $this->contratos($user),
            'manutencao' => $this->manutencao($request, $user),
            'estoque' => $this->estoque($request, $user),
            'multas' => $this->multas($request, $user),
            'financeiro', 'cobrancas' => $this->financeiro($user),
            'inadimplencia' => $this->inadimplencia($user),
            'relatorios' => $this->relatorios($user),
            'lojas' => $this->lojas(),
            'usuarios' => $this->usuarios($request),
            'bancos' => [
                'pagbankConfig' => $this->pagBank->config(),
                'asaasConfig' => $this->asaas->config(),
                'sicoobConfig' => $this->sicoob->config(),
                'itauConfig' => $this->itau->config(),
                'pixGatewayConfig' => $this->pixGateway->config(),
                'bancoSelecionado' => in_array($request->query('banco'), ['pagbank', 'asaas', 'sicoob', 'itau'], true)
                    ? $request->query('banco')
                    : $this->pixGateway->config()->gateway,
            ],
            'pagbank' => ['pagbankConfig' => $this->pagBank->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'asaas' => ['asaasConfig' => $this->asaas->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'sicoob' => ['sicoobConfig' => $this->sicoob->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'itau' => ['itauConfig' => $this->itau->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'whatsapp' => [
                'whatsappConfig' => $this->whatsApp->config(),
                'whatsappLogs' => WhatsappLog::with('cliente')->latest('id')->limit(80)->get(),
                'graphVersion' => $this->whatsApp->graphVersion(),
            ],
            'telegram' => [
                'telegramConfig' => $this->telegram->config(),
                'telegramLogs' => TelegramLog::with('cliente')->latest('id')->limit(100)->get(),
                'telegramVinculados' => Cliente::query()->whereNotNull('telegram_chat_id')->count(),
            ],
            default => [],
        }));
    }

    private function lookModulo(string $page, User $user): array
    {
        $motos = $this->scope(Motocicleta::query(), $user);
        $contratos = $this->scope(Contrato::query(), $user);
        $cobrancas = $this->scope(Cobranca::query(), $user);
        $multas = $this->scope(MultaTransito::query(), $user);
        $registrosBase = $this->scope(LookModuloRegistro::query(), $user)->where('modulo', $page);

        $abertoFinanceiro = (float) (clone $cobrancas)
            ->whereIn('status', ['aberta', 'parcial', 'atrasada'])
            ->sum(DB::raw('valor_atualizado - valor_pago'));
        $atrasoFinanceiro = (float) (clone $cobrancas)
            ->whereDate('vencimento', '<', today())
            ->where('status', '<>', 'paga')
            ->sum(DB::raw('valor_atualizado - valor_pago'));
        $pagamentosMes = DB::table('pagamentos as p')->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id');
        $this->scopeQuery($pagamentosMes, $user, 'c.loja_id');
        $recebidoMes = (float) $pagamentosMes
            ->whereYear('p.pago_em', now()->year)
            ->whereMonth('p.pago_em', now()->month)
            ->sum('p.valor');

        $catalogo = [
            'reservas' => [
                'titulo' => 'Reservas e disponibilidade',
                'subtitulo' => 'Organize pedidos de locacao, disponibilidade, retirada, troca e encerramento em um fluxo simples para a equipe.',
                'indicadores' => [
                    ['label' => 'Registros', 'valor' => (clone $registrosBase)->count(), 'tipo' => ''],
                    ['label' => 'Contratos ativos', 'valor' => (clone $contratos)->where('status', 'ativo')->count(), 'tipo' => 'ok'],
                    ['label' => 'Motos disponiveis', 'valor' => (clone $motos)->where('status_operacional', 'disponivel')->count(), 'tipo' => 'ok'],
                    ['label' => 'Proximas cobrancas', 'valor' => (clone $cobrancas)->whereBetween('vencimento', [today(), today()->addDays(7)])->count(), 'tipo' => 'warn'],
                ],
                'rotinas' => [
                    ['nome' => 'Consulta de disponibilidade', 'descricao' => 'Registre loja, data, hora, grupo desejado e moto sugerida antes de abrir contrato.', 'status' => 'em uso'],
                    ['nome' => 'Reserva com checklist', 'descricao' => 'Controle cliente, documentos, caucao, origem do atendimento e prioridade.', 'status' => 'em uso'],
                    ['nome' => 'Substituicao e encerramento', 'descricao' => 'Anote troca de moto, avarias, km, pendencias e cobrancas finais.', 'status' => 'em uso'],
                ],
                'form' => ['titulo' => 'Reserva ou solicitacao', 'pessoa' => 'Cliente', 'documento' => 'Moto / grupo', 'telefone' => 'Contato'],
                'atalhos' => ['clientes', 'motos', 'contratos', 'financeiro'],
            ],
            'contas' => [
                'titulo' => 'Contas e bancos',
                'subtitulo' => 'Acompanhe plano de contas, lancamentos bancarios, transferencias e conciliacoes em uma tela de controle.',
                'indicadores' => [
                    ['label' => 'Registros', 'valor' => (clone $registrosBase)->count(), 'tipo' => ''],
                    ['label' => 'A receber', 'valor' => Locx::moeda($abertoFinanceiro), 'tipo' => 'warn'],
                    ['label' => 'Recebido no mes', 'valor' => Locx::moeda($recebidoMes), 'tipo' => 'ok'],
                    ['label' => 'Atraso', 'valor' => Locx::moeda($atrasoFinanceiro), 'tipo' => 'danger'],
                ],
                'rotinas' => [
                    ['nome' => 'Plano de contas', 'descricao' => 'Classifique receitas, despesas, centros de custo e centros de resultado.', 'status' => 'em uso'],
                    ['nome' => 'Movimento em contas', 'descricao' => 'Registre lancamentos bancarios, transferencias e posicao financeira diaria.', 'status' => 'em uso'],
                    ['nome' => 'Conciliacao bancaria', 'descricao' => 'Controle PIX, recebimentos, taxas, estornos e divergencias.', 'status' => 'em uso'],
                ],
                'form' => ['titulo' => 'Lancamento ou conciliacao', 'pessoa' => 'Conta / favorecido', 'documento' => 'Documento', 'telefone' => 'Banco / canal'],
                'atalhos' => ['financeiro', 'cobrancas', 'relatorios'],
            ],
            'documentos' => [
                'titulo' => 'Documentos e assinatura digital',
                'subtitulo' => 'Centralize anexos, validade, contratos, recibos, comunicados e pendencias de assinatura.',
                'indicadores' => [
                    ['label' => 'Registros', 'valor' => (clone $registrosBase)->count(), 'tipo' => ''],
                    ['label' => 'Clientes', 'valor' => Cliente::count(), 'tipo' => ''],
                    ['label' => 'Contratos ativos', 'valor' => (clone $contratos)->where('status', 'ativo')->count(), 'tipo' => 'ok'],
                    ['label' => 'Multas abertas', 'valor' => (clone $multas)->whereIn('status', ['aberta', 'em_recurso'])->count(), 'tipo' => 'warn'],
                ],
                'rotinas' => [
                    ['nome' => 'Anexos digitais', 'descricao' => 'Organize CNH, comprovante, contrato, laudos, fotos e recibos por cliente/moto.', 'status' => 'em uso'],
                    ['nome' => 'Controle de validade', 'descricao' => 'Registre vencimentos de CNH, documentos da moto, apolice e contrato.', 'status' => 'em uso'],
                    ['nome' => 'Assinatura digital', 'descricao' => 'Acompanhe envio, assinatura, pendencia e retorno de documentos.', 'status' => 'em uso'],
                ],
                'form' => ['titulo' => 'Documento ou pendencia', 'pessoa' => 'Cliente / responsavel', 'documento' => 'Tipo / numero', 'telefone' => 'Contato'],
                'atalhos' => ['clientes', 'contratos', 'multas', 'configuracoes'],
            ],
        ];

        return [
            'lookModulo' => $catalogo[$page],
            'lookRegistros' => (clone $registrosBase)->with('loja', 'usuario')->latest('id')->limit(80)->get(),
            'lookRelacionados' => collect($catalogo[$page]['atalhos'])
                ->filter(fn (string $modulo) => array_key_exists($modulo, Locx::MODULOS) && $user->pode($modulo))
                ->values()
                ->all(),
        ];
    }

    public function salvarLookModulo(Request $request): RedirectResponse
    {
        $modulos = ['reservas', 'contas', 'documentos'];
        $dados = $request->validate([
            'modulo' => ['required', Rule::in($modulos)],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'pessoa' => ['nullable', 'string', 'max:180'],
            'documento' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'vencimento' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['aberto', 'em_andamento', 'pendente', 'aprovado', 'concluido', 'cancelado'])],
            'descricao' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->autorizar($request->user(), $dados['modulo'], 'criar');
        $dados['valor'] = $dados['valor'] ?? 0;
        $dados['usuario_id'] = $request->user()->id;

        LookModuloRegistro::create($dados);

        return $this->voltar($dados['modulo'], 'Registro salvo com sucesso.');
    }

    public function salvarCliente(Request $request): RedirectResponse
    {
        $cliente = $request->integer('id') ? Cliente::findOrFail($request->integer('id')) : new Cliente;
        $this->autorizar($request->user(), 'clientes', $cliente->exists ? 'editar' : 'criar');
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'nome' => ['required', 'string', 'max:180'],
            'cpf' => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'cpf')->ignore($cliente->id)],
            'rg' => ['nullable', 'string', 'max:30'],
            'cnh' => ['nullable', 'string', 'max:40'],
            'endereco' => ['nullable', 'string'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'telegram_notificacoes' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:160'],
            'portal_ativo' => ['nullable', 'boolean'],
            'senha_portal' => [$cliente->exists ? 'nullable' : 'nullable', 'string', 'min:6', 'max:120'],
            'status' => ['required', Rule::in(['ativo', 'inadimplente', 'bloqueado', 'encerrado'])],
            'foto_cliente' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'foto_documento' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'comprovante_residencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        unset($dados['id']);
        $senhaPortal = $dados['senha_portal'] ?? null;
        unset($dados['senha_portal']);
        $dados['portal_ativo'] = $request->boolean('portal_ativo');
        $dados['telegram_notificacoes'] = $request->boolean('telegram_notificacoes', true);
        if ($senhaPortal) {
            $dados['senha'] = Hash::make($senhaPortal);
        }
        foreach (['foto_cliente', 'foto_documento', 'comprovante_residencia'] as $campo) {
            if ($request->hasFile($campo)) {
                $dados[$campo] = $request->file($campo)->store('clientes', 'public');
            } else {
                unset($dados[$campo]);
            }
        }
        $cliente->fill($dados);
        $cliente->save();

        return $this->voltar('clientes', 'Cliente salvo com sucesso.');
    }

    public function salvarMoto(Request $request): RedirectResponse
    {
        $moto = $request->integer('id') ? Motocicleta::findOrFail($request->integer('id')) : new Motocicleta;
        $this->autorizar($request->user(), 'motos', $moto->exists ? 'editar' : 'criar');
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'loja_id' => ['required', 'exists:lojas,id'],
            'modelo' => ['required', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:80'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'placa' => ['nullable', 'string', 'max:15'],
            'renavam' => ['nullable', 'string', 'max:40'],
            'chassi' => ['nullable', 'string', 'max:80'],
            'data_aquisicao' => ['nullable', 'date'],
            'seguro' => ['nullable', 'string', 'max:120'],
            'rastreador' => ['nullable', 'string', 'max:120'],
            'status_operacional' => ['required', Rule::in(['disponivel', 'alugada', 'manutencao', 'recuperacao', 'encerrada'])],
        ]);
        unset($dados['id']);
        $moto->fill($dados);
        $moto->save();

        return $this->voltar('motos', 'Motocicleta salva com sucesso.');
    }

    public function salvarContrato(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'contratos', 'criar');
        $dados = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'motocicleta_id' => ['required', 'exists:motocicletas,id'],
            'loja_id' => ['required', 'exists:lojas,id'],
            'data_inicio' => ['required', 'date'],
            'valor_contratado' => ['required', 'numeric', 'min:0.01'],
            'forma_cobranca' => ['required', Rule::in(['semanal', 'quinzenal', 'mensal'])],
            'cobranca_automatica' => ['nullable', 'boolean'],
            'proxima_cobranca_em' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['ativo', 'suspenso', 'encerrado'])],
        ]);
        $dados['cobranca_automatica'] = $request->boolean('cobranca_automatica');
        $dados['proxima_cobranca_em'] = $dados['proxima_cobranca_em'] ?: null;

        DB::transaction(function () use ($dados): void {
            Contrato::create($dados + ['historico_alteracoes' => 'Contrato criado em '.now()->format('d/m/Y H:i')]);
            Motocicleta::whereKey($dados['motocicleta_id'])->update(['status_operacional' => 'alugada']);
        });

        return $this->voltar('contratos', 'Contrato criado.');
    }

    public function salvarOrdemServico(Request $request): RedirectResponse
    {
        $ordem = $request->integer('id') ? OrdemServico::findOrFail($request->integer('id')) : new OrdemServico;
        $this->autorizar($request->user(), 'manutencao', $ordem->exists ? 'editar' : 'criar');
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'motocicleta_id' => ['nullable', 'exists:motocicletas,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'tipo' => ['required', Rule::in(['preventiva', 'corretiva', 'vistoria', 'sinistro'])],
            'titulo' => ['required', 'string', 'max:180'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['aberta', 'em_andamento', 'aguardando_peca', 'concluida', 'cancelada'])],
            'prioridade' => ['required', Rule::in(['baixa', 'normal', 'alta', 'urgente'])],
            'custo_previsto' => ['nullable', 'numeric', 'min:0'],
            'custo_final' => ['nullable', 'numeric', 'min:0'],
            'previsto_em' => ['nullable', 'date'],
        ]);

        unset($dados['id']);
        $dados['custo_previsto'] = $dados['custo_previsto'] ?? 0;
        $dados['custo_final'] = $dados['custo_final'] ?? 0;

        DB::transaction(function () use ($ordem, $dados): void {
            if (! $ordem->exists) {
                $dados['aberto_em'] = now();
            }
            if ($dados['status'] === 'concluida' && ! $ordem->concluido_em) {
                $dados['concluido_em'] = now();
            }
            if ($dados['status'] !== 'concluida') {
                $dados['concluido_em'] = null;
            }

            $ordem->fill($dados);
            $ordem->save();

            if ($ordem->motocicleta_id && in_array($ordem->status, ['aberta', 'em_andamento', 'aguardando_peca'], true)) {
                Motocicleta::whereKey($ordem->motocicleta_id)->update(['status_operacional' => 'manutencao']);
            }

            if ($ordem->motocicleta_id && $ordem->status === 'concluida') {
                Motocicleta::whereKey($ordem->motocicleta_id)
                    ->where('status_operacional', 'manutencao')
                    ->update(['status_operacional' => 'disponivel']);
            }
        });

        return $this->voltar('manutencao', 'Ordem de serviço salva.');
    }

    public function salvarEstoqueProduto(Request $request): RedirectResponse
    {
        $produto = $request->integer('id') ? EstoqueProduto::findOrFail($request->integer('id')) : new EstoqueProduto;
        $this->autorizar($request->user(), 'estoque', $produto->exists ? 'editar' : 'criar');
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'nome' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80'],
            'grupo' => ['nullable', 'string', 'max:80'],
            'unidade' => ['required', 'string', 'max:20'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
        ]);

        unset($dados['id']);
        $dados['estoque_minimo'] = $dados['estoque_minimo'] ?? 0;
        $dados['custo_unitario'] = $dados['custo_unitario'] ?? 0;
        $produto->fill($dados);
        $produto->save();

        return $this->voltar('estoque', 'Produto salvo.');
    }

    public function salvarEstoqueMovimento(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'estoque', 'criar');
        $dados = $request->validate([
            'produto_id' => ['required', 'exists:estoque_produtos,id'],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'tipo' => ['required', Rule::in(['entrada', 'saida', 'ajuste'])],
            'quantidade' => ['required', 'numeric', 'min:0.01'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0'],
            'origem' => ['nullable', 'string', 'max:120'],
            'observacao' => ['nullable', 'string', 'max:3000'],
            'movimentado_em' => ['nullable', 'date'],
        ]);

        $dados['valor_unitario'] = $dados['valor_unitario'] ?? 0;
        $dados['movimentado_em'] = $dados['movimentado_em'] ?? now();
        EstoqueMovimento::create($dados);

        return $this->voltar('estoque', 'Movimento de estoque registrado.');
    }

    public function salvarMulta(Request $request): RedirectResponse
    {
        $multa = $request->integer('id') ? MultaTransito::findOrFail($request->integer('id')) : new MultaTransito;
        $this->autorizar($request->user(), 'multas', $multa->exists ? 'editar' : 'criar');
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'motocicleta_id' => ['nullable', 'exists:motocicletas,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'contrato_id' => ['nullable', 'exists:contratos,id'],
            'auto_infracao' => ['nullable', 'string', 'max:80'],
            'orgao' => ['nullable', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vencimento' => ['nullable', 'date'],
            'ocorrida_em' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['aberta', 'em_recurso', 'transferida', 'paga', 'cancelada'])],
        ]);

        unset($dados['id']);
        $multa->fill($dados);
        $multa->save();

        return $this->voltar('multas', 'Multa salva.');
    }

    public function salvarCrmCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($request->user(), 'crm', 'editar');
        $this->autorizarClienteCrm($request->user(), $cliente);
        $dados = $request->validate([
            'crm_etapa' => ['required', Rule::in(array_keys($this->crmEtapas()))],
        ]);

        $cliente->update($dados);

        return redirect()->route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id])->with('success', 'Etapa do cliente atualizada.');
    }

    public function salvarCrmNota(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'crm', 'criar');
        $dados = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tipo' => ['required', Rule::in(['nota', 'ligacao', 'whatsapp', 'telegram', 'email', 'visita', 'negociacao'])],
            'texto' => ['required', 'string', 'max:3000'],
        ]);
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $this->autorizarClienteCrm($request->user(), $cliente);

        CrmNota::create($dados + [
            'usuario_id' => $request->user()->id,
            'criado_em' => now(),
        ]);
        $cliente->update(['crm_ultimo_contato_em' => now()]);

        return redirect()->route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id])->with('success', 'Nota adicionada ao CRM.');
    }

    public function salvarCrmTarefa(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'crm', 'criar');
        $dados = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'titulo' => ['required', 'string', 'max:180'],
            'tipo' => ['required', Rule::in(['follow_up', 'ligacao', 'whatsapp', 'telegram', 'email', 'cobranca', 'recolhimento'])],
            'prazo_em' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:3000'],
        ]);
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $this->autorizarClienteCrm($request->user(), $cliente);

        $cobranca = null;
        if (in_array($dados['tipo'], ['whatsapp', 'telegram', 'cobranca'], true)) {
            $cobranca = Cobranca::with('cliente', 'contrato.motocicleta')
                ->where('cliente_id', $cliente->id)
                ->whereNotIn('status', ['paga', 'cancelada'])
                ->orderBy('vencimento')
                ->first();
        }

        CrmTarefa::create($dados + [
            'cobranca_id' => $cobranca?->id,
            'usuario_id' => $request->user()->id,
            'status' => 'aberta',
            'criado_em' => now(),
        ]);

        $mensagem = 'Tarefa criada no CRM.';
        if (in_array($dados['tipo'], ['whatsapp', 'telegram', 'cobranca'], true)) {
            if ($dados['tipo'] === 'whatsapp') {
                $mensagem .= $dados['prazo_em']
                    ? ' WhatsApp agendado para o prazo da tarefa.'
                    : ' Informe um prazo para disparar o WhatsApp automaticamente.';
            } else {
                $mensagem .= $dados['prazo_em']
                    ? ' Disparo agendado para o prazo da tarefa.'
                    : ' Informe um prazo para disparar automaticamente.';
            }
        }

        return redirect()->route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id])->with('success', $mensagem);
    }

    public function concluirCrmTarefa(Request $request, CrmTarefa $tarefa): RedirectResponse
    {
        $this->autorizar($request->user(), 'crm', 'editar');
        $tarefa->loadMissing('cliente');
        $this->autorizarClienteCrm($request->user(), $tarefa->cliente);
        $tarefa->update([
            'status' => 'concluida',
            'concluido_em' => now(),
        ]);

        return redirect()->route('locx.index', ['page' => 'crm', 'cliente' => $tarefa->cliente_id])->with('success', 'Tarefa concluida.');
    }

    public function syncPortalAtendimento(Request $request, PortalAtendimento $atendimento): JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $atendimento->loadMissing('cliente', 'atendente');
        $this->autorizarClienteCrm($request->user(), $atendimento->cliente);

        $mensagens = $atendimento->mensagens()
            ->where('id', '>', $request->integer('after_id'))
            ->get()
            ->map(fn (PortalAtendimentoMensagem $mensagem) => $this->formatarMensagemPortal($mensagem))
            ->values();

        return response()->json([
            'ok' => true,
            'atendimento_id' => $atendimento->id,
            'status' => $atendimento->status,
            'status_label' => $this->statusAtendimentoLabel($atendimento->status),
            'atendente_nome' => $atendimento->atendente?->nome,
            'nao_lido' => $this->portalAtendimentoNaoLido($atendimento),
            'mensagens' => $mensagens,
        ]);
    }

    public function syncPortalAtendimentosCliente(Request $request, Cliente $cliente): JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $this->autorizarClienteCrm($request->user(), $cliente);

        $atendimentos = PortalAtendimento::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (PortalAtendimento $atendimento) => [
                'id' => $atendimento->id,
                'status' => $atendimento->status,
                'ultima_mensagem_id' => (int) $atendimento->mensagens()->max('id'),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'atendimentos' => $atendimentos,
        ]);
    }

    public function syncPortalInbox(Request $request): JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $inbox = $this->portalInbox($request->user());

        return response()->json([
            'ok' => true,
            'assinatura' => $this->portalInboxSignature($request->user(), $inbox),
            'metricas' => $this->portalInboxMetrics($request->user(), $inbox),
            'atendimentos' => $inbox
                ->map(fn (PortalAtendimento $atendimento) => $this->formatarAtendimentoInbox($atendimento))
                ->values(),
        ]);
    }

    public function responderPortalAtendimento(Request $request, PortalAtendimento $atendimento): RedirectResponse|JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $atendimento->loadMissing('cliente');
        $this->autorizarClienteCrm($request->user(), $atendimento->cliente);

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'mensagem.required' => 'Digite a resposta para o cliente.',
            'mensagem.min' => 'A resposta precisa ter pelo menos 3 caracteres.',
            'mensagem.max' => 'A resposta deve ter no maximo 2000 caracteres.',
        ]);

        if (($atendimento->canal ?? 'portal') === 'telegram') {
            $envioTelegram = $this->telegram->enviarTexto(
                (string) ($atendimento->canal_chat_id ?: $atendimento->cliente->telegram_chat_id),
                $dados['mensagem'],
                null,
                $atendimento->cliente,
                'resposta_atendente',
                'atendimento'
            );

            if (! ($envioTelegram['ok'] ?? false)) {
                $erro = 'Não foi possível enviar a resposta pelo Telegram: '.($envioTelegram['erro'] ?? 'falha desconhecida');
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => $erro], 422);
                }

                return redirect()
                    ->route('locx.index', ['page' => 'crm', 'cliente' => $atendimento->cliente_id])
                    ->withErrors(['telegram' => $erro]);
            }
        }

        $mensagem = PortalAtendimentoMensagem::create([
            'portal_atendimento_id' => $atendimento->id,
            'remetente' => 'humano',
            'usuario_id' => $request->user()->id,
            'remetente_nome' => $request->user()->nome,
            'mensagem' => $dados['mensagem'],
        ]);

        $agora = now();
        $atendimento->update([
            'status' => 'respondido',
            'resposta' => $dados['mensagem'],
            'lido_em' => $agora,
            'atendente_id' => $atendimento->atendente_id ?: $request->user()->id,
            'assumido_em' => $atendimento->assumido_em ?: $agora,
            'ultima_mensagem_em' => $agora,
            'atualizado_em' => $agora,
            'encerrado_em' => null,
        ]);

        $atendimento->cliente->update(['crm_ultimo_contato_em' => $agora]);
        $atendimento->load('atendente');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'atendimento_id' => $atendimento->id,
                'status' => $atendimento->status,
                'status_label' => $this->statusAtendimentoLabel($atendimento->status),
                'atendente_nome' => $atendimento->atendente?->nome,
                'mensagens' => [$this->formatarMensagemPortal($mensagem)],
            ]);
        }

        return redirect()
            ->route('locx.index', ['page' => 'crm', 'cliente' => $atendimento->cliente_id])
            ->with('success', ($atendimento->canal ?? 'portal') === 'telegram' ? 'Resposta enviada pelo Telegram.' : 'Resposta enviada para o portal do cliente.');
    }

    public function acaoPortalAtendimento(Request $request, PortalAtendimento $atendimento): RedirectResponse|JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $atendimento->loadMissing('cliente');
        $this->autorizarClienteCrm($request->user(), $atendimento->cliente);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['assumir', 'aguardar_cliente', 'resolver', 'reabrir'])],
        ]);

        if ($dados['acao'] === 'reabrir') {
            $outroAtivo = PortalAtendimento::query()
                ->where('cliente_id', $atendimento->cliente_id)
                ->where('id', '<>', $atendimento->id)
                ->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'])
                ->orderByDesc('id')
                ->first();

            if ($outroAtivo) {
                $texto = 'Este cliente ja possui o atendimento #'.$outroAtivo->id.' em andamento. Conclua o atendimento atual antes de reabrir este historico.';

                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => $texto], 422);
                }

                return redirect()
                    ->route('locx.index', ['page' => 'crm', 'atendimento' => $outroAtivo->id])
                    ->withErrors(['atendimento' => $texto]);
            }
        }

        $agora = now();
        $alteracoes = ['atualizado_em' => $agora];
        $mensagem = 'Atendimento atualizado.';

        if ($dados['acao'] === 'assumir') {
            $alteracoes += [
                'status' => 'em_atendimento',
                'atendente_id' => $request->user()->id,
                'assumido_em' => $atendimento->assumido_em ?: $agora,
                'lido_em' => $agora,
                'encerrado_em' => null,
            ];
            $mensagem = 'Atendimento assumido por '.$request->user()->nome.'.';
        } elseif ($dados['acao'] === 'aguardar_cliente') {
            $alteracoes += [
                'status' => 'respondido',
                'atendente_id' => $atendimento->atendente_id ?: $request->user()->id,
                'assumido_em' => $atendimento->assumido_em ?: $agora,
                'lido_em' => $agora,
                'encerrado_em' => null,
            ];
            $mensagem = 'Atendimento marcado como aguardando o cliente.';
        } elseif ($dados['acao'] === 'resolver') {
            $alteracoes += [
                'status' => 'fechado',
                'atendente_id' => $atendimento->atendente_id ?: $request->user()->id,
                'lido_em' => $agora,
                'encerrado_em' => $agora,
            ];
            $mensagem = 'Atendimento concluido.';
        } else {
            $alteracoes += [
                'status' => 'em_atendimento',
                'atendente_id' => $atendimento->atendente_id ?: $request->user()->id,
                'assumido_em' => $atendimento->assumido_em ?: $agora,
                'lido_em' => $agora,
                'encerrado_em' => null,
            ];
            $mensagem = 'Atendimento reaberto.';
        }

        $atendimento->update($alteracoes);
        $atendimento->load('atendente');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'mensagem' => $mensagem,
                'status' => $atendimento->status,
                'status_label' => $this->statusAtendimentoLabel($atendimento->status),
                'atendente_nome' => $atendimento->atendente?->nome,
            ]);
        }

        return redirect()
            ->route('locx.index', ['page' => 'crm', 'atendimento' => $atendimento->id])
            ->with('success', $mensagem);
    }

    public function marcarPortalAtendimentoLido(Request $request, PortalAtendimento $atendimento): JsonResponse
    {
        $this->autorizarResponderPortal($request->user());
        $atendimento->loadMissing('cliente');
        $this->autorizarClienteCrm($request->user(), $atendimento->cliente);

        $atendimento->update(['lido_em' => now()]);

        return response()->json(['ok' => true]);
    }

    public function salvarCobranca(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'cobrancas', 'criar');
        $dados = $request->validate([
            'contrato_id' => ['required', 'exists:contratos,id'],
            'vencimento' => ['required', 'date'],
            'valor_principal' => ['required', 'numeric', 'min:0.01'],
        ]);
        $contrato = Contrato::findOrFail($dados['contrato_id']);
        $cobranca = Cobranca::create([
            ...$dados,
            'cliente_id' => $contrato->cliente_id,
            'loja_id' => $contrato->loja_id,
            'valor_atualizado' => $dados['valor_principal'],
            'valor_pago' => 0,
            'status' => 'aberta',
            'whatsapp_status' => 'pendente',
            'telegram_status' => 'pendente',
        ]);
        $resultado = $this->pixGateway->criarPix($cobranca);
        $mensagem = $resultado['ok'] ?? false
            ? 'Cobrança criada e PIX '.$this->pixGateway->nomeGateway().' gerado.'
            : 'Cobrança criada. '.$this->pixGateway->nomeGateway().': '.($resultado['erro'] ?? 'PIX não gerado');

        if (($resultado['ok'] ?? false) && $cobranca->fresh()->pix_copia_cola) {
            $email = $this->emailCobranca->enviarCobranca($cobranca->fresh(['cliente', 'contrato.motocicleta']));
            $mensagem .= ($email['ok'] ?? false)
                ? ' E-mail enviado para '.$email['email'].'.'
                : ' E-mail nao enviado: '.($email['erro'] ?? 'falha desconhecida').'.';
        }

        return $this->voltar(
            in_array($request->string('page')->toString(), ['financeiro', 'cobrancas'], true)
                ? $request->string('page')->toString()
                : 'financeiro',
            $mensagem
        );
    }

    public function salvarPagamento(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'financeiro', 'editar');
        $dados = $request->validate([
            'cobranca_id' => ['required', 'exists:cobrancas,id'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma' => ['required', Rule::in(['pix', 'dinheiro', 'cartao', 'transferencia'])],
        ]);

        $pagamento = null;

        DB::transaction(function () use ($dados, &$pagamento): void {
            $cobranca = Cobranca::lockForUpdate()->findOrFail($dados['cobranca_id']);
            $pagamento = Pagamento::create($dados + ['pago_em' => now()]);
            $pago = (float) $cobranca->valor_pago + (float) $dados['valor'];
            $status = $pago >= (float) $cobranca->valor_principal ? 'paga' : 'parcial';
            $cobranca->update([
                'valor_pago' => $pago,
                'valor_atualizado' => $this->calculator->valorAtualizado(
                    $cobranca->valor_principal,
                    $pago,
                    $cobranca->vencimento
                ),
                'status' => $status,
                'whatsapp_status' => $status === 'paga' ? 'conciliado' : $cobranca->whatsapp_status,
                'telegram_status' => $status === 'paga' ? 'conciliado' : $cobranca->telegram_status,
                'atualizado_em' => now(),
            ]);

            if ($status === 'paga') {
                $this->crmAutomation->fecharTarefasDeCobranca($cobranca->fresh('cliente'));
            }
        });

        $mensagem = 'Pagamento registrado.';
        if ($pagamento) {
            $email = $this->emailPagamento->enviarConfirmacao(
                $pagamento->fresh('cobranca.cliente', 'cobranca.contrato.motocicleta')
            );
            $mensagem .= ($email['ok'] ?? false)
                ? ' E-mail de confirmacao enviado para '.$email['email'].'.'
                : ' E-mail nao enviado: '.($email['erro'] ?? 'falha desconhecida').'.';
        }

        return $this->voltar('financeiro', $mensagem);
    }

    public function gerarPix(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $page = $request->string('page', 'cobrancas')->toString();
        $this->autorizar($request->user(), $page === 'cobrancas' ? 'cobrancas' : 'financeiro', 'editar');
        $resultado = $this->pixGateway->criarPix($cobranca);
        $mensagem = ($resultado['ok'] ?? false)
            ? 'PIX '.$this->pixGateway->nomeGateway().' gerado com sucesso.'
            : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida');

        if (($resultado['ok'] ?? false) && $cobranca->fresh()->pix_copia_cola) {
            $email = $this->emailCobranca->enviarCobranca($cobranca->fresh(['cliente', 'contrato.motocicleta']));
            $mensagem .= ($email['ok'] ?? false)
                ? ' E-mail enviado para '.$email['email'].'.'
                : ' E-mail nao enviado: '.($email['erro'] ?? 'falha desconhecida').'.';
        }

        return $this->voltar(
            in_array($page, ['financeiro', 'cobrancas'], true) ? $page : 'cobrancas',
            $mensagem
        );
    }

    public function conciliarPix(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'financeiro', 'editar');

        $resultado = $this->pixGateway->conciliarPendentes(
            (int) config('locx.pix.conciliacao_limite', 50)
        );

        $mensagem = 'Conciliação PIX executada: '
            .$resultado['analisadas'].' analisadas, '
            .$resultado['baixadas'].' baixadas, '
            .$resultado['pendentes'].' pendentes.';

        if (! empty($resultado['erros'])) {
            $mensagem .= ' Erros: '.implode(' | ', array_slice($resultado['erros'], 0, 3));
        }

        return $this->voltar($request->string('page', 'financeiro')->toString(), $mensagem);
    }

    public function enviarWhatsApp(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $this->autorizar($request->user(), 'inadimplencia', 'editar');
        $this->autorizarCobranca($request->user(), $cobranca);
        $resultado = $this->whatsApp->enviarCobranca($cobranca);

        return $this->voltar(
            'inadimplencia',
            ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Cobrança aceita pela Meta para envio.')
                : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida')
        );
    }

    public function enviarTelegram(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $this->autorizar($request->user(), 'inadimplencia', 'editar');
        $this->autorizarCobranca($request->user(), $cobranca);
        $resultado = $this->telegram->enviarCobranca($cobranca);

        return $this->voltar(
            'inadimplencia',
            ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Cobrança enviada pelo Telegram.')
                : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida')
        );
    }

    public function salvarWhatsApp(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'whatsapp', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'oficial', 'evolution'])],
            'ativo' => ['required', 'boolean'],
            'waba_id' => ['nullable', 'required_if:modo,oficial', 'string', 'max:255', 'regex:/^\d+$/'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'evolution_base_url' => ['nullable', 'required_if:modo,evolution', 'url', 'max:500'],
            'evolution_instance' => ['nullable', 'required_if:modo,evolution', 'string', 'max:120'],
            'evolution_api_key' => ['nullable', 'string'],
            'verify_token' => ['required', 'string', 'max:255'],
            'template_cobranca' => ['required', 'string', 'max:120'],
            'template_language' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
            'template_lembrete' => ['required', 'string', 'max:120'],
            'template_bloqueio' => ['required', 'string', 'max:120'],
        ]);

        if (blank($dados['access_token'] ?? null)) {
            unset($dados['access_token']);
        }
        if (blank($dados['evolution_api_key'] ?? null)) {
            unset($dados['evolution_api_key']);
        }

        WhatsappConfig::query()->updateOrCreate(['id' => 1], $dados + ['atualizado_em' => now()]);

        return $this->voltar('whatsapp', 'Configurações do WhatsApp salvas.');
    }

    public function testarWhatsApp(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'whatsapp', 'editar');
        $resultado = $this->whatsApp->testar();

        return $this->voltar('whatsapp', ($resultado['ok'] ?? false)
            ? ($resultado['mensagem'] ?? 'Conexão validada.')
            : 'Erro: '.($resultado['erro'] ?? $resultado['mensagem'] ?? 'falha desconhecida'));
    }

    public function salvarTelegram(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'telegram', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'api'])],
            'ativo' => ['required', 'boolean'],
            'bot_token' => ['nullable', 'string'],
            'bot_username' => ['nullable', 'string', 'max:120'],
            'atendimento_bot_token' => ['nullable', 'string'],
            'atendimento_bot_username' => ['nullable', 'string', 'max:120'],
            'webhook_secret' => ['required', 'string', 'min:16', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'atendimento_webhook_secret' => ['nullable', 'string', 'min:16', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'parse_mode' => ['nullable', Rule::in(['HTML', 'MarkdownV2', 'sem_formatacao'])],
            'template_cobranca' => ['required', 'string', 'max:4000'],
            'template_lembrete' => ['nullable', 'string', 'max:4000'],
            'template_vencimento' => ['nullable', 'string', 'max:4000'],
            'template_pagamento' => ['nullable', 'string', 'max:4000'],
            'template_gerente' => ['nullable', 'string', 'max:4000'],
            'gerente_chat_id' => ['nullable', 'string', 'max:80'],
        ]);

        if (blank($dados['bot_token'] ?? null)) {
            unset($dados['bot_token']);
        }
        if (blank($dados['atendimento_bot_token'] ?? null)) {
            unset($dados['atendimento_bot_token']);
        }
        $dados['bot_username'] = ltrim((string) ($dados['bot_username'] ?? ''), '@') ?: null;
        $dados['atendimento_bot_username'] = ltrim((string) ($dados['atendimento_bot_username'] ?? ''), '@') ?: null;
        $temBotAtendimento = filled($dados['atendimento_bot_token'] ?? null) || filled($dados['atendimento_bot_username'] ?? null);
        $dados['atendimento_webhook_secret'] = ($dados['atendimento_webhook_secret'] ?? null) ?: ($temBotAtendimento ? Str::random(48) : null);
        $parseMode = $dados['parse_mode'] ?? null;
        $dados['parse_mode'] = $parseMode === 'sem_formatacao' ? null : $parseMode;

        TelegramConfig::query()->updateOrCreate(['id' => 1], $dados + ['atualizado_em' => now()]);

        return $this->voltar('telegram', 'Configurações do Telegram salvas.');
    }

    public function testarTelegram(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'telegram', 'editar');
        $resultado = $this->telegram->testarTodos();

        return $this->voltar('telegram', ($resultado['ok'] ?? false)
            ? ($resultado['mensagem'] ?? 'Conexão validada.')
            : 'Erro: '.($resultado['erro'] ?? $resultado['mensagem'] ?? 'falha desconhecida'));
    }

    public function configurarWebhookTelegram(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'telegram', 'editar');
        $resultado = $this->telegram->configurarWebhooks(route('locx.webhook-telegram'));

        return $this->voltar('telegram', ($resultado['ok'] ?? false)
            ? ($resultado['mensagem'] ?? 'Webhook configurado.')
            : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida'));
    }

    public function criarCampanhaCobranca(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'cobrancas', 'criar');
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:180'],
            'publico' => ['required', Rule::in(['todos_abertos', 'vence_hoje', 'vencidas_7', 'vencidas_15', 'vencidas_30', 'selecionadas'])],
            'canais' => ['required', 'array', 'min:1'],
            'canais.*' => ['required', Rule::in(['whatsapp', 'email', 'telegram'])],
            'estrategia' => ['required', Rule::in(['todos', 'prioridade'])],
            'mensagem' => ['required', 'string', 'min:10', 'max:4000'],
            'agendado_para' => ['nullable', 'date'],
            'cobrancas' => ['nullable', 'array'],
            'cobrancas.*' => ['integer', 'exists:cobrancas,id'],
        ], [
            'canais.required' => 'Selecione pelo menos um canal de envio.',
            'mensagem.required' => 'Informe a mensagem da campanha.',
        ]);

        if ($dados['publico'] === 'selecionadas' && empty($dados['cobrancas'])) {
            return back()->withInput()->withErrors(['cobrancas' => 'Marque pelo menos uma cobrança na tabela.']);
        }

        $campanha = $this->campanhasCobranca->criar($dados, $request->user());
        if ($campanha->total_destinatarios === 0) {
            $this->campanhasCobranca->cancelar($campanha);
            return $this->voltar('cobrancas', 'Nenhuma cobrança corresponde ao público escolhido.');
        }

        if (! $campanha->agendado_para || $campanha->agendado_para->isPast()) {
            $resultado = $this->campanhasCobranca->processar($campanha, 200);
            $mensagem = 'Campanha #'.$campanha->id.' processada: '.$resultado['enviados'].' enviados e '.$resultado['falhas'].' falhas.';
        } else {
            $mensagem = 'Campanha #'.$campanha->id.' agendada para '.$campanha->agendado_para->format('d/m/Y H:i').'.';
        }

        return $this->voltar('cobrancas', $mensagem);
    }

    public function executarCampanhaCobranca(Request $request, CobrancaCampanha $campanha): RedirectResponse
    {
        $this->autorizar($request->user(), 'cobrancas', 'editar');
        abort_unless($this->campanhasCobranca->podeGerenciar($campanha, $request->user()), 403, 'Campanha fora do seu acesso.');
        $resultado = $this->campanhasCobranca->processar($campanha, 300);

        return $this->voltar('cobrancas', 'Campanha #'.$campanha->id.': '.$resultado['processados'].' processados, '.$resultado['enviados'].' enviados e '.$resultado['falhas'].' falhas.');
    }

    public function cancelarCampanhaCobranca(Request $request, CobrancaCampanha $campanha): RedirectResponse
    {
        $this->autorizar($request->user(), 'cobrancas', 'editar');
        abort_unless($this->campanhasCobranca->podeGerenciar($campanha, $request->user()), 403, 'Campanha fora do seu acesso.');
        $this->campanhasCobranca->cancelar($campanha);

        return $this->voltar('cobrancas', 'Campanha #'.$campanha->id.' cancelada.');
    }

    public function salvarPagBank(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'pagbank', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'api'])],
            'ambiente' => ['required', Rule::in(['sandbox', 'producao'])],
            'ativo' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'merchant_reference' => ['required', 'string', 'max:80'],
        ]);
        $acao = $request->string('acao')->toString();
        PagbankConfig::query()->updateOrCreate(['id' => 1], $dados + [
            'webhook_url' => $dados['webhook_url'] ?: route('locx.webhook-pagbank'),
            'atualizado_em' => now(),
        ]);
        $mensagem = 'Configurações do PagBank salvas.';
        if ($acao === 'testar') {
            $resultado = $this->pagBank->testar();
            $mensagem = ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Conexão validada.')
                : 'PagBank salvo, mas o teste falhou: '.($resultado['erro'] ?? 'erro desconhecido');
        }

        if ($request->input('page') === 'bancos') {
            return redirect()->route('locx.index', ['page' => 'bancos', 'banco' => 'pagbank'])->with('success', $mensagem);
        }

        return $this->voltar('pagbank', $mensagem);
    }

    public function salvarAsaas(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'asaas', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'api'])],
            'ambiente' => ['required', Rule::in(['sandbox', 'producao'])],
            'ativo' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:160'],
        ]);
        $acao = $request->string('acao')->toString();
        if (blank($dados['api_key'] ?? null)) {
            unset($dados['api_key']);
        }
        $dados['webhook_url'] = $dados['webhook_url'] ?: route('locx.webhook-asaas');
        $dados['webhook_token'] = $dados['webhook_token'] ?: AsaasService::DEFAULT_WEBHOOK_TOKEN;

        \App\Models\AsaasConfig::query()->updateOrCreate(['id' => 1], $dados + ['atualizado_em' => now()]);
        $mensagem = 'Configurações do Asaas salvas.';
        if ($acao === 'testar') {
            $resultado = $this->asaas->testar();
            $mensagem = ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Conexão validada.')
                : 'Asaas salvo, mas o teste falhou: '.($resultado['erro'] ?? 'erro desconhecido');
        }

        if ($request->input('page') === 'bancos') {
            return redirect()->route('locx.index', ['page' => 'bancos', 'banco' => 'asaas'])->with('success', $mensagem);
        }

        return $this->voltar('asaas', $mensagem);
    }

    public function salvarSicoob(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'sicoob', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'api'])],
            'ambiente' => ['required', Rule::in(['sandbox', 'producao'])],
            'ativo' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'chave_pix' => ['nullable', 'string', 'max:180'],
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'token_url' => ['nullable', 'url', 'max:500'],
            'cert_path' => ['nullable', 'string', 'max:500'],
            'key_path' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:160'],
            'acao' => ['nullable', Rule::in(['salvar', 'testar'])],
            'page' => ['nullable', 'string'],
        ]);

        $dados['api_base_url'] = $dados['api_base_url'] ?: SicoobService::DEFAULT_API_BASE_URL;
        $dados['token_url'] = $dados['token_url'] ?: SicoobService::DEFAULT_TOKEN_URL;
        $dados['webhook_token'] = $dados['webhook_token'] ?: SicoobService::DEFAULT_WEBHOOK_TOKEN;
        $dados['webhook_url'] = $dados['webhook_url'] ?: route('locx.webhook-sicoob', ['token' => $dados['webhook_token']]);
        unset($dados['acao'], $dados['page']);
        \App\Models\SicoobConfig::query()->updateOrCreate(
            ['id' => 1],
            $dados + ['access_token' => null, 'token_expires_at' => null, 'atualizado_em' => now()]
        );

        $mensagem = 'Configurações do Sicoob salvas.';
        if ($request->input('acao') === 'testar') {
            $resultado = $this->sicoob->testar();
            $mensagem = ($resultado['ok'] ?? false)
                ? 'Sicoob salvo e conexão validada: '.($resultado['mensagem'] ?? 'ok')
                : 'Sicoob salvo, mas o teste falhou: '.($resultado['erro'] ?? 'erro desconhecido');
        }

        if ($request->input('page') === 'bancos') {
            return redirect()->route('locx.index', ['page' => 'bancos', 'banco' => 'sicoob'])->with('success', $mensagem);
        }

        return $this->voltar('sicoob', $mensagem);
    }

    public function salvarItau(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'itau', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'api'])],
            'ambiente' => ['required', Rule::in(['sandbox', 'producao'])],
            'ativo' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'chave_pix' => ['nullable', 'string', 'max:180'],
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'token_url' => ['nullable', 'url', 'max:500'],
            'cert_path' => ['nullable', 'string', 'max:500'],
            'key_path' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:160'],
            'acao' => ['nullable', Rule::in(['salvar', 'testar'])],
            'page' => ['nullable', 'string'],
        ]);

        $dados['api_base_url'] = $dados['api_base_url'] ?: ItauService::DEFAULT_API_BASE_URL;
        $dados['token_url'] = $dados['token_url'] ?: ItauService::DEFAULT_TOKEN_URL;
        $dados['webhook_token'] = $dados['webhook_token'] ?: ItauService::DEFAULT_WEBHOOK_TOKEN;
        $dados['webhook_url'] = $dados['webhook_url'] ?: route('locx.webhook-itau', ['token' => $dados['webhook_token']]);
        unset($dados['acao'], $dados['page']);

        ItauConfig::query()->updateOrCreate(
            ['id' => 1],
            $dados + ['access_token' => null, 'token_expires_at' => null, 'atualizado_em' => now()]
        );

        $mensagem = 'Configuracoes do Itau salvas.';
        if ($request->input('acao') === 'testar') {
            $resultado = $this->itau->testar();
            $mensagem = ($resultado['ok'] ?? false)
                ? 'Itau salvo e conexao validada: '.($resultado['mensagem'] ?? 'ok')
                : 'Itau salvo, mas o teste falhou: '.($resultado['erro'] ?? 'erro desconhecido');
        }

        if ($request->input('page') === 'bancos') {
            return redirect()->route('locx.index', ['page' => 'bancos', 'banco' => 'itau'])->with('success', $mensagem);
        }

        return $this->voltar('itau', $mensagem);
    }

    public function salvarGatewayPix(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'configuracoes', 'editar');
        $dados = $request->validate([
            'gateway' => ['required', Rule::in(['pagbank', 'asaas', 'sicoob', 'itau'])],
            'page' => ['nullable', 'string'],
        ]);

        $this->pixGateway->salvar($dados['gateway']);
        $page = in_array($dados['page'] ?? '', ['bancos', 'pagbank', 'asaas', 'sicoob', 'itau', 'configuracoes'], true)
            ? $dados['page']
            : 'configuracoes';

        return $this->voltar($page, 'Gateway PIX principal atualizado.');
    }

    public function salvarUsuario(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'usuarios', $request->integer('id') ? 'editar' : 'criar');
        $usuario = $request->integer('id') ? User::findOrFail($request->integer('id')) : new User;
        $dados = $request->validate([
            'id' => ['nullable', 'integer'],
            'nome' => ['required', 'string', 'max:140'],
            'email' => ['required', 'email', 'max:160', Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'senha' => [$usuario->exists ? 'nullable' : 'required', 'string', 'min:6'],
            'perfil' => ['required', Rule::in(['administrador_geral', 'diretor', 'financeiro', 'gerente_loja', 'atendente', 'cobranca'])],
            'loja_id' => ['nullable', 'exists:lojas,id'],
            'status' => ['required', Rule::in(['ativo', 'bloqueado'])],
            'lojas' => ['array'],
            'lojas.*' => ['integer', 'exists:lojas,id'],
            'perms' => ['array'],
        ]);

        DB::transaction(function () use ($usuario, $dados): void {
            $usuario->fill(collect($dados)->except(['id', 'senha', 'lojas', 'perms'])->all());
            if (! empty($dados['senha'])) {
                $usuario->senha = Hash::make($dados['senha']);
            }
            $usuario->save();
            $usuario->lojas()->sync($dados['lojas'] ?? []);
            $usuario->permissoes()->delete();
            foreach ($dados['perms'] ?? [] as $modulo => $acoes) {
                foreach (array_keys($acoes) as $acao) {
                    if (isset(Locx::MODULOS[$modulo], Locx::ACOES[$acao])) {
                        UsuarioPermissao::create([
                            'usuario_id' => $usuario->id,
                            'modulo' => $modulo,
                            'acao' => $acao,
                        ]);
                    }
                }
            }
        });

        return $this->voltar('usuarios', 'Usuário e permissões salvos.');
    }

    private function dashboard(User $user): array
    {
        $motos = $this->scope(Motocicleta::query(), $user);
        $cobrancas = $this->scope(Cobranca::query(), $user);
        $pagamentos = DB::table('pagamentos as p')->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id');
        $this->scopeQuery($pagamentos, $user, 'c.loja_id');
        $recebidoMes = (clone $pagamentos)->whereYear('p.pago_em', now()->year)->whereMonth('p.pago_em', now()->month)->sum('p.valor');
        $recebidoHoje = (clone $pagamentos)->whereDate('p.pago_em', today())->sum('p.valor');
        $aReceber = (clone $cobrancas)->whereIn('status', ['aberta', 'parcial', 'atrasada'])->sum(DB::raw('valor_atualizado - valor_pago'));
        $atraso = (clone $cobrancas)->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->sum(DB::raw('valor_atualizado - valor_pago'));
        $labels30 = [];
        $recebidos30 = [];
        foreach (range(29, 0) as $dias) {
            $data = today()->subDays($dias);
            $labels30[] = $data->format('d/m');
            $recebidos30[] = (float) (clone $pagamentos)->whereDate('p.pago_em', $data)->sum('p.valor');
        }
        $lojas = Loja::orderBy('nome')->get();
        $lojaRecebido = $lojas->map(fn (Loja $loja) => (float) DB::table('pagamentos as p')
            ->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
            ->where('c.loja_id', $loja->id)->sum('p.valor'));

        return [
            'totalMotos' => (clone $motos)->count(),
            'motosAlugadas' => (clone $motos)->where('status_operacional', 'alugada')->count(),
            'motosDisponiveis' => (clone $motos)->where('status_operacional', 'disponivel')->count(),
            'motosManutencao' => (clone $motos)->where('status_operacional', 'manutencao')->count(),
            'recebidoMes' => (float) $recebidoMes,
            'recebidoHoje' => (float) $recebidoHoje,
            'aReceber' => (float) $aReceber,
            'atraso' => (float) $atraso,
            'clientesInadimplentes' => (clone $cobrancas)->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->distinct('cliente_id')->count('cliente_id'),
            'cobrancasStatus' => [
                'pagas' => (clone $cobrancas)->where('status', 'paga')->count(),
                'abertas' => (clone $cobrancas)->where('status', 'aberta')->count(),
                'parciais' => (clone $cobrancas)->where('status', 'parcial')->count(),
                'atrasadas' => (clone $cobrancas)->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->count(),
            ],
            'labels30' => $labels30,
            'recebidos30' => $recebidos30,
            'lojaLabels' => $lojas->pluck('nome')->all(),
            'lojaRecebido' => $lojaRecebido->all(),
            'vencimentosProximos' => $this->scope(Cobranca::with('cliente', 'contrato.motocicleta'), $user)
                ->whereBetween('vencimento', [today()->subDay(), today()->addDays(2)])->orderBy('vencimento')->limit(8)->get(),
            'topInadimplentes' => $this->scope(Cobranca::with('cliente', 'contrato.motocicleta'), $user)
                ->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')
                ->orderByRaw('(valor_atualizado - valor_pago) DESC')->limit(6)->get(),
        ];
    }

    private function crm(Request $request, User $user): array
    {
        $this->crmAutomation->sincronizarAtrasos();

        $clientes = $this->scope(Cliente::with('loja'), $user)->orderBy('nome')->limit(160)->get();
        $inbox = $this->portalInbox($user);

        $atendimentoSelecionado = null;
        if ($request->integer('atendimento')) {
            $atendimentoSelecionado = $this->scope(
                PortalAtendimento::with(['cliente.loja', 'mensagens', 'atendente']),
                $user
            )->whereKey($request->integer('atendimento'))->first();
        }

        $clienteSelecionado = null;
        if ($atendimentoSelecionado) {
            $clienteSelecionado = $atendimentoSelecionado->cliente;
        } elseif ($request->integer('cliente')) {
            $clienteSelecionado = $this->scope(Cliente::with('loja'), $user)
                ->whereKey($request->integer('cliente'))
                ->first();

            if ($clienteSelecionado) {
                $atendimentoSelecionado = $this->portalAtendimentoPrincipalDoCliente($user, $clienteSelecionado->id);
                $atendimentoSelecionado?->loadMissing('cliente.loja', 'mensagens', 'atendente');
            }
        }

        $atendimentoSelecionado ??= $inbox->firstWhere('status', 'aguardando_humano')
            ?: $inbox->firstWhere('status', 'novo')
            ?: $inbox->first();
        $clienteSelecionado ??= $atendimentoSelecionado?->cliente ?: $clientes->first();

        if ($atendimentoSelecionado && ! $atendimentoSelecionado->relationLoaded('mensagens')) {
            $atendimentoSelecionado->load('mensagens', 'atendente', 'cliente.loja');
        }

        $crmClientes = $clientes->map(function (Cliente $cliente): array {
            $cobrancas = Cobranca::query()->where('cliente_id', $cliente->id);
            $saldoAberto = (float) (clone $cobrancas)
                ->where('status', '<>', 'paga')
                ->sum(DB::raw('valor_atualizado - valor_pago'));
            $atrasadas = (clone $cobrancas)
                ->whereDate('vencimento', '<', today())
                ->where('status', '<>', 'paga')
                ->count();
            $proximaTarefa = CrmTarefa::query()
                ->where('cliente_id', $cliente->id)
                ->where('status', 'aberta')
                ->orderBy('prazo_em')
                ->first();
            $ultimoWhatsapp = WhatsappLog::query()->where('cliente_id', $cliente->id)->max('criado_em');
            $ultimaNota = CrmNota::query()->where('cliente_id', $cliente->id)->max('criado_em');
            $ultimoPagamento = DB::table('pagamentos as p')
                ->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
                ->where('c.cliente_id', $cliente->id)
                ->max('p.pago_em');

            return [
                'cliente' => $cliente,
                'saldo_aberto' => $saldoAberto,
                'atrasadas' => $atrasadas,
                'proxima_tarefa' => $proximaTarefa,
                'ultimo_contato' => collect([$cliente->crm_ultimo_contato_em, $ultimoWhatsapp, $ultimaNota, $ultimoPagamento])
                    ->filter()
                    ->map(fn ($data) => \Carbon\Carbon::parse($data))
                    ->sortDesc()
                    ->first(),
            ];
        });

        $tarefasAbertas = CrmTarefa::with('cliente')
            ->whereIn('cliente_id', $clientes->pluck('id'))
            ->where('status', 'aberta')
            ->orderBy('prazo_em')
            ->limit(30)
            ->get();

        $clienteResumo = $clienteSelecionado
            ? $crmClientes->first(fn (array $linha) => $linha['cliente']->id === $clienteSelecionado->id)
            : null;
        $atendimentosCliente = $clienteSelecionado
            ? $this->portalAtendimentosDoCliente($user, $clienteSelecionado->id)
            : collect();

        return [
            'crmEtapas' => $this->crmEtapas(),
            'crmPipeline' => collect($this->crmEtapas())->mapWithKeys(
                fn ($label, $etapa) => [$etapa => $clientes->where('crm_etapa', $etapa)->count()]
            ),
            'crmClientes' => $crmClientes,
            'crmCliente' => $clienteSelecionado,
            'crmClienteResumo' => $clienteResumo,
            'crmTimeline' => $clienteSelecionado ? $this->crmTimeline($clienteSelecionado) : collect(),
            'crmTarefasAbertas' => $tarefasAbertas,
            'crmTarefasVencidas' => $tarefasAbertas->filter(fn (CrmTarefa $tarefa) => $tarefa->prazo_em && $tarefa->prazo_em->isPast())->count(),
            'crmPortalInbox' => $inbox,
            'crmPortalInboxSignature' => $this->portalInboxSignature($user, $inbox),
            'crmPortalMetricas' => $this->portalInboxMetrics($user, $inbox),
            'crmAtendimentoSelecionado' => $atendimentoSelecionado,
            'crmAtendimentosCliente' => $atendimentosCliente,
            'crmRespostasRapidas' => [
                'Ola! Vou verificar isso para voce agora.',
                'Pode me informar o numero do contrato ou da cobranca?',
                'Recebi sua mensagem. Vou validar os dados e retorno em seguida.',
                'Seu comprovante foi recebido e esta em analise.',
                'O atendimento foi encaminhado para a equipe responsavel.',
                'Consegui ajudar ou ficou alguma duvida?',
            ],
        ];
    }

    private function portalInbox(User $user)
    {
        $atendimentos = $this->scope(
            PortalAtendimento::with(['cliente', 'ultimaMensagem', 'ultimaMensagemCliente', 'atendente']),
            $user
        )
            ->where('status', '<>', 'cancelado')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return $atendimentos
            ->groupBy('cliente_id')
            ->map(function ($grupo): PortalAtendimento {
                /** @var PortalAtendimento $principal */
                $principal = $this->ordenarAtendimentosPortal($grupo)->first();
                $abertos = $grupo->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido']);
                $naoLidos = $grupo->filter(fn (PortalAtendimento $atendimento) => $this->portalAtendimentoNaoLido($atendimento))->count();

                $principal->setAttribute('cliente_total_atendimentos', $grupo->count());
                $principal->setAttribute('cliente_atendimentos_abertos', $abertos->count());
                $principal->setAttribute('cliente_nao_lidos', $naoLidos);
                $principal->setAttribute(
                    'cliente_assuntos_busca',
                    $grupo->pluck('assunto')->filter()->unique()->implode(' ')
                );

                return $principal;
            })
            ->sort(function (PortalAtendimento $a, PortalAtendimento $b): int {
                return $this->compararAtendimentosPortal($a, $b);
            })
            ->take(60)
            ->values();
    }

    private function portalAtendimentosDoCliente(User $user, int $clienteId)
    {
        return $this->scope(
            PortalAtendimento::with(['ultimaMensagem', 'ultimaMensagemCliente', 'atendente']),
            $user
        )
            ->where('cliente_id', $clienteId)
            ->where('status', '<>', 'cancelado')
            ->orderByRaw('COALESCE(atualizado_em, ultima_mensagem_em, criado_em) DESC')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    private function portalAtendimentoPrincipalDoCliente(User $user, int $clienteId): ?PortalAtendimento
    {
        $atendimentos = $this->portalAtendimentosDoCliente($user, $clienteId);

        return $this->ordenarAtendimentosPortal($atendimentos)->first();
    }

    private function ordenarAtendimentosPortal($atendimentos)
    {
        return $atendimentos->sort(function (PortalAtendimento $a, PortalAtendimento $b): int {
            return $this->compararAtendimentosPortal($a, $b);
        })->values();
    }

    private function compararAtendimentosPortal(PortalAtendimento $a, PortalAtendimento $b): int
    {
        $prioridades = [
            'aguardando_humano' => 0,
            'novo' => 1,
            'em_atendimento' => 2,
            'respondido' => 3,
            'fechado' => 4,
            'cancelado' => 5,
        ];
        $prioridadeA = $prioridades[$a->status] ?? 9;
        $prioridadeB = $prioridades[$b->status] ?? 9;

        if ($prioridadeA !== $prioridadeB) {
            return $prioridadeA <=> $prioridadeB;
        }

        $dataA = ($a->atualizado_em ?: $a->ultima_mensagem_em ?: $a->criado_em)?->timestamp ?? 0;
        $dataB = ($b->atualizado_em ?: $b->ultima_mensagem_em ?: $b->criado_em)?->timestamp ?? 0;

        if ($dataA !== $dataB) {
            return $dataB <=> $dataA;
        }

        return $b->id <=> $a->id;
    }

    private function portalInboxSignature(User $user, $inbox = null): string
    {
        $inbox ??= $this->portalInbox($user);

        return $inbox
            ->map(fn (PortalAtendimento $atendimento) => implode(':', [
                $atendimento->cliente_id,
                $atendimento->id,
                $atendimento->status,
                (int) $atendimento->ultimaMensagem?->id,
                $atendimento->atendente_id ?: 0,
                (int) ($atendimento->cliente_nao_lidos ?? 0),
                (int) ($atendimento->cliente_total_atendimentos ?? 1),
                (int) ($atendimento->cliente_atendimentos_abertos ?? 0),
            ]))
            ->implode('|');
    }

    private function portalInboxMetrics(User $user, $inbox = null): array
    {
        $inbox ??= $this->portalInbox($user);
        $ativos = $inbox->whereIn('status', ['novo', 'em_atendimento', 'aguardando_humano', 'respondido']);
        $base = $this->scope(PortalAtendimento::query(), $user);

        return [
            'abertos' => $ativos->count(),
            'aguardando_humano' => $inbox->where('status', 'aguardando_humano')->count(),
            'em_atendimento' => $inbox->where('status', 'em_atendimento')->count(),
            'aguardando_cliente' => $inbox->where('status', 'respondido')->count(),
            'resolvidos_hoje' => (clone $base)
                ->where('status', 'fechado')
                ->whereDate('encerrado_em', today())
                ->distinct()
                ->count('cliente_id'),
        ];
    }

    private function formatarAtendimentoInbox(PortalAtendimento $atendimento): array
    {
        $ultimaMensagem = $atendimento->ultimaMensagem;
        $nome = $atendimento->cliente?->nome ?: 'Cliente';
        $iniciais = collect(preg_split('/\s+/', trim($nome)))
            ->filter()
            ->take(2)
            ->map(fn (string $parte) => strtoupper(substr($parte, 0, 1)))
            ->implode('');
        $naoLidos = (int) ($atendimento->cliente_nao_lidos ?? ($this->portalAtendimentoNaoLido($atendimento) ? 1 : 0));

        return [
            'id' => $atendimento->id,
            'cliente_id' => $atendimento->cliente_id,
            'cliente_nome' => $nome,
            'iniciais' => $iniciais ?: 'CL',
            'assunto' => $atendimento->assunto,
            'canal' => $atendimento->canal ?? 'portal',
            'canal_label' => ($atendimento->canal ?? 'portal') === 'telegram' ? 'Telegram' : 'Portal',
            'assunto_label' => ucfirst(str_replace('_', ' ', $atendimento->assunto)),
            'assuntos_busca' => (string) ($atendimento->cliente_assuntos_busca ?? $atendimento->assunto),
            'status' => $atendimento->status,
            'status_label' => $this->statusAtendimentoLabel($atendimento->status),
            'prioridade' => $atendimento->prioridade,
            'ultima_mensagem' => $ultimaMensagem?->mensagem ?: $atendimento->mensagem,
            'ultima_hora' => ($ultimaMensagem?->criado_em ?: $atendimento->criado_em)?->format('H:i'),
            'nao_lido' => $naoLidos > 0,
            'nao_lidos' => $naoLidos,
            'total_atendimentos' => (int) ($atendimento->cliente_total_atendimentos ?? 1),
            'atendimentos_abertos' => (int) ($atendimento->cliente_atendimentos_abertos ?? 0),
            'atendente_nome' => $atendimento->atendente?->nome,
            'url' => route('locx.index', ['page' => 'crm', 'atendimento' => $atendimento->id]),
        ];
    }

    private function portalAtendimentoNaoLido(PortalAtendimento $atendimento): bool
    {
        $ultimaCliente = $atendimento->relationLoaded('ultimaMensagemCliente')
            ? $atendimento->ultimaMensagemCliente
            : $atendimento->mensagens()->where('remetente', 'cliente')->latest('id')->first();

        return (bool) ($ultimaCliente && (! $atendimento->lido_em || $ultimaCliente->criado_em?->gt($atendimento->lido_em)));
    }

    private function statusAtendimentoLabel(string $status): string
    {
        return match ($status) {
            'novo' => 'Novo',
            'aguardando_humano' => 'Aguardando equipe',
            'em_atendimento' => 'Em atendimento',
            'respondido' => 'Aguardando cliente',
            'fechado' => 'Resolvido',
            'cancelado' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function crmTimeline(Cliente $cliente)
    {
        $items = collect();

        CrmNota::with('usuario')
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (CrmNota $nota) => $items->push([
                'data' => $nota->criado_em,
                'tipo' => 'Nota',
                'titulo' => ucfirst($nota->tipo).' registrada',
                'texto' => $nota->texto,
                'status' => 'info',
            ]));

        CrmTarefa::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (CrmTarefa $tarefa) => $items->push([
                'data' => $tarefa->concluido_em ?: $tarefa->criado_em,
                'tipo' => 'Tarefa',
                'titulo' => $tarefa->titulo,
                'texto' => ($tarefa->observacao ?: 'Sem observacao').($tarefa->prazo_em ? ' | Prazo: '.$tarefa->prazo_em->format('d/m/Y H:i') : ''),
                'status' => $tarefa->status === 'concluida' ? 'ok' : 'warn',
            ]));

        PortalAtendimento::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (PortalAtendimento $atendimento) => $items->push([
                'data' => $atendimento->criado_em,
                'tipo' => 'Portal',
                'titulo' => 'Lau - '.str_replace('_', ' ', $atendimento->assunto),
                'texto' => $atendimento->mensagem,
                'status' => $atendimento->status === 'respondido' ? 'ok' : ($atendimento->prioridade === 'alta' ? 'danger' : 'warn'),
            ]));

        Cobranca::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (Cobranca $cobranca) => $items->push([
                'data' => $cobranca->criado_em ?? $cobranca->vencimento,
                'tipo' => 'Cobranca',
                'titulo' => 'Cobranca #'.$cobranca->id.' - '.Locx::moeda($cobranca->valor_principal),
                'texto' => 'Vencimento '.$cobranca->vencimento->format('d/m/Y').' | Status '.$cobranca->status,
                'status' => $cobranca->status === 'paga' ? 'ok' : ($cobranca->status === 'atrasada' ? 'danger' : 'warn'),
            ]));

        WhatsappLog::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (WhatsappLog $log) => $items->push([
                'data' => $log->criado_em,
                'tipo' => 'WhatsApp',
                'titulo' => $log->tipo ?: 'Mensagem',
                'texto' => $log->erro ?: \Illuminate\Support\Str::limit((string) $log->mensagem, 140),
                'status' => $log->status === 'enviado' || $log->status === 'demo' ? 'ok' : ($log->status === 'erro' ? 'danger' : 'warn'),
            ]));

        TelegramLog::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (TelegramLog $log) => $items->push([
                'data' => $log->criado_em,
                'tipo' => 'Telegram',
                'titulo' => $log->tipo ?: 'Mensagem',
                'texto' => $log->erro ?: \Illuminate\Support\Str::limit((string) $log->mensagem, 140),
                'status' => in_array($log->status, ['enviado', 'demo', 'recebido'], true) ? 'ok' : ($log->status === 'erro' ? 'danger' : 'warn'),
            ]));

        CobrancaCampanhaItem::query()
            ->with('campanha')
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(fn (CobrancaCampanhaItem $item) => $items->push([
                'data' => $item->processado_em ?: $item->criado_em,
                'tipo' => 'Campanha',
                'titulo' => $item->campanha?->nome ?: 'Campanha de cobrança',
                'texto' => $item->erro ?: 'Canais: '.implode(', ', array_keys((array) $item->resultados_json)),
                'status' => in_array($item->status, ['enviado', 'parcial'], true) ? 'ok' : ($item->status === 'falha' ? 'danger' : 'warn'),
            ]));

        DB::table('pagamentos as p')
            ->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
            ->where('c.cliente_id', $cliente->id)
            ->select('p.*', 'c.id as cobranca_numero')
            ->orderByDesc('p.id')
            ->limit(20)
            ->get()
            ->each(fn ($pagamento) => $items->push([
                'data' => \Carbon\Carbon::parse($pagamento->pago_em),
                'tipo' => 'Pagamento',
                'titulo' => 'Pagamento da cobranca #'.$pagamento->cobranca_numero,
                'texto' => Locx::moeda($pagamento->valor).' via '.$pagamento->forma,
                'status' => 'ok',
            ]));

        return $items
            ->filter(fn ($item) => ! empty($item['data']))
            ->sortByDesc('data')
            ->take(40)
            ->values();
    }

    private function crmEtapas(): array
    {
        return [
            'lead' => 'Lead',
            'cadastro' => 'Cadastro',
            'contrato_ativo' => 'Contrato ativo',
            'em_cobranca' => 'Em cobranca',
            'recuperacao' => 'Recuperacao',
            'encerrado' => 'Encerrado',
        ];
    }

    private function clientes(Request $request): array
    {
        return [
            'clienteEdit' => $request->integer('edit') ? Cliente::findOrFail($request->integer('edit')) : null,
            'clientes' => Cliente::latest('id')->limit(120)->get(),
        ];
    }

    private function motos(Request $request, User $user): array
    {
        return [
            'motoEdit' => $request->integer('edit') ? Motocicleta::findOrFail($request->integer('edit')) : null,
            'motos' => $this->scope(Motocicleta::with('loja'), $user)->latest('id')->limit(150)->get(),
        ];
    }

    private function contratos(User $user): array
    {
        return [
            'clientes' => Cliente::orderBy('nome')->get(),
            'motos' => $this->scope(Motocicleta::query(), $user)->latest('id')->get(),
            'contratos' => $this->scope(Contrato::with('cliente', 'motocicleta', 'loja'), $user)->latest('id')->limit(120)->get(),
        ];
    }

    private function manutencao(Request $request, User $user): array
    {
        $ordensBase = $this->scope(OrdemServico::query(), $user);
        $ordemEdit = null;
        if ($request->integer('edit')) {
            $ordemEdit = (clone $ordensBase)->whereKey($request->integer('edit'))->firstOrFail();
        }

        return [
            'ordemEdit' => $ordemEdit,
            'motos' => $this->scope(Motocicleta::query(), $user)->orderBy('placa')->get(),
            'clientes' => $this->scope(Cliente::query(), $user)->orderBy('nome')->get(),
            'ordensServico' => $this->scope(OrdemServico::with('loja', 'motocicleta', 'cliente'), $user)->latest('id')->limit(140)->get(),
            'manutencaoResumo' => [
                'abertas' => (clone $ordensBase)->whereIn('status', ['aberta', 'em_andamento', 'aguardando_peca'])->count(),
                'concluidasMes' => (clone $ordensBase)->where('status', 'concluida')->whereMonth('concluido_em', now()->month)->whereYear('concluido_em', now()->year)->count(),
                'custoPrevisto' => (float) (clone $ordensBase)->whereIn('status', ['aberta', 'em_andamento', 'aguardando_peca'])->sum('custo_previsto'),
                'custoFinalMes' => (float) (clone $ordensBase)->whereMonth('concluido_em', now()->month)->whereYear('concluido_em', now()->year)->sum('custo_final'),
            ],
        ];
    }

    private function estoque(Request $request, User $user): array
    {
        $produtos = $this->scope(EstoqueProduto::with('loja'), $user)->latest('id')->limit(150)->get();
        $saldos = EstoqueMovimento::query()
            ->select('produto_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'saida' THEN -quantidade ELSE quantidade END), 0) as saldo")
            ->whereIn('produto_id', $produtos->pluck('id'))
            ->groupBy('produto_id')
            ->pluck('saldo', 'produto_id');

        $produtosBaixos = $produtos->filter(
            fn (EstoqueProduto $produto) => (float) ($saldos[$produto->id] ?? 0) <= (float) $produto->estoque_minimo
        )->count();

        return [
            'produtoEdit' => $request->integer('edit')
                ? $this->scope(EstoqueProduto::query(), $user)->whereKey($request->integer('edit'))->firstOrFail()
                : null,
            'produtosEstoque' => $produtos,
            'estoqueSaldos' => $saldos,
            'movimentosEstoque' => $this->scope(EstoqueMovimento::with('produto', 'loja'), $user)->latest('id')->limit(120)->get(),
            'estoqueResumo' => [
                'produtos' => $produtos->count(),
                'baixos' => $produtosBaixos,
                'entradasMes' => (float) $this->scope(EstoqueMovimento::query(), $user)->where('tipo', 'entrada')->whereMonth('movimentado_em', now()->month)->sum('quantidade'),
                'saidasMes' => (float) $this->scope(EstoqueMovimento::query(), $user)->where('tipo', 'saida')->whereMonth('movimentado_em', now()->month)->sum('quantidade'),
            ],
        ];
    }

    private function multas(Request $request, User $user): array
    {
        $multasBase = $this->scope(MultaTransito::query(), $user);

        return [
            'multaEdit' => $request->integer('edit')
                ? (clone $multasBase)->whereKey($request->integer('edit'))->firstOrFail()
                : null,
            'motos' => $this->scope(Motocicleta::query(), $user)->orderBy('placa')->get(),
            'clientes' => $this->scope(Cliente::query(), $user)->orderBy('nome')->get(),
            'contratos' => $this->scope(Contrato::with('cliente', 'motocicleta'), $user)->latest('id')->limit(180)->get(),
            'multasTransito' => $this->scope(MultaTransito::with('loja', 'motocicleta', 'cliente', 'contrato'), $user)->latest('id')->limit(140)->get(),
            'multasResumo' => [
                'abertas' => (clone $multasBase)->whereIn('status', ['aberta', 'em_recurso'])->count(),
                'valorAberto' => (float) (clone $multasBase)->whereIn('status', ['aberta', 'em_recurso'])->sum('valor'),
                'pagasMes' => (clone $multasBase)->where('status', 'paga')->whereMonth('criado_em', now()->month)->whereYear('criado_em', now()->year)->count(),
                'vencendo' => (clone $multasBase)->whereIn('status', ['aberta', 'em_recurso'])->whereBetween('vencimento', [today(), today()->addDays(7)])->count(),
            ],
        ];
    }

    private function financeiro(User $user): array
    {
        $cobrancasQuery = $this->scope(Cobranca::query(), $user);
        $cobrancas = $this->scope(Cobranca::with('cliente'), $user)->latest('id')->limit(160)->get();
        foreach ($cobrancas as $cobranca) {
            if ($cobranca->status !== 'paga') {
                $atualizado = $this->calculator->valorAtualizado($cobranca->valor_principal, $cobranca->valor_pago, $cobranca->vencimento);
                $status = $cobranca->vencimento->isPast() ? 'atrasada' : $cobranca->status;
                if (abs($atualizado - (float) $cobranca->valor_atualizado) > 0.01 || $status !== $cobranca->status) {
                    $cobranca->update(['valor_atualizado' => $atualizado, 'status' => $status]);
                }
            }
        }

        $campanhasQuery = CobrancaCampanha::query();
        if (! $user->isAdmin()) {
            $campanhasQuery->where('criado_por', $user->id);
        }

        $campanhaItensQuery = CobrancaCampanhaItem::query()
            ->with('campanha', 'cliente', 'cobranca');
        if (! $user->isAdmin()) {
            $campanhaItensQuery->whereHas('campanha', fn (Builder $query) => $query->where('criado_por', $user->id));
        }

        return [
            'financeiroResumo' => [
                'aberto' => (float) (clone $cobrancasQuery)->where('status', '<>', 'paga')->sum(DB::raw('valor_atualizado - valor_pago')),
                'pagoMes' => (float) Pagamento::whereYear('pago_em', now()->year)->whereMonth('pago_em', now()->month)->sum('valor'),
                'parciais' => (clone $cobrancasQuery)->where('status', 'parcial')->count(),
                'atrasadas' => (clone $cobrancasQuery)->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->count(),
            ],
            'contratos' => $this->scope(Contrato::with('cliente', 'motocicleta'), $user)->latest('id')->get(),
            'cobrancasAbertas' => $this->scope(Cobranca::with('cliente'), $user)->where('status', '<>', 'paga')->orderBy('vencimento')->get(),
            'cobrancas' => $cobrancas,
            'campanhasCobranca' => (clone $campanhasQuery)->with('criador')->latest('id')->limit(30)->get(),
            'campanhaItensRecentes' => $campanhaItensQuery->latest('id')->limit(100)->get(),
            'campanhasResumo' => [
                'agendadas' => (clone $campanhasQuery)->where('status', 'agendada')->count(),
                'concluidas' => (clone $campanhasQuery)->where('status', 'concluida')->count(),
                'falhas' => (clone $campanhasQuery)->whereIn('status', ['parcial', 'falha'])->count(),
            ],
        ];
    }

    private function inadimplencia(User $user): array
    {
        return [
            'inadimplentes' => $this->scope(Cobranca::with('cliente', 'contrato.motocicleta'), $user)
                ->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->orderBy('vencimento')->get(),
        ];
    }

    private function relatorios(User $user): array
    {
        $lojas = Loja::orderBy('id')->get();

        return [
            'relatorioLojas' => $lojas->map(fn (Loja $loja) => [
                'label' => $loja->nome,
                'value' => (float) DB::table('pagamentos as p')->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')
                    ->where('c.loja_id', $loja->id)->sum('p.valor'),
            ]),
            'clientesStatus' => collect(['ativo', 'inadimplente', 'bloqueado', 'encerrado'])
                ->mapWithKeys(fn ($status) => [$status => Cliente::where('status', $status)->count()]),
            'cobrancas' => $this->scope(Cobranca::with('cliente'), $user)->latest('id')->limit(160)->get(),
        ];
    }

    private function lojas(): array
    {
        return [
            'resumoLojas' => Loja::orderBy('nome')->get()->map(fn (Loja $loja) => [
                'loja' => $loja,
                'motos' => Motocicleta::where('loja_id', $loja->id)->count(),
                'alugadas' => Motocicleta::where('loja_id', $loja->id)->where('status_operacional', 'alugada')->count(),
                'disponiveis' => Motocicleta::where('loja_id', $loja->id)->where('status_operacional', 'disponivel')->count(),
                'recebido' => DB::table('pagamentos as p')->join('cobrancas as c', 'c.id', '=', 'p.cobranca_id')->where('c.loja_id', $loja->id)->sum('p.valor'),
                'atraso' => Cobranca::where('loja_id', $loja->id)->whereDate('vencimento', '<', today())->where('status', '<>', 'paga')->sum(DB::raw('valor_atualizado - valor_pago')),
            ]),
        ];
    }

    private function usuarios(Request $request): array
    {
        $edit = $request->integer('edit')
            ? User::with('lojas', 'permissoes')->findOrFail($request->integer('edit'))
            : null;

        return [
            'usuarioEdit' => $edit,
            'usuarios' => User::with('loja')->latest('id')->get(),
            'lojasSelecionadas' => $edit?->lojas->pluck('id')->all() ?? [],
            'permissoesSelecionadas' => $edit?->permissoes->groupBy('modulo')->map(
                fn ($items) => $items->pluck('acao')->flip()->map(fn () => true)->all()
            )->all() ?? [],
        ];
    }

    private function scope(Builder $query, User $user, string $coluna = 'loja_id'): Builder
    {
        return Locx::limitarPorLoja($query, $user, $coluna);
    }

    private function scopeQuery($query, User $user, string $coluna): void
    {
        if (! $user->isAdmin()) {
            $ids = $user->lojaIdsPermitidas();
            $ids ? $query->whereIn($coluna, $ids) : $query->whereRaw('1 = 0');
        }
    }

    private function autorizar(User $user, string $modulo, string $acao): void
    {
        abort_unless($user->pode($modulo, $acao), 403, 'Acesso negado para esta ação.');
    }

    private function autorizarResponderPortal(User $user): void
    {
        abort_unless(
            $user->pode('crm', 'editar') || $user->pode('crm', 'criar'),
            403,
            'Acesso negado para esta ação.'
        );
    }

    private function formatarMensagemPortal(PortalAtendimentoMensagem $mensagem): array
    {
        $remetente = $this->normalizarRemetentePortal($mensagem->remetente);
        $texto = $mensagem->mensagem;

        if ($remetente === 'cliente' && in_array($texto, [
            'Debito/fatura',
            'PIX/comprovante',
            'Contrato',
            'Moto/manutencao',
            'Documentos',
            'Multa',
            'Falar com a loja',
        ], true)) {
            $texto = 'Escolheu o assunto: '.$texto;
        }

        return [
            'id' => $mensagem->id,
            'remetente' => $remetente,
            'remetente_nome' => $mensagem->remetente_nome,
            'mensagem' => $texto,
            'hora' => $mensagem->criado_em?->format('H:i') ?? now()->format('H:i'),
            'criado_em' => $mensagem->criado_em?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    private function normalizarRemetentePortal(?string $remetente): string
    {
        $remetente = Str::lower(trim((string) $remetente));

        if (in_array($remetente, ['cliente', 'client', 'usuario', 'user', 'portal_cliente'], true)) {
            return 'cliente';
        }

        if (in_array($remetente, ['humano', 'atendente', 'admin', 'administrador', 'equipe', 'operador'], true)) {
            return 'humano';
        }

        return 'bot';
    }

    private function autorizarCobranca(User $user, Cobranca $cobranca): void
    {
        if ($user->isAdmin()) {
            return;
        }

        abort_unless(in_array((int) $cobranca->loja_id, $user->lojaIdsPermitidas(), true), 403, 'Cobrança fora da loja permitida.');
    }

    private function autorizarClienteCrm(User $user, Cliente $cliente): void
    {
        if ($user->isAdmin()) {
            return;
        }

        abort_unless(in_array((int) $cliente->loja_id, $user->lojaIdsPermitidas(), true), 403, 'Cliente fora da loja permitida.');
    }

    private function voltar(string $page, string $mensagem): RedirectResponse
    {
        return redirect()->route('locx.index', ['page' => $page])->with('success', $mensagem);
    }
}
