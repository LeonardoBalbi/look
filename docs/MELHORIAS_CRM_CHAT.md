# Central de Atendimento do CRM

## O que foi implementado

O módulo de CRM passou a ter uma central de atendimento em três áreas:

1. **Caixa de entrada:** lista as conversas, mensagens não lidas, assunto, prioridade, horário e situação.
2. **Conversa selecionada:** mostra apenas um atendimento por vez, com histórico completo e campo de resposta.
3. **Contexto do cliente:** reúne etapa comercial, saldo em aberto, cobranças atrasadas, próxima tarefa, notas e dados de contato.

## Recursos da central

- Busca de conversas por cliente, assunto ou mensagem.
- Filtros para pendentes, em atendimento, aguardando cliente e resolvidas.
- Atualização automática da fila sem recarregar a página inteira.
- Indicadores de conversas abertas, atendimentos pendentes e tarefas vencidas.
- Identificação de mensagens não lidas.
- Responsável pelo atendimento.
- Ações para assumir, aguardar o cliente, concluir e reabrir.
- Respostas rápidas editáveis antes do envio.
- Envio com `Enter` e quebra de linha com `Shift + Enter`.
- Contador de caracteres.
- Nome do atendente gravado na mensagem.
- Histórico comercial e financeiro acessível durante a conversa.
- Interface responsiva para computador, tablet e celular.

## Estados do atendimento

- `novo`: conversa criada e ainda não tratada.
- `aguardando_humano`: o assistente solicitou participação da equipe.
- `em_atendimento`: um atendente assumiu a conversa.
- `respondido`: a equipe respondeu e aguarda o cliente.
- `fechado`: atendimento concluído.

Quando um atendente assume uma conversa, o bot deixa de responder automaticamente naquele atendimento. As mensagens seguintes permanecem com a equipe humana.

## Atualização do banco de dados

A migration adiciona ao atendimento:

- atendente responsável;
- data em que foi assumido;
- data de encerramento;
- data da última mensagem;
- data da última atualização.

Nas mensagens são gravados o usuário e o nome do atendente que respondeu.

## Publicação

Depois de substituir o projeto no servidor, execute:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Depois, atualize o navegador com `Ctrl + F5`.

## Arquivos principais

- `resources/views/locx/partials/crm.blade.php`
- `public/locx/assets/css/style.css`
- `public/locx/assets/js/app.js`
- `app/Http/Controllers/LocxController.php`
- `app/Http/Controllers/ClientePortalController.php`
- `app/Models/PortalAtendimento.php`
- `app/Models/PortalAtendimentoMensagem.php`
- `database/migrations/2026_07_20_000002_add_management_fields_to_portal_atendimentos_table.php`

## Organização por cliente

A caixa de entrada agora trabalha com **uma única linha por cliente**. Isso evita que o mesmo cliente apareça várias vezes quando possui atendimentos antigos ou assuntos diferentes.

Na linha do cliente aparecem:

- atendimento atual em destaque;
- quantidade total de atendimentos no histórico;
- quantidade de assuntos ainda abertos, caso existam dados antigos duplicados;
- total de mensagens não lidas daquele cliente;
- assunto, prioridade e última mensagem do atendimento principal.

Ao abrir o cliente, a coluna lateral mostra o **Histórico de atendimentos**, permitindo alternar entre protocolos antigos sem misturar as mensagens.

## Regra de atendimento ativo

O portal mantém somente **um atendimento ativo por cliente**:

- **Continuar atendimento:** reabre o último protocolo e mantém o histórico e o assunto.
- **Novo assunto:** encerra o atendimento ativo anterior e cria um novo protocolo.
- Os atendimentos encerrados nunca são apagados; ficam disponíveis no histórico do CRM e no resumo do portal.

Essa regra reduz duplicidades, impede dois atendentes de trabalharem no mesmo cliente sem perceber e deixa a caixa de entrada mais limpa.
