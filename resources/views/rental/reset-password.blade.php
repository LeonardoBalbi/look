<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova senha | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('password.update') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Criar nova senha</h1>
    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <input type="hidden" name="token" value="{{ $token }}">
    <input name="email" type="email" placeholder="E-mail" required value="{{ old('email', $email) }}" autocomplete="email">
    <input name="senha" type="password" placeholder="Nova senha (mínimo 8 caracteres)" required autocomplete="new-password">
    <input name="senha_confirmation" type="password" placeholder="Repita a nova senha" required autocomplete="new-password">
    <button type="submit">Salvar nova senha</button>
    <a href="{{ route('rental.login') }}">Voltar para o acesso</a>
</form>
</body>
</html>
