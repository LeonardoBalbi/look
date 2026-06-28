<div class="cards">
    @foreach($crmEtapas as $etapa => $label)
        <div class="metric {{ $etapa === 'em_cobranca' || $etapa === 'recuperacao' ? 'danger' : ($etapa === 'contrato_ativo' ? 'ok' : 'warn') }}">
            <span>{{ $label }}</span>
            <strong>{{ $crmPipeline[$etapa] ?? 0 }}</strong>
            <small>clientes no CRM</small>
        </div>
    @endforeach
</div>

<div class="grid side crm-layout">
    <div class="panel">
        <div class="section-head">
            <h2>Clientes no CRM</h2>
            <div class="filters"><input type="search" placeholder="Buscar cliente"></div>
        </div>
        <div class="table-wrap">
            <table>
                <tr><th>Cliente</th><th>Etapa</th><th>Saldo</th><th>Atrasos</th><th>Próxima ação</th><th></th></tr>
                @forelse($crmClientes as $linha)
                    @php($clienteLinha = $linha['cliente'])
                    <tr>
                        <td><strong>{{ $clienteLinha->nome }}</strong><br><small>{{ $clienteLinha->whatsapp ?: $clienteLinha->email ?: '-' }}</small></td>
                        <td>{!! \App\Support\Locx::status($crmEtapas[$clienteLinha->crm_etapa] ?? $clienteLinha->crm_etapa) !!}</td>
                        <td>{{ \App\Support\Locx::moeda($linha['saldo_aberto']) }}</td>
                        <td>{{ $linha['atrasadas'] }}</td>
                        <td>
                            @if($linha['proxima_tarefa'])
                                {{ $linha['proxima_tarefa']->titulo }}<br><small>{{ $linha['proxima_tarefa']->prazo_em?->format('d/m/Y H:i') ?: 'sem prazo' }}</small>
                            @else
                                <small>sem tarefa aberta</small>
                            @endif
                        </td>
                        <td><a class="btn secondary" href="{{ route('locx.index', ['page' => 'crm', 'cliente' => $clienteLinha->id]) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum cliente encontrado.</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Tarefas abertas</h2>
        <div class="crm-task-list">
            @forelse($crmTarefasAbertas as $tarefa)
                <div class="crm-task">
                    <div>
                        <strong>{{ $tarefa->titulo }}</strong>
                        <span>{{ $tarefa->cliente?->nome }} · {{ $tarefa->prazo_em?->format('d/m/Y H:i') ?: 'sem prazo' }}</span>
                    </div>
                    <form method="post" action="{{ route('locx.crm.tarefas.concluir', $tarefa) }}">
                        @csrf
                        <button class="btn secondary" type="submit">Concluir</button>
                    </form>
                </div>
            @empty
                <p class="empty">Sem tarefas abertas.</p>
            @endforelse
        </div>
    </div>
</div>

@if($crmCliente)
    <div class="grid side crm-detail">
        <div class="panel">
            <div class="section-head">
                <div>
                    <h2>{{ $crmCliente->nome }}</h2>
                    <p>{{ $crmCliente->whatsapp ?: '-' }} · {{ $crmCliente->email ?: '-' }}</p>
                </div>
                <a class="btn secondary" href="{{ route('locx.index', ['page' => 'clientes', 'edit' => $crmCliente->id]) }}">Editar cadastro</a>
            </div>

            <form method="post" action="{{ route('locx.crm.cliente', $crmCliente) }}" class="form-grid">
                @csrf
                <label>Etapa do CRM
                    <select name="crm_etapa">
                        @foreach($crmEtapas as $etapa => $label)
                            <option value="{{ $etapa }}" @selected($crmCliente->crm_etapa === $etapa)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div><button class="btn secondary" type="submit">Atualizar etapa</button></div>
            </form>

            <hr>

            <h2>Timeline</h2>
            <div class="crm-timeline">
                @forelse($crmTimeline as $item)
                    <div class="crm-event">
                        <span class="tag {{ $item['status'] }}">{{ $item['tipo'] }}</span>
                        <div>
                            <strong>{{ $item['titulo'] }}</strong>
                            <p>{{ $item['texto'] }}</p>
                            <small>{{ \Carbon\Carbon::parse($item['data'])->format('d/m/Y H:i') }}</small>
                        </div>
                    </div>
                @empty
                    <p class="empty">Nenhum evento ainda para este cliente.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h2>Nova nota</h2>
            <form method="post" action="{{ route('locx.crm.notas.salvar') }}" class="form-grid">
                @csrf
                <input type="hidden" name="cliente_id" value="{{ $crmCliente->id }}">
                <label>Tipo<select name="tipo"><option value="nota">nota</option><option value="ligacao">ligação</option><option value="whatsapp">WhatsApp</option><option value="email">e-mail</option><option value="visita">visita</option><option value="negociacao">negociação</option></select></label>
                <label class="span-3">Texto<textarea name="texto" required></textarea></label>
                <div class="span-3"><button type="submit">Salvar nota</button></div>
            </form>

            <hr>

            <h2>Nova tarefa</h2>
            <form method="post" action="{{ route('locx.crm.tarefas.salvar') }}" class="form-grid">
                @csrf
                <input type="hidden" name="cliente_id" value="{{ $crmCliente->id }}">
                <label class="span-2">Título<input name="titulo" required placeholder="Ex.: Reenviar PIX e confirmar pagamento"></label>
                <label>Tipo<select name="tipo"><option value="follow_up">follow-up</option><option value="ligacao">ligação</option><option value="whatsapp">WhatsApp</option><option value="email">e-mail</option><option value="cobranca">cobrança</option><option value="recolhimento">recolhimento</option></select></label>
                <label>Prazo<input type="datetime-local" name="prazo_em"></label>
                <label class="span-3">Observação<textarea name="observacao"></textarea></label>
                <div class="span-3"><button type="submit">Criar tarefa</button></div>
            </form>
        </div>
    </div>
@endif
