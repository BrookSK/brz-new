<?php
// Iniciar sessão antes de qualquer output
$sessionLifetime = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', (string) $sessionLifetime);

$cookieParams = session_get_cookie_params();
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

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
