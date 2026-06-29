<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LocX - {{ $pages[$page] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Locx::asset('assets/css/style.css') }}">
</head>
<body>
<header class="mobile-header">
    <div class="brand"><div class="brand-icon">LX</div><div><strong>Loc<b>X</b></strong><span>Gestão operacional</span></div></div>
    <button type="button" class="mobile-menu-toggle">☰</button>
</header>
<div class="mobile-menu-overlay"></div>
<div class="app">
    <aside class="sidebar" id="sidebarMenu">
        <button type="button" class="mobile-menu-close">Fechar</button>
        <div class="brand"><div class="brand-icon">LX</div><div><strong>Loc<b>X</b></strong><span>Financeiro e Operacional</span></div></div>
        <div class="nav-title">Navegação</div>
        <nav class="menu">
            @foreach ($pages as $key => $label)
                @if ($user->pode($key))
                    <a class="{{ $page === $key ? 'active' : '' }}" href="{{ route('locx.index', ['page' => $key]) }}">
                        <span>{!! \App\Support\Locx::icon($key) !!}</span>{{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <div class="empresa-logo"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.png') }}" alt="LocX"></div>
            Logado como: <strong>{{ $user->nome }}</strong><br>
            {{ \App\Support\Locx::perfil($user->perfil) }}<br><br>
            <form method="post" action="{{ route('locx.logout') }}">@csrf<button class="btn secondary" type="submit">Sair</button></form>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div><h1>{{ $pages[$page] }}</h1><p>Sistema web LocX com gestão multiunidades, operação, financeiro e cobrança.</p></div>
            <div class="toolbar"><a class="btn secondary" href="{{ route('locx.index', ['page' => 'dashboard']) }}">Visão Geral</a></div>
        </header>

        @if (session('success'))<div class="notice"><strong>{{ session('success') }}</strong></div>@endif
        @if ($errors->any())<div class="alert"><strong>{{ $errors->first() }}</strong></div>@endif

        @if ($page === 'dashboard')
            <div class="cards kpi-row">
                <div class="metric"><span>Receita recebida no mês</span><strong>{{ \App\Support\Locx::moeda($recebidoMes) }}</strong><small>Pagamentos conciliados</small></div>
                <div class="metric ok"><span>Recebido hoje</span><strong>{{ \App\Support\Locx::moeda($recebidoHoje) }}</strong><small>Baixas do dia</small></div>
                <div class="metric warn"><span>A receber</span><strong>{{ \App\Support\Locx::moeda($aReceber) }}</strong><small>Cobranças abertas/parciais</small></div>
                <div class="metric danger"><span>Inadimplência</span><strong>{{ \App\Support\Locx::moeda($atraso) }}</strong><small>{{ $clientesInadimplentes }} clientes em atraso</small></div>
            </div>
            <div class="chart-row four">
                <div class="panel chart-card"><div class="chart-head"><div><span>RECEITA</span><h2>Composição financeira</h2></div><strong>{{ \App\Support\Locx::moeda($recebidoMes + $aReceber) }}</strong></div><div id="chartReceita" class="donut-premium"></div></div>
                <div class="panel chart-card"><div class="chart-head"><div><span>STATUS</span><h2>Cobranças</h2></div><strong>{{ array_sum($cobrancasStatus) }}</strong></div><div id="chartStatus" class="donut-premium"></div></div>
                <div class="panel chart-card"><div class="chart-head"><div><span>RECEBIDO</span><h2>Por loja</h2></div><strong>{{ \App\Support\Locx::moeda(array_sum($lojaRecebido)) }}</strong></div><div id="chartRecebidoLojas" class="mini-bars-premium"></div></div>
                <div class="panel chart-card"><div class="chart-head"><div><span>OPERAÇÃO</span><h2>Frota</h2></div><strong>{{ $totalMotos }} motos</strong></div><div id="chartOperacao" class="donut-premium"></div></div>
            </div>
            <div class="panel chart-wide-panel"><div class="chart-head"><div><span>EVOLUÇÃO</span><h2>Receita recebida - últimos 30 dias</h2></div><strong>{{ \App\Support\Locx::moeda(array_sum($recebidos30)) }}</strong></div><div id="chartReceb30" class="chart-bars chart-wide clean"></div></div>
            <div class="cards">
                <div class="metric"><span>Total de motos</span><strong>{{ $totalMotos }}</strong></div>
                <div class="metric ok"><span>Disponíveis</span><strong>{{ $motosDisponiveis }}</strong></div>
                <div class="metric warn"><span>Alugadas</span><strong>{{ $motosAlugadas }}</strong></div>
                <div class="metric danger"><span>Manutenção</span><strong>{{ $motosManutencao }}</strong></div>
            </div>
            <div class="grid side dashboard-tables">
                <div class="panel"><h2>Vencimentos próximos</h2><div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr>
                    @foreach ($vencimentosProximos as $item)<tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa ?? '-' }}</td><td>{{ $item->vencimento->format('d/m/Y') }}</td><td>{{ \App\Support\Locx::moeda($item->valor_principal) }}</td><td>{!! \App\Support\Locx::status($item->status) !!}</td></tr>@endforeach
                </table></div></div>
                <div class="panel"><h2>Inadimplência - Top clientes</h2><div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Dias</th><th>Saldo</th></tr>
                    @foreach ($topInadimplentes as $item)<tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa ?? '-' }}</td><td>{{ app(\App\Services\CobrancaCalculator::class)->diasAtrasoAteDomingo($item->vencimento) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_atualizado - $item->valor_pago) }}</td></tr>@endforeach
                </table></div></div>
            </div>
            <div class="panel"><h2>Módulos do sistema</h2><div class="module-grid">
                @foreach (['crm' => 'Relacionamento e follow-up', 'clientes' => 'Cadastro completo e documentos', 'motos' => 'Frota, status e lojas', 'contratos' => 'Locação e histórico', 'financeiro' => 'Recebimentos e pagamentos', 'cobrancas' => 'WhatsApp e PIX', 'inadimplencia' => 'Juros e bloqueios', 'relatorios' => 'Indicadores gerenciais', 'usuarios' => 'Perfis e permissões'] as $modulo => $descricao)
                    <a class="module-card" href="{{ route('locx.index', ['page' => $modulo]) }}"><i>{!! \App\Support\Locx::icon($modulo) !!}</i><div><strong>{{ $pages[$modulo] }}</strong><br><small>{{ $descricao }}</small></div></a>
                @endforeach
            </div></div>
            <script>
                window.addEventListener('load',()=>{locxDonutPremium('chartReceita',[{label:'Recebido',value:@json(round($recebidoMes)),color:'#16a34a'},{label:'A receber',value:@json(round($aReceber)),color:'#2563eb'},{label:'Em atraso',value:@json(round($atraso)),color:'#ef4444'}],'R$');locxDonutPremium('chartStatus',[{label:'Pagas',value:@json($cobrancasStatus['pagas']),color:'#16a34a'},{label:'Abertas',value:@json($cobrancasStatus['abertas']),color:'#2563eb'},{label:'Parciais',value:@json($cobrancasStatus['parciais']),color:'#f59e0b'},{label:'Atrasadas',value:@json($cobrancasStatus['atrasadas']),color:'#ef4444'}]);locxMiniBarsPremium('chartRecebidoLojas',@json($lojaLabels),@json($lojaRecebido),'R$');locxDonutPremium('chartOperacao',[{label:'Alugadas',value:@json($motosAlugadas),color:'#2563eb'},{label:'Disponíveis',value:@json($motosDisponiveis),color:'#16a34a'},{label:'Manutenção',value:@json($motosManutencao),color:'#f59e0b'},{label:'Outras',value:@json(max(0,$totalMotos-$motosAlugadas-$motosDisponiveis-$motosManutencao)),color:'#64748b'}]);locxBars('chartReceb30',@json($labels30),@json($recebidos30));});
            </script>

        @elseif ($page === 'crm')
            @include('locx.partials.crm')

        @elseif ($page === 'clientes')
            <div class="grid side">
                <div class="panel"><h2>{{ $clienteEdit ? 'Editar' : 'Novo' }} Cliente</h2>
                    <form method="post" action="{{ route('locx.clientes.salvar') }}" enctype="multipart/form-data" class="form-grid">@csrf
                        <input type="hidden" name="id" value="{{ $clienteEdit?->id }}">
                        <label>Loja<select name="loja_id"><option value="">Selecione</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$clienteEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
                        <label>Nome<input name="nome" required value="{{ old('nome',$clienteEdit?->nome) }}"></label>
                        <label>CPF<input name="cpf" value="{{ old('cpf',$clienteEdit?->cpf) }}"></label>
                        <label>RG<input name="rg" value="{{ old('rg',$clienteEdit?->rg) }}"></label>
                        <label>CNH<input name="cnh" value="{{ old('cnh',$clienteEdit?->cnh) }}"></label>
                        <label>Status<select name="status">@foreach(['ativo','inadimplente','bloqueado','encerrado'] as $status)<option @selected(old('status',$clienteEdit?->status ?? 'ativo')===$status)>{{ $status }}</option>@endforeach</select></label>
                        <label>Telefone<input name="telefone" value="{{ old('telefone',$clienteEdit?->telefone) }}"></label>
                        <label>WhatsApp<input name="whatsapp" value="{{ old('whatsapp',$clienteEdit?->whatsapp) }}"></label>
                        <label>E-mail<input type="email" name="email" value="{{ old('email',$clienteEdit?->email) }}"></label>
                        <label class="span-3">Endereço<textarea name="endereco">{{ old('endereco',$clienteEdit?->endereco) }}</textarea></label>
                        <label>Foto Cliente<input type="file" name="foto_cliente"></label><label>Documento<input type="file" name="foto_documento"></label><label>Comprovante residência<input type="file" name="comprovante_residencia"></label>
                        <div class="span-3"><button type="submit">Salvar Cliente</button></div>
                    </form>
                </div>
                <div class="panel"><h2>Clientes Cadastrados</h2><div class="table-wrap"><table><tr><th>Nome</th><th>CPF</th><th>WhatsApp</th><th>Status</th><th>Ações</th></tr>
                    @foreach($clientes as $cliente)<tr><td>{{ $cliente->nome }}</td><td>{{ $cliente->cpf }}</td><td>{{ $cliente->whatsapp }}</td><td>{!! \App\Support\Locx::status($cliente->status) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'clientes','edit'=>$cliente->id]) }}">Editar</a> <a class="btn secondary" href="{{ route('locx.index',['page'=>'crm','cliente'=>$cliente->id]) }}">CRM</a></td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif ($page === 'motos')
            <div class="grid side">
                <div class="panel"><h2>{{ $motoEdit ? 'Editar' : 'Nova' }} Motocicleta</h2><form method="post" action="{{ route('locx.motos.salvar') }}" class="form-grid">@csrf
                    <input type="hidden" name="id" value="{{ $motoEdit?->id }}">
                    <label>Loja<select name="loja_id" required>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$motoEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
                    <label>Marca<input name="marca" value="{{ old('marca',$motoEdit?->marca) }}"></label><label>Modelo<input name="modelo" required value="{{ old('modelo',$motoEdit?->modelo) }}"></label>
                    <label>Ano<input type="number" name="ano" value="{{ old('ano',$motoEdit?->ano) }}"></label><label>Placa<input name="placa" value="{{ old('placa',$motoEdit?->placa) }}"></label><label>Renavam<input name="renavam" value="{{ old('renavam',$motoEdit?->renavam) }}"></label>
                    <label>Chassi<input name="chassi" value="{{ old('chassi',$motoEdit?->chassi) }}"></label><label>Data aquisição<input type="date" name="data_aquisicao" value="{{ old('data_aquisicao',$motoEdit?->data_aquisicao?->format('Y-m-d')) }}"></label>
                    <label>Status<select name="status_operacional">@foreach(['disponivel','alugada','manutencao','recuperacao','encerrada'] as $status)<option value="{{ $status }}" @selected(old('status_operacional',$motoEdit?->status_operacional ?? 'disponivel')===$status)>{{ $status }}</option>@endforeach</select></label>
                    <label>Seguro<input name="seguro" value="{{ old('seguro',$motoEdit?->seguro) }}"></label><label>Rastreador<input name="rastreador" value="{{ old('rastreador',$motoEdit?->rastreador) }}"></label><div class="span-3"><button type="submit">Salvar Moto</button></div>
                </form></div>
                <div class="panel"><h2>Frota Cadastrada</h2><div class="table-wrap"><table><tr><th>Loja</th><th>Placa</th><th>Modelo</th><th>Ano</th><th>Status</th><th>Ações</th></tr>
                    @foreach($motos as $moto)<tr><td>{{ $moto->loja?->nome }}</td><td>{{ $moto->placa }}</td><td>{{ $moto->modelo }}</td><td>{{ $moto->ano }}</td><td>{!! \App\Support\Locx::status($moto->status_operacional) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'motos','edit'=>$moto->id]) }}">Editar</a></td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif ($page === 'contratos')
            <div class="grid side">
                <div class="panel"><h2>Novo Contrato</h2><form method="post" action="{{ route('locx.contratos.salvar') }}" class="form-grid">@csrf
                    <label>Cliente<select name="cliente_id">@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach</select></label>
                    <label>Moto<select name="motocicleta_id">@foreach($motos as $moto)<option value="{{ $moto->id }}">{{ $moto->placa }} - {{ $moto->modelo }}</option>@endforeach</select></label>
                    <label>Loja<select name="loja_id">@foreach($lojas as $loja)<option value="{{ $loja->id }}">{{ $loja->nome }}</option>@endforeach</select></label>
                    <label>Data início<input type="date" name="data_inicio" value="{{ old('data_inicio',today()->format('Y-m-d')) }}"></label><label>Valor contratado<input type="number" step="0.01" name="valor_contratado" value="{{ old('valor_contratado','500.00') }}"></label>
                    <label>Forma<select name="forma_cobranca"><option>semanal</option><option>quinzenal</option><option>mensal</option></select></label><label>Status<select name="status"><option>ativo</option><option>suspenso</option><option>encerrado</option></select></label>
                    <label>Próxima cobrança<input type="date" name="proxima_cobranca_em" value="{{ old('proxima_cobranca_em', today()->format('Y-m-d')) }}"></label>
                    <label><input type="checkbox" name="cobranca_automatica" value="1" @checked(old('cobranca_automatica', true))> Cobrança automática</label>
                    <div class="span-3"><button type="submit">Criar Contrato</button></div>
                </form></div>
                <div class="panel"><h2>Contratos</h2><div class="table-wrap"><table><tr><th>ID</th><th>Cliente</th><th>Moto</th><th>Loja</th><th>Valor</th><th>Recorrência</th><th>Status</th></tr>
                    @foreach($contratos as $contrato)<tr><td>#{{ $contrato->id }}</td><td>{{ $contrato->cliente?->nome }}</td><td>{{ $contrato->motocicleta?->placa }}</td><td>{{ $contrato->loja?->nome }}</td><td>{{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</td><td>{{ $contrato->cobranca_automatica ? $contrato->forma_cobranca : 'manual' }}<br><small>{{ $contrato->proxima_cobranca_em ? 'Próx. '.$contrato->proxima_cobranca_em->format('d/m/Y') : 'sem data' }}</small></td><td>{!! \App\Support\Locx::status($contrato->status) !!}</td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif (in_array($page, ['financeiro','cobrancas','pix'], true))
            <div class="cards"><div class="metric"><span>Total aberto</span><strong>{{ \App\Support\Locx::moeda($financeiroResumo['aberto']) }}</strong></div><div class="metric ok"><span>Pago mês</span><strong>{{ \App\Support\Locx::moeda($financeiroResumo['pagoMes']) }}</strong></div><div class="metric warn"><span>Parciais</span><strong>{{ $financeiroResumo['parciais'] }}</strong></div><div class="metric danger"><span>Atrasadas</span><strong>{{ $financeiroResumo['atrasadas'] }}</strong></div></div>
            <div class="grid side">
                <div class="panel"><h2>Nova Cobrança</h2><form method="post" action="{{ route('locx.cobrancas.salvar') }}" class="form-grid">@csrf
                    <label class="span-2">Contrato<select name="contrato_id">@foreach($contratos as $contrato)<option value="{{ $contrato->id }}">#{{ $contrato->id }} - {{ $contrato->cliente?->nome }} / {{ $contrato->motocicleta?->placa }} - {{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</option>@endforeach</select></label>
                    <label>Vencimento<input type="date" name="vencimento" value="{{ today()->format('Y-m-d') }}"></label><label>Valor<input type="number" step="0.01" name="valor_principal" value="500.00"></label><div class="span-3"><button type="submit">Gerar Cobrança + PIX</button></div>
                </form><hr><h2>Registrar Pagamento</h2><form method="post" action="{{ route('locx.pagamentos.salvar') }}" class="form-grid">@csrf
                    <label class="span-2">Cobrança<select name="cobranca_id">@foreach($cobrancasAbertas as $cobranca)<option value="{{ $cobranca->id }}">#{{ $cobranca->id }} - {{ $cobranca->cliente?->nome }} - {{ \App\Support\Locx::moeda($cobranca->valor_atualizado-$cobranca->valor_pago) }}</option>@endforeach</select></label>
                    <label>Valor Pago<input type="number" step="0.01" name="valor" required></label><label>Forma<select name="forma"><option>pix</option><option>dinheiro</option><option>cartao</option><option>transferencia</option></select></label><div class="span-3"><button class="btn success" type="submit">Registrar Pagamento</button></div>
                </form></div>
                <div class="panel"><h2>{{ $page === 'pix' ? 'Conciliação PIX' : 'Cobranças' }}</h2>@include('locx.partials.cobrancas_qr')</div>
            </div>

        @elseif ($page === 'inadimplencia')
            <div class="panel"><h2>Motor de Inadimplência - Regra LocX</h2><p>Sem pagamento: 10% simples ao dia sobre o valor principal até domingo. Pagamento parcial: 10% composto ao dia sobre o saldo restante até domingo.</p>
                <div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Dias</th><th>Saldo Original</th><th>Pago</th><th>Atualizado</th><th>WhatsApp</th></tr>
                    @foreach($inadimplentes as $item)
                        @php($atualizado=app(\App\Services\CobrancaCalculator::class)->valorAtualizado($item->valor_principal,$item->valor_pago,$item->vencimento))
                        <tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa }}</td><td>{{ app(\App\Services\CobrancaCalculator::class)->diasAtrasoAteDomingo($item->vencimento) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_principal) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_pago) }}</td><td>{{ \App\Support\Locx::moeda($atualizado) }}</td><td><div class="actions"><a class="btn secondary" target="_blank" href="https://wa.me/55{{ preg_replace('/\D/','',$item->cliente?->whatsapp) }}">Abrir</a><form method="post" action="{{ route('locx.cobrancas.whatsapp',$item) }}">@csrf<button class="btn success" type="submit">Enviar API</button></form></div></td></tr>
                    @endforeach
                </table></div>
            </div>

        @elseif ($page === 'relatorios')
            <div class="grid report-charts"><div class="panel report-chart-card"><h2>Faturamento por loja</h2><div id="chartLojas" class="chart-bars report-bars"></div></div><div class="panel report-chart-card"><h2>Clientes por status</h2><div id="donutClientes" class="donut-box report-donut"></div></div></div>
            <div class="panel"><h2>Relatório financeiro detalhado</h2>@include('locx.partials.cobrancas_qr')</div>
            <script>window.addEventListener('load',()=>{locxBars('chartLojas',@json($relatorioLojas->pluck('label')),@json($relatorioLojas->pluck('value')));locxDonut('donutClientes',[{label:'Ativos',value:@json($clientesStatus['ativo'])},{label:'Inadimplentes',value:@json($clientesStatus['inadimplente'])},{label:'Bloqueados',value:@json($clientesStatus['bloqueado'])},{label:'Encerrados',value:@json($clientesStatus['encerrado'])}]);});</script>

        @elseif ($page === 'lojas')
            <div class="panel"><h2>Controle por Loja</h2><div class="table-wrap"><table><tr><th>Loja</th><th>Motos</th><th>Alugadas</th><th>Disponíveis</th><th>Recebido</th><th>Em atraso</th></tr>
                @foreach($resumoLojas as $item)<tr><td>{{ $item['loja']->nome }}</td><td>{{ $item['motos'] }}</td><td>{{ $item['alugadas'] }}</td><td>{{ $item['disponiveis'] }}</td><td>{{ \App\Support\Locx::moeda($item['recebido']) }}</td><td>{{ \App\Support\Locx::moeda($item['atraso']) }}</td></tr>@endforeach
            </table></div></div>

        @elseif ($page === 'usuarios')
            <div class="grid side">
                <div class="panel"><h2>{{ $usuarioEdit ? 'Editar' : 'Novo' }} Usuário</h2><form method="post" action="{{ route('locx.usuarios.salvar') }}" class="form">@csrf
                    <input type="hidden" name="id" value="{{ $usuarioEdit?->id }}"><label>Nome<input name="nome" required value="{{ old('nome',$usuarioEdit?->nome) }}"></label><label>E-mail<input type="email" name="email" required value="{{ old('email',$usuarioEdit?->email) }}"></label>
                    <label>Senha<input type="password" name="senha" {{ $usuarioEdit ? '' : 'required' }} placeholder="{{ $usuarioEdit ? 'Manter senha atual' : 'Mínimo de 6 caracteres' }}"></label>
                    <label>Perfil<select name="perfil">@foreach(['administrador_geral','diretor','financeiro','gerente_loja','atendente','cobranca'] as $perfil)<option value="{{ $perfil }}" @selected(old('perfil',$usuarioEdit?->perfil ?? 'atendente')===$perfil)>{{ \App\Support\Locx::perfil($perfil) }}</option>@endforeach</select></label>
                    <label>Loja principal<select name="loja_id"><option value="">Central / Todas</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$usuarioEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
                    <label>Status<select name="status"><option value="ativo" @selected(($usuarioEdit?->status ?? 'ativo')==='ativo')>ativo</option><option value="bloqueado" @selected($usuarioEdit?->status==='bloqueado')>bloqueado</option></select></label>
                    <h3>Lojas liberadas</h3><div class="checkgrid">@foreach($lojas as $loja)<label><input type="checkbox" name="lojas[]" value="{{ $loja->id }}" @checked(in_array($loja->id,$lojasSelecionadas,true))> {{ $loja->nome }}</label>@endforeach</div>
                    <h3>Permissões por módulo</h3><div class="perm-table"><table><tr><th>Módulo</th>@foreach($acoes as $acao)<th>{{ $acao }}</th>@endforeach</tr>
                        @foreach($pages as $modulo=>$nome)<tr><td>{{ $nome }}</td>@foreach($acoes as $acaoKey=>$acao)<td><input type="checkbox" name="perms[{{ $modulo }}][{{ $acaoKey }}]" value="1" @checked(!empty($permissoesSelecionadas[$modulo][$acaoKey]))></td>@endforeach</tr>@endforeach
                    </table></div><br><button type="submit">Salvar Usuário</button>
                </form></div>
                <div class="panel"><h2>Usuários</h2><div class="table-wrap"><table><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Loja principal</th><th>Status</th><th>Ações</th></tr>
                    @foreach($usuarios as $usuario)<tr><td>{{ $usuario->nome }}</td><td>{{ $usuario->email }}</td><td>{{ \App\Support\Locx::perfil($usuario->perfil) }}</td><td>{{ $usuario->loja?->nome ?? 'Todas / Central' }}</td><td>{!! \App\Support\Locx::status($usuario->status) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'usuarios','edit'=>$usuario->id]) }}">Editar</a></td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif ($page === 'pagbank')
            <div class="grid side">
                <div class="panel"><h2>Configuração PagBank / PIX</h2><form method="post" action="{{ route('locx.pagbank.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($pagbankConfig->modo==='demo')>demo</option><option value="api" @selected($pagbankConfig->modo==='api')>api oficial</option></select></label>
                    <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($pagbankConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($pagbankConfig->ambiente==='producao')>produção</option></select></label>
                    <label>Ativo<select name="ativo"><option value="1" @selected($pagbankConfig->ativo)>sim</option><option value="0" @selected(!$pagbankConfig->ativo)>não</option></select></label>
                    <label>Client ID<input name="client_id" value="{{ $pagbankConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $pagbankConfig->client_secret }}"></label>
                    <label class="span-3">Access Token PagBank<input type="password" name="access_token" value="{{ $pagbankConfig->access_token }}"></label><label class="span-2">URL Webhook<input name="webhook_url" value="{{ $pagbankConfig->webhook_url ?: route('locx.webhook-pagbank') }}"></label><label>Referência<input name="merchant_reference" value="{{ $pagbankConfig->merchant_reference ?: 'LOCX' }}"></label>
                    <div class="span-3"><button name="acao" value="salvar">Salvar PagBank</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                </form></div>
                <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $pagbankConfig->webhook_url ?: route('locx.webhook-pagbank') }}</code></p><p><strong>Ambiente:</strong> {{ $pagbankConfig->ambiente }} · <strong>Modo:</strong> {{ $pagbankConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ $pixGatewayConfig->gateway === 'asaas' ? 'Asaas' : 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="pagbank"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>Use <strong>demo</strong> para testar sem credenciais.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_pagbank.html') }}" target="_blank">Abrir manual PagBank</a></p></div>
            </div>

        @elseif ($page === 'asaas')
            <div class="grid side">
                <div class="panel"><h2>Configuração Asaas / PIX</h2><form method="post" action="{{ route('locx.asaas.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($asaasConfig->modo==='demo')>demo</option><option value="api" @selected($asaasConfig->modo==='api')>api oficial</option></select></label>
                    <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($asaasConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($asaasConfig->ambiente==='producao')>produção</option></select></label>
                    <label>Ativo<select name="ativo"><option value="1" @selected($asaasConfig->ativo)>sim</option><option value="0" @selected(!$asaasConfig->ativo)>não</option></select></label>
                    <label class="span-3">API Key Asaas<input type="password" name="api_key" value="" placeholder="{{ $asaasConfig->api_key ? 'Chave salva - deixe vazio para manter' : 'Cole a API Key do Asaas' }}"></label>
                    <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $asaasConfig->webhook_url ?: route('locx.webhook-asaas') }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $asaasConfig->webhook_token ?: 'locx_asaas_webhook_token' }}"></label>
                    <div class="span-3"><button name="acao" value="salvar">Salvar Asaas</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                </form></div>
                <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $asaasConfig->webhook_url ?: route('locx.webhook-asaas') }}</code></p><p><strong>Ambiente:</strong> {{ $asaasConfig->ambiente }} · <strong>Modo:</strong> {{ $asaasConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ $pixGatewayConfig->gateway === 'asaas' ? 'Asaas' : 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="asaas"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>Cadastre a API Key, configure o webhook no painel Asaas e gere uma cobrança de teste.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_asaas.html') }}" target="_blank">Abrir manual Asaas</a></p></div>
            </div>

        @elseif ($page === 'whatsapp')
            <div class="panel"><h2>WhatsApp Business API</h2><p>Configure a integração oficial da Meta. No modo <strong>demo</strong>, o sistema apenas registra uma simulação e nenhuma mensagem é enviada. Versão da Graph API: <strong>{{ $graphVersion }}</strong>.</p>
                <form method="post" action="{{ route('locx.whatsapp.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($whatsappConfig->modo==='demo')>Demo / Simulado</option><option value="oficial" @selected($whatsappConfig->modo==='oficial')>Oficial - Meta Cloud API</option></select></label><label>Status<select name="ativo"><option value="1" @selected($whatsappConfig->ativo)>Ativo</option><option value="0" @selected(!$whatsappConfig->ativo)>Inativo</option></select></label>
                    <label>WABA ID<input name="waba_id" value="{{ $whatsappConfig->waba_id }}" placeholder="ID da conta do WhatsApp Business"></label><label>Phone Number ID<input name="phone_number_id" value="{{ $whatsappConfig->phone_number_id }}"></label><label class="span-3">Access Token permanente<input type="password" name="access_token" value="" placeholder="{{ $whatsappConfig->access_token ? 'Token salvo — deixe vazio para manter' : 'Cole o token permanente da Meta' }}"></label><label>Verify Token<input name="verify_token" value="{{ $whatsappConfig->verify_token ?: 'locx_webhook_token' }}"></label>
                    <label>Template cobrança<input name="template_cobranca" value="{{ $whatsappConfig->template_cobranca }}"></label><label>Idioma do template<input name="template_language" value="{{ $whatsappConfig->template_language ?: 'pt_BR' }}" placeholder="pt_BR"></label><label>Template lembrete<input name="template_lembrete" value="{{ $whatsappConfig->template_lembrete }}"></label><label>Template bloqueio<input name="template_bloqueio" value="{{ $whatsappConfig->template_bloqueio }}"></label><div class="span-3"><button type="submit">Salvar Configuração</button></div>
                </form>
            </div>
            <div class="grid side"><div class="panel"><h2>Testar conexão e template</h2><form method="post" action="{{ route('locx.whatsapp.testar') }}">@csrf<button class="btn success">Validar na Meta</button></form><p>O teste confirma o token, o número, o nome, o idioma e a aprovação do template.</p><p><strong>URL do Webhook:</strong><br><code>{{ route('locx.webhook-whatsapp') }}</code></p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_whatsapp.html') }}" target="_blank">Abrir manual WhatsApp</a></p></div>
                <div class="panel"><h2>Últimos envios</h2><div class="table-wrap"><table><tr><th>Data</th><th>Cliente</th><th>Telefone</th><th>Status</th><th>HTTP</th><th>Detalhe</th></tr>@foreach($whatsappLogs as $log)<tr><td>{{ $log->criado_em?->format('d/m/Y H:i') }}</td><td>{{ $log->cliente?->nome ?? '-' }}</td><td>{{ $log->telefone }}</td><td>{!! \App\Support\Locx::status($log->status) !!}</td><td>{{ $log->http_code ?? '-' }}</td><td title="{{ $log->erro ?: $log->resposta_api }}">{{ \Illuminate\Support\Str::limit($log->erro ?: $log->resposta_api, 90) ?: '-' }}</td></tr>@endforeach</table></div></div>
            </div>

        @elseif ($page === 'configuracoes')
            <div class="panel"><h2>Configurações e Integrações Futuras</h2><div class="module-grid">
                <a class="module-card" href="{{ route('locx.index',['page'=>'pagbank']) }}"><i>{!! \App\Support\Locx::icon('pagbank') !!}</i><div><strong>PagBank</strong><br><small>PIX automático e baixa por webhook</small></div></a>
                <a class="module-card" href="{{ route('locx.index',['page'=>'asaas']) }}"><i>{!! \App\Support\Locx::icon('asaas') !!}</i><div><strong>Asaas</strong><br><small>PIX com cobrança e webhook</small></div></a>
                <a class="module-card" href="{{ route('locx.index',['page'=>'whatsapp']) }}"><i>{!! \App\Support\Locx::icon('whatsapp') !!}</i><div><strong>WhatsApp API</strong><br><small>Mensagens automáticas</small></div></a>
                <div class="module-card"><i>✍</i><div><strong>Assinatura Digital</strong><br><small>Contratos eletrônicos</small></div></div><div class="module-card"><i>⌖</i><div><strong>Rastreadores</strong><br><small>Integração veicular</small></div></div>
            </div></div>
        @endif
    </main>
</div>
<script src="{{ \App\Support\Locx::asset('assets/js/app.js') }}"></script>
</body>
</html>
