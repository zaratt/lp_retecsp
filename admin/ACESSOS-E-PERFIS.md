# Controle de Acesso - Painel Admin RETEC

## Objetivo
Este documento descreve as regras de acesso e bloqueio do painel interno em `/admin/painel.php`.

## Perfis existentes
- `admin`
- `comercial`

## Secoes do painel
- `dashboard`
- `comercial`

## Matriz de permissao
| Perfil      | Dashboard | Comercial |
|-------------|-----------|-----------|
| admin       | Permitido | Permitido |
| comercial   | Bloqueado | Permitido |

## Regras implementadas
1. Apenas perfil `admin` pode acessar a secao `dashboard`.
2. Perfil `comercial` pode acessar somente a secao `comercial`.
3. Se um usuario `comercial` tentar abrir `?sec=dashboard` manualmente, ele e redirecionado para `?sec=comercial` com mensagem de acesso restrito.
4. No login, se o usuario nao for `admin`, o redirecionamento de entrada vai para `comercial`.
5. O botao de navegacao `Dashboard` e exibido somente para perfil `admin`.

## Regra de visualizacao de registros
### Perfil admin
- Visualiza todos os registros de todos os vendedores.
- Dashboard consolida dados gerais de toda a operacao.

### Perfil comercial
- Visualiza apenas os proprios registros (filtro por `vendedor_id` do usuario logado).
- Nao enxerga dados de outros vendedores.
- Nao acessa Dashboard.

## Pontos tecnicos (arquivos)
- Validacao de perfil e acesso por secao:
  - `/admin/includes/auth.php`
- Regras de redirecionamento, filtro e exibicao de botoes:
  - `/admin/painel.php`

## Cenarios de teste recomendados
1. Login com `admin`:
   - Deve ver botoes `Dashboard` e `Comercial`.
   - Deve acessar `?sec=dashboard` normalmente.
   - Deve ver registros de todos os vendedores na listagem.

2. Login com `comercial`:
   - Deve ver apenas botao `Comercial`.
   - Se abrir `?sec=dashboard`, deve ser redirecionado para `?sec=comercial`.
   - Deve ver apenas registros do proprio usuario.

3. Tentativa de acesso direto sem sessao:
   - Deve cair na tela de login.

## Observacoes
- As regras de permissao estao no backend (nao apenas na interface), evitando bypass por URL.
- Mudancas futuras de perfil devem manter esta matriz como referencia de seguranca.
