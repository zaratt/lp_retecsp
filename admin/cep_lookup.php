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

$cepRaw = trim((string)($_GET['cep'] ?? ''));
$cepDigits = preg_replace('/\D+/', '', $cepRaw);
if (!is_string($cepDigits) || strlen($cepDigits) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'CEP invalido.']);
    exit;
}

$cepFormatted = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5, 3);

try {
    $stmtCache = admin_db()->prepare(
        'SELECT cep, bairro, municipio
         FROM localidades_cache
         WHERE REPLACE(cep, "-", "") = :cep
         LIMIT 1'
    );
    $stmtCache->execute(['cep' => $cepDigits]);
    $cached = $stmtCache->fetch();
    if (is_array($cached)) {
        echo json_encode([
            'item' => [
                'cep' => (string)$cached['cep'],
                'bairro' => (string)$cached['bairro'],
                'municipio' => (string)$cached['municipio'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $url = 'https://brasilapi.com.br/api/cep/v2/' . $cepDigits;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao consultar BrasilAPI.']);
        exit;
    }

    $statusCode = 200;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $matches)) {
        $statusCode = (int)$matches[1];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded) || $statusCode >= 400) {
        http_response_code($statusCode >= 400 ? $statusCode : 502);
        echo json_encode(['error' => 'CEP nao encontrado na BrasilAPI.']);
        exit;
    }

    $apiCep = trim((string)($decoded['cep'] ?? $cepFormatted));
    $apiBairro = trim((string)($decoded['neighborhood'] ?? ''));
    $apiMunicipio = trim((string)($decoded['city'] ?? ''));
    $apiUf = trim((string)($decoded['state'] ?? ''));

    if ($apiBairro === '' || $apiMunicipio === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Resposta da BrasilAPI sem bairro/municipio.']);
        exit;
    }

    $stmtUpsert = admin_db()->prepare(
        'INSERT INTO localidades_cache (cep, bairro, municipio, uf, source)
         VALUES (:cep, :bairro, :municipio, :uf, :source)
         ON DUPLICATE KEY UPDATE
             bairro = VALUES(bairro),
             municipio = VALUES(municipio),
             uf = VALUES(uf),
             source = VALUES(source),
             updated_at = NOW()'
    );
    $stmtUpsert->execute([
        'cep' => $apiCep !== '' ? $apiCep : $cepFormatted,
        'bairro' => $apiBairro,
        'municipio' => $apiMunicipio,
        'uf' => $apiUf !== '' ? $apiUf : null,
        'source' => 'brasilapi_cep_v2',
    ]);

    echo json_encode([
        'item' => [
            'cep' => $apiCep !== '' ? $apiCep : $cepFormatted,
            'bairro' => $apiBairro,
            'municipio' => $apiMunicipio,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    admin_log_event('Erro em cep_lookup.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar CEP.']);
}
