<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('admin_find_user_by_username')) {
    function admin_find_user_by_username(string $username): ?array
    {
        $stmt = admin_db()->prepare('SELECT * FROM usuarios WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('admin_authenticate')) {
    function admin_authenticate(string $username, string $password): ?array
    {
        $user = admin_find_user_by_username($username);
        if (!$user || (int)$user['is_active'] !== 1) {
            return null;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = admin_db()->prepare('UPDATE usuarios SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'hash' => $newHash,
                'id' => (int)$user['id'],
            ]);
        }

        $updateLastLogin = admin_db()->prepare('UPDATE usuarios SET last_login_at = NOW() WHERE id = :id');
        $updateLastLogin->execute(['id' => (int)$user['id']]);

        return $user;
    }
}

if (!function_exists('admin_login_user')) {
    function admin_login_user(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'display_name' => (string)$user['display_name'],
            'role' => (string)$user['role'],
        ];
    }
}

if (!function_exists('admin_current_user')) {
    function admin_current_user(): ?array
    {
        $user = $_SESSION['admin_user'] ?? null;
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('admin_logout_user')) {
    function admin_logout_user(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}

if (!function_exists('admin_can_access')) {
    function admin_can_access(array $user, string $section): bool
    {
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return in_array($section, ['dashboard', 'comercial'], true);
        }
        if ($role === 'comercial') {
            return $section === 'comercial';
        }
        return false;
    }
}
