<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminConfiguracoesController extends Controller {
    
    public function index(Request $request) {
        try {
            $dbg = (string) ($request->getParam('__debug_admin_config') ?? '');
            if ($dbg === '1') {
                header('Content-Type: text/plain; charset=UTF-8');
                $out = [];
                $out[] = 'debug=AdminConfiguracoesController@index';
                $out[] = '__FILE__=' . (string) __FILE__;
                $out[] = 'realpath(__FILE__)=' . (string) (realpath(__FILE__) ?: '');
                $out[] = 'php_version=' . (string) PHP_VERSION;
                $out[] = 'sapi=' . (string) (php_sapi_name() ?: '');
                $out[] = 'cwd=' . (string) (getcwd() ?: '');
                $out[] = 'document_root=' . (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
                $out[] = 'request_uri=' . (string) ($_SERVER['REQUEST_URI'] ?? '');

                if (function_exists('opcache_get_status')) {
                    $st = @opcache_get_status(false);
                    $enabled = (is_array($st) && isset($st['opcache_enabled'])) ? (bool) $st['opcache_enabled'] : null;
                    $out[] = 'opcache_get_status=available';
                    $out[] = 'opcache_enabled=' . ($enabled === null ? 'null' : ($enabled ? '1' : '0'));

                    if (is_array($st) && isset($st['scripts']) && is_array($st['scripts'])) {
                        $needle = (string) (realpath(__FILE__) ?: __FILE__);
                        $keys = array_keys($st['scripts']);
                        $foundKey = '';
                        foreach ($keys as $k) {
                            if (!is_string($k)) continue;
                            if ($k === $needle || str_replace('\\', '/', $k) === str_replace('\\', '/', $needle)) {
                                $foundKey = $k;
                                break;
                            }
                        }
                        $out[] = 'opcache_script_entry_found=' . ($foundKey !== '' ? '1' : '0');
                        if ($foundKey !== '') {
                            $entry = $st['scripts'][$foundKey];
                            if (is_array($entry)) {
                                $out[] = 'opcache_script_timestamp=' . (string) ($entry['timestamp'] ?? '');
                                $out[] = 'opcache_script_last_used_timestamp=' . (string) ($entry['last_used_timestamp'] ?? '');
                            }
                        }
                    }
                } else {
                    $out[] = 'opcache_get_status=unavailable';
                }

                echo implode("\n", $out);
                exit;
            }
        } catch (\Exception $e) {
        }

        $auth = new AuthService();
        $auth->requerPerfil('admin');
        try {
            $pdo = Database::getConnection();
            
            // Buscar configurações
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];

            $config = [];

            if (($tableInfo['mode'] ?? '') === 'single_row') {
                $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$tableInfo['idCol']} ASC LIMIT 1");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                $map = $tableInfo['columnMap'] ?? [];
                foreach ($map as $categoria => $chaves) {
                    foreach ($chaves as $chave => $col) {
                        if (array_key_exists($col, $row)) {
                            if (!isset($config[$categoria])) {
                                $config[$categoria] = [];
                            }
                            $config[$categoria][$chave] = (string) ($row[$col] ?? '');
                        }
                    }
                }
            } else {
                $orderBy = [];
                if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                    $orderBy = [$tableInfo['categoriaCol'], $tableInfo['chaveCol']];
                } else {
                    $orderBy = [$tableInfo['keyCol']];
                }
                if (!empty($tableInfo['updatedAtCol'])) {
                    $orderBy[] = $tableInfo['updatedAtCol'] . ' ASC';
                }
                if (!empty($tableInfo['idCol'])) {
                    $orderBy[] = $tableInfo['idCol'] . ' ASC';
                }

                if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                    $sql = "SELECT {$tableInfo['categoriaCol']} AS categoria, {$tableInfo['chaveCol']} AS chave, {$tableInfo['valueCol']} AS valor FROM {$table} ORDER BY " . implode(', ', $orderBy);
                } else {
                    $sql = "SELECT {$tableInfo['keyCol']} AS chave, {$tableInfo['valueCol']} AS valor FROM {$table} ORDER BY " . implode(', ', $orderBy);
                }

                $stmt = $pdo->query($sql);
                $configuracoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($configuracoes as $c) {
                    $valor = $c['valor'] ?? '';

                    $categoria = '';
                    $chave = '';

                    if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                        $categoria = (string) ($c['categoria'] ?? '');
                        $chave = (string) ($c['chave'] ?? '');
                    } else {
                        $fullKey = (string) ($c['chave'] ?? '');
                        if ($fullKey === '') {
                            continue;
                        }

                        if (preg_match('/^(wordpress|woocommerce)_(br|red|us)_(.+)$/', $fullKey, $m)) {
                            $categoria = $m[1] . '_' . $m[2];
                            $chave = $m[3];
                        } elseif (strpos($fullKey, '_') !== false) {
                            [$categoria, $chave] = explode('_', $fullKey, 2);
                        } else {
                            $categoria = 'geral';
                            $chave = $fullKey;
                        }
                    }

                    $categoria = trim($categoria);
                    $chave = trim($chave);
                    if ($categoria === '' || $chave === '') {
                        continue;
                    }
                    if (!isset($config[$categoria])) {
                        $config[$categoria] = [];
                    }
                    $config[$categoria][$chave] = $valor;
                }
            }

            // Comissão específica por representante (tabela dedicada)
            try {
                $repData = $request->getParam('representante_comissoes', null);
                if (is_array($repData) && $this->tableExists($pdo, 'representante_comissoes')) {
                    foreach ($repData as $rid => $percent) {
                        $rid = (int) $rid;
                        if ($rid <= 0) continue;
                        $p = is_numeric($percent) ? (float) $percent : null;
                        if ($p === null) continue;
                        if ($p < 0) $p = 0;
                        if ($p > 100) $p = 100;
                        $stmtUp = $pdo->prepare('INSERT INTO representante_comissoes (representante_id, percentual, ativo, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), ativo = 1, updated_at = NOW()');
                        $stmtUp->execute([$rid, $p]);
                    }
                }
            } catch (\Exception $e) {
            }
            
        } catch (\Exception $e) {
            $config = [];
        }

        $mapaCalor = [];
        try {
            $mapaCalor = $this->getMapaCalorData($pdo ?? null);
        } catch (\Exception $e) {
            $mapaCalor = [];
        }

        $mapaCalorTabHtml = '';
        try {
            $mapaCalorTabHtml = $this->renderMapaCalorTabHtml($mapaCalor);
        } catch (\Exception $e) {
            $mapaCalorTabHtml = $this->renderMapaCalorTabHtml([]);
        }

        $repComissoesHtml = '';
        try {
            $repComissoesHtml = $this->renderRepresentantesComissoesHtml($pdo ?? null);
        } catch (\Exception $e) {
            $repComissoesHtml = '';
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $clubeFaixas = [];
        try {
            if (isset($pdo) && $pdo instanceof \PDO && $this->tableExists($pdo, 'clube_descontos_faixas')) {
                $st = $pdo->query('SELECT id, peso_min_kg, peso_max_kg, percentual_desconto, ativo, ordem FROM clube_descontos_faixas ORDER BY ativo DESC, ordem ASC, peso_min_kg ASC, id ASC');
                $clubeFaixas = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (\Exception $e) {
            $clubeFaixas = [];
        }

        // Demandas config
        $demandasConfig = ['demandas_senha_painel' => '', 'demandas_emails_notificacao' => '', 'demandas_webhook_url' => '', 'demandas_usuarios_notificacao' => ''];
        $demandasUsuarios = [];
        try {
            if (isset($pdo) && $pdo instanceof \PDO) {
                $st = $pdo->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('demandas_senha_painel','demandas_emails_notificacao','demandas_webhook_url','demandas_usuarios_notificacao')");
                $st->execute();
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $demandasConfig[$r['chave']] = $r['valor'] ?? '';
                }
                $st2 = $pdo->query("SELECT id, nome, email FROM usuarios WHERE perfil IN ('admin','suporte') ORDER BY nome");
                $demandasUsuarios = $st2 ? ($st2->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (\Exception $e) {}

        echo "<!-- DEBUG_ADMIN_CONFIG controller=" . basename(__FILE__) . " ts=" . date('c') . " -->\n";
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    :root {
      --navy: #18253D;
      --navy-hover: #243049;
      --bg-page: #F5F7FA;
      --surface: #FFFFFF;
      --surface-soft: #FAFBFC;
      --border: #EBF0F6;
      --border-strong: #E2E8F0;
      --text-main: #1F2937;
      --text-secondary: #64748B;
      --text-muted: #94A3B8;
    }
    .settings-page { max-width: 1440px; margin: 0 auto; padding: 24px; }
    .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .page-title { margin: 0; font-size: 20px; line-height: 1.2; font-weight: 700; color: var(--navy); }
    .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .btn-config { height: 36px; border-radius: 8px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; font-size: 13px; font-weight: 500; cursor: pointer; transition: .18s ease; white-space: nowrap; border: 1px solid var(--border-strong); background: #fff; color: #374151; }
    .btn-config:hover { background: #F8FAFC; border-color: #CBD5E1; }
    .settings-layout { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 16px; align-items: start; }
    .settings-sidebar { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 10px; }
    .settings-nav { display: flex; flex-direction: column; gap: 4px; list-style: none; padding: 0; margin: 0; }
    .settings-nav .nav-link { width: 100%; min-height: 38px; border: none; border-radius: 8px; background: transparent; color: #334155; padding: 0 12px; display: flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 500; text-align: left; cursor: pointer; transition: .18s ease; }
    .settings-nav .nav-link i { width: 15px; font-size: 14px; color: var(--text-muted); flex: 0 0 auto; }
    .settings-nav .nav-link:hover { background: #F8FAFC; color: var(--navy); }
    .settings-nav .nav-link.active { background: var(--navy) !important; color: #fff !important; }
    .settings-nav .nav-link.active i { color: #fff !important; }
    .settings-mobile-nav-wrap { display: none; }
    .settings-mobile-nav { width: 100%; height: 42px; border: 1px solid var(--border-strong); border-radius: 8px; background: #fff; color: var(--text-main); padding: 0 12px; font-size: 13px; outline: none; }
    .settings-mobile-nav:focus { border-color: #94A3B8; box-shadow: 0 0 0 3px rgba(100,116,139,.1); }
    .settings-content-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .settings-content-card .card { border: none; box-shadow: none; margin: 0; }
    .settings-content-card .card-header { padding: 16px 18px; border-bottom: 1px solid var(--border); background: var(--surface-soft); }
    .settings-content-card .card-header h5 { margin: 0; color: var(--navy); font-size: 15px; font-weight: 700; }
    .settings-content-card .card-body { padding: 18px; }
    .settings-content-card .form-label { color: var(--text-main); font-size: 13px; font-weight: 500; }
    .settings-content-card .form-control, .settings-content-card .form-select { border: 1px solid var(--border-strong); border-radius: 8px; font-size: 13px; transition: .18s ease; }
    .settings-content-card .form-control:focus, .settings-content-card .form-select:focus { border-color: #94A3B8; box-shadow: 0 0 0 3px rgba(100,116,139,.1); }
    .settings-content-card .form-check-input:checked { background-color: var(--navy); border-color: var(--navy); }
    .settings-content-card .btn-primary { background: var(--navy); border-color: var(--navy); border-radius: 8px; font-size: 13px; font-weight: 500; }
    .settings-content-card .btn-primary:hover { background: var(--navy-hover); }
    @media (max-width: 1100px) {
      .settings-page { padding: 20px; }
      .settings-layout { grid-template-columns: 230px minmax(0, 1fr); }
    }
    @media (max-width: 768px) {
      .settings-page { padding: 14px; }
      .page-header { flex-direction: column; align-items: stretch; gap: 14px; margin-bottom: 14px; }
      .page-title { font-size: 19px; }
      .header-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
      .header-actions .btn-config { width: 100%; height: 40px; }
      .settings-layout { grid-template-columns: 1fr; gap: 12px; }
      .settings-sidebar { display: none; }
      .settings-mobile-nav-wrap { display: block; }
      .settings-content-card .card-header { padding: 14px; }
      .settings-content-card .card-body { padding: 14px; }
    }
    @media (max-width: 420px) {
      .settings-page { padding: 12px; }
      .header-actions { grid-template-columns: 1fr; }
    }
    </style>';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('configuracoes');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="settings-page">

            <header class="page-header">
                <h1 class="page-title">Configurações</h1>
                <div class="header-actions">
                    <button type="button" class="btn-config" onclick="testarStripeAPI()">
                        <i class="bi bi-plug-fill"></i> Testar Conexão
                    </button>
                    <button type="button" class="btn-config" onclick="verDocumentacaoStripe()">
                        <i class="bi bi-file-earmark-text"></i> Documentação
                    </button>
                </div>
            </header>

            <section class="settings-layout">

                <!-- MENU LATERAL DESKTOP -->
                <aside class="settings-sidebar">
                    <div class="settings-nav nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                        <button class="nav-link active" id="v-pills-loja-tab" data-bs-toggle="pill" data-bs-target="#v-pills-loja" type="button">
                            <i class="bi bi-shop"></i> Loja
                        </button>
                        <button class="nav-link" id="v-pills-layout-tab" data-bs-toggle="pill" data-bs-target="#v-pills-layout" type="button">
                            <i class="bi bi-image"></i> Layout
                        </button>
                        <button class="nav-link" id="v-pills-email-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email" type="button">
                            <i class="bi bi-envelope-fill"></i> Email
                        </button>
                        <button class="nav-link" id="v-pills-email-creator-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email-creator" type="button">
                            <i class="bi bi-pencil-square"></i> Criar E-mail
                        </button>
                        <button class="nav-link" id="v-pills-notificacoes-tab" data-bs-toggle="pill" data-bs-target="#v-pills-notificacoes" type="button">
                            <i class="bi bi-bell-fill"></i> Notificações
                        </button>
                        <button class="nav-link" id="v-pills-pagamentos-tab" data-bs-toggle="pill" data-bs-target="#v-pills-pagamentos" type="button">
                            <i class="bi bi-credit-card-fill"></i> Pagamentos
                        </button>
                        <button class="nav-link" id="v-pills-entrega-tab" data-bs-toggle="pill" data-bs-target="#v-pills-entrega" type="button">
                            <i class="bi bi-truck"></i> Entrega
                        </button>
                        <button class="nav-link" id="v-pills-seo-tab" data-bs-toggle="pill" data-bs-target="#v-pills-seo" type="button">
                            <i class="bi bi-search"></i> SEO
                        </button>
                        <button class="nav-link" id="v-pills-assessoria-tab" data-bs-toggle="pill" data-bs-target="#v-pills-assessoria" type="button">
                            <i class="bi bi-robot"></i> Assessoria / IA
                        </button>
                        <button class="nav-link" id="v-pills-comissoes-tab" data-bs-toggle="pill" data-bs-target="#v-pills-comissoes" type="button">
                            <i class="bi bi-percent"></i> Comissões
                        </button>
                        <button class="nav-link" id="v-pills-mapa-calor-tab" data-bs-toggle="pill" data-bs-target="#v-pills-mapa-calor" type="button">
                            <i class="bi bi-map-fill"></i> Mapa de calor
                        </button>
                        <button class="nav-link" id="v-pills-sistema-tab" data-bs-toggle="pill" data-bs-target="#v-pills-sistema" type="button">
                            <i class="bi bi-gear-fill"></i> Sistema
                        </button>
                        <button class="nav-link" id="v-pills-wordpress-tab" data-bs-toggle="pill" data-bs-target="#v-pills-wordpress" type="button">
                            <i class="bi bi-wordpress"></i> WordPress
                        </button>
                        <button class="nav-link" id="v-pills-woocommerce-tab" data-bs-toggle="pill" data-bs-target="#v-pills-woocommerce" type="button">
                            <i class="bi bi-bag-check-fill"></i> WooCommerce
                        </button>
                        <button class="nav-link" id="v-pills-demandas-tab" data-bs-toggle="pill" data-bs-target="#v-pills-demandas" type="button">
                            <i class="bi bi-list-check"></i> Demandas (TI)
                        </button>
                    </div>
                </aside>

                <!-- MENU MOBILE -->
                <div class="settings-mobile-nav-wrap">
                    <select class="settings-mobile-nav" id="settingsMobileSelect">
                        <option value="v-pills-loja" selected>Loja</option>
                        <option value="v-pills-layout">Layout</option>
                        <option value="v-pills-email">Email</option>
                        <option value="v-pills-email-creator">Criar E-mail</option>
                        <option value="v-pills-notificacoes">Notificações</option>
                        <option value="v-pills-pagamentos">Pagamentos</option>
                        <option value="v-pills-entrega">Entrega</option>
                        <option value="v-pills-seo">SEO</option>
                        <option value="v-pills-assessoria">Assessoria / IA</option>
                        <option value="v-pills-comissoes">Comissões</option>
                        <option value="v-pills-mapa-calor">Mapa de calor</option>
                        <option value="v-pills-sistema">Sistema</option>
                        <option value="v-pills-wordpress">WordPress</option>
                        <option value="v-pills-woocommerce">WooCommerce</option>
                        <option value="v-pills-demandas">Demandas (TI)</option>
                    </select>
                </div>

                <!-- CONTEÚDO -->
                <article class="settings-content-card">
                    <form method="POST" action="/admin/configuracoes/salvar" enctype="multipart/form-data" novalidate>
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- Configurações da Loja -->
                            <div class="tab-pane fade show active" id="v-pills-loja" role="tabpanel">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Configurações da Loja</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nome da Loja</label>
                                            <input type="text" class="form-control" name="loja_nome" value="' . $this->getConfigValue($config, 'loja', 'nome', 'Braziliana') . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Descrição</label>
                                            <textarea class="form-control" name="loja_descricao" rows="3">' . $this->getConfigValue($config, 'loja', 'descricao', '') . '</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email de Contato</label>
                                            <input type="email" class="form-control" name="loja_email" value="' . $this->getConfigValue($config, 'loja', 'email', 'contato@brazilianashop.com.br') . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefone</label>
                                            <input type="tel" class="form-control" name="loja_telefone" value="' . $this->getConfigValue($config, 'loja', 'telefone', '') . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Endereço</label>
                                            <input type="text" class="form-control" name="loja_endereco" value="' . $this->getConfigValue($config, 'loja', 'endereco', '') . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Logo URL</label>
                                            <input type="text" class="form-control" name="loja_logo" value="' . $this->getConfigValue($config, 'loja', 'logo', '') . '">
                                        </div>
                                        <hr>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="loja_conversao_moeda_ativa" name="loja_conversao_moeda_ativa" value="1" ' . ($this->getConfigValue($config, 'loja', 'conversao_moeda_ativa', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label" for="loja_conversao_moeda_ativa">
                                                    <strong>Exibir conversão de moeda para o cliente</strong>
                                                </label>
                                            </div>
                                            <small class="text-muted">Quando desativado, o seletor de moeda BRL/USD e os valores convertidos não aparecem para o cliente nas telas do site (exceto no checkout, onde a conversão é sempre disponível).</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <div class="tab-pane fade" id="v-pills-layout" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Layout</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <div class="mb-2 fw-semibold">Logotipo</div>
                                                <div class="text-muted small mb-3">Upload do logo para aparecer no topo do site.</div>

                                                ';
                                                $existingLogo = (string) $this->getConfigValue($config, 'layout', 'logo', '');
                                                $existingLogo = is_string($existingLogo) ? trim($existingLogo) : '';
                                                $existingLogoEsc = htmlspecialchars($existingLogo, ENT_QUOTES, 'UTF-8');
                                                echo '
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-5">
                                                        <div class="border rounded p-2" style="background: #fff;">
                                                            <div class="text-muted small mb-2">Pré-visualização</div>
                                                            <div style="height: 54px; display:flex; align-items:center; justify-content:flex-start; gap:10px;">
                                                                ' . ($existingLogoEsc !== '' ? '<img src="' . $existingLogoEsc . '" alt="Logotipo" style="max-height: 48px; max-width: 100%; object-fit: contain;">' : '<div class="text-muted">Nenhum logotipo cadastrado</div>') . '
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="layout_logo_keep" value="' . $existingLogoEsc . '">
                                                    </div>
                                                    <div class="col-12 col-md-7">
                                                        <label class="form-label">Upload do Logotipo</label>
                                                        <input type="file" class="form-control" name="layout_logo" accept="image/*">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLayoutLogo">Remover logotipo</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener("DOMContentLoaded", function() {
                                                    var btn = document.getElementById("btnRemoveLayoutLogo");
                                                    if (!btn) return;
                                                    btn.addEventListener("click", function() {
                                                        var input = document.querySelector("input[name=layout_logo_keep]");
                                                        if (input) input.value = "";
                                                        alert("Logotipo será removido ao salvar.");
                                                    });
                                                });
                                                </script>
                                                ';
                                            echo '
                                            </div>

                                            <div class="mb-4">
                                                <div class="mb-2 fw-semibold">Logotipo do Rodapé</div>
                                                <div class="text-muted small mb-3">Upload do logo para aparecer no rodapé do site.</div>

                                                ';
                                                $existingFooterLogo = (string) $this->getConfigValue($config, 'layout', 'logo_footer', '');
                                                $existingFooterLogo = is_string($existingFooterLogo) ? trim($existingFooterLogo) : '';
                                                $existingFooterLogoEsc = htmlspecialchars($existingFooterLogo, ENT_QUOTES, 'UTF-8');
                                                echo '
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-5">
                                                        <div class="border rounded p-2" style="background: #fff;">
                                                            <div class="text-muted small mb-2">Pré-visualização</div>
                                                            <div style="height: 54px; display:flex; align-items:center; justify-content:flex-start; gap:10px;">
                                                                ' . ($existingFooterLogoEsc !== '' ? '<img src="' . $existingFooterLogoEsc . '" alt="Logotipo Rodapé" style="max-height: 48px; max-width: 100%; object-fit: contain;">' : '<div class="text-muted">Nenhum logotipo do rodapé cadastrado</div>') . '
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="layout_logo_footer_keep" value="' . $existingFooterLogoEsc . '">
                                                    </div>
                                                    <div class="col-12 col-md-7">
                                                        <label class="form-label">Upload do Logotipo do Rodapé</label>
                                                        <input type="file" class="form-control" name="layout_logo_footer" accept="image/*">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLayoutFooterLogo">Remover logotipo do rodapé</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener("DOMContentLoaded", function() {
                                                    var btn = document.getElementById("btnRemoveLayoutFooterLogo");
                                                    if (!btn) return;
                                                    btn.addEventListener("click", function() {
                                                        var input = document.querySelector("input[name=layout_logo_footer_keep]");
                                                        if (input) input.value = "";
                                                        alert("Logotipo do rodapé será removido ao salvar.");
                                                    });
                                                });
                                                </script>
                                                ';
                                            echo '
                                            </div>

                                            <div class="mb-4">
                                                <div class="mb-2 fw-semibold">Logo do Admin</div>
                                                <div class="text-muted small mb-3">Upload do logo para aparecer no painel administrativo.</div>

                                                ';
                                                $existingAdminLogo = (string) $this->getConfigValue($config, 'layout', 'logo_admin', '');
                                                $existingAdminLogo = is_string($existingAdminLogo) ? trim($existingAdminLogo) : '';
                                                $existingAdminLogoEsc = htmlspecialchars($existingAdminLogo, ENT_QUOTES, 'UTF-8');
                                                echo '
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-5">
                                                        <div class="border rounded p-2" style="background: #fff;">
                                                            <div class="text-muted small mb-2">Pré-visualização</div>
                                                            <div style="height: 54px; display:flex; align-items:center; justify-content:flex-start; gap:10px;">
                                                                ' . ($existingAdminLogoEsc !== '' ? '<img src="' . $existingAdminLogoEsc . '" alt="Logo Admin" style="max-height: 48px; max-width: 100%; object-fit: contain;">' : '<div class="text-muted">Nenhum logo do admin cadastrado</div>') . '
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="layout_logo_admin_keep" value="' . $existingAdminLogoEsc . '">
                                                    </div>
                                                    <div class="col-12 col-md-7">
                                                        <label class="form-label">Upload do Logo do Admin</label>
                                                        <input type="file" class="form-control" name="layout_logo_admin" accept="image/*">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLayoutAdminLogo">Remover logo do admin</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener("DOMContentLoaded", function() {
                                                    var btn = document.getElementById("btnRemoveLayoutAdminLogo");
                                                    if (!btn) return;
                                                    btn.addEventListener("click", function() {
                                                        var input = document.querySelector("input[name=layout_logo_admin_keep]");
                                                        if (input) input.value = "";
                                                        alert("Logo do admin será removido ao salvar.");
                                                    });
                                                });
                                                </script>
                                                ';

                                            echo '
                                            </div>

                                            <div class="mb-4">
                                                <div class="mb-2 fw-semibold">Favicon</div>
                                                <div class="text-muted small mb-3">Upload do ícone para aparecer na aba do navegador.</div>

                                                ';
                                                $existingFavicon = (string) $this->getConfigValue($config, 'layout', 'favicon', '');
                                                $existingFavicon = is_string($existingFavicon) ? trim($existingFavicon) : '';
                                                $existingFaviconEsc = htmlspecialchars($existingFavicon, ENT_QUOTES, 'UTF-8');
                                                echo '
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-5">
                                                        <div class="border rounded p-2" style="background: #fff;">
                                                            <div class="text-muted small mb-2">Pré-visualização</div>
                                                            <div style="height: 54px; display:flex; align-items:center; justify-content:flex-start; gap:10px;">
                                                                ' . ($existingFaviconEsc !== '' ? '<img src="' . $existingFaviconEsc . '" alt="Favicon" style="height: 32px; width: 32px; object-fit: contain;"> <span class="text-muted small">' . $existingFaviconEsc . '</span>' : '<div class="text-muted">Nenhum favicon cadastrado</div>') . '
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="layout_favicon_keep" value="' . $existingFaviconEsc . '">
                                                    </div>
                                                    <div class="col-12 col-md-7">
                                                        <label class="form-label">Upload do Favicon</label>
                                                        <input type="file" class="form-control" name="layout_favicon" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLayoutFavicon">Remover favicon</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener("DOMContentLoaded", function() {
                                                    var btn = document.getElementById("btnRemoveLayoutFavicon");
                                                    if (!btn) return;
                                                    btn.addEventListener("click", function() {
                                                        var input = document.querySelector("input[name=layout_favicon_keep]");
                                                        if (input) input.value = "";
                                                        alert("Favicon será removido ao salvar.");
                                                    });
                                                });
                                                </script>
                                                ';

                                            // --- Avatar BRI ---
                                            echo '
                                            <div class="mb-4">
                                                <div class="mb-2 fw-semibold">Avatar BRI (GIF/PNG)</div>
                                                <div class="text-muted small mb-3">Imagem do avatar da assistente BRI exibida no chat.</div>
                                                ';
                                                $existingBriAvatar = (string) $this->getConfigValue($config, 'layout', 'bri_avatar', '');
                                                $existingBriAvatar = is_string($existingBriAvatar) ? trim($existingBriAvatar) : '';
                                                $existingBriAvatarEsc = htmlspecialchars($existingBriAvatar, ENT_QUOTES, 'UTF-8');
                                                echo '
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-5">
                                                        <div class="border rounded p-2" style="background: #fff;">
                                                            <div class="text-muted small mb-2">Pré-visualização</div>
                                                            <div style="height: 54px; display:flex; align-items:center; justify-content:flex-start; gap:10px;">
                                                                ' . ($existingBriAvatarEsc !== '' ? '<img src="' . $existingBriAvatarEsc . '" alt="Avatar BRI" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;"> <span class="text-muted small">' . $existingBriAvatarEsc . '</span>' : '<div class="text-muted">Nenhum avatar cadastrado (usando padrão)</div>') . '
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="layout_bri_avatar_keep" value="' . $existingBriAvatarEsc . '">
                                                    </div>
                                                    <div class="col-12 col-md-7">
                                                        <label class="form-label">Upload do Avatar BRI</label>
                                                        <input type="file" class="form-control" name="layout_bri_avatar" accept="image/gif,image/png,image/webp">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLayoutBriAvatar">Remover avatar</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener("DOMContentLoaded", function() {
                                                    var btn = document.getElementById("btnRemoveLayoutBriAvatar");
                                                    if (!btn) return;
                                                    btn.addEventListener("click", function() {
                                                        var input = document.querySelector("input[name=layout_bri_avatar_keep]");
                                                        if (input) input.value = "";
                                                        alert("Avatar BRI será removido ao salvar (voltará ao padrão).");
                                                    });
                                                });
                                                </script>
                                            </div>
                                                ';

                                            $selectedLang = (string) ($_POST['layout_banners_lang'] ?? ($_GET['layout_banners_lang'] ?? 'pt'));
                                            if (!in_array($selectedLang, ['pt', 'en'], true)) {
                                                $selectedLang = 'pt';
                                            }

                                            echo '
                                            </div>

                                            <div class="mb-2 fw-semibold">Banners</div>
                                            <div class="text-muted small mb-3">Cadastre imagens para rodarem no header do site.</div>
                                            <div class="text-muted small mb-3">
                                                Desktop: <strong>1149 x 436</strong><br>
                                                Mobile: <strong>391 x 333</strong>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small mb-1">Idioma do banner</label>
                                                <select class="form-select" name="layout_banners_lang" onchange="(function(sel){var u=new URL(window.location.href);u.searchParams.set(\'layout_banners_lang\', sel.value);window.location.href=u.toString();})(this)">';

                                            echo '<option value="pt" ' . ($selectedLang === 'pt' ? 'selected' : '') . '>Português (PT)</option>';
                                            echo '<option value="en" ' . ($selectedLang === 'en' ? 'selected' : '') . '>English (EN)</option>';

                                            echo '</select>
                                                <div class="text-muted small mt-1">Os banners exibidos na Home mudam de acordo com o idioma selecionado no site.</div>
                                            </div>

                                            <div id="layout-banners-existing" class="row g-2 mb-3">
                                                ';

                                                $bannersKey = ($selectedLang === 'en') ? 'banners_en' : 'banners';
                                                $existingBannersRaw = (string) $this->getConfigValue($config, 'layout', $bannersKey, '[]');
                                                $existingBanners = json_decode($existingBannersRaw, true);
                                                if (!is_array($existingBanners)) $existingBanners = [];
                                                foreach ($existingBanners as $idx => $item) {
                                                    $desktop = '';
                                                    $mobile = '';
                                                    $link = '';

                                                    if (is_string($item)) {
                                                        $desktop = trim($item);
                                                    } elseif (is_array($item)) {
                                                        $desktop = isset($item['desktop']) && is_string($item['desktop']) ? trim((string) $item['desktop']) : '';
                                                        $mobile = isset($item['mobile']) && is_string($item['mobile']) ? trim((string) $item['mobile']) : '';
                                                        $link = isset($item['link']) && is_string($item['link']) ? trim((string) $item['link']) : '';
                                                    }

                                                    if ($desktop === '' && $mobile === '') continue;

                                                    $desktopEsc = htmlspecialchars($desktop, ENT_QUOTES, 'UTF-8');
                                                    $mobileEsc = htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8');
                                                    $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

                                                    echo '<div class="col-12 col-md-6">'
                                                        . '<div class="border rounded p-2 h-100">'
                                                        . '<div class="row g-2">'
                                                        . '<div class="col-12 col-sm-6">'
                                                        . '<div class="small text-muted mb-1">Desktop (1149x436)</div>'
                                                        . '<div class="ratio ratio-16x9 mb-2">'
                                                        . ($desktopEsc !== '' ? '<img src="' . $desktopEsc . '" class="w-100 h-100" style="object-fit: cover;" alt="Banner Desktop">' : '<div class="d-flex align-items-center justify-content-center text-muted" style="background:#f8fafc;">Sem imagem</div>')
                                                        . '</div>'
                                                        . '<input type="hidden" name="layout_banners_keep_desktop[]" value="' . $desktopEsc . '">' 
                                                        . '</div>'
                                                        . '<div class="col-12 col-sm-6">'
                                                        . '<div class="small text-muted mb-1">Mobile (391x333)</div>'
                                                        . '<div class="ratio" style="--bs-aspect-ratio: 85.2%;">'
                                                        . ($mobileEsc !== '' ? '<img src="' . $mobileEsc . '" class="w-100 h-100" style="object-fit: cover;" alt="Banner Mobile">' : '<div class="d-flex align-items-center justify-content-center text-muted" style="background:#f8fafc;">Sem imagem</div>')
                                                        . '</div>'
                                                        . '<input type="hidden" name="layout_banners_keep_mobile[]" value="' . $mobileEsc . '">' 
                                                        . '</div>'
                                                        . '<div class="col-12">'
                                                        . '<label class="form-label small mb-1">Link (ao clicar)</label>'
                                                        . '<input type="url" class="form-control" name="layout_banners_keep_link[]" value="' . $linkEsc . '" placeholder="https://...">'
                                                        . '</div>'
                                                        . '</div>'
                                                        . '<button type="button" class="btn btn-sm btn-outline-danger w-100 mt-2" onclick="this.closest(\'.col-12\').remove();">Remover</button>'
                                                        . '</div>'
                                                        . '</div>';
                                                }
                                                echo '
                                            </div>

                                            <div id="layout-banners-upload-list" class="d-flex flex-column gap-2">
                                                <div class="border rounded p-2">
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col-12 col-md-6">
                                                            <label class="form-label small mb-1">Banner Desktop (1149x436)</label>
                                                            <input type="file" class="form-control" name="layout_banners_desktop[]" accept="image/*">
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label class="form-label small mb-1">Banner Mobile (391x333)</label>
                                                            <input type="file" class="form-control" name="layout_banners_mobile[]" accept="image/*">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small mb-1">Link (ao clicar)</label>
                                                            <input type="url" class="form-control" name="layout_banners_link[]" placeholder="https://...">
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="button" class="btn btn-outline-secondary w-100" onclick="this.closest(\'.border\').remove();" title="Remover">-</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-primary" id="btnAddLayoutBanner">
                                                    <i class="fas fa-plus me-1"></i>Adicionar banner
                                                </button>
                                            </div>

                                            <script>
                                            document.addEventListener("DOMContentLoaded", function() {
                                                var btn = document.getElementById("btnAddLayoutBanner");
                                                var list = document.getElementById("layout-banners-upload-list");
                                                if (!btn || !list) return;

                                                btn.addEventListener("click", function() {
                                                    var box = document.createElement("div");
                                                    box.className = "border rounded p-2";
                                                    box.innerHTML = `
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label small mb-1">Banner Desktop (1149x436)</label>
                                                                <input type="file" class="form-control" name="layout_banners_desktop[]" accept="image/*">
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label small mb-1">Banner Mobile (391x333)</label>
                                                                <input type="file" class="form-control" name="layout_banners_mobile[]" accept="image/*">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small mb-1">Link (ao clicar)</label>
                                                                <input type="url" class="form-control" name="layout_banners_link[]" placeholder="https://...">
                                                            </div>
                                                            <div class="col-12">
                                                                <button type="button" class="btn btn-outline-secondary w-100" title="Remover">-</button>
                                                            </div>
                                                        </div>
                                                    `;

                                                    var removeBtn = box.querySelector("button");
                                                    if (removeBtn) {
                                                        removeBtn.addEventListener("click", function() { box.remove(); });
                                                    }
                                                    list.appendChild(box);
                                                });
                                            });
                                            </script>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="v-pills-woocommerce" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">WooCommerce (REST API)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-warning">
                                                Configure as credenciais por origem (BR / RED / US). Se estiver vazio, o sistema pode cair para a configuração antiga (sem origem) quando aplicável.
                                            </div>

                                            <div class="border rounded p-3 mb-3">
                                                <div class="fw-semibold mb-2">BR (https://br.brazilianashop.com.br)</div>
                                                <div class="mb-3">
                                                    <label class="form-label">Store URL</label>
                                                    <input type="url" class="form-control" name="woocommerce_br_store_url" value="' . $this->getConfigValue($config, 'woocommerce_br', 'store_url', $this->getConfigValue($config, 'woocommerce', 'store_url', '')) . '" placeholder="https://br.brazilianashop.com.br/">
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Key</label>
                                                            <input type="password" class="form-control" name="woocommerce_br_consumer_key" value="' . $this->getConfigValue($config, 'woocommerce_br', 'consumer_key', $this->getConfigValue($config, 'woocommerce', 'consumer_key', '')) . '" placeholder="ck_...">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Secret</label>
                                                            <input type="password" class="form-control" name="woocommerce_br_consumer_secret" value="' . $this->getConfigValue($config, 'woocommerce_br', 'consumer_secret', $this->getConfigValue($config, 'woocommerce', 'consumer_secret', '')) . '" placeholder="cs_...">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 mb-3">
                                                <div class="fw-semibold mb-2">RED (https://redirecionamento.brazilianashop.com.br)</div>
                                                <div class="mb-3">
                                                    <label class="form-label">Store URL</label>
                                                    <input type="url" class="form-control" name="woocommerce_red_store_url" value="' . $this->getConfigValue($config, 'woocommerce_red', 'store_url', '') . '" placeholder="https://redirecionamento.brazilianashop.com.br/">
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Key</label>
                                                            <input type="password" class="form-control" name="woocommerce_red_consumer_key" value="' . $this->getConfigValue($config, 'woocommerce_red', 'consumer_key', '') . '" placeholder="ck_...">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Secret</label>
                                                            <input type="password" class="form-control" name="woocommerce_red_consumer_secret" value="' . $this->getConfigValue($config, 'woocommerce_red', 'consumer_secret', '') . '" placeholder="cs_...">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3">
                                                <div class="fw-semibold mb-2">US (https://us.brazilianashop.com.br)</div>
                                                <div class="mb-3">
                                                    <label class="form-label">Store URL</label>
                                                    <input type="url" class="form-control" name="woocommerce_us_store_url" value="' . $this->getConfigValue($config, 'woocommerce_us', 'store_url', '') . '" placeholder="https://us.brazilianashop.com.br/">
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Key</label>
                                                            <input type="password" class="form-control" name="woocommerce_us_consumer_key" value="' . $this->getConfigValue($config, 'woocommerce_us', 'consumer_key', '') . '" placeholder="ck_...">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Consumer Secret</label>
                                                            <input type="password" class="form-control" name="woocommerce_us_consumer_secret" value="' . $this->getConfigValue($config, 'woocommerce_us', 'consumer_secret', '') . '" placeholder="cs_...">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Store URL</label>
                                                <input type="url" class="form-control" name="woocommerce_store_url" value="' . $this->getConfigValue($config, 'woocommerce', 'store_url', '') . '" placeholder="https://br.brazilianashop.com.br/">
                                                <small class="text-muted">URL base da loja (sem /wp-admin). Ex: <code>https://br.brazilianashop.com.br</code></small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Consumer Key</label>
                                                        <input type="password" class="form-control" name="woocommerce_consumer_key" value="' . $this->getConfigValue($config, 'woocommerce', 'consumer_key', '') . '" placeholder="ck_...">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Consumer Secret</label>
                                                        <input type="password" class="form-control" name="woocommerce_consumer_secret" value="' . $this->getConfigValue($config, 'woocommerce', 'consumer_secret', '') . '" placeholder="cs_...">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="alert alert-info mb-0">
                                                Essas credenciais são usadas para <strong>atualizar o pedido no WooCommerce</strong> (order meta) com <code>wexpress_shipping_id</code>, link da etiqueta e rastreio.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="v-pills-wordpress" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Integração WordPress (Somente leitura)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-warning">
                                                Configure as credenciais do banco WordPress por origem (BR / RED / US). Como são sites diferentes, podem existir IDs/números repetidos.
                                            </div>

                                            <div class="border rounded p-3 mb-3">
                                                <div class="fw-semibold mb-2">BR (https://br.brazilianashop.com.br)</div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Host</label>
                                                            <input type="text" class="form-control" name="wordpress_br_db_host" value="' . $this->getConfigValue($config, 'wordpress_br', 'db_host', $this->getConfigValue($config, 'wordpress', 'db_host', 'localhost')) . '" placeholder="localhost">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Database (nome)</label>
                                                            <input type="text" class="form-control" name="wordpress_br_db_name" value="' . $this->getConfigValue($config, 'wordpress_br', 'db_name', $this->getConfigValue($config, 'wordpress', 'db_name', '')) . '" placeholder="wp_database">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Usuário</label>
                                                            <input type="text" class="form-control" name="wordpress_br_db_user" value="' . $this->getConfigValue($config, 'wordpress_br', 'db_user', $this->getConfigValue($config, 'wordpress', 'db_user', '')) . '" placeholder="wp_user">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Senha</label>
                                                            <input type="password" class="form-control" name="wordpress_br_db_pass" value="' . $this->getConfigValue($config, 'wordpress_br', 'db_pass', $this->getConfigValue($config, 'wordpress', 'db_pass', '')) . '" placeholder="********">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Prefixo das tabelas</label>
                                                            <input type="text" class="form-control" name="wordpress_br_table_prefix" value="' . $this->getConfigValue($config, 'wordpress_br', 'table_prefix', $this->getConfigValue($config, 'wordpress', 'table_prefix', 'wp_')) . '" placeholder="wp_">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 mb-3">
                                                <div class="fw-semibold mb-2">RED (https://redirecionamento.brazilianashop.com.br)</div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Host</label>
                                                            <input type="text" class="form-control" name="wordpress_red_db_host" value="' . $this->getConfigValue($config, 'wordpress_red', 'db_host', 'localhost') . '" placeholder="localhost">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Database (nome)</label>
                                                            <input type="text" class="form-control" name="wordpress_red_db_name" value="' . $this->getConfigValue($config, 'wordpress_red', 'db_name', '') . '" placeholder="wp_database">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Usuário</label>
                                                            <input type="text" class="form-control" name="wordpress_red_db_user" value="' . $this->getConfigValue($config, 'wordpress_red', 'db_user', '') . '" placeholder="wp_user">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Senha</label>
                                                            <input type="password" class="form-control" name="wordpress_red_db_pass" value="' . $this->getConfigValue($config, 'wordpress_red', 'db_pass', '') . '" placeholder="********">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Prefixo das tabelas</label>
                                                            <input type="text" class="form-control" name="wordpress_red_table_prefix" value="' . $this->getConfigValue($config, 'wordpress_red', 'table_prefix', 'wp_') . '" placeholder="wp_">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3">
                                                <div class="fw-semibold mb-2">US (https://us.brazilianashop.com.br)</div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Host</label>
                                                            <input type="text" class="form-control" name="wordpress_us_db_host" value="' . $this->getConfigValue($config, 'wordpress_us', 'db_host', 'localhost') . '" placeholder="localhost">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Database (nome)</label>
                                                            <input type="text" class="form-control" name="wordpress_us_db_name" value="' . $this->getConfigValue($config, 'wordpress_us', 'db_name', '') . '" placeholder="wp_database">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Usuário</label>
                                                            <input type="text" class="form-control" name="wordpress_us_db_user" value="' . $this->getConfigValue($config, 'wordpress_us', 'db_user', '') . '" placeholder="wp_user">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Senha</label>
                                                            <input type="password" class="form-control" name="wordpress_us_db_pass" value="' . $this->getConfigValue($config, 'wordpress_us', 'db_pass', '') . '" placeholder="********">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Prefixo das tabelas</label>
                                                            <input type="text" class="form-control" name="wordpress_us_table_prefix" value="' . $this->getConfigValue($config, 'wordpress_us', 'table_prefix', 'wp_') . '" placeholder="wp_">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Host</label>
                                                        <input type="text" class="form-control" name="wordpress_db_host" value="' . $this->getConfigValue($config, 'wordpress', 'db_host', 'localhost') . '" placeholder="localhost">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Database (nome)</label>
                                                        <input type="text" class="form-control" name="wordpress_db_name" value="' . $this->getConfigValue($config, 'wordpress', 'db_name', '') . '" placeholder="wp_database">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Usuário</label>
                                                        <input type="text" class="form-control" name="wordpress_db_user" value="' . $this->getConfigValue($config, 'wordpress', 'db_user', '') . '" placeholder="wp_user">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Senha</label>
                                                        <input type="password" class="form-control" name="wordpress_db_pass" value="' . $this->getConfigValue($config, 'wordpress', 'db_pass', '') . '" placeholder="********">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Prefixo das tabelas</label>
                                                        <input type="text" class="form-control" name="wordpress_table_prefix" value="' . $this->getConfigValue($config, 'wordpress', 'table_prefix', 'wp_') . '" placeholder="wp_">
                                                        <small class="text-muted">Normalmente <code>wp_</code>. Se o site antigo tiver outro prefixo, ajuste aqui.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="alert alert-info mb-0">
                                                Essa integração é <strong>somente leitura</strong>. O sistema usará essas credenciais para exibir pedidos do site antigo.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="v-pills-notificacoes" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurar Notificações por Webhook</h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="formNotificacoes">
                                                <div class="mb-3">
                                                    <label class="form-label">Evento</label>
                                                    <select name="evento" class="form-select" required>
                                                        <option value="">Selecione um evento...</option>
                                                        <option value="novo_pedido">Novo Pedido</option>
                                                        <option value="pedido_aprovado">Pedido Aprovado</option>
                                                        <option value="pedido_enviado">Pedido Enviado</option>
                                                        <option value="pedido_entregue">Pedido Entregue</option>
                                                        <option value="pedido_cancelado">Pedido Cancelado</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">URL do Webhook</label>
                                                    <input type="url" name="webhook_url" class="form-control" placeholder="https://seu-webhook.com/notificacoes" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Método HTTP</label>
                                                    <select name="webhook_method" class="form-select">
                                                        <option value="POST">POST</option>
                                                        <option value="PUT">PUT</option>
                                                        <option value="PATCH">PATCH</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Headers (JSON)</label>
                                                    <textarea name="webhook_headers" class="form-control" rows="3" placeholder="{&quot;Authorization&quot;: &quot;Bearer token123&quot;}"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Campos Personalizados (JSON)</label>
                                                    <textarea name="webhook_campos" class="form-control" rows="5" placeholder="{&quot;empresa&quot;: &quot;Braziliana&quot;}"></textarea>
                                                    <small class="text-muted">Esses campos são mesclados no payload final enviado ao webhook.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Template da Mensagem</label>
                                                    <textarea name="webhook_template" class="form-control" rows="4" placeholder="Olá {{nome}}, seu pedido #{{codigo_pedido}} está {{status}}"></textarea>
                                                    <small class="text-muted">Você pode usar variáveis no formato <code>{{nome}}</code>, <code>{{codigo_pedido}}</code>, <code>{{status}}</code>, etc.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Campos Enviados no Webhook</label>
                                                    <div class="border rounded p-3 bg-light">
                                                        <div class="mb-2"><strong>Variáveis disponíveis:</strong></div>
                                                        <div class="row">
                                                            <div class="col-md-4"><code>{{evento}}</code></div>
                                                            <div class="col-md-4"><code>{{pedido_id}}</code></div>
                                                            <div class="col-md-4"><code>{{codigo_pedido}}</code></div>
                                                            <div class="col-md-4"><code>{{status}}</code></div>
                                                            <div class="col-md-4"><code>{{moeda}}</code></div>
                                                            <div class="col-md-4"><code>{{valor_total}}</code></div>
                                                            <div class="col-md-4"><code>{{nome}}</code></div>
                                                            <div class="col-md-4"><code>{{email}}</code></div>
                                                            <div class="col-md-4"><code>{{telefone}}</code></div>
                                                            <div class="col-md-4"><code>{{data}}</code></div>
                                                        </div>
                                                        <div class="mt-2"><small class="text-muted">Além disso, o sistema pode adicionar campos extras do evento (quando aplicável) e também tudo que você colocar em “Campos Personalizados (JSON)”.</small></div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Exemplo de Payload (JSON)</label>
                                                    <pre class="border rounded p-3 bg-light mb-0" style="white-space: pre-wrap;">{
  "channel": "whatsapp",
  "evento": "novo_pedido",
  "to": "5511999999999",
  "message": "Olá Cliente, seu pedido #ABC123 está aprovado.",
  "vars": {
    "evento": "novo_pedido",
    "pedido_id": "123",
    "codigo_pedido": "ABC123",
    "status": "aprovado",
    "moeda": "BRL",
    "valor_total": "199.90",
    "nome": "Cliente",
    "email": "cliente@exemplo.com",
    "telefone": "5511999999999",
    "data": "2026-01-30 12:00:00"
  }
}</pre>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="webhook_ativo" id="notificacoes_webhook_ativo" checked>
                                                        <label class="form-check-label" for="notificacoes_webhook_ativo">Webhook Ativo</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="webhook_retries" id="notificacoes_webhook_retries" checked>
                                                        <label class="form-check-label" for="notificacoes_webhook_retries">Tentativas de Reenvio</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Logs de Envio</label>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Data</th>
                                                                    <th>Status</th>
                                                                    <th>Resposta</th>
                                                                    <th>Ações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="notificacoes-logs-webhook">
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Nenhum log encontrado</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="d-flex justify-content-end">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="limparLogsWebhookNotificacoes()">
                                                            <i class="fas fa-trash"></i> Limpar logs
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-primary" onclick="salvarNotificacaoAdmin()">
                                                        <i class="fas fa-save me-2"></i>Salvar Configuração
                                                    </button>
                                                    <button type="button" class="btn btn-success" onclick="testarWebhookNotificacoes()">
                                                        <i class="fas fa-paper-plane me-2"></i>Testar Webhook
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações de Email -->
                                <div class="tab-pane fade" id="v-pills-email" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Email</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Driver SMTP</label>
                                                <select class="form-select" name="email_driver">
                                                    <option value="smtp" ' . ($this->getConfigValue($config, 'email', 'driver', 'smtp') === 'smtp' ? 'selected' : '') . '>SMTP</option>
                                                    <option value="mail" ' . ($this->getConfigValue($config, 'email', 'driver', '') === 'mail' ? 'selected' : '') . '>PHP Mail</option>
                                                    <option value="sendmail" ' . ($this->getConfigValue($config, 'email', 'driver', '') === 'sendmail' ? 'selected' : '') . '>Sendmail</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Host SMTP</label>
                                                <input type="text" class="form-control" name="email_host" value="' . $this->getConfigValue($config, 'email', 'host', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Porta SMTP</label>
                                                <input type="number" class="form-control" name="email_port" value="' . $this->getConfigValue($config, 'email', 'port', '587') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Usuário SMTP</label>
                                                <input type="text" class="form-control" name="email_username" value="' . $this->getConfigValue($config, 'email', 'username', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Senha SMTP</label>
                                                <input type="password" class="form-control" name="email_password" value="' . $this->getConfigValue($config, 'email', 'password', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Criptografia</label>
                                                <select class="form-select" name="email_encryption">
                                                    <option value="tls" ' . ($this->getConfigValue($config, 'email', 'encryption', 'tls') === 'tls' ? 'selected' : '') . '>TLS</option>
                                                    <option value="ssl" ' . ($this->getConfigValue($config, 'email', 'encryption', '') === 'ssl' ? 'selected' : '') . '>SSL</option>
                                                    <option value="" ' . ($this->getConfigValue($config, 'email', 'encryption', '') === '' ? 'selected' : '') . '>Nenhuma</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de Envio</label>
                                                <input type="email" class="form-control" name="email_from" value="' . $this->getConfigValue($config, 'email', 'from', 'noreply@brazilianashop.com.br') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nome de Envio</label>
                                                <input type="text" class="form-control" name="email_from_name" value="' . $this->getConfigValue($config, 'email', 'from_name', 'Braziliana') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de teste (para)</label>
                                                <input type="email" class="form-control" name="email_test_to" value="' . $this->getConfigValue($config, 'email', 'test_to', '') . '">
                                                <small class="text-muted">Usado ao clicar em “Testar” nos templates de e-mail.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Criador de E-mail -->
                                <div class="tab-pane fade" id="v-pills-email-creator" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Criador de E-mail</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipo de Evento</label>
                                                        <select class="form-select" id="evento_tipo" onchange="carregarVariaveis()">
                                                            <option value="">Selecione um evento...</option>
                                                            <option value="novo_pedido">🛒 Novo Pedido</option>
                                                            <option value="pedido_aprovado">✅ Pedido Aprovado</option>
                                                            <option value="pedido_enviado">📦 Pedido Enviado</option>
                                                            <option value="pedido_entregue">🎁 Pedido Entregue</option>
                                                            <option value="pedido_cancelado">❌ Pedido Cancelado</option>
                                                            <option value="novo_usuario">👤 Novo Usuário</option>
                                                            <option value="recuperar_senha">🔑 Recuperar Senha</option>
                                                            <option value="contato_contato">📧 Contato</option>
                                                            <option value="carne_criado">📄 Carnê Criado</option>
                                                            <option value="carne_cobranca">💰 Carnê - Cobrança</option>
                                                            <option value="carne_parcela_proxima_vencimento">⏰ Carnê - Parcela Próxima Vencimento</option>
                                                            <option value="carne_pagamento_confirmado">✅ Carnê - Pagamento Confirmado</option>
                                                            <option value="carne_quitado">🎉 Carnê Quitado</option>
                                                            <option value="carne_envio_liberado">🚚 Carnê - Envio Liberado</option>
                                                            <option value="carne_aviso_cancelamento">🚨 Carnê - Aviso Cancelamento</option>
                                                            <option value="carne_cancelado">❌ Carnê Cancelado</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Assunto do E-mail</label>
                                                        <input type="text" class="form-control" id="email_assunto" placeholder="Assunto do e-mail">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Variáveis Disponíveis</label>
                                                        <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;" id="variaveis_disponiveis">
                                                            <small class="text-muted">Selecione um evento para ver as variáveis disponíveis</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Editor HTML</label>
                                                        <textarea class="form-control" id="email_conteudo" rows="15" placeholder="Digite o conteúdo HTML do e-mail..."></textarea>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-outline-primary" onclick="inserirVariavel()">
                                                            <i class="fas fa-code"></i> Inserir Variável
                                                        </button>
                                                        <button type="button" class="btn btn-outline-success" onclick="previsualizarEmail()">
                                                            <i class="fas fa-eye"></i> Pré-visualizar
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info" onclick="salvarTemplate()">
                                                            <i class="fas fa-save"></i> Salvar Template
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
                                            <!-- Pré-visualização -->
                                            <div class="row mt-4" id="preview_section" style="display: none;">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📧 Pré-visualização do E-mail</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <iframe id="email_preview" style="width: 100%; height: 400px; border: 1px solid #ddd;"></iframe>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Templates Salvos -->
                                            <div class="row mt-4">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📋 Templates Salvos</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div id="templates_salvos">
                                                                <small class="text-muted">Nenhum template salvo ainda</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações de Entrega -->
                                <div class="tab-pane fade" id="v-pills-entrega" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Entrega</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Moeda Padrão</label>
                                                <select class="form-select" name="entrega_moeda_padrao">
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'USD' ? 'selected' : '') . '>USD - Dólar Americano</option>
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'BRL' ? 'selected' : '') . '>BRL - Real Brasileiro</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Taxa de Serviço (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_taxa_servico_kg" value="' . $this->getConfigValue($config, 'entrega', 'taxa_servico_kg', '39') . '">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Frete Grátis Acima de</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_frete_gratis_acima" value="' . $this->getConfigValue($config, 'entrega', 'frete_gratis_acima', '0') . '">
                                                </div>
                                                <small class="text-muted">Deixe como 0 para frete sempre grátis</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Valor Padrão do Frete (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_frete_padrao" value="' . $this->getConfigValue($config, 'entrega', 'frete_padrao', '15') . '">
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Custo fixo interno por item (USD)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_custo_envio_por_item_usd" value="' . $this->getConfigValue($config, 'entrega', 'custo_envio_por_item_usd', '0') . '">
                                                </div>
                                                <small class="text-muted">Usado nos relatórios para calcular custo interno de envio (custo por item x quantidade total de itens do pedido).</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Prazo Padrão (dias)</label>
                                                <input type="number" class="form-control" name="entrega_prazo_padrao" value="' . $this->getConfigValue($config, 'entrega', 'prazo_padrao', '30') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">CEP de Origem</label>
                                                <input type="text" class="form-control" name="entrega_cep_origem" value="' . $this->getConfigValue($config, 'entrega', 'cep_origem', '') . '">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="entrega_calcular_automatico" value="1" ' . ($this->getConfigValue($config, 'entrega', 'calcular_automatico', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Calcular frete automaticamente</label>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">W-Express (Etiquetas)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="wexpress_enabled" name="entrega_wexpress_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="wexpress_enabled">Habilitar W-Express</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_wexpress_ambiente">
                                                            <option value="sandbox" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_ambiente', 'sandbox') === 'sandbox' ? 'selected' : '') . '>Sandbox</option>
                                                            <option value="production" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_ambiente', '') === 'production' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">A API do Swagger usa sandbox.wexpress.me</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_wexpress_api_key" value="' . $this->getConfigValue($config, 'entrega', 'wexpress_api_key', '') . '" placeholder="Cole a API Key da W-Express">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Solicite a chave por e-mail conforme a documentação da W-Express</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Service Code</label>
                                                <select class="form-select" name="entrega_wexpress_service_code">
                                                    <option value="wexpress_correios_std" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', 'wexpress_correios_std') === 'wexpress_correios_std' ? 'selected' : '') . '>wexpress_correios_std</option>
                                                    <option value="wexpress_correios_exp" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_correios_exp' ? 'selected' : '') . '>wexpress_correios_exp</option>
                                                    <option value="wexpress_correios_prime_express" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_correios_prime_express' ? 'selected' : '') . '>wexpress_correios_prime_express</option>
                                                    <option value="wexpress_premium" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_premium' ? 'selected' : '') . '>wexpress_premium</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Sender (JSON)</label>
                                                <textarea class="form-control" name="entrega_wexpress_sender_json" rows="6" placeholder="{\n  \"first_name\": \"Tim\", ... }">' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'wexpress_sender_json', ''), ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Dados do remetente (EUA). Pode colar o objeto sender do exemplo oficial da W-Express.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios (Etiquetas - Provider)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Provider de etiqueta</label>
                                                        <select class="form-select" name="entrega_correios_provider">
                                                            <option value="sigep" ' . ($this->getConfigValue($config, 'entrega', 'correios_provider', 'sigep') === 'sigep' ? 'selected' : '') . '>SIGEP (SOAP)</option>
                                                            <option value="prepostagem_v3" ' . ($this->getConfigValue($config, 'entrega', 'correios_provider', '') === 'prepostagem_v3' ? 'selected' : '') . '>Pré-Postagem v3 (REST)</option>
                                                        </select>
                                                        <small class="text-muted">Escolha como o sistema vai gerar etiquetas dos Correios (SIGEP legado ou Pré-Postagem v3).</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Token (Pré-Postagem v3)</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_correios_prepostagem_token" value="' . $this->getConfigValue($config, 'entrega', 'correios_prepostagem_token', '') . '" placeholder="Bearer token (Cartão de Postagem)">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                        <small class="text-muted">A API Pré-Postagem exige autorização via Cartão de Postagem.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">IdCorreios (opcional)</label>
                                                        <input type="text" class="form-control" name="entrega_correios_prepostagem_id_correios" value="' . $this->getConfigValue($config, 'entrega', 'correios_prepostagem_id_correios', '') . '" placeholder="IdCorreios">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Código do serviço (Pré-Postagem)</label>
                                                        <input type="text" class="form-control" name="entrega_correios_prepostagem_codigo_servico" value="' . $this->getConfigValue($config, 'entrega', 'correios_prepostagem_codigo_servico', '') . '" placeholder="Ex.: 03220">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Remetente (JSON - Pré-Postagem)</label>
                                                <textarea class="form-control" name="entrega_correios_prepostagem_sender_json" rows="6" placeholder="{\n  \"nome\": \"Fulano\",\n  \"cpfCnpj\": \"00000000000\",\n  \"endereco\": {\n    \"cep\": \"00000000\",\n    \"logradouro\": \"Rua\",\n    \"numero\": \"123\",\n    \"bairro\": \"Centro\",\n    \"cidade\": \"Cidade\",\n    \"uf\": \"SP\"\n  }\n}">' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_prepostagem_sender_json', ''), ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Estrutura compatível com o schema RemetenteDTO / EnderecoRemetenteDTO da API Pré-Postagem.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios (SIGEP Web)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="sigep_enabled" name="entrega_sigep_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'sigep_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="sigep_enabled">Habilitar SIGEP Web</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_sigep_ambiente">
                                                            <option value="homologacao" ' . ($this->getConfigValue($config, 'entrega', 'sigep_ambiente', 'homologacao') === 'homologacao' ? 'selected' : '') . '>Homologação</option>
                                                            <option value="producao" ' . ($this->getConfigValue($config, 'entrega', 'sigep_ambiente', '') === 'producao' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">Use Homologação até validar contrato/cartão e serviços.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Usuário</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_usuario" value="' . $this->getConfigValue($config, 'entrega', 'sigep_usuario', '') . '" placeholder="Usuário SIGEP">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Senha</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_sigep_senha" value="' . $this->getConfigValue($config, 'entrega', 'sigep_senha', '') . '" placeholder="Senha SIGEP">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Contrato</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_numero_contrato" value="' . $this->getConfigValue($config, 'entrega', 'sigep_numero_contrato', '') . '" placeholder="Número do contrato">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Cartão de Postagem</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_cartao_postagem" value="' . $this->getConfigValue($config, 'entrega', 'sigep_cartao_postagem', '') . '" placeholder="Cartão de postagem">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">CNPJ</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_cnpj" value="' . $this->getConfigValue($config, 'entrega', 'sigep_cnpj', '') . '" placeholder="CNPJ do contrato">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Serviço</label>
                                                        <select class="form-select" name="entrega_sigep_servico">
                                                            <option value="PAC" ' . ($this->getConfigValue($config, 'entrega', 'sigep_servico', 'PAC') === 'PAC' ? 'selected' : '') . '>PAC</option>
                                                            <option value="SEDEX" ' . ($this->getConfigValue($config, 'entrega', 'sigep_servico', '') === 'SEDEX' ? 'selected' : '') . '>SEDEX</option>
                                                        </select>
                                                        <small class="text-muted">Você pode ajustar quando tiver o contrato em mãos.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Código do Serviço no Contrato</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_servico_codigo" value="' . $this->getConfigValue($config, 'entrega', 'sigep_servico_codigo', '') . '" placeholder="Ex.: 04162 (depende do contrato)">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-outline-primary" onclick="testarSigepAPI()">
                                                    <i class="fas fa-plug"></i> Testar SIGEP
                                                </button>
                                                <small class="text-muted align-self-center">Executa um teste de solicita\u00E7\u00E3o de etiqueta via SIGEP e mostra o retorno.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios (Rastreamento)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="correios_tracking_enabled" name="entrega_correios_tracking_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'correios_tracking_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="correios_tracking_enabled">Habilitar rastreamento via API</label>
                                                        </div>
                                                        <small class="text-muted">Ative apenas quando tiver o token/API key e o endpoint.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Usuário (Meu Correios - Token)</label>
                                                        <input type="text" class="form-control" name="entrega_correios_token_usuario" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_token_usuario', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Usuário do Meu Correios">
                                                        <small class="text-muted">Usado para gerar automaticamente o token (Authorization: Basic). Se vazio, o sistema tenta reutilizar o usuário do SIGEP.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Senha / Código de acesso (Meu Correios - Token)</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_correios_token_senha" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_token_senha', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Senha/Código de acesso do Meu Correios">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                        <small class="text-muted">Muitas vezes esta credencial é diferente da senha do SIGEP. Necessária para auto-renovar token.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente (Token)</label>
                                                        <select class="form-select" name="entrega_correios_token_ambiente">
                                                            <option value="" ' . ($this->getConfigValue($config, 'entrega', 'correios_token_ambiente', '') === '' ? 'selected' : '') . '>Seguir SIGEP</option>
                                                            <option value="homologacao" ' . ($this->getConfigValue($config, 'entrega', 'correios_token_ambiente', '') === 'homologacao' ? 'selected' : '') . '>Homologação</option>
                                                            <option value="producao" ' . ($this->getConfigValue($config, 'entrega', 'correios_token_ambiente', '') === 'producao' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">Força onde o sistema vai gerar o token (api/apihom). Se vazio, segue o ambiente do SIGEP ou a Base URL do rastreio.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-8"></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Token / API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_correios_tracking_token" value="' . $this->getConfigValue($config, 'entrega', 'correios_tracking_token', '') . '" placeholder="Cole o token/API key">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">O sistema usa automaticamente o endpoint do Packet Service conforme o ambiente selecionado em SIGEP (Homologação/Produção).</small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente (CEP)</label>
                                                        <select class="form-select" name="entrega_correios_cep_ambiente">
                                                            <option value="" ' . ($this->getConfigValue($config, 'entrega', 'correios_cep_ambiente', '') === '' ? 'selected' : '') . '>Seguir SIGEP</option>
                                                            <option value="homologacao" ' . ($this->getConfigValue($config, 'entrega', 'correios_cep_ambiente', '') === 'homologacao' ? 'selected' : '') . '>Homologação</option>
                                                            <option value="producao" ' . ($this->getConfigValue($config, 'entrega', 'correios_cep_ambiente', '') === 'producao' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">Usado para consulta de CEP (Busca CEP). Se vazio, segue o ambiente do SIGEP.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="mb-3">
                                                        <label class="form-label">Base URL (CEP)</label>
                                                        <input type="text" class="form-control" name="entrega_correios_cep_base_url" value="' . $this->getConfigValue($config, 'entrega', 'correios_cep_base_url', '') . '" placeholder="https://api.correios.com.br/cep">
                                                        <small class="text-muted">Opcional. Se vazio, o sistema usa a URL padrão do ambiente selecionado.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Token (CEP - Busca CEP)</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_correios_cep_token" value="' . $this->getConfigValue($config, 'entrega', 'correios_cep_token', '') . '" placeholder="Bearer token (API Busca CEP)">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Se preenchido, este token será usado apenas para consulta de CEP. Se vazio, o sistema reutiliza o token do Rastreamento.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios Mundial (PACKET)</h6>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_correios_packet_ambiente">
                                                            <option value="homologacao" ' . ($this->getConfigValue($config, 'entrega', 'correios_packet_ambiente', 'homologacao') === 'homologacao' ? 'selected' : '') . '>Homologação</option>
                                                            <option value="producao" ' . ($this->getConfigValue($config, 'entrega', 'correios_packet_ambiente', '') === 'producao' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-8"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Login</label>
                                                        <input type="text" class="form-control" name="entrega_correios_packet_login" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_packet_login', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Login PACKET">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_correios_packet_password" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_packet_password', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Password PACKET">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Cartão de postagem</label>
                                                        <input type="text" class="form-control" name="entrega_correios_packet_cartao_postagem" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'correios_packet_cartao_postagem', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Ex.: 0076772055">
                                                        <small class="text-muted">Usado para autenticar e gerar o token do PACKET.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>

                                <h6 class="mb-3">ShipStation (UPS) - Exterior</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="shipstation_enabled" name="entrega_shipstation_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'shipstation_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="shipstation_enabled">Habilitar ShipStation (UPS)</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3"></div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_shipstation_api_key" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_api_key', '') . '" placeholder="Cole a API key da ShipStation">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">From Address (JSON)</label>
                                                <textarea class="form-control" name="entrega_shipstation_from_address_json" rows="6" placeholder="{\n  \"name\": \"Sender\", ... }">' . htmlspecialchars((trim((string) $this->getConfigValue($config, 'entrega', 'shipstation_from_address_json', '')) !== '' ? (string) $this->getConfigValue($config, 'entrega', 'shipstation_from_address_json', '') : '{"name":"Fabiana Bond","company_name":"Braziliana LLC","phone":"8432228518","email":"fabiana@brazilianashop.com","address_line1":"1227 W Broad St","address_line2":"","city_locality":"Saint Pauls","state_province":"NC","postal_code":"28384-9200","country_code":"US"}'), ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Endereço do remetente (EUA) no formato esperado pela ShipStation.</small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Carrier ID</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_carrier_id" value="' . (trim((string) $this->getConfigValue($config, 'entrega', 'shipstation_carrier_id', '')) !== '' ? $this->getConfigValue($config, 'entrega', 'shipstation_carrier_id', '') : 'se-4875726') . '" placeholder="Carrier ID (ShipStation)">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Carrier Code</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_carrier_code" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_carrier_code', 'ups') . '" placeholder="ups">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Service Code</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_service_code" value="' . (trim((string) $this->getConfigValue($config, 'entrega', 'shipstation_service_code', '')) !== '' ? $this->getConfigValue($config, 'entrega', 'shipstation_service_code', '') : 'ups_worldwide_saver') . '" placeholder="Service code">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Package Code</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_package_code" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_package_code', 'package') . '" placeholder="package">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Label Layout</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_label_layout" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_label_layout', '4x6') . '" placeholder="4x6">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Label Format</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_label_format" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_label_format', 'pdf') . '" placeholder="pdf">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Download Type</label>
                                                        <input type="text" class="form-control" name="entrega_shipstation_label_download_type" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_label_download_type', 'url') . '" placeholder="url">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Display Scheme</label>
                                                <input type="text" class="form-control" name="entrega_shipstation_display_scheme" value="' . $this->getConfigValue($config, 'entrega', 'shipstation_display_scheme', 'label') . '" placeholder="label">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações SEO -->
                                <div class="tab-pane fade" id="v-pills-seo" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações SEO</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Meta Title Padrão</label>
                                                <input type="text" class="form-control" name="seo_title" value="' . $this->getConfigValue($config, 'seo', 'title', 'Braziliana - Produtos de Qualidade') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meta Description Padrão</label>
                                                <textarea class="form-control" name="seo_description" rows="3">' . $this->getConfigValue($config, 'seo', 'description', 'Encontre os melhores produtos na Braziliana') . '</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Palavras-chave</label>
                                                <input type="text" class="form-control" name="seo_keywords" value="' . $this->getConfigValue($config, 'seo', 'keywords', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Google Analytics</label>
                                                <input type="text" class="form-control" name="google_analytics" placeholder="UA-XXXXXXXX-X" value="' . $this->getConfigValue($config, 'seo', 'google_analytics', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Google Tag Manager</label>
                                                <input type="text" class="form-control" name="google_tag_manager" placeholder="GTM-XXXXXXX" value="' . $this->getConfigValue($config, 'seo', 'google_tag_manager', '') . '">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sitemap_gerado" ' . ($this->getConfigValue($config, 'seo', 'sitemap_gerado', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Gerar Sitemap automaticamente</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configurações Assessoria / IA -->
                                <div class="tab-pane fade" id="v-pills-assessoria" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações da Assessoria (ScrapingBee + ChatGPT)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="mb-3">ScrapingBee</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">API Key</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="scrapingbee_api_key" value="' . $this->getConfigValue($config, 'scrapingbee', 'api_key', '') . '" placeholder="Cole a API Key do ScrapingBee">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <h6 class="mb-3">ChatGPT</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">API Key</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="chatgpt_api_key" value="' . $this->getConfigValue($config, 'chatgpt', 'api_key', '') . '" placeholder="Cole a API Key do ChatGPT">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Modelo</label>
                                                        <input type="text" class="form-control" name="chatgpt_model" value="' . $this->getConfigValue($config, 'chatgpt', 'model', 'gpt-3.5-turbo') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Temperature</label>
                                                        <input type="text" class="form-control" name="chatgpt_temperature" value="' . $this->getConfigValue($config, 'chatgpt', 'temperature', '0.1') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Max Tokens</label>
                                                        <input type="number" class="form-control" name="chatgpt_max_tokens" value="' . $this->getConfigValue($config, 'chatgpt', 'max_tokens', '1000') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Margem de peso (%%)</label>
                                                        <input type="number" class="form-control" name="chatgpt_peso_margem" value="' . $this->getConfigValue($config, 'chatgpt', 'peso_margem', '15') . '">
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h6 class="mb-3">Webhooks da Assessoria</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Início do processamento do orçamento (URL)</label>
                                                        <input type="url" class="form-control" name="assessoria_webhook_inicio_url" value="' . $this->getConfigValue($config, 'assessoria', 'webhook_inicio_url', '') . '" placeholder="https://seu-webhook.com/assessoria/inicio">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Conclusão do processamento do orçamento (URL)</label>
                                                        <input type="url" class="form-control" name="assessoria_webhook_conclusao_url" value="' . $this->getConfigValue($config, 'assessoria', 'webhook_conclusao_url', '') . '" placeholder="https://seu-webhook.com/assessoria/concluido">
                                                    </div>
                                                    <small class="text-muted">O sistema enviará POST em JSON com dados do usuário e do orçamento quando o processamento iniciar e quando finalizar.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações de Pagamentos -->
                                <div class="tab-pane fade" id="v-pills-pagamentos" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Pagamentos</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">🇧🇷 Asaas</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="asaas_enabled" name="pagamentos_asaas_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="asaas_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Ambiente</label>
                                                                <select class="form-select" name="pagamentos_asaas_ambiente">
                                                                    <option value="sandbox" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', 'sandbox') === 'sandbox' ? 'selected' : '') . '>Sandbox (Testes)</option>
                                                                    <option value="production" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', '') === 'production' ? 'selected' : '') . '>Produção</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">API Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_asaas_api_key" value="' . $this->getConfigValue($config, 'pagamentos', 'asaas_api_key', '') . '" placeholder="Sua API Key do Asaas">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">API Key obtida no painel do Asaas</small>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-outline-primary" onclick="testarAsaasAPI()">
                                                                    <i class="fas fa-plug"></i> Testar Conexão
                                                                </button>
                                                                <button type="button" class="btn btn-outline-info" onclick="verDocumentacaoAsaas()">
                                                                    <i class="fas fa-book"></i> Documentação
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">💳 Stripe</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="stripe_enabled" name="pagamentos_stripe_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="stripe_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Ambiente</label>
                                                                <select class="form-select" name="pagamentos_stripe_ambiente">
                                                                    <option value="test" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', 'test') === 'test' ? 'selected' : '') . '>Test (Chaves de Teste)</option>
                                                                    <option value="live" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', '') === 'live' ? 'selected' : '') . '>Live (Chaves de Produção)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Publishable Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_publishable_key" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_publishable_key', '') . '" placeholder="pk_test_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Chave pública (pk_test_... ou pk_live_...)</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Secret Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_secret_key" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_secret_key', '') . '" placeholder="sk_test_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Chave secreta (sk_test_... ou sk_live_...)</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Webhook Signing Secret</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_webhook_secret" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_webhook_secret', '') . '" placeholder="whsec_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Signing secret do endpoint de webhook (whsec_...). Necessário para validar o Stripe-Signature.</small>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-outline-primary" onclick="testarStripeAPI()">
                                                                    <i class="fas fa-plug"></i> Testar Conexão
                                                                </button>
                                                                <button type="button" class="btn btn-outline-info" onclick="verDocumentacaoStripe()">
                                                                    <i class="fas fa-book"></i> Documentação
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">💱 Câmbio Real</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="cambioreal_enabled" name="pagamentos_cambioreal_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'cambioreal_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="cambioreal_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Base URL</label>
                                                                <input type="text" class="form-control" name="pagamentos_cambioreal_base_url" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_base_url', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="https://sandbox.cambioreal.com">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">APP ID</label>
                                                                <input type="text" class="form-control" name="pagamentos_cambioreal_app_id" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_app_id', ''), ENT_QUOTES, 'UTF-8') . '">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">APP Public</label>
                                                                <input type="text" class="form-control" name="pagamentos_cambioreal_app_public" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_app_public', ''), ENT_QUOTES, 'UTF-8') . '">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">APP Secret</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_cambioreal_app_secret" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_app_secret', ''), ENT_QUOTES, 'UTF-8') . '">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">Webhook URL</label>
                                                                <input type="text" class="form-control" value="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/webhook/cambioreal', ENT_QUOTES, 'UTF-8') . '" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>
                                            
                                            <hr>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card border-info">
                                                        <div class="card-header bg-info bg-opacity-10">
                                                            <h6 class="mb-0">💱 Câmbio Real Taxas <small class="text-muted">(taxa de serviço e impostos)</small></h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">APP ID</label>
                                                                <input type="text" class="form-control" name="pagamentos_cambioreal_taxas_app_id" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_taxas_app_id', ''), ENT_QUOTES, 'UTF-8') . '">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">APP Public</label>
                                                                <input type="text" class="form-control" name="pagamentos_cambioreal_taxas_app_public" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_taxas_app_public', ''), ENT_QUOTES, 'UTF-8') . '">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">APP Secret</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_cambioreal_taxas_app_secret" value="' . htmlspecialchars($this->getConfigValue($config, 'pagamentos', 'cambioreal_taxas_app_secret', ''), ENT_QUOTES, 'UTF-8') . '">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">Webhook URL</label>
                                                                <input type="text" class="form-control" value="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/webhook/cambioreal-taxas', ENT_QUOTES, 'UTF-8') . '" readonly>
                                                                <div class="form-text">Configure esta URL no painel da Câmbio Real Taxas antes de criar a APIKey.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">🇧🇷 AppMax</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="appmax_enabled" name="pagamentos_appmax_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="appmax_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client ID</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_client_id" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_client_id', '') . '" placeholder="CLIENT_ID">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client Secret</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_client_secret" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_client_secret', '') . '" placeholder="CLIENT_SECRET">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">App ID</label>
                                                                        <input type="text" class="form-control" name="pagamentos_appmax_app_id" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_app_id', '') . '" placeholder="APP_ID">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Access Token</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_access_token" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_access_token', '') . '" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Ambiente</label>
                                                                        <select class="form-select" name="pagamentos_appmax_ambiente">
                                                                            <option value="production" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_ambiente', 'production') === 'production' ? 'selected' : '') . '>Produção</option>
                                                                            <option value="homolog" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_ambiente', 'production') === 'homolog' ? 'selected' : '') . '>Homologação</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Base URL (opcional)</label>
                                                                        <input type="url" class="form-control" name="pagamentos_appmax_base_url" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_base_url', '') . '" placeholder="https://admin.appmax.com.br/api/v3">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Webhook URL</label>
                                                                        <input type="text" class="form-control" value="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/webhook/appmax', ENT_QUOTES, 'UTF-8') . '" readonly>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">💙 Mercado Pago</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="mercadopago_enabled" name="pagamentos_mercadopago_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'mercadopago_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="mercadopago_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Access Token</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_mercadopago_access_token" value="' . $this->getConfigValue($config, 'pagamentos', 'mercadopago_access_token', '') . '" placeholder="APP_USR-...">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                        <small class="text-muted">Use o Access Token da conta/app do Mercado Pago.</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Public Key (opcional)</label>
                                                                        <input type="text" class="form-control" name="pagamentos_mercadopago_public_key" value="' . $this->getConfigValue($config, 'pagamentos', 'mercadopago_public_key', '') . '" placeholder="APP_USR-...">
                                                                        <small class="text-muted">Obrigatória apenas se você for usar SDK/JS do MP no frontend.</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client ID (OAuth)</label>
                                                                        <input type="text" class="form-control" name="pagamentos_mercadopago_client_id" value="' . $this->getConfigValue($config, 'pagamentos', 'mercadopago_client_id', '') . '" placeholder="1234567890">
                                                                        <small class="text-muted">Obrigatório para Marketplace Split (OAuth do vendedor).</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client Secret (OAuth)</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_mercadopago_client_secret" value="' . $this->getConfigValue($config, 'pagamentos', 'mercadopago_client_secret', '') . '" placeholder="********">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                        <small class="text-muted">Obrigatório para Marketplace Split (OAuth do vendedor).</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Webhook URL</label>
                                                                        <input type="text" class="form-control" value="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/webhook/mercadopago', ENT_QUOTES, 'UTF-8') . '" readonly>
                                                                        <small class="text-muted">Configure esta URL no painel do Mercado Pago para receber notificações.</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                                                        <a href="/mercadopago/oauth/start" class="btn btn-sm btn-primary">Conectar Mercado Pago (Conta do Produto)</a>
                                                                        <small class="text-muted">Faça login e autorize a conta que vai receber o valor do produto (OAuth).</small>
                                                                        ' . (!empty($this->getConfigValue($config, 'pagamentos', 'mercadopago_seller_access_token', ''))
                                                                            ? '<span class="badge bg-success">Conectado</span>'
                                                                            : '<span class="badge bg-secondary">Não conectado</span>') . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <h6 class="mb-3">Webhook - Pedido Manual</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Link de Pagamento do Pedido Manual (URL)</label>
                                                        <input type="url" class="form-control" name="pagamentos_webhook_link_pagamento_pedido_manual_url" value="' . $this->getConfigValue($config, 'pagamentos', 'webhook_link_pagamento_pedido_manual_url', '') . '" placeholder="https://seu-webhook.com/pedidos/manual/link-pagamento">
                                                        <small class="text-muted">O sistema enviará POST em JSON com dados do pedido, cliente e link de pagamento assim que o link for gerado.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <h6 class="mb-3">Desconto no PIX</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Desconto na taxa de serviço para PIX (%)</label>
                                                        <input type="number" class="form-control" name="pagamentos_pix_desconto_taxa_servico_percent" value="' . $this->getConfigValue($config, 'pagamentos', 'pix_desconto_taxa_servico_percent', '0') . '" step="0.01" min="0" max="100">
                                                        <small class="text-muted">Aplicado ao calcular a taxa de serviço quando a forma de pagamento selecionada for PIX.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card border-success mb-3">
                                                        <div class="card-header bg-success bg-opacity-10">
                                                            <h6 class="mb-0"><i class="fas fa-tags me-1"></i> Desconto Promocional na Taxa de Serviço (Compra Orgânica)</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="alert alert-info small mb-3">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                Este desconto é aplicado <strong>somente sobre a taxa de serviço</strong> em compras orgânicas reais.
                                                                Não se aplica a redirecionamentos, vendas manuais ou compras feitas pelo admin em nome do cliente.
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="promocao_taxa_servico_ativo" name="promocao_taxa_servico_ativo" value="1" ' . ($this->getConfigValue($config, 'promocao', 'taxa_servico_ativo', '0') === '1' ? 'checked' : '') . '>
                                                                    <label class="form-check-label" for="promocao_taxa_servico_ativo">
                                                                        <strong>Ativar desconto na taxa de serviço</strong>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Tipo de desconto</label>
                                                                        <select class="form-select" name="promocao_taxa_servico_tipo" id="promocao_taxa_servico_tipo">
                                                                            <option value="percentual" ' . ($this->getConfigValue($config, 'promocao', 'taxa_servico_tipo', 'percentual') === 'percentual' ? 'selected' : '') . '>Porcentagem (%)</option>
                                                                            <option value="fixo" ' . ($this->getConfigValue($config, 'promocao', 'taxa_servico_tipo', 'percentual') === 'fixo' ? 'selected' : '') . '>Valor fixo (USD)</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Valor do desconto</label>
                                                                        <input type="number" class="form-control" name="promocao_taxa_servico_valor" id="promocao_taxa_servico_valor" value="' . $this->getConfigValue($config, 'promocao', 'taxa_servico_valor', '0') . '" step="0.01" min="0">
                                                                        <small class="text-muted" id="promocao_taxa_servico_hint">Ex: 10 = 10% de desconto na taxa de serviço</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <script>
                                                            document.addEventListener("DOMContentLoaded", function() {
                                                                var tipoSel = document.getElementById("promocao_taxa_servico_tipo");
                                                                var hint = document.getElementById("promocao_taxa_servico_hint");
                                                                if (tipoSel && hint) {
                                                                    function updateHint() {
                                                                        if (tipoSel.value === "fixo") {
                                                                            hint.textContent = "Ex: 8 = US$ 8.00 de desconto na taxa de serviço";
                                                                        } else {
                                                                            hint.textContent = "Ex: 10 = 10% de desconto na taxa de serviço";
                                                                        }
                                                                    }
                                                                    tipoSel.addEventListener("change", updateHint);
                                                                    updateHint();
                                                                }
                                                            });
                                                            </script>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">👑 Clube Brasiliana</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-4">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Cashback (%)</label>
                                                                        <input type="number" step="0.01" min="0" class="form-control" name="clube_cashback_percent" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'cashback_percent', '0'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <small class="text-muted">Percentual de cashback em créditos internos (apenas produtos com Clube Ativo).</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Rendimento Normal (%)</label>
                                                                        <input type="number" step="0.01" min="0" class="form-control" name="clube_rendimento_percent" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'rendimento_percent', '0'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <small class="text-muted">Percentual de créditos internos gerados periodicamente para o Clube Normal.</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Rendimento Turbo (%)</label>
                                                                        <input type="number" step="0.01" min="0" class="form-control" name="clube_rendimento_turbo_percent" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'rendimento_turbo_percent', '2'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <small class="text-muted">Percentual de rendimento para recargas Turbo (permanência mínima de 6 meses).</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">Intervalo do rendimento</label>
                                                                    <div class="input-group mb-3">
                                                                        <input type="number" min="1" step="1" class="form-control" name="clube_rendimento_intervalo_valor" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'rendimento_intervalo_valor', '30'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <select class="form-select" name="clube_rendimento_intervalo_unidade">
                                                                            ';

        $unit = (string) $this->getConfigValue($config, 'clube', 'rendimento_intervalo_unidade', 'dia');
        $unit = strtolower(trim($unit));
        if (!in_array($unit, ['minuto', 'hora', 'dia', 'mes'], true)) {
            $unit = 'dia';
        }

        echo '                                                                <option value="minuto" ' . ($unit === 'minuto' ? 'selected' : '') . '>Minuto(s)</option>
                                                                            <option value="hora" ' . ($unit === 'hora' ? 'selected' : '') . '>Hora(s)</option>
                                                                            <option value="dia" ' . ($unit === 'dia' ? 'selected' : '') . '>Dia(s)</option>
                                                                            <option value="mes" ' . ($unit === 'mes' ? 'selected' : '') . '>Mês(es)</option>
                                                                        </select>
                                                                    </div>
                                                                    <small class="text-muted">Configura a periodicidade do crédito por permanência.</small>
                                                                </div>
                                                            </div>

                                                            <div class="row mt-2">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Cron Secret (Rendimento)</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="clube_cron_secret" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'cron_secret', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Token para /cron/clube/rendimento">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                        <small class="text-muted">Usado para proteger o endpoint <code>/cron/clube/rendimento?token=...</code>.</small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row mt-2">
                                                                <div class="col-12">
                                                                    <div class="border rounded p-3 bg-light">
                                                                        <div class="fw-semibold mb-2">Faixas de desconto progressivo (peso total de produtos com Clube Ativo)</div>
                                                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                                                            <div class="text-muted small">Para cadastrar uma nova faixa: preencha a linha <strong>Nova</strong> abaixo (o <strong>Peso mín</strong> pode ser <strong>0</strong>) e clique em <strong>Salvar Configurações</strong>.</div>
                                                                            <button type="button" class="btn btn-sm btn-primary" onclick="try{addClubeFaixaNova();}catch(e){}">Adicionar faixa</button>
                                                                        </div>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th style="width:80px;">Ativo</th>
                                                                                        <th style="width:120px;">Ordem</th>
                                                                                        <th style="width:180px;">Peso mín (kg)</th>
                                                                                        <th style="width:180px;">Peso máx (kg)</th>
                                                                                        <th style="width:180px;">Desconto (%)</th>
                                                                                        <th style="width:120px;">Remover</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>';

        if (empty($clubeFaixas)) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhuma faixa cadastrada</td></tr>';
        } else {
            foreach ($clubeFaixas as $fx) {
                $idFx = (int) ($fx['id'] ?? 0);
                $ativoFx = (int) ($fx['ativo'] ?? 0);
                $ordFx = (int) ($fx['ordem'] ?? 0);
                $minFx = (string) ($fx['peso_min_kg'] ?? '0');
                $maxFx = (string) ($fx['peso_max_kg'] ?? '0');
                $pctFx = (string) ($fx['percentual_desconto'] ?? '0');

                echo '<tr>'
                    . '<td>'
                    . '<input type="hidden" name="clube_faixas[' . $idFx . '][id]" value="' . $idFx . '">'
                    . '<input type="hidden" name="clube_faixas[' . $idFx . '][ativo]" value="0">'
                    . '<input class="form-check-input" type="checkbox" name="clube_faixas[' . $idFx . '][ativo]" value="1" ' . ($ativoFx ? 'checked' : '') . '>'
                    . '</td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][ordem]" value="' . htmlspecialchars((string) $ordFx, ENT_QUOTES, 'UTF-8') . '" step="1"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][peso_min_kg]" value="' . htmlspecialchars((string) $minFx, ENT_QUOTES, 'UTF-8') . '" step="0.001" min="0"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][peso_max_kg]" value="' . htmlspecialchars((string) $maxFx, ENT_QUOTES, 'UTF-8') . '" step="0.001" min="0"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][percentual_desconto]" value="' . htmlspecialchars((string) $pctFx, ENT_QUOTES, 'UTF-8') . '" step="0.01" min="0"></td>'
                    . '<td class="text-center"><input class="form-check-input" type="checkbox" name="clube_faixas_remover[]" value="' . $idFx . '"></td>'
                    . '</tr>';
            }
        }

        echo '                                                                <tr>'
                                                                                    . '<td>'
                                                                                    . '<input type="hidden" name="clube_faixa_nova[ativo]" value="0">'
                                                                                    . '<input class="form-check-input" type="checkbox" name="clube_faixa_nova[ativo]" value="1" checked>'
                                                                                    . '</td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[ordem]" value="0" step="1"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[peso_min_kg]" value="0" step="0.001" min="0"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[peso_max_kg]" value="0" step="0.001" min="0"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[percentual_desconto]" value="0" step="0.01" min="0"></td>'
                                                                                    . '<td class="text-muted small">Nova</td>'
                                                                                    . '</tr>';

        echo '                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        <div class="text-muted small mt-2">O desconto progressivo será calculado somente com base no peso total dos produtos com Clube Ativo.</div>
                                                                        <script>
                                                                        (function(){
                                                                            function getFirstByName(n){
                                                                                try{var els=document.getElementsByName(n); return (els&&els[0])?els[0]:null;}catch(e){return null;}
                                                                            }
                                                                            window.addClubeFaixaNova = function(){
                                                                                var ativoEl = getFirstByName("clube_faixa_nova[ativo]");
                                                                                var ordemEl = getFirstByName("clube_faixa_nova[ordem]");
                                                                                var minEl = getFirstByName("clube_faixa_nova[peso_min_kg]");
                                                                                var maxEl = getFirstByName("clube_faixa_nova[peso_max_kg]");
                                                                                var pctEl = getFirstByName("clube_faixa_nova[percentual_desconto]");
                                                                                if(!ordemEl||!minEl||!maxEl||!pctEl){return;}

                                                                                var ativo = (ativoEl && ativoEl.checked) ? 1 : 0;
                                                                                var ordem = parseInt((ordemEl.value||"0"),10); if(isNaN(ordem)) ordem = 0;
                                                                                var min = parseFloat((minEl.value||"0").toString().replace(",",".")); if(isNaN(min)) min = 0;
                                                                                var max = parseFloat((maxEl.value||"0").toString().replace(",",".")); if(isNaN(max)) max = 0;
                                                                                var pct = parseFloat((pctEl.value||"0").toString().replace(",",".")); if(isNaN(pct)) pct = 0;

                                                                                if(!(min>0 || max>0 || pct>0)){
                                                                                    pctEl.focus();
                                                                                    return;
                                                                                }

                                                                                var table = pctEl.closest("table");
                                                                                if(!table){return;}
                                                                                var tbody = table.querySelector("tbody");
                                                                                if(!tbody){return;}

                                                                                var idx = String(Date.now()) + String(Math.floor(Math.random()*1000));
                                                                                var tr = document.createElement("tr");
                                                                                tr.setAttribute("data-clube-nova", idx);
                                                                                tr.innerHTML = `
                                                                                    <td>
                                                                                        <input type="hidden" name="clube_faixas_novas[${idx}][ativo]" value="0">
                                                                                        <input class="form-check-input" type="checkbox" name="clube_faixas_novas[${idx}][ativo]" value="1" ${ativo ? "checked" : ""}>
                                                                                    </td>
                                                                                    <td><input type="number" class="form-control form-control-sm" name="clube_faixas_novas[${idx}][ordem]" value="${ordem}" step="1"></td>
                                                                                    <td><input type="number" class="form-control form-control-sm" name="clube_faixas_novas[${idx}][peso_min_kg]" value="${min}" step="0.001" min="0"></td>
                                                                                    <td><input type="number" class="form-control form-control-sm" name="clube_faixas_novas[${idx}][peso_max_kg]" value="${max}" step="0.001" min="0"></td>
                                                                                    <td><input type="number" class="form-control form-control-sm" name="clube_faixas_novas[${idx}][percentual_desconto]" value="${pct}" step="0.01" min="0"></td>
                                                                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="try{this.closest(\"tr\").remove();}catch(e){}">Remover</button></td>
                                                                                `;

                                                                                var novaRow = getFirstByName("clube_faixa_nova[peso_min_kg]");
                                                                                var trNova = null;
                                                                                if(novaRow){
                                                                                    trNova = novaRow.closest("tr");
                                                                                }
                                                                                if(trNova && trNova.parentNode === tbody){
                                                                                    tbody.insertBefore(tr, trNova);
                                                                                } else {
                                                                                    tbody.appendChild(tr);
                                                                                }

                                                                                if(ativoEl){ativoEl.checked = true;}
                                                                                ordemEl.value = "0";
                                                                                minEl.value = "0";
                                                                                maxEl.value = "0";
                                                                                pctEl.value = "0";
                                                                                pctEl.focus();
                                                                            };
                                                                        })();
                                                                        </script>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Status dos Gateways -->
                                            <div class="row mt-4">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📊 Status dos Gateways de Pagamento</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="d-flex align-items-center mb-3">
                                                                        <div class="me-3">
                                                                            <i class="fas fa-circle text-' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'success' : 'secondary') . '"></i>
                                                                            <strong>Asaas:</strong>
                                                                        </div>
                                                                        <span class="badge bg-' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'success' : 'secondary') . '">
                                                                            ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'Ativo' : 'Inativo') . '
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-muted small">
                                                                        Ambiente: ' . ucfirst($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', 'sandbox')) . ' | 
                                                                        API Key: ' . (empty($this->getConfigValue($config, 'pagamentos', 'asaas_api_key', '')) ? 'Não configurada' : 'Configurada') . '
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="d-flex align-items-center mb-3">
                                                                        <div class="me-3">
                                                                            <i class="fas fa-circle text-' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'success' : 'secondary') . '"></i>
                                                                            <strong>Stripe:</strong>
                                                                        </div>
                                                                        <span class="badge bg-' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'success' : 'secondary') . '">
                                                                            ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'Ativo' : 'Inativo') . '
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-muted small">
                                                                        Ambiente: ' . ucfirst($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', 'test')) . ' | 
                                                                        Keys: ' . (empty($this->getConfigValue($config, 'pagamentos', 'stripe_publishable_key', '')) ? 'Não configuradas' : 'Configuradas') . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="v-pills-comissoes" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Comissões</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label class="form-label">Comissão de processamento (Online) %</label>
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="comissao_processamento_percent" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'processamento_percent', $this->getConfigValue($config, 'comissao', 'comissao_processamento_percent', '0')), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Percentual aplicado sobre o valor líquido (total - impostos - custo do produto) ao finalizar compras online.</small>
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label class="form-label">Início da 1ª janela</label>
                                                    <input type="date" class="form-control" name="comissao_janela_primeiro_inicio" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_primeiro_inicio', ''), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Defina a data de início da primeira janela global.</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Fim da 1ª janela</label>
                                                    <input type="date" class="form-control" name="comissao_janela_primeiro_fim" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_primeiro_fim', ''), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Defina a data de término da primeira janela global.</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Duração das janelas (dias)</label>
                                                    <input type="number" min="1" step="1" class="form-control" name="comissao_janela_duracao_dias" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_duracao_dias', '14'), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Após a 1ª janela, as próximas são calculadas automaticamente.</small>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Faixas de comissão (Pedidos Manuais)</label>
                                                <input type="hidden" name="comissao_manual_faixas" id="comissao_manual_faixas" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'manual_faixas', '[{"min":0,"max":999999999,"percent":0}]'), ENT_QUOTES, 'UTF-8') . '">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle" id="comissaoManualFaixasTable">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 30%">Mínimo (R$)</th>
                                                                <th style="width: 30%">Máximo (R$)</th>
                                                                <th style="width: 25%">Comissão (%)</th>
                                                                <th style="width: 15%">Ações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="comissaoManualFaixasBody"></tbody>
                                                    </table>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComissaoFaixa">
                                                    <i class="fas fa-plus"></i> Adicionar faixa
                                                </button>
                                                <small class="text-muted d-block mt-2">O faturamento usado é a soma do total faturado de pedidos manuais pagos.</small>
                                            </div>

                                            ' . $repComissoesHtml . '
                                        </div>
                                    </div>
                                </div>

                                ' . $mapaCalorTabHtml . '
                                
                                <!-- Configurações do Sistema -->
                                <div class="tab-pane fade" id="v-pills-sistema" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações do Sistema</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Fuso Horário</label>
                                                <select class="form-select" name="timezone">
                                                    <option value="America/Sao_Paulo" ' . ($this->getConfigValue($config, 'sistema', 'timezone', 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '') . '>America/São Paulo</option>
                                                    <option value="America/New_York" ' . ($this->getConfigValue($config, 'sistema', 'timezone', '') === 'America/New_York' ? 'selected' : '') . '>America/New York</option>
                                                    <option value="Europe/London" ' . ($this->getConfigValue($config, 'sistema', 'timezone', '') === 'Europe/London' ? 'selected' : '') . '>Europe/London</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Idioma Padrão</label>
                                                <select class="form-select" name="idioma">
                                                    <option value="pt-BR" ' . ($this->getConfigValue($config, 'sistema', 'idioma', 'pt-BR') === 'pt-BR' ? 'selected' : '') . '>Português (Brasil)</option>
                                                    <option value="en-US" ' . ($this->getConfigValue($config, 'sistema', 'idioma', '') === 'en-US' ? 'selected' : '') . '>English (US)</option>
                                                    <option value="es-ES" ' . ($this->getConfigValue($config, 'sistema', 'idioma', '') === 'es-ES' ? 'selected' : '') . '>Español</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Moeda Padrão</label>
                                                <select class="form-select" name="moeda">
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'sistema', 'moeda', 'USD') === 'USD' ? 'selected' : '') . '>Dólar (USD)</option>
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'BRL' ? 'selected' : '') . '>Real (BRL)</option>
                                                    <option value="EUR" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'EUR' ? 'selected' : '') . '>Euro (EUR)</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Taxa de conversão USD → BRL</label>
                                                <input type="number" step="0.0001" min="0" class="form-control" name="sistema_usd_brl_rate" value="' . htmlspecialchars($this->getConfigValue($config, 'sistema', 'usd_brl_rate', '5.85'), ENT_QUOTES, 'UTF-8') . '">
                                                <small class="text-muted">Taxa usada no conversor global e para cálculos auxiliares em BRL quando necessário.</small>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="manutencao" ' . ($this->getConfigValue($config, 'sistema', 'manutencao', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Modo Manutenção</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="debug" ' . ($this->getConfigValue($config, 'sistema', 'debug', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Modo Debug</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="cache_ativado" ' . ($this->getConfigValue($config, 'sistema', 'cache_ativado', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Cache Ativado</label>
                                            </div>

                                            <hr class="my-4">

                                            <div class="border rounded p-3 bg-light">
                                                <div class="fw-semibold mb-2"><i class="fas fa-comment-dots me-1"></i>Pop-up de Boas-vindas</div>
                                                <div class="text-muted small mb-3">Exibe um pop-up de boas-vindas na primeira visita do usuário ao site.</div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="sistema_welcome_popup_enabled" value="1" ' . ($this->getConfigValue($config, 'sistema', 'welcome_popup_enabled', '1') === '1' ? 'checked' : '') . '>
                                                    <label class="form-check-label">Ativar pop-up de boas-vindas</label>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <div class="border rounded p-3 bg-light">
                                                <div class="fw-semibold mb-2"><i class="fas fa-lock me-1"></i>Bloqueio do Site (Site Lock)</div>

                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="sistema_site_lock_enabled" value="1" ' . ($this->getConfigValue($config, 'sistema', 'site_lock_enabled', '0') === '1' ? 'checked' : '') . '>
                                                    <label class="form-check-label">Ativar senha no site</label>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Modo de bloqueio</label>
                                                    <select class="form-select" name="sistema_site_lock_mode" id="siteLockMode">
                                                        <option value="total" ' . ($this->getConfigValue($config, 'sistema', 'site_lock_mode', 'total') === 'total' ? 'selected' : '') . '>Bloquear todo o site</option>
                                                        <option value="parcial" ' . ($this->getConfigValue($config, 'sistema', 'site_lock_mode', 'total') === 'parcial' ? 'selected' : '') . '>Bloquear somente páginas específicas</option>
                                                    </select>
                                                    <small class="text-muted">Total: exige senha pra acessar qualquer página. Parcial: só bloqueia as rotas listadas abaixo.</small>
                                                </div>

                                                <div class="mb-3" id="siteLockBlockedPathsWrapper" style="' . ($this->getConfigValue($config, 'sistema', 'site_lock_mode', 'total') === 'parcial' ? '' : 'display:none') . '">
                                                    <label class="form-label">Páginas bloqueadas</label>
                                                    <input type="text" class="form-control" name="sistema_site_lock_blocked_paths" value="' . htmlspecialchars($this->getConfigValue($config, 'sistema', 'site_lock_blocked_paths', '/assessoria,/status-pedido'), ENT_QUOTES, 'UTF-8') . '" placeholder="/assessoria,/status-pedido">
                                                    <small class="text-muted">Rotas separadas por vírgula. Ex: <code>/assessoria,/status-pedido,/redirecionamento</code></small>
                                                </div>

                                                <script>
                                                document.getElementById("siteLockMode").addEventListener("change", function(){
                                                    document.getElementById("siteLockBlockedPathsWrapper").style.display = this.value === "parcial" ? "" : "none";
                                                });
                                                </script>

                                                <div class="mb-0">
                                                    <label class="form-label">Senha do site</label>
                                                    <input type="password" class="form-control" name="sistema_site_lock_password" value="' . htmlspecialchars($this->getConfigValue($config, 'sistema', 'site_lock_password', ''), ENT_QUOTES, 'UTF-8') . '" placeholder="********">
                                                    <small class="text-muted">Não use a mesma senha do admin. Quem digitar essa senha uma vez fica liberado na sessão.</small>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <div class="border rounded p-3 bg-light">
                                                <div class="fw-semibold mb-2"><i class="fas fa-file-import me-1"></i>Importação de Usuários (CSV)</div>
                                                <div class="text-muted small mb-3">Baixe o modelo, preencha e importe. O endereço usa prioridade <strong>Billing</strong> e, se não houver, usa <strong>Shipping</strong>.</div>

                                                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                                    <a class="btn btn-outline-primary btn-sm" href="/admin/configuracoes/importar-usuarios/modelo" target="_blank">
                                                        <i class="fas fa-download me-1"></i>Baixar modelo CSV
                                                    </a>
                                                </div>

                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-8">
                                                        <label class="form-label mb-1">Arquivo CSV</label>
                                                        <input type="file" class="form-control" name="usuarios_import_csv" id="usuarios_import_csv" accept=".csv,text/csv">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="button" class="btn btn-primary w-100" id="btnImportarUsuariosCsv">
                                                            <i class="fas fa-upload me-1"></i>Importar Usuários
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="mt-3" id="usuariosImportProgressWrap" style="display:none;">
                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span id="usuariosImportProgressLabel">Preparando...</span>
                                                        <span id="usuariosImportProgressPercent">0%</span>
                                                    </div>
                                                    <div class="progress" style="height: 18px;">
                                                        <div class="progress-bar" role="progressbar" style="width:0%" id="usuariosImportProgressBar">0%</div>
                                                    </div>
                                                    <div class="small text-muted mt-2" id="usuariosImportProgressStats"></div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 bg-light mt-3">
                                                <div class="fw-semibold mb-2"><i class="fas fa-file-import me-1"></i>Importação de Pedidos (CSV)</div>
                                                <div class="text-muted small mb-3">Baixe o modelo, preencha e importe. As colunas podem estar em qualquer ordem (com header).</div>

                                                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                                    <a class="btn btn-outline-primary btn-sm" href="/admin/pedidos/importar/modelo" target="_blank">
                                                        <i class="fas fa-download me-1"></i>Baixar modelo CSV
                                                    </a>
                                                </div>

                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-8">
                                                        <label class="form-label mb-1">Arquivo CSV</label>
                                                        <input type="file" class="form-control" name="pedidos_import_csv" id="pedidos_import_csv" accept=".csv,text/csv">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="button" class="btn btn-primary w-100" id="btnImportarPedidosCsv">
                                                            <i class="fas fa-upload me-1"></i>Importar Pedidos
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="mt-3" id="pedidosImportProgressWrap" style="display:none;">
                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span id="pedidosImportProgressLabel">Preparando...</span>
                                                        <span id="pedidosImportProgressPercent">0%</span>
                                                    </div>
                                                    <div class="progress" style="height: 18px;">
                                                        <div class="progress-bar" role="progressbar" style="width:0%" id="pedidosImportProgressBar">0%</div>
                                                    </div>
                                                    <div class="small text-muted mt-2" id="pedidosImportProgressStats"></div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 bg-light mt-3">
                                                <div class="fw-semibold mb-2"><i class="fas fa-file-import me-1"></i>Importação de Produtos (CSV)</div>
                                                <div class="text-muted small mb-3">Baixe o modelo, preencha e importe. As colunas podem estar em qualquer ordem (com header).</div>

                                                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                                    <a class="btn btn-outline-primary btn-sm" href="/admin/produtos/importar/modelo" target="_blank">
                                                        <i class="fas fa-download me-1"></i>Baixar modelo CSV
                                                    </a>
                                                </div>

                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-8">
                                                        <label class="form-label mb-1">Arquivo CSV</label>
                                                        <input type="file" class="form-control" name="produtos_import_csv" id="produtos_import_csv" accept=".csv,text/csv">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="button" class="btn btn-primary w-100" id="btnImportarProdutosCsv">
                                                            <i class="fas fa-upload me-1"></i>Importar Produtos
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="mt-3" id="produtosImportProgressWrap" style="display:none;">
                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span id="produtosImportProgressLabel">Preparando...</span>
                                                        <span id="produtosImportProgressPercent">0%</span>
                                                    </div>
                                                    <div class="progress" style="height: 18px;">
                                                        <div class="progress-bar" role="progressbar" style="width:0%" id="produtosImportProgressBar">0%</div>
                                                    </div>
                                                    <div class="small text-muted mt-2" id="produtosImportProgressStats"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configurações de Demandas (TI) -->
                                <div class="tab-pane fade" id="v-pills-demandas" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Demandas (TI)</h5>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="fw-bold small mb-3"><i class="fas fa-lock me-2"></i>Acesso ao Painel</h6>
                                            <div class="mb-3">
                                                <label class="form-label">Senha do Painel de Demandas</label>
                                                <input type="text" class="form-control" name="demandas_senha_painel" value="' . htmlspecialchars($demandasConfig['demandas_senha_painel'], ENT_QUOTES, 'UTF-8') . '" placeholder="Deixe vazio para desativar">
                                                <small class="text-muted">Se preenchida, será exigida ao acessar o painel de demandas.</small>
                                            </div>

                                            <hr>
                                            <h6 class="fw-bold small mb-3"><i class="fas fa-envelope me-2"></i>Notificações por Email</h6>
                                            <div class="mb-3">
                                                <label class="form-label">Emails que recebem novas solicitações</label>
                                                <textarea class="form-control" name="demandas_emails_notificacao" rows="3" placeholder="email1@exemplo.com, email2@exemplo.com">' . htmlspecialchars($demandasConfig['demandas_emails_notificacao'], ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Separados por vírgula. Toda nova demanda (bug ou função) será enviada para esses emails.</small>
                                            </div>

                                            <hr>
                                            <h6 class="fw-bold small mb-3"><i class="fas fa-plug me-2"></i>Webhook</h6>
                                            <div class="mb-3">
                                                <label class="form-label">URL do Webhook</label>
                                                <input type="url" class="form-control" name="demandas_webhook_url" value="' . htmlspecialchars($demandasConfig['demandas_webhook_url'], ENT_QUOTES, 'UTF-8') . '" placeholder="https://hooks.slack.com/...">
                                                <small class="text-muted">Recebe POST JSON com dados da nova solicitação. Compatível com Slack, Discord, etc.</small>
                                            </div>

                                            <hr>
                                            <h6 class="fw-bold small mb-3"><i class="fas fa-bell me-2"></i>Notificações Push (no Admin)</h6>
                                            <div class="mb-3">
                                                <label class="form-label">Usuários que recebem notificações</label>
                                                <select class="form-select" name="demandas_usuarios_notificacao[]" multiple size="6">';

        $idsNotifDemandas = array_filter(array_map('intval', explode(',', $demandasConfig['demandas_usuarios_notificacao'])));
        foreach ($demandasUsuarios as $u) {
            $sel = in_array((int)$u['id'], $idsNotifDemandas) ? ' selected' : '';
            echo '<option value="' . (int)$u['id'] . '"' . $sel . '>' . htmlspecialchars($u['nome']) . ' (' . htmlspecialchars($u['email']) . ')</option>';
        }

        echo '                                </select>
                                                <small class="text-muted">Segure Ctrl/Cmd para selecionar múltiplos. Esses usuários verão notificações em tempo real no painel admin.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                        </div>
                            
                            <div class="d-flex justify-content-end mt-4" id="admin-config-salvar-geral">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Configurações
                                </button>
                            </div>
                    </form>
                </article>

            </section>
            </div>
            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile select navigation sync
    document.addEventListener("DOMContentLoaded", function() {
        var mobileSelect = document.getElementById("settingsMobileSelect");
        if (mobileSelect) {
            mobileSelect.addEventListener("change", function() {
                var val = this.value;
                var tabBtn = document.getElementById(val + "-tab");
                if (tabBtn) tabBtn.click();
            });
            // Sync select when tab changes
            var tabBtns = document.querySelectorAll("#v-pills-tab .nav-link");
            tabBtns.forEach(function(btn) {
                btn.addEventListener("shown.bs.tab", function() {
                    var target = this.getAttribute("data-bs-target");
                    if (target) {
                        mobileSelect.value = target.replace("#", "");
                    }
                });
            });
        }
    });
    </script>
    ' . $this->getPagamentosJS() . $this->getEmailCreatorJS() . $this->getNotificacoesJS() . $this->getEntregaJS() . $this->getComissoesJS() . $this->getUsuariosImportJS() . $this->getPedidosImportJS() . $this->getProdutosImportJS() . '
</body>
</html>';
        exit;
    }

    private function getPedidosImportJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnImportarPedidosCsv');
    const fileInput = document.getElementById('pedidos_import_csv');
    const wrap = document.getElementById('pedidosImportProgressWrap');
    const bar = document.getElementById('pedidosImportProgressBar');
    const percentEl = document.getElementById('pedidosImportProgressPercent');
    const labelEl = document.getElementById('pedidosImportProgressLabel');
    const statsEl = document.getElementById('pedidosImportProgressStats');

    if (!btn || !fileInput || !wrap || !bar || !percentEl || !labelEl || !statsEl) return;

    let running = false;

    function setProgress(processed, total, okCount, failCount, label){
        const t = (typeof total === 'number' && total > 0) ? total : 0;
        const p = (typeof processed === 'number' && processed > 0) ? processed : 0;
        let pct = (t > 0) ? Math.floor((p / t) * 100) : 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        percentEl.textContent = pct + '%';
        labelEl.textContent = label || 'Processando...';
        statsEl.textContent = 'Processados: ' + p + ' / ' + t + ' | OK: ' + (okCount||0) + ' | Falhas: ' + (failCount||0);
    }

    async function iniciarImportacao(file){
        const fd = new FormData();
        fd.append('pedidos_import_csv', file);
        const resp = await fetch('/admin/pedidos/importar/iniciar', { method: 'POST', body: fd });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao iniciar a importação.');
        }
        return json;
    }

    async function processarLote(token, batchSize){
        const fd = new URLSearchParams();
        fd.set('token', token);
        fd.set('batch', String(batchSize || 200));
        const resp = await fetch('/admin/pedidos/importar/processar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: fd.toString()
        });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao processar lote.');
        }
        return json;
    }

    btn.addEventListener('click', async function(){
        if (running) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert('Selecione um arquivo CSV primeiro.');
            return;
        }

        running = true;
        btn.disabled = true;
        wrap.style.display = '';
        setProgress(0, 0, 0, 0, 'Enviando arquivo...');

        try {
            const init = await iniciarImportacao(file);
            const token = init.token;
            const total = init.total || 0;
            let last = init;

            setProgress(0, total, 0, 0, 'Importação iniciada...');

            while (!last.done) {
                last = await processarLote(token, 200);
                setProgress(last.processed || 0, last.total || total, last.okCount || 0, last.failCount || 0, 'Processando em lotes...');
            }

            setProgress(last.processed || total, last.total || total, last.okCount || 0, last.failCount || 0, 'Finalizado');
        } catch (e) {
            alert(e && e.message ? e.message : 'Erro na importação.');
            labelEl.textContent = 'Erro';
        } finally {
            running = false;
            btn.disabled = false;
        }
    });
});
</script>
JS;
    }

    private function getProdutosImportJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnImportarProdutosCsv');
    const fileInput = document.getElementById('produtos_import_csv');
    const wrap = document.getElementById('produtosImportProgressWrap');
    const bar = document.getElementById('produtosImportProgressBar');
    const percentEl = document.getElementById('produtosImportProgressPercent');
    const labelEl = document.getElementById('produtosImportProgressLabel');
    const statsEl = document.getElementById('produtosImportProgressStats');

    if (!btn || !fileInput || !wrap || !bar || !percentEl || !labelEl || !statsEl) return;

    let running = false;

    function setProgress(processed, total, okCount, failCount, label){
        const t = (typeof total === 'number' && total > 0) ? total : 0;
        const p = (typeof processed === 'number' && processed > 0) ? processed : 0;
        let pct = (t > 0) ? Math.floor((p / t) * 100) : 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        percentEl.textContent = pct + '%';
        labelEl.textContent = label || 'Processando...';
        statsEl.textContent = 'Processados: ' + p + ' / ' + t + ' | OK: ' + (okCount||0) + ' | Falhas: ' + (failCount||0);
    }

    async function iniciarImportacao(file){
        const fd = new FormData();
        fd.append('produtos_import_csv', file);
        const resp = await fetch('/admin/produtos/importar/iniciar', { method: 'POST', body: fd });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao iniciar a importação.');
        }
        return json;
    }

    async function processarLote(token, batchSize){
        const fd = new URLSearchParams();
        fd.set('token', token);
        fd.set('batch', String(batchSize || 200));
        const resp = await fetch('/admin/produtos/importar/processar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: fd.toString()
        });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao processar lote.');
        }
        return json;
    }

    btn.addEventListener('click', async function(){
        if (running) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert('Selecione um arquivo CSV primeiro.');
            return;
        }

        running = true;
        btn.disabled = true;
        wrap.style.display = '';
        setProgress(0, 0, 0, 0, 'Enviando arquivo...');

        try {
            const init = await iniciarImportacao(file);
            const token = init.token;
            const total = init.total || 0;
            let last = init;

            setProgress(0, total, 0, 0, 'Importação iniciada...');

            while (!last.done) {
                last = await processarLote(token, 200);
                setProgress(last.processed || 0, last.total || total, last.okCount || 0, last.failCount || 0, 'Processando em lotes...');
            }

            setProgress(last.processed || total, last.total || total, last.okCount || 0, last.failCount || 0, 'Finalizado');
        } catch (e) {
            alert(e && e.message ? e.message : 'Erro na importação.');
            labelEl.textContent = 'Erro';
        } finally {
            running = false;
            btn.disabled = false;
        }
    });
});
</script>
JS;
    }

    private function getUsuariosImportJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnImportarUsuariosCsv');
    const fileInput = document.getElementById('usuarios_import_csv');
    const wrap = document.getElementById('usuariosImportProgressWrap');
    const bar = document.getElementById('usuariosImportProgressBar');
    const percentEl = document.getElementById('usuariosImportProgressPercent');
    const labelEl = document.getElementById('usuariosImportProgressLabel');
    const statsEl = document.getElementById('usuariosImportProgressStats');

    if (!btn || !fileInput || !wrap || !bar || !percentEl || !labelEl || !statsEl) return;

    let running = false;

    function setProgress(processed, total, okCount, failCount, label){
        const t = (typeof total === 'number' && total > 0) ? total : 0;
        const p = (typeof processed === 'number' && processed > 0) ? processed : 0;
        let pct = (t > 0) ? Math.floor((p / t) * 100) : 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        percentEl.textContent = pct + '%';
        labelEl.textContent = label || 'Processando...';
        statsEl.textContent = 'Processados: ' + p + ' / ' + t + ' | OK: ' + (okCount||0) + ' | Falhas: ' + (failCount||0);
    }

    async function iniciarImportacao(file){
        const fd = new FormData();
        fd.append('usuarios_import_csv', file);
        const resp = await fetch('/admin/configuracoes/importar-usuarios/iniciar', { method: 'POST', body: fd });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao iniciar a importação.');
        }
        return json;
    }

    async function processarLote(token, batchSize){
        const fd = new URLSearchParams();
        fd.set('token', token);
        fd.set('batch', String(batchSize || 300));
        const resp = await fetch('/admin/configuracoes/importar-usuarios/processar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: fd.toString()
        });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao processar lote.');
        }
        return json;
    }

    btn.addEventListener('click', async function(){
        if (running) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert('Selecione um arquivo CSV primeiro.');
            return;
        }

        running = true;
        btn.disabled = true;
        wrap.style.display = '';
        setProgress(0, 0, 0, 0, 'Enviando arquivo...');

        try {
            const init = await iniciarImportacao(file);
            const token = init.token;
            const total = init.total || 0;
            let last = init;

            setProgress(0, total, 0, 0, 'Importação iniciada...');

            while (!last.done) {
                last = await processarLote(token, 300);
                setProgress(last.processed || 0, last.total || total, last.okCount || 0, last.failCount || 0, 'Processando em lotes de 300...');
            }

            setProgress(last.processed || total, last.total || total, last.okCount || 0, last.failCount || 0, 'Finalizado');
        } catch (e) {
            alert(e && e.message ? e.message : 'Erro na importação.');
            labelEl.textContent = 'Erro';
        } finally {
            running = false;
            btn.disabled = false;
        }
    });
});
</script>
JS;
    }

    private function getComissoesJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const hidden = document.getElementById('comissao_manual_faixas');
    const body = document.getElementById('comissaoManualFaixasBody');
    const btnAdd = document.getElementById('btnAddComissaoFaixa');
    if (!hidden || !body || !btnAdd) return;

    const normalizeNumber = (v) => {
        if (v === null || v === undefined) return 0;
        const s = String(v).replace(',', '.').trim();
        const n = Number(s);
        return isNaN(n) ? 0 : n;
    };

    const parseJson = () => {
        try {
            const raw = String(hidden.value || '').trim();
            if (!raw) return [];
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    };

    const serialize = () => {
        const rows = [];
        body.querySelectorAll('tr').forEach(tr => {
            const min = normalizeNumber(tr.querySelector('.cm-min')?.value);
            const max = normalizeNumber(tr.querySelector('.cm-max')?.value);
            const percent = normalizeNumber(tr.querySelector('.cm-percent')?.value);
            rows.push({ min, max, percent });
        });
        hidden.value = JSON.stringify(rows);
    };

    const addRow = (min, max, percent) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-min" value="${String(min ?? 0)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-max" value="${String(max ?? 0)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-percent" value="${String(percent ?? 0)}"></td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm cm-del"><i class="fas fa-trash"></i></button>
            </td>
        `;
        body.appendChild(tr);

        tr.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('input', serialize);
            inp.addEventListener('change', serialize);
        });
        tr.querySelector('.cm-del')?.addEventListener('click', function(){
            tr.remove();
            serialize();
        });
    };

    const initial = parseJson();
    if (initial.length === 0) {
        addRow(0, 999999999, 0);
    } else {
        initial.forEach(it => addRow(it.min ?? 0, it.max ?? 0, it.percent ?? 0));
    }
    serialize();

    btnAdd.addEventListener('click', function(){
        addRow(0, 0, 0);
        serialize();
    });

    const form = hidden.closest('form');
    if (form) {
        form.addEventListener('submit', function(){
            serialize();
        });
    }
});
</script>
JS;
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn() > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function renderRepresentantesComissoesHtml(?\PDO $pdo): string {
        if (!$pdo || !$this->tableExists($pdo, 'usuarios') || !$this->tableExists($pdo, 'representante_comissoes')) {
            return '';
        }

        $uCols = $this->getColumns($pdo, 'usuarios');
        $nomeCol = $this->pickColumn($uCols, ['nome', 'name']);
        if (!$nomeCol) {
            return '';
        }
        if (!in_array('perfil', $uCols, true)) {
            return '';
        }

        $reps = [];
        try {
            $st = $pdo->prepare('SELECT id, ' . $nomeCol . ' AS nome, email FROM usuarios WHERE LOWER(COALESCE(perfil,\'\')) = \'representante\' ORDER BY ' . $nomeCol . ' ASC');
            $st->execute();
            $reps = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $reps = [];
        }

        if (empty($reps)) {
            return '<div class="alert alert-info">Nenhum usuário com perfil Representante encontrado.</div>';
        }

        $map = [];
        try {
            $ids = array_values(array_filter(array_map(function ($r) {
                return (int) ($r['id'] ?? 0);
            }, $reps)));
            $ids = array_values(array_unique($ids));
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st2 = $pdo->prepare('SELECT representante_id, percentual, ativo FROM representante_comissoes WHERE representante_id IN (' . $in . ')');
                $st2->execute($ids);
                foreach (($st2->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $row) {
                    $rid = (int) ($row['representante_id'] ?? 0);
                    if ($rid > 0) {
                        $map[$rid] = [
                            'percentual' => (float) ($row['percentual'] ?? 0),
                            'ativo' => (int) ($row['ativo'] ?? 1),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $map = [];
        }

        $html = '<hr>'
            . '<h6 class="mb-2">Comissões por Representante</h6>'
            . '<div class="text-muted small mb-2">Configure o percentual (%) de comissão para cada representante. Usado no painel do representante e no cálculo: (venda - custo) * %.</div>'
            . '<div class="table-responsive">'
            . '<table class="table table-sm table-bordered align-middle">'
            . '<thead><tr><th style="width:45%">Representante</th><th style="width:35%">E-mail</th><th style="width:20%">Comissão (%)</th></tr></thead><tbody>';

        foreach ($reps as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $nome = htmlspecialchars((string) ($r['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $email = htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $pct = isset($map[$rid]) ? (float) ($map[$rid]['percentual'] ?? 0) : 0.0;
            $pctEsc = htmlspecialchars((string) $pct, ENT_QUOTES, 'UTF-8');
            $html .= '<tr>'
                . '<td>' . $nome . ' <span class="text-muted">(#' . $rid . ')</span></td>'
                . '<td>' . $email . '</td>'
                . '<td><input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" name="representante_comissoes[' . $rid . ']" value="' . $pctEsc . '"></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private function getColumns(\PDO $pdo, string $table): array {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function detectItensTable(\PDO $pdo): ?string {
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function normalizePaidWhere(?string $pedidoStatusCol): string {
        if (!$pedidoStatusCol) {
            return '';
        }
        $paid = [
            'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
        ];
        return " WHERE LOWER(COALESCE(ped.{$pedidoStatusCol}, '')) IN ('" . implode("','", $paid) . "')";
    }

    private function getMapaCalorData($pdo): array {
        if (!$pdo instanceof \PDO) {
            return [];
        }

        $out = [
            'sexo' => [],
            'faixa_etaria_consumo' => [],
            'regioes_estado' => [],
            'regioes_pais' => [],
            'mais_vendidos_produtos' => [],
            'mais_vendidos_categorias' => [],
            'mais_vendidos_tipos' => [],
        ];

        // Usuários
        if ($this->tableExists($pdo, 'usuarios')) {
            $uCols = $this->getColumns($pdo, 'usuarios');
            $sexoCol = $this->pickColumn($uCols, ['sexo', 'genero', 'gender']);
            if ($sexoCol) {
                try {
                    $stmt = $pdo->query("SELECT LOWER(TRIM(COALESCE({$sexoCol}, '')) ) AS sexo, COUNT(*) AS total FROM usuarios GROUP BY LOWER(TRIM(COALESCE({$sexoCol}, '')) ) ORDER BY total DESC");
                    $out['sexo'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['sexo'] = [];
                }
            }

            $estadoCol = $this->pickColumn($uCols, ['estado', 'uf', 'state']);
            if ($estadoCol) {
                try {
                    $stmt = $pdo->query("SELECT UPPER(TRIM(COALESCE({$estadoCol}, ''))) AS estado, COUNT(*) AS total FROM usuarios GROUP BY UPPER(TRIM(COALESCE({$estadoCol}, ''))) ORDER BY total DESC");
                    $out['regioes_estado'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['regioes_estado'] = [];
                }
            }

            $paisCol = $this->pickColumn($uCols, ['pais_residencia', 'pais', 'country']);
            if ($paisCol) {
                try {
                    $stmt = $pdo->query("SELECT UPPER(TRIM(COALESCE({$paisCol}, ''))) AS pais, COUNT(*) AS total FROM usuarios GROUP BY UPPER(TRIM(COALESCE({$paisCol}, ''))) ORDER BY total DESC");
                    $out['regioes_pais'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['regioes_pais'] = [];
                }
            }
        }

        // Consumo por faixa etária
        if ($this->tableExists($pdo, 'usuarios') && $this->tableExists($pdo, 'pedidos')) {
            $uCols = $this->getColumns($pdo, 'usuarios');
            $pCols = $this->getColumns($pdo, 'pedidos');

            $dataNascCol = $this->pickColumn($uCols, ['data_nascimento', 'nascimento', 'birthdate', 'dob']);
            $usuarioIdCol = $this->pickColumn($pCols, ['usuario_id', 'user_id']);
            $totalCol = $this->pickColumn($pCols, ['valor_total', 'total', 'amount', 'valor']);
            $statusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

            if ($dataNascCol && $usuarioIdCol && $totalCol) {
                $wherePaid = '';
                if ($statusCol) {
                    $paid = [
                        'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
                    ];
                    $wherePaid = " AND LOWER(COALESCE(p.{$statusCol}, '')) IN ('" . implode("','", $paid) . "')";
                }

                $idadeExpr = "TIMESTAMPDIFF(YEAR, u.{$dataNascCol}, CURDATE())";
                $faixaExpr = "CASE\n"
                    . " WHEN {$idadeExpr} < 18 THEN '0-17'\n"
                    . " WHEN {$idadeExpr} BETWEEN 18 AND 24 THEN '18-24'\n"
                    . " WHEN {$idadeExpr} BETWEEN 25 AND 34 THEN '25-34'\n"
                    . " WHEN {$idadeExpr} BETWEEN 35 AND 44 THEN '35-44'\n"
                    . " WHEN {$idadeExpr} BETWEEN 45 AND 54 THEN '45-54'\n"
                    . " WHEN {$idadeExpr} BETWEEN 55 AND 64 THEN '55-64'\n"
                    . " ELSE '65+'\n END";

                try {
                    $sql = "SELECT {$faixaExpr} AS faixa, SUM(COALESCE(p.{$totalCol},0)) AS total_gasto, COUNT(*) AS pedidos\n"
                        . "FROM pedidos p\n"
                        . "INNER JOIN usuarios u ON u.id = p.{$usuarioIdCol}\n"
                        . "WHERE u.{$dataNascCol} IS NOT NULL {$wherePaid}\n"
                        . "GROUP BY faixa\n"
                        . "ORDER BY total_gasto DESC";
                    $stmt = $pdo->query($sql);
                    $out['faixa_etaria_consumo'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['faixa_etaria_consumo'] = [];
                }
            }
        }

        // Mais vendidos (produtos / categorias / tipos)
        $itensTable = null;
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                $itensTable = $t;
                break;
            }
        }
        if ($itensTable && $this->tableExists($pdo, 'produtos') && $this->tableExists($pdo, 'pedidos')) {
            $iCols = $this->getColumns($pdo, $itensTable);
            $pCols = $this->getColumns($pdo, 'pedidos');
            $prCols = $this->getColumns($pdo, 'produtos');

            $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
            $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
            $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
            $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

            $wherePaid = '';
            if ($pedidoStatusCol) {
                $paid = [
                    'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
                ];
                $wherePaid = " WHERE LOWER(COALESCE(ped.{$pedidoStatusCol}, '')) IN ('" . implode("','", $paid) . "')";
            }

            if ($colPedidoId && $colProdutoId) {
                $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';
                $nomeProdutoCol = $this->pickColumn($prCols, ['name', 'nome']);
                $tipoCol = $this->pickColumn($prCols, ['type', 'tipo']);

                // Produtos mais vendidos
                if ($nomeProdutoCol) {
                    try {
                        $sql = "SELECT pr.id, pr.{$nomeProdutoCol} AS produto, {$qtdExpr} AS quantidade\n"
                            . "FROM {$itensTable} i\n"
                            . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                            . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                            . $wherePaid . "\n"
                            . "GROUP BY pr.id, pr.{$nomeProdutoCol}\n"
                            . "ORDER BY quantidade DESC\n"
                            . "LIMIT 15";
                        $stmt = $pdo->query($sql);
                        $out['mais_vendidos_produtos'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                    } catch (\Exception $e) {
                        $out['mais_vendidos_produtos'] = [];
                    }
                }

                // Categorias mais vendidas
                if ($this->tableExists($pdo, 'categorias') && in_array('category_id', $prCols, true)) {
                    $cCols = $this->getColumns($pdo, 'categorias');
                    $catNomeCol = $this->pickColumn($cCols, ['name', 'nome']);
                    if ($catNomeCol) {
                        try {
                            $sql = "SELECT c.{$catNomeCol} AS categoria, {$qtdExpr} AS quantidade\n"
                                . "FROM {$itensTable} i\n"
                                . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                                . "LEFT JOIN categorias c ON c.id = pr.category_id\n"
                                . $wherePaid . "\n"
                                . "GROUP BY c.{$catNomeCol}\n"
                                . "ORDER BY quantidade DESC\n"
                                . "LIMIT 15";
                            $stmt = $pdo->query($sql);
                            $out['mais_vendidos_categorias'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                        } catch (\Exception $e) {
                            $out['mais_vendidos_categorias'] = [];
                        }
                    }
                }

                // Tipos mais vendidos
                if ($tipoCol) {
                    try {
                        $sql = "SELECT COALESCE(NULLIF(TRIM(pr.{$tipoCol}),''), 'sem_tipo') AS tipo, {$qtdExpr} AS quantidade\n"
                            . "FROM {$itensTable} i\n"
                            . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                            . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                            . $wherePaid . "\n"
                            . "GROUP BY tipo\n"
                            . "ORDER BY quantidade DESC\n"
                            . "LIMIT 15";
                        $stmt = $pdo->query($sql);
                        $out['mais_vendidos_tipos'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                    } catch (\Exception $e) {
                        $out['mais_vendidos_tipos'] = [];
                    }
                }
            }
        }

        return $out;
    }

    private function renderMapaCalorTabHtml(array $data): string {
        $sexo = $data['sexo'] ?? [];
        $faixa = $data['faixa_etaria_consumo'] ?? [];
        $estados = $data['regioes_estado'] ?? [];
        $paises = $data['regioes_pais'] ?? [];
        $produtos = $data['mais_vendidos_produtos'] ?? [];
        $categorias = $data['mais_vendidos_categorias'] ?? [];
        $tipos = $data['mais_vendidos_tipos'] ?? [];

        $renderRows = function($rows, $cols) {
            if (!is_array($rows) || empty($rows)) {
                return '<tr><td colspan="' . count($cols) . '" class="text-center text-muted">Sem dados</td></tr>';
            }
            $html = '';
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $html .= '<tr>';
                foreach ($cols as $c) {
                    $v = $r[$c] ?? '';
                    $html .= '<td>' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            return $html;
        };

        $sexRows = $renderRows($sexo, ['sexo', 'total']);
        $faixaRows = $renderRows($faixa, ['faixa', 'total_gasto', 'pedidos']);
        $estadoRows = $renderRows($estados, ['estado', 'total']);
        $paisRows = $renderRows($paises, ['pais', 'total']);
        $prodRows = $renderRows($produtos, ['id', 'produto', 'quantidade']);
        $catRows = $renderRows($categorias, ['categoria', 'quantidade']);
        $tipoRows = $renderRows($tipos, ['tipo', 'quantidade']);

        $cardsCategorias = '';
        if (is_array($categorias) && !empty($categorias)) {
            foreach ($categorias as $c) {
                if (!is_array($c)) continue;
                $nome = (string) ($c['categoria'] ?? '');
                if (trim($nome) === '') continue;
                $qtd = (string) ($c['quantidade'] ?? '0');
                $cardsCategorias .= '<div class="col-6 col-lg-4">'
                    . '<a href="#" class="card h-100 text-decoration-none mapa-calor-card" data-seg="categoria" data-val="' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '">'
                    . '<div class="card-body">'
                    . '<div class="small text-muted">Categoria</div>'
                    . '<div class="fw-bold">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</div>'
                    . '<div class="small">Vendidos: <span class="fw-semibold">' . htmlspecialchars($qtd, ENT_QUOTES, 'UTF-8') . '</span></div>'
                    . '</div></a></div>';
            }
        }
        if ($cardsCategorias === '') {
            $cardsCategorias = '<div class="col-12"><div class="text-muted">Sem dados de categorias para segmentar.</div></div>';
        }

        $cardsLojas = '<div class="col-12"><div class="text-muted">Sem dados de lojas para segmentar.</div></div>';
        // Lojas serão carregadas via AJAX (quando existir tabela lojas ou colunas loja/loja_id)
        $cardsLojas = '<div class="col-12" id="mapaCalorLojasWrap"><div class="text-muted">Carregando lojas...</div></div>';

        $mapaCalorScript = <<<'HTML'
                        <script>
                            (function(){
                                function qs(sel){ return document.querySelector(sel); }
                                function esc(s){
                                    return String(s ?? '')
                                        .replace(/&/g,'&amp;')
                                        .replace(/</g,'&lt;')
                                        .replace(/>/g,'&gt;')
                                        .replace(/"/g,'&quot;')
                                        .replace(/'/g,'&#039;');
                                }

                                async function loadClientes(seg, val){
                                    const card = qs('#mapaCalorSegmentoCard');
                                    const body = qs('#mapaCalorClientesBody');
                                    const title = qs('#mapaCalorSegmentoTitulo');
                                    const sub = qs('#mapaCalorSegmentoSub');
                                    const exportBtn = qs('#mapaCalorExportBtn');
                                    if (!card || !body || !title || !sub || !exportBtn) return;

                                    card.style.display = 'block';
                                    title.textContent = 'Clientes do segmento';
                                    sub.textContent = seg + ': ' + val;
                                    exportBtn.href = '/admin/configuracoes/mapa-calor/export-emails?seg=' + encodeURIComponent(seg) + '&val=' + encodeURIComponent(val);

                                    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Carregando...</td></tr>';

                                    try {
                                        const resp = await fetch('/admin/configuracoes/mapa-calor/clientes?seg=' + encodeURIComponent(seg) + '&val=' + encodeURIComponent(val));
                                        const json = await resp.json();
                                        const rows = (json && json.clientes) ? json.clientes : [];
                                        if (!rows.length) {
                                            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sem dados</td></tr>';
                                            return;
                                        }
                                        let html = '';
                                        rows.forEach(r => {
                                            html += '<tr>'
                                                + '<td>' + esc(r.nome || '') + '</td>'
                                                + '<td>' + esc(r.email || '') + '</td>'
                                                + '<td>' + esc(r.pedidos || 0) + '</td>'
                                                + '<td>' + esc(r.total_gasto || 0) + '</td>'
                                                + '</tr>';
                                        });
                                        body.innerHTML = html;
                                    } catch (e) {
                                        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Erro ao carregar</td></tr>';
                                    }
                                }

                                function bindCards(){
                                    document.querySelectorAll('.mapa-calor-card').forEach(el => {
                                        el.addEventListener('click', function(ev){
                                            ev.preventDefault();
                                            const seg = this.getAttribute('data-seg') || '';
                                            const val = this.getAttribute('data-val') || '';
                                            if (!seg || !val) return;
                                            loadClientes(seg, val);
                                        });
                                    });
                                }

                                async function loadLojas(){
                                    const grid = qs('#mapaCalorLojasGrid');
                                    if (!grid) return;
                                    try {
                                        const resp = await fetch('/admin/configuracoes/mapa-calor/clientes?seg=lojas');
                                        const json = await resp.json();
                                        const lojas = (json && json.lojas) ? json.lojas : [];
                                        if (!lojas.length) {
                                            grid.innerHTML = '<div class="col-12"><div class="text-muted">Sem dados de lojas para segmentar.</div></div>';
                                            bindCards();
                                            return;
                                        }
                                        let html = '';
                                        lojas.forEach(l => {
                                            html += '<div class="col-6 col-lg-4">'
                                                + '<a href="#" class="card h-100 text-decoration-none mapa-calor-card" data-seg="loja" data-val="' + esc(l.label || '') + '">'
                                                + '<div class="card-body">'
                                                + '<div class="small text-muted">Loja</div>'
                                                + '<div class="fw-bold">' + esc(l.label || '') + '</div>'
                                                + '<div class="small">Vendidos: <span class="fw-semibold">' + esc(l.quantidade || 0) + '</span></div>'
                                                + '</div></a></div>';
                                        });
                                        grid.innerHTML = html;
                                        bindCards();
                                    } catch (e) {
                                        grid.innerHTML = '<div class="col-12"><div class="text-danger">Erro ao carregar lojas</div></div>';
                                    }
                                }

                                bindCards();
                                loadLojas();
                            })();
                        </script>
HTML;

        return '
            <div class="tab-pane fade" id="v-pills-mapa-calor" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Mapa de calor</h5>
                        <span class="badge bg-secondary">Beta</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" style="border-radius: 12px;">
                            Clique em uma categoria ou loja para ver os clientes que mais consumiram e exportar e-mails para campanhas.
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Segmentação por categoria</strong></div>
                                    <div class="card-body">
                                        <div class="row g-2">' . $cardsCategorias . '</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Segmentação por loja</strong></div>
                                    <div class="card-body">
                                        <div class="row g-2" id="mapaCalorLojasGrid">' . $cardsLojas . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4" id="mapaCalorSegmentoCard" style="display:none;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="mapaCalorSegmentoTitulo">Clientes do segmento</strong>
                                    <div class="small text-muted" id="mapaCalorSegmentoSub"></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" id="mapaCalorExportBtn" href="#" target="_blank">
                                        <i class="fas fa-file-csv me-1"></i>Exportar e-mails (CSV)
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>E-mail</th>
                                                <th>Pedidos</th>
                                                <th>Total gasto</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mapaCalorClientesBody">
                                            <tr><td colspan="4" class="text-center text-muted">Selecione um segmento acima.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Usuários por sexo</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Sexo</th><th>Total</th></tr></thead>
                                                <tbody>' . $sexRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Faixa etária com maior consumo</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Faixa</th><th>Total gasto</th><th>Pedidos</th></tr></thead>
                                                <tbody>' . $faixaRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Regiões de cadastro (Estados)</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Estado</th><th>Total</th></tr></thead>
                                                <tbody>' . $estadoRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Regiões de cadastro (Países)</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>País</th><th>Total</th></tr></thead>
                                                <tbody>' . $paisRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Produtos mais vendidos</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>ID</th><th>Produto</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $prodRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Categorias mais vendidas</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Categoria</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $catRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Tipos de produtos mais vendidos</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Tipo</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $tipoRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ' . $mapaCalorScript . '
                    </div>
                </div>
            </div>
        ';
    }

    public function mapaCalorClientes(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $pdo = Database::getConnection();
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Sem conexão com banco']);
            return;
        }

        $seg = (string) ($request->getParam('seg', '') ?? '');
        $val = (string) ($request->getParam('val', '') ?? '');
        $seg = trim($seg);
        $val = trim($val);

        // Endpoint auxiliar: retornar lista de lojas para cards
        if ($seg === 'lojas') {
            $lojas = $this->getLojasSegmentos($pdo);
            echo json_encode(['success' => true, 'lojas' => $lojas]);
            return;
        }

        if ($seg === '' || $val === '') {
            echo json_encode(['success' => true, 'clientes' => []]);
            return;
        }

        $clientes = $this->getClientesTopPorSegmento($pdo, $seg, $val, 100);
        echo json_encode(['success' => true, 'clientes' => $clientes]);
    }

    public function mapaCalorExportEmails(Request $request) {
        try {
            $pdo = Database::getConnection();
        } catch (\Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Sem conexão com banco';
            return;
        }

        $seg = (string) ($request->getParam('seg', '') ?? '');
        $val = (string) ($request->getParam('val', '') ?? '');
        $seg = trim($seg);
        $val = trim($val);

        if ($seg === '' || $val === '') {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Parâmetros inválidos';
            return;
        }

        $clientes = $this->getClientesTopPorSegmento($pdo, $seg, $val, 1000);
        $emails = [];
        foreach ($clientes as $c) {
            if (!is_array($c)) continue;
            $em = trim((string) ($c['email'] ?? ''));
            if ($em === '') continue;
            $emails[$em] = true;
        }
        $emails = array_keys($emails);

        $fileName = 'emails_' . preg_replace('/[^a-z0-9\-_]+/i', '_', $seg) . '_' . preg_replace('/[^a-z0-9\-_]+/i', '_', $val) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['email']);
        foreach ($emails as $em) {
            fputcsv($out, [$em]);
        }
        fclose($out);
    }

    private function getLojasSegmentos(\PDO $pdo): array {
        $itensTable = $this->detectItensTable($pdo);
        if (!$itensTable || !$this->tableExists($pdo, 'produtos') || !$this->tableExists($pdo, 'pedidos')) {
            return [];
        }
        $iCols = $this->getColumns($pdo, $itensTable);
        $pCols = $this->getColumns($pdo, 'pedidos');
        $prCols = $this->getColumns($pdo, 'produtos');

        $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
        $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
        $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
        if (!$colPedidoId || !$colProdutoId) {
            return [];
        }

        $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);
        $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);
        $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';

        $lojaIdCol = $this->pickColumn($prCols, ['loja_id']);
        $lojaSlugCol = $this->pickColumn($prCols, ['loja', 'store', 'seller']);

        // Preferir tabela lojas quando existir
        if ($lojaIdCol && $this->tableExists($pdo, 'lojas')) {
            try {
                $sql = "SELECT COALESCE(l.nome, CONCAT('Loja #', pr.{$lojaIdCol})) AS label, {$qtdExpr} AS quantidade\n"
                    . "FROM {$itensTable} i\n"
                    . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                    . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                    . "LEFT JOIN lojas l ON l.id = pr.{$lojaIdCol}\n"
                    . $wherePaid . "\n"
                    . "GROUP BY label\n"
                    . "ORDER BY quantidade DESC\n"
                    . "LIMIT 15";
                $stmt = $pdo->query($sql);
                $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                return $rows;
            } catch (\Exception $e) {
                return [];
            }
        }

        if ($lojaSlugCol) {
            try {
                $sql = "SELECT COALESCE(NULLIF(TRIM(pr.{$lojaSlugCol}),''), 'sem_loja') AS label, {$qtdExpr} AS quantidade\n"
                    . "FROM {$itensTable} i\n"
                    . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                    . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                    . $wherePaid . "\n"
                    . "GROUP BY label\n"
                    . "ORDER BY quantidade DESC\n"
                    . "LIMIT 15";
                $stmt = $pdo->query($sql);
                $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                return $rows;
            } catch (\Exception $e) {
                return [];
            }
        }

        return [];
    }

    private function getClientesTopPorSegmento(\PDO $pdo, string $seg, string $val, int $limit): array {
        $limit = max(1, min(2000, (int) $limit));

        $itensTable = $this->detectItensTable($pdo);
        if (!$itensTable || !$this->tableExists($pdo, 'produtos') || !$this->tableExists($pdo, 'pedidos')) {
            return [];
        }

        $uTable = $this->tableExists($pdo, 'usuarios') ? 'usuarios' : null;
        if ($uTable === null) {
            return [];
        }

        $iCols = $this->getColumns($pdo, $itensTable);
        $pCols = $this->getColumns($pdo, 'pedidos');
        $prCols = $this->getColumns($pdo, 'produtos');
        $uCols = $this->getColumns($pdo, 'usuarios');

        $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
        $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
        $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
        $usuarioIdCol = $this->pickColumn($pCols, ['usuario_id', 'user_id']);
        $totalCol = $this->pickColumn($pCols, ['valor_total', 'total', 'amount', 'valor']);
        $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

        if (!$colPedidoId || !$colProdutoId || !$usuarioIdCol || !$totalCol) {
            return [];
        }

        $nomeUserCol = $this->pickColumn($uCols, ['nome', 'name']);
        if (!$nomeUserCol) {
            $nomeUserCol = 'id';
        }
        $emailCol = $this->pickColumn($uCols, ['email']);
        if (!$emailCol) {
            return [];
        }

        $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);
        $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';

        $joinSeg = '';
        $whereSeg = '';
        $params = [];

        if ($seg === 'categoria') {
            if (!in_array('category_id', $prCols, true) || !$this->tableExists($pdo, 'categorias')) {
                return [];
            }
            $cCols = $this->getColumns($pdo, 'categorias');
            $catNomeCol = $this->pickColumn($cCols, ['name', 'nome']);
            if (!$catNomeCol) {
                return [];
            }
            $joinSeg = 'LEFT JOIN categorias c ON c.id = pr.category_id';
            $whereSeg = ' AND c.' . $catNomeCol . ' = :seg_val';
            $params[':seg_val'] = $val;
        } elseif ($seg === 'loja') {
            $lojaIdCol = $this->pickColumn($prCols, ['loja_id']);
            $lojaSlugCol = $this->pickColumn($prCols, ['loja', 'store', 'seller']);

            if ($lojaIdCol && $this->tableExists($pdo, 'lojas')) {
                $joinSeg = 'LEFT JOIN lojas l ON l.id = pr.' . $lojaIdCol;
                $whereSeg = ' AND COALESCE(l.nome, CONCAT(\'Loja #\', pr.' . $lojaIdCol . ')) = :seg_val';
                $params[':seg_val'] = $val;
            } elseif ($lojaSlugCol) {
                $whereSeg = ' AND COALESCE(NULLIF(TRIM(pr.' . $lojaSlugCol . '),\'\'), \'sem_loja\') = :seg_val';
                $params[':seg_val'] = $val;
            } else {
                return [];
            }
        } else {
            return [];
        }

        // Para ranking por segmento, o total_gasto será soma do total do pedido
        try {
            $sql = "SELECT\n"
                . "  COALESCE(u.{$nomeUserCol}, CONCAT('Cliente #', u.id)) AS nome,\n"
                . "  u.{$emailCol} AS email,\n"
                . "  COUNT(DISTINCT ped.id) AS pedidos,\n"
                . "  SUM(COALESCE(ped.{$totalCol},0)) AS total_gasto\n"
                . "FROM pedidos ped\n"
                . "INNER JOIN {$itensTable} i ON i.{$colPedidoId} = ped.id\n"
                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                . "INNER JOIN usuarios u ON u.id = ped.{$usuarioIdCol}\n"
                . ($joinSeg ? ($joinSeg . "\n") : '')
                . $wherePaid
                . " AND u.{$emailCol} IS NOT NULL AND u.{$emailCol} <> ''"
                . $whereSeg
                . "\nGROUP BY nome, email\n"
                . "ORDER BY total_gasto DESC\n"
                . "LIMIT {$limit}";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function salvar(Request $request) {
        try {
            $auth = new AuthService();
            $auth->requerPerfil('admin');

            $pdo = Database::getConnection();
            $acao = (string) ($request->getParam('acao') ?? '');
            if ($acao === 'importar_usuarios') {
                @ini_set('max_execution_time', '0');
                @set_time_limit(0);
                @ini_set('memory_limit', '-1');
                if (function_exists('ignore_user_abort')) {
                    @ignore_user_abort(true);
                }
                if (function_exists('session_write_close')) {
                    @session_write_close();
                }

                $resultado = $this->importarUsuariosCsv($pdo);
                header('Location: /admin/configuracoes?import_users=1&ok=' . (int) ($resultado['ok'] ?? 0) . '&fail=' . (int) ($resultado['fail'] ?? 0));
                exit;
            }

            $pdo->beginTransaction();

            // Upload do logotipo do layout
            try {
                $keepLogo = (string) ($request->getParam('layout_logo_keep', '') ?? '');
                $keepLogo = trim($keepLogo);

                $logoUrl = $keepLogo;
                if (isset($_FILES['layout_logo']) && is_array($_FILES['layout_logo'])) {
                    $name = (string) ($_FILES['layout_logo']['name'] ?? '');
                    $tmp = (string) ($_FILES['layout_logo']['tmp_name'] ?? '');
                    $err = (int) ($_FILES['layout_logo']['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'], true)) {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                            $candidates = [
                                $docRoot . '/public/uploads/logo/',
                                $docRoot . '/uploads/logo/',
                                $docRoot . '/public/uploads/logos/',
                                $docRoot . '/uploads/logos/',
                            ];
                            $uploadDir = '';
                            foreach ($candidates as $dir) {
                                if (!is_dir($dir)) {
                                    @mkdir($dir, 0755, true);
                                }
                                if (is_dir($dir) && is_writable($dir)) {
                                    $uploadDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
                                    break;
                                }
                            }

                            if ($uploadDir !== '') {
                                $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logo/' : '/uploads/logo/';
                                if (strpos(str_replace('\\', '/', $uploadDir), '/logos/') !== false) {
                                    $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logos/' : '/uploads/logos/';
                                }
                                $fileName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $logoUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }
                }

                $request->setParam('layout_logo', $logoUrl);
            } catch (\Exception $e) {
            }

            // Upload do logotipo do rodapé
            try {
                $keepLogo = (string) ($request->getParam('layout_logo_footer_keep', '') ?? '');
                $keepLogo = trim($keepLogo);

                $logoUrl = $keepLogo;
                if (isset($_FILES['layout_logo_footer']) && is_array($_FILES['layout_logo_footer'])) {
                    $name = (string) ($_FILES['layout_logo_footer']['name'] ?? '');
                    $tmp = (string) ($_FILES['layout_logo_footer']['tmp_name'] ?? '');
                    $err = (int) ($_FILES['layout_logo_footer']['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'], true)) {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                            $candidates = [
                                $docRoot . '/public/uploads/logo/',
                                $docRoot . '/uploads/logo/',
                                $docRoot . '/public/uploads/logos/',
                                $docRoot . '/uploads/logos/',
                            ];
                            $uploadDir = '';
                            foreach ($candidates as $dir) {
                                if (!is_dir($dir)) {
                                    @mkdir($dir, 0755, true);
                                }
                                if (is_dir($dir) && is_writable($dir)) {
                                    $uploadDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
                                    break;
                                }
                            }

                            if ($uploadDir !== '') {
                                $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logo/' : '/uploads/logo/';
                                if (strpos(str_replace('\\', '/', $uploadDir), '/logos/') !== false) {
                                    $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logos/' : '/uploads/logos/';
                                }
                                $fileName = 'logo_footer_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $logoUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }
                }

                $request->setParam('layout_logo_footer', $logoUrl);
            } catch (\Exception $e) {
            }

            // Upload do logo do admin
            try {
                $keepLogo = (string) ($request->getParam('layout_logo_admin_keep', '') ?? '');
                $keepLogo = trim($keepLogo);

                $logoUrl = $keepLogo;
                if (isset($_FILES['layout_logo_admin']) && is_array($_FILES['layout_logo_admin'])) {
                    $name = (string) ($_FILES['layout_logo_admin']['name'] ?? '');
                    $tmp = (string) ($_FILES['layout_logo_admin']['tmp_name'] ?? '');
                    $err = (int) ($_FILES['layout_logo_admin']['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'], true)) {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                            $candidates = [
                                $docRoot . '/public/uploads/logo/',
                                $docRoot . '/uploads/logo/',
                                $docRoot . '/public/uploads/logos/',
                                $docRoot . '/uploads/logos/',
                            ];
                            $uploadDir = '';
                            foreach ($candidates as $dir) {
                                if (!is_dir($dir)) {
                                    @mkdir($dir, 0755, true);
                                }
                                if (is_dir($dir) && is_writable($dir)) {
                                    $uploadDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
                                    break;
                                }
                            }

                            if ($uploadDir !== '') {
                                $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logo/' : '/uploads/logo/';
                                if (strpos(str_replace('\\', '/', $uploadDir), '/logos/') !== false) {
                                    $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/logos/' : '/uploads/logos/';
                                }
                                $fileName = 'logo_admin_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $logoUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }
                }

                $request->setParam('layout_logo_admin', $logoUrl);
            } catch (\Exception $e) {
            }

            // Upload do favicon
            try {
                $keepFavicon = (string) ($request->getParam('layout_favicon_keep', '') ?? '');
                $keepFavicon = trim($keepFavicon);

                $faviconUrl = $keepFavicon;
                if (isset($_FILES['layout_favicon']) && is_array($_FILES['layout_favicon'])) {
                    $name = (string) ($_FILES['layout_favicon']['name'] ?? '');
                    $tmp = (string) ($_FILES['layout_favicon']['tmp_name'] ?? '');
                    $err = (int) ($_FILES['layout_favicon']['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['ico','png','svg'], true)) {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                            $candidates = [
                                $docRoot . '/public/uploads/favicon/',
                                $docRoot . '/uploads/favicon/',
                                $docRoot . '/public/uploads/favicons/',
                                $docRoot . '/uploads/favicons/',
                            ];
                            $uploadDir = '';
                            foreach ($candidates as $dir) {
                                if (!is_dir($dir)) {
                                    @mkdir($dir, 0755, true);
                                }
                                if (is_dir($dir) && is_writable($dir)) {
                                    $uploadDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
                                    break;
                                }
                            }

                            if ($uploadDir !== '') {
                                $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/favicon/' : '/uploads/favicon/';
                                if (strpos(str_replace('\\', '/', $uploadDir), '/favicons/') !== false) {
                                    $webDir = strpos(str_replace('\\', '/', $uploadDir), '/public/') !== false ? '/public/uploads/favicons/' : '/uploads/favicons/';
                                }
                                $fileName = 'favicon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $faviconUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }
                }

                $request->setParam('layout_favicon', $faviconUrl);
            } catch (\Exception $e) {
            }

            // Upload do avatar BRI
            try {
                $keepBriAvatar = (string) ($request->getParam('layout_bri_avatar_keep', '') ?? '');
                $keepBriAvatar = trim($keepBriAvatar);

                $briAvatarUrl = $keepBriAvatar;
                if (isset($_FILES['layout_bri_avatar']) && is_array($_FILES['layout_bri_avatar'])) {
                    $name = (string) ($_FILES['layout_bri_avatar']['name'] ?? '');
                    $tmp = (string) ($_FILES['layout_bri_avatar']['tmp_name'] ?? '');
                    $err = (int) ($_FILES['layout_bri_avatar']['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['gif','png','webp'], true)) {
                            $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                            $uploadDir = $docRoot . '/public/uploads/logo/';
                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0755, true);
                            }
                            if (is_dir($uploadDir) && is_writable($uploadDir)) {
                                $fileName = 'bri_avatar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $briAvatarUrl = '/public/uploads/logo/' . $fileName;
                                }
                            }
                        }
                    }
                }

                $request->setParam('layout_bri_avatar', $briAvatarUrl);
            } catch (\Exception $e) {
            }

            // Upload de banners do layout
            try {
                $bannersLang = (string) $request->getParam('layout_banners_lang', 'pt');
                if (!in_array($bannersLang, ['pt', 'en'], true)) {
                    $bannersLang = 'pt';
                }

                $keepDesktop = $request->getParam('layout_banners_keep_desktop', []);
                $keepMobile = $request->getParam('layout_banners_keep_mobile', []);
                $keepLink = $request->getParam('layout_banners_keep_link', []);
                if (!is_array($keepDesktop)) $keepDesktop = [];
                if (!is_array($keepMobile)) $keepMobile = [];
                if (!is_array($keepLink)) $keepLink = [];

                $maxKeep = max(count($keepDesktop), count($keepMobile), count($keepLink));
                $keptItems = [];
                for ($i = 0; $i < $maxKeep; $i++) {
                    $d = isset($keepDesktop[$i]) && is_string($keepDesktop[$i]) ? trim((string) $keepDesktop[$i]) : '';
                    $m = isset($keepMobile[$i]) && is_string($keepMobile[$i]) ? trim((string) $keepMobile[$i]) : '';
                    $l = isset($keepLink[$i]) && is_string($keepLink[$i]) ? trim((string) $keepLink[$i]) : '';
                    if ($d === '' && $m === '') continue;
                    $keptItems[] = ['desktop' => $d, 'mobile' => $m, 'link' => $l];
                }

                $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                $candidates = [
                    $docRoot . '/public/uploads/banners/',
                    $docRoot . '/uploads/banners/',
                ];
                $uploadDir = '';
                foreach ($candidates as $dir) {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    if (is_dir($dir) && is_writable($dir)) {
                        $uploadDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
                        break;
                    }
                }

                $webDir = '/uploads/banners/';
                $newItems = [];

                $namesDesktop = (isset($_FILES['layout_banners_desktop']['name']) && is_array($_FILES['layout_banners_desktop']['name'])) ? $_FILES['layout_banners_desktop']['name'] : [];
                $namesMobile  = (isset($_FILES['layout_banners_mobile']['name']) && is_array($_FILES['layout_banners_mobile']['name'])) ? $_FILES['layout_banners_mobile']['name'] : [];
                $links = $request->getParam('layout_banners_link', []);
                if (!is_array($links)) $links = [];
                $maxUploads = max(is_array($namesDesktop) ? count($namesDesktop) : 0, is_array($namesMobile) ? count($namesMobile) : 0);

                for ($i = 0; $i < $maxUploads; $i++) {
                    $desktopUrl = '';
                    $mobileUrl = '';
                    $linkUrl = (isset($links[$i]) && is_string($links[$i])) ? trim((string) $links[$i]) : '';

                    if ($uploadDir !== '' && isset($_FILES['layout_banners_desktop']['tmp_name'][$i])) {
                        $name = (string) ($_FILES['layout_banners_desktop']['name'][$i] ?? '');
                        $tmp = (string) ($_FILES['layout_banners_desktop']['tmp_name'][$i] ?? '');
                        $err = (int) ($_FILES['layout_banners_desktop']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
                                $fileName = 'banner_desktop_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $desktopUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }

                    if ($uploadDir !== '' && isset($_FILES['layout_banners_mobile']['tmp_name'][$i])) {
                        $name = (string) ($_FILES['layout_banners_mobile']['name'][$i] ?? '');
                        $tmp = (string) ($_FILES['layout_banners_mobile']['tmp_name'][$i] ?? '');
                        $err = (int) ($_FILES['layout_banners_mobile']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        if ($err === UPLOAD_ERR_OK && $tmp !== '' && $name !== '') {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
                                $fileName = 'banner_mobile_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $filePath = $uploadDir . $fileName;
                                if (@move_uploaded_file($tmp, $filePath)) {
                                    $mobileUrl = $webDir . $fileName;
                                }
                            }
                        }
                    }

                    if ($desktopUrl === '' && $mobileUrl === '') continue;
                    $newItems[] = ['desktop' => $desktopUrl, 'mobile' => $mobileUrl, 'link' => $linkUrl];
                }

                $final = array_merge($keptItems, $newItems);
                $json = json_encode(array_values($final), JSON_UNESCAPED_UNICODE);
                if ($bannersLang === 'en') {
                    $request->setParam('layout_banners_en', $json);
                } else {
                    $request->setParam('layout_banners', $json);
                }
            } catch (\Exception $e) {
            }

            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];
            $valueCol = $tableInfo['valueCol'];
            $updatedAtCol = $tableInfo['updatedAtCol'];

            // Garantir colunas do site-lock em schema legado (single_row)
            try {
                if (($tableInfo['mode'] ?? '') === 'single_row') {
                    $st = $pdo->query('DESCRIBE ' . $table);
                    $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    if (!is_array($cols)) {
                        $cols = [];
                    }

                    $addedAny = false;
                    if (!in_array('sistema_site_lock_enabled', $cols, true) && !in_array('site_lock_enabled', $cols, true)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN sistema_site_lock_enabled TINYINT(1) NOT NULL DEFAULT 0');
                            $addedAny = true;
                        } catch (\Exception $e) {
                        }
                    }
                    if (!in_array('sistema_site_lock_password', $cols, true) && !in_array('site_lock_password', $cols, true)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN sistema_site_lock_password VARCHAR(191) DEFAULT NULL');
                            $addedAny = true;
                        } catch (\Exception $e) {
                        }
                    }
                    if (!in_array('sistema_site_lock_mode', $cols, true) && !in_array('site_lock_mode', $cols, true)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN sistema_site_lock_mode VARCHAR(20) NOT NULL DEFAULT \'total\'');
                            $addedAny = true;
                        } catch (\Exception $e) {
                        }
                    }
                    if (!in_array('sistema_site_lock_blocked_paths', $cols, true) && !in_array('site_lock_blocked_paths', $cols, true)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN sistema_site_lock_blocked_paths TEXT DEFAULT NULL');
                            $addedAny = true;
                        } catch (\Exception $e) {
                        }
                    }

                    // Garantir coluna de conversão de moeda
                    if (!in_array('loja_conversao_moeda_ativa', $cols, true) && !in_array('conversao_moeda_ativa', $cols, true)) {
                        try {
                            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN loja_conversao_moeda_ativa TINYINT(1) NOT NULL DEFAULT 0');
                            $addedAny = true;
                        } catch (\Exception $e) {
                        }
                    }

                    if ($addedAny) {
                        $tableInfo = $this->getConfigTableInfo($pdo);
                        $table = $tableInfo['table'];
                        $valueCol = $tableInfo['valueCol'];
                        $updatedAtCol = $tableInfo['updatedAtCol'];
                    }
                }
            } catch (\Exception $e) {
            }

            // Se for schema single_row, garantir que existe uma linha (e descobrir o id correto)
            if (($tableInfo['mode'] ?? '') === 'single_row') {
                $idCol = $tableInfo['idCol'] ?? 'id';
                try {
                    $stmtFirst = $pdo->query("SELECT {$idCol} AS id FROM {$table} ORDER BY {$idCol} ASC LIMIT 1");
                    $firstRow = $stmtFirst ? ($stmtFirst->fetch(\PDO::FETCH_ASSOC) ?: null) : null;
                    $firstId = is_array($firstRow) ? (int) ($firstRow['id'] ?? 0) : 0;
                    if ($firstId <= 0) {
                        // cria uma linha vazia com defaults
                        $pdo->exec("INSERT INTO {$table} () VALUES ()");
                        $firstId = (int) $pdo->lastInsertId();
                    }
                    if ($firstId > 0) {
                        $tableInfo['idVal'] = $firstId;
                    }
                } catch (\Exception $e) {
                }
            }
            
            // Mapeamento de configurações
            $configMap = [
                'loja' => ['nome', 'descricao', 'email', 'telefone', 'endereco', 'logo', 'conversao_moeda_ativa'],
                'layout' => ['banners', 'banners_en', 'logo', 'logo_footer', 'logo_admin', 'favicon', 'bri_avatar'],
                'email' => ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from', 'from_name', 'test_to'],
                'pagamentos' => ['asaas_enabled', 'asaas_ambiente', 'asaas_api_key', 'stripe_enabled', 'stripe_ambiente', 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'appmax_enabled', 'appmax_client_id', 'appmax_client_secret', 'appmax_app_id', 'appmax_access_token', 'appmax_ambiente', 'appmax_base_url', 'mercadopago_enabled', 'mercadopago_access_token', 'mercadopago_public_key', 'mercadopago_client_id', 'mercadopago_client_secret', 'cambioreal_enabled', 'cambioreal_base_url', 'cambioreal_app_id', 'cambioreal_app_public', 'cambioreal_app_secret', 'cambioreal_taxas_app_id', 'cambioreal_taxas_app_public', 'cambioreal_taxas_app_secret', 'webhook_link_pagamento_pedido_manual_url', 'pix_desconto_taxa_servico_percent'],
                'clube' => ['cashback_percent', 'rendimento_percent', 'rendimento_turbo_percent', 'rendimento_intervalo_valor', 'rendimento_intervalo_unidade', 'cron_secret'],
                'comissao' => ['manual_faixas', 'processamento_percent', 'janela_primeiro_inicio', 'janela_primeiro_fim', 'janela_duracao_dias'],
                'demandas' => ['demandas_senha_painel', 'demandas_emails_notificacao', 'demandas_webhook_url', 'demandas_usuarios_notificacao'],
                'entrega' => ['moeda_padrao', 'taxa_servico_kg', 'frete_gratis_acima', 'frete_padrao', 'custo_envio_por_item_usd', 'prazo_padrao', 'cep_origem', 'calcular_automatico', 'wexpress_enabled', 'wexpress_ambiente', 'wexpress_api_key', 'wexpress_service_code', 'wexpress_sender_json', 'correios_provider', 'correios_prepostagem_token', 'correios_prepostagem_id_correios', 'correios_prepostagem_codigo_servico', 'correios_prepostagem_sender_json', 'sigep_enabled', 'sigep_ambiente', 'sigep_usuario', 'sigep_senha', 'sigep_cnpj', 'sigep_servico_codigo', 'sigep_numero_contrato', 'sigep_cartao_postagem', 'correios_tracking_enabled', 'correios_tracking_base_url', 'correios_tracking_token', 'correios_tracking_header', 'correios_token_usuario', 'correios_token_senha', 'correios_token_ambiente', 'correios_token', 'correios_token_expira_em', 'correios_cep_ambiente', 'correios_cep_base_url', 'correios_cep_token', 'correios_packet_ambiente', 'correios_packet_login', 'correios_packet_password', 'correios_packet_cartao_postagem', 'shipstation_enabled', 'shipstation_api_key', 'shipstation_from_address_json', 'shipstation_carrier_id', 'shipstation_carrier_code', 'shipstation_service_code', 'shipstation_package_code', 'shipstation_label_layout', 'shipstation_label_format', 'shipstation_label_download_type', 'shipstation_display_scheme'],
                'seo' => ['title', 'description', 'keywords', 'google_analytics', 'google_tag_manager', 'sitemap_gerado'],
                'sistema' => ['timezone', 'idioma', 'moeda', 'usd_brl_rate', 'manutencao', 'debug', 'cache_ativado', 'site_lock_enabled', 'site_lock_password', 'site_lock_mode', 'site_lock_blocked_paths', 'welcome_popup_enabled'],
                'wordpress' => ['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'],
                'wordpress_br' => ['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'],
                'wordpress_red' => ['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'],
                'wordpress_us' => ['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'],
                'woocommerce' => ['store_url', 'consumer_key', 'consumer_secret'],
                'woocommerce_br' => ['store_url', 'consumer_key', 'consumer_secret'],
                'woocommerce_red' => ['store_url', 'consumer_key', 'consumer_secret'],
                'woocommerce_us' => ['store_url', 'consumer_key', 'consumer_secret'],
                'scrapingbee' => ['api_key'],
                'chatgpt' => ['api_key', 'model', 'temperature', 'max_tokens', 'peso_margem'],
                'assessoria' => ['webhook_inicio_url', 'webhook_conclusao_url'],
                'promocao' => ['taxa_servico_ativo', 'taxa_servico_tipo', 'taxa_servico_valor'],
                'desconto' => ['emails_autorizadores']
            ];
            
            $checkboxKeys = ['calcular_automatico', 'sitemap_gerado', 'manutencao', 'debug', 'cache_ativado', 'site_lock_enabled', 'welcome_popup_enabled', 'asaas_enabled', 'stripe_enabled', 'appmax_enabled', 'mercadopago_enabled', 'cambioreal_enabled', 'wexpress_enabled', 'sigep_enabled', 'correios_tracking_enabled', 'shipstation_enabled', 'taxa_servico_ativo', 'conversao_moeda_ativa'];

            foreach ($configMap as $categoria => $chaves) {
                foreach ($chaves as $chave) {
                    $valor = $request->getParam($categoria . '_' . $chave);

                    // Checkboxes não enviados no POST quando desmarcados
                    if ($valor === null && in_array($chave, $checkboxKeys, true)) {
                        $valor = '0';
                    }

                    if ($valor !== null) {
                        // Converter checkboxes para 0/1
                        if (in_array($chave, $checkboxKeys, true)) {
                            $valor = ($valor === '1' || $valor === 1 || $valor === true) ? '1' : '0';
                        }
                        
                        // Validar valores específicos
                        if ($chave === 'moeda_padrao') {
                            $valor = in_array($valor, ['USD', 'BRL']) ? $valor : 'USD';
                        }
                        if ($chave === 'taxa_servico_kg') {
                            $valor = is_numeric($valor) ? floatval($valor) : 39;
                        }
                        if ($chave === 'frete_gratis_acima') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($chave === 'frete_padrao') {
                            $valor = is_numeric($valor) ? floatval($valor) : 15;
                        }
                        if ($chave === 'custo_envio_por_item_usd') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($categoria === 'comissao' && in_array($chave, ['processamento_percent', 'comissao_processamento_percent'], true)) {
                            $valor = is_numeric($valor) ? (float) $valor : 0;
                            if ($valor < 0) $valor = 0;
                            if ($valor > 100) $valor = 100;
                        }
                        if ($categoria === 'pagamentos' && $chave === 'pix_desconto_taxa_servico_percent') {
                            $valor = is_numeric($valor) ? (float) $valor : 0;
                            if ($valor < 0) $valor = 0;
                            if ($valor > 100) $valor = 100;
                        }
                        if ($categoria === 'promocao' && $chave === 'taxa_servico_tipo') {
                            $valor = in_array($valor, ['percentual', 'fixo'], true) ? $valor : 'percentual';
                        }
                        if ($categoria === 'promocao' && $chave === 'taxa_servico_valor') {
                            $valor = is_numeric($valor) ? (float) $valor : 0;
                            if ($valor < 0) $valor = 0;
                        }
                        if ($chave === 'comissao_percentual') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($chave === 'prazo_padrao') {
                            $valor = is_numeric($valor) ? intval($valor) : 30;
                        }

                        if ($categoria === 'comissao' && $chave === 'manual_faixas') {
                            $decoded = json_decode((string) $valor, true);
                            if ($decoded === null || !is_array($decoded)) {
                                $valor = '[{"min":0,"max":999999999,"percent":0}]';
                            } else {
                                $valor = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                            }
                        }
                        
                        // Atualizar ou inserir configuração
                        $fullKey = $categoria . '_' . $chave;

                        if (($tableInfo['mode'] ?? '') === 'single_row') {
                            $map = $tableInfo['columnMap'] ?? [];
                            $col = $map[$categoria][$chave] ?? null;
                            if (!empty($col) && preg_match('/^[a-zA-Z0-9_]+$/', (string) $col)) {
                                $idCol = $tableInfo['idCol'];
                                $idVal = $tableInfo['idVal'] ?? 1;
                                $set = "{$col} = ?";
                                if (!empty($updatedAtCol)) {
                                    $set .= ", {$updatedAtCol} = NOW()";
                                }
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$set} WHERE {$idCol} = ?");
                                $stmtUpdate->execute([$valor, $idVal]);
                            }
                            continue;
                        }

                        if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                            $catCol = $tableInfo['categoriaCol'];
                            $keyCol = $tableInfo['chaveCol'];

                            if (!empty($updatedAtCol)) {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ?, {$updatedAtCol} = NOW() WHERE {$catCol} = ? AND {$keyCol} = ?");
                            } else {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ? WHERE {$catCol} = ? AND {$keyCol} = ?");
                            }
                            $stmtUpdate->execute([$valor, $categoria, $chave]);
                        } else {
                            $keyCol = $tableInfo['keyCol'];
                            if (!empty($updatedAtCol)) {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ?, {$updatedAtCol} = NOW() WHERE {$keyCol} = ?");
                            } else {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ? WHERE {$keyCol} = ?");
                            }
                            $stmtUpdate->execute([$valor, $fullKey]);
                        }

                        // rowCount() pode ser 0 mesmo quando o registro existe (valor não mudou).
                        // Só inserir quando realmente não existir.
                        if ($stmtUpdate->rowCount() === 0) {
                            $exists = false;
                            if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                                $catCol = $tableInfo['categoriaCol'];
                                $keyCol = $tableInfo['chaveCol'];
                                $stExists = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$catCol} = ? AND {$keyCol} = ? LIMIT 1");
                                $stExists->execute([$categoria, $chave]);
                                $exists = (bool) $stExists->fetchColumn();
                            } else {
                                $keyCol = $tableInfo['keyCol'];
                                $stExists = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$keyCol} = ? LIMIT 1");
                                $stExists->execute([$fullKey]);
                                $exists = (bool) $stExists->fetchColumn();
                            }

                            if (!$exists) {
                                // Detectar colunas NOT NULL sem default para evitar erros de INSERT
                                $extraCols = '';
                                $extraPlaceholders = '';
                                $extraVals = [];
                                try {
                                    $stDesc = $pdo->query("DESCRIBE {$table}");
                                    $descRows = $stDesc ? $stDesc->fetchAll(\PDO::FETCH_ASSOC) : [];
                                    foreach ($descRows as $dr) {
                                        $colName = (string) ($dr['Field'] ?? '');
                                        $nullable = strtoupper((string) ($dr['Null'] ?? 'YES'));
                                        $defaultVal = $dr['Default'] ?? null;
                                        $extra = strtolower((string) ($dr['Extra'] ?? ''));
                                        if ($colName === '' || strpos($extra, 'auto_increment') !== false) continue;
                                        // Pular colunas que já estamos inserindo
                                        if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                                            if (in_array($colName, [$tableInfo['categoriaCol'], $tableInfo['chaveCol'], $valueCol, $updatedAtCol], true)) continue;
                                        } else {
                                            if (in_array($colName, [$tableInfo['keyCol'], $valueCol, $updatedAtCol], true)) continue;
                                        }
                                        if ($nullable === 'NO' && $defaultVal === null) {
                                            $extraCols .= ', ' . $colName;
                                            $extraPlaceholders .= ', ?';
                                            $extraVals[] = '';
                                        }
                                    }
                                } catch (\Exception $e) {}

                                if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                                    $catCol = $tableInfo['categoriaCol'];
                                    $keyCol = $tableInfo['chaveCol'];
                                    if (!empty($updatedAtCol)) {
                                        $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$catCol}, {$keyCol}, {$valueCol}, {$updatedAtCol}{$extraCols}) VALUES (?, ?, ?, NOW(){$extraPlaceholders})");
                                        $stmtInsert->execute(array_merge([$categoria, $chave, $valor], $extraVals));
                                    } else {
                                        $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$catCol}, {$keyCol}, {$valueCol}{$extraCols}) VALUES (?, ?, ?{$extraPlaceholders})");
                                        $stmtInsert->execute(array_merge([$categoria, $chave, $valor], $extraVals));
                                    }
                                } else {
                                    $keyCol = $tableInfo['keyCol'];
                                    if (!empty($updatedAtCol)) {
                                        $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valueCol}, {$updatedAtCol}{$extraCols}) VALUES (?, ?, NOW(){$extraPlaceholders})");
                                    } else {
                                        $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valueCol}{$extraCols}) VALUES (?, ?{$extraPlaceholders})");
                                    }
                                    $stmtInsert->execute(array_merge([$fullKey, $valor], $extraVals));
                                }
                            }
                        }
                    }
                }
            }

            try {
                if ($this->tableExists($pdo, 'clube_descontos_faixas')) {
                    $rem = $request->getParam('clube_faixas_remover', []);
                    if (!is_array($rem)) $rem = [];
                    $remIds = array_values(array_unique(array_map('intval', $rem)));
                    if (!empty($remIds)) {
                        $in = implode(',', array_fill(0, count($remIds), '?'));
                        $stDel = $pdo->prepare('DELETE FROM clube_descontos_faixas WHERE id IN (' . $in . ')');
                        $stDel->execute($remIds);
                    }

                    $faixas = $request->getParam('clube_faixas', []);
                    if (!is_array($faixas)) $faixas = [];

                    foreach ($faixas as $row) {
                        if (!is_array($row)) continue;
                        $idFx = (int) ($row['id'] ?? 0);
                        if ($idFx <= 0) continue;
                        if (in_array($idFx, $remIds, true)) continue;

                        $ativo = (int) (($row['ativo'] ?? 0) ? 1 : 0);
                        $ordem = (int) ($row['ordem'] ?? 0);
                        $min = (float) str_replace(',', '.', (string) ($row['peso_min_kg'] ?? 0));
                        $max = (float) str_replace(',', '.', (string) ($row['peso_max_kg'] ?? 0));
                        $pct = (float) str_replace(',', '.', (string) ($row['percentual_desconto'] ?? 0));
                        if ($min < 0) $min = 0.0;
                        if ($max < 0) $max = 0.0;
                        if ($pct < 0) $pct = 0.0;

                        $stUp = $pdo->prepare('UPDATE clube_descontos_faixas SET peso_min_kg = ?, peso_max_kg = ?, percentual_desconto = ?, ativo = ?, ordem = ?, updated_at = NOW() WHERE id = ?');
                        $stUp->execute([$min, $max, $pct, $ativo, $ordem, $idFx]);
                    }

                    $toInsert = [];

                    $nova = $request->getParam('clube_faixa_nova', []);
                    if (is_array($nova)) {
                        $toInsert[] = $nova;
                    }

                    $novas = $request->getParam('clube_faixas_novas', []);
                    if (is_array($novas)) {
                        foreach ($novas as $rowN) {
                            if (is_array($rowN)) {
                                $toInsert[] = $rowN;
                            }
                        }
                    }

                    if (!empty($toInsert)) {
                        $stIns = $pdo->prepare('INSERT INTO clube_descontos_faixas (peso_min_kg, peso_max_kg, percentual_desconto, ativo, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                        foreach ($toInsert as $rowN) {
                            $minN = (float) str_replace(',', '.', (string) ($rowN['peso_min_kg'] ?? 0));
                            $maxN = (float) str_replace(',', '.', (string) ($rowN['peso_max_kg'] ?? 0));
                            $pctN = (float) str_replace(',', '.', (string) ($rowN['percentual_desconto'] ?? 0));
                            $ativoN = (int) (($rowN['ativo'] ?? 0) ? 1 : 0);
                            $ordN = (int) ($rowN['ordem'] ?? 0);
                            if ($minN < 0) $minN = 0.0;
                            if ($maxN < 0) $maxN = 0.0;
                            if ($pctN < 0) $pctN = 0.0;
                            if ($minN > 0 || $maxN > 0 || $pctN > 0) {
                                $stIns->execute([$minN, $maxN, $pctN, $ativoN, $ordN]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $pdo->commit();
            
            header('Location: /admin/configuracoes?success=1');
            exit;
            
        } catch (\Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $e3) {
            }
            $schemaInfo = '';
            try {
                if (isset($pdo)) {
                    $ti = $this->getConfigTableInfo($pdo);
                    $schemaInfo = ' (tabela=' . htmlspecialchars((string) ($ti['table'] ?? '')) . ', modo=' . htmlspecialchars((string) ($ti['mode'] ?? '')) . ')';
                }
            } catch (\Exception $e2) {
            }
            echo '<div class="alert alert-danger">Erro ao salvar configurações: ' . $e->getMessage() . $schemaInfo . '</div>';
            echo '<a href="/admin/configuracoes" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }

    public function importarUsuariosModelo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $headers = [
            'ID','User Email','User Login','First Name','Last Name','Billing Company','Billing Address 1','Billing Address 2','Billing City','Billing Postcode','Billing Country','Billing State','Billing Phone','Shipping Company','Shipping Address 1','Shipping Address 2','Shipping City','Shipping Postcode','Shipping Country','Shipping State','suite','billing_cpf','billing_birthdate','billing_number','billing_neighborhood','billing_cellphone','shipping_number','shipping_neighborhood','shipping_suite','billing_cnpj','_current_woo_wallet_balance','User Role','User Pass'
        ];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="import_usuarios_modelo.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        fclose($out);
        exit;
    }

    public function importarUsuariosIniciar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        if (!isset($_FILES['usuarios_import_csv']) || empty($_FILES['usuarios_import_csv']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não enviado.']);
            exit;
        }
        if (!empty($_FILES['usuarios_import_csv']['error']) && $_FILES['usuarios_import_csv']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Falha no upload do CSV.']);
            exit;
        }

        $tmpUpload = (string) $_FILES['usuarios_import_csv']['tmp_name'];
        $token = bin2hex(random_bytes(16));
        $csvPath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'usuarios_import_' . $token . '.csv';
        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'usuarios_import_' . $token . '.json';

        if (!@move_uploaded_file($tmpUpload, $csvPath)) {
            if (!@copy($tmpUpload, $csvPath)) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o arquivo no servidor.']);
                exit;
            }
        }

        $scan = $this->scanUsuariosCsv($csvPath);
        if (!($scan['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => (string) ($scan['error'] ?? 'CSV inválido')]);
            exit;
        }

        $state = [
            'token' => $token,
            'csv' => $csvPath,
            'delimiter' => (string) ($scan['delimiter'] ?? ','),
            'hasHeader' => (bool) ($scan['hasHeader'] ?? true),
            'headerMap' => (is_array($scan['headerMap'] ?? null) ? ($scan['headerMap'] ?? null) : null),
            'total' => (int) ($scan['total'] ?? 0),
            'offset' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
            'createdAt' => date('c'),
        ];
        @file_put_contents($statePath, json_encode($state));

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $state['total'],
            'processed' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
        ]);
        exit;
    }

    public function importarUsuariosProcessar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $pdo = Database::getConnection();
        $token = trim((string) ($request->getParam('token') ?? ''));
        $batchSize = (int) ($request->getParam('batch') ?? 300);
        if ($batchSize <= 0) $batchSize = 300;
        if ($batchSize > 1000) $batchSize = 1000;

        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'usuarios_import_' . $token . '.json';
        if (!is_file($statePath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Importação não encontrada (expirada).']);
            exit;
        }

        $stateRaw = @file_get_contents($statePath);
        $state = is_string($stateRaw) ? json_decode($stateRaw, true) : null;
        if (!is_array($state)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Estado da importação corrompido.']);
            exit;
        }

        if (!empty($state['done'])) {
            echo json_encode([
                'ok' => true,
                'token' => $token,
                'total' => (int) ($state['total'] ?? 0),
                'processed' => (int) ($state['offset'] ?? 0),
                'okCount' => (int) ($state['okCount'] ?? 0),
                'failCount' => (int) ($state['failCount'] ?? 0),
                'done' => true,
            ]);
            exit;
        }

        $csvPath = (string) ($state['csv'] ?? '');
        if ($csvPath === '' || !is_file($csvPath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não encontrado no servidor.']);
            exit;
        }

        $delimiter = (string) ($state['delimiter'] ?? ',');
        $hasHeader = (bool) ($state['hasHeader'] ?? true);
        $headerMap = (is_array($state['headerMap'] ?? null) ? ($state['headerMap'] ?? null) : null);
        $offset = (int) ($state['offset'] ?? 0);
        if ($offset < 0) $offset = 0;

        $res = $this->processUsuariosCsvBatch($pdo, $csvPath, $delimiter, $hasHeader, $headerMap, $offset, $batchSize);

        $state['offset'] = $offset + (int) ($res['processedNow'] ?? 0);
        $state['okCount'] = (int) ($state['okCount'] ?? 0) + (int) ($res['okNow'] ?? 0);
        $state['failCount'] = (int) ($state['failCount'] ?? 0) + (int) ($res['failNow'] ?? 0);
        $total = (int) ($state['total'] ?? 0);
        $processed = (int) ($state['offset'] ?? 0);
        $state['done'] = ($total > 0 && $processed >= $total) || (int) ($res['processedNow'] ?? 0) === 0;

        @file_put_contents($statePath, json_encode($state));

        if (!empty($state['done'])) {
            try { @unlink($csvPath); } catch (\Exception $e) {}
            try { @unlink($statePath); } catch (\Exception $e) {}
        }

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $total,
            'processed' => $processed,
            'okCount' => (int) ($state['okCount'] ?? 0),
            'failCount' => (int) ($state['failCount'] ?? 0),
            'done' => (bool) ($state['done'] ?? false),
        ]);
        exit;
    }

    private function normalizeUsuariosImportHeader(string $v): string {
        $s = trim($v);
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        return $s;
    }

    private function buildUsuariosImportHeaderMap(array $header): array {
        // Map interno (key -> idx) para reordenar as colunas para o layout esperado do importador.
        $aliases = [
            'id' => ['id','userid','user_id'],
            'email' => ['useremail','usermail','email','emailaddress','user_mail','user_email'],
            'login' => ['userlogin','login','username','user_name'],
            'first_name' => ['firstname','first_name','nome','givenname'],
            'last_name' => ['lastname','last_name','sobrenome','surname','familyname'],

            'billing_company' => ['billingcompany'],
            'billing_address1' => ['billingaddress1','billingaddress','billingstreet','billingrua','billinglogradouro'],
            'billing_address2' => ['billingaddress2','billingcomplement','billingcomplemento','billingaddressline2'],
            'billing_city' => ['billingcity','billingcidade'],
            'billing_postcode' => ['billingpostcode','billingcep','billingzipcode','billingzip'],
            'billing_country' => ['billingcountry','billingpais','billingcountrycode'],
            'billing_state' => ['billingstate','billinguf','billingestado'],
            'billing_phone' => ['billingphone','billingtelefone','phone','telefone'],

            'shipping_company' => ['shippingcompany'],
            'shipping_address1' => ['shippingaddress1','shippingaddress','shippingstreet','shippingrua','shippinglogradouro'],
            'shipping_address2' => ['shippingaddress2','shippingcomplement','shippingcomplemento','shippingaddressline2'],
            'shipping_city' => ['shippingcity','shippingcidade'],
            'shipping_postcode' => ['shippingpostcode','shippingcep','shippingzipcode','shippingzip'],
            'shipping_country' => ['shippingcountry','shippingpais','shippingcountrycode'],
            'shipping_state' => ['shippingstate','shippinguf','shippingestado'],

            'suite' => ['suite','suíte'],
            'billing_cpf' => ['billingcpf','cpf','documento'],
            'billing_birthdate' => ['billingbirthdate','birthdate','datanascimento','billingdob','dob'],
            'billing_number' => ['billingnumber','billingnumero','numero'],
            'billing_neighborhood' => ['billingneighborhood','billingbairro','neighborhood','bairro'],
            'billing_cellphone' => ['billingcellphone','cellphone','celular','billingcelular'],
            'shipping_number' => ['shippingnumber','shippingnumero'],
            'shipping_neighborhood' => ['shippingneighborhood','shippingbairro'],
            'shipping_suite' => ['shippingsuite'],
            'billing_cnpj' => ['billingcnpj','cnpj'],
            'woo_wallet_balance' => ['_current_woo_wallet_balance','woowalletbalance','walletbalance','saldo'],
            'user_role' => ['userrole','role','perfil'],
            'user_pass' => ['userpass','password','senha'],
        ];

        $aliasToKey = [];
        foreach ($aliases as $key => $als) {
            foreach ($als as $a) {
                $aliasToKey[$this->normalizeUsuariosImportHeader((string) $a)] = $key;
            }
        }

        $map = [];
        foreach ($header as $idx => $name) {
            $norm = $this->normalizeUsuariosImportHeader((string) $name);
            if ($norm === '') continue;
            $key = $aliasToKey[$norm] ?? null;
            if ($key && !isset($map[$key])) {
                $map[$key] = (int) $idx;
            }
        }
        return $map;
    }

    private function getUsuariosImportExpectedKeyOrder(): array {
        // Essa ordem bate com os índices consumidos por processUsuarioRow()
        return [
            'id',
            'email',
            'login',
            'first_name',
            'last_name',
            'billing_company',
            'billing_address1',
            'billing_address2',
            'billing_city',
            'billing_postcode',
            'billing_country',
            'billing_state',
            'billing_phone',
            'shipping_company',
            'shipping_address1',
            'shipping_address2',
            'shipping_city',
            'shipping_postcode',
            'shipping_country',
            'shipping_state',
            'suite',
            'billing_cpf',
            'billing_birthdate',
            'billing_number',
            'billing_neighborhood',
            'billing_cellphone',
            'shipping_number',
            'shipping_neighborhood',
            'shipping_suite',
            'billing_cnpj',
            'woo_wallet_balance',
            'user_role',
            'user_pass',
        ];
    }

    private function scanUsuariosCsv(string $csvPath): array {

        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['ok' => false, 'error' => 'Não foi possível ler o CSV.'];
        }

        $first = fgetcsv($fh, 0, ',');
        $delimiter = ',';
        if (is_array($first) && count($first) === 1) {
            rewind($fh);
            $first = fgetcsv($fh, 0, ';');
            $delimiter = ';';
        }

        $header = is_array($first) ? $first : [];
        $headerMap = null;
        $isHeader = false;

        if (!empty($header)) {
            $tmp = $this->buildUsuariosImportHeaderMap($header);
            // Considera que tem header se conseguir mapear pelo menos algumas chaves essenciais.
            $score = 0;
            foreach (['email','first_name','last_name','billing_postcode','billing_city'] as $k) {
                if (isset($tmp[$k])) $score++;
            }
            if ($score >= 2) {
                $isHeader = true;
                $headerMap = $tmp;
            } else {
                // fallback: detectar header por texto típico
                $joined = strtolower(implode(' ', array_map('strval', $header)));
                if (strpos($joined, 'user') !== false && (strpos($joined, 'mail') !== false || strpos($joined, 'email') !== false)) {
                    $isHeader = true;
                    $headerMap = $tmp;
                }
            }
        }

        if (!$isHeader) {
            rewind($fh);
        }

        $total = 0;
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }
            $total++;
        }
        fclose($fh);
        return ['ok' => true, 'delimiter' => $delimiter, 'hasHeader' => $isHeader, 'headerMap' => $headerMap, 'total' => $total];
    }

    private function processUsuariosCsvBatch(\PDO $pdo, string $csvPath, string $delimiter, bool $hasHeader, ?array $headerMap, int $offset, int $limit): array {
        $expectedKeys = $this->getUsuariosImportExpectedKeyOrder();

        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['processedNow' => 0, 'okNow' => 0, 'failNow' => 0];
        }

        if ($hasHeader) {
            $hdrRow = fgetcsv($fh, 0, $delimiter);
            if (is_array($hdrRow)) {
                $headerMap = $this->buildUsuariosImportHeaderMap($hdrRow);
            }
        }

        $skipped = 0;
        while ($skipped < $offset && ($rowSkip = fgetcsv($fh, 0, $delimiter)) !== false) {
            $skipped++;
        }

        $helper = new \App\Controllers\AdminUsuariosHelper();
        $processedNow = 0;
        $okNow = 0;
        $failNow = 0;

        $this->ensureImportRowStatusTable($pdo);

        while ($processedNow < $limit && ($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 5) {
                continue;
            }
            if ($hasHeader && is_array($headerMap) && !empty($headerMap)) {
                $ordered = [];
                foreach ($expectedKeys as $k) {
                    $idx = $headerMap[$k] ?? null;
                    $ordered[] = ($idx !== null && array_key_exists($idx, $row)) ? (string) $row[$idx] : '';
                }
                $row = $ordered;
            }
            $row = array_pad($row, count($expectedKeys), '');

            $rowKey = $this->getUsuarioImportRowKey($row);
            if ($rowKey !== '' && $this->isImportRowOk($pdo, 'usuarios', $rowKey)) {
                $okNow++;
                $processedNow++;
                continue;
            }
            try {
                $this->processUsuarioRow($pdo, $helper, $row);
                if ($rowKey !== '') {
                    $this->markImportRowOk($pdo, 'usuarios', $rowKey);
                }
                $okNow++;
            } catch (\Exception $e) {
                if ($rowKey !== '') {
                    $this->markImportRowFail($pdo, 'usuarios', $rowKey, $e->getMessage());
                }
                $failNow++;
            }
            $processedNow++;
        }

        fclose($fh);
        return ['processedNow' => $processedNow, 'okNow' => $okNow, 'failNow' => $failNow];
    }

    private function getUsuarioImportRowKey(array $row): string {
        $idExt = trim((string) ($row[0] ?? ''));
        $email = strtolower(trim((string) ($row[1] ?? '')));
        $login = strtolower(trim((string) ($row[2] ?? '')));
        $cpf = trim((string) ($row[21] ?? ''));
        $cnpj = trim((string) ($row[29] ?? ''));

        $doc = $cpf !== '' ? $cpf : $cnpj;
        $doc = preg_replace('/[^0-9]/', '', (string) $doc);

        if ($email !== '') return 'email:' . $email;
        if ($login !== '') return 'login:' . $login;
        if ($doc !== '') return 'doc:' . $doc;
        if ($idExt !== '') return 'id:' . $idExt;
        return '';
    }

    private function ensureImportRowStatusTable(\PDO $pdo): void {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['import_row_status']);
            $ok = (bool) $st->fetchColumn();
            if ($ok) return;
        } catch (\Exception $e) {
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS import_row_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                import_type VARCHAR(40) NOT NULL,
                row_key VARCHAR(191) NOT NULL,
                status VARCHAR(10) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                ok_at DATETIME NULL,
                fail_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_import_row (import_type, row_key),
                KEY idx_import_type_status (import_type, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    private function isImportRowOk(\PDO $pdo, string $type, string $rowKey): bool {
        try {
            $st = $pdo->prepare('SELECT status FROM import_row_status WHERE import_type = :t AND row_key = :k LIMIT 1');
            $st->execute([':t' => $type, ':k' => $rowKey]);
            $s = strtolower((string) ($st->fetchColumn() ?: ''));
            return $s === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function markImportRowOk(\PDO $pdo, string $type, string $rowKey): void {
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, ok_at) VALUES (:t,:k,\'ok\',1,NOW()) ON DUPLICATE KEY UPDATE status=\'ok\', attempts=attempts+1, last_error=NULL, ok_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey]);
        } catch (\Exception $e) {
        }
    }

    private function markImportRowFail(\PDO $pdo, string $type, string $rowKey, string $error): void {
        $error = trim((string) $error);
        if (strlen($error) > 2000) {
            $error = substr($error, 0, 2000);
        }
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, last_error, fail_at) VALUES (:t,:k,\'fail\',1,:e,NOW()) ON DUPLICATE KEY UPDATE status=\'fail\', attempts=attempts+1, last_error=:e, fail_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey, ':e' => $error]);
        } catch (\Exception $e) {
        }
    }

    private function processUsuarioRow(\PDO $pdo, \App\Controllers\AdminUsuariosHelper $helper, array $row): void {
        $get = function(int $idx) use ($row) {
            return trim((string) ($row[$idx] ?? ''));
        };

        $idExt = $get(0);
        $email = $get(1);
        $login = $get(2);
        $firstName = $get(3);
        $lastName = $get(4);

        $suite = $get(20);
        $billingCpf = $get(21);
        $billingBirth = $get(22);
        $billingCell = $get(25);
        $shippingSuite = $get(28);
        $billingCnpj = $get(29);
        $wooWalletBalance = $get(30);
        $role = $get(31);
        $pass = $get(32);

        if ($email === '' && $login === '' && $idExt === '') {
            throw new \RuntimeException('Linha vazia');
        }

        $nome = trim($firstName . ' ' . $lastName);
        if ($nome === '') {
            $nome = $login !== '' ? $login : $email;
        }

        $perfil = strtolower(trim($role));
        if ($perfil === '') {
            $perfil = 'cliente';
        }

        $telefone = $billingCell !== '' ? $billingCell : '';
        if ($telefone === '') {
            $telefone = $get(12);
        }

        $doc = $billingCpf !== '' ? $billingCpf : '';
        if ($doc === '' && $billingCnpj !== '') {
            $doc = $billingCnpj;
        }

        $emailFinal = '';
        if ($email !== '') {
            $emailFinal = $email;
        } elseif ($login !== '') {
            $emailFinal = $login . '@local';
        }

        $usuarioId = 0;
        try {
            if ($idExt !== '' && ctype_digit($idExt)) {
                $usuarioId = $this->findUsuarioIdById($pdo, (int) $idExt);
            }
            if ($usuarioId <= 0 && $email !== '') {
                $usuarioId = $this->findUsuarioIdByEmail($pdo, $email);
            }
            if ($usuarioId <= 0 && $doc !== '') {
                $usuarioId = $this->findUsuarioIdByDocumento($pdo, $doc);
            }
        } catch (\Exception $e) {
            $usuarioId = 0;
        }

        if ($emailFinal === '') {
            $seed = '';
            if ($idExt !== '') {
                $seed = 'id' . $idExt;
            } elseif (trim((string) $doc) !== '') {
                $seed = 'doc' . preg_replace('/[^0-9]/', '', (string) $doc);
            } else {
                $seed = substr(sha1($nome . '|' . $telefone), 0, 12);
            }
            $emailFinal = 'import_' . $seed . '@local';
        }

        $dadosUsuario = [
            'nome' => $nome,
            'email' => $emailFinal,
            'telefone' => $telefone,
            'cpf' => $billingCpf,
            'documento' => $doc,
            'suite' => ($suite !== '' ? $suite : ($shippingSuite !== '' ? $shippingSuite : null)),
            'perfil' => $perfil,
        ];
        if (trim((string) $doc) === '') {
            $dadosUsuario['_allow_missing_documento'] = 1;
        }
        if ($pass !== '') {
            $dadosUsuario['senha'] = $pass;
        }

        if ($usuarioId > 0) {
            // Política: preencher somente campos vazios (não sobrescrever)
            $current = [];
            try {
                $stCur = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                $stCur->execute([$usuarioId]);
                $current = $stCur->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $current = [];
            }

            $isEmpty = function($v): bool {
                if ($v === null) return true;
                if (is_string($v)) return trim($v) === '';
                return false;
            };

            foreach ($dadosUsuario as $k => $v) {
                if (!array_key_exists($k, $current)) {
                    continue;
                }
                if (!$isEmpty($current[$k] ?? null)) {
                    unset($dadosUsuario[$k]);
                }
            }
            // Senha: nunca sobrescrever se já existir
            if (isset($dadosUsuario['senha']) && isset($current['senha']) && !$isEmpty($current['senha'])) {
                unset($dadosUsuario['senha']);
            }

            if (!empty($dadosUsuario)) {
                $helper->atualizarUsuario($usuarioId, $dadosUsuario);
            }
        } else {
            $usuarioId = (int) $helper->criarUsuario($dadosUsuario);
        }

        if ($usuarioId > 0 && $wooWalletBalance !== '') {
            $raw = (string) $wooWalletBalance;
            $raw = preg_replace('/\s+/', '', $raw);
            $raw = str_replace(['R$', 'USD', 'BRL'], '', $raw);
            $raw = trim($raw);
            if ($raw !== '') {
                $num = $raw;
                if (strpos($num, ',') !== false && strpos($num, '.') !== false) {
                    $num = str_replace('.', '', $num);
                    $num = str_replace(',', '.', $num);
                } elseif (strpos($num, ',') !== false) {
                    $num = str_replace(',', '.', $num);
                }
                $balance = is_numeric($num) ? (float) $num : null;
                if ($balance !== null) {
                    try {
                        $stIns = $pdo->prepare('INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)');
                        $stIns->execute([(int) $usuarioId]);
                        $stUp = $pdo->prepare('UPDATE carteiras SET saldo_usd = ?, updated_at = NOW() WHERE usuario_id = ?');
                        $stUp->execute([$balance, (int) $usuarioId]);
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        $addr = $this->pickEnderecoFromRowMapped($get);
        if ($usuarioId > 0 && !empty($addr)) {
            $this->salvarEnderecoPrincipal($pdo, $usuarioId, $addr);
        }
        $this->atualizarCamposUsuarioEnderecoSeExistir($pdo, $usuarioId, $addr ?? [], $billingBirth);
    }

    private function pickEnderecoFromRowMapped(callable $get): array {
        $billing = [
            'endereco' => $get(6),
            'complemento' => $get(7),
            'cidade' => $get(8),
            'cep' => $get(9),
            'pais' => $get(10),
            'estado' => $get(11),
            'numero' => $get(23),
            'bairro' => $get(24),
        ];

        $shipping = [
            'endereco' => $get(14),
            'complemento' => $get(15),
            'cidade' => $get(16),
            'cep' => $get(17),
            'pais' => $get(18),
            'estado' => $get(19),
            'numero' => $get(26),
            'bairro' => $get(27),
        ];

        $hasBilling = false;
        foreach (['endereco','cidade','cep'] as $k) {
            if (!empty($billing[$k])) { $hasBilling = true; break; }
        }
        $src = $hasBilling ? $billing : $shipping;
        $src['tipo'] = $hasBilling ? 'cobranca' : 'entrega';
        foreach ($src as $k => $v) {
            if ($v === '') unset($src[$k]);
        }
        return $src;
    }

    private function importarUsuariosCsv(\PDO $pdo): array {
        $ok = 0;
        $fail = 0;

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if (!isset($_FILES['usuarios_import_csv']) || empty($_FILES['usuarios_import_csv']['tmp_name'])) {
            return ['ok' => 0, 'fail' => 1];
        }
        if (!empty($_FILES['usuarios_import_csv']['error']) && $_FILES['usuarios_import_csv']['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => 0, 'fail' => 1];
        }

        $tmp = (string) $_FILES['usuarios_import_csv']['tmp_name'];
        $fh = @fopen($tmp, 'r');
        if (!$fh) {
            return ['ok' => 0, 'fail' => 1];
        }

        $expected = [
            'ID','User Email','User Login','First Name','Last Name','Billing Company','Billing Address 1','Billing Address 2','Billing City','Billing Postcode','Billing Country','Billing State','Billing Phone','Shipping Company','Shipping Address 1','Shipping Address 2','Shipping City','Shipping Postcode','Shipping Country','Shipping State','suite','billing_cpf','billing_birthdate','billing_number','billing_neighborhood','billing_cellphone','shipping_number','shipping_neighborhood','shipping_suite','billing_cnpj','_current_woo_wallet_balance','User Role','User Pass'
        ];

        $first = fgetcsv($fh, 0, ',');
        $delimiter = ',';
        if (is_array($first) && count($first) === 1) {
            rewind($fh);
            $first = fgetcsv($fh, 0, ';');
            $delimiter = ';';
        }

        $normalizeHeader = function($v) {
            $s = trim((string) $v);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        $header = is_array($first) ? array_map($normalizeHeader, $first) : [];
        $headerMap = null;
        $isHeader = false;
        if (!empty($header)) {
            $map = [];
            foreach ($header as $idx => $name) {
                $key = (string) $name;
                if ($key === '') continue;
                $map[$key] = (int) $idx;
            }
            $okHeader = true;
            foreach ($expected as $col) {
                if (!array_key_exists($col, $map)) {
                    $okHeader = false;
                    break;
                }
            }
            if ($okHeader) {
                $isHeader = true;
                $headerMap = $map;
            }
        }
        if (!$isHeader) {
            rewind($fh);
        }

        $helper = new \App\Controllers\AdminUsuariosHelper();

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 5) {
                continue;
            }

            if ($isHeader && is_array($headerMap)) {
                $ordered = [];
                foreach ($expected as $col) {
                    $idx = $headerMap[$col] ?? null;
                    $ordered[] = ($idx !== null && array_key_exists($idx, $row)) ? (string) $row[$idx] : '';
                }
                $row = $ordered;
            }
            $row = array_pad($row, count($expected), '');
            try {
                $this->processUsuarioRow($pdo, $helper, $row);
                $ok++;
            } catch (\Exception $e) {
                $fail++;
            }

            if ((($ok + $fail) % 200) === 0) {
                @set_time_limit(0);
            }
        }

        fclose($fh);
        return ['ok' => $ok, 'fail' => $fail];
    }

    private function findUsuarioIdById(\PDO $pdo, int $id): int {
        if ($id <= 0) return 0;
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function findUsuarioIdByEmail(\PDO $pdo, string $email): int {
        $email = trim($email);
        if ($email === '') return 0;
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function findUsuarioIdByDocumento(\PDO $pdo, string $documento): int {
        $documento = trim((string) $documento);
        if ($documento === '') return 0;

        // Normalizar para buscar tanto formatado quanto não formatado
        $docDigits = preg_replace('/[^0-9]/', '', $documento);
        $doc = $docDigits !== '' ? $docDigits : $documento;

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE usuarios');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }
        if (empty($cols)) return 0;

        $cDocumento = in_array('documento', $cols, true) ? 'documento' : null;
        $cCpf = in_array('cpf', $cols, true) ? 'cpf' : null;

        try {
            if ($cDocumento) {
                // comparar em digits também, removendo pontuação
                $sql = "SELECT id FROM usuarios WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER({$cDocumento}),'.',''),'-',''),'/',''),' ','') ,',','') = LOWER(?) LIMIT 1";
                $st = $pdo->prepare($sql);
                $st->execute([$doc]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            }
        } catch (\Exception $e) {
        }

        try {
            if ($cCpf) {
                $sql = "SELECT id FROM usuarios WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER({$cCpf}),'.',''),'-',''),'/',''),' ','') ,',','') = LOWER(?) LIMIT 1";
                $st = $pdo->prepare($sql);
                $st->execute([$doc]);
                return (int) ($st->fetchColumn() ?: 0);
            }
        } catch (\Exception $e) {
        }

        return 0;
    }

    private function pickEnderecoFromRow(array $row): array {
        $get = function(int $idx) use ($row) {
            return trim((string) ($row[$idx] ?? ''));
        };

        $billing = [
            'endereco' => $get(6),
            'complemento' => $get(7),
            'cidade' => $get(8),
            'cep' => $get(9),
            'pais' => $get(10),
            'estado' => $get(11),
            'numero' => $get(23),
            'bairro' => $get(24),
        ];

        $shipping = [
            'endereco' => $get(14),
            'complemento' => $get(15),
            'cidade' => $get(16),
            'cep' => $get(17),
            'pais' => $get(18),
            'estado' => $get(19),
            'numero' => $get(26),
            'bairro' => $get(27),
        ];

        $hasBilling = false;
        foreach (['endereco','cidade','cep'] as $k) {
            if (!empty($billing[$k])) { $hasBilling = true; break; }
        }
        $src = $hasBilling ? $billing : $shipping;

        $src['tipo'] = $hasBilling ? 'cobranca' : 'entrega';

        foreach ($src as $k => $v) {
            if ($v === '') {
                unset($src[$k]);
            }
        }
        return $src;
    }

    private function salvarEnderecoPrincipal(\PDO $pdo, int $usuarioId, array $dados): void {
        if ($usuarioId <= 0) {
            return;
        }

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE enderecos');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }
        if (empty($cols)) {
            return;
        }

        $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
        if ($usuarioCol === '') {
            return;
        }
        $principalCol = in_array('principal', $cols, true) ? 'principal' : (in_array('is_principal', $cols, true) ? 'is_principal' : '');

        $payload = [];
        $map = [
            'tipo' => ['tipo'],
            'cep' => ['cep'],
            'endereco' => ['endereco', 'logradouro'],
            'numero' => ['numero'],
            'complemento' => ['complemento'],
            'bairro' => ['bairro'],
            'cidade' => ['cidade'],
            'estado' => ['estado', 'uf'],
            'pais' => ['pais'],
        ];
        foreach ($map as $inputKey => $cands) {
            $val = isset($dados[$inputKey]) ? trim((string) $dados[$inputKey]) : '';
            if ($val === '') continue;
            foreach ($cands as $col) {
                if (in_array($col, $cols, true)) {
                    $payload[$col] = $val;
                    break;
                }
            }
        }
        if ($principalCol !== '') {
            $payload[$principalCol] = 1;
        }
        if (empty($payload)) {
            return;
        }

        try {
            $sqlSel = 'SELECT id FROM enderecos WHERE ' . $usuarioCol . ' = :uid' . ($principalCol !== '' ? (' AND ' . $principalCol . ' = 1') : '') . ' ORDER BY id DESC LIMIT 1';
            $stSel = $pdo->prepare($sqlSel);
            $stSel->bindValue(':uid', $usuarioId, \PDO::PARAM_INT);
            $stSel->execute();
            $existingId = (int) ($stSel->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $existingRow = [];
                try {
                    $stRow = $pdo->prepare('SELECT * FROM enderecos WHERE id = :id LIMIT 1');
                    $stRow->execute([':id' => $existingId]);
                    $existingRow = $stRow->fetch(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) {
                    $existingRow = [];
                }

                $isEmpty = function($v): bool {
                    if ($v === null) return true;
                    if (is_string($v)) return trim($v) === '';
                    return false;
                };

                $set = [];
                $params = [':id' => $existingId];
                foreach ($payload as $col => $val) {
                    // não sobrescrever campos já preenchidos
                    if (array_key_exists($col, $existingRow) && !$isEmpty($existingRow[$col] ?? null)) {
                        continue;
                    }
                    $set[] = $col . ' = :' . $col;
                    $params[':' . $col] = $val;
                }
                if (!empty($set)) {
                    $sqlUp = 'UPDATE enderecos SET ' . implode(', ', $set) . ' WHERE id = :id';
                    $stUp = $pdo->prepare($sqlUp);
                    $stUp->execute($params);
                }
            } else {
                $colsIns = [$usuarioCol];
                $valsIns = [':uid'];
                $params = [':uid' => $usuarioId];
                foreach ($payload as $col => $val) {
                    $colsIns[] = $col;
                    $valsIns[] = ':' . $col;
                    $params[':' . $col] = $val;
                }
                $sqlIn = 'INSERT INTO enderecos (' . implode(', ', $colsIns) . ') VALUES (' . implode(', ', $valsIns) . ')';
                $stIn = $pdo->prepare($sqlIn);
                $stIn->execute($params);
                $existingId = (int) $pdo->lastInsertId();
            }

            if ($principalCol !== '' && $existingId > 0) {
                $st = $pdo->prepare('UPDATE enderecos SET ' . $principalCol . ' = 0 WHERE ' . $usuarioCol . ' = :uid AND id <> :id');
                $st->execute([':uid' => $usuarioId, ':id' => $existingId]);
            }
        } catch (\Exception $e) {
        }
    }

    private function atualizarCamposUsuarioEnderecoSeExistir(\PDO $pdo, int $usuarioId, array $endereco, string $birthdate): void {
        if ($usuarioId <= 0) return;
        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE usuarios');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }
        if (empty($cols)) return;

        $current = [];
        try {
            $stCur = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
            $stCur->execute([$usuarioId]);
            $current = $stCur->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $current = [];
        }

        $isEmpty = function($v): bool {
            if ($v === null) return true;
            if (is_string($v)) return trim($v) === '';
            return false;
        };

        $set = [];
        $params = [];

        $map = [
            'cep' => 'cep',
            'endereco' => 'endereco',
            'numero' => 'numero',
            'bairro' => 'bairro',
            'cidade' => 'cidade',
            'estado' => 'estado',
        ];
        foreach ($map as $k => $col) {
            if (!empty($endereco[$k]) && in_array($col, $cols, true)) {
                if (array_key_exists($col, $current) && !$isEmpty($current[$col] ?? null)) {
                    continue;
                }
                $set[] = $col . ' = ?';
                $params[] = (string) $endereco[$k];
            }
        }

        $birth = trim((string) $birthdate);
        if ($birth !== '') {
            $colBirth = in_array('data_nascimento', $cols, true) ? 'data_nascimento' : (in_array('birthdate', $cols, true) ? 'birthdate' : '');
            if ($colBirth !== '') {
                if (array_key_exists($colBirth, $current) && !$isEmpty($current[$colBirth] ?? null)) {
                    // não sobrescrever data já preenchida
                    // segue sem setar
                } else {
                $norm = trim((string) $birth);
                $norm = preg_replace('/\s+/', '', $norm);

                $dt = null;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $norm)) {
                    $dt = $norm;
                } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $norm, $m)) {
                    $dt = $m[3] . '-' . $m[2] . '-' . $m[1];
                } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $norm, $m)) {
                    $dt = $m[3] . '-' . $m[2] . '-' . $m[1];
                } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $norm, $m)) {
                    $dt = $m[1] . '-' . $m[2] . '-' . $m[3];
                }
                if ($dt !== null) {
                    $set[] = $colBirth . ' = ?';
                    $params[] = $dt;
                }
                }
            }
        }

        if (empty($set)) return;
        try {
            $params[] = $usuarioId;
            $stUp = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stUp->execute($params);
        } catch (\Exception $e) {
        }
    }
    
    public function testarSigep(Request $request) {
        try {
            $pdo = Database::getConnection();
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];

            $get = function(string $cat, string $key, string $default = '') use ($pdo, $tableInfo, $table): string {
                try {
                    $mode = (string) ($tableInfo['mode'] ?? '');
                    if ($mode === 'single_row') {
                        $cols = [];
                        try {
                            $st = $pdo->query('DESCRIBE ' . $table);
                            $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                        } catch (\Exception $e) {
                            $cols = [];
                        }

                        $col = null;
                        $map = $tableInfo['columnMap'] ?? [];
                        if (isset($map[$cat]) && isset($map[$cat][$key])) {
                            $col = $map[$cat][$key];
                        } else {
                            $guess = $key;
                            if (in_array($guess, $cols, true)) {
                                $col = $guess;
                            }
                        }

                        if (!$col || !preg_match('/^[a-zA-Z0-9_]+$/', (string) $col)) {
                            return $default;
                        }

                        $idCol = (string) ($tableInfo['idCol'] ?? 'id');
                        $stmt = $pdo->query('SELECT ' . $col . ' FROM ' . $table . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                        $v = $stmt->fetchColumn();
                        return ($v === false || $v === null) ? $default : (string) $v;
                    }

                    if ($mode === 'categoria_chave') {
                        $catCol = $tableInfo['categoriaCol'];
                        $keyCol = $tableInfo['chaveCol'];
                        $valCol = $tableInfo['valueCol'];
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ? LIMIT 1');
                        $stmt->execute([$cat, $key]);
                        $v = $stmt->fetchColumn();
                        return ($v === false || $v === null) ? $default : (string) $v;
                    }

                    $keyCol = $tableInfo['keyCol'];
                    $valCol = $tableInfo['valueCol'];
                    $fullKey = $cat . '_' . $key;
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$fullKey]);
                    $v = $stmt->fetchColumn();
                    return ($v === false || $v === null) ? $default : (string) $v;
                } catch (\Exception $e) {
                    return $default;
                }
            };

            $enabled = $get('entrega', 'sigep_enabled', '0');
            if ($enabled !== '1') {
                echo json_encode(['success' => false, 'error' => 'SIGEP está desabilitado nas configurações.']);
                exit;
            }

            $ambiente = $get('entrega', 'sigep_ambiente', 'homologacao');
            $usuario = $get('entrega', 'sigep_usuario', '');
            $senha = $get('entrega', 'sigep_senha', '');
            $cnpj = $get('entrega', 'sigep_cnpj', '');
            $servicoCodigo = $get('entrega', 'sigep_servico_codigo', '');
            $contrato = $get('entrega', 'sigep_numero_contrato', '');
            $cartao = $get('entrega', 'sigep_cartao_postagem', '');

            if ($usuario === '' || $senha === '' || $contrato === '' || $cartao === '' || $servicoCodigo === '') {
                echo json_encode(['success' => false, 'error' => 'Preencha usuário, senha, contrato, cartão de postagem e código do serviço.']);
                exit;
            }

            if (!class_exists('\\SoapClient')) {
                echo json_encode(['success' => false, 'error' => 'Extensão SOAP não disponível no PHP do servidor.']);
                exit;
            }

            $amb = strtolower(trim((string) $ambiente));
            $wsdl = ($amb === 'producao' || $amb === 'production')
                ? 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl'
                : 'https://apphom.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl';

            $localWsdl = __DIR__ . '/../Resources/wsdl/AtendeCliente.wsdl';
            if (is_file($localWsdl)) {
                $wsdl = $localWsdl;
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'protocol_version' => 1.1,
                    'ignore_errors' => true,
                    'header' => "Connection: close\r\n"
                        . "Accept: text/xml, application/xml;q=0.9, */*;q=0.8\r\n"
                        . "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) brz-sigep/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            try {
                $client = new \SoapClient($wsdl, [
                    'exceptions' => true,
                    'trace' => false,
                    'cache_wsdl' => WSDL_CACHE_BOTH,
                    'connection_timeout' => 20,
                    'stream_context' => $context,
                    'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                ]);
            } catch (\Throwable $e) {
                $extra = [];
                $extra[] = 'allow_url_fopen=' . (ini_get('allow_url_fopen') ? '1' : '0');
                $extra[] = 'openssl.cafile=' . (string) ini_get('openssl.cafile');
                $extra[] = 'curl.cainfo=' . (string) ini_get('curl.cainfo');
                throw new \Exception('SIGEP falhou ao carregar WSDL: ' . $e->getMessage() . ' | ' . implode(', ', $extra));
            }

            $params = [
                'tipoDestinatario' => 'C',
                'identificador' => $cnpj,
                'idServico' => $servicoCodigo,
                'qtdEtiquetas' => 1,
                'usuario' => $usuario,
                'senha' => $senha,
            ];

            $resp = $client->__soapCall('solicitaEtiquetas', [$params]);

            echo json_encode([
                'success' => true,
                'ambiente' => $ambiente,
                'response' => $resp,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function getEntregaJS() {
        ob_start();
        ?>
        <script>
        function testarSigepAPI() {
            fetch('/admin/configuracoes/testar-sigep', { method: 'POST' })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (response.ok && data.success) {
                        alert('✅ SIGEP OK (' + (data.ambiente || '') + ')\n\nResposta: ' + JSON.stringify(data.response));
                    } else {
                        alert('❌ SIGEP falhou: ' + (data.error || JSON.stringify(data)));
                    }
                })
                .catch(err => alert('❌ Erro ao testar SIGEP: ' + err.message));
        }
        </script>
        <?php
        return ob_get_clean();
    }

    // JavaScript para pagamentos
    private function getPagamentosJS() {
        ob_start();
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            try {
                var btn = document.getElementById('v-pills-pagamentos-tab');
                var pane = document.getElementById('v-pills-pagamentos');
                if (!btn || !pane) {
                    return;
                }
                btn.addEventListener('click', function(){
                    try {
                        // Se o Bootstrap não ativar a aba (por algum erro de JS/DOM), aplicamos o fallback.
                        window.setTimeout(function(){
                            try {
                                var alreadyActive = pane.classList.contains('active') && pane.classList.contains('show');
                                if (!alreadyActive) {
                                    var panes = document.querySelectorAll('#v-pills-tabContent .tab-pane');
                                    panes.forEach(function(p){
                                        p.classList.remove('active');
                                        p.classList.remove('show');
                                    });
                                    var links = document.querySelectorAll('#v-pills-tab .nav-link');
                                    links.forEach(function(l){
                                        l.classList.remove('active');
                                    });
                                    btn.classList.add('active');
                                    pane.classList.add('active');
                                    pane.classList.add('show');
                                }

                                // Mantém o conteúdo no topo ao trocar a aba.
                                try {
                                    if (typeof window.scrollTo === 'function') {
                                        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
                                    } else {
                                        window.scrollTo(0, 0);
                                    }
                                } catch (e) {}
                            } catch (e) {}
                        }, 0);
                    } catch (e) {}
                });
            } catch (e) {}
        });

        function togglePasswordVisibility(button) {
            const input = button.previousElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function testarAsaasAPI() {
            const apiKeyEl = document.querySelector('input[name="pagamentos_asaas_api_key"]');
            const ambEl = document.querySelector('select[name="pagamentos_asaas_ambiente"]');
            const apiKey = apiKeyEl ? apiKeyEl.value : '';
            const ambiente = ambEl ? ambEl.value : 'sandbox';
            
            if (!apiKey) {
                alert('Digite a API Key do Asaas primeiro');
                return;
            }
            
            // URL da API do Asaas
            const url = ambiente === 'production' ? 'https://www.asaas.com/api/v3/myAccount' : 'https://sandbox.asaas.com/api/v3/myAccount';
            
            fetch(url, {
                headers: {
                    'access_token': apiKey,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    alert('✅ Conexão com Asaas bem-sucedida!\n\nConta: ' + data.name + '\nAmbiente: ' + ambiente);
                } else {
                    alert('❌ Erro na conexão com Asaas: ' + (data.errors?.[0]?.description || 'Verifique sua API Key'));
                }
            })
            .catch(error => {
                alert('❌ Erro ao testar conexão: ' + error.message);
            });
        }
        
        function testarStripeAPI() {
            const pkEl = document.querySelector('input[name="pagamentos_stripe_publishable_key"]');
            const skEl = document.querySelector('input[name="pagamentos_stripe_secret_key"]');
            const ambEl = document.querySelector('select[name="pagamentos_stripe_ambiente"]');
            const publishableKey = pkEl ? pkEl.value : '';
            const secretKey = skEl ? skEl.value : '';
            const ambiente = ambEl ? ambEl.value : 'test';
            
            if (!publishableKey || !secretKey) {
                alert('Digite as chaves do Stripe primeiro');
                return;
            }
            
            // Testar com a API do Stripe (usando a chave secreta)
            fetch('https://api.stripe.com/v1/account', {
                headers: {
                    'Authorization': 'Bearer ' + secretKey,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    alert('✅ Conexão com Stripe bem-sucedida!\n\nConta: ' + (data.business_profile?.name || data.display_name) + '\nAmbiente: ' + ambiente);
                } else {
                    alert('❌ Erro na conexão com Stripe: ' + (data.error?.message || 'Verifique suas chaves'));
                }
            })
            .catch(error => {
                alert('❌ Erro ao testar conexão: ' + error.message);
            });
        }
        
        function verDocumentacaoAsaas() {
            window.open('https://docs.asaas.com/reference/introduction', '_blank');
        }
        
        function verDocumentacaoStripe() {
            window.open('https://stripe.com/docs/api', '_blank');
        }
        </script>
        <?php
        return ob_get_clean();
    }

    private function getNotificacoesJS() {
        ob_start();
        ?>
        <script>
        function syncSalvarGeralVisibilityNotificacoes() {
            const btnContainer = document.getElementById('admin-config-salvar-geral');
            if (!btnContainer) {
                return;
            }

            const tabPane = document.getElementById('v-pills-notificacoes');
            const isActive = !!(tabPane && tabPane.classList.contains('active') && tabPane.classList.contains('show'));
            btnContainer.style.display = isActive ? 'none' : '';
        }

        function getNotificacoesFormData() {
            const container = document.getElementById('formNotificacoes');
            const formData = new FormData();
            if (!container) {
                return formData;
            }

            const fields = container.querySelectorAll('input[name], select[name], textarea[name]');
            fields.forEach(el => {
                if (el.type === 'checkbox') {
                    formData.set(el.name, el.checked ? '1' : '0');
                } else {
                    formData.set(el.name, el.value || '');
                }
            });
            return formData;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tabBtn = document.getElementById('v-pills-notificacoes-tab');
            if (tabBtn) {
                tabBtn.addEventListener('shown.bs.tab', syncSalvarGeralVisibilityNotificacoes);
                tabBtn.addEventListener('hidden.bs.tab', syncSalvarGeralVisibilityNotificacoes);
            }
            syncSalvarGeralVisibilityNotificacoes();

            const eventoSelect = document.querySelector('#formNotificacoes select[name="evento"]');
            if (eventoSelect) {
                eventoSelect.addEventListener('change', function() {
                    carregarNotificacaoPorEvento(eventoSelect.value);
                });
                if (eventoSelect.value) {
                    carregarNotificacaoPorEvento(eventoSelect.value);
                }
            }
        });

        function carregarNotificacaoPorEvento(evento) {
            const container = document.getElementById('formNotificacoes');
            if (!container) {
                return;
            }
            if (!evento) {
                const urlEl = container.querySelector('input[name="webhook_url"]');
                const metodoEl = container.querySelector('select[name="webhook_method"]');
                const headersEl = container.querySelector('textarea[name="webhook_headers"]');
                const camposEl = container.querySelector('textarea[name="webhook_campos"]');
                const tplEl = container.querySelector('textarea[name="webhook_template"]');
                const ativoEl = container.querySelector('input[name="webhook_ativo"]');
                const retriesEl = container.querySelector('input[name="webhook_retries"]');
                if (urlEl) urlEl.value = '';
                if (metodoEl) metodoEl.value = 'POST';
                if (headersEl) headersEl.value = '';
                if (camposEl) camposEl.value = '';
                if (tplEl) tplEl.value = '';
                if (ativoEl) ativoEl.checked = true;
                if (retriesEl) retriesEl.checked = true;
                return;
            }

            const params = new URLSearchParams();
            params.set('evento', evento);

            fetch('/admin/notificacao?' + params.toString())
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success || !data.notificacao) {
                        throw new Error(data.error || 'Falha ao carregar configuração');
                    }

                    const n = data.notificacao;
                    const urlEl = container.querySelector('input[name="webhook_url"]');
                    const metodoEl = container.querySelector('select[name="webhook_method"]');
                    const headersEl = container.querySelector('textarea[name="webhook_headers"]');
                    const camposEl = container.querySelector('textarea[name="webhook_campos"]');
                    const tplEl = container.querySelector('textarea[name="webhook_template"]');
                    const ativoEl = container.querySelector('input[name="webhook_ativo"]');
                    const retriesEl = container.querySelector('input[name="webhook_retries"]');

                    if (urlEl) urlEl.value = n.url || '';
                    if (metodoEl) metodoEl.value = (n.metodo || 'POST').toUpperCase();
                    if (headersEl) headersEl.value = n.headers || '';
                    if (camposEl) camposEl.value = n.campos || '';
                    if (tplEl) tplEl.value = n.template || '';
                    if (ativoEl) ativoEl.checked = (n.ativo || '1') === '1';
                    if (retriesEl) retriesEl.checked = (n.retries || '1') === '1';
                })
                .catch(() => {
                });
        }

        function salvarNotificacaoAdmin() {
            const formData = getNotificacoesFormData();

            fetch('/admin/salvar-notificacao', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Configuração de notificação salva com sucesso!');
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao salvar configuração: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error.message);
            });
        }

        function testarWebhookNotificacoes() {
            const evento = document.querySelector('#formNotificacoes select[name="evento"]').value;
            if (!evento) {
                alert('Selecione um evento.');
                return;
            }

            const formData = new FormData();
            formData.set('evento', evento);

            fetch('/admin/testar-webhook', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Webhook testado com sucesso!\n\nResposta: ' + JSON.stringify(data, null, 2));
                } else {
                    alert('Erro ao testar webhook: ' + (data.error || JSON.stringify(data)));
                }
                carregarLogsWebhookNotificacoes();
            })
            .catch(error => {
                alert('Erro ao testar webhook: ' + error.message);
                carregarLogsWebhookNotificacoes();
            });
        }

        function carregarLogsWebhookNotificacoes() {
            fetch('/admin/logs-webhook')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('notificacoes-logs-webhook');
                    tbody.innerHTML = '';

                    if (data.logs && data.logs.length > 0) {
                        data.logs.forEach(log => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${new Date(log.data_envio).toLocaleString('pt-BR')}</td>
                                <td><span class="badge bg-${log.status == 'sucesso' ? 'success' : 'danger'}">${log.status}</span></td>
                                <td><small>${log.resposta || 'Sem resposta'}</small></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesLogNotificacoes(${log.id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="excluirLogWebhookNotificacoes(${log.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum log encontrado</td></tr>';
                    }
                })
                .catch(() => {
                });
        }

        function excluirLogWebhookNotificacoes(logId) {
            if (!confirm('Deseja excluir este log?')) {
                return;
            }

            fetch(`/admin/log-webhook/${logId}/excluir`, {
                method: 'POST'
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao excluir log: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao excluir log: ' + error.message);
            });
        }

        function limparLogsWebhookNotificacoes() {
            if (!confirm('Deseja limpar todos os logs?')) {
                return;
            }

            fetch('/admin/logs-webhook/limpar', {
                method: 'POST'
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao limpar logs: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao limpar logs: ' + error.message);
            });
        }

        function verDetalhesLogNotificacoes(logId) {
            fetch(`/admin/log-webhook/${logId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.log) {
                        alert(`Detalhes do Log #${logId}:\n\n` +
                            `Data: ${new Date(data.log.data_envio).toLocaleString('pt-BR')}\n` +
                            `Status: ${data.log.status}\n` +
                            `URL: ${data.log.webhook_url}\n` +
                            `Método: ${data.log.metodo}\n` +
                            `Headers: ${data.log.headers}\n` +
                            `Payload: ${data.log.payload}\n` +
                            `Resposta: ${data.log.resposta}`);
                    }
                });
        }

        function formatarJSONNotificacoes(textarea) {
            try {
                const value = textarea.value;
                const formatted = JSON.stringify(JSON.parse(value), null, 2);
                textarea.value = formatted;
            } catch (e) {
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tab = document.getElementById('v-pills-notificacoes-tab');
            if (tab) {
                tab.addEventListener('shown.bs.tab', function() {
                    carregarLogsWebhookNotificacoes();
                });
            }

            const headersTextarea = document.querySelector('#formNotificacoes textarea[name="webhook_headers"]');
            const camposTextarea = document.querySelector('#formNotificacoes textarea[name="webhook_campos"]');

            if (headersTextarea) {
                headersTextarea.addEventListener('blur', function() {
                    formatarJSONNotificacoes(this);
                });
            }
            if (camposTextarea) {
                camposTextarea.addEventListener('blur', function() {
                    formatarJSONNotificacoes(this);
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    // JavaScript para o criador de e-mail
    private function getEmailCreatorJS() {
        ob_start();
        ?>
        <script>
        // Variáveis disponíveis por evento
        const variaveisEvento = {
            "novo_pedido": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{cliente_nome}}": "Nome do Cliente",
                "{{cliente_email}}": "Email do Cliente",
                "{{valor_total}}": "Total do Pedido",
                "{{moeda}}": "Moeda",
                "{{data_pedido}}": "Data do Pedido",
                "{{itens}}": "Lista de Itens",
                "{{endereco_entrega}}": "Endereço de Entrega"
            },
            "pedido_aprovado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{cliente_nome}}": "Nome do Cliente",
                "{{data_aprovacao}}": "Data de Aprovação",
                "{{valor_total}}": "Total do Pedido"
            },
            "pedido_enviado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{codigo_rastreamento}}": "Código de Rastreamento",
                "{{data_envio}}": "Data de Envio",
                "{{transportadora}}": "Transportadora"
            },
            "pedido_entregue": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{data_entrega}}": "Data de Entrega",
                "{{recebedor}}": "Quem Recebeu"
            },
            "pedido_cancelado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{motivo_cancelamento}}": "Motivo do Cancelamento",
                "{{data_cancelamento}}": "Data do Cancelamento"
            },
            "novo_usuario": {
                "{{usuario_nome}}": "Nome do Usuário",
                "{{usuario_email}}": "Email do Usuário",
                "{{data_cadastro}}": "Data de Cadastro",
                "{{token_confirmacao}}": "Token de Confirmação"
            },
            "recuperar_senha": {
                "{{usuario_nome}}": "Nome do Usuário",
                "{{usuario_email}}": "Email do Usuário",
                "{{token_reset}}": "Token de Reset",
                "{{data_solicitacao}}": "Data da Solicitação"
            },
            "contato_contato": {
                "{{nome_contato}}": "Nome do Contato",
                "{{email_contato}}": "Email do Contato",
                "{{mensagem}}": "Mensagem",
                "{{data_contato}}": "Data do Contato"
            },
            "carne_criado": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{cliente_email}}": "Email do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{total_geral}}": "Valor Total do Carnê",
                "{{quantidade_parcelas}}": "Quantidade de Parcelas",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_cobranca": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{cliente_email}}": "Email do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{numero_parcela}}": "Número da Parcela",
                "{{total_parcelas}}": "Total de Parcelas",
                "{{valor_parcela}}": "Valor da Parcela",
                "{{valor_produtos}}": "Valor Produtos",
                "{{valor_taxas}}": "Valor Taxas",
                "{{vencimento}}": "Data de Vencimento",
                "{{status_parcela}}": "Status da Parcela",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_parcela_proxima_vencimento": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{numero_parcela}}": "Número da Parcela",
                "{{valor_parcela}}": "Valor da Parcela",
                "{{vencimento}}": "Data de Vencimento",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_pagamento_confirmado": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{numero_parcela}}": "Número da Parcela",
                "{{valor_parcela}}": "Valor da Parcela",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_quitado": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{total_geral}}": "Valor Total do Carnê",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_envio_liberado": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_aviso_cancelamento": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{dias_para_cancelamento}}": "Dias para Cancelamento",
                "{{url_meu_carne}}": "Link para o Carnê"
            },
            "carne_cancelado": {
                "{{cliente_nome}}": "Nome do Cliente",
                "{{carne_id}}": "ID do Carnê",
                "{{pedido_id}}": "ID do Pedido",
                "{{motivo_cancelamento}}": "Motivo do Cancelamento",
                "{{url_meu_carne}}": "Link para o Carnê"
            }
        };
        
        function carregarVariaveis() {
            const evento = document.getElementById("evento_tipo").value;
            const variaveisDiv = document.getElementById("variaveis_disponiveis");
            
            if (!evento || !variaveisEvento[evento]) {
                variaveisDiv.innerHTML = "<small class=\"text-muted\">Selecione um evento para ver as variáveis disponíveis</small>";
                return;
            }
            
            let html = "<div class=\"mb-2\"><strong>Variáveis disponíveis:</strong></div>";
            for (const [variavel, descricao] of Object.entries(variaveisEvento[evento])) {
                html += "<div class=\"mb-1\">";
                html += "<code class=\"bg-light p-1 rounded\" style=\"cursor: pointer; font-size: 12px;\" onclick=\"inserirVariavelNoCursor('" + variavel + "')\">" + variavel + "</code>";
                html += "<small class=\"text-muted ms-2\">" + descricao + "</small>";
                html += "</div>";
            }
            
            variaveisDiv.innerHTML = html;
        }
        
        function inserirVariavelNoCursor(variavel) {
            const textarea = document.getElementById("email_conteudo");
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variavel + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + variavel.length;
            textarea.focus();
        }
        
        function inserirVariavel() {
            const evento = document.getElementById("evento_tipo").value;
            if (!evento) {
                alert("Selecione um evento primeiro");
                return;
            }
            
            const variaveis = Object.keys(variaveisEvento[evento]);
            if (variaveis.length === 0) return;
            
            const variavel = prompt("Variáveis disponíveis:\n" + variaveis.join("\n"), variaveis[0]);
            if (variavel) {
                inserirVariavelNoCursor(variavel);
            }
        }
        
        function previsualizarEmail() {
            const conteudo = document.getElementById("email_conteudo").value;
            const preview = document.getElementById("email_preview");
            const previewSection = document.getElementById("preview_section");
            
            if (!conteudo) {
                alert("Digite o conteúdo do e-mail primeiro");
                return;
            }
            
            // Criar HTML básico para preview
            const htmlCompleto = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Preview</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                    </style>
                </head>
                <body>
                    ${conteudo}
                </body>
                </html>
            `;
            
            preview.srcdoc = htmlCompleto;
            previewSection.style.display = "block";
        }
        
        function salvarTemplate() {
            const evento = document.getElementById("evento_tipo").value;
            const assunto = document.getElementById("email_assunto").value;
            const conteudo = document.getElementById("email_conteudo").value;
            
            if (!evento || !assunto || !conteudo) {
                alert("Preencha todos os campos");
                return;
            }
            
            const formData = new FormData();
            formData.set('evento', evento);
            formData.set('assunto', assunto);
            formData.set('corpo_html', conteudo);
            formData.set('ativo', '1');

            fetch('/admin/salvar-email-template', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Template salvo com sucesso!');
                    carregarTemplatesSalvos();
                } else {
                    alert('Erro ao salvar template: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error.message);
            });
        }
        
        function carregarTemplatesSalvos() {
            const div = document.getElementById("templates_salvos");

            div.innerHTML = "<small class=\"text-muted\">Carregando...</small>";

            fetch('/admin/email-templates')
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    div.innerHTML = "<small class=\"text-muted\">Erro ao carregar templates</small>";
                    return;
                }

                const templates = Array.isArray(data.templates) ? data.templates : [];
                if (templates.length === 0) {
                    div.innerHTML = "<small class=\"text-muted\">Nenhum template salvo ainda</small>";
                    return;
                }

                let html = "<div class=\"row\">";
                for (const tpl of templates) {
                    const evento = tpl.evento;
                    html += "<div class=\"col-md-4 mb-3\">";
                    html += "<div class=\"card\">";
                    html += "<div class=\"card-body\">";
                    html += "<h6 class=\"card-title\">" + (evento || '') + "</h6>";
                    html += "<p class=\"card-text\"><small>" + (tpl.assunto || '') + "</small></p>";
                    html += "<p class=\"card-text\"><small class=\"text-muted\">" + (tpl.updated_at || '') + "</small></p>";
                    html += "<div class=\"d-flex gap-2\">";
                    html += "<button type=\"button\" class=\"btn btn-sm btn-outline-primary\" onclick=\"carregarTemplate('" + evento + "')\">Carregar</button>";
                    html += "<button type=\"button\" class=\"btn btn-sm btn-outline-success\" onclick=\"testarTemplateEmail('" + evento + "')\">Testar</button>";
                    html += "</div>";
                    html += "</div>";
                    html += "</div>";
                    html += "</div>";
                }
                html += "</div>";
                div.innerHTML = html;
            })
            .catch(error => {
                div.innerHTML = "<small class=\"text-muted\">Erro ao carregar templates: " + error.message + "</small>";
            });
        }
        
        function carregarTemplate(evento) {
            const params = new URLSearchParams();
            params.set('evento', evento);

            fetch('/admin/email-template?' + params.toString())
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success || !data.template) {
                    alert('Erro ao carregar template: ' + (data.error || JSON.stringify(data)));
                    return;
                }
                const tpl = data.template;
                document.getElementById("evento_tipo").value = tpl.evento || evento;
                document.getElementById("email_assunto").value = tpl.assunto || '';
                document.getElementById("email_conteudo").value = tpl.corpo_html || '';
                carregarVariaveis();
            })
            .catch(error => {
                alert('Erro ao carregar template: ' + error.message);
            });
        }

        function testarTemplateEmail(evento) {
            const formData = new FormData();
            formData.set('evento', evento);

            const testTo = document.querySelector('input[name="email_test_to"]');
            if (testTo && testTo.value) {
                formData.set('to', testTo.value);
            }

            fetch('/admin/testar-email-template', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Email de teste enviado para: ' + (data.to || 'admin'));
                } else {
                    alert('Erro ao testar e-mail: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao testar e-mail: ' + error.message);
            });
        }
        
        // Carregar templates ao iniciar
        document.addEventListener("DOMContentLoaded", function() {
            carregarTemplatesSalvos();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function getConfigValue($config, $categoria, $chave, $default = '') {
        if (isset($config[$categoria]) && is_array($config[$categoria]) && array_key_exists($chave, $config[$categoria])) {
            return (string) $config[$categoria][$chave];
        }
        return $default;
    }

    private function getConfigTableInfo(\PDO $pdo): array {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        $cols = [];
        $types = [];

        $bestScore = -1;
        foreach ($tableCandidates as $t) {
            try {
                $stmtTable = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmtTable->execute([$t]);
                if (!$stmtTable->fetchColumn()) {
                    continue;
                }

                $stmtD = $pdo->query('DESCRIBE ' . $t);
                $describeRowsT = $stmtD ? ($stmtD->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                $colsT = [];
                $typesT = [];
                foreach ($describeRowsT as $r) {
                    $field = (string) ($r['Field'] ?? '');
                    if ($field === '') continue;
                    $colsT[] = $field;
                    $typesT[$field] = strtolower((string) ($r['Type'] ?? ''));
                }

                $hasCategoria = in_array('categoria', $colsT, true);
                $hasChave = in_array('chave', $colsT, true);
                $hasValor = in_array('valor', $colsT, true) || in_array('value', $colsT, true) || in_array('conteudo', $colsT, true) || in_array('content', $colsT, true) || in_array('config_value', $colsT, true);

                $hasKey = false;
                foreach (['chave','key','nome','config_key','configuracao','slug','parametro'] as $kc) {
                    if (in_array($kc, $colsT, true)) { $hasKey = true; break; }
                }

                $score = 0;
                if ($hasCategoria && $hasChave && $hasValor) {
                    $score = 3;
                } elseif ($hasKey && $hasValor) {
                    $score = 2;
                } elseif (in_array('id', $colsT, true)) {
                    $score = 1;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $table = $t;
                    $cols = $colsT;
                    $types = $typesT;
                }
            } catch (\Exception $e) {
            }
        }

        if (!$table) {
            throw new \Exception('Tabela de configurações não encontrada');
        }

        $keyCandidates = ['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'];
        $valueCandidates = ['valor', 'value', 'conteudo', 'content', 'config_value'];
        $updatedCandidates = ['updated_at', 'data_atualizacao', 'updated'];
        $idCandidates = ['id'];

        // Suporte para schema com categoria + chave + valor
        $hasCategoria = in_array('categoria', $cols, true);
        $hasChave = in_array('chave', $cols, true);

        // Schema de 1 linha com colunas diretas (ex: configuracoes_sistema legado)
        if (!$hasCategoria && !$hasChave && in_array('id', $cols, true)) {
            $paymentCols = [
                'asaas_enabled',
                'asaas_ambiente',
                'asaas_api_key',
                'stripe_enabled',
                'stripe_ambiente',
                'stripe_publishable_key',
                'stripe_secret_key',
                'cambioreal_enabled',
                'cambioreal_app_id',
                'cambioreal_app_secret',
                'cambioreal_base_url',
            ];

            $temAlguma = false;
            foreach ($paymentCols as $pc) {
                if (in_array($pc, $cols, true)) {
                    $temAlguma = true;
                    break;
                }
            }

            if ($temAlguma) {
                $updatedAtCol = '';
                foreach ($updatedCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $updatedAtCol = $c;
                        break;
                    }
                }

                $columnMap = [
                    'pagamentos' => [
                        'asaas_enabled' => 'asaas_enabled',
                        'asaas_ambiente' => 'asaas_ambiente',
                        'asaas_api_key' => 'asaas_api_key',
                        'stripe_enabled' => 'stripe_enabled',
                        'stripe_ambiente' => 'stripe_ambiente',
                        'stripe_publishable_key' => 'stripe_publishable_key',
                        'stripe_secret_key' => 'stripe_secret_key',
                        'cambioreal_enabled' => 'cambioreal_enabled',
                        'cambioreal_app_id' => 'cambioreal_app_id',
                        'cambioreal_app_secret' => 'cambioreal_app_secret',
                        'cambioreal_base_url' => 'cambioreal_base_url',
                    ],
                ];

                // Sistema: proteção por senha (pré-publicação)
                try {
                    $colEnabled = null;
                    foreach (['sistema_site_lock_enabled', 'site_lock_enabled'] as $c) {
                        if (in_array($c, $cols, true)) {
                            $colEnabled = $c;
                            break;
                        }
                    }
                    $colPass = null;
                    foreach (['sistema_site_lock_password', 'site_lock_password'] as $c) {
                        if (in_array($c, $cols, true)) {
                            $colPass = $c;
                            break;
                        }
                    }
                    if ($colEnabled || $colPass) {
                        $columnMap['sistema'] = $columnMap['sistema'] ?? [];
                        if ($colEnabled) {
                            $columnMap['sistema']['site_lock_enabled'] = $colEnabled;
                        }
                        if ($colPass) {
                            $columnMap['sistema']['site_lock_password'] = $colPass;
                        }
                    }

                    $colMode = null;
                    foreach (['sistema_site_lock_mode', 'site_lock_mode'] as $c) {
                        if (in_array($c, $cols, true)) {
                            $colMode = $c;
                            break;
                        }
                    }
                    $colBlocked = null;
                    foreach (['sistema_site_lock_blocked_paths', 'site_lock_blocked_paths'] as $c) {
                        if (in_array($c, $cols, true)) {
                            $colBlocked = $c;
                            break;
                        }
                    }
                    if ($colMode || $colBlocked) {
                        $columnMap['sistema'] = $columnMap['sistema'] ?? [];
                        if ($colMode) {
                            $columnMap['sistema']['site_lock_mode'] = $colMode;
                        }
                        if ($colBlocked) {
                            $columnMap['sistema']['site_lock_blocked_paths'] = $colBlocked;
                        }
                    }
                } catch (\Exception $e) {
                }

                // Layout (favicon, logos, banners) em schema single_row
                try {
                    $layoutCols = [
                        'layout_logo' => 'logo',
                        'layout_logo_footer' => 'logo_footer',
                        'layout_logo_admin' => 'logo_admin',
                        'layout_favicon' => 'favicon',
                        'layout_banners' => 'banners',
                        'layout_banners_en' => 'banners_en',
                    ];
                    foreach ($layoutCols as $col => $key) {
                        if (in_array($col, $cols, true)) {
                            $columnMap['layout'] = $columnMap['layout'] ?? [];
                            $columnMap['layout'][$key] = $col;
                        }
                    }
                } catch (\Exception $e) {
                }

                // Loja (conversão de moeda) em schema single_row
                try {
                    $lojaCols = [
                        'loja_conversao_moeda_ativa' => 'conversao_moeda_ativa',
                        'loja_nome' => 'nome',
                        'loja_descricao' => 'descricao',
                        'loja_email' => 'email',
                        'loja_telefone' => 'telefone',
                        'loja_endereco' => 'endereco',
                        'loja_logo' => 'logo',
                    ];
                    foreach ($lojaCols as $col => $key) {
                        if (in_array($col, $cols, true)) {
                            $columnMap['loja'] = $columnMap['loja'] ?? [];
                            $columnMap['loja'][$key] = $col;
                        }
                    }
                } catch (\Exception $e) {
                }

                if (in_array('webhook_link_pagamento_pedido_manual_url', $cols, true)) {
                    $columnMap['pagamentos']['webhook_link_pagamento_pedido_manual_url'] = 'webhook_link_pagamento_pedido_manual_url';
                }

                if (in_array('comissao_manual_faixas', $cols, true)) {
                    $columnMap['comissao'] = [
                        'manual_faixas' => 'comissao_manual_faixas',
                    ];
                }

                $emailMapCandidates = [
                    'driver' => ['email_driver'],
                    'host' => ['email_host', 'smtp_host'],
                    'port' => ['email_port', 'smtp_port'],
                    'username' => ['email_username', 'smtp_usuario', 'smtp_user'],
                    'password' => ['email_password', 'smtp_senha', 'smtp_pass'],
                    'encryption' => ['email_encryption', 'smtp_criptografia'],
                    'from' => ['email_from', 'email_remetente'],
                    'from_name' => ['email_from_name', 'nome_remetente'],
                    'test_to' => ['email_test_to', 'email_teste_para'],
                ];

                $emailColumnMap = [];
                foreach ($emailMapCandidates as $k => $cands) {
                    foreach ($cands as $colName) {
                        if (in_array($colName, $cols, true)) {
                            $emailColumnMap[$k] = $colName;
                            break;
                        }
                    }
                }
                if (!empty($emailColumnMap)) {
                    $columnMap['email'] = $emailColumnMap;
                }

                // Entrega / W-Express (configuracoes_sistema legado)
                $wexpressCols = [
                    'wexpress_enabled',
                    'wexpress_ambiente',
                    'wexpress_api_key',
                    'wexpress_service_code',
                    'wexpress_sender_json',
                ];
                $temWexpress = false;
                foreach ($wexpressCols as $wc) {
                    if (in_array($wc, $cols, true)) {
                        $temWexpress = true;
                        break;
                    }
                }
                if ($temWexpress) {
                    $columnMap['entrega'] = $columnMap['entrega'] ?? [];
                    foreach ($wexpressCols as $wc) {
                        if (in_array($wc, $cols, true)) {
                            $columnMap['entrega'][$wc] = $wc;
                        }
                    }
                }

                return [
                    'mode' => 'single_row',
                    'table' => $table,
                    'idCol' => 'id',
                    'idVal' => 1,
                    'updatedAtCol' => $updatedAtCol,
                    'valueCol' => '',
                    'columnMap' => $columnMap
                ];
            }
        }

        if ($hasCategoria && $hasChave) {
            $valueCol = null;
            foreach ($valueCandidates as $c) {
                if (in_array($c, $cols, true)) {
                    $valueCol = $c;
                    break;
                }
            }
            if ($valueCol) {
                $updatedAtCol = '';
                foreach ($updatedCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $updatedAtCol = $c;
                        break;
                    }
                }

                $idCol = '';
                foreach ($idCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $idCol = $c;
                        break;
                    }
                }

                return [
                    'mode' => 'categoria_chave',
                    'table' => $table,
                    'categoriaCol' => 'categoria',
                    'chaveCol' => 'chave',
                    'valueCol' => $valueCol,
                    'updatedAtCol' => $updatedAtCol,
                    'idCol' => $idCol,
                ];
            }
        }

        $keyCol = null;
        foreach ($keyCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $keyCol = $c;
                break;
            }
        }
        $valueCol = null;
        foreach ($valueCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $valueCol = $c;
                break;
            }
        }

        // Inferência por tipo/nome quando colunas não seguem o padrão
        if (!$keyCol || !$valueCol) {
            $reserved = array_merge($idCandidates, $updatedCandidates, ['created_at', 'data_criacao', 'descricao', 'tipo', 'type']);

            $textLike = [];
            foreach ($cols as $c) {
                if (in_array($c, $reserved, true)) {
                    continue;
                }
                $t = $types[$c] ?? '';
                if (strpos($t, 'char') !== false || strpos($t, 'text') !== false || strpos($t, 'enum') !== false) {
                    $textLike[] = $c;
                }
            }

            if (!$keyCol) {
                foreach ($textLike as $c) {
                    $lc = strtolower($c);
                    if (strpos($lc, 'chav') !== false || strpos($lc, 'key') !== false || strpos($lc, 'nome') !== false || strpos($lc, 'slug') !== false || strpos($lc, 'param') !== false) {
                        $keyCol = $c;
                        break;
                    }
                }
                if (!$keyCol && !empty($textLike)) {
                    $keyCol = $textLike[0];
                }
            }

            if (!$valueCol) {
                foreach ($textLike as $c) {
                    if ($c === $keyCol) {
                        continue;
                    }
                    $lc = strtolower($c);
                    if (strpos($lc, 'val') !== false || strpos($lc, 'conteud') !== false || strpos($lc, 'content') !== false) {
                        $valueCol = $c;
                        break;
                    }
                }
                if (!$valueCol) {
                    foreach ($textLike as $c) {
                        if ($c !== $keyCol) {
                            $valueCol = $c;
                            break;
                        }
                    }
                }
            }
        }

        if (!$keyCol || !$valueCol) {
            throw new \Exception('Tabela de configurações incompatível: colunas não encontradas (cols=' . implode(', ', $cols) . ')');
        }

        $updatedAtCol = '';
        foreach ($updatedCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $updatedAtCol = $c;
                break;
            }
        }

        $idCol = '';
        foreach ($idCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $idCol = $c;
                break;
            }
        }

        return [
            'mode' => 'chave_valor',
            'table' => $table,
            'keyCol' => $keyCol,
            'valueCol' => $valueCol,
            'updatedAtCol' => $updatedAtCol,
            'idCol' => $idCol,
        ];
    }
}
