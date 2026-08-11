<div class="table-wrap">
    @php
        $podeGerarPix = $page === 'cobrancas'
            ? $user->pode('cobrancas', 'editar')
            : $user->pode('financeiro', 'editar');
        $podeEnviarCobranca = $user->pode('inadimplencia', 'editar');
    @endphp
    <table>
        <thead><tr><th>ID</th><th>Cliente</th><th>Vencimento</th><th>Principal</th><th>Pago</th><th>Atualizado</th><th>PIX/Canais</th><th>Gateway PIX</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        @forelse ($cobrancas as $cobranca)
            @php
                $saldo = max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago);
                $dueState = $cobranca->vencimento?->isPast() && !$cobranca->vencimento?->isToday()
                    ? 'overdue'
                    : ($cobranca->vencimento?->isToday() ? 'today' : 'future');
                $gateway = $cobranca->asaas_status ? 'asaas' : ($cobranca->sicoob_status ? 'sicoob' : ($cobranca->itau_status ? 'itau' : ($cobranca->pagbank_status ? 'pagbank' : 'nao gerado')));
                $searchText = \Illuminate\Support\Str::lower(implode(' ', [
                    '#'.$cobranca->id,
                    $cobranca->cliente?->nome,
                    $cobranca->cliente?->whatsapp,
                    $cobranca->contrato?->motocicleta?->placa,
                    $cobranca->status,
                    $gateway,
                    $cobranca->whatsapp_status,
                    $cobranca->telegram_status,
                ]));
            @endphp
            <tr data-billing-row
                data-search="{{ e($searchText) }}"
                data-status="{{ $cobranca->status }}"
                data-due="{{ $dueState }}"
                data-pix="{{ $cobranca->pix_copia_cola ? 'yes' : 'no' }}"
                data-whatsapp="{{ $cobranca->whatsapp_status ?: 'pendente' }}"
                data-telegram="{{ $cobranca->telegram_status ?: 'pendente' }}">
                <td>#{{ $cobranca->id }}</td>
                <td><strong>{{ $cobranca->cliente?->nome }}</strong><br><small>{{ $cobranca->contrato?->motocicleta?->placa ?: 'Sem placa' }}</small></td>
                <td>{{ $cobranca->vencimento?->format('d/m/Y') }}<br><small>{{ $dueState === 'overdue' ? 'Atrasada' : ($dueState === 'today' ? 'Vence hoje' : 'A vencer') }}</small></td>
                <td>{{ \App\Support\RentalSupport::moeda($cobranca->valor_principal) }}</td>
                <td>{{ \App\Support\RentalSupport::moeda($cobranca->valor_pago) }}</td>
                <td><strong>{{ \App\Support\RentalSupport::moeda($cobranca->valor_atualizado) }}</strong><br><small>Saldo {{ \App\Support\RentalSupport::moeda($saldo) }}</small></td>
                <td>
                    <div class="pix-cell">
                        <div><strong>{{ $cobranca->pix_copia_cola ? 'PIX gerado' : 'Sem PIX' }}</strong></div>
                        <small>WhatsApp: {!! \App\Support\RentalSupport::status($cobranca->whatsapp_status) !!}</small><br>
                        <small>Telegram: {!! \App\Support\RentalSupport::status($cobranca->telegram_status ?? 'pendente') !!}</small>
                        @if ($cobranca->pix_copia_cola)
                            @php($qrImagem = \App\Support\PixQrCode::dataUri($cobranca->pix_copia_cola, $cobranca->pix_qrcode))
                            <div class="pix-tools">
                                <button type="button" class="btn secondary pix-copy-btn" data-pix="{{ e($cobranca->pix_copia_cola) }}">Copiar PIX</button>
                                @if ($qrImagem)
                                    <a class="pix-qr-link" href="{{ $qrImagem }}" target="_blank" title="Abrir QR Code maior">
                                        <img class="pix-qr" src="{{ $qrImagem }}" alt="QR Code PIX da cobrança #{{ $cobranca->id }}">
                                    </a>
                                @endif
                            </div>
                            <small class="pix-help">Se o celular nao ler na tabela, toque no QR para abrir maior ou use Copiar PIX.</small>
                            <code class="pix-code">{{ \Illuminate\Support\Str::limit($cobranca->pix_copia_cola, 52) }}</code>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="gateway-cell">
                        @if ($cobranca->asaas_status)
                            <small>Asaas</small><br>{!! \App\Support\RentalSupport::status($cobranca->asaas_status) !!}
                        @elseif ($cobranca->sicoob_status)
                            <small>Sicoob</small><br>{!! \App\Support\RentalSupport::status($cobranca->sicoob_status) !!}
                        @elseif ($cobranca->itau_status)
                            <small>Itau</small><br>{!! \App\Support\RentalSupport::status($cobranca->itau_status) !!}
                        @elseif ($cobranca->pagbank_status)
                            <small>PagBank</small><br>{!! \App\Support\RentalSupport::status($cobranca->pagbank_status) !!}
                        @else
                            <span class="tag muted">não gerado</span>
                        @endif
                    </div>
                </td>
                <td>{!! \App\Support\RentalSupport::status($cobranca->status) !!}</td>
                <td>
                    <div class="billing-actions">
                        @if($podeGerarPix)
                            <form method="post" action="{{ route('rental.cobrancas.pix', $cobranca) }}">
                                @csrf
                                <input type="hidden" name="page" value="{{ $page }}">
                                <button class="btn secondary" type="submit">Gerar PIX</button>
                            </form>
                        @endif
                        @if($cobranca->cliente?->whatsapp)
                            <a class="btn secondary" target="_blank" href="https://wa.me/55{{ preg_replace('/\D/','',$cobranca->cliente?->whatsapp) }}">WhatsApp</a>
                        @endif
                        @if($podeEnviarCobranca)
                            <form method="post" action="{{ route('rental.cobrancas.whatsapp',$cobranca) }}">@csrf<button class="btn success" type="submit">Enviar WhatsApp</button></form>
                            <form method="post" action="{{ route('rental.cobrancas.telegram',$cobranca) }}">@csrf<button class="btn secondary" type="submit">Enviar Telegram</button></form>
                        @endif
                        @if(!$podeGerarPix && !$podeEnviarCobranca && !$cobranca->cliente?->whatsapp)
                            <span class="tag muted">somente consulta</span>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">Nenhuma cobrança encontrada.</td></tr>
        @endforelse
            <tr data-billing-empty hidden><td colspan="10" class="empty">Nenhuma cobrança corresponde aos filtros selecionados.</td></tr>
        </tbody>
    </table>
</div>
