<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | {{ $branding['store_name'] }} — {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('rental.login.store') }}">
    @csrf
    <x-brand class="login-brand" />
    <p>Gestão financeira e operacional</p>
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    <input name="email" type="email" placeholder="E-mail" required autofocus value="{{ old('email') }}" autocomplete="username">
    <input name="senha" type="password" placeholder="Senha" required autocomplete="current-password">
    <button type="submit">Entrar</button>
    <a href="{{ route('password.request') }}">Esqueci minha senha</a>
    <small>Use o acesso fornecido pelo administrador da sua empresa.</small>
</form>
</body>
</html>
