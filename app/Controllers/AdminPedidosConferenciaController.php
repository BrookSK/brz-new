<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminPedidosConferenciaController extends Controller {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = \Config\Database::getConnection();
    }

    private function renderPageStart(string $activeMenuKey = 'pedidos-conferencia'): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos para Conferência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        renderAdminSidebar($activeMenuKey);

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-clipboard-check me-2"></i>Pedidos para conferência</h1>
            </div>';

        $this->renderFlashIfAny();
    }

    private function renderPageEnd(): void {
        renderAdminScripts();
        echo '</main></div></div></body></html>';
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
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
            . '</div>';
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

    private function getPedidoItensTable(): ?string {
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

    public function index($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $colsPedidos = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        if (!in_array('status_conferencia', $colsPedidos, true)) {
            $_SESSION['message'] = 'Sua base ainda não possui o campo status_conferencia. Rode a migration 085_add_tipo_compra_e_conferencia.sql.';
            $_SESSION['message_type'] = 'warning';
        }

        $temOrigem = in_array('origem_pedido', $colsPedidos, true);
        $temCodigo = in_array('codigo_pedido', $colsPedidos, true) ? 'codigo_pedido' : (in_array('numero_pedido', $colsPedidos, true) ? 'numero_pedido' : '');
        $temMoeda = in_array('moeda', $colsPedidos, true);
        $temTotal = in_array('total', $colsPedidos, true) ? 'total' : (in_array('valor_total', $colsPedidos, true) ? 'valor_total' : '');
        $temCreated = in_array('created_at', $colsPedidos, true) ? 'created_at' : '';
        $temTipoCompra = in_array('tipo_compra', $colsPedidos, true);

        $select = ['p.id'];
        if ($temCodigo !== '') $select[] = 'p.' . $temCodigo . ' AS codigo_pedido';
        if ($temOrigem) $select[] = 'p.origem_pedido';
        if ($temMoeda) $select[] = 'p.moeda';
        if ($temTotal !== '') $select[] = 'p.' . $temTotal . ' AS total';
        if ($temCreated !== '') $select[] = 'p.' . $temCreated . ' AS created_at';
        if ($temTipoCompra) $select[] = 'p.tipo_compra';
        $select[] = 'p.status';
        $select[] = 'p.status_conferencia';

        $where = ["p.status_conferencia = 'pendente'"];
        if ($temOrigem) {
            $where[] = "p.origem_pedido IN ('assessoria','redirecionamento')";
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p';
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.id DESC LIMIT 500';

        try {
            $stmt = $this->connection->query($sql);
            $pedidos = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            $pedidos = [];
        }

        $itensPorPedido = [];
        try {
            $ids = array_values(array_filter(array_map(function ($r) {
                return (int) ($r['id'] ?? 0);
            }, $pedidos)));
            if (!empty($ids)) {
                $itensTable = $this->getPedidoItensTable();
                if ($itensTable) {
                    $colsItens = [];
                    try {
                        $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
                        $colsItens = $stmtColsI ? ($stmtColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsItens = [];
                    }

                    $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
                    $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
                    $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';

                    if ($colProduto !== '' && $colQtd !== '' && $colPedido !== '') {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $sqlItens = 'SELECT i.' . $colPedido . ' AS pedido_id, i.' . $colProduto . ' AS produto_id, i.' . $colQtd . ' AS quantidade'
                            . ', COALESCE(p.nome, p.name, \'\') AS produto_nome'
                            . ' FROM ' . $itensTable . ' i'
                            . ' LEFT JOIN produtos p ON p.id = i.' . $colProduto
                            . ' WHERE i.' . $colPedido . ' IN (' . $placeholders . ')';
                        $stItensAll = $this->connection->prepare($sqlItens);
                        $stItensAll->execute($ids);
                        $rows = $stItensAll->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        foreach ($rows as $r) {
                            $pid = (int) ($r['pedido_id'] ?? 0);
                            if ($pid <= 0) {
                                continue;
                            }
                            if (!isset($itensPorPedido[$pid])) {
                                $itensPorPedido[$pid] = [];
                            }
                            $itensPorPedido[$pid][] = $r;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $itensPorPedido = [];
        }

        $this->renderPageStart('pedidos-conferencia');

        echo '<div class="card">
            <div class="card-body">';

        if (empty($pedidos)) {
            echo '<div class="text-muted">Nenhum pedido pendente de conferência.</div>';
        } else {
            echo '<div class="table-responsive">'
                . '<table class="table table-sm align-middle">'
                . '<thead><tr>'
                . '<th>ID</th>'
                . '<th>Origem</th>'
                . '<th>Moeda</th>'
                . '<th>Total</th>'
                . '<th>Criado em</th>'
                . '<th>Tipo compra</th>'
                . '<th style="width: 320px;">Ações</th>'
                . '</tr></thead><tbody>';

            foreach ($pedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $origem = (string) ($p['origem_pedido'] ?? '');
                $moeda = (string) ($p['moeda'] ?? '');
                $total = isset($p['total']) ? (float) $p['total'] : 0.0;
                $createdAt = (string) ($p['created_at'] ?? '');
                $tipoCompraAtual = strtolower(trim((string) ($p['tipo_compra'] ?? '')));

                $detId = 'det-pedido-' . $pid;

                echo '<tr>'
                    . '<td><a href="/admin/pedidos/detalhes/' . $pid . '" target="_blank">#' . $pid . '</a></td>'
                    . '<td>' . htmlspecialchars($origem !== '' ? $origem : '-') . '</td>'
                    . '<td>' . htmlspecialchars($moeda !== '' ? $moeda : '-') . '</td>'
                    . '<td>' . htmlspecialchars(number_format($total, 2, ',', '.')) . '</td>'
                    . '<td>' . htmlspecialchars($createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-') . '</td>'
                    . '<td>'
                    . '  <form class="d-flex gap-2" method="POST" action="/admin/pedidos/conferencia/confirmar/' . $pid . '">'
                    . '    <select class="form-select form-select-sm" name="tipo_compra" required>'
                    . '      <option value=""' . ($tipoCompraAtual === '' ? ' selected' : '') . '>Selecione...</option>'
                    . '      <option value="online"' . ($tipoCompraAtual === 'online' ? ' selected' : '') . '>Online</option>'
                    . '      <option value="offline"' . ($tipoCompraAtual === 'offline' ? ' selected' : '') . '>Offline</option>'
                    . '    </select>'
                    . '</td>'
                    . '<td>'
                    . '    <button class="btn btn-outline-info btn-sm me-1" type="button" data-bs-toggle="collapse" data-bs-target="#' . $detId . '" aria-expanded="false" aria-controls="' . $detId . '"><i class="fas fa-eye me-1"></i>Detalhes</button>'
                    . '    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Confirmar</button>'
                    . '  </form>'
                    . '  <form class="d-inline" method="POST" action="/admin/pedidos/conferencia/cancelar/' . $pid . '" onsubmit="return confirm(\'Cancelar este pedido?\');">'
                    . '    <button type="submit" class="btn btn-outline-danger btn-sm mt-1"><i class="fas fa-times me-1"></i>Cancelar pedido</button>'
                    . '  </form>'
                    . '</td>'
                    . '</tr>';

                echo '<tr class="collapse" id="' . $detId . '">'
                    . '<td colspan="7" class="bg-light">'
                    . '<div class="small">'
                    . '<div class="fw-semibold mb-2">Detalhes do pedido</div>'
                    . '<div style="border: 1px solid rgba(148, 163, 184, 0.35); border-radius: 12px; overflow: hidden; background: #fff;">'
                    . '<iframe src="/admin/pedidos/detalhes/' . $pid . '?embed=1" style="width: 100%; height: 680px; border: 0;" loading="lazy"></iframe>'
                    . '</div>'
                    . '</div>'
                    . '</td>'
                    . '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div></div>';

        $this->renderPageEnd();
        exit;
    }

    public function confirmar($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $id = (int) $request->getParam('id', 0);
        $tipoCompra = strtolower(trim((string) $request->getParam('tipo_compra', '')));

        if ($id <= 0) {
            $_SESSION['message'] = 'Pedido inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        if (!in_array($tipoCompra, ['online', 'offline'], true)) {
            $_SESSION['message'] = 'Selecione o tipo de compra (online/offline).';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        try {
            $this->connection->beginTransaction();

            // atualizar pedido
            $colsPedidos = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $set = [];
            $params = [':id' => $id];

            if (in_array('tipo_compra', $colsPedidos, true)) {
                $set[] = 'tipo_compra = :tipo_compra';
                $params[':tipo_compra'] = $tipoCompra;
            }
            if (in_array('status_conferencia', $colsPedidos, true)) {
                $set[] = 'status_conferencia = :status_conferencia';
                $params[':status_conferencia'] = 'confirmado';
            }

            if (!empty($set)) {
                $st = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $st->execute($params);
            }

            // inserir itens do pedido na lista_compras (idempotente)
            $itensTable = $this->getPedidoItensTable();
            if ($itensTable) {
                $colsItens = [];
                try {
                    $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
                    $colsItens = $stmtColsI ? ($stmtColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsItens = [];
                }

                $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
                $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');

                if ($colProduto !== '' && $colQtd !== '') {
                    $stItens = $this->connection->prepare('SELECT ' . $colProduto . ' AS produto_id, ' . $colQtd . ' AS quantidade FROM ' . $itensTable . ' WHERE pedido_id = ?');
                    $stItens->execute([$id]);
                    $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    // cols lista_compras
                    $colsLista = [];
                    try {
                        $stmtColsL = $this->connection->query('DESCRIBE lista_compras');
                        $colsLista = $stmtColsL ? ($stmtColsL->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsLista = [];
                    }

                    $itensConsolidados = [];
                    foreach ($itens as $it) {
                        $produtoId = (int) ($it['produto_id'] ?? 0);
                        $qtd = (int) ($it['quantidade'] ?? 0);
                        if ($produtoId <= 0 || $qtd <= 0) {
                            continue;
                        }
                        if (!isset($itensConsolidados[$produtoId])) {
                            $itensConsolidados[$produtoId] = 0;
                        }
                        $itensConsolidados[$produtoId] += $qtd;
                    }

                    $temQtdFaltante = in_array('quantidade_faltante', $colsLista, true);
                    $temQtdNec = in_array('quantidade_necessaria', $colsLista, true);
                    $colQtdLista = $temQtdFaltante ? 'quantidade_faltante' : ($temQtdNec ? 'quantidade_necessaria' : '');

                    foreach ($itensConsolidados as $produtoId => $qtd) {
                        $produtoId = (int) $produtoId;
                        $qtd = (int) $qtd;
                        if ($produtoId <= 0 || $qtd <= 0) {
                            continue;
                        }

                        // se já existe item pendente desse pedido/produto/tipo_compra, somar quantidade ao invés de duplicar
                        try {
                            if (in_array('pedido_id', $colsLista, true) && in_array('produto_id', $colsLista, true) && $colQtdLista !== '') {
                                $sqlFind = 'SELECT id, ' . $colQtdLista . ' AS qtd FROM lista_compras WHERE pedido_id = ? AND produto_id = ? AND status = \'pendente\'';
                                if (in_array('tipo_compra', $colsLista, true)) {
                                    $sqlFind .= ' AND tipo_compra = ?';
                                }
                                $sqlFind .= ' ORDER BY id DESC LIMIT 1';
                                $stFind = $this->connection->prepare($sqlFind);
                                if (in_array('tipo_compra', $colsLista, true)) {
                                    $stFind->execute([$id, $produtoId, $tipoCompra]);
                                } else {
                                    $stFind->execute([$id, $produtoId]);
                                }
                                $ex = $stFind->fetch(\PDO::FETCH_ASSOC);
                                if (is_array($ex) && !empty($ex['id'])) {
                                    $newQtd = ((int) ($ex['qtd'] ?? 0)) + $qtd;
                                    $stUpd = $this->connection->prepare('UPDATE lista_compras SET ' . $colQtdLista . ' = ? WHERE id = ?');
                                    $stUpd->execute([$newQtd, (int) $ex['id']]);
                                    continue;
                                }
                            }
                        } catch (\Exception $e) {
                        }

                        $colsIns = [];
                        $valsIns = [];
                        $pIns = [];

                        if (in_array('produto_id', $colsLista, true)) {
                            $colsIns[] = 'produto_id';
                            $valsIns[] = ':produto_id';
                            $pIns[':produto_id'] = $produtoId;
                        }
                        if (in_array('pedido_id', $colsLista, true)) {
                            $colsIns[] = 'pedido_id';
                            $valsIns[] = ':pedido_id';
                            $pIns[':pedido_id'] = $id;
                        }
                        if (in_array('quantidade_faltante', $colsLista, true)) {
                            $colsIns[] = 'quantidade_faltante';
                            $valsIns[] = ':q';
                            $pIns[':q'] = $qtd;
                        } elseif (in_array('quantidade_necessaria', $colsLista, true)) {
                            $colsIns[] = 'quantidade_necessaria';
                            $valsIns[] = ':q';
                            $pIns[':q'] = $qtd;
                        }
                        if (in_array('status', $colsLista, true)) {
                            $colsIns[] = 'status';
                            $valsIns[] = "'pendente'";
                        }
                        if (in_array('tipo_compra', $colsLista, true)) {
                            $colsIns[] = 'tipo_compra';
                            $valsIns[] = ':tipo_compra';
                            $pIns[':tipo_compra'] = $tipoCompra;
                        }

                        if (empty($colsIns)) {
                            continue;
                        }

                        $sqlIns = 'INSERT INTO lista_compras (' . implode(',', $colsIns) . ') VALUES (' . implode(',', $valsIns) . ')';
                        $stIns = $this->connection->prepare($sqlIns);
                        $stIns->execute($pIns);
                    }
                }
            }

            $this->connection->commit();

            $_SESSION['message'] = 'Pedido confirmado e enviado para a lista de compras.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/pedidos/conferencia');
            exit;
        } catch (\Exception $e) {
            try {
                $this->connection->rollBack();
            } catch (\Exception $e2) {
            }
            $_SESSION['message'] = 'Erro ao confirmar pedido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }
    }

    public function cancelar($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['message'] = 'Pedido inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        try {
            $colsPedidos = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $set = [];
            $params = [':id' => $id];

            if (in_array('status_conferencia', $colsPedidos, true)) {
                $set[] = 'status_conferencia = :status_conferencia';
                $params[':status_conferencia'] = 'cancelado';
            }
            if (in_array('status', $colsPedidos, true)) {
                $set[] = 'status = :status';
                $params[':status'] = 'cancelado';
            }

            if (!empty($set)) {
                $st = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $st->execute($params);
            }

            $_SESSION['message'] = 'Pedido cancelado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao cancelar pedido.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/pedidos/conferencia');
        exit;
    }
}
