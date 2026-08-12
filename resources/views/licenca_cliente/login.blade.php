<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha Licença | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('licenca-cliente.login.store') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Minha Licença</h1>
    <p>Acesse seu plano, cobranças e pagamentos.</p>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <input name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}" placeholder="E-mail da empresa">
    <input name="senha" type="password" required autocomplete="current-password" placeholder="Senha">
    <button>Entrar</button>
    <a href="{{ route('licenca-cliente.password.request') }}">Esqueci minha senha</a>
</form>
</body>
</html>
