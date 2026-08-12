# Plataforma de Gestão de Locações

Aplicação web em Laravel para administrar locadoras de motocicletas: reservas, clientes, frota, contratos, cobranças, recebimentos, inadimplência, manutenção, estoque, multas, CRM e atendimento ao cliente.

## Identidade configurável

O produto e a locadora são identidades independentes:

- `APP_NAME`: nome comercial do produto de software;
- `STORE_NAME`: nome da locadora que utiliza a instalação;
- `STORE_LEGAL_NAME` e `STORE_DOCUMENT`: dados jurídicos opcionais;
- `SUPPORT_EMAIL` e `SUPPORT_PHONE`: contatos exibidos ao usuário.

Os valores ficam centralizados em `config/branding.php` e são carregados do `.env`. Não altere views, e-mails ou código para trocar o nome da locadora.

## Instalação resumida

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Antes do seeder em produção, defina `INITIAL_ADMIN_EMAIL`, `INITIAL_SUPER_ADMIN_EMAIL` e uma senha forte em `INITIAL_ADMIN_PASSWORD`. A aplicação não exibe nem publica credenciais iniciais.

Configure também banco de dados, e-mail, domínio HTTPS, nome do produto, nome da locadora, contatos de suporte e credenciais das integrações.

## Processos em segundo plano

Configure no servidor:

```text
* * * * * cd /caminho/da-aplicacao && php artisan schedule:run >> /dev/null 2>&1
```

Quando `QUEUE_CONNECTION` não for `sync`, mantenha também um worker de filas supervisionado.

## Validação

```bash
php artisan test
php artisan view:cache
php artisan route:cache
php artisan config:cache
```

## Documentação

- Manual do usuário: `docs/MANUAL_DO_USUARIO.md`
- Implantação e entrega: `docs/GUIA_IMPLANTACAO_COMERCIAL.md`
- Manual técnico existente: `docs/MANUAL_CLIENTE_INSTALACAO.md`

## Segurança

Documentos novos de clientes são armazenados no disco privado e baixados somente por rota autenticada. Ações de escrita são registradas em `audit_logs`. Em produção, use HTTPS, backups externos testados, credenciais exclusivas por cliente e `APP_DEBUG=false`.

A recuperação de senha utiliza token expirável e resposta que não revela se o usuário existe. Os webhooks de pagamento das licenças exigem `LICENSE_PAYMENT_WEBHOOK_TOKEN`.

O portal de autoatendimento comercial está disponível em `/minha-licenca/login`; o acesso é liberado individualmente no cadastro do cliente pelo Super Admin.
