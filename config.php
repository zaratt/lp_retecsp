<?php
/**
 * Configurações SMTP — RETEC SP
 *
 * ⚠️  NÃO versionar este arquivo no Git.
 *     Adicione "config.php" ao seu .gitignore.
 *     O acesso direto via HTTP é bloqueado pelo .htaccess.
 */

// Credenciais SMTP
define('SMTP_HOST', 'mail.retecsp.com.br');
define('SMTP_USER', 'noreply@retecsp.com.br');
define('SMTP_PASS', 'SENHA_AQUI');   // ← substitua antes de publicar
define('SMTP_PORT', 587);

// Nome exibido como remetente
define('SMTP_FROM_NAME', 'RETEC SP — Site');
