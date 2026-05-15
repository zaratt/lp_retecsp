<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$user = admin_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nao autenticado.']);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
if (strlen($query) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $stmt = admin_db()->prepare(
        'SELECT id, nome, bairro, cep
         FROM clientes
         WHERE nome LIKE :nome
         ORDER BY nome ASC
         LIMIT 20'
    );
    $stmt->execute(['nome' => '%' . $query . '%']);
    $items = $stmt->fetchAll();

    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    admin_log_event('Erro em clientes_search.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar clientes.']);
}
