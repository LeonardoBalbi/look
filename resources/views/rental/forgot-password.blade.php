<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar senha | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('password.email') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Recuperar senha</h1>
    <p>Informe o e-mail do seu usuário. O link terá validade de 60 minutos.</p>
    @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <input name="email" type="email" placeholder="E-mail" required autofocus value="{{ old('email') }}" autocomplete="email">
    <button type="submit">Enviar link</button>
    <a href="{{ route('rental.login') }}">Voltar para o acesso</a>
</form>
</body>
</html>
