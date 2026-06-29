<div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>Cliente</th><th>Vencimento</th><th>Principal</th><th>Pago</th><th>Atualizado</th><th>PIX/WhatsApp</th><th>Gateway PIX</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($cobrancas as $cobranca)
            <tr>
                <td>#{{ $cobranca->id }}</td>
                <td>{{ $cobranca->cliente?->nome }}</td>
                <td>{{ $cobranca->vencimento?->format('d/m/Y') }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_principal) }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_pago) }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_atualizado) }}</td>
                <td>
                    <div class="pix-cell">
                        <div>{{ $cobranca->pix_copia_cola ? 'PIX gerado' : 'Sem PIX' }} / {!! \App\Support\Locx::status($cobranca->whatsapp_status) !!}</div>
                        @if ($cobranca->pix_copia_cola)
                            @php($qrImagem = \App\Support\PixQrCode::dataUri($cobranca->pix_copia_cola, $cobranca->pix_qrcode))
                            <div class="pix-tools">
                                <button type="button" class="btn secondary pix-copy-btn" data-pix="{{ e($cobranca->pix_copia_cola) }}">Copiar PIX</button>
                                @if ($qrImagem)
                                    <a class="pix-qr-link" href="{{ $qrImagem }}" target="_blank" title="Abrir QR Code maior">
                                        <img class="pix-qr" src="{{ $qrImagem }}" alt="QR Code PIX da cobranca #{{ $cobranca->id }}">
                                    </a>
                                @endif
                            </div>
                            <small class="pix-help">Se o celular nao ler na tabela, toque no QR para abrir maior ou use Copiar PIX.</small>
                            <code class="pix-code">{{ \Illuminate\Support\Str::limit($cobranca->pix_copia_cola, 52) }}</code>
                        @endif
                    </div>
                </td>
                <td>
                    <form method="post" action="{{ route('locx.cobrancas.pix', $cobranca) }}">
                        @csrf
                        <input type="hidden" name="page" value="{{ $page }}">
                        <button class="btn secondary" type="submit">Gerar PIX</button>
                    </form>
                    <br>
                    @if ($cobranca->asaas_status)
                        <small>Asaas</small><br>{!! \App\Support\Locx::status($cobranca->asaas_status) !!}
                    @elseif ($cobranca->pagbank_status)
                        <small>PagBank</small><br>{!! \App\Support\Locx::status($cobranca->pagbank_status) !!}
                    @else
                        <span class="tag muted">nao gerado</span>
                    @endif
                </td>
                <td>{!! \App\Support\Locx::status($cobranca->status) !!}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Nenhuma cobranca encontrada.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
