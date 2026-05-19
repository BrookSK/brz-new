<?php
/**
 * Endpoint direto para download de etiqueta PDF dos pacotes WordPress.
 * Acesso: /wp-etiqueta.php?id=1545
 * Este arquivo existe separado para contornar OPcache do index.php/controller.
 */
date_default_timezone_set('America/Sao_Paulo');

// Sessão
$sessionLifetime = 60 * 60 * 24 * 7;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', (string) $sessionLifetime);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não autenticado. Faça login primeiro.';
    exit;
}

// Verificar perfil
$perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['perfil'] ?? ''))));
if (!in_array($perfil, ['admin', 'vendedor', 'suporte'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado.';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Parâmetro id é obrigatório.';
    exit;
}

// Conectar ao banco local
require_once __DIR__ . '/../config/Database.php';
$pdo = \Config\Database::getConnection();

// Verificar tabela
$st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
$st->execute(['wp_packet_etiquetas']);
if (((int) $st->fetchColumn()) === 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Tabela wp_packet_etiquetas não existe.';
    exit;
}

// Buscar etiqueta
$st = $pdo->prepare("SELECT * FROM wp_packet_etiquetas WHERE id = ? LIMIT 1");
$st->execute([$id]);
$etiqueta = $st->fetch(\PDO::FETCH_ASSOC);

if (!$etiqueta) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Etiqueta #' . $id . ' não encontrada.';
    exit;
}

$origem = (string) ($etiqueta['origem'] ?? 'br');
$wpPostId = (int) ($etiqueta['wp_post_id'] ?? 0);
$tracking = (string) ($etiqueta['tracking_number'] ?? '');

if ($tracking === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Etiqueta #' . $id . ' sem tracking.';
    exit;
}

// Conectar ao WordPress
$source = strtolower(trim($origem));
if (!in_array($source, ['br', 'red', 'us'], true)) $source = 'br';
$cat = 'wordpress_' . $source;
$wpCfg = ['table_prefix' => 'wp_'];

$cols = [];
$stC = $pdo->query('DESCRIBE configuracoes_sistema');
$cols = $stC ? ($stC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

$hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);

if ($hasCategoria) {
    $stCfg = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
    foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
        $stCfg->execute([$cat, $k]);
        $v = $stCfg->fetchColumn();
        if ($v !== false && $v !== null) $wpCfg[$k] = (string) $v;
        elseif ($source === 'br') {
            $stCfg->execute(['wordpress', $k]);
            $v2 = $stCfg->fetchColumn();
            if ($v2 !== false && $v2 !== null) $wpCfg[$k] = (string) $v2;
        }
    }
} else {
    $stCfg = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
    foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
        $stCfg->execute([$cat . '_' . $k]);
        $v = $stCfg->fetchColumn();
        if ($v !== false && $v !== null) $wpCfg[$k] = (string) $v;
        elseif ($source === 'br') {
            $stCfg->execute(['wordpress_' . $k]);
            $v2 = $stCfg->fetchColumn();
            if ($v2 !== false && $v2 !== null) $wpCfg[$k] = (string) $v2;
        }
    }
}

$host = trim((string) ($wpCfg['db_host'] ?? ''));
$dbname = trim((string) ($wpCfg['db_name'] ?? ''));
$user = trim((string) ($wpCfg['db_user'] ?? ''));
$pass = (string) ($wpCfg['db_pass'] ?? '');
$prefix = trim((string) ($wpCfg['table_prefix'] ?? 'wp_'));
if ($prefix === '') $prefix = 'wp_';

$port = null;
if ($host !== '' && strpos($host, ':') !== false) {
    $parts = explode(':', $host, 2);
    $host = trim($parts[0]);
    $portPart = trim($parts[1] ?? '');
    if ($portPart !== '' && ctype_digit($portPart)) $port = (int) $portPart;
}

if ($host === '' || $dbname === '' || $user === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'WordPress (' . $source . ') não configurado.';
    exit;
}

$dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
try {
    $wpPdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falha ao conectar no WordPress (' . $source . '): ' . $e->getMessage();
    exit;
}

// Buscar PDF
$metaKeys = ['_label_data', '_correios_label_data'];
foreach ($metaKeys as $metaKey) {
    $stL = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1");
    $stL->execute([$wpPostId, $metaKey]);
    $labelData = (string) ($stL->fetchColumn() ?: '');
    if ($labelData !== '') {
        $pdfContent = base64_decode($labelData);
        if ($pdfContent !== false && strlen($pdfContent) > 100) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
            header('Content-Length: ' . strlen($pdfContent));
            echo $pdfContent;
            exit;
        }
    }
}

// Fallback: meta_json local
$meta = json_decode((string) ($etiqueta['meta_json'] ?? '{}'), true) ?: [];
$localLabel = $meta['_label_data'] ?? ($meta['_correios_label_data'] ?? '');
if ($localLabel !== '') {
    $pdfContent = base64_decode($localLabel);
    if ($pdfContent !== false && strlen($pdfContent) > 100) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'PDF não disponível para tracking ' . $tracking . ' (wp_post_id=' . $wpPostId . ', origem=' . $source . ').';
exit;
