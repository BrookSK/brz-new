<?php
// Iniciar sessão antes de qualquer output
$sessionLifetime = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', (string) $sessionLifetime);

$cookieParams = session_get_cookie_params();
$xfp = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$xfs = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $xfp === 'https'
    || $xfs === 'on';

// Evitar perda de sessão ao alternar http/https (cookie Secure não é enviado em http)
try {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $isLocal = stripos($host, 'localhost') !== false || preg_match('/^\d+\.\d+\.\d+\.\d+(?::\d+)?$/', $host);
    if (!$secure && !$isLocal && (stripos($uri, '/admin') === 0 || stripos($uri, '/loginadmin') === 0)) {
        header('Location: https://' . $host . $uri);
        exit;
    }
} catch (\Throwable $e) {
}

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(
        $sessionLifetime,
        ($cookieParams['path'] ?? '/') . '; samesite=Lax',
        $cookieParams['domain'] ?? '',
        $secure,
        true
    );
}

session_start();

// Login persistente: se a sessão foi perdida no servidor, restaurar a partir do remember_token
try {
    $isLogged = !empty($_SESSION['logado']);
    $remember = isset($_COOKIE['remember_token']) ? trim((string) $_COOKIE['remember_token']) : '';
    if (!$isLogged && $remember !== '') {
        $userModel = new \App\Models\Usuario();
        $u = $userModel->findByRememberToken($remember);
        if (is_array($u) && !empty($u['id'])) {
            $auth = new \App\Services\AuthService();
            $auth->criarSessao($u);
        } else {
            if (PHP_VERSION_ID >= 70300) {
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('remember_token', '', time() - 3600, '/; samesite=Lax', '', $secure, true);
            }
        }
    }
} catch (\Throwable $e) {
}

// Renovar o cookie da sessão (sliding expiration) para manter o login ativo por 7 dias após a última atividade
try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $name = session_name();
        $id = session_id();
        if ($name && $id) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie($name, $id, [
                    'expires' => time() + $sessionLifetime,
                    'path' => $cookieParams['path'] ?? '/',
                    'domain' => $cookieParams['domain'] ?? '',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie(
                    $name,
                    $id,
                    time() + $sessionLifetime,
                    ($cookieParams['path'] ?? '/') . '; samesite=Lax',
                    $cookieParams['domain'] ?? '',
                    $secure,
                    true
                );
            }
        }
    }
} catch (\Throwable $e) {
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Autoload manual temporário (até executar composer install)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

spl_autoload_register(function ($class) {
    $prefix = 'Config\\';
    $base_dir = __DIR__ . '/../config/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

try {
    \App\Core\I18n::boot();
} catch (\Throwable $e) {
}
if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null, array $params = []): string {
        try {
            return \App\Core\I18n::t($key, $fallback, $params);
        } catch (\Throwable $e) {
            return $fallback !== null ? $fallback : $key;
        }
    }
}

use App\Core\Router;
use App\Core\Request;

$request = new Request();
$router = new Router();

require_once __DIR__ . '/../app/routes.php';
require_once __DIR__ . '/../app/routes_admin.php';

$router->dispatch($request);
