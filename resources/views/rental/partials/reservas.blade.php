<div class="rental-flow" aria-label="Fluxo da locação">
    @foreach(['Pedido', 'Análise', 'Confirmação', 'Retirada', 'Devolução'] as $etapa)
        <div><span>{{ $loop->iteration }}</span><strong>{{ $etapa }}</strong></div>
    @endforeach
</div>

<div class="cards">
    <div class="metric"><span>Em preparação</span><strong>{{ $reservasResumo['preparacao'] }}</strong><small>Solicitadas, em análise ou confirmadas</small></div>
    <div class="metric ok"><span>Retiradas hoje</span><strong>{{ $reservasResumo['retiradasHoje'] }}</strong><small>Agenda operacional</small></div>
    <div class="metric warn"><span>Devoluções hoje</span><strong>{{ $reservasResumo['devolucoesHoje'] }}</strong><small>Vistoria e fechamento</small></div>
    <div class="metric ok"><span>Motos disponíveis</span><strong>{{ $reservasResumo['disponiveis'] }}</strong><small>Disponibilidade atual</small></div>
</div>

<div class="grid side">
    <section class="panel">
        <div class="section-head">
            <div>
                <h2>{{ $reservaEdit ? 'Editar reserva #'.$reservaEdit->id : 'Nova reserva' }}</h2>
                <p class="crm-subtitle">Informe o período completo para que o sistema evite conflito de veículo.</p>
            </div>
            @if($reservaEdit)<a class="btn secondary" href="{{ route('rental.index', ['page' => 'reservas']) }}">Nova reserva</a>@endif
        </div>

        @if($user->pode('reservas', $reservaEdit ? 'editar' : 'criar'))
            <form method="post" action="{{ route('rental.reservas.salvar') }}" class="form-grid">
                @csrf
                <input type="hidden" name="id" value="{{ $reservaEdit?->id }}">
                @if($singleStore)
                    <input type="hidden" name="loja_id" value="{{ $reservaEdit?->loja_id ?: $lojaUnica?->id }}">
                @else
                <label>Unidade
                    <select name="loja_id">
                        <option value="">Central</option>
                        @foreach($lojasReserva as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id', $reservaEdit?->loja_id) == $loja->id)>{{ $loja->nome }}</option>@endforeach
                    </select>
                </label>
                @endif
                <label class="span-2">Cliente
                    <select name="cliente_id">
                        <option value="">Cliente ainda não cadastrado</option>
                        @foreach($clientesReserva as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id', $reservaEdit?->cliente_id) == $cliente->id)>{{ $cliente->nome }}{{ $cliente->cpf ? ' — '.$cliente->cpf : '' }}</option>@endforeach
                    </select>
                </label>
                <label>Retirada<input type="datetime-local" name="retirada_em" required value="{{ old('retirada_em', $reservaEdit?->retirada_em?->format('Y-m-d\TH:i')) }}"></label>
                <label>Devolução<input type="datetime-local" name="devolucao_em" required value="{{ old('devolucao_em', $reservaEdit?->devolucao_em?->format('Y-m-d\TH:i')) }}"></label>
                <label>Origem
                    <select name="origem">@foreach(['balcao' => 'Balcão', 'whatsapp' => 'WhatsApp', 'telefone' => 'Telefone', 'site' => 'Site', 'parceiro' => 'Parceiro'] as $value => $label)<option value="{{ $value }}" @selected(old('origem', $reservaEdit?->origem ?? 'balcao') === $value)>{{ $label }}</option>@endforeach</select>
                </label>
                <label class="span-2">Motocicleta
                    <select name="motocicleta_id">
                        <option value="">Definir posteriormente</option>
                        @foreach($motosReserva as $moto)<option value="{{ $moto->id }}" @selected(old('motocicleta_id', $reservaEdit?->motocicleta_id) == $moto->id)>{{ $moto->placa ?: 'Sem placa' }} — {{ $moto->modelo_nome }} / {{ $moto->cor ?: 'cor não informada' }}</option>@endforeach
                    </select>
                </label>
                <label>Categoria / grupo<input name="categoria" maxlength="120" value="{{ old('categoria', $reservaEdit?->categoria) }}" placeholder="Ex.: CG 160"></label>
                <label>Valor estimado<input type="number" step="0.01" min="0" name="valor_estimado" value="{{ old('valor_estimado', $reservaEdit?->valor_estimado ?? 0) }}"></label>
                <label>Caução<input type="number" step="0.01" min="0" name="caucao" value="{{ old('caucao', $reservaEdit?->caucao ?? 0) }}"></label>
                <label>Status
                    <select name="status">@foreach(['solicitada' => 'Solicitada', 'em_analise' => 'Em análise', 'confirmada' => 'Confirmada', 'retirada' => 'Retirada', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $reservaEdit?->status ?? 'solicitada') === $value)>{{ $label }}</option>@endforeach</select>
                </label>
                <label class="span-3">Observações<textarea name="observacoes" maxlength="5000" placeholder="Documentos pendentes, caução, preferência do cliente ou orientação para a retirada.">{{ old('observacoes', $reservaEdit?->observacoes) }}</textarea></label>
                <div class="span-3"><button type="submit">{{ $reservaEdit ? 'Atualizar reserva' : 'Salvar reserva' }}</button></div>
            </form>
        @else
            <p class="empty">Seu perfil pode consultar reservas, mas não pode alterá-las.</p>
        @endif
    </section>

    <aside class="panel">
        <h2>Checklist de confirmação</h2>
        <div class="look-steps">
            <div><span>1</span><strong>Cliente validado</strong><small>Confirme contato, CNH e documentos necessários.</small></div>
            <div><span>2</span><strong>Disponibilidade</strong><small>Escolha a moto ou mantenha somente a categoria até a confirmação.</small></div>
            <div><span>3</span><strong>Condições registradas</strong><small>Informe período, valor, caução e observações importantes.</small></div>
        </div>
    </aside>
</div>

<section class="panel">
    <div class="section-head"><div><h2>Agenda de reservas</h2><p class="crm-subtitle">Retiradas e devoluções organizadas por período.</p></div></div>
    <div class="table-wrap"><table>
        <tr><th>Reserva</th><th>Período</th><th>Cliente</th><th>Moto / categoria</th><th>Unidade</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
        @forelse($reservas as $reserva)
            <tr>
                <td>#{{ $reserva->id }}<br><small>{{ ucfirst($reserva->origem) }}</small></td>
                <td>{{ $reserva->retirada_em->format('d/m/Y H:i') }}<br><small>até {{ $reserva->devolucao_em->format('d/m/Y H:i') }}</small></td>
                <td>{{ $reserva->cliente?->nome ?? 'A definir' }}</td>
                <td>{{ $reserva->motocicleta?->placa ?? 'A definir' }}<br><small>{{ $reserva->motocicleta?->modelo_nome ?? $reserva->categoria }}</small></td>
                <td>{{ $reserva->loja?->nome ?? 'Central' }}</td>
                <td>{{ \App\Support\RentalSupport::moeda($reserva->valor_estimado) }}</td>
                <td>{!! \App\Support\RentalSupport::status($reserva->status) !!}</td>
                <td>@if($user->pode('reservas', 'editar'))<a class="btn secondary" href="{{ route('rental.index', ['page' => 'reservas', 'edit' => $reserva->id]) }}">Editar</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8">Nenhuma reserva cadastrada.</td></tr>
        @endforelse
    </table></div>
</section>

@if($reservasLegadas->isNotEmpty())
    <details class="panel legacy-reservations">
        <summary><strong>Registros anteriores de reserva ({{ $reservasLegadas->count() }})</strong></summary>
        <p class="crm-subtitle">Registros criados antes do calendário profissional foram preservados para consulta.</p>
        <div class="table-wrap"><table><tr><th>Título</th><th>Cliente</th><th>Referência</th><th>Prazo</th><th>Status</th></tr>
            @foreach($reservasLegadas as $registro)<tr><td>{{ $registro->titulo }}</td><td>{{ $registro->pessoa ?: '-' }}</td><td>{{ $registro->documento ?: '-' }}</td><td>{{ $registro->vencimento?->format('d/m/Y') ?: '-' }}</td><td>{!! \App\Support\RentalSupport::status($registro->status) !!}</td></tr>@endforeach
        </table></div>
    </details>
@endif
