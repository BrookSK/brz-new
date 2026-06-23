<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminPedidosEditController extends Controller {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensurePedidoMedidasColumns(): void {
        try {
            if (!$this->tableExists('pedidos')) {
                return;
            }

            $cols = $this->getColsFromTable('pedidos');
            $need = [
                'peso_total' => "ALTER TABLE pedidos ADD COLUMN peso_total DECIMAL(10,3) NULL",
                'altura' => "ALTER TABLE pedidos ADD COLUMN altura DECIMAL(8,2) NULL",
                'largura' => "ALTER TABLE pedidos ADD COLUMN largura DECIMAL(8,2) NULL",
                'comprimento' => "ALTER TABLE pedidos ADD COLUMN comprimento DECIMAL(8,2) NULL",
            ];

            foreach ($need as $col => $sql) {
                if (!is_array($cols) || !in_array($col, $cols, true)) {
                    try {
                        $this->connection->exec($sql);
                    } catch (\Exception $e) {
                    }
                }
            }

            // Migrar colunas INT para DECIMAL se necessário
            try {
                $stDesc = $this->connection->query("DESCRIBE pedidos");
                $rows = $stDesc ? $stDesc->fetchAll(\PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    $field = strtolower((string) ($row['Field'] ?? ''));
                    $type  = strtolower((string) ($row['Type'] ?? ''));
                    if (in_array($field, ['altura', 'largura', 'comprimento'], true) && strpos($type, 'int') !== false) {
                        try {
                            $this->connection->exec("ALTER TABLE pedidos MODIFY COLUMN {$field} DECIMAL(8,2) NULL");
                        } catch (\Exception $e) {}
                    }
                    // Migrar status de ENUM para VARCHAR para suportar todos os valores
                    if ($field === 'status' && strpos($type, 'enum') !== false) {
                        try {
                            $this->connection->exec("ALTER TABLE pedidos MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'pendente'");
                        } catch (\Exception $e) {}
                    }
                }
            } catch (\Exception $e) {}
        } catch (\Exception $e) {
        }
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getItensTable(): string {
        if ($this->tableExists('pedido_itens')) return 'pedido_itens';
        if ($this->tableExists('pedido_items')) return 'pedido_items';
        return 'pedido_itens';
    }

    private function getItensTableForPedido(int $pedidoId): string {
        $prefer = 'pedido_itens';
        $t1 = $this->tableExists('pedido_itens') ? 'pedido_itens' : null;
        $t2 = $this->tableExists('pedido_items') ? 'pedido_items' : null;

        if (!$t1 && !$t2) {
            return $prefer;
        }
        if ($t1 && !$t2) return $t1;
        if ($t2 && !$t1) return $t2;

        $c1 = 0;
        $c2 = 0;
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $c1 = (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $c1 = 0;
        }
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $c2 = (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $c2 = 0;
        }

        if ($c2 > $c1) return 'pedido_items';
        return 'pedido_itens';
    }

    private function getColsFromTable(string $table): array {
        try {
            $stmt = $this->connection->query('DESCRIBE ' . $table);
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickFirstExistingColumn(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return '';
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTotalEstoqueProduto(int $produtoId): int {
        if ($produtoId <= 0 || !$this->tableExists('estoque_interno')) {
            return 0;
        }
        try {
            $stmt = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmt->execute([':produto_id' => $produtoId]);
            return (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTotalReservadoAtivoProdutoSemPedido(int $produtoId, int $pedidoId): int {
        if ($produtoId <= 0 || !$this->tableExists('estoque_reservas')) {
            return 0;
        }
        if (!$this->columnExists('estoque_reservas', 'produto_id') || !$this->columnExists('estoque_reservas', 'quantidade_reservada') || !$this->columnExists('estoque_reservas', 'status')) {
            return 0;
        }
        $temPedido = $this->columnExists('estoque_reservas', 'pedido_id');
        try {
            $sql = "SELECT COALESCE(SUM(quantidade_reservada),0) as total FROM estoque_reservas WHERE produto_id = :produto_id AND status = 'ativa'";
            $params = [':produto_id' => $produtoId];
            if ($temPedido) {
                $sql .= ' AND (pedido_id IS NULL OR pedido_id <> :pedido_id)';
                $params[':pedido_id'] = $pedidoId;
            }
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function upsertReserva(int $pedidoId, int $produtoId, int $qtd, string $status = 'ativa'): void {
        if ($pedidoId <= 0 || $produtoId <= 0 || $qtd <= 0) {
            return;
        }
        if (!$this->tableExists('estoque_reservas')) {
            return;
        }
        if (!$this->columnExists('estoque_reservas', 'produto_id') || !$this->columnExists('estoque_reservas', 'quantidade_reservada') || !$this->columnExists('estoque_reservas', 'status')) {
            return;
        }
        $temPedido = $this->columnExists('estoque_reservas', 'pedido_id');

        $status = trim($status) !== '' ? trim($status) : 'ativa';

        try {
            if ($temPedido) {
                $stmtChk = $this->connection->prepare("SELECT id FROM estoque_reservas WHERE produto_id = :produto_id AND pedido_id = :pedido_id AND status = :status LIMIT 1");
                $stmtChk->execute([':produto_id' => $produtoId, ':pedido_id' => $pedidoId, ':status' => $status]);
                $id = (int) ($stmtChk->fetchColumn() ?: 0);
                if ($id > 0) {
                    $stmtUpd = $this->connection->prepare('UPDATE estoque_reservas SET quantidade_reservada = :q, status = :status WHERE id = :id LIMIT 1');
                    $stmtUpd->execute([':q' => $qtd, ':status' => $status, ':id' => $id]);
                    return;
                }
            }

            $cols = ['produto_id', 'quantidade_reservada', 'status'];
            $vals = [':produto_id', ':q', ':status'];
            $params = [':produto_id' => $produtoId, ':q' => $qtd, ':status' => $status];
            if ($temPedido) {
                $cols[] = 'pedido_id';
                $vals[] = ':pedido_id';
                $params[':pedido_id'] = $pedidoId;
            }
            $stmtIns = $this->connection->prepare('INSERT INTO estoque_reservas (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmtIns->execute($params);
        } catch (\Exception $e) {
        }
    }

    private function upsertPendenciaCompra(int $pedidoId, int $produtoId, int $qtdFaltante, string $nomeProdutoCustom = ''): void {
        if ($pedidoId <= 0 || $produtoId <= 0 || $qtdFaltante <= 0) {
            return;
        }
        if (!$this->tableExists('lista_compras')) {
            return;
        }

        $temPedido = $this->columnExists('lista_compras', 'pedido_id');
        $temNomeProduto = $this->columnExists('lista_compras', 'nome_produto');

        // Verificar se já existe item comprado para este produto/pedido — se sim, descontar a quantidade já comprada
        if ($temPedido) {
            try {
                $sqlComprado = "SELECT COALESCE(SUM(quantidade_faltante), 0) as qty_comprada FROM lista_compras WHERE produto_id = :produto_id AND pedido_id = :pedido_id AND status != 'pendente'";
                $paramsComprado = [':produto_id' => $produtoId, ':pedido_id' => $pedidoId];
                if ($temNomeProduto && $nomeProdutoCustom !== '') {
                    $sqlComprado .= ' AND nome_produto = :nome_produto';
                    $paramsComprado[':nome_produto'] = $nomeProdutoCustom;
                }
                $stmtComprado = $this->connection->prepare($sqlComprado);
                $stmtComprado->execute($paramsComprado);
                $jaComprado = (int) ($stmtComprado->fetchColumn() ?: 0);
                if ($jaComprado > 0) {
                    $qtdFaltante = $qtdFaltante - $jaComprado;
                    if ($qtdFaltante <= 0) {
                        return; // Toda a quantidade já foi comprada, não precisa criar pendência
                    }
                }
            } catch (\Exception $e) {}
        }

        // Se não tem coluna nome_produto, tentar criar
        if (!$temNomeProduto && $nomeProdutoCustom !== '') {
            try {
                $this->connection->exec("ALTER TABLE lista_compras ADD COLUMN nome_produto VARCHAR(255) DEFAULT NULL");
                $temNomeProduto = true;
            } catch (\Exception $e) {}
        }

        try {
            $sqlSel = "SELECT id, quantidade_faltante FROM lista_compras WHERE produto_id = :produto_id AND status = 'pendente'";
            $params = [':produto_id' => $produtoId];
            if ($temPedido) {
                $sqlSel .= ' AND pedido_id = :pedido_id';
                $params[':pedido_id'] = $pedidoId;
            }
            // Se tem nome customizado, buscar pelo nome para não misturar com itens de nome diferente
            if ($temNomeProduto && $nomeProdutoCustom !== '') {
                $sqlSel .= ' AND nome_produto = :nome_produto';
                $params[':nome_produto'] = $nomeProdutoCustom;
            }
            $sqlSel .= ' ORDER BY COALESCE(data_solicitacao, created_at) ASC, id ASC LIMIT 1';
            $stmtSel = $this->connection->prepare($sqlSel);
            $stmtSel->execute($params);
            $row = $stmtSel->fetch(\PDO::FETCH_ASSOC);
            if ($row && (int) ($row['id'] ?? 0) > 0) {
                $stmtUpd = $this->connection->prepare('UPDATE lista_compras SET quantidade_faltante = :q, quantidade_necessaria = GREATEST(COALESCE(quantidade_necessaria,0), :q) WHERE id = :id LIMIT 1');
                $stmtUpd->execute([':q' => $qtdFaltante, ':id' => (int) $row['id']]);
                return;
            }

            $cols = ['produto_id', 'quantidade_necessaria', 'quantidade_faltante', 'prioridade', 'status', 'data_solicitacao'];
            $vals = [':produto_id', ':q', ':q', "'media'", "'pendente'", 'CURDATE()'];
            $params = [':produto_id' => $produtoId, ':q' => $qtdFaltante];
            if ($temPedido) {
                $cols[] = 'pedido_id';
                $vals[] = ':pedido_id';
                $params[':pedido_id'] = $pedidoId;
            }
            if ($temNomeProduto && $nomeProdutoCustom !== '') {
                $cols[] = 'nome_produto';
                $vals[] = ':nome_produto';
                $params[':nome_produto'] = $nomeProdutoCustom;
            }
            $stmtIns = $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmtIns->execute($params);
        } catch (\Exception $e) {
        }
    }

    private function limparReservasEPendenciasDoPedido(int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

        // Reservas
        if ($this->tableExists('estoque_reservas') && $this->columnExists('estoque_reservas', 'pedido_id')) {
            try {
                $stmt = $this->connection->prepare("DELETE FROM estoque_reservas WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }

        // Pendências geradas por pedido (se existir coluna)
        // Só remove itens pendentes — preserva itens já comprados/finalizados
        if ($this->tableExists('lista_compras') && $this->columnExists('lista_compras', 'pedido_id')) {
            try {
                $stmt = $this->connection->prepare("DELETE FROM lista_compras WHERE pedido_id = :pedido_id AND status = 'pendente'");
                $stmt->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }
    }

    private function finalizarCicloPedido(int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

        // Ao finalizar o ciclo (ex.: entregue), dar baixa física no estoque pelo que estava reservado.
        // Sem isso, o "reservado" some e o "disponível" sobe, sem reduzir o estoque total.
        $this->baixarEstoqueInternoPeloReservadoDoPedido($pedidoId);

        if ($this->tableExists('estoque_reservas') && $this->columnExists('estoque_reservas', 'pedido_id') && $this->columnExists('estoque_reservas', 'status')) {
            try {
                $stmt = $this->connection->prepare("UPDATE estoque_reservas SET status = 'finalizada' WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }

        if ($this->tableExists('lista_compras') && $this->columnExists('lista_compras', 'pedido_id') && $this->columnExists('lista_compras', 'status')) {
            try {
                $stmt = $this->connection->prepare("UPDATE lista_compras SET status = 'cancelado', quantidade_faltante = 0 WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }
    }

    private function baixarEstoqueInternoPeloReservadoDoPedido(int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

        if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_reservas')) {
            return;
        }

        if (!$this->columnExists('estoque_reservas', 'pedido_id')
            || !$this->columnExists('estoque_reservas', 'produto_id')
            || !$this->columnExists('estoque_reservas', 'quantidade_reservada')) {
            return;
        }

        if (!$this->columnExists('estoque_interno', 'produto_id') || !$this->columnExists('estoque_interno', 'quantidade')) {
            return;
        }

        // Consumir reservas ainda não finalizadas (para evitar dupla baixa)
        $temStatus = $this->columnExists('estoque_reservas', 'status');
        try {
            $sql = 'SELECT produto_id, COALESCE(SUM(COALESCE(quantidade_reservada,0)),0) as qtd '
                . 'FROM estoque_reservas WHERE pedido_id = :pedido_id';
            if ($temStatus) {
                $sql .= " AND (status IS NULL OR status = '' OR status <> 'finalizada')";
            }
            $sql .= ' GROUP BY produto_id';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':pedido_id' => $pedidoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $rows = [];
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $r) {
            $produtoId = (int) ($r['produto_id'] ?? 0);
            $qtd = (int) ($r['qtd'] ?? 0);
            if ($produtoId <= 0 || $qtd <= 0) {
                continue;
            }

            $restante = $qtd;
            try {
                $stmtLocs = $this->connection->prepare(
                    'SELECT id, quantidade FROM estoque_interno WHERE produto_id = :produto_id AND quantidade > 0 '
                    . 'ORDER BY CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC, data_compra ASC, id ASC'
                );
                $stmtLocs->execute([':produto_id' => $produtoId]);
                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $locs = [];
            }

            foreach ($locs as $loc) {
                if ($restante <= 0) {
                    break;
                }
                $locId = (int) ($loc['id'] ?? 0);
                $qAtual = (int) ($loc['quantidade'] ?? 0);
                if ($locId <= 0 || $qAtual <= 0) {
                    continue;
                }
                $consumir = ($qAtual <= $restante) ? $qAtual : $restante;
                $novoQ = $qAtual - $consumir;
                try {
                    $stmtUpd = $this->connection->prepare('UPDATE estoque_interno SET quantidade = :q WHERE id = :id LIMIT 1');
                    $stmtUpd->execute([':q' => $novoQ, ':id' => $locId]);
                } catch (\Exception $e) {
                }
                $restante -= $consumir;
            }
        }

        // Marcar como finalizada para não consumir novamente
        if ($temStatus) {
            try {
                $stmtFin = $this->connection->prepare("UPDATE estoque_reservas SET status = 'finalizada' WHERE pedido_id = :pedido_id AND (status IS NULL OR status = '' OR status <> 'finalizada')");
                $stmtFin->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }
    }

    public function editar($request) {
        try {
            $id = (int) $request->getParam('id');
            if ($id <= 0) {
                echo '<div class="alert alert-danger">Pedido inválido</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $pedidoModel = new \App\Models\PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($id);
            if (!$pedido) {
                echo '<div class="alert alert-danger">Pedido não encontrado</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $itens = $pedido['items'] ?? [];
            if (!is_array($itens)) {
                $itens = [];
            }

            $stmt = $this->connection->prepare("SELECT id, name, price, sku, loja FROM produtos WHERE active = 1 ORDER BY name");
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $codigoPedido = (string) ($pedido['codigo_pedido'] ?? $pedido['numero_pedido'] ?? $pedido['codigo'] ?? $pedido['numero'] ?? $id);
            if ($codigoPedido === '') {
                $codigoPedido = (string) $id;
            }

            $statusLower = strtolower((string) ($pedido['status'] ?? ''));
            $statusAtual = strtolower(trim((string) ($pedido['status'] ?? '')));
            $canEditItens = ($statusLower !== 'pago');

            // Moeda e taxa de conversão para converter preços de produtos (USD) para moeda do pedido
            $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL')));
            if ($moedaPedido === '') $moedaPedido = 'BRL';
            $taxaConvPedido = (float) ($pedido['taxa_conversao'] ?? 0);
            if ($taxaConvPedido <= 1.01 && $moedaPedido === 'BRL') {
                try {
                    $stTx = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'usd_brl_rate' LIMIT 1");
                    $stTx->execute();
                    $v = (float) str_replace(',', '.', (string) ($stTx->fetchColumn() ?: '0'));
                    if ($v > 1.01) $taxaConvPedido = $v;
                } catch (\Exception $e) {}
                if ($taxaConvPedido <= 1.01) $taxaConvPedido = 5.85;
            }

            $gatewayPedido = strtolower((string) ($pedido['payment_gateway'] ?? $pedido['pagamento_gateway'] ?? ''));
            $transacao = (string) ($pedido['payment_id'] ?? $pedido['pagamento_transacao'] ?? '');
            $temPagamentoAsaas = ($gatewayPedido === 'asaas' && $transacao !== '');

            include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

            echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pedido #' . htmlspecialchars($codigoPedido) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/pedidos-redesign.css" rel="stylesheet">';

            renderAdminSidebarStyles();

            echo '
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

            renderAdminSidebar('pedidos');

            echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="page-title">Editar Pedido #' . htmlspecialchars($codigoPedido) . '</h1>
                    <div class="d-flex gap-2">
                        <a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                        <button type="button" class="btn btn-success" onclick="salvarPedido()">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>

                ' . (!$canEditItens ? '<div class="alert alert-warning">Este pedido está com status <strong>Pago</strong>. Você pode <strong>alterar o status</strong> abaixo, mas não pode editar/adicionar itens até voltar para <strong>Pendente</strong>.</div>' : '') . '

                <div class="row">
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="fw-bold mb-1"><i class="fas fa-link me-2"></i>Cobrar diferença (Payment Link)</div>
                                <div class="text-muted small mb-3">Salve o pedido primeiro com os novos itens. O valor da diferença é calculado automaticamente.</div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <div>
                                        <label class="form-label small mb-0">Diferença calculada</label>
                                        <input type="text" class="form-control" id="diferenca_calculada" readonly style="max-width:180px;" value="R$ 0,00">
                                    </div>
                                    <div>
                                        <label class="form-label small mb-0">Valor manual (opcional)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="diferenca_valor" placeholder="Deixe vazio p/ automático" style="max-width:180px;">
                                    </div>
                                    <div class="d-flex align-items-end" style="padding-bottom:1px;">
                                        <button type="button" class="btn btn-primary" onclick="gerarLinkDiferenca()">
                                            <i class="fas fa-link me-1"></i>Gerar link de pagamento
                                        </button>
                                    </div>
                                </div>
                                <div id="box_link_diferenca" class="w-100 mt-2" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-info-circle me-2"></i>Informações</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Código</label>
                                    <input type="text" class="form-control" value="' . htmlspecialchars($codigoPedido) . '" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="pedido_status">
                                        ' . (function() use ($statusAtual) {
                                            $html = '';
                                            $currentLower = strtolower(trim((string) $statusAtual));
                                            foreach (\App\Controllers\AdminPedidosController::getStatusList() as $val => $label) {
                                                $sel = ($currentLower === strtolower($val)) ? ' selected' : '';
                                                $html .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                            }
                                            if ($currentLower !== '' && !array_key_exists($currentLower, array_change_key_case(\App\Controllers\AdminPedidosController::getStatusList(), CASE_LOWER))) {
                                                $html .= '<option value="' . htmlspecialchars((string) $statusAtual) . '" selected>' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $statusAtual))) . '</option>';
                                            }
                                            return $html;
                                        })() . '
                                    </select>
                                    <button type="button" class="btn btn-outline-primary w-100 mt-2" onclick="atualizarSomenteStatus()">
                                        <i class="fas fa-rotate me-1"></i>Atualizar Status
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <div class="fw-bold">Medidas e peso (obrigatório para gerar etiqueta)</div>
                                    <div class="row g-2 mt-1">
                                        <div class="col-6">
                                            <label class="form-label">Peso real (kg)</label>
                                            <input type="number" class="form-control" id="pedido_peso_total" step="0.001" min="0" value="' . htmlspecialchars((string) ($pedido['peso_total'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Comprimento (cm)</label>
                                            <input type="number" class="form-control" id="pedido_comprimento" step="0.01" min="0" value="' . htmlspecialchars((string) ($pedido['comprimento'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Largura (cm)</label>
                                            <input type="number" class="form-control" id="pedido_largura" step="0.01" min="0" value="' . htmlspecialchars((string) ($pedido['largura'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Altura (cm)</label>
                                            <input type="number" class="form-control" id="pedido_altura" step="0.01" min="0" value="' . htmlspecialchars((string) ($pedido['altura'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Código de Rastreio</label>
                                    <input type="text" class="form-control" id="tracking_code" value="' . htmlspecialchars((string) ($pedido['tracking_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '" placeholder="Ex: AB123456789BR">
                                    <div class="form-text">Código de rastreio exibido para o cliente. Deixe vazio se não houver.</div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label">Observação do vendedor</label>
                                    <textarea class="form-control" id="observacao_vendedor" rows="4" placeholder="Observação interna para compras/PDF...">' . htmlspecialchars((string) ($pedido['observacao_vendedor'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea>
                                    <div class="form-text">Essa observação é interna e aparece no PDF do relatório de compras.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-calculator me-2"></i>Financeiro</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Subtotal</label>
                                    <input type="text" class="form-control" id="subtotal_produtos" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Frete</label>
                                    <input type="number" class="form-control" id="valor_frete" value="' . (float) ($pedido['frete'] ?? 0) . '" step="0.01" onchange="calcularTotal()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Desconto (%)</label>
                                    <input type="number" class="form-control" id="percentual_desconto" value="0" min="0" max="100" step="0.01" onchange="calcularTotal()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Valor Desconto</label>
                                    <input type="text" class="form-control" id="valor_desconto" readonly>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Total</label>
                                    <input type="text" class="form-control fw-bold fs-5" id="valor_total" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-shopping-cart me-2"></i>Itens</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Loja</th>
                                                <th>Qtd</th>
                                                <th>Preço</th>
                                                <th>Subtotal</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itens_pedido">';

            foreach ($itens as $item) {
                echo '<tr class="item-row" data-item-id="' . (int) ($item['id'] ?? 0) . '" data-produto-id="' . (int) ($item['produto_id'] ?? 0) . '" data-nome-produto="' . htmlspecialchars((string) ($item['nome_produto'] ?? '')) . '" data-nome-produto-sku="' . htmlspecialchars((string) ($item['nome_produto_sku'] ?? '')) . '" data-loja="' . htmlspecialchars((string) ($item['loja'] ?? 'outro')) . '">
                        <td>
                            <div class="d-flex align-items-start gap-1">
                                <div class="flex-grow-1">
                                    <strong class="item-nome-display">' . htmlspecialchars((string) ($item['nome_produto'] ?? '')) . '</strong>
                                    <input type="text" class="form-control form-control-sm item-nome-input" value="' . htmlspecialchars((string) ($item['nome_produto'] ?? '')) . '" style="display:none;">
                                    <br><small class="text-muted">SKU: ' . htmlspecialchars((string) ($item['nome_produto_sku'] ?? 'N/A')) . '</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="editarNomeItem(this)" title="Editar nome do produto">
                                    <i class="fas fa-pencil-alt" style="font-size:11px;"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">' . ucfirst((string) ($item['loja'] ?? 'outro')) . '</span>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm quantidade" value="' . (int) ($item['quantidade'] ?? 0) . '" min="1" onchange="atualizarSubtotal(this)" ' . (!$canEditItens ? 'readonly' : '') . '>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm preco_unitario" value="' . (float) ($item['preco_unitario'] ?? 0) . '" min="0" step="0.01" onchange="atualizarSubtotal(this)" ' . (!$canEditItens ? 'readonly' : '') . '>
                        </td>
                        <td class="subtotal">R$ ' . number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.') . '</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removerItem(this)" ' . (!$canEditItens ? 'disabled' : '') . '>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>';
            }

            echo '</tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-primary" onclick="abrirModalAdicionarProduto()" ' . (!$canEditItens ? 'disabled' : '') . '>
                                    <i class="fas fa-plus me-2"></i>Adicionar Produto
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="modalAdicionarProduto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="busca_produto" placeholder="Buscar produto..." oninput="buscarProdutos()">
                    </div>
                    <div class="row" id="lista_produtos">';

            foreach (($produtos ?? []) as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $nome = (string) ($p['name'] ?? '');
                $preco = (float) ($p['price'] ?? 0);
                // Converter preço USD para moeda do pedido
                if ($moedaPedido === 'BRL' && $preco > 0) {
                    $preco = round($preco * $taxaConvPedido, 2);
                }
                $sku = (string) ($p['sku'] ?? '');
                $loja = (string) ($p['loja'] ?? '');

                $jsNome = htmlspecialchars(json_encode($nome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                $jsSku = htmlspecialchars(json_encode($sku, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                $jsLoja = htmlspecialchars(json_encode($loja, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

                echo '<div class="col-md-6 mb-2">'
                    . '<div class="card">'
                    . '<div class="card-body">'
                    . '<div class="d-flex justify-content-between align-items-start gap-2">'
                    . '<div>'
                    . '<div style="font-weight:700;">' . htmlspecialchars($nome) . '</div>'
                    . '<div class="small text-muted">SKU: ' . htmlspecialchars($sku !== '' ? $sku : 'N/A') . '</div>'
                    . (!empty($loja) ? ('<div class="small text-muted">Loja: ' . htmlspecialchars($loja) . '</div>') : '')
                    . '</div>'
                    . '<div class="text-end">'
                    . '<div class="small text-muted">R$</div>'
                    . '<div style="font-weight:800;">' . number_format($preco, 2, ',', '.') . '</div>'
                    . '</div>'
                    . '</div>'
                    . '<div class="mt-2">'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick="selecionarProduto(' . (int) $pid . ', ' . $jsNome . ', ' . (float) $preco . ', ' . $jsSku . ', ' . $jsLoja . ')">Adicionar</button>'
                    . '</div>'
                    . '</div>'
                    . '</div>'
                    . '</div>';
            }

            echo '        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            const pedidoId = ' . (int) $id . ';
            const canEditItens = ' . ($canEditItens ? 'true' : 'false') . ';
            const temPagamentoAsaas = ' . ($temPagamentoAsaas ? 'true' : 'false') . ';
            const totalOriginalPedido = ' . json_encode((float) ($pedido['total'] ?? ($pedido['valor_total'] ?? 0))) . ';

            // Guardar URL de origem (listagem de pedidos com paginação/filtros)
            try {
                // Prioridade 1: returnUrl passado via query string
                var urlParams = new URLSearchParams(window.location.search);
                var returnUrlParam = urlParams.get("returnUrl");
                if (returnUrlParam) {
                    sessionStorage.setItem("brz_pedidos_returnUrl", returnUrlParam);
                } else {
                    // Prioridade 2: document.referrer se vier da listagem diretamente
                    var ref = document.referrer || "";
                    if (ref && ref.indexOf("/admin/pedidos") !== -1 && ref.indexOf("/editar/") === -1) {
                        sessionStorage.setItem("brz_pedidos_returnUrl", ref);
                    }
                }
            } catch(e){}

            window.abrirModalAdicionarProduto = function(){
                if (!canEditItens) return;
                const el = document.getElementById("modalAdicionarProduto");
                if (!el) {
                    alert("Modal de produto não encontrado na página. Atualize a página com Ctrl+F5 e tente novamente.");
                    return;
                }
                try {
                    const inst = bootstrap.Modal.getOrCreateInstance(el);
                    inst.show();
                } catch (e) {
                    console.error(e);
                }
            };

            window.calcularTotal = function(){
                let subtotal = 0;
                document.querySelectorAll(".item-row").forEach(function(row){
                    const qtdEl = row.querySelector(".quantidade");
                    const precoEl = row.querySelector(".preco_unitario");
                    const qtd = parseFloat(qtdEl ? qtdEl.value : 0) || 0;
                    const preco = parseFloat(precoEl ? precoEl.value : 0) || 0;
                    subtotal += qtd * preco;
                });

                const frete = parseFloat(document.getElementById("valor_frete")?.value || 0) || 0;
                const desconto = parseFloat(document.getElementById("percentual_desconto")?.value || 0) || 0;
                const valorDesconto = subtotal * (desconto / 100);
                const total = subtotal + frete - valorDesconto;

                const setVal = function(id, v){
                    const el = document.getElementById(id);
                    if (el) el.value = v;
                };

                setVal("subtotal_produtos", "R$ " + subtotal.toFixed(2).replace(".", ","));
                setVal("valor_desconto", "R$ " + valorDesconto.toFixed(2).replace(".", ","));
                setVal("valor_total", "R$ " + total.toFixed(2).replace(".", ","));

                // Atualizar diferença calculada
                const difCalc = document.getElementById("diferenca_calculada");
                if (difCalc) {
                    const dif = Math.max(0, total - totalOriginalPedido);
                    difCalc.value = "R$ " + dif.toFixed(2).replace(".", ",");
                    difCalc.style.color = dif > 0 ? "#198754" : "";
                }
            };

            window.atualizarSubtotal = function(input){
                const row = input.closest(".item-row");
                if (!row) return;
                const qtd = parseFloat(row.querySelector(".quantidade")?.value || 0) || 0;
                const preco = parseFloat(row.querySelector(".preco_unitario")?.value || 0) || 0;
                const subtotal = qtd * preco;
                const cell = row.querySelector(".subtotal");
                if (cell) cell.textContent = "R$ " + subtotal.toFixed(2).replace(".", ",");
                window.calcularTotal();
            };

            window.removerItem = function(btn){
                if (!canEditItens) return;
                if (confirm("Tem certeza que deseja remover este item?")) {
                    const row = btn.closest(".item-row");
                    if (row) row.remove();
                    window.calcularTotal();
                }
            };

            window.buscarProdutos = function(){
                const termo = (document.getElementById("busca_produto")?.value || "").toLowerCase();
                document.querySelectorAll("#lista_produtos .col-md-6").forEach(function(card){
                    const texto = (card.textContent || "").toLowerCase();
                    card.style.display = texto.includes(termo) ? "block" : "none";
                });
            };

            window.selecionarProduto = function(id, nome, preco, sku, loja){
                if (!canEditItens) return;
                const tbody = document.getElementById("itens_pedido");
                if (!tbody) return;
                const newRow = tbody.insertRow();
                newRow.className = "item-row";
                newRow.setAttribute("data-produto-id", id);
                newRow.setAttribute("data-nome-produto", nome);
                newRow.setAttribute("data-nome-produto-sku", sku);
                newRow.setAttribute("data-loja", loja || "outro");

                newRow.innerHTML =
                    "<td><strong>" + nome + "</strong><br><small class=\"text-muted\">SKU: " + sku + "</small></td>" +
                    "<td><span class=\"badge bg-secondary\">" + (loja || "outro") + "</span></td>" +
                    "<td><input type=\"number\" class=\"form-control form-control-sm quantidade\" value=\"1\" min=\"1\" onchange=\"atualizarSubtotal(this)\"></td>" +
                    "<td><input type=\"number\" class=\"form-control form-control-sm preco_unitario\" value=\"" + Number(preco) + "\" min=\"0\" step=\"0.01\" onchange=\"atualizarSubtotal(this)\"></td>" +
                    "<td class=\"subtotal\">R$ " + Number(preco).toFixed(2).replace(".", ",") + "</td>" +
                    "<td><button type=\"button\" class=\"btn btn-sm btn-danger\" onclick=\"removerItem(this)\"><i class=\"fas fa-trash\"></i></button></td>";

                try {
                    const modalEl = document.getElementById("modalAdicionarProduto");
                    const inst = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                    if (inst) inst.hide();
                } catch (e) {}
                window.calcularTotal();
            };

            window.salvarPedido = function(){
                const itens = [];
                document.querySelectorAll(".item-row").forEach(function(row){
                    const item = {
                        quantidade: row.querySelector(".quantidade")?.value,
                        preco_unitario: row.querySelector(".preco_unitario")?.value,
                        produto_id: row.dataset.produtoId || "",
                        nome_produto: row.dataset.nomeProduto || "",
                        nome_produto_sku: row.dataset.nomeProdutoSku || "",
                        loja: row.dataset.loja || "outro"
                    };
                    if (row.dataset.itemId) item.id = row.dataset.itemId;
                    itens.push(item);
                });

                const dados = {
                    pedido_id: pedidoId,
                    status: document.getElementById("pedido_status")?.value,
                    peso_total: document.getElementById("pedido_peso_total")?.value,
                    altura: document.getElementById("pedido_altura")?.value,
                    largura: document.getElementById("pedido_largura")?.value,
                    comprimento: document.getElementById("pedido_comprimento")?.value,
                    frete: document.getElementById("valor_frete")?.value,
                    desconto: document.getElementById("percentual_desconto")?.value,
                    observacao_vendedor: document.getElementById("observacao_vendedor")?.value,
                    tracking_code: document.getElementById("tracking_code")?.value,
                    itens: itens
                };

                fetch("/admin/pedidos/salvar", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify(dados)
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var st = document.getElementById("pedido_status")?.value || "";
                        var label = st.replace(/_/g, " ");
                        sessionStorage.setItem("brz_pedidos_flash", "Pedido #" + pedidoId + " salvo com sucesso" + (label ? " — Status: " + label : ""));
                        var returnUrl = sessionStorage.getItem("brz_pedidos_returnUrl") || "/admin/pedidos";
                        // Adicionar âncora para rolar até o pedido na listagem
                        if (returnUrl.indexOf("#") === -1) {
                            returnUrl += "#pedido-" + pedidoId;
                        }
                        window.location.href = returnUrl;
                        return;
                    }
                    alert("Erro: " + (data.message || "Falha ao salvar"));
                })
                .catch(function(){
                    alert("Erro ao salvar pedido");
                });
            };

            window.atualizarSomenteStatus = function(){
                const st = document.getElementById("pedido_status")?.value;
                if (!st) return;

                // Coletar medidas junto com o status
                const itens = [];
                document.querySelectorAll(".item-row").forEach(function(row){
                    const item = {
                        quantidade: row.querySelector(".quantidade")?.value,
                        preco_unitario: row.querySelector(".preco_unitario")?.value,
                        produto_id: row.dataset.produtoId || "",
                        nome_produto: row.dataset.nomeProduto || "",
                        nome_produto_sku: row.dataset.nomeProdutoSku || "",
                        loja: row.dataset.loja || "outro"
                    };
                    if (row.dataset.itemId) item.id = row.dataset.itemId;
                    itens.push(item);
                });

                const dados = {
                    pedido_id: pedidoId,
                    status: st,
                    peso_total: document.getElementById("pedido_peso_total")?.value,
                    altura: document.getElementById("pedido_altura")?.value,
                    largura: document.getElementById("pedido_largura")?.value,
                    comprimento: document.getElementById("pedido_comprimento")?.value,
                    frete: document.getElementById("valor_frete")?.value,
                    desconto: document.getElementById("percentual_desconto")?.value,
                    observacao_vendedor: document.getElementById("observacao_vendedor")?.value,
                    tracking_code: document.getElementById("tracking_code")?.value,
                    itens: itens
                };

                fetch("/admin/pedidos/salvar", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify(dados)
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        sessionStorage.setItem("brz_pedidos_flash", "Status do pedido #" + pedidoId + " atualizado para: " + st.replace(/_/g, " "));
                        var returnUrl = sessionStorage.getItem("brz_pedidos_returnUrl") || "/admin/pedidos";
                        window.location.href = returnUrl;
                        return;
                    }
                    alert("Erro: " + (data.message || "Falha ao atualizar status"));
                })
                .catch(function(){
                    alert("Erro ao atualizar status");
                });
            };

            window.gerarLinkDiferenca = function(){
                const box = document.getElementById("box_link_diferenca");
                if (!box) return;
                box.style.display = "block";
                box.className = "alert alert-info";
                box.textContent = "Gerando link de pagamento...";

                var inp = document.getElementById("diferenca_valor");
                var valManual = inp ? inp.value.trim() : "";

                fetch("/admin/pedidos/gerar-link-diferenca", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ pedido_id: pedidoId, valor_manual: valManual })
                })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.success) {
                            box.className = "alert alert-danger";
                            box.textContent = (data && data.error) ? data.error : "Erro ao gerar link";
                            return;
                        }
                        const link = data.public_url || "";
                        const fullLink = window.location.origin + link;
                        box.className = "alert alert-success";
                        var html = "<div class=\\"fw-bold\\">Link de pagamento gerado</div>";
                        html += "<div class=\\"small mt-1\\">Diferença: <strong>R$ " + Number(data.diferenca).toFixed(2).replace(".", ",") + "</strong></div>";
                        if (link) {
                            html += "<div class=\\"mt-2 d-flex gap-2 align-items-center\\">";
                            html += "<input type=\\"text\\" class=\\"form-control form-control-sm\\" id=\\"link_diferenca_url\\" value=\\"" + fullLink + "\\" readonly onclick=\\"this.select()\\" style=\\"max-width:400px;\\">";
                            html += "<button class=\\"btn btn-sm btn-outline-dark\\" id=\\"btn_copiar_link\\">Copiar</button>";
                            html += "<a class=\\"btn btn-sm btn-outline-primary\\" href=\\"" + link + "\\" target=\\"_blank\\" rel=\\"noopener\\">Abrir</a>";
                            html += "</div>";
                        }
                        box.innerHTML = html;
                        if (link) {
                            var btnCopiar = document.getElementById("btn_copiar_link");
                            if (btnCopiar) {
                                btnCopiar.addEventListener("click", function(){
                                    navigator.clipboard.writeText(fullLink).then(function(){
                                        btnCopiar.textContent = "Copiado!";
                                        setTimeout(function(){ btnCopiar.textContent = "Copiar"; }, 1500);
                                    });
                                });
                            }
                        }
                    })
                    .catch(function(){
                        box.className = "alert alert-danger";
                        box.textContent = "Erro ao gerar o link de pagamento.";
                    });
            };

            window.gerarCobrancaDiferenca = function(){
                return window.gerarLinkDiferenca();
            };

            window.calcularTotal();

            window.editarNomeItem = function(btn){
                var td = btn.closest("td");
                if (!td) return;
                var display = td.querySelector(".item-nome-display");
                var input = td.querySelector(".item-nome-input");
                if (!display || !input) return;

                if (input.style.display === "none") {
                    input.value = display.textContent.trim();
                    display.style.display = "none";
                    input.style.display = "";
                    input.focus();
                    input.select();
                    btn.innerHTML = "<i class=\"fas fa-check\" style=\"font-size:11px;\"></i>";
                    btn.classList.remove("btn-outline-secondary");
                    btn.classList.add("btn-success");
                } else {
                    var novoNome = input.value.trim();
                    if (novoNome === "") {
                        alert("O nome não pode ficar vazio.");
                        input.focus();
                        return;
                    }
                    display.textContent = novoNome;
                    display.style.display = "";
                    input.style.display = "none";
                    btn.innerHTML = "<i class=\"fas fa-pencil-alt\" style=\"font-size:11px;\"></i>";
                    btn.classList.remove("btn-success");
                    btn.classList.add("btn-outline-secondary");

                    var row = btn.closest(".item-row");
                    if (row) row.dataset.nomeProduto = novoNome;
                }
            };
        })();
    </script>
</body>
</html>';

    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
        exit;

    }

    }

    public function gerarLinkDiferenca($request) {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $dados = json_decode(file_get_contents('php://input'), true);
            $pedidoId = (int) ($dados['pedido_id'] ?? 0);
            $valorManual = trim((string) ($dados['valor_manual'] ?? ''));

            if ($pedidoId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Pedido inválido']);
                return;
            }

            $auth = new AuthService();
            $admin = $auth->getUsuarioLogado();
            $adminId = (int) ($admin['id'] ?? 0);

            // Buscar pedido
            $st = $this->connection->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            $pedido = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                echo json_encode(['success' => false, 'error' => 'Pedido não encontrado']);
                return;
            }

            $totalAtual = (float) ($pedido['total'] ?? ($pedido['valor_total'] ?? 0));
            $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL')));
            if ($moeda === '') $moeda = 'BRL';

            // Calcular subtotal atual dos itens
            $itensTable = $this->getItensTableForPedido($pedidoId);
            $colsItens = $this->getColsFromTable($itensTable);
            $colSub = in_array('subtotal', $colsItens, true) ? 'subtotal' : null;
            $subtotalItens = 0.0;
            if ($colSub) {
                $stSub = $this->connection->prepare("SELECT COALESCE(SUM({$colSub}), 0) FROM {$itensTable} WHERE pedido_id = ?");
                $stSub->execute([$pedidoId]);
                $subtotalItens = (float) ($stSub->fetchColumn() ?: 0);
            }

            // Calcular diferença
            $diferenca = 0.0;
            if ($valorManual !== '' && is_numeric(str_replace(',', '.', $valorManual))) {
                $diferenca = (float) str_replace(',', '.', $valorManual);
            } else {
                // Diferença = total atual dos itens recalculado - total original do pedido
                $diferenca = round(max(0, $subtotalItens - $totalAtual), 2);
                if ($diferenca <= 0) {
                    // Tentar: novo total (subtotal + taxas) - total original
                    $taxaServico = (float) ($pedido['servicos'] ?? ($pedido['taxa_servico'] ?? 0));
                    $impostos = (float) ($pedido['impostos'] ?? ($pedido['valor_impostos'] ?? 0));
                    $frete = (float) ($pedido['frete'] ?? ($pedido['valor_frete'] ?? 0));
                    $novoTotal = $subtotalItens + $taxaServico + $impostos + $frete;
                    $diferenca = round(max(0, $novoTotal - $totalAtual), 2);
                }
            }

            if ($diferenca <= 0) {
                echo json_encode(['success' => false, 'error' => 'Sem diferença a cobrar. Adicione itens e salve o pedido primeiro.']);
                return;
            }

            // Buscar itens novos (os que foram adicionados após o pedido original)
            // Para simplificar, usamos a descrição genérica
            $codigoPedido = (string) ($pedido['codigo_pedido'] ?? ($pedido['numero_pedido'] ?? $pedidoId));
            $descricao = 'Diferença pedido #' . $codigoPedido;

            // Criar Payment Link via PaymentLinkService
            $svc = new \App\Services\PaymentLinkService();
            $result = $svc->createLink([
                'currency' => $moeda,
                'products' => [
                    ['name' => $descricao, 'value' => $diferenca]
                ],
                'taxa_servico_valor' => 0,
                'impostos_valor' => 0,
                'descricao' => $descricao,
            ], $adminId);

            if (empty($result['success'])) {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Falha ao criar link']);
                return;
            }

            // Registrar no pedido quem gerou o link (para comissão)
            try {
                $colsPed = $this->getColsFromTable('pedidos');
                $sets = [];
                $params = [':id' => $pedidoId];

                // Garantir colunas de diferença existam
                $needCols = [
                    'diferenca_link_id' => "ALTER TABLE pedidos ADD COLUMN diferenca_link_id INT NULL",
                    'diferenca_valor' => "ALTER TABLE pedidos ADD COLUMN diferenca_valor DECIMAL(10,2) NULL",
                    'diferenca_vendedor_id' => "ALTER TABLE pedidos ADD COLUMN diferenca_vendedor_id INT NULL",
                    'diferenca_created_at' => "ALTER TABLE pedidos ADD COLUMN diferenca_created_at DATETIME NULL",
                ];
                foreach ($needCols as $col => $ddl) {
                    if (!in_array($col, $colsPed, true)) {
                        try { $this->connection->exec($ddl); } catch (\Exception $e) {}
                    }
                }

                $sets[] = 'diferenca_link_id = :lid';
                $params[':lid'] = (int) $result['id'];
                $sets[] = 'diferenca_valor = :dval';
                $params[':dval'] = $diferenca;
                $sets[] = 'diferenca_vendedor_id = :vid';
                $params[':vid'] = $adminId;
                $sets[] = 'diferenca_created_at = NOW()';

                if (!empty($sets)) {
                    $stUpd = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = :id');
                    $stUpd->execute($params);
                }
            } catch (\Exception $e) {
                error_log('[DIFERENCA_LINK] Erro ao registrar no pedido: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'diferenca' => $diferenca,
                'link_id' => $result['id'],
                'public_url' => $result['public_url'],
                'token' => $result['token'],
                'moeda' => $moeda,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function salvar($request) {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            $pedidoId = (int) ($dados['pedido_id'] ?? 0);
            if ($pedidoId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Pedido inválido']);
                return;
            }

            $auth = new AuthService();
            $usuarioLogado = $auth->getUsuarioLogado();
            $audUsuarioId = (int) ($usuarioLogado['id'] ?? 0);

            $oldPedido = [];
            try {
                $stOld = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
                $stOld->execute([':id' => $pedidoId]);
                $oldPedido = $stOld->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $oldPedido = [];
            }

            $oldItensResumo = [];
            try {
                $tOld = $this->getItensTableForPedido($pedidoId);
                $stOldItens = $this->connection->prepare('SELECT produto_id, quantidade, preco_unitario, subtotal FROM ' . $tOld . ' WHERE pedido_id = :id');
                $stOldItens->execute([':id' => $pedidoId]);
                $oldItensResumo = $stOldItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $oldItensResumo = [];
            }

            $oldStatus = '';
            try {
                $stmtOld = $this->connection->prepare('SELECT status FROM pedidos WHERE id = :id LIMIT 1');
                $stmtOld->execute([':id' => $pedidoId]);
                $oldStatus = (string) ($stmtOld->fetchColumn() ?: '');
            } catch (\Exception $e) {
                $oldStatus = '';
            }

            $statusOldNorm = strtolower(trim((string) $oldStatus));
            $cicloFechadoOld = in_array($statusOldNorm, [
                'produto_consolidado',
                'em_transporte',
                'aguardando_liberacao_aduaneira',
                'enviado_ao_destinatario',
                'enviado',
                'entregue',
            ], true);

            $oldQtyByProduto = [];
            if ($cicloFechadoOld) {
                $tOld = $this->getItensTableForPedido($pedidoId);
                try {
                    $stmtOldItens = $this->connection->prepare('SELECT produto_id, quantidade FROM ' . $tOld . ' WHERE pedido_id = :id');
                    $stmtOldItens->execute([':id' => $pedidoId]);
                    $rows = $stmtOldItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows as $r) {
                        $pid = (int) ($r['produto_id'] ?? 0);
                        $q = (int) ($r['quantidade'] ?? 0);
                        if ($pid <= 0 || $q <= 0) continue;
                        $oldQtyByProduto[$pid] = ($oldQtyByProduto[$pid] ?? 0) + $q;
                    }
                } catch (\Exception $e) {
                    $oldQtyByProduto = [];
                }
            }

            // Se estiver pago, permite salvar status e medidas, mas não altera itens
            $isPago = strtolower(trim($oldStatus)) === 'pago';

            $newStatus = (string) ($dados['status'] ?? '');
            $cicloFechado = in_array($newStatus, [
                'produto_consolidado',
                'em_transporte',
                'aguardando_liberacao_aduaneira',
                'enviado_ao_destinatario',
                'enviado',
                'entregue',
            ], true);

            $this->ensurePedidoMedidasColumns();

            $pesoTotal = (float) str_replace(',', '.', (string) ($dados['peso_total'] ?? '0'));
            $altura = (float) str_replace(',', '.', (string) ($dados['altura'] ?? '0'));
            $largura = (float) str_replace(',', '.', (string) ($dados['largura'] ?? '0'));
            $comprimento = (float) str_replace(',', '.', (string) ($dados['comprimento'] ?? '0'));

            if ($cicloFechado) {
                if ($pesoTotal <= 0 || $altura <= 0 || $largura <= 0 || $comprimento <= 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Para marcar como Caixa Fechada (ou status seguintes), preencha Peso real (kg), Altura, Largura e Comprimento.'
                    ]);
                    return;
                }
            }
            $statusReserva = $cicloFechado ? 'finalizada' : 'ativa';

            // Garantir coluna nome_produto nas tabelas de itens (DDL antes da transação)
            $itensTablesPreCheck = [];
            if ($this->tableExists('pedido_itens')) $itensTablesPreCheck[] = 'pedido_itens';
            if ($this->tableExists('pedido_items')) $itensTablesPreCheck[] = 'pedido_items';
            if (empty($itensTablesPreCheck)) $itensTablesPreCheck[] = $this->getItensTable();
            foreach ($itensTablesPreCheck as $t) {
                if (!$this->columnExists($t, 'nome_produto')) {
                    try { $this->connection->exec("ALTER TABLE {$t} ADD COLUMN nome_produto VARCHAR(255) DEFAULT NULL"); } catch (\Exception $e) {}
                }
            }

            $this->connection->beginTransaction();

            if ($cicloFechadoOld && !empty($oldQtyByProduto) && $this->tableExists('estoque_interno')) {
                $newQtyByProduto = [];
                foreach (($dados['itens'] ?? []) as $it) {
                    $pid = (int) ($it['produto_id'] ?? 0);
                    $q = (int) ($it['quantidade'] ?? 0);
                    if ($pid <= 0 || $q <= 0) continue;
                    $newQtyByProduto[$pid] = ($newQtyByProduto[$pid] ?? 0) + $q;
                }

                $colsEstoque = $this->getColsFromTable('estoque_interno');
                $hasProdutoId = in_array('produto_id', $colsEstoque, true);
                $hasQuantidade = in_array('quantidade', $colsEstoque, true);
                if ($hasProdutoId && $hasQuantidade) {
                    foreach ($oldQtyByProduto as $produtoId => $qOld) {
                        $qNew = (int) ($newQtyByProduto[$produtoId] ?? 0);
                        $delta = (int) $qOld - (int) $qNew;
                        if ($delta <= 0) {
                            continue;
                        }

                        try {
                            $stmtPick = $this->connection->prepare('SELECT id, quantidade FROM estoque_interno WHERE produto_id = :produto_id ORDER BY id ASC LIMIT 1');
                            $stmtPick->execute([':produto_id' => (int) $produtoId]);
                            $loc = $stmtPick->fetch(\PDO::FETCH_ASSOC);
                            if (is_array($loc) && (int) ($loc['id'] ?? 0) > 0) {
                                $locId = (int) $loc['id'];
                                $qAtual = (int) ($loc['quantidade'] ?? 0);
                                $stmtUp = $this->connection->prepare('UPDATE estoque_interno SET quantidade = :q WHERE id = :id LIMIT 1');
                                $stmtUp->execute([':q' => ($qAtual + $delta), ':id' => $locId]);
                            } else {
                                $insertCols = ['produto_id', 'quantidade'];
                                $insertVals = [':produto_id', ':quantidade'];
                                $params = [':produto_id' => (int) $produtoId, ':quantidade' => (int) $delta];

                                if (in_array('created_at', $colsEstoque, true)) {
                                    $insertCols[] = 'created_at';
                                    $insertVals[] = 'NOW()';
                                }

                                $sqlIns = 'INSERT INTO estoque_interno (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $insertVals) . ')';
                                $stmtIns = $this->connection->prepare($sqlIns);
                                $stmtIns->execute($params);
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }
            }

            // Recalcular reservas/pendências baseado no novo estado do pedido
            $this->limparReservasEPendenciasDoPedido($pedidoId);

            // Primeiro, remover todos os itens existentes do pedido (sincronizar tabelas quando ambas existirem)
            $itensTables = [];
            if ($this->tableExists('pedido_itens')) $itensTables[] = 'pedido_itens';
            if ($this->tableExists('pedido_items')) $itensTables[] = 'pedido_items';
            if (empty($itensTables)) $itensTables[] = $this->getItensTable();

            $subtotal = 0;

            if (!$isPago) {
            foreach ($itensTables as $t) {
                $stmt = $this->connection->prepare("DELETE FROM {$t} WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            }

            // Calcular subtotal e inserir novos itens
            foreach (($dados['itens'] ?? []) as $item) {
                $subtotalItem = ((float) ($item['quantidade'] ?? 0)) * ((float) ($item['preco_unitario'] ?? 0));
                $subtotal += $subtotalItem;

                // Inserir novo item em todas as tabelas existentes (com colunas dinâmicas)
                foreach ($itensTables as $t) {
                    $cols = $this->getColsFromTable($t);
                    if (empty($cols)) {
                        continue;
                    }

                    $insertCols = [];
                    $insertVals = [];
                    $params = [];

                    $map = [
                        'pedido_id' => $pedidoId,
                        'produto_id' => (int) ($item['produto_id'] ?? 0),
                        'quantidade' => (int) ($item['quantidade'] ?? 0),
                        'preco_unitario' => (float) ($item['preco_unitario'] ?? 0),
                        'subtotal' => (float) $subtotalItem,
                        'nome_produto' => (string) ($item['nome_produto'] ?? ''),
                        'nome_produto_sku' => (string) ($item['nome_produto_sku'] ?? ''),
                        'loja' => (string) ($item['loja'] ?? ''),
                    ];

                    foreach ($map as $c => $v) {
                        if (in_array($c, $cols, true)) {
                            $insertCols[] = $c;
                            $ph = ':' . $c;
                            $insertVals[] = $ph;
                            $params[$ph] = $v;
                        }
                    }

                    if (in_array('created_at', $cols, true)) {
                        $insertCols[] = 'created_at';
                        $insertVals[] = 'NOW()';
                    }

                    if (empty($insertCols)) {
                        continue;
                    }

                    $sql = 'INSERT INTO ' . $t . ' (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);
                }

                // Estoque: reservar o que houver disponível e gerar pendência só para o que faltar
                $produtoId = (int) ($item['produto_id'] ?? 0);
                $qtdPedido = (int) ($item['quantidade'] ?? 0);
                if ($produtoId > 0 && $qtdPedido > 0) {
                    $estoqueTotal = $this->getTotalEstoqueProduto($produtoId);
                    $reservadoOutros = $this->getTotalReservadoAtivoProdutoSemPedido($produtoId, $pedidoId);
                    $disponivel = $estoqueTotal - $reservadoOutros;
                    if ($disponivel < 0) {
                        $disponivel = 0;
                    }

                    $reservar = $qtdPedido;
                    if ($reservar > $disponivel) {
                        $reservar = $disponivel;
                    }
                    if ($reservar > 0) {
                        $this->upsertReserva($pedidoId, $produtoId, $reservar, $statusReserva);
                    }
                    $faltante = $qtdPedido - $reservar;
                    if (!$cicloFechado && $faltante > 0) {
                        // Se o nome do produto foi customizado, verificar se difere do nome original
                        $nomeProdutoCustom = '';
                        $nomeProdutoItem = trim((string) ($item['nome_produto'] ?? ''));
                        if ($nomeProdutoItem !== '' && $produtoId > 0) {
                            try {
                                $nomeCol = $this->columnExists('produtos', 'name') ? 'name' : ($this->columnExists('produtos', 'nome') ? 'nome' : null);
                                if ($nomeCol) {
                                    $stNome = $this->connection->prepare("SELECT {$nomeCol} FROM produtos WHERE id = ? LIMIT 1");
                                    $stNome->execute([$produtoId]);
                                    $nomeOriginal = trim((string) ($stNome->fetchColumn() ?: ''));
                                    if ($nomeOriginal !== '' && $nomeProdutoItem !== $nomeOriginal) {
                                        $nomeProdutoCustom = $nomeProdutoItem;
                                    }
                                }
                            } catch (\Exception $e) {}
                        }
                        $this->upsertPendenciaCompra($pedidoId, $produtoId, $faltante, $nomeProdutoCustom);
                    }
                }
            }

            } // end if (!$isPago) — processamento de itens

            // Se pago, recalcular subtotal a partir dos itens existentes
            if ($isPago) {
                try {
                    $itensTable = $this->tableExists('pedido_itens') ? 'pedido_itens' : $this->getItensTable();
                    $stSub = $this->connection->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM {$itensTable} WHERE pedido_id = :pid");
                    $stSub->execute([':pid' => $pedidoId]);
                    $subtotal = (float) ($stSub->fetchColumn() ?: 0);
                } catch (\Exception $e) { $subtotal = 0; }
            }

            // Calcular valores
            $frete = (float) ($dados['frete'] ?? 0);
            $percentualDesconto = (float) ($dados['desconto'] ?? 0);
            $valorDesconto = $subtotal * ($percentualDesconto / 100);
            $total = $subtotal + $frete - $valorDesconto;

            // Atualizar pedido
            $colsPedidos = $this->getColsFromTable('pedidos');
            $setParts = [];
            $paramsUpd = [':pedido_id' => $pedidoId];

            $setParts[] = 'status = :status';
            $paramsUpd[':status'] = $newStatus;

            $setParts[] = 'frete = :frete';
            $paramsUpd[':frete'] = $frete;

            $setParts[] = 'subtotal = :subtotal';
            $paramsUpd[':subtotal'] = $subtotal;

            $setParts[] = 'total = :total';
            $paramsUpd[':total'] = $total;

            if (is_array($colsPedidos) && in_array('observacao_vendedor', $colsPedidos, true)) {
                $setParts[] = 'observacao_vendedor = :observacao_vendedor';
                $paramsUpd[':observacao_vendedor'] = (string) ($dados['observacao_vendedor'] ?? '');
            }

            // Tracking code (código de rastreio)
            if (array_key_exists('tracking_code', $dados)) {
                $trackingCandidates = ['tracking_code', 'codigo_rastreio', 'rastreamento', 'tracking'];
                $trackingCol = null;
                foreach ($trackingCandidates as $candidate) {
                    if (is_array($colsPedidos) && in_array($candidate, $colsPedidos, true)) {
                        $trackingCol = $candidate;
                        break;
                    }
                }
                if ($trackingCol) {
                    $trackingValue = trim((string) ($dados['tracking_code'] ?? ''));
                    $setParts[] = $trackingCol . ' = :tracking_code';
                    $paramsUpd[':tracking_code'] = $trackingValue !== '' ? $trackingValue : null;
                }
            }

            if (is_array($colsPedidos) && in_array('peso_total', $colsPedidos, true)) {
                $setParts[] = 'peso_total = :peso_total';
                $paramsUpd[':peso_total'] = $pesoTotal > 0 ? $pesoTotal : null;
            }
            if (is_array($colsPedidos) && in_array('altura', $colsPedidos, true)) {
                $setParts[] = 'altura = :altura';
                $paramsUpd[':altura'] = $altura > 0 ? $altura : null;
            }
            if (is_array($colsPedidos) && in_array('largura', $colsPedidos, true)) {
                $setParts[] = 'largura = :largura';
                $paramsUpd[':largura'] = $largura > 0 ? $largura : null;
            }
            if (is_array($colsPedidos) && in_array('comprimento', $colsPedidos, true)) {
                $setParts[] = 'comprimento = :comprimento';
                $paramsUpd[':comprimento'] = $comprimento > 0 ? $comprimento : null;
            }

            // Se marcar como pago/aprovado, manter payment_status/pago_em consistentes (impacta comissões)
            $paidValues = ['pago','paid','approved','aprovado','concluido','concluído','confirmed','received','succeeded','success'];
            $isPaid = in_array(strtolower(trim((string) $newStatus)), $paidValues, true);
            if ($isPaid && is_array($colsPedidos)) {
                if (in_array('payment_status', $colsPedidos, true)) {
                    $setParts[] = 'payment_status = :payment_status';
                    $paramsUpd[':payment_status'] = 'approved';
                }
                if (in_array('pago_em', $colsPedidos, true)) {
                    $setParts[] = 'pago_em = COALESCE(pago_em, NOW())';
                }
            }

            $stmt = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $setParts) . ' WHERE id = :pedido_id');
            $stmt->execute($paramsUpd);

            if ($cicloFechado) {
                $this->finalizarCicloPedido($pedidoId);
            }

            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }

            try {
                $newPedido = [];
                try {
                    $stNew = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
                    $stNew->execute([':id' => $pedidoId]);
                    $newPedido = $stNew->fetch(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $newPedido = [];
                }

                $newItensResumo = [];
                try {
                    $tNew = $this->getItensTableForPedido($pedidoId);
                    $stNewItens = $this->connection->prepare('SELECT produto_id, quantidade, preco_unitario, subtotal FROM ' . $tNew . ' WHERE pedido_id = :id');
                    $stNewItens->execute([':id' => $pedidoId]);
                    $newItensResumo = $stNewItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $newItensResumo = [];
                }

                $oldPick = [];
                $newPick = [];
                foreach (['status', 'peso_total', 'altura', 'largura', 'comprimento', 'total', 'subtotal', 'frete', 'updated_at'] as $k) {
                    if (is_array($oldPedido) && array_key_exists($k, $oldPedido)) $oldPick[$k] = $oldPedido[$k];
                    if (is_array($newPedido) && array_key_exists($k, $newPedido)) $newPick[$k] = $newPedido[$k];
                }
                $oldPick['itens'] = $oldItensResumo;
                $newPick['itens'] = $newItensResumo;

                $auth->registrarLogAuditoria($audUsuarioId > 0 ? $audUsuarioId : null, 'pedido_editado', 'pedidos', (int) $pedidoId, $oldPick, $newPick);
            } catch (\Throwable $e) {
            }

            echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso']);
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
