# Manual do Cliente e Instalacao - LocX

Este documento explica, em linguagem simples, o que o sistema LocX faz, como usar no dia a dia e como instalar em um servidor.

## 1. O que e o LocX

O LocX e um sistema para controlar locacao de motos, clientes, contratos, cobrancas, pagamentos, PIX, inadimplencia, WhatsApp, e-mail e CRM.

Na pratica, ele ajuda a empresa a responder:

- quem esta com moto alugada;
- quem esta com contrato ativo;
- quem tem cobranca em aberto;
- quem esta atrasado;
- quanto cada cliente deve;
- qual PIX deve ser enviado;
- quem precisa ser cobrado hoje;
- quais tarefas ja foram feitas no atendimento.

Antes o sistema funcionava mais como cadastro e financeiro. Agora, com CRM e automacoes, ele tambem organiza o trabalho que precisa ser feito depois da cobranca.

## 2. Principais modulos

### Dashboard

E a primeira tela de visao geral.

Mostra indicadores como total em aberto, inadimplencia, clientes em atraso, resumo por loja e atalhos para os outros modulos.

Exemplo: o gerente entra de manha e ve rapidamente se aumentou o valor atrasado ou se alguma loja precisa de atencao.

### Clientes

E onde ficam os dados cadastrais do cliente.

Serve para cadastrar ou editar nome, CPF/CNPJ, RG, CNH, telefone, WhatsApp, e-mail, endereco, documentos e status do cliente.

Importante:

Use `Clientes` para cadastro. Use `CRM` para acompanhamento.

Exemplo: se o cliente mudou de telefone, altere em `Clientes`. Se o cliente prometeu pagar amanha, registre em `CRM`.

### Motos

E onde fica o cadastro da frota.

Serve para controlar modelo, marca, ano, placa, RENAVAM, chassi, seguro, rastreador e status operacional.

Status comuns:

- disponivel;
- alugada;
- manutencao;
- recuperacao;
- encerrada.

Exemplo: quando uma moto entra em manutencao, altere o status para `manutencao`.

### Contratos

E onde a locacao e registrada.

Um contrato liga cliente, moto, loja, valor, forma de cobranca, data de inicio e status.

Formas de cobranca:

- semanal;
- quinzenal;
- mensal.

Para gerar cobrancas recorrentes automaticamente, o contrato precisa estar ativo e com cobranca automatica marcada.

Exemplo: cliente Maria alugou uma moto por R$ 250 por semana. O contrato deve ser criado com forma de cobranca `semanal`.

### Financeiro e Cobrancas

E onde ficam as cobrancas e pagamentos.

O sistema permite criar cobranca manual, gerar PIX, copiar codigo PIX, visualizar QR Code, registrar pagamento e ver cobrancas abertas, pagas e parciais.

Exemplo: cliente atrasou a semanalidade. O usuario abre a cobranca, gera PIX e envia ao cliente.

### Inadimplencia

E a area para acompanhar clientes atrasados.

Ajuda a ver quem esta atrasado, ha quantos dias, valor em aberto, saldo atualizado e possibilidade de acao de cobranca.

Exemplo: o gerente filtra os clientes com atraso e decide quem precisa de contato urgente.

### CRM

CRM e a parte de relacionamento e acompanhamento do cliente.

Ele nao substitui o financeiro. Ele organiza o trabalho de atendimento.

No CRM voce ve:

- historico do cliente;
- notas internas;
- tarefas abertas;
- tarefas concluidas;
- etapa do cliente;
- cobrancas relacionadas;
- contatos feitos;
- eventos de pagamento.

Etapas possiveis:

- lead;
- contrato ativo;
- em cobranca;
- recuperacao;
- encerrado.

Exemplo: cliente Joao atrasou. O sistema cria tarefa `Cobrar cliente`. O funcionario liga para Joao e registra uma nota: `Cliente prometeu pagar na sexta-feira`.

### WhatsApp

O sistema tem area de configuracao para WhatsApp.

Ele pode trabalhar em modo demo, sem envio real, ou em modo oficial, usando API da Meta/WhatsApp.

Importante:

Para enviar WhatsApp real automaticamente, a empresa precisa ter uma integracao oficial de WhatsApp, como Meta WhatsApp Cloud API ou outro provedor autorizado.

Sem isso, o sistema pode gerar cobranca e tarefa, mas nao consegue mandar mensagem real sozinho.

### Asaas e PagBank

O sistema pode gerar PIX usando gateway configurado.

Hoje existem integracoes para Asaas e PagBank.

Para usar PIX real, e necessario:

- conta ativa no gateway;
- token/API Key de producao;
- chave PIX cadastrada;
- dados corretos do cliente;
- CPF/CNPJ valido;
- gateway escolhido no painel.

Erro comum no Asaas:

```text
Voce nao possui uma chave Pix cadastrada para recebimentos de cobrancas via Pix.
```

Solucao: cadastrar uma chave PIX dentro da conta Asaas.

## 3. O que ficou automatico

### Cobranca recorrente

O sistema pode gerar cobrancas automaticamente a partir dos contratos.

Exemplo:

Contrato semanal de R$ 250. Toda semana, o sistema pode criar a proxima cobranca sozinho.

Para funcionar:

- contrato ativo;
- cobranca automatica marcada;
- rotina agendada no servidor;
- variaveis de recorrencia ativas no `.env`.

### PIX automatico

Quando a recorrencia gerar uma cobranca, o sistema pode tambem gerar PIX automaticamente, se essa opcao estiver ativa.

Para funcionar:

- gateway configurado;
- Asaas ou PagBank ativo;
- chave PIX cadastrada;
- cliente com CPF/CNPJ valido.

### E-mail automatico

O sistema pode enviar e-mail quando uma cobranca recorrente for criada.

Para funcionar:

- SMTP configurado no `.env`;
- cliente com e-mail valido;
- opcao de envio de e-mail ativa.

### WhatsApp automatico

O sistema pode enviar WhatsApp de cobranca se a integracao oficial estiver configurada.

Para funcionar:

- API oficial de WhatsApp;
- token valido;
- numero oficial;
- template aprovado;
- opcao de envio ativa.

### Tarefas automaticas no CRM

O CRM cria tarefas sozinho quando uma cobranca atrasa.

Regras atuais:

- atraso de 1 dia: cria tarefa `Cobrar cliente`;
- atraso de 3 dias: cria tarefa `Avisar gerente sobre atraso`;
- pagamento confirmado: fecha automaticamente as tarefas daquela cobranca.

Exemplo:

Cliente Carlos venceu dia 10.

Dia 11:

- sistema cria `Cobrar cliente`.

Dia 13:

- sistema cria `Avisar gerente sobre atraso`.

Dia 14:

- Carlos paga via PIX;
- cobranca fica paga;
- tarefas do CRM sao fechadas automaticamente.

## 4. Como usar no dia a dia

### Rotina recomendada pela manha

1. Entrar no sistema.
2. Abrir `Dashboard`.
3. Ver inadimplencia e cobrancas abertas.
4. Abrir `CRM`.
5. Ver tarefas abertas.
6. Cobrar clientes com tarefa pendente.
7. Registrar notas importantes.
8. Acompanhar pagamentos no financeiro.

### Como cadastrar um cliente

1. Acesse `Clientes`.
2. Use o formulario de novo cliente.
3. Preencha nome, CPF/CNPJ, telefone, WhatsApp e e-mail.
4. Anexe documentos, se necessario.
5. Salve.

Boa pratica: preencha CPF/CNPJ corretamente. Isso e necessario para gerar PIX real no Asaas/PagBank.

### Como cadastrar uma moto

1. Acesse `Motos`.
2. Preencha modelo, marca, placa e dados principais.
3. Informe a loja.
4. Escolha o status operacional.
5. Salve.

### Como criar contrato

1. Acesse `Contratos`.
2. Escolha cliente.
3. Escolha moto.
4. Escolha loja.
5. Informe valor contratado.
6. Escolha forma de cobranca: semanal, quinzenal ou mensal.
7. Marque cobranca automatica se quiser que o sistema gere as proximas cobrancas sozinho.
8. Salve.

### Como gerar cobranca manual

1. Acesse `Financeiro` ou `Cobrancas`.
2. Escolha o contrato.
3. Informe vencimento.
4. Informe valor.
5. Salve.
6. Gere o PIX, se necessario.

### Como registrar pagamento manual

1. Acesse `Financeiro`.
2. Localize a cobranca.
3. Informe o valor pago.
4. Escolha forma de pagamento.
5. Salve.

Se o pagamento quitar a cobranca, o sistema marca como paga e fecha tarefas de cobranca no CRM.

### Como usar o CRM

1. Acesse `CRM`.
2. Veja tarefas abertas.
3. Clique no cliente.
4. Leia historico e cobrancas.
5. Registre nota do contato.
6. Crie tarefa manual, se precisar.
7. Conclua tarefas ja resolvidas.

Exemplo de nota:

```text
Cliente informou que pagara na sexta-feira ate 18h.
```

Exemplo de tarefa manual:

```text
Ligar para confirmar retirada da moto.
```

### Diferenca entre Clientes e CRM

Use `Clientes` para dados fixos:

- CPF;
- telefone;
- endereco;
- documentos;
- status cadastral.

Use `CRM` para acompanhamento:

- cobranca;
- contato;
- negociacao;
- historico;
- tarefas;
- observacoes.

## 5. Como testar sem gerar custo

Antes de ligar automacoes reais, teste com simulacao.

### Testar CRM sem gravar tarefas

```bash
php artisan locx:sincronizar-crm --dry-run
```

Esse comando apenas mostra o que seria criado. Nao cria tarefa, nao manda WhatsApp, nao gera PIX e nao cobra nada.

### Testar cobranca recorrente sem gravar

```bash
php artisan locx:gerar-cobrancas-recorrentes --dry-run
```

Esse comando apenas simula as cobrancas que seriam geradas.

### Testar PIX sem custo

Use gateway em modo demo/sandbox, quando disponivel.

Nunca use producao para teste sem entender o impacto.

## 6. Requisitos para instalacao

Servidor recomendado:

- PHP compativel com Laravel 12;
- Composer;
- MySQL ou MariaDB;
- servidor web Apache ou Nginx;
- extensoes PHP exigidas pelo Laravel;
- acesso SSH;
- cron ativo;
- HTTPS no dominio.

Pastas que precisam de escrita:

- `storage`;
- `bootstrap/cache`.

O dominio deve apontar para a pasta:

```text
public
```

Nunca aponte o dominio para a raiz do projeto.

## 7. Instalacao em servidor

### Passo 1: enviar codigo

No servidor, entre na pasta onde o projeto ficara instalado.

Exemplo:

```bash
cd /var/www
git clone https://github.com/LeonardoBalbi/look.git locx
cd locx
```

Se o projeto ja existe no servidor:

```bash
cd /caminho/do/projeto
git pull origin main
```

### Passo 2: instalar dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### Passo 3: criar arquivo `.env`

Se ainda nao existir:

```bash
cp .env.example .env
```

No Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### Passo 4: gerar chave da aplicacao

```bash
php artisan key:generate
```

### Passo 5: configurar banco no `.env`

Exemplo:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=locx
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco
```

### Passo 6: configurar ambiente de producao

No `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
```

Importante: `APP_DEBUG=false` evita mostrar erros tecnicos para o cliente.

### Passo 7: rodar migrations

```bash
php artisan migrate --force
```

Se for instalacao nova e quiser criar usuario inicial e dados padrao:

```bash
php artisan migrate --seed --force
```

Login inicial, quando o seed for usado:

```text
admin@locx.com.br
123456
```

Troque essa senha antes de usar em producao.

### Passo 8: criar link de storage

```bash
php artisan storage:link
```

### Passo 9: limpar e gerar caches

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Passo 10: configurar permissao de pastas

O usuario do servidor web precisa escrever em:

```text
storage
bootstrap/cache
```

Em hospedagens comuns, isso geralmente e feito pelo painel. Em VPS Linux, ajuste dono/permissao conforme o usuario do PHP/servidor web.

### Passo 11: configurar o dominio

No Apache ou Nginx, o dominio deve apontar para:

```text
/caminho/do/projeto/public
```

Exemplo:

```text
/var/www/locx/public
```

Se apontar para a raiz do projeto, pode causar erro e tambem expor arquivos sensiveis.

## 8. Configuracao do cron

O cron e obrigatorio para automacoes rodarem sozinhas.

Configure no servidor:

```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Exemplo:

```bash
* * * * * cd /var/www/locx && php artisan schedule:run >> /dev/null 2>&1
```

O cron chama o agendador do Laravel a cada minuto. O Laravel decide quais tarefas devem rodar naquele horario.

Sem cron:

- cobranca recorrente nao roda sozinha;
- tarefas automaticas do CRM nao rodam sozinhas;
- envio agendado nao acontece sozinho.

## 9. Variaveis importantes do `.env`

### CRM automatico

```env
LOCX_CRM_AUTOMACOES_ATIVAS=true
LOCX_CRM_AUTOMACOES_HORARIO=07:15
```

Essas variaveis fazem o sistema criar tarefas de cobranca atrasada no CRM.

### Recorrencia de cobrancas

```env
LOCX_RECORRENCIA_ATIVA=true
LOCX_RECORRENCIA_GERAR_PIX=true
LOCX_RECORRENCIA_ENVIAR_WHATSAPP=false
LOCX_RECORRENCIA_ENVIAR_EMAIL=false
LOCX_RECORRENCIA_DIAS_ANTECEDENCIA=0
LOCX_RECORRENCIA_MAX_POR_CONTRATO=12
LOCX_RECORRENCIA_HORARIO=07:00
```

Explicacao:

- `LOCX_RECORRENCIA_ATIVA=true`: liga geracao automatica de cobrancas;
- `LOCX_RECORRENCIA_GERAR_PIX=true`: gera PIX junto com a cobranca;
- `LOCX_RECORRENCIA_ENVIAR_WHATSAPP=false`: nao envia WhatsApp automaticamente;
- `LOCX_RECORRENCIA_ENVIAR_EMAIL=false`: nao envia e-mail automaticamente;
- `LOCX_RECORRENCIA_HORARIO=07:00`: horario diario da rotina.

### E-mail SMTP

Exemplo:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.seudominio.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=senha
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=financeiro@seudominio.com
MAIL_FROM_NAME="LocX"
```

Sem SMTP, o sistema nao envia e-mail real.

### WhatsApp

O WhatsApp oficial depende de configuracao no painel e credenciais da Meta ou provedor autorizado.

Itens necessarios:

- token de acesso;
- phone number id;
- WABA ID;
- template aprovado;
- idioma do template;
- webhook configurado.

### Asaas

Para producao:

- conta Asaas ativa;
- API Key de producao;
- chave PIX cadastrada;
- webhook configurado;
- clientes com CPF/CNPJ valido.

Erro comum:

```text
O CPF/CNPJ informado e invalido.
```

Solucao: corrigir o CPF/CNPJ no cadastro do cliente.

Erro comum:

```text
Voce nao possui uma chave Pix cadastrada para recebimentos de cobrancas via Pix.
```

Solucao: cadastrar chave PIX na conta Asaas.

### PagBank

Para producao:

- conta PagBank ativa;
- access token;
- webhook configurado;
- cliente com CPF/CNPJ valido;
- cliente com e-mail valido.

## 10. Comandos uteis

### Ver rotas

```bash
php artisan route:list
```

### Rodar testes

```bash
php artisan test
```

### Limpar cache

```bash
php artisan optimize:clear
```

### Gerar caches de producao

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Simular recorrencia

```bash
php artisan locx:gerar-cobrancas-recorrentes --dry-run
```

### Executar recorrencia real

```bash
php artisan locx:gerar-cobrancas-recorrentes
```

### Simular CRM automatico

```bash
php artisan locx:sincronizar-crm --dry-run
```

### Executar CRM automatico real

```bash
php artisan locx:sincronizar-crm
```

## 11. Webhooks

Webhooks servem para o gateway avisar o sistema quando algo acontece.

Exemplo:

Cliente paga um PIX no Asaas.

O Asaas chama o webhook do sistema.

O sistema:

- encontra a cobranca;
- registra pagamento;
- marca a cobranca como paga;
- fecha tarefas de cobranca no CRM.

URLs comuns:

```text
https://seu-dominio.com/webhooks/asaas
https://seu-dominio.com/webhooks/pagbank
https://seu-dominio.com/webhooks/whatsapp
```

Essas URLs precisam estar acessiveis publicamente com HTTPS.

## 12. Fluxos de exemplo

### Fluxo 1: locacao nova

1. Cadastrar cliente.
2. Cadastrar moto.
3. Criar contrato.
4. Marcar cobranca automatica, se desejar.
5. Gerar primeira cobranca.
6. Gerar PIX.
7. Enviar ao cliente.

### Fluxo 2: cobranca atrasada

1. Cobranca vence.
2. No dia seguinte, CRM cria tarefa `Cobrar cliente`.
3. Funcionario cobra o cliente.
4. Funcionario registra nota no CRM.
5. Se passar 3 dias, CRM cria tarefa `Avisar gerente sobre atraso`.
6. Gerente acompanha no CRM.

### Fluxo 3: pagamento confirmado

1. Cliente paga.
2. Gateway envia webhook ou usuario registra pagamento manual.
3. Sistema marca cobranca como paga.
4. Sistema fecha tarefa de cobranca no CRM.
5. Cliente volta para etapa normal, se nao tiver mais atraso.

### Fluxo 4: cobranca recorrente

1. Contrato esta ativo.
2. Cobranca automatica esta marcada.
3. Cron roda no servidor.
4. Sistema cria a proxima cobranca.
5. Se configurado, sistema gera PIX.
6. Se configurado, sistema envia e-mail ou WhatsApp.

## 13. Problemas comuns

### Erro 500 no servidor, mas local funciona

Possiveis causas:

- `.env` incorreto;
- banco nao configurado;
- migrations nao rodaram;
- cache antigo;
- permissao errada em `storage`;
- dominio apontando para pasta errada;
- versao de PHP incompativel;
- extensao PHP faltando.

Primeiros comandos para tentar:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Verifique tambem o log:

```text
storage/logs/laravel.log
```

### PIX nao gera

Verifique:

- gateway ativo;
- modo correto: sandbox ou producao;
- token correto;
- chave PIX cadastrada;
- CPF/CNPJ valido;
- valor maior que zero.

### WhatsApp nao envia

Verifique:

- esta em modo demo ou oficial;
- token valido;
- template aprovado;
- idioma correto;
- numero oficial configurado;
- webhook funcionando.

### Tarefas automaticas nao aparecem no CRM

Verifique:

- migration rodada;
- cron ativo;
- `LOCX_CRM_AUTOMACOES_ATIVAS=true`;
- existem cobrancas atrasadas;
- comando manual funciona:

```bash
php artisan locx:sincronizar-crm --dry-run
```

### Cobrancas recorrentes nao aparecem

Verifique:

- contrato ativo;
- cobranca automatica marcada;
- cron ativo;
- `LOCX_RECORRENCIA_ATIVA=true`;
- comando manual funciona:

```bash
php artisan locx:gerar-cobrancas-recorrentes --dry-run
```

## 14. Checklist para colocar em producao

Antes de liberar para uso real:

- dominio aponta para `public`;
- HTTPS ativo;
- `.env` revisado;
- `APP_DEBUG=false`;
- banco configurado;
- migrations rodadas;
- usuario admin com senha alterada;
- `storage` e `bootstrap/cache` com permissao;
- cron configurado;
- Asaas/PagBank testado;
- chave PIX cadastrada;
- webhook configurado;
- SMTP testado, se usar e-mail;
- WhatsApp oficial configurado, se usar envio real;
- teste `--dry-run` feito;
- backup do banco configurado.

## 15. Resumo simples para o cliente

O LocX controla a locacao da moto do inicio ao fim:

1. cadastra cliente;
2. cadastra moto;
3. cria contrato;
4. gera cobranca;
5. gera PIX;
6. acompanha atraso;
7. cria tarefas de cobranca no CRM;
8. registra pagamento;
9. fecha tarefas automaticamente;
10. guarda historico do relacionamento com o cliente.

O financeiro mostra o dinheiro.

O CRM mostra o que precisa ser feito.

Juntos, eles ajudam a empresa a cobrar melhor, esquecer menos coisas e atender o cliente com mais controle.
