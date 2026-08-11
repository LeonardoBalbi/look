<div class="look-page">
    <section class="look-hero">
        <div>
            <span>Look Operacional</span>
            <h2>{{ $lookModulo['titulo'] }}</h2>
            <p>{{ $lookModulo['subtitulo'] }}</p>
        </div>
        <div class="look-hero-mark">{!! \App\Support\RentalSupport::icon($page) !!}</div>
    </section>

    @if($page === 'reservas')
        <div class="rental-flow" aria-label="Fluxo da locação">
            @foreach(['Pedido', 'Documentos', 'Confirmação', 'Retirada', 'Devolução'] as $etapa)
                <div><span>{{ $loop->iteration }}</span><strong>{{ $etapa }}</strong></div>
            @endforeach
        </div>
    @endif

    <div class="cards">
        @foreach($lookModulo['indicadores'] as $indicador)
            <div class="metric {{ $indicador['tipo'] }}">
                <span>{{ $indicador['label'] }}</span>
                <strong>{{ $indicador['valor'] }}</strong>
                <small>base atual</small>
            </div>
        @endforeach
    </div>

    <div class="grid side look-layout">
        <div class="panel">
            <div class="section-head">
                <div>
                    <h2>{{ $user->pode($page, 'criar') ? 'Novo registro' : 'Registros do modulo' }}</h2>
                    <p class="crm-subtitle">{{ $user->pode($page, 'criar') ? 'Salve acompanhamentos, pendencias, valores e prazos deste modulo.' : 'Este perfil consulta os registros deste modulo.' }}</p>
                </div>
            </div>
            @if($user->pode($page, 'criar'))
                <form method="post" action="{{ route('rental.look-modulos.salvar') }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="modulo" value="{{ $page }}">
                    <label>Loja
                        <select name="loja_id">
                            <option value="">Geral</option>
                            @foreach($lojas as $loja)
                                <option value="{{ $loja->id }}">{{ $loja->nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="span-2">{{ $lookModulo['form']['titulo'] }}<input name="titulo" required value="{{ old('titulo') }}"></label>
                    <label>{{ $lookModulo['form']['pessoa'] }}<input name="pessoa" value="{{ old('pessoa') }}"></label>
                    <label>{{ $lookModulo['form']['documento'] }}<input name="documento" value="{{ old('documento') }}"></label>
                    <label>{{ $lookModulo['form']['telefone'] }}<input name="telefone" value="{{ old('telefone') }}"></label>
                    <label>{{ $lookModulo['valor_label'] ?? 'Valor' }}<input type="number" step="0.01" name="valor" value="{{ old('valor', 0) }}"></label>
                    <label>{{ $lookModulo['data_label'] ?? 'Prazo / vencimento' }}<input type="date" name="vencimento" value="{{ old('vencimento') }}"></label>
                    <label>Status
                        <select name="status">
                            @foreach(($lookModulo['status_labels'] ?? ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado']) as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('status', 'aberto') === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="span-3">Detalhes<textarea name="descricao" placeholder="{{ $lookModulo['detalhes_placeholder'] ?? 'Escreva o combinado, proximo passo, responsavel ou observacao importante.' }}">{{ old('descricao') }}</textarea></label>
                    <div class="span-3"><button type="submit">Salvar registro</button></div>
                </form>
            @else
                <p class="empty">A criação de registros não está liberada para este perfil.</p>
            @endif
        </div>

        <div class="panel">
            <h2>Rotinas do modulo</h2>
            <div class="look-routines">
                @foreach($lookModulo['rotinas'] as $rotina)
                    <article class="look-routine">
                        <div>
                            <strong>{{ $rotina['nome'] }}</strong>
                            <p>{{ $rotina['descricao'] }}</p>
                        </div>
                        <span class="tag {{ in_array($rotina['status'], ['ativo', 'em uso'], true) ? 'ok' : 'warn' }}">{{ $rotina['status'] }}</span>
                    </article>
                @endforeach
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="section-head">
            <div>
                <h2>Registros salvos</h2>
                <p class="crm-subtitle">Historico deste modulo para a equipe acompanhar sem perder contexto.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <tr><th>ID</th><th>Titulo</th><th>Pessoa</th><th>Referencia</th><th>Loja</th><th>Valor</th><th>Prazo</th><th>Status</th><th>Criado por</th></tr>
                @forelse($lookRegistros as $registro)
                    <tr>
                        <td>#{{ $registro->id }}</td>
                        <td>{{ $registro->titulo }}<br><small>{{ \Illuminate\Support\Str::limit($registro->descricao, 80) }}</small></td>
                        <td>{{ $registro->pessoa ?: '-' }}<br><small>{{ $registro->telefone ?: '' }}</small></td>
                        <td>{{ $registro->documento ?: '-' }}</td>
                        <td>{{ $registro->loja?->nome ?? 'Geral' }}</td>
                        <td>{{ \App\Support\RentalSupport::moeda($registro->valor) }}</td>
                        <td>{{ $registro->vencimento?->format('d/m/Y') ?? '-' }}</td>
                        <td>{!! \App\Support\RentalSupport::status($registro->status) !!}</td>
                        <td>{{ $registro->usuario?->nome ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">Nenhum registro ainda. Cadastre o primeiro acompanhamento acima.</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div class="grid side look-layout">
        <div class="panel">
            <h2>Atalhos relacionados</h2>
            <div class="look-shortcuts">
                @forelse($lookRelacionados as $modulo)
                    <a class="module-card" href="{{ route('rental.index', ['page' => $modulo]) }}">
                        <i>{!! \App\Support\RentalSupport::icon($modulo) !!}</i>
                        <div>
                            <strong>{{ $pages[$modulo] }}</strong>
                            <small>Abrir rotina conectada</small>
                        </div>
                    </a>
                @empty
                    <p class="empty">Nenhum atalho liberado para este usuario.</p>
                @endforelse
            </div>
        </div>
        <div class="panel">
            <h2>Como usar bem</h2>
            <div class="look-steps">
                <div><span>1</span><strong>Registre o fato</strong><small>Coloque titulo claro, pessoa ou referencia e prazo quando houver.</small></div>
                <div><span>2</span><strong>Acompanhe o status</strong><small>Use aberto, em andamento, pendente e concluido para organizar a rotina.</small></div>
                <div><span>3</span><strong>Conecte com os modulos</strong><small>Use os atalhos para ir ao cadastro, financeiro, CRM ou frota.</small></div>
            </div>
        </div>
    </div>
</div>
