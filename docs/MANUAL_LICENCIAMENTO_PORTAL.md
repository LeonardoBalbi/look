# Manual passo a passo - Licenca plataforma comercial

Este manual explica como ficou a estrutura de licenca no Gestor de Locações e como evoluir para um portal online usando Vercel + Supabase.

## 1. Objetivo

A licenca transforma o Gestor de Locações em um sistema controlado por plano.

Com ela voce consegue:

- liberar ou bloquear o sistema por cliente;
- limitar quantidade de lojas;
- limitar quantidade de usuarios;
- liberar modulos por plano, como Pix, WhatsApp, CRM ou multi-loja;
- validar a licenca em um servidor online;
- no futuro, deixar o cliente pagar e renovar sozinho.

## 2. Como ficou dentro do Gestor de Locações

O Gestor de Locações agora tem uma area em `Configuracoes > Licenca plataforma comercial`.

Nessa tela o administrador informa:

- modo da licenca: local ou online;
- URL da API do portal;
- chave da licenca;
- nome e documento da empresa;
- tolerancia offline em dias.

O sistema tambem mostra:

- status atual da licenca;
- plano;
- data de vencimento;
- limites de lojas e usuarios;
- uso atual da instalacao.

## 3. Tabelas locais criadas

Foram criadas duas tabelas locais:

`licenca_config`

Guarda a configuracao principal da licenca instalada naquele Gestor de Locações.

Campos importantes:

- `modo`: local ou online;
- `status`: local, ativa, trial, teste, bloqueada, vencida;
- `plano`;
- `empresa_nome`;
- `empresa_documento`;
- `instancia_id`;
- `licenca_chave`, armazenada criptografada;
- `api_url`;
- `max_lojas`;
- `max_usuarios`;
- `modulos_json`;
- `vence_em`;
- `ultima_validacao_ok_em`;
- `tolerancia_offline_dias`;
- `mensagem`.

`licenca_validacao_logs`

Guarda o historico das consultas feitas ao portal online.

Isso ajuda a saber:

- quando o Gestor de Locações consultou o portal;
- qual resposta recebeu;
- se houve erro de internet;
- se a licenca foi recusada;
- se o servidor respondeu corretamente.

## 4. Regras implantadas

O Gestor de Locações ficou com estas regras:

- se a licenca estiver em modo local, o sistema continua funcionando como antes;
- se a licenca estiver em modo online, o sistema consulta o portal;
- `dashboard` e `configuracoes` continuam acessiveis mesmo com problema de licenca;
- telas de modulos fora do plano ficam bloqueadas;
- visualizar dados continua permitido quando o modulo esta liberado;
- criar, editar e excluir ficam bloqueados se a licenca estiver vencida, bloqueada ou fora da tolerancia;
- criar nova loja respeita `max_lojas`;
- criar novo usuario respeita `max_usuarios`.

Essa regra evita travar totalmente o cliente de forma brusca, mas impede novas operacoes quando a licenca nao esta regular.

## 5. Comando automatico

Foi criado o comando:

`php artisan rental:validar-licenca`

Ele consulta a API online configurada na tela de licenca.

Tambem existe a opcao:

`php artisan rental:validar-licenca --json`

Ela retorna o resultado em JSON, util para diagnostico ou automacao.

## 6. Agendamento automatico

O comando foi colocado no scheduler do Laravel para rodar de hora em hora quando a licenca online estiver ativa.

No servidor do cliente, o cron do Laravel precisa estar ativo:

`* * * * * php artisan schedule:run`

Sem esse cron, o botao manual da tela ainda funciona, mas a validacao automatica nao roda sozinha.

## 7. Portal online recomendado

Para o seu caso, Vercel + Supabase combina bem.

Use assim:

- Vercel hospeda o portal e a API;
- Supabase guarda clientes, planos, licencas e pagamentos;
- Gestor de Locações consulta a API da Vercel;
- a API da Vercel consulta o Supabase;
- o Supabase responde se a licenca esta ativa ou bloqueada.

Fluxo:

1. Cliente abre o Gestor de Locações.
2. Gestor de Locações consulta `https://seu-portal.vercel.app/api/validar-licenca`.
3. A API verifica a chave no Supabase.
4. A API retorna plano, status, limites e modulos.
5. Gestor de Locações salva a resposta localmente.
6. Gestor de Locações aplica as regras.

## 8. Estrutura sugerida no Supabase

No Supabase, crie estas tabelas quando for construir o portal:

`clientes`

- id;
- nome;
- documento;
- email;
- telefone;
- status.

`planos`

- id;
- nome;
- preco;
- max_lojas;
- max_usuarios;
- modulos;
- ativo.

`licencas`

- id;
- cliente_id;
- plano_id;
- chave;
- status;
- vence_em;
- instancia_id;
- ultimo_check_em;
- tolerancia_offline_dias.

`pagamentos`

- id;
- cliente_id;
- licenca_id;
- gateway;
- valor;
- status;
- vencimento;
- pago_em.

`validacao_logs`

- id;
- licenca_id;
- instancia_id;
- ip;
- payload;
- resposta;
- criado_em.

## 9. Resposta esperada da API

A API do portal deve retornar algo nesse formato:

```json
{
  "status": "ativa",
  "plano": "profissional",
  "empresa": "Moto Facil",
  "max_lojas": 5,
  "max_usuarios": 20,
  "modulos": ["pix", "whatsapp", "multi_loja", "crm"],
  "vence_em": "2026-12-31",
  "tolerancia_offline_dias": 7,
  "mensagem": "Licenca ativa."
}
```

Status recomendados:

- `ativa`: cliente em dia;
- `trial`: periodo de teste;
- `teste`: ambiente de demonstracao;
- `bloqueada`: cliente inadimplente ou cancelado;
- `vencida`: plano expirado;
- `pendente`: ainda nao ativada.

## 10. Passo a passo para usar no cliente

1. Instale ou atualize o Gestor de Locações.
2. Rode as migrations.
3. Entre como administrador.
4. Abra `Configuracoes`.
5. Va ate `Licenca plataforma comercial`.
6. Marque o modo `online`.
7. Informe a URL da API do portal.
8. Informe a chave da licenca.
9. Salve.
10. Clique em validar agora.
11. Confira se apareceu status `ativa`, `trial` ou `teste`.
12. Teste criar uma loja e um usuario.
13. Teste acessar um modulo fora do plano, se houver.
14. Ative o cron do Laravel no servidor.

## 11. Passo a passo para operar o portal

1. Cadastre o cliente no portal.
2. Crie ou escolha um plano.
3. Gere uma chave de licenca.
4. Vincule a chave ao cliente.
5. Configure limites e modulos do plano.
6. Copie a URL da API e a chave para o Gestor de Locações do cliente.
7. Valide pelo botao do Gestor de Locações.
8. Acompanhe os logs de validacao no portal e no Gestor de Locações.

## 12. Renovacao e pagamento

Nesta implementacao inicial, o portal administrativo ja pode ser acessado em:

`/licencas-portal`

Use o login de administrador do Gestor de Locações.

O endpoint para configurar na tela `Licenca plataforma comercial` e:

`/api/licencas-portal`

Em producao, use a URL completa do seu dominio, por exemplo:

`https://seu-dominio.com/api/licencas-portal`

Na fase 3 completa, o portal deve permitir:

- cliente entrar com email e senha;
- ver plano atual;
- ver vencimento;
- pagar renovacao;
- mudar plano;
- atualizar dados;
- emitir segunda via;
- liberar automaticamente a licenca apos pagamento confirmado.

O gateway de pagamento pode ser Asaas, Mercado Pago, Stripe, PagBank ou outro.

Quando o pagamento for confirmado:

1. gateway chama o webhook do portal;
2. portal marca pagamento como pago;
3. portal atualiza `licencas.status` para `ativa`;
4. portal atualiza `vence_em`;
5. Gestor de Locações valida novamente e recebe a licenca liberada.

## 13. Cuidados importantes

- Nunca coloque a chave secreta do Supabase dentro do Gestor de Locações do cliente.
- O Gestor de Locações deve conhecer apenas a URL publica da API e a chave da licenca.
- A API da Vercel deve usar variaveis de ambiente para acessar o Supabase.
- Guarde logs de validacao para suporte.
- Mantenha tolerancia offline para evitar bloquear o cliente por falha temporaria de internet.
- Comece com poucos planos e poucos modulos para reduzir complexidade.

## 14. Roadmap recomendado

Fase 1: licenca local e tela no Gestor de Locações.

Fase 2: validacao online com Vercel + Supabase.

Fase 3: portal do cliente com pagamento e renovacao.

Fase 4: painel administrativo para suporte, bloqueio, troca de plano e historico.

Fase 5: atualizacao automatica, avisos dentro do Gestor de Locações e relatorios de uso.

## 15. Checklist final

- migrations executadas;
- tela de licenca aparecendo em configuracoes;
- chave salva;
- validacao manual funcionando;
- comando `rental:validar-licenca` funcionando;
- scheduler configurado;
- limites de lojas e usuarios testados;
- modulos por plano testados;
- logs sendo gravados;
- plano de contingencia definido para falha de internet.
