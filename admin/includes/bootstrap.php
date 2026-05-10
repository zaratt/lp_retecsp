<?php
declare(strict_types=1);

if (!function_exists('admin_is_https')) {
    function admin_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }
}

if (!function_exists('admin_is_local')) {
    function admin_is_local(): bool
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}

if (!admin_is_https() && !admin_is_local()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Conexao segura obrigatoria.');
}

ini_set('session.cookie_secure', admin_is_https() ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '1800');
session_name('retec_admin_sid');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

if ((time() - (int)$_SESSION['last_activity']) > 1800) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

date_default_timezone_set('America/Sao_Paulo');

define('ADMIN_ROOT', dirname(__DIR__));
define('PROJECT_ROOT', dirname(ADMIN_ROOT));

$credentialsFile = PROJECT_ROOT . '/config.credentials.php';
$credentials = [];
if (is_readable($credentialsFile)) {
    $loaded = require $credentialsFile;
    if (is_array($loaded)) {
        $credentials = $loaded;
    }
}

define('DB_HOST', (string)($credentials['DB_HOST'] ?? 'bd_retecsp.mysql.dbaas.com.br'));
define('DB_NAME', (string)($credentials['DB_NAME'] ?? 'bd_retecsp'));
define('DB_USER', (string)($credentials['DB_USER'] ?? 'bd_retecsp'));
define('DB_PASS', (string)($credentials['DB_PASS'] ?? ''));
define('DB_PORT', (int)($credentials['DB_PORT'] ?? 3306));

if (!function_exists('admin_db')) {
    function admin_db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}

if (!function_exists('admin_h')) {
    function admin_h(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_redirect')) {
    function admin_redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('admin_set_flash')) {
    function admin_set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('admin_get_flash')) {
    function admin_get_flash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }
}

if (!function_exists('admin_log_event')) {
    function admin_log_event(string $message): void
    {
        $logDir = PROJECT_ROOT . '/logs';
        if (!is_dir($logDir)) {
            return;
        }
        $line = sprintf("[%s] admin: %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents($logDir . '/admin.log', $line, FILE_APPEND | LOCK_EX);
    }
}
