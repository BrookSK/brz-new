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
        $statusFilterRaw = $request->getParam('status', '');

        // Suportar múltiplos status (array do multi-select)
        $statusFilter = [];
        if (is_array($statusFilterRaw)) {
            $statusFilter = array_filter(array_map('trim', $statusFilterRaw), function($s) { return $s !== ''; });
        } elseif (is_string($statusFilterRaw) && $statusFilterRaw !== '') {
            $statusFilter = [$statusFilterRaw];
        }

        // Padrão: filtrar por 'pago' quando nenhum status é selecionado
        $statusDefaultApplied = false;
        if (empty($statusFilter) && $request->getParam('status', null) === null) {
            $statusFilter = ['pago'];
            $statusDefaultApplied = true;
        }

        // Buscar pedidos
        $where = ["p.created_at >= :ds", "p.created_at < DATE_ADD(:de, INTERVAL 1 DAY)"];
        $params = [':ds' => $dateStart, ':de' => $dateEnd];

        // Detectar colunas
        $cols = [];
        try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        if (!empty($statusFilter)) {
            // Expandir status englobados pela hierarquia de progressão
            // Ex: 'produto_consolidado' (Caixa Fechada) engloba 'pago', 'itens_comprados', etc.
            $expandido = [];
            foreach ($statusFilter as $sf) {
                $expandido[] = $sf;
                $englobados = \App\Controllers\AdminPedidosController::getStatusEnglobados($sf);
                foreach ($englobados as $eng) {
                    $expandido[] = $eng;
                }
            }
            $expandido = array_unique($expandido);

            $placeholders = [];
            foreach (array_values($expandido) as $i => $s) {
                $key = ':st' . $i;
                $placeholders[] = $key;
                $params[$key] = $s;
            }
            $where[] = "p.status IN (" . implode(',', $placeholders) . ")";
        } else {
            // Excluir pedidos de carnê e deletados/cancelados da listagem (apenas quando não há filtro explícito)
            $where[] = "LOWER(COALESCE(p.status,'')) NOT IN ('carne_pagando','carne_aguardando')";
        }

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
        $clienteJoinCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('cliente_id', $cols, true) ? 'cliente_id' : 'id');

        $sql = "SELECT p.*";
        // Adicionar campos do usuario apenas se existirem
        $uFields = ['email'=>'u_email','documento'=>'u_documento','telefone'=>'u_telefone','celular'=>'u_celular','data_nascimento'=>'u_nascimento'];
        foreach ($uFields as $uf => $alias) {
            if (in_array($uf, $userCols, true)) { $sql .= ", u.{$uf} AS {$alias}"; }
        }
        if (in_array('nome', $userCols, true)) { $sql .= ", u.nome AS u_nome"; }
        elseif (in_array('name', $userCols, true)) { $sql .= ", u.name AS u_nome"; }
        $sql .= " FROM pedidos p LEFT JOIN usuarios u ON u.id = p.{$clienteJoinCol}"
            . " WHERE " . implode(' AND ', $where)
            . " ORDER BY p.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Fallback: preencher dados do cliente de todas as fontes
        if (!empty($pedidos)) {
            foreach ($pedidos as &$_p) {
                // Nome
                if (empty($_p['cliente_nome']) || trim((string)$_p['cliente_nome']) === '') {
                    foreach (['cliente_nome','nome','customer_name','u_nome'] as $k) {
                        if (!empty($_p[$k]) && trim((string)$_p[$k]) !== '') { $_p['cliente_nome'] = (string)$_p[$k]; break; }
                    }
                }
                // Email
                if (empty($_p['cliente_email']) || trim((string)$_p['cliente_email']) === '') {
                    foreach (['cliente_email','email','customer_email','u_email'] as $k) {
                        if (!empty($_p[$k]) && trim((string)$_p[$k]) !== '') { $_p['cliente_email'] = (string)$_p[$k]; break; }
                    }
                }
                // CPF
                if (empty($_p['cliente_cpf']) || trim((string)$_p['cliente_cpf']) === '') {
                    foreach (['cliente_cpf','cliente_cpf_cnpj','cliente_documento','documento','u_documento'] as $k) {
                        if (!empty($_p[$k]) && trim((string)$_p[$k]) !== '') { $_p['cliente_cpf'] = (string)$_p[$k]; break; }
                    }
                }
                // Telefone
                $_p['cliente_telefone'] = '';
                foreach (['cliente_telefone','telefone','u_telefone','u_celular'] as $k) {
                    if (!empty($_p[$k]) && trim((string)$_p[$k]) !== '') { $_p['cliente_telefone'] = (string)$_p[$k]; break; }
                }
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
        $data = compact('pedidos', 'itensPorPedido', 'rastreioPorPedido', 'impressoesPorPedido', 'dateStart', 'dateEnd', 'statusFilter', 'statusList', 'statusDefaultApplied');
        extract($data);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-pedidos/index.php';
        $content = ob_get_clean();

        // Renderizar com layout admin
        $title = 'Relatório de Pedidos';
        $sidebarActive = 'relatorio-pedidos';
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
        $clienteId = (int)($pedido['usuario_id'] ?? ($pedido['cliente_id'] ?? ($pedido['user_id'] ?? 0)));
        // Fallback: buscar do banco se não veio no model
        if ($clienteId <= 0) {
            try {
                $colsPedCheck = [];
                try { $stC = $this->db->query('DESCRIBE pedidos'); $colsPedCheck = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $cidCol = in_array('cliente_id', $colsPedCheck, true) ? 'cliente_id' : (in_array('usuario_id', $colsPedCheck, true) ? 'usuario_id' : '');
                if ($cidCol !== '') {
                    $st = $this->db->prepare("SELECT {$cidCol} FROM pedidos WHERE id = ? LIMIT 1");
                    $st->execute([(int)$id]);
                    $clienteId = (int)($st->fetchColumn() ?: 0);
                }
            } catch (\Exception $e) {}
        }
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
                try {
                    // Tentar com principal primeiro
                    $st = $this->db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1");
                    $st->execute([$clienteId]);
                    $end = $st->fetch(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    // Se falhar (coluna principal não existe), buscar sem
                    try {
                        $st = $this->db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1");
                        $st->execute([$clienteId]);
                        $end = $st->fetch(\PDO::FETCH_ASSOC);
                    } catch (\Exception $e) { $end = null; }
                }
                if ($end) {
                    foreach ($endFields as $ef) {
                        if (empty($endEntrega[$ef]) && !empty($end[$ef])) {
                            $endEntrega[$ef] = (string)$end[$ef];
                        }
                    }
                }

                // Fallback: dados do usuario
                if (empty($endEntrega['endereco']) && !empty($cliente)) {
                    foreach ($endFields as $ef) {
                        if (empty($endEntrega[$ef]) && !empty($cliente[$ef])) {
                            $endEntrega[$ef] = (string)$cliente[$ef];
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

        // Pré-carregar pesos dos produtos
        $pesosProdutos = [];
        if (!empty($itens)) {
            $pids = [];
            foreach ($itens as $it) { $pid = (int)($it['produto_id'] ?? 0); if ($pid > 0) $pids[$pid] = true; }
            if (!empty($pids)) {
                try {
                    $prodCols = [];
                    try { $stPC = $this->db->query('DESCRIBE produtos'); $prodCols = $stPC ? $stPC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                    $pesoCol = in_array('weight', $prodCols, true) ? 'weight' : (in_array('peso', $prodCols, true) ? 'peso' : '');
                    if ($pesoCol !== '') {
                        $in = implode(',', array_keys($pids));
                        $stP = $this->db->query("SELECT id, {$pesoCol} AS peso FROM produtos WHERE id IN ({$in})");
                        foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                            $pesosProdutos[(int)$r['id']] = (float)($r['peso'] ?? 0);
                        }
                    }
                } catch (\Exception $e) {}
            }
        }

        $fmt = function($v) use ($moeda) {
            if ($moeda === 'BRL') return 'R$ ' . number_format((float)$v, 2, ',', '.');
            return 'US$ ' . number_format((float)$v, 2, '.', ',');
        };

        // Buscar dados do Carnê (se for pedido de carnê)
        $carneInfo = null;
        try {
            $stCarne = $this->db->prepare("
                SELECT c.id, c.status, c.quantidade_parcelas, c.total_geral, c.created_at,
                    (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status = 'paga') as parcelas_pagas,
                    (SELECT SUM(COALESCE(valor_produtos,0) + COALESCE(valor_taxas,0)) FROM carne_parcelas WHERE carne_id = c.id AND status = 'paga') as valor_pago,
                    (SELECT MIN(vencimento) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente')) as proximo_vencimento,
                    (SELECT MAX(vencimento) FROM carne_parcelas WHERE carne_id = c.id) as ultima_parcela_vencimento
                FROM carnes c WHERE c.pedido_id = ? LIMIT 1
            ");
            $stCarne->execute([(int)$id]);
            $carneInfo = $stCarne->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            // tabela carnes pode não existir
        }

        // Determinar tipo/origem do pedido para exibição no PDF
        // Analisa cada item para saber se é redirecionamento ou produto do site
        $itensRedirecionamento = 0;
        $itensSite = 0;
        $itensDesapego = 0;
        foreach ($itens as $it) {
            $tipoItem = (string)($it['tipo_item'] ?? '');
            $prodId = (int)($it['produto_id'] ?? 0);
            if ($tipoItem === 'pacote_redirecionamento' || $prodId >= 999990) {
                $itensRedirecionamento++;
            } else {
                $itensSite++;
                // Verificar se é desapego
                if ($prodId > 0) {
                    try {
                        $stDesapPdf = $this->db->prepare('SELECT desapego FROM produtos WHERE id = ? LIMIT 1');
                        $stDesapPdf->execute([$prodId]);
                        if ((int)($stDesapPdf->fetchColumn() ?: 0) === 1) {
                            $itensDesapego++;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        $origemPedido = trim((string)($pedido['origem_pedido'] ?? ''));

        // Determinar a origem REAL baseada nos itens (fonte primária de verdade)
        // O campo origem_pedido pode estar vazio em pedidos antigos
        $origemReal = $origemPedido;
        if ($itensRedirecionamento > 0 && $itensSite === 0) {
            $origemReal = 'redirecionamento';
        } elseif ($itensRedirecionamento > 0 && $itensSite > 0) {
            $origemReal = 'misto';
        } elseif ($itensRedirecionamento === 0 && $itensSite > 0 && $origemPedido === '') {
            $origemReal = 'site';
        }
        // Se origem_pedido tem valor válido (assessoria, manual) e não há itens de redir, respeitar
        if ($origemPedido === 'assessoria' || $origemPedido === 'manual') {
            $origemReal = $origemPedido;
        }

        // Montar informação do tipo de pedido
        $tipoPedido = [
            'codigo' => 'site',
            'label' => 'Produtos do Site',
            'descricao' => 'Pedido de produtos disponíveis no catálogo da loja.',
            'cor' => '#0b6623', // verde
            'icone' => '🛒',
        ];
        if ($origemReal === 'redirecionamento') {
            $tipoPedido = [
                'codigo' => 'redirecionamento',
                'label' => 'Redirecionamento de Pacote',
                'descricao' => 'Pedido originado do serviço de redirecionamento de pacotes (compras próprias do cliente).',
                'cor' => '#1565c0', // azul
                'icone' => '📦',
            ];
        } elseif ($origemReal === 'misto') {
            $tipoPedido = [
                'codigo' => 'misto',
                'label' => 'Pedido Misto',
                'descricao' => 'Este pedido contém ' . $itensRedirecionamento . ' item(ns) de redirecionamento e ' . $itensSite . ' item(ns) do catálogo do site.',
                'cor' => '#37474f', // cinza escuro
                'icone' => '🔀',
            ];
        } elseif ($origemReal === 'assessoria') {
            $tipoPedido = [
                'codigo' => 'assessoria',
                'label' => 'Assessoria de Compra',
                'descricao' => 'Pedido criado via orçamento de assessoria personalizada.',
                'cor' => '#6a1b9a', // roxo
                'icone' => '🎯',
            ];
        } elseif ($origemReal === 'manual') {
            $tipoPedido = [
                'codigo' => 'manual',
                'label' => 'Pedido Manual',
                'descricao' => 'Pedido criado manualmente pela equipe administrativa.',
                'cor' => '#e65100', // laranja
                'icone' => '✏️',
            ];
        }

        // Sobrescrever tipo se contém itens de desapego
        if ($itensDesapego > 0 && $itensRedirecionamento === 0) {
            if ($itensDesapego === $itensSite) {
                // Todos os itens são desapego
                $tipoPedido = [
                    'codigo' => 'desapego',
                    'label' => 'Desapego Braziliana',
                    'descricao' => 'Pedido de produto(s) do Desapego Braziliana (entrega somente EUA).',
                    'cor' => '#0891b2', // teal
                    'icone' => '💚',
                ];
            } else {
                // Mix de desapego + site
                $tipoPedido['descricao'] .= ' Contém ' . $itensDesapego . ' item(ns) de Desapego Braziliana.';
            }
        }

        // Suite do cliente (importante para redirecionamento)
        $tipoPedido['suite'] = $suite;
        $tipoPedido['itens_redirecionamento'] = $itensRedirecionamento;
        $tipoPedido['itens_site'] = $itensSite;
        $tipoPedido['itens_desapego'] = $itensDesapego;

        require __DIR__ . '/../Views/admin/relatorio-pedidos/imprimir.php';
        exit;
    }
}
