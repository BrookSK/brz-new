<?php
namespace App\Controllers;

class AdminPedidosEditController {
    private $connection;

    public function __construct() {
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
            // Extrair ID do Request
            $id = $request->getParam('id');
            
            // Buscar pedido diretamente - usando colunas que existem
            $stmt = $this->connection->prepare("
                SELECT p.*, 
                       u.nome as cliente_nome, u.email as cliente_email
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                echo '<div class="alert alert-danger">Pedido não encontrado</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $statusAtual = strtolower(trim((string) ($pedido['status'] ?? '')));
            $bloquearEdicao = ($statusAtual === 'pago');
            
            // Buscar itens do pedido
            $stmt = $this->connection->prepare("
                SELECT 
                    pi.id,
                    pi.pedido_id,
                    pi.produto_id,
                    pi.quantidade,
                    pi.preco_unitario,
                    pi.subtotal,
                    pi.nome_produto,
                    pi.nome_produto_sku,
                    pi.loja,
                    pi.created_at,
                    (SELECT pf.nome_arquivo 
                     FROM produto_fotos pf 
                     WHERE pf.produto_id = pi.produto_id 
                     ORDER BY pf.principal DESC, pf.ordem ASC 
                     LIMIT 1) as imagem_principal
                FROM pedido_itens pi 
                WHERE pi.pedido_id = :id 
                ORDER BY pi.id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Processar itens
            foreach ($itens as &$item) {
                $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
                if (empty($item['nome_produto'])) {
                    $item['nome_produto'] = 'Produto #' . $item['produto_id'];
                }
            }
            
            $pedido['items'] = $itens;
            
            // Obter todos os produtos para adicionar
            $stmt = $this->connection->prepare("SELECT id, name, price, sku, loja FROM produtos WHERE active = 1 ORDER BY name");
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pedido #' . $pedido['codigo_pedido'] . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit me-2"></i>Editar Pedido #' . $pedido['codigo_pedido'] . '</h2>
                    <div>
                        <a href="/admin/pedidos/detalhes/' . $id . '" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                        <button type="button" class="btn btn-success" onclick="salvarPedido()" ' . ($bloquearEdicao ? 'disabled' : '') . '>
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>

                ' . ($bloquearEdicao ? '<div class="alert alert-warning">Este pedido está com status <strong>Pago</strong>. Para editar/adicionar itens e calcular a diferença corretamente, altere primeiro o status para <strong>Pendente</strong> e então edite novamente.</div>' : '') . '
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-info-circle me-2"></i>Informações</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Código</label>
                                    <input type="text" class="form-control" value="' . $pedido['codigo_pedido'] . '" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="pedido_status">
                                        <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="processando" ' . ($pedido['status'] == 'processando' ? 'selected' : '') . '>Processando</option>
                                        <option value="produto_consolidado" ' . ($pedido['status'] == 'produto_consolidado' ? 'selected' : '') . '>Produto Consolidado</option>
                                        <option value="em_transporte" ' . ($pedido['status'] == 'em_transporte' ? 'selected' : '') . '>Em Transporte</option>
                                        <option value="aguardando_liberacao_aduaneira" ' . ($pedido['status'] == 'aguardando_liberacao_aduaneira' ? 'selected' : '') . '>Aguardando Liberação Aduaneira</option>
                                        <option value="enviado_ao_destinatario" ' . ($pedido['status'] == 'enviado_ao_destinatario' ? 'selected' : '') . '>Enviado ao Destinatário</option>
                                        <option value="enviado" ' . ($pedido['status'] == 'enviado' ? 'selected' : '') . '>Enviado</option>
                                        <option value="entregue" ' . ($pedido['status'] == 'entregue' ? 'selected' : '') . '>Entregue</option>
                                        <option value="cancelado" ' . ($pedido['status'] == 'cancelado' ? 'selected' : '') . '>Cancelado</option>
                                    </select>
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
                                    <input type="number" class="form-control" id="valor_frete" value="' . ($pedido['frete'] ?? 0) . '" step="0.01" onchange="calcularTotal()">
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
                                            echo '<tr class="item-row" data-item-id="' . $item['id'] . '" data-produto-id="' . $item['produto_id'] . '" data-nome-produto="' . htmlspecialchars($item['nome_produto']) . '" data-nome-produto-sku="' . htmlspecialchars($item['nome_produto_sku'] ?? '') . '">
                                                <td>
                                                    <strong>' . htmlspecialchars($item['nome_produto']) . '</strong>
                                                    <br><small class="text-muted">SKU: ' . htmlspecialchars($item['nome_produto_sku'] ?? 'N/A') . '</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-' . ($item['loja'] == 'sams' ? 'primary' : ($item['loja'] == 'costco' ? 'success' : 'secondary')) . '">
                                                        ' . ucfirst($item['loja'] ?? 'outro') . '
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm quantidade" value="' . $item['quantidade'] . '" min="1" onchange="atualizarSubtotal(this)">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm preco_unitario" value="' . $item['preco_unitario'] . '" min="0" step="0.01" onchange="atualizarSubtotal(this)">
                                                </td>
                                                <td class="subtotal">' . number_format($item['subtotal'], 2, ',', '.') . '</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removerItem(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                                
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarProduto">
                                    <i class="fas fa-plus me-2"></i>Adicionar Produto
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Adicionar Produto -->
    <div class="modal fade" id="modalAdicionarProduto">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-plus me-2"></i>Adicionar Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="busca_produto" placeholder="Buscar produto..." onkeyup="buscarProdutos()">
                    <div class="row" id="lista_produtos">';
                    
                    foreach ($produtos as $produto) {
                    $lojaBadge = '';
                    $lojaClass = 'secondary';
                    if ($produto['loja'] == 'sams') {
                        $lojaBadge = 'Sams';
                        $lojaClass = 'primary';
                    } elseif ($produto['loja'] == 'costco') {
                        $lojaBadge = 'Costco';
                        $lojaClass = 'success';
                    } else {
                        $lojaBadge = 'Outro';
                    }
                    
                    echo '<div class="col-md-6 mb-3">
                        <div class="card product-card" onclick="selecionarProduto(' . $produto['id'] . ', \'' . htmlspecialchars($produto['name']) . '\', ' . $produto['price'] . ', \'' . htmlspecialchars($produto['sku']) . '\', \'' . $produto['loja'] . '\')">
                            <div class="card-body">
                                <h6>' . htmlspecialchars($produto['name']) . '</h6>
                                <p class="mb-0">
                                    <small class="text-muted">SKU: ' . htmlspecialchars($produto['sku']) . '</small><br>
                                    <span class="badge bg-' . $lojaClass . '">' . $lojaBadge . '</span><br>
                                    <strong>R$ ' . number_format($produto['price'], 2, ',', '.') . '</strong>
                                </p>
                            </div>
                        </div>
                    </div>';
                }
                    
                    echo '</div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pedidoId = ' . $id . ';
        
        function calcularTotal() {
            let subtotal = 0;
            document.querySelectorAll(".item-row").forEach(function(row) {
                let qtd = parseFloat(row.querySelector(".quantidade").value) || 0;
                let preco = parseFloat(row.querySelector(".preco_unitario").value) || 0;
                subtotal += qtd * preco;
            });
            
            let frete = parseFloat(document.getElementById("valor_frete").value) || 0;
            let desconto = parseFloat(document.getElementById("percentual_desconto").value) || 0;
            let valorDesconto = subtotal * (desconto / 100);
            let total = subtotal + frete - valorDesconto;
            
            document.getElementById("subtotal_produtos").value = "R$ " + subtotal.toFixed(2).replace(".", ",");
            document.getElementById("valor_desconto").value = "R$ " + valorDesconto.toFixed(2).replace(".", ",");
            document.getElementById("valor_total").value = "R$ " + total.toFixed(2).replace(".", ",");
        }
        
        function atualizarSubtotal(input) {
            let row = input.closest(".item-row");
            let qtd = parseFloat(row.querySelector(".quantidade").value) || 0;
            let preco = parseFloat(row.querySelector(".preco_unitario").value) || 0;
            let subtotal = qtd * preco;
            row.querySelector(".subtotal").textContent = "R$ " + subtotal.toFixed(2).replace(".", ",");
            calcularTotal();
        }
        
        function removerItem(btn) {
            if (confirm("Tem certeza que deseja remover este item?")) {
                btn.closest(".item-row").remove();
                calcularTotal();
            }
        }
        
        function buscarProdutos() {
            let termo = document.getElementById("busca_produto").value.toLowerCase();
            document.querySelectorAll("#lista_produtos .col-md-6").forEach(function(card) {
                let texto = card.textContent.toLowerCase();
                card.style.display = texto.includes(termo) ? "block" : "none";
            });
        }
        
        function selecionarProduto(id, nome, preco, sku, loja) {
            let tbody = document.getElementById("itens_pedido");
            let newRow = tbody.insertRow();
            newRow.className = "item-row";
            newRow.setAttribute("data-produto-id", id);
            newRow.setAttribute("data-nome-produto", nome);
            newRow.setAttribute("data-nome-produto-sku", sku);
            newRow.setAttribute("data-loja", loja || "outro");
            
            let lojaBadge = "";
            let lojaClass = "secondary";
            if (loja === "sams") {
                lojaBadge = "Sams";
                lojaClass = "primary";
            } else if (loja === "costco") {
                lojaBadge = "Costco";
                lojaClass = "success";
            } else {
                lojaBadge = "Outro";
            }
            
            newRow.innerHTML = 
                "<td><strong>" + nome + "</strong><br><small class=\"text-muted\">SKU: " + sku + "</small></td>" +
                "<td><span class=\"badge bg-" + lojaClass + "\">" + lojaBadge + "</span></td>" +
                "<td><input type=\"number\" class=\"form-control form-control-sm quantidade\" value=\"1\" min=\"1\" onchange=\"atualizarSubtotal(this)\"></td>" +
                "<td><input type=\"number\" class=\"form-control form-control-sm preco_unitario\" value=\"" + preco + "\" min=\"0\" step=\"0.01\" onchange=\"atualizarSubtotal(this)\"></td>" +
                "<td class=\"subtotal\">R$ " + preco.toFixed(2).replace(".", ",") + "</td>" +
                "<td><button type=\"button\" class=\"btn btn-sm btn-danger\" onclick=\"removerItem(this)\"><i class=\"fas fa-trash\"></i></button></td>";
            
            bootstrap.Modal.getInstance(document.getElementById("modalAdicionarProduto")).hide();
            calcularTotal();
        }
        
        function salvarPedido() {
            if (' . ($bloquearEdicao ? 'true' : 'false') . ') {
                alert("Este pedido está com status Pago. Altere para Pendente antes de editar para calcular a diferença corretamente.");
                return;
            }
            let itens = [];
            document.querySelectorAll(".item-row").forEach(function(row) {
                let item = {
                    quantidade: row.querySelector(".quantidade").value,
                    preco_unitario: row.querySelector(".preco_unitario").value
                };
                
                // Se for um item existente, tem data-item-id
                if (row.dataset.itemId) {
                    item.id = row.dataset.itemId;
                    item.produto_id = row.dataset.produtoId || "";
                    item.nome_produto = row.dataset.nomeProduto || "";
                    item.nome_produto_sku = row.dataset.nomeProdutoSku || "";
                    item.loja = row.dataset.loja || "outro";
                } else {
                    // Se for um item novo, pegar dos atributos data
                    item.produto_id = row.dataset.produtoId || "";
                    item.nome_produto = row.dataset.nomeProduto || "";
                    item.nome_produto_sku = row.dataset.nomeProdutoSku || "";
                    item.loja = row.dataset.loja || "outro";
                }
                
                itens.push(item);
            });
            
            let dados = {
                pedido_id: pedidoId,
                status: document.getElementById("pedido_status").value,
                frete: document.getElementById("valor_frete").value,
                desconto: document.getElementById("percentual_desconto").value,
                itens: itens
            };
            
            fetch("/admin/pedidos/salvar", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(dados)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert("Pedido salvo com sucesso!");
                    window.location.href = "/admin/pedidos/detalhes/" + pedidoId;
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(function(error) {
                console.error("Erro:", error);
                alert("Erro ao salvar pedido");
            });
        }
        
        // Inicializar
        calcularTotal();
    </script>
</body>
</html>';
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

            if (strtolower(trim($oldStatus)) === 'pago') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Pedido está com status Pago. Altere para Pendente antes de editar/adicionar itens para calcular a diferença corretamente.'
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

            // Recalcular reservas/pendências baseado no novo estado do pedido
            $this->limparReservasEPendenciasDoPedido($pedidoId);
            
            // Primeiro, remover todos os itens existentes do pedido
            $stmt = $this->connection->prepare("DELETE FROM pedido_itens WHERE pedido_id = :pedido_id");
            $stmt->bindParam(':pedido_id', $dados['pedido_id']);
            $stmt->execute();
            
            // Calcular subtotal e inserir novos itens
            $subtotal = 0;
            foreach ($dados['itens'] as $item) {
                $subtotalItem = $item['quantidade'] * $item['preco_unitario'];
                $subtotal += $subtotalItem;
                
                // Inserir novo item
                $stmt = $this->connection->prepare("
                    INSERT INTO pedido_itens (
                        pedido_id, produto_id, quantidade, preco_unitario, subtotal,
                        nome_produto, nome_produto_sku, loja, created_at
                    ) VALUES (
                        :pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal,
                        :nome_produto, :nome_produto_sku, :loja, NOW()
                    )
                ");
                $stmt->bindParam(':pedido_id', $dados['pedido_id']);
                $stmt->bindParam(':produto_id', $item['produto_id']);
                $stmt->bindParam(':quantidade', $item['quantidade']);
                $stmt->bindParam(':preco_unitario', $item['preco_unitario']);
                $stmt->bindParam(':subtotal', $subtotalItem);
                $stmt->bindParam(':nome_produto', $item['nome_produto']);
                $stmt->bindParam(':nome_produto_sku', $item['nome_produto_sku']);
                $stmt->bindParam(':loja', $item['loja']);
                $stmt->execute();

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
            $frete = floatval($dados['frete']);
            $percentualDesconto = floatval($dados['desconto']);
            $valorDesconto = $subtotal * ($percentualDesconto / 100);
            $total = $subtotal + $frete - $valorDesconto;
            
            // Atualizar pedido - usando colunas corretas (sem updated_at)
            $stmt = $this->connection->prepare("
                UPDATE pedidos SET 
                    status = :status,
                    frete = :frete,
                    subtotal = :subtotal,
                    total = :total
                WHERE id = :pedido_id
            ");
            $stmt->bindParam(':status', $dados['status']);
            $stmt->bindParam(':frete', $frete);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':pedido_id', $dados['pedido_id']);
            $stmt->execute();

            // Fechar ciclo: a partir de "Produto Consolidado" o pedido deixa de contar como demanda.
            if ($cicloFechado) {
                $this->finalizarCicloPedido($pedidoId);
            }
            
            $this->connection->commit();
            
            echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso']);
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
