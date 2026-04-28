<?php
/**
 * Configurações SMTP — RETEC SP
 *
 * Segredos SMTP devem ficar no arquivo config.credentials.php,
 * que não deve ser versionado e deve estar bloqueado via .htaccess.
 */

$smtpCredentials = [];
$credentialsFile = __DIR__ . '/config.credentials.php';

if (is_readable($credentialsFile)) {
    $loaded = require_once $credentialsFile;
    if (is_array($loaded)) {
        $smtpCredentials = $loaded;
    }
}

// Credenciais SMTP
define('SMTP_HOST', $smtpCredentials['SMTP_HOST'] ?? 'mail.retecsp.com.br');
define('SMTP_USER', $smtpCredentials['SMTP_USER'] ?? 'noreply@retecsp.com.br');
define('SMTP_PASS', $smtpCredentials['SMTP_PASS'] ?? '');
define('SMTP_PORT', (int)($smtpCredentials['SMTP_PORT'] ?? 587));
define('SMTP_SECURE', strtolower((string)($smtpCredentials['SMTP_SECURE'] ?? 'tls')));

// Nome exibido como remetente
define('SMTP_FROM_NAME', 'RETEC SP — Site');

if (SMTP_PASS === '') {
    error_log('RETEC config.php — SMTP_PASS não configurada em config.credentials.php');
}
