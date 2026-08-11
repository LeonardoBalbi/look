@include('errors.minimal', ['code' => 403, 'title' => 'Acesso não autorizado', 'message' => $exception->getMessage() ?: 'Seu perfil não possui permissão para acessar esta área.'])
