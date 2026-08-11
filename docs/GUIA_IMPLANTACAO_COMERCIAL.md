# Guia de Implantação e Entrega Comercial

## Identidade

Defina no `.env`:

```env
APP_NAME="Gestor de Locações"
STORE_NAME="Nome da Locadora"
STORE_LEGAL_NAME="Razão Social"
STORE_DOCUMENT="00.000.000/0001-00"
STORE_CITY="Cidade"
SUPPORT_EMAIL="suporte@exemplo.com"
SUPPORT_PHONE="(00) 00000-0000"
```

`APP_NAME` identifica o produto. `STORE_NAME` identifica a empresa usuária. Não utilize o mesmo valor automaticamente para as duas finalidades.

## Credenciais iniciais

Antes de executar o seeder em produção:

```env
INITIAL_ADMIN_EMAIL=administrador@cliente.com.br
INITIAL_SUPER_ADMIN_EMAIL=plataforma@fornecedor.com.br
INITIAL_ADMIN_PASSWORD=uma-senha-forte-e-exclusiva
```

Troque ou remova `INITIAL_ADMIN_PASSWORD` do ambiente após a criação das contas. O superadministrador é destinado à administração comercial e não deve ser entregue como conta operacional comum.

## Checklist de produção

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- domínio com HTTPS;
- banco de produção exclusivo;
- `APP_KEY` gerada e protegida;
- e-mail transacional validado;
- disco privado configurado;
- backup externo automatizado;
- restauração de backup testada;
- scheduler ativo;
- worker de fila supervisionado, quando utilizado;
- credenciais reais dos gateways configuradas;
- tokens de webhook exclusivos;
- usuários e permissões revisados;
- política de privacidade, termos e contrato de suporte entregues;
- canal para solicitações relacionadas a dados pessoais definido.

## Comandos de publicação

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Execute `php artisan db:seed --force` somente na implantação inicial e após configurar as credenciais iniciais.

## Arquivos e documentos

Novos documentos são gravados no disco definido por `PRIVATE_FILESYSTEM_DISK`. Instalações atualizadas podem possuir arquivos antigos no disco público; faça inventário e migre esses arquivos antes da entrega definitiva. A rota autenticada mantém leitura temporária de arquivos legados para não interromper a operação.

## Integrações

Configure tokens exclusivos:

```env
WHATSAPP_WEBHOOK_TOKEN=
ASAAS_WEBHOOK_TOKEN=
SICOOB_WEBHOOK_TOKEN=
ITAU_WEBHOOK_TOKEN=
INTEGRATION_PREFIX=RENTAL
LICENSE_PREFIX=RENTAL
MERCHANT_REFERENCE=RENTAL
PRODUCT_ID=rental-management
```

Quando tokens não forem informados, a aplicação deriva valores do `APP_KEY`. Em produção, valores explícitos facilitam rotação e administração segura.

## Auditoria

A tabela `audit_logs` registra ações HTTP que alteram dados, usuário, rota, IP, campos enviados e resultado. Senhas, tokens e segredos são excluídos dos metadados.

## Modelo comercial suportado

A versão atual é adequada para uma instalação por empresa, com várias lojas dentro da mesma instalação. Para oferecer um SaaS centralizado compartilhando a mesma infraestrutura entre empresas, ainda é necessário introduzir isolamento por `empresa_id` em banco, arquivos, cache e filas antes de hospedar clientes diferentes na mesma base.

## Entrega ao cliente

Entregue:

- URL de produção;
- usuário administrador;
- manual do usuário;
- canais e horários de suporte;
- política de backup;
- responsabilidades sobre integrações externas;
- condições do plano e limites;
- procedimento de exportação e encerramento do serviço.
