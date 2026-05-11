<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminDescricaoProdutosController extends Controller {

    private function ensureTable(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS produto_descricoes_ia (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            descricao_gerada TEXT,
            descricao_gerada_en TEXT,
            descricao_editada TEXT,
            descricao_editada_en TEXT,
            status_revisao ENUM('sem_descricao','gerando','pendente_revisao','aprovado','reprovado','erro') DEFAULT 'sem_descricao',
            erro_geracao TEXT,
            aprovado_por INT DEFAULT NULL,
            data_geracao DATETIME DEFAULT NULL,
            data_aprovacao DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_produto_id (produto_id),
            INDEX idx_status (status_revisao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Ensure EN columns exist (for upgrades)
        try { $pdo->exec("ALTER TABLE produto_descricoes_ia ADD COLUMN descricao_gerada_en TEXT AFTER descricao_gerada"); } catch (\Exception $e) {}
        try { $pdo->exec("ALTER TABLE produto_descricoes_ia ADD COLUMN descricao_editada_en TEXT AFTER descricao_editada"); } catch (\Exception $e) {}
    }

    private function getChatGPTApiKey(\PDO $pdo): ?string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $v = (string) ($st->fetchColumn() ?: '');
            return $v !== '' ? $v : null;
        } catch (\Exception $e) { return null; }
    }

    private function getChatGPTModel(\PDO $pdo): string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_model']);
            $v = trim((string) ($st->fetchColumn() ?: ''));
            return $v !== '' ? $v : 'gpt-4o-mini';
        } catch (\Exception $e) { return 'gpt-4o-mini'; }
    }

    private function getColumns(\PDO $pdo): array {
        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE produtos');
            $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) { $cols = []; }

        $nameCol = in_array('nome', $cols) ? 'nome' : (in_array('name', $cols) ? 'name' : 'nome');
        $descCol = in_array('descricao', $cols) ? 'descricao' : (in_array('description', $cols) ? 'description' : 'descricao');
        $hasCategoria = in_array('categoria_id', $cols);
        $hasAtivo = in_array('ativo', $cols) ? 'ativo' : (in_array('active', $cols) ? 'active' : null);
        $hasFoto = in_array('foto_principal', $cols) ? 'foto_principal' : (in_array('imagem', $cols) ? 'imagem' : (in_array('image', $cols) ? 'image' : null));

        return compact('cols', 'nameCol', 'descCol', 'hasCategoria', 'hasAtivo', 'hasFoto');
    }

    private function callChatGPT(\PDO $pdo, string $userMessage, string $lang = 'pt'): array {
        $apiKey = $this->getChatGPTApiKey($pdo);
        if (!$apiKey) {
            return ['error' => 'API Key do ChatGPT não configurada. Vá em Configurações > IA.'];
        }
        $model = $this->getChatGPTModel($pdo);

        if ($lang === 'en') {
            $systemPrompt = "Create a short, clear and commercial product description for the product below. Use only the information provided. Do not invent brand, measurements, composition, warranty, origin, material or technical specifications that are not present. The description should be suitable for e-commerce, with natural, objective and professional language. Return only the description text, without title, without bullet points and without explanations.";
        } else {
            $systemPrompt = "Crie uma descrição curta, clara e comercial para o produto abaixo. Use apenas as informações fornecidas. Não invente marca, medidas, composição, garantia, origem, material ou especificações técnicas que não estejam presentes. A descrição deve ser adequada para e-commerce, com linguagem natural, objetiva e profissional. Retorne apenas o texto da descrição, sem título, sem tópicos e sem explicações.";
        }

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'Erro de conexão: ' . $curlError];
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
            $errMsg = $data['error']['message'] ?? 'Erro desconhecido da API (HTTP ' . $httpCode . ')';
            return ['error' => $errMsg];
        }
        return ['text' => trim($data['choices'][0]['message']['content'])];
    }

    public function gerarDescricao(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);
        $colInfo = $this->getColumns($pdo);

        $produtoId = (int) $request->getParam('produto_id');
        if ($produtoId <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido']); return; }

        // Get product info
        $fotoCol = $colInfo['hasFoto'] ? ", p.{$colInfo['hasFoto']}" : '';
        $sql = "SELECT p.id, p.{$colInfo['nameCol']} AS nome" . ($colInfo['hasCategoria'] ? ", c.nome AS categoria" : ", NULL AS categoria") . " {$fotoCol} FROM produtos p";
        if ($colInfo['hasCategoria']) $sql .= " LEFT JOIN categorias c ON c.id = p.categoria_id";
        $sql .= " WHERE p.id = ?";
        $st = $pdo->prepare($sql); $st->execute([$produtoId]);
        $produto = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$produto) { echo json_encode(['success' => false, 'error' => 'Produto não encontrado']); return; }

        // Mark as generating
        $stCheck = $pdo->prepare("SELECT id FROM produto_descricoes_ia WHERE produto_id = ?");
        $stCheck->execute([$produtoId]);
        if ($stCheck->fetchColumn()) {
            $pdo->prepare("UPDATE produto_descricoes_ia SET status_revisao='gerando', erro_geracao=NULL WHERE produto_id=?")->execute([$produtoId]);
        } else {
            $pdo->prepare("INSERT INTO produto_descricoes_ia (produto_id, status_revisao) VALUES (?, 'gerando')")->execute([$produtoId]);
        }

        // Build user message
        $msg = "Nome: " . ($produto['nome'] ?? '');
        if (!empty($produto['categoria'])) $msg .= "\nCategoria: " . $produto['categoria'];

        $result = $this->callChatGPT($pdo, $msg);

        if (isset($result['error'])) {
            $pdo->prepare("UPDATE produto_descricoes_ia SET status_revisao='erro', erro_geracao=?, data_geracao=NOW() WHERE produto_id=?")->execute([$result['error'], $produtoId]);
            echo json_encode(['success' => false, 'error' => $result['error']]);
            return;
        }

        $pdo->prepare("UPDATE produto_descricoes_ia SET descricao_gerada=?, status_revisao='pendente_revisao', erro_geracao=NULL, data_geracao=NOW() WHERE produto_id=?")->execute([$result['text'], $produtoId]);

        // Generate English version
        $msgEn = "Product name: " . ($produto['nome'] ?? '');
        if (!empty($produto['categoria'])) $msgEn .= "\nCategory: " . $produto['categoria'];
        $resultEn = $this->callChatGPT($pdo, $msgEn, 'en');
        $descEn = $resultEn['text'] ?? '';
        if ($descEn !== '') {
            $pdo->prepare("UPDATE produto_descricoes_ia SET descricao_gerada_en=? WHERE produto_id=?")->execute([$descEn, $produtoId]);
        }

        echo json_encode(['success' => true, 'descricao' => $result['text'], 'descricao_en' => $descEn, 'produto_id' => $produtoId]);
    }

    public function gerarLote(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['success' => false, 'error' => 'Nenhum produto selecionado']); return; }
        // Frontend will call gerarDescricao sequentially; this endpoint just validates
        echo json_encode(['success' => true, 'total' => count($ids), 'ids' => array_map('intval', $ids)]);
    }

    public function revisar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);
        $colInfo = $this->getColumns($pdo);

        $produtoId = (int) $request->getParam('id');
        if ($produtoId <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido']); return; }

        $fotoCol = $colInfo['hasFoto'] ? ", p.{$colInfo['hasFoto']} AS foto" : ", NULL AS foto";
        $sql = "SELECT p.id, p.{$colInfo['nameCol']} AS nome, d.descricao_gerada, d.descricao_editada, d.status_revisao, d.erro_geracao {$fotoCol}
                FROM produtos p LEFT JOIN produto_descricoes_ia d ON d.produto_id = p.id WHERE p.id = ?";
        $st = $pdo->prepare($sql); $st->execute([$produtoId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Produto não encontrado']); return; }

        echo json_encode(['success' => true, 'produto' => $row]);
    }

    public function aprovar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);
        $colInfo = $this->getColumns($pdo);

        $data = json_decode(file_get_contents('php://input'), true);
        $produtoId = (int) ($data['produto_id'] ?? 0);
        $descricaoFinal = trim((string) ($data['descricao'] ?? ''));
        if ($produtoId <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido']); return; }

        // If no custom text provided, use the generated one
        if ($descricaoFinal === '') {
            $st = $pdo->prepare("SELECT descricao_gerada, descricao_editada FROM produto_descricoes_ia WHERE produto_id = ?");
            $st->execute([$produtoId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            $descricaoFinal = !empty($row['descricao_editada']) ? $row['descricao_editada'] : ($row['descricao_gerada'] ?? '');
        }
        if ($descricaoFinal === '') { echo json_encode(['success' => false, 'error' => 'Nenhuma descrição para aprovar']); return; }

        $uid = $_SESSION['usuario_id'] ?? 0;
        $pdo->prepare("UPDATE produto_descricoes_ia SET descricao_editada=?, status_revisao='aprovado', aprovado_por=?, data_aprovacao=NOW() WHERE produto_id=?")->execute([$descricaoFinal, $uid, $produtoId]);

        // Update product description
        $pdo->prepare("UPDATE produtos SET {$colInfo['descCol']} = ? WHERE id = ?")->execute([$descricaoFinal, $produtoId]);

        echo json_encode(['success' => true, 'produto_id' => $produtoId]);
    }

    public function reprovar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);

        $data = json_decode(file_get_contents('php://input'), true);
        $produtoId = (int) ($data['produto_id'] ?? 0);
        if ($produtoId <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido']); return; }

        $pdo->prepare("UPDATE produto_descricoes_ia SET status_revisao='reprovado' WHERE produto_id=?")->execute([$produtoId]);
        echo json_encode(['success' => true, 'produto_id' => $produtoId]);
    }

    public function aprovarLote(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);
        $colInfo = $this->getColumns($pdo);

        $data = json_decode(file_get_contents('php://input'), true);
        $ids = array_map('intval', $data['ids'] ?? []);
        if (empty($ids)) { echo json_encode(['success' => false, 'error' => 'Nenhum produto selecionado']); return; }

        $uid = $_SESSION['usuario_id'] ?? 0;
        $aprovados = 0;
        foreach ($ids as $pid) {
            $st = $pdo->prepare("SELECT descricao_gerada, descricao_editada FROM produto_descricoes_ia WHERE produto_id = ? AND status_revisao = 'pendente_revisao'");
            $st->execute([$pid]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) continue;
            $desc = !empty($row['descricao_editada']) ? $row['descricao_editada'] : $row['descricao_gerada'];
            if (empty($desc)) continue;
            $pdo->prepare("UPDATE produto_descricoes_ia SET descricao_editada=?, status_revisao='aprovado', aprovado_por=?, data_aprovacao=NOW() WHERE produto_id=?")->execute([$desc, $uid, $pid]);
            $pdo->prepare("UPDATE produtos SET {$colInfo['descCol']} = ? WHERE id = ?")->execute([$desc, $pid]);
            $aprovados++;
        }
        echo json_encode(['success' => true, 'aprovados' => $aprovados]);
    }

    public function reprovarLote(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);

        $data = json_decode(file_get_contents('php://input'), true);
        $ids = array_map('intval', $data['ids'] ?? []);
        if (empty($ids)) { echo json_encode(['success' => false, 'error' => 'Nenhum produto selecionado']); return; }

        $reprovados = 0;
        foreach ($ids as $pid) {
            $st = $pdo->prepare("UPDATE produto_descricoes_ia SET status_revisao='reprovado' WHERE produto_id = ? AND status_revisao IN ('pendente_revisao','erro')");
            $st->execute([$pid]);
            $reprovados += $st->rowCount();
        }
        echo json_encode(['success' => true, 'reprovados' => $reprovados]);
    }

    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);
        $colInfo = $this->getColumns($pdo);

        // Stats
        $totalSemDesc = 0; $totalPendentes = 0; $totalAprovados = 0; $totalErros = 0;
        try {
            $st = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ({$colInfo['descCol']} IS NULL OR {$colInfo['descCol']} = '')");
            $totalSemDesc = (int) $st->fetchColumn();
        } catch (\Exception $e) {}
        try { $totalPendentes = (int) $pdo->query("SELECT COUNT(*) FROM produto_descricoes_ia WHERE status_revisao='pendente_revisao'")->fetchColumn(); } catch (\Exception $e) {}
        try { $totalAprovados = (int) $pdo->query("SELECT COUNT(*) FROM produto_descricoes_ia WHERE status_revisao='aprovado'")->fetchColumn(); } catch (\Exception $e) {}
        try { $totalErros = (int) $pdo->query("SELECT COUNT(*) FROM produto_descricoes_ia WHERE status_revisao='erro'")->fetchColumn(); } catch (\Exception $e) {}

        // Filter
        $filtro = strtolower(trim((string) $request->getParam('filtro', 'sem_descricao')));
        $validFiltros = ['sem_descricao','pendente_revisao','aprovado','reprovado','erro'];
        if (!in_array($filtro, $validFiltros)) $filtro = 'sem_descricao';

        // Query products
        $fotoCol = $colInfo['hasFoto'] ? ", p.{$colInfo['hasFoto']} AS foto" : ", NULL AS foto";
        $catJoin = $colInfo['hasCategoria'] ? "LEFT JOIN categorias c ON c.id = p.categoria_id" : "";
        $catSelect = $colInfo['hasCategoria'] ? ", c.nome AS categoria" : ", NULL AS categoria";

        if ($filtro === 'sem_descricao') {
            $sql = "SELECT p.id, p.{$colInfo['nameCol']} AS nome {$catSelect} {$fotoCol}, COALESCE(d.status_revisao, 'sem_descricao') AS status_revisao
                    FROM produtos p {$catJoin} LEFT JOIN produto_descricoes_ia d ON d.produto_id = p.id
                    WHERE ({$colInfo['descCol']} IS NULL OR {$colInfo['descCol']} = '')
                    ORDER BY p.id DESC LIMIT 200";
        } else {
            $sql = "SELECT p.id, p.{$colInfo['nameCol']} AS nome {$catSelect} {$fotoCol}, d.status_revisao
                    FROM produtos p {$catJoin} INNER JOIN produto_descricoes_ia d ON d.produto_id = p.id
                    WHERE d.status_revisao = '{$filtro}'
                    ORDER BY d.updated_at DESC LIMIT 200";
        }
        $produtos = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Render page
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        $this->renderPage($produtos, $filtro, $totalSemDesc, $totalPendentes, $totalAprovados, $totalErros);
    }

    private function renderPage(array $produtos, string $filtro, int $totalSemDesc, int $totalPendentes, int $totalAprovados, int $totalErros): void {
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Descrição de Produtos - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/dashboard-redesign.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '<style>
:root { --navy: #18253D; }
.kpi-card { background:#fff; border-radius:12px; padding:20px; border:1px solid #e9ecef; }
.kpi-card .kpi-value { font-size:1.8rem; font-weight:700; color:var(--navy); }
.kpi-card .kpi-label { font-size:0.85rem; color:#6c757d; }
.status-badge { font-size:0.75rem; padding:4px 10px; border-radius:20px; font-weight:500; }
.status-sem_descricao { background:#f8d7da; color:#842029; }
.status-gerando { background:#fff3cd; color:#664d03; }
.status-pendente_revisao { background:#cff4fc; color:#055160; }
.status-aprovado { background:#d1e7dd; color:#0f5132; }
.status-reprovado { background:#f8d7da; color:#842029; }
.status-erro { background:#f8d7da; color:#842029; }
.filter-tabs .nav-link { color:#6c757d; border:1px solid #dee2e6; border-radius:8px; margin-right:6px; padding:6px 14px; font-size:0.85rem; }
.filter-tabs .nav-link.active { background:var(--navy); color:#fff; border-color:var(--navy); }
.product-img { width:40px; height:40px; object-fit:cover; border-radius:6px; border:1px solid #eee; }
.progress-bar-gen { height:6px; border-radius:3px; background:#e9ecef; overflow:hidden; margin-top:8px; }
.progress-bar-gen .fill { height:100%; background:var(--navy); transition:width 0.3s; }
</style></head>
<body style="background:#f5f6fa;">
<div class="container-fluid"><div class="row">';

        renderAdminSidebar('descricao-produtos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">';
        // Title
        echo '<div class="d-flex align-items-center justify-content-between mb-4">
<div><h1 class="h3 mb-0" style="color:var(--navy);font-weight:700;"><i class="fas fa-pen-fancy me-2"></i>Descrição de Produtos</h1>
<small class="text-muted">Geração e revisão de descrições com IA</small></div></div>';

        // KPI Cards
        echo '<div class="row g-3 mb-4">
<div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-value">' . $totalSemDesc . '</div><div class="kpi-label"><i class="fas fa-exclamation-circle text-danger me-1"></i>Sem Descrição</div></div></div>
<div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-value">' . $totalPendentes . '</div><div class="kpi-label"><i class="fas fa-clock text-info me-1"></i>Pendentes Revisão</div></div></div>
<div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-value">' . $totalAprovados . '</div><div class="kpi-label"><i class="fas fa-check-circle text-success me-1"></i>Aprovados</div></div></div>
<div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-value">' . $totalErros . '</div><div class="kpi-label"><i class="fas fa-times-circle text-danger me-1"></i>Erros</div></div></div>
</div>';

        // Filter tabs
        $tabs = [
            'sem_descricao' => 'Todos sem descrição',
            'pendente_revisao' => 'Pendentes',
            'aprovado' => 'Aprovados',
            'reprovado' => 'Reprovados',
            'erro' => 'Com erro'
        ];
        echo '<nav class="filter-tabs mb-3"><ul class="nav">';
        foreach ($tabs as $key => $label) {
            $active = ($filtro === $key) ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="?filtro=' . $key . '">' . $label . '</a></li>';
        }
        echo '</ul></nav>';

        // Action bar
        echo '<div class="d-flex gap-2 mb-3 flex-wrap" id="actionBar">
<button class="btn btn-sm btn-primary" onclick="gerarSelecionados()" id="btnGerarSel" disabled><i class="fas fa-magic me-1"></i>Gerar selecionados</button>
<button class="btn btn-sm btn-success" onclick="aprovarSelecionados()" id="btnAprovarSel" disabled><i class="fas fa-check me-1"></i>Aprovar selecionados</button>
<button class="btn btn-sm btn-danger" onclick="reprovarSelecionados()" id="btnReprovarSel" disabled><i class="fas fa-times me-1"></i>Reprovar selecionados</button>
<div id="progressArea" class="ms-3 align-self-center" style="display:none;min-width:200px;">
<small class="text-muted" id="progressText">0/0</small>
<div class="progress-bar-gen"><div class="fill" id="progressFill" style="width:0%"></div></div>
</div></div>';

        // Table
        echo '<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead style="background:#f8f9fa;"><tr>
<th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
<th style="width:50px;">Img</th>
<th>Produto</th>
<th>Categoria</th>
<th>Status</th>
<th style="width:140px;">Ações</th>
</tr></thead><tbody>';

        if (empty($produtos)) {
            echo '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum produto encontrado neste filtro.</td></tr>';
        }
        foreach ($produtos as $p) {
            $id = (int) $p['id'];
            $nome = htmlspecialchars((string) ($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $cat = htmlspecialchars((string) ($p['categoria'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $foto = !empty($p['foto']) ? htmlspecialchars($p['foto'], ENT_QUOTES, 'UTF-8') : '';
            $fotoTag = $foto ? '<img src="' . $foto . '" class="product-img" alt="">' : '<div class="product-img d-flex align-items-center justify-content-center bg-light"><i class="fas fa-image text-muted"></i></div>';
            $status = $p['status_revisao'] ?? 'sem_descricao';
            $statusLabels = ['sem_descricao'=>'Sem descrição','gerando'=>'Gerando...','pendente_revisao'=>'Pendente','aprovado'=>'Aprovado','reprovado'=>'Reprovado','erro'=>'Erro'];
            $badge = '<span class="status-badge status-' . $status . '">' . ($statusLabels[$status] ?? $status) . '</span>';

            $actions = '';
            if (in_array($status, ['sem_descricao','erro','reprovado'])) {
                $actions .= '<button class="btn btn-sm btn-outline-primary me-1" onclick="gerarUm(' . $id . ')" title="Gerar"><i class="fas fa-magic"></i></button>';
            }
            if (in_array($status, ['pendente_revisao','aprovado','reprovado','erro'])) {
                $actions .= '<button class="btn btn-sm btn-outline-secondary" onclick="revisarProduto(' . $id . ')" title="Revisar"><i class="fas fa-eye"></i></button>';
            }

            echo '<tr id="row-' . $id . '"><td><input type="checkbox" class="row-check" value="' . $id . '" onchange="updateActionBar()"></td>';
            echo '<td>' . $fotoTag . '</td><td><strong>' . $nome . '</strong></td><td>' . $cat . '</td><td>' . $badge . '</td><td>' . $actions . '</td></tr>';
        }
        echo '</tbody></table></div></div>';

        // Modal
        echo '<div class="modal fade" id="modalRevisar" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header" style="background:var(--navy);color:#fff;"><h5 class="modal-title"><i class="fas fa-pen-fancy me-2"></i>Revisar Descrição</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="d-flex align-items-center gap-3 mb-3">
<img id="modalFoto" src="" class="product-img" style="width:60px;height:60px;" alt="">
<div><h6 id="modalNome" class="mb-0"></h6><small class="text-muted" id="modalStatus"></small></div></div>
<label class="form-label fw-bold">Descrição gerada pela IA:</label>
<textarea class="form-control" id="modalDescricao" rows="6"></textarea>
<div id="modalErro" class="alert alert-danger mt-2" style="display:none;"></div>
</div>
<div class="modal-footer">
<button class="btn btn-success" onclick="aprovarModal()"><i class="fas fa-check me-1"></i>Aprovar</button>
<button class="btn btn-danger" onclick="reprovarModal()"><i class="fas fa-times me-1"></i>Reprovar</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
</div></div></div></div>';

        // JavaScript
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentProdutoId = null;
const modalEl = document.getElementById("modalRevisar");
const modal = new bootstrap.Modal(modalEl);

function toggleAll(el) {
    document.querySelectorAll(".row-check").forEach(c => c.checked = el.checked);
    updateActionBar();
}
function updateActionBar() {
    const checked = document.querySelectorAll(".row-check:checked").length;
    document.getElementById("btnGerarSel").disabled = checked === 0;
    document.getElementById("btnAprovarSel").disabled = checked === 0;
    document.getElementById("btnReprovarSel").disabled = checked === 0;
}
function getSelectedIds() {
    return [...document.querySelectorAll(".row-check:checked")].map(c => parseInt(c.value));
}

async function gerarUm(id) {
    const row = document.getElementById("row-"+id);
    if(row) row.querySelector("td:nth-child(5)").innerHTML = \'<span class="status-badge status-gerando">Gerando...</span>\';
    const fd = new FormData(); fd.append("produto_id", id);
    const r = await fetch("/admin/descricao-produtos/gerar", {method:"POST", body:fd});
    const data = await r.json();
    if(data.success) {
        if(row) row.querySelector("td:nth-child(5)").innerHTML = \'<span class="status-badge status-pendente_revisao">Pendente</span>\';
    } else {
        if(row) row.querySelector("td:nth-child(5)").innerHTML = \'<span class="status-badge status-erro">Erro</span>\';
        alert(data.error || "Erro ao gerar");
    }
}

async function gerarSelecionados() {
    const ids = getSelectedIds();
    if(!ids.length) return;
    const area = document.getElementById("progressArea");
    const text = document.getElementById("progressText");
    const fill = document.getElementById("progressFill");
    area.style.display = "block";
    let done = 0;
    for(const id of ids) {
        text.textContent = done + "/" + ids.length;
        fill.style.width = ((done/ids.length)*100)+"%";
        await gerarUm(id);
        done++;
    }
    text.textContent = done + "/" + ids.length + " ✓";
    fill.style.width = "100%";
    setTimeout(()=>{ area.style.display="none"; }, 2000);
}

async function aprovarSelecionados() {
    const ids = getSelectedIds();
    if(!ids.length) return;
    if(!confirm("Aprovar "+ids.length+" descrições?")) return;
    const r = await fetch("/admin/descricao-produtos/aprovar-lote", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({ids})});
    const data = await r.json();
    if(data.success) location.reload();
    else alert(data.error || "Erro");
}

async function reprovarSelecionados() {
    const ids = getSelectedIds();
    if(!ids.length) return;
    if(!confirm("Reprovar "+ids.length+" descrições?")) return;
    const r = await fetch("/admin/descricao-produtos/reprovar-lote", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({ids})});
    const data = await r.json();
    if(data.success) location.reload();
    else alert(data.error || "Erro");
}

async function revisarProduto(id) {
    currentProdutoId = id;
    const r = await fetch("/admin/descricao-produtos/revisar/"+id);
    const data = await r.json();
    if(!data.success) { alert(data.error); return; }
    const p = data.produto;
    document.getElementById("modalNome").textContent = p.nome || "";
    document.getElementById("modalFoto").src = p.foto || "";
    document.getElementById("modalFoto").style.display = p.foto ? "" : "none";
    document.getElementById("modalDescricao").value = p.descricao_editada || p.descricao_gerada || "";
    document.getElementById("modalStatus").textContent = p.status_revisao || "";
    const erroEl = document.getElementById("modalErro");
    if(p.erro_geracao) { erroEl.textContent = p.erro_geracao; erroEl.style.display=""; } else { erroEl.style.display="none"; }
    modal.show();
}

async function aprovarModal() {
    const desc = document.getElementById("modalDescricao").value.trim();
    if(!desc) { alert("Descrição vazia"); return; }
    const r = await fetch("/admin/descricao-produtos/aprovar", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({produto_id:currentProdutoId, descricao:desc})});
    const data = await r.json();
    if(data.success) { modal.hide(); location.reload(); }
    else alert(data.error || "Erro");
}

async function reprovarModal() {
    const r = await fetch("/admin/descricao-produtos/reprovar", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({produto_id:currentProdutoId})});
    const data = await r.json();
    if(data.success) { modal.hide(); location.reload(); }
    else alert(data.error || "Erro");
}
</script>';

        echo '</main></div></div></body></html>';
    }
}
