<?php
/**
 * csrf_token.php — Gera e retorna um token CSRF de sessão
 * Chamado via fetch() pelo contato.html antes do envio do formulário.
 */

// Endurece cookies de sessão para reduzir risco de sequestro/fixação de sessão.
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (!$isHttps) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    exit(json_encode(['error' => 'Conexão segura obrigatória.']));
}

ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Gera token caso ainda não exista na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['csrf_token']]);
