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
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getUsdToBrlRate(): float {
        try {
            $st = $this->connection->query("SELECT valor FROM configuracoes_sistema WHERE categoria='sistema' AND chave='usd_brl_rate' LIMIT 1");
            $v = $st ? $st->fetchColumn() : false;
            if ($v !== false && is_numeric($v) && (float)$v > 0) return (float)$v;
        } catch (\Exception $e) {}
        return 5.80;
    }

    private function getPedidoCompleto(int $pedidoId): ?array {
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email, u.telefone AS cliente_telefone
            FROM pedidos p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) return null;

        // Itens
        try {
            $stI = $this->connection->prepare("
                SELECT pi.*, pr.name AS produto_nome, pr.sku, pr.ncm
                FROM pedido_itens pi
                LEFT JOIN produtos pr ON pi.produto_id = pr.id
                WHERE pi.pedido_id = ?
            ");
            $stI->execute([$pedidoId]);
            $pedido['itens'] = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $pedido['itens'] = [];
        }

        // Endereço
        try {
            $stE = $this->connection->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1");
            $stE->execute([$pedido['usuario_id']]);
            $pedido['endereco'] = $stE->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $pedido['endereco'] = null;
        }

        return $pedido;
    }

    public function index($request) {
        $this->requireAccess();

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $janelasAbertas = [];
        $janelasFinalizadas = [];
        $janelasGeradas = [];
        $errorMsg = null;

        try {
            if (!$this->tableExists('remessa_janelas')) {
                throw new \Exception('Tabela remessa_janelas não encontrada.');
            }
            $stA = $this->connection->query("SELECT * FROM remessa_janelas WHERE status = 'aberta' ORDER BY data_inicio DESC");
            $janelasAbertas = $stA ? $stA->fetchAll(\PDO::FETCH_ASSOC) : [];

            $stF = $this->connection->query("SELECT * FROM remessa_janelas WHERE status IN ('finalizada','atraso') ORDER BY data_inicio DESC LIMIT 20");
            $janelasFinalizadas = $stF ? $stF->fetchAll(\PDO::FETCH_ASSOC) : [];

            $stG = $this->connection->query("SELECT * FROM remessa_janelas WHERE status = 'remessa_gerada' ORDER BY data_inicio DESC LIMIT 20");
            $janelasGeradas = $stG ? $stG->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }

        echo '<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conferência de Remessa - Braziliana Admin</title>
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

        if ($errorMsg) {
            echo '<div class="alert alert-danger"><strong>Erro:</strong> ' . htmlspecialchars($errorMsg) . '</div>';
        }

        $renderJanelas = function(array $janelas, string $titulo, string $badgeClass) {
            echo '<div class="card mb-4"><div class="card-header"><strong>' . htmlspecialchars($titulo) . '</strong></div><div class="card-body">';
            if (!$janelas) {
                echo '<div class="text-muted">Nenhuma janela.</div>';
            } else {
                echo '<div class="list-group">';
                foreach ($janelas as $j) {
                    $di = date('d/m/Y', strtotime((string)$j['data_inicio']));
                    $df = date('d/m/Y', strtotime((string)$j['data_fim']));
                    $st = htmlspecialchars((string)($j['status'] ?? ''));
                    echo '<a class="list-group-item list-group-item-action" href="/admin/remessa-conferencia/janela/' . (int)$j['id'] . '">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>Janela #' . (int)$j['id'] . '</strong> <span class="text-muted small">(' . $di . ' a ' . $df . ')</span></div>
                            <span class="badge bg-' . $badgeClass . '">' . $st . '</span>
                        </div>
                    </a>';
                }
                echo '</div>';
            }
            echo '</div></div>';
        };

        $renderJanelas($janelasAbertas, 'Janelas Abertas', 'success');
        $renderJanelas($janelasFinalizadas, 'Janelas Finalizadas / Em Atraso', 'secondary');
        $renderJanelas($janelasGeradas, 'Remessas Geradas', 'info');

        echo '</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>';
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
        try {
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {}

        $hasMoeda = in_array('moeda', $colsPedidos, true);
        $hasCurrency = in_array('currency', $colsPedidos, true);

        $sql = "SELECT rjp.pedido_id, rjp.etiqueta_gerada, rjp.etiqueta_gerada_em,
                    rjp.wexpress_shipping_id, rjp.wexpress_tracking_number, rjp.courier_tracking_number, rjp.wexpress_status,
                    p.created_at, p.total, p.status,
                    " . ($hasMoeda ? 'p.moeda,' : "'' AS moeda,") . "
                    " . ($hasCurrency ? 'p.currency,' : "'' AS currency,") . "
                    u.nome AS cliente_nome, u.email AS cliente_email
                FROM remessa_janela_pedidos rjp
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

        echo '<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Total</th><th>Etiqueta</th><th>Ações</th></tr></thead><tbody>';

        if (!$pedidos) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            foreach ($pedidos as $p) {
                $pid = (int)($p['pedido_id'] ?? 0);
                $et = (int)($p['etiqueta_gerada'] ?? 0);
                $moeda = strtoupper(trim((string)($p['moeda'] ?? ($p['currency'] ?? 'USD'))));
                $tv = is_numeric($p['total'] ?? null) ? (float)$p['total'] : null;
                if ($tv !== null && $moeda === 'BRL') $tv *= $brlToUsd;
                $tvStr = $tv !== null ? 'US$ ' . number_format($tv, 2, ',', '.') : '-';
                $dt = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string)$p['created_at'])) : '-';
                $wxShipId = (string)($p['wexpress_shipping_id'] ?? '');
                $wxCourier = (string)($p['courier_tracking_number'] ?? '');
                $wxTrack = (string)($p['wexpress_tracking_number'] ?? '');
                $labelUrl = '';
                if ($wxShipId !== '') $labelUrl = 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId);

                echo '<tr>
                    <td><strong>#' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</strong></td>
                    <td>' . htmlspecialchars((string)($p['cliente_nome'] ?? 'N/A')) . '<br><small class="text-muted">' . htmlspecialchars((string)($p['cliente_email'] ?? '')) . '</small></td>
                    <td>' . $dt . '</td>
                    <td>' . $tvStr . '</td>
                    <td>' . ($et === 1 ? '<span class="badge bg-success">Gerada</span>' : '<span class="badge bg-warning text-dark">Pendente</span>');
                if ($wxCourier !== '') echo '<br><small class="text-muted">Tracking: ' . htmlspecialchars($wxCourier) . '</small>';
                elseif ($wxTrack !== '') echo '<br><small class="text-muted">Tracking: ' . htmlspecialchars($wxTrack) . '</small>';
                echo '</td><td class="text-nowrap">'
                    . ($labelUrl !== '' ? '<a class="btn btn-sm btn-outline-primary me-1" target="_blank" href="' . htmlspecialchars($labelUrl) . '"><i class="fas fa-tag"></i> Etiqueta</a>' : '')
                    . '<a class="btn btn-sm btn-outline-secondary" href="/admin/remessa-conferencia/janela/' . $janelaId . '/pedido/' . $pid . '"><i class="fas fa-eye"></i> Detalhes</a>'
                    . '</td></tr>';
            }
        }

        echo '</tbody></table></div></div></div></main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>';
        exit;
    }

    public function detalhesPedido($request, $janelaId, $pedidoId) {
        $this->requireAccess();

        $jid = (int) $janelaId;
        $pid = (int) $pedidoId;
        if ($jid <= 0 || $pid <= 0) { header('Location: /admin/remessa-conferencia'); exit; }

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

        echo '<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/admin/remessa-conferencia/janela/' . $jid . '"><i class="fas fa-arrow-left"></i> Voltar</a>
        ' . ($labelUrl !== '' ? '<a class="btn btn-outline-primary" href="' . htmlspecialchars($labelUrl) . '" target="_blank"><i class="fas fa-download"></i> Baixar etiqueta</a>' : '') . '
    </div>
</div>';

        if ($wxStatus !== '' || $wxShipId !== '' || $wxCourier !== '') {
            echo '<div class="alert alert-light border mb-3 small">';
            if ($wxStatus !== '') echo '<div><strong>Status W-Express:</strong> ' . htmlspecialchars($wxStatus) . '</div>';
            if ($wxShipId !== '') echo '<div><strong>Shipping ID:</strong> ' . htmlspecialchars($wxShipId) . '</div>';
            if ($wxTrack !== '') echo '<div><strong>WExpress tracking:</strong> ' . htmlspecialchars($wxTrack) . '</div>';
            if ($wxCourier !== '') echo '<div><strong>Courier tracking:</strong> ' . htmlspecialchars($wxCourier) . '</div>';
            echo '</div>';
        }

        // Medidas
        $pesoTotal = (float)($pedido['peso_total'] ?? 0);
        $altura = (float)($pedido['altura'] ?? 0);
        $largura = (float)($pedido['largura'] ?? 0);
        $comprimento = (float)($pedido['comprimento'] ?? 0);
        if ($pesoTotal > 0 || $altura > 0) {
            echo '<div class="card mb-3"><div class="card-header"><strong>Medidas e Peso</strong></div><div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-3"><div class="fw-bold">' . number_format($pesoTotal, 3) . ' kg</div><div class="text-muted small">Peso</div></div>
                    <div class="col-3"><div class="fw-bold">' . number_format($altura, 2) . ' cm</div><div class="text-muted small">Altura</div></div>
                    <div class="col-3"><div class="fw-bold">' . number_format($largura, 2) . ' cm</div><div class="text-muted small">Largura</div></div>
                    <div class="col-3"><div class="fw-bold">' . number_format($comprimento, 2) . ' cm</div><div class="text-muted small">Comprimento</div></div>
                </div>
            </div></div>';
        }

        // Cliente + Pedido
        $totalUsd = is_numeric($pedido['total'] ?? null) ? (float)$pedido['total'] : null;
        if ($totalUsd !== null && $moeda === 'BRL') $totalUsd *= $brlToUsd;
        echo '<div class="row"><div class="col-md-6">
            <div class="card mb-3"><div class="card-header"><strong>Cliente</strong></div><div class="card-body">
                <div><strong>Nome:</strong> ' . htmlspecialchars((string)($pedido['cliente_nome'] ?? '')) . '</div>
                <div><strong>Email:</strong> ' . htmlspecialchars((string)($pedido['cliente_email'] ?? '')) . '</div>
                <div><strong>Telefone:</strong> ' . htmlspecialchars((string)($pedido['cliente_telefone'] ?? '')) . '</div>
            </div></div>
        </div><div class="col-md-6">
            <div class="card mb-3"><div class="card-header"><strong>Pedido</strong></div><div class="card-body">
                <div><strong>Data:</strong> ' . (!empty($pedido['created_at']) ? date('d/m/Y H:i', strtotime((string)$pedido['created_at'])) : '-') . '</div>
                <div><strong>Total:</strong> US$ ' . ($totalUsd !== null ? number_format($totalUsd, 2, ',', '.') : '-') . '</div>
                <div><strong>Status:</strong> ' . htmlspecialchars((string)($pedido['status'] ?? '')) . '</div>
            </div></div>
        </div></div>';

        // Endereço
        $end = $pedido['endereco'] ?? null;
        echo '<div class="card mb-3"><div class="card-header"><strong>Endereço de Entrega</strong></div><div class="card-body">';
        if (is_array($end) && !empty($end)) {
            $logr = trim((string)($end['endereco'] ?? ($end['logradouro'] ?? '')));
            $num = trim((string)($end['numero'] ?? ''));
            $compl = trim((string)($end['complemento'] ?? ''));
            $bairro = trim((string)($end['bairro'] ?? ''));
            $cidade = trim((string)($end['cidade'] ?? ''));
            $estado = trim((string)($end['estado'] ?? ''));
            $cep = trim((string)($end['cep'] ?? ''));
            echo htmlspecialchars($logr . ($num ? ', ' . $num : '') . ($compl ? ' - ' . $compl : '') . ($bairro ? ' - ' . $bairro : ''))
                . '<br>' . htmlspecialchars($cidade . ($estado ? '/' . $estado : '') . ($cep ? ' - CEP: ' . $cep : ''));
        } else {
            echo '<span class="text-muted">Não encontrado</span>';
        }
        echo '</div></div>';

        // Itens
        $itens = $pedido['itens'] ?? [];
        echo '<div class="card"><div class="card-header"><strong>Itens</strong></div><div class="card-body">
        <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Produto</th><th>SKU</th><th>NCM</th><th>Qtd</th><th>Preço</th></tr></thead><tbody>';
        if (!$itens) {
            echo '<tr><td colspan="5" class="text-center text-muted">Nenhum item.</td></tr>';
        } else {
            foreach ($itens as $it) {
                $pu = null;
                foreach (['preco_unitario', 'valor_unitario', 'preco', 'price'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) { $pu = (float)$it[$c]; break; }
                }
                if ($pu !== null && $moeda === 'BRL') $pu *= $brlToUsd;
                echo '<tr>
                    <td>' . htmlspecialchars((string)($it['produto_nome'] ?? $it['nome_produto'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string)($it['sku'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string)($it['ncm'] ?? '')) . '</td>
                    <td>' . (int)($it['quantidade'] ?? 0) . '</td>
                    <td>US$ ' . ($pu !== null ? number_format($pu, 2, ',', '.') : '-') . '</td>
                </tr>';
            }
        }
        echo '</tbody></table></div></div></div>';
        echo '</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>';
        exit;
    }
}
