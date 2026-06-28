<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\CrmNota;
use App\Models\CrmTarefa;
use App\Models\Loja;
use App\Models\Motocicleta;
use App\Models\Pagamento;
use App\Models\PagbankConfig;
use App\Models\User;
use App\Models\UsuarioPermissao;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Services\AsaasService;
use App\Services\CobrancaCalculator;
use App\Services\PagBankService;
use App\Services\PixGatewayService;
use App\Services\WhatsAppService;
use App\Support\Locx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocxController extends Controller
{
    public function __construct(
        private readonly CobrancaCalculator $calculator,
        private readonly PagBankService $pagBank,
        private readonly AsaasService $asaas,
        private readonly PixGatewayService $pixGateway,
        private readonly WhatsAppService $whatsApp,
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
            'crm' => $this->crm($request, $user),
            'clientes' => $this->clientes($request),
            'motos' => $this->motos($request, $user),
            'contratos' => $this->contratos($user),
            'financeiro', 'cobrancas', 'pix' => $this->financeiro($user),
            'inadimplencia' => $this->inadimplencia($user),
            'relatorios' => $this->relatorios($user),
            'lojas' => $this->lojas(),
            'usuarios' => $this->usuarios($request),
            'pagbank' => ['pagbankConfig' => $this->pagBank->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'asaas' => ['asaasConfig' => $this->asaas->config(), 'pixGatewayConfig' => $this->pixGateway->config()],
            'whatsapp' => [
                'whatsappConfig' => $this->whatsApp->config(),
                'whatsappLogs' => WhatsappLog::with('cliente')->latest('id')->limit(80)->get(),
                'graphVersion' => $this->whatsApp->graphVersion(),
            ],
            default => [],
        }));
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
            'email' => ['nullable', 'email', 'max:160'],
            'status' => ['required', Rule::in(['ativo', 'inadimplente', 'bloqueado', 'encerrado'])],
            'foto_cliente' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'foto_documento' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'comprovante_residencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        unset($dados['id']);
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
            'tipo' => ['required', Rule::in(['nota', 'ligacao', 'whatsapp', 'email', 'visita', 'negociacao'])],
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
            'tipo' => ['required', Rule::in(['follow_up', 'ligacao', 'whatsapp', 'email', 'cobranca', 'recolhimento'])],
            'prazo_em' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:3000'],
        ]);
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $this->autorizarClienteCrm($request->user(), $cliente);

        CrmTarefa::create($dados + [
            'usuario_id' => $request->user()->id,
            'status' => 'aberta',
            'criado_em' => now(),
        ]);

        return redirect()->route('locx.index', ['page' => 'crm', 'cliente' => $cliente->id])->with('success', 'Tarefa criada no CRM.');
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

    public function salvarCobranca(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'financeiro', 'criar');
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
        ]);
        $resultado = $this->pixGateway->criarPix($cobranca);
        $mensagem = $resultado['ok'] ?? false
            ? 'Cobrança criada e PIX '.$this->pixGateway->nomeGateway().' gerado.'
            : 'Cobrança criada. '.$this->pixGateway->nomeGateway().': '.($resultado['erro'] ?? 'PIX não gerado');

        return $this->voltar('financeiro', $mensagem);
    }

    public function salvarPagamento(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'financeiro', 'editar');
        $dados = $request->validate([
            'cobranca_id' => ['required', 'exists:cobrancas,id'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma' => ['required', Rule::in(['pix', 'dinheiro', 'cartao', 'transferencia'])],
        ]);

        DB::transaction(function () use ($dados): void {
            $cobranca = Cobranca::lockForUpdate()->findOrFail($dados['cobranca_id']);
            Pagamento::create($dados + ['pago_em' => now()]);
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
                'atualizado_em' => now(),
            ]);
        });

        return $this->voltar('financeiro', 'Pagamento registrado.');
    }

    public function gerarPix(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $this->autorizar($request->user(), 'financeiro', 'editar');
        $resultado = $this->pixGateway->criarPix($cobranca);

        return $this->voltar(
            $request->string('page', 'financeiro')->toString(),
            ($resultado['ok'] ?? false) ? 'PIX '.$this->pixGateway->nomeGateway().' gerado com sucesso.' : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida')
        );
    }

    public function enviarWhatsApp(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $this->autorizar($request->user(), 'inadimplencia', 'editar');
        $resultado = $this->whatsApp->enviarCobranca($cobranca);

        return $this->voltar(
            'inadimplencia',
            ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Cobrança aceita pela Meta para envio.')
                : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida')
        );
    }

    public function salvarWhatsApp(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'whatsapp', 'editar');
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'oficial'])],
            'ativo' => ['required', 'boolean'],
            'waba_id' => ['nullable', 'required_if:modo,oficial', 'string', 'max:255', 'regex:/^\d+$/'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['required', 'string', 'max:255'],
            'template_cobranca' => ['required', 'string', 'max:120'],
            'template_language' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
            'template_lembrete' => ['required', 'string', 'max:120'],
            'template_bloqueio' => ['required', 'string', 'max:120'],
        ]);

        if (blank($dados['access_token'] ?? null)) {
            unset($dados['access_token']);
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
            : 'Erro: '.($resultado['erro'] ?? 'falha desconhecida'));
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
        $dados['webhook_token'] = $dados['webhook_token'] ?: 'locx_asaas_webhook_token';

        \App\Models\AsaasConfig::query()->updateOrCreate(['id' => 1], $dados + ['atualizado_em' => now()]);
        $mensagem = 'Configurações do Asaas salvas.';
        if ($acao === 'testar') {
            $resultado = $this->asaas->testar();
            $mensagem = ($resultado['ok'] ?? false)
                ? ($resultado['mensagem'] ?? 'Conexão validada.')
                : 'Asaas salvo, mas o teste falhou: '.($resultado['erro'] ?? 'erro desconhecido');
        }

        return $this->voltar('asaas', $mensagem);
    }

    public function salvarGatewayPix(Request $request): RedirectResponse
    {
        $this->autorizar($request->user(), 'configuracoes', 'editar');
        $dados = $request->validate([
            'gateway' => ['required', Rule::in(['pagbank', 'asaas'])],
            'page' => ['nullable', 'string'],
        ]);

        $this->pixGateway->salvar($dados['gateway']);
        $page = in_array($dados['page'] ?? '', ['pagbank', 'asaas', 'pix', 'configuracoes'], true)
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
        $clientes = $this->scope(Cliente::with('loja'), $user)->orderBy('nome')->limit(160)->get();
        $clienteSelecionado = null;
        if ($request->integer('cliente')) {
            $clienteSelecionado = $this->scope(Cliente::with('loja'), $user)->whereKey($request->integer('cliente'))->first();
        }
        $clienteSelecionado ??= $clientes->first();

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

        return [
            'crmEtapas' => $this->crmEtapas(),
            'crmPipeline' => collect($this->crmEtapas())->mapWithKeys(
                fn ($label, $etapa) => [$etapa => $clientes->where('crm_etapa', $etapa)->count()]
            ),
            'crmClientes' => $crmClientes,
            'crmCliente' => $clienteSelecionado,
            'crmTimeline' => $clienteSelecionado ? $this->crmTimeline($clienteSelecionado) : collect(),
            'crmTarefasAbertas' => $tarefasAbertas,
        ];
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
