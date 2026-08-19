<div class="cards">
    <div class="metric warn"><span>Ordens abertas</span><strong>{{ $manutencaoResumo['abertas'] }}</strong></div>
    <div class="metric ok"><span>Concluídas no mês</span><strong>{{ $manutencaoResumo['concluidasMes'] }}</strong></div>
    <div class="metric"><span>Custo previsto</span><strong>{{ \App\Support\RentalSupport::moeda($manutencaoResumo['custoPrevisto']) }}</strong></div>
    <div class="metric ok"><span>Custo final no mês</span><strong>{{ \App\Support\RentalSupport::moeda($manutencaoResumo['custoFinalMes']) }}</strong></div>
</div>

<div class="grid side">
    <div class="panel">
        <h2>{{ $ordemEdit ? 'Editar' : 'Nova' }} Ordem de Serviço</h2>
        <form method="post" action="{{ route('rental.manutencao.ordens.salvar') }}" class="form-grid">
            @csrf
            <input type="hidden" name="id" value="{{ $ordemEdit?->id }}">
            @if($singleStore)<input type="hidden" name="loja_id" value="{{ $ordemEdit?->loja_id ?: $lojaUnica?->id }}">@else<label>Loja<select name="loja_id"><option value="">Selecione</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$ordemEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>@endif
            <label>Moto<select name="motocicleta_id"><option value="">Sem moto</option>@foreach($motos as $moto)<option value="{{ $moto->id }}" @selected(old('motocicleta_id',$ordemEdit?->motocicleta_id)==$moto->id)>{{ $moto->placa ?: 'sem placa' }} - {{ $moto->modelo_nome }}</option>@endforeach</select></label>
            <label>Cliente<select name="cliente_id"><option value="">Sem cliente</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id',$ordemEdit?->cliente_id)==$cliente->id)>{{ $cliente->nome }}</option>@endforeach</select></label>
            <label>Tipo<select name="tipo">@foreach(['preventiva','corretiva','vistoria','sinistro'] as $tipo)<option value="{{ $tipo }}" @selected(old('tipo',$ordemEdit?->tipo ?? 'corretiva')===$tipo)>{{ ucfirst($tipo) }}</option>@endforeach</select></label>
            <label>Status<select name="status">@foreach(['aberta','em_andamento','aguardando_peca','concluida','cancelada'] as $status)<option value="{{ $status }}" @selected(old('status',$ordemEdit?->status ?? 'aberta')===$status)>{{ str_replace('_',' ', $status) }}</option>@endforeach</select></label>
            <label>Prioridade<select name="prioridade">@foreach(['baixa','normal','alta','urgente'] as $prioridade)<option value="{{ $prioridade }}" @selected(old('prioridade',$ordemEdit?->prioridade ?? 'normal')===$prioridade)>{{ ucfirst($prioridade) }}</option>@endforeach</select></label>
            <label class="span-3">Título<input name="titulo" required value="{{ old('titulo',$ordemEdit?->titulo) }}"></label>
            <label>Custo previsto<input type="number" step="0.01" name="custo_previsto" value="{{ old('custo_previsto',$ordemEdit?->custo_previsto ?? 0) }}"></label>
            <label>Custo final<input type="number" step="0.01" name="custo_final" value="{{ old('custo_final',$ordemEdit?->custo_final ?? 0) }}"></label>
            <label>Previsão<input type="date" name="previsto_em" value="{{ old('previsto_em',$ordemEdit?->previsto_em?->format('Y-m-d')) }}"></label>
            <label class="span-3">Descrição<textarea name="descricao">{{ old('descricao',$ordemEdit?->descricao) }}</textarea></label>
            <div class="span-3"><button type="submit">Salvar Ordem</button></div>
        </form>
    </div>
    <div class="panel">
        <h2>Ordens de Serviço</h2>
        <div class="table-wrap"><table><tr><th>ID</th><th>Moto</th><th>Cliente</th><th>Status</th><th>Previsão</th><th>Custo</th><th>Ações</th></tr>
            @foreach($ordensServico as $ordem)
                <tr><td>#{{ $ordem->id }}</td><td>{{ $ordem->motocicleta?->placa ?? '-' }}<br><small>{{ $ordem->motocicleta?->modelo_nome }}</small></td><td>{{ $ordem->cliente?->nome ?? '-' }}</td><td>{!! \App\Support\RentalSupport::status($ordem->status) !!}</td><td>{{ $ordem->previsto_em?->format('d/m/Y') ?? '-' }}</td><td>{{ \App\Support\RentalSupport::moeda($ordem->custo_final ?: $ordem->custo_previsto) }}</td><td><a class="btn secondary" href="{{ route('rental.index',['page'=>'manutencao','edit'=>$ordem->id]) }}">Editar</a></td></tr>
            @endforeach
        </table></div>
    </div>
</div>
