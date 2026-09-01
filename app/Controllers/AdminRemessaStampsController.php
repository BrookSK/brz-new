<?php
namespace App\Controllers;

use Config\Database;
use App\Models\PedidoEcommerce;
use App\Services\AuthService;
use App\Services\StampsService;

class AdminRemessaStampsController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ensureStampsEtiquetasTable(): void {
        try {
            if ($this->tableExists('stamps_etiquetas')) {
                return;
            }
            $sql = "CREATE TABLE IF NOT EXISTS stamps_etiquetas (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  pedido_id INT NOT NULL,\n"
                . "  stamps_label_id VARCHAR(80) NULL,\n"
                . "  tracking_number VARCHAR(120) NULL,\n"
                . "  carrier VARCHAR(40) NULL,\n"
                . "  service_type VARCHAR(80) NULL,\n"
                . "  packaging_type VARCHAR(80) NULL,\n"
                . "  label_url TEXT NULL,\n"
                . "  status VARCHAR(30) DEFAULT 'gerada',\n"
                . "  last_request_json LONGTEXT NULL,\n"
                . "  last_response_json LONGTEXT NULL,\n"
                . "  last_http_code INT NULL,\n"
                . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
                . "  updated_at TIMESTAMP NULL DEFAULT NULL,\n"
                . "  UNIQUE KEY uniq_stamps_etiquetas_pedido_id (pedido_id),\n"
                . "  KEY idx_stamps_etiquetas_tracking_number (tracking_number)\n"
                . ")";
            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    private function requireAdmin(): void {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
    }

    private function getPedidosExteriorPagosSemEtiqueta(): array {
        if (!$this->tableExists('pedidos')) {
            return [];
        }

        $colsPedidos = [];
        try {
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        $statusWhere = "(LOWER(COALESCE(p.status,'')) IN ('pago','paid','approved','aprovado','confirmado','confirmed'))";
        if (is_array($colsPedidos) && in_array('payment_status', $colsPedidos, true)) {
            $statusWhere .= " OR (UPPER(COALESCE(p.payment_status,'')) IN ('APPROVED','CONFIRMED','RECEIVED','PAID','SUCCEEDED','SUCCESS'))";
        }
        if (is_array($colsPedidos) && in_array('status_pagamento', $colsPedidos, true)) {
            $statusWhere .= " OR (UPPER(COALESCE(p.status_pagamento,'')) IN ('APPROVED','CONFIRMED','RECEIVED','PAID','SUCCEEDED','SUCCESS','PAGO','APROVADO'))";
        }

        $joinEndereco = '';
        $wherePais = '1=1';
        if (is_array($colsPedidos) && in_array('endereco_entrega_id', $colsPedidos, true) && $this->tableExists('enderecos')) {
            try {
                $colsEnd = [];
                try {
                    $stColsE = $this->connection->query('DESCRIBE enderecos');
                    $colsEnd = $stColsE ? ($stColsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsEnd = [];
                }
                if (is_array($colsEnd) && in_array('pais', $colsEnd, true)) {
                    $joinEndereco = ' LEFT JOIN enderecos e ON e.id = p.endereco_entrega_id ';
                    $wherePais = "UPPER(COALESCE(e.pais,'BR')) <> 'BR'";
                }
            } catch (\Exception $e) {
            }
        }

        $totalExpr = (is_array($colsPedidos) && in_array('total', $colsPedidos, true)) ? 'p.total' : (in_array('valor_total', $colsPedidos, true) ? 'p.valor_total' : '0');

        $sql = "
            SELECT
                p.id AS pedido_id,
                u.nome as cliente_nome,
                p.usuario_id,
                p.created_at,
                {$totalExpr} as valor_total,
                " . ($joinEndereco !== '' ? "UPPER(COALESCE(e.pais,'')) AS pais" : "'' AS pais") . "
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            {$joinEndereco}
            LEFT JOIN stamps_etiquetas se ON se.pedido_id = p.id
            WHERE ({$statusWhere})
              AND {$wherePais}
              AND se.id IS NULL
            ORDER BY p.created_at ASC
        ";

        try {
            $st = $this->connection->prepare($sql);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                $r['pedido_id'] = (int) ($r['pedido_id'] ?? 0);
                $r['id'] = (int) ($r['pedido_id'] ?? 0);
            }
            unset($r);
            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getEtiquetasGeradas(): array {
        if (!$this->tableExists('stamps_etiquetas')) {
            return [];
        }
        try {
            $st = $this->connection->prepare("
                SELECT se.*, p.usuario_id, u.nome as cliente_nome
                FROM stamps_etiquetas se
                LEFT JOIN pedidos p ON p.id = se.pedido_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                ORDER BY se.created_at DESC
                LIMIT 100
            ");
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index($request) {
        $this->requireAdmin();

        $svc = new StampsService();
        $enabled = false;
        try {
            $enabled = $svc->isEnabled();
        } catch (\Exception $e) {
            $enabled = false;
        }

        $pedidos = $this->getPedidosExteriorPagosSemEtiqueta();
        $etiquetas = $this->getEtiquetasGeradas();

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . __('admin.stamps.page_title', 'Remessa Stamps (UPS)') . ' - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';
        renderAdminSidebar('remessa-stamps');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="page-title">' . __('admin.stamps.heading', 'Remessa Stamps (UPS)') . '</h1>
                <div>
                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="fas fa-sync"></i> ' . __('common.refresh', 'Atualizar') . '</button>
                </div>
            </div>';

        if (!$enabled) {
            echo '<div class="alert alert-warning">' . __('admin.stamps.disabled_warning', 'Stamps está desabilitado ou não configurado. Vá em') . ' <strong>/admin/configuracoes</strong> &gt; <strong>' . __('admin.stamps.delivery_menu', 'Entrega') . '</strong> ' . __('admin.stamps.enable_configure', 'e ative/configure.') . '</div>';
        }

        echo '<div class="card mb-4">
            <div class="card-header"><strong>' . __('admin.stamps.orders_ready_label', 'Pedidos pagos (exterior) - prontos para etiqueta') . '</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>' . __('admin.stamps.th_order', 'Pedido') . '</th><th>' . __('common.customer', 'Cliente') . '</th><th>' . __('admin.stamps.th_country', 'País') . '</th><th>' . __('admin.stamps.th_date', 'Data') . '</th><th>' . __('common.actions', 'Ação') . '</th></tr></thead>
                        <tbody>';

        if (empty($pedidos)) {
            echo '<tr><td colspan="5" class="text-center text-muted">' . __('admin.stamps.no_orders_waiting', 'Nenhum pedido exterior aguardando etiqueta.') . '</td></tr>';
        } else {
            foreach ($pedidos as $p) {
                $pid = (int) ($p['pedido_id'] ?? 0);
                echo '<tr>'
                    . '<td>#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($p['cliente_nome'] ?? '-')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($p['pais'] ?? '')) . '</td>'
                    . '<td>' . (!empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string) $p['created_at'])) : '-') . '</td>'
                    . '<td>'
                    . '<button class="btn btn-sm btn-primary" onclick="gerarEtiqueta(' . $pid . ')"><i class="fas fa-tag"></i> ' . __('admin.stamps.generate_label', 'Gerar etiqueta') . '</button>'
                    . ' <a class="btn btn-sm btn-outline-secondary" href="/admin/pedidos/detalhes/' . $pid . '" target="_blank"><i class="fas fa-eye"></i> ' . __('admin.stamps.order', 'Pedido') . '</a>'
                    . '</td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table></div></div></div>';

        echo '<div class="card">
            <div class="card-header"><strong>' . __('admin.stamps.generated_labels', 'Etiquetas geradas') . '</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>' . __('admin.stamps.th_order', 'Pedido') . '</th><th>' . __('common.customer', 'Cliente') . '</th><th>' . __('admin.stamps.th_tracking', 'Tracking') . '</th><th>' . __('admin.stamps.th_carrier', 'Carrier') . '</th><th>' . __('admin.stamps.th_label', 'Label') . '</th><th>' . __('admin.stamps.th_date', 'Data') . '</th></tr></thead>
                        <tbody>';

        if (empty($etiquetas)) {
            echo '<tr><td colspan="6" class="text-center text-muted">' . __('admin.stamps.no_labels', 'Nenhuma etiqueta gerada.') . '</td></tr>';
        } else {
            foreach ($etiquetas as $e) {
                $pid = (int) ($e['pedido_id'] ?? 0);
                $trk = (string) ($e['tracking_number'] ?? '');
                $url = (string) ($e['label_url'] ?? '');
                echo '<tr>'
                    . '<td><a href="/admin/pedidos/detalhes/' . $pid . '" target="_blank">#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</a></td>'
                    . '<td>' . htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) . '</td>'
                    . '<td>' . htmlspecialchars($trk) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($e['carrier'] ?? '')) . '</td>'
                    . '<td>' . ($url !== '' ? ('<a href="' . htmlspecialchars($url) . '" target="_blank">' . __('admin.stamps.open', 'Abrir') . '</a>') : '-') . '</td>'
                    . '<td>' . (!empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-') . '</td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table></div></div></div>';

        echo '</main></div></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function gerarEtiqueta(pedidoId) {
    if (!confirm("' . __('admin.stamps.confirm_generate', 'Gerar etiqueta Stamps para o pedido') . ' #" + pedidoId + "?")) return;
    fetch("/admin/remessa-stamps/gerar-etiqueta", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({pedido_id: pedidoId})
    })
    .then(r => r.json().catch(() => ({})).then(data => ({ok: r.ok, data})))
    .then(({ok, data}) => {
        if (ok && data.success) {
            location.reload();
            return;
        }
        alert("' . __('common.error', 'Erro') . ': " + (data.message || data.error || JSON.stringify(data)));
    })
    .catch(err => alert("' . __('common.error', 'Erro') . ': " + err.message));
}
</script>
</body>
</html>';
        exit;
    }

    private function getDestinoFromPedido(array $pedido): array {
        $pais = '';
        $end = $pedido['endereco'] ?? null;
        if (is_array($end)) {
            $pais = (string) ($end['pais'] ?? ($end['country'] ?? ($end['country_code'] ?? '')));
        }

        if ($pais === '') {
            foreach (['pais_entrega', 'shipping_country', 'shipping_country_code', 'country_entrega', 'pais_destino', 'pais', 'country'] as $k) {
                if (isset($pedido[$k]) && (string) $pedido[$k] !== '') {
                    $pais = (string) $pedido[$k];
                    break;
                }
            }
        }

        $pais = strtoupper(trim($pais));
        if ($pais === '') {
            $pais = 'BR';
        }

        $endOut = [];
        if (is_array($end)) {
            $endOut = $end;
        } else {
            $endOut = [
                'endereco' => (string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? '')),
                'logradouro' => (string) ($pedido['endereco_entrega'] ?? ($pedido['logradouro_entrega'] ?? '')),
                'numero' => (string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? '')),
                'complemento' => (string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? '')),
                'bairro' => (string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? '')),
                'cidade' => (string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? '')),
                'estado' => (string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? '')),
                'cep' => (string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? '')),
                'pais' => $pais,
            ];
        }

        return [
            'pais' => $pais,
            'nome' => (string) ($pedido['cliente_nome'] ?? ''),
            'email' => (string) ($pedido['cliente_email'] ?? ''),
            'telefone' => (string) ($pedido['cliente_telefone'] ?? ''),
            'endereco' => $endOut,
        ];
    }

    private function buildLabelPayload(StampsService $svc, array $pedido): array {
        $fromJson = trim((string) $this->getConfigEntregaValue('stamps_from_address_json', ''));
        $from = $fromJson !== '' ? json_decode($fromJson, true) : null;
        if (!is_array($from)) {
            throw new \Exception(__('admin.stamps.err_from_address', 'Stamps: configure stamps_from_address_json (JSON) em /admin/configuracoes > Entrega'));
        }

        $serviceType = (string) $this->getConfigEntregaValue('stamps_service_type', '');
        if (trim($serviceType) === '') {
            throw new \Exception(__('admin.stamps.err_service_type', 'Stamps: configure stamps_service_type em /admin/configuracoes > Entrega'));
        }

        $packagingType = (string) $this->getConfigEntregaValue('stamps_packaging_type', 'package');
        if (trim($packagingType) === '') {
            $packagingType = 'package';
        }

        $dest = $this->getDestinoFromPedido($pedido);
        $end = (array) ($dest['endereco'] ?? []);

        $addr1 = (string) ($end['endereco'] ?? ($end['logradouro'] ?? ($end['address_line1'] ?? '')));
        $num = trim((string) ($end['numero'] ?? ''));
        if ($num !== '' && $addr1 !== '') {
            $addr1 = trim($addr1) . ', ' . $num;
        }

        $to = [
            'name' => trim((string) ($dest['nome'] ?? '')),
            'address_line1' => trim($addr1),
            'address_line2' => trim((string) ($end['complemento'] ?? ($end['bairro'] ?? ($end['address_line2'] ?? '')))),
            'city' => trim((string) ($end['cidade'] ?? ($end['city'] ?? ''))),
            'state_province' => trim((string) ($end['estado'] ?? ($end['state'] ?? ($end['state_province'] ?? '')))),
            'postal_code' => preg_replace('/\s+/', '', (string) ($end['cep'] ?? ($end['postal_code'] ?? ''))),
            'country_code' => strtoupper(trim((string) ($dest['pais'] ?? 'BR'))),
            'email' => (string) ($dest['email'] ?? ''),
            'phone' => (string) ($dest['telefone'] ?? ''),
        ];

        if (trim((string) ($to['name'] ?? '')) === '') {
            $to['name'] = 'Cliente';
        }
        if (trim((string) ($to['address_line1'] ?? '')) === '') {
            throw new \Exception(__('admin.stamps.err_incomplete_address', 'Stamps: endereço de entrega incompleto no pedido (address_line1).'));
        }
        if (trim((string) ($to['city'] ?? '')) === '') {
            $to['city'] = 'City';
        }
        if (trim((string) ($to['postal_code'] ?? '')) === '') {
            $to['postal_code'] = '00000';
        }

        $pesoKg = 0.0;
        $itens = $pedido['itens'] ?? ($pedido['items'] ?? []);
        if (is_array($itens)) {
            foreach ($itens as $it) {
                if (!is_array($it)) continue;
                $qtd = (int) ($it['quantidade'] ?? ($it['qty'] ?? 1));
                if ($qtd <= 0) $qtd = 1;
                $peso = null;
                foreach (['peso', 'weight', 'peso_kg'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) {
                        $peso = (float) $it[$c];
                        break;
                    }
                }
                if ($peso !== null && $peso > 0) {
                    $pesoKg += ($peso * $qtd);
                }
            }
        }
        if ($pesoKg <= 0 && is_numeric($pedido['peso_total'] ?? null)) {
            $pesoKg = (float) $pedido['peso_total'];
        }
        if ($pesoKg <= 0) {
            $pesoKg = 0.2;
        }

        $weightOz = max(1, (int) round($pesoKg * 35.274));

        return [
            'from_address' => $from,
            'to_address' => $to,
            'service_type' => $serviceType,
            'package' => [
                'packaging_type' => $packagingType,
                'weight' => $weightOz,
                'weight_unit' => 'ounce',
            ],
            'ship_date' => date('Y-m-d'),
            'label_options' => [
                'label_size' => '4x6',
                'label_format' => 'png',
                'label_output_type' => 'url',
            ],
            'is_test_label' => $svc->isStaging(),
        ];
    }

    private function getConfigEntregaValue(string $key, $default = '') {
        try {
            if (!$this->tableExists('configuracoes_sistema')) {
                return $default;
            }

            $cols = [];
            try {
                $st = $this->connection->query('DESCRIBE configuracoes_sistema');
                $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $k = (string) $key;

            if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $fullKey = 'entrega_' . $k;
                $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $stmt->execute([$fullKey]);
                $v = $stmt->fetchColumn();
                if ($v === false || $v === null) {
                    return $default;
                }
                return $v;
            }

            $colName = (strpos($k, 'stamps_') === 0) ? $k : ('stamps_' . $k);
            if (in_array($colName, $cols, true)) {
                $stmt = $this->connection->query('SELECT ' . $colName . ' FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $v = $stmt->fetchColumn();
                if ($v === false || $v === null) {
                    return $default;
                }
                return $v;
            }

            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    public function gerarEtiqueta($request) {
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = $request->getParam('pedido_id');
            if ($raw === null) {
                $body = file_get_contents('php://input');
                $json = $body ? json_decode($body, true) : null;
                if (is_array($json) && isset($json['pedido_id'])) {
                    $raw = $json['pedido_id'];
                }
            }
            $pedidoId = (int) $raw;
            if ($pedidoId <= 0) {
                echo json_encode(['success' => false, 'message' => __('admin.stamps.invalid_order', 'Pedido inválido')]);
                return;
            }

            $this->ensureStampsEtiquetasTable();
            if (!$this->tableExists('stamps_etiquetas')) {
                echo json_encode(['success' => false, 'message' => __('admin.stamps.table_missing', 'Tabela stamps_etiquetas não encontrada. Rode a migration database/migrations/070_create_stamps_etiquetas.sql')]);
                return;
            }

            $stCheck = $this->connection->prepare('SELECT id FROM stamps_etiquetas WHERE pedido_id = ? LIMIT 1');
            $stCheck->execute([$pedidoId]);
            $exists = (int) ($stCheck->fetchColumn() ?: 0);
            if ($exists > 0) {
                echo json_encode(['success' => false, 'message' => __('admin.stamps.label_exists', 'Já existe etiqueta Stamps para este pedido')]);
                return;
            }

            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($pedidoId);
            if (!is_array($pedido) || empty($pedido['id'])) {
                echo json_encode(['success' => false, 'message' => __('admin.stamps.order_not_found', 'Pedido não encontrado')]);
                return;
            }

            $svc = new StampsService();
            $payload = $this->buildLabelPayload($svc, $pedido);

            $resp = null;
            $httpCode = null;
            $errorMsg = null;
            try {
                $resp = $svc->createLabel($payload);
                $httpCode = $svc->getLastHttpCode();
            } catch (\Exception $e) {
                $httpCode = $svc->getLastHttpCode();
                $errorMsg = $e->getMessage();
            }

            if ($errorMsg !== null) {
                echo json_encode(['success' => false, 'message' => $errorMsg, 'http_code' => $httpCode]);
                return;
            }

            $labelId = is_array($resp) ? (string) ($resp['label_id'] ?? '') : '';
            $tracking = is_array($resp) ? (string) ($resp['tracking_number'] ?? '') : '';
            $carrier = is_array($resp) ? (string) ($resp['carrier'] ?? '') : '';
            $serviceType = is_array($resp) ? (string) ($resp['service_type'] ?? '') : '';
            $packagingType = is_array($resp) ? (string) ($resp['packaging_type'] ?? '') : '';

            $labelUrl = '';
            if (is_array($resp) && isset($resp['labels'][0]['href'])) {
                $labelUrl = (string) $resp['labels'][0]['href'];
            }

            $stIns = $this->connection->prepare('INSERT INTO stamps_etiquetas (pedido_id, stamps_label_id, tracking_number, carrier, service_type, packaging_type, label_url, status, last_request_json, last_response_json, last_http_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stIns->execute([
                $pedidoId,
                $labelId !== '' ? $labelId : null,
                $tracking !== '' ? $tracking : null,
                $carrier !== '' ? $carrier : null,
                $serviceType !== '' ? $serviceType : null,
                $packagingType !== '' ? $packagingType : null,
                $labelUrl !== '' ? $labelUrl : null,
                'gerada',
                json_encode($payload),
                json_encode($resp),
                $httpCode,
            ]);

            try {
                $obs = 'Etiqueta Stamps gerada';
                if ($tracking !== '') {
                    $obs .= ' - Rastreio: ' . $tracking;
                }
                $pedidoModel->atualizarStatus((int) $pedidoId, 'enviado', $obs, $_SESSION['usuario_id'] ?? null);
            } catch (\Exception $e) {
            }

            try {
                $notif = new \App\Services\NotificationService();
                $notif->notificarEventoPedido('stamps_label_created', (int) $pedidoId, [
                    'tracking_number' => $tracking,
                    'label_url' => $labelUrl,
                    'label_id' => $labelId,
                    'carrier' => $carrier,
                    'service_type' => $serviceType,
                ]);
            } catch (\Exception $e) {
            }

            echo json_encode(['success' => true, 'label_id' => $labelId, 'tracking_number' => $tracking, 'label_url' => $labelUrl]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }
    }
}
