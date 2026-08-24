# Orientações: cron e cobranças automáticas

Este documento explica como colocar a geração automática de cobranças em funcionamento no servidor e como tratar contratos antigos com datas incorretas.

## 1. Como o fluxo funciona

Ao cadastrar um contrato marcado como **Cobrança automática**, o sistema cria imediatamente a primeira cobrança. Depois disso, o agendador do Laravel cria as cobranças seguintes conforme a recorrência semanal, quinzenal ou mensal.

Para uma parcela aparecer no financeiro, no portal do cliente ou na inadimplência, deve existir um registro de cobrança. A data **Próxima cobrança** do contrato, sozinha, não representa uma parcela.

O sistema impede duplicidade usando a combinação de contrato e vencimento.

## 2. Configuração obrigatória no `.env`

No arquivo `.env` do servidor, confirme:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Sao_Paulo

RENTAL_RECORRENCIA_ATIVA=true
RENTAL_RECORRENCIA_HORARIO=07:00
RENTAL_RECORRENCIA_DIAS_ANTECEDENCIA=0
RENTAL_RECORRENCIA_MAX_POR_CONTRATO=12
```

Opções de geração e envio:

```env
RENTAL_RECORRENCIA_GERAR_PIX=false
RENTAL_RECORRENCIA_ENVIAR_WHATSAPP=false
RENTAL_RECORRENCIA_ENVIAR_EMAIL=false
RENTAL_RECORRENCIA_ENVIAR_TELEGRAM=false
```

Ative PIX ou mensagens somente depois de testar as credenciais do respectivo serviço. A cobrança pode ser criada automaticamente mesmo com todas essas opções em `false`.

Depois de alterar o `.env`, execute na raiz da aplicação:

```bash
php artisan optimize:clear
php artisan config:cache
```

## 3. Ativação do cron

O cron deve chamar o Laravel a cada minuto. O próprio Laravel decide executar a cobrança no horário configurado.

Descubra o caminho do PHP:

```bash
which php
```

Descubra a pasta do projeto. Ela deve ser a pasta que contém o arquivo `artisan`, não a pasta `public`.

Exemplo para VPS/Linux, usando `crontab -e`:

```cron
* * * * * cd /var/www/rental-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Exemplo comum no cPanel:

```cron
* * * * * cd /home/USUARIO/rental-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Substitua os caminhos pelos caminhos reais do servidor.

Durante a configuração, é possível usar um arquivo de log para encontrar erros:

```cron
* * * * * cd /var/www/rental-app && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Depois que estiver funcionando, o redirecionamento pode voltar para `/dev/null`.

## 4. Verificação do servidor

Confira as tarefas reconhecidas:

```bash
php artisan schedule:list
```

A lista deve conter `rental:gerar-cobrancas-recorrentes` no horário configurado.

Faça uma simulação, sem alterar o banco:

```bash
php artisan rental:gerar-cobrancas-recorrentes --dry-run
```

Revise a quantidade, os clientes, os vencimentos e os valores exibidos. Somente depois execute a geração real:

```bash
php artisan rental:gerar-cobrancas-recorrentes
```

Não execute repetidamente se a simulação apresentar datas ou valores incorretos. Corrija primeiro os contratos.

## 5. Conferência do relógio

O vencimento depende da data do servidor. Confira:

```bash
date
php -r "echo date('c'), PHP_EOL;"
```

O fuso esperado é `America/Sao_Paulo`. Em uma VPS com `systemd`, também é possível consultar:

```bash
timedatectl status
```

Se o calendário real estiver em setembro, mas o servidor informar agosto, corrija o relógio/NTP do servidor antes de gerar cobranças.

## 6. Correção dos contratos antigos do Adalberto

Antes de qualquer alteração, faça backup do banco.

Localize os contratos sem presumir que os IDs serão iguais em todos os servidores:

```sql
SELECT
    c.id,
    cl.nome,
    c.data_inicio,
    c.data_fim,
    c.valor_contratado,
    c.forma_cobranca,
    c.proxima_cobranca_em,
    c.cobranca_automatica,
    c.status
FROM contratos c
JOIN clientes cl ON cl.id = c.cliente_id
WHERE cl.nome LIKE '%ADALBERTO PEREIRA BELINHA JUNIOR%'
ORDER BY c.id;
```

Na base analisada, foram encontrados dois problemas:

- próxima cobrança anterior ao início do contrato;
- data de início gravada com o ano `0004`.

Confirme no contrato assinado ou no sistema anterior as datas corretas. Não tente adivinhar. Depois, atualize somente o contrato conferido:

```sql
START TRANSACTION;

UPDATE contratos
SET
    data_inicio = 'AAAA-MM-DD',
    proxima_cobranca_em = 'AAAA-MM-DD',
    cobranca_automatica = 1
WHERE id = ID_CONFIRMADO;

SELECT id, data_inicio, proxima_cobranca_em, valor_contratado, forma_cobranca, status
FROM contratos
WHERE id = ID_CONFIRMADO;

COMMIT;
```

Troque `AAAA-MM-DD` e `ID_CONFIRMADO` pelos dados verificados. Se o resultado da consulta estiver errado, execute `ROLLBACK` em vez de `COMMIT`.

Após corrigir os contratos, execute primeiro a simulação:

```bash
php artisan rental:gerar-cobrancas-recorrentes --dry-run
```

Confira especialmente:

- quantidade de parcelas retroativas;
- valor de cada parcela;
- periodicidade semanal, quinzenal ou mensal;
- data final do contrato;
- limite máximo de 12 cobranças por execução.

Somente depois da conferência execute a geração real.

## 7. Como o cliente recebe a informação

Quando a cobrança existe, ela pode aparecer no portal do cliente com valor, vencimento, status e PIX. O envio automático depende de o cliente ter os dados cadastrados e do canal estar ativado:

- WhatsApp: número válido e integração configurada;
- e-mail: endereço válido e SMTP configurado;
- Telegram: cliente vinculado ao bot;
- portal: acesso do cliente liberado.

O término da vigência do contrato é diferente do vencimento de uma parcela. Atualmente, as cobranças recorrentes tratam as parcelas; o aviso específico de fim do contrato deve ser acompanhado separadamente.

## 8. Checklist final

- [ ] Backup do banco realizado.
- [ ] Data, hora e fuso do servidor corretos.
- [ ] `RENTAL_RECORRENCIA_ATIVA=true`.
- [ ] Cache de configuração recriado.
- [ ] Cron executando a cada minuto.
- [ ] `schedule:list` mostra a tarefa de cobrança.
- [ ] Contratos antigos revisados.
- [ ] Simulação executada sem valores inesperados.
- [ ] Primeira cobrança visível no financeiro.
- [ ] Cobrança vencida visível na inadimplência.
- [ ] Portal e canais de aviso testados.

