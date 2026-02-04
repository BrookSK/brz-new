<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminPedidosEditController {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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

    private function upsertPendenciaCompra(int $pedidoId, int $produtoId, int $qtdFaltante): void {
        if ($pedidoId <= 0 || $produtoId <= 0 || $qtdFaltante <= 0) {
            return;
        }
        if (!$this->tableExists('lista_compras')) {
            return;
        }

        $temPedido = $this->columnExists('lista_compras', 'pedido_id');
        try {
            $sqlSel = "SELECT id, quantidade_faltante FROM lista_compras WHERE produto_id = :produto_id AND status = 'pendente'";
            $params = [':produto_id' => $produtoId];
            if ($temPedido) {
                $sqlSel .= ' AND pedido_id = :pedido_id';
                $params[':pedido_id'] = $pedidoId;
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
        if ($this->tableExists('lista_compras') && $this->columnExists('lista_compras', 'pedido_id')) {
            try {
                $stmt = $this->connection->prepare("DELETE FROM lista_compras WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            } catch (\Exception $e) {
            }
        }
    }

    private function finalizarCicloPedido(int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

            renderAdminSidebarStyles();

            echo '
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

            renderAdminSidebar('pedidos');

            echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit me-2"></i>Editar Pedido #' . htmlspecialchars($codigoPedido) . '</h2>
                    <div class="d-flex gap-2">
                        <a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                        <button type="button" class="btn btn-success" onclick="salvarPedido()" ' . (!$canEditItens ? 'disabled' : '') . '>
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>

                ' . (!$canEditItens ? '<div class="alert alert-warning">Este pedido está com status <strong>Pago</strong>. Você pode <strong>alterar o status</strong> abaixo, mas não pode editar/adicionar itens até voltar para <strong>Pendente</strong>.</div>' : '') . '

                <div class="row">
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-bold">Cobrança de diferença</div>
                                    <div class="text-muted small">Gera automaticamente: <strong>(novo total) - (valor já pago)</strong> no Asaas.</div>
                                </div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <input type="number" step="0.01" min="0" class="form-control" id="diferenca_valor" placeholder="Valor (opcional)" style="max-width: 180px;" ' . ($temPagamentoAsaas ? '' : 'disabled') . '>
                                    <button type="button" class="btn btn-outline-dark" onclick="gerarLinkDiferenca()" ' . ($temPagamentoAsaas ? '' : 'disabled') . '>
                                        <i class="fas fa-link me-1"></i>Gerar link da diferença
                                    </button>
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
                                        <option value="pendente" ' . ($statusAtual === 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($statusAtual === 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="processando" ' . ($statusAtual === 'processando' ? 'selected' : '') . '>Processando</option>
                                        <option value="produto_consolidado" ' . ($statusAtual === 'produto_consolidado' ? 'selected' : '') . '>Produto Consolidado</option>
                                        <option value="em_transporte" ' . ($statusAtual === 'em_transporte' ? 'selected' : '') . '>Em Transporte</option>
                                        <option value="aguardando_liberacao_aduaneira" ' . ($statusAtual === 'aguardando_liberacao_aduaneira' ? 'selected' : '') . '>Aguardando Liberação Aduaneira</option>
                                        <option value="enviado_ao_destinatario" ' . ($statusAtual === 'enviado_ao_destinatario' ? 'selected' : '') . '>Enviado ao Destinatário</option>
                                        <option value="enviado" ' . ($statusAtual === 'enviado' ? 'selected' : '') . '>Enviado</option>
                                        <option value="entregue" ' . ($statusAtual === 'entregue' ? 'selected' : '') . '>Entregue</option>
                                        <option value="cancelado" ' . ($statusAtual === 'cancelado' ? 'selected' : '') . '>Cancelado</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary w-100 mt-2" onclick="atualizarSomenteStatus()">
                                        <i class="fas fa-rotate me-1"></i>Atualizar Status
                                    </button>
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
                            <strong>' . htmlspecialchars((string) ($item['nome_produto'] ?? '')) . '</strong>
                            <br><small class="text-muted">SKU: ' . htmlspecialchars((string) ($item['nome_produto_sku'] ?? 'N/A')) . '</small>
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
                if (!canEditItens) return;
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
                    frete: document.getElementById("valor_frete")?.value,
                    desconto: document.getElementById("percentual_desconto")?.value,
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
                        alert("Pedido salvo com sucesso!");
                        window.location.href = "/admin/pedidos/detalhes/" + pedidoId;
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
                window.location.href = "/admin/pedidos/atualizar-status/" + pedidoId + "/" + st;
            };

            window.gerarLinkDiferenca = function(){
                const box = document.getElementById("box_link_diferenca");
                if (!box) return;
                box.style.display = "block";
                box.className = "alert alert-info";
                box.textContent = "Gerando link...";

                var inp = document.getElementById("diferenca_valor");
                var val = inp ? inp.value : "";

                fetch("/admin/estoque/compras/gerar-link-diferenca", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "pedido_id=" + encodeURIComponent(String(pedidoId)) + "&valor=" + encodeURIComponent(String(val))
                })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.success) {
                            box.className = "alert alert-danger";
                            box.textContent = (data && data.message) ? data.message : "Erro ao gerar link";
                            return;
                        }
                        const link = data.bankSlipUrl || data.invoiceUrl || "";
                        box.className = "alert alert-success";
                        box.innerHTML = "<div class=\"fw-bold\">Cobrança gerada</div>" +
                            (data.diferenca ? ("<div class=\"small\">Diferença: <strong>R$ " + Number(data.diferenca).toFixed(2).replace(".", ",") + "</strong></div>") : "") +
                            (link ? ("<div class=\"mt-2\"><a class=\"btn btn-sm btn-outline-dark\" href=\"" + link + "\" target=\"_blank\" rel=\"noopener\">Abrir link</a></div>") : "");
                    })
                    .catch(function(){
                        box.className = "alert alert-danger";
                        box.textContent = "Erro ao gerar a cobrança.";
                    });
            };

            window.gerarCobrancaDiferenca = function(){
                return window.gerarLinkDiferenca();
            };

            window.calcularTotal();
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

    public function salvar($request) {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            $pedidoId = (int) ($dados['pedido_id'] ?? 0);
            if ($pedidoId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Pedido inválido']);
                return;
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

            // Se estiver pago, não permite editar itens aqui (apenas via rota atualizar-status).
            if (strtolower(trim($oldStatus)) === 'pago') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Pedido está com status Pago. Para editar itens, altere para Pendente primeiro. Para alterar apenas o status, use o botão "Atualizar Status".'
                ]);
                return;
            }

            $newStatus = (string) ($dados['status'] ?? '');
            $cicloFechado = in_array($newStatus, [
                'produto_consolidado',
                'em_transporte',
                'aguardando_liberacao_aduaneira',
                'enviado_ao_destinatario',
                'enviado',
                'entregue',
            ], true);
            $statusReserva = $cicloFechado ? 'finalizada' : 'ativa';

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

            foreach ($itensTables as $t) {
                $stmt = $this->connection->prepare("DELETE FROM {$t} WHERE pedido_id = :pedido_id");
                $stmt->execute([':pedido_id' => $pedidoId]);
            }

            // Calcular subtotal e inserir novos itens
            $subtotal = 0;
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
                        $this->upsertPendenciaCompra($pedidoId, $produtoId, $faltante);
                    }
                }
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

            $this->connection->commit();
            echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso']);
        } catch (\Exception $e) {
            try {
                if ($this->connection && $this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
            } catch (\Exception $e2) {
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
