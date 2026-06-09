<?php
namespace App\Controllers;

use App\Services\AuthService;
use Config\Database;

class AdminPedidosSplitController extends Controller {
    private \PDO $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = Database::getConnection();
    }

    /**
     * Exibe a página de split com os itens do pedido
     */
    public function index($request) {
        $id = (int) ($request->getParam('id') ?? 0);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Split Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pedidos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">';

        // Flash message
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['flash_message'])) {
            $fType = $_SESSION['flash_type'] ?? 'info';
            echo '<div class="alert alert-' . htmlspecialchars($fType) . ' alert-dismissible fade show" role="alert">'
                . $_SESSION['flash_message']
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        }

        echo '<div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title"><i class="fas fa-cut me-2"></i>Split Order</h1>
                <a href="/admin/pedidos" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
              </div>';

        // Form para buscar pedido
        echo '<div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" action="/admin/pedidos/split" class="d-flex align-items-end gap-3">
                        <div>
                            <label for="order_id" class="form-label fw-bold">ID do Pedido</label>
                            <input type="number" class="form-control" id="order_id" name="id" value="' . ($id > 0 ? $id : '') . '" required placeholder="Ex: 1234" style="max-width:200px;">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Buscar</button>
                    </form>
                </div>
              </div>';

        // Se pedido informado, carregar e exibir itens
        if ($id > 0) {
            $this->renderOrderItems($id);
        }

        echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>';
    }

    /**
     * Renderiza os itens do pedido para seleção
     */
    private function renderOrderItems(int $pedidoId): void {
        // Buscar pedido
        $pedido = null;
        try {
            $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao buscar pedido: ' . htmlspecialchars($e->getMessage()) . '</div>';
            return;
        }

        if (!$pedido) {
            echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Pedido #' . $pedidoId . ' não encontrado.</div>';
            return;
        }

        // Determinar tabela de itens
        $itensTable = $this->getItensTableForPedido($pedidoId);

        // Buscar itens
        $itens = [];
        try {
            $colsItens = $this->getTableColumns($itensTable);

            // Determinar colunas
            $colQtd = $this->pickCol($colsItens, ['quantidade', 'qty']);
            $colPrecoUnit = $this->pickCol($colsItens, ['preco_unitario', 'valor_unitario', 'price', 'preco']);
            $colSubtotal = $this->pickCol($colsItens, ['subtotal']);
            $colNomeProduto = $this->pickCol($colsItens, ['nome_produto', 'produto_nome', 'nome']);
            $colProdutoId = $this->pickCol($colsItens, ['produto_id']);
            $colSku = $this->pickCol($colsItens, ['nome_produto_sku', 'sku']);

            $selectParts = ['pi.id', 'pi.pedido_id'];
            if ($colProdutoId) $selectParts[] = 'pi.' . $colProdutoId . ' AS produto_id';
            if ($colQtd) $selectParts[] = 'pi.' . $colQtd . ' AS quantidade';
            if ($colPrecoUnit) $selectParts[] = 'pi.' . $colPrecoUnit . ' AS preco_unitario';
            if ($colSubtotal) $selectParts[] = 'pi.' . $colSubtotal . ' AS subtotal';
            if ($colNomeProduto) $selectParts[] = 'pi.' . $colNomeProduto . ' AS nome_produto';
            if ($colSku) $selectParts[] = 'pi.' . $colSku . ' AS sku';

            // Tentar fazer JOIN com produtos para pegar peso e nome
            $joinProdutos = false;
            $pesoCol = null;
            $prodNameCol = null;
            if ($this->tableExists('produtos')) {
                $colsProd = $this->getTableColumns('produtos');
                $pesoCol = $this->pickCol($colsProd, ['weight', 'peso', 'peso_kg', 'product_weight']);
                $prodNameCol = $this->pickCol($colsProd, ['name', 'nome', 'titulo', 'title']);
                if ($colProdutoId) {
                    $joinProdutos = true;
                    if ($pesoCol) $selectParts[] = 'pr.' . $pesoCol . ' AS peso_unitario';
                    if ($prodNameCol) $selectParts[] = 'pr.' . $prodNameCol . ' AS produto_nome_catalogo';
                }
            }

            $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $itensTable . ' pi';
            if ($joinProdutos) {
                $sql .= ' LEFT JOIN produtos pr ON pi.' . $colProdutoId . ' = pr.id';
            }
            $sql .= ' WHERE pi.pedido_id = :pedido_id ORDER BY pi.id ASC';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':pedido_id' => $pedidoId]);
            $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao buscar itens: ' . htmlspecialchars($e->getMessage()) . '</div>';
            return;
        }

        if (empty($itens)) {
            echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Pedido #' . $pedidoId . ' não possui itens.</div>';
            return;
        }

        $codigoPedido = (string) ($pedido['codigo_pedido'] ?? $pedido['numero_pedido'] ?? $pedido['codigo'] ?? $pedido['numero'] ?? $pedidoId);
        if ($codigoPedido === '' || $codigoPedido === '0') $codigoPedido = (string) $pedidoId;

        $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? $pedido['currency'] ?? 'BRL')));
        if ($moeda === '') $moeda = 'BRL';
        $simboloMoeda = ($moeda === 'USD') ? '$' : 'R$';

        $statusPedido = (string) ($pedido['status'] ?? '');
        $valorTotalPedido = (float) ($pedido['valor_total'] ?? $pedido['total'] ?? $pedido['amount'] ?? 0);

        echo '<div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Pedido #' . htmlspecialchars($codigoPedido) . ' 
                    <span class="badge bg-light text-dark ms-2">' . htmlspecialchars(ucfirst($statusPedido)) . '</span>
                    <span class="badge bg-info ms-1">' . htmlspecialchars($moeda) . '</span>
                    <span class="float-end fw-normal fs-6">Total: ' . $simboloMoeda . ' ' . number_format($valorTotalPedido, 2, ',', '.') . '</span></h5>
                </div>
                <div class="card-body">';

        echo '<p class="text-muted mb-3"><i class="fas fa-info-circle me-1"></i>Selecione os itens que deseja <strong>separar</strong> para um novo pedido. Os itens selecionados serão removidos deste pedido e um novo pedido será criado com eles.</p>';

        echo '<div class="mb-3">
                <strong>Peso total selecionado:</strong> <span id="total-weight" class="badge bg-warning text-dark fs-6">0.000 kg</span>
                <span class="ms-3"><strong>Valor selecionado:</strong> <span id="total-value" class="badge bg-success fs-6">' . $simboloMoeda . ' 0,00</span></span>
              </div>';

        echo '<form method="post" action="/admin/pedidos/split" id="split-order-form">
                <input type="hidden" name="pedido_id" value="' . $pedidoId . '">
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>Produto</th>
                            <th>SKU</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Preço Unit.</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Peso Total</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($itens as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $nome = (string) ($item['nome_produto'] ?? $item['produto_nome_catalogo'] ?? 'Produto #' . ($item['produto_id'] ?? $itemId));
            $sku = (string) ($item['sku'] ?? '-');
            $qtd = (int) ($item['quantidade'] ?? 1);
            $precoUnit = (float) ($item['preco_unitario'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? ($precoUnit * $qtd));
            $pesoUnit = (float) ($item['peso_unitario'] ?? 0);
            $pesoTotal = $pesoUnit * $qtd;

            echo '<tr>
                    <td><input type="checkbox" class="form-check-input item-checkbox" name="items[]" value="' . $itemId . '" data-weight="' . $pesoTotal . '" data-value="' . $subtotal . '"></td>
                    <td>' . htmlspecialchars($nome) . '</td>
                    <td><small class="text-muted">' . htmlspecialchars($sku) . '</small></td>
                    <td class="text-center">' . $qtd . '</td>
                    <td class="text-end">' . $simboloMoeda . ' ' . number_format($precoUnit, 2, ',', '.') . '</td>
                    <td class="text-end fw-bold">' . $simboloMoeda . ' ' . number_format($subtotal, 2, ',', '.') . '</td>
                    <td class="text-end">' . number_format($pesoTotal, 3, ',', '.') . ' kg</td>
                  </tr>';
        }

        echo '</tbody></table></div>';

        echo '<div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-danger btn-lg" id="btn-split" disabled>
                    <i class="fas fa-cut me-2"></i>Separar Itens Selecionados
                </button>
              </div>';

        echo '</form>';
        echo '</div></div>';

        // JavaScript para calcular peso e valor total dos itens selecionados
        echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll(".item-checkbox");
    const totalWeightDisplay = document.getElementById("total-weight");
    const totalValueDisplay = document.getElementById("total-value");
    const btnSplit = document.getElementById("btn-split");
    const selectAll = document.getElementById("select-all");
    const simboloMoeda = "' . $simboloMoeda . '";

    function formatNumber(num, decimals) {
        return num.toFixed(decimals).replace(".", ",").replace(/\\B(?=(\\d{3})+(?!\\d))/g, ".");
    }

    function updateTotals() {
        let totalWeight = 0;
        let totalValue = 0;
        let anyChecked = false;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                anyChecked = true;
                totalWeight += parseFloat(cb.getAttribute("data-weight")) || 0;
                totalValue += parseFloat(cb.getAttribute("data-value")) || 0;
            }
        });

        totalWeightDisplay.textContent = formatNumber(totalWeight, 3) + " kg";
        totalValueDisplay.textContent = simboloMoeda + " " + formatNumber(totalValue, 2);
        btnSplit.disabled = !anyChecked;

        // Não permitir selecionar TODOS os itens
        let allChecked = true;
        checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
        if (allChecked && checkboxes.length > 0) {
            btnSplit.disabled = true;
            totalValueDisplay.textContent += " (não pode separar todos)";
        }
    }

    checkboxes.forEach(cb => cb.addEventListener("change", updateTotals));

    selectAll.addEventListener("change", function() {
        checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
        updateTotals();
    });

    // Confirmação antes de enviar
    document.getElementById("split-order-form").addEventListener("submit", function(e) {
        let allChecked = true;
        checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
        if (allChecked) {
            e.preventDefault();
            alert("Você não pode separar TODOS os itens. Pelo menos um item deve permanecer no pedido original.");
            return;
        }
        if (!confirm("Tem certeza que deseja separar os itens selecionados em um novo pedido? Esta ação não pode ser desfeita.")) {
            e.preventDefault();
        }
    });
});
</script>';
    }

    /**
     * Processa o split do pedido
     */
    public function processar($request) {
        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        $selectedItems = $_POST['items'] ?? [];

        if ($pedidoId <= 0 || empty($selectedItems)) {
            $this->redirectWithMessage('/admin/pedidos/split', 'ID do pedido inválido ou nenhum item selecionado.', 'danger');
            return;
        }

        // Buscar pedido original
        $pedido = null;
        try {
            $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->redirectWithMessage('/admin/pedidos/split?id=' . $pedidoId, 'Erro ao buscar pedido: ' . $e->getMessage(), 'danger');
            return;
        }

        if (!$pedido) {
            $this->redirectWithMessage('/admin/pedidos/split', 'Pedido não encontrado.', 'danger');
            return;
        }

        $itensTable = $this->getItensTableForPedido($pedidoId);
        $colsItens = $this->getTableColumns($itensTable);
        $colsPedidos = $this->getTableColumns('pedidos');

        // Buscar TODOS os itens do pedido
        $todosItens = [];
        try {
            $stmt = $this->connection->prepare('SELECT * FROM ' . $itensTable . ' WHERE pedido_id = :pedido_id');
            $stmt->execute([':pedido_id' => $pedidoId]);
            $todosItens = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $this->redirectWithMessage('/admin/pedidos/split?id=' . $pedidoId, 'Erro ao buscar itens: ' . $e->getMessage(), 'danger');
            return;
        }

        // Validar: não pode selecionar TODOS os itens
        $selectedItemIds = array_map('intval', $selectedItems);
        $allItemIds = array_map(function($it) { return (int) ($it['id'] ?? 0); }, $todosItens);
        if (count(array_intersect($selectedItemIds, $allItemIds)) >= count($allItemIds)) {
            $this->redirectWithMessage('/admin/pedidos/split?id=' . $pedidoId, 'Você não pode separar todos os itens. Pelo menos um item deve permanecer no pedido original.', 'danger');
            return;
        }

        // Separar itens selecionados vs itens que ficam
        $itensSeparar = [];
        $itensPermanecer = [];
        foreach ($todosItens as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if (in_array($itemId, $selectedItemIds, true)) {
                $itensSeparar[] = $item;
            } else {
                $itensPermanecer[] = $item;
            }
        }

        if (empty($itensSeparar)) {
            $this->redirectWithMessage('/admin/pedidos/split?id=' . $pedidoId, 'Nenhum item válido selecionado para separar.', 'danger');
            return;
        }

        // Calcular totais para distribuição proporcional
        $colQtd = $this->pickCol($colsItens, ['quantidade', 'qty']);
        $colPrecoUnit = $this->pickCol($colsItens, ['preco_unitario', 'valor_unitario', 'price', 'preco']);
        $colSubtotal = $this->pickCol($colsItens, ['subtotal']);
        $colProdutoId = $this->pickCol($colsItens, ['produto_id']);

        $subtotalOriginal = 0;
        foreach ($todosItens as $item) {
            $sub = (float) ($item[$colSubtotal] ?? 0);
            if ($sub == 0 && $colPrecoUnit && $colQtd) {
                $sub = (float) ($item[$colPrecoUnit] ?? 0) * (int) ($item[$colQtd] ?? 1);
            }
            $subtotalOriginal += $sub;
        }

        $subtotalSeparar = 0;
        foreach ($itensSeparar as $item) {
            $sub = (float) ($item[$colSubtotal] ?? 0);
            if ($sub == 0 && $colPrecoUnit && $colQtd) {
                $sub = (float) ($item[$colPrecoUnit] ?? 0) * (int) ($item[$colQtd] ?? 1);
            }
            $subtotalSeparar += $sub;
        }

        $subtotalPermanecer = $subtotalOriginal - $subtotalSeparar;

        // Proporção para distribuir frete, impostos, descontos (NÃO taxa de serviço)
        $proporcaoNovo = ($subtotalOriginal > 0) ? ($subtotalSeparar / $subtotalOriginal) : 0;
        $proporcaoOriginal = 1 - $proporcaoNovo;

        // ===== CALCULAR TAXA DE SERVIÇO BASEADA NO PESO (US$ 39/kg arredondado para cima) =====
        // Buscar peso dos produtos para calcular a taxa de serviço corretamente
        $pesoColProd = null;
        if ($this->tableExists('produtos')) {
            $colsProd = $this->getTableColumns('produtos');
            $pesoColProd = $this->pickCol($colsProd, ['weight', 'peso', 'peso_kg', 'product_weight']);
        }

        // Buscar taxa por kg da configuração
        $taxaPorKg = 39.0;
        try {
            $stCfg = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'taxa_servico_usd_por_kg' LIMIT 1");
            $stCfg->execute();
            $cfgVal = $stCfg->fetchColumn();
            if ($cfgVal && (float) $cfgVal > 0) {
                $taxaPorKg = (float) $cfgVal;
            }
        } catch (\Exception $e) {}

        // Buscar taxa de conversão USD->BRL do pedido
        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? $pedido['currency'] ?? 'USD')));
        $taxaConversao = (float) ($pedido['taxa_conversao'] ?? $pedido['exchange_rate'] ?? $pedido['conversion_rate'] ?? 0);
        if ($taxaConversao <= 1.01 && $moedaPedido === 'BRL') {
            // Buscar taxa da configuração do sistema
            try {
                $stTx = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'usd_brl_rate' LIMIT 1");
                $stTx->execute();
                $v = (float) str_replace(',', '.', (string) ($stTx->fetchColumn() ?: '0'));
                if ($v > 1.01) $taxaConversao = $v;
            } catch (\Exception $e) {}
            if ($taxaConversao <= 1.01) $taxaConversao = 5.85;
        }

        // Calcular peso total dos itens que vão para o novo pedido
        $pesoNovo = $this->calcularPesoItens($itensSeparar, $colProdutoId, $colQtd, $pesoColProd);
        $pesoPermanecer = $this->calcularPesoItens($itensPermanecer, $colProdutoId, $colQtd, $pesoColProd);

        // Taxa de serviço = ceil(peso) * $39/kg (em USD)
        // Se a moeda do pedido é BRL, multiplicar pela taxa de conversão
        $taxaServicoNovo = ceil($pesoNovo) * $taxaPorKg;
        $taxaServicoPermanecer = ceil($pesoPermanecer) * $taxaPorKg;

        if ($moedaPedido === 'BRL' && $taxaConversao > 1.0) {
            $taxaServicoNovo = round($taxaServicoNovo * $taxaConversao, 2);
            $taxaServicoPermanecer = round($taxaServicoPermanecer * $taxaConversao, 2);
        }

        // Iniciar transação
        $this->connection->beginTransaction();
        try {
            // ===== CRIAR NOVO PEDIDO =====
            // Copiar TODOS os campos do pedido original, exceto id e campos auto-gerados
            $dadosNovoPedido = [];
            $colsIgnorar = ['id', 'deleted_at', 'updated_at', 'tracking_code', 'codigo_rastreio',
                'peso_total', 'altura', 'largura', 'comprimento',
                'status_conferencia', 'conferido_por', 'conferido_em'];

            foreach ($colsPedidos as $col) {
                if (in_array($col, $colsIgnorar, true)) continue;
                if (array_key_exists($col, $pedido) && $pedido[$col] !== null) {
                    $dadosNovoPedido[$col] = $pedido[$col];
                }
            }

            // Valores proporcionais (sobrescrever com valores ajustados)
            // NOTA: taxa_servico/servicos NÃO é proporcional - é recalculada pelo peso
            $colsValorProporcional = [
                'valor_total', 'total', 'amount',
                'subtotal',
                'valor_frete', 'frete', 'shipping', 'shipping_cost',
                'valor_impostos', 'impostos', 'tax', 'taxes',
                'valor_desconto', 'desconto', 'discount',
            ];

            foreach ($colsValorProporcional as $col) {
                if (in_array($col, $colsPedidos, true) && isset($pedido[$col]) && $pedido[$col] !== null) {
                    $valorOriginal = (float) $pedido[$col];
                    $dadosNovoPedido[$col] = round($valorOriginal * $proporcaoNovo, 2);
                }
            }

            // Taxa de serviço: recalcular baseado no peso (ceil(peso) * $39/kg)
            $colsTaxaServico = ['valor_taxa_servico', 'taxa_servico', 'service_fee', 'servicos'];
            foreach ($colsTaxaServico as $col) {
                if (in_array($col, $colsPedidos, true) && isset($pedido[$col]) && $pedido[$col] !== null) {
                    $dadosNovoPedido[$col] = $taxaServicoNovo;
                }
            }

            // Recalcular valor_total do novo pedido: subtotal + taxa_servico + impostos + frete - desconto
            $novoSubtotal = $subtotalSeparar;
            $colSubtotalPedido = $this->pickCol($colsPedidos, ['subtotal']);
            if ($colSubtotalPedido) {
                $dadosNovoPedido[$colSubtotalPedido] = round($novoSubtotal, 2);
            }

            // Recalcular total do novo pedido
            $colTotalPedido = $this->pickCol($colsPedidos, ['valor_total', 'total', 'amount']);
            if ($colTotalPedido) {
                $novoFrete = 0;
                $colFretePedido = $this->pickCol($colsPedidos, ['valor_frete', 'frete', 'shipping', 'shipping_cost']);
                if ($colFretePedido && isset($dadosNovoPedido[$colFretePedido])) {
                    $novoFrete = (float) $dadosNovoPedido[$colFretePedido];
                }
                $novoImpostos = 0;
                $colImpostosPedido = $this->pickCol($colsPedidos, ['valor_impostos', 'impostos', 'tax', 'taxes']);
                if ($colImpostosPedido && isset($dadosNovoPedido[$colImpostosPedido])) {
                    $novoImpostos = (float) $dadosNovoPedido[$colImpostosPedido];
                }
                $novoDesconto = 0;
                $colDescontoPedido = $this->pickCol($colsPedidos, ['valor_desconto', 'desconto', 'discount']);
                if ($colDescontoPedido && isset($dadosNovoPedido[$colDescontoPedido])) {
                    $novoDesconto = (float) $dadosNovoPedido[$colDescontoPedido];
                }

                $dadosNovoPedido[$colTotalPedido] = round($novoSubtotal + $taxaServicoNovo + $novoImpostos + $novoFrete - $novoDesconto, 2);
            }

            // Código do novo pedido: original + "-B"
            $codigoOriginal = (string) ($pedido['codigo_pedido'] ?? $pedido['numero_pedido'] ?? $pedido['codigo'] ?? $pedido['numero'] ?? $pedidoId);
            if ($codigoOriginal === '' || $codigoOriginal === '0') $codigoOriginal = (string) $pedidoId;

            $colCodigo = $this->pickCol($colsPedidos, ['codigo_pedido', 'numero_pedido', 'codigo', 'numero']);
            if ($colCodigo) {
                $dadosNovoPedido[$colCodigo] = $codigoOriginal . '-B';
            }

            // Data de criação
            if (in_array('created_at', $colsPedidos, true)) {
                $dadosNovoPedido['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('data_pedido', $colsPedidos, true)) {
                $dadosNovoPedido['data_pedido'] = date('Y-m-d H:i:s');
            }
            if (in_array('data_criacao', $colsPedidos, true)) {
                $dadosNovoPedido['data_criacao'] = date('Y-m-d H:i:s');
            }

            // Observação de split
            $obsCol = $this->pickCol($colsPedidos, ['observacao_vendedor', 'observacao_interna', 'observacao', 'notas', 'notes']);
            if ($obsCol) {
                $obsExistente = (string) ($dadosNovoPedido[$obsCol] ?? '');
                $dadosNovoPedido[$obsCol] = trim($obsExistente . "\n[SPLIT] Pedido separado do pedido original #" . $codigoOriginal . " (ID: " . $pedidoId . ") em " . date('d/m/Y H:i'));
            }

            // Inserir novo pedido
            $columns = implode(', ', array_keys($dadosNovoPedido));
            $placeholders = ':' . implode(', :', array_keys($dadosNovoPedido));
            $sqlInsert = "INSERT INTO pedidos ({$columns}) VALUES ({$placeholders})";
            $stmtInsert = $this->connection->prepare($sqlInsert);
            foreach ($dadosNovoPedido as $key => $value) {
                $stmtInsert->bindValue(':' . $key, $value);
            }
            $stmtInsert->execute();
            $novoPedidoId = (int) $this->connection->lastInsertId();

            // ===== MOVER ITENS SELECIONADOS PARA O NOVO PEDIDO =====
            foreach ($itensSeparar as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId <= 0) continue;

                $stmtMove = $this->connection->prepare('UPDATE ' . $itensTable . ' SET pedido_id = :novo_pedido_id WHERE id = :item_id');
                $stmtMove->execute([':novo_pedido_id' => $novoPedidoId, ':item_id' => $itemId]);
            }

            // ===== ATUALIZAR VALORES DO PEDIDO ORIGINAL =====
            $updateOriginal = [];
            foreach ($colsValorProporcional as $col) {
                if (in_array($col, $colsPedidos, true) && isset($pedido[$col]) && $pedido[$col] !== null) {
                    $valorOriginal = (float) $pedido[$col];
                    $updateOriginal[$col] = round($valorOriginal * $proporcaoOriginal, 2);
                }
            }

            // Taxa de serviço do pedido original: recalcular pelo peso remanescente
            foreach ($colsTaxaServico as $col) {
                if (in_array($col, $colsPedidos, true) && isset($pedido[$col]) && $pedido[$col] !== null) {
                    $updateOriginal[$col] = $taxaServicoPermanecer;
                }
            }

            // Recalcular subtotal do pedido original
            if ($colSubtotalPedido) {
                $updateOriginal[$colSubtotalPedido] = round($subtotalPermanecer, 2);
            }

            // Recalcular total do pedido original
            if ($colTotalPedido) {
                $origFrete = 0;
                if ($colFretePedido && isset($updateOriginal[$colFretePedido])) {
                    $origFrete = (float) $updateOriginal[$colFretePedido];
                }
                $origImpostos = 0;
                if ($colImpostosPedido && isset($updateOriginal[$colImpostosPedido])) {
                    $origImpostos = (float) $updateOriginal[$colImpostosPedido];
                }
                $origDesconto = 0;
                if ($colDescontoPedido && isset($updateOriginal[$colDescontoPedido])) {
                    $origDesconto = (float) $updateOriginal[$colDescontoPedido];
                }

                $updateOriginal[$colTotalPedido] = round($subtotalPermanecer + $taxaServicoPermanecer + $origImpostos + $origFrete - $origDesconto, 2);
            }

            // Adicionar observação no pedido original
            if ($obsCol && in_array($obsCol, $colsPedidos, true)) {
                $obsOriginal = (string) ($pedido[$obsCol] ?? '');
                $updateOriginal[$obsCol] = trim($obsOriginal . "\n[SPLIT] Itens separados para novo pedido #" . ($colCodigo ? $dadosNovoPedido[$colCodigo] : $novoPedidoId) . " (ID: " . $novoPedidoId . ") em " . date('d/m/Y H:i'));
            }

            if (!empty($updateOriginal)) {
                $setParts = [];
                foreach ($updateOriginal as $col => $val) {
                    $setParts[] = $col . ' = :upd_' . $col;
                }
                $sqlUpdate = 'UPDATE pedidos SET ' . implode(', ', $setParts) . ' WHERE id = :upd_pedido_id';
                $stmtUpdate = $this->connection->prepare($sqlUpdate);
                foreach ($updateOriginal as $col => $val) {
                    $stmtUpdate->bindValue(':upd_' . $col, $val);
                }
                $stmtUpdate->bindValue(':upd_pedido_id', $pedidoId);
                $stmtUpdate->execute();
            }

            $this->connection->commit();

            // Sucesso
            $novoCodigoPedido = $colCodigo ? $dadosNovoPedido[$colCodigo] : (string) $novoPedidoId;
            $this->redirectWithMessage(
                '/admin/pedidos/split?id=' . $pedidoId,
                'Split realizado com sucesso! Novo pedido criado: <a href="/admin/pedidos/detalhes/' . $novoPedidoId . '" class="alert-link">#' . htmlspecialchars($novoCodigoPedido) . ' (ID: ' . $novoPedidoId . ')</a>',
                'success'
            );

        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->redirectWithMessage('/admin/pedidos/split?id=' . $pedidoId, 'Erro ao processar split: ' . $e->getMessage(), 'danger');
        }
    }

    // ========== HELPERS ==========

    private function getItensTableForPedido(int $pedidoId): string {
        $t1 = $this->tableExists('pedido_itens');
        $t2 = $this->tableExists('pedido_items');

        if (!$t1 && !$t2) return 'pedido_itens';
        if ($t1 && !$t2) return 'pedido_itens';
        if ($t2 && !$t1) return 'pedido_items';

        // Ambas existem - verificar qual tem itens para este pedido
        $c1 = 0;
        $c2 = 0;
        try {
            $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = :id');
            $st->execute([':id' => $pedidoId]);
            $c1 = (int) ($st->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        try {
            $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = :id');
            $st->execute([':id' => $pedidoId]);
            $c2 = (int) ($st->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        return ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
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

    private function getTableColumns(string $table): array {
        try {
            $stmt = $this->connection->query('DESCRIBE ' . $table);
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickCol(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Calcula o peso total de um conjunto de itens buscando o peso de cada produto
     */
    private function calcularPesoItens(array $itens, ?string $colProdutoId, ?string $colQtd, ?string $pesoColProd): float {
        if (!$colProdutoId || !$pesoColProd) {
            return 0.0;
        }

        $pesoTotal = 0.0;
        foreach ($itens as $item) {
            $produtoId = (int) ($item[$colProdutoId] ?? 0);
            $qtd = (int) ($item[$colQtd] ?? 1);
            if ($produtoId <= 0) continue;

            try {
                $stmt = $this->connection->prepare('SELECT ' . $pesoColProd . ' FROM produtos WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $produtoId]);
                $peso = (float) ($stmt->fetchColumn() ?: 0);
                $pesoTotal += $peso * $qtd;
            } catch (\Exception $e) {
                // Se não conseguir buscar peso, continua
            }
        }

        return $pesoTotal;
    }

    private function redirectWithMessage(string $url, string $message, string $type = 'success'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }
}
