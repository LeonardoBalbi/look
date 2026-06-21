# Templates necessários na Meta

Crie todos em `Portuguese (BR)` e sem botão de chamada. Os nomes das variáveis
devem ser exatamente iguais aos abaixo.

## locx_lembrete_vencimento

```text
Olá! A cobrança de {{customer_name}}, referente à moto {{vehicle_plate}}, vence em {{due_date}}.

Valor: {{amount}}
PIX: {{pix_code}}

Em caso de dúvida, fale com a LocX.
```

## locx_vencimento_pix

```text
Olá, {{customer_name}}. A cobrança da moto {{vehicle_plate}} vence hoje, {{due_date}}.

Valor: {{amount}}
PIX: {{pix_code}}

Após o pagamento, aguarde a confirmação da LocX.
```

## locx_cobranca_atraso

```text
Olá! Identificamos uma pendência no contrato de {{customer_name}} referente à moto {{vehicle_plate}}.

Dias em atraso: {{days_overdue}}
Saldo atualizado: {{updated_balance}}

PIX: {{pix_code}}

Em caso de dúvida, entre em contato com a LocX.
```

## locx_aviso_gerente

```text
Aviso interno LocX: o cliente {{customer_name}}, moto {{vehicle_plate}}, está há {{days_overdue}} dias em atraso.

Saldo: {{updated_balance}}
Contato do cliente: {{customer_phone}}

Verifique o caso no sistema.
```

## locx_pagamento_confirmado

```text
Olá, {{customer_name}}. Confirmamos o pagamento de {{amount_paid}} via {{payment_method}} em {{payment_date}}.

Referência da cobrança: {{charge_id}}

Obrigado por utilizar a LocX.
```
