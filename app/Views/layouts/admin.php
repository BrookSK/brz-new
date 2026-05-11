<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? __('admin.panel_title', 'Painel Administrativo - Braziliana') ?></title>
    <?php
    $adminFavicon = '';
    try {
        $rawFav = '';
        $tablesToTryFav = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        foreach ($tablesToTryFav as $t) {
            if ($rawFav !== '') break;
            try {
                $pdoFav = \Config\Database::getConnection();
                $stmtT = $pdoFav->prepare('SHOW TABLES LIKE ?');
                $stmtT->execute([$t]);
                if (!$stmtT->fetchColumn()) {
                    continue;
                }

                $stmtCols = $pdoFav->query('DESCRIBE ' . $t);
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (!is_array($cols)) {
                    $cols = [];
                }

                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                    if ($valCol !== '') {
                        $stmt = $pdoFav->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                        $stmt->execute(['layout', 'favicon']);
                        $rawFav = (string) ($stmt->fetchColumn() ?: '');
                        if ($rawFav !== '') break;
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
                    $stmt = $pdoFav->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute(['layout_favicon']);
                    $rawFav = (string) ($stmt->fetchColumn() ?: '');
                    if ($rawFav !== '') break;
                }

                if (in_array('layout_favicon', $cols, true)) {
                    $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                    $stmt2 = $pdoFav->query('SELECT layout_favicon AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $rawFav = (string) ($stmt2->fetchColumn() ?: '');
                    if ($rawFav !== '') break;
                }
            } catch (\Exception $e) {
            }
        }

        $adminFavicon = is_string($rawFav) ? trim($rawFav) : '';
    } catch (\Exception $e) {
        $adminFavicon = '';
    }

    $faviconVer = $adminFavicon !== '' ? substr(sha1($adminFavicon), 0, 10) : '';
    $adminFaviconHref = $adminFavicon !== '' ? ($adminFavicon . (strpos($adminFavicon, '?') === false ? '?' : '&') . 'v=' . rawurlencode($faviconVer)) : '';
    $adminFaviconEsc = htmlspecialchars($adminFaviconHref, ENT_QUOTES, 'UTF-8');
    $faviconMime = '';
    if ($adminFavicon !== '') {
        $ext = strtolower(pathinfo(parse_url($adminFavicon, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if ($ext === 'ico') $faviconMime = 'image/x-icon';
        elseif ($ext === 'png') $faviconMime = 'image/png';
        elseif ($ext === 'svg') $faviconMime = 'image/svg+xml';
    }
    ?>
    <?php if ($adminFaviconEsc !== ''): ?>
        <link rel="icon" href="<?= $adminFaviconEsc ?>" <?= $faviconMime !== '' ? ('type="' . htmlspecialchars($faviconMime, ENT_QUOTES, 'UTF-8') . '"') : '' ?>>
        <link rel="shortcut icon" href="<?= $adminFaviconEsc ?>" <?= $faviconMime !== '' ? ('type="' . htmlspecialchars($faviconMime, ENT_QUOTES, 'UTF-8') . '"') : '' ?>>
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/pedidos') !== false && strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/pedidos/') === false): ?>
    <link href="/assets/css/pedidos-redesign.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/produtos') !== false): ?>
    <link href="/assets/css/produtos-redesign.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/grupos-compras') !== false): ?>
    <link href="/assets/css/grupos-compras-redesign.css" rel="stylesheet">
    <?php endif; ?>
    <?php
    include_once __DIR__ . '/../partials/admin_sidebar.php';
    if (function_exists('renderAdminSidebarStyles')) {
        renderAdminSidebarStyles();
    }
    ?>
    <style>
        :root {
            --primary-color: #0b1f3a;
            --primary-color-2: #1d4ed8;
            --secondary-color: #94a3b8;
            --danger-color: #ef4444;

            --bg-surface: #f6f8fb;
            --primary-btn: #0b1f3a;

            --radius-md: 14px;
            --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
            --shadow-lg: 0 16px 44px rgba(15, 23, 42, 0.12);
        }

        html {
            height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            background: var(--bg-surface);
            color: #0f172a;
        }

        .card,
        .dropdown-menu,
        .modal-content,
        .form-control,
        .form-select,
        .btn {
            border-radius: var(--radius-md);
        }

        .card {
            border: 0;
            box-shadow: var(--shadow-sm);
        }

        .shadow {
            box-shadow: var(--shadow-md) !important;
        }

        .shadow-sm {
            box-shadow: var(--shadow-sm) !important;
        }

        .shadow-lg {
            box-shadow: var(--shadow-lg) !important;
        }

        .btn-success {
            border: 1px solid rgba(16, 185, 129, 0.22);
            background: rgba(16, 185, 129, 0.10);
            color: #065f46;
        }

        .btn-info {
            border: 1px solid rgba(56, 189, 248, 0.22);
            background: rgba(56, 189, 248, 0.10);
            color: #0b1f3a;
        }

        .btn-warning {
            border: 1px solid rgba(245, 158, 11, 0.22);
            background: rgba(245, 158, 11, 0.12);
            color: #7c2d12;
        }

        .btn-primary {
            border: none;
            background: var(--primary-btn);
            color: #ffffff;
        }

        .admin-shell {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .admin-shell > .row {
            flex: 1 0 auto;
            min-height: 0;
        }

        .sidebar {
            min-height: 100vh;
            background: #0b1f3a;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.35rem;
            margin: 0.2rem 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .sidebar-brand {
            color: #fff;
            font-weight: bold;
            padding: 1rem;
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #1d4ed8 !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #38bdf8 !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .text-xs {
            font-size: 0.7rem;
        }
        
        .font-weight-bold {
            font-weight: 700 !important;
        }
        
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid admin-shell">
        <div class="row">
            <!-- Sidebar -->
            <?php
            if (function_exists('renderAdminSidebar')) {
                renderAdminSidebar($sidebarActive ?? '');
            }
            ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 d-flex flex-column">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show mt-3" role="alert">
                        <?= $_SESSION['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <?= $content ?? '' ?>
            </main>
        </div>

        <footer class="border-top py-3 mt-auto">
            <div class="text-center text-muted small">&copy; 2026 Braziliana. <?= __('admin.all_rights_reserved', 'Todos os direitos reservados.') ?></div>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/produtos') !== false): ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        // Hide variations sections
        document.querySelectorAll('[id*="variaco"],[id*="variacao"],[data-bs-target*="variaco"],[data-bs-target*="variacao"],[href*="variaco"],[href*="variacao"]').forEach(function(el){el.style.display='none';if(el.closest('.nav-item'))el.closest('.nav-item').style.display='none';});
        // Hide the tab pane for variations
        document.querySelectorAll('.tab-pane').forEach(function(el){if(el.id&&(el.id.indexOf('variaco')>-1||el.id.indexOf('variacao')>-1))el.style.display='none';});
        // Hide alert about variations
        document.querySelectorAll('.alert-info').forEach(function(el){if(el.textContent.indexOf('variações')>-1||el.textContent.indexOf('Variações')>-1)el.style.display='none';});
        // Hide cards with "Variações" in header
        document.querySelectorAll('.card-header,.card-title,h5,h4,h3').forEach(function(el){if(el.textContent.trim().indexOf('Variações')>-1||el.textContent.trim().indexOf('variações')>-1){var card=el.closest('.card')||el.closest('.mb-4');if(card)card.style.display='none';}});
    });
    </script>
    <?php endif; ?>
</body>
</html>
