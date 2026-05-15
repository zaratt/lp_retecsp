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

$type = trim((string)($_GET['type'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

if (!in_array($type, ['cep', 'bairro', 'municipio'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de busca invalido.']);
    exit;
}

$minLength = $type === 'cep' ? 3 : 2;
if (strlen($query) < $minLength) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    if ($type === 'cep') {
        $digits = preg_replace('/\D+/', '', $query);
        if (!is_string($digits) || strlen($digits) < 3) {
            echo json_encode(['items' => []]);
            exit;
        }

        $stmt = admin_db()->prepare(
            'SELECT cep, bairro, municipio
             FROM localidades_cache
             WHERE REPLACE(cep, "-", "") LIKE :cep
             ORDER BY updated_at DESC
             LIMIT 30'
        );
        $stmt->execute(['cep' => $digits . '%']);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $cep = trim((string)($row['cep'] ?? ''));
            $bairro = trim((string)($row['bairro'] ?? ''));
            $municipio = trim((string)($row['municipio'] ?? ''));
            if ($cep === '') {
                continue;
            }
            $items[] = [
                'cep' => $cep,
                'bairro' => $bairro,
                'municipio' => $municipio,
            ];
        }

        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'bairro') {
        $stmt = admin_db()->prepare(
            'SELECT bairro, municipio, MAX(updated_at) AS updated_at
             FROM localidades_cache
             WHERE TRIM(COALESCE(bairro, "")) <> ""
               AND bairro LIKE :query
             GROUP BY bairro, municipio
             ORDER BY updated_at DESC, bairro ASC
             LIMIT 30'
        );
        $stmt->execute(['query' => '%' . $query . '%']);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $bairro = trim((string)($row['bairro'] ?? ''));
            $municipio = trim((string)($row['municipio'] ?? ''));
            if ($bairro === '') {
                continue;
            }
            $items[] = [
                'bairro' => $bairro,
                'municipio' => $municipio,
            ];
        }

        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = admin_db()->prepare(
                'SELECT municipio, MAX(updated_at) AS updated_at
                 FROM localidades_cache
         WHERE TRIM(COALESCE(municipio, "")) <> ""
           AND municipio LIKE :query
         GROUP BY municipio
                 ORDER BY updated_at DESC, municipio ASC
         LIMIT 30'
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $municipio = trim((string)($row['municipio'] ?? ''));
        if ($municipio === '') {
            continue;
        }
        $items[] = [
            'municipio' => $municipio,
        ];
    }

    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    admin_log_event('Erro em localidades_search.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar localidades.']);
}
