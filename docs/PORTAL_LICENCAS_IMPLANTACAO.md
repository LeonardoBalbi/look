# Implantação do Portal de Licenças

Esta branch contém o modo servidor central de licenças. Ela deve ser publicada separadamente da aplicação utilizada pelas lojas.

## 1. Domínio e banco

Crie um domínio como `licencas.seudominio.com.br` e um banco MySQL exclusivo, por exemplo `portal_licencas`. Não utilize o banco de nenhuma loja.

## 2. Configuração mínima

Copie `.env.example` para `.env` e preencha:

```env
APP_ROLE=license_server
APP_NAME="Portal de Licenças"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://licencas.seudominio.com.br
APP_KEY=

DB_DATABASE=portal_licencas
DB_USERNAME=usuario_exclusivo
DB_PASSWORD=senha-forte

INITIAL_SUPER_ADMIN_EMAIL=seu-email@dominio.com.br
INITIAL_ADMIN_PASSWORD=senha-inicial-longa
LICENSE_PAYMENT_WEBHOOK_TOKEN=token-longo-e-aleatorio
```

## 3. Instalação

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan optimize
```

Depois do primeiro acesso, altere a senha inicial e remova `INITIAL_ADMIN_PASSWORD` do ambiente.

## 4. Uso diário

1. Entre em `https://licencas.seudominio.com.br/login`.
2. Cadastre o cliente.
3. Cadastre ou escolha um plano.
4. Gere uma licença para o cliente.
5. Copie a chave gerada para o `.env` da aplicação da loja.
6. Na aplicação da loja, execute `php artisan rental:validar-licenca` para testar.

O assinante pode pagar ou solicitar renovação em `https://licencas.seudominio.com.br/minha-licenca/login`.

## 5. API usada pelas lojas

As instalações consultam:

```text
POST https://licencas.seudominio.com.br/api/licencas-portal/validar-licenca
```

A chave é vinculada automaticamente ao identificador da primeira instalação que a validar. Para transferir uma licença a outro servidor, use a opção de desvincular instalação no portal.
