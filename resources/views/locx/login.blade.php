<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login LocX</title>
    <link rel="stylesheet" href="{{ \App\Support\Locx::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('locx.login.store') }}">
    @csrf
    <div class="logo"></div>
    <h1>LocX</h1>
    <p>Gestão financeira e operacional</p>
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif
    <input name="email" type="email" placeholder="E-mail" required autofocus value="{{ old('email', 'admin@locx.com.br') }}">
    <input name="senha" type="password" placeholder="Senha" required>
    <button type="submit">Entrar</button>
    <small>Usuário inicial: admin@locx.com.br / 123456</small>
</form>
</body>
</html>
