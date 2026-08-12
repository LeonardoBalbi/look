<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha Licença | {{ $branding['product_name'] }}</title>
    <style>
        :root{--bg:#f4f7fb;--panel:#fff;--ink:#152033;--muted:#64748b;--line:#dbe3ee;--brand:#0f766e;--ok:#047857;--warn:#b54708;--danger:#b42318}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,Segoe UI,Arial,sans-serif}.shell{max-width:1120px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:24px}.top h1{margin:0 0 5px}.top p{margin:0;color:var(--muted)}button,.btn{display:inline-flex;border:0;border-radius:7px;background:var(--brand);color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#fff;color:var(--brand);border:1px solid var(--line)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:18px}.panel h2{margin:0 0 14px}.license-head{display:flex;justify-content:space-between;gap:12px}.badge{border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800;background:#fff2cc;color:var(--warn)}.badge.ativa,.badge.pago,.badge.trial,.badge.teste{background:#dcfae6;color:var(--ok)}.badge.bloqueada,.badge.vencida,.badge.cancelado,.badge.falhou{background:#fee4e2;color:var(--danger)}.facts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0}.fact{background:#f8fafc;border-radius:8px;padding:11px}.fact small{display:block;color:var(--muted)}.fact strong{font-size:17px}.inline{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.inline label{display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--muted)}input,select{border:1px solid var(--line);border-radius:7px;padding:9px;font:inherit}.notice{padding:12px 14px;border-radius:8px;background:#ecfdf3;color:#067647;margin-bottom:16px}.alert{padding:12px 14px;border-radius:8px;background:#fff1f0;color:var(--danger);margin-bottom:16px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:left;border-bottom:1px solid var(--line);padding:11px;font-size:13px}th{color:var(--muted);background:#f8fafc}@media(max-width:760px){.grid,.facts{grid-template-columns:1fr}.top{align-items:flex-start}.shell{padding:16px}}
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <div><h1>Minha Licença</h1><p>{{ $cliente->nome }} · {{ $branding['product_name'] }}</p></div>
        <form method="post" action="{{ route('licenca-cliente.logout') }}">@csrf<button class="btn secondary">Sair</button></form>
    </header>
    @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    <div class="grid">
    @forelse($licencas as $licenca)
        <section class="panel">
            <div class="license-head"><div><small>Plano contratado</small><h2>{{ $licenca->plano?->nome ?? 'Sem plano' }}</h2></div><span class="badge {{ $licenca->status }}">{{ $licenca->status }}</span></div>
            <div class="facts">
                <div class="fact"><small>Vencimento</small><strong>{{ $licenca->vence_em?->format('d/m/Y') ?: 'não informado' }}</strong></div>
                <div class="fact"><small>Lojas</small><strong>{{ $licenca->plano?->max_lojas ?: 'livre' }}</strong></div>
                <div class="fact"><small>Usuários</small><strong>{{ $licenca->plano?->max_usuarios ?: 'livre' }}</strong></div>
            </div>
            <p><strong>Valor mensal:</strong> R$ {{ number_format(($licenca->plano?->preco_centavos ?? 0)/100,2,',','.') }}<br><strong>Renovação:</strong> {{ $licenca->renovacao_automatica ? 'automática' : 'manual' }}</p>
            <form class="inline" method="post" action="{{ route('licenca-cliente.renovacoes.solicitar') }}">
                @csrf<input type="hidden" name="licenca_id" value="{{ $licenca->id }}">
                <label>Período<select name="meses">@foreach([1,3,6,12] as $meses)<option value="{{ $meses }}">{{ $meses }} mês(es)</option>@endforeach</select></label>
                <button>Solicitar renovação</button>
            </form>
        </section>
    @empty
        <section class="panel"><h2>Nenhuma licença</h2><p>Não existe licença vinculada a esta empresa. Fale com o suporte.</p></section>
    @endforelse
    </div>

    <section class="panel">
        <h2>Cobranças e pagamentos</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Data</th><th>Plano</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Pagamento</th></tr></thead>
            <tbody>
            @forelse($pagamentos as $pagamento)
                <tr>
                    <td>{{ $pagamento->criado_em?->format('d/m/Y') }}</td><td>{{ $pagamento->plano?->nome ?? '-' }}</td>
                    <td>R$ {{ number_format($pagamento->valor_centavos/100,2,',','.') }}</td><td>{{ $pagamento->vencimento?->format('d/m/Y') ?: '-' }}</td>
                    <td><span class="badge {{ $pagamento->status }}">{{ $pagamento->status }}</span></td>
                    <td>@if($pagamento->status === 'pendente' && $pagamento->link_pagamento)<a class="btn" href="{{ $pagamento->link_pagamento }}" target="_blank" rel="noopener">Pagar agora</a>@elseif($pagamento->status === 'pendente')<span>Aguardando emissão do link</span>@elseif($pagamento->status === 'pago')<span>Pago em {{ $pagamento->pago_em?->format('d/m/Y') }}</span>@else — @endif</td>
                </tr>
            @empty<tr><td colspan="6">Nenhuma cobrança ou pagamento registrado.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
</body>
</html>
