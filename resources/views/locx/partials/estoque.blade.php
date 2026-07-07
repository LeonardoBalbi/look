<div class="cards">
    <div class="metric"><span>Produtos</span><strong>{{ $estoqueResumo['produtos'] }}</strong></div>
    <div class="metric warn"><span>Baixo estoque</span><strong>{{ $estoqueResumo['baixos'] }}</strong></div>
    <div class="metric ok"><span>Entradas no mês</span><strong>{{ number_format($estoqueResumo['entradasMes'], 2, ',', '.') }}</strong></div>
    <div class="metric danger"><span>Saídas no mês</span><strong>{{ number_format($estoqueResumo['saidasMes'], 2, ',', '.') }}</strong></div>
</div>

<div class="grid side">
    <div class="panel">
        <h2>{{ $produtoEdit ? 'Editar' : 'Novo' }} Produto</h2>
        <form method="post" action="{{ route('locx.estoque.produtos.salvar') }}" class="form-grid">
            @csrf
            <input type="hidden" name="id" value="{{ $produtoEdit?->id }}">
            <label>Loja<select name="loja_id"><option value="">Geral</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}" @selected(old('loja_id',$produtoEdit?->loja_id)==$loja->id)>{{ $loja->nome }}</option>@endforeach</select></label>
            <label class="span-2">Nome<input name="nome" required value="{{ old('nome',$produtoEdit?->nome) }}"></label>
            <label>SKU<input name="sku" value="{{ old('sku',$produtoEdit?->sku) }}"></label>
            <label>Grupo<input name="grupo" value="{{ old('grupo',$produtoEdit?->grupo) }}"></label>
            <label>Unidade<input name="unidade" required value="{{ old('unidade',$produtoEdit?->unidade ?? 'un') }}"></label>
            <label>Estoque mínimo<input type="number" step="0.01" name="estoque_minimo" value="{{ old('estoque_minimo',$produtoEdit?->estoque_minimo ?? 0) }}"></label>
            <label>Custo unitário<input type="number" step="0.01" name="custo_unitario" value="{{ old('custo_unitario',$produtoEdit?->custo_unitario ?? 0) }}"></label>
            <label>Status<select name="status"><option value="ativo" @selected(old('status',$produtoEdit?->status ?? 'ativo')==='ativo')>ativo</option><option value="inativo" @selected(old('status',$produtoEdit?->status)==='inativo')>inativo</option></select></label>
            <div class="span-3"><button type="submit">Salvar Produto</button></div>
        </form>
        <hr>
        <h2>Movimento</h2>
        <form method="post" action="{{ route('locx.estoque.movimentos.salvar') }}" class="form-grid">
            @csrf
            <label class="span-2">Produto<select name="produto_id">@foreach($produtosEstoque as $produto)<option value="{{ $produto->id }}">{{ $produto->nome }}</option>@endforeach</select></label>
            <label>Loja<select name="loja_id"><option value="">Geral</option>@foreach($lojas as $loja)<option value="{{ $loja->id }}">{{ $loja->nome }}</option>@endforeach</select></label>
            <label>Tipo<select name="tipo"><option value="entrada">entrada</option><option value="saida">saída</option><option value="ajuste">ajuste</option></select></label>
            <label>Quantidade<input type="number" step="0.01" name="quantidade" required></label>
            <label>Valor unitário<input type="number" step="0.01" name="valor_unitario" value="0"></label>
            <label class="span-3">Origem<input name="origem" placeholder="Compra, OS, ajuste interno"></label>
            <label class="span-3">Observação<textarea name="observacao"></textarea></label>
            <div class="span-3"><button type="submit">Registrar Movimento</button></div>
        </form>
    </div>
    <div class="panel">
        <h2>Produtos em Estoque</h2>
        <div class="table-wrap"><table><tr><th>Produto</th><th>Loja</th><th>Saldo</th><th>Mínimo</th><th>Custo</th><th>Status</th><th>Ações</th></tr>
            @foreach($produtosEstoque as $produto)
                @php($saldo = (float) ($estoqueSaldos[$produto->id] ?? 0))
                <tr><td>{{ $produto->nome }}<br><small>{{ $produto->sku ?: $produto->grupo }}</small></td><td>{{ $produto->loja?->nome ?? 'Geral' }}</td><td>{{ number_format($saldo, 2, ',', '.') }} {{ $produto->unidade }}</td><td>{{ number_format((float) $produto->estoque_minimo, 2, ',', '.') }}</td><td>{{ \App\Support\Locx::moeda($produto->custo_unitario) }}</td><td>{!! \App\Support\Locx::status($produto->status) !!}</td><td><a class="btn secondary" href="{{ route('locx.index',['page'=>'estoque','edit'=>$produto->id]) }}">Editar</a></td></tr>
            @endforeach
        </table></div>
        <hr>
        <h2>Últimos Movimentos</h2>
        <div class="table-wrap"><table><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Origem</th></tr>
            @foreach($movimentosEstoque as $movimento)
                <tr><td>{{ $movimento->movimentado_em?->format('d/m/Y H:i') }}</td><td>{{ $movimento->produto?->nome }}</td><td>{!! \App\Support\Locx::status($movimento->tipo) !!}</td><td>{{ number_format((float) $movimento->quantidade, 2, ',', '.') }}</td><td>{{ $movimento->origem ?: '-' }}</td></tr>
            @endforeach
        </table></div>
    </div>
</div>
