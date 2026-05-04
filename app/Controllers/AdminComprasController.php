<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminComprasController extends Controller {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = \Config\Database::getConnection();
    }

    private function renderFlashIfAny(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['message'])) {
            return;
        }
        $type = (string) ($_SESSION['message_type'] ?? 'info');
        $msg = (string) $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show mt-3" role="alert">'
            . htmlspecialchars($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
            . '</div>';
    }

    private function getConfigValue(string $chave, $default = null) {
        try {
            $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function getTaxaServicoPorKg(): float {
        return floatval($this->getConfigValue('entrega_taxa_servico_kg', '39'));
    }

    private function calcularFrete(float $subtotal, float $pesoTotal, string $moeda = 'USD'): float {
        $calcularAutomatico = $this->getConfigValue('entrega_calcular_automatico', '1');
        $calcularAutomatico = ($calcularAutomatico === '1' || strtolower((string) $calcularAutomatico) === 'true');
        if (!$calcularAutomatico) {
            return 0.0;
        }

        $freteGratisAcima = floatval($this->getConfigValue('entrega_frete_gratis_acima', '0'));
        if ($freteGratisAcima <= 0 || $subtotal >= $freteGratisAcima) {
            return 0.0;
        }

        $fretePorKg = floatval($this->getConfigValue('entrega_frete_padrao', '15'));
        if ($fretePorKg <= 0) {
            return 0.0;
        }

        $pesoArredondado = ceil($pesoTotal);
        return $fretePorKg * $pesoArredondado;
    }

    private function getPedidoMoeda(array $pedidoRow): string {
        $candidates = ['moeda', 'moeda_original', 'moeda_padrao'];
        foreach ($candidates as $c) {
            if (!empty($pedidoRow[$c])) {
                return strtoupper(trim((string) $pedidoRow[$c]));
            }
        }
        return 'BRL';
    }

    private function getPedidoTotalAtual($pedidoOrId): float {
        $total = 0.0;
        $pedidoId = 0;
        if (is_array($pedidoOrId)) {
            if (!empty($pedidoOrId['valor_total'])) {
                return (float) $pedidoOrId['valor_total'];
            }
            if (!empty($pedidoOrId['total'])) {
                return (float) $pedidoOrId['total'];
            }
            $pedidoId = (int) ($pedidoOrId['id'] ?? 0);
        } else {
            $pedidoId = (int) $pedidoOrId;
        }

        if ($pedidoId <= 0) {
            return $total;
        }

        try {
            $stmt = $this->connection->prepare('SELECT total, valor_total FROM pedidos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pedidoId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                if (isset($row['valor_total']) && $row['valor_total'] !== null) {
                    return (float) $row['valor_total'];
                }
                if (isset($row['total']) && $row['total'] !== null) {
                    return (float) $row['total'];
                }
            }
        } catch (\Exception $e) {
        }

        return $total;
    }

    private function getProdutoInfo(int $produtoId): ?array {
        try {
            $cols = [];
            $stmtCols = $this->connection->query('DESCRIBE produtos');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            $nomeCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : null);
            $skuCol = in_array('sku', $cols, true) ? 'sku' : null;
            $pesoCol = in_array('peso', $cols, true) ? 'peso' : null;

            $priceCol = null;
            foreach (['price', 'valor', 'value', 'preco'] as $c) {
                if (in_array($c, $cols, true)) {
                    $priceCol = $c;
                    break;
                }
            }

            $select = ['id'];
            $select[] = $skuCol ? ('`' . $skuCol . '` as sku') : "'' as sku";
            $select[] = $nomeCol ? ('`' . $nomeCol . '` as nome') : "'' as nome";
            $select[] = $pesoCol ? ('`' . $pesoCol . '` as peso') : '0 as peso';
            $select[] = $priceCol ? ('`' . $priceCol . '` as preco') : '0 as preco';

            $stmt = $this->connection->prepare('SELECT ' . implode(', ', $select) . ' FROM produtos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $produtoId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function inserirItemNoPedido(string $itensTable, int $pedidoId, int $produtoId, int $quantidade, float $precoUnitario, float $subtotal, array $produtoInfo): void {
        // Inserção flexível para suportar pedido_itens e pedido_items
        $cols = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE ' . $itensTable);
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $insCols = [];
        $ph = [];
        $vals = [];

        $map = [
            'pedido_id' => $pedidoId,
            'produto_id' => $produtoId,
            'quantidade' => $quantidade,
        ];

        if (in_array('preco_unitario', $cols, true)) {
            $map['preco_unitario'] = $precoUnitario;
        }
        if (in_array('valor_unitario', $cols, true)) {
            $map['valor_unitario'] = $precoUnitario;
        }
        if (in_array('subtotal', $cols, true)) {
            $map['subtotal'] = $subtotal;
        }
        if (in_array('sku', $cols, true)) {
            $map['sku'] = (string) ($produtoInfo['sku'] ?? '');
        }
        if (in_array('nome_produto', $cols, true)) {
            $map['nome_produto'] = (string) ($produtoInfo['nome'] ?? '');
        }
        if (in_array('nome_produto_sku', $cols, true)) {
            $nome = (string) ($produtoInfo['nome'] ?? '');
            $sku = (string) ($produtoInfo['sku'] ?? '');
            $map['nome_produto_sku'] = trim($nome . ($sku !== '' ? (' - ' . $sku) : ''));
        }
        if (in_array('created_at', $cols, true)) {
            // alguns schemas aceitam NOW() via default; mas aqui mantemos simples com bind
            $map['created_at'] = date('Y-m-d H:i:s');
        }

        foreach ($map as $c => $v) {
            if (!in_array($c, $cols, true)) {
                continue;
            }
            $insCols[] = $c;
            $ph[] = '?';
            $vals[] = $v;
        }

        if (empty($insCols)) {
            return;
        }

        $sql = 'INSERT INTO ' . $itensTable . ' (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $ph) . ')';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($vals);
    }

    private function recalcularTotaisPedido(int $pedidoId, string $moeda = 'BRL'): array {
        $itensTable = $this->findPedidoItensTable();
        if (!$itensTable || $pedidoId <= 0) {
            return ['subtotal' => 0.0, 'frete' => 0.0, 'taxa_servico' => 0.0, 'impostos' => 0.0, 'imposto_local' => 0.0, 'total' => 0.0];
        }

        // subtotal
        $subtotal = 0.0;
        try {
            $unitExpr = '0';
            if ($this->columnExists($itensTable, 'preco_unitario')) {
                $unitExpr = 'i.preco_unitario';
            } elseif ($this->columnExists($itensTable, 'valor_unitario')) {
                $unitExpr = 'i.valor_unitario';
            }

            $stmt = $this->connection->prepare(
                "SELECT COALESCE(SUM(COALESCE(i.subtotal, (({$unitExpr}) * COALESCE(i.quantidade,0)))),0) as subtotal
                 FROM {$itensTable} i
                 WHERE i.pedido_id = :pedido_id"
            );
            $stmt->execute([':pedido_id' => $pedidoId]);
            $subtotal = (float) ($stmt->fetchColumn() ?: 0.0);
        } catch (\Exception $e) {
        }

        // peso total
        $pesoTotal = 0.0;
        try {
            $stmt = $this->connection->prepare(
                "SELECT COALESCE(SUM(COALESCE(p.peso,0) * COALESCE(i.quantidade,0)),0) as peso
                 FROM {$itensTable} i
                 JOIN produtos p ON p.id = i.produto_id
                 WHERE i.pedido_id = :pedido_id"
            );
            $stmt->execute([':pedido_id' => $pedidoId]);
            $pesoTotal = (float) ($stmt->fetchColumn() ?: 0.0);
        } catch (\Exception $e) {
        }

        $taxaServico = ceil($pesoTotal) * $this->getTaxaServicoPorKg();
        $frete = $this->calcularFrete($subtotal, $pesoTotal, $moeda);
        $impostos = $subtotal * 0.80;

        // Imposto local do grupo de compras
        $impostoLocal = 0.0;
        try {
            $stImpL = $this->connection->prepare(
                "SELECT MAX(g.imposto_local_percent) FROM grupos_compras g
                 INNER JOIN produtos p ON p.grupo_compras_id = g.id
                 INNER JOIN {$itensTable} i ON i.produto_id = p.id
                 WHERE i.pedido_id = :pedido_id AND g.imposto_local_percent > 0"
            );
            $stImpL->execute([':pedido_id' => $pedidoId]);
            $maxPercent = (float) ($stImpL->fetchColumn() ?: 0);
            if ($maxPercent > 0) {
                $impostoLocal = $subtotal * ($maxPercent / 100.0);
            }
        } catch (\Exception $e) {}

        $total = $subtotal + $taxaServico + $impostos + $frete + $impostoLocal;

        return [
            'subtotal' => $subtotal,
            'peso' => $pesoTotal,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'imposto_local' => $impostoLocal,
            'frete' => $frete,
            'total' => $total,
        ];
    }

    private function findPedidoItensTable(): ?string {
        try {
            $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute(['pedido_itens']);
            if ((int) $stmtT->fetchColumn() > 0) {
                return 'pedido_itens';
            }
            $stmtT->execute(['pedido_items']);
            if ((int) $stmtT->fetchColumn() > 0) {
                return 'pedido_items';
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    private function pedidoEstaPago(array $pedidoRow): bool {
        $st = strtolower(trim((string) ($pedidoRow['status'] ?? '')));
        if (in_array($st, ['pago', 'paid', 'aprovado', 'approved'], true)) {
            return true;
        }
        $ps = strtolower(trim((string) ($pedidoRow['payment_status'] ?? '')));
        if (in_array($ps, ['approved', 'aprovado', 'paid', 'pago'], true)) {
            return true;
        }
        if (!empty($pedidoRow['pago_em'])) {
            return true;
        }
        return false;
    }

    private function fetchProdutosSelect(): array {
        try {
            $nomeCol = $this->columnExists('produtos', 'name') ? 'name' : ($this->columnExists('produtos', 'nome') ? 'nome' : null);
            $selectNome = $nomeCol ? ('p.' . $nomeCol . ' as produto_nome') : "'' as produto_nome";
            $cols = ['p.id as produto_id', 'p.sku as sku', $selectNome];
            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM produtos p ORDER BY produto_nome ASC, p.id DESC LIMIT 2000';
            $stmt = $this->connection->query($sql);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchPedidosSelect(): array {
        try {
            $cols = ['id', 'codigo_pedido', 'status', 'valor_total', 'created_at', 'pago_em', 'payment_status'];
            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM pedidos ORDER BY id DESC LIMIT 500';
            $stmt = $this->connection->query($sql);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchUsuariosSelect(): array {
        if (!$this->tableExists('usuarios')) {
            return [];
        }
        try {
            $temName = $this->columnExists('usuarios', 'name');
            $temNome = $this->columnExists('usuarios', 'nome');
            $nomeExpr = $temName ? 'u.name' : ($temNome ? 'u.nome' : "''");

            $cols = ['u.id as id', $nomeExpr . ' as nome'];
            if ($this->columnExists('usuarios', 'email')) {
                $cols[] = 'u.email as email';
            } else {
                $cols[] = "'' as email";
            }

            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM usuarios u ORDER BY nome ASC, u.id DESC LIMIT 2000';
            $stmt = $this->connection->query($sql);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function novoItem($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        try {
            $usuarios = $this->fetchUsuariosSelect();
            $produtosSelect = $this->fetchProdutosSelect();
        } catch (\Exception $e) {
            $usuarios = [];
            $produtosSelect = [];
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Item - Lista de Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        renderAdminSidebar('compras');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-plus me-2"></i>Novo Item na Lista de Compras</h1>
                <div>
                    <a class="btn btn-outline-secondary" href="/admin/estoque/compras" target="_blank"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
                </div>
            </div>

            ';

        $this->renderFlashIfAny();

        echo '

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/admin/estoque/compras/salvar" id="formNovoItem">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Usuário *</label>
                                <select class="form-select" name="usuario_id" id="novo_usuario_id" required>
                                    <option value="">Selecione...</option>';

        foreach ($usuarios as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid <= 0) continue;
            $nome = (string) ($u['nome'] ?? '');
            $email = (string) ($u['email'] ?? '');
            $label = trim($nome) !== '' ? $nome : ('Usuário #' . $uid);
            if ($email !== '') $label .= ' - ' . $email;
            echo '<option value="' . $uid . '">' . htmlspecialchars($label) . '</option>';
        }

        echo '                 </select>
                                <div class="form-text">Selecione o usuário para carregar os pedidos.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pedido *</label>
                                <select class="form-select" name="pedido_id" id="novo_pedido_id" required disabled>
                                    <option value="">Selecione um usuário primeiro...</option>
                                </select>
                                <div class="form-text">Ao salvar, se o pedido estiver pendente, o sistema soma o valor pendente ao total do pedido (sem chamar gateway automaticamente).</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto *</label>
                                <select class="form-select" name="produto_id" required>
                                    <option value="">Selecione...</option>';

        foreach ($produtosSelect as $p) {
            $pid = (int) ($p['produto_id'] ?? 0);
            if ($pid <= 0) continue;
            $pn = (string) ($p['produto_nome'] ?? '');
            $sku = (string) ($p['sku'] ?? '');
            $label = trim($pn) !== '' ? $pn : ('Produto #' . $pid);
            if ($sku !== '') $label .= ' - ' . $sku;
            echo '<option value="' . $pid . '">' . htmlspecialchars($label) . '</option>';
        }

        echo '                 </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Quantidade *</label>
                                <input type="number" class="form-control" name="quantidade" min="1" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Valor pendente (diferença) *</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="valor_pendente" value="0" readonly>
                                <div class="form-text">Calculado automaticamente ao salvar (regras padrão).</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Prioridade</label>
                                <select class="form-select" name="prioridade">
                                    <option value="media" selected>Média</option>
                                    <option value="baixa">Baixa</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar</button>
                                    <a class="btn btn-outline-secondary" href="/admin/estoque/compras" target="_blank">Cancelar</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        </div>
    </div>';

        renderAdminScripts();

        echo '<script>
            function escapeHtml(str){
                if (str === null || str === undefined) return "";
                return String(str)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/\"/g, "&quot;")
                    .replace(/\'/g, "&#039;");
            }

            function formatMoney(v){
                if (v === null || v === undefined || v === "") return "-";
                var n = Number(v);
                if (isNaN(n)) return String(v);
                return "$ " + n.toFixed(2);
            }

            function loadPedidosDoUsuario(usuarioId){
                var sel = document.getElementById("novo_pedido_id");
                if (!sel) return;
                sel.disabled = true;
                sel.innerHTML = "<option value=\"\">Carregando...</option>";

                fetch("/admin/estoque/compras/pedidos-usuario?usuario_id=" + encodeURIComponent(String(usuarioId)), {
                    headers: { "Accept": "application/json" }
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || !data.success) {
                        sel.innerHTML = "<option value=\"\">Erro ao carregar pedidos</option>";
                        return;
                    }
                    var pedidos = data.pedidos || [];
                    if (!pedidos.length) {
                        sel.innerHTML = "<option value=\"\">Nenhum pedido para este usuário</option>";
                        return;
                    }
                    var html = "<option value=\"\">Selecione...</option>";
                    pedidos.forEach(function(p){
                        var pid = p.id || 0;
                        if (!pid) return;
                        var label = "Pedido #" + pid;
                        if (p.codigo_pedido) label += " (" + p.codigo_pedido + ")";
                        if (p.status) label += " - " + p.status;
                        if (p.valor_total !== null && p.valor_total !== undefined) label += " - " + formatMoney(p.valor_total);
                        html += "<option value=\"" + escapeHtml(pid) + "\">" + escapeHtml(label) + "</option>";
                    });
                    sel.innerHTML = html;
                    sel.disabled = false;
                })
                .catch(function(){
                    sel.innerHTML = "<option value=\"\">Erro ao carregar pedidos</option>";
                });
            }

            var userSel = document.getElementById("novo_usuario_id");
            if (userSel) {
                userSel.addEventListener("change", function(){
                    var uid = this.value || "";
                    if (!uid) {
                        var sel = document.getElementById("novo_pedido_id");
                        if (sel) {
                            sel.disabled = true;
                            sel.innerHTML = "<option value=\"\">Selecione um usuário primeiro...</option>";
                        }
                        return;
                    }
                    loadPedidosDoUsuario(uid);
                });
            }
        </script>';

        echo '</body></html>';
    }

    public function pedidosUsuario($request) {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = (int) $request->getParam('usuario_id', 0);

        if ($usuarioId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            return;
        }

        try {
            $cols = ['id', 'codigo_pedido', 'status', 'valor_total', 'created_at', 'pago_em', 'payment_status'];
            $colsReal = [];
            foreach ($cols as $c) {
                if ($this->columnExists('pedidos', $c)) {
                    $colsReal[] = $c;
                }
            }
            if (empty($colsReal)) {
                $colsReal = ['id'];
            }

            $stmt = $this->connection->prepare('SELECT ' . implode(', ', $colsReal) . ' FROM pedidos WHERE usuario_id = :uid ORDER BY id DESC LIMIT 200');
            $stmt->execute([':uid' => $usuarioId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'pedidos' => $rows]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos do usuário.']);
            return;
        }
    }

    public function gerarLinkDiferenca($request) {
        header('Content-Type: application/json; charset=utf-8');

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        $valorRaw = (string) ($request->getParam('valor', '') ?? '');
        $valorRaw = trim($valorRaw);
        $valor = null;
        if ($valorRaw !== '') {
            $valorNorm = str_replace([' ', 'R$', 'r$'], '', $valorRaw);
            $valorNorm = str_replace('.', '', $valorNorm);
            $valorNorm = str_replace(',', '.', $valorNorm);
            if (is_numeric($valorNorm)) {
                $tmp = (float) $valorNorm;
                if ($tmp > 0) {
                    $valor = $tmp;
                }
            }
        }

        if ($pedidoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            return;
        }

        try {
            $svc = new \App\Services\PedidoDiferencaAsaasService($this->connection);
            $result = $svc->gerarCobrancaDiferenca($pedidoId, $valor);

            $created = is_array($result['created'] ?? null) ? $result['created'] : [];

            echo json_encode([
                'success' => true,
                'pedido_id' => $pedidoId,
                'novo_total' => $result['novo_total'] ?? null,
                'valor_ja_pago' => $result['valor_ja_pago'] ?? null,
                'diferenca' => $result['diferenca'] ?? null,
                'billingType' => $result['billingType'] ?? null,
                'payment_id' => $created['id'] ?? null,
                'status' => $created['status'] ?? null,
                'invoiceUrl' => $created['invoiceUrl'] ?? null,
                'bankSlipUrl' => $created['bankSlipUrl'] ?? null,
            ]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao gerar link: ' . $e->getMessage()]);
            return;
        }
    }

    public function pedidosItem($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';

        header('Content-Type: application/json; charset=utf-8');

        if ($produtoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            return;
        }

        try {
            // Detectar tabela de itens do pedido
            $itensTable = $this->findPedidoItensTable();
            if (!$itensTable) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            // Estratégia 1: buscar pedidos via lista_compras.pedido_id
            $pedidoIds = [];
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            if ($temPedidoEmLista) {
                $whereLoja = '';
                $params = [':produto_id' => $produtoId];
                if ($temLojaIdEmLista) {
                    if ($semLoja) {
                        $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                    } elseif ($lojaId > 0) {
                        $whereLoja = ' AND lc.loja_id = :loja_id';
                        $params[':loja_id'] = $lojaId;
                    }
                }
                $stmt = $this->connection->prepare(
                    "SELECT DISTINCT lc.pedido_id FROM lista_compras lc
                     WHERE lc.produto_id = :produto_id AND lc.pedido_id IS NOT NULL AND lc.pedido_id <> 0" . $whereLoja
                );
                $stmt->execute($params);
                $pedidoIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            }

            // Estratégia 2: buscar pedidos que contêm esse produto na tabela de itens
            // Excluir pedidos cancelados/apagados
            $statusExcluidos = "('cancelado','cancelled','apagado','deleted','lixeira','trash','rejeitado','rejected')";
            try {
                $stItens = $this->connection->prepare(
                    "SELECT DISTINCT i.pedido_id FROM {$itensTable} i
                     INNER JOIN pedidos ped ON ped.id = i.pedido_id
                     WHERE i.produto_id = ? AND i.pedido_id IS NOT NULL AND i.pedido_id > 0
                     AND LOWER(COALESCE(ped.status,'')) NOT IN {$statusExcluidos}"
                );
                $stItens->execute([$produtoId]);
                $pedidoIdsFromItens = $stItens->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                $pedidoIds = array_values(array_unique(array_merge($pedidoIds, $pedidoIdsFromItens)));
            } catch (\Exception $e) {}

            if (empty($pedidoIds)) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            // Buscar dados dos pedidos
            $in = implode(',', array_fill(0, count($pedidoIds), '?'));

            // Detectar coluna de cliente
            $colsPed = [];
            try { $stC = $this->connection->query('DESCRIBE pedidos'); $colsPed = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $clienteCol = in_array('cliente_id', $colsPed, true) ? 'cliente_id' : (in_array('usuario_id', $colsPed, true) ? 'usuario_id' : '');
            $totalCol = in_array('total', $colsPed, true) ? 'total' : (in_array('valor_total', $colsPed, true) ? 'valor_total' : '');

            $selectPed = 'p.*';
            $joinUser = '';
            if ($clienteCol !== '') {
                // Detectar coluna nome do usuario
                $colsUser = [];
                try { $stU = $this->connection->query('DESCRIBE usuarios'); $colsUser = $stU ? $stU->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $nomeCol = in_array('nome', $colsUser, true) ? 'nome' : (in_array('name', $colsUser, true) ? 'name' : '');
                if ($nomeCol !== '') {
                    $selectPed .= ", u.{$nomeCol} as cliente_nome, u.email as cliente_email";
                    $joinUser = " LEFT JOIN usuarios u ON u.id = p.{$clienteCol}";
                }
            }

            $stmtPedidos = $this->connection->prepare("SELECT {$selectPed} FROM pedidos p{$joinUser} WHERE p.id IN ({$in}) AND LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelled','apagado','deleted','lixeira','trash','rejeitado','rejected') ORDER BY p.id DESC");
            $stmtPedidos->execute(array_map('intval', $pedidoIds));
            $pedidosRows = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $pedidos = [];
            foreach ($pedidosRows as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $pedidos[$pid] = [
                    'id' => $pid,
                    'codigo_pedido' => (string) ($p['codigo_pedido'] ?? ($p['numero_pedido'] ?? '')),
                    'status' => (string) ($p['status'] ?? ''),
                    'valor_total' => $totalCol ? (float) ($p[$totalCol] ?? 0) : null,
                    'moeda' => (string) ($p['moeda'] ?? ''),
                    'created_at' => (string) ($p['created_at'] ?? ''),
                    'pago_em' => (string) ($p['pago_em'] ?? ''),
                    'cliente_nome' => (string) ($p['cliente_nome'] ?? ''),
                    'cliente_email' => (string) ($p['cliente_email'] ?? ''),
                    'payment_gateway' => (string) ($p['payment_gateway'] ?? ($p['gateway'] ?? '')),
                    'payment_id' => (string) ($p['payment_id'] ?? ($p['asaas_payment_id'] ?? '')),
                    'itens' => [],
                ];
            }

            // Buscar itens desse produto nos pedidos
            $stmtItens = $this->connection->prepare(
                "SELECT i.* FROM {$itensTable} i WHERE i.pedido_id IN ({$in}) AND i.produto_id = ?"
            );
            $vals = array_map('intval', $pedidoIds);
            $vals[] = $produtoId;
            $stmtItens->execute($vals);
            $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($itens as $it) {
                $pid = (int) ($it['pedido_id'] ?? 0);
                if (!isset($pedidos[$pid])) continue;
                $pedidos[$pid]['itens'][] = [
                    'produto_id' => (int) ($it['produto_id'] ?? 0),
                    'quantidade' => (int) ($it['quantidade'] ?? 0),
                    'preco_unitario' => isset($it['preco_unitario']) ? (float) $it['preco_unitario'] : null,
                    'subtotal' => isset($it['subtotal']) ? (float) $it['subtotal'] : null,
                    'nome_produto' => (string) ($it['nome_produto'] ?? ($it['nome_produto_sku'] ?? '')),
                ];
            }

            echo json_encode(['success' => true, 'pedidos' => array_values($pedidos)]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
    }

    public function reabrirCompras($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

        try {
            // Registrar quais itens vão ser reabertos para exibir apenas eles após o redirect
            $whereLoja = '';
            $selParams = [];
            if ($produtoId > 0) {
                $selParams[':produto_id'] = $produtoId;
            }
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $whereLoja .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $whereLoja .= ' AND lc.loja_id = :loja_id';
                    $selParams[':loja_id'] = $lojaId;
                }
            }

            $selSql = "SELECT DISTINCT lc.produto_id";
            if ($temLojaIdEmLista) {
                $selSql .= ", COALESCE(lc.loja_id,0) as loja_id";
            } else {
                $selSql .= ", 0 as loja_id";
            }
            $selSql .= " FROM lista_compras lc WHERE lc.status IN ('comprado','cancelado')";
            if ($produtoId > 0) {
                $selSql .= ' AND lc.produto_id = :produto_id';
            }
            $selSql .= $whereLoja;

            $stmtSel = $this->connection->prepare($selSql);
            $stmtSel->execute($selParams);
            $reabertosRows = $stmtSel->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['compras_reabertas'] = [
                'ts' => time(),
                'produto_id' => $produtoId,
                'loja_id' => $lojaId,
                'sem_loja' => $semLoja ? 1 : 0,
                'items' => array_values(array_map(function ($r) {
                    return [
                        'produto_id' => (int) ($r['produto_id'] ?? 0),
                        'loja_id' => (int) ($r['loja_id'] ?? 0),
                    ];
                }, $reabertosRows)),
            ];

            $sql = "UPDATE lista_compras lc SET lc.status = 'pendente' WHERE lc.status IN ('comprado','cancelado')";
            $params = [];
            if ($produtoId > 0) {
                $sql .= ' AND lc.produto_id = :produto_id';
                $params[':produto_id'] = $produtoId;
            }
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $affected = (int) $stmt->rowCount();

            // fallback: se nenhum registro foi marcado como comprado e o filtro por loja pode estar divergente,
            // tentar novamente sem restringir por loja.
            if ($affected === 0 && $temLojaIdEmLista && !$semLoja && $lojaId > 0) {
                $sql2 = "UPDATE lista_compras lc SET lc.status = 'comprado', lc.quantidade_faltante = 0 WHERE lc.status = 'pendente'";
                $params2 = [];
                if ($produtoId > 0) {
                    $sql2 .= ' AND lc.produto_id = :produto_id';
                    $params2[':produto_id'] = $produtoId;
                }
                $stmt2 = $this->connection->prepare($sql2);
                $stmt2->execute($params2);
                $affected = (int) $stmt2->rowCount();
            }

            $_SESSION['message'] = 'Itens reabertos.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras?status=pendente&somente_reabertos=1' . ($semLoja ? '&sem_loja=1' : ($lojaId > 0 ? ('&loja_id=' . $lojaId) : '')));
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao reabrir itens.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras?status=concluidas');
            exit;
        }
    }

    public function removerItem($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);

        if ($produtoId <= 0) {
            $_SESSION['message'] = 'Parâmetros inválidos.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }

        try {
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $whereLoja = '';
            $params = [':produto_id' => $produtoId];
            if ($temLojaIdEmLista) {
                if ($lojaId > 0) {
                    $whereLoja = ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                } else {
                    $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                }
            }

            $stmt = $this->connection->prepare("UPDATE lista_compras lc SET lc.status = 'cancelado' WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id" . $whereLoja);
            $stmt->execute($params);

            $affected = (int) $stmt->rowCount();

            // Se nenhum registro foi afetado e o filtro por loja pode estar divergente do registro na lista,
            // tentar remover todas as pendências do produto (independente de loja).
            if ($affected === 0 && $lojaId > 0) {
                $stmt2 = $this->connection->prepare("UPDATE lista_compras lc SET lc.status = 'cancelado' WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id");
                $stmt2->execute([':produto_id' => $produtoId]);
                $affected = (int) $stmt2->rowCount();
            }

            if ($affected > 0) {
                $_SESSION['message'] = 'Item removido da lista.';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Nenhum item pendente encontrado para remover.';
                $_SESSION['message_type'] = 'warning';
            }
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao remover item.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->connection->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function fetchLojas(): array {
        try {
            if (!$this->tableExists('lojas')) {
                return [];
            }
            $stmt = $this->connection->query('SELECT id, nome, slug, ativo FROM lojas WHERE ativo = 1 ORDER BY nome ASC');
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function resolveProdutoImagem(array $produto): ?string {
        $candidates = ['foto_principal', 'imagem', 'image', 'thumb', 'thumbnail', 'imagem_raw', 'images'];
        foreach ($candidates as $c) {
            if (!isset($produto[$c]) || $produto[$c] === null || $produto[$c] === '') {
                continue;
            }
            $raw = $produto[$c];
            if (is_string($raw)) {
                $s = trim($raw);
                if ($s === '') continue;
                // JSON de imagens
                if ($s[0] === '{' || $s[0] === '[') {
                    $decoded = json_decode($s, true);
                    if (is_array($decoded)) {
                        $paths = [];
                        if (isset($decoded['principal'])) $paths[] = $decoded['principal'];
                        if (isset($decoded['capa'])) $paths[] = $decoded['capa'];
                        if (isset($decoded['url'])) $paths[] = $decoded['url'];
                        if (isset($decoded['src'])) $paths[] = $decoded['src'];
                        if (isset($decoded['0'])) $paths[] = $decoded['0'];
                        if (isset($decoded[0])) $paths[] = $decoded[0];
                        foreach ($paths as $p) {
                            if (is_string($p) && trim($p) !== '') {
                                $s = trim($p);
                                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                                    return $s;
                                }
                                // Correção: alguns registros salvam URL pública como "/uploads/produtos/https://..."
                                if (strpos($s, '/uploads/produtos/http://') === 0 || strpos($s, '/uploads/produtos/https://') === 0) {
                                    return substr($s, strlen('/uploads/produtos/'));
                                }
                                // Se já estiver no path correto, manter
                                if (strpos($s, '/uploads/produtos/') === 0) {
                                    return $s;
                                }
                                // Se vier como 'uploads/produtos/...' (sem barra inicial), normalizar
                                if (strpos($s, 'uploads/produtos/') === 0) {
                                    $s = substr($s, strlen('uploads/produtos/'));
                                }
                                // Se vier como 'images/...' (sem barra inicial), manter em /images
                                if (strpos($s, 'images/') === 0) {
                                    return '/' . ltrim($s, '/');
                                }
                                if (strpos($s, '/') === 0) {
                                    return $s;
                                }
                                return '/uploads/produtos/' . ltrim($s, '/');
                            }
                            if (is_array($p) && !empty($p['url']) && is_string($p['url'])) {
                                $s = trim((string) $p['url']);
                                if ($s === '') continue;
                                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                                    return $s;
                                }
                                if (strpos($s, '/uploads/produtos/') === 0) {
                                    return $s;
                                }
                                if (strpos($s, 'uploads/produtos/') === 0) {
                                    $s = substr($s, strlen('uploads/produtos/'));
                                }
                                if (strpos($s, 'images/') === 0) {
                                    return '/' . ltrim($s, '/');
                                }
                                if (strpos($s, '/') === 0) {
                                    return $s;
                                }
                                return '/uploads/produtos/' . ltrim($s, '/');
                            }
                            if (is_array($p) && !empty($p['src']) && is_string($p['src'])) {
                                $s = trim((string) $p['src']);
                                if ($s === '') continue;
                                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                                    return $s;
                                }
                                if (strpos($s, '/uploads/produtos/') === 0) {
                                    return $s;
                                }
                                if (strpos($s, 'uploads/produtos/') === 0) {
                                    $s = substr($s, strlen('uploads/produtos/'));
                                }
                                if (strpos($s, 'images/') === 0) {
                                    return '/' . ltrim($s, '/');
                                }
                                if (strpos($s, '/') === 0) {
                                    return $s;
                                }
                                return '/uploads/produtos/' . ltrim($s, '/');
                            }
                            if (is_array($p) && !empty($p['path']) && is_string($p['path'])) {
                                $s = trim((string) $p['path']);
                                if ($s === '') continue;
                                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                                    return $s;
                                }
                                if (strpos($s, '/uploads/produtos/') === 0) {
                                    return $s;
                                }
                                if (strpos($s, 'uploads/produtos/') === 0) {
                                    $s = substr($s, strlen('uploads/produtos/'));
                                }
                                if (strpos($s, 'images/') === 0) {
                                    return '/' . ltrim($s, '/');
                                }
                                if (strpos($s, '/') === 0) {
                                    return $s;
                                }
                                return '/uploads/produtos/' . ltrim($s, '/');
                            }
                            if (is_array($p) && isset($p[0]) && is_string($p[0]) && trim((string) $p[0]) !== '') {
                                $s = trim((string) $p[0]);
                                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                                    return $s;
                                }
                                if (strpos($s, '/uploads/produtos/') === 0) {
                                    return $s;
                                }
                                if (strpos($s, 'uploads/produtos/') === 0) {
                                    $s = substr($s, strlen('uploads/produtos/'));
                                }
                                if (strpos($s, 'images/') === 0) {
                                    return '/' . ltrim($s, '/');
                                }
                                if (strpos($s, '/') === 0) {
                                    return $s;
                                }
                                return '/uploads/produtos/' . ltrim($s, '/');
                            }
                        }
                    }
                }

                if (preg_match('#^https?://#i', $s) || strpos($s, '//') === 0) {
                    return $s;
                }

                // Correção: alguns registros salvam URL pública como "/uploads/produtos/https://..."
                if (strpos($s, '/uploads/produtos/http://') === 0 || strpos($s, '/uploads/produtos/https://') === 0) {
                    return substr($s, strlen('/uploads/produtos/'));
                }
                if (strpos($s, '/uploads/produtos/') === 0) {
                    return $s;
                }
                if (strpos($s, 'uploads/produtos/') === 0) {
                    $s = substr($s, strlen('uploads/produtos/'));
                }
                if (strpos($s, 'images/') === 0) {
                    return '/' . ltrim($s, '/');
                }
                if (strpos($s, '/') === 0) {
                    return $s;
                }
                return '/uploads/produtos/' . ltrim($s, '/');
            }
        }

        // Fallback: buscar em produto_fotos quando existir
        $pid = (int) ($produto['produto_id'] ?? ($produto['id'] ?? 0));
        if ($pid > 0 && $this->tableExists('produto_fotos')) {
            try {
                $stmt = $this->connection->prepare('SELECT nome_arquivo FROM produto_fotos WHERE produto_id = :pid ORDER BY principal DESC, ordem ASC, id DESC LIMIT 1');
                $stmt->execute([':pid' => $pid]);
                $file = (string) ($stmt->fetchColumn() ?: '');
                if ($file !== '') {
                    $file = trim($file);
                    if (strpos($file, 'images/') === 0) {
                        return '/' . ltrim($file, '/');
                    }
                    if ($file !== '' && !(preg_match('#^https?://#i', $file) || strpos($file, '//') === 0 || strpos($file, '/') === 0)) {
                        return '/uploads/produtos/' . ltrim($file, '/');
                    }
                    return $file;
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    public function index($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        try {
            $lojas = $this->fetchLojas();
            $lojaIdFilter = (int) $request->getParam('loja_id', 0);
            $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
            $statusView = (string) $request->getParam('status', 'pendente');
            $statusView = in_array($statusView, ['pendente', 'concluidas'], true) ? $statusView : 'pendente';

            $tipoCompraView = strtolower(trim((string) $request->getParam('tipo_compra', 'todos')));
            if (!in_array($tipoCompraView, ['offline', 'online', 'carne', 'todos'], true)) {
                $tipoCompraView = 'todos';
            }

            $somenteReabertos = (string) $request->getParam('somente_reabertos', '0') === '1';
            $reabertos = null;
            if ($somenteReabertos && isset($_SESSION['compras_reabertas']) && is_array($_SESSION['compras_reabertas'])) {
                $reabertos = $_SESSION['compras_reabertas'];
            }

            $produtosSelect = $this->fetchProdutosSelect();
            $pedidosSelect = $this->fetchPedidosSelect();

            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temTipoCompraEmLista = $this->columnExists('lista_compras', 'tipo_compra');
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $temCost = $this->columnExists('produtos', 'cost_price');
            $temFoto = $this->columnExists('produtos', 'foto_principal');
            $temImages = $this->columnExists('produtos', 'images');
            $temLojaText = $this->columnExists('produtos', 'loja');

            $temImagem = $this->columnExists('produtos', 'imagem');
            $temImage = $this->columnExists('produtos', 'image');
            $temThumb = $this->columnExists('produtos', 'thumb');
            $temThumbnail = $this->columnExists('produtos', 'thumbnail');

            $whereTipoCompra = '';
            if ($temTipoCompraEmLista) {
                if ($tipoCompraView === 'offline') {
                    $whereTipoCompra = " AND (lc.tipo_compra = 'offline' OR lc.tipo_compra IS NULL OR lc.tipo_compra = '')";
                } elseif ($tipoCompraView === 'online') {
                    $whereTipoCompra = " AND (lc.tipo_compra = 'online' OR lc.tipo_compra IS NULL OR lc.tipo_compra = '')";
                } elseif ($tipoCompraView === 'carne') {
                    $whereTipoCompra = " AND lc.tipo_compra = 'carne'";
                } else {
                    $whereTipoCompra = '';
                }
            }

            $selectCols = [
                'p.id as produto_id',
                'p.sku as sku',
            ];
            if ($this->columnExists('produtos', 'name')) {
                $selectCols[] = 'p.name as produto_nome';
            } elseif ($this->columnExists('produtos', 'nome')) {
                $selectCols[] = 'p.nome as produto_nome';
            } else {
                $selectCols[] = "'' as produto_nome";
            }
            if ($temCost) $selectCols[] = 'p.cost_price as cost_price';
            if ($this->columnExists('produtos', 'price')) $selectCols[] = 'p.price as price';
            if ($temLojaIdEmProdutos) $selectCols[] = 'p.loja_id as produto_loja_id';
            if ($temLojaText) $selectCols[] = 'p.loja as produto_loja';
            if ($temFoto) $selectCols[] = 'p.foto_principal as foto_principal';
            if ($temImages) $selectCols[] = 'p.images as images';
            if ($temImagem) $selectCols[] = 'p.imagem as imagem';
            if ($temImage) $selectCols[] = 'p.image as image';
            if ($temThumb) $selectCols[] = 'p.thumb as thumb';
            if ($temThumbnail) $selectCols[] = 'p.thumbnail as thumbnail';

            // Consolidar por produto + loja (para não repetir linhas)
            $rankExpr = "CASE lc.prioridade WHEN 'urgente' THEN 4 WHEN 'alta' THEN 3 WHEN 'media' THEN 2 WHEN 'baixa' THEN 1 ELSE 0 END";
            $sql = 'SELECT ' . implode(', ', $selectCols)
                . ', agg.quantidade_faltante as quantidade_faltante'
                . ', agg.quantidade_necessaria as quantidade_necessaria'
                . ', agg.prioridade as prioridade'
                . ', agg.data_solicitacao as data_solicitacao'
                . ', agg.loja_id as loja_id'
                . ', agg.status as status'
                . ', agg.nome_produto_custom as nome_produto_custom'
                . ($temTipoCompraEmLista ? ', agg.tipo_compra as tipo_compra' : ", '' as tipo_compra")
                . ' FROM ('
                . '   SELECT lc.produto_id, '
                . ($temLojaIdEmLista && $temLojaIdEmProdutos
                    ? 'COALESCE(NULLIF(lc.loja_id,0), p_inner.loja_id, 0) as loja_id'
                    : ($temLojaIdEmLista ? 'COALESCE(lc.loja_id,0) as loja_id' : '0 as loja_id'))
                . '     , lc.status as status'
                . ($this->columnExists('lista_compras', 'nome_produto') ? ', COALESCE(lc.nome_produto, \'\') as nome_produto_custom' : ", '' as nome_produto_custom")
                . ($temTipoCompraEmLista ? ", COALESCE(lc.tipo_compra, '') as tipo_compra" : ", '' as tipo_compra")
                . '     , SUM(CASE WHEN COALESCE(lc.quantidade_faltante,0) > 0 THEN lc.quantidade_faltante ELSE COALESCE(lc.quantidade_necessaria,0) END) as quantidade_faltante'
                . '     , SUM(COALESCE(lc.quantidade_necessaria,0)) as quantidade_necessaria'
                . '     , MIN(COALESCE(lc.data_solicitacao, CURDATE())) as data_solicitacao'
                . '     , CASE MAX(' . $rankExpr . ") WHEN 4 THEN 'urgente' WHEN 3 THEN 'alta' WHEN 2 THEN 'media' WHEN 1 THEN 'baixa' ELSE 'media' END as prioridade"
                . '   FROM lista_compras lc'
                . ($temLojaIdEmProdutos ? ' LEFT JOIN produtos p_inner ON p_inner.id = lc.produto_id' : '')
                . ($temPedidoEmLista ? ' LEFT JOIN pedidos ped ON ped.id = lc.pedido_id' : '')
                . '   WHERE '
                . ($statusView === 'concluidas' ? "lc.status IN ('comprado','cancelado')" : "lc.status = 'pendente'")
                . ($temPedidoEmLista ? " AND (lc.pedido_id IS NULL OR lc.pedido_id = 0 OR ped.status IN ('pago','processando','enviado','entregue','consolidado','produto_consolidado','rascunho_etiqueta','etiqueta_efetivada','aguardando_lib_alfandegaria','finalizacao_embalagem','entrega_finalizada','carne_pagando','carne_aguardando','pagamento'))" : '')
                . $whereTipoCompra
                . ($reabertos && !empty($reabertos['items'])
                    ? ($temLojaIdEmLista
                        ? (' AND (' . implode(' OR ', array_values(array_filter(array_map(function ($x) {
                            $pid = (int) ($x['produto_id'] ?? 0);
                            $lid = (int) ($x['loja_id'] ?? 0);
                            if ($pid <= 0) return '';
                            return '(lc.produto_id = ' . $pid . ' AND COALESCE(lc.loja_id,0) = ' . $lid . ')';
                        }, (array) $reabertos['items'])))) . ')')
                        : (' AND lc.produto_id IN (' . implode(',', array_values(array_unique(array_map(function ($x) {
                            return (int) ($x['produto_id'] ?? 0);
                        }, (array) $reabertos['items'])))) . ')'))
                    : '')
                . '   GROUP BY lc.produto_id, '
                . ($this->columnExists('lista_compras', 'nome_produto') ? 'COALESCE(lc.nome_produto, \'\'), ' : '')
                . ($temTipoCompraEmLista ? "COALESCE(lc.tipo_compra, ''), " : '')
                . ($temLojaIdEmLista && $temLojaIdEmProdutos
                    ? 'COALESCE(NULLIF(lc.loja_id,0), p_inner.loja_id, 0), lc.status'
                    : ($temLojaIdEmLista ? 'COALESCE(lc.loja_id,0), lc.status' : '0, lc.status'))
                . ' ) agg'
                . ' LEFT JOIN produtos p ON agg.produto_id = p.id';

            $params = [];
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' WHERE agg.loja_id = 0';
                } elseif ($lojaIdFilter > 0) {
                    $sql .= ' WHERE agg.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaIdFilter;
                }
            }
            $sql .= ' ORDER BY agg.prioridade DESC, agg.data_solicitacao ASC';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $compras = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Limpar filtro após exibir (para não "grudar" na sessão)
            if ($reabertos !== null) {
                unset($_SESSION['compras_reabertas']);
            }

            // Estatísticas: itens/valor pendente
            $totalItensPendentes = 0;
            $valorTotalPendente = 0.0;
            $valorTotalPago = 0.0;
            foreach ($compras as $c) {
                $qfRaw = isset($c['quantidade_faltante']) ? (int) $c['quantidade_faltante'] : null;
                $qf = ($qfRaw !== null && $qfRaw > 0)
                    ? $qfRaw
                    : (int) ($c['quantidade_necessaria'] ?? 0);
                $totalItensPendentes += $qf;
                $cost = isset($c['cost_price']) ? (float) $c['cost_price'] : 0.0;
                $valorTotalPendente += ($qf * $cost);
            }

            // Total efetivamente pago (somando subtotais dos itens em pedidos pagos)
            $itensTable = $this->findPedidoItensTable();
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            if ($itensTable && $temPedidoEmLista && !empty($compras)) {
                $produtoIds = [];
                foreach ($compras as $c) {
                    $pid = (int) ($c['produto_id'] ?? 0);
                    if ($pid > 0) {
                        $produtoIds[$pid] = true;
                    }
                }
                $produtoIds = array_keys($produtoIds);

                if (!empty($produtoIds)) {
                    $inProdutos = implode(',', array_fill(0, count($produtoIds), '?'));

                    $produtoIdsList = $produtoIds;
                    $lcParams = array_map('intval', $produtoIdsList);

                    $lcSql = "SELECT DISTINCT pedido_id FROM lista_compras WHERE pedido_id IS NOT NULL AND pedido_id <> 0 AND produto_id IN ($inProdutos)";
                    if ($statusView === 'concluidas') {
                        $lcSql .= " AND status IN ('comprado','cancelado')";
                    } else {
                        $lcSql .= " AND status = 'pendente'";
                    }
                    if ($temLojaIdEmLista) {
                        if ($semLoja) {
                            $lcSql .= " AND (loja_id IS NULL OR loja_id = 0)";
                        } elseif ($lojaIdFilter > 0) {
                            $lcSql .= " AND loja_id = ?";
                            $lcParams[] = (int) $lojaIdFilter;
                        }
                    }

                    $stmtLc = $this->connection->prepare($lcSql);
                    $stmtLc->execute($lcParams);
                    $pedidoIds = $stmtLc->fetchAll(\PDO::FETCH_COLUMN) ?: [];

                    if (!empty($pedidoIds)) {
                        $pedidoIds = array_values(array_unique(array_map('intval', $pedidoIds)));
                        $inPedidos = implode(',', array_fill(0, count($pedidoIds), '?'));

                        $stmtPedidos = $this->connection->prepare("SELECT id, status, pago_em, payment_status FROM pedidos WHERE id IN ($inPedidos)");
                        $stmtPedidos->execute($pedidoIds);
                        $pedidoRows = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        $pedidoPago = [];
                        foreach ($pedidoRows as $pr) {
                            $pedidoPago[(int) $pr['id']] = $this->pedidoEstaPago($pr);
                        }

                        $unitExpr = '0';
                        if ($this->columnExists($itensTable, 'preco_unitario')) {
                            $unitExpr = 'i.preco_unitario';
                        } elseif ($this->columnExists($itensTable, 'valor_unitario')) {
                            $unitExpr = 'i.valor_unitario';
                        }

                        $stmtItens = $this->connection->prepare(
                            "SELECT i.pedido_id, i.produto_id, COALESCE(i.subtotal, (({$unitExpr}) * COALESCE(i.quantidade,0))) as subtotal
                             FROM {$itensTable} i
                             WHERE i.pedido_id IN ($inPedidos) AND i.produto_id IN ($inProdutos)"
                        );
                        $stmtItens->execute(array_merge($pedidoIds, array_map('intval', $produtoIdsList)));
                        $itRows = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        foreach ($itRows as $ir) {
                            $pid = (int) ($ir['pedido_id'] ?? 0);
                            if ($pid <= 0) continue;
                            if (empty($pedidoPago[$pid])) continue;
                            $valorTotalPago += (float) ($ir['subtotal'] ?? 0);
                        }
                    }
                }
            }

            // Contadores gerais
            $stmt = $this->connection->prepare("SELECT COUNT(*) as total_itens, SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes, SUM(CASE WHEN status = 'comprado' THEN 1 ELSE 0 END) as comprados, SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados FROM lista_compras");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['message'] = 'Erro ao carregar lista de compras: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            $compras = [];
            $estatisticas = ['total_itens' => 0, 'pendentes' => 0, 'comprados' => 0, 'cancelados' => 0];
            $lojas = [];
            $lojaIdFilter = 0;
            $semLoja = false;
            $statusView = 'pendente';
            $tipoCompraView = 'todos';
            $totalItensPendentes = 0;
            $valorTotalPendente = 0.0;
            $produtosSelect = [];
            $pedidosSelect = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('compras');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shopping-basket me-2"></i>Lista de Compras</h1>
                    <div>';

        echo '<button type="button" class="btn btn-primary me-2" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>';

        if ($statusView !== 'pendente') {
            echo '<form method="POST" action="/admin/estoque/compras/reabrir" class="d-inline">
                            <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                            <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                            <button type="button" class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalReabrirCompras">
                                <i class="fas fa-rotate-left me-1"></i>Reabrir itens
                            </button>
                        </form>';
        }

        echo '<button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

                $this->renderFlashIfAny();

                echo '<div class="alert alert-info mb-3">'
                    . '<div><strong>Importante:</strong> as quantidades exibidas na Lista de Compras representam o <strong>faltante</strong> considerando o estoque cadastrado (tela de Estoque), e não necessariamente o total pedido.</div>'
                    . '<div class="small text-muted mt-1">Pedidos do site (online) e pedidos manuais seguem a mesma regra de cálculo do faltante.</div>'
                    . '</div>';

                $qsLoja = '';
                if ($semLoja) {
                    $qsLoja = '&sem_loja=1';
                } elseif ($lojaIdFilter > 0) {
                    $qsLoja = '&loja_id=' . (int) $lojaIdFilter;
                }

                echo '<div class="d-flex flex-wrap gap-2 mb-2">'
                    . '<a class="btn btn-sm ' . ($statusView === 'pendente' ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=pendente' . $qsLoja . '">Pendentes</a>'
                    . '<a class="btn btn-sm ' . ($statusView === 'concluidas' ? 'btn-secondary' : 'btn-outline-secondary') . '" href="/admin/estoque/compras?status=concluidas' . $qsLoja . '">Concluídas</a>'
                    . '</div>';

                echo '<div class="card mb-4">
                    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a class="btn btn-sm ' . (!$semLoja && $lojaIdFilter === 0 ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=' . $statusView . '">Todas</a>';

                foreach ($lojas as $l) {
                    $lid = (int) ($l['id'] ?? 0);
                    $lname = (string) ($l['nome'] ?? '');
                    $active = (!$semLoja && $lojaIdFilter === $lid);
                    echo '<a class="btn btn-sm ' . ($active ? 'btn-primary' : 'btn-outline-primary') . '" href="/admin/estoque/compras?status=' . $statusView . '&loja_id=' . $lid . '">' . htmlspecialchars($lname) . '</a>';
                }

                echo '<a class="btn btn-sm ' . ($semLoja ? 'btn-danger' : 'btn-outline-danger') . '" href="/admin/estoque/compras?status=' . $statusView . '&sem_loja=1">Sem loja</a>'
                    . '</div>'
                    . '<div class="d-flex flex-wrap gap-1 align-items-center"><small class="text-muted me-1">Tipo:</small>'
                    . '<a class="btn btn-sm ' . ($tipoCompraView === 'todos' ? 'btn-dark' : 'btn-outline-dark') . '" href="/admin/estoque/compras?status=' . $statusView . $qsLoja . '&tipo_compra=todos">Todos</a>'
                    . '<a class="btn btn-sm ' . ($tipoCompraView === 'online' ? 'btn-dark' : 'btn-outline-dark') . '" href="/admin/estoque/compras?status=' . $statusView . $qsLoja . '&tipo_compra=online">Online</a>'
                    . '<a class="btn btn-sm ' . ($tipoCompraView === 'offline' ? 'btn-dark' : 'btn-outline-dark') . '" href="/admin/estoque/compras?status=' . $statusView . $qsLoja . '&tipo_compra=offline">Offline</a>'
                    . '<a class="btn btn-sm ' . ($tipoCompraView === 'carne' ? 'btn-warning' : 'btn-outline-warning') . '" href="/admin/estoque/compras?status=' . $statusView . $qsLoja . '&tipo_compra=carne"><i class="fas fa-file-invoice-dollar me-1"></i>Carnê</a>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                // Cards de Estatísticas
                echo '<div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Itens</h5>
                                <h3>' . number_format($estatisticas['total_itens']) . '</h3>
                                <small>Na lista de compras</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Pendentes</h5>
                                <h3>' . number_format($estatisticas['pendentes']) . '</h3>
                                <small>Aguardando compra</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Comprados</h5>
                                <h3>' . number_format($estatisticas['comprados']) . '</h3>
                                <small>Itens adquiridos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Cancelados</h5>
                                <h3>' . number_format($estatisticas['cancelados']) . '</h3>
                                <small>Itens cancelados</small>
                            </div>
                        </div>
                    </div>
                </div>';

                // Tabela de Compras
                echo '<div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Itens da Lista de Compras</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-end gap-2 mb-3">';

                if ($semLoja) {
                    echo '<button type="button" class="btn btn-sm btn-outline-danger" disabled>PDF (Sem loja)</button>';
                } elseif ($lojaIdFilter > 0) {
                    echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/admin/estoque/compras/pdf?loja_id=' . (int) $lojaIdFilter . '\', \'_blank\')"><i class="fas fa-file-pdf me-1"></i>PDF desta loja</button>';
                } else {
                    echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')"><i class="fas fa-file-pdf me-1"></i>PDF (geral)</button>';
                }

                echo '        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Loja</th>
                                        <th>Quantidade</th>
                                        <th>Status</th>
                                        <th>Prioridade</th>
                                        <th>Data Solicitação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($compras as $item) {
                                    $status_class = $item['status'] == 'pendente' ? 'warning' : 
                                                   ($item['status'] == 'comprado' ? 'success' : 'danger');
                                    $prioridade_class = $item['prioridade'] == 'urgente' ? 'danger' : 
                                                       ($item['prioridade'] == 'alta' ? 'warning' : 'info');

                                    $qf = (int) ($item['quantidade_faltante'] ?? 0);
                                    if ($qf <= 0) {
                                        $qf = (int) ($item['quantidade_necessaria'] ?? 0);
                                    }

                                    $imgUrl = $this->resolveProdutoImagem($item);
                                    $imgTag = $imgUrl
                                        ? '<img src="' . htmlspecialchars($imgUrl) . '" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px; border: 1px solid rgba(148, 163, 184, 0.22); background: rgba(148, 163, 184, 0.06);">'
                                        : '<div style="width:36px;height:36px;border-radius:10px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>';

                                    $lojaNome = '-';
                                    $lojaIdRow = (int) ($item['loja_id'] ?? 0);
                                    if ($lojaIdRow <= 0) {
                                        $lojaIdRow = (int) ($item['produto_loja_id'] ?? 0);
                                    }
                                    if ($lojaIdRow > 0 && $this->tableExists('lojas')) {
                                        try {
                                            $stmtLn = $this->connection->prepare('SELECT nome FROM lojas WHERE id = :id LIMIT 1');
                                            $stmtLn->execute([':id' => $lojaIdRow]);
                                            $ln = $stmtLn->fetchColumn();
                                            if ($ln !== false && (string) $ln !== '') $lojaNome = (string) $ln;
                                        } catch (\Exception $e) {
                                        }
                                    }

                                    $missingLoja = ($lojaIdRow <= 0);

                                    // Usar nome customizado se disponível (item com nome editado no pedido)
                                    $nomeCustom = trim((string) ($item['nome_produto_custom'] ?? ''));
                                    if ($nomeCustom !== '') {
                                        $item['produto_nome'] = $nomeCustom;
                                    }
                                     
                                    $btnRemoverItem = '<button type="button" class="btn btn-outline-danger"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalRemoverItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-trash"></i></button>';
                                    $btnVerPedidos = '<button type="button" class="btn btn-outline-dark"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalPedidosItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-sem-loja="' . ($missingLoja ? '1' : '0') . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-eye"></i></button>';
                                    $btnReabrirItem = '<button type="button" class="btn btn-outline-secondary"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalReabrirItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . '><i class="fas fa-rotate-left"></i></button>';
                                    $btnLoja = $missingLoja
                                        ? '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalLoja" data-produto-id="' . (int) $item['produto_id'] . '" data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"><i class="fas fa-store"></i></button>'
                                        : '';
                                    $btnConcluirItem = '<button type="button" class="btn btn-outline-success"'
                                        . ' data-bs-toggle="modal" data-bs-target="#modalConcluirItem"'
                                        . ' data-produto-id="' . (int) $item['produto_id'] . '"'
                                        . ' data-loja-id="' . (int) $lojaIdRow . '"'
                                        . ' data-produto-nome="' . htmlspecialchars($item['produto_nome']) . '"'
                                        . ' data-quantidade="' . (int) $qf . '"'
                                        . '><i class="fas fa-check"></i></button>';

                                    echo '<tr>'
                                        . '<td>'
                                        . '<div class="d-flex gap-2 align-items-center">' . $imgTag . '<div>'
                                        . '<strong>' . htmlspecialchars($item['produto_nome']) . '</strong>'
                                        . '<br><small class="text-muted">ID: ' . (int) $item['produto_id'] . '</small>'
                                        . ((!empty($item['tipo_compra']) && $item['tipo_compra'] === 'carne') ? ' <span class="badge bg-warning text-dark" style="font-size:10px"><i class="fas fa-file-invoice-dollar me-1"></i>Carnê</span>' : '')
                                        . '</div></div>'
                                        . '</td>'
                                        . '<td>' . (!$missingLoja ? htmlspecialchars($lojaNome) : '<span class="badge bg-danger">Sem loja</span>') . '</td>'
                                        . '<td><span class="badge bg-primary">' . $qf . '</span></td>'
                                        . '<td><span class="badge bg-' . $status_class . '">' . ucfirst((string) $item['status']) . '</span></td>'
                                        . '<td><span class="badge bg-' . $prioridade_class . '">' . ucfirst((string) $item['prioridade']) . '</span></td>'
                                        . '<td>' . (!empty($item['data_solicitacao']) ? date('d/m/Y', strtotime((string) $item['data_solicitacao'])) : '-') . '</td>'
                                        . '<td>'
                                        . '<div class="btn-group btn-group-sm">'
                                        . $btnVerPedidos
                                        . ($statusView === 'pendente' ? $btnLoja : '')
                                        . ($statusView === 'pendente'
                                            ? $btnConcluirItem
                                            : $btnReabrirItem)
                                        . ($statusView === 'pendente' ? $btnRemoverItem : '')
                                        . '</div>'
                                        . '</td>'
                                        . '</tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

                // Modal: Definir loja
                echo '<div class="modal fade" id="modalLoja" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/definir-loja">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Definir loja do produto</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="modal_produto_id" value="">
                                        <div class="mb-2 text-muted" id="modal_produto_nome"></div>
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja_id" required>
                                            <option value="">Selecione...</option>';
                foreach ($lojas as $l) {
                    echo '<option value="' . (int) ($l['id'] ?? 0) . '">' . htmlspecialchars((string) ($l['nome'] ?? '')) . '</option>';
                }
                echo '            </select>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Salvar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                // Modal: Pedidos do item
                echo '<div class="modal fade" id="modalPedidosItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Pedidos relacionados</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2 text-muted" id="pedidos_produto_nome"></div>
                                    <div id="pedidos_loading" class="text-muted">Carregando...</div>
                                    <div id="pedidos_empty" class="alert alert-warning d-none">Nenhum pedido encontrado para este item.</div>
                                    <div class="accordion" id="accordionPedidos"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    function escapeHtml(str){
                        if (str === null || str === undefined) return "";
                        return String(str)
                            .replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/\"/g, "&quot;")
                            .replace(/\'/g, "&#039;");
                    }

                    function formatMoney(v){
                        if (v === null || v === undefined || v === "") return "-";
                        var n = Number(v);
                        if (isNaN(n)) return String(v);
                        return "$ " + n.toFixed(2);
                    }

                    function renderPedidosAccordion(pedidos){
                        var acc = document.getElementById("accordionPedidos");
                        if (!acc) return;
                        acc.innerHTML = "";

                        pedidos.forEach(function(p, idx){
                            var pid = p.id || 0;
                            var headId = "pedidoHead_" + pid;
                            var bodyId = "pedidoBody_" + pid;
                            var total = (p.valor_total !== null && p.valor_total !== undefined) ? formatMoney(p.valor_total) : "-";
                            var cliente = (p.cliente_nome || "") + (p.cliente_email ? (" - " + p.cliente_email) : "");
                            var criado = p.created_at ? escapeHtml(p.created_at) : "";
                            var pagoEm = p.pago_em ? escapeHtml(p.pago_em) : "";
                            var status = p.status ? escapeHtml(p.status) : "";
                            var codigo = p.codigo_pedido ? escapeHtml(p.codigo_pedido) : "";

                            var itensHtml = "";
                            if (Array.isArray(p.itens) && p.itens.length > 0) {
                                itensHtml += "<div class=\"table-responsive\"><table class=\"table table-sm\">";
                                itensHtml += "<thead><tr><th>Produto</th><th style=\"width:90px;\">Qtd</th><th style=\"width:120px;\">Preço</th><th style=\"width:120px;\">Subtotal</th></tr></thead><tbody>";
                                p.itens.forEach(function(it){
                                    itensHtml += "<tr>";
                                    itensHtml += "<td>" + escapeHtml(it.nome_produto || it.nome_produto_sku || ("Produto ID: " + (it.produto_id||""))) + "</td>";
                                    itensHtml += "<td>" + escapeHtml(it.quantidade || 0) + "</td>";
                                    itensHtml += "<td>" + formatMoney(it.preco_unitario) + "</td>";
                                    itensHtml += "<td>" + formatMoney(it.subtotal) + "</td>";
                                    itensHtml += "</tr>";
                                });
                                itensHtml += "</tbody></table></div>";
                            } else {
                                itensHtml = "<div class=\"text-muted\">Itens do pedido não disponíveis.</div>";
                            }

                            var html = "";
                            html += "<div class=\"accordion-item\">";
                            html += "<h2 class=\"accordion-header\" id=\"" + headId + "\">";
                            html += "<button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#" + bodyId + "\">";
                            html += "Pedido #" + pid + (codigo ? (" (" + codigo + ")") : "") + " - " + status + " - " + total;
                            html += "</button></h2>";
                            html += "<div id=\"" + bodyId + "\" class=\"accordion-collapse collapse\" data-bs-parent=\"#accordionPedidos\">";
                            html += "<div class=\"accordion-body\">";
                            html += "<div class=\"mb-2\">";
                            html += "<div><strong>Cliente:</strong> " + escapeHtml(cliente) + "</div>";
                            html += "<div><strong>Criado em:</strong> " + criado + "</div>";
                            if (pagoEm) html += "<div><strong>Pago em:</strong> " + pagoEm + "</div>";
                            var stLow = String(p.status || "").toLowerCase();
                            var pago = (stLow === "pago" || stLow === "paid" || stLow === "aprovado" || stLow === "approved" || (p.pago_em && String(p.pago_em).trim() !== ""));

                            html += "<div class=\"mt-2 d-flex flex-wrap gap-2\">";
                            html += "<a class=\"btn btn-sm btn-outline-primary\" href=\"/admin/pedidos/detalhes/" + pid + "\" target=\"_blank\">Abrir pedido</a>";
                            var gw = String(p.payment_gateway || p.gateway || "").toLowerCase();
                            var payId = String(p.payment_id || p.asaas_payment_id || "");
                            var temAsaas = (gw === "asaas" && payId.trim() !== "");
                            if (!pago && temAsaas) {
                                html += "<div class=\"input-group input-group-sm\" style=\"max-width:320px;\">";
                                html += "<span class=\"input-group-text\">$</span>";
                                html += "<input type=\"number\" step=\"0.01\" min=\"0\" class=\"form-control\" placeholder=\"Valor da diferença\" id=\"diff_val_" + pid + "\">";
                                html += "<button type=\"button\" class=\"btn btn-outline-success\" onclick=\"gerarLinkDiferenca(" + pid + ")\">Gerar link</button>";
                                html += "</div>";
                            } else if (!pago && !temAsaas) {
                                html += "<div class=\"text-muted small\">Cobrança de diferença disponível apenas para pedidos Asaas.</div>";
                            }
                            html += "</div>";
                            html += "<div class=\"mt-2\" id=\"diff_out_" + pid + "\"></div>";
                            html += "</div>";
                            html += itensHtml;
                            html += "</div></div></div>";
                            acc.insertAdjacentHTML("beforeend", html);
                        });
                    }

                    function gerarLinkDiferenca(pedidoId){
                        var out = document.getElementById("diff_out_" + pedidoId);
                        if (out) out.innerHTML = "<div class=\"text-muted\">Gerando link...</div>";
                        var inp = document.getElementById("diff_val_" + pedidoId);
                        var val = inp ? inp.value : "";
                        fetch("/admin/estoque/compras/gerar-link-diferenca", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "pedido_id=" + encodeURIComponent(String(pedidoId)) + "&valor=" + encodeURIComponent(String(val))
                        })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (!data || !data.success) {
                                if (out) out.innerHTML = "<div class=\"alert alert-danger\">" + escapeHtml((data && data.message) ? data.message : "Erro ao gerar link") + "</div>";
                                return;
                            }
                            var url = data.invoiceUrl || data.bankSlipUrl || "";
                            if (out) {
                                out.innerHTML = url
                                    ? ("<div class=\"alert alert-success\">Link gerado: <a href=\"" + escapeHtml(url) + "\" target=\"_blank\">Abrir cobrança</a></div>")
                                    : ("<div class=\"alert alert-success\">Link gerado com sucesso.</div>");
                            }
                        })
                        .catch(function(){
                            if (out) out.innerHTML = "<div class=\"alert alert-danger\">Erro ao gerar link</div>";
                        });
                    }

                    var modalPedidosItem = document.getElementById("modalPedidosItem");
                    if (modalPedidosItem) {
                        modalPedidosItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            var produtoId = button.getAttribute("data-produto-id");
                            var lojaId = button.getAttribute("data-loja-id");
                            var semLoja = button.getAttribute("data-sem-loja");
                            var produtoNome = button.getAttribute("data-produto-nome");
                            var lojaId = button.getAttribute("data-loja-id") || "0";
                            var semLoja = button.getAttribute("data-sem-loja") || "0";
                            var produtoNome = button.getAttribute("data-produto-nome") || "";

                            var label = document.getElementById("pedidos_produto_nome");
                            if (label) label.textContent = produtoNome;

                            var loading = document.getElementById("pedidos_loading");
                            var empty = document.getElementById("pedidos_empty");
                            var acc = document.getElementById("accordionPedidos");
                            if (loading) loading.classList.remove("d-none");
                            if (empty) empty.classList.add("d-none");
                            if (acc) acc.innerHTML = "";

                            var url = "/admin/estoque/compras/pedidos?produto_id=" + encodeURIComponent(produtoId)
                                + "&loja_id=" + encodeURIComponent(lojaId)
                                + "&sem_loja=" + encodeURIComponent(semLoja);

                            fetch(url, { headers: { "Accept": "application/json" } })
                                .then(function(r){ return r.json(); })
                                .then(function(data){
                                    if (loading) loading.classList.add("d-none");
                                    if (!data || !data.success) {
                                        if (empty) {
                                            empty.classList.remove("d-none");
                                            empty.textContent = (data && data.message) ? data.message : "Erro ao buscar pedidos.";
                                        }
                                        return;
                                    }
                                    var pedidos = data.pedidos || [];
                                    if (!pedidos.length) {
                                        if (empty) empty.classList.remove("d-none");
                                        return;
                                    }
                                    renderPedidosAccordion(pedidos);
                                })
                                .catch(function(){
                                    if (loading) loading.classList.add("d-none");
                                    if (empty) {
                                        empty.classList.remove("d-none");
                                        empty.textContent = "Erro ao buscar pedidos.";
                                    }
                                });
                        });
                    }
                </script>';

                // Modal: Reabrir compras (tudo do filtro)
                echo '<div class="modal fade" id="modalReabrirCompras" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/reabrir">
                                    <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                                    <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reabrir itens</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-secondary mb-0">
                                            Deseja voltar para <strong>pendente</strong> todos os itens concluídos deste filtro?
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Reabrir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalLoja = document.getElementById("modalLoja");
                    if (modalLoja) {
                        modalLoja.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            var produtoId = button.getAttribute("data-produto-id");
                            var produtoNome = button.getAttribute("data-produto-nome");
                            var input = document.getElementById("modal_produto_id");
                            var label = document.getElementById("modal_produto_nome");
                            if (input) input.value = produtoId;
                            if (label) label.textContent = produtoNome;
                        });
                    }
                </script>';

                // Modal: Remover item
                echo '<div class="modal fade" id="modalRemoverItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/remover-item">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Remover item da lista</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="remover_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="remover_loja_id" value="0">
                                        <div class="alert alert-warning mb-0">
                                            Tem certeza que deseja remover da lista o item <strong id="remover_produto_nome"></strong>?
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger">Remover</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalRemoverItem = document.getElementById("modalRemoverItem");
                    if (modalRemoverItem) {
                        modalRemoverItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("remover_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("remover_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("remover_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                        });
                    }
                </script>';

                // Modal: Reabrir item
                echo '<div class="modal fade" id="modalReabrirItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/reabrir">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reabrir item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="reabrir_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="reabrir_loja_id" value="0">
                                        <div class="alert alert-secondary mb-0">
                                            Voltar para <strong>pendente</strong>: <strong id="reabrir_produto_nome"></strong>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Reabrir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalReabrirItem = document.getElementById("modalReabrirItem");
                    if (modalReabrirItem) {
                        modalReabrirItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("reabrir_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("reabrir_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("reabrir_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                        });
                    }
                </script>';

                // Modal: Concluir item
                echo '<div class="modal fade" id="modalConcluirItem" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/concluir">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Concluir item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="produto_id" id="concluir_produto_id" value="">
                                        <input type="hidden" name="loja_id" id="concluir_loja_id" value="0">
                                        <input type="hidden" name="redirect_loja_id" id="concluir_redirect_loja_id" value="0">
                                        <input type="hidden" name="redirect_sem_loja" id="concluir_redirect_sem_loja" value="0">
                                        <div class="alert alert-success mb-0">
                                            Concluir compra de: <strong id="concluir_produto_nome"></strong>
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label">Quantidade comprada (apenas para compra parcial)</label>
                                            <input type="number" class="form-control" name="quantidade_comprada" id="concluir_quantidade_comprada" min="0" max="0" value="0">
                                            <div class="form-text">Se comprar parcial, informe quantos itens foram comprados. A diferença continuará pendente.</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success" name="modo" value="total">Confirmar compra total</button>
                                        <button type="submit" class="btn btn-outline-success" name="modo" value="parcial">Confirmar parcial</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    var modalConcluirItem = document.getElementById("modalConcluirItem");
                    if (modalConcluirItem) {
                        modalConcluirItem.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            document.getElementById("concluir_produto_id").value = button.getAttribute("data-produto-id") || "";
                            document.getElementById("concluir_loja_id").value = button.getAttribute("data-loja-id") || "0";
                            document.getElementById("concluir_produto_nome").textContent = button.getAttribute("data-produto-nome") || "";
                            var maxQ = parseInt(button.getAttribute("data-quantidade") || "0", 10);
                            var inp = document.getElementById("concluir_quantidade_comprada");
                            // Preservar o filtro atual da URL para o redirect após confirmar
                            var urlParams = new URLSearchParams(window.location.search);
                            var currentLojaId = urlParams.get("loja_id") || "0";
                            var currentSemLoja = urlParams.get("sem_loja") || "0";
                            document.getElementById("concluir_redirect_loja_id").value = currentLojaId;
                            document.getElementById("concluir_redirect_sem_loja").value = currentSemLoja;
                            if (inp) {
                                inp.max = String(Math.max(0, maxQ));
                                inp.value = "0";
                            }
                        });
                        var form = modalConcluirItem.querySelector("form");
                        if (form) {
                            form.addEventListener("submit", function (e) {
                                var inp = document.getElementById("concluir_quantidade_comprada");
                                if (!inp) return;
                                var max = parseInt(inp.max || "0", 10);
                                var val = parseInt(inp.value || "0", 10);
                                if (val > max) {
                                    e.preventDefault();
                                    alert("O numero de produtos ultrapassa a quantidade de compra, caso tenha comprado itens sobressalentes por favor dê entrada no estoque ");
                                }
                            });
                        }
                    }
                </script>';

                // Modal: Concluir compras (tudo do filtro)
                echo '<div class="modal fade" id="modalConcluirCompras" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="/admin/estoque/compras/concluir">
                                    <input type="hidden" name="loja_id" value="' . (int) $lojaIdFilter . '">
                                    <input type="hidden" name="sem_loja" value="' . ($semLoja ? '1' : '0') . '">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Concluir compras</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-success">
                                            Você pode concluir <strong>total</strong> ou <strong>parcial</strong> os itens pendentes deste filtro.
                                        </div>
                                        <div>
                                            <label class="form-label">Quantidade comprada (apenas para compra parcial)</label>
                                            <input type="number" class="form-control" name="quantidade_comprada" min="0" value="0">
                                            <div class="form-text">Em compra parcial, o restante continuará pendente.</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success" name="modo" value="total">Confirmar compra total</button>
                                        <button type="submit" class="btn btn-outline-success" name="modo" value="parcial">Confirmar parcial</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';

                echo '</main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function salvar($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $produtoId = (int) $request->getParam('produto_id', 0);
        $pedidoId = (int) $request->getParam('pedido_id', 0);
        $usuarioId = (int) $request->getParam('usuario_id', 0);
        $quantidade = (int) $request->getParam('quantidade', 0);
        $valorPendente = (float) $request->getParam('valor_pendente', 0);
        $prioridade = (string) $request->getParam('prioridade', 'media');
        $prioridade = in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true) ? $prioridade : 'media';

        if ($produtoId <= 0 || $pedidoId <= 0 || $quantidade <= 0) {
            $_SESSION['message'] = 'Parâmetros inválidos.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }

        try {
            $this->connection->beginTransaction();

            $colsPedidos = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $stmtPedido = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
            $stmtPedido->execute([':id' => $pedidoId]);
            $pedido = $stmtPedido->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                $this->connection->rollBack();
                $_SESSION['message'] = 'Pedido não encontrado.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/estoque/compras');
                exit;
            }

            $pedidoPago = $this->pedidoEstaPago($pedido);

            if ($usuarioId > 0 && $this->columnExists('pedidos', 'usuario_id')) {
                try {
                    $stmtChk = $this->connection->prepare('SELECT usuario_id FROM pedidos WHERE id = :id LIMIT 1');
                    $stmtChk->execute([':id' => $pedidoId]);
                    $uPed = (int) ($stmtChk->fetchColumn() ?: 0);
                    if ($uPed > 0 && $uPed !== $usuarioId) {
                        $this->connection->rollBack();
                        $_SESSION['message'] = 'Pedido não pertence ao usuário selecionado.';
                        $_SESSION['message_type'] = 'danger';
                        header('Location: /admin/estoque/compras/novo');
                        exit;
                    }
                } catch (\Exception $e) {
                }
            }

            // Inserir também no pedido (item real) e recalcular diferença automaticamente
            $produtoInfo = $this->getProdutoInfo($produtoId);
            if (!$produtoInfo) {
                $this->connection->rollBack();
                $_SESSION['message'] = 'Produto não encontrado.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/estoque/compras/novo');
                exit;
            }

            $precoUnit = (float) ($produtoInfo['preco'] ?? 0);
            $subtotalNovoItem = $precoUnit * $quantidade;

            $itensTable = $this->findPedidoItensTable();
            if ($itensTable) {
                $this->inserirItemNoPedido($itensTable, $pedidoId, $produtoId, $quantidade, $precoUnit, $subtotalNovoItem, $produtoInfo);
            }

            $moedaPedido = $this->getPedidoMoeda($pedido);
            $oldTotal = $this->getPedidoTotalAtual($pedido);
            $novos = $this->recalcularTotaisPedido($pedidoId, $moedaPedido);
            $newTotal = (float) ($novos['total'] ?? 0);
            $valorPendente = $newTotal - $oldTotal;
            if ($valorPendente < 0) {
                $valorPendente = 0.0;
            }

            // Atualizar totais no pedido sempre (regras padrão)
            $set = [];
            $params = [':id' => $pedidoId];

            if (in_array('subtotal_produtos', $colsPedidos, true)) {
                $set[] = 'subtotal_produtos = :sub';
                $params[':sub'] = (float) ($novos['subtotal'] ?? 0);
            } elseif (in_array('subtotal', $colsPedidos, true)) {
                $set[] = 'subtotal = :sub';
                $params[':sub'] = (float) ($novos['subtotal'] ?? 0);
            }

            if (in_array('taxa_servico', $colsPedidos, true)) {
                $set[] = 'taxa_servico = :ts';
                $params[':ts'] = (float) ($novos['taxa_servico'] ?? 0);
            } elseif (in_array('servicos', $colsPedidos, true)) {
                $set[] = 'servicos = :ts';
                $params[':ts'] = (float) ($novos['taxa_servico'] ?? 0);
            }

            if (in_array('valor_impostos', $colsPedidos, true)) {
                $set[] = 'valor_impostos = :imp';
                $params[':imp'] = (float) ($novos['impostos'] ?? 0);
            } elseif (in_array('impostos', $colsPedidos, true)) {
                $set[] = 'impostos = :imp';
                $params[':imp'] = (float) ($novos['impostos'] ?? 0);
            }

            if (in_array('valor_frete', $colsPedidos, true)) {
                $set[] = 'valor_frete = :frete';
                $params[':frete'] = (float) ($novos['frete'] ?? 0);
            } elseif (in_array('frete', $colsPedidos, true)) {
                $set[] = 'frete = :frete';
                $params[':frete'] = (float) ($novos['frete'] ?? 0);
            }

            if (in_array('peso_total', $colsPedidos, true)) {
                $set[] = 'peso_total = :peso';
                $params[':peso'] = (float) ($novos['peso'] ?? 0);
            }

            if (in_array('imposto_local', $colsPedidos, true)) {
                $set[] = 'imposto_local = :imp_local';
                $params[':imp_local'] = (float) ($novos['imposto_local'] ?? 0);
            }

            if (in_array('valor_total', $colsPedidos, true)) {
                $set[] = 'valor_total = :total';
                $params[':total'] = $newTotal;
            } elseif (in_array('total', $colsPedidos, true)) {
                $set[] = 'total = :total';
                $params[':total'] = $newTotal;
            }

            if (in_array('valor_total_brl', $colsPedidos, true)) {
                $taxaConv = 1.0;
                if (in_array('taxa_conversao_utilizada', $colsPedidos, true) && isset($pedido['taxa_conversao_utilizada'])) {
                    $taxaConv = (float) $pedido['taxa_conversao_utilizada'];
                } elseif (in_array('taxa_conversao', $colsPedidos, true) && isset($pedido['taxa_conversao'])) {
                    $taxaConv = (float) $pedido['taxa_conversao'];
                }
                $totalBrl = ($moedaPedido === 'BRL') ? $newTotal : ($newTotal * $taxaConv);
                $set[] = 'valor_total_brl = :total_brl';
                $params[':total_brl'] = $totalBrl;
            }

            if (!empty($set)) {
                $stmtUpd = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id LIMIT 1');
                $stmtUpd->execute($params);
            }

            // Inserir item na lista de compras
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $cols = ['produto_id', 'quantidade_necessaria', 'quantidade_faltante', 'prioridade', 'status', 'data_solicitacao'];
            $vals = [':produto_id', ':q', ':q', ':prioridade', "'pendente'", 'CURDATE()'];
            $insertParams = [':produto_id' => $produtoId, ':q' => $quantidade, ':prioridade' => $prioridade];

            if ($temPedidoEmLista) {
                $cols[] = 'pedido_id';
                $vals[] = ':pedido_id';
                $insertParams[':pedido_id'] = $pedidoId;
            }

            if ($temLojaIdEmLista) {
                // tentar inferir loja pelo produto
                $lojaId = 0;
                try {
                    $temLojaIdProduto = $this->columnExists('produtos', 'loja_id');
                    $temLojaSlugProduto = $this->columnExists('produtos', 'loja');

                    if ($temLojaIdProduto && $temLojaSlugProduto) {
                        $stmtL = $this->connection->prepare('SELECT loja_id, loja FROM produtos WHERE id = :id LIMIT 1');
                        $stmtL->execute([':id' => $produtoId]);
                        $rowL = $stmtL->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $lojaId = (int) ($rowL['loja_id'] ?? 0);

                        if ($lojaId <= 0 && $this->tableExists('lojas')) {
                            $slug = trim((string) ($rowL['loja'] ?? ''));
                            if ($slug !== '') {
                                $stmtFind = $this->connection->prepare('SELECT id FROM lojas WHERE slug = :s OR nome = :s LIMIT 1');
                                $stmtFind->execute([':s' => $slug]);
                                $found = (int) ($stmtFind->fetchColumn() ?: 0);
                                if ($found > 0) {
                                    $lojaId = $found;
                                }
                            }
                        }
                    } elseif ($temLojaIdProduto) {
                        $stmtL = $this->connection->prepare('SELECT loja_id FROM produtos WHERE id = :id LIMIT 1');
                        $stmtL->execute([':id' => $produtoId]);
                        $lojaId = (int) $stmtL->fetchColumn();
                    } elseif ($temLojaSlugProduto && $this->tableExists('lojas')) {
                        $stmtL = $this->connection->prepare('SELECT loja FROM produtos WHERE id = :id LIMIT 1');
                        $stmtL->execute([':id' => $produtoId]);
                        $slug = trim((string) ($stmtL->fetchColumn() ?: ''));
                        if ($slug !== '') {
                            $stmtFind = $this->connection->prepare('SELECT id FROM lojas WHERE slug = :s OR nome = :s LIMIT 1');
                            $stmtFind->execute([':s' => $slug]);
                            $found = (int) ($stmtFind->fetchColumn() ?: 0);
                            if ($found > 0) {
                                $lojaId = $found;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $lojaId = 0;
                }

                $cols[] = 'loja_id';
                if ($lojaId > 0) {
                    $vals[] = ':loja_id';
                    $insertParams[':loja_id'] = $lojaId;
                } else {
                    $vals[] = 'NULL';
                }
            }

            $stmtIns = $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmtIns->execute($insertParams);

            $this->connection->commit();

            if ($pedidoPago) {
                $_SESSION['message'] = 'Item inserido no pedido e na lista de compras. Pedido está pago: diferença calculada em $ ' . number_format($valorPendente, 2, '.', ',') . '.';
                $_SESSION['message_type'] = 'warning';
            } else {
                $_SESSION['message'] = 'Item inserido no pedido e na lista. Diferença calculada automaticamente: $ ' . number_format($valorPendente, 2, '.', ',') . '.';
                $_SESSION['message_type'] = 'success';
            }
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $_SESSION['message'] = 'Erro ao salvar novo item: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    public function editarItem($request) {
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $quantidade = (int) $request->getParam('quantidade', 0);
        $prioridade = (string) $request->getParam('prioridade', 'media');
        $prioridade = in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true) ? $prioridade : 'media';

        if ($produtoId <= 0) {
            $_SESSION['message'] = 'Parâmetros inválidos.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }

        try {
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $this->connection->beginTransaction();
            $whereLoja = '';
            $params = [':produto_id' => $produtoId];
            if ($temLojaIdEmLista) {
                if ($lojaId > 0) {
                    $whereLoja = ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                } else {
                    $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                }
            }

            $stmt = $this->connection->prepare("UPDATE lista_compras lc SET lc.status = 'cancelado' WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id" . $whereLoja);
            $stmt->execute($params);

            $cols = ['produto_id', 'quantidade_necessaria', 'quantidade_faltante', 'prioridade', 'status', 'data_solicitacao'];
            $vals = [':produto_id', ':q', ':q', ':prioridade', "'pendente'", 'CURDATE()'];
            $insertParams = [':produto_id' => $produtoId, ':q' => $quantidade, ':prioridade' => $prioridade];
            if ($temLojaIdEmLista) {
                $cols[] = 'loja_id';
                if ($lojaId > 0) {
                    $vals[] = ':ins_loja_id';
                    $insertParams[':ins_loja_id'] = $lojaId;
                } else {
                    $vals[] = 'NULL';
                }
            }

            $stmt = $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmt->execute($insertParams);

            $this->connection->commit();
            $_SESSION['message'] = 'Item atualizado.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $_SESSION['message'] = 'Erro ao atualizar item.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    public function mudarStatus($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }

    public function concluirCompras($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $produtoId = (int) $request->getParam('produto_id', 0);
        $lojaId = (int) $request->getParam('loja_id', 0);
        $semLoja = (string) $request->getParam('sem_loja', '0') === '1';
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

        // Usar o filtro que o usuário estava visualizando (passado pelo JS via redirect_loja_id/redirect_sem_loja)
        // Se não vier, cai no loja_id do produto como fallback
        $redirectLojaIdRaw = $request->getParam('redirect_loja_id', null);
        $redirectSemLojaRaw = $request->getParam('redirect_sem_loja', null);
        $hasRedirectFilter = ($redirectLojaIdRaw !== null || $redirectSemLojaRaw !== null);

        $redirectParams = ['status' => 'pendente'];
        if ($hasRedirectFilter) {
            $redirectSemLoja = (string) ($redirectSemLojaRaw ?? '0') === '1';
            $redirectLojaId = (int) ($redirectLojaIdRaw ?? 0);
            if ($redirectSemLoja) {
                $redirectParams['sem_loja'] = '1';
            } elseif ($redirectLojaId > 0) {
                $redirectParams['loja_id'] = (string) $redirectLojaId;
            }
            // Se redirect_loja_id = 0 e redirect_sem_loja = 0 → filtro "Todas as lojas", não adiciona nada
        } else {
            // Fallback: comportamento anterior (usa loja do produto)
            if ($semLoja) {
                $redirectParams['sem_loja'] = '1';
            } elseif ($lojaId > 0) {
                $redirectParams['loja_id'] = (string) $lojaId;
            }
        }
        $redirectUrl = '/admin/estoque/compras' . (!empty($redirectParams) ? ('?' . http_build_query($redirectParams)) : '');

        $modo = (string) $request->getParam('modo', 'total');
        $modo = in_array($modo, ['total', 'parcial'], true) ? $modo : 'total';
        $quantidadeComprada = (int) $request->getParam('quantidade_comprada', 0);
        if ($quantidadeComprada < 0) $quantidadeComprada = 0;

        try {
            // Parcial só faz sentido para concluir UM produto (senão não tem como distribuir quantidade)
            if ($modo === 'parcial' && $produtoId > 0) {
                if ($quantidadeComprada <= 0) {
                    $_SESSION['message'] = 'Informe a quantidade comprada para concluir parcialmente.';
                    $_SESSION['message_type'] = 'warning';
                    header('Location: ' . $redirectUrl);
                    exit;
                }

                $whereLoja = '';
                $params = [':produto_id' => $produtoId];
                if ($temLojaIdEmLista) {
                    if ($semLoja) {
                        $whereLoja = ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                    } elseif ($lojaId > 0) {
                        $whereLoja = ' AND lc.loja_id = :loja_id';
                        $params[':loja_id'] = $lojaId;
                    }
                }

                // Aplicar compra parcial consumindo quantidades das pendências mais antigas
                $stmtSel = $this->connection->prepare(
                    "SELECT id, quantidade_faltante, quantidade_necessaria
                     FROM lista_compras lc
                     WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id" . $whereLoja .
                    " ORDER BY lc.id ASC"
                );
                $stmtSel->execute($params);
                $rows = $stmtSel->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // fallback: se filtro de loja não retornou nada, tentar sem loja
                if (empty($rows) && $temLojaIdEmLista && !$semLoja && $lojaId > 0) {
                    $stmtSel2 = $this->connection->prepare(
                        "SELECT id, quantidade_faltante, quantidade_necessaria\n"
                        . " FROM lista_compras lc\n"
                        . " WHERE lc.status = 'pendente' AND lc.produto_id = :produto_id\n"
                        . " ORDER BY lc.id ASC"
                    );
                    $stmtSel2->execute([':produto_id' => $produtoId]);
                    $rows = $stmtSel2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }

                $totalNeed = 0;
                foreach ($rows as $r) {
                    $qf = (int) ($r['quantidade_faltante'] ?? 0);
                    $qn = (int) ($r['quantidade_necessaria'] ?? 0);
                    $need = $qf > 0 ? $qf : $qn;
                    if ($need > 0) $totalNeed += $need;
                }
                if ($quantidadeComprada > $totalNeed) {
                    $_SESSION['message'] = 'O numero de produtos ultrapassa a quantidade de compra, caso tenha comprado itens sobressalentes por favor dê entrada no estoque';
                    $_SESSION['message_type'] = 'warning';
                    header('Location: ' . $redirectUrl);
                    exit;
                }

                $restante = $quantidadeComprada;
                foreach ($rows as $r) {
                    if ($restante <= 0) break;
                    $id = (int) ($r['id'] ?? 0);
                    if ($id <= 0) continue;

                    $qf = (int) ($r['quantidade_faltante'] ?? 0);
                    $qn = (int) ($r['quantidade_necessaria'] ?? 0);
                    $need = $qf > 0 ? $qf : $qn;
                    if ($need <= 0) continue;

                    if ($restante >= $need) {
                        $stmtUp = $this->connection->prepare("UPDATE lista_compras SET status = 'comprado', quantidade_faltante = 0 WHERE id = :id");
                        $stmtUp->execute([':id' => $id]);
                        $restante -= $need;
                    } else {
                        $novoFaltante = $need - $restante;
                        $stmtUp = $this->connection->prepare("UPDATE lista_compras SET status = 'pendente', quantidade_faltante = :qf WHERE id = :id");
                        $stmtUp->execute([':id' => $id, ':qf' => $novoFaltante]);
                        $restante = 0;
                    }
                }

                $_SESSION['message'] = 'Compra parcial registrada. O restante continua pendente.';
                $_SESSION['message_type'] = 'success';
                header('Location: ' . $redirectUrl);
                exit;
            }

            // Total (comportamento padrão): marcar como comprado
            $sql = "UPDATE lista_compras lc SET lc.status = 'comprado', lc.quantidade_faltante = 0 WHERE lc.status = 'pendente'";
            $params = [];
            if ($produtoId > 0) {
                $sql .= ' AND lc.produto_id = :produto_id';
                $params[':produto_id'] = $produtoId;
            }
            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $affected = (int) $stmt->rowCount();

            // fallback: se nenhum registro foi marcado como comprado e o filtro por loja pode estar divergente,
            // tentar novamente sem restringir por loja.
            if ($affected === 0 && $temLojaIdEmLista && !$semLoja && $lojaId > 0) {
                $sql2 = "UPDATE lista_compras lc SET lc.status = 'comprado', lc.quantidade_faltante = 0 WHERE lc.status = 'pendente'";
                $params2 = [];
                if ($produtoId > 0) {
                    $sql2 .= ' AND lc.produto_id = :produto_id';
                    $params2[':produto_id'] = $produtoId;
                }
                $stmt2 = $this->connection->prepare($sql2);
                $stmt2->execute($params2);
                $affected = (int) $stmt2->rowCount();
            }

            if ($affected > 0) {
                $_SESSION['message'] = 'Compras concluídas.';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Nenhum item pendente encontrado para concluir.';
                $_SESSION['message_type'] = 'warning';
            }
            header('Location: ' . $redirectUrl);
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao concluir compras.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras');
            exit;
        }
    }

    public function gerarPDF($request) {
        $lojaIdFiltro = (int) $request->getParam('loja_id', 0);
        $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
        $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
        $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
        $temFoto = $this->columnExists('produtos', 'foto_principal');
        $temImages = $this->columnExists('produtos', 'images');
        $temObsVendedor = $this->columnExists('pedidos', 'observacao_vendedor');

        $selectCols = ['p.id as produto_id', 'p.sku as sku'];
        if ($this->columnExists('produtos', 'nome')) { $selectCols[] = 'p.nome as produto_nome'; }
        elseif ($this->columnExists('produtos', 'name')) { $selectCols[] = 'p.name as produto_nome'; }
        else { $selectCols[] = "'' as produto_nome"; }
        if ($temFoto) $selectCols[] = 'p.foto_principal as foto_principal';
        if ($temImages) $selectCols[] = 'p.images as images';

        $rankExpr = "CASE lc.prioridade WHEN 'urgente' THEN 4 WHEN 'alta' THEN 3 WHEN 'media' THEN 2 WHEN 'baixa' THEN 1 ELSE 0 END";

        $lojaIdExpr = '0 as loja_id';
        if ($temLojaIdEmLista && $temLojaIdEmProdutos) {
            $lojaIdExpr = 'COALESCE(NULLIF(lc.loja_id,0), p_inner.loja_id, 0) as loja_id';
        } elseif ($temLojaIdEmLista) {
            $lojaIdExpr = 'COALESCE(lc.loja_id,0) as loja_id';
        }

        $sql = 'SELECT ' . implode(', ', $selectCols)
            . ', agg.quantidade_faltante, agg.quantidade_necessaria, agg.loja_id, agg.prioridade, agg.nome_produto_custom'
            . ($temPedidoEmLista ? ', agg.pedido_id' : '')
            . ' FROM ('
            . '   SELECT lc.produto_id, ' . $lojaIdExpr
            . ($temPedidoEmLista ? ', MIN(NULLIF(COALESCE(lc.pedido_id,0),0)) as pedido_id' : '')
            . ($this->columnExists('lista_compras', 'nome_produto') ? ", COALESCE(lc.nome_produto, '') as nome_produto_custom" : ", '' as nome_produto_custom")
            . ', SUM(COALESCE(lc.quantidade_faltante,0)) as quantidade_faltante'
            . ', SUM(COALESCE(lc.quantidade_necessaria,0)) as quantidade_necessaria'
            . ", CASE MAX({$rankExpr}) WHEN 4 THEN 'urgente' WHEN 3 THEN 'alta' WHEN 2 THEN 'media' WHEN 1 THEN 'baixa' ELSE 'media' END as prioridade"
            . '   FROM lista_compras lc'
            . ($temLojaIdEmProdutos ? ' LEFT JOIN produtos p_inner ON p_inner.id = lc.produto_id' : '')
            . ($temPedidoEmLista ? ' LEFT JOIN pedidos ped ON ped.id = lc.pedido_id' : '')
            . "   WHERE lc.status = 'pendente'"
            . ($temPedidoEmLista ? " AND (lc.pedido_id IS NULL OR lc.pedido_id = 0 OR ped.status IN ('pago','processando','enviado','entregue','consolidado','produto_consolidado','rascunho_etiqueta','etiqueta_efetivada','aguardando_lib_alfandegaria','finalizacao_embalagem','entrega_finalizada'))" : '')
            . '   GROUP BY lc.produto_id, '
            . ($this->columnExists('lista_compras', 'nome_produto') ? "COALESCE(lc.nome_produto, ''), " : '')
            . ($temLojaIdEmLista && $temLojaIdEmProdutos ? 'COALESCE(NULLIF(lc.loja_id,0), p_inner.loja_id, 0)' : ($temLojaIdEmLista ? 'COALESCE(lc.loja_id,0)' : '0'))
            . ' ) agg'
            . ' JOIN produtos p ON agg.produto_id = p.id';

        $params = [];
        if ($lojaIdFiltro > 0) {
            $sql .= ' WHERE agg.loja_id = :loja_id';
            $params[':loja_id'] = $lojaIdFiltro;
        }
        $sql .= ' ORDER BY agg.loja_id ASC, agg.prioridade DESC';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Agrupar por loja
        $lojaNames = [];
        if ($this->tableExists('lojas')) {
            try { $stL = $this->connection->query('SELECT id, nome FROM lojas'); foreach ($stL->fetchAll(\PDO::FETCH_ASSOC) as $l) { $lojaNames[(int)$l['id']] = $l['nome']; } } catch (\Exception $e) {}
        }

        $porLoja = [];
        foreach ($rows as $r) {
            $lid = (int) ($r['loja_id'] ?? 0);
            $porLoja[$lid][] = $r;
        }

        // Obs por pedido
        $obsByPedido = [];
        if ($temPedidoEmLista && $temObsVendedor) {
            $pids = [];
            foreach ($rows as $r) { $pid = (int)($r['pedido_id']??0); if ($pid>0) $pids[$pid]=true; }
            if (!empty($pids)) {
                $in = implode(',', array_keys($pids));
                try { $st = $this->connection->query("SELECT id, observacao_vendedor FROM pedidos WHERE id IN ({$in})"); foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $o) { $obsByPedido[(int)$o['id']] = $o['observacao_vendedor']??''; } } catch (\Exception $e) {}
            }
        }

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Lista de Compras</title>
        <style>
            body{font-family:Arial,sans-serif;color:#111;margin:18px;font-size:12px;}
            h1{font-size:18px;margin:0 0 4px;}
            h2{font-size:14px;margin:20px 0 6px;padding:6px 10px;background:#e8e8e8;border-radius:4px;}
            .meta{font-size:11px;color:#666;margin-bottom:14px;}
            table{width:100%;border-collapse:collapse;margin-bottom:10px;}
            th,td{border:1px solid #ccc;padding:6px 8px;vertical-align:middle;}
            th{background:#f0f0f0;text-align:left;font-size:11px;}
            .img{width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #ddd;}
            .check{width:16px;height:16px;border:1.5px solid #333;display:inline-block;}
            .qtd-comprada{width:60px;height:22px;border:1px solid #999;border-radius:3px;}
            @media print{.no-print{display:none;} h2{break-before:auto;}}
        </style></head><body>';

        echo '<h1>Lista de Compras</h1><div class="meta">Gerado em: ' . date('d/m/Y H:i') . '</div>';

        foreach ($porLoja as $lid => $items) {
            $nomeLoja = ($lid > 0 && isset($lojaNames[$lid])) ? $lojaNames[$lid] : 'Sem Loja Definida';
            echo '<h2>' . htmlspecialchars($nomeLoja) . ' (' . count($items) . ' itens)</h2>';
            echo '<table><thead><tr>'
                . '<th style="width:30px;">Ok</th>'
                . '<th style="width:50px;">Foto</th>'
                . '<th>Produto</th>'
                . '<th style="width:55px;">Qtd</th>'
                . '<th style="width:70px;">Qtd Comprada</th>'
                . ($temObsVendedor ? '<th>Obs. Pedido</th>' : '')
                . '</tr></thead><tbody>';

            foreach ($items as $r) {
                $qf = (int)($r['quantidade_faltante'] ?? $r['quantidade_necessaria'] ?? 0);
                $img = $this->resolveProdutoImagem($r);
                $imgTag = $img ? '<img class="img" src="'.htmlspecialchars($img).'">' : '';
                $nome = trim((string)($r['nome_produto_custom'] ?? ''));
                if ($nome === '') $nome = (string)($r['produto_nome'] ?? '');
                $pidLinha = (int)($r['pedido_id'] ?? 0);
                $obs = ($pidLinha > 0 && isset($obsByPedido[$pidLinha])) ? trim($obsByPedido[$pidLinha]) : '';

                echo '<tr>'
                    . '<td style="text-align:center;"><span class="check"></span></td>'
                    . '<td style="text-align:center;">' . $imgTag . '</td>'
                    . '<td><strong>' . htmlspecialchars($nome) . '</strong></td>'
                    . '<td style="text-align:center;font-weight:bold;">' . $qf . '</td>'
                    . '<td style="text-align:center;"><input type="text" class="qtd-comprada"></td>'
                    . ($temObsVendedor ? '<td style="font-size:11px;">' . htmlspecialchars($obs) . '</td>' : '')
                    . '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<div class="no-print" style="margin-top:14px;"><button onclick="window.print()">Imprimir / Salvar como PDF</button></div>';
        echo '</body></html>';
        exit;
    }

    public function definirLojaProduto($request) {
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $lojaId = (int) $request->getParam('loja_id');
            if ($produtoId <= 0 || $lojaId <= 0) {
                $_SESSION['message'] = 'Parâmetros inválidos.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/estoque/compras?sem_loja=1');
                exit;
            }

            $cols = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE produtos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $slug = null;
            if ($this->tableExists('lojas')) {
                $stmt = $this->connection->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $lojaId]);
                $tmp = $stmt->fetchColumn();
                if ($tmp !== false && (string) $tmp !== '') $slug = (string) $tmp;
            }

            $sqlParts = [];
            $params = [':id' => $produtoId];
            if (in_array('loja_id', $cols, true)) {
                $sqlParts[] = 'loja_id = :loja_id';
                $params[':loja_id'] = $lojaId;
            }
            if (in_array('loja', $cols, true) && $slug !== null) {
                $sqlParts[] = 'loja = :loja';
                $params[':loja'] = $slug;
            }
            if (!empty($sqlParts)) {
                $stmtUpd = $this->connection->prepare('UPDATE produtos SET ' . implode(', ', $sqlParts) . ' WHERE id = :id LIMIT 1');
                $stmtUpd->execute($params);
            }

            // Atualizar lista_compras pendentes deste produto sem loja
            $hasLojaIdLista = $this->columnExists('lista_compras', 'loja_id');
            if ($hasLojaIdLista) {
                $stmtLC = $this->connection->prepare("UPDATE lista_compras SET loja_id = :loja_id WHERE produto_id = :produto_id AND status = 'pendente' AND (loja_id IS NULL OR loja_id = 0)");
                $stmtLC->execute([':loja_id' => $lojaId, ':produto_id' => $produtoId]);
            }

            $_SESSION['message'] = 'Loja configurada e aplicada às compras pendentes.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/estoque/compras');
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao definir loja.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/estoque/compras?sem_loja=1');
            exit;
        }
    }

    public function verificarEstoque($request) {
        $produto_id = $request->getParam('produto_id');
        echo json_encode(['success' => true, 'message' => 'Verificação de estoque para produto ID: ' . $produto_id]);
    }
}
