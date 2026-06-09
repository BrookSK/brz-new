<?php
// Timezone padrão: São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Endpoint direto para etiqueta PDF (contorna OPcache)
$_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($_uri === '/admin/wp-etiqueta') {
    // Parse query string direto do REQUEST_URI (nginx pode não passar $_GET)
    $_qs = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';
    parse_str($_qs, $_qsParams);
    
    // Incluir apenas o config de DB e executar inline
    $sessionLifetime = 60 * 60 * 24 * 7;
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    ini_set('session.cookie_lifetime', (string) $sessionLifetime);
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($usuarioId <= 0) { http_response_code(401); echo 'Login necessário.'; exit; }
    
    $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['perfil'] ?? ''))));
    if (!in_array($perfil, ['admin', 'vendedor', 'suporte'], true)) { http_response_code(403); echo 'Acesso negado.'; exit; }
    
    $id = (int) ($_qsParams['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) { http_response_code(400); echo 'id obrigatório. URI=' . ($_SERVER['REQUEST_URI'] ?? '') . ' QS=' . $_qs; exit; }
    
    require_once __DIR__ . '/../config/Database.php';
    $pdo = \Config\Database::getConnection();
    
    $st = $pdo->prepare("SELECT * FROM wp_packet_etiquetas WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $etiqueta = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$etiqueta) { http_response_code(404); echo 'Etiqueta #'.$id.' não encontrada.'; exit; }
    
    $origem = strtolower(trim((string)($etiqueta['origem'] ?? 'br')));
    $wpPostId = (int)($etiqueta['wp_post_id'] ?? 0);
    $tracking = (string)($etiqueta['tracking_number'] ?? '');
    if ($tracking === '') { http_response_code(404); echo 'Sem tracking.'; exit; }
    
    // Conectar WP
    if (!in_array($origem, ['br','red','us'], true)) $origem = 'br';
    $cat = 'wordpress_' . $origem;
    $wpCfg = ['table_prefix'=>'wp_'];
    $cols = []; $stC = $pdo->query('DESCRIBE configuracoes_sistema'); $cols = $stC ? ($stC->fetchAll(\PDO::FETCH_COLUMN)?:[]) : [];
    $hasCategoria = in_array('categoria',$cols,true) && in_array('chave',$cols,true) && in_array('valor',$cols,true);
    if ($hasCategoria) {
        $stCfg = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria=? AND chave=? LIMIT 1');
        foreach (['db_host','db_name','db_user','db_pass','table_prefix'] as $k) {
            $stCfg->execute([$cat,$k]); $v=$stCfg->fetchColumn();
            if ($v!==false&&$v!==null) $wpCfg[$k]=(string)$v;
            elseif ($origem==='br') { $stCfg->execute(['wordpress',$k]); $v2=$stCfg->fetchColumn(); if($v2!==false&&$v2!==null) $wpCfg[$k]=(string)$v2; }
        }
    } else {
        $stCfg = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave=? LIMIT 1');
        foreach (['db_host','db_name','db_user','db_pass','table_prefix'] as $k) {
            $stCfg->execute([$cat.'_'.$k]); $v=$stCfg->fetchColumn();
            if ($v!==false&&$v!==null) $wpCfg[$k]=(string)$v;
            elseif ($origem==='br') { $stCfg->execute(['wordpress_'.$k]); $v2=$stCfg->fetchColumn(); if($v2!==false&&$v2!==null) $wpCfg[$k]=(string)$v2; }
        }
    }
    $host=trim((string)($wpCfg['db_host']??'')); $dbname=trim((string)($wpCfg['db_name']??'')); $user=trim((string)($wpCfg['db_user']??'')); $pass=(string)($wpCfg['db_pass']??''); $prefix=trim((string)($wpCfg['table_prefix']??'wp_'));
    if($prefix==='')$prefix='wp_';
    $port=null; if($host!==''&&strpos($host,':')!==false){$parts=explode(':',$host,2);$host=trim($parts[0]);$pp=trim($parts[1]??'');if($pp!==''&&ctype_digit($pp))$port=(int)$pp;}
    if($host===''||$dbname===''||$user===''){http_response_code(500);echo 'WP('.$origem.') não configurado.';exit;}
    $dsn='mysql:host='.$host.';'.($port?('port='.$port.';'):'').'dbname='.$dbname.';charset=utf8mb4';
    try{$wpPdo=new \PDO($dsn,$user,$pass,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);}catch(\Exception $e){http_response_code(500);echo 'Falha WP: '.$e->getMessage();exit;}
    
    foreach(['_label_data','_correios_label_data'] as $mk){
        $stL=$wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id=? AND meta_key=? LIMIT 1");
        $stL->execute([$wpPostId,$mk]); $ld=(string)($stL->fetchColumn()?:'');
        if($ld!==''){$pdf=base64_decode($ld);if($pdf!==false&&strlen($pdf)>100){header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="etiqueta-'.$tracking.'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;}}
    }
    // Fallback meta_json
    $meta=json_decode((string)($etiqueta['meta_json']??'{}'),true)?:[];
    $ll=$meta['_label_data']??($meta['_correios_label_data']??'');
    if($ll!==''){$pdf=base64_decode($ll);if($pdf!==false&&strlen($pdf)>100){header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="etiqueta-'.$tracking.'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;}}
    
    http_response_code(404); echo 'PDF não disponível para '.$tracking; exit;
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
            // Verificar se o usuário está ativo antes de restaurar sessão
            $userStatus = strtolower(trim((string) ($u['status'] ?? $u['ativo'] ?? $u['active'] ?? '')));
            if ($userStatus === 'inativo' || $userStatus === 'inactive' || $userStatus === '0' || $userStatus === 'bloqueado' || $userStatus === 'blocked') {
                // Usuário inativo — limpar cookie e não restaurar sessão
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
            } else {
                $auth = new \App\Services\AuthService();
                $auth->criarSessao($u);
            }
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

// Pacotes WordPress: dispatch direto para ações via ?action= (bypass OPcache)
if ($request->getPath() === '/admin/pacotes-wordpress' && isset($_GET['action']) && $_GET['action'] !== '') {
    $act = $_GET['action'];
    if ($act === 'etiqueta-pdf') {
        $ctrl2 = new \App\Controllers\AdminPacotesWpController2();
        $ctrl2->etiquetaPdf($request);
        exit;
    }
    // Demais actions via controller principal
    $ctrl = new \App\Controllers\AdminPacotesWordpressController();
    if ($act === 'containers') { $ctrl->containers($request); exit; }
    if ($act === 'container-novo') { $ctrl->containerNovo($request); exit; }
    if ($act === 'container-detalhes') { $ctrl->containerDetalhes($request); exit; }
    if ($act === 'faturas') { $ctrl->faturas($request); exit; }
    if ($act === 'fatura-nova') { $ctrl->faturaNova($request); exit; }
    if ($act === 'sincronizar') { $ctrl->sincronizar($request); exit; }
    if ($act === 'container-criar') { $ctrl->containerCriar($request); exit; }
    if ($act === 'container-deletar') { $ctrl->containerDeletar($request); exit; }
    if ($act === 'fatura-criar') { $ctrl->faturaCriar($request); exit; }
}

$router->dispatch($request);
