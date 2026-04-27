<?php
/**
 * csrf_token.php — Gera e retorna um token CSRF de sessão
 * Chamado via fetch() pelo contato.html antes do envio do formulário.
 */

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Gera token caso ainda não exista na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['csrf_token']]);
