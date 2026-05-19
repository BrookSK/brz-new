<?php
// Timezone padrão: São Paulo
date_default_timezone_set('America/Sao_Paulo');

// DEBUG: verificar se o deploy está atualizado (remover após confirmar)
if (isset($_GET['_deploy_check'])) {
    header('Content-Type: application/json');
    echo json_encode(['deploy_ts' => '2026-05-19T14:30:00', 'GET' => $_GET, 'query' => $_SERVER['QUERY_STRING'] ?? '']);
    exit;
}

// Forçar invalidação do OPcache para o controller de pacotes WordPress
if (function_exists('opcache_invalidate')) {
    $pacotesCtrl = __DIR__ . '/../app/Controllers/AdminPacotesWordpressController.php';
    if (is_file($pacotesCtrl)) {
        opcache_invalidate($pacotesCtrl, true);
    }
}

// Iniciar sessão antes de qualquer output
$sessionLifetime = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', (string) $sessionLifetime);

try {
    if (!headers_sent()) {
        header('X-App-Frontcontroller: 1');
    }
} catch (\Throwable $e) {
}

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

function _clubeQuickCheckoutCapReached(): bool {
    try {
        $pdo = \Config\Database::getConnection();
        $st = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(valor_brl,0)),0) AS total
            FROM carteira_recargas
            WHERE origem = 'clube_quick_checkout'
              AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')");
        $st->execute();
        $total = (float) ($st->fetchColumn() ?: 0);
        return ($total + 0.00001 >= 150000.00);
    } catch (\Throwable $e) {
        return false;
    }
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
    if (strpos($p, '/pagar/') === 0) return true;
    if (strpos($p, '/clube/recarga/status') === 0) return true;
    if (strpos($p, '/clube/recarga/comprovante') === 0) return true;
    if (strpos($p, '/clube/recarga') === 0) {
        return !_clubeQuickCheckoutCapReached();
    }
    if (strpos($p, '/como-funciona-clube') === 0) return true;
    if (strpos($p, '/uploads/') === 0) return true;
    if (strpos($p, '/assets/') === 0) return true;
    if ($p === '/favicon.ico' || $p === '/robots.txt' || $p === '/sitemap.xml') return true;
    return false;
}

function _siteLockIsBlockedPartial(string $path, string $blockedPathsRaw = ''): bool {
    $p = strtolower(trim((string) $path));
    if ($p === '') return false;
    $raw = $blockedPathsRaw !== '' ? $blockedPathsRaw : trim((string) _siteLockGetConfig('sistema', 'site_lock_blocked_paths', ''));
    if ($raw === '') return false;
    $paths = array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== '');
    foreach ($paths as $blocked) {
        $blocked = strtolower($blocked);
        if ($blocked !== '' && strpos($p, $blocked) === 0) {
            return true;
        }
    }
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
        $mode = trim((string) _siteLockGetConfig('sistema', 'site_lock_mode', ''));
        $blockedPaths = trim((string) _siteLockGetConfig('sistema', 'site_lock_blocked_paths', ''));

        // Se mode não foi encontrado mas blocked_paths tem valor, assumir parcial
        if ($mode === '' && $blockedPaths !== '') {
            $mode = 'parcial';
        }
        if ($mode === '' || !in_array($mode, ['total', 'parcial'], true)) {
            $mode = 'total';
        }

        if ($mode === 'total') {
            // Modo total: bloqueia tudo exceto bypass paths (comportamento original)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $ok = !empty($_SESSION['site_lock_ok']);
            if (!$ok && !_siteLockIsBypassPath($path)) {
                $next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                header('Location: /site-lock?next=' . urlencode($next));
                exit;
            }
        } else {
            // Modo parcial: bloqueia somente as rotas configuradas em site_lock_blocked_paths
            if (_siteLockIsBlockedPartial($path, $blockedPaths)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $ok = !empty($_SESSION['site_lock_ok']);
                if (!$ok) {
                    $next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                    header('Location: /site-lock?next=' . urlencode($next));
                    exit;
                }
            }
        }
    }
} catch (\Throwable $e) {
}

require_once __DIR__ . '/../app/routes.php';
require_once __DIR__ . '/../app/routes_admin.php';

$router->dispatch($request);
