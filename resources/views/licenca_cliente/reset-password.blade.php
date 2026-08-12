<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova senha | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('licenca-cliente.password.update') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Criar nova senha</h1>
    @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <input type="hidden" name="token" value="{{ $token }}">
    <input name="email" type="email" required autocomplete="email" value="{{ old('email',$email) }}" placeholder="E-mail">
    <input name="senha" type="password" required autocomplete="new-password" placeholder="Nova senha (mínimo 8 caracteres)">
    <input name="senha_confirmation" type="password" required autocomplete="new-password" placeholder="Repita a nova senha">
    <button>Salvar nova senha</button>
</form>
</body>
</html>
