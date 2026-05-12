<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminEmailMarketingController extends Controller {

    private function ensureTables(\PDO $pdo): void {
        $tables = ['email_mkt_segmentos','email_mkt_campanhas','email_mkt_campanha_clientes','email_mkt_logs','email_mkt_config','email_mkt_envio_controle'];
        foreach ($tables as $t) {
            try { $st = $pdo->prepare("SELECT 1 FROM {$t} LIMIT 1"); $st->execute(); } catch (\Exception $e) {
                $sqlFile = __DIR__ . '/../../database/migrations/175_create_email_marketing_schema.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    foreach ($statements as $stmt) { if ($stmt !== '') { try { $pdo->exec($stmt); } catch (\Exception $ex) {} } }
                }
                break;
            }
        }
    }

    private function getConfig(\PDO $pdo, string $chave, string $default = ''): string {
        try {
            $st = $pdo->prepare("SELECT valor FROM email_mkt_config WHERE chave = ? LIMIT 1");
            $st->execute([$chave]);
            $v = $st->fetchColumn();
            return $v !== false ? (string)$v : $default;
        } catch (\Exception $e) { return $default; }
    }

    private function getChatGPTApiKey(\PDO $pdo): ?string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $v = (string)($st->fetchColumn() ?: '');
            return $v !== '' ? $v : null;
        } catch (\Exception $e) { return null; }
    }

    private function getChatGPTModel(\PDO $pdo): string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_model']);
            $v = trim((string)($st->fetchColumn() ?: ''));
            return $v !== '' ? $v : 'gpt-4o-mini';
        } catch (\Exception $e) { return 'gpt-4o-mini'; }
    }

    private function callAI(\PDO $pdo, string $systemPrompt, string $userMessage): array {
        $apiKey = $this->getChatGPTApiKey($pdo);
        if (!$apiKey) return ['error' => 'API Key do ChatGPT não configurada.'];
        $model = $this->getChatGPTModel($pdo);
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userMessage]],
            'temperature' => 0.7, 'max_tokens' => 2000
        ]);
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey], CURLOPT_TIMEOUT=>90]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['error'=>'Conexão: '.$err];
        $data = json_decode($resp, true);
        if ($code !== 200 || !isset($data['choices'][0]['message']['content']))
            return ['error' => $data['error']['message'] ?? 'Erro API (HTTP '.$code.')'];
        return ['text' => trim($data['choices'][0]['message']['content'])];
    }

    // ============================================================
    // DASHBOARD
    // ============================================================
    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);

        $tab = strtolower(trim((string)$request->getParam('tab', 'dashboard')));
        $validTabs = ['dashboard','campanhas','pendentes','aprovadas','agendadas','historico','segmentos','gatilhos','templates','config','logs','metricas'];
        if (!in_array($tab, $validTabs)) $tab = 'dashboard';

        // Stats
        $stats = ['total_campanhas'=>0,'pendentes'=>0,'aprovadas'=>0,'enviadas'=>0,'abertas'=>0,'clicadas'=>0,'convertidas'=>0,'rejeitadas'=>0];
        try {
            $stats['total_campanhas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas")->fetchColumn();
            $stats['pendentes'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='pendente_revisao'")->fetchColumn();
            $stats['aprovadas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='aprovada'")->fetchColumn();
            $stats['enviadas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_enviado),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['abertas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_aberto),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['clicadas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_clicado),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['convertidas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_convertido),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['rejeitadas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='rejeitada'")->fetchColumn();
        } catch (\Exception $e) {}

        // Campanhas list based on tab
        $campanhas = [];
        try {
            $where = '1=1';
            if ($tab === 'pendentes') $where = "status='pendente_revisao'";
            elseif ($tab === 'aprovadas') $where = "status IN ('aprovada','agendada')";
            elseif ($tab === 'agendadas') $where = "status='agendada'";
            elseif ($tab === 'historico') $where = "status IN ('finalizada','cancelada','rejeitada')";
            elseif ($tab === 'campanhas') $where = "1=1";
            $st = $pdo->query("SELECT * FROM email_mkt_campanhas WHERE {$where} ORDER BY updated_at DESC LIMIT 50");
            $campanhas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->renderPage($tab, $stats, $campanhas, $pdo);
    }

    // ============================================================
    // GERAR CAMPANHAS VIA IA
    // ============================================================
    public function gerarCampanhas(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);

        $diasRecompra = (int)$this->getConfig($pdo, 'dias_recompra_minimo', '30');
        $nomeLoja = 'Braziliana';
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'loja_nome' OR (categoria='loja' AND chave='nome') LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v) $nomeLoja = $v;
        } catch (\Exception $e) {}

        // Analyze clients
        $userNomeCol = 'nome'; $userEmailCol = 'email';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        // Find clients without purchase in 30+ days
        $clientes30 = [];
        try {
            $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra,
                    COUNT(p.id) AS total_pedidos, COALESCE(AVG(p.valor_total),0) AS ticket_medio
                    FROM usuarios u
                    LEFT JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                    GROUP BY u.id, u.{$userNomeCol}, u.email
                    HAVING ultima_compra IS NOT NULL AND ultima_compra < DATE_SUB(NOW(), INTERVAL {$diasRecompra} DAY)
                    ORDER BY ultima_compra ASC LIMIT 100";
            $clientes30 = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        if (empty($clientes30)) {
            echo json_encode(['success' => true, 'message' => 'Nenhum cliente elegível para campanha no momento.', 'campanhas_geradas' => 0]);
            return;
        }

        // Group by days since last purchase
        $grupos = ['30_dias' => [], '60_dias' => [], '90_dias' => []];
        foreach ($clientes30 as $c) {
            $dias = (int)floor((time() - strtotime($c['ultima_compra'])) / 86400);
            if ($dias >= 90) $grupos['90_dias'][] = $c;
            elseif ($dias >= 60) $grupos['60_dias'][] = $c;
            else $grupos['30_dias'][] = $c;
        }

        $campanhasGeradas = 0;
        $tom = $this->getConfig($pdo, 'tom_marca', 'humanizado, elegante, conversacional');
        $palavrasProibidas = $this->getConfig($pdo, 'palavras_proibidas', '');

        foreach ($grupos as $tipo => $clientes) {
            if (empty($clientes)) continue;

            $tipoLabel = str_replace('_', ' ', $tipo);
            $gatilho = 'reativacao_' . str_replace('_dias', '', $tipo);

            // Check if similar campaign already exists recently
            $st = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanhas WHERE gatilho = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND status NOT IN ('rejeitada','cancelada')");
            $st->execute([$gatilho]);
            if ((int)$st->fetchColumn() > 0) continue;

            // Generate campaign content via AI
            $systemPrompt = "Você é um especialista em email marketing para e-commerce. Gere o conteúdo de uma campanha de reativação para clientes que não compram há {$tipoLabel}.
Tom: {$tom}. Palavras proibidas: {$palavrasProibidas}.
NÃO crie descontos, cupons ou promoções. NÃO use urgência falsa.
Retorne em JSON com as chaves: assunto, pre_header, tag_campanha, titulo_email, subtitulo_email, paragrafo_1, paragrafo_2, texto_destaque, paragrafo_fechamento, texto_cta, texto_sub_cta.
O assunto deve ter no máximo 50 caracteres. O corpo total entre 120-220 palavras.";

            $userMsg = "Loja: {$nomeLoja}\nTipo: Reativação {$tipoLabel}\nTotal de clientes: " . count($clientes) . "\nExemplos de nomes: " . implode(', ', array_slice(array_column($clientes, 'nome'), 0, 5));

            $result = $this->callAI($pdo, $systemPrompt, $userMsg);
            if (isset($result['error'])) continue;

            $content = json_decode($result['text'], true);
            if (!$content || !isset($content['assunto'])) {
                // Try to extract JSON from response
                preg_match('/\{.*\}/s', $result['text'], $m);
                if (!empty($m[0])) $content = json_decode($m[0], true);
                if (!$content) continue;
            }

            // Create campaign
            $st = $pdo->prepare("INSERT INTO email_mkt_campanhas (nome, tipo, gatilho, status, assunto, pre_header, variaveis_ia, total_clientes, observacoes_ia) VALUES (?, 'reativacao', ?, 'pendente_revisao', ?, ?, ?, ?, ?)");
            $st->execute([
                "Reativação {$tipoLabel} - " . date('d/m'),
                $gatilho,
                $content['assunto'] ?? 'Sentimos sua falta!',
                $content['pre_header'] ?? '',
                json_encode($content, JSON_UNESCAPED_UNICODE),
                count($clientes),
                "Campanha gerada automaticamente para " . count($clientes) . " clientes sem compra há {$tipoLabel}."
            ]);
            $campanhaId = (int)$pdo->lastInsertId();

            // Link clients
            $stInsert = $pdo->prepare("INSERT IGNORE INTO email_mkt_campanha_clientes (campanha_id, cliente_id, email, nome) VALUES (?, ?, ?, ?)");
            foreach ($clientes as $c) {
                $stInsert->execute([$campanhaId, (int)$c['id'], $c['email'], $c['nome']]);
            }

            // Build HTML from template
            $html = $this->buildEmailHtml($content, $nomeLoja);
            $pdo->prepare("UPDATE email_mkt_campanhas SET html_content = ? WHERE id = ?")->execute([$html, $campanhaId]);

            $campanhasGeradas++;
        }

        echo json_encode(['success' => true, 'campanhas_geradas' => $campanhasGeradas, 'message' => "{$campanhasGeradas} campanha(s) gerada(s) com sucesso."]);
    }

    private function buildEmailHtml(array $vars, string $nomeLoja): string {
        $template = file_get_contents(__DIR__ . '/../../resources/email_marketing_template.html');
        if (!$template) return '<p>Template não encontrado</p>';

        $replacements = [
            '{{NOME_LOJA}}' => htmlspecialchars($nomeLoja),
            '{{TAG_CAMPANHA}}' => htmlspecialchars($vars['tag_campanha'] ?? 'Novidades'),
            '{{TITULO_EMAIL}}' => htmlspecialchars($vars['titulo_email'] ?? $vars['assunto'] ?? ''),
            '{{SUBTITULO_EMAIL}}' => htmlspecialchars($vars['subtitulo_email'] ?? ''),
            '{{ASSUNTO_EMAIL}}' => htmlspecialchars($vars['assunto'] ?? ''),
            '{{NOME_CLIENTE}}' => '{{NOME_CLIENTE}}', // kept for personalization at send time
            '{{PARAGRAFO_1}}' => htmlspecialchars($vars['paragrafo_1'] ?? ''),
            '{{PARAGRAFO_2}}' => htmlspecialchars($vars['paragrafo_2'] ?? ''),
            '{{TEXTO_DESTAQUE}}' => htmlspecialchars($vars['texto_destaque'] ?? ''),
            '{{PARAGRAFO_FECHAMENTO}}' => htmlspecialchars($vars['paragrafo_fechamento'] ?? ''),
            '{{TEXTO_CTA}}' => htmlspecialchars($vars['texto_cta'] ?? 'Visitar a loja'),
            '{{LINK_CTA}}' => 'https://brazilianashop.com.br',
            '{{TEXTO_SUB_CTA}}' => htmlspecialchars($vars['texto_sub_cta'] ?? 'Acesse do celular ou computador'),
            '{{ENDERECO_LOJA}}' => 'Braziliana Shop',
            '{{LINK_DESCADASTRO}}' => '#',
            '{{LINK_POLITICA}}' => '/politica-privacidade',
        ];

        // Remove product section if no products
        $template = preg_replace('/<!-- PRODUTOS SUGERIDOS.*?<\/div>\s*<\/div>/s', '', $template);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // ============================================================
    // APROVAR CAMPANHA
    // ============================================================
    public function aprovar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $uid = (int)($_SESSION['usuario_id'] ?? 0);
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='aprovada', aprovado_por=?, data_aprovacao=NOW() WHERE id=? AND status='pendente_revisao'")->execute([$uid, $id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // REJEITAR CAMPANHA
    // ============================================================
    public function rejeitar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='rejeitada' WHERE id=? AND status IN ('pendente_revisao','rascunho_ia')")->execute([$id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // DETALHES DA CAMPANHA (JSON)
    // ============================================================
    public function detalhes(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $id = (int)$request->getParam('id');
        $st = $pdo->prepare("SELECT * FROM email_mkt_campanhas WHERE id = ?"); $st->execute([$id]);
        $campanha = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$campanha) { echo json_encode(['success'=>false,'error'=>'Não encontrada']); return; }
        // Get clients
        $st2 = $pdo->prepare("SELECT * FROM email_mkt_campanha_clientes WHERE campanha_id = ? ORDER BY id LIMIT 200"); $st2->execute([$id]);
        $clientes = $st2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success'=>true,'campanha'=>$campanha,'clientes'=>$clientes]);
    }

    // ============================================================
    // SALVAR CONFIGURAÇÕES
    // ============================================================
    public function salvarConfig(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) { echo json_encode(['success'=>false,'error'=>'Dados inválidos']); return; }
        foreach ($data as $chave => $valor) {
            $pdo->prepare("INSERT INTO email_mkt_config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$chave, (string)$valor]);
        }
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // RENDER PAGE
    // ============================================================
    private function renderPage(string $tab, array $stats, array $campanhas, \PDO $pdo): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Email Marketing - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/dashboard-redesign.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '<style>
:root{--navy:#18253D;--navy-hover:#243049;}
.mkt-page{padding:24px 0;width:100%;}
.mkt-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px;border-bottom:1px solid #EBF0F6;padding-bottom:12px;}
.mkt-tab{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:500;color:#6B7280;text-decoration:none;border:1px solid transparent;transition:.18s;}
.mkt-tab:hover{color:var(--navy);background:#F8FAFC;}
.mkt-tab.active{background:var(--navy);color:#fff;border-color:var(--navy);}
.mkt-tab .badge-count{font-size:10px;background:rgba(255,255,255,.2);padding:1px 6px;border-radius:99px;margin-left:4px;}
.mkt-tab.active .badge-count{background:rgba(255,255,255,.25);}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:500;}
.st-rascunho{background:#F1F5F9;color:#475569;}.st-pendente{background:#FEF3C7;color:#92400E;}
.st-aprovada{background:#D1FAE5;color:#065F46;}.st-agendada{background:#EDE9FE;color:#5B21B6;}
.st-disparando{background:#E0F2FE;color:#075985;}.st-finalizada{background:#D1FAE5;color:#065F46;}
.st-rejeitada{background:#FFE4E6;color:#9F1239;}.st-cancelada{background:#F1F5F9;color:#475569;}
.camp-table{width:100%;border-collapse:collapse;}
.camp-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;padding:11px 14px;border-bottom:1px solid #EBF0F6;background:#FAFBFC;}
.camp-table td{padding:13px 14px;border-bottom:1px solid #F1F5F9;font-size:13px;color:#374151;}
.camp-table tr:hover td{background:#FAFBFC;}
.btn-navy{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:.18s;}
.btn-navy:hover{background:var(--navy-hover);color:#fff;}
.btn-ghost{background:#fff;color:#374151;border:1px solid #E2E8F0;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:.18s;}
.btn-ghost:hover{background:#F8FAFC;border-color:#CBD5E1;}
</style></head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('email-marketing');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="mkt-page">';

        // Header
        echo '<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
<div><h1 style="font-size:20px;font-weight:700;color:var(--navy);margin:0;">Automações de Email Marketing</h1>
<small style="color:#64748B;">Campanhas inteligentes geradas por IA</small></div>
<button class="btn-navy" onclick="gerarCampanhasIA()"><i class="bi bi-stars me-1"></i>Gerar Campanhas com IA</button>
</div>';

        // KPI Cards
        echo '<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Campanhas</div><div class="kpi-value">'.$stats['total_campanhas'].'</div></div><div class="kpi-icon"><i class="bi bi-envelope-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Pendentes</div><div class="kpi-value">'.$stats['pendentes'].'</div></div><div class="kpi-icon"><i class="bi bi-clock-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Enviados</div><div class="kpi-value">'.$stats['enviadas'].'</div></div><div class="kpi-icon"><i class="bi bi-send-fill"></i></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Convertidos</div><div class="kpi-value">'.$stats['convertidas'].'</div></div><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div></div>
</div>';

        // Tabs
        $tabs = ['dashboard'=>'Dashboard','campanhas'=>'Todas','pendentes'=>'Pendentes','aprovadas'=>'Aprovadas','agendadas'=>'Agendadas','historico'=>'Histórico','segmentos'=>'Segmentos','config'=>'Configurações'];
        echo '<nav class="mkt-tabs">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' active' : '';
            $count = '';
            if ($key === 'pendentes' && $stats['pendentes'] > 0) $count = '<span class="badge-count">'.$stats['pendentes'].'</span>';
            echo '<a class="mkt-tab'.$active.'" href="?tab='.$key.'">'.$label.$count.'</a>';
        }
        echo '</nav>';

        // Content based on tab
        if ($tab === 'config') {
            $this->renderConfigTab($pdo);
        } elseif ($tab === 'dashboard' || $tab === 'campanhas' || $tab === 'pendentes' || $tab === 'aprovadas' || $tab === 'agendadas' || $tab === 'historico') {
            $this->renderCampanhasTable($campanhas);
        } else {
            echo '<div class="section-card"><div class="section-body"><p style="color:#94A3B8;">Seção em desenvolvimento.</p></div></div>';
        }

        // Progress modal
        echo '<div id="progressModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;display:none;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;padding:32px;max-width:400px;width:90%;text-align:center;">
<i class="bi bi-stars" style="font-size:32px;color:var(--navy);"></i>
<h5 style="margin:12px 0 8px;color:var(--navy);">Gerando campanhas...</h5>
<p id="progressText" style="color:#64748B;font-size:13px;">Analisando clientes e criando segmentações</p>
</div></div>';

        // Campaign detail modal
        echo '<div class="modal fade" id="modalCampanha" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
<div class="modal-header" style="background:var(--navy);color:#fff;"><h5 class="modal-title" id="modalCampTitle">Campanha</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="modalCampBody"><p>Carregando...</p></div>
<div class="modal-footer">
<button class="btn btn-success" onclick="aprovarCampanha()"><i class="bi bi-check-lg me-1"></i>Aprovar</button>
<button class="btn btn-danger" onclick="rejeitarCampanha()"><i class="bi bi-x-lg me-1"></i>Rejeitar</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
</div></div></div></div>';

        // JS
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentCampId = null;
async function gerarCampanhasIA(){
    const m=document.getElementById("progressModal");m.style.display="flex";
    const r=await fetch("/admin/email-marketing/gerar",{method:"POST"});
    const d=await r.json();m.style.display="none";
    alert(d.message||"Concluído");location.reload();
}
async function verCampanha(id){
    currentCampId=id;
    const modal=new bootstrap.Modal(document.getElementById("modalCampanha"));
    document.getElementById("modalCampBody").innerHTML="<p>Carregando...</p>";
    modal.show();
    const r=await fetch("/admin/email-marketing/detalhes?id="+id);
    const d=await r.json();
    if(!d.success){document.getElementById("modalCampBody").innerHTML="<p class=text-danger>"+d.error+"</p>";return;}
    const c=d.campanha;
    document.getElementById("modalCampTitle").textContent=c.nome||"Campanha #"+c.id;
    let html="<div class=row><div class=col-md-6>";
    html+="<p><strong>Status:</strong> "+c.status+"</p>";
    html+="<p><strong>Tipo:</strong> "+c.tipo+"</p>";
    html+="<p><strong>Gatilho:</strong> "+(c.gatilho||"-")+"</p>";
    html+="<p><strong>Assunto:</strong> "+(c.assunto||"-")+"</p>";
    html+="<p><strong>Clientes:</strong> "+c.total_clientes+"</p>";
    html+="<p><strong>Observações IA:</strong><br><small>"+(c.observacoes_ia||"-")+"</small></p>";
    html+="</div><div class=col-md-6>";
    html+="<h6>Preview do Email</h6><div style=border:1px solid #eee;border-radius:8px;max-height:400px;overflow:auto;padding:8px;>"+(c.html_content||"<p>Sem conteúdo</p>")+"</div>";
    html+="</div></div>";
    if(d.clientes&&d.clientes.length){
        html+="<hr><h6>Clientes ("+d.clientes.length+")</h6><div style=max-height:200px;overflow:auto;><table class=camp-table><thead><tr><th>Nome</th><th>Email</th><th>Status</th></tr></thead><tbody>";
        d.clientes.forEach(cl=>{html+="<tr><td>"+(cl.nome||"-")+"</td><td>"+cl.email+"</td><td>"+cl.status+"</td></tr>";});
        html+="</tbody></table></div>";
    }
    document.getElementById("modalCampBody").innerHTML=html;
}
async function aprovarCampanha(){
    if(!currentCampId)return;
    const r=await fetch("/admin/email-marketing/aprovar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(d.success){bootstrap.Modal.getInstance(document.getElementById("modalCampanha")).hide();location.reload();}
    else alert(d.error||"Erro");
}
async function rejeitarCampanha(){
    if(!currentCampId||!confirm("Rejeitar esta campanha?"))return;
    const r=await fetch("/admin/email-marketing/rejeitar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(d.success){bootstrap.Modal.getInstance(document.getElementById("modalCampanha")).hide();location.reload();}
    else alert(d.error||"Erro");
}
</script>';
        echo '</div></main></div></div></body></html>';
    }

    private function renderCampanhasTable(array $campanhas): void {
        if (empty($campanhas)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><i class="bi bi-envelope" style="font-size:40px;color:#94A3B8;"></i><p style="color:#94A3B8;margin-top:12px;">Nenhuma campanha encontrada. Clique em "Gerar Campanhas com IA" para começar.</p></div></div>';
            return;
        }
        $statusMap = ['rascunho_ia'=>'st-rascunho','pendente_revisao'=>'st-pendente','aprovada'=>'st-aprovada','agendada'=>'st-agendada','disparando'=>'st-disparando','finalizada'=>'st-finalizada','rejeitada'=>'st-rejeitada','cancelada'=>'st-cancelada'];
        $statusLabels = ['rascunho_ia'=>'Rascunho IA','pendente_revisao'=>'Pendente','aprovada'=>'Aprovada','agendada'=>'Agendada','disparando'=>'Disparando','finalizada'=>'Finalizada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada'];

        echo '<div class="section-card"><div style="overflow-x:auto;"><table class="camp-table"><thead><tr><th>Campanha</th><th>Tipo</th><th>Status</th><th>Clientes</th><th>Enviados</th><th>Abertos</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        foreach ($campanhas as $c) {
            $st = $c['status'] ?? 'rascunho_ia';
            $badge = '<span class="status-pill '.($statusMap[$st]??'st-rascunho').'">'.($statusLabels[$st]??$st).'</span>';
            $data = !empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '-';
            echo '<tr><td><strong>'.htmlspecialchars($c['nome']??'').'</strong><br><small style="color:#94A3B8;">'.htmlspecialchars($c['assunto']??'').'</small></td>';
            echo '<td>'.ucfirst(str_replace('_',' ',$c['tipo']??'')).'</td><td>'.$badge.'</td>';
            echo '<td>'.(int)$c['total_clientes'].'</td><td>'.(int)$c['total_enviado'].'</td><td>'.(int)$c['total_aberto'].'</td>';
            echo '<td>'.$data.'</td><td><button class="btn-ghost" style="padding:4px 10px;font-size:12px;" onclick="verCampanha('.(int)$c['id'].')"><i class="bi bi-eye me-1"></i>Ver</button></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private function renderConfigTab(\PDO $pdo): void {
        $configs = [];
        try { $st = $pdo->query("SELECT chave, valor FROM email_mkt_config"); $configs = $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []; } catch (\Exception $e) {}

        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-gear-fill"></i> Configurações do Email Marketing</h2></div><div class="section-body">
<div class="row g-3">
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Tom da Marca</label><input type="text" class="form-control" id="cfg_tom_marca" value="'.htmlspecialchars($configs['tom_marca']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Palavras Proibidas</label><input type="text" class="form-control" id="cfg_palavras_proibidas" value="'.htmlspecialchars($configs['palavras_proibidas']??'').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Limite Diário</label><input type="number" class="form-control" id="cfg_limite_diario" value="'.htmlspecialchars($configs['limite_diario']??'200').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Dias Recompra Mínimo</label><input type="number" class="form-control" id="cfg_dias_recompra_minimo" value="'.htmlspecialchars($configs['dias_recompra_minimo']??'30').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Max Campanhas/Mês</label><input type="number" class="form-control" id="cfg_max_campanhas_mes_cliente" value="'.htmlspecialchars($configs['max_campanhas_mes_cliente']??'4').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Intervalo (dias)</label><input type="number" class="form-control" id="cfg_intervalo_campanhas_dias" value="'.htmlspecialchars($configs['intervalo_campanhas_dias']??'7').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Nome</label><input type="text" class="form-control" id="cfg_remetente_nome" value="'.htmlspecialchars($configs['remetente_nome']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Email</label><input type="email" class="form-control" id="cfg_remetente_email" value="'.htmlspecialchars($configs['remetente_email']??'').'"></div>
<div class="col-12"><button class="btn-navy" onclick="salvarConfig()"><i class="bi bi-check-lg me-1"></i>Salvar Configurações</button></div>
</div></div></div>
<script>
async function salvarConfig(){
    const data={};
    document.querySelectorAll("[id^=cfg_]").forEach(el=>{data[el.id.replace("cfg_","")]=el.value;});
    const r=await fetch("/admin/email-marketing/config",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
    const d=await r.json();alert(d.success?"Salvo!":"Erro");
}
</script>';
    }
}
