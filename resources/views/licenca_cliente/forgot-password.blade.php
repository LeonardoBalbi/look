<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar acesso | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<form class="login-card" method="post" action="{{ route('licenca-cliente.password.email') }}">
    @csrf
    <x-brand class="login-brand" />
    <h1>Recuperar acesso</h1>
    <p>Enviaremos um link com validade de 60 minutos.</p>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <input name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}" placeholder="E-mail da empresa">
    <button>Enviar link</button>
    <a href="{{ route('licenca-cliente.login') }}">Voltar</a>
</form>
</body>
</html>
