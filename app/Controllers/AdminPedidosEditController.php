<?php
namespace App\Controllers;

class AdminPedidosEditController {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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
            $stmt = $this->connection->prepare("SELECT id, name, price, sku FROM produtos WHERE active = 1 ORDER BY name");
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
                        <button type="button" class="btn btn-success" onclick="salvarPedido()">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
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
                                    <input type="text" class="form-control" value="' . $pedido['codigo_pedido'] . '" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="pedido_status">
                                        <option value="pendente" ' . ($pedido['status'] == 'pendente' ? 'selected' : '') . '>Pendente</option>
                                        <option value="pago" ' . ($pedido['status'] == 'pago' ? 'selected' : '') . '>Pago</option>
                                        <option value="processando" ' . ($pedido['status'] == 'processando' ? 'selected' : '') . '>Processando</option>
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
                                                <th>Qtd</th>
                                                <th>Preço</th>
                                                <th>Subtotal</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itens_pedido">';
                                        
                                        foreach ($itens as $item) {
                                            echo '<tr class="item-row" data-item-id="' . $item['id'] . '">
                                                <td>
                                                    <strong>' . htmlspecialchars($item['nome_produto']) . '</strong>
                                                    <br><small class="text-muted">SKU: ' . htmlspecialchars($item['nome_produto_sku'] ?? 'N/A') . '</small>
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
                        echo '<div class="col-md-6 mb-3">
                            <div class="card product-card" onclick="selecionarProduto(' . $produto['id'] . ', \'' . htmlspecialchars($produto['name']) . '\', ' . $produto['price'] . ', \'' . htmlspecialchars($produto['sku']) . '\')">
                                <div class="card-body">
                                    <h6>' . htmlspecialchars($produto['name']) . '</h6>
                                    <p class="mb-0">
                                        <small class="text-muted">SKU: ' . htmlspecialchars($produto['sku']) . '</small><br>
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
        
        function selecionarProduto(id, nome, preco, sku) {
            let novaLinha = "<tr class=\\"item-row\\">\\
                <td><strong>" + nome + "</strong><br><small class=\\"text-muted\\">SKU: " + sku + "</small></td>\\
                <td><input type=\\"number\\" class=\\"form-control form-control-sm quantidade\\" value=\\"1\\" min=\\"1\\" onchange=\\"atualizarSubtotal(this)\\"></td>\\
                <td><input type=\\"number\\" class=\\"form-control form-control-sm preco_unitario\\" value=\\"" + preco + "\\" min=\\"0\\" step=\\"0.01\\" onchange=\\"atualizarSubtotal(this)\\"></td>\\
                <td class=\\"subtotal\\">R$ " + preco.toFixed(2).replace(".", ",") + "</td>\\
                <td><button type=\\"button\\" class=\\"btn btn-sm btn-danger\\" onclick=\\"removerItem(this)\\"><i class=\\"fas fa-trash\\"></i></button></td>\\
            </tr>";
            
            document.getElementById("itens_pedido").insertAdjacentHTML("beforeend", novaLinha);
            bootstrap.Modal.getInstance(document.getElementById("modalAdicionarProduto")).hide();
            calcularTotal();
        }
        
        function buscarProdutos() {
            let termo = document.getElementById("busca_produto").value.toLowerCase();
            document.querySelectorAll("#lista_produtos .col-md-6").forEach(function(card) {
                let texto = card.textContent.toLowerCase();
                card.style.display = texto.includes(termo) ? "block" : "none";
            });
        }
        
        function salvarPedido() {
            let itens = [];
            document.querySelectorAll(".item-row").forEach(function(row) {
                itens.push({
                    id: row.dataset.itemId,
                    quantidade: row.querySelector(".quantidade").value,
                    preco_unitario: row.querySelector(".preco_unitario").value
                });
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
            
            $this->connection->beginTransaction();
            
            // Calcular subtotal dos itens
            $subtotal = 0;
            foreach ($dados['itens'] as $item) {
                $subtotal += ($item['quantidade'] * $item['preco_unitario']);
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
            
            // Atualizar itens - sem updated_at
            foreach ($dados['itens'] as $item) {
                $subtotalItem = $item['quantidade'] * $item['preco_unitario'];
                
                $stmt = $this->connection->prepare("
                    UPDATE pedido_itens SET 
                        quantidade = :quantidade,
                        preco_unitario = :preco_unitario,
                        subtotal = :subtotal
                    WHERE id = :id
                ");
                $stmt->bindParam(':quantidade', $item['quantidade']);
                $stmt->bindParam(':preco_unitario', $item['preco_unitario']);
                $stmt->bindParam(':subtotal', $subtotalItem);
                $stmt->bindParam(':id', $item['id']);
                $stmt->execute();
            }
            
            $this->connection->commit();
            
            echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso']);
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
