# Painel Admin RETEC

## O que foi criado
- Login por perfil (`admin` e `comercial`)
- Pagina unica `painel.php` com abas Dashboard e Comercial
- Formulario Comercial com validacao server-side e gravacao no MySQL
- Seed com usuario inicial `admretec`

## Configuracao
1. Copie `config.credentials.example.php` para `config.credentials.php` na raiz do projeto.
2. Preencha `DB_PASS` com a senha real do banco.
3. Execute o script `admin/install.sql` no banco `bd_retecsp` (via phpMyAdmin/Locaweb).

## Primeiro acesso
- URL: `/admin/painel.php`
- Usuario inicial: `admretec`
- Senha seed prevista no SQL: `Trocar123!`

## Regras importantes do formulario
- Campo vendedor sempre usa o usuario logado
- Servico aceita apenas `4m` ou `26m`
- Datas usam somente formato de data (`DATE`), sem horario
