# LOCX Laravel v9 - Final sem licenciamento

Esta versão foi limpa para instalação local/servidor sem dependência de licença.

Removido/desativado:

- `license.json`
- pasta de gerador de licença
- `private_key.pem`
- `public_key.pem`
- validação de domínio/IP/banco por licença
- qualquer bloqueio de instalação por licença

## Importante

Extraia este ZIP em uma pasta nova. Não jogue por cima da pasta antiga, porque arquivos antigos de licenciamento podem continuar lá.

Exemplo:

```bash
cd D:\
```

Extraia o ZIP e entre na pasta:

```bash
cd locx-laravel-v9-final-sem-licenca
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Acesse:

```text
http://127.0.0.1:8000/locx/index.php
```

Login inicial:

```text
admin@locx.com.br
123456
```

## Se já existia banco antigo

Se puder apagar tudo e recriar:

```bash
php artisan migrate:fresh --seed
```

Se não puder apagar os dados, crie outro banco vazio e configure o `.env`:

```env
DB_DATABASE=locx_laravel
DB_USERNAME=root
DB_PASSWORD=
```

Depois rode:

```bash
php artisan migrate --seed
```

## Observação

O Laravel já possui a função global `e()` e `redirect()`. Nesta versão, os conflitos com funções antigas do LOCX foram ajustados.
