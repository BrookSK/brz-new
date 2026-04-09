<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminRelatorioPedidosController extends Controller {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $dateStart = $request->getParam('date_start', date('Y-m-d'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));
        $statusFilter = $request->getParam('status', '');

        // Buscar pedidos
        $where = ["p.created_at >= :ds", "p.created_at < DATE_ADD(:de, INTERVAL 1 DAY)"];
        $params = [':ds' => $dateStart, ':de' => $dateEnd];

        // Detectar colunas
        $cols = [];
        try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        if ($statusFilter !== '') {
            $where[] = "p.status = :st";
            $params[':st'] = $statusFilter;
        }

        // Excluir pedidos de carnê e deletados/cancelados da listagem
        $where[] = "LOWER(COALESCE(p.status,'')) NOT IN ('carne_pagando','carne_aguardando','cancelado','cancelled','apagado','deleted','lixeira','trash')";

        // Excluir soft-deleted (deleted_at)
        if (in_array('deleted_at', $cols, true)) {
            $where[] = "p.deleted_at IS NULL";
        }

        $select = ['p.id', 'p.status', 'p.created_at'];
        $colMap = [
            'codigo_pedido' => ['codigo_pedido','numero_pedido'],
            'total' => ['total','valor_total'],
            'moeda' => ['moeda','currency'],
            'taxa_conversao' => ['taxa_conversao'],
            'subtotal' => ['subtotal','subtotal_produtos'],
            'servicos' => ['servicos','taxa_servico'],
            'impostos' => ['impostos','valor_impostos'],
            'frete' => ['frete','valor_frete'],
            'forma_pagamento' => ['forma_pagamento','payment_method'],
            'pago_em' => ['pago_em','paid_at'],
            'observacoes' => ['observacoes','observacao'],
            'cliente_id' => ['cliente_id','usuario_id'],
        ];
        foreach ($colMap as $alias => $candidates) {
            foreach ($candidates as $c) {
                if (in_array($c, $cols, true)) { $select[] = "p.{$c} AS {$alias}"; break; }
            }
        }

        // Detectar coluna nome do usuario
        $userCols = [];
        try { $stUC = $this->db->query('DESCRIBE usuarios'); $userCols = $stUC ? $stUC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $userNomeCol = in_array('nome', $userCols, true) ? 'u.nome' : (in_array('name', $userCols, true) ? 'u.name' : "''");
        $clienteJoinCol = in_array('cliente_id', $cols, true) ? 'cliente_id' : (in_array('usuario_id', $cols, true) ? 'usuario_id' : 'id');

        // Buscar nome do cliente de múltiplas fontes (pedido ou usuario)
        $nomeExprParts = [$userNomeCol];
        foreach (['cliente_nome','nome','customer_name'] as $nc) {
            if (in_array($nc, $cols, true)) { array_unshift($nomeExprParts, "p.{$nc}"); }
        }
        $nomeExpr = "COALESCE(NULLIF(" . implode(",''), NULLIF(", $nomeExprParts) . ",''), '')";

        // Email do cliente
        $emailExprParts = ['u.email'];
        foreach (['cliente_email','email','customer_email'] as $ec) {
            if (in_array($ec, $cols, true)) { array_unshift($emailExprParts, "p.{$ec}"); }
        }
        $emailExpr = "COALESCE(NULLIF(" . implode(",''), NULLIF(", $emailExprParts) . ",''), '')";

        // CPF
        $cpfExprParts = [];
        foreach (['cliente_cpf_cnpj','cliente_documento','documento','cpf'] as $dc) {
            if (in_array($dc, $cols, true)) { $cpfExprParts[] = "p.{$dc}"; }
        }
        if (in_array('documento', $userCols, true)) { $cpfExprParts[] = 'u.documento'; }
        $cpfExpr = !empty($cpfExprParts) ? "COALESCE(NULLIF(" . implode(",''), NULLIF(", $cpfExprParts) . ",''), '')" : "''";

        $sql = "SELECT " . implode(', ', $select) . ",
                {$nomeExpr} AS cliente_nome,
                {$emailExpr} AS cliente_email,
                {$cpfExpr} AS cliente_cpf
                FROM pedidos p
                LEFT JOIN usuarios u ON u.id = p.{$clienteJoinCol}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Fallback: preencher dados do cliente de colunas do pedido ou do usuario
        if (!empty($pedidos)) {
            $clienteIds = [];
            foreach ($pedidos as $p) {
                $cid = (int)($p['cliente_id'] ?? 0);
                if ($cid > 0) $clienteIds[$cid] = true;
            }
            $clienteData = [];
            if (!empty($clienteIds)) {
                try {
                    $in = implode(',', array_keys($clienteIds));
                    $stU = $this->db->query("SELECT * FROM usuarios WHERE id IN ({$in})");
                    foreach ($stU->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                        $clienteData[(int)$u['id']] = $u;
                    }
                } catch (\Exception $e) {}
            }

            foreach ($pedidos as &$_p) {
                $cid = (int)($_p['cliente_id'] ?? 0);
                $u = $clienteData[$cid] ?? [];

                if (empty($_p['cliente_nome']) || trim((string)$_p['cliente_nome']) === '') {
                    foreach (['cliente_nome','nome','customer_name'] as $k) { if (!empty($_p[$k])) { $_p['cliente_nome'] = (string)$_p[$k]; break; } }
                    if (empty($_p['cliente_nome'])) { $_p['cliente_nome'] = (string)($u['nome'] ?? ($u['name'] ?? '')); }
                }
                if (empty($_p['cliente_email'])) {
                    foreach (['cliente_email','email','customer_email'] as $k) { if (!empty($_p[$k])) { $_p['cliente_email'] = (string)$_p[$k]; break; } }
                    if (empty($_p['cliente_email'])) { $_p['cliente_email'] = (string)($u['email'] ?? ''); }
                }
                if (empty($_p['cliente_cpf'])) {
                    foreach (['cliente_cpf','cliente_cpf_cnpj','cliente_documento','documento'] as $k) { if (!empty($_p[$k])) { $_p['cliente_cpf'] = (string)$_p[$k]; break; } }
                    if (empty($_p['cliente_cpf'])) { $_p['cliente_cpf'] = (string)($u['documento'] ?? ''); }
                }
                $_p['cliente_telefone'] = '';
                foreach (['cliente_telefone','telefone'] as $k) { if (!empty($_p[$k])) { $_p['cliente_telefone'] = (string)$_p[$k]; break; } }
                if (empty($_p['cliente_telefone'])) { $_p['cliente_telefone'] = (string)($u['telefone'] ?? ($u['celular'] ?? '')); }
            }
            unset($_p);
        }

        // Buscar itens de cada pedido
        $itensTable = null;
        foreach (['pedido_itens','pedido_items'] as $t) {
            try { $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?"); $st->execute([$t]); if ((int)$st->fetchColumn()>0) { $itensTable=$t; break; } } catch (\Exception $e) {}
        }

        $itensPorPedido = [];
        if ($itensTable && !empty($pedidos)) {
            $pids = array_column($pedidos, 'id');
            $in = implode(',', array_fill(0, count($pids), '?'));
            // Detectar coluna de nome do produto
            $prodCols = [];
            try { $stPC = $this->db->query('DESCRIBE produtos'); $prodCols = $stPC ? $stPC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $nomeCol = in_array('name', $prodCols, true) ? 'p2.name' : (in_array('nome', $prodCols, true) ? 'p2.nome' : "''");
            $fotoCol = in_array('foto_principal', $prodCols, true) ? 'p2.foto_principal' : "''";

            $stIt = $this->db->prepare("SELECT i.*, {$fotoCol} AS produto_foto, {$nomeCol} AS produto_nome_db
                FROM {$itensTable} i LEFT JOIN produtos p2 ON p2.id = i.produto_id
                WHERE i.pedido_id IN ({$in})");
            $stIt->execute($pids);
            foreach ($stIt->fetchAll(\PDO::FETCH_ASSOC) as $it) {
                $itensPorPedido[(int)$it['pedido_id']][] = $it;
            }
        }

        // Buscar rastreio
        $rastreioPorPedido = [];
        if (!empty($pedidos)) {
            $pids = array_column($pedidos, 'id');
            foreach (['correios_etiquetas'=>'codigo_etiqueta', 'shipstation_etiquetas'=>'tracking_number', 'stamps_etiquetas'=>'tracking_number'] as $tbl=>$col) {
                try {
                    $in = implode(',', array_fill(0, count($pids), '?'));
                    $st = $this->db->prepare("SELECT pedido_id, {$col} AS tracking FROM {$tbl} WHERE pedido_id IN ({$in}) AND {$col} IS NOT NULL AND {$col} != ''");
                    $st->execute($pids);
                    foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $pid = (int)$r['pedido_id'];
                        if (!isset($rastreioPorPedido[$pid]) || $rastreioPorPedido[$pid] === '') {
                            $rastreioPorPedido[$pid] = $r['tracking'];
                        }
                    }
                } catch (\Exception $e) {}
            }
        }

        // Buscar impressões
        $impressoesPorPedido = [];
        try {
            if (in_array('print_count', $cols, true)) {
                foreach ($pedidos as $p) {
                    $impressoesPorPedido[(int)$p['id']] = [
                        'count' => (int)($p['print_count'] ?? 0),
                        'by' => (string)($p['last_printed_by'] ?? '')
                    ];
                }
            }
        } catch (\Exception $e) {}

        // Status disponíveis
        $statusList = [];
        try {
            $st = $this->db->query("SELECT DISTINCT status FROM pedidos WHERE status IS NOT NULL AND status != '' ORDER BY status");
            $statusList = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {}

        // Passar para a view
        $data = compact('pedidos', 'itensPorPedido', 'rastreioPorPedido', 'impressoesPorPedido', 'dateStart', 'dateEnd', 'statusFilter', 'statusList');
        extract($data);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-pedidos/index.php';
        $content = ob_get_clean();

        // Renderizar com layout admin
        $title = 'Relatório de Pedidos';
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Registrar impressão (AJAX)
     */
    public function registrarImpressao(Request $request) {
        header('Content-Type: application/json');
        $pedidoId = (int) $request->getParam('pedido_id', 0);
        if ($pedidoId <= 0) { echo json_encode(['success'=>false]); exit; }

        try {
            $cols = [];
            try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

            // Garantir colunas existam
            if (!in_array('print_count', $cols, true)) {
                try { $this->db->exec("ALTER TABLE pedidos ADD COLUMN print_count INT NOT NULL DEFAULT 0"); } catch (\Exception $e) {}
            }
            if (!in_array('last_printed_by', $cols, true)) {
                try { $this->db->exec("ALTER TABLE pedidos ADD COLUMN last_printed_by VARCHAR(255) NULL"); } catch (\Exception $e) {}
            }

            $userName = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_email'] ?? 'Admin');
            $this->db->prepare("UPDATE pedidos SET print_count = COALESCE(print_count,0) + 1, last_printed_by = ? WHERE id = ?")
                ->execute([$userName, $pedidoId]);

            echo json_encode(['success'=>true]);
        } catch (\Exception $e) {
            echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
        }
        exit;
    }

    /**
     * Imprimir pedido individual (HTML para impressão)
     */
    public function imprimir(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $pedidoModel = new \App\Models\PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($id);
        if (!$pedido) { echo 'Pedido não encontrado'; exit; }

        $itens = $pedido['items'] ?? [];
        $moeda = strtoupper(trim((string)($pedido['moeda'] ?? 'BRL')));
        $taxa = (float)($pedido['taxa_conversao'] ?? 1);

        // Rastreio
        $tracking = '';
        try {
            foreach (['correios_etiquetas'=>'codigo_etiqueta','shipstation_etiquetas'=>'tracking_number','stamps_etiquetas'=>'tracking_number'] as $tbl=>$col) {
                try { $st = $this->db->prepare("SELECT {$col} FROM {$tbl} WHERE pedido_id=? ORDER BY id DESC LIMIT 1"); $st->execute([(int)$id]); $v = (string)($st->fetchColumn() ?: ''); if ($v !== '') { $tracking = $v; break; } } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {}

        // Dados do cliente
        $cliente = [];
        $clienteId = (int)($pedido['cliente_id'] ?? ($pedido['usuario_id'] ?? 0));
        if ($clienteId > 0) {
            try {
                $st = $this->db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
                $st->execute([$clienteId]);
                $cliente = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {}
        }

        // Endereço de entrega (do pedido ou do cadastro)
        $endEntrega = [];
        try {
            // Primeiro tentar dados do pedido
            $colsPed = [];
            try { $stC = $this->db->query('DESCRIBE pedidos'); $colsPed = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

            $endFields = ['pais','cep','endereco','numero','complemento','bairro','cidade','estado'];
            $temEndNoPedido = false;
            foreach ($endFields as $ef) {
                if (in_array($ef, $colsPed, true) && !empty($pedido[$ef])) { $temEndNoPedido = true; break; }
            }

            if ($temEndNoPedido) {
                foreach ($endFields as $ef) {
                    $endEntrega[$ef] = (string)($pedido[$ef] ?? '');
                }
            }

            // Se não tem no pedido, buscar do cadastro
            if (empty($endEntrega['endereco']) && $clienteId > 0) {
                $st = $this->db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1");
                $st->execute([$clienteId]);
                $end = $st->fetch(\PDO::FETCH_ASSOC);
                if ($end) {
                    foreach ($endFields as $ef) {
                        if (empty($endEntrega[$ef]) && !empty($end[$ef])) {
                            $endEntrega[$ef] = (string)$end[$ef];
                        }
                    }
                }
            }
        } catch (\Exception $e) {}

        // Suite do cliente
        $suite = (string)($cliente['suite'] ?? ($pedido['suite_cliente'] ?? ($pedido['suite'] ?? '')));

        // Consolidar dados do cliente de todas as fontes
        $pick = function(array $keys) use ($pedido, $cliente) {
            foreach ($keys as $k) {
                if (!empty($pedido[$k]) && trim((string)$pedido[$k]) !== '') return (string)$pedido[$k];
            }
            foreach ($keys as $k) {
                if (!empty($cliente[$k]) && trim((string)$cliente[$k]) !== '') return (string)$cliente[$k];
            }
            return '';
        };

        $clienteConsolidado = [
            'nome' => $pick(['cliente_nome','nome','customer_name','name']),
            'email' => $pick(['cliente_email','email','customer_email']),
            'cpf' => $pick(['cliente_cpf_cnpj','cliente_documento','documento','cpf','cpf_cnpj']),
            'telefone' => $pick(['cliente_telefone','telefone','celular','phone']),
            'data_nascimento' => $pick(['data_nascimento','birth_date','birthdate']),
            'suite' => $suite,
        ];

        // Destinatário (se diferente do cliente)
        $destinatario = [
            'nome' => (string)($pedido['destinatario_nome'] ?? ''),
            'documento' => (string)($pedido['destinatario_documento'] ?? ''),
            'telefone' => (string)($pedido['destinatario_telefone'] ?? ''),
        ];

        $fmt = function($v) use ($moeda) {
            if ($moeda === 'BRL') return 'R$ ' . number_format((float)$v, 2, ',', '.');
            return 'US$ ' . number_format((float)$v, 2, '.', ',');
        };

        require __DIR__ . '/../Views/admin/relatorio-pedidos/imprimir.php';
        exit;
    }
}
