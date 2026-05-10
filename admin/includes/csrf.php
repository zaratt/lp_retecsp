<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token(string $formName): string
    {
        if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        if (empty($_SESSION['csrf_tokens'][$formName])) {
            $_SESSION['csrf_tokens'][$formName] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_tokens'][$formName];
    }
}

if (!function_exists('admin_csrf_validate')) {
    function admin_csrf_validate(string $formName, string $token): bool
    {
        $sessionToken = $_SESSION['csrf_tokens'][$formName] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
            return false;
        }

        $valid = hash_equals($sessionToken, $token);
        unset($_SESSION['csrf_tokens'][$formName]);

        return $valid;
    }
}
