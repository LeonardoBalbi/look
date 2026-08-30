<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal do Cliente | {{ $branding['store_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('cliente.login.store') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Área do Cliente</h1>
    <p>Acesse suas cobranças, pagamentos e mensagens.</p>
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif
    <input name="email" type="email" placeholder="E-mail" required autofocus value="{{ old('email') }}">
    <input name="senha" type="password" placeholder="Senha" required autocomplete="current-password">
    <button type="submit">Entrar na minha área</button>
    <small>O acesso é liberado pela equipe {{ $branding['store_name'] }} no cadastro do cliente.</small>
</form>
</body>
</html>
