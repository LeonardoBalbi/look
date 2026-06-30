<div class="crm-workspace">
    <div class="crm-quickbar">
        <div>
            <span>Atendimento</span>
            <strong>{{ $crmCliente?->nome ?? 'Selecione um cliente' }}</strong>
            <small>{{ $crmCliente ? (($crmEtapas[$crmCliente->crm_etapa] ?? $crmCliente->crm_etapa).' - '.($crmCliente->whatsapp ?: $crmCliente->email ?: 'sem contato')) : 'Abra um cliente na lista para registrar contato, tarefa ou etapa.' }}</small>
        </div>
        <div class="crm-quick-actions">
            @if($crmCliente?->whatsapp)
                <a class="btn secondary" target="_blank" href="https://wa.me/55{{ preg_replace('/\D/','',$crmCliente->whatsapp) }}">WhatsApp</a>
            @endif
            @if($crmCliente?->email)
                <a class="btn secondary" href="mailto:{{ $crmCliente->email }}">E-mail</a>
            @endif
            @if($crmCliente)
                <a class="btn secondary" href="{{ route('locx.index', ['page' => 'financeiro']) }}">Financeiro</a>
            @endif
        </div>
    </div>

    <div class="cards crm-stage-cards">
        @foreach($crmEtapas as $etapa => $label)
            <div class="metric {{ $etapa === 'em_cobranca' || $etapa === 'recuperacao' ? 'danger' : ($etapa === 'contrato_ativo' ? 'ok' : 'warn') }}">
                <span>{{ $label }}</span>
                <strong>{{ $crmPipeline[$etapa] ?? 0 }}</strong>
                <small>clientes</small>
            </div>
        @endforeach
    </div>

    <div class="grid side crm-layout">
        <div class="panel">
            <div class="section-head">
                <div>
                    <h2>Clientes no CRM</h2>
                    <p class="crm-subtitle">Abra um cliente para acompanhar cobrancas, contatos e tarefas.</p>
                </div>
                <div class="filters">
                    <input id="crmClientSearch" type="search" placeholder="Buscar nome, telefone ou e-mail">
                </div>
            </div>

            <div class="table-wrap">
                <table class="crm-client-table">
                    <tr><th>Cliente</th><th>Etapa</th><th>Saldo</th><th>Atrasos</th><th>Proxima acao</th><th></th></tr>
                    @forelse($crmClientes as $linha)
                        @php($clienteLinha = $linha['cliente'])
                        <tr class="{{ $crmCliente?->id === $clienteLinha->id ? 'is-selected' : '' }}" data-crm-client="{{ \Illuminate\Support\Str::lower($clienteLinha->nome.' '.$clienteLinha->whatsapp.' '.$clienteLinha->email) }}">
                            <td>
                                <strong>{{ $clienteLinha->nome }}</strong>
                                <small>{{ $clienteLinha->whatsapp ?: $clienteLinha->email ?: '-' }}</small>
                            </td>
                            <td>{!! \App\Support\Locx::status($crmEtapas[$clienteLinha->crm_etapa] ?? $clienteLinha->crm_etapa) !!}</td>
                            <td><strong class="{{ $linha['saldo_aberto'] > 0 ? 'crm-money-open' : 'crm-money-ok' }}">{{ \App\Support\Locx::moeda($linha['saldo_aberto']) }}</strong></td>
                            <td>{!! $linha['atrasadas'] > 0 ? '<span class="tag danger">'.$linha['atrasadas'].'</span>' : '<span class="tag ok">0</span>' !!}</td>
                            <td>
                                @if($linha['proxima_tarefa'])
                                    <strong>{{ $linha['proxima_tarefa']->titulo }}</strong>
                                    <small>{{ $linha['proxima_tarefa']->prazo_em?->format('d/m/Y H:i') ?: 'sem prazo' }}</small>
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
            <div class="section-head">
                <div>
                    <h2>Tarefas abertas</h2>
                    <p class="crm-subtitle">Pendencias de follow-up e cobranca.</p>
                </div>
            </div>
            <div class="crm-task-list">
                @forelse($crmTarefasAbertas as $tarefa)
                    <div class="crm-task">
                        <div>
                            <strong>{{ $tarefa->titulo }}</strong>
                            <span>{{ $tarefa->cliente?->nome }} - {{ $tarefa->prazo_em?->format('d/m/Y H:i') ?: 'sem prazo' }}</span>
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
                        <p>{{ $crmCliente->whatsapp ?: '-' }} - {{ $crmCliente->email ?: '-' }}</p>
                    </div>
                    <a class="btn secondary" href="{{ route('locx.index', ['page' => 'clientes', 'edit' => $crmCliente->id]) }}">Editar cadastro</a>
                </div>

                <div class="crm-contact-strip">
                    <div><span>Contato</span><strong>{{ $crmCliente->whatsapp ?: 'sem WhatsApp' }}</strong></div>
                    <div><span>E-mail</span><strong>{{ $crmCliente->email ?: 'sem e-mail' }}</strong></div>
                    <div><span>Ultima movimentacao</span><strong>{{ $crmTimeline->first()['data'] ?? null ? \Carbon\Carbon::parse($crmTimeline->first()['data'])->format('d/m/Y H:i') : 'sem historico' }}</strong></div>
                </div>

                <form method="post" action="{{ route('locx.crm.cliente', $crmCliente) }}" class="form-grid crm-stage-form">
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

                <h2>Historico do cliente</h2>
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
                <h2>Registrar contato</h2>
                <form method="post" action="{{ route('locx.crm.notas.salvar') }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="cliente_id" value="{{ $crmCliente->id }}">
                    <label>Tipo<select name="tipo"><option value="nota">nota</option><option value="ligacao">ligacao</option><option value="whatsapp">WhatsApp</option><option value="email">e-mail</option><option value="visita">visita</option><option value="negociacao">negociacao</option></select></label>
                    <label class="span-3">Texto<textarea name="texto" required></textarea></label>
                    <div class="span-3"><button type="submit">Salvar nota</button></div>
                </form>

                <hr>

                <h2>Criar tarefa</h2>
                <form method="post" action="{{ route('locx.crm.tarefas.salvar') }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="cliente_id" value="{{ $crmCliente->id }}">
                    <label class="span-2">Titulo<input name="titulo" required placeholder="Ex.: Reenviar PIX e confirmar pagamento"></label>
                    <label>Tipo<select name="tipo"><option value="follow_up">follow-up</option><option value="ligacao">ligacao</option><option value="whatsapp">WhatsApp</option><option value="email">e-mail</option><option value="cobranca">cobranca</option><option value="recolhimento">recolhimento</option></select></label>
                    <label>Prazo<input type="datetime-local" name="prazo_em"></label>
                    <label class="span-3">Observacao<textarea name="observacao"></textarea></label>
                    <div class="span-3"><button type="submit">Criar tarefa</button></div>
                </form>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('crmClientSearch');
    if (! input) return;

    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-crm-client]').forEach((row) => {
            row.hidden = term !== '' && ! row.dataset.crmClient.includes(term);
        });
    });
});
</script>
