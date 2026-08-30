<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha área | {{ $branding['store_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
    {{-- LOCX V38: CSS crítico garante o menu do Gerência mesmo antes da renovação do cache externo. --}}
    <style>
        @media (min-width:769px){
            .client-mobile-header{display:none!important}
            .client-app{display:flex!important;align-items:flex-start!important;min-height:100vh!important;background:#f5f7fb!important}
            .client-app>.client-sidebar{display:flex!important;flex:0 0 252px!important;flex-direction:column!important;position:sticky!important;top:10px!important;width:252px!important;height:calc(100vh - 20px)!important;margin:10px!important;padding:12px 10px!important;border:1px solid #c9d9e8!important;border-radius:18px!important;background:linear-gradient(180deg,#fff 0%,#f7fbff 100%)!important;box-shadow:0 12px 30px rgba(15,23,42,.08)!important}
            .client-app>.client-main{flex:1!important;min-width:0!important;width:auto!important;max-width:1180px!important;margin:0 auto!important;padding:24px!important}
        }
    </style>
</head>
<body data-store-name="{{ $branding['store_name'] }}" data-product-name="{{ $branding['product_name'] }}" data-portal-version="38">
<!-- LOCX-PORTAL-V38-MENU-VERTICAL -->
<header class="mobile-header client-mobile-header">
    <x-brand />
    <button type="button" class="mobile-menu-toggle" aria-label="Abrir menu do cliente" aria-controls="sidebarMenu" aria-expanded="false">☰</button>
</header>
<div class="mobile-menu-overlay" data-menu-close></div>
<div class="client-shell client-app app">
    <aside class="sidebar client-sidebar" id="sidebarMenu" aria-label="Dados do cliente conectado">
        <button type="button" class="mobile-menu-close" data-menu-close>Fechar</button>
        <x-brand />
        <div class="client-sidebar-user">
            <strong>{{ $cliente->nome }}</strong>
            <span>{{ $cliente->email }}</span>
        </div>
        <div class="client-sidebar-spacer"></div>
        <form class="client-sidebar-logout" method="post" action="{{ route('cliente.logout') }}">
            @csrf
            <button class="btn secondary" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9"></path></svg>
                Sair
            </button>
        </form>
    </aside>

    <main class="client-main main" id="resumo">
        <section class="client-title">
            <div>
                <span class="client-eyebrow">ÁREA DO CLIENTE</span>
                <h1>Olá, {{ \Illuminate\Support\Str::before($cliente->nome, ' ') }}</h1>
                <p>Acompanhe cobranças, pagamentos efetuados e mensagens enviadas pela equipe.</p>
            </div>
            <a class="btn secondary" href="#faturas">Ver faturas</a>
        </section>

        @if (session('message_success'))
            <div class="notice client-feedback"><strong>{{ session('message_success') }}</strong></div>
        @endif

        <section class="cards">
            <a class="metric {{ $saldoAberto > 0 ? 'warn' : 'ok' }}" href="#faturas"><span>Saldo em aberto</span><strong>{{ \App\Support\RentalSupport::moeda($saldoAberto) }}</strong><small>{{ $cobrancasAbertas->count() }} cobranças pendentes</small></a>
            <a class="metric {{ $saldoAtrasado > 0 ? 'danger' : 'ok' }}" href="#faturas"><span>Débito atrasado</span><strong>{{ \App\Support\RentalSupport::moeda($saldoAtrasado) }}</strong><small>{{ $saldoAtrasado > 0 ? 'Regularize para evitar bloqueios' : 'Sem atraso' }}</small></a>
            <a class="metric ok" href="#pagamentos"><span>Pagamentos</span><strong>{{ $pagamentos->count() }}</strong><small>Últimos pagamentos registrados</small></a>
            <a class="metric {{ $mensagensNaoLidas > 0 ? 'warn' : 'ok' }}" href="#mensagens"><span>Mensagens</span><strong>{{ $mensagensNaoLidas }}</strong><small>{{ $mensagensNaoLidas === 1 ? 'mensagem não lida' : 'mensagens não lidas' }}</small></a>
        </section>

        <section class="grid side">
            <div class="panel" id="faturas">
                <div class="section-head">
                    <div><h2>Cobranças e pagamento</h2><p class="muted">Use o PIX copia e cola ou QR Code quando a cobrança estiver gerada.</p></div>
                </div>
                <div class="client-invoices">
                    @forelse ($cobrancas as $cobranca)
                        @php($saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago))
                        <article class="invoice-card {{ $cobranca->status === 'paga' ? 'is-paid' : '' }}">
                            <div class="invoice-main">
                                <div>
                                    <strong>Cobrança #{{ $cobranca->id }}</strong>
                                    <span>{{ $cobranca->contrato?->motocicleta?->placa ?: 'Contrato #'.$cobranca->contrato_id }}</span>
                                </div>
                                {!! \App\Support\RentalSupport::status($cobranca->status) !!}
                            </div>
                            <div class="invoice-values">
                                <span>Vencimento <b>{{ $cobranca->vencimento?->format('d/m/Y') }}</b></span>
                                <span>Valor <b>{{ \App\Support\RentalSupport::moeda($cobranca->valor_principal) }}</b></span>
                                <span>Pago <b>{{ \App\Support\RentalSupport::moeda($cobranca->valor_pago) }}</b></span>
                                <span>Saldo <b>{{ \App\Support\RentalSupport::moeda($saldo) }}</b></span>
                            </div>
                            @if ($cobranca->status !== 'paga')
                                @if ($cobranca->pix_copia_cola)
                                    @php($qrImagem = \App\Support\PixQrCode::dataUri($cobranca->pix_copia_cola, $cobranca->pix_qrcode))
                                    <div class="client-pix">
                                        @if ($qrImagem)
                                            <img class="pix-qr" src="{{ $qrImagem }}" alt="QR Code PIX da cobrança #{{ $cobranca->id }}">
                                        @endif
                                        <div>
                                            <code class="pix-code">{{ $cobranca->pix_copia_cola }}</code>
                                            <button type="button" class="btn secondary pix-copy-btn" data-pix="{{ e($cobranca->pix_copia_cola) }}">Copiar PIX</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="notice">Cobrança registrada, mas o PIX ainda não foi gerado pela equipe.</div>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div class="empty">Nenhuma cobrança encontrada para este cadastro.</div>
                    @endforelse
                </div>
            </div>

            <aside>
                <div class="panel">
                    <h2>Avisos financeiros</h2>
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
                        <span>Status <b>{!! \App\Support\RentalSupport::status($cliente->status) !!}</b></span>
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

        <section class="panel client-messages-panel" id="mensagens">
            <div class="section-head">
                <div>
                    <span class="client-section-kicker">COMUNICAÇÃO</span>
                    <h2>Mensagens da equipe</h2>
                    <p class="muted">Comunicados e informações enviados diretamente para você.</p>
                </div>
                @if($mensagensNaoLidas > 0)
                    <form method="post" action="{{ route('cliente.mensagens.lidas') }}">
                        @csrf
                        <button type="submit" class="btn secondary">Marcar todas como lidas</button>
                    </form>
                @endif
            </div>
            <div class="client-message-list">
                @forelse($mensagensPortal as $mensagem)
                    @php($tipoLabels = ['informacao' => 'Informação', 'cobranca' => 'Cobrança', 'pagamento' => 'Pagamento', 'documento' => 'Documento', 'aviso' => 'Aviso'])
                    <article class="client-message {{ $mensagem->lida_em ? 'is-read' : 'is-unread' }} type-{{ $mensagem->tipo }}">
                        <div class="client-message-head">
                            <div>
                                <span class="client-message-type">{{ $tipoLabels[$mensagem->tipo] ?? 'Informação' }}</span>
                                @if(! $mensagem->lida_em)<span class="client-unread-badge">NOVA</span>@endif
                            </div>
                            <time datetime="{{ $mensagem->enviada_em?->toIso8601String() }}">{{ $mensagem->enviada_em?->format('d/m/Y H:i') }}</time>
                        </div>
                        <h3>{{ $mensagem->assunto }}</h3>
                        <p>{{ $mensagem->mensagem }}</p>
                        <footer>
                            <small>Enviada por {{ $mensagem->usuario?->nome ?: 'Equipe '.$branding['store_name'] }}</small>
                            @if(! $mensagem->lida_em)
                                <form method="post" action="{{ route('cliente.mensagens.lida', $mensagem) }}">
                                    @csrf
                                    <button type="submit" class="btn secondary">Marcar como lida</button>
                                </form>
                            @else
                                <small>Lida em {{ $mensagem->lida_em->format('d/m/Y H:i') }}</small>
                            @endif
                        </footer>
                    </article>
                @empty
                    <div class="empty client-message-empty">Nenhuma mensagem enviada pela equipe até o momento.</div>
                @endforelse
            </div>
        </section>

        <section class="grid two">
            <div class="panel">
                <h2>Contratos</h2>
                <div class="table-wrap"><table><tr><th>ID</th><th>Moto</th><th>Inicio</th><th>Valor</th><th>Status</th></tr>
                    @forelse ($contratos as $contrato)
                        <tr><td>#{{ $contrato->id }}</td><td>{{ $contrato->motocicleta?->placa }} - {{ $contrato->motocicleta?->modelo_nome }}</td><td>{{ $contrato->data_inicio?->format('d/m/Y') }}</td><td>{{ \App\Support\RentalSupport::moeda($contrato->valor_contratado) }}</td><td>{!! \App\Support\RentalSupport::status($contrato->status) !!}</td></tr>
                    @empty
                        <tr><td colspan="5" class="empty">Nenhum contrato encontrado.</td></tr>
                    @endforelse
                </table></div>
            </div>
            <div class="panel" id="pagamentos">
                <h2>Últimos pagamentos</h2>
                <div class="table-wrap"><table><tr><th>Data</th><th>Cobrança</th><th>Forma</th><th>Valor</th></tr>
                    @forelse ($pagamentos as $pagamento)
                        <tr><td>{{ \Carbon\Carbon::parse($pagamento->pago_em)->format('d/m/Y H:i') }}</td><td>#{{ $pagamento->cobranca_numero }}</td><td>{{ ucfirst(str_replace('_', ' ', $pagamento->forma)) }}</td><td>{{ \App\Support\RentalSupport::moeda($pagamento->valor) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nenhum pagamento registrado.</td></tr>
                    @endforelse
                </table></div>
            </div>
        </section>
    </main>
    <div class="client-chat-widget" id="chat">
        <button type="button" class="chat-fab" data-chat-toggle aria-expanded="{{ session('chat_success') || $errors->has('assunto') || $errors->has('mensagem') ? 'true' : 'false' }}" aria-controls="clientChatPopup">
            <span class="chat-avatar image"><img src="{{ \App\Support\RentalSupport::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></span>
            <span><strong>Lau</strong><small>Atendimento</small></span>
        </button>
        @php($chatAtivo = $chatAtendimento && in_array($chatAtendimento->status, ['novo', 'em_atendimento', 'aguardando_humano', 'respondido'], true))
        @php($chatComLoja = $chatAtivo && (in_array($chatAtendimento->status, ['aguardando_humano', 'respondido'], true) || ($chatAtendimento->atendente_id && $chatAtendimento->status === 'em_atendimento') || $chatAtendimento->mensagens->contains('remetente', 'humano')))
        @php($mostrarRetomadaChat = ! $chatAtivo && $ultimoAtendimentoEncerrado)
        @php($mostrarOpcoesChat = ! $mostrarRetomadaChat && (! $chatAtivo || (! $chatComLoja && ($chatAtendimento?->status === 'novo' || $chatAtendimento?->assunto === 'outro'))))
        <section class="client-chat-popup {{ session('chat_success') || $errors->has('assunto') || $errors->has('mensagem') ? 'is-open' : '' }}" id="clientChatPopup" role="dialog" aria-modal="false" aria-labelledby="clientChatTitle" aria-label="Chat do Lau" data-chat-greeting="Ola, {{ \Illuminate\Support\Str::before($cliente->nome, ' ') }}. Como posso ajudar?" data-chat-sync-url="{{ route('cliente.chat.sync') }}" data-chat-close-url="{{ route('cliente.chat.close') }}" data-chat-human="{{ $chatComLoja ? '1' : '0' }}" data-chat-show-options="{{ $mostrarOpcoesChat ? '1' : '0' }}">
            <div class="chat-head">
                <div class="chat-avatar image"><img src="{{ \App\Support\RentalSupport::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></div>
                <div>
                    <h2 id="clientChatTitle">Lau</h2>
                    <p data-chat-status>{{ $chatComLoja ? 'Aguardando a loja' : 'Online' }}</p>
                </div>
                <button type="button" class="chat-close" data-chat-close aria-label="Fechar chat">&times;</button>
            </div>
            <div class="chat-thread" data-chat-thread role="log" aria-live="polite" aria-relevant="additions text" aria-label="Mensagens do atendimento">
                @if ($chatMensagens->isEmpty())
                    <article class="chat-row bot" role="article" aria-label="Lau disse" data-chat-message-id="0">
                        <span class="chat-mini-avatar"><img src="{{ \App\Support\RentalSupport::asset('assets/img/atendente-lauro.png') }}" alt="Lau"></span>
                        <div class="chat-bubble"><span class="chat-sender">Lau</span>Ola, {{ \Illuminate\Support\Str::before($cliente->nome, ' ') }}. Como posso ajudar?</div>
                    </article>
                @else
                    @foreach ($chatMensagens as $mensagem)
                        @php($nomeRemetente = $mensagem->remetente === 'cliente' ? 'Você' : ($mensagem->remetente === 'humano' ? ($mensagem->remetente_nome ?: 'Equipe '.$branding['store_name']) : 'Lau'))
                        <article class="chat-row {{ $mensagem->remetente }}" role="article" aria-label="{{ $nomeRemetente }} disse as {{ $mensagem->criado_em?->format('H:i') }}" data-chat-message-id="{{ $mensagem->id }}" data-chat-client-token="{{ $mensagem->client_token ?: '' }}">
                            @if ($mensagem->remetente !== 'cliente')
                                <span class="chat-mini-avatar"><img src="{{ \App\Support\RentalSupport::asset('assets/img/atendente-lauro.png') }}" alt="{{ $nomeRemetente }}"></span>
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
<script src="{{ \App\Support\RentalSupport::asset('assets/js/app.js') }}"></script>
</body>
</html>
