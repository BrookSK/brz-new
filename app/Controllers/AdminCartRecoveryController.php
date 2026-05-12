<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminCartRecoveryController extends Controller {

    private function ensureTable(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cart_recovery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            carrinho_id INT DEFAULT NULL,
            status ENUM('abandonado','em_atendimento','recuperado','perdido','nao_retornou') DEFAULT 'abandonado',
            atendido_por INT DEFAULT NULL,
            pedido_recuperado_id INT DEFAULT NULL,
            valor_carrinho DECIMAL(10,2) DEFAULT 0,
            itens_carrinho INT DEFAULT 0,
            pagina_abandono VARCHAR(255) DEFAULT NULL,
            detectado_em DATETIME NOT NULL,
            atendido_em DATETIME DEFAULT NULL,
            recuperado_em DATETIME DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_status (status),
            INDEX idx_detectado (detectado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTable($pdo);

        // Detect new abandoned carts (users who visited cart/checkout 15+ min ago without completing)
        $this->detectarAbandonos($pdo);

        // Get filter
        $filtro = trim((string)$request->getParam('status', 'todos'));

        // Stats
        $stats = ['abandonados'=>0,'em_atendimento'=>0,'recuperados'=>0,'perdidos'=>0];
        try {
            $stats['abandonados'] = (int)$pdo->query("SELECT COUNT(*) FROM cart_recovery WHERE status='abandonado'")->fetchColumn();
            $stats['em_atendimento'] = (int)$pdo->query("SELECT COUNT(*) FROM cart_recovery WHERE status='em_atendimento'")->fetchColumn();
            $stats['recuperados'] = (int)$pdo->query("SELECT COUNT(*) FROM cart_recovery WHERE status='recuperado'")->fetchColumn();
            $stats['perdidos'] = (int)$pdo->query("SELECT COUNT(*) FROM cart_recovery WHERE status IN ('perdido','nao_retornou')")->fetchColumn();
        } catch (\Exception $e) {}

        // Get records
        $where = "1=1";
        if ($filtro === 'abandonado') $where = "cr.status='abandonado'";
        elseif ($filtro === 'em_atendimento') $where = "cr.status='em_atendimento'";
        elseif ($filtro === 'recuperado') $where = "cr.status='recuperado'";
        elseif ($filtro === 'perdido') $where = "cr.status IN ('perdido','nao_retornou')";

        $userNomeCol = 'nome';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        $registros = [];
        try {
            $sql = "SELECT cr.*, u.{$userNomeCol} AS cliente_nome, u.email AS cliente_email, u.telefone AS cliente_telefone
                    FROM cart_recovery cr
                    LEFT JOIN usuarios u ON u.id = cr.usuario_id
                    WHERE {$where}
                    ORDER BY cr.detectado_em ASC";
            $registros = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->renderPage($stats, $registros, $filtro);
    }

    private function detectarAbandonos(\PDO $pdo): void {
        // Find users who visited cart/checkout pages 15+ minutes ago but have no paid order since
        try {
            $sql = "SELECT DISTINCT be.usuario_id, MAX(be.created_at) AS ultimo_evento
                    FROM behavior_events be
                    WHERE be.event_type IN ('cart_view','begin_checkout')
                    AND be.usuario_id IS NOT NULL
                    AND be.created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    AND be.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND be.usuario_id NOT IN (
                        SELECT p.usuario_id FROM pedidos p
                        WHERE p.status IN ('pago','processando','enviado','entregue')
                        AND p.created_at > be.created_at
                        AND p.usuario_id IS NOT NULL
                    )
                    AND be.usuario_id NOT IN (
                        SELECT cr.usuario_id FROM cart_recovery cr
                        WHERE cr.detectado_em > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    )
                    GROUP BY be.usuario_id";
            $abandonos = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stInsert = $pdo->prepare("INSERT INTO cart_recovery (usuario_id, pagina_abandono, detectado_em, valor_carrinho, itens_carrinho) VALUES (?, ?, ?, ?, ?)");

            foreach ($abandonos as $a) {
                $uid = (int)$a['usuario_id'];
                // Get cart value
                $valor = 0; $itens = 0;
                try {
                    $stCart = $pdo->prepare("SELECT c.id, c.subtotal_produtos, (SELECT COUNT(*) FROM carrinho_items WHERE carrinho_id = c.id) AS total_itens FROM carrinhos c WHERE c.usuario_id = ? ORDER BY c.created_at DESC LIMIT 1");
                    $stCart->execute([$uid]);
                    $cart = $stCart->fetch(\PDO::FETCH_ASSOC);
                    if ($cart) {
                        $valor = (float)($cart['subtotal_produtos'] ?? 0);
                        $itens = (int)($cart['total_itens'] ?? 0);
                    }
                } catch (\Exception $e) {}

                if ($itens > 0) {
                    $stInsert->execute([$uid, 'checkout', $a['ultimo_evento'], $valor, $itens]);
                }
            }
        } catch (\Exception $e) {}
    }

    public function atualizarStatus(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $status = trim((string)($data['status'] ?? ''));
        $observacoes = trim((string)($data['observacoes'] ?? ''));

        if ($id <= 0 || !in_array($status, ['em_atendimento','recuperado','perdido','nao_retornou'])) {
            echo json_encode(['success'=>false,'error'=>'Dados inválidos']);
            return;
        }

        $adminId = (int)($_SESSION['usuario_id'] ?? 0);

        $updates = "status=?, updated_at=NOW()";
        $params = [$status];

        if ($status === 'em_atendimento') {
            $updates .= ", atendido_por=?, atendido_em=NOW()";
            $params[] = $adminId;
        } elseif ($status === 'recuperado') {
            $updates .= ", atendido_por=COALESCE(atendido_por,?), recuperado_em=NOW()";
            $params[] = $adminId;
        }

        if ($observacoes !== '') {
            $updates .= ", observacoes=?";
            $params[] = $observacoes;
        }

        $params[] = $id;
        $pdo->prepare("UPDATE cart_recovery SET {$updates} WHERE id=?")->execute($params);

        // If recovered and has a linked order, attribute commission
        if ($status === 'recuperado') {
            $pedidoId = (int)($data['pedido_id'] ?? 0);
            if ($pedidoId > 0) {
                $pdo->prepare("UPDATE cart_recovery SET pedido_recuperado_id=? WHERE id=?")->execute([$pedidoId, $id]);
                // Attribute commission to the admin who recovered
                try {
                    $pdo->prepare("UPDATE pedidos SET admin_criador_id=? WHERE id=? AND (admin_criador_id IS NULL OR admin_criador_id=0)")->execute([$adminId, $pedidoId]);
                } catch (\Exception $e) {}
            }
        }

        echo json_encode(['success'=>true]);
    }

    private function renderPage(array $stats, array $registros, string $filtro): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Recuperação de Carrinho - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/dashboard-redesign.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('cart-recovery');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="dashboard-page">';

        // Header
        echo '<header class="page-header"><div><h1 class="page-title">Recuperação de Carrinho</h1><p class="page-subtitle">Clientes que abandonaram o carrinho ou checkout nos últimos 7 dias</p></div></header>';

        // KPIs
        echo '<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Abandonados</div><div class="kpi-value">'.$stats['abandonados'].'</div><div class="kpi-subtext">Aguardando contato</div></div><div class="kpi-icon" style="background:#FFE4E6;color:#9F1239;border-color:#FFE4E6;"><i class="bi bi-cart-x-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Em Atendimento</div><div class="kpi-value">'.$stats['em_atendimento'].'</div><div class="kpi-subtext">Sendo contactados</div></div><div class="kpi-icon" style="background:#FEF3C7;color:#92400E;border-color:#FEF3C7;"><i class="bi bi-headset"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Recuperados</div><div class="kpi-value">'.$stats['recuperados'].'</div><div class="kpi-subtext">Vendas salvas</div></div><div class="kpi-icon" style="background:#D1FAE5;color:#065F46;border-color:#D1FAE5;"><i class="bi bi-check-circle-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Perdidos</div><div class="kpi-value">'.$stats['perdidos'].'</div><div class="kpi-subtext">Não converteram</div></div><div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div></div>
</div>';

        // Filters
        $filtros = ['todos'=>'Todos','abandonado'=>'Abandonados','em_atendimento'=>'Em Atendimento','recuperado'=>'Recuperados','perdido'=>'Perdidos'];
        echo '<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">';
        foreach ($filtros as $k => $v) {
            $active = ($filtro === $k) ? 'background:#18253D;color:#fff;border-color:#18253D;' : '';
            echo '<a href="?status='.$k.'" style="padding:6px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;font-weight:500;color:#374151;text-decoration:none;'.$active.'">'.$v.'</a>';
        }
        echo '</div>';

        // Table
        if (empty($registros)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><i class="bi bi-cart-check" style="font-size:40px;color:#94A3B8;"></i><p style="color:#94A3B8;margin-top:12px;">Nenhum carrinho abandonado encontrado.</p></div></div>';
        } else {
            echo '<div class="section-card"><div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;">
<thead><tr style="background:#FAFBFC;">
<th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Cliente</th>
<th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Itens</th>
<th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Valor</th>
<th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Abandonou em</th>
<th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Status</th>
<th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#94A3B8;font-weight:700;">Ações</th>
</tr></thead><tbody>';

            $statusLabels = ['abandonado'=>['Abandonado','#FFE4E6','#9F1239'],'em_atendimento'=>['Em Atendimento','#FEF3C7','#92400E'],'recuperado'=>['Recuperado','#D1FAE5','#065F46'],'perdido'=>['Perdido','#F1F5F9','#475569'],'nao_retornou'=>['Não Retornou','#F1F5F9','#475569']];

            foreach ($registros as $r) {
                $st = $r['status'] ?? 'abandonado';
                $badge = $statusLabels[$st] ?? $statusLabels['abandonado'];
                $nome = htmlspecialchars($r['cliente_nome'] ?? 'Cliente #'.$r['usuario_id']);
                $email = htmlspecialchars($r['cliente_email'] ?? '');
                $tel = htmlspecialchars($r['cliente_telefone'] ?? '');
                $valor = 'US$ ' . number_format((float)($r['valor_carrinho'] ?? 0), 2, '.', ',');
                $data = !empty($r['detectado_em']) ? date('d/m H:i', strtotime($r['detectado_em'])) : '-';

                echo '<tr style="border-bottom:1px solid #F1F5F9;">
<td style="padding:12px 14px;"><strong>'.$nome.'</strong><br><small style="color:#94A3B8;">'.$email.'</small>'.($tel ? '<br><small style="color:#64748B;">'.$tel.'</small>' : '').'</td>
<td style="padding:12px 14px;text-align:center;">'.(int)$r['itens_carrinho'].'</td>
<td style="padding:12px 14px;text-align:center;font-weight:600;color:#18253D;">'.$valor.'</td>
<td style="padding:12px 14px;text-align:center;color:#64748B;">'.$data.'</td>
<td style="padding:12px 14px;text-align:center;"><span style="padding:3px 9px;border-radius:999px;font-size:11px;font-weight:500;background:'.$badge[1].';color:'.$badge[2].';">'.$badge[0].'</span></td>
<td style="padding:12px 14px;text-align:center;">
<select onchange="atualizarStatusCarrinho('.(int)$r['id'].', this.value)" style="padding:4px 8px;border:1px solid #E2E8F0;border-radius:6px;font-size:11px;">
<option value="">Alterar...</option>
<option value="em_atendimento">Em Atendimento</option>
<option value="recuperado">Recuperado</option>
<option value="perdido">Perdido</option>
<option value="nao_retornou">Não Retornou</option>
</select>
</td></tr>';
            }
            echo '</tbody></table></div></div>';
        }

        echo '</div></main></div></div>';
        renderAdminScripts();
        echo '<script>
async function atualizarStatusCarrinho(id, status){
    if(!status) return;
    var pedidoId = 0;
    if(status === "recuperado"){
        pedidoId = parseInt(prompt("ID do pedido recuperado (deixe 0 se não souber):") || "0");
    }
    var r = await fetch("/admin/cart-recovery/status", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({id:id, status:status, pedido_id:pedidoId})});
    var d = await r.json();
    if(d.success) location.reload();
    else alert(d.error || "Erro");
}
</script></body></html>';
    }
}
