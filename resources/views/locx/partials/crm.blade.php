@php
    $statusLabels = [
        'novo' => 'Novo',
        'aguardando_humano' => 'Aguardando equipe',
        'em_atendimento' => 'Em atendimento',
        'respondido' => 'Aguardando cliente',
        'fechado' => 'Resolvido',
        'cancelado' => 'Cancelado',
    ];
    $statusClasses = [
        'novo' => 'is-new',
        'aguardando_humano' => 'is-attention',
        'em_atendimento' => 'is-active',
        'respondido' => 'is-waiting',
        'fechado' => 'is-resolved',
        'cancelado' => 'is-muted',
    ];
    $atendimentoAtual = $crmAtendimentoSelecionado;
    $clienteAtual = $crmCliente;
    $nomesPartes = $clienteAtual ? preg_split('/\s+/', trim($clienteAtual->nome)) : [];
    $iniciaisCliente = collect($nomesPartes)->filter()->take(2)->map(fn ($parte) => strtoupper(substr($parte, 0, 1)))->implode('') ?: 'CL';
    $opcoesTriagem = ['Debito/fatura', 'PIX/comprovante', 'Contrato', 'Moto/manutencao', 'Documentos', 'Multa', 'Falar com a loja'];
@endphp

<div class="crm-desk" data-crm-desk data-csrf="{{ csrf_token() }}">
    <section class="crm-desk-hero">
        <div>
            <span class="crm-eyebrow">CENTRAL DE RELACIONAMENTO</span>
            <span class="sr-only">Clientes no CRM</span>
            <span class="sr-only">Chats do portal</span>
            <h2>Atendimento organizado, humano e com contexto</h2>
            <p>Converse com o cliente, acompanhe o histórico e resolva pendências sem sair do CRM.</p>
        </div>
        <div class="crm-live-indicator"><i></i> Atualização automática ativa</div>
    </section>

    <section class="crm-support-metrics" aria-label="Indicadores do atendimento">
        <article><span>Clientes com chat</span><strong data-crm-metric="abertos">{{ $crmPortalMetricas['abertos'] ?? 0 }}</strong><small>Uma linha por cliente</small></article>
        <article class="attention"><span>Aguardando equipe</span><strong data-crm-metric="aguardando_humano">{{ $crmPortalMetricas['aguardando_humano'] ?? 0 }}</strong><small>Precisam de resposta</small></article>
        <article class="active"><span>Em atendimento</span><strong data-crm-metric="em_atendimento">{{ $crmPortalMetricas['em_atendimento'] ?? 0 }}</strong><small>Assumidas pela equipe</small></article>
        <article class="waiting"><span>Aguardando cliente</span><strong data-crm-metric="aguardando_cliente">{{ $crmPortalMetricas['aguardando_cliente'] ?? 0 }}</strong><small>Equipe já respondeu</small></article>
        <article class="task"><span>Tarefas vencidas</span><strong>{{ $crmTarefasVencidas }}</strong><small>Follow-ups pendentes</small></article>
    </section>

    <section class="crm-service-workspace">
        <aside class="crm-conversation-sidebar panel">
            <header class="crm-sidebar-head">
                <div>
                    <span class="crm-eyebrow">CAIXA DE ENTRADA</span>
                    <h3>Clientes com atendimento</h3>
                </div>
                <span class="crm-queue-count">{{ $crmPortalInbox->count() }}</span>
            </header>

            <label class="crm-search-box">
                <span aria-hidden="true">⌕</span>
                <input type="search" placeholder="Buscar cliente ou histórico" data-crm-inbox-search>
            </label>

            <div class="crm-inbox-filters" role="tablist" aria-label="Filtrar conversas">
                <button type="button" class="is-active" data-crm-inbox-filter="all">Todas</button>
                <button type="button" data-crm-inbox-filter="attention">Pendentes</button>
                <button type="button" data-crm-inbox-filter="em_atendimento">Em atendimento</button>
                <button type="button" data-crm-inbox-filter="respondido">Aguardando</button>
                <button type="button" data-crm-inbox-filter="fechado">Resolvidas</button>
            </div>

            <div class="crm-conversation-list"
                 data-crm-portal-inbox
                 data-selected-id="{{ $atendimentoAtual?->id }}"
                 data-selected-client-id="{{ $clienteAtual?->id }}"
                 data-sync-url="{{ route('locx.crm.portal-atendimentos.inbox-sync') }}"
                 data-signature="{{ $crmPortalInboxSignature }}">
                @forelse($crmPortalInbox as $atendimento)
                    @php
                        $ultimaMensagem = $atendimento->ultimaMensagem;
                        $ultimaCliente = $atendimento->ultimaMensagemCliente;
                        $naoLidaAtual = $ultimaCliente && (!$atendimento->lido_em || $ultimaCliente->criado_em?->gt($atendimento->lido_em));
                        $naoLidos = (int) ($atendimento->cliente_nao_lidos ?? ($naoLidaAtual ? 1 : 0));
                        $totalAtendimentos = (int) ($atendimento->cliente_total_atendimentos ?? 1);
                        $atendimentosAbertos = (int) ($atendimento->cliente_atendimentos_abertos ?? 0);
                        $nomeCliente = $atendimento->cliente?->nome ?? 'Cliente';
                        $iniciais = collect(preg_split('/\s+/', trim($nomeCliente)))->filter()->take(2)->map(fn ($parte) => strtoupper(substr($parte, 0, 1)))->implode('') ?: 'CL';
                        $textoBusca = \Illuminate\Support\Str::lower($nomeCliente.' '.($atendimento->cliente_assuntos_busca ?? $atendimento->assunto).' '.($ultimaMensagem?->mensagem ?: $atendimento->mensagem));
                    @endphp
                    <a href="{{ route('locx.index', ['page' => 'crm', 'atendimento' => $atendimento->id]) }}#crmAtendimentoAtual"
                       class="crm-conversation-item {{ $clienteAtual?->id === $atendimento->cliente_id ? 'is-selected' : '' }} {{ $naoLidos > 0 ? 'has-unread' : '' }}"
                       data-crm-inbox-item
                       data-status="{{ $atendimento->status }}"
                       data-search="{{ $textoBusca }}"
                       data-cliente-id="{{ $atendimento->cliente_id }}"
                       data-atendimento-id="{{ $atendimento->id }}">
                        <span class="crm-contact-avatar">{{ $iniciais }}</span>
                        <span class="crm-conversation-content">
                            <span class="crm-conversation-title">
                                <strong>{{ $nomeCliente }}</strong>
                                <time>{{ ($ultimaMensagem?->criado_em ?: $atendimento->criado_em)?->format('H:i') }}</time>
                            </span>
                            <span class="crm-conversation-preview">{{ $ultimaMensagem?->mensagem ?: $atendimento->mensagem }}</span>
                            <span class="crm-conversation-meta">
                                <em class="crm-status-dot {{ $statusClasses[$atendimento->status] ?? 'is-muted' }}">{{ $statusLabels[$atendimento->status] ?? $atendimento->status }}</em>
                                <small>{{ ($atendimento->canal ?? 'portal') === 'telegram' ? 'Telegram' : 'Portal' }} · {{ ucfirst(str_replace('_', ' ', $atendimento->assunto)) }}</small>
                                @if($atendimento->prioridade === 'alta')<b>Alta prioridade</b>@endif
                            </span>
                            <span class="crm-conversation-history-summary">
                                {{ $totalAtendimentos }} {{ $totalAtendimentos === 1 ? 'atendimento' : 'atendimentos' }} no histórico
                                @if($atendimentosAbertos > 1) · {{ $atendimentosAbertos }} assuntos abertos @endif
                            </span>
                        </span>
                        @if($naoLidos > 0)<span class="crm-unread-badge" aria-label="{{ $naoLidos }} mensagens não lidas">{{ $naoLidos }}</span>@endif
                    </a>
                @empty
                    <div class="crm-empty-state compact">
                        <strong>Nenhuma conversa</strong>
                        <p>Cada cliente aparecerá uma única vez; os chats antigos ficam no histórico.</p>
                    </div>
                @endforelse
            </div>
        </aside>

        <main class="crm-chat-panel panel" id="crmAtendimentoAtual" tabindex="-1">
            @if($atendimentoAtual && $clienteAtual)
                <article class="crm-active-conversation {{ $statusClasses[$atendimentoAtual->status] ?? '' }}"
                         data-crm-chat="{{ $atendimentoAtual->id }}"
                         data-cliente-id="{{ $clienteAtual->id }}"
                         data-sync-url="{{ route('locx.crm.portal-atendimentos.sync', $atendimentoAtual) }}"
                         data-read-url="{{ route('locx.crm.portal-atendimentos.lido', $atendimentoAtual) }}"
                         data-action-url="{{ route('locx.crm.portal-atendimentos.acao', $atendimentoAtual) }}">
                    <header class="crm-chat-header">
                        <div class="crm-chat-person">
                            <span class="crm-contact-avatar large">{{ $iniciaisCliente }}</span>
                            <div>
                                <h3>{{ $clienteAtual->nome }}</h3>
                                <p>{{ $clienteAtual->whatsapp ?: $clienteAtual->email ?: 'Sem contato cadastrado' }}</p>
                            </div>
                        </div>
                        <div class="crm-chat-actions">
                            @if($atendimentoAtual->status === 'fechado')
                                <button type="button" class="btn secondary" data-crm-action="reabrir">Reabrir</button>
                            @else
                                @if(!$atendimentoAtual->atendente_id)
                                    <button type="button" class="btn secondary" data-crm-action="assumir">Assumir</button>
                                @endif
                                <button type="button" class="btn secondary" data-crm-action="aguardar_cliente">Aguardar cliente</button>
                                <button type="button" class="btn crm-resolve-button" data-crm-action="resolver">Concluir</button>
                            @endif
                        </div>
                    </header>

                    <div class="crm-chat-contextbar">
                        <span><small>Status</small><b class="crm-status-pill {{ $statusClasses[$atendimentoAtual->status] ?? '' }}" data-crm-status-label>{{ $statusLabels[$atendimentoAtual->status] ?? $atendimentoAtual->status }}</b></span>
                        <span><small>Canal</small><b>{{ ($atendimentoAtual->canal ?? 'portal') === 'telegram' ? 'Telegram' : 'Portal do cliente' }}</b></span>
                        <span><small>Assunto</small><b>{{ ucfirst(str_replace('_', ' ', $atendimentoAtual->assunto)) }}</b></span>
                        <span><small>Prioridade</small><b class="{{ $atendimentoAtual->prioridade === 'alta' ? 'text-danger' : '' }}">{{ ucfirst($atendimentoAtual->prioridade) }}</b></span>
                        <span><small>Responsável</small><b data-crm-attendant>{{ $atendimentoAtual->atendente?->nome ?: 'Não atribuído' }}</b></span>
                        <span><small>Protocolo</small><b>#{{ $atendimentoAtual->id }}</b></span>
                    </div>

                    <div class="crm-chat-thread" role="log" aria-live="polite" aria-label="Mensagens do atendimento #{{ $atendimentoAtual->id }}">
                        <div class="crm-chat-day"><span>Histórico da conversa</span></div>
                        @forelse($atendimentoAtual->mensagens as $mensagem)
                            @php
                                $remetenteOriginal = \Illuminate\Support\Str::lower(trim((string) $mensagem->remetente));
                                $tipoRemetente = in_array($remetenteOriginal, ['cliente', 'client', 'usuario', 'user', 'portal_cliente'], true)
                                    ? 'cliente'
                                    : (in_array($remetenteOriginal, ['humano', 'atendente', 'admin', 'administrador', 'equipe', 'operador'], true)
                                        ? 'humano'
                                        : 'bot');
                                $nomeRemetente = $tipoRemetente === 'cliente'
                                    ? 'Cliente · '.$clienteAtual->nome
                                    : ($tipoRemetente === 'humano'
                                        ? 'Atendente · '.($mensagem->remetente_nome ?: 'Equipe LOCX')
                                        : 'Assistente · '.$atendimentoAtual->assistente);
                                $textoMensagem = $tipoRemetente === 'cliente' && in_array($mensagem->mensagem, $opcoesTriagem, true)
                                    ? 'Escolheu o assunto: '.$mensagem->mensagem
                                    : $mensagem->mensagem;
                            @endphp
                            <article class="crm-chat-line {{ $tipoRemetente }}" data-crm-chat-message-id="{{ $mensagem->id }}" data-remetente-original="{{ $remetenteOriginal }}">
                                <div class="crm-chat-bubble">
                                    <span>{{ $nomeRemetente }}</span>
                                    <p>{{ $textoMensagem }}</p>
                                    <time datetime="{{ $mensagem->criado_em?->toIso8601String() }}">{{ $mensagem->criado_em?->format('d/m/Y H:i') }}</time>
                                </div>
                            </article>
                        @empty
                            <div class="crm-empty-state compact"><p>A conversa ainda não possui mensagens.</p></div>
                        @endforelse
                    </div>

                    @if($atendimentoAtual->status !== 'fechado')
                        <section class="crm-composer">
                            <div class="crm-quick-replies" aria-label="Respostas rápidas">
                                <span>Respostas rápidas</span>
                                @foreach($crmRespostasRapidas as $respostaRapida)
                                    <button type="button" data-crm-quick-reply="{{ $respostaRapida }}">{{ $respostaRapida }}</button>
                                @endforeach
                            </div>
                            <form method="post" action="{{ route('locx.crm.portal-atendimentos.responder', $atendimentoAtual) }}" class="crm-chat-reply" data-crm-chat-reply>
                                @csrf
                                <label class="sr-only" for="crmPortalReply{{ $atendimentoAtual->id }}">Responder atendimento</label>
                                <textarea id="crmPortalReply{{ $atendimentoAtual->id }}" name="mensagem" required rows="3" maxlength="2000" placeholder="Digite uma resposta clara e objetiva..."></textarea>
                                <div class="crm-composer-footer">
                                    <small><kbd>Enter</kbd> envia · <kbd>Shift + Enter</kbd> quebra linha</small>
                                    <span data-crm-char-count>0/2000</span>
                                    <button type="submit"><span>Enviar resposta</span> <b>➜</b></button>
                                </div>
                            </form>
                        </section>
                    @else
                        <div class="crm-closed-conversation">
                            <strong>Atendimento concluído</strong>
                            <p>Reabra a conversa caso seja necessário enviar uma nova resposta.</p>
                            <button type="button" class="btn secondary" data-crm-action="reabrir">Reabrir atendimento</button>
                        </div>
                    @endif
                </article>
            @else
                <div class="crm-empty-state crm-chat-empty">
                    <span>💬</span>
                    <strong>Selecione uma conversa</strong>
                    <p>Escolha um atendimento na caixa de entrada para visualizar o histórico e responder.</p>
                </div>
            @endif
        </main>

        <aside class="crm-customer-context panel">
            @if($clienteAtual)
                <header class="crm-context-profile">
                    <span class="crm-contact-avatar xlarge">{{ $iniciaisCliente }}</span>
                    <div>
                        <span class="crm-eyebrow">CLIENTE</span>
                        <h3>{{ $clienteAtual->nome }}</h3>
                        <p>{{ $clienteAtual->loja?->nome ?: 'Loja não informada' }}</p>
                    </div>
                </header>

                <div class="crm-contact-actions">
                    @if($clienteAtual->whatsapp)
                        <a href="https://wa.me/55{{ preg_replace('/\D/', '', $clienteAtual->whatsapp) }}" target="_blank">WhatsApp</a>
                    @endif
                    @if($clienteAtual->email)<a href="mailto:{{ $clienteAtual->email }}">E-mail</a>@endif
                    @if($clienteAtual->telegram_chat_id)<span class="tag info">Telegram vinculado</span>@endif
                    <a href="{{ route('locx.index', ['page' => 'clientes', 'edit' => $clienteAtual->id]) }}">Cadastro</a>
                </div>

                <section class="crm-context-section crm-attendance-history-section">
                    <header>
                        <h4>Histórico de atendimentos</h4>
                        <span>{{ $crmAtendimentosCliente->count() }}</span>
                    </header>
                    <div class="crm-attendance-history">
                        @forelse($crmAtendimentosCliente as $historico)
                            <a href="{{ route('locx.index', ['page' => 'crm', 'atendimento' => $historico->id]) }}#crmAtendimentoAtual"
                               class="{{ $atendimentoAtual?->id === $historico->id ? 'is-current' : '' }}">
                                <span>
                                    <strong>#{{ $historico->id }} · {{ ($historico->canal ?? 'portal') === 'telegram' ? 'Telegram' : 'Portal' }} · {{ ucfirst(str_replace('_', ' ', $historico->assunto)) }}</strong>
                                    <small>{{ ($historico->ultima_mensagem_em ?: $historico->criado_em)?->format('d/m/Y H:i') }}</small>
                                </span>
                                <em class="crm-status-dot {{ $statusClasses[$historico->status] ?? 'is-muted' }}">{{ $statusLabels[$historico->status] ?? $historico->status }}</em>
                            </a>
                        @empty
                            <p class="crm-context-muted">Este cliente ainda não possui atendimentos.</p>
                        @endforelse
                    </div>
                </section>

                <section class="crm-context-section">
                    <header><h4>Resumo comercial</h4></header>
                    <div class="crm-financial-snapshot">
                        <article><span>Saldo aberto</span><strong>{{ \App\Support\Locx::moeda($crmClienteResumo['saldo_aberto'] ?? 0) }}</strong></article>
                        <article class="{{ ($crmClienteResumo['atrasadas'] ?? 0) > 0 ? 'danger' : '' }}"><span>Cobranças atrasadas</span><strong>{{ $crmClienteResumo['atrasadas'] ?? 0 }}</strong></article>
                    </div>
                </section>

                <section class="crm-context-section">
                    <header><h4>Etapa do CRM</h4></header>
                    <form method="post" action="{{ route('locx.crm.cliente', $clienteAtual) }}" class="crm-compact-form">
                        @csrf
                        <select name="crm_etapa">
                            @foreach($crmEtapas as $etapa => $label)
                                <option value="{{ $etapa }}" @selected($clienteAtual->crm_etapa === $etapa)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit">Atualizar</button>
                    </form>
                </section>

                <section class="crm-context-section">
                    <header><h4>Próxima ação</h4></header>
                    @if($crmClienteResumo['proxima_tarefa'] ?? null)
                        <div class="crm-next-task">
                            <strong>{{ $crmClienteResumo['proxima_tarefa']->titulo }}</strong>
                            <span>{{ $crmClienteResumo['proxima_tarefa']->prazo_em?->format('d/m/Y H:i') ?: 'Sem prazo' }}</span>
                        </div>
                    @else
                        <p class="crm-context-muted">Nenhuma tarefa aberta para este cliente.</p>
                    @endif
                </section>

                <section class="crm-note-card">
                    <header>
                        <div>
                            <h4>Registrar nota interna</h4>
                            <p>Observação visível somente para a equipe.</p>
                        </div>
                    </header>
                    <form method="post" action="{{ route('locx.crm.notas.salvar') }}" class="crm-note-form">
                        @csrf
                        <input type="hidden" name="cliente_id" value="{{ $clienteAtual->id }}">
                        <label>Tipo
                        <select name="tipo">
                            <option value="nota">Nota</option><option value="ligacao">Ligação</option><option value="whatsapp">WhatsApp</option><option value="telegram">Telegram</option><option value="email">E-mail</option><option value="negociacao">Negociação</option>
                        </select>
                        </label>
                        <textarea name="texto" required placeholder="Informação visível somente para a equipe"></textarea>
                        <button type="submit">Salvar nota interna</button>
                    </form>
                </section>

                <details class="crm-context-details">
                    <summary>Criar tarefa de acompanhamento</summary>
                    <form method="post" action="{{ route('locx.crm.tarefas.salvar') }}" class="crm-stacked-form">
                        @csrf
                        <input type="hidden" name="cliente_id" value="{{ $clienteAtual->id }}">
                        <input name="titulo" required placeholder="Ex.: Confirmar pagamento">
                        <select name="tipo"><option value="follow_up">Follow-up</option><option value="ligacao">Ligação</option><option value="whatsapp">WhatsApp</option><option value="telegram">Telegram</option><option value="email">E-mail</option><option value="cobranca">Cobrança</option></select>
                        <input type="datetime-local" name="prazo_em">
                        <textarea name="observacao" placeholder="Observação opcional"></textarea>
                        <button type="submit">Criar tarefa</button>
                    </form>
                </details>
            @else
                <div class="crm-empty-state compact"><p>Nenhum cliente selecionado.</p></div>
            @endif
        </aside>
    </section>

    <section class="crm-management-grid">
        <div class="panel crm-pipeline-panel">
            <header class="crm-section-title">
                <div><span class="crm-eyebrow">VISÃO COMERCIAL</span><h3>Pipeline de clientes</h3></div>
            </header>
            <div class="crm-stage-cards modern">
                @foreach($crmEtapas as $etapa => $label)
                    <article><span>{{ $label }}</span><strong>{{ $crmPipeline[$etapa] ?? 0 }}</strong><small>clientes</small></article>
                @endforeach
            </div>
        </div>

        <div class="panel crm-task-panel">
            <header class="crm-section-title"><div><span class="crm-eyebrow">FOLLOW-UP</span><h3>Tarefas abertas</h3></div><b>{{ $crmTarefasAbertas->count() }}</b></header>
            <div class="crm-task-list modern">
                @forelse($crmTarefasAbertas->take(8) as $tarefa)
                    <article class="{{ $tarefa->prazo_em && $tarefa->prazo_em->isPast() ? 'is-overdue' : '' }}">
                        <div><strong>{{ $tarefa->titulo }}</strong><span>{{ $tarefa->cliente?->nome }} · {{ $tarefa->prazo_em?->format('d/m/Y H:i') ?: 'Sem prazo' }}</span></div>
                        <form method="post" action="{{ route('locx.crm.tarefas.concluir', $tarefa) }}">@csrf<button type="submit">Concluir</button></form>
                    </article>
                @empty
                    <p class="crm-context-muted">Nenhuma tarefa aberta.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="panel crm-client-base">
        <header class="crm-section-title">
            <div><span class="crm-eyebrow">BASE DO CRM</span><h3>Clientes e próximos contatos</h3></div>
            <label class="crm-search-box small"><span>⌕</span><input id="crmClientSearch" type="search" placeholder="Buscar cliente"></label>
        </header>
        <div class="table-wrap">
            <table class="crm-client-table">
                <thead><tr><th>Cliente</th><th>Etapa</th><th>Saldo</th><th>Atrasos</th><th>Próxima ação</th><th></th></tr></thead>
                <tbody>
                @forelse($crmClientes as $linha)
                    @php($clienteLinha = $linha['cliente'])
                    <tr data-crm-client="{{ \Illuminate\Support\Str::lower($clienteLinha->nome.' '.$clienteLinha->whatsapp.' '.$clienteLinha->email) }}">
                        <td><strong>{{ $clienteLinha->nome }}</strong><small>{{ $clienteLinha->whatsapp ?: $clienteLinha->email ?: 'Sem contato' }}</small></td>
                        <td><span class="crm-stage-label">{{ $crmEtapas[$clienteLinha->crm_etapa] ?? $clienteLinha->crm_etapa }}</span></td>
                        <td><strong>{{ \App\Support\Locx::moeda($linha['saldo_aberto']) }}</strong></td>
                        <td><span class="crm-number-badge {{ $linha['atrasadas'] > 0 ? 'danger' : '' }}">{{ $linha['atrasadas'] }}</span></td>
                        <td>@if($linha['proxima_tarefa'])<strong>{{ $linha['proxima_tarefa']->titulo }}</strong><small>{{ $linha['proxima_tarefa']->prazo_em?->format('d/m/Y H:i') ?: 'Sem prazo' }}</small>@else<small>Sem tarefa aberta</small>@endif</td>
                        <td><a class="btn secondary" href="{{ route('locx.index', ['page' => 'crm', 'cliente' => $clienteLinha->id]) }}">Abrir CRM</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum cliente encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($clienteAtual)
        <section class="panel crm-history-panel">
            <header class="crm-section-title"><div><span class="crm-eyebrow">HISTÓRICO UNIFICADO</span><h3>Movimentações de {{ $clienteAtual->nome }}</h3></div></header>
            <div class="crm-timeline modern">
                @forelse($crmTimeline->take(16) as $item)
                    <article>
                        <span class="crm-timeline-icon {{ $item['status'] }}"></span>
                        <div><small>{{ $item['tipo'] }} · {{ \Carbon\Carbon::parse($item['data'])->format('d/m/Y H:i') }}</small><strong>{{ $item['titulo'] }}</strong><p>{{ $item['texto'] }}</p></div>
                    </article>
                @empty
                    <p class="crm-context-muted">Nenhum evento registrado para este cliente.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
