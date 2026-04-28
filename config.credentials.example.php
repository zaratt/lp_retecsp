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
];
