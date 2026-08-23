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
    <p class="login-subtitle">Gestão financeira e operacional</p>
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    <label class="login-field">
        <span>E-mail</span>
        <input name="email" type="email" placeholder="seu@email.com.br" required autofocus value="{{ old('email') }}" autocomplete="username">
    </label>
    <label class="login-field">
        <span>Senha</span>
        <input name="senha" type="password" placeholder="Digite sua senha" required autocomplete="current-password">
    </label>
    <button class="login-submit" type="submit">Entrar</button>
    <div class="login-footer">
        <a href="{{ route('password.request') }}">Esqueci minha senha</a>
        <small>Use o acesso fornecido pelo administrador da sua empresa.</small>
    </div>
</form>
</body>
</html>
