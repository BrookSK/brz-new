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

function _siteLockGetConfig(string $categoria, string $chave, string $default = ''): string {
    try {
        $pdo = \Config\Database::getConnection();
        $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        foreach ($tablesToTry as $t) {
            try {
                $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
                $stmtT->execute([$t]);
                if (!$stmtT->fetchColumn()) {
                    continue;
                }
                $stmtCols = $pdo->query('DESCRIBE ' . $t);
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (!is_array($cols)) {
                    $cols = [];
                }

                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                    if ($valCol !== '') {
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                        $stmt->execute([$categoria, $chave]);
                        $v = (string) ($stmt->fetchColumn() ?: '');
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }

                $keyCol = '';
                if (in_array('chave', $cols, true)) $keyCol = 'chave';
                elseif (in_array('key', $cols, true)) $keyCol = 'key';
                elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
                elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
                $valCol = '';
                if (in_array('valor', $cols, true)) $valCol = 'valor';
                elseif (in_array('value', $cols, true)) $valCol = 'value';
                elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
                if ($keyCol !== '' && $valCol !== '') {
                    $full = $categoria . '_' . $chave;
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$full]);
                    $v = (string) ($stmt->fetchColumn() ?: '');
                    if ($v !== '') {
                        return $v;
                    }
                }

                $colDirect = $categoria . '_' . $chave;
                if (in_array($colDirect, $cols, true)) {
                    $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                    $stmt2 = $pdo->query('SELECT ' . $colDirect . ' AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $v = (string) ($stmt2 ? ($stmt2->fetchColumn() ?: '') : '');
                    if ($v !== '') {
                        return $v;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
    } catch (\Throwable $e) {
    }
    return $default;
}

function _siteLockIsBypassPath(string $path): bool {
    $p = strtolower((string) $path);
    if ($p === '' || $p === '/') return false;
    if (strpos($p, '/admin') === 0) return true;
    if (strpos($p, '/loginadmin') === 0) return true;
    if (strpos($p, '/webhook/') === 0) return true;
    if (strpos($p, '/site-lock') === 0) return true;
    if (strpos($p, '/clube/recarga') === 0) return true;
    if (strpos($p, '/como-funciona-clube') === 0) return true;
    if (strpos($p, '/uploads/') === 0) return true;
    if (strpos($p, '/assets/') === 0) return true;
    if ($p === '/favicon.ico' || $p === '/robots.txt' || $p === '/sitemap.xml') return true;
    return false;
}

$request = new Request();
$router = new Router();

try {
    $path = $request->getPath();
    $enabled = trim((string) _siteLockGetConfig('sistema', 'site_lock_enabled', '0'));
    $pwd = (string) _siteLockGetConfig('sistema', 'site_lock_password', '');
    $enabledBool = ($enabled === '1' || strtolower($enabled) === 'true');

    if ($enabledBool && $pwd !== '') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ok = !empty($_SESSION['site_lock_ok']);
        if (!$ok && !_siteLockIsBypassPath($path)) {
            $next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /site-lock?next=' . urlencode($next));
            exit;
        }
    }
} catch (\Throwable $e) {
}

require_once __DIR__ . '/../app/routes.php';
require_once __DIR__ . '/../app/routes_admin.php';

$router->dispatch($request);
