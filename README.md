# LocX — Laravel 12

Sistema de gestão financeira e operacional desenvolvido em Laravel 12.

A interface original foi preservada, mas a aplicação agora usa rotas, controllers,
models Eloquent, autenticação, autorização, views Blade, validação, CSRF e serviços
Laravel. Não existem mais páginas executadas com `require`, sessões iniciadas
manualmente, consultas PDO dentro das views ou regras de negócio em templates.

## Instalação

Manual completo para cliente, uso diario e instalacao em servidor:

```text
docs/MANUAL_CLIENTE_INSTALACAO.md
```

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

No Windows PowerShell, use `Copy-Item .env.example .env`.

Acesse `http://127.0.0.1:8000`.

Login inicial:

```text
admin@locx.com.br
123456
```

Troque essa senha antes de publicar o sistema.

## Publicação

O domínio deve apontar para a pasta `public`. Configure no `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
FILESYSTEM_DISK=public
```

Depois execute:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Garanta permissão de escrita em `storage` e `bootstrap/cache`.

## Visual

O estilo aprovado permanece em `public/locx/assets`, incluindo CSS, JavaScript,
logo e manuais.

## Testes

```bash
php artisan test
```
