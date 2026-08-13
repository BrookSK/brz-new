<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminDesapegoController extends Controller {

    private function getDirectPdo(): \PDO {
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    /**
     * Painel principal: lista desapeguistas, produtos vinculados, comissões
     */
    public function comissoes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $pdo = $this->getDirectPdo();

        // Verificar se tabelas/colunas existem
        $colsUsr = [];
        try { $st = $pdo->query('DESCRIBE usuarios'); $colsUsr = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) {}
        $colsProd = [];
        try { $st = $pdo->query('DESCRIBE produtos'); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) {}

        $hasDesapego = in_array('is_desapeguista', $colsUsr, true) && in_array('desapego', $colsProd, true);

        // Buscar desapeguistas
        $desapeguistas = [];
        if ($hasDesapego) {
            $nomeCol = in_array('nome', $colsUsr, true) ? 'nome' : 'name';
            $st = $pdo->query("SELECT id, {$nomeCol} AS nome, email, desapeguista_comissao FROM usuarios WHERE is_desapeguista = 1 ORDER BY {$nomeCol} ASC");
            $desapeguistas = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        }

        // Para cada desapeguista, buscar produtos vinculados e comissões
        $nameCol = in_array('name', $colsProd, true) ? 'name' : 'nome';
        $priceCol = in_array('price', $colsProd, true) ? 'price' : 'valor';

        foreach ($desapeguistas as &$d) {
            $did = (int) $d['id'];

            // Produtos vinculados
            try {
                $stP = $pdo->prepare("SELECT id, {$nameCol} AS nome, {$priceCol} AS preco, stock AS estoque FROM produtos WHERE desapeguista_id = ? AND desapego = 1 ORDER BY id DESC");
                $stP->execute([$did]);
                $d['produtos'] = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $d['produtos'] = [];
            }

            // Comissões (da tabela desapego_comissoes)
            $d['comissoes'] = [];
            $d['total_comissao'] = 0;
            $d['total_pago'] = 0;
            $d['total_pendente'] = 0;
            try {
                $tableExists = false;
                try { $pdo->query('SELECT 1 FROM desapego_comissoes LIMIT 1'); $tableExists = true; } catch (\Throwable $e) {}
                if ($tableExists) {
                    $stC = $pdo->prepare("SELECT dc.*, p.{$nameCol} AS produto_nome 
                        FROM desapego_comissoes dc 
                        LEFT JOIN produtos p ON dc.produto_id = p.id 
                        WHERE dc.desapeguista_id = ? 
                        ORDER BY dc.created_at DESC 
                        LIMIT 100");
                    $stC->execute([$did]);
                    $d['comissoes'] = $stC->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    foreach ($d['comissoes'] as $c) {
                        $d['total_comissao'] += (float) ($c['valor_comissao'] ?? 0);
                        if (($c['status'] ?? '') === 'pago') {
                            $d['total_pago'] += (float) ($c['valor_comissao'] ?? 0);
                        } else {
                            $d['total_pendente'] += (float) ($c['valor_comissao'] ?? 0);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        unset($d);

        // Filtro por desapeguista específico
        $filtroDesapeguista = (int) $request->getParam('desapeguista_id', 0);

        // Incluir sidebar
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desapego - Comissões - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '<style>
        .desapeguista-card { border-radius: 16px; border: none; box-shadow: 0 4px 16px rgba(0,0,0,.06); margin-bottom: 1.5rem; }
        .desapeguista-header { background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%); color: #fff; border-radius: 16px 16px 0 0; padding: 1.2rem 1.5rem; }
        .stat-box { background: rgba(255,255,255,.12); border-radius: 10px; padding: .6rem 1rem; text-align: center; }
        .stat-box .value { font-size: 1.3rem; font-weight: 700; }
        .stat-box .label { font-size: .75rem; opacity: .85; }
        .produto-row { border-bottom: 1px solid #f0f0f0; padding: .6rem 0; }
        .produto-row:last-child { border-bottom: none; }
        .comissao-badge { font-size: .75rem; padding: .3rem .6rem; border-radius: 8px; }
        .badge-pendente { background: #fef3cd; color: #856404; }
        .badge-aprovado { background: #d1ecf1; color: #0c5460; }
        .badge-pago { background: #d4edda; color: #155724; }
        .badge-cancelado { background: #f8d7da; color: #721c24; }
        </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('desapego');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h3 fw-bold"><i class="fas fa-hand-holding-heart me-2 text-info"></i>Desapego Braziliana — Comissões</h1>
                    <p class="text-muted mb-0">Gerencie desapeguistas, produtos vinculados e comissões.</p>
                </div>
            </div>';

        if (!$hasDesapego) {
            echo '<div class="alert alert-warning">As colunas de desapego ainda não existem no banco. Execute a migration 213 primeiro.</div>';
        } elseif (empty($desapeguistas)) {
            echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Nenhum desapeguista cadastrado ainda. Marque usuários como desapeguista na <a href="/admin/usuarios">listagem de usuários</a>.</div>';
        } else {
            // Filtro
            echo '<div class="mb-4">
                <form method="GET" action="/admin/desapego/comissoes" class="d-flex gap-2 align-items-center">
                    <select name="desapeguista_id" class="form-select" style="max-width:300px;" onchange="this.form.submit()">
                        <option value="0">Todos os desapeguistas</option>';
            foreach ($desapeguistas as $dOpt) {
                $sel = ($filtroDesapeguista === (int) $dOpt['id']) ? ' selected' : '';
                echo '<option value="' . (int) $dOpt['id'] . '"' . $sel . '>' . htmlspecialchars($dOpt['nome']) . '</option>';
            }
            echo '  </select>
                </form>
            </div>';

            foreach ($desapeguistas as $d) {
                if ($filtroDesapeguista > 0 && (int) $d['id'] !== $filtroDesapeguista) continue;

                $totalProdutos = count($d['produtos']);
                echo '<div class="card desapeguista-card">
                    <div class="desapeguista-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h5 class="mb-0 fw-bold"><i class="bi bi-person-heart me-2"></i>' . htmlspecialchars($d['nome']) . '</h5>
                                <small class="opacity-75">' . htmlspecialchars($d['email'] ?? '') . ' — Comissão: ' . htmlspecialchars((string) $d['desapeguista_comissao']) . '%</small>
                            </div>
                            <div class="d-flex gap-2">
                                <div class="stat-box">
                                    <div class="value">' . $totalProdutos . '</div>
                                    <div class="label">Produtos</div>
                                </div>
                                <div class="stat-box">
                                    <div class="value">$' . number_format($d['total_comissao'], 2) . '</div>
                                    <div class="label">Total Comissão</div>
                                </div>
                                <div class="stat-box">
                                    <div class="value">$' . number_format($d['total_pago'], 2) . '</div>
                                    <div class="label">Pago</div>
                                </div>
                                <div class="stat-box">
                                    <div class="value">$' . number_format($d['total_pendente'], 2) . '</div>
                                    <div class="label">Pendente</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">';

                // Produtos vinculados
                if (empty($d['produtos'])) {
                    echo '<p class="text-muted mb-0"><i class="bi bi-box-seam me-1"></i>Nenhum produto vinculado a este desapeguista.</p>';
                } else {
                    echo '<h6 class="fw-semibold mb-2"><i class="bi bi-box-seam me-1"></i>Produtos Vinculados</h6>
                        <div class="table-responsive">
                        <table class="table table-sm table-hover mb-3">
                            <thead><tr><th>#</th><th>Produto</th><th>Preço</th><th>Estoque</th></tr></thead>
                            <tbody>';
                    foreach ($d['produtos'] as $prod) {
                        echo '<tr>
                            <td>' . (int) $prod['id'] . '</td>
                            <td><a href="/produto/detalhes/' . (int) $prod['id'] . '" target="_blank">' . htmlspecialchars($prod['nome']) . '</a></td>
                            <td>US$ ' . number_format((float) $prod['preco'], 2) . '</td>
                            <td>' . (int) ($prod['estoque'] ?? 0) . '</td>
                        </tr>';
                    }
                    echo '</tbody></table></div>';
                }

                // Comissões
                if (!empty($d['comissoes'])) {
                    echo '<h6 class="fw-semibold mb-2 mt-3"><i class="bi bi-cash-stack me-1"></i>Histórico de Comissões</h6>
                        <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead><tr><th>Data</th><th>Produto</th><th>Venda</th><th>%</th><th>Comissão</th><th>Status</th><th>Pago em</th></tr></thead>
                            <tbody>';
                    foreach ($d['comissoes'] as $c) {
                        $statusBadge = 'badge-' . ($c['status'] ?? 'pendente');
                        $statusLabel = ucfirst($c['status'] ?? 'pendente');
                        $dataPag = !empty($c['data_pagamento']) ? date('d/m/Y', strtotime($c['data_pagamento'])) : '—';
                        echo '<tr>
                            <td>' . (!empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '—') . '</td>
                            <td>' . htmlspecialchars($c['produto_nome'] ?? 'Produto #' . ($c['produto_id'] ?? '?')) . '</td>
                            <td>US$ ' . number_format((float) ($c['valor_venda'] ?? 0), 2) . '</td>
                            <td>' . htmlspecialchars((string) ($c['percentual_comissao'] ?? 30)) . '%</td>
                            <td class="fw-bold">US$ ' . number_format((float) ($c['valor_comissao'] ?? 0), 2) . '</td>
                            <td><span class="comissao-badge ' . $statusBadge . '">' . $statusLabel . '</span></td>
                            <td>' . $dataPag . '</td>
                        </tr>';
                    }
                    echo '</tbody></table></div>';
                } elseif (!empty($d['produtos'])) {
                    echo '<p class="text-muted mt-3 mb-0"><i class="bi bi-clock-history me-1"></i>Nenhuma comissão registrada ainda para este desapeguista.</p>';
                }

                echo '</div></div>';
            }
        }

        echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    }

    /**
     * Tela de listagem de produtos desapego pendentes (pedidos até caixa_fechada)
     */
    public function pendentes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);

        $pdo = $this->getDirectPdo();

        // Status que significam "ainda não enviou" (até caixa_fechada na progressão)
        $statusAtesCaixaFechada = [
            'pendente', 'processando', 'pago',
            'itens_parcialmente_comprados', 'itens_comprados',
            'invoice_liberado', 'invoice_confirmado',
            'fatura_pendente', 'fatura_paga', 'caixa_fechada'
        ];

        $colsProd = [];
        try { $st = $pdo->query('DESCRIBE produtos'); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) {}

        $hasDesapego = in_array('desapego', $colsProd, true);
        $itens = [];

        if ($hasDesapego) {
            $nameCol = in_array('name', $colsProd, true) ? 'name' : 'nome';
            $priceCol = in_array('price', $colsProd, true) ? 'price' : 'valor';
            $fotoCol = in_array('foto_principal', $colsProd, true) ? 'foto_principal' : null;

            $inStatus = implode(',', array_fill(0, count($statusAtesCaixaFechada), '?'));

            $sql = "SELECT 
                        pi.pedido_id,
                        pi.produto_id,
                        pi.quantidade,
                        pi.preco_unitario,
                        pr.{$nameCol} AS produto_nome,
                        pr.{$priceCol} AS produto_preco,
                        " . ($fotoCol ? "pr.{$fotoCol} AS foto_principal," : '') . "
                        pr.desapeguista_id,
                        p.status AS pedido_status,
                        p.created_at AS pedido_data,
                        u.nome AS cliente_nome,
                        u.email AS cliente_email,
                        du.nome AS desapeguista_nome
                    FROM pedido_itens pi
                    INNER JOIN produtos pr ON pi.produto_id = pr.id AND pr.desapego = 1
                    INNER JOIN pedidos p ON pi.pedido_id = p.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN usuarios du ON pr.desapeguista_id = du.id
                    WHERE p.status IN ({$inStatus})";

            // Excluir pedidos deletados
            $colsPed = [];
            try { $stP = $pdo->query('DESCRIBE pedidos'); $colsPed = $stP ? $stP->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) {}
            if (in_array('deleted_at', $colsPed, true)) {
                $sql .= " AND p.deleted_at IS NULL";
            }

            $sql .= " ORDER BY p.created_at DESC";

            try {
                $st = $pdo->prepare($sql);
                $st->execute($statusAtesCaixaFechada);
                $itens = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log('[DESAPEGO_PENDENTES] Erro: ' . $e->getMessage());
                $itens = [];
            }
        }

        // Incluir sidebar
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desapego - Pendentes - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('desapego');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h3 fw-bold"><i class="fas fa-hand-holding-heart me-2 text-info"></i>Desapego — Itens Pendentes</h1>
                    <p class="text-muted mb-0">Produtos de desapego em pedidos que ainda não foram enviados (até Caixa Fechada).</p>
                </div>
                <a href="/admin/desapego/comissoes" class="btn btn-outline-primary btn-sm"><i class="fas fa-percentage me-1"></i>Comissões</a>
            </div>';

        if (!$hasDesapego) {
            echo '<div class="alert alert-warning">Coluna desapego não encontrada. Execute a migration 213.</div>';
        } elseif (empty($itens)) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Nenhum item de desapego pendente no momento. Todos já foram enviados ou não há pedidos com itens de desapego.</div>';
        } else {
            echo '<div class="card border-0 shadow-sm" style="border-radius:14px;">
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Preço Unit.</th>
                            <th>Status Pedido</th>
                            <th>Cliente</th>
                            <th>Desapeguista</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($itens as $item) {
                $statusBadgeColor = 'secondary';
                $st = strtolower((string) ($item['pedido_status'] ?? ''));
                if ($st === 'pago') $statusBadgeColor = 'success';
                elseif (str_contains($st, 'parcial')) $statusBadgeColor = 'warning';
                elseif (str_contains($st, 'caixa')) $statusBadgeColor = 'dark';
                elseif (str_contains($st, 'invoice') || str_contains($st, 'fatura')) $statusBadgeColor = 'info';

                $data = !empty($item['pedido_data']) ? date('d/m/Y', strtotime($item['pedido_data'])) : '—';

                echo '<tr>
                    <td><a href="/admin/pedidos/detalhes/' . (int) $item['pedido_id'] . '" class="fw-bold">#' . str_pad((int) $item['pedido_id'], 6, '0', STR_PAD_LEFT) . '</a></td>
                    <td>' . htmlspecialchars($item['produto_nome'] ?? 'Produto #' . $item['produto_id']) . '</td>
                    <td class="text-center">' . (int) ($item['quantidade'] ?? 1) . '</td>
                    <td>US$ ' . number_format((float) ($item['preco_unitario'] ?? $item['produto_preco'] ?? 0), 2) . '</td>
                    <td><span class="badge bg-' . $statusBadgeColor . '">' . htmlspecialchars(str_replace('_', ' ', ucfirst($item['pedido_status'] ?? ''))) . '</span></td>
                    <td>
                        <div class="small fw-semibold">' . htmlspecialchars($item['cliente_nome'] ?? '—') . '</div>
                        <div class="small text-muted">' . htmlspecialchars($item['cliente_email'] ?? '') . '</div>
                    </td>
                    <td>' . (!empty($item['desapeguista_nome']) ? '<span class="badge" style="background:rgba(8,145,178,.12);color:#0891b2;"><i class="bi bi-person-heart me-1"></i>' . htmlspecialchars($item['desapeguista_nome']) . '</span>' : '<span class="text-muted">—</span>') . '</td>
                    <td class="small text-muted">' . $data . '</td>
                </tr>';
            }

            echo '</tbody></table></div></div>';
        }

        echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    }

    /**
     * Marcar comissão como paga (AJAX)
     */
    public function marcarPago(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        header('Content-Type: application/json; charset=UTF-8');

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $pdo = $this->getDirectPdo();
            $st = $pdo->prepare("UPDATE desapego_comissoes SET status = 'pago', data_pagamento = NOW(), updated_at = NOW() WHERE id = ?");
            $st->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Marcar comissão como aprovada (AJAX)
     */
    public function marcarAprovado(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        header('Content-Type: application/json; charset=UTF-8');

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $pdo = $this->getDirectPdo();
            $st = $pdo->prepare("UPDATE desapego_comissoes SET status = 'aprovado', updated_at = NOW() WHERE id = ?");
            $st->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
