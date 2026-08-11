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
        .theme-toggle{min-width:116px;justify-content:center}
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

    <div class="api-box">
        <div>
            <strong>URL da API de licenciamento</strong><br>
            <code>{{ $apiUrl }}</code>
        </div>
        <a class="btn secondary" href="#licencas">Gerar chave</a>
    </div>

    <section class="cards">
        <div class="metric"><span>Clientes</span><strong>{{ $clientes->count() }}</strong></div>
        <div class="metric"><span>Planos</span><strong>{{ $planos->count() }}</strong></div>
        <div class="metric"><span>Licencas</span><strong>{{ $licencas->count() }}</strong></div>
        <div class="metric"><span>Checks recentes</span><strong>{{ $logs->count() }}</strong></div>
    </section>

    <div class="grid">
        <section class="panel" id="clientes">
            <h2>Novo cliente</h2>
            <form class="form-grid" method="post" action="{{ route('licencas-portal.clientes.salvar') }}">
                @csrf
                <label class="span-2">Nome<input name="nome" required maxlength="180" placeholder="Locadora Exemplo"></label>
                <label>Documento<input name="documento" maxlength="30" placeholder="00.000.000/0001-00"></label>
                <label>Status<select name="status"><option value="ativo">ativo</option><option value="bloqueado">bloqueado</option><option value="cancelado">cancelado</option></select></label>
                <label>E-mail<input type="email" name="email" maxlength="160"></label>
                <label>Telefone<input name="telefone" maxlength="40"></label>
                <div class="span-2"><button>Salvar cliente</button></div>
            </form>
        </section>

        <section class="panel" id="planos">
            <h2>Novo plano</h2>
            <form class="form-grid" method="post" action="{{ route('licencas-portal.planos.salvar') }}">
                @csrf
                <label>Codigo<input name="codigo" required maxlength="80" placeholder="profissional"></label>
                <label>Nome<input name="nome" required maxlength="120" placeholder="Profissional"></label>
                <label>Preco mensal<input type="number" step="0.01" min="0" name="preco" value="0"></label>
                <label>Ativo<select name="ativo"><option value="1">sim</option><option value="0">nao</option></select></label>
                <label>Limite lojas<input type="number" min="1" name="max_lojas" placeholder="5"></label>
                <label>Limite usuarios<input type="number" min="1" name="max_usuarios" placeholder="20"></label>
                <label class="span-2">Modulos<textarea name="modulos" placeholder="pix, whatsapp, multi_loja, crm, financeiro"></textarea></label>
                <div class="span-2"><button>Salvar plano</button></div>
            </form>
        </section>
    </div>

    <section class="panel" id="licencas">
        <h2>Nova licenca</h2>
        <form class="form-grid" method="post" action="{{ route('licencas-portal.licencas.salvar') }}">
            @csrf
            <label>Cliente<select name="cliente_id" required><option value="">Selecione</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach</select></label>
            <label>Plano<select name="plano_id" required><option value="">Selecione</option>@foreach($planos as $plano)<option value="{{ $plano->id }}">{{ $plano->nome }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="ativa">ativa</option><option value="trial">trial</option><option value="teste">teste</option><option value="pendente">pendente</option><option value="bloqueada">bloqueada</option><option value="vencida">vencida</option></select></label>
            <label>Vence em<input type="date" name="vence_em" value="{{ now()->addMonth()->format('Y-m-d') }}"></label>
            <label>Tolerancia offline<input type="number" min="1" max="60" name="tolerancia_offline_dias" value="7"></label>
            <label>Chave opcional<input name="chave" maxlength="120" placeholder="vazio gera automatico"></label>
            <label class="span-2">Mensagem<input name="mensagem" maxlength="500" placeholder="Licenca liberada pelo portal."></label>
            <div class="span-2"><button>Salvar licenca</button></div>
        </form>
    </section>

    <section class="panel">
        <h2>Licencas emitidas</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Cliente</th><th>Plano</th><th>Status</th><th>Chave</th><th>Vencimento</th><th>Instancia</th><th>Ultimo check</th></tr></thead>
            <tbody>
            @forelse($licencas as $licenca)
                <tr>
                    <td>{{ $licenca->cliente?->nome ?? '-' }}</td>
                    <td>{{ $licenca->plano?->nome ?? '-' }}</td>
                    <td><span class="badge {{ $licenca->status }}">{{ $licenca->status }}</span></td>
                    <td><code>{{ $licenca->chave }}</code></td>
                    <td>{{ $licenca->vence_em?->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $licenca->instancia_id ?: '-' }}</td>
                    <td>{{ $licenca->ultimo_check_em?->format('d/m/Y H:i') ?: '-' }}</td>
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
                <thead><tr><th>Nome</th><th>Documento</th><th>Status</th><th>Licencas</th></tr></thead>
                <tbody>
                @forelse($clientes as $cliente)
                    <tr><td>{{ $cliente->nome }}</td><td>{{ $cliente->documento ?: '-' }}</td><td><span class="badge {{ $cliente->status }}">{{ $cliente->status }}</span></td><td>{{ $cliente->licencas_count }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">Nenhum cliente cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="panel">
            <h2>Planos cadastrados</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Plano</th><th>Limites</th><th>Modulos</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($planos as $plano)
                    <tr>
                        <td>{{ $plano->nome }}<br><small class="muted">{{ $plano->codigo }}</small></td>
                        <td>{{ $plano->max_lojas ?: 'lojas livre' }} lojas<br>{{ $plano->max_usuarios ?: 'usuarios livre' }} usuarios</td>
                        <td>{{ implode(', ', $plano->modulos_json ?: []) ?: 'todos' }}</td>
                        <td><span class="badge {{ $plano->ativo ? 'ativo' : 'bloqueada' }}">{{ $plano->ativo ? 'ativo' : 'inativo' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Nenhum plano cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

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
