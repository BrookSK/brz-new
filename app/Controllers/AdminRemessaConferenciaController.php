<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminRemessaConferenciaController extends Controller {

    private $connection;

    public function __construct() {
        $this->connection = \Config\Database::getConnection();
    }

    private function requireAccess(): void {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'conferente']);
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->connection->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) { return false; }
    }

    private function getUsdToBrlRate(): float {
        try {
            foreach (['configuracoes_sistema','configuracoes','settings','config'] as $t) {
                try {
                    $st = $this->connection->prepare("SHOW TABLES LIKE ?");
                    $st->execute([$t]);
                    if (!$st->fetchColumn()) continue;
                    $stCols = $this->connection->query("DESCRIBE {$t}");
                    $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                    if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                        $vc = in_array('valor', $cols, true) ? 'valor' : 'value';
                        $st2 = $this->connection->prepare("SELECT {$vc} FROM {$t} WHERE categoria='sistema' AND chave='usd_brl_rate' LIMIT 1");
                        $st2->execute();
                        $v = $st2->fetchColumn();
                        if ($v !== false && is_numeric($v) && (float)$v > 0) return (float)$v;
                    }
                } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {}
        return 5.80;
    }

    private function getPedidoCompleto(int $pedidoId): ?array {
        // Detectar colunas disponíveis
        $colsPedidos = [];
        try {
            $st = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {}

        $colsUsuarios = [];
        try {
            $st = $this->connection->query('DESCRIBE usuarios');
            $colsUsuarios = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {}

        $nomeCol = in_array('nome', $colsUsuarios, true) ? 'u.nome' : (in_array('name', $colsUsuarios, true) ? 'u.name' : 'NULL');
        $emailCol = in_array('email', $colsUsuarios, true) ? 'u.email' : 'NULL';
        $telCol = in_array('telefone', $colsUsuarios, true) ? 'u.telefone' : (in_array('phone', $colsUsuarios, true) ? 'u.phone' : 'NULL');
        $cpfCol = in_array('cpf', $colsUsuarios, true) ? 'u.cpf' : (in_array('documento', $colsUsuarios, true) ? 'u.documento' : 'NULL');
        $suiteCol = in_array('suite', $colsUsuarios, true) ? 'u.suite' : 'NULL';

        $stmt = $this->connection->prepare("
            SELECT p.*,
                {$nomeCol} AS cliente_nome,
                {$emailCol} AS cliente_email,
                {$telCol} AS cliente_telefone,
                {$cpfCol} AS cliente_cpf,
                {$suiteCol} AS cliente_suite
            FROM pedidos p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) return null;

        // Itens com foto
        try {
            $colsProd = [];
            try { $st = $this->connection->query('DESCRIBE produtos'); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $ncmCol = 'NULL AS ncm';
            foreach (['ncm','tariff_code','ncm_code','codigo_ncm'] as $c) {
                if (in_array($c, $colsProd, true)) { $ncmCol = "pr.{$c} AS ncm"; break; }
            }
            $fotoCol = in_array('foto_principal', $colsProd, true) ? 'pr.foto_principal' : 'NULL';
            $pesoCol = 'NULL AS peso_produto';
            foreach (['peso','weight','peso_kg'] as $c) {
                if (in_array($c, $colsProd, true)) { $pesoCol = "pr.{$c} AS peso_produto"; break; }
            }
            $stI = $this->connection->prepare("
                SELECT pi.*, pr.name AS produto_nome, pr.sku, {$ncmCol}, {$fotoCol} AS foto_produto, {$pesoCol}
                FROM pedido_itens pi
                LEFT JOIN produtos pr ON pi.produto_id = pr.id
                WHERE pi.pedido_id = ?
            ");
            $stI->execute([$pedidoId]);
            $pedido['itens'] = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { $pedido['itens'] = []; }

        // Endereço
        try {
            $stE = $this->connection->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1");
            $stE->execute([$pedido['usuario_id']]);
            $pedido['endereco'] = $stE->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) { $pedido['endereco'] = null; }

        // Pagamentos split
        try {
            $stP = $this->connection->prepare("SELECT * FROM pedido_pagamentos WHERE pedido_id = ? ORDER BY id ASC");
            $stP->execute([$pedidoId]);
            $pedido['pagamentos'] = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { $pedido['pagamentos'] = []; }

        return $pedido;
    }

    private function getMedicamentoFlag(int $janelaId, int $pedidoId): bool {
        try {
            $st = $this->connection->prepare("SELECT medicamento FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1");
            $st->execute([$janelaId, $pedidoId]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) { return false; }
    }

    private function getDocumentos(int $janelaId, int $pedidoId): array {
        try {
            if (!$this->tableExists('remessa_janela_documentos')) return [];
            $st = $this->connection->prepare("SELECT * FROM remessa_janela_documentos WHERE janela_id = ? AND pedido_id = ? ORDER BY id ASC");
            $st->execute([$janelaId, $pedidoId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $byTipo = [];
            foreach ($rows as $r) { $byTipo[(string)($r['tipo'] ?? 'outro')] = $r; }
            return $byTipo;
        } catch (\Exception $e) { return []; }
    }

    private function ensureDocumentosTable(): void {
        try {
            $this->connection->exec("CREATE TABLE IF NOT EXISTS remessa_janela_documentos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                janela_id INT NOT NULL,
                pedido_id INT NOT NULL,
                tipo VARCHAR(50) NOT NULL DEFAULT 'medicamento',
                original_name VARCHAR(255) NULL,
                file_path VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rjd (janela_id, pedido_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {}
        try {
            $this->connection->exec("ALTER TABLE remessa_janela_pedidos ADD COLUMN medicamento TINYINT(1) NOT NULL DEFAULT 0");
        } catch (\Exception $e) {}
    }

    public function index($request) {
        $this->requireAccess();
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $janelasAbertas = $janelasFinalizadas = $janelasGeradas = [];
        $errorMsg = null;
        try {
            if (!$this->tableExists('remessa_janelas')) throw new \Exception('Tabela remessa_janelas não encontrada.');
            $stA = $this->connection->query("SELECT * FROM remessa_janelas WHERE status = 'aberta' ORDER BY data_inicio DESC");
            $janelasAbertas = $stA ? $stA->fetchAll(\PDO::FETCH_ASSOC) : [];
            $stF = $this->connection->query("SELECT * FROM remessa_janelas WHERE status IN ('finalizada','atraso') ORDER BY data_inicio DESC LIMIT 20");
            $janelasFinalizadas = $stF ? $stF->fetchAll(\PDO::FETCH_ASSOC) : [];
            $stG = $this->connection->query("SELECT * FROM remessa_janelas WHERE status = 'remessa_gerada' ORDER BY data_inicio DESC LIMIT 20");
            $janelasGeradas = $stG ? $stG->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) { $errorMsg = $e->getMessage(); }

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Conferência de Remessa</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-conferencia');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>Conferência de Remessa</h1>
    <button class="btn btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Atualizar</button>
</div>';
        if ($errorMsg) echo '<div class="alert alert-danger"><strong>Erro:</strong> ' . htmlspecialchars($errorMsg) . '</div>';

        $renderJanelas = function(array $janelas, string $titulo, string $badgeClass) {
            echo '<div class="card mb-4"><div class="card-header"><strong>' . htmlspecialchars($titulo) . '</strong></div><div class="card-body">';
            if (!$janelas) { echo '<div class="text-muted">Nenhuma janela.</div>'; }
            else {
                echo '<div class="list-group">';
                foreach ($janelas as $j) {
                    $di = date('d/m/Y', strtotime((string)$j['data_inicio']));
                    $df = date('d/m/Y', strtotime((string)$j['data_fim']));
                    echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-conferencia/janela/' . (int)$j['id'] . '">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>Janela #' . (int)$j['id'] . '</strong> <span class="text-muted small">(' . $di . ' a ' . $df . ')</span></div>
                            <span class="badge bg-' . $badgeClass . '">' . htmlspecialchars((string)($j['status'] ?? '')) . '</span>
                        </div></a>';
                }
                echo '</div>';
            }
            echo '</div></div>';
        };
        $renderJanelas($janelasAbertas, 'Janelas Abertas', 'success');
        $renderJanelas($janelasFinalizadas, 'Janelas Finalizadas / Em Atraso', 'secondary');
        $renderJanelas($janelasGeradas, 'Remessas Geradas', 'info');
        echo '</main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
        exit;
    }

    public function verJanela($request, $id) {
        $this->requireAccess();
        $janelaId = (int) $id;
        if ($janelaId <= 0) { header('Location: /admin/remessa-conferencia'); exit; }
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $stJ = $this->connection->prepare('SELECT * FROM remessa_janelas WHERE id = ? LIMIT 1');
        $stJ->execute([$janelaId]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC);
        if (!$janela) { header('Location: /admin/remessa-conferencia'); exit; }

        $colsPedidos = [];
        try { $st = $this->connection->query('DESCRIBE pedidos'); $colsPedidos = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $hasMoeda = in_array('moeda', $colsPedidos, true);
        $hasCurrency = in_array('currency', $colsPedidos, true);
        $hasCep = in_array('cep', $colsPedidos, true);

        // Detectar coluna CEP do endereço
        $colsEnd = [];
        try { $st = $this->connection->query('DESCRIBE enderecos'); $colsEnd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $cepEndCol = in_array('cep', $colsEnd, true) ? 'cep' : (in_array('zip_code', $colsEnd, true) ? 'zip_code' : null);

        $sql = "SELECT rjp.pedido_id, rjp.etiqueta_gerada, rjp.wexpress_shipping_id,
                    rjp.wexpress_tracking_number, rjp.courier_tracking_number, rjp.wexpress_status,
                    p.created_at, p.total, p.status,
                    " . ($hasMoeda ? 'p.moeda,' : "'' AS moeda,") . "
                    " . ($hasCurrency ? 'p.currency,' : "'' AS currency,") . "
                    u.nome AS cliente_nome, u.email AS cliente_email";

        // Tentar pegar CEP do endereço
        if ($cepEndCol) {
            $sql .= ", (SELECT e.{$cepEndCol} FROM enderecos e WHERE e.usuario_id = p.usuario_id ORDER BY e.id DESC LIMIT 1) AS cep_entrega";
        } else {
            $sql .= ", NULL AS cep_entrega";
        }

        // Tentar pegar quantidade total de itens
        if ($this->tableExists('pedido_itens')) {
            $sql .= ", (SELECT SUM(pi.quantidade) FROM pedido_itens pi WHERE pi.pedido_id = p.id) AS qtd_itens";
        } else {
            $sql .= ", NULL AS qtd_itens";
        }

        $sql .= " FROM remessa_janela_pedidos rjp
                LEFT JOIN pedidos p ON p.id = rjp.pedido_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                WHERE rjp.janela_id = ?
                ORDER BY (p.created_at IS NULL) ASC, p.created_at DESC";

        $st = $this->connection->prepare($sql);
        $st->execute([$janelaId]);
        $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $total = count($pedidos);
        $geradas = count(array_filter($pedidos, fn($p) => (int)($p['etiqueta_gerada'] ?? 0) === 1));
        $pendentes = $total - $geradas;
        $usdToBrl = $this->getUsdToBrlRate();
        $brlToUsd = $usdToBrl > 0 ? 1.0 / $usdToBrl : 1.0;
        $badge = match($janela['status'] ?? '') { 'aberta' => 'success', 'atraso' => 'danger', 'remessa_gerada' => 'info', default => 'secondary' };

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Janela #' . $janelaId . ' - Conferência</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-conferencia');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h3 mb-0">Janela #' . $janelaId . ' <span class="badge bg-' . $badge . '">' . htmlspecialchars((string)($janela['status'] ?? '')) . '</span></h1>
        <div class="text-muted small">' . date('d/m/Y', strtotime((string)$janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime((string)$janela['data_fim'])) . '</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/admin/remessa-conferencia"><i class="fas fa-arrow-left"></i> Voltar</a>
        <button class="btn btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync"></i> Atualizar</button>
    </div>
</div>
<div class="row mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Total pedidos</div><div class="h4 mb-0">' . $total . '</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Etiquetas geradas</div><div class="h4 mb-0">' . $geradas . '</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Pendentes</div><div class="h4 mb-0">' . $pendentes . '</div></div></div></div>
</div>
<div class="card"><div class="card-header"><strong>Pedidos desta janela</strong></div><div class="card-body">
<div class="table-responsive"><table class="table table-hover align-middle table-sm">
<thead><tr><th>Pedido</th><th>Data/Hora</th><th>Cliente</th><th>ZIP/CEP</th><th>Qtd</th><th>Etiqueta</th><th>Ações</th></tr></thead><tbody>';

        if (!$pedidos) {
            echo '<tr><td colspan="7" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            foreach ($pedidos as $p) {
                $pid = (int)($p['pedido_id'] ?? 0);
                $et = (int)($p['etiqueta_gerada'] ?? 0);
                $dt = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string)$p['created_at'])) : '-';
                $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                $labelUrl = $wxShipId !== '' ? 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId) : '';
                $cep = (string)($p['cep_entrega'] ?? '');
                $qtd = $p['qtd_itens'] !== null ? (int)$p['qtd_itens'] : '-';

                echo '<tr>
                    <td><strong>#' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</strong></td>
                    <td>' . $dt . '</td>
                    <td>' . htmlspecialchars((string)($p['cliente_nome'] ?? 'N/A')) . '</td>
                    <td>' . htmlspecialchars($cep !== '' ? $cep : '-') . '</td>
                    <td>' . $qtd . '</td>
                    <td>' . ($et === 1 ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>');
                if ($wxCourier !== '') echo '<br><small class="text-muted">' . htmlspecialchars($wxCourier) . '</small>';
                elseif ($wxTrack !== '') echo '<br><small class="text-muted">' . htmlspecialchars($wxTrack) . '</small>';
                echo '</td><td class="text-nowrap">'
                    . ($labelUrl !== '' ? '<a class="btn btn-sm btn-outline-primary me-1" target="_blank" href="' . htmlspecialchars($labelUrl) . '"><i class="fas fa-tag"></i> Etiqueta</a>' : '')
                    . '<a class="btn btn-sm btn-outline-secondary" href="/admin/remessa-conferencia/janela/' . $janelaId . '/pedido/' . $pid . '"><i class="fas fa-eye"></i> Detalhes</a>'
                    . '</td></tr>';
            }
        }
        echo '</tbody></table></div></div></div></main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
        exit;
    }

    public function salvarMedicamento($request, $janelaId, $pedidoId) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;
        $this->ensureDocumentosTable();
        $flag = (string)($request->getParam('medicamento') ?? '0') === '1' ? 1 : 0;
        try {
            $st = $this->connection->prepare("UPDATE remessa_janela_pedidos SET medicamento = ? WHERE janela_id = ? AND pedido_id = ?");
            $st->execute([$flag, $jid, $pid]);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    public function uploadDocumento($request, $janelaId, $pedidoId, $tipo) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;
        $tipo = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$tipo));
        $this->ensureDocumentosTable();
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Arquivo inválido']); exit;
        }
        $orig = (string)$_FILES['arquivo']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed, true)) { echo json_encode(['success' => false, 'error' => 'Tipo não permitido']); exit; }
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $dir = $docRoot . '/uploads/remessa-docs/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $fname = 'doc_' . $jid . '_' . $pid . '_' . $tipo . '_' . time() . '.' . $ext;
        if (!@move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . $fname)) {
            echo json_encode(['success' => false, 'error' => 'Falha ao salvar arquivo']); exit;
        }
        $path = '/uploads/remessa-docs/' . $fname;
        try {
            $st = $this->connection->prepare("DELETE FROM remessa_janela_documentos WHERE janela_id = ? AND pedido_id = ? AND tipo = ?");
            $st->execute([$jid, $pid, $tipo]);
            $st2 = $this->connection->prepare("INSERT INTO remessa_janela_documentos (janela_id, pedido_id, tipo, original_name, file_path, created_at) VALUES (?,?,?,?,?,NOW())");
            $st2->execute([$jid, $pid, $tipo, $orig, $path]);
            echo json_encode(['success' => true, 'path' => $path]);
        } catch (\Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    public function detalhesPedido($request, $janelaId, $pedidoId) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;
        if ($jid <= 0 || $pid <= 0) { header('Location: /admin/remessa-conferencia'); exit; }
        $this->ensureDocumentosTable();
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $stChk = $this->connection->prepare('SELECT * FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
        $stChk->execute([$jid, $pid]);
        $rel = $stChk->fetch(\PDO::FETCH_ASSOC) ?: [];

        $pedido = $this->getPedidoCompleto($pid);
        if (!$pedido) { header('Location: /admin/remessa-conferencia/janela/' . $jid); exit; }

        $usdToBrl = $this->getUsdToBrlRate();
        $brlToUsd = $usdToBrl > 0 ? 1.0 / $usdToBrl : 1.0;
        $moeda = strtoupper(trim((string)($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moeda === '') $moeda = 'USD';

        $et = (int)($rel['etiqueta_gerada'] ?? 0);
        $wxShipId = (string)($rel['wexpress_shipping_id'] ?? '');
        $wxStatus = (string)($rel['wexpress_status'] ?? '');
        $wxTrack = (string)($rel['wexpress_tracking_number'] ?? '');
        $wxCourier = (string)($rel['courier_tracking_number'] ?? '');
        $labelUrl = $wxShipId !== '' ? 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId) : '';
        $medicamentoFlag = (bool)($rel['medicamento'] ?? false);
        $docs = $this->getDocumentos($jid, $pid);

        $h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
        $fmtUsd = fn($v) => $v !== null ? 'US$ ' . number_format((float)$v, 2, ',', '.') : '-';
        $fmtBrl = fn($v) => $v !== null ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '-';

        // Endereço
        $end = $pedido['endereco'] ?? null;
        $logr = is_array($end) ? trim((string)($end['endereco'] ?? ($end['logradouro'] ?? ''))) : '';
        $num = is_array($end) ? trim((string)($end['numero'] ?? '')) : '';
        $compl = is_array($end) ? trim((string)($end['complemento'] ?? '')) : '';
        $bairro = is_array($end) ? trim((string)($end['bairro'] ?? '')) : '';
        $cidade = is_array($end) ? trim((string)($end['cidade'] ?? '')) : '';
        $estado = is_array($end) ? trim((string)($end['estado'] ?? '')) : '';
        $cep = is_array($end) ? trim((string)($end['cep'] ?? ($end['zip_code'] ?? ''))) : '';
        $pais = is_array($end) ? trim((string)($end['pais'] ?? ($end['country'] ?? 'BR'))) : 'BR';

        // Pagamentos split
        $pagamentos = $pedido['pagamentos'] ?? [];
        $totalBrl = 0.0; $totalUsdPago = 0.0;
        $produtosValor = 0.0; $taxaServico = 0.0; $impostoValor = 0.0;
        $impostoLocal = 0.0; $appmaxValor = 0.0; $cambioRealValor = 0.0;
        $metodoPagamento = (string)($pedido['forma_pagamento'] ?? ($pedido['pagamento_metodo'] ?? ''));
        $dataPagamento = (string)($pedido['pago_em'] ?? ($pedido['pagamento_data'] ?? ($pedido['paid_at'] ?? '')));
        foreach ($pagamentos as $pg) {
            $v = (float)($pg['valor'] ?? 0);
            $m = strtolower((string)($pg['moeda'] ?? 'BRL'));
            if ($m === 'brl') $totalBrl += $v;
            else $totalUsdPago += $v;
            $gw = strtolower((string)($pg['gateway'] ?? ''));
            $comp = strtolower((string)($pg['componente'] ?? ''));
            if ($gw === 'appmax') $appmaxValor += $v;
            if ($gw === 'cambioreal') $cambioRealValor += $v;
            if (strpos($comp, 'produto') !== false || strpos($comp, 'product') !== false) $produtosValor += $v;
            if (strpos($comp, 'taxa') !== false || strpos($comp, 'servico') !== false || strpos($comp, 'service') !== false) $taxaServico += $v;
            if (strpos($comp, 'imposto') !== false || strpos($comp, 'tax') !== false) $impostoValor += $v;
            if (strpos($comp, 'local') !== false) $impostoLocal += $v;
            if ($metodoPagamento === '' && isset($pg['metodo'])) $metodoPagamento = (string)$pg['metodo'];
        }
        $totalPedidoUsd = is_numeric($pedido['total'] ?? null) ? (float)$pedido['total'] : null;
        if ($totalPedidoUsd !== null && $moeda === 'BRL') $totalPedidoUsd *= $brlToUsd;
        $subtotal = is_numeric($pedido['subtotal'] ?? null) ? (float)$pedido['subtotal'] : null;
        if ($subtotal !== null && $moeda === 'BRL') $subtotal *= $brlToUsd;
        $frete = is_numeric($pedido['frete'] ?? null) ? (float)$pedido['frete'] : null;
        if ($frete !== null && $moeda === 'BRL') $frete *= $brlToUsd;

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedido #' . $pid . ' - Conferência</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-conferencia');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h3 mb-0">Pedido #' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</h1>
        <div class="text-muted small">Janela #' . $jid . ' | Etiqueta: <span class="badge bg-' . ($et === 1 ? 'success' : 'warning') . '">' . ($et === 1 ? 'Gerada' : 'Pendente') . '</span></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="/admin/remessa-conferencia/janela/' . $jid . '"><i class="fas fa-arrow-left"></i> Voltar</a>
        ' . ($labelUrl !== '' ? '<a class="btn btn-outline-primary" href="' . $h($labelUrl) . '" target="_blank"><i class="fas fa-download"></i> Baixar etiqueta</a>' : '') . '
    </div>
</div>';

        // Status / Tracking
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-info-circle me-1"></i>Status e Rastreio</strong></div><div class="card-body">
<div class="row g-2">
    <div class="col-md-3"><div class="text-muted small">Status do Pedido</div><div>' . $h($pedido['status'] ?? '') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Data do Pedido</div><div>' . (!empty($pedido['created_at']) ? date('d/m/Y H:i', strtotime((string)$pedido['created_at'])) : '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Status W-Express</div><div>' . $h($wxStatus !== '' ? $wxStatus : '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Etiqueta</div><div>' . ($et === 1 ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Shipping ID</div><div>' . $h($wxShipId !== '' ? $wxShipId : '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">WExpress Tracking</div><div>' . $h($wxTrack !== '' ? $wxTrack : '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Courier Tracking</div><div>' . $h($wxCourier !== '' ? $wxCourier : '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Etiqueta URL</div><div>' . ($labelUrl !== '' ? '<a href="' . $h($labelUrl) . '" target="_blank">Abrir</a>' : '-') . '</div></div>
</div></div></div>';

        // Medicamento
        echo '<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="fas fa-pills me-1"></i>Medicamento</strong>
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="switchMedicamento" ' . ($medicamentoFlag ? 'checked' : '') . ' onchange="salvarMedicamento(this.checked)">
        <label class="form-check-label" for="switchMedicamento">É medicamento</label>
    </div>
</div>
<div class="card-body" id="medicamentoBlock" ' . (!$medicamentoFlag ? 'style="display:none"' : '') . '>
    <div class="alert alert-warning py-2 mb-3"><i class="fas fa-exclamation-triangle me-1"></i>Pedido marcado como medicamento. Upload do documento é <strong>obrigatório</strong>.</div>
    <div class="mb-2"><strong>Documento do medicamento:</strong>
    ' . (!empty($docs['medicamento']['file_path'])
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Enviado: ' . $h($docs['medicamento']['original_name'] ?? '') . '</span> <a href="' . $h($docs['medicamento']['file_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary ms-2">Ver</a>'
        : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Pendente</span>') . '
    </div>
    <div class="mt-2">
        <label class="form-label">Upload do documento (PDF, JPG, PNG)</label>
        <input type="file" class="form-control" id="fileMedicamento" accept=".pdf,.jpg,.jpeg,.png,.webp">
        <button class="btn btn-sm btn-primary mt-2" onclick="uploadDoc(\'medicamento\')"><i class="fas fa-upload me-1"></i>Enviar</button>
    </div>
</div>
<div class="card-body" id="semMedicamentoBlock" ' . ($medicamentoFlag ? 'style="display:none"' : '') . '>
    <div class="text-muted small"><i class="fas fa-info-circle me-1"></i>Para pedidos do site, o comprovante de pagamento é exibido nas informações do pedido e não exige upload.</div>
</div></div>';

        // Cliente
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-user me-1"></i>Dados do Cliente</strong></div><div class="card-body">
<div class="row g-2">
    <div class="col-md-4"><div class="text-muted small">Nome</div><div>' . $h($pedido['cliente_nome'] ?? '') . '</div></div>
    <div class="col-md-2"><div class="text-muted small">Suíte</div><div>' . $h($pedido['cliente_suite'] ?? '') . '</div></div>
    <div class="col-md-4"><div class="text-muted small">E-mail</div><div>' . $h($pedido['cliente_email'] ?? '') . '</div></div>
    <div class="col-md-2"><div class="text-muted small">CPF/Doc</div><div>' . $h($pedido['cliente_cpf'] ?? '') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Celular</div><div>' . $h($pedido['cliente_telefone'] ?? '') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">IP</div><div>' . $h($pedido['ip_cliente'] ?? ($pedido['customer_ip'] ?? '')) . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Aceita substituição</div><div>' . $h($pedido['aceita_substituicao'] ?? '-') . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Código de rastreio</div><div>' . $h($wxCourier !== '' ? $wxCourier : ($wxTrack !== '' ? $wxTrack : '-')) . '</div></div>
</div></div></div>';

        // Endereço de entrega
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-map-marker-alt me-1"></i>Endereço de Entrega</strong></div><div class="card-body">
<div class="row g-2">
    <div class="col-md-4"><div class="text-muted small">Nome</div><div>' . $h($pedido['cliente_nome'] ?? '') . '</div></div>
    <div class="col-md-4"><div class="text-muted small">Rua/Logradouro</div><div>' . $h($logr) . '</div></div>
    <div class="col-md-2"><div class="text-muted small">Número</div><div>' . $h($num) . '</div></div>
    <div class="col-md-2"><div class="text-muted small">Complemento</div><div>' . $h($compl) . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Bairro</div><div>' . $h($bairro) . '</div></div>
    <div class="col-md-3"><div class="text-muted small">Cidade</div><div>' . $h($cidade) . '</div></div>
    <div class="col-md-2"><div class="text-muted small">Estado</div><div>' . $h($estado) . '</div></div>
    <div class="col-md-2"><div class="text-muted small">CEP</div><div>' . $h($cep) . '</div></div>
    <div class="col-md-2"><div class="text-muted small">País</div><div>' . $h($pais) . '</div></div>
</div></div></div>';

        // Itens
        $itens = $pedido['itens'] ?? [];
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-box me-1"></i>Itens do Pedido</strong></div><div class="card-body">
<div class="table-responsive"><table class="table table-sm align-middle">
<thead><tr><th>#</th><th>Imagem</th><th>Declaração</th><th>SKU</th><th>NCM</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th></tr></thead><tbody>';
        if (!$itens) {
            echo '<tr><td colspan="8" class="text-center text-muted">Nenhum item.</td></tr>';
        } else {
            $idx = 1;
            foreach ($itens as $it) {
                $pu = null;
                foreach (['preco_unitario','valor_unitario','preco','price'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) { $pu = (float)$it[$c]; break; }
                }
                if ($pu !== null && $moeda === 'BRL') $pu *= $brlToUsd;
                $qtdIt = (int)($it['quantidade'] ?? 0);
                $totIt = $pu !== null ? $pu * $qtdIt : null;
                $foto = trim((string)($it['foto_produto'] ?? ''));
                if ($foto !== '' && !str_starts_with($foto, 'http')) $foto = '/' . ltrim($foto, '/');
                echo '<tr>
                    <td>' . $idx . '</td>
                    <td>' . ($foto !== '' ? '<img src="' . $h($foto) . '" style="width:48px;height:48px;object-fit:cover;border-radius:6px">' : '<span class="text-muted">-</span>') . '</td>
                    <td>' . $h($it['produto_nome'] ?? $it['nome_produto'] ?? '') . '</td>
                    <td>' . $h($it['sku'] ?? '') . '</td>
                    <td>' . $h($it['ncm'] ?? '') . '</td>
                    <td>' . $qtdIt . '</td>
                    <td>' . $fmtUsd($pu) . '</td>
                    <td>' . $fmtUsd($totIt) . '</td>
                </tr>';
                $idx++;
            }
        }
        echo '</tbody></table></div></div></div>';

        // Pagamento
        echo '<div class="row"><div class="col-md-6">
<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-credit-card me-1"></i>Pagamento</strong></div><div class="card-body">
<table class="table table-sm mb-0">
<tr><td class="text-muted">Valor pago (BRL)</td><td>' . $fmtBrl($totalBrl > 0 ? $totalBrl : null) . '</td></tr>
<tr><td class="text-muted">Valor pago (USD)</td><td>' . $fmtUsd($totalUsdPago > 0 ? $totalUsdPago : $totalPedidoUsd) . '</td></tr>
<tr><td class="text-muted">Taxa de conversão</td><td>R$ ' . number_format($usdToBrl, 4, ',', '.') . ' / USD</td></tr>
<tr><td class="text-muted">Data de crédito</td><td>' . ($dataPagamento !== '' ? date('d/m/Y H:i', strtotime($dataPagamento)) : '-') . '</td></tr>
<tr><td class="text-muted">Método</td><td>' . $h($metodoPagamento) . '</td></tr>
<tr><td class="text-muted">Produtos</td><td>' . $fmtBrl($produtosValor > 0 ? $produtosValor : null) . '</td></tr>
<tr><td class="text-muted">Taxa de serviço</td><td>' . $fmtBrl($taxaServico > 0 ? $taxaServico : null) . '</td></tr>
<tr><td class="text-muted">Imposto</td><td>' . $fmtBrl($impostoValor > 0 ? $impostoValor : null) . '</td></tr>
<tr><td class="text-muted">Imposto local</td><td>' . $fmtBrl($impostoLocal > 0 ? $impostoLocal : null) . '</td></tr>
<tr><td class="text-muted">AppMax</td><td>' . $fmtBrl($appmaxValor > 0 ? $appmaxValor : null) . '</td></tr>
<tr><td class="text-muted">Câmbio Real</td><td>' . $fmtBrl($cambioRealValor > 0 ? $cambioRealValor : null) . '</td></tr>
</table></div></div></div>
<div class="col-md-6">
<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-calculator me-1"></i>Totais</strong></div><div class="card-body">
<table class="table table-sm mb-0">
<tr><td class="text-muted">Subtotal</td><td>' . $fmtUsd($subtotal) . '</td></tr>
<tr><td class="text-muted">Frete</td><td>' . $fmtUsd($frete) . '</td></tr>
<tr><td class="text-muted">Desconto</td><td>' . $fmtUsd(is_numeric($pedido['desconto'] ?? null) ? (float)$pedido['desconto'] : null) . '</td></tr>
<tr><td class="text-muted">Imposto local</td><td>' . $fmtUsd(is_numeric($pedido['imposto_local'] ?? null) ? (float)$pedido['imposto_local'] : null) . '</td></tr>
<tr class="fw-bold"><td>Total</td><td>' . $fmtUsd($totalPedidoUsd) . '</td></tr>
</table></div></div>
</div></div>';

        // Split detalhado
        if (!empty($pagamentos)) {
            echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-code-branch me-1"></i>Split de Pagamento</strong></div><div class="card-body">
<div class="table-responsive"><table class="table table-sm">
<thead><tr><th>Componente</th><th>Gateway</th><th>Método</th><th>Moeda</th><th>Valor</th><th>Status</th><th>Link</th></tr></thead><tbody>';
            foreach ($pagamentos as $pg) {
                $gw = strtolower((string)($pg['gateway'] ?? ''));
                $gwLabel = $gw !== '' ? strtoupper($gw) : '-';
                if ($gw === 'cambioreal') $gwLabel = 'Câmbio Real';
                if ($gw === 'appmax') $gwLabel = 'AppMax';
                $url = trim((string)($pg['invoice_url'] ?? ($pg['bank_slip_url'] ?? '')));
                $pix = trim((string)($pg['pix_payload'] ?? ''));
                $link = $url !== '' ? '<a href="' . $h($url) . '" target="_blank">Abrir</a>' : ($pix !== '' ? '<span class="text-muted small">PIX</span>' : '-');
                echo '<tr>
                    <td>' . $h($pg['componente'] ?? '') . '</td>
                    <td>' . $h($gwLabel) . '</td>
                    <td>' . $h($pg['metodo'] ?? '') . '</td>
                    <td>' . $h(strtoupper((string)($pg['moeda'] ?? ''))) . '</td>
                    <td>' . number_format((float)($pg['valor'] ?? 0), 2, ',', '.') . '</td>
                    <td>' . $h($pg['status'] ?? '') . '</td>
                    <td>' . $link . '</td>
                </tr>';
            }
            echo '</tbody></table></div></div></div>';
        }

        // Medidas
        $pesoTotal = (float)($pedido['peso_total'] ?? 0);
        $altura = (float)($pedido['altura'] ?? 0);
        $largura = (float)($pedido['largura'] ?? 0);
        $comprimento = (float)($pedido['comprimento'] ?? 0);
        if ($pesoTotal > 0 || $altura > 0) {
            echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-ruler-combined me-1"></i>Medidas e Peso</strong></div><div class="card-body">
<div class="row g-2 text-center">
    <div class="col-3"><div class="fw-bold">' . number_format($pesoTotal, 3) . ' kg</div><div class="text-muted small">Peso</div></div>
    <div class="col-3"><div class="fw-bold">' . number_format($altura, 2) . ' cm</div><div class="text-muted small">Altura</div></div>
    <div class="col-3"><div class="fw-bold">' . number_format($largura, 2) . ' cm</div><div class="text-muted small">Largura</div></div>
    <div class="col-3"><div class="fw-bold">' . number_format($comprimento, 2) . ' cm</div><div class="text-muted small">Comprimento</div></div>
</div></div></div>';
        }

        // Informações úteis
        $useful = [
            'Código do pedido' => $pedido['codigo_pedido'] ?? $pedido['codigo'] ?? '',
            'Gateway' => $pedido['payment_gateway'] ?? $pedido['pagamento_gateway'] ?? '',
            'ID transação' => $pedido['payment_id'] ?? $pedido['pagamento_transacao'] ?? '',
            'Status pagamento' => $pedido['pagamento_status'] ?? $pedido['payment_status'] ?? '',
            'Moeda' => $moeda,
            'Observação vendedor' => $pedido['observacao_vendedor'] ?? '',
            'Observação cliente' => $pedido['observacao_cliente'] ?? ($pedido['customer_note'] ?? ''),
            'Criado em' => !empty($pedido['created_at']) ? date('d/m/Y H:i:s', strtotime((string)$pedido['created_at'])) : '',
            'Atualizado em' => !empty($pedido['updated_at']) ? date('d/m/Y H:i:s', strtotime((string)$pedido['updated_at'])) : '',
        ];
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-info me-1"></i>Informações Úteis</strong></div><div class="card-body">
<table class="table table-sm mb-0">';
        foreach ($useful as $k => $v) {
            if ((string)$v === '') continue;
            echo '<tr><td class="text-muted" style="width:200px">' . $h($k) . '</td><td>' . $h($v) . '</td></tr>';
        }
        echo '</table></div></div>';

        echo '</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const jid = ' . $jid . ', pid = ' . $pid . ';
function salvarMedicamento(val) {
    document.getElementById("medicamentoBlock").style.display = val ? "" : "none";
    document.getElementById("semMedicamentoBlock").style.display = val ? "none" : "";
    fetch("/admin/remessa-conferencia/janela/" + jid + "/pedido/" + pid + "/medicamento", {
        method: "POST", headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: "medicamento=" + (val ? "1" : "0")
    }).then(r => r.json()).catch(() => {});
}
function uploadDoc(tipo) {
    const input = document.getElementById("file" + tipo.charAt(0).toUpperCase() + tipo.slice(1));
    if (!input || !input.files[0]) { alert("Selecione um arquivo."); return; }
    const fd = new FormData();
    fd.append("arquivo", input.files[0]);
    fetch("/admin/remessa-conferencia/janela/" + jid + "/pedido/" + pid + "/documento/" + tipo, {
        method: "POST", body: fd
    }).then(r => r.json()).then(d => {
        if (d.success) { alert("Documento enviado!"); location.reload(); }
        else alert("Erro: " + (d.error || "Falha"));
    }).catch(() => alert("Erro ao enviar"));
}
</script>
</body></html>';
        exit;
    }
}
