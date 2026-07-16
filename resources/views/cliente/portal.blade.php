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
                        <span>Loja <b>{{ $cliente->loja?->nome ?: '-' }}</b></span>
                        <span>Status <b>{!! \App\Support\Locx::status($cliente->status) !!}</b></span>
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
</div>
<script src="{{ \App\Support\Locx::asset('assets/js/app.js') }}"></script>
</body>
</html>
