# Instalação simples: uma aplicação por loja

## Como funciona

Cada loja recebe uma instalação independente:

- uma cópia da aplicação;
- um endereço próprio, por exemplo `loja-a.seusistema.com.br`;
- um banco de dados próprio;
- uma licença própria;
- sua própria logo, nome e configurações;
- seus próprios usuários, clientes, motos, contratos e cobranças.

Não existe troca dinâmica de banco nem mistura de dados entre lojas. Se o cliente comprar uma segunda loja, é criada outra instalação.

## Exemplo com três clientes

| Loja | Endereço | Banco |
|---|---|---|
| Locadora Silva | `silva.seusistema.com.br` | `rental_silva` |
| Moto Express | `express.seusistema.com.br` | `rental_express` |
| Top Motos | `topmotos.seusistema.com.br` | `rental_topmotos` |

Todas usam o mesmo código, mas cada instalação possui seu próprio arquivo `.env` e banco de dados.

## Configuração de uma nova loja

1. Copie a aplicação para a hospedagem da nova loja.
2. Crie um banco MySQL vazio exclusivo para ela.
3. Copie `.env.example` para `.env`.
4. Configure `APP_URL`, `STORE_NAME`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`.
5. Mantenha `SINGLE_STORE_MODE=true`.
6. Execute `composer install --no-dev --optimize-autoloader`.
7. Execute `php artisan key:generate`.
8. Execute `php artisan migrate --force`.
9. Cadastre ou valide a licença dessa loja.
10. Faça o primeiro acesso e altere a senha inicial.

## Atualizações

O código pode ser atualizado igualmente em todas as instalações. Antes de atualizar, faça backup do banco e dos arquivos enviados por cada loja.

## Vantagens deste modelo

- mais simples de entender e manter no início;
- isolamento completo dos dados;
- backup e restauração por loja;
- uma falha em um banco não mistura dados de outros clientes;
- permite mover uma loja para outro servidor com facilidade.

## Limitações

- cada nova loja precisa de uma instalação e banco;
- atualizações precisam ser aplicadas em todas as instalações;
- consome mais espaço e recursos que um SaaS com banco compartilhado;
- uma empresa com várias filiais terá uma instalação para cada filial.
