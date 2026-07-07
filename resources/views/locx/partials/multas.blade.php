<div class="cards">
    <div class="metric warn"><span>Multas abertas</span><strong>{{ $multasResumo['abertas'] }}</strong></div>
    <div class="metric danger"><span>Valor aberto</span><strong>{{ \App\Support\Locx::moeda($multasResumo['valorAberto']) }}</strong></div>
    <div class="metric ok"><span>Pagas no mês</span><strong>{{ $multasResumo['pagasMes'] }}</strong></div>
    <div class="metric"><span>Vencem em 7 dias</span><strong>{{ $multasResumo['vencendo'] }}</strong></div>
</div>

<div class="grid side">
    <div class="panel">
        <h2>{{ $multaEdit ? 'Editar' : 'Nova' }} Multa</h2>
        <form method="post" action="{{ route('locx.multas.salvar') }}" class="form-grid">
            @csrf
            <input type="hidden" name="id" value="{{ $multaEdit?->id }}">
            <label>Loja<select name="loja_id"><option value="">Selecione</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$multaEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
            <label>Moto<select name="motocicleta_id"><option value="">Sem moto</option>@foreach($motos as $moto)<option value="{{ $moto->id }}" @selected(old('motocicleta_id',$multaEdit?->motocicleta_id)==$moto->id)>{{ $moto->placa ?: 'sem placa' }} - {{ $moto->modelo }}</option>@endforeach</select></label>
            <label>Cliente<select name="cliente_id"><option value="">Sem cliente</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id',$multaEdit?->cliente_id)==$cliente->id)>{{ $cliente->nome }}</option>@endforeach</select></label>
            <label class="span-2">Contrato<select name="contrato_id"><option value="">Sem contrato</option>@foreach($contratos as $contrato)<option value="{{ $contrato->id }}" @selected(old('contrato_id',$multaEdit?->contrato_id)==$contrato->id)>#{{ $contrato->id }} - {{ $contrato->cliente?->nome }} / {{ $contrato->motocicleta?->placa }}</option>@endforeach</select></label>
            <label>Auto<input name="auto_infracao" value="{{ old('auto_infracao',$multaEdit?->auto_infracao) }}"></label>
            <label>Órgão<input name="orgao" value="{{ old('orgao',$multaEdit?->orgao) }}"></label>
            <label>Valor<input type="number" step="0.01" name="valor" required value="{{ old('valor',$multaEdit?->valor ?? 0) }}"></label>
            <label>Ocorrência<input type="date" name="ocorrida_em" value="{{ old('ocorrida_em',$multaEdit?->ocorrida_em?->format('Y-m-d')) }}"></label>
            <label>Vencimento<input type="date" name="vencimento" value="{{ old('vencimento',$multaEdit?->vencimento?->format('Y-m-d')) }}"></label>
            <label>Status<select name="status">@foreach(['aberta','em_recurso','transferida','paga','cancelada'] as $status)<option value="{{ $status }}" @selected(old('status',$multaEdit?->status ?? 'aberta')===$status)>{{ str_replace('_',' ', $status) }}</option>@endforeach</select></label>
            <label class="span-3">Descrição<textarea name="descricao">{{ old('descricao',$multaEdit?->descricao) }}</textarea></label>
            <div class="span-3"><button type="submit">Salvar Multa</button></div>
        </form>
    </div>
    <div class="panel">
        <h2>Multas</h2>
        <div class="table-wrap"><table><tr><th>ID</th><th>Moto</th><th>Cliente</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr>
            @foreach($multasTransito as $multa)
                <tr><td>#{{ $multa->id }}</td><td>{{ $multa->motocicleta?->placa ?? '-' }}</td><td>{{ $multa->cliente?->nome ?? '-' }}</td><td>{{ \App\Support\Locx::moeda($multa->valor) }}</td><td>{{ $multa->vencimento?->format('d/m/Y') ?? '-' }}</td><td>{!! \App\Support\Locx::status($multa->status) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'multas','edit'=>$multa->id]) }}">Editar</a></td></tr>
            @endforeach
        </table></div>
    </div>
</div>
