<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha Area - LocX</title>
    <link rel="stylesheet" href="{{ \App\Support\Locx::asset('assets/css/style.css') }}">
</head>
<body>
<div class="client-shell">
    <header class="client-header">
        <div class="brand brand-logo"><img src="{{ \App\Support\Locx::asset('assets/img/logo-locx.svg') }}" alt="LocX Aluguel de Motos"></div>
        <div class="client-header-user">
            <strong>{{ $cliente->nome }}</strong>
            <span>{{ $cliente->email }}</span>
            <form method="post" action="{{ route('cliente.logout') }}">@csrf<button class="btn secondary" type="submit">Sair</button></form>
        </div>
    </header>

    <main class="client-main">
        <section class="client-title">
            <div>
                <h1>Minha area</h1>
                <p>Acompanhe debitos, faturas, PIX, contratos e avisos vinculados ao seu cadastro.</p>
            </div>
            <a class="btn secondary" href="#faturas">Ver faturas</a>
        </section>

        <section class="cards">
            <div class="metric {{ $saldoAberto > 0 ? 'warn' : 'ok' }}"><span>Saldo em aberto</span><strong>{{ \App\Support\Locx::moeda($saldoAberto) }}</strong><small>{{ $cobrancasAbertas->count() }} faturas pendentes</small></div>
            <div class="metric {{ $saldoAtrasado > 0 ? 'danger' : 'ok' }}"><span>Debito atrasado</span><strong>{{ \App\Support\Locx::moeda($saldoAtrasado) }}</strong><small>{{ $saldoAtrasado > 0 ? 'Regularize para evitar bloqueios' : 'Sem atraso' }}</small></div>
            <div class="metric"><span>Contratos</span><strong>{{ $contratos->count() }}</strong><small>{{ $contratos->where('status', 'ativo')->count() }} ativos</small></div>
            <div class="metric ok"><span>Pagamentos</span><strong>{{ $cobrancasPagas->count() }}</strong><small>Faturas quitadas</small></div>
        </section>

        <section class="grid side">
            <div class="panel" id="faturas">
                <div class="section-head">
                    <div><h2>Faturas e pagamento</h2><p class="muted">Use o PIX copia e cola ou QR Code quando a fatura estiver gerada.</p></div>
                </div>
                <div class="client-invoices">
                    @forelse ($cobrancas as $cobranca)
                        @php($saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago))
                        <article class="invoice-card {{ $cobranca->status === 'paga' ? 'is-paid' : '' }}">
                            <div class="invoice-main">
                                <div>
                                    <strong>Fatura #{{ $cobranca->id }}</strong>
                                    <span>{{ $cobranca->contrato?->motocicleta?->placa ?: 'Contrato #'.$cobranca->contrato_id }}</span>
                                </div>
                                {!! \App\Support\Locx::status($cobranca->status) !!}
                            </div>
                            <div class="invoice-values">
                                <span>Vencimento <b>{{ $cobranca->vencimento?->format('d/m/Y') }}</b></span>
                                <span>Valor <b>{{ \App\Support\Locx::moeda($cobranca->valor_principal) }}</b></span>
                                <span>Pago <b>{{ \App\Support\Locx::moeda($cobranca->valor_pago) }}</b></span>
                                <span>Saldo <b>{{ \App\Support\Locx::moeda($saldo) }}</b></span>
                            </div>
                            @if ($cobranca->status !== 'paga')
                                @if ($cobranca->pix_copia_cola)
                                    @php($qrImagem = \App\Support\PixQrCode::dataUri($cobranca->pix_copia_cola, $cobranca->pix_qrcode))
                                    <div class="client-pix">
                                        @if ($qrImagem)
                                            <img class="pix-qr" src="{{ $qrImagem }}" alt="QR Code PIX da fatura #{{ $cobranca->id }}">
                                        @endif
                                        <div>
                                            <code class="pix-code">{{ $cobranca->pix_copia_cola }}</code>
                                            <button type="button" class="btn secondary pix-copy-btn" data-pix="{{ e($cobranca->pix_copia_cola) }}">Copiar PIX</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="notice">Fatura registrada, mas o PIX ainda nao foi gerado pela equipe.</div>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div class="empty">Nenhuma fatura encontrada para este cadastro.</div>
                    @endforelse
                </div>
            </div>

            <aside>
                <div class="panel">
                    <h2>Notificacoes</h2>
                    <div class="client-notices">
                        @foreach ($notificacoes as $notificacao)
                            <div class="client-notice {{ $notificacao['tipo'] }}">
                                <strong>{{ $notificacao['titulo'] }}</strong>
                                <span>{{ $notificacao['texto'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="panel">
                    <h2>Meus dados</h2>
                    <div class="client-data">
                        <span>CPF <b>{{ $cliente->cpf ?: '-' }}</b></span>
                        <span>WhatsApp <b>{{ $cliente->whatsapp ?: '-' }}</b></span>
                        <span>Telegram atendimento <b>{{ $cliente->telegram_atendimento_chat_id ? ('@'.($cliente->telegram_atendimento_username ?: 'vinculado')) : 'nao vinculado' }}</b></span>
                        <span>Telegram <b>{{ $cliente->telegram_chat_id ? ('@'.($cliente->telegram_username ?: 'vinculado')) : 'não vinculado' }}</b></span>
                        <span>Loja <b>{{ $cliente->loja?->nome ?: '-' }}</b></span>
                        <span>Status <b>{!! \App\Support\Locx::status($cliente->status) !!}</b></span>
                    </div>
                    <div class="telegram-link-card">
                        <strong>Receber avisos pelo Telegram</strong>
                        @if ($cliente->telegram_chat_id)
                            <span class="tag ok">Telegram vinculado</span>
                            <p>As cobrancas, PIX e lembretes chegam por este bot.</p>
                        @elseif ($telegramLink)
                            <p>Toque no botao, abra o bot e pressione Iniciar para vincular seu cadastro.</p>
                            <a class="btn telegram-btn" href="{{ $telegramLink }}" target="_blank" rel="noopener">Vincular Telegram</a>
                        @else
                            <p>O bot ainda não foi configurado pela empresa.</p>
                        @endif
                    </div>
                    <div class="telegram-link-card">
                        <strong>Falar com a equipe pelo Telegram</strong>
                        @if ($cliente->telegram_atendimento_chat_id)
                            <span class="tag ok">Atendimento vinculado</span>
                            <p>Voce pode enviar mensagens pelo bot de atendimento.</p>
                        @elseif ($telegramAtendimentoLink)
                            <p>Toque no botao, abra o bot de atendimento e pressione Iniciar.</p>
                            <a class="btn telegram-btn" href="{{ $telegramAtendimentoLink }}" target="_blank" rel="noopener">Vincular atendimento</a>
                        @else
                            <p>O bot de atendimento ainda nao foi configurado pela empresa.</p>
                        @endif
                    </div>
                </div>
            </aside>
        </section>

        <section class="grid two">
            <div class="panel">
                <h2>Contratos</h2>
                <div class="table-wrap"><table><tr><th>ID</th><th>Moto</th><th>Inicio</th><th>Valor</th><th>Status</th></tr>
                    @forelse ($contratos as $contrato)
                        <tr><td>#{{ $contrato->id }}</td><td>{{ $contrato->motocicleta?->placa }} - {{ $contrato->motocicleta?->modelo }}</td><td>{{ $contrato->data_inicio?->format('d/m/Y') }}</td><td>{{ \App\Support\Locx::moeda($contrato->valor_contratado) }}</td><td>{!! \App\Support\Locx::status($contrato->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="5" class="empty">Nenhum contrato encontrado.</td></tr>
                    @endforelse
                </table></div>
            </div>
            <div class="panel">
                <h2>Ultimos pagamentos</h2>
                <div class="table-wrap"><table><tr><th>Data</th><th>Fatura</th><th>Forma</th><th>Valor</th></tr>
                    @forelse ($pagamentos as $pagamento)
                        <tr><td>{{ \Carbon\Carbon::parse($pagamento->pago_em)->format('d/m/Y H:i') }}</td><td>#{{ $pagamento->cobranca_numero }}</td><td>{{ $pagamento->forma }}</td><td>{{ \App\Support\Locx::moeda($pagamento->valor) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nenhum pagamento registrado.</td></tr>
                    @endforelse
                </table></div>
            </div>
        </section>
    </main>
    <div class="client-chat-widget" id="chat">
        <button type="button" class="chat-fab" data-chat-toggle aria-expanded="{{ session('chat_success') || $errors->has('assunto') || $errors->has('mensagem') ? 'true' : 'false' }}" aria-controls="clientChatPopup">
            <span class="chat-avatar image"><img src="{{ \App\Support\Locx::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></span>
            <span><strong>Lau</strong><small>Atendimento</small></span>
        </button>
        @php($chatAtivo = $chatAtendimento && in_array($chatAtendimento->status, ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'], true))
        @php($chatComLoja = $chatAtivo && (in_array($chatAtendimento->status, ['aguardando_humano', 'respondido'], true) || ($chatAtendimento->atendente_id && $chatAtendimento->status === 'em_atendimento') || $chatAtendimento->mensagens->contains('remetente', 'humano')))
        @php($mostrarRetomadaChat = ! $chatAtivo && $ultimoAtendimentoEncerrado)
        @php($mostrarOpcoesChat = ! $mostrarRetomadaChat && (! $chatAtivo || (! $chatComLoja && ($chatAtendimento?->status === 'novo' || $chatAtendimento?->assunto === 'outro'))))
        <section class="client-chat-popup {{ session('chat_success') || $errors->has('assunto') || $errors->has('mensagem') ? 'is-open' : '' }}" id="clientChatPopup" role="dialog" aria-modal="false" aria-labelledby="clientChatTitle" aria-label="Chat do Lau" data-chat-greeting="Ola, {{ \Illuminate\Support\Str::before($cliente->nome, ' ') }}. Como posso ajudar?" data-chat-sync-url="{{ route('cliente.chat.sync') }}" data-chat-close-url="{{ route('cliente.chat.close') }}" data-chat-human="{{ $chatComLoja ? '1' : '0' }}" data-chat-show-options="{{ $mostrarOpcoesChat ? '1' : '0' }}">
            <div class="chat-head">
                <div class="chat-avatar image"><img src="{{ \App\Support\Locx::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></div>
                <div>
                    <h2 id="clientChatTitle">Lau</h2>
                    <p data-chat-status>{{ $chatComLoja ? 'Aguardando a loja' : 'Online' }}</p>
                </div>
                <button type="button" class="chat-close" data-chat-close aria-label="Fechar chat">&times;</button>
            </div>
            <div class="chat-thread" data-chat-thread role="log" aria-live="polite" aria-relevant="additions text" aria-label="Mensagens do atendimento">
                @if ($chatMensagens->isEmpty())
                    <article class="chat-row bot" role="article" aria-label="Lau disse" data-chat-message-id="0">
                        <span class="chat-mini-avatar"><img src="{{ \App\Support\Locx::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></span>
                        <div class="chat-bubble"><span class="chat-sender">Lau</span>Ola, {{ \Illuminate\Support\Str::before($cliente->nome, ' ') }}. Como posso ajudar?</div>
                    </article>
                @else
                    @foreach ($chatMensagens as $mensagem)
                        @php($nomeRemetente = $mensagem->remetente === 'cliente' ? 'Voce' : ($mensagem->remetente === 'humano' ? ($mensagem->remetente_nome ?: 'Equipe LOCX') : 'Lau'))
                        <article class="chat-row {{ $mensagem->remetente }}" role="article" aria-label="{{ $nomeRemetente }} disse as {{ $mensagem->criado_em?->format('H:i') }}" data-chat-message-id="{{ $mensagem->id }}" data-chat-client-token="{{ $mensagem->client_token ?: '' }}">
                            @if ($mensagem->remetente !== 'cliente')
                                <span class="chat-mini-avatar"><img src="{{ \App\Support\Locx::asset('assets/img/atendente-lauro.png') }}" alt="{{ $nomeRemetente }}"></span>
                            @endif
                            <div class="chat-bubble">
                                @if ($mensagem->remetente !== 'cliente')
                                    <span class="chat-sender">{{ $nomeRemetente }}</span>
                                @else
                                    <span class="chat-sender">{{ $nomeRemetente }}</span>
                                @endif
                                {{ $mensagem->mensagem }}
                                <time datetime="{{ $mensagem->criado_em?->toIso8601String() }}">{{ $mensagem->criado_em?->format('H:i') }}</time>
                            </div>
                        </article>
                    @endforeach
                @endif
                <div class="chat-typing" data-chat-typing aria-label="Lau esta digitando"><span></span><span></span><span></span></div>
                @if($mostrarRetomadaChat)
                    <section class="chat-resume-card" data-chat-resume-card>
                        <span>Último atendimento</span>
                        <strong>#{{ $ultimoAtendimentoEncerrado->id }} · {{ ucfirst(str_replace('_', ' ', $ultimoAtendimentoEncerrado->assunto)) }}</strong>
                        <p>Encerrado em {{ ($ultimoAtendimentoEncerrado->encerrado_em ?: $ultimoAtendimentoEncerrado->atualizado_em ?: $ultimoAtendimentoEncerrado->criado_em)?->format('d/m/Y H:i') }}.</p>
                        <div>
                            <button type="button" data-chat-resume-action="continuar" data-atendimento-id="{{ $ultimoAtendimentoEncerrado->id }}">Continuar atendimento</button>
                            <button type="button" class="secondary" data-chat-resume-action="novo">Novo assunto</button>
                        </div>
                    </section>
                @endif
                <div class="chat-options is-waiting {{ $mostrarOpcoesChat ? '' : 'is-hidden' }}" data-chat-options role="group" aria-label="Opcoes de atendimento">
                    <span>Escolha uma opcao:</span>
                    @foreach ($chatAssuntos as $valor => $label)
                        <button type="button" class="quick-reply {{ old('assunto') === $valor ? 'is-selected' : '' }}" data-chat-subject="{{ $valor }}">{{ $label }}</button>
                    @endforeach
                </div>
                @if($atendimentosPortal->isNotEmpty())
                    <details class="chat-history-compact">
                        <summary>Ver atendimentos anteriores ({{ $atendimentosPortal->count() }})</summary>
                        <div>
                            @foreach($atendimentosPortal->take(6) as $historico)
                                <article>
                                    <span>#{{ $historico->id }} · {{ ucfirst(str_replace('_', ' ', $historico->assunto)) }}</span>
                                    <small>{{ ucfirst(str_replace('_', ' ', $historico->status)) }} · {{ ($historico->ultima_mensagem_em ?: $historico->criado_em)?->format('d/m/Y H:i') }}</small>
                                </article>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
            @if (session('chat_success'))<div class="notice"><strong>{{ session('chat_success') }}</strong></div>@endif
            @if ($errors->has('assunto') || $errors->has('mensagem'))<div class="alert"><strong>{{ $errors->first('assunto') ?: $errors->first('mensagem') }}</strong></div>@endif
            <form method="post" action="{{ route('cliente.chat.store') }}" class="chat-form" data-chat-form>
                @csrf
                <input type="hidden" name="atendimento_id" value="{{ old('atendimento_id', $chatAtivo ? $chatAtendimento?->id : null) }}" data-chat-atendimento>
                <input type="hidden" name="modo" value="" data-chat-mode>
                <input type="hidden" name="assunto" id="chatSubject" value="{{ old('assunto') }}">
                <div class="chat-compose">
                    <label class="sr-only" for="chatMessage">Mensagem para o atendimento</label>
                    <textarea id="chatMessage" name="mensagem" rows="2" placeholder="Digite sua mensagem...">{{ old('mensagem') }}</textarea>
                    <button type="submit">Enviar</button>
                </div>
                <button type="button" class="chat-end-btn" data-chat-end>Encerrar chat</button>
            </form>
        </section>
    </div>
</div>
<script src="{{ \App\Support\Locx::asset('assets/js/app.js') }}"></script>
</body>
</html>
