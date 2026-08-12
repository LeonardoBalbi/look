<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administração comercial | {{ $branding['product_name'] }}</title>
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('rental-theme') || 'light';
    </script>
    <style>
        :root{--bg:#f5f7fb;--panel:#fff;--ink:#162033;--muted:#667085;--line:#d7dee8;--brand:#0f766e;--brand2:#155e75;--danger:#b42318;--warn:#b54708;--ok:#047857}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,Segoe UI,Arial,sans-serif}.shell{max-width:1280px;margin:0 auto;padding:24px}.topbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:22px}.topbar h1{font-size:28px;line-height:1.1;margin:0 0 6px}.topbar p{margin:0;color:var(--muted)}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn,button{border:0;border-radius:6px;background:var(--brand);color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#fff;color:var(--brand2);border:1px solid var(--line)}.notice{background:#ecfdf3;border:1px solid #abefc6;color:#067647;border-radius:8px;padding:12px 14px;margin-bottom:18px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.metric{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:16px}.metric span{display:block;color:var(--muted);font-size:13px}.metric strong{font-size:28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:18px;margin-bottom:18px}.panel h2{font-size:18px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.span-2{grid-column:span 2}label{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--muted);font-weight:700}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:6px;padding:10px 11px;font:inherit;color:var(--ink);background:#fff}textarea{min-height:76px;resize:vertical}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px}table{width:100%;border-collapse:collapse;min-width:760px;background:#fff}th,td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:top}th{background:#f8fafc;color:#475467}tr:last-child td{border-bottom:0}code{background:#eef4ff;border-radius:5px;padding:3px 5px;font-size:12px}.badge{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:12px;font-weight:800}.badge.ativa,.badge.trial,.badge.teste,.badge.ativo{background:#dcfae6;color:var(--ok)}.badge.bloqueada,.badge.cancelado,.badge.vencida{background:#fee4e2;color:var(--danger)}.badge.pendente{background:#fef0c7;color:var(--warn)}.muted{color:var(--muted)}.api-box{background:#eef6f6;border:1px solid #99d6cf;border-radius:8px;padding:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:18px}.api-box code{background:#fff;color:#134e4a;word-break:break-all}@media (max-width:900px){.cards,.grid{grid-template-columns:1fr}.topbar{display:block}.actions{margin-top:14px}.form-grid{grid-template-columns:1fr}.span-2{grid-column:auto}}
        html[data-theme="dark"]{color-scheme:dark;--bg:#0b1220;--panel:#111c2e;--ink:#e5eefb;--muted:#9fb0c7;--line:#27364d;--brand:#14b8a6;--brand2:#67e8f9;--danger:#f87171;--warn:#fbbf24;--ok:#34d399}
        html[data-theme="dark"] input,html[data-theme="dark"] select,html[data-theme="dark"] textarea,html[data-theme="dark"] table{background:#0f1a2b;color:var(--ink)}
        html[data-theme="dark"] th{background:#172235;color:#b8c5d8}
        html[data-theme="dark"] tr:hover td{background:#162236}
        html[data-theme="dark"] .btn.secondary,html[data-theme="dark"] .api-box code{background:#172235;color:var(--ink);border-color:var(--line)}
        html[data-theme="dark"] .api-box{background:#102a2b;border-color:#1f6f6c}
        html[data-theme="dark"] code{background:#172235;color:#c7d2fe}
        .theme-toggle{min-width:116px;justify-content:center}.btn.danger,button.danger{background:var(--danger)}.btn.small,button.small{padding:6px 9px;font-size:12px}.inline{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap}.inline input,.inline select{width:auto;min-width:70px}.actions-cell{display:flex;gap:6px;flex-wrap:wrap;min-width:270px}.help{font-size:12px;color:var(--muted);margin-top:5px}.badge.pago{background:#dcfae6;color:var(--ok)}.badge.falhou,.badge.estornado{background:#fee4e2;color:var(--danger)}
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div>
            <h1>Administração comercial</h1>
            <p>Clientes, planos, licenças e validações de {{ $branding['product_name'] }}.</p>
        </div>
        <div class="actions">
            <button type="button" class="btn secondary theme-toggle" data-theme-toggle>Tema claro</button>
            <a class="btn secondary" href="{{ route('rental.index', ['page' => 'configuracoes']) }}">Voltar à aplicação</a>
        </div>
    </header>

    @if (session('success'))
        <div class="notice"><strong>{{ session('success') }}</strong></div>
    @endif
    @if ($errors->any())
        <div class="notice" style="background:#fff1f0;border-color:#fecdca;color:#b42318"><strong>{{ $errors->first() }}</strong></div>
    @endif

    <div class="api-box">
        <div>
            <strong>URL da API de licenciamento</strong><br>
            <code>{{ $apiUrl }}</code>
        </div>
        <a class="btn secondary" href="#licencas">Gerar chave</a>
    </div>
    <div class="api-box">
        <div><strong>Portal de pagamento do cliente</strong><br><code>{{ route('licenca-cliente.login') }}</code></div>
        <a class="btn secondary" href="{{ route('licenca-cliente.login') }}" target="_blank" rel="noopener">Abrir Minha Licença</a>
    </div>

    <section class="cards">
        <div class="metric"><span>Clientes</span><strong>{{ $clientes->count() }}</strong></div>
        <div class="metric"><span>Planos</span><strong>{{ $planos->count() }}</strong></div>
        <div class="metric"><span>Licencas</span><strong>{{ $licencas->count() }}</strong></div>
        <div class="metric"><span>Pagamentos</span><strong>{{ $pagamentos->count() }}</strong></div>
    </section>

    <div class="grid">
        <section class="panel" id="clientes">
            <h2>{{ $clienteEdit ? 'Editar cliente' : 'Novo cliente' }}</h2>
            <form class="form-grid" method="post" action="{{ route('licencas-portal.clientes.salvar') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $clienteEdit?->id }}">
                <label class="span-2">Nome<input name="nome" required maxlength="180" value="{{ old('nome', $clienteEdit?->nome) }}" placeholder="Locadora Exemplo"></label>
                <label>Documento<input name="documento" maxlength="30" value="{{ old('documento', $clienteEdit?->documento) }}" placeholder="00.000.000/0001-00"></label>
                <label>Status<select name="status">@foreach(['ativo','bloqueado','cancelado'] as $status)<option value="{{ $status }}" @selected(old('status',$clienteEdit?->status ?? 'ativo')===$status)>{{ $status }}</option>@endforeach</select></label>
                <label>E-mail<input type="email" name="email" maxlength="160" value="{{ old('email', $clienteEdit?->email) }}"></label>
                <label>Telefone<input name="telefone" maxlength="40" value="{{ old('telefone', $clienteEdit?->telefone) }}"></label>
                <label>Portal Minha Licença<select name="portal_ativo"><option value="1" @selected(old('portal_ativo',$clienteEdit?->portal_ativo ?? false))>liberado</option><option value="0" @selected(!old('portal_ativo',$clienteEdit?->portal_ativo ?? false))>bloqueado</option></select></label>
                <label>Senha do portal<input type="password" name="senha_portal" minlength="8" autocomplete="new-password" placeholder="deixe vazio para manter ou enviar recuperação"></label>
                <div class="span-2"><button>Salvar cliente</button> @if($clienteEdit)<a class="btn secondary" href="{{ url('/licencas-portal#clientes') }}">Cancelar</a>@endif</div>
            </form>
        </section>

        <section class="panel" id="planos">
            <h2>{{ $planoEdit ? 'Editar plano' : 'Novo plano' }}</h2>
            <form class="form-grid" method="post" action="{{ route('licencas-portal.planos.salvar') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $planoEdit?->id }}">
                <label>Codigo<input name="codigo" required maxlength="80" value="{{ old('codigo', $planoEdit?->codigo) }}" placeholder="profissional"></label>
                <label>Nome<input name="nome" required maxlength="120" value="{{ old('nome', $planoEdit?->nome) }}" placeholder="Profissional"></label>
                <label>Preco mensal<input type="number" step="0.01" min="0" name="preco" value="{{ old('preco', $planoEdit ? number_format($planoEdit->preco_centavos/100,2,'.','') : '0.00') }}"></label>
                <label>Ativo<select name="ativo"><option value="1" @selected(old('ativo',$planoEdit?->ativo ?? true))>sim</option><option value="0" @selected(!old('ativo',$planoEdit?->ativo ?? true))>nao</option></select></label>
                <label>Limite lojas<input type="number" min="1" name="max_lojas" value="{{ old('max_lojas', $planoEdit?->max_lojas) }}" placeholder="5"></label>
                <label>Limite usuarios<input type="number" min="1" name="max_usuarios" value="{{ old('max_usuarios', $planoEdit?->max_usuarios) }}" placeholder="20"></label>
                <label class="span-2">Modulos<textarea name="modulos" placeholder="pix, whatsapp, multi_loja, crm, financeiro">{{ old('modulos', $planoEdit ? implode(', ', $planoEdit->modulos_json ?: []) : '') }}</textarea></label>
                <div class="span-2"><button>Salvar plano</button> @if($planoEdit)<a class="btn secondary" href="{{ url('/licencas-portal#planos') }}">Cancelar</a>@endif</div>
            </form>
        </section>
    </div>

    <section class="panel" id="licencas">
        <h2>{{ $licencaEdit ? 'Editar licença e alterar plano' : 'Nova licença' }}</h2>
        <form class="form-grid" method="post" action="{{ route('licencas-portal.licencas.salvar') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $licencaEdit?->id }}">
            <label>Cliente<select name="cliente_id" required><option value="">Selecione</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id',$licencaEdit?->cliente_id)==$cliente->id)>{{ $cliente->nome }}</option>@endforeach</select></label>
            <label>Plano<select name="plano_id" required><option value="">Selecione</option>@foreach($planos as $plano)<option value="{{ $plano->id }}" @selected(old('plano_id',$licencaEdit?->plano_id)==$plano->id)>{{ $plano->nome }}</option>@endforeach</select></label>
            <label>Status<select name="status">@foreach(['ativa','trial','teste','pendente','bloqueada','vencida'] as $status)<option value="{{ $status }}" @selected(old('status',$licencaEdit?->status ?? 'ativa')===$status)>{{ $status }}</option>@endforeach</select></label>
            <label>Vence em<input type="date" name="vence_em" value="{{ old('vence_em',$licencaEdit?->vence_em?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d')) }}"></label>
            <label>Tolerancia offline<input type="number" min="1" max="60" name="tolerancia_offline_dias" value="{{ old('tolerancia_offline_dias',$licencaEdit?->tolerancia_offline_dias ?? 7) }}"></label>
            <label>Meses por renovacao<input type="number" min="1" max="24" name="meses_por_renovacao" value="{{ old('meses_por_renovacao',$licencaEdit?->meses_por_renovacao ?? 1) }}"></label>
            <label>Chave opcional<input name="chave" maxlength="120" value="{{ old('chave',$licencaEdit?->chave) }}" placeholder="vazio gera automatico"></label>
            <label>Renovacao automatica<select name="renovacao_automatica"><option value="1" @selected(old('renovacao_automatica',$licencaEdit?->renovacao_automatica ?? false))>ativa</option><option value="0" @selected(!old('renovacao_automatica',$licencaEdit?->renovacao_automatica ?? false))>inativa</option></select></label>
            @if($licencaEdit?->instancia_id)<label><span>Instalacao</span><span><input type="checkbox" name="desvincular_instancia" value="1" style="width:auto"> desvincular {{ $licencaEdit->instancia_id }}</span></label>@endif
            <label class="span-2">Mensagem<input name="mensagem" maxlength="500" value="{{ old('mensagem',$licencaEdit?->mensagem) }}" placeholder="Licenca liberada pelo portal."></label>
            <div class="span-2"><button>Salvar licenca</button> @if($licencaEdit)<a class="btn secondary" href="{{ url('/licencas-portal#licencas') }}">Cancelar</a>@endif</div>
        </form>
    </section>

    <section class="panel">
        <h2>Licencas emitidas</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Cliente</th><th>Plano</th><th>Status</th><th>Chave</th><th>Vencimento</th><th>Renovação</th><th>Ações</th></tr></thead>
            <tbody>
            @forelse($licencas as $licenca)
                <tr>
                    <td>{{ $licenca->cliente?->nome ?? '-' }}</td>
                    <td>{{ $licenca->plano?->nome ?? '-' }}</td>
                    <td><span class="badge {{ $licenca->status }}">{{ $licenca->status }}</span></td>
                    <td><code>{{ $licenca->chave }}</code></td>
                    <td>{{ $licenca->vence_em?->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $licenca->renovacao_automatica ? 'automática' : 'manual' }}<br><small>{{ $licenca->pagamentos_count }} pagamento(s)</small></td>
                    <td class="actions-cell">
                        <a class="btn secondary small" href="{{ route('licencas-portal.index',['licenca_edit'=>$licenca->id]).'#licencas' }}">Editar / plano</a>
                        <form method="post" action="{{ route('licencas-portal.licencas.bloqueio',$licenca) }}">@csrf<button class="small {{ $licenca->status === 'bloqueada' ? '' : 'danger' }}">{{ $licenca->status === 'bloqueada' ? 'Desbloquear' : 'Bloquear' }}</button></form>
                        <form class="inline" method="post" action="{{ route('licencas-portal.licencas.renovar',$licenca) }}">@csrf<input type="number" name="meses" min="1" max="24" value="1" title="Meses"><input type="number" name="valor" min="0" step="0.01" value="{{ number_format(($licenca->plano?->preco_centavos ?? 0)/100,2,'.','') }}" title="Valor"><button class="small">Renovar</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Nenhuma licenca emitida.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Clientes cadastrados</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Nome</th><th>Documento</th><th>Status</th><th>Licencas</th><th>Ação</th></tr></thead>
                <tbody>
                @forelse($clientes as $cliente)
                    <tr><td>{{ $cliente->nome }}<br><small>{{ $cliente->email ?: 'sem e-mail' }}</small></td><td>{{ $cliente->documento ?: '-' }}</td><td><span class="badge {{ $cliente->status }}">{{ $cliente->status }}</span><br><small>Minha Licença: {{ $cliente->portal_ativo ? 'liberado' : 'bloqueado' }}</small></td><td>{{ $cliente->licencas_count }}</td><td><a class="btn secondary small" href="{{ route('licencas-portal.index',['cliente_edit'=>$cliente->id]).'#clientes' }}">Editar</a></td></tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum cliente cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="panel">
            <h2>Planos cadastrados</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Plano</th><th>Limites</th><th>Modulos</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                @forelse($planos as $plano)
                    <tr>
                        <td>{{ $plano->nome }}<br><small class="muted">{{ $plano->codigo }}</small></td>
                        <td>{{ $plano->max_lojas ?: 'lojas livre' }} lojas<br>{{ $plano->max_usuarios ?: 'usuarios livre' }} usuarios</td>
                        <td>{{ implode(', ', $plano->modulos_json ?: []) ?: 'todos' }}</td>
                        <td><span class="badge {{ $plano->ativo ? 'ativo' : 'bloqueada' }}">{{ $plano->ativo ? 'ativo' : 'inativo' }}</span></td>
                        <td><a class="btn secondary small" href="{{ route('licencas-portal.index',['plano_edit'=>$plano->id]).'#planos' }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum plano cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    <section class="panel" id="pagamentos">
        <h2>{{ $pagamentoEdit ? 'Editar cobrança ou pagamento' : 'Registrar cobrança ou pagamento' }}</h2>
        <form class="form-grid" method="post" action="{{ route('licencas-portal.pagamentos.salvar') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $pagamentoEdit?->id }}">
            <label>Licença<select name="licenca_id" required><option value="">Selecione</option>@foreach($licencas as $licenca)<option value="{{ $licenca->id }}" @selected(old('licenca_id',$pagamentoEdit?->licenca_id)==$licenca->id)>{{ $licenca->cliente?->nome }} · {{ $licenca->plano?->nome }}</option>@endforeach</select></label>
            <label>Novo plano (opcional)<select name="plano_id"><option value="">Manter plano atual</option>@foreach($planos as $plano)<option value="{{ $plano->id }}" @selected(old('plano_id',$pagamentoEdit?->plano_id)==$plano->id)>{{ $plano->nome }}</option>@endforeach</select></label>
            <label>Gateway<select name="gateway">@foreach(['manual','asaas','pagbank','mercadopago','stripe','outro'] as $gateway)<option @selected(old('gateway',$pagamentoEdit?->gateway ?? 'manual')===$gateway)>{{ $gateway }}</option>@endforeach</select></label>
            <label>Referência externa<input name="referencia_externa" maxlength="160" value="{{ old('referencia_externa',$pagamentoEdit?->referencia_externa) }}" placeholder="ID informado pelo gateway"></label>
            <label>Valor<input type="number" name="valor" min="0" step="0.01" required value="{{ old('valor',$pagamentoEdit ? number_format($pagamentoEdit->valor_centavos/100,2,'.','') : '0.00') }}"></label>
            <label>Status<select name="status">@foreach(['pendente','pago','cancelado','estornado','falhou'] as $status)<option value="{{ $status }}" @selected(old('status',$pagamentoEdit?->status ?? 'pendente')===$status)>{{ $status }}</option>@endforeach</select></label>
            <label>Meses de renovação<input type="number" name="meses_renovacao" min="1" max="24" value="{{ old('meses_renovacao',$pagamentoEdit?->meses_renovacao ?? 1) }}" required></label>
            <label>Vencimento<input type="date" name="vencimento" value="{{ old('vencimento',$pagamentoEdit?->vencimento?->format('Y-m-d') ?? now()->addDays(5)->format('Y-m-d')) }}"></label>
            <label class="span-2">Link de pagamento<input type="url" name="link_pagamento" maxlength="1000" value="{{ old('link_pagamento',$pagamentoEdit?->link_pagamento) }}" placeholder="https://gateway.example/pagar/..."></label>
            <div class="span-2"><button>{{ $pagamentoEdit ? 'Salvar cobrança' : 'Registrar pagamento' }}</button> @if($pagamentoEdit)<a class="btn secondary" href="{{ url('/licencas-portal#pagamentos') }}">Cancelar</a>@endif</div>
        </form>
        <p class="help">Quando um pagamento é confirmado, a licença é ativada e o vencimento é estendido apenas uma vez. Webhook: <code>{{ $webhookUrl }}</code></p>
    </section>

    <section class="panel">
        <h2>Histórico de pagamentos</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Data</th><th>Cliente</th><th>Gateway / referência</th><th>Valor</th><th>Status</th><th>Renovação</th><th>Ação</th></tr></thead>
            <tbody>
            @forelse($pagamentos as $pagamento)
                <tr>
                    <td>{{ $pagamento->criado_em?->format('d/m/Y H:i') ?? $pagamento->criado_em }}</td>
                    <td>{{ $pagamento->cliente?->nome ?? '-' }}<br><small>{{ $pagamento->plano?->nome ?? '-' }}</small></td>
                    <td>{{ $pagamento->gateway }}<br><code>{{ $pagamento->referencia_externa }}</code>@if($pagamento->link_pagamento)<br><a href="{{ $pagamento->link_pagamento }}" target="_blank" rel="noopener">Abrir cobrança</a>@endif</td>
                    <td>R$ {{ number_format($pagamento->valor_centavos/100,2,',','.') }}</td>
                    <td><span class="badge {{ $pagamento->status }}">{{ $pagamento->status }}</span><br><small>{{ $pagamento->pago_em?->format('d/m/Y H:i') }}</small></td>
                    <td>{{ $pagamento->meses_renovacao }} mês(es)<br><small>{{ $pagamento->renovado_em ? 'aplicada em '.$pagamento->renovado_em->format('d/m/Y H:i') : 'não aplicada' }}</small></td>
                    <td class="actions-cell"><a class="btn secondary small" href="{{ route('licencas-portal.index',['pagamento_edit'=>$pagamento->id]).'#pagamentos' }}">Editar / inserir link</a>@if($pagamento->status !== 'pago')<form method="post" action="{{ route('licencas-portal.pagamentos.confirmar',$pagamento) }}">@csrf<button class="small">Confirmar pagamento</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Nenhum pagamento registrado.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel">
        <h2>Ultimas validacoes</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Quando</th><th>Cliente</th><th>Status</th><th>Chave</th><th>Instancia</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->criado_em }}</td>
                    <td>{{ $log->licenca?->cliente?->nome ?? '-' }}</td>
                    <td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td>
                    <td><code>{{ $log->chave_mascarada ?: '-' }}</code></td>
                    <td>{{ $log->instancia_id ?: '-' }}</td>
                    <td>{{ $log->ip ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nenhuma validacao registrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
<script>
    function rentalApplyTheme(theme){
        const next = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.dataset.theme = next;
        localStorage.setItem('rental-theme', next);
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const dark = next === 'dark';
            button.textContent = dark ? 'Tema escuro' : 'Tema claro';
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute('aria-label', dark ? 'Alternar para tema claro' : 'Alternar para tema escuro');
        });
    }
    rentalApplyTheme(document.documentElement.dataset.theme || 'light');
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => rentalApplyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'));
    });
</script>
</body>
</html>
