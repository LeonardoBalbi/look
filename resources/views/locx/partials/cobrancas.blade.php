<div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>Cliente</th><th>Vencimento</th><th>Principal</th><th>Pago</th><th>Atualizado</th><th>PIX/WhatsApp</th><th>PagBank</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($cobrancas as $cobranca)
            <tr>
                <td>#{{ $cobranca->id }}</td>
                <td>{{ $cobranca->cliente?->nome }}</td>
                <td>{{ $cobranca->vencimento?->format('d/m/Y') }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_principal) }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_pago) }}</td>
                <td>{{ \App\Support\Locx::moeda($cobranca->valor_atualizado) }}</td>
                <td>{{ $cobranca->pix_copia_cola ? 'PIX gerado' : 'Sem PIX' }} / {!! \App\Support\Locx::status($cobranca->whatsapp_status) !!}</td>
                <td>
                    <form method="post" action="{{ route('locx.cobrancas.pix', $cobranca) }}">
                        @csrf
                        <input type="hidden" name="page" value="{{ $page }}">
                        <button class="btn secondary" type="submit">Gerar PIX</button>
                    </form>
                    <br>
                    {!! $cobranca->pagbank_status ? \App\Support\Locx::status($cobranca->pagbank_status) : '<span class="tag muted">não gerado</span>' !!}
                </td>
                <td>{!! \App\Support\Locx::status($cobranca->status) !!}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Nenhuma cobrança encontrada.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
