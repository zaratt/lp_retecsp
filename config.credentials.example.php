<?php
/**
 * Exemplo de credenciais SMTP locais.
 *
 * 1) Copie este arquivo para config.credentials.php
 * 2) Preencha com as credenciais reais
 * 3) Nao versionar config.credentials.php
 */

return [
    'SMTP_HOST' => 'email-ssl.com.br',
    'SMTP_USER' => 'noreply@retecsp.com.br',
    'SMTP_PASS' => 'SENHA_AQUI',
    'SMTP_PORT' => 465,
    // Opcoes: ssl (porta 465), tls (porta 587), none
    'SMTP_SECURE' => 'ssl',

    // Banco MySQL (painel admin)
    'DB_HOST' => 'bd_retecsp.mysql.dbaas.com.br',
    'DB_NAME' => 'bd_retecsp',
    'DB_USER' => 'bd_retecsp',
    'DB_PASS' => 'SENHA_BANCO_AQUI',
    'DB_PORT' => 3306,
];
