# Automações LocX com n8n

Esta pasta contém uma alternativa ao agendamento direto no Laravel. O Laravel
continua responsável pelos dados, regras financeiras, PIX e envio ao WhatsApp.
O n8n agenda e acompanha as cinco rotinas:

1. três dias antes: lembrete;
2. no vencimento: geração/confirmação de PIX;
3. um dia depois: cobrança;
4. três dias depois: aviso ao gerente;
5. pagamento registrado: confirmação ao cliente.

## Segurança

As automações vêm desativadas por padrão. Primeiro teste com o WhatsApp em modo
`demo`. O endpoint do Laravel exige um Bearer Token compartilhado somente entre
LocX e n8n.

No `.env` do Laravel:

```env
N8N_AUTOMATIONS_ENABLED=false
N8N_API_TOKEN=gere-um-token-longo
```

Copie `n8n/.env.example` para `n8n/.env` e use o mesmo token em
`LOCX_N8N_TOKEN`.

## Inicialização com Docker

```powershell
Copy-Item n8n\.env.example n8n\.env
docker compose --env-file n8n\.env -f n8n\docker-compose.yml up -d
```

Abra `http://localhost:5678`, crie o usuário proprietário e importe os cinco
arquivos da pasta `n8n/workflows`.

Também é possível importar via CLI:

```powershell
docker compose --env-file n8n\.env -f n8n\docker-compose.yml exec n8n n8n import:workflow --separate --input=/workflows
```

Revise cada workflow e clique em **Publish**. Workflows agendados só executam
automaticamente quando publicados.

## Teste seguro

1. Mantenha `N8N_AUTOMATIONS_ENABLED=false`.
2. Inicie Laravel e n8n.
3. Confirme a API:

```text
GET http://127.0.0.1:8000/api/n8n/status
Authorization: Bearer SEU_TOKEN
```

4. Coloque o WhatsApp do LocX em `Demo`.
5. Altere para `N8N_AUTOMATIONS_ENABLED=true`.
6. Execute manualmente cada workflow.
7. Confira `automacao_logs` e “Últimos envios” no LocX.
8. Só então use o modo oficial.

## Templates esperados

- `locx_lembrete_vencimento`
- `locx_vencimento_pix`
- `locx_cobranca_atraso`
- `locx_aviso_gerente`
- `locx_pagamento_confirmado`

Todos devem existir em `pt_BR`, estar aprovados e usar exatamente os nomes de
variáveis enviados pelo Laravel.

Os textos e as variáveis estão em [TEMPLATES_WHATSAPP.md](TEMPLATES_WHATSAPP.md).
