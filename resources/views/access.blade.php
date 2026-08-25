@php
    $selectedType = in_array(request('tipo'), ['equipe', 'cliente'], true) ? request('tipo') : 'equipe';
    $stores = collect(config('domains.stores'));
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#090b0d">
    <title>Escolha sua unidade | LocX</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/home.css') }}">
</head>
<body class="access-body">
<header class="access-header">
    <a href="/" aria-label="Voltar para a página inicial">
        <img src="{{ \App\Support\RentalSupport::asset('assets/img/logo-locx.jpg') }}" alt="LocX Aluguel de Motos" width="980" height="436">
    </a>
    <a href="/">← Voltar ao site</a>
</header>

<main class="access-main">
    <section class="access-card">
        <span class="section-kicker">Acesso LocX</span>
        <h1>Encontre a sua unidade.</h1>
        <p>Escolha como você quer entrar e selecione a loja responsável pelo seu atendimento.</p>

        <div class="access-tabs" role="tablist" aria-label="Tipo de acesso">
            <a class="{{ $selectedType === 'equipe' ? 'active' : '' }}" href="/acesso?tipo=equipe">Equipe da loja</a>
            <a class="{{ $selectedType === 'cliente' ? 'active' : '' }}" href="/acesso?tipo=cliente">Cliente</a>
        </div>

        <div class="store-options">
            @forelse($stores as $store)
                <a href="{{ route('access.store', ['type' => $selectedType, 'store' => $store['slug']], absolute: false) }}">
                    <span><small>UNIDADE</small><strong>{{ $store['name'] }}</strong></span>
                    <b aria-hidden="true">→</b>
                </a>
            @empty
                <p class="access-empty">Nenhuma unidade pública foi configurada.</p>
            @endforelse
        </div>

        @if($selectedType === 'equipe')
            <div class="superadmin-access">
                <span>Administração da plataforma</span>
                <a href="{{ config('domains.admin_url') }}/login">Acesso exclusivo do Superadmin <b>↗</b></a>
            </div>
        @endif
    </section>
</main>
</body>
</html>
