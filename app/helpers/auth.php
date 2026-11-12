<?php
declare(strict_types=1);

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../config/database.php';

function startAppSession(): void
{
    loadProjectEnvironment();
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) (getenv('APP_ENV') ?: 'local')) === 'production';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function currentUser(): ?array
{
    startAppSession();
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function requireAdmin(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: /login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/'), true, 303);
        exit;
    }
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Administrator access is required.';
        exit;
    }
    return $user;
}

function loginUser(array $user): void
{
    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function csrfToken(): string
{
    startAppSession();
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function hasValidCsrfToken(?string $token): bool
{
    startAppSession();
    return is_string($token) && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

function endAppSession(): void
{
    startAppSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
