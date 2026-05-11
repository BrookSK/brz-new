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

    /**
     * Converte um envio de redirecionamento para o formato de "pedido" esperado pela view de conferência
     */
    private function getEnvioRedirecionamentoComoPedido(int $envioId): ?array {
        try {
            $st = $this->connection->prepare("
                SELECT e.*, r.nome AS redirecionador_nome, r.email AS redirecionador_email,
                    c.nome AS cliente_nome, c.cpf AS cliente_cpf, c.email AS cliente_email, c.telefone AS cliente_telefone
                FROM redirecionamento_envios e
                LEFT JOIN redirecionadores r ON r.id = e.redirecionador_id
                LEFT JOIN redirecionamento_clientes c ON c.id = e.cliente_id
                WHERE e.id = ? LIMIT 1
            ");
            $st->execute([$envioId]);
            $envio = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$envio) return null;

            // Buscar produtos
            $stP = $this->connection->prepare("SELECT * FROM redirecionamento_produtos_envio WHERE envio_id = ?");
            $stP->execute([$envioId]);
            $produtos = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Montar itens no formato esperado pela view
            $itens = [];
            $totalQtd = 0;
            foreach ($produtos as $p) {
                $qtd = (int)($p['quantidade'] ?? 1);
                $totalQtd += $qtd;
                $itens[] = [
                    'produto_id' => 0,
                    'nome_produto' => $p['descricao'] ?? 'Produto',
                    'produto_nome' => $p['descricao'] ?? 'Produto',
                    'quantidade' => $qtd,
                    'preco_unitario' => (float)($p['preco_usd'] ?? 0),
                    'valor_unitario' => (float)($p['preco_usd'] ?? 0),
                    'subtotal' => (float)($p['preco_usd'] ?? 0) * $qtd,
                    'ncm' => $p['ncm'] ?? '',
                    'peso_kg' => (float)($p['peso_kg'] ?? 0),
                    'imagem' => 'placeholder.jpg',
                ];
            }

            $totalProdutos = array_sum(array_column($itens, 'subtotal'));
            $valorCobrado = (float)($envio['valor_cobrado_usd'] ?? 0);

            return [
                'id' => 900000 + $envioId,
                'codigo_pedido' => 'REDIR-' . $envioId,
                'created_at' => $envio['created_at'] ?? date('Y-m-d H:i:s'),
                'status' => $envio['status'] ?? 'pago',
                'moeda' => 'USD',
                'currency' => 'USD',
                'total' => $valorCobrado,
                'valor_total' => $valorCobrado,
                'subtotal_produtos' => $totalProdutos,
                'subtotal' => $totalProdutos,
                'valor_frete' => (float)($envio['valor_frete_usd'] ?? 0),
                'frete' => (float)($envio['valor_frete_usd'] ?? 0),
                'taxa_servico' => 0,
                'servicos' => 0,
                'valor_impostos' => 0,
                'impostos' => 0,
                'imposto_local' => 0,
                'cliente_nome' => $envio['destinatario_nome'] ?? ($envio['cliente_nome'] ?? ''),
                'cliente_email' => $envio['destinatario_email'] ?? ($envio['cliente_email'] ?? ''),
                'cliente_telefone' => $envio['destinatario_telefone'] ?? ($envio['cliente_telefone'] ?? ''),
                'cliente_cpf_cnpj' => $envio['destinatario_cpf'] ?? ($envio['cliente_cpf'] ?? ''),
                'cliente_documento' => $envio['destinatario_cpf'] ?? ($envio['cliente_cpf'] ?? ''),
                'documento' => $envio['destinatario_cpf'] ?? ($envio['cliente_cpf'] ?? ''),
                'endereco' => [
                    'endereco' => $envio['dest_logradouro'] ?? '',
                    'logradouro' => $envio['dest_logradouro'] ?? '',
                    'numero' => $envio['dest_numero'] ?? '',
                    'complemento' => $envio['dest_complemento'] ?? '',
                    'bairro' => $envio['dest_bairro'] ?? '',
                    'cidade' => $envio['dest_cidade'] ?? '',
                    'estado' => $envio['dest_estado'] ?? '',
                    'cep' => $envio['dest_cep'] ?? '',
                    'pais' => 'BR',
                ],
                'endereco_entrega' => $envio['dest_logradouro'] ?? '',
                'numero' => $envio['dest_numero'] ?? '',
                'numero_entrega' => $envio['dest_numero'] ?? '',
                'complemento' => $envio['dest_complemento'] ?? '',
                'complemento_entrega' => $envio['dest_complemento'] ?? '',
                'bairro' => $envio['dest_bairro'] ?? '',
                'bairro_entrega' => $envio['dest_bairro'] ?? '',
                'cidade' => $envio['dest_cidade'] ?? '',
                'cidade_entrega' => $envio['dest_cidade'] ?? '',
                'estado' => $envio['dest_estado'] ?? '',
                'estado_entrega' => $envio['dest_estado'] ?? '',
                'cep' => $envio['dest_cep'] ?? '',
                'cep_entrega' => $envio['dest_cep'] ?? '',
                'peso_total' => (float)($envio['peso_kg'] ?? 0),
                'peso_informado' => (float)($envio['peso_kg'] ?? 0),
                'largura' => (float)($envio['largura_cm'] ?? 0),
                'altura' => (float)($envio['altura_cm'] ?? 0),
                'comprimento' => (float)($envio['comprimento_cm'] ?? 0),
                'largura_informada' => (float)($envio['largura_cm'] ?? 0),
                'altura_informada' => (float)($envio['altura_cm'] ?? 0),
                'comprimento_informada' => (float)($envio['comprimento_cm'] ?? 0),
                'items' => $itens,
                'itens' => $itens,
                'quantidade_itens' => $totalQtd,
                'forma_pagamento' => 'stripe',
                'payment_gateway' => 'stripe',
                'pagamento_status' => ($envio['status_pagamento'] ?? '') === 'pago' ? 'PAID' : 'PENDING',
                'payment_status' => ($envio['status_pagamento'] ?? '') === 'pago' ? 'PAID' : 'PENDING',
                'observacoes' => 'Envio de Redirecionamento #' . $envioId . ' — Redirecionador: ' . ($envio['redirecionador_nome'] ?? ''),
                '_is_redirecionamento' => true,
                '_envio_id' => $envioId,
                '_redirecionador_nome' => $envio['redirecionador_nome'] ?? '',
                '_redirecionador_email' => $envio['redirecionador_email'] ?? '',
                '_stripe_payment_intent' => $envio['stripe_payment_intent'] ?? '',
                'pagamentos' => [
                    [
                        'gateway' => 'stripe',
                        'componente' => 'Envio Redirecionamento',
                        'metodo' => 'card',
                        'moeda' => 'USD',
                        'valor' => $valorCobrado,
                        'status' => ($envio['status_pagamento'] ?? '') === 'pago' ? 'paid' : 'pending',
                        'gateway_status' => ($envio['status_pagamento'] ?? ''),
                        'payment_id' => $envio['stripe_payment_intent'] ?? '',
                    ],
                ],
            ];
        } catch (\Exception $e) {
            error_log('[CONFERENCIA] Erro ao buscar envio redirecionamento #' . $envioId . ': ' . $e->getMessage());
            return null;
        }
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
        try {
            $this->connection->exec("ALTER TABLE remessa_janela_pedidos ADD COLUMN recebido_confirmado TINYINT(1) NOT NULL DEFAULT 0");
        } catch (\Exception $e) {}
        try {
            $this->connection->exec("ALTER TABLE remessa_janela_pedidos ADD COLUMN recebido_confirmado_em DATETIME NULL");
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

        $colsEnd = [];
        try { $st = $this->connection->query('DESCRIBE enderecos'); $colsEnd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $cepEndCol = in_array('cep', $colsEnd, true) ? 'cep' : (in_array('zip_code', $colsEnd, true) ? 'zip_code' : null);

        $sql = "SELECT rjp.pedido_id, rjp.etiqueta_gerada, rjp.wexpress_shipping_id,
                    rjp.wexpress_tracking_number, rjp.courier_tracking_number, rjp.wexpress_status,
                    p.created_at, p.total, p.status,
                    " . ($hasMoeda ? 'p.moeda,' : "'' AS moeda,") . "
                    " . ($hasCurrency ? 'p.currency,' : "'' AS currency,") . "
                    u.nome AS cliente_nome, u.email AS cliente_email";

        if ($cepEndCol) {
            $sql .= ", (SELECT e.{$cepEndCol} FROM enderecos e WHERE e.usuario_id = p.usuario_id ORDER BY e.id DESC LIMIT 1) AS cep_entrega";
        } else {
            $sql .= ", NULL AS cep_entrega";
        }

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

                // Detectar envio de redirecionamento
                $isRedir = ($pid >= 900000);
                $clienteNome = (string)($p['cliente_nome'] ?? 'N/A');
                $pedidoLabel = '#' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT);

                if ($isRedir) {
                    $envioRedirId = $pid - 900000;
                    $pedidoLabel = 'REDIR-' . $envioRedirId;
                    // Buscar nome do destinatário, data e qtd
                    try {
                        $stR = $this->connection->prepare("SELECT e.destinatario_nome, e.dest_cep, e.created_at, c.nome AS cli_nome,
                            (SELECT SUM(p2.quantidade) FROM redirecionamento_produtos_envio p2 WHERE p2.envio_id = e.id) AS qtd_produtos
                            FROM redirecionamento_envios e LEFT JOIN redirecionamento_clientes c ON c.id = e.cliente_id WHERE e.id = ? LIMIT 1");
                        $stR->execute([$envioRedirId]);
                        $redir = $stR->fetch(\PDO::FETCH_ASSOC);
                        if ($redir) {
                            $clienteNome = ($redir['destinatario_nome'] ?: $redir['cli_nome']) ?: 'Redirecionamento';
                            if (empty($cep)) $cep = (string)($redir['dest_cep'] ?? '');
                            if ($dt === '-' && !empty($redir['created_at'])) $dt = date('d/m/Y H:i', strtotime((string)$redir['created_at']));
                            if ($qtd === '-' && $redir['qtd_produtos'] !== null) $qtd = (int)$redir['qtd_produtos'];
                        }
                    } catch (\Exception $e) {}
                }

                echo '<tr>
                    <td><strong>' . $pedidoLabel . '</strong>' . ($isRedir ? ' <span class="badge bg-info" style="font-size:.6rem">REDIR</span>' : '') . '</td>
                    <td>' . $dt . '</td>
                    <td>' . htmlspecialchars($clienteNome) . '</td>
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

        // Detectar se é envio de redirecionamento (pedido_id >= 900000)
        $isRedirecionamento = ($pid >= 900000);
        $envioRedirId = $isRedirecionamento ? ($pid - 900000) : 0;

        if ($isRedirecionamento) {
            $pedido = $this->getEnvioRedirecionamentoComoPedido($envioRedirId);
        } else {
            $pedido = $this->getPedidoCompleto($pid);
        }
        if (!$pedido) { header('Location: /admin/remessa-conferencia/janela/' . $jid); exit; }

        $usdToBrl = $this->getUsdToBrlRate();
        $moeda = strtoupper(trim((string)($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moeda === '') $moeda = 'USD';

        $et = (int)($rel['etiqueta_gerada'] ?? 0);
        $wxShipId = (string)($rel['wexpress_shipping_id'] ?? '');
        $wxStatus = (string)($rel['wexpress_status'] ?? '');
        $wxTrack = (string)($rel['wexpress_tracking_number'] ?? '');
        $wxCourier = (string)($rel['courier_tracking_number'] ?? '');
        $labelUrl = $wxShipId !== '' ? 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId) : '';
        $medicamentoFlag = (bool)($rel['medicamento'] ?? false);
        $recebidoConfirmado = (int)($rel['recebido_confirmado'] ?? 0);
        $recebidoEm = (string)($rel['recebido_confirmado_em'] ?? '');
        $docs = $this->getDocumentos($jid, $pid);

        $h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
        $fmtMoeda = function($v, $m) {
            if ($v === null) return '-';
            return $m === 'BRL'
                ? 'R$ ' . number_format((float)$v, 2, ',', '.')
                : 'US$ ' . number_format((float)$v, 2, ',', '.');
        };
        $fmtBrl = fn($v) => $v !== null ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '-';

        // Endereço
        $end = $pedido['endereco'] ?? null;
        $logr   = is_array($end) ? trim((string)($end['endereco'] ?? ($end['logradouro'] ?? ''))) : '';
        $num    = is_array($end) ? trim((string)($end['numero'] ?? '')) : '';
        $compl  = is_array($end) ? trim((string)($end['complemento'] ?? '')) : '';
        $bairro = is_array($end) ? trim((string)($end['bairro'] ?? '')) : '';
        $cidade = is_array($end) ? trim((string)($end['cidade'] ?? '')) : '';
        $estado = is_array($end) ? trim((string)($end['estado'] ?? '')) : '';
        $cep    = is_array($end) ? trim((string)($end['cep'] ?? ($end['zip_code'] ?? ''))) : '';
        $pais   = is_array($end) ? trim((string)($end['pais'] ?? ($end['country'] ?? 'BR'))) : 'BR';

        // Pagamentos split
        $pagamentos = $pedido['pagamentos'] ?? [];
        $totalBrl = 0.0;
        $produtosValor = 0.0; $taxaServico = 0.0; $impostoValor = 0.0; $impostoLocal = 0.0;
        $metodoPagamento = (string)($pedido['forma_pagamento'] ?? ($pedido['pagamento_metodo'] ?? ''));
        $dataPagamento = (string)($pedido['pago_em'] ?? ($pedido['pagamento_data'] ?? ($pedido['paid_at'] ?? '')));

        $appmaxPagamentos = [];
        $cambioRealPagamentos = [];

        foreach ($pagamentos as $pg) {
            $v = (float)($pg['valor'] ?? 0);
            $m = strtolower((string)($pg['moeda'] ?? 'BRL'));
            if ($m === 'brl') $totalBrl += $v;
            $gw = strtolower((string)($pg['gateway'] ?? ''));
            $comp = strtolower((string)($pg['componente'] ?? ''));
            if ($gw === 'appmax') $appmaxPagamentos[] = $pg;
            if ($gw === 'cambioreal') $cambioRealPagamentos[] = $pg;
            if (strpos($comp, 'produto') !== false || strpos($comp, 'product') !== false) $produtosValor += $v;
            if (strpos($comp, 'taxa') !== false || strpos($comp, 'servico') !== false || strpos($comp, 'service') !== false) $taxaServico += $v;
            if (strpos($comp, 'imposto') !== false || strpos($comp, 'tax') !== false) $impostoValor += $v;
            if (strpos($comp, 'local') !== false) $impostoLocal += $v;
            if ($metodoPagamento === '' && isset($pg['metodo'])) $metodoPagamento = (string)$pg['metodo'];
        }

        $appmaxValorTotal = array_sum(array_map(fn($pg) => (float)($pg['valor'] ?? 0), $appmaxPagamentos));
        $cambioRealValorTotal = array_sum(array_map(fn($pg) => (float)($pg['valor'] ?? 0), $cambioRealPagamentos));

        // Totais direto do banco na moeda do pedido
        $subtotal    = is_numeric($pedido['subtotal'] ?? null)     ? (float)$pedido['subtotal']     : null;
        $frete       = is_numeric($pedido['frete'] ?? null)        ? (float)$pedido['frete']        : null;
        $desconto    = is_numeric($pedido['desconto'] ?? null)     ? (float)$pedido['desconto']     : null;
        $impLocal    = is_numeric($pedido['imposto_local'] ?? null) ? (float)$pedido['imposto_local'] : null;
        $totalPedido = is_numeric($pedido['total'] ?? null)        ? (float)$pedido['total']        : null;

        // Status split em português
        $traduzirStatus = function(string $s): string {
            $map = [
                'approved'      => 'Aprovado',
                'aprovado'      => 'Aprovado',
                'pending'       => 'Pendente',
                'pendente'      => 'Pendente',
                'paid'          => 'Pago',
                'pago'          => 'Pago',
                'cancelled'     => 'Cancelado',
                'cancelado'     => 'Cancelado',
                'refunded'      => 'Estornado',
                'failed'        => 'Falhou',
                'falhou'        => 'Falhou',
                'LABEL_CREATED' => 'Etiqueta Gerada',
            ];
            return $map[$s] ?? $map[strtolower($s)] ?? $s;
        };

        // Verificar se existe doc medicamento
        $hasMedDoc = !empty($docs['medicamento']['file_path']);

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedido #' . $pid . ' - Conferência</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('remessa-conferencia');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Pedido #' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</h1>
        <div class="text-muted small">Janela #' . $jid . ' | Etiqueta: <span class="badge bg-' . ($et === 1 ? 'success' : 'warning') . '">' . ($et === 1 ? 'Gerada' : 'Pendente') . '</span></div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">';

        // Confirmar Recebimento
        if ($recebidoConfirmado === 1) {
            $dtRec = $recebidoEm !== '' ? date('d/m/Y H:i', strtotime($recebidoEm)) : '';
            echo '<span class="badge bg-success fs-6 px-3 py-2"><i class="fas fa-check-circle me-1"></i>Recebimento Confirmado' . ($dtRec !== '' ? ' em ' . $dtRec : '') . '</span>';
        } else {
            echo '<button class="btn btn-success" id="btnConfirmarRecebimento" onclick="confirmarRecebimento()"
                title="Confirmar que o pedido chegou no Brasil. Muda o status para Aguardando Liberação Aduaneira.">
                Confirmar Recebimento no Brasil
            </button>
            <small class="text-muted d-block" style="font-size:0.7rem;max-width:200px">Pedido chegou no BR â†’ muda para Aguardando Liberação Aduaneira</small>';
        }

        echo '<a class="btn btn-outline-secondary" href="/admin/remessa-conferencia/janela/' . $jid . '"><i class="fas fa-arrow-left"></i> Voltar</a>';
        if ($labelUrl !== '') echo '<a class="btn btn-outline-primary" href="' . $h($labelUrl) . '" target="_blank"><i class="fas fa-download"></i> Baixar etiqueta</a>';
        echo '<a class="btn btn-outline-info" href="/admin/remessa-conferencia/janela/' . $jid . '/pedido/' . $pid . '/comprovante/appmax" target="_blank"><i class="fas fa-file-invoice me-1"></i>Comprovante AppMax</a>';
        echo '<a class="btn btn-outline-info" href="/admin/remessa-conferencia/janela/' . $jid . '/pedido/' . $pid . '/comprovante/cambioreal" target="_blank"><i class="fas fa-file-invoice me-1"></i>Comprovante Câmbio Real</a>';
        echo '<a class="btn btn-outline-info" href="/admin/remessa-conferencia/janela/' . $jid . '/pedido/' . $pid . '/comprovante/cambioreal_taxas" target="_blank"><i class="fas fa-file-invoice me-1"></i>Comprovante CR Taxas</a>';
        echo '<a class="btn btn-outline-info" href="/admin/remessa-conferencia/janela/' . $jid . '/pedido/' . $pid . '/comprovante/stripe" target="_blank"><i class="fas fa-credit-card me-1"></i>Comprovante Stripe</a>';
        echo '<a class="btn btn-outline-dark" href="/admin/remessa-conferencia/janela/' . $jid . '/pedido/' . $pid . '/invoice" target="_blank"><i class="fas fa-file-alt me-1"></i>Invoice</a>';
        if ($hasMedDoc) {
            echo '<a class="btn btn-outline-warning" href="' . $h($docs['medicamento']['file_path']) . '" target="_blank"><i class="fas fa-pills me-1"></i>Doc. Medicamento</a>';
        }
        echo '</div></div>';

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

        // Itens â€” sem SKU e NCM
        $itens = $pedido['itens'] ?? [];
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-box me-1"></i>Itens do Pedido</strong></div><div class="card-body">
<div class="table-responsive"><table class="table table-sm align-middle">
<thead><tr><th>#</th><th>Imagem</th><th>Declaração</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th></tr></thead><tbody>';
        if (!$itens) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum item.</td></tr>';
        } else {
            $idx = 1;
            foreach ($itens as $it) {
                $pu = null;
                foreach (['preco_unitario','valor_unitario','preco','price'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) { $pu = (float)$it[$c]; break; }
                }
                $qtdIt = (int)($it['quantidade'] ?? 0);
                $totIt = $pu !== null ? $pu * $qtdIt : null;
                $foto = trim((string)($it['foto_produto'] ?? ''));
                if ($foto !== '' && !str_starts_with($foto, 'http')) $foto = '/' . ltrim($foto, '/');
                echo '<tr>
                    <td>' . $idx . '</td>
                    <td>' . ($foto !== '' ? '<img src="' . $h($foto) . '" style="width:48px;height:48px;object-fit:cover;border-radius:6px">' : '<span class="text-muted">-</span>') . '</td>
                    <td>' . $h($it['produto_nome'] ?? $it['nome_produto'] ?? '') . '</td>
                    <td>' . $qtdIt . '</td>
                    <td>' . $fmtMoeda($pu, 'USD') . '</td>
                    <td>' . $fmtMoeda($totIt, 'USD') . '</td>
                </tr>';
                $idx++;
            }
        }
        echo '</tbody></table></div></div></div>';

        // Pagamento â€” bloco principal + dois cards AppMax / Câmbio Real
        echo '<div class="row"><div class="col-md-6">
<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-credit-card me-1"></i>Pagamento</strong></div><div class="card-body">
<table class="table table-sm mb-0">';
        $totalUsd = 0.0;
        foreach ($pagamentos as $pg) {
            $pgM = strtoupper(trim((string)($pg['moeda'] ?? '')));
            if ($pgM === 'USD') $totalUsd += (float)($pg['valor'] ?? 0);
        }
        if ($totalUsd > 0 && $totalBrl <= 0) {
            echo '<tr><td class="text-muted">Valor pago (USD)</td><td>' . $fmtMoeda($totalUsd, 'USD') . '</td></tr>';
        } else {
            echo '<tr><td class="text-muted">Valor pago (BRL)</td><td>' . $fmtBrl($totalBrl > 0 ? $totalBrl : null) . '</td></tr>';
        }
        echo '<tr><td class="text-muted">Data de crédito</td><td>' . ($dataPagamento !== '' ? date('d/m/Y H:i', strtotime($dataPagamento)) : '-') . '</td></tr>';
        echo '<tr><td class="text-muted">Método</td><td>' . $h($metodoPagamento) . '</td></tr>';
        echo '<tr><td class="text-muted">Produtos</td><td>' . ($produtosValor > 0 ? $fmtMoeda($produtosValor, $moeda) : ($subtotal !== null ? $fmtMoeda($subtotal, $moeda) : '-')) . '</td></tr>';
        echo '<tr><td class="text-muted">Taxa de serviço</td><td>' . $fmtMoeda($taxaServico > 0 ? $taxaServico : null, $moeda) . '</td></tr>';
        echo '<tr><td class="text-muted">Imposto</td><td>' . $fmtMoeda($impostoValor > 0 ? $impostoValor : null, $moeda) . '</td></tr>';
        echo '<tr><td class="text-muted">Imposto local</td><td>' . $fmtMoeda($impostoLocal > 0 ? $impostoLocal : null, $moeda) . '</td></tr>';
        echo '</table></div></div></div>';

        // Cards de pagamento lado a lado
        echo '<div class="row mb-3">';

        // Determinar se é pedido antigo (AppMax) ou novo (Câmbio Real Taxas)
        $cambioRealTaxasPagamentos = array_filter($pagamentos, fn($pg) => strtolower((string)($pg['gateway'] ?? '')) === 'cambioreal_taxas');
        $usaAppmax = !empty($appmaxPagamentos);

        if ($usaAppmax) {
            // Pedido antigo com AppMax
            echo '<div class="col-md-6"><div class="card h-100"><div class="card-header bg-primary text-white"><strong><i class="fas fa-credit-card me-1"></i>AppMax</strong></div><div class="card-body">';
            echo '<div class="mb-2"><strong>Valor AppMax (BRL):</strong> ' . $fmtBrl($appmaxValorTotal > 0 ? $appmaxValorTotal : null) . '</div>';
            foreach ($appmaxPagamentos as $pg) {
                echo '<hr class="my-2"><table class="table table-sm mb-0">';
                $fields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id','invoice_url','bank_slip_url','digitable_line','pix_payload'];
                foreach ($fields as $f) {
                    if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                    $val = (string)$pg[$f];
                    if ($f === 'pix_payload' && strlen($val) > 50) $val = substr($val, 0, 50) . '...';
                    if ($f === 'invoice_url' || $f === 'bank_slip_url') {
                        $val = '<a href="' . $h($val) . '" target="_blank">' . $h($val) . '</a>';
                    } else {
                        $val = $h($val);
                    }
                    echo '<tr><td class="text-muted" style="width:140px">' . $h($f) . '</td><td>' . $val . '</td></tr>';
                }
                echo '</table>';
            }
            echo '</div></div></div>';
        } else {
            // Pedido novo: Câmbio Real Taxas
            $crTaxasValorTotal = array_sum(array_map(fn($pg) => (float)($pg['valor'] ?? 0), $cambioRealTaxasPagamentos));
            echo '<div class="col-md-6"><div class="card h-100"><div class="card-header bg-warning text-dark"><strong><i class="fas fa-receipt me-1"></i>Câmbio Real Taxas</strong></div><div class="card-body">';
            if (empty($cambioRealTaxasPagamentos)) {
                echo '<div class="text-muted">Nenhum pagamento Câmbio Real Taxas encontrado.</div>';
            } else {
                echo '<div class="mb-2"><strong>Valor CR Taxas (BRL):</strong> ' . $fmtBrl($crTaxasValorTotal > 0 ? $crTaxasValorTotal : null) . '</div>';
                foreach ($cambioRealTaxasPagamentos as $pg) {
                    echo '<hr class="my-2"><table class="table table-sm mb-0">';
                    $fields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id','invoice_url','bank_slip_url','digitable_line','pix_payload'];
                    foreach ($fields as $f) {
                        if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                        $val = (string)$pg[$f];
                        if ($f === 'pix_payload' && strlen($val) > 50) $val = substr($val, 0, 50) . '...';
                        if ($f === 'invoice_url' || $f === 'bank_slip_url') {
                            $val = '<a href="' . $h($val) . '" target="_blank">' . $h($val) . '</a>';
                        } else {
                            $val = $h($val);
                        }
                        echo '<tr><td class="text-muted" style="width:140px">' . $h($f) . '</td><td>' . $val . '</td></tr>';
                    }
                    echo '</table>';
                }
            }
            echo '</div></div></div>';
        }

        // Câmbio Real card
        echo '<div class="col-md-6"><div class="card h-100"><div class="card-header bg-success text-white"><strong><i class="fas fa-exchange-alt me-1"></i>Câmbio Real</strong></div><div class="card-body">';
        if (empty($cambioRealPagamentos)) {
            echo '<div class="text-muted">Nenhum pagamento Câmbio Real encontrado.</div>';
        } else {
            echo '<div class="mb-2"><strong>Valor Câmbio Real (BRL):</strong> ' . $fmtBrl($cambioRealValorTotal > 0 ? $cambioRealValorTotal : null) . '</div>';
            foreach ($cambioRealPagamentos as $pg) {
                echo '<hr class="my-2"><table class="table table-sm mb-0">';
                $fields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id','invoice_url','bank_slip_url','digitable_line','pix_payload'];
                foreach ($fields as $f) {
                    if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                    $val = (string)$pg[$f];
                    if ($f === 'pix_payload' && strlen($val) > 50) $val = substr($val, 0, 50) . 'â€¦';
                    if ($f === 'invoice_url' || $f === 'bank_slip_url') {
                        $val = '<a href="' . $h($val) . '" target="_blank">' . $h($val) . '</a>';
                    } else {
                        $val = $h($val);
                    }
                    echo '<tr><td class="text-muted" style="width:140px">' . $h($f) . '</td><td>' . $val . '</td></tr>';
                }
                echo '</table>';
            }
        }
        echo '</div></div></div>';

        // Stripe card (em row separada se existir)
        $stripePagamentos = array_filter($pagamentos, fn($pg) => strtolower((string)($pg['gateway'] ?? '')) === 'stripe');
        if (!empty($stripePagamentos)) {
            $stripeValorTotal = array_sum(array_map(fn($pg) => (float)($pg['valor'] ?? 0), $stripePagamentos));
            echo '</div>'; // end row AppMax/CambioReal
            echo '<div class="row mb-3"><div class="col-md-6"><div class="card h-100"><div class="card-header bg-info text-white"><strong><i class="fas fa-credit-card me-1"></i>Stripe</strong></div><div class="card-body">';
            echo '<div class="mb-2"><strong>Valor Stripe (USD):</strong> ' . $fmtMoeda($stripeValorTotal, 'USD') . '</div>';
            foreach ($stripePagamentos as $pg) {
                echo '<hr class="my-2"><table class="table table-sm mb-0">';
                $fields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id'];
                foreach ($fields as $f) {
                    if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                    echo '<tr><td class="text-muted" style="width:140px">' . $h($f) . '</td><td>' . $h((string)$pg[$f]) . '</td></tr>';
                }
                echo '</table>';
            }
            echo '</div></div></div></div>'; // end card + col + row
        } else {
            echo '</div>'; // end row AppMax/CambioReal
        }

        // Split detalhado â€” sem coluna Link, status em português
        if (!empty($pagamentos)) {
            echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-code-branch me-1"></i>Split de Pagamento</strong></div><div class="card-body">
<div class="table-responsive"><table class="table table-sm">
<thead><tr><th>Componente</th><th>Gateway</th><th>Método</th><th>Moeda</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
            foreach ($pagamentos as $pg) {
                $gw = strtolower((string)($pg['gateway'] ?? ''));
                $gwLabel = $gw !== '' ? strtoupper($gw) : '-';
                if ($gw === 'cambioreal') $gwLabel = 'Câmbio Real';
                if ($gw === 'appmax') $gwLabel = 'AppMax';
                $statusRaw = (string)($pg['status'] ?? '');
                echo '<tr>
                    <td>' . $h($pg['componente'] ?? '') . '</td>
                    <td>' . $h($gwLabel) . '</td>
                    <td>' . $h($pg['metodo'] ?? '') . '</td>
                    <td>' . $h(strtoupper((string)($pg['moeda'] ?? ''))) . '</td>
                    <td>' . number_format((float)($pg['valor'] ?? 0), 2, ',', '.') . '</td>
                    <td>' . $h($traduzirStatus($statusRaw)) . '</td>
                </tr>';
            }
            echo '</tbody></table></div></div></div>';
        }

        // Medidas
        $pesoTotal   = (float)($pedido['peso_total'] ?? 0);
        $altura      = (float)($pedido['altura'] ?? 0);
        $largura     = (float)($pedido['largura'] ?? 0);
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

        // Informações Ãšteis â€” inclui todos os dados de pedido_pagamentos agrupados por gateway
        echo '<div class="card mb-3"><div class="card-header"><strong><i class="fas fa-info me-1"></i>Informações Ãšteis</strong></div><div class="card-body">';
        $useful = [
            'Código do pedido'   => $pedido['codigo_pedido'] ?? $pedido['codigo'] ?? '',
            'Gateway'            => $pedido['payment_gateway'] ?? $pedido['pagamento_gateway'] ?? '',
            'ID transação'       => $pedido['payment_id'] ?? $pedido['pagamento_transacao'] ?? '',
            'Status pagamento'   => $pedido['pagamento_status'] ?? $pedido['payment_status'] ?? '',
            'Moeda'              => $moeda,
            'Observação vendedor'=> $pedido['observacao_vendedor'] ?? '',
            'Observação cliente' => $pedido['observacao_cliente'] ?? ($pedido['customer_note'] ?? ''),
            'Criado em'          => !empty($pedido['created_at']) ? date('d/m/Y H:i:s', strtotime((string)$pedido['created_at'])) : '',
            'Atualizado em'      => !empty($pedido['updated_at']) ? date('d/m/Y H:i:s', strtotime((string)$pedido['updated_at'])) : '',
        ];
        echo '<table class="table table-sm mb-3">';
        foreach ($useful as $k => $v) {
            if ((string)$v === '') continue;
            echo '<tr><td class="text-muted" style="width:200px">' . $h($k) . '</td><td>' . $h($v) . '</td></tr>';
        }
        echo '</table>';

        // Dados completos de pedido_pagamentos agrupados por gateway
        $gwGroups = [];
        foreach ($pagamentos as $pg) {
            $gw = strtolower((string)($pg['gateway'] ?? 'outro'));
            $gwGroups[$gw][] = $pg;
        }
        $allFields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id','invoice_url','bank_slip_url','digitable_line','pix_payload'];
        foreach ($gwGroups as $gwKey => $pgList) {
            $gwLabel = match($gwKey) { 'appmax' => 'AppMax', 'cambioreal' => 'Câmbio Real', 'cambioreal_taxas' => 'Câmbio Real Taxas', 'stripe' => 'Stripe', default => strtoupper($gwKey) };
            echo '<h6 class="mt-3 mb-2 text-secondary border-bottom pb-1">' . $h($gwLabel) . '</h6>';
            foreach ($pgList as $i => $pg) {
                if (count($pgList) > 1) echo '<div class="text-muted small mb-1">Registro #' . ($i + 1) . '</div>';
                echo '<table class="table table-sm mb-2">';
                foreach ($allFields as $f) {
                    if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                    $val = (string)$pg[$f];
                    if ($f === 'pix_payload' && strlen($val) > 50) $val = substr($val, 0, 50) . 'â€¦';
                    if ($f === 'invoice_url' || $f === 'bank_slip_url') {
                        $display = '<a href="' . $h($val) . '" target="_blank">' . $h($val) . '</a>';
                    } else {
                        $display = $h($val);
                    }
                    echo '<tr><td class="text-muted" style="width:160px">' . $h($f) . '</td><td>' . $display . '</td></tr>';
                }
                echo '</table>';
            }
        }
        echo '</div></div>';

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
function confirmarRecebimento() {
    if (!confirm("Confirmar que o pedido chegou no Brasil? O status será alterado para Aguardando Liberação Aduaneira.")) return;
    const btn = document.getElementById("btnConfirmarRecebimento");
    if (btn) { btn.disabled = true; btn.textContent = "Confirmando..."; }
    fetch("/admin/remessa-conferencia/janela/" + jid + "/pedido/" + pid + "/confirmar-recebimento", {
        method: "POST", headers: {"Content-Type":"application/x-www-form-urlencoded"}, body: ""
    }).then(r => r.json()).then(d => {
        if (d.success) { alert("Recebimento confirmado!"); location.reload(); }
        else { alert("Erro: " + (d.error || "Falha")); if (btn) { btn.disabled = false; btn.textContent = "âœ“ Confirmar Recebimento"; } }
    }).catch(() => { alert("Erro ao confirmar"); if (btn) { btn.disabled = false; } });
}
</script>
</body></html>';
        exit;
    }

    public function confirmarRecebimento($request, $janelaId, $pedidoId) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;
        try {
            $this->ensureDocumentosTable();
            // Marcar recebimento na janela
            $st = $this->connection->prepare("UPDATE remessa_janela_pedidos SET recebido_confirmado = 1, recebido_confirmado_em = NOW() WHERE janela_id = ? AND pedido_id = ?");
            $st->execute([$jid, $pid]);
            // Mudar status do pedido para aguardando_liberacao_aduaneira
            try {
                $stP = $this->connection->prepare("UPDATE pedidos SET status = 'aguardando_liberacao_aduaneira', updated_at = NOW() WHERE id = ?");
                $stP->execute([$pid]);
            } catch (\Exception $e) {}
            echo json_encode(['success' => true]);
        } catch (\Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    public function gerarComprovante($request, $janelaId, $pedidoId, $gateway) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;
        $gateway = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$gateway));

        // Detectar envio de redirecionamento
        $isRedir = ($pid >= 900000);
        if ($isRedir) {
            $pedido = $this->getEnvioRedirecionamentoComoPedido($pid - 900000);
        } else {
            $pedido = $this->getPedidoCompleto($pid);
        }
        if (!$pedido) { echo '<p>Pedido não encontrado.</p>'; exit; }

        $h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
        $moeda = strtoupper(trim((string)($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moeda === '') $moeda = 'USD';
        $fmtMoeda = fn($v, $m) => $v !== null ? ($m === 'BRL' ? 'R$ ' : 'US$ ') . number_format((float)$v, 2, ',', '.') : '-';

        $taxaUsdBrl = 5.85;
        try {
            foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                try {
                    $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                    $st->execute([$k]);
                    $val = $st->fetchColumn();
                    $v = (float) str_replace(',', '.', trim((string) ($val ?? '')));
                    if ($v > 0.0001) {
                        $taxaUsdBrl = $v;
                        break;
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        $pagamentos = array_filter($pedido['pagamentos'] ?? [], fn($pg) => strtolower((string)($pg['gateway'] ?? '')) === $gateway);
        $gwLabel = match($gateway) { 'appmax' => 'AppMax', 'cambioreal' => 'Câmbio Real', 'cambioreal_taxas' => 'Câmbio Real Taxas', 'stripe' => 'Stripe', default => strtoupper($gateway) };
        $totalGw = array_sum(array_map(fn($pg) => (float)($pg['valor'] ?? 0), $pagamentos));
        $itens = $pedido['itens'] ?? [];
        $dataHoje = date('d/m/Y H:i');

        ob_start();
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Comprovante ' . $h($gwLabel) . ' - Pedido #' . $pid . '</title>
<style>
body{font-family:Arial,sans-serif;font-size:13px;color:#222;margin:30px}
h2{font-size:15px;color:#555;margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:4px}
table{width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed}
th,td{border:1px solid #ddd;padding:4px 6px;text-align:left;vertical-align:top}
th{background:#f5f5f5;width:160px}
.itens th,.itens td{font-size:11px}
.itens th:nth-child(1),.itens td:nth-child(1){width:24px}
.itens th:nth-child(3),.itens td:nth-child(3){width:30px;text-align:center}
.itens th:nth-child(4),.itens td:nth-child(4){width:90px;text-align:right}
.itens th:nth-child(5),.itens td:nth-child(5){width:90px;text-align:right}
.wrap{word-break:break-word;overflow-wrap:anywhere;white-space:normal}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px}
.logo{font-size:22px;font-weight:bold;color:#1a5276}
</style></head><body>
<div class="header">
    <div><div class="logo">Braziliana</div><div style="color:#888;font-size:12px">Comprovante de Pagamento</div></div>
    <div style="text-align:right"><div><strong>Data:</strong> ' . $dataHoje . '</div><div><strong>Gateway:</strong> ' . $h($gwLabel) . '</div></div>
</div>
<h2>Dados do Pedido</h2>
<table>
<tr><th>Pedido</th><td>#' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</td><th>Data</th><td>' . (!empty($pedido['created_at']) ? date('d/m/Y H:i', strtotime((string)$pedido['created_at'])) : '-') . '</td></tr>
<tr><th>Cliente</th><td>' . $h($pedido['cliente_nome'] ?? '') . '</td><th>E-mail</th><td>' . $h($pedido['cliente_email'] ?? '') . '</td></tr>
<tr><th>Moeda</th><td>' . $h($moeda) . '</td><th>Status</th><td>' . $h($pedido['status'] ?? '') . '</td></tr>
</table>
<h2>Dados do Pagamento â€” ' . $h($gwLabel) . '</h2>';

        $allFields = ['componente','gateway','metodo','moeda','valor','status','gateway_status','payment_id','invoice_url','bank_slip_url','digitable_line','pix_payload'];
        foreach ($pagamentos as $i => $pg) {
            if (count($pagamentos) > 1) echo '<p><strong>Registro #' . ($i + 1) . '</strong></p>';
            echo '<table>';
            foreach ($allFields as $f) {
                if (!isset($pg[$f]) || $pg[$f] === null || $pg[$f] === '') continue;
                $val = (string)$pg[$f];
                if ($f === 'pix_payload' && strlen($val) > 50) $val = substr($val, 0, 50) . 'â€¦';
                echo '<tr><th>' . $h($f) . '</th><td>' . $h($val) . '</td></tr>';
            }
            echo '</table>';
        }

        echo '<h2>Itens</h2><table class="itens"><thead><tr><th>#</th><th>Produto</th><th>Qtd</th><th>Preço Unit. (USD)</th><th>Total (USD)</th></tr></thead><tbody>';
        $idx = 1;
        foreach ($itens as $it) {
            $pu = null;
            foreach (['preco_unitario','valor_unitario','preco','price'] as $c) {
                if (isset($it[$c]) && is_numeric($it[$c])) { $pu = (float)$it[$c]; break; }
            }
            $qtdIt = (int)($it['quantidade'] ?? 0);
            $totIt = $pu !== null ? $pu * $qtdIt : null;
            echo '<tr><td>' . $idx . '</td><td class="wrap">' . $h($it['produto_nome'] ?? '') . '</td><td>' . $qtdIt . '</td><td>' . $fmtMoeda($pu, 'USD') . '</td><td>' . $fmtMoeda($totIt, 'USD') . '</td></tr>';
            $idx++;
        }
        echo '</tbody></table>';
        echo '<div style="margin-top:6px;color:#666;font-size:12px">Os valores dos itens estão em USD. Conversão estimada para BRL usando a taxa configurada no sistema: 1 USD = R$ ' . number_format($taxaUsdBrl, 4, ',', '.') . '.</div>';
        echo '<h2>Total ' . $h($gwLabel) . '</h2><table>';

        // Detectar moeda do pagamento
        $pgMoeda = 'BRL';
        foreach ($pagamentos as $pg) {
            if (!empty($pg['moeda'])) { $pgMoeda = strtoupper(trim((string)$pg['moeda'])); break; }
        }
        if ($pgMoeda === '' || $moeda === 'USD') $pgMoeda = $moeda;

        if ($pgMoeda === 'USD') {
            $totalBrlEq = $totalGw * $taxaUsdBrl;
            echo '<tr><th>Total pago (USD)</th><td><strong>US$ ' . number_format($totalGw, 2, ',', '.') . '</strong></td></tr>';
            echo '<tr><th>Equivalente (BRL)</th><td><strong>R$ ' . number_format($totalBrlEq, 2, ',', '.') . '</strong></td></tr>';
        } else {
            $totalUsdEq = $taxaUsdBrl > 0 ? ($totalGw / $taxaUsdBrl) : 0.0;
            echo '<tr><th>Total pago (BRL)</th><td><strong>R$ ' . number_format($totalGw, 2, ',', '.') . '</strong></td></tr>';
            echo '<tr><th>Equivalente (USD)</th><td><strong>US$ ' . number_format($totalUsdEq, 2, ',', '.') . '</strong></td></tr>';
        }
        echo '<tr><th>Taxa de câmbio</th><td>1 USD = R$ ' . number_format($taxaUsdBrl, 4, ',', '.') . '</td></tr>';
        echo '</table></body></html>';
        $html = ob_get_clean();

        $fname = 'comprovante_' . $gateway . '_pedido_' . $pid . '_' . date('Ymd') . '.pdf';
        $this->renderPdf($html, $fname);
        exit;
    }

    public function gerarInvoice($request, $janelaId, $pedidoId) {
        $this->requireAccess();
        $jid = (int)$janelaId; $pid = (int)$pedidoId;

        $pedido = $this->getPedidoCompleto($pid);
        if (!$pedido) { echo '<p>Pedido não encontrado.</p>'; exit; }

        $h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
        $moeda = strtoupper(trim((string)($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moeda === '') $moeda = 'USD';
        $fmtMoeda = fn($v, $m) => $v !== null ? ($m === 'BRL' ? 'R$ ' : 'US$ ') . number_format((float)$v, 2, ',', '.') : '-';

        $taxaUsdBrl = 5.85;
        try {
            foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                try {
                    $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                    $st->execute([$k]);
                    $val = $st->fetchColumn();
                    $v = (float) str_replace(',', '.', trim((string) ($val ?? '')));
                    if ($v > 0.0001) {
                        $taxaUsdBrl = $v;
                        break;
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        $itens = $pedido['itens'] ?? [];
        $pagamentos = $pedido['pagamentos'] ?? [];
        $end = $pedido['endereco'] ?? null;
        $logr   = is_array($end) ? trim((string)($end['endereco'] ?? ($end['logradouro'] ?? ''))) : '';
        $num    = is_array($end) ? trim((string)($end['numero'] ?? '')) : '';
        $compl  = is_array($end) ? trim((string)($end['complemento'] ?? '')) : '';
        $bairro = is_array($end) ? trim((string)($end['bairro'] ?? '')) : '';
        $cidade = is_array($end) ? trim((string)($end['cidade'] ?? '')) : '';
        $estado = is_array($end) ? trim((string)($end['estado'] ?? '')) : '';
        $cep    = is_array($end) ? trim((string)($end['cep'] ?? ($end['zip_code'] ?? ''))) : '';
        $pais   = is_array($end) ? trim((string)($end['pais'] ?? ($end['country'] ?? 'BR'))) : 'BR';
        $dataHoje = date('d/m/Y H:i');

        ob_start();
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Invoice - Pedido #' . $pid . '</title>
<style>
body{font-family:Arial,sans-serif;font-size:13px;color:#222;margin:30px}
h1{font-size:22px;margin-bottom:4px}
h2{font-size:15px;color:#555;margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:4px}
table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:left}
th{background:#f5f5f5}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px}
.logo{font-size:24px;font-weight:bold;color:#1a5276}
.totals td:last-child{text-align:right}
</style></head><body>
<div class="header">
    <div><div class="logo">Braziliana</div><div style="color:#888;font-size:12px">Invoice / Fatura</div></div>
    <div style="text-align:right">
        <div><strong>Invoice #:</strong> ' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT) . '</div>
        <div><strong>Data:</strong> ' . $dataHoje . '</div>
        <div><strong>Moeda:</strong> ' . $h($moeda) . '</div>
    </div>
</div>

<h2>Dados do Cliente</h2>
<table>
<tr><th>Nome</th><td>' . $h($pedido['cliente_nome'] ?? '') . '</td><th>E-mail</th><td>' . $h($pedido['cliente_email'] ?? '') . '</td></tr>
<tr><th>CPF/Doc</th><td>' . $h($pedido['cliente_cpf'] ?? '') . '</td><th>Telefone</th><td>' . $h($pedido['cliente_telefone'] ?? '') . '</td></tr>
<tr><th>Suíte</th><td>' . $h($pedido['cliente_suite'] ?? '') . '</td><th>Status pedido</th><td>' . $h($pedido['status'] ?? '') . '</td></tr>
</table>

<h2>Endereço de Entrega</h2>
<table>
<tr><th>Logradouro</th><td>' . $h($logr . ($num !== '' ? ', ' . $num : '') . ($compl !== '' ? ' - ' . $compl : '')) . '</td><th>Bairro</th><td>' . $h($bairro) . '</td></tr>
<tr><th>Cidade</th><td>' . $h($cidade) . '</td><th>Estado</th><td>' . $h($estado) . '</td></tr>
<tr><th>CEP</th><td>' . $h($cep) . '</td><th>País</th><td>' . $h($pais) . '</td></tr>
</table>

<h2>Itens</h2>
<table>
<thead><tr><th>#</th><th>Produto / Declaração</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th></tr></thead>
<tbody>';
        $idx = 1;
        foreach ($itens as $it) {
            $pu = null;
            foreach (['preco_unitario','valor_unitario','preco','price'] as $c) {
                if (isset($it[$c]) && is_numeric($it[$c])) { $pu = (float)$it[$c]; break; }
            }
            $qtdIt = (int)($it['quantidade'] ?? 0);
            $totIt = $pu !== null ? $pu * $qtdIt : null;
            $foto = trim((string)($it['foto_produto'] ?? ''));
            $imgTag = '-';
            if ($foto !== '') {
                if (!str_starts_with($foto, 'http')) $foto = '/' . ltrim($foto, '/');
                $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                $localPath = $docRoot . $foto;
                $raw = @file_get_contents($localPath);
                if ($raw !== false && strlen($raw) > 100) {
                    $ext = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
                    $converted = false;
                    // Tentar converter AVIF/WEBP para JPEG via Imagick
                    if (in_array($ext, ['avif', 'webp'], true) && class_exists('Imagick')) {
                        try {
                            $im = new \Imagick();
                            $im->readImageBlob($raw);
                            $im->setImageFormat('jpeg');
                            $im->setImageCompressionQuality(85);
                            $raw = $im->getImagesBlob();
                            $im->clear(); $im->destroy();
                            $ext = 'jpg';
                            $converted = true;
                        } catch (\Throwable $e) {}
                    }
                    // Tentar converter via GD
                    if (!$converted && in_array($ext, ['avif', 'webp'], true)) {
                        try {
                            $img = @imagecreatefromstring($raw);
                            if ($img !== false) {
                                ob_start();
                                imagejpeg($img, null, 85);
                                $raw = ob_get_clean();
                                imagedestroy($img);
                                $ext = 'jpg';
                                $converted = true;
                            }
                        } catch (\Throwable $e) {}
                    }
                    if ($converted || !in_array($ext, ['avif', 'webp'], true)) {
                        $mime = match($ext) { 'png' => 'image/png', 'gif' => 'image/gif', default => 'image/jpeg' };
                        $imgTag = '<img src="data:' . $mime . ';base64,' . base64_encode($raw) . '" style="width:40px;height:40px;object-fit:cover">';
                    } else {
                        // Fallback: URL absoluta (funciona no browser ao abrir o HTML)
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
                        $absUrl = $host !== '' ? $scheme . '://' . $host . $foto : $foto;
                        $imgTag = '<img src="' . htmlspecialchars($absUrl, ENT_QUOTES, 'UTF-8') . '" style="width:40px;height:40px;object-fit:cover">';
                    }
                }
            }
            echo '<tr><td>' . $idx . '</td><td>' . $h($it['produto_nome'] ?? '') . '</td><td>' . $qtdIt . '</td><td>' . $fmtMoeda($pu, 'USD') . '</td><td>' . $fmtMoeda($totIt, 'USD') . '</td></tr>';
            $idx++;
        }
        echo '</tbody></table>';
        echo '<div style="margin-top:6px;color:#666;font-size:12px">Os valores dos itens estão em USD. Conversão estimada para BRL usando a taxa configurada no sistema: 1 USD = R$ ' . number_format($taxaUsdBrl, 4, ',', '.') . '.</div>';

        echo '<h2>Resumo de Pagamento</h2><table>';
        $cambioRealPagamentos = array_filter($pagamentos, fn($pg) => strtolower((string)($pg['gateway'] ?? '')) === 'cambioreal');
        $totalBrl = 0.0;
        $metodoPagamento = 'Câmbio Real';
        $dataPagamento = (string)($pedido['pago_em'] ?? ($pedido['pagamento_data'] ?? ($pedido['paid_at'] ?? '')));
        foreach ($cambioRealPagamentos as $pg) {
            $v = (float)($pg['valor'] ?? 0);
            $m = strtolower((string)($pg['moeda'] ?? 'BRL'));
            if ($m === 'brl') {
                $totalBrl += $v;
            }
            if ($dataPagamento === '' && !empty($pg['created_at'])) {
                $dataPagamento = (string) $pg['created_at'];
            }
        }
        $totalUsdEq = $taxaUsdBrl > 0 ? ($totalBrl / $taxaUsdBrl) : 0.0;
        echo '<tr><th>Método</th><td>' . $h($metodoPagamento) . '</td></tr>';
        echo '<tr><th>Data de crédito</th><td>' . ($dataPagamento !== '' ? date('d/m/Y H:i', strtotime($dataPagamento)) : '-') . '</td></tr>';
        echo '<tr><th>Total pago (BRL)</th><td><strong>R$ ' . number_format($totalBrl, 2, ',', '.') . '</strong></td></tr>';
        echo '<tr><th>Equivalente (USD)</th><td><strong>US$ ' . number_format($totalUsdEq, 2, ',', '.') . '</strong></td></tr>';
        echo '<tr><th>Taxa de câmbio</th><td>1 USD = R$ ' . number_format($taxaUsdBrl, 4, ',', '.') . '</td></tr>';
        echo '</table></body></html>';

        $html = ob_get_clean();
        $fname = 'invoice_pedido_' . $pid . '_' . date('Ymd') . '.pdf';
        $this->renderPdf($html, $fname);
        exit;
    }

    private function renderPdf(string $html, string $filename): void {
        $dompdfClass = 'Dompdf\\Dompdf';
        if (class_exists($dompdfClass)) {
            try {
                $dompdf = new $dompdfClass(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                echo $dompdf->output();
                return;
            } catch (\Throwable $e) {}
        }
        // Fallback: download como HTML
        $htmlFilename = str_replace('.pdf', '.html', $filename);
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $htmlFilename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $html;
    }
}
