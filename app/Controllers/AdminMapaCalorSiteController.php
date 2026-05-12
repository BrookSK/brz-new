<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminMapaCalorSiteController extends Controller {

    private function ensureTable(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_heatmap_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            usuario_id INT DEFAULT NULL,
            pagina VARCHAR(500) NOT NULL,
            tipo ENUM('pageview','click','scroll','time_on_page') NOT NULL,
            x INT DEFAULT NULL,
            y INT DEFAULT NULL,
            scroll_depth INT DEFAULT NULL,
            tempo_segundos INT DEFAULT NULL,
            elemento VARCHAR(255) DEFAULT NULL,
            viewport_width INT DEFAULT NULL,
            viewport_height INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pagina (pagina(191)),
            INDEX idx_tipo (tipo),
            INDEX idx_created (created_at),
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // ============================================================
    // DASHBOARD DO MAPA DE CALOR
    // ============================================================
    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);

        // Stats
        $stats = ['total_eventos'=>0, 'paginas_unicas'=>0, 'sessoes'=>0, 'cliques'=>0];
        try {
            $stats['total_eventos'] = (int)$pdo->query("SELECT COUNT(*) FROM site_heatmap_events")->fetchColumn();
            $stats['paginas_unicas'] = (int)$pdo->query("SELECT COUNT(DISTINCT pagina) FROM site_heatmap_events")->fetchColumn();
            $stats['sessoes'] = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_heatmap_events")->fetchColumn();
            $stats['cliques'] = (int)$pdo->query("SELECT COUNT(*) FROM site_heatmap_events WHERE tipo='click'")->fetchColumn();
        } catch (\Exception $e) {}

        // Top pages
        $topPages = [];
        try {
            $topPages = $pdo->query("SELECT pagina, COUNT(*) AS visitas, COUNT(DISTINCT session_id) AS sessoes_unicas, AVG(CASE WHEN tipo='time_on_page' THEN tempo_segundos END) AS tempo_medio, MAX(CASE WHEN tipo='scroll' THEN scroll_depth END) AS max_scroll FROM site_heatmap_events GROUP BY pagina ORDER BY visitas DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Top clicked elements
        $topClicks = [];
        try {
            $topClicks = $pdo->query("SELECT elemento, pagina, COUNT(*) AS cliques FROM site_heatmap_events WHERE tipo='click' AND elemento IS NOT NULL AND elemento != '' GROUP BY elemento, pagina ORDER BY cliques DESC LIMIT 15")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Scroll depth distribution
        $scrollData = [];
        try {
            $scrollData = $pdo->query("SELECT CASE WHEN scroll_depth <= 25 THEN '0-25%' WHEN scroll_depth <= 50 THEN '25-50%' WHEN scroll_depth <= 75 THEN '50-75%' ELSE '75-100%' END AS faixa, COUNT(*) AS total FROM site_heatmap_events WHERE tipo='scroll' AND scroll_depth IS NOT NULL GROUP BY faixa ORDER BY MIN(scroll_depth)")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Funnel: pages in order of visit frequency
        $funnel = [];
        try {
            $funnel = $pdo->query("SELECT pagina, COUNT(DISTINCT session_id) AS sessoes FROM site_heatmap_events WHERE tipo='pageview' GROUP BY pagina ORDER BY sessoes DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->renderPage($stats, $topPages, $topClicks, $scrollData, $funnel);
    }

    // ============================================================
    // COLETAR EVENTO (chamado pelo JS do frontend)
    // ============================================================
    public function collect(Request $request) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['pagina']) || empty($data['tipo'])) {
            echo json_encode(['ok'=>false]);
            return;
        }

        try {
            $pdo = Database::getConnection();
            $this->ensureTable($pdo);

            $sessionId = $data['session_id'] ?? (session_id() ?: md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? '')));
            $usuarioId = $_SESSION['usuario_id'] ?? null;

            $st = $pdo->prepare("INSERT INTO site_heatmap_events (session_id, usuario_id, pagina, tipo, x, y, scroll_depth, tempo_segundos, elemento, viewport_width, viewport_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([
                $sessionId,
                $usuarioId ? (int)$usuarioId : null,
                substr((string)$data['pagina'], 0, 500),
                $data['tipo'],
                isset($data['x']) ? (int)$data['x'] : null,
                isset($data['y']) ? (int)$data['y'] : null,
                isset($data['scroll_depth']) ? (int)$data['scroll_depth'] : null,
                isset($data['tempo']) ? (int)$data['tempo'] : null,
                isset($data['elemento']) ? substr((string)$data['elemento'], 0, 255) : null,
                isset($data['vw']) ? (int)$data['vw'] : null,
                isset($data['vh']) ? (int)$data['vh'] : null,
            ]);
        } catch (\Exception $e) {}

        echo json_encode(['ok'=>true]);
    }

    // ============================================================
    // DADOS DE CLIQUES POR PÁGINA (para visualização)
    // ============================================================
    public function dadosPagina(Request $request) {
        header('Content-Type: application/json');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $pagina = trim((string)$request->getParam('pagina', '/'));

        $cliques = [];
        try {
            $st = $pdo->prepare("SELECT x, y, COUNT(*) AS intensidade FROM site_heatmap_events WHERE pagina = ? AND tipo = 'click' AND x IS NOT NULL AND y IS NOT NULL GROUP BY x, y ORDER BY intensidade DESC LIMIT 500");
            $st->execute([$pagina]);
            $cliques = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $scroll = [];
        try {
            $st = $pdo->prepare("SELECT scroll_depth, COUNT(*) AS total FROM site_heatmap_events WHERE pagina = ? AND tipo = 'scroll' AND scroll_depth IS NOT NULL GROUP BY scroll_depth ORDER BY scroll_depth");
            $st->execute([$pagina]);
            $scroll = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        echo json_encode(['success'=>true, 'cliques'=>$cliques, 'scroll'=>$scroll, 'pagina'=>$pagina]);
    }

    // ============================================================
    // RENDER PAGE
    // ============================================================
    private function renderPage(array $stats, array $topPages, array $topClicks, array $scrollData, array $funnel): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mapa de Calor - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/dashboard-redesign.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('mapa-calor-site');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="dashboard-page">';

        // Header
        echo '<header class="page-header"><div><h1 class="page-title">Mapa de Calor do Site</h1><p class="page-subtitle">Comportamento dos clientes: páginas visitadas, cliques, scroll e tempo de permanência</p></div></header>';

        // KPIs
        echo '<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Eventos Coletados</div><div class="kpi-value">'.number_format($stats['total_eventos']).'</div></div><div class="kpi-icon"><i class="bi bi-activity"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Páginas Únicas</div><div class="kpi-value">'.$stats['paginas_unicas'].'</div></div><div class="kpi-icon"><i class="bi bi-file-earmark"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Sessões</div><div class="kpi-value">'.number_format($stats['sessoes']).'</div></div><div class="kpi-icon"><i class="bi bi-people"></i></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Cliques Rastreados</div><div class="kpi-value">'.number_format($stats['cliques']).'</div></div><div class="kpi-icon"><i class="bi bi-cursor-fill"></i></div></div>
</div>';

        // Info about tracking
        if ($stats['total_eventos'] === 0) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;">
<i class="bi bi-info-circle" style="font-size:40px;color:#94A3B8;"></i>
<h5 style="color:var(--navy);margin-top:16px;">Tracking ainda não ativado</h5>
<p style="color:#64748B;max-width:500px;margin:8px auto;">Para coletar dados de comportamento, adicione o script de tracking no frontend do site. O script coleta: pageviews, cliques (posição X/Y), profundidade de scroll e tempo na página.</p>
<div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-top:16px;text-align:left;max-width:600px;margin-left:auto;margin-right:auto;">
<code style="font-size:12px;color:#18253D;">&lt;script src="/assets/js/heatmap-tracker.js"&gt;&lt;/script&gt;</code>
</div>
</div></div>';
        }

        // Top Pages
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-bar-chart-fill"></i> Páginas Mais Visitadas</h2></div><div class="section-body">';
        if (!empty($topPages)) {
            echo '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#FAFBFC;"><th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Página</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Visitas</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Sessões</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Tempo Médio</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Scroll Máx</th></tr></thead><tbody>';
            foreach ($topPages as $p) {
                $tempo = $p['tempo_medio'] ? gmdate('i:s', (int)$p['tempo_medio']) : '-';
                $scroll = $p['max_scroll'] ? $p['max_scroll'].'%' : '-';
                echo '<tr style="border-bottom:1px solid #F1F5F9;"><td style="padding:10px 14px;"><strong>'.htmlspecialchars($p['pagina']).'</strong></td><td style="padding:10px 14px;text-align:center;">'.(int)$p['visitas'].'</td><td style="padding:10px 14px;text-align:center;">'.(int)$p['sessoes_unicas'].'</td><td style="padding:10px 14px;text-align:center;">'.$tempo.'</td><td style="padding:10px 14px;text-align:center;">'.$scroll.'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados ainda.</p>';
        }
        echo '</div></div>';

        // Funnel + Scroll side by side
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">';

        // Funnel
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-funnel-fill"></i> Funil de Navegação</h2></div><div class="section-body">';
        if (!empty($funnel)) {
            $maxSessoes = (int)($funnel[0]['sessoes'] ?? 1);
            foreach ($funnel as $i => $f) {
                $pct = $maxSessoes > 0 ? round(((int)$f['sessoes'] / $maxSessoes) * 100) : 0;
                echo '<div style="margin-bottom:8px;"><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;"><span style="color:#374151;">'.htmlspecialchars($f['pagina']).'</span><span style="color:#18253D;font-weight:600;">'.(int)$f['sessoes'].' sessões</span></div><div style="background:#E2E8F0;border-radius:4px;height:8px;overflow:hidden;"><div style="background:var(--navy);height:100%;width:'.$pct.'%;border-radius:4px;"></div></div></div>';
            }
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados.</p>';
        }
        echo '</div></div>';

        // Scroll depth
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-arrow-down-circle-fill"></i> Profundidade de Scroll</h2></div><div class="section-body">';
        if (!empty($scrollData)) {
            foreach ($scrollData as $s) {
                $total = (int)$s['total'];
                echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #F1F5F9;"><span style="font-size:14px;font-weight:600;color:#18253D;">'.$s['faixa'].'</span><span style="font-size:13px;color:#64748B;">'.$total.' eventos</span></div>';
            }
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados de scroll.</p>';
        }
        echo '</div></div>';
        echo '</div>';

        // Top Clicks
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-cursor-fill"></i> Elementos Mais Clicados</h2></div><div class="section-body">';
        if (!empty($topClicks)) {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;">';
            foreach ($topClicks as $c) {
                echo '<div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;"><div style="font-size:13px;font-weight:600;color:#18253D;margin-bottom:4px;">'.htmlspecialchars($c['elemento']).'</div><div style="font-size:11px;color:#94A3B8;">'.htmlspecialchars($c['pagina']).'</div><div style="font-size:14px;font-weight:700;color:var(--navy);margin-top:6px;">'.(int)$c['cliques'].' cliques</div></div>';
            }
            echo '</div>';
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados de cliques.</p>';
        }
        echo '</div></div>';

        echo '</div></main></div></div>';
        renderAdminScripts();
        echo '</body></html>';
    }
}
