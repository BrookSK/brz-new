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
    // ANÁLISE DE IA
    // ============================================================
    public function analiseIA(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();

        // Collect data for AI analysis
        $dados = [];
        try {
            $dados['top_paginas'] = $pdo->query("SELECT pagina, COUNT(*) AS visitas, COUNT(DISTINCT session_id) AS sessoes, AVG(CASE WHEN tipo='time_on_page' THEN tempo_segundos END) AS tempo_medio, MAX(CASE WHEN tipo='scroll' THEN scroll_depth END) AS max_scroll FROM site_heatmap_events GROUP BY pagina ORDER BY visitas DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['top_cliques'] = $pdo->query("SELECT elemento, pagina, COUNT(*) AS cliques FROM site_heatmap_events WHERE tipo='click' AND elemento IS NOT NULL GROUP BY elemento, pagina ORDER BY cliques DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['scroll_abandono'] = $pdo->query("SELECT pagina, AVG(scroll_depth) AS scroll_medio FROM site_heatmap_events WHERE tipo='scroll' GROUP BY pagina ORDER BY scroll_medio ASC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['tempo_baixo'] = $pdo->query("SELECT pagina, AVG(tempo_segundos) AS tempo_medio FROM site_heatmap_events WHERE tipo='time_on_page' AND tempo_segundos IS NOT NULL GROUP BY pagina HAVING tempo_medio < 10 ORDER BY tempo_medio ASC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['total_sessoes'] = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_heatmap_events")->fetchColumn();
        } catch (\Exception $e) {}

        if (empty($dados['top_paginas'])) {
            echo json_encode(['success' => false, 'error' => 'Dados insuficientes. Aguarde mais visitas ao site.']);
            return;
        }

        // Call AI
        $apiKey = null;
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $apiKey = (string)($st->fetchColumn() ?: '');
        } catch (\Exception $e) {}
        if (!$apiKey) { echo json_encode(['success'=>false,'error'=>'API Key não configurada.']); return; }

        $model = 'gpt-4o-mini';
        try { $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1"); $st->execute(['chatgpt_model']); $v = trim((string)($st->fetchColumn() ?: '')); if ($v) $model = $v; } catch (\Exception $e) {}

        $systemPrompt = "Você é um consultor de loja online explicando para o dono da loja (que não é técnico) como os clientes estão usando o site. Use linguagem simples, como se estivesse conversando. Analise os dados e responda:

1. COMO OS CLIENTES USAM O SITE: Explique de forma simples o que os dados mostram. Exemplo: 'A maioria dos seus clientes entra pela home e vai direto para os produtos.'

2. ONDE ESTÃO TRAVANDO: Identifique páginas onde os clientes saem rápido ou não rolam até o final. Explique o que isso significa na prática. Exemplo: 'Na página de checkout, muita gente sai sem terminar a compra.'

3. O QUE MAIS PROCURAM: Com base nos cliques, diga o que os clientes querem encontrar. Exemplo: 'Seus clientes clicam muito no botão de busca, isso mostra que querem encontrar produtos específicos.'

4. PROBLEMAS QUE PODEM ESTAR CAUSANDO PERDA DE VENDAS: Liste problemas claros. Exemplo: 'A página X tem tempo muito baixo, pode ser que esteja carregando lenta ou o conteúdo não está interessante.'

5. O QUE FAZER PARA MELHORAR: Dê sugestões práticas e simples. Exemplo: 'Coloque os produtos mais vendidos logo no topo da home' ou 'Simplifique o checkout para ter menos passos.'

Seja direto, prático e evite termos técnicos. Fale como se fosse um amigo dando conselhos sobre a loja.";

        $userMsg = "DADOS DO SITE:\n\n";
        $userMsg .= "Total de sessões: " . $dados['total_sessoes'] . "\n\n";
        $userMsg .= "TOP PÁGINAS VISITADAS:\n";
        foreach ($dados['top_paginas'] as $p) {
            $userMsg .= "- " . $p['pagina'] . " | " . $p['visitas'] . " visitas | " . (int)$p['sessoes'] . " sessões | Tempo médio: " . round((float)($p['tempo_medio'] ?? 0)) . "s | Scroll máx: " . (int)($p['max_scroll'] ?? 0) . "%\n";
        }
        $userMsg .= "\nELEMENTOS MAIS CLICADOS:\n";
        foreach ($dados['top_cliques'] as $c) {
            $userMsg .= "- " . $c['elemento'] . " em " . $c['pagina'] . " (" . $c['cliques'] . " cliques)\n";
        }
        if (!empty($dados['scroll_abandono'])) {
            $userMsg .= "\nPÁGINAS COM MENOR SCROLL (possível abandono):\n";
            foreach ($dados['scroll_abandono'] as $s) {
                $userMsg .= "- " . $s['pagina'] . " | Scroll médio: " . round((float)$s['scroll_medio']) . "%\n";
            }
        }
        if (!empty($dados['tempo_baixo'])) {
            $userMsg .= "\nPÁGINAS COM TEMPO MUITO BAIXO (<10s):\n";
            foreach ($dados['tempo_baixo'] as $t) {
                $userMsg .= "- " . $t['pagina'] . " | Tempo médio: " . round((float)$t['tempo_medio']) . "s\n";
            }
        }

        $payload = json_encode(['model'=>$model, 'messages'=>[['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userMsg]], 'temperature'=>0.7, 'max_tokens'=>2000]);
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey], CURLOPT_TIMEOUT=>90]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($resp, true);

        if ($code === 200 && isset($data['choices'][0]['message']['content'])) {
            echo json_encode(['success'=>true, 'analise'=>trim($data['choices'][0]['message']['content'])]);
        } else {
            echo json_encode(['success'=>false, 'error'=>$data['error']['message'] ?? 'Erro da API']);
        }
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
        echo '<header class="page-header"><div><h1 class="page-title">Mapa de Calor do Site</h1><p class="page-subtitle">Veja onde seus clientes clicam, até onde rolam a página e quanto tempo ficam em cada seção</p></div>
<div style="display:flex;gap:8px;">
<button class="btn-dash-primary" onclick="analisarComIA()" style="padding:8px 16px;font-size:13px;"><i class="bi bi-stars me-1"></i>Análise de IA</button>
</div></header>';

        // KPIs
        echo '<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Interações</div><div class="kpi-value">'.number_format($stats['total_eventos']).'</div><div class="kpi-subtext">Ações registradas</div></div><div class="kpi-icon"><i class="bi bi-activity"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Páginas Visitadas</div><div class="kpi-value">'.$stats['paginas_unicas'].'</div><div class="kpi-subtext">Diferentes</div></div><div class="kpi-icon"><i class="bi bi-file-earmark"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Visitantes</div><div class="kpi-value">'.number_format($stats['sessoes']).'</div><div class="kpi-subtext">Pessoas navegando</div></div><div class="kpi-icon"><i class="bi bi-people"></i></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Cliques</div><div class="kpi-value">'.number_format($stats['cliques']).'</div><div class="kpi-subtext">Onde clicaram</div></div><div class="kpi-icon"><i class="bi bi-cursor-fill"></i></div></div>
</div>';

        // Info about tracking
        if ($stats['total_eventos'] === 0) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;">
<i class="bi bi-info-circle" style="font-size:40px;color:#94A3B8;"></i>
<h5 style="color:var(--navy);margin-top:16px;">Coletando dados...</h5>
<p style="color:#64748B;max-width:500px;margin:8px auto;">O sistema está ativo e coletando informações sobre como seus clientes navegam no site. Os dados aparecerão aqui assim que houver visitas. Isso acontece automaticamente — não precisa fazer nada.</p>
</div></div>';
        }

        // Top Pages
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-bar-chart-fill"></i> Páginas Mais Acessadas</h2></div><div class="section-body">';
        if (!empty($topPages)) {
            echo '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#FAFBFC;"><th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Página</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Acessos</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Visitantes</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Tempo na Página</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Até onde rolaram</th></tr></thead><tbody>';
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
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-funnel-fill"></i> Caminho dos Clientes</h2></div><div class="section-body">
<p style="font-size:12px;color:#94A3B8;margin-bottom:12px;">Mostra por quais páginas seus clientes mais passam. Quanto maior a barra, mais gente visita.</p>';
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
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-arrow-down-circle-fill"></i> Até onde rolam a página</h2></div><div class="section-body">
<p style="font-size:12px;color:#94A3B8;margin-bottom:12px;">Mostra se os clientes veem a página inteira ou param no meio. Se param cedo, o conteúdo pode não estar atraente.</p>';
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
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-cursor-fill"></i> O que mais clicam</h2></div><div class="section-body">
<p style="font-size:12px;color:#94A3B8;margin-bottom:12px;">Botões, links e áreas que seus clientes mais clicam. Isso mostra o que eles procuram.</p>';
        if (!empty($topClicks)) {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;">';
            foreach ($topClicks as $c) {
                $elem = htmlspecialchars($c['elemento'] ?? '');
                // Make page path friendly
                $paginaFriendly = $c['pagina'] ?? '/';
                $pageLabels = ['/'=>'Página Inicial', '/produtos'=>'Produtos', '/carrinho'=>'Carrinho', '/checkout'=>'Checkout', '/contato'=>'Contato', '/faq'=>'FAQ', '/assessoria'=>'Assessoria'];
                foreach ($pageLabels as $path => $label) {
                    if ($paginaFriendly === $path || strpos($paginaFriendly, $path.'/') === 0) { $paginaFriendly = $label; break; }
                }
                if (strpos($paginaFriendly, '/produto/detalhes/') === 0) $paginaFriendly = 'Página do Produto';
                if (strpos($paginaFriendly, '/grupo') === 0) $paginaFriendly = 'Grupo de Compras';
                
                echo '<div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;"><div style="font-size:14px;font-weight:600;color:#18253D;margin-bottom:4px;">'.$elem.'</div><div style="font-size:11px;color:#64748B;">em: '.htmlspecialchars($paginaFriendly).'</div><div style="font-size:15px;font-weight:700;color:var(--navy);margin-top:6px;">'.(int)$c['cliques'].' cliques</div></div>';
            }
            echo '</div>';
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados de cliques.</p>';
        }
        echo '</div></div>';

        // Heatmap Visualization Section
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-fire"></i> Visualização do Mapa de Calor</h2></div><div class="section-body">
<div style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
<select id="heatmapPageSelect" onchange="carregarHeatmap()" style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">';
        if (!empty($topPages)) {
            foreach ($topPages as $p) {
                echo '<option value="'.htmlspecialchars($p['pagina']).'">'.htmlspecialchars($p['pagina']).' ('.(int)$p['visitas'].' visitas)</option>';
            }
        } else {
            echo '<option value="/">/ (home)</option>';
        }
        echo '</select>
<button onclick="carregarHeatmap()" class="btn-dash-secondary" style="padding:8px 14px;font-size:13px;"><i class="bi bi-arrow-clockwise me-1"></i>Atualizar</button>
</div>
<div id="heatmapContainer" style="position:relative;width:100%;height:600px;border-radius:10px;overflow:hidden;border:1px solid #E2E8F0;">
<iframe id="heatmapIframe" src="/" style="width:100%;height:100%;border:none;pointer-events:none;"></iframe>
<canvas id="heatmapCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;opacity:0.6;"></canvas>
<div id="heatmapInfo" style="position:absolute;bottom:10px;left:10px;background:rgba(0,0,0,.7);color:#fff;padding:6px 12px;border-radius:6px;font-size:11px;">Selecione uma página para visualizar</div>
</div>
<div style="display:flex;gap:16px;margin-top:12px;font-size:11px;color:#94A3B8;">
<span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#0000ff;vertical-align:middle;margin-right:4px;"></span>Pouco clicado</span>
<span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#00ff00;vertical-align:middle;margin-right:4px;"></span>Médio</span>
<span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#ffff00;vertical-align:middle;margin-right:4px;"></span>Frequente</span>
<span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#ff0000;vertical-align:middle;margin-right:4px;"></span>Muito clicado</span>
</div>
</div></div>';

        // AI Analysis Modal
        echo '<div id="modalAnaliseIA" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;max-width:700px;width:95%;max-height:85vh;overflow-y:auto;">
<div style="background:var(--navy);color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
<h6 style="margin:0;font-size:15px;font-weight:700;"><i class="bi bi-stars me-2"></i>Análise de IA - Comportamento do Site</h6>
<button onclick="document.getElementById(\'modalAnaliseIA\').style.display=\'none\'" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
</div>
<div id="analiseIAContent" style="padding:24px;"><div style="text-align:center;padding:40px;"><i class="bi bi-stars" style="font-size:32px;color:var(--navy);animation:spin 2s linear infinite;"></i><p style="color:#64748B;margin-top:12px;">Analisando dados de comportamento...</p></div></div>
</div></div>';

        echo '</div></main></div></div>';
        renderAdminScripts();
        echo '<script>
async function carregarHeatmap(){
    var pagina = document.getElementById("heatmapPageSelect").value;
    var info = document.getElementById("heatmapInfo");
    var iframe = document.getElementById("heatmapIframe");
    info.textContent = "Carregando: " + pagina;
    
    // Load the actual page in iframe
    iframe.src = pagina;
    
    var r = await fetch("/admin/mapa-calor-site/dados-pagina?pagina="+encodeURIComponent(pagina));
    var d = await r.json();
    
    if(!d.success || !d.cliques || !d.cliques.length){
        info.textContent = "Sem dados de cliques para esta página ainda.";
        var canvas = document.getElementById("heatmapCanvas");
        var ctx = canvas.getContext("2d");
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }
    
    // Wait for iframe to load then render heatmap
    setTimeout(function(){ renderHeatmap(d.cliques); }, 1000);
    info.textContent = pagina + " — " + d.cliques.length + " zonas de clique";
}

function renderHeatmap(cliques){
    var canvas = document.getElementById("heatmapCanvas");
    var container = document.getElementById("heatmapContainer");
    canvas.width = container.offsetWidth;
    canvas.height = container.offsetHeight;
    var ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Find max intensity
    var maxInt = 1;
    cliques.forEach(function(c){ if(c.intensidade > maxInt) maxInt = c.intensidade; });
    
    // Draw heatmap points
    cliques.forEach(function(c){
        var x = (c.x / 100) * canvas.width;
        var y = (c.y / 100) * canvas.height;
        var intensity = c.intensidade / maxInt;
        var radius = 25 + (intensity * 40);
        
        var gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
        gradient.addColorStop(0, "rgba(255,0,0," + (intensity * 0.7) + ")");
        gradient.addColorStop(0.3, "rgba(255,165,0," + (intensity * 0.5) + ")");
        gradient.addColorStop(0.6, "rgba(255,255,0," + (intensity * 0.3) + ")");
        gradient.addColorStop(0.8, "rgba(0,255,0," + (intensity * 0.15) + ")");
        gradient.addColorStop(1, "rgba(0,0,255,0)");
        
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fillStyle = gradient;
        ctx.fill();
    });
}

async function analisarComIA(){
    document.getElementById("modalAnaliseIA").style.display = "flex";
    document.getElementById("analiseIAContent").innerHTML = \'<div style="text-align:center;padding:40px;"><i class="bi bi-stars" style="font-size:32px;color:var(--navy);animation:spin 2s linear infinite;"></i><p style="color:#64748B;margin-top:12px;">Analisando dados de comportamento...</p></div>\';
    
    var r = await fetch("/admin/mapa-calor-site/analise-ia", {method:"POST"});
    var d = await r.json();
    
    if(d.success && d.analise){
        document.getElementById("analiseIAContent").innerHTML = \'<div style="font-size:14px;color:#374151;line-height:1.8;white-space:pre-wrap;">\' + d.analise + \'</div>\';
    } else {
        document.getElementById("analiseIAContent").innerHTML = \'<p style="color:#BE123C;">\' + (d.error || "Erro na análise") + \'</p>\';
    }
}

// Auto-load first page
document.addEventListener("DOMContentLoaded", function(){ carregarHeatmap(); });
</script>';
        echo '</body></html>';
    }
}
