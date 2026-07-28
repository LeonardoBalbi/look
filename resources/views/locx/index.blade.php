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
    <div class="brand brand-logo"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.svg') }}" alt="LocX Aluguel de Motos"></div>
    <button type="button" class="mobile-menu-toggle">☰</button>
</header>
<div class="mobile-menu-overlay"></div>
<div class="app">
    <aside class="sidebar" id="sidebarMenu">
        <button type="button" class="mobile-menu-close">Fechar</button>
        <div class="brand brand-logo"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.svg') }}" alt="LocX Aluguel de Motos"></div>
        <div class="nav-title">Navegação</div>
        @php
            $moduleDetails = [
            'dashboard' => 'Painel executivo com indicadores de receita, cobranças, inadimplência, frota e operação por loja.',
            'reservas' => 'Controle pedidos de reserva, disponibilidade da frota, previsão de retirada e oportunidades antes de virar contrato.',
            'crm' => 'Centralize conversas, notas internas, tarefas de follow-up, histórico do cliente e acompanhamento comercial.',
            'clientes' => 'Cadastre dados pessoais, documentos, contatos, portal do cliente, status e vínculos com lojas.',
            'motos' => 'Gerencie placa, modelo, cor, ano, loja, status operacional, manutenção e histórico da frota.',
            'contratos' => 'Crie contratos de locação, acompanhe vigência, valores, recorrência de cobrança e vínculo com moto e cliente.',
            'manutencao' => 'Registre ordens de serviço, vistorias, custos, previsão de conclusão e indisponibilidade da moto.',
            'estoque' => 'Controle peças, entradas, saídas e custos ligados à operação e manutenção.',
            'multas' => 'Acompanhe infrações, vencimentos, responsáveis, valores e repasses ao cliente.',
            'financeiro' => 'Veja recebimentos, baixas, pagamentos, saldos em aberto e movimentações financeiras.',
            'contas' => 'Organize contas bancárias, lançamentos, conciliação e visão de caixa.',
            'cobrancas' => 'Gere cobranças, Pix, QR Code, campanhas de cobrança e acompanhe status de pagamento.',
            'inadimplencia' => 'Priorize clientes em atraso, calcule saldo atualizado, juros, acordos e ações de recuperação.',
            'bancos' => 'Configure gateways Pix, credenciais, webhooks e o provedor principal de recebimento.',
            'pagbank' => 'Configure o PagBank para gerar Pix, webhooks e conciliação de pagamentos.',
            'asaas' => 'Configure o Asaas para emissão de cobranças Pix, API Key e webhook.',
            'sicoob' => 'Configure o Sicoob para Pix com credenciais, certificado, chave Pix e webhook.',
            'itau' => 'Configure o Itaú Pix com OAuth, certificado, chave privada e webhook.',
            'whatsapp' => 'Configure envios automáticos, cobranças, testes de integração e histórico de mensagens.',
            'telegram' => 'Configure bots de aviso e atendimento, vínculo de clientes e logs de envio.',
            'documentos' => 'Centralize anexos, contratos, comprovantes, documentos do cliente e controle de assinatura.',
            'relatorios' => 'Analise indicadores, faturamento por loja, clientes, frota, cobranças e resultados operacionais.',
            'lojas' => 'Compare unidades, motos, recebidos, atrasos e desempenho por loja.',
            'usuarios' => 'Administre usuários, perfis, permissões por módulo e lojas liberadas.',
            'configuracoes' => 'Acesse integrações, canais, gateways e parâmetros operacionais do sistema.',
            ];
        @endphp
        <nav class="menu">
            @foreach (\App\Support\Locx::MENU_GRUPOS as $grupo => $modulos)
                @php
                    $itens = collect($modulos)
                        ->filter(fn ($key) => isset($pages[$key]) && $user->pode($key))
                        ->values();
                @endphp
                @if ($itens->isNotEmpty())
                    <details class="menu-group" {{ $itens->contains($page) ? 'open' : '' }}>
                        <summary>
                            <span>{{ $grupo }}</span>
                            <small>{{ $itens->count() }}</small>
                        </summary>
                        <div class="menu-group-items">
                            @foreach ($itens as $key)
                                <a class="sidebar-menu-info {{ $page === $key ? 'active' : '' }}" href="{{ route('locx.index', ['page' => $key]) }}" data-menu-info="{{ $moduleDetails[$key] ?? $pages[$key] }}" title="{{ $moduleDetails[$key] ?? $pages[$key] }}" aria-label="{{ $pages[$key] }}. {{ $moduleDetails[$key] ?? $pages[$key] }}">
                                    <span>{!! \App\Support\Locx::icon($key) !!}</span>{{ $pages[$key] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <div class="empresa-logo"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.svg') }}" alt="LocX Aluguel de Motos"></div>
            Logado como: <strong>{{ $user->nome }}</strong><br>
            {{ \App\Support\Locx::perfil($user->perfil) }}<br><br>
            <form method="post" action="{{ route('locx.logout') }}">@csrf<button class="btn secondary" type="submit">Sair</button></form>
        </div>
    </aside>
    <main class="main">
        @php
            $pageSubtitles = [
                'dashboard' => 'Indicadores essenciais da operação, financeiro e cobrança em um só lugar.',
                'clientes' => 'Cadastro, portal do cliente e relacionamento reunidos para consulta rápida.',
                'motos' => 'Controle da frota por loja, placa e status operacional.',
                'contratos' => 'Locações ativas, recorrência de cobrança e documentos de contrato.',
                'cobrancas' => 'Geração de PIX, canais de cobrança e acompanhamento de status.',
                'inadimplencia' => 'Clientes em atraso, saldos atualizados e prioridades de recuperação.',
                'crm' => 'Atendimento, histórico e próximas ações com contexto do cliente.',
                'bancos' => 'Configuração dos gateways PIX e status de integração.',
                'whatsapp' => 'Configuração da integração oficial e histórico de envios.',
                'telegram' => 'Bots de aviso, atendimento e logs de mensagens.',
                'configuracoes' => 'Acessos rápidos para integrações e parâmetros operacionais.',
            ];

            $quickActions = [
                'clientes' => [['label' => 'Novo cliente', 'href' => route('locx.index', ['page' => 'clientes'])]],
                'motos' => [['label' => 'Nova moto', 'href' => route('locx.index', ['page' => 'motos'])]],
                'contratos' => [['label' => 'Novo contrato', 'href' => route('locx.index', ['page' => 'contratos'])]],
                'cobrancas' => [['label' => 'Nova cobrança', 'href' => '#nova-cobranca']],
                'crm' => [['label' => 'Abrir clientes', 'href' => route('locx.index', ['page' => 'clientes'])]],
                'whatsapp' => [['label' => 'Testar integração', 'href' => '#testar-integracao']],
                'telegram' => [['label' => 'Testar bot', 'href' => '#testar-bot']],
            ][$page] ?? [];
        @endphp
        <header class="topbar">
            <div class="topbar-title">
                <span>{{ $page === 'dashboard' ? 'Painel executivo' : 'Área operacional' }}</span>
                <h1>{{ $pages[$page] }}</h1>
                <p>{{ $pageSubtitles[$page] ?? 'Sistema web LocX com gestão multiunidades, operação, financeiro e cobrança.' }}</p>
            </div>
            <div class="toolbar">
                @foreach ($quickActions as $action)
                    <a class="btn" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                @endforeach
                <a class="btn secondary" href="{{ route('locx.index', ['page' => 'dashboard']) }}">Visão Geral</a>
            </div>
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
                <div class="panel"><h2>Vencimentos próximos</h2><div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
                    @foreach ($vencimentosProximos as $item)<tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa ?? '-' }}</td><td>{{ $item->vencimento->format('d/m/Y') }}</td><td>{{ \App\Support\Locx::moeda($item->valor_principal) }}</td><td>{!! \App\Support\Locx::status($item->status) !!}</td></tr>@endforeach
                </table></div></div>
                <div class="panel"><h2>Inadimplência - Top clientes</h2><div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Dias</th><th>Saldo</th></tr>
                    @foreach ($topInadimplentes as $item)<tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa ?? '-' }}</td><td>{{ app(\App\Services\CobrancaCalculator::class)->diasAtrasoAteDomingo($item->vencimento) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_atualizado - $item->valor_pago) }}</td></tr>@endforeach
                </table></div></div>
            </div>
            <div class="panel"><h2>Módulos do sistema</h2><div class="module-grid">
                @foreach (['reservas' => 'Disponibilidade e pré-locação', 'crm' => 'Relacionamento e follow-up', 'clientes' => 'Cadastro completo e documentos', 'motos' => 'Frota, status e lojas', 'contratos' => 'Locação e histórico', 'manutencao' => 'Ordens de serviço da frota', 'estoque' => 'Peças, entradas e saídas', 'multas' => 'Infrações e repasses', 'financeiro' => 'Recebimentos, baixas e caixa', 'cobrancas' => 'Gerar, enviar e acompanhar', 'inadimplencia' => 'Juros, acordos e bloqueios', 'contas' => 'Bancos, despesas e conciliação', 'documentos' => 'Anexos e assinatura digital', 'relatorios' => 'Indicadores e DRE', 'lojas' => 'Resultado por unidade', 'usuarios' => 'Perfis e permissões'] as $modulo => $descricao)
                    <a class="module-card module-card-info" href="{{ route('locx.index', ['page' => $modulo]) }}" data-module-info="{{ $moduleDetails[$modulo] ?? $descricao }}" aria-label="{{ $pages[$modulo] }}. {{ $moduleDetails[$modulo] ?? $descricao }}"><i>{!! \App\Support\Locx::icon($modulo) !!}</i><div><strong>{{ $pages[$modulo] }}</strong><br><small>{{ $descricao }}</small></div></a>
                @endforeach
            </div></div>
            <script>
                window.addEventListener('load',()=>{locxDonutPremium('chartReceita',[{label:'Recebido',value:@json(round($recebidoMes)),color:'#16a34a'},{label:'A receber',value:@json(round($aReceber)),color:'#2563eb'},{label:'Em atraso',value:@json(round($atraso)),color:'#ef4444'}],'R$');locxDonutPremium('chartStatus',[{label:'Pagas',value:@json($cobrancasStatus['pagas']),color:'#16a34a'},{label:'Abertas',value:@json($cobrancasStatus['abertas']),color:'#2563eb'},{label:'Parciais',value:@json($cobrancasStatus['parciais']),color:'#f59e0b'},{label:'Atrasadas',value:@json($cobrancasStatus['atrasadas']),color:'#ef4444'}]);locxMiniBarsPremium('chartRecebidoLojas',@json($lojaLabels),@json($lojaRecebido),'R$');locxDonutPremium('chartOperacao',[{label:'Alugadas',value:@json($motosAlugadas),color:'#2563eb'},{label:'Disponíveis',value:@json($motosDisponiveis),color:'#16a34a'},{label:'Manutenção',value:@json($motosManutencao),color:'#f59e0b'},{label:'Outras',value:@json(max(0,$totalMotos-$motosAlugadas-$motosDisponiveis-$motosManutencao)),color:'#64748b'}]);locxBars('chartReceb30',@json($labels30),@json($recebidos30));});
            </script>

        @elseif (in_array($page, ['reservas','contas','documentos'], true))
            @include('locx.partials.look_modulo')

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
                        <label>Notificações Telegram<select name="telegram_notificacoes"><option value="1" @selected(old('telegram_notificacoes',$clienteEdit?->telegram_notificacoes ?? true))>permitidas</option><option value="0" @selected(!old('telegram_notificacoes',$clienteEdit?->telegram_notificacoes ?? true))>desativadas</option></select></label>
                        <label>Portal do cliente<select name="portal_ativo"><option value="0" @selected(!old('portal_ativo',$clienteEdit?->portal_ativo ?? false))>bloqueado</option><option value="1" @selected(old('portal_ativo',$clienteEdit?->portal_ativo ?? false))>liberado</option></select></label>
                        <label class="span-2">Senha do portal<input type="password" name="senha_portal" placeholder="{{ $clienteEdit?->senha ? 'Preencha somente para trocar' : 'Mínimo 6 caracteres' }}"></label>
                        <label class="span-3">Endereço<textarea name="endereco">{{ old('endereco',$clienteEdit?->endereco) }}</textarea></label>
                        <label>Foto Cliente<input type="file" name="foto_cliente"></label><label>Documento<input type="file" name="foto_documento"></label><label>Comprovante residência<input type="file" name="comprovante_residencia"></label>
                        <div class="span-3"><button type="submit">Salvar Cliente</button></div>
                    </form>
                </div>
                <div class="panel"><h2>Clientes Cadastrados</h2><div class="table-wrap"><table><tr><th>Nome</th><th>CPF</th><th>WhatsApp</th><th>Telegram</th><th>Status</th><th>Portal</th><th>Ações</th></tr>
                    @foreach($clientes as $cliente)<tr><td>{{ $cliente->nome }}</td><td>{{ $cliente->cpf }}</td><td>{{ $cliente->whatsapp }}</td><td>{!! \App\Support\Locx::status($cliente->telegram_chat_id ? 'vinculado' : 'não vinculado') !!}<br><small>{{ $cliente->telegram_username ? '@'.$cliente->telegram_username : '' }}</small></td><td>{!! \App\Support\Locx::status($cliente->status) !!}</td><td>{!! \App\Support\Locx::status($cliente->portal_ativo ? 'ativo' : 'bloqueado') !!}<br><small>{{ $cliente->ultimo_login_em ? 'Último acesso '.$cliente->ultimo_login_em->format('d/m/Y H:i') : 'sem acesso' }}</small></td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'clientes','edit'=>$cliente->id]) }}">Editar</a> <a class="btn secondary" href="{{ route('locx.index',['page'=>'crm','cliente'=>$cliente->id]) }}">CRM</a></td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif ($page === 'motos')
            <div class="grid side">
                <div class="panel"><h2>{{ $motoEdit ? 'Editar' : 'Nova' }} Motocicleta</h2><form method="post" action="{{ route('locx.motos.salvar') }}" class="form-grid">@csrf
                    <input type="hidden" name="id" value="{{ $motoEdit?->id }}">
                    <label>Loja<select name="loja_id" required>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$motoEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
                    <label>Marca<input name="marca" list="marcas-moto" value="{{ old('marca',$motoEdit?->marca_nome ?? $motoEdit?->marca) }}"></label><label>Modelo<input name="modelo" list="modelos-moto" required value="{{ old('modelo',$motoEdit?->modelo_nome ?? $motoEdit?->modelo) }}"></label>
                    <datalist id="marcas-moto">@foreach($marcasMoto as $marca)<option value="{{ $marca }}"></option>@endforeach</datalist>
                    <datalist id="modelos-moto">@foreach($modelosMoto as $modelo)<option value="{{ $modelo }}"></option>@endforeach</datalist>
                    <label>Ano<input type="number" name="ano" value="{{ old('ano',$motoEdit?->ano) }}"></label><label>Placa<input name="placa" value="{{ old('placa',$motoEdit?->placa) }}"></label><label>Cor<input name="cor" list="cores-moto" value="{{ old('cor',$motoEdit?->cor) }}"></label>
                    <datalist id="cores-moto">@foreach($coresMoto as $cor)<option value="{{ $cor }}"></option>@endforeach</datalist>
                    <label>Renavam<input name="renavam" value="{{ old('renavam',$motoEdit?->renavam) }}"></label>
                    <label>Chassi<input name="chassi" value="{{ old('chassi',$motoEdit?->chassi) }}"></label><label>Data aquisição<input type="date" name="data_aquisicao" value="{{ old('data_aquisicao',$motoEdit?->data_aquisicao?->format('Y-m-d')) }}"></label>
                    <label>Status<select name="status_operacional">@foreach(['disponivel','alugada','manutencao','recuperacao','encerrada'] as $status)<option value="{{ $status }}" @selected(old('status_operacional',$motoEdit?->status_operacional ?? 'disponivel')===$status)>{{ $status }}</option>@endforeach</select></label>
                    <label>Seguro<input name="seguro" value="{{ old('seguro',$motoEdit?->seguro) }}"></label><label>Rastreador<input name="rastreador" value="{{ old('rastreador',$motoEdit?->rastreador) }}"></label><div class="span-3"><button type="submit">Salvar Moto</button></div>
                </form></div>
                <div class="panel"><h2>Frota Cadastrada</h2><div class="table-wrap"><table><tr><th>Loja</th><th>Placa</th><th>Modelo</th><th>Cor</th><th>Ano</th><th>Status</th><th>Ações</th></tr>
                    @foreach($motos as $moto)<tr><td>{{ $moto->loja?->nome }}</td><td>{{ $moto->placa }}</td><td>{{ $moto->modelo_nome }}</td><td>{{ $moto->cor ?: '-' }}</td><td>{{ $moto->ano }}</td><td>{!! \App\Support\Locx::status($moto->status_operacional) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'motos','edit'=>$moto->id]) }}">Editar</a></td></tr>@endforeach
                </table></div></div>
            </div>

        @elseif ($page === 'contratos')
            <div class="grid side">
                <div class="panel"><h2>Novo Contrato</h2><form method="post" action="{{ route('locx.contratos.salvar') }}" class="form-grid">@csrf
                    <label>Cliente<select name="cliente_id">@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach</select></label>
                    <label>Moto<select name="motocicleta_id">@foreach($motos as $moto)<option value="{{ $moto->id }}">{{ $moto->placa }} - {{ $moto->modelo_nome }}</option>@endforeach</select></label>
                    <label>Loja<select name="loja_id">@foreach($lojas as $loja)<option value="{{ $loja->id }}">{{ $loja->nome }}</option>@endforeach</select></label>
                    <label>Data início<input type="date" name="data_inicio" value="{{ old('data_inicio',today()->format('Y-m-d')) }}"></label><label>Valor contratado<input type="number" step="0.01" name="valor_contratado" value="{{ old('valor_contratado','500.00') }}"></label>
                    <label>Forma<select name="forma_cobranca"><option>semanal</option><option>quinzenal</option><option>mensal</option></select></label><label>Status<select name="status"><option>ativo</option><option>suspenso</option><option>encerrado</option></select></label>
                    <label>Próxima cobrança<input type="date" name="proxima_cobranca_em" value="{{ old('proxima_cobranca_em', today()->format('Y-m-d')) }}"></label>
                    <label><input type="checkbox" name="cobranca_automatica" value="1" @checked(old('cobranca_automatica', true))> Cobrança automática</label>
                    <div class="span-3"><button type="submit">Criar Contrato</button></div>
                </form></div>
                <div class="panel"><h2>Contratos</h2><div class="table-wrap"><table><tr><th>ID</th><th>Cliente</th><th>Moto</th><th>Loja</th><th>Valor</th><th>Recorrência</th><th>Status</th><th>Ações</th></tr>
                    @foreach($contratos as $contrato)<tr><td>#{{ $contrato->id }}</td><td>{{ $contrato->cliente?->nome }}</td><td>{{ $contrato->motocicleta?->placa }}</td><td>{{ $contrato->loja?->nome }}</td><td>{{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</td><td>{{ $contrato->cobranca_automatica ? $contrato->forma_cobranca : 'manual' }}<br><small>{{ $contrato->proxima_cobranca_em ? 'Próx. '.$contrato->proxima_cobranca_em->format('d/m/Y') : 'sem data' }}</small></td><td>{!! \App\Support\Locx::status($contrato->status) !!}</td><td><button type="button" class="btn contract-generate-btn" data-contract-open="contrato-preview-{{ $contrato->id }}">Gerar contrato</button></td></tr>@endforeach
                </table></div></div>
            </div>
            @foreach($contratos as $contrato)
                @php($clienteContrato = $contrato->cliente)
                @php($motoContrato = $contrato->motocicleta)
                @php($lojaContrato = $contrato->loja)
                <div class="contract-modal" id="contrato-preview-{{ $contrato->id }}" aria-hidden="true">
                    <div class="contract-modal-backdrop" data-contract-close></div>
                    <div class="contract-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="contrato-title-{{ $contrato->id }}">
                        <div class="contract-modal-head">
                            <div><h2 id="contrato-title-{{ $contrato->id }}">Contrato de Locação</h2><p>Pré-visualização para impressão ou PDF</p></div>
                            <button type="button" class="contract-modal-close" data-contract-close aria-label="Fechar">&times;</button>
                        </div>
                        <div class="contract-document" id="contrato-doc-{{ $contrato->id }}">
                            <div class="contract-brand"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.svg') }}" alt="LocX Aluguel de Motos"></div>
                            <h1>CONTRATO DE LOCAÇÃO DE MOTOCICLETA</h1>
                            <h3>Contrato nº #{{ $contrato->id }}</h3>
                            <p>Pelo presente instrumento particular, as partes abaixo identificadas ajustam a locação da motocicleta descrita neste documento, conforme as condições comerciais cadastradas no sistema LocX.</p>
                            <h2>1. CONTRATANTE</h2>
                            <p><strong>Nome:</strong> {{ $clienteContrato?->nome ?: '-' }}</p>
                            <p><strong>CPF:</strong> {{ $clienteContrato?->cpf ?: '-' }}</p>
                            <p><strong>RG:</strong> {{ $clienteContrato?->rg ?: '-' }}</p>
                            <p><strong>CNH:</strong> {{ $clienteContrato?->cnh ?: '-' }}</p>
                            <p><strong>Telefone:</strong> {{ $clienteContrato?->telefone ?: '-' }}</p>
                            <p><strong>WhatsApp:</strong> {{ $clienteContrato?->whatsapp ?: '-' }}</p>
                            <p><strong>E-mail:</strong> {{ $clienteContrato?->email ?: '-' }}</p>
                            <p><strong>Endereço:</strong> {{ $clienteContrato?->endereco ?: '-' }}</p>
                            <h2>2. CONTRATADA</h2>
                            <p>LOCX, unidade {{ $lojaContrato?->nome ?: 'Central' }}, responsável pela operação, gestão financeira e acompanhamento da locação.</p>
                            <h2>3. MOTOCICLETA LOCADA</h2>
                            <p><strong>Moto:</strong> {{ $motoContrato?->modelo_nome ?: '-' }}</p>
                            <p><strong>Placa:</strong> {{ $motoContrato?->placa ?: '-' }}</p>
                            <p><strong>Ano:</strong> {{ $motoContrato?->ano ?: '-' }}</p>
                            <p><strong>Renavam:</strong> {{ $motoContrato?->renavam ?: '-' }}</p>
                            <p><strong>Chassi:</strong> {{ $motoContrato?->chassi ?: '-' }}</p>
                            <h2>4. CONDIÇÕES DA LOCAÇÃO</h2>
                            <p><strong>Data de início:</strong> {{ $contrato->data_inicio?->format('d/m/Y') ?: '-' }}</p>
                            <p><strong>Data de fim:</strong> {{ $contrato->data_fim?->format('d/m/Y') ?: 'Indeterminado' }}</p>
                            <p><strong>Valor contratado:</strong> {{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</p>
                            <p><strong>Forma de cobrança:</strong> {{ strtoupper($contrato->forma_cobranca) }}</p>
                            <p><strong>Status:</strong> {{ strtoupper($contrato->status) }}</p>
                            <p><strong>Loja:</strong> {{ $lojaContrato?->nome ?: '-' }}</p>
                            <h2>5. CLÁUSULAS GERAIS</h2>
                            <p>O contratante declara receber a motocicleta em condições de uso, obrigando-se a zelar pelo bem, cumprir os prazos de pagamento e devolver o veículo nas mesmas condições recebidas, salvo desgaste natural.</p>
                            <p>A inadimplência poderá gerar cobrança de encargos, bloqueio operacional e demais medidas administrativas previstas nas regras internas da contratada.</p>
                            <p>Este contrato foi gerado automaticamente pelo sistema LocX com base nos dados cadastrados no módulo de contratos.</p>
                            <p class="contract-date">{{ $lojaContrato?->nome ?: 'LocX' }}, {{ ($contrato->data_inicio ?: today())->translatedFormat('d \d\e F \d\e Y') }}.</p>
                            <div class="contract-signatures"><div><span></span><strong>CONTRATANTE</strong></div><div><span></span><strong>LOCX</strong></div></div>
                        </div>
                        <div class="contract-modal-actions"><button type="button" class="btn secondary" data-contract-close>Fechar</button><button type="button" class="btn success" data-contract-print="contrato-doc-{{ $contrato->id }}">Imprimir / PDF</button></div>
                    </div>
                </div>
            @endforeach

        @elseif ($page === 'manutencao')
            @include('locx.partials.manutencao')

        @elseif ($page === 'estoque')
            @include('locx.partials.estoque')

        @elseif ($page === 'multas')
            @include('locx.partials.multas')

        @elseif (in_array($page, ['financeiro','cobrancas'], true))
            <div class="cards"><div class="metric"><span>Total aberto</span><strong>{{ \App\Support\Locx::moeda($financeiroResumo['aberto']) }}</strong></div><div class="metric ok"><span>Pago mês</span><strong>{{ \App\Support\Locx::moeda($financeiroResumo['pagoMes']) }}</strong></div><div class="metric warn"><span>Parciais</span><strong>{{ $financeiroResumo['parciais'] }}</strong></div><div class="metric danger"><span>Atrasadas</span><strong>{{ $financeiroResumo['atrasadas'] }}</strong></div></div>
            <div class="grid side">
                <div class="panel" id="{{ $page === 'cobrancas' ? 'nova-cobranca' : 'registrar-pagamento' }}">
                    @if ($page === 'cobrancas')
                        <h2>Nova Cobrança</h2>
                        <form method="post" action="{{ route('locx.cobrancas.salvar') }}" class="form-grid">@csrf
                            <label class="span-2">Contrato<select name="contrato_id">@foreach($contratos as $contrato)<option value="{{ $contrato->id }}">#{{ $contrato->id }} - {{ $contrato->cliente?->nome }} / {{ $contrato->motocicleta?->placa }} - {{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</option>@endforeach</select></label>
                            <label>Vencimento<input type="date" name="vencimento" value="{{ today()->format('Y-m-d') }}"></label>
                            <label>Valor<input type="number" step="0.01" name="valor_principal" value="500.00"></label>
                            <div class="span-3"><button type="submit">Gerar cobrança + PIX</button></div>
                        </form>
                    @else
                        <h2>Registrar Pagamento</h2>
                        <form method="post" action="{{ route('locx.pagamentos.salvar') }}" class="form-grid">@csrf
                            <label class="span-2">Cobrança<select name="cobranca_id">@foreach($cobrancasAbertas as $cobranca)<option value="{{ $cobranca->id }}">#{{ $cobranca->id }} - {{ $cobranca->cliente?->nome }} - {{ \App\Support\Locx::moeda($cobranca->valor_atualizado-$cobranca->valor_pago) }}</option>@endforeach</select></label>
                            <label>Valor pago<input type="number" step="0.01" name="valor" required></label>
                            <label>Forma<select name="forma"><option>pix</option><option>dinheiro</option><option>cartao</option><option>transferencia</option></select></label>
                            <div class="span-3"><button class="btn success" type="submit">Registrar pagamento</button></div>
                        </form>
                        <hr>
                        <form method="post" action="{{ route('locx.pix.conciliar') }}" class="toolbar">
                            @csrf
                            <input type="hidden" name="page" value="financeiro">
                            <button class="btn secondary" type="submit">Conciliar PIX</button>
                        </form>
                    @endif
                </div>
                <div class="panel"><h2>{{ $page === 'cobrancas' ? 'Cobranças e envios' : 'Títulos e recebimentos' }}</h2>
                    <div class="billing-tools" data-billing-tools>
                        <label class="billing-search">
                            <span>Buscar</span>
                            <input type="search" placeholder="Cliente, ID, placa, status ou gateway" data-billing-search>
                        </label>
                        <div class="billing-filter-row" role="tablist" aria-label="Filtrar cobranças">
                            <button type="button" class="is-active" data-billing-filter="all">Todas</button>
                            <button type="button" data-billing-filter="overdue">Atrasadas</button>
                            <button type="button" data-billing-filter="today">Vencem hoje</button>
                            <button type="button" data-billing-filter="open">Em aberto</button>
                            <button type="button" data-billing-filter="without-pix">Sem PIX</button>
                            <button type="button" data-billing-filter="with-pix">PIX gerado</button>
                            <button type="button" data-billing-filter="whatsapp-sent">WhatsApp enviado</button>
                            <button type="button" data-billing-filter="telegram-sent">Telegram enviado</button>
                        </div>
                        <small><strong data-billing-visible-count>{{ $cobrancas->count() }}</strong> cobranças visíveis</small>
                    </div>
                    @include('locx.partials.cobrancas_qr')
                </div>
            </div>

            @if ($page === 'cobrancas')
                <section class="campaign-section">
                    <div class="campaign-head">
                        <div><span>CENTRAL MULTICANAL</span><h2>Campanhas de cobrança</h2><p>Envie por WhatsApp, e-mail e Telegram sem duplicar a mesma cobrança dentro da campanha.</p></div>
                        <div class="campaign-kpis"><b>{{ $campanhasResumo['agendadas'] }}</b><small>agendadas</small><b>{{ $campanhasResumo['concluidas'] }}</b><small>concluídas</small><b>{{ $campanhasResumo['falhas'] }}</b><small>com falhas</small></div>
                    </div>
                    <div class="grid side campaign-grid">
                        <div class="panel">
                            <h2>Nova campanha</h2>
                            <form method="post" action="{{ route('locx.cobrancas.campanhas.criar') }}" class="form-grid campaign-form">@csrf
                                <label class="span-2">Nome da campanha<input name="nome" required value="{{ old('nome','Cobranças '.now()->format('d/m/Y H:i')) }}"></label>
                                <label>Público<select name="publico" id="campaignAudience"><option value="todos_abertos">Todas em aberto</option><option value="vence_hoje">Vencem hoje</option><option value="vencidas_7">Vencidas há 7 dias ou mais</option><option value="vencidas_15">Vencidas há 15 dias ou mais</option><option value="vencidas_30">Vencidas há 30 dias ou mais</option><option value="selecionadas">Somente selecionadas abaixo</option></select></label>
                                <label>Estratégia<select name="estrategia"><option value="todos">Enviar em todos os canais marcados</option><option value="prioridade">Tentar canais em prioridade até um funcionar</option></select></label>
                                <label>Agendar para<input type="datetime-local" name="agendado_para" value="{{ old('agendado_para') }}"><small>Deixe vazio para enviar agora.</small></label>
                                <fieldset class="span-3 campaign-channels"><legend>Canais</legend><label><input type="checkbox" name="canais[]" value="whatsapp" checked> WhatsApp</label><label><input type="checkbox" name="canais[]" value="email" checked> E-mail</label><label><input type="checkbox" name="canais[]" value="telegram" checked> Telegram</label></fieldset>
                                <label class="span-3">Mensagem<textarea name="mensagem" rows="8" required>{{ old('mensagem', "Olá, {cliente}.

Identificamos a cobrança #{cobranca_id}, com vencimento em {vencimento} e saldo atualizado de {saldo}.

PIX: {pix}

Consulte com segurança: {link_portal}

Caso já tenha pago, desconsidere esta mensagem.") }}</textarea><small>Variáveis: {cliente}, {cobranca_id}, {vencimento}, {valor}, {saldo}, {dias_atraso}, {placa}, {pix}, {link_portal}.</small></label>
                                <div class="span-3 campaign-select-list">
                                    <strong>Cobranças para seleção manual</strong>
                                    <div class="campaign-checks">
                                        @foreach($cobrancasAbertas->take(80) as $item)
                                            <label><input type="checkbox" name="cobrancas[]" value="{{ $item->id }}"> #{{ $item->id }} · {{ $item->cliente?->nome }} · {{ $item->vencimento?->format('d/m/Y') }} · {{ \App\Support\Locx::moeda(max(0,(float)$item->valor_atualizado-(float)$item->valor_pago)) }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="span-3"><button type="submit">Criar e processar campanha</button></div>
                            </form>
                        </div>
                        <div class="panel">
                            <h2>Últimas campanhas</h2>
                            <div class="campaign-list">
                                @forelse($campanhasCobranca as $campanha)
                                    <article class="campaign-item">
                                        <div><strong>#{{ $campanha->id }} · {{ $campanha->nome }}</strong><span>{{ strtoupper(implode(' + ', $campanha->canais_json ?: [])) }} · {{ $campanha->criado_em?->format('d/m/Y H:i') }}</span></div>
                                        <div>{!! \App\Support\Locx::status($campanha->status) !!}</div>
                                        <div class="campaign-progress"><span style="width: {{ $campanha->total_destinatarios ? min(100, round(($campanha->total_processados/$campanha->total_destinatarios)*100)) : 0 }}%"></span></div>
                                        <small>{{ $campanha->total_processados }}/{{ $campanha->total_destinatarios }} processados · {{ $campanha->total_enviados }} enviados · {{ $campanha->total_falhas }} falhas</small>
                                        <div class="actions">
                                            @if(!in_array($campanha->status,['concluida','cancelada'],true))
                                                <form method="post" action="{{ route('locx.cobrancas.campanhas.executar',$campanha) }}">@csrf<button class="btn secondary">Processar agora</button></form>
                                                <form method="post" action="{{ route('locx.cobrancas.campanhas.cancelar',$campanha) }}">@csrf<button class="btn danger">Cancelar</button></form>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="empty">Nenhuma campanha criada.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="panel campaign-report">
                        <h2>Relatório recente de destinatários</h2>
                        <p>Mostra o resultado por cliente e evita que falhas fiquem escondidas apenas no total da campanha.</p>
                        <div class="table-wrap"><table><tr><th>Campanha</th><th>Cliente</th><th>Cobrança</th><th>Canais tentados</th><th>Status</th><th>Processado</th><th>Detalhe</th></tr>
                            @forelse($campanhaItensRecentes as $item)
                                <tr>
                                    <td>#{{ $item->campanha_id }} · {{ $item->campanha?->nome }}</td>
                                    <td>{{ $item->cliente?->nome ?: '-' }}</td>
                                    <td>#{{ $item->cobranca_id }}</td>
                                    <td>{{ strtoupper(implode(' + ', array_keys((array) $item->resultados_json))) ?: strtoupper(implode(' + ', (array) $item->canais_json)) }}</td>
                                    <td>{!! \App\Support\Locx::status($item->status) !!}</td>
                                    <td>{{ $item->processado_em?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td title="{{ $item->erro }}">{{ $item->erro ? \Illuminate\Support\Str::limit($item->erro, 120) : 'Sem erro registrado' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty">Nenhum disparo de campanha processado.</td></tr>
                            @endforelse
                        </table></div>
                    </div>
                </section>
            @endif

        @elseif ($page === 'inadimplencia')
            <div class="panel"><h2>Motor de Inadimplência - Regra LocX</h2><p>Sem pagamento: 10% simples ao dia sobre o valor principal até domingo. Pagamento parcial: 10% composto ao dia sobre o saldo restante até domingo.</p>
                <div class="table-wrap"><table><tr><th>Cliente</th><th>Moto</th><th>Dias</th><th>Saldo Original</th><th>Pago</th><th>Atualizado</th><th>Canais</th></tr>
                    @foreach($inadimplentes as $item)
                        @php($atualizado=app(\App\Services\CobrancaCalculator::class)->valorAtualizado($item->valor_principal,$item->valor_pago,$item->vencimento))
                        <tr><td>{{ $item->cliente?->nome }}</td><td>{{ $item->contrato?->motocicleta?->placa }}</td><td>{{ app(\App\Services\CobrancaCalculator::class)->diasAtrasoAteDomingo($item->vencimento) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_principal) }}</td><td>{{ \App\Support\Locx::moeda($item->valor_pago) }}</td><td>{{ \App\Support\Locx::moeda($atualizado) }}</td><td><div class="actions"><a class="btn secondary" target="_blank" href="https://wa.me/55{{ preg_replace('/\D/','',$item->cliente?->whatsapp) }}">Abrir WhatsApp</a><form method="post" action="{{ route('locx.cobrancas.whatsapp',$item) }}">@csrf<button class="btn success" type="submit">Enviar WhatsApp</button></form><form method="post" action="{{ route('locx.cobrancas.telegram',$item) }}">@csrf<button class="btn secondary" type="submit">Enviar Telegram</button></form></div></td></tr>
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

        @elseif ($page === 'bancos')
            @php($bancoSelecionado = $bancoSelecionado ?? 'pagbank')
            <div class="panel">
                <h2>Integrações de pagamento</h2>
                <form method="get" action="{{ route('locx.index') }}" class="form-grid">
                    <input type="hidden" name="page" value="bancos">
                    <label>Selecionar integração<select name="banco" onchange="this.form.submit()"><option value="pagbank" @selected($bancoSelecionado==='pagbank')>PagBank</option><option value="asaas" @selected($bancoSelecionado==='asaas')>Asaas</option><option value="sicoob" @selected($bancoSelecionado==='sicoob')>Sicoob</option><option value="itau" @selected($bancoSelecionado==='itau')>Itau</option></select></label>
                    <div><button class="btn secondary" type="submit">Abrir integração</button></div>
                </form>
            </div>

            @if ($bancoSelecionado === 'pagbank')
                <div class="grid side">
                    <div class="panel"><h2>Configuração PagBank / PIX</h2><form method="post" action="{{ route('locx.pagbank.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos">
                        <label>Modo<select name="modo"><option value="demo" @selected($pagbankConfig->modo==='demo')>demo</option><option value="api" @selected($pagbankConfig->modo==='api')>api oficial</option></select></label>
                        <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($pagbankConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($pagbankConfig->ambiente==='producao')>produção</option></select></label>
                        <label>Ativo<select name="ativo"><option value="1" @selected($pagbankConfig->ativo)>sim</option><option value="0" @selected(!$pagbankConfig->ativo)>não</option></select></label>
                        <label>Client ID<input name="client_id" value="{{ $pagbankConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $pagbankConfig->client_secret }}"></label>
                        <label class="span-3">Access Token PagBank<input type="password" name="access_token" value="{{ $pagbankConfig->access_token }}"></label><label class="span-2">URL Webhook<input name="webhook_url" value="{{ $pagbankConfig->webhook_url ?: route('locx.webhook-pagbank') }}"></label><label>Referência<input name="merchant_reference" value="{{ $pagbankConfig->merchant_reference ?: 'LOCX' }}"></label>
                        <div class="span-3"><button name="acao" value="salvar">Salvar PagBank</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                    </form></div>
                    <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $pagbankConfig->webhook_url ?: route('locx.webhook-pagbank') }}</code></p><p><strong>Ambiente:</strong> {{ $pagbankConfig->ambiente }} · <strong>Modo:</strong> {{ $pagbankConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>Use <strong>demo</strong> para testar sem credenciais.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_pagbank.html') }}" target="_blank">Abrir manual PagBank</a></p></div>
                </div>
            @elseif ($bancoSelecionado === 'asaas')
                <div class="grid side">
                    <div class="panel"><h2>Configuração Asaas / PIX</h2><form method="post" action="{{ route('locx.asaas.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos">
                        <label>Modo<select name="modo"><option value="demo" @selected($asaasConfig->modo==='demo')>demo</option><option value="api" @selected($asaasConfig->modo==='api')>api oficial</option></select></label>
                        <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($asaasConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($asaasConfig->ambiente==='producao')>produção</option></select></label>
                        <label>Ativo<select name="ativo"><option value="1" @selected($asaasConfig->ativo)>sim</option><option value="0" @selected(!$asaasConfig->ativo)>não</option></select></label>
                        <label class="span-3">API Key Asaas<input type="password" name="api_key" value="" placeholder="{{ $asaasConfig->api_key ? 'Chave salva - deixe vazio para manter' : 'Cole a API Key do Asaas' }}"></label>
                        <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $asaasConfig->webhook_url ?: route('locx.webhook-asaas') }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $asaasConfig->webhook_token ?: 'locx_asaas_webhook_token' }}"></label>
                        <div class="span-3"><button name="acao" value="salvar">Salvar Asaas</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                    </form></div>
                    <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $asaasConfig->webhook_url ?: route('locx.webhook-asaas') }}</code></p><p><strong>Ambiente:</strong> {{ $asaasConfig->ambiente }} · <strong>Modo:</strong> {{ $asaasConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>Cadastre a API Key, configure o webhook no painel Asaas e gere uma cobrança de teste.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_asaas.html') }}" target="_blank">Abrir manual Asaas</a></p></div>
                </div>
            @elseif ($bancoSelecionado === 'sicoob')
                <div class="grid side">
                    <div class="panel"><h2>Configuração Sicoob / PIX</h2><form method="post" action="{{ route('locx.sicoob.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos">
                        <label>Modo<select name="modo"><option value="demo" @selected($sicoobConfig->modo==='demo')>demo</option><option value="api" @selected($sicoobConfig->modo==='api')>api oficial</option></select></label>
                        <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($sicoobConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($sicoobConfig->ambiente==='producao')>produção</option></select></label>
                        <label>Ativo<select name="ativo"><option value="1" @selected($sicoobConfig->ativo)>sim</option><option value="0" @selected(!$sicoobConfig->ativo)>não</option></select></label>
                        <label class="span-2">Client ID<input name="client_id" value="{{ $sicoobConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $sicoobConfig->client_secret }}"></label>
                        <label class="span-3">Chave PIX<input name="chave_pix" value="{{ $sicoobConfig->chave_pix }}" placeholder="CPF/CNPJ, e-mail, telefone ou chave aleatória"></label>
                        <label class="span-3">URL Token OAuth<input name="token_url" value="{{ $sicoobConfig->token_url ?: \App\Services\SicoobService::DEFAULT_TOKEN_URL }}"></label>
                        <label class="span-3">URL Base API PIX<input name="api_base_url" value="{{ $sicoobConfig->api_base_url ?: \App\Services\SicoobService::DEFAULT_API_BASE_URL }}"></label>
                        <label class="span-2">Certificado PEM<input name="cert_path" value="{{ $sicoobConfig->cert_path }}" placeholder="C:\certificados\sicoob-cert.pem"></label><label>Chave PEM<input name="key_path" value="{{ $sicoobConfig->key_path }}" placeholder="C:\certificados\sicoob-key.pem"></label>
                        <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $sicoobConfig->webhook_url ?: route('locx.webhook-sicoob', ['token' => $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN]) }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN }}"></label>
                        <div class="span-3"><button name="acao" value="salvar">Salvar Sicoob</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                    </form></div>
                    <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $sicoobConfig->webhook_url ?: route('locx.webhook-sicoob', ['token' => $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN]) }}</code></p><p><strong>Ambiente:</strong> {{ $sicoobConfig->ambiente }} · <strong>Modo:</strong> {{ $sicoobConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p><a class="btn secondary" href="https://developers.sicoob.com.br/portal/apis" target="_blank" rel="noopener">Abrir portal Sicoob</a></p></div>
                </div>
            @else
                <div class="grid side">
                    <div class="panel"><h2>Configuracao Itau / PIX</h2><form method="post" action="{{ route('locx.itau.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos">
                        <label>Modo<select name="modo"><option value="demo" @selected($itauConfig->modo==='demo')>demo</option><option value="api" @selected($itauConfig->modo==='api')>api oficial</option></select></label>
                        <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($itauConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($itauConfig->ambiente==='producao')>producao</option></select></label>
                        <label>Ativo<select name="ativo"><option value="1" @selected($itauConfig->ativo)>sim</option><option value="0" @selected(!$itauConfig->ativo)>nao</option></select></label>
                        <label class="span-2">Client ID<input name="client_id" value="{{ $itauConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $itauConfig->client_secret }}"></label>
                        <label class="span-3">Chave PIX<input name="chave_pix" value="{{ $itauConfig->chave_pix }}" placeholder="CPF/CNPJ, e-mail, telefone ou chave aleatoria"></label>
                        <label class="span-3">URL Token OAuth<input name="token_url" value="{{ $itauConfig->token_url ?: \App\Services\ItauService::DEFAULT_TOKEN_URL }}"></label>
                        <label class="span-3">URL Base API PIX<input name="api_base_url" value="{{ $itauConfig->api_base_url ?: \App\Services\ItauService::DEFAULT_API_BASE_URL }}"></label>
                        <label class="span-2">Certificado .crt<input name="cert_path" value="{{ $itauConfig->cert_path }}" placeholder="C:\certificados\itau-cert.crt"></label><label>Chave privada .key<input name="key_path" value="{{ $itauConfig->key_path }}" placeholder="C:\certificados\itau-key.key"></label>
                        <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $itauConfig->webhook_url ?: route('locx.webhook-itau', ['token' => $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN]) }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN }}"></label>
                        <div class="span-3"><button name="acao" value="salvar">Salvar Itau</button> <button class="btn secondary" name="acao" value="testar">Testar conexao</button></div>
                    </form></div>
                    <div class="panel"><h2>Status da integracao</h2><p><strong>Webhook:</strong><br><code>{{ $itauConfig->webhook_url ?: route('locx.webhook-itau', ['token' => $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN]) }}</code></p><p><strong>Ambiente:</strong> {{ $itauConfig->ambiente }} · <strong>Modo:</strong> {{ $itauConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob', 'itau' => 'Itau'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="bancos"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option><option value="itau" @selected($pixGatewayConfig->gateway==='itau')>Itau</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>O Itau exige token OAuth2 com mTLS. Configure o .crt emitido pelo Itau, a .key privada correspondente e confirme a URL base da API contratada no portal.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_itau.html') }}" target="_blank">Abrir manual Itau</a></p><p><a class="btn secondary" href="https://devportal.itau.com.br/certificado-dinamico" target="_blank" rel="noopener">Portal de certificado</a></p></div>
                </div>
            @endif

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

        @elseif ($page === 'sicoob')
            <div class="grid side">
                <div class="panel"><h2>Configuração Sicoob / PIX</h2><form method="post" action="{{ route('locx.sicoob.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($sicoobConfig->modo==='demo')>demo</option><option value="api" @selected($sicoobConfig->modo==='api')>api oficial</option></select></label>
                    <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($sicoobConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($sicoobConfig->ambiente==='producao')>produção</option></select></label>
                    <label>Ativo<select name="ativo"><option value="1" @selected($sicoobConfig->ativo)>sim</option><option value="0" @selected(!$sicoobConfig->ativo)>não</option></select></label>
                    <label class="span-2">Client ID<input name="client_id" value="{{ $sicoobConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $sicoobConfig->client_secret }}"></label>
                    <label class="span-3">Chave PIX<input name="chave_pix" value="{{ $sicoobConfig->chave_pix }}"></label>
                    <label class="span-3">URL Token OAuth<input name="token_url" value="{{ $sicoobConfig->token_url ?: \App\Services\SicoobService::DEFAULT_TOKEN_URL }}"></label>
                    <label class="span-3">URL Base API PIX<input name="api_base_url" value="{{ $sicoobConfig->api_base_url ?: \App\Services\SicoobService::DEFAULT_API_BASE_URL }}"></label>
                    <label class="span-2">Certificado PEM<input name="cert_path" value="{{ $sicoobConfig->cert_path }}"></label><label>Chave PEM<input name="key_path" value="{{ $sicoobConfig->key_path }}"></label>
                    <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $sicoobConfig->webhook_url ?: route('locx.webhook-sicoob', ['token' => $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN]) }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN }}"></label>
                    <div class="span-3"><button name="acao" value="salvar">Salvar Sicoob</button> <button class="btn secondary" name="acao" value="testar">Testar conexão</button></div>
                </form></div>
                <div class="panel"><h2>Status da integração</h2><p><strong>Webhook:</strong><br><code>{{ $sicoobConfig->webhook_url ?: route('locx.webhook-sicoob', ['token' => $sicoobConfig->webhook_token ?: \App\Services\SicoobService::DEFAULT_WEBHOOK_TOKEN]) }}</code></p><p><strong>Ambiente:</strong> {{ $sicoobConfig->ambiente }} · <strong>Modo:</strong> {{ $sicoobConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="sicoob"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p><a class="btn secondary" href="https://developers.sicoob.com.br/portal/apis" target="_blank" rel="noopener">Abrir portal Sicoob</a></p></div>
            </div>

        @elseif ($page === 'itau')
            <div class="grid side">
                <div class="panel"><h2>Configuracao Itau / PIX</h2><form method="post" action="{{ route('locx.itau.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($itauConfig->modo==='demo')>demo</option><option value="api" @selected($itauConfig->modo==='api')>api oficial</option></select></label>
                    <label>Ambiente<select name="ambiente"><option value="sandbox" @selected($itauConfig->ambiente==='sandbox')>sandbox</option><option value="producao" @selected($itauConfig->ambiente==='producao')>producao</option></select></label>
                    <label>Ativo<select name="ativo"><option value="1" @selected($itauConfig->ativo)>sim</option><option value="0" @selected(!$itauConfig->ativo)>nao</option></select></label>
                    <label class="span-2">Client ID<input name="client_id" value="{{ $itauConfig->client_id }}"></label><label>Client Secret<input type="password" name="client_secret" value="{{ $itauConfig->client_secret }}"></label>
                    <label class="span-3">Chave PIX<input name="chave_pix" value="{{ $itauConfig->chave_pix }}"></label>
                    <label class="span-3">URL Token OAuth<input name="token_url" value="{{ $itauConfig->token_url ?: \App\Services\ItauService::DEFAULT_TOKEN_URL }}"></label>
                    <label class="span-3">URL Base API PIX<input name="api_base_url" value="{{ $itauConfig->api_base_url ?: \App\Services\ItauService::DEFAULT_API_BASE_URL }}"></label>
                    <label class="span-2">Certificado .crt<input name="cert_path" value="{{ $itauConfig->cert_path }}"></label><label>Chave privada .key<input name="key_path" value="{{ $itauConfig->key_path }}"></label>
                    <label class="span-2">URL Webhook<input name="webhook_url" value="{{ $itauConfig->webhook_url ?: route('locx.webhook-itau', ['token' => $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN]) }}"></label><label>Token Webhook<input name="webhook_token" value="{{ $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN }}"></label>
                    <div class="span-3"><button name="acao" value="salvar">Salvar Itau</button> <button class="btn secondary" name="acao" value="testar">Testar conexao</button></div>
                </form></div>
                <div class="panel"><h2>Status da integracao</h2><p><strong>Webhook:</strong><br><code>{{ $itauConfig->webhook_url ?: route('locx.webhook-itau', ['token' => $itauConfig->webhook_token ?: \App\Services\ItauService::DEFAULT_WEBHOOK_TOKEN]) }}</code></p><p><strong>Ambiente:</strong> {{ $itauConfig->ambiente }} · <strong>Modo:</strong> {{ $itauConfig->modo === 'api' ? 'api oficial' : 'demo' }}</p><p><strong>Gateway PIX principal:</strong> {{ ['asaas' => 'Asaas', 'sicoob' => 'Sicoob', 'itau' => 'Itau'][$pixGatewayConfig->gateway] ?? 'PagBank' }}</p><form method="post" action="{{ route('locx.gateway-pix.salvar') }}" class="form-grid">@csrf<input type="hidden" name="page" value="itau"><label>Usar para gerar PIX<select name="gateway"><option value="pagbank" @selected($pixGatewayConfig->gateway==='pagbank')>PagBank</option><option value="asaas" @selected($pixGatewayConfig->gateway==='asaas')>Asaas</option><option value="sicoob" @selected($pixGatewayConfig->gateway==='sicoob')>Sicoob</option><option value="itau" @selected($pixGatewayConfig->gateway==='itau')>Itau</option></select></label><div><button class="btn secondary">Atualizar gateway</button></div></form><p>Use modo demo ate concluir o certificado dinamico. Depois preencha Client ID, Client Secret, chave PIX, .crt e .key.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_itau.html') }}" target="_blank">Abrir manual Itau</a></p></div>
            </div>

        @elseif ($page === 'whatsapp')
            <div class="panel"><h2>WhatsApp Business API</h2><p>Configure a integração oficial da Meta. No modo <strong>demo</strong>, o sistema apenas registra uma simulação e nenhuma mensagem é enviada. Versão da Graph API: <strong>{{ $graphVersion }}</strong>.</p>
                <form method="post" action="{{ route('locx.whatsapp.salvar') }}" class="form-grid">@csrf
                    <label>Modo<select name="modo"><option value="demo" @selected($whatsappConfig->modo==='demo')>Demo / Simulado</option><option value="oficial" @selected($whatsappConfig->modo==='oficial')>Oficial - Meta Cloud API</option><option value="evolution" @selected($whatsappConfig->modo==='evolution')>Evolution API</option></select></label><label>Status<select name="ativo"><option value="1" @selected($whatsappConfig->ativo)>Ativo</option><option value="0" @selected(!$whatsappConfig->ativo)>Inativo</option></select></label>
                    <label>WABA ID<input name="waba_id" value="{{ $whatsappConfig->waba_id }}" placeholder="ID da conta do WhatsApp Business"></label><label>Phone Number ID<input name="phone_number_id" value="{{ $whatsappConfig->phone_number_id }}"></label><label class="span-3">Access Token permanente<input type="password" name="access_token" value="" placeholder="{{ $whatsappConfig->access_token ? 'Token salvo - deixe vazio para manter' : 'Cole o token permanente da Meta' }}"></label><label>Verify Token<input name="verify_token" value="{{ $whatsappConfig->verify_token ?: 'locx_webhook_token' }}"></label>
                    <label class="span-2">URL Evolution<input name="evolution_base_url" value="{{ $whatsappConfig->evolution_base_url }}" placeholder="https://sua-evolution.com"></label><label>Instância Evolution<input name="evolution_instance" value="{{ $whatsappConfig->evolution_instance }}" placeholder="locx"></label><label class="span-3">API Key Evolution<input type="password" name="evolution_api_key" value="" placeholder="{{ $whatsappConfig->evolution_api_key ? 'API Key salva - deixe vazio para manter' : 'Cole a API Key da Evolution' }}"></label>
                    <label>Template cobrança<input name="template_cobranca" value="{{ $whatsappConfig->template_cobranca }}"></label><label>Idioma do template<input name="template_language" value="{{ $whatsappConfig->template_language ?: 'pt_BR' }}" placeholder="pt_BR"></label><label>Template lembrete<input name="template_lembrete" value="{{ $whatsappConfig->template_lembrete }}"></label><label>Template bloqueio<input name="template_bloqueio" value="{{ $whatsappConfig->template_bloqueio }}"></label><div class="span-3"><button type="submit">Salvar Configuração</button></div>
                </form>
            </div>
            <div class="grid side"><div class="panel" id="testar-integracao"><h2>Testar conexão</h2><form method="post" action="{{ route('locx.whatsapp.testar') }}">@csrf<button class="btn success">Validar integração</button></form><p>No modo oficial, valida a Meta e o template. No modo Evolution, valida a URL, instância e API Key.</p><p><strong>URL do Webhook:</strong><br><code>{{ route('locx.webhook-whatsapp') }}</code></p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_whatsapp.html') }}" target="_blank">Abrir manual WhatsApp</a></p></div>
                <div class="panel"><h2>Últimos envios</h2><div class="table-wrap"><table><tr><th>Data</th><th>Cliente</th><th>Telefone</th><th>Status</th><th>HTTP</th><th>Detalhe</th></tr>@foreach($whatsappLogs as $log)<tr><td>{{ $log->criado_em?->format('d/m/Y H:i') }}</td><td>{{ $log->cliente?->nome ?? '-' }}</td><td>{{ $log->telefone }}</td><td>{!! \App\Support\Locx::status($log->status) !!}</td><td>{{ $log->http_code ?? '-' }}</td><td title="{{ $log->erro ?: $log->resposta_api }}">{{ \Illuminate\Support\Str::limit($log->erro ?: $log->resposta_api, 90) ?: '-' }}</td></tr>@endforeach</table></div></div>
            </div>

        @elseif ($page === 'telegram')
            <div class="cards">
                <div class="metric info"><span>Clientes vinculados</span><strong>{{ $telegramVinculados }}</strong><small>aptos a receber mensagens</small></div>
                <div class="metric {{ $telegramConfig->ativo ? 'ok' : 'danger' }}"><span>Integração</span><strong>{{ $telegramConfig->modo === 'api' ? 'API' : 'DEMO' }}</strong><small>{{ $telegramConfig->ativo ? 'ativa' : 'inativa' }}</small></div>
                <div class="metric"><span>Bot avisos</span><strong>{{ $telegramConfig->bot_username ? '@'.$telegramConfig->bot_username : '-' }}</strong><small>cobranças e lembretes</small></div>
                <div class="metric"><span>Bot atendimento</span><strong>{{ $telegramConfig->atendimento_bot_username ? '@'.$telegramConfig->atendimento_bot_username : '-' }}</strong><small>chat no CRM</small></div>
            </div>
            <div class="grid side">
                <div class="panel"><h2>Telegram Bot</h2><p>Configure um bot para avisos de cobrança e outro bot opcional para atendimento no CRM.</p>
                    <form method="post" action="{{ route('locx.telegram.salvar') }}" class="form-grid">@csrf
                        <label>Modo<select name="modo"><option value="demo" @selected($telegramConfig->modo==='demo')>Demo / Simulado</option><option value="api" @selected($telegramConfig->modo==='api')>API oficial</option></select></label>
                        <label>Status<select name="ativo"><option value="1" @selected($telegramConfig->ativo)>Ativo</option><option value="0" @selected(!$telegramConfig->ativo)>Inativo</option></select></label>
                        <label>Usuario do bot de avisos<input name="bot_username" value="{{ $telegramConfig->bot_username }}" placeholder="locx_avisos_bot"></label>
                        <label class="span-3">Token do BotFather<input type="password" name="bot_token" placeholder="{{ $telegramConfig->bot_token ? 'Token salvo — deixe vazio para manter' : 'Cole o token do bot' }}"></label>
                        <label class="span-2">Segredo do webhook<input name="webhook_secret" value="{{ $telegramConfig->webhook_secret }}"></label>
                        <label>Usuário do bot de atendimento<input name="atendimento_bot_username" value="{{ $telegramConfig->atendimento_bot_username }}" placeholder="locx_atendimento_bot"></label>
                        <label class="span-3">Token do BotFather - atendimento<input type="password" name="atendimento_bot_token" placeholder="{{ $telegramConfig->atendimento_bot_token ? 'Token salvo — deixe vazio para manter' : 'Cole o token do bot de atendimento' }}"></label>
                        <label class="span-2">Segredo do webhook - atendimento<input name="atendimento_webhook_secret" value="{{ $telegramConfig->atendimento_webhook_secret }}"></label>
                        <label>Formatação<select name="parse_mode"><option value="sem_formatacao" @selected(blank($telegramConfig->parse_mode))>Sem formatação (recomendado)</option><option value="HTML" @selected($telegramConfig->parse_mode==='HTML')>HTML</option><option value="MarkdownV2" @selected($telegramConfig->parse_mode==='MarkdownV2')>MarkdownV2</option></select></label>
                        <label class="span-3">Modelo padrão de cobrança<textarea name="template_cobranca" rows="8">{{ $telegramConfig->template_cobranca }}</textarea></label>
                        <label class="span-3">Modelo lembrete<textarea name="template_lembrete" rows="5">{{ $telegramConfig->template_lembrete }}</textarea></label>
                        <label class="span-3">Modelo vencimento<textarea name="template_vencimento" rows="5">{{ $telegramConfig->template_vencimento }}</textarea></label>
                        <label class="span-3">Modelo pagamento confirmado<textarea name="template_pagamento" rows="5">{{ $telegramConfig->template_pagamento }}</textarea></label>
                        <label class="span-2">Chat ID do gerente<input name="gerente_chat_id" value="{{ $telegramConfig->gerente_chat_id }}" placeholder="Chat ID para alertas internos"></label>
                        <label class="span-3">Modelo aviso gerente<textarea name="template_gerente" rows="5">{{ $telegramConfig->template_gerente }}</textarea></label>
                        <div class="span-3"><button>Salvar Telegram</button></div>
                    </form>
                </div>
                <div class="panel" id="testar-bot"><h2>Conexão e webhook</h2><p><strong>Webhook público:</strong><br><code>{{ route('locx.webhook-telegram') }}</code></p><p>Primeiro salve os tokens e usuários dos bots. Depois teste e configure o webhook.</p>
                    <div class="actions"><form method="post" action="{{ route('locx.telegram.testar') }}">@csrf<button class="btn success">Testar bot</button></form><form method="post" action="{{ route('locx.telegram.webhook') }}">@csrf<button class="btn secondary">Configurar webhook</button></form></div>
                    <hr><h3>Como o cliente vincula</h3><p>No Portal do Cliente aparecerá o botão <strong>Vincular Telegram</strong>. O link identifica o cadastro com segurança e grava o chat ID após o cliente pressionar Iniciar.</p><p><a class="btn secondary" href="{{ \App\Support\Locx::asset('docs/manual_telegram.html') }}" target="_blank">Abrir manual Telegram</a></p>
                </div>
            </div>
            <div class="panel"><h2>Últimas mensagens e envios</h2><div class="table-wrap"><table><tr><th>Data</th><th>Cliente</th><th>Chat</th><th>Tipo</th><th>Status</th><th>Detalhe</th></tr>@forelse($telegramLogs as $log)<tr><td>{{ $log->criado_em?->format('d/m/Y H:i') }}</td><td>{{ $log->cliente?->nome ?? '-' }}</td><td>{{ $log->username ? '@'.$log->username : ($log->chat_id ?: '-') }}</td><td>{{ $log->tipo }}</td><td>{!! \App\Support\Locx::status($log->status) !!}</td><td title="{{ $log->erro ?: $log->mensagem }}">{{ \Illuminate\Support\Str::limit($log->erro ?: $log->mensagem,100) }}</td></tr>@empty<tr><td colspan="6" class="empty">Nenhum registro do Telegram.</td></tr>@endforelse</table></div></div>

        @elseif ($page === 'configuracoes')
            <div class="panel"><h2>Configurações e integrações</h2><div class="module-grid">
                <a class="module-card" href="{{ route('locx.index',['page'=>'bancos']) }}"><i>{!! \App\Support\Locx::icon('bancos') !!}</i><div><strong>Integrações de pagamento</strong><br><small>Gateways Pix em uma única tela</small></div></a>
                <a class="module-card" href="{{ route('locx.index',['page'=>'whatsapp']) }}"><i>{!! \App\Support\Locx::icon('whatsapp') !!}</i><div><strong>WhatsApp API</strong><br><small>Mensagens automáticas</small></div></a>
                <a class="module-card" href="{{ route('locx.index',['page'=>'telegram']) }}"><i>{!! \App\Support\Locx::icon('telegram') !!}</i><div><strong>Telegram Bot</strong><br><small>Disparos e atendimento multicanal</small></div></a>
            </div></div>
        @endif
    </main>
</div>
<script src="{{ \App\Support\Locx::asset('assets/js/app.js') }}"></script>
</body>
</html>
