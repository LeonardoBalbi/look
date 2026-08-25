# Arquitetura comercial por domínio

## Resultado

O produto é entregue como duas aplicações separadas, mantidas em branches diferentes:

| Aplicação | Branch | Exemplo de domínio | Banco |
|---|---|---|---|
| Site e acesso às unidades | `codex/aplicacao-por-loja` | `locx.com.br` | não depende de dados privados para navegar |
| Aplicação da loja | `codex/aplicacao-por-loja` | `barra.locx.com.br` | um banco exclusivo por licença |
| Portal do Superadmin | `codex/portal-licencas` | `admin.locx.com.br` | um banco central de licenças |

## Como elas conversam

1. Você cria o cliente, o plano e a licença no portal central.
2. O portal gera uma chave de licença.
3. A instalação da loja recebe a URL da API e a chave em seu arquivo `.env`.
4. A loja envia periodicamente sua chave, identificador da instalação e uso para a API HTTPS.
5. O portal responde com status, vencimento, plano, módulos e limite de usuários.
6. A loja mantém um cache local para tolerar indisponibilidade temporária da internet.

## Configuração da aplicação da loja

```env
APP_ROLE=store
APP_URL=https://barra.locx.com.br
LOCX_BASE_DOMAIN=locx.com.br
LOCX_SITE_URL=https://locx.com.br
LOCX_ADMIN_URL=https://admin.locx.com.br
LOCX_PUBLIC_STORES="barra|Barra da Tijuca"
SINGLE_STORE_MODE=true
RENTAL_LICENSE_API_URL=https://admin.locx.com.br/api/licencas-portal
RENTAL_LICENSE_KEY=CHAVE-GERADA-NO-PORTAL
```

Cada cliente deve possuir seu próprio `APP_URL`, banco, `APP_KEY` e chave de licença.

## Configuração do portal central

```env
APP_ROLE=license_server
APP_NAME="Portal de Licenças"
APP_URL=https://admin.locx.com.br
LOCX_BASE_DOMAIN=locx.com.br
LOCX_SITE_URL=https://locx.com.br
LOCX_ADMIN_URL=https://admin.locx.com.br
DB_DATABASE=portal_licencas
INITIAL_SUPER_ADMIN_EMAIL=seu-email@dominio.com.br
INITIAL_ADMIN_PASSWORD=uma-senha-longa-e-exclusiva
LICENSE_PAYMENT_WEBHOOK_TOKEN=gere-um-token-longo-e-aleatorio
```

O portal central não deve compartilhar banco com nenhuma loja.

## Fluxo público de acesso

1. Em `locx.com.br`, equipe e cliente escolhem sua unidade em `/acesso`.
2. A equipe da Barra segue para `barra.locx.com.br/login`.
3. O cliente da Barra segue para `barra.locx.com.br/portal/login`.
4. O Superadmin segue exclusivamente para `admin.locx.com.br/login`.
5. Após autenticar, o Superadmin é direcionado para `/licencas-portal`; contas comuns são recusadas.

## Segurança e operação

- Use HTTPS nos dois domínios.
- Nunca envie a chave de licença por URL; a integração utiliza requisição POST.
- Não reutilize `APP_KEY`, banco ou senha entre clientes.
- Faça backup independente do portal e de cada loja.
- Restrinja o Super Admin do portal apenas à equipe responsável pelas licenças.
- Configure o agendador do Laravel para executar as validações periódicas.

## O que fica inacessível em cada domínio

Com `APP_ROLE=store`, o portal central, o portal de pagamentos de licença e a API emissora retornam 404.

Com `APP_ROLE=license_server`, CRM, clientes, motos, contratos, cobranças operacionais e webhooks da loja retornam 404. Apenas autenticação, administração de licenças, área de pagamento do assinante e API de licenças ficam disponíveis.
