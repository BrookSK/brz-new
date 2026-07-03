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
            x DECIMAL(5,1) DEFAULT NULL,
            y DECIMAL(5,1) DEFAULT NULL,
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
        // Upgrade x/y to decimal for precision
        try { $pdo->exec("ALTER TABLE site_heatmap_events MODIFY COLUMN x DECIMAL(5,1) DEFAULT NULL, MODIFY COLUMN y DECIMAL(5,1) DEFAULT NULL"); } catch (\Exception $e) {}
    }

    // ============================================================
    // DASHBOARD DO MAPA DE CALOR
    // ============================================================
    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);

        // Global period filter (default 30 days)
        $periodo = (int)$request->getParam('periodo', 30);
        if ($periodo < 1) $periodo = 30;
        if ($periodo > 365) $periodo = 365;
        $dateFilter = "AND created_at >= DATE_SUB(NOW(), INTERVAL {$periodo} DAY)";

        // Stats (filtered by period)
        $stats = ['total_eventos'=>0, 'paginas_unicas'=>0, 'sessoes'=>0, 'cliques'=>0];
        try {
            $stats['total_eventos'] = (int)$pdo->query("SELECT COUNT(*) FROM site_heatmap_events WHERE 1=1 {$dateFilter}")->fetchColumn();
            $stats['paginas_unicas'] = (int)$pdo->query("SELECT COUNT(DISTINCT pagina) FROM site_heatmap_events WHERE 1=1 {$dateFilter}")->fetchColumn();
            $stats['sessoes'] = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_heatmap_events WHERE 1=1 {$dateFilter}")->fetchColumn();
            $stats['cliques'] = (int)$pdo->query("SELECT COUNT(*) FROM site_heatmap_events WHERE tipo='click' {$dateFilter}")->fetchColumn();
        } catch (\Exception $e) {}

        // Top pages (filtered)
        $topPages = [];
        try {
            $topPages = $pdo->query("SELECT pagina, COUNT(*) AS visitas, COUNT(DISTINCT session_id) AS sessoes_unicas, AVG(CASE WHEN tipo='time_on_page' THEN tempo_segundos END) AS tempo_medio, MAX(CASE WHEN tipo='scroll' THEN scroll_depth END) AS max_scroll FROM site_heatmap_events WHERE 1=1 {$dateFilter} GROUP BY pagina ORDER BY visitas DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Top clicked elements (filtered)
        $topClicks = [];
        try {
            $topClicks = $pdo->query("SELECT elemento, pagina, COUNT(*) AS cliques FROM site_heatmap_events WHERE tipo='click' AND elemento IS NOT NULL AND elemento != '' {$dateFilter} GROUP BY elemento, pagina ORDER BY cliques DESC LIMIT 15")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Scroll depth (filtered)
        $scrollData = [];
        try {
            $scrollData = $pdo->query("SELECT CASE WHEN scroll_depth <= 25 THEN '0-25%' WHEN scroll_depth <= 50 THEN '25-50%' WHEN scroll_depth <= 75 THEN '50-75%' ELSE '75-100%' END AS faixa, COUNT(*) AS total FROM site_heatmap_events WHERE tipo='scroll' AND scroll_depth IS NOT NULL {$dateFilter} GROUP BY faixa ORDER BY MIN(scroll_depth)")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Funnel (filtered)
        $funnel = [];
        try {
            $funnel = $pdo->query("SELECT pagina, COUNT(DISTINCT session_id) AS sessoes FROM site_heatmap_events WHERE tipo='pageview' {$dateFilter} GROUP BY pagina ORDER BY sessoes DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->renderPage($stats, $topPages, $topClicks, $scrollData, $funnel, $request, $periodo);
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

            $visitorId = substr((string)($data['visitor_id'] ?? ''), 0, 64);
            $sessionId = substr((string)($data['session_id'] ?? ''), 0, 64);
            if (!$visitorId) $visitorId = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if (!$sessionId) $sessionId = session_id() ?: $visitorId;

            $usuarioId = null;
            if (session_status() === PHP_SESSION_ACTIVE || @session_start()) {
                $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            }

            // Save to heatmap events (existing table)
            $st = $pdo->prepare("INSERT INTO site_heatmap_events (session_id, usuario_id, pagina, tipo, x, y, scroll_depth, tempo_segundos, elemento, viewport_width, viewport_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([
                $sessionId,
                $usuarioId,
                substr((string)$data['pagina'], 0, 500),
                $data['tipo'],
                isset($data['x']) ? round((float)$data['x'], 1) : null,
                isset($data['y']) ? round((float)$data['y'], 1) : null,
                isset($data['scroll_depth']) ? (int)$data['scroll_depth'] : null,
                isset($data['tempo']) ? (int)$data['tempo'] : null,
                isset($data['elemento']) ? substr((string)$data['elemento'], 0, 255) : null,
                isset($data['vw']) ? (int)$data['vw'] : null,
                isset($data['vh']) ? (int)$data['vh'] : null,
            ]);

            // Save to behavior_events (enriched data)
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS behavior_events (id BIGINT AUTO_INCREMENT PRIMARY KEY, visitor_id VARCHAR(64), session_id VARCHAR(64), usuario_id INT DEFAULT NULL, event_type VARCHAR(50), page_url VARCHAR(500), page_type VARCHAR(50), product_id INT DEFAULT NULL, category_id INT DEFAULT NULL, element_text VARCHAR(255), metadata JSON, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_visitor(visitor_id), INDEX idx_event(event_type), INDEX idx_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $eventType = $data['event_type'] ?? $data['tipo'];
                $stBe = $pdo->prepare("INSERT INTO behavior_events (visitor_id, session_id, usuario_id, event_type, page_url, page_type, product_id, element_text, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stBe->execute([
                    $visitorId,
                    $sessionId,
                    $usuarioId,
                    $eventType,
                    substr((string)$data['pagina'], 0, 500),
                    $data['page_type'] ?? null,
                    isset($data['product_id']) ? (int)$data['product_id'] : null,
                    isset($data['elemento']) ? substr((string)$data['elemento'], 0, 255) : null,
                    json_encode(array_intersect_key($data, array_flip(['utm_source','utm_medium','utm_campaign','device_type','referrer'])))
                ]);
            } catch (\Exception $e) {}

            // Update/create visitor session
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_sessions (id BIGINT AUTO_INCREMENT PRIMARY KEY, visitor_id VARCHAR(64), session_id VARCHAR(64), usuario_id INT DEFAULT NULL, landing_page VARCHAR(500), referrer_url VARCHAR(500), utm_source VARCHAR(100), utm_medium VARCHAR(100), utm_campaign VARCHAR(200), device_type VARCHAR(20), pages_viewed INT DEFAULT 0, converted TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_session(session_id), INDEX idx_visitor(visitor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stSess = $pdo->prepare("INSERT INTO visitor_sessions (visitor_id, session_id, usuario_id, landing_page, referrer_url, utm_source, utm_medium, utm_campaign, device_type, pages_viewed, last_activity_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE pages_viewed = pages_viewed + 1, last_activity_at = NOW(), usuario_id = COALESCE(VALUES(usuario_id), usuario_id)");
                $stSess->execute([
                    $visitorId, $sessionId, $usuarioId,
                    substr((string)$data['pagina'], 0, 500),
                    substr((string)($data['referrer'] ?? ''), 0, 500),
                    substr((string)($data['utm_source'] ?? ''), 0, 100),
                    substr((string)($data['utm_medium'] ?? ''), 0, 100),
                    substr((string)($data['utm_campaign'] ?? ''), 0, 200),
                    $data['device_type'] ?? 'desktop'
                ]);
            } catch (\Exception $e) {}

            // Update visitor score
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_scores (id INT AUTO_INCREMENT PRIMARY KEY, visitor_id VARCHAR(64) NOT NULL, usuario_id INT DEFAULT NULL, score INT DEFAULT 0, classificacao ENUM('frio','morno','quente','muito_quente') DEFAULT 'frio', ultima_visita DATETIME, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_visitor(visitor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $scoreAdd = 1; // pageview
                $eventType = $data['event_type'] ?? $data['tipo'];
                if ($eventType === 'product_view') $scoreAdd = 2;
                elseif ($eventType === 'click') $scoreAdd = 1;
                elseif ($eventType === 'add_to_cart') $scoreAdd = 6;
                elseif ($eventType === 'begin_checkout') $scoreAdd = 8;
                elseif ($eventType === 'purchase_completed') $scoreAdd = 15;

                $pdo->prepare("INSERT INTO visitor_scores (visitor_id, usuario_id, score, ultima_visita) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE score = score + ?, usuario_id = COALESCE(VALUES(usuario_id), usuario_id), ultima_visita = NOW(), classificacao = CASE WHEN score + ? > 50 THEN 'muito_quente' WHEN score + ? > 25 THEN 'quente' WHEN score + ? > 10 THEN 'morno' ELSE 'frio' END")->execute([$visitorId, $usuarioId, $scoreAdd, $scoreAdd, $scoreAdd, $scoreAdd, $scoreAdd]);
            } catch (\Exception $e) {}

            // Link visitor to user if logged in
            if ($usuarioId) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_customer_links (id INT AUTO_INCREMENT PRIMARY KEY, visitor_id VARCHAR(64), usuario_id INT, linked_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_vu(visitor_id, usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $pdo->prepare("INSERT IGNORE INTO visitor_customer_links (visitor_id, usuario_id) VALUES (?, ?)")->execute([$visitorId, $usuarioId]);
                } catch (\Exception $e) {}
            }

            // Save cookie consent if provided
            if (($data['tipo'] ?? '') === 'consent') {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS cookie_consents (id INT AUTO_INCREMENT PRIMARY KEY, visitor_id VARCHAR(64), usuario_id INT DEFAULT NULL, accepted_essential TINYINT(1) DEFAULT 1, accepted_analytics TINYINT(1) DEFAULT 0, accepted_marketing TINYINT(1) DEFAULT 0, policy_version VARCHAR(20) DEFAULT '1.0', ip_anonymized VARCHAR(45), user_agent VARCHAR(500), consented_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_visitor(visitor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $ipAnon = preg_replace('/\.\d+$/', '.0', $_SERVER['REMOTE_ADDR'] ?? '');
                    $pdo->prepare("INSERT INTO cookie_consents (visitor_id, usuario_id, accepted_essential, accepted_analytics, accepted_marketing, ip_anonymized, user_agent) VALUES (?, ?, 1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE accepted_analytics=VALUES(accepted_analytics), accepted_marketing=VALUES(accepted_marketing), consented_at=NOW()")->execute([
                        $visitorId, $usuarioId,
                        !empty($data['consent_analytics']) ? 1 : 0,
                        !empty($data['consent_marketing']) ? 1 : 0,
                        $ipAnon,
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                    ]);
                } catch (\Exception $e) {}
            }

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

        $input = json_decode(file_get_contents('php://input'), true);
        $perguntaUsuario = trim((string)($input['pergunta'] ?? ''));

        // Collect data for AI analysis
        $dados = [];
        try {
            $dados['top_paginas'] = $pdo->query("SELECT pagina, COUNT(*) AS visitas, COUNT(DISTINCT session_id) AS sessoes, AVG(CASE WHEN tipo='time_on_page' THEN tempo_segundos END) AS tempo_medio, MAX(CASE WHEN tipo='scroll' THEN scroll_depth END) AS max_scroll FROM site_heatmap_events GROUP BY pagina ORDER BY visitas DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['top_cliques'] = $pdo->query("SELECT elemento, pagina, COUNT(*) AS cliques FROM site_heatmap_events WHERE tipo='click' AND elemento IS NOT NULL GROUP BY elemento, pagina ORDER BY cliques DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['scroll_abandono'] = $pdo->query("SELECT pagina, AVG(scroll_depth) AS scroll_medio FROM site_heatmap_events WHERE tipo='scroll' GROUP BY pagina ORDER BY scroll_medio ASC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['tempo_baixo'] = $pdo->query("SELECT pagina, AVG(tempo_segundos) AS tempo_medio FROM site_heatmap_events WHERE tipo='time_on_page' AND tempo_segundos IS NOT NULL GROUP BY pagina HAVING tempo_medio < 10 ORDER BY tempo_medio ASC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $dados['total_sessoes'] = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_heatmap_events")->fetchColumn();
        } catch (\Exception $e) {}

        // Enriched behavioral data from cookies
        try {
            $stCheck = $pdo->query("SHOW TABLES LIKE 'visitor_sessions'");
            if ($stCheck && $stCheck->fetchColumn()) {
                $dados['dispositivos'] = $pdo->query("SELECT device_type, COUNT(*) AS total FROM visitor_sessions GROUP BY device_type")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $dados['origens'] = $pdo->query("SELECT utm_source, COUNT(*) AS total FROM visitor_sessions WHERE utm_source IS NOT NULL AND utm_source != '' GROUP BY utm_source ORDER BY total DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}
        try {
            $stCheck2 = $pdo->query("SHOW TABLES LIKE 'visitor_scores'");
            if ($stCheck2 && $stCheck2->fetchColumn()) {
                $dados['scores'] = $pdo->query("SELECT classificacao, COUNT(*) AS total FROM visitor_scores GROUP BY classificacao")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}
        try {
            $stCheck3 = $pdo->query("SHOW TABLES LIKE 'behavior_events'");
            if ($stCheck3 && $stCheck3->fetchColumn()) {
                $dados['eventos_carrinho'] = (int)$pdo->query("SELECT COUNT(*) FROM behavior_events WHERE event_type='add_to_cart'")->fetchColumn();
                $dados['eventos_checkout'] = (int)$pdo->query("SELECT COUNT(*) FROM behavior_events WHERE event_type='begin_checkout'")->fetchColumn();
                $dados['exit_intents'] = (int)$pdo->query("SELECT COUNT(*) FROM behavior_events WHERE event_type='exit_intent'")->fetchColumn();
                $dados['buscas'] = $pdo->query("SELECT element_text, COUNT(*) AS total FROM behavior_events WHERE event_type='search_performed' AND element_text IS NOT NULL GROUP BY element_text ORDER BY total DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
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

        if ($perguntaUsuario !== '') {
            $userMsg .= "\n\nPERGUNTA DO PROPRIETÁRIO DA LOJA:\n" . $perguntaUsuario . "\n\nResponda focando nesta dúvida específica, usando os dados acima como base.";
        }

        // Add enriched behavioral data
        if (!empty($dados['dispositivos'])) {
            $userMsg .= "\n\nDISPOSITIVOS (dados de cookies):\n";
            foreach ($dados['dispositivos'] as $d) $userMsg .= "- " . $d['device_type'] . ": " . $d['total'] . " sessões\n";
        }
        if (!empty($dados['origens'])) {
            $userMsg .= "\nORIGENS DO TRÁFEGO (UTM - cookies):\n";
            foreach ($dados['origens'] as $o) $userMsg .= "- " . $o['utm_source'] . ": " . $o['total'] . " visitas\n";
        }
        if (!empty($dados['scores'])) {
            $userMsg .= "\nSCORE COMPORTAMENTAL DOS VISITANTES (cookies):\n";
            foreach ($dados['scores'] as $s) $userMsg .= "- " . $s['classificacao'] . ": " . $s['total'] . " visitantes\n";
        }
        if (($dados['eventos_carrinho'] ?? 0) > 0 || ($dados['eventos_checkout'] ?? 0) > 0) {
            $userMsg .= "\nEVENTOS DE CONVERSÃO (cookies):\n";
            $userMsg .= "- Adições ao carrinho: " . ($dados['eventos_carrinho'] ?? 0) . "\n";
            $userMsg .= "- Inícios de checkout: " . ($dados['eventos_checkout'] ?? 0) . "\n";
            $userMsg .= "- Intenções de sair: " . ($dados['exit_intents'] ?? 0) . "\n";
        }
        if (!empty($dados['buscas'])) {
            $userMsg .= "\nBUSCAS REALIZADAS (cookies):\n";
            foreach ($dados['buscas'] as $b) $userMsg .= "- \"" . ($b['element_text'] ?? '') . "\" (" . $b['total'] . "x)\n";
        }
        $userMsg .= "\n\n[NOTA: Dados marcados com (cookies) foram coletados via cookies analíticos com consentimento do visitante.]";

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
    private function renderPage(array $stats, array $topPages, array $topClicks, array $scrollData, array $funnel, Request $request, int $periodo): void {
        $pdo = Database::getConnection();
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mapa de Calor - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/assets/css/dashboard-redesign.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('mapa-calor-site');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="dashboard-page">';

        // Header
        echo '<header class="page-header"><div><h1 class="page-title">Mapa de Calor do Site</h1><p class="page-subtitle">Veja onde seus clientes clicam, até onde rolam a página e quanto tempo ficam em cada seção</p></div>
<div style="display:flex;gap:8px;">
<button class="btn-dash-primary" onclick="analisarComIA()" style="padding:8px 16px;font-size:13px;"><i class="bi bi-stars me-1"></i>Análise de IA</button>
</div></header>';

        // Global period filter
        $periodos = [7=>'7 dias',14=>'14 dias',30=>'30 dias',60=>'60 dias',90=>'90 dias'];
        // Mobile: Dropdown
        echo '<div class="d-md-none mb-3"><select class="form-select form-select-sm" onchange="window.location.href=\'?periodo=\'+this.value">';
        foreach ($periodos as $dias => $label) {
            $sel = ($periodo == $dias) ? ' selected' : '';
            echo '<option value="'.$dias.'"'.$sel.'>Período: '.$label.'</option>';
        }
        echo '</select></div>';
        // Desktop: Buttons
        echo '<div style="display:none;gap:6px;margin-bottom:16px;align-items:center;flex-wrap:wrap;" class="d-md-flex">
<span style="font-size:12px;color:#94A3B8;font-weight:600;">Período:</span>';
        foreach ($periodos as $dias => $label) {
            $active = ($periodo == $dias) ? 'background:#18253D;color:#fff;border-color:#18253D;' : '';
            echo '<a href="?periodo='.$dias.'" style="padding:5px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;color:#374151;'.$active.'">'.$label.'</a>';
        }
        echo '</div>';

        // KPIs
        echo '<div class="kpi-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Interações</div><div class="kpi-value">'.number_format($stats['total_eventos']).'</div><div class="kpi-subtext">Ações registradas</div></div></div>
<div class="kpi-card"><div><div class="kpi-label">Páginas Visitadas</div><div class="kpi-value">'.$stats['paginas_unicas'].'</div><div class="kpi-subtext">Diferentes</div></div></div>
<div class="kpi-card"><div><div class="kpi-label">Visitantes</div><div class="kpi-value">'.number_format($stats['sessoes']).'</div><div class="kpi-subtext">Pessoas navegando</div></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Cliques</div><div class="kpi-value">'.number_format($stats['cliques']).'</div><div class="kpi-subtext">Onde clicaram</div></div></div>
</div>
<style>@media(min-width:768px){.kpi-grid{grid-template-columns:repeat(4,1fr) !important;}.mapa-grid-2col{grid-template-columns:1fr 1fr !important;}}</style>';

        // Info about tracking
        if ($stats['total_eventos'] === 0) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;">
<i class="bi bi-info-circle" style="font-size:40px;color:#94A3B8;"></i>
<h5 style="color:var(--navy);margin-top:16px;">Coletando dados...</h5>
<p style="color:#64748B;max-width:500px;margin:8px auto;">O sistema está ativo e coletando informações sobre como seus clientes navegam no site. Os dados aparecerão aqui assim que houver visitas. Isso acontece automaticamente — não precisa fazer nada.</p>
</div></div>';
        }

        // Data sources info block
        if ($stats['total_eventos'] > 0) {
            echo '<div class="section-card" style="border-left:3px solid #18253D;"><div class="section-body" style="padding:14px 18px;">
<div style="font-size:13px;font-weight:700;color:#18253D;margin-bottom:8px;"><i class="bi bi-info-circle me-1"></i>Informações consideradas nesta análise</div>
<p style="font-size:12px;color:#64748B;margin-bottom:6px;">Esta análise usa dados de navegação coletados por cookies (com consentimento): páginas visitadas, cliques, rolagem, tempo na página, produtos visualizados, eventos de carrinho e checkout.</p>
<div style="font-size:11px;color:#94A3B8;"><i class="bi bi-cookie me-1"></i>Dados coletados com consentimento do visitante.</div>
</div></div>';
        }

        // Top Pages
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">Páginas Mais Acessadas</h2></div><div class="section-body">';
        if (!empty($topPages)) {
            echo '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#FAFBFC;"><th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Página</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Acessos</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;" class="d-none d-md-table-cell">Visitantes</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;" class="d-none d-md-table-cell">Tempo</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;" class="d-none d-md-table-cell">Scroll</th></tr></thead><tbody>';
            foreach ($topPages as $p) {
                $tempo = $p['tempo_medio'] ? gmdate('i:s', (int)$p['tempo_medio']) : '-';
                $scroll = $p['max_scroll'] ? $p['max_scroll'].'%' : '-';
                echo '<tr style="border-bottom:1px solid #F1F5F9;"><td style="padding:10px 14px;word-break:break-all;"><strong>'.htmlspecialchars($p['pagina']).'</strong></td><td style="padding:10px 14px;text-align:center;">'.(int)$p['visitas'].'</td><td style="padding:10px 14px;text-align:center;" class="d-none d-md-table-cell">'.(int)$p['sessoes_unicas'].'</td><td style="padding:10px 14px;text-align:center;" class="d-none d-md-table-cell">'.$tempo.'</td><td style="padding:10px 14px;text-align:center;" class="d-none d-md-table-cell">'.$scroll.'</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p style="color:#94A3B8;text-align:center;">Sem dados ainda.</p>';
        }
        echo '</div></div>';

        // Funnel + Scroll side by side
        echo '<div style="display:grid;grid-template-columns:1fr;gap:14px;margin-bottom:16px;" class="mapa-grid-2col">';

        // Funnel
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title">Caminho dos Clientes</h2></div><div class="section-body">
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
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title">Até onde rolam a página</h2></div><div class="section-body">
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
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">O que mais clicam</h2></div><div class="section-body">
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

        // ============================================================
        // ANALYTICS: Products, Temporal Graph, Origins
        // ============================================================
        try {

        // Products Analytics
        $prodMaisVisitados = []; $prodMaisComprados = [];
        try {
            $nomeCol = 'nome';
            try { $cols = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN) ?: []; if (!in_array('nome', $cols) && in_array('name', $cols)) $nomeCol = 'name'; } catch (\Throwable $e) {}

            $rows = $pdo->query("SELECT SUBSTRING_INDEX(e.pagina, '/', -1) AS prod_id, COUNT(*) AS visitas FROM site_heatmap_events e WHERE e.pagina LIKE '/produto/detalhes/%' AND e.tipo = 'pageview' AND e.created_at >= DATE_SUB(NOW(), INTERVAL {$periodo} DAY) GROUP BY prod_id ORDER BY visitas DESC LIMIT 8")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) {
                $ids = array_filter(array_map(function($p){ return (int)$p['prod_id']; }, $rows));
                if (!empty($ids)) {
                    $in = implode(',', $ids);
                    $stN = $pdo->query("SELECT id, {$nomeCol} FROM produtos WHERE id IN ({$in})");
                    $nomes = [];
                    foreach (($stN ? $stN->fetchAll(\PDO::FETCH_ASSOC) : []) as $n) { $nomes[(int)$n['id']] = $n[$nomeCol] ?? ''; }
                    foreach ($rows as $r) { $prodMaisVisitados[] = ['nome' => $nomes[(int)$r['prod_id']] ?? 'Produto #'.$r['prod_id'], 'visitas' => (int)$r['visitas']]; }
                }
            }
        } catch (\Throwable $e) {}
        try {
            $itensTable = 'pedido_itens';
            try { $pdo->query("SELECT 1 FROM pedido_itens LIMIT 1"); } catch (\Throwable $e) { $itensTable = 'pedido_items'; }
            $prodMaisComprados = $pdo->query("SELECT p.{$nomeCol} AS nome, COUNT(DISTINCT pi2.pedido_id) AS vendas FROM {$itensTable} pi2 JOIN pedidos ped ON ped.id = pi2.pedido_id AND ped.status IN ('pago','entregue') JOIN produtos p ON p.id = pi2.produto_id WHERE ped.created_at > DATE_SUB(NOW(), INTERVAL {$periodo} DAY) GROUP BY p.id, p.{$nomeCol} ORDER BY vendas DESC LIMIT 8")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}
        echo '<div style="display:grid;grid-template-columns:1fr;gap:14px;margin-bottom:16px;" class="mapa-grid-2col">';
        // Most visited products
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title">Produtos Mais Visitados</h2></div><div class="section-body">';
        try {
        if (!empty($prodMaisVisitados)) {
            foreach ($prodMaisVisitados as $i => $pv) {
                echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F1F5F9;"><span style="font-size:13px;color:#374151;">'.($i+1).'. '.htmlspecialchars($pv['nome']).'</span><span style="font-size:13px;font-weight:700;color:#18253D;">'.(int)$pv['visitas'].' visitas</span></div>';
            }
        } else { echo '<p style="color:#94A3B8;font-size:12px;">Sem dados ainda.</p>'; }
        } catch (\Exception $e) { echo '<p style="color:#94A3B8;font-size:12px;">Sem dados.</p>'; }
        echo '</div></div>';

        // Most purchased products
        echo '<div class="section-card" style="margin-bottom:0;"><div class="section-card-header"><h2 class="section-title">Produtos Mais Comprados</h2></div><div class="section-body">';
        try {
        if (!empty($prodMaisComprados)) {
            foreach ($prodMaisComprados as $i => $pc) {
                echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F1F5F9;"><span style="font-size:13px;color:#374151;">'.($i+1).'. '.htmlspecialchars($pc['nome']).'</span><span style="font-size:13px;font-weight:700;color:#065F46;">'.(int)$pc['vendas'].' vendas</span></div>';
            }
        } else { echo '<p style="color:#94A3B8;font-size:12px;">Sem dados ainda.</p>'; }
        } catch (\Exception $e) { echo '<p style="color:#94A3B8;font-size:12px;">Sem dados.</p>'; }
        echo '</div></div>';
        echo '</div>';

        // Temporal Graph (uses individual 'dias' param if set, otherwise global 'periodo')
        $diasGrafico = (int)($request->getParam('dias', 0));
        if ($diasGrafico <= 0) $diasGrafico = $periodo; // Use global period as default
        if ($diasGrafico < 7) $diasGrafico = 7;
        if ($diasGrafico > 90) $diasGrafico = 90;
        $graphData = ['labels'=>[],'visitas'=>[],'pedidos'=>[],'novos_usuarios'=>[]];
        try {
            for ($d = $diasGrafico - 1; $d >= 0; $d--) {
                $date = date('Y-m-d', strtotime("-{$d} days"));
                $graphData['labels'][] = date('d/m', strtotime($date));
                $graphData['visitas'][] = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_heatmap_events WHERE DATE(created_at) = '{$date}'")->fetchColumn();
                $graphData['pedidos'][] = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = '{$date}' AND status IN ('pago','entregue','enviado')")->fetchColumn();
                $graphData['novos_usuarios'][] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = '{$date}'")->fetchColumn();
            }
        } catch (\Exception $e) {}

        echo '<div class="section-card"><div class="section-card-header" style="flex-wrap:wrap;gap:8px;"><h2 class="section-title">Evolução</h2>
<div style="display:flex;gap:6px;align-items:center;">
<a href="?periodo='.$periodo.'&dias=7" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;text-decoration:none;color:'.($diasGrafico==7?'#fff':'#374151').';background:'.($diasGrafico==7?'#18253D':'#fff').';">7 dias</a>
<a href="?periodo='.$periodo.'&dias=14" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;text-decoration:none;color:'.($diasGrafico==14?'#fff':'#374151').';background:'.($diasGrafico==14?'#18253D':'#fff').';">14 dias</a>
<a href="?periodo='.$periodo.'&dias=30" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;text-decoration:none;color:'.($diasGrafico==30?'#fff':'#374151').';background:'.($diasGrafico==30?'#18253D':'#fff').';">30 dias</a>
<a href="?periodo='.$periodo.'&dias=60" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;text-decoration:none;color:'.($diasGrafico==60?'#fff':'#374151').';background:'.($diasGrafico==60?'#18253D':'#fff').';">60 dias</a>
<a href="?periodo='.$periodo.'&dias=90" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;text-decoration:none;color:'.($diasGrafico==90?'#fff':'#374151').';background:'.($diasGrafico==90?'#18253D':'#fff').';">90 dias</a>
</div></div><div class="section-body">
<canvas id="chartTemporal" style="width:100%;height:280px;"></canvas>
</div></div>';

        // Origins block (cookies)
        $origens = [];
        try {
            $stCheck = $pdo->query("SHOW TABLES LIKE 'visitor_sessions'");
            if ($stCheck && $stCheck->fetchColumn()) {
                $origens = $pdo->query("SELECT COALESCE(NULLIF(utm_source,''),'Direto') AS origem, device_type, COUNT(*) AS total, COUNT(DISTINCT visitor_id) AS visitantes FROM visitor_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$periodo} DAY) GROUP BY COALESCE(NULLIF(utm_source,''),'Direto'), device_type ORDER BY total DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}

        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">Origem dos Visitantes (Cookies)</h2></div><div class="section-body">';
        if (!empty($origens)) {
            echo '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#FAFBFC;"><th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Origem</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;" class="d-none d-md-table-cell">Dispositivo</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Sessões</th><th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;" class="d-none d-md-table-cell">Únicos</th></tr></thead><tbody>';
            foreach ($origens as $o) {
                $deviceIcon = $o['device_type'] === 'mobile' ? '📱' : ($o['device_type'] === 'tablet' ? '📟' : '💻');
                echo '<tr style="border-bottom:1px solid #F1F5F9;"><td style="padding:10px 14px;font-weight:600;color:#18253D;word-break:break-word;">'.htmlspecialchars($o['origem']).'</td><td style="padding:10px 14px;text-align:center;" class="d-none d-md-table-cell">'.$deviceIcon.' '.ucfirst($o['device_type']).'</td><td style="padding:10px 14px;text-align:center;">'.(int)$o['total'].'</td><td style="padding:10px 14px;text-align:center;" class="d-none d-md-table-cell">'.(int)$o['visitantes'].'</td></tr>';
            }
            echo '</tbody></table>';
            echo '<div style="margin-top:10px;font-size:11px;color:#94A3B8;">Dados obtidos via cookies analíticos com consentimento do visitante.</div>';
        } else {
            echo '<p style="color:#94A3B8;font-size:12px;">Dados de origem serão exibidos após visitantes aceitarem cookies analíticos.</p>';
        }
        echo '</div></div>';

        } catch (\Throwable $e) {
            echo '<div class="section-card"><div class="section-body"><p style="color:#94A3B8;">Erro ao carregar analytics: '.htmlspecialchars($e->getMessage()).'</p></div></div>';
        }

        // Heatmap Visualization Section
        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">Visualização do Mapa de Calor</h2></div><div class="section-body">
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
<div id="heatmapContainer" style="position:relative;width:100%;height:700px;border-radius:10px;overflow-y:scroll;overflow-x:hidden;border:1px solid #E2E8F0;">
<div id="heatmapInner" style="position:relative;width:100%;height:2000px;">
<iframe id="heatmapIframe" src="/" style="width:100%;height:2000px;border:none;pointer-events:none;"></iframe>
<canvas id="heatmapCanvas" style="position:absolute;top:0;left:0;width:100%;height:2000px;pointer-events:none;opacity:0.55;"></canvas>
</div>
<div id="heatmapInfo" style="position:sticky;bottom:10px;left:10px;background:rgba(0,0,0,.7);color:#fff;padding:6px 12px;border-radius:6px;font-size:11px;display:inline-block;margin:10px;">Selecione uma página para visualizar</div>
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
<h6 style="margin:0;font-size:15px;font-weight:700;"><i class="bi bi-stars me-2"></i>Análise de IA</h6>
<button onclick="document.getElementById(\'modalAnaliseIA\').style.display=\'none\'" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
</div>
<div style="padding:20px;">
<div style="margin-bottom:16px;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;color:#94A3B8;display:block;margin-bottom:6px;">Sua dúvida ou pergunta (opcional)</label>
<div style="display:flex;gap:8px;align-items:flex-start;">
<textarea id="iaHeatmapPergunta" rows="2" placeholder="Ex: Por que os clientes não estão comprando? Onde estão travando no checkout?" style="flex:1;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
<button type="button" id="btnMicHeatmap" onclick="toggleMicHeatmap()" style="width:40px;height:40px;border-radius:50%;border:2px solid #E2E8F0;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-mic-fill" style="font-size:16px;color:#64748B;"></i></button>
</div>
<small id="micHeatmapStatus" style="color:#94A3B8;font-size:11px;">Deixe vazio para análise geral. Ou pergunte algo específico.</small>
</div>
<button onclick="executarAnaliseIA()" class="btn-dash-primary" style="width:100%;padding:10px;font-size:14px;margin-bottom:16px;"><i class="bi bi-stars me-1"></i>Analisar</button>
<div id="analiseIAContent"></div>
</div>
</div></div>';

        echo '</div></main></div></div>';
        renderAdminScripts();
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
        echo '<script>
// Temporal chart
var chartData = ' . json_encode($graphData) . ';
if(chartData && chartData.labels && chartData.labels.length && document.getElementById("chartTemporal")){
    new Chart(document.getElementById("chartTemporal"), {
        type: "line",
        data: {
            labels: chartData.labels,
            datasets: [
                {label:"Visitas", data:chartData.visitas, borderColor:"#F97316", backgroundColor:"rgba(249,115,22,.1)", tension:0.4, fill:true, borderWidth:2},
                {label:"Pedidos", data:chartData.pedidos, borderColor:"#16A34A", backgroundColor:"rgba(22,163,74,.1)", tension:0.4, fill:true, borderWidth:2},
                {label:"Novos Usuários", data:chartData.novos_usuarios, borderColor:"#2563EB", backgroundColor:"rgba(37,99,235,.1)", tension:0.4, fill:true, borderWidth:2}
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:"bottom",labels:{font:{size:12},padding:16}}},
            scales:{y:{beginAtZero:true,grid:{color:"#F1F5F9"}},x:{grid:{display:false}}}
        }
    });
}
</script>';
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
    var inner = document.getElementById("heatmapInner");
    canvas.width = inner.offsetWidth;
    canvas.height = inner.offsetHeight;
    var ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Find max intensity
    var maxInt = 1;
    cliques.forEach(function(c){ if(c.intensidade > maxInt) maxInt = c.intensidade; });
    
    // Draw heatmap points - x and y are percentages (0-100) of page width/height
    cliques.forEach(function(c){
        var x = (parseFloat(c.x) / 100) * canvas.width;
        var y = (parseFloat(c.y) / 100) * canvas.height;
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
    document.getElementById("analiseIAContent").innerHTML = "";
}

async function executarAnaliseIA(){
    var pergunta = document.getElementById("iaHeatmapPergunta").value.trim();
    document.getElementById("analiseIAContent").innerHTML = \'<div style="text-align:center;padding:30px;"><i class="bi bi-stars" style="font-size:28px;color:var(--navy);animation:spin 2s linear infinite;"></i><p style="color:#64748B;margin-top:10px;">Analisando...</p></div>\';
    
    var body = JSON.stringify({pergunta: pergunta});
    var r = await fetch("/admin/mapa-calor-site/analise-ia", {method:"POST", headers:{"Content-Type":"application/json"}, body: body});
    var d = await r.json();
    
    if(d.success && d.analise){
        document.getElementById("analiseIAContent").innerHTML = \'<div style="font-size:14px;color:#374151;line-height:1.8;white-space:pre-wrap;">\' + d.analise + \'</div>\';
    } else {
        document.getElementById("analiseIAContent").innerHTML = \'<p style="color:#BE123C;">\' + (d.error || "Erro na análise") + \'</p>\';
    }
}

var heatmapRecorder = null;
var heatmapRecording = false;
function toggleMicHeatmap(){
    if(heatmapRecording){ stopMicHeatmap(); return; }
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
        heatmapRecorder = new MediaRecorder(stream);
        var chunks = [];
        heatmapRecorder.ondataavailable = function(e){ chunks.push(e.data); };
        heatmapRecorder.onstop = async function(){
            stream.getTracks().forEach(function(t){t.stop();});
            var blob = new Blob(chunks,{type:"audio/webm"});
            document.getElementById("micHeatmapStatus").textContent = "Transcrevendo...";
            var fd = new FormData(); fd.append("audio", blob, "audio.webm");
            var r = await fetch("/admin/email-marketing/transcrever", {method:"POST", body:fd});
            var d = await r.json();
            if(d.success && d.text){
                document.getElementById("iaHeatmapPergunta").value += (document.getElementById("iaHeatmapPergunta").value ? " " : "") + d.text;
                document.getElementById("micHeatmapStatus").textContent = "Transcrição adicionada!";
            } else {
                document.getElementById("micHeatmapStatus").textContent = d.error || "Erro";
            }
        };
        heatmapRecorder.start();
        heatmapRecording = true;
        document.getElementById("btnMicHeatmap").style.borderColor = "#BE123C";
        document.getElementById("btnMicHeatmap").style.background = "#FFE4E6";
        document.getElementById("micHeatmapStatus").textContent = "Gravando... clique para parar";
    }).catch(function(){ document.getElementById("micHeatmapStatus").textContent = "Microfone não disponível"; });
}
function stopMicHeatmap(){
    if(heatmapRecorder && heatmapRecorder.state !== "inactive") heatmapRecorder.stop();
    heatmapRecording = false;
    document.getElementById("btnMicHeatmap").style.borderColor = "#E2E8F0";
    document.getElementById("btnMicHeatmap").style.background = "#fff";
}

// Auto-load first page
document.addEventListener("DOMContentLoaded", function(){ carregarHeatmap(); });
</script>';
        echo '</body></html>';
    }
}
