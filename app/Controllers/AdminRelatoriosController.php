<?php
namespace App\Controllers;

use App\Core\Request;
use Config\Database;

class AdminRelatoriosController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function getConfigNumber(string $categoria, string $chave, float $default = 0.0): float {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        foreach ($tableCandidates as $t) {
            try {
                $stmtTable = $this->connection->prepare("SHOW TABLES LIKE ?");
                $stmtTable->execute([$t]);
                if ($stmtTable->fetchColumn()) {
                    $table = $t;
                    break;
                }
            } catch (\Exception $e) {
            }
        }
        if (!$table) {
            return $default;
        }

        try {
            $stDesc = $this->connection->query('DESCRIBE ' . $table);
            $cols = $stDesc ? ($stDesc->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            // mode categoria+chave
            if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $valCol = null;
                foreach (['valor', 'value', 'conteudo', 'content', 'config_value'] as $c) {
                    if (in_array($c, $cols, true)) {
                        $valCol = $c;
                        break;
                    }
                }
                if ($valCol) {
                    $st = $this->connection->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE categoria = ? AND chave = ? ORDER BY id DESC LIMIT 1');
                    $st->execute([$categoria, $chave]);
                    $v = (string) ($st->fetchColumn() ?: '');
                    $v = str_replace(',', '.', $v);
                    return is_numeric($v) ? (float) $v : $default;
                }
            }

            // mode key/value
            $keyCol = null;
            foreach (['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'] as $c) {
                if (in_array($c, $cols, true)) {
                    $keyCol = $c;
                    break;
                }
            }
            $valCol = null;
            foreach (['valor', 'value', 'conteudo', 'content', 'config_value'] as $c) {
                if (in_array($c, $cols, true)) {
                    $valCol = $c;
                    break;
                }
            }
            if ($keyCol && $valCol) {
                $fullKey = $categoria . '_' . $chave;
                $st = $this->connection->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? ORDER BY id DESC LIMIT 1');
                $st->execute([$fullKey]);
                $v = (string) ($st->fetchColumn() ?: '');
                $v = str_replace(',', '.', $v);
                return is_numeric($v) ? (float) $v : $default;
            }

            // mode single_row (colunas diretas)
            if (is_array($cols) && in_array('id', $cols, true) && !in_array('categoria', $cols, true) && !in_array('chave', $cols, true)) {
                if (in_array($chave, $cols, true)) {
                    $st = $this->connection->query('SELECT ' . $chave . ' FROM ' . $table . ' ORDER BY id ASC LIMIT 1');
                    $v = (string) ($st ? ($st->fetchColumn() ?: '') : '');
                    $v = str_replace(',', '.', $v);
                    return is_numeric($v) ? (float) $v : $default;
                }
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    private function detectPaidAt(array $pedido): ?string {
        foreach (['pago_em', 'paid_at', 'data_pagamento', 'data_aprovacao', 'updated_at'] as $c) {
            if (!empty($pedido[$c]) && is_string($pedido[$c])) {
                return (string) $pedido[$c];
            }
        }
        return null;
    }

    private function getPedidoItensTable(): ?string {
        foreach (['pedido_itens', 'pedidos_itens', 'itens_pedido', 'pedido_items'] as $t) {
            try {
                $st = $this->connection->prepare('SHOW TABLES LIKE ?');
                $st->execute([$t]);
                if ($st->fetchColumn()) {
                    return $t;
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    public function auditoriaLogs(Request $request) {
        $usuarioId = (int) $request->getParam('usuario_id', 0);
        $dataInicio = (string) $request->getParam('data_inicio', '');
        $dataFim = (string) $request->getParam('data_fim', '');
        $q = trim((string) $request->getParam('q', ''));

        $page = (int) $request->getParam('page', 1);
        if ($page < 1) $page = 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $hasLogs = false;
        try {
            $st = $this->connection->prepare('SHOW TABLES LIKE ?');
            $st->execute(['auditoria_logs']);
            $hasLogs = (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            $hasLogs = false;
        }

        $usuarios = [];
        try {
            $st = $this->connection->prepare('SHOW TABLES LIKE ?');
            $st->execute(['usuarios']);
            if ($st->fetchColumn()) {
                $stU = $this->connection->query('SELECT id, COALESCE(nome, name, email) AS nome, email FROM usuarios ORDER BY id DESC LIMIT 500');
                $usuarios = $stU ? ($stU->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (\Exception $e) {
            $usuarios = [];
        }

        $rows = [];
        $totalRows = 0;
        if ($hasLogs) {
            $where = [];
            $params = [];
            if ($usuarioId > 0) {
                $where[] = 'l.usuario_id = :uid';
                $params[':uid'] = $usuarioId;
            }
            if ($dataInicio !== '') {
                $where[] = 'DATE(l.created_at) >= :di';
                $params[':di'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $where[] = 'DATE(l.created_at) <= :df';
                $params[':df'] = $dataFim;
            }
            if ($q !== '') {
                $where[] = '(l.acao LIKE :q OR l.tabela LIKE :q OR l.valores_novos LIKE :q OR l.valores_antigos LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }

            $joinUser = '';
            $selectUser = '';
            try {
                $st = $this->connection->prepare('SHOW TABLES LIKE ?');
                $st->execute(['usuarios']);
                if ($st->fetchColumn()) {
                    $joinUser = ' LEFT JOIN usuarios u ON u.id = l.usuario_id ';
                    $selectUser = ', u.email AS usuario_email, COALESCE(u.nome, u.name, u.email) AS usuario_nome';
                }
            } catch (\Exception $e) {
            }

            $baseSql = 'FROM auditoria_logs l' . $joinUser . (!empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '');

            try {
                $stCount = $this->connection->prepare('SELECT COUNT(*) ' . $baseSql);
                $stCount->execute($params);
                $totalRows = (int) ($stCount->fetchColumn() ?: 0);
            } catch (\Exception $e) {
                $totalRows = 0;
            }

            try {
                $sql = 'SELECT l.*' . $selectUser . ' ' . $baseSql . ' ORDER BY l.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
                $stList = $this->connection->prepare($sql);
                $stList->execute($params);
                $rows = $stList->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $rows = [];
            }
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auditoria de Ações - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('relatorios');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">'
            . '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">'
            . '<h1 class="h2"><i class="fas fa-user-shield me-2"></i>Auditoria / Logs de Uso</h1>'
            . '</div>';

        if (!$hasLogs) {
            echo '<div class="alert alert-warning">A tabela <strong>auditoria_logs</strong> não existe no banco. Rode a migration <code>068_create_auditoria_logs.sql</code>.</div>';
        }

        echo '<div class="card mb-3"><div class="card-body">'
            . '<form method="GET" class="row g-2 align-items-end">'
            . '<div class="col-md-3"><label class="form-label">Usuário</label><select class="form-select" name="usuario_id">'
            . '<option value="0">Todos</option>';
        foreach ($usuarios as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $label = trim((string) (($u['nome'] ?? '') . ' ' . ($u['email'] ?? '')));
            if ($label === '') $label = 'Usuário #' . $uid;
            echo '<option value="' . $uid . '" ' . ($usuarioId === $uid ? 'selected' : '') . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select></div>'
            . '<div class="col-md-2"><label class="form-label">De</label><input type="date" class="form-control" name="data_inicio" value="' . htmlspecialchars($dataInicio) . '"></div>'
            . '<div class="col-md-2"><label class="form-label">Até</label><input type="date" class="form-control" name="data_fim" value="' . htmlspecialchars($dataFim) . '"></div>'
            . '<div class="col-md-4"><label class="form-label">Buscar</label><input type="text" class="form-control" name="q" value="' . htmlspecialchars($q) . '" placeholder="ação, rota, tabela..."></div>'
            . '<div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit">Filtrar</button></div>'
            . '</form>'
            . '</div></div>';

        $totalPages = $perPage > 0 ? (int) ceil($totalRows / $perPage) : 1;
        if ($totalPages < 1) $totalPages = 1;

        echo '<div class="d-flex justify-content-between align-items-center mb-2">'
            . '<div class="text-muted small">Total: <strong>' . number_format($totalRows) . '</strong> registros</div>'
            . '<div class="text-muted small">Página <strong>' . $page . '</strong> de <strong>' . $totalPages . '</strong></div>'
            . '</div>';

        echo '<div class="card"><div class="card-body">'
            . '<div class="table-responsive">'
            . '<table class="table table-sm table-hover">'
            . '<thead><tr>'
            . '<th>Data</th><th>Usuário</th><th>Ação</th><th>IP</th><th>Detalhes</th>'
            . '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center text-muted">Nenhum registro encontrado.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $dt = !empty($r['created_at']) ? date('d/m/Y H:i:s', strtotime((string) $r['created_at'])) : '-';
                $uName = (string) ($r['usuario_nome'] ?? '');
                $uEmail = (string) ($r['usuario_email'] ?? '');
                $uLabel = trim($uName !== '' ? ($uName . ($uEmail !== '' ? (' (' . $uEmail . ')') : '')) : ($uEmail !== '' ? $uEmail : ''));
                if ($uLabel === '') {
                    $uLabel = 'Usuário #' . (int) ($r['usuario_id'] ?? 0);
                }

                $acao = (string) ($r['acao'] ?? '');
                $ip = (string) ($r['ip'] ?? '');
                $payload = (string) ($r['valores_novos'] ?? '');
                $short = $payload;
                if (strlen($short) > 240) {
                    $short = substr($short, 0, 240) . '...';
                }

                $detailId = 'log_' . (int) ($r['id'] ?? 0);
                echo '<tr>'
                    . '<td>' . htmlspecialchars($dt) . '</td>'
                    . '<td>' . htmlspecialchars($uLabel) . '</td>'
                    . '<td><code>' . htmlspecialchars($acao) . '</code></td>'
                    . '<td>' . htmlspecialchars($ip) . '</td>'
                    . '<td>'
                    . '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#' . $detailId . '">Ver</button>'
                    . '<div class="collapse mt-2" id="' . $detailId . '">'
                    . '<pre class="small mb-0" style="white-space:pre-wrap;">' . htmlspecialchars($payload) . '</pre>'
                    . '</div>'
                    . '</td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table></div></div></div>';

        // paginação
        $qs = $_GET;
        echo '<nav class="mt-3"><ul class="pagination pagination-sm">';
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
        $qs['page'] = $prev;
        echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="?' . htmlspecialchars(http_build_query($qs)) . '">Anterior</a></li>';
        $qs['page'] = $next;
        echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="?' . htmlspecialchars(http_build_query($qs)) . '">Próxima</a></li>';
        echo '</ul></nav>';

        echo '</main></div></div>'
            . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>'
            . '</body></html>';
        exit;
    }

    public function financeiro(Request $request) {
        // filtros
        $dataInicioCriacao = (string) $request->getParam('data_inicio_criacao', '');
        $dataFimCriacao = (string) $request->getParam('data_fim_criacao', '');
        $dataInicioPagamento = (string) $request->getParam('data_inicio_pagamento', '');
        $dataFimPagamento = (string) $request->getParam('data_fim_pagamento', '');
        $status = (string) $request->getParam('status', '');
        $moeda = strtoupper(trim((string) $request->getParam('moeda', '')));
        if ($moeda !== '' && !in_array($moeda, ['USD', 'BRL'], true)) {
            $moeda = '';
        }

        $custoEnvioPorItemUsd = $this->getConfigNumber('entrega', 'custo_envio_por_item_usd', 0.0);
        $comissaoPercentual = $this->getConfigNumber('entrega', 'comissao_percentual', 0.0);

        $itensTable = $this->getPedidoItensTable();
        $temItens = ($itensTable !== null);

        // colunas tolerantes
        $colsPedidos = [];
        try {
            $st = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        $colTotal = null;
        foreach (['valor_total', 'total', 'amount'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colTotal = $c;
                break;
            }
        }
        $colImpostos = null;
        foreach (['valor_impostos', 'impostos'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colImpostos = $c;
                break;
            }
        }
        $colMoeda = null;
        foreach (['moeda', 'currency'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colMoeda = $c;
                break;
            }
        }
        $colTaxa = in_array('taxa_conversao', $colsPedidos, true) ? 'taxa_conversao' : null;

        $where = [];
        $params = [];
        if ($dataInicioCriacao !== '' && in_array('created_at', $colsPedidos, true)) {
            $where[] = 'DATE(p.created_at) >= :di';
            $params[':di'] = $dataInicioCriacao;
        }
        if ($dataFimCriacao !== '' && in_array('created_at', $colsPedidos, true)) {
            $where[] = 'DATE(p.created_at) <= :df';
            $params[':df'] = $dataFimCriacao;
        }
        if ($status !== '' && in_array('status', $colsPedidos, true)) {
            $where[] = 'p.status = :st';
            $params[':st'] = $status;
        }
        if ($moeda !== '' && $colMoeda !== null) {
            $where[] = 'UPPER(COALESCE(p.' . $colMoeda . ", 'BRL')) = :m";
            $params[':m'] = $moeda;
        }

        $sql = 'SELECT p.* FROM pedidos p';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . (in_array('created_at', $colsPedidos, true) ? 'p.created_at DESC' : 'p.id DESC') . ' LIMIT 500';

        $rows = [];
        try {
            $st = $this->connection->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $rows = [];
        }

        // Filtrar por data de pagamento em PHP (tolerante)
        if ($dataInicioPagamento !== '' || $dataFimPagamento !== '') {
            $rows = array_values(array_filter($rows, function ($p) use ($dataInicioPagamento, $dataFimPagamento) {
                $paidAt = $this->detectPaidAt((array) $p);
                if (!$paidAt) return false;
                $d = date('Y-m-d', strtotime((string) $paidAt));
                if ($dataInicioPagamento !== '' && $d < $dataInicioPagamento) return false;
                if ($dataFimPagamento !== '' && $d > $dataFimPagamento) return false;
                return true;
            }));
        }

        // Mapas de itens: qtd total por pedido + custo produto (USD)
        $qtdByPedido = [];
        $custoProdutosUsdByPedido = [];
        if ($temItens) {
            try {
                $stDesc = $this->connection->query('DESCRIBE ' . $itensTable);
                $colsI = $stDesc ? ($stDesc->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

                $colPedidoId = in_array('pedido_id', $colsI, true) ? 'pedido_id' : null;
                $colProdutoId = in_array('produto_id', $colsI, true) ? 'produto_id' : null;
                $colQtd = null;
                foreach (['quantidade', 'qty', 'qtd'] as $c) {
                    if (in_array($c, $colsI, true)) {
                        $colQtd = $c;
                        break;
                    }
                }
                if ($colPedidoId && $colProdutoId && $colQtd && !empty($rows)) {
                    $ids = array_values(array_unique(array_map(fn($r) => (int) ($r['id'] ?? 0), $rows)));
                    $ids = array_values(array_filter($ids, fn($v) => $v > 0));
                    if (!empty($ids)) {
                        $in = implode(',', array_fill(0, count($ids), '?'));
                        $sqlItens = 'SELECT ' . $colPedidoId . ' AS pedido_id, ' . $colProdutoId . ' AS produto_id, ' . $colQtd . ' AS quantidade FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' IN (' . $in . ')';
                        $stItens = $this->connection->prepare($sqlItens);
                        $stItens->execute($ids);
                        $itRows = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        // carregar custos por produto (USD)
                        $colsP = [];
                        try {
                            $stCP = $this->connection->query('DESCRIBE produtos');
                            $colsP = $stCP ? ($stCP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsP = [];
                        }
                        $colCusto = null;
                        foreach (['preco_custo', 'custo', 'cost_price', 'valor_custo'] as $c) {
                            if (in_array($c, $colsP, true)) {
                                $colCusto = $c;
                                break;
                            }
                        }
                        $custoByProduto = [];
                        if ($colCusto) {
                            $pids = array_values(array_unique(array_map(fn($r) => (int) ($r['produto_id'] ?? 0), $itRows)));
                            $pids = array_values(array_filter($pids, fn($v) => $v > 0));
                            if (!empty($pids)) {
                                $inP = implode(',', array_fill(0, count($pids), '?'));
                                $stP = $this->connection->prepare('SELECT id, ' . $colCusto . ' AS custo FROM produtos WHERE id IN (' . $inP . ')');
                                $stP->execute($pids);
                                $pRows = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                foreach ($pRows as $pr) {
                                    $custoByProduto[(int) $pr['id']] = (float) ($pr['custo'] ?? 0);
                                }
                            }
                        }

                        foreach ($itRows as $ir) {
                            $pid = (int) ($ir['pedido_id'] ?? 0);
                            if ($pid <= 0) continue;
                            $q = (int) ($ir['quantidade'] ?? 0);
                            if ($q <= 0) $q = 1;
                            $qtdByPedido[$pid] = ($qtdByPedido[$pid] ?? 0) + $q;

                            $prodId = (int) ($ir['produto_id'] ?? 0);
                            $custoUnit = (float) ($custoByProduto[$prodId] ?? 0);
                            $custoProdutosUsdByPedido[$pid] = ($custoProdutosUsdByPedido[$pid] ?? 0) + ($custoUnit * $q);
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        $out = [];
        $totais = [
            'total_usd' => 0.0,
            'impostos_usd' => 0.0,
            'envio_fixo_usd' => 0.0,
            'custo_produtos_usd' => 0.0,
            'comissao_usd' => 0.0,
            'lucro_usd' => 0.0,
            'total_brl' => 0.0,
            'impostos_brl' => 0.0,
            'envio_fixo_brl' => 0.0,
            'custo_produtos_brl' => 0.0,
            'comissao_brl' => 0.0,
            'lucro_brl' => 0.0,
        ];

        $qtdPedidos = 0;
        $qtdPedidosUsd = 0;
        $qtdPedidosBrl = 0;
        $totalOrigUsd = 0.0;
        $totalOrigBrl = 0.0;
        $impostosOrigUsd = 0.0;
        $impostosOrigBrl = 0.0;

        foreach ($rows as $p) {
            $pedidoId = (int) ($p['id'] ?? 0);
            if ($pedidoId <= 0) continue;

            $m = strtoupper((string) ($colMoeda ? ($p[$colMoeda] ?? 'BRL') : 'BRL'));
            if (!in_array($m, ['USD', 'BRL'], true)) $m = 'BRL';
            $taxa = $colTaxa ? (float) ($p[$colTaxa] ?? 1.0) : 1.0;
            if ($taxa <= 0) $taxa = 1.0;

            $totalOrig = (float) ($colTotal ? ($p[$colTotal] ?? 0) : 0);
            $impostosOrig = (float) ($colImpostos ? ($p[$colImpostos] ?? 0) : 0);

            $qtdPedidos++;
            if ($m === 'USD') {
                $qtdPedidosUsd++;
                $totalOrigUsd += $totalOrig;
                $impostosOrigUsd += $impostosOrig;
            } else {
                $qtdPedidosBrl++;
                $totalOrigBrl += $totalOrig;
                $impostosOrigBrl += $impostosOrig;
            }

            $totalUsd = ($m === 'USD') ? $totalOrig : ($totalOrig / $taxa);
            $impostosUsd = ($m === 'USD') ? $impostosOrig : ($impostosOrig / $taxa);
            $totalBrl = ($m === 'BRL') ? $totalOrig : ($totalOrig * $taxa);
            $impostosBrl = ($m === 'BRL') ? $impostosOrig : ($impostosOrig * $taxa);

            $qtdItens = (int) ($qtdByPedido[$pedidoId] ?? 0);
            if ($qtdItens <= 0) $qtdItens = 1;

            $custoEnvioFixoUsd = $custoEnvioPorItemUsd * $qtdItens;
            $custoEnvioFixoBrl = $custoEnvioFixoUsd * $taxa;

            $custoProdutosUsd = (float) ($custoProdutosUsdByPedido[$pedidoId] ?? 0);
            $custoProdutosBrl = $custoProdutosUsd * $taxa;

            $baseComissaoUsd = max(0.0, $totalUsd - $impostosUsd);
            $valorComissaoUsd = $baseComissaoUsd * ($comissaoPercentual / 100.0);
            $valorComissaoBrl = $valorComissaoUsd * $taxa;

            $lucroUsd = $totalUsd - $impostosUsd - $custoEnvioFixoUsd - $custoProdutosUsd - $valorComissaoUsd;
            $lucroBrl = $totalBrl - $impostosBrl - $custoEnvioFixoBrl - $custoProdutosBrl - $valorComissaoBrl;

            $createdAt = !empty($p['created_at']) ? (string) $p['created_at'] : '';
            $paidAt = $this->detectPaidAt($p) ?? '';
            $numero = (string) ($p['numero_pedido'] ?? ($p['codigo_pedido'] ?? $pedidoId));

            $out[] = [
                'id' => $pedidoId,
                'numero' => $numero,
                'status' => (string) ($p['status'] ?? ''),
                'created_at' => $createdAt,
                'paid_at' => $paidAt,
                'moeda' => $m,
                'taxa_conversao' => $taxa,
                'qtd_itens' => $qtdItens,
                'total_usd' => $totalUsd,
                'impostos_usd' => $impostosUsd,
                'envio_fixo_usd' => $custoEnvioFixoUsd,
                'custo_produtos_usd' => $custoProdutosUsd,
                'comissao_usd' => $valorComissaoUsd,
                'lucro_usd' => $lucroUsd,
                'total_brl' => $totalBrl,
                'impostos_brl' => $impostosBrl,
                'envio_fixo_brl' => $custoEnvioFixoBrl,
                'custo_produtos_brl' => $custoProdutosBrl,
                'comissao_brl' => $valorComissaoBrl,
                'lucro_brl' => $lucroBrl,
            ];

            $totais['total_usd'] += $totalUsd;
            $totais['impostos_usd'] += $impostosUsd;
            $totais['envio_fixo_usd'] += $custoEnvioFixoUsd;
            $totais['custo_produtos_usd'] += $custoProdutosUsd;
            $totais['comissao_usd'] += $valorComissaoUsd;
            $totais['lucro_usd'] += $lucroUsd;

            $totais['total_brl'] += $totalBrl;
            $totais['impostos_brl'] += $impostosBrl;
            $totais['envio_fixo_brl'] += $custoEnvioFixoBrl;
            $totais['custo_produtos_brl'] += $custoProdutosBrl;
            $totais['comissao_brl'] += $valorComissaoBrl;
            $totais['lucro_brl'] += $lucroBrl;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatório Financeiro - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('relatorios');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">'
            . '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">'
            . '<h1 class="h2"><i class="fas fa-chart-line me-2"></i>Relatório Financeiro (Completo)</h1>'
            . '</div>';

        echo '<div class="card mb-3"><div class="card-body">'
            . '<form method="GET" class="row g-2 align-items-end">'
            . '<div class="col-md-2"><label class="form-label">Criação (de)</label><input type="date" class="form-control" name="data_inicio_criacao" value="' . htmlspecialchars($dataInicioCriacao) . '"></div>'
            . '<div class="col-md-2"><label class="form-label">Criação (até)</label><input type="date" class="form-control" name="data_fim_criacao" value="' . htmlspecialchars($dataFimCriacao) . '"></div>'
            . '<div class="col-md-2"><label class="form-label">Pagamento (de)</label><input type="date" class="form-control" name="data_inicio_pagamento" value="' . htmlspecialchars($dataInicioPagamento) . '"></div>'
            . '<div class="col-md-2"><label class="form-label">Pagamento (até)</label><input type="date" class="form-control" name="data_fim_pagamento" value="' . htmlspecialchars($dataFimPagamento) . '"></div>'
            . '<div class="col-md-2"><label class="form-label">Status</label><input type="text" class="form-control" name="status" value="' . htmlspecialchars($status) . '" placeholder="ex: pago"></div>'
            . '<div class="col-md-1"><label class="form-label">Moeda</label><select class="form-select" name="moeda">'
            . '<option value="" ' . ($moeda === '' ? 'selected' : '') . '>Todas</option>'
            . '<option value="USD" ' . ($moeda === 'USD' ? 'selected' : '') . '>USD</option>'
            . '<option value="BRL" ' . ($moeda === 'BRL' ? 'selected' : '') . '>BRL</option>'
            . '</select></div>'
            . '<div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit">Filtrar</button></div>'
            . '</form>'
            . '<div class="mt-3 d-flex gap-2">'
            . '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="/admin/estoque/relatorios/financeiro/export?format=csv&' . http_build_query($_GET) . '">Exportar CSV</a>'
            . '<a class="btn btn-outline-danger btn-sm" target="_blank" href="/admin/estoque/relatorios/financeiro/export?format=pdf&' . http_build_query($_GET) . '">Exportar PDF</a>'
            . '</div>'
            . '</div></div>';

        echo '<div class="row g-3 mb-3">'
            . '<div class="col-md-3"><div class="card"><div class="card-body">'
            . '<div class="text-muted small">Quantidade de vendas</div>'
            . '<div class="h4 mb-0">' . number_format($qtdPedidos) . '</div>'
            . '<div class="small text-muted">USD: ' . number_format($qtdPedidosUsd) . ' | BRL: ' . number_format($qtdPedidosBrl) . '</div>'
            . '</div></div></div>'

            . '<div class="col-md-3"><div class="card"><div class="card-body">'
            . '<div class="text-muted small">Total arrecadado (origem)</div>'
            . '<div class="h6 mb-0">US$ ' . number_format($totalOrigUsd, 2, '.', ',') . '</div>'
            . '<div class="h6 mb-0">R$ ' . number_format($totalOrigBrl, 2, ',', '.') . '</div>'
            . '</div></div></div>'

            . '<div class="col-md-3"><div class="card"><div class="card-body">'
            . '<div class="text-muted small">Impostos arrecadados (origem)</div>'
            . '<div class="h6 mb-0">US$ ' . number_format($impostosOrigUsd, 2, '.', ',') . '</div>'
            . '<div class="h6 mb-0">R$ ' . number_format($impostosOrigBrl, 2, ',', '.') . '</div>'
            . '</div></div></div>'

            . '<div class="col-md-3"><div class="card"><div class="card-body">'
            . '<div class="text-muted small">Lucro (consolidado)</div>'
            . '<div class="h6 mb-0">US$ ' . number_format($totais['lucro_usd'], 2, '.', ',') . '</div>'
            . '<div class="h6 mb-0">R$ ' . number_format($totais['lucro_brl'], 2, ',', '.') . '</div>'
            . '</div></div></div>'
            . '</div>';

        echo '<div class="row g-3 mb-3">'
            . '<div class="col-md-6"><div class="card"><div class="card-body">'
            . '<div class="fw-bold mb-2">Consolidado (USD)</div>'
            . '<div>Total arrecadado: <strong>$ ' . number_format($totais['total_usd'], 2, '.', ',') . '</strong></div>'
            . '<div>Impostos (pass-through): <strong>$ ' . number_format($totais['impostos_usd'], 2, '.', ',') . '</strong></div>'
            . '<div>Custo envio fixo: <strong>$ ' . number_format($totais['envio_fixo_usd'], 2, '.', ',') . '</strong></div>'
            . '<div>Custo produtos: <strong>$ ' . number_format($totais['custo_produtos_usd'], 2, '.', ',') . '</strong></div>'
            . '<div>Comissão: <strong>$ ' . number_format($totais['comissao_usd'], 2, '.', ',') . '</strong></div>'
            . '</div></div></div>'
            . '<div class="col-md-6"><div class="card"><div class="card-body">'
            . '<div class="fw-bold mb-2">Consolidado (BRL)</div>'
            . '<div>Total arrecadado: <strong>R$ ' . number_format($totais['total_brl'], 2, ',', '.') . '</strong></div>'
            . '<div>Impostos (pass-through): <strong>R$ ' . number_format($totais['impostos_brl'], 2, ',', '.') . '</strong></div>'
            . '<div>Custo envio fixo: <strong>R$ ' . number_format($totais['envio_fixo_brl'], 2, ',', '.') . '</strong></div>'
            . '<div>Custo produtos: <strong>R$ ' . number_format($totais['custo_produtos_brl'], 2, ',', '.') . '</strong></div>'
            . '<div>Comissão: <strong>R$ ' . number_format($totais['comissao_brl'], 2, ',', '.') . '</strong></div>'
            . '</div></div></div>'
            . '</div>';

        echo '<div class="card"><div class="card-body">'
            . '<div class="table-responsive"><table class="table table-sm table-hover">'
            . '<thead><tr>'
            . '<th>ID</th><th>Número</th><th>Status</th><th>Criado</th><th>Pago</th><th>Moeda</th><th>Tx</th><th>Itens</th>'
            . '<th>Total USD</th><th>Impostos USD</th><th>Envio USD</th><th>Custo Prod USD</th><th>Comissão USD</th><th>Lucro USD</th>'
            . '<th>Total BRL</th><th>Impostos BRL</th><th>Envio BRL</th><th>Custo Prod BRL</th><th>Comissão BRL</th><th>Lucro BRL</th>'
            . '</tr></thead><tbody>';

        foreach ($out as $r) {
            echo '<tr>'
                . '<td>' . (int) $r['id'] . '</td>'
                . '<td>' . htmlspecialchars((string) $r['numero']) . '</td>'
                . '<td>' . htmlspecialchars((string) $r['status']) . '</td>'
                . '<td>' . (!empty($r['created_at']) ? date('d/m/Y', strtotime((string) $r['created_at'])) : '-') . '</td>'
                . '<td>' . (!empty($r['paid_at']) ? date('d/m/Y', strtotime((string) $r['paid_at'])) : '-') . '</td>'
                . '<td>' . htmlspecialchars((string) $r['moeda']) . '</td>'
                . '<td>' . number_format((float) $r['taxa_conversao'], 4, '.', ',') . '</td>'
                . '<td>' . (int) $r['qtd_itens'] . '</td>'
                . '<td>$ ' . number_format((float) $r['total_usd'], 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format((float) $r['impostos_usd'], 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format((float) $r['envio_fixo_usd'], 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format((float) $r['custo_produtos_usd'], 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format((float) $r['comissao_usd'], 2, '.', ',') . '</td>'
                . '<td>$ ' . number_format((float) $r['lucro_usd'], 2, '.', ',') . '</td>'
                . '<td>R$ ' . number_format((float) $r['total_brl'], 2, ',', '.') . '</td>'
                . '<td>R$ ' . number_format((float) $r['impostos_brl'], 2, ',', '.') . '</td>'
                . '<td>R$ ' . number_format((float) $r['envio_fixo_brl'], 2, ',', '.') . '</td>'
                . '<td>R$ ' . number_format((float) $r['custo_produtos_brl'], 2, ',', '.') . '</td>'
                . '<td>R$ ' . number_format((float) $r['comissao_brl'], 2, ',', '.') . '</td>'
                . '<td>R$ ' . number_format((float) $r['lucro_brl'], 2, ',', '.') . '</td>'
                . '</tr>';
        }

        echo '</tbody></table></div></div></div>';

        echo '</main></div></div>'
            . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>'
            . '</body></html>';
    }

    public function exportFinanceiro(Request $request) {
        $format = strtolower((string) $request->getParam('format', 'csv'));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            $format = 'csv';
        }

        // Reutilizar o mesmo cálculo do financeiro() via chamada interna
        // (sem duplicar muita lógica: monta Request fake usando $_GET)
        $tmp = new Request();
        foreach ($_GET as $k => $v) {
            // Request getParam já acessa internamente, então manteremos via $_GET no financeiro.
        }

        // Gerar os mesmos dados chamando financeiro(), mas capturando saída HTML e/ou extraindo tabela.
        // Para manter simples e confiável: CSV é montado diretamente reexecutando a consulta mínima.
        // Para PDF, retornamos HTML imprimível.

        // Chamar financeiro() para montar dados (capturar HTML) quando PDF
        if ($format === 'pdf') {
            ob_start();
            $this->financeiro($request);
            $html = ob_get_clean();
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: inline; filename="relatorio_financeiro_' . date('Y-m-d') . '.html"');
            echo $html;
            exit;
        }

        // CSV: reexecutar financeiro() e extrair dados via buffer seria caro; então geramos um CSV resumido chamando financeiro() e varrendo a tabela.
        // Aqui, faremos um CSV simples usando o mesmo endpoint HTML e extraindo por regex é frágil; então melhor redirecionar para PDF/HTML?
        // Implementação robusta: reusar a lógica do financeiro() acima seria duplicação; mas aceitável para CSV com campos essenciais.
        // Para evitar duplicação aqui, vamos apenas renderizar o HTML e o usuário pode exportar no navegador.
        // Como você pediu CSV real, vamos gerar CSV com as colunas principais a partir do próprio cálculo novamente (duplicação mínima).

        // Reaproveitar: chamar financeiro() gera $out local; não acessível. Então faremos uma versão compacta para CSV aqui.
        $dataInicioCriacao = (string) $request->getParam('data_inicio_criacao', '');
        $dataFimCriacao = (string) $request->getParam('data_fim_criacao', '');
        $status = (string) $request->getParam('status', '');
        $moeda = strtoupper(trim((string) $request->getParam('moeda', '')));
        if ($moeda !== '' && !in_array($moeda, ['USD', 'BRL'], true)) {
            $moeda = '';
        }

        $custoEnvioPorItemUsd = $this->getConfigNumber('entrega', 'custo_envio_por_item_usd', 0.0);
        $comissaoPercentual = $this->getConfigNumber('entrega', 'comissao_percentual', 0.0);
        $itensTable = $this->getPedidoItensTable();

        $colsPedidos = [];
        try {
            $st = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }
        $colTotal = null;
        foreach (['valor_total', 'total', 'amount'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colTotal = $c;
                break;
            }
        }
        $colImpostos = null;
        foreach (['valor_impostos', 'impostos'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colImpostos = $c;
                break;
            }
        }
        $colMoeda = null;
        foreach (['moeda', 'currency'] as $c) {
            if (in_array($c, $colsPedidos, true)) {
                $colMoeda = $c;
                break;
            }
        }
        $colTaxa = in_array('taxa_conversao', $colsPedidos, true) ? 'taxa_conversao' : null;

        $where = [];
        $params = [];
        if ($dataInicioCriacao !== '' && in_array('created_at', $colsPedidos, true)) {
            $where[] = 'DATE(p.created_at) >= :di';
            $params[':di'] = $dataInicioCriacao;
        }
        if ($dataFimCriacao !== '' && in_array('created_at', $colsPedidos, true)) {
            $where[] = 'DATE(p.created_at) <= :df';
            $params[':df'] = $dataFimCriacao;
        }
        if ($status !== '' && in_array('status', $colsPedidos, true)) {
            $where[] = 'p.status = :st';
            $params[':st'] = $status;
        }
        if ($moeda !== '' && $colMoeda !== null) {
            $where[] = 'UPPER(COALESCE(p.' . $colMoeda . ", 'BRL')) = :m";
            $params[':m'] = $moeda;
        }
        $sql = 'SELECT p.* FROM pedidos p' . (!empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY ' . (in_array('created_at', $colsPedidos, true) ? 'p.created_at DESC' : 'p.id DESC') . ' LIMIT 2000';
        $rows = [];
        try {
            $st = $this->connection->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $rows = [];
        }

        // Mapas mínimos para custo/qtd
        $qtdByPedido = [];
        $custoProdutosUsdByPedido = [];
        if ($itensTable) {
            try {
                $stDesc = $this->connection->query('DESCRIBE ' . $itensTable);
                $colsI = $stDesc ? ($stDesc->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                $colPedidoId = in_array('pedido_id', $colsI, true) ? 'pedido_id' : null;
                $colProdutoId = in_array('produto_id', $colsI, true) ? 'produto_id' : null;
                $colQtd = null;
                foreach (['quantidade', 'qty', 'qtd'] as $c) {
                    if (in_array($c, $colsI, true)) {
                        $colQtd = $c;
                        break;
                    }
                }
                if ($colPedidoId && $colProdutoId && $colQtd && !empty($rows)) {
                    $ids = array_values(array_unique(array_map(fn($r) => (int) ($r['id'] ?? 0), $rows)));
                    $ids = array_values(array_filter($ids, fn($v) => $v > 0));
                    if (!empty($ids)) {
                        $in = implode(',', array_fill(0, count($ids), '?'));
                        $stItens = $this->connection->prepare('SELECT ' . $colPedidoId . ' AS pedido_id, ' . $colProdutoId . ' AS produto_id, ' . $colQtd . ' AS quantidade FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' IN (' . $in . ')');
                        $stItens->execute($ids);
                        $itRows = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        $colsP = [];
                        try {
                            $stCP = $this->connection->query('DESCRIBE produtos');
                            $colsP = $stCP ? ($stCP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsP = [];
                        }
                        $colCusto = null;
                        foreach (['preco_custo', 'custo', 'cost_price', 'valor_custo'] as $c) {
                            if (in_array($c, $colsP, true)) {
                                $colCusto = $c;
                                break;
                            }
                        }
                        $custoByProduto = [];
                        if ($colCusto) {
                            $pids = array_values(array_unique(array_map(fn($r) => (int) ($r['produto_id'] ?? 0), $itRows)));
                            $pids = array_values(array_filter($pids, fn($v) => $v > 0));
                            if (!empty($pids)) {
                                $inP = implode(',', array_fill(0, count($pids), '?'));
                                $stP = $this->connection->prepare('SELECT id, ' . $colCusto . ' AS custo FROM produtos WHERE id IN (' . $inP . ')');
                                $stP->execute($pids);
                                $pRows = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                foreach ($pRows as $pr) {
                                    $custoByProduto[(int) $pr['id']] = (float) ($pr['custo'] ?? 0);
                                }
                            }
                        }

                        foreach ($itRows as $ir) {
                            $pid = (int) ($ir['pedido_id'] ?? 0);
                            if ($pid <= 0) continue;
                            $q = (int) ($ir['quantidade'] ?? 0);
                            if ($q <= 0) $q = 1;
                            $qtdByPedido[$pid] = ($qtdByPedido[$pid] ?? 0) + $q;
                            $prodId = (int) ($ir['produto_id'] ?? 0);
                            $custoUnit = (float) ($custoByProduto[$prodId] ?? 0);
                            $custoProdutosUsdByPedido[$pid] = ($custoProdutosUsdByPedido[$pid] ?? 0) + ($custoUnit * $q);
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_financeiro_' . date('Y-m-d') . '.csv"');

        $fp = fopen('php://output', 'w');
        fputcsv($fp, [
            'pedido_id', 'numero', 'status', 'created_at', 'paid_at', 'moeda', 'taxa_conversao', 'qtd_itens',
            'total_usd', 'impostos_usd', 'envio_fixo_usd', 'custo_produtos_usd', 'comissao_usd', 'lucro_usd',
            'total_brl', 'impostos_brl', 'envio_fixo_brl', 'custo_produtos_brl', 'comissao_brl', 'lucro_brl',
        ]);

        foreach ($rows as $p) {
            $pedidoId = (int) ($p['id'] ?? 0);
            if ($pedidoId <= 0) continue;
            $m = strtoupper((string) ($colMoeda ? ($p[$colMoeda] ?? 'BRL') : 'BRL'));
            if (!in_array($m, ['USD', 'BRL'], true)) $m = 'BRL';
            $taxa = $colTaxa ? (float) ($p[$colTaxa] ?? 1.0) : 1.0;
            if ($taxa <= 0) $taxa = 1.0;
            $totalOrig = (float) ($colTotal ? ($p[$colTotal] ?? 0) : 0);
            $impostosOrig = (float) ($colImpostos ? ($p[$colImpostos] ?? 0) : 0);
            $totalUsd = ($m === 'USD') ? $totalOrig : ($totalOrig / $taxa);
            $impostosUsd = ($m === 'USD') ? $impostosOrig : ($impostosOrig / $taxa);
            $totalBrl = ($m === 'BRL') ? $totalOrig : ($totalOrig * $taxa);
            $impostosBrl = ($m === 'BRL') ? $impostosOrig : ($impostosOrig * $taxa);
            $qtdItens = (int) ($qtdByPedido[$pedidoId] ?? 0);
            if ($qtdItens <= 0) $qtdItens = 1;
            $envioUsd = $custoEnvioPorItemUsd * $qtdItens;
            $envioBrl = $envioUsd * $taxa;
            $custoProdUsd = (float) ($custoProdutosUsdByPedido[$pedidoId] ?? 0);
            $custoProdBrl = $custoProdUsd * $taxa;
            $baseComUsd = max(0.0, $totalUsd - $impostosUsd);
            $comUsd = $baseComUsd * ($comissaoPercentual / 100.0);
            $comBrl = $comUsd * $taxa;
            $lucroUsd = $totalUsd - $impostosUsd - $envioUsd - $custoProdUsd - $comUsd;
            $lucroBrl = $totalBrl - $impostosBrl - $envioBrl - $custoProdBrl - $comBrl;
            $numero = (string) ($p['numero_pedido'] ?? ($p['codigo_pedido'] ?? $pedidoId));
            $createdAt = !empty($p['created_at']) ? (string) $p['created_at'] : '';
            $paidAt = $this->detectPaidAt($p) ?? '';

            fputcsv($fp, [
                $pedidoId, $numero, (string) ($p['status'] ?? ''), $createdAt, $paidAt, $m, $taxa, $qtdItens,
                $totalUsd, $impostosUsd, $envioUsd, $custoProdUsd, $comUsd, $lucroUsd,
                $totalBrl, $impostosBrl, $envioBrl, $custoProdBrl, $comBrl, $lucroBrl,
            ]);
        }
        fclose($fp);
        exit;
    }

    public function index($request) {
        try {
            // Buscar estatísticas gerais
            $stmt = $this->connection->prepare("
                SELECT 
                    COUNT(DISTINCT p.id) as total_produtos,
                    COUNT(DISTINCT CASE WHEN e.quantidade > 0 THEN p.id END) as produtos_com_estoque,
                    COALESCE(SUM(e.quantidade), 0) as total_itens_estoque,
                    COUNT(DISTINCT CASE WHEN lc.status = 'pendente' THEN lc.produto_id END) as produtos_comprar,
                    COALESCE(SUM(lc.quantidade_faltante), 0) as total_itens_faltantes,
                    COUNT(DISTINCT CASE WHEN e.is_alimenticio = 1 AND e.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN e.produto_id END) as produtos_vencendo
                FROM produtos p
                LEFT JOIN estoque_interno e ON p.id = e.produto_id
                LEFT JOIN lista_compras lc ON p.id = lc.produto_id AND lc.status = 'pendente'
                WHERE p.active = 1
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Movimentações recentes
            $stmt = $this->connection->prepare("
                SELECT 
                    em.*,
                    p.name as produto_nome,
                    p.sku,
                    u.name as usuario_nome
                FROM estoque_movimentacao em
                JOIN produtos p ON em.produto_id = p.id
                LEFT JOIN usuarios u ON em.usuario_id = u.id
                ORDER BY em.data_movimentacao DESC
                LIMIT 20
            ");
            $stmt->execute();
            $movimentacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Produtos críticos
            $stmt = $this->connection->prepare("
                SELECT 
                    p.id,
                    p.name as produto_nome,
                    p.sku,
                    p.loja,
                    COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                    ec.estoque_minimo,
                    ec.estoque_ideal
                FROM produtos p
                LEFT JOIN estoque_interno e ON p.id = e.produto_id
                LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                WHERE p.active = 1
                GROUP BY p.id, p.name, p.sku, p.loja, ec.estoque_minimo, ec.estoque_ideal
                HAVING quantidade_estoque <= COALESCE(ec.estoque_minimo, 5)
                ORDER BY quantidade_estoque ASC
                LIMIT 10
            ");
            $stmt->execute();
            $produtos_criticos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Compras urgentes
            $stmt = $this->connection->prepare("
                SELECT 
                    lc.*,
                    p.name as produto_nome,
                    p.sku,
                    p.price,
                    p.loja
                FROM lista_compras lc
                JOIN produtos p ON lc.produto_id = p.id
                WHERE lc.status = 'pendente' AND lc.prioridade IN ('urgente', 'alta')
                ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
                LIMIT 10
            ");
            $stmt->execute();
            $compras_urgentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $estatisticas = [];
            $movimentacoes = [];
            $produtos_criticos = [];
            $compras_urgentes = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('relatorios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-file-pdf me-2"></i>Relatórios e Análises</h1>
                    <div>
                        <a class="btn btn-primary me-2" href="/admin/estoque/relatorios/financeiro">
                            <i class="fas fa-chart-line me-1"></i>Financeiro (Completo)
                        </a>
                        <button type="button" class="btn btn-danger me-2" onclick="gerarPDFCompleto()">
                            <i class="fas fa-file-pdf me-1"></i>Relatório Completo
                        </button>
                        <button type="button" class="btn btn-warning me-2" onclick="gerarPDFEstoque()">
                            <i class="fas fa-warehouse me-1"></i>Estoque
                        </button>
                        <button type="button" class="btn btn-info me-2" onclick="gerarPDFCompras()">
                            <i class="fas fa-shopping-basket me-1"></i>Compras
                        </button>
                        <a class="btn btn-success" href="/admin/estoque/relatorios/movimentacao">
                            <i class="fas fa-exchange-alt me-1"></i>Movimentação
                        </a>
                    </div>
                </div>';

                // Cards de Estatísticas
                echo '<!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Produtos</h5>
                                <h3>' . number_format($estatisticas['total_produtos']) . '</h3>
                                <small>Cadastrados no sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Com Estoque</h5>
                                <h3>' . number_format($estatisticas['produtos_com_estoque']) . '</h3>
                                <small>Com itens em estoque</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Precisa Comprar</h5>
                                <h3>' . number_format($estatisticas['produtos_comprar']) . '</h3>
                                <small>Na lista de compras</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Vencendo</h5>
                                <h3>' . number_format($estatisticas['produtos_vencendo']) . '</h3>
                                <small>Próximos 30 dias</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros para Relatórios -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-filter me-2"></i>Filtros para Relatórios</h6>
                    </div>
                    <div class="card-body">
                        <form id="formRelatorio">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de Relatório</label>
                                    <select class="form-select" id="tipo_relatorio" name="tipo_relatorio">
                                        <option value="completo">Relatório Completo</option>
                                        <option value="estoque">Apenas Estoque</option>
                                        <option value="compras">Apenas Compras</option>
                                        <option value="movimentacao">Apenas Movimentação</option>
                                        <option value="validades">Produtos por Validade</option>
                                        <option value="criticos">Produtos Críticos</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Inicial</label>
                                    <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Final</label>
                                    <input type="date" class="form-control" id="data_final" name="data_final">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Loja</label>
                                    <select class="form-select" id="loja_filtro" name="loja_filtro">
                                        <option value="">Todas</option>
                                        <option value="sams">Sams</option>
                                        <option value="costco">Costco</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-primary" onclick="gerarRelatorioPersonalizado()">
                                        <i class="fas fa-file-pdf me-1"></i>Gerar Relatório Personalizado
                                    </button>
                                    <button type="button" class="btn btn-secondary ms-2" onclick="limparFiltros()">
                                        <i class="fas fa-times me-1"></i>Limpar Filtros
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Produtos Críticos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Produtos Críticos</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Estoque</th>
                                                <th>Mínimo</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($produtos_criticos as $produto) {
                                            $status = $produto['quantidade_estoque'] == 0 ? 'Esgotado' : 'Crítico';
                                            echo '<tr>
                                                <td>
                                                    <strong>' . htmlspecialchars($produto['produto_nome']) . '</strong>
                                                    <br><small class="text-muted">' . htmlspecialchars($produto['sku']) . '</small>
                                                </td>
                                                <td><span class="badge bg-danger">' . $produto['quantidade_estoque'] . '</span></td>
                                                <td>' . $produto['estoque_minimo'] . '</td>
                                                <td><span class="badge bg-danger">' . $status . '</span></td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6><i class="fas fa-shopping-basket me-2"></i>Compras Urgentes</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Faltante</th>
                                                <th>Prioridade</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($compras_urgentes as $compra) {
                                            $prioridade_class = $compra['prioridade'] == 'urgente' ? 'danger' : 'warning';
                                            $total = $compra['quantidade_faltante'] * $compra['price'];
                                            echo '<tr>
                                                <td>
                                                    <strong>' . htmlspecialchars($compra['produto_nome']) . '</strong>
                                                    <br><small class="text-muted">' . htmlspecialchars($compra['sku']) . '</small>
                                                </td>
                                                <td><span class="badge bg-danger">' . $compra['quantidade_faltante'] . '</span></td>
                                                <td><span class="badge bg-' . $prioridade_class . '">' . ucfirst($compra['prioridade']) . '</span></td>
                                                <td>R$ ' . number_format($total, 2, ',', '.') . '</td>
                                            </tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Movimentações Recentes -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-history me-2"></i>Movimentações Recentes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Tipo</th>
                                        <th>Quantidade</th>
                                        <th>Anterior</th>
                                        <th>Nova</th>
                                        <th>Motivo</th>
                                        <th>Usuário</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($movimentacoes as $mov) {
                                    $tipo_class = $mov['tipo_movimentacao'] == 'entrada' ? 'success' : 
                                                 ($mov['tipo_movimentacao'] == 'saida' ? 'danger' : 
                                                 ($mov['tipo_movimentacao'] == 'ajuste' ? 'warning' : 'secondary'));
                                    echo '<tr>
                                        <td>' . date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) . '</td>
                                        <td>
                                            <strong>' . htmlspecialchars($mov['produto_nome']) . '</strong>
                                            <br><small class="text-muted">' . htmlspecialchars($mov['sku']) . '</small>
                                        </td>
                                        <td><span class="badge bg-' . $tipo_class . '">' . ucfirst($mov['tipo_movimentacao']) . '</span></td>
                                        <td>' . $mov['quantidade'] . '</td>
                                        <td>' . $mov['quantidade_anterior'] . '</td>
                                        <td>' . $mov['quantidade_nova'] . '</td>
                                        <td>' . htmlspecialchars($mov['motivo']) . '</td>
                                        <td>' . htmlspecialchars($mov['usuario_nome'] ?? 'Sistema') . '</td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function gerarPDFCompleto() {
            window.open("/admin/estoque/relatorio-pdf?tipo=completo", "_blank");
        }

        function gerarPDFEstoque() {
            window.open("/admin/estoque/relatorio-pdf?tipo=estoque", "_blank");
        }

        function gerarPDFCompras() {
            window.open("/admin/estoque/compras/pdf", "_blank");
        }

        function gerarPDFMovimentacao() {
            window.open("/admin/estoque/relatorio-pdf?tipo=movimentacao", "_blank");
        }

        function gerarRelatorioPersonalizado() {
            const tipo = document.getElementById("tipo_relatorio").value;
            const dataInicial = document.getElementById("data_inicial").value;
            const dataFinal = document.getElementById("data_final").value;
            const loja = document.getElementById("loja_filtro").value;
            
            let url = "/admin/estoque/relatorio-pdf?tipo=" + tipo;
            if (dataInicial) url += "&data_inicial=" + dataInicial;
            if (dataFinal) url += "&data_final=" + dataFinal;
            if (loja) url += "&loja=" + loja;
            
            window.open(url, "_blank");
        }

        function limparFiltros() {
            document.getElementById("formRelatorio").reset();
        }

        // Configurar datas padrão
        document.addEventListener("DOMContentLoaded", function() {
            const hoje = new Date();
            const trintaDiasAtras = new Date(hoje.getTime() - 30 * 24 * 60 * 60 * 1000);
            
            document.getElementById("data_inicial").value = trintaDiasAtras.toISOString().split("T")[0];
            document.getElementById("data_final").value = hoje.toISOString().split("T")[0];
        });
    </script>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function gerarPDF($request) {
        try {
            $tipo = $request->getParam('tipo', 'completo');
            $data_inicial = $request->getParam('data_inicial');
            $data_final = $request->getParam('data_final');
            $loja = $request->getParam('loja');

            // Buscar dados conforme o tipo
            $dados = $this->buscarDadosRelatorio($tipo, $data_inicial, $data_final, $loja);

            // Gerar PDF
            $this->gerarArquivoPDF($dados, $tipo);

        } catch (\Exception $e) {
            echo "Erro ao gerar PDF: " . $e->getMessage();
        }
    }

    private function buscarDadosRelatorio($tipo, $data_inicial, $data_final, $loja) {
        $where_clauses = [];
        $params = [];

        if ($data_inicial) {
            $where_clauses[] = "DATE(em.data_movimentacao) >= :data_inicial";
            $params[':data_inicial'] = $data_inicial;
        }
        if ($data_final) {
            $where_clauses[] = "DATE(em.data_movimentacao) <= :data_final";
            $params[':data_final'] = $data_final;
        }
        if ($loja) {
            $where_clauses[] = "p.loja = :loja";
            $params[':loja'] = $loja;
        }

        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

        switch ($tipo) {
            case 'estoque':
                $sql = "
                    SELECT 
                        p.id,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        p.price,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        CASE 
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                            ELSE 'normal'
                        END as status_estoque
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, p.price, ec.estoque_minimo, ec.estoque_ideal
                    ORDER BY status_estoque, p.name
                ";
                break;

            case 'compras':
                $sql = "
                    SELECT 
                        lc.*,
                        p.name as produto_nome,
                        p.sku,
                        p.price,
                        p.loja,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque_atual
                    FROM lista_compras lc
                    JOIN produtos p ON lc.produto_id = p.id
                    LEFT JOIN estoque_interno e ON lc.produto_id = e.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY lc.id, p.name, p.sku, p.price, p.loja
                    ORDER BY lc.prioridade DESC, lc.data_solicitacao ASC
                ";
                break;

            case 'movimentacao':
                $sql = "
                    SELECT 
                        em.*,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        u.name as usuario_nome
                    FROM estoque_movimentacao em
                    JOIN produtos p ON em.produto_id = p.id
                    LEFT JOIN usuarios u ON em.usuario_id = u.id
                    $where_sql
                    ORDER BY em.data_movimentacao DESC
                ";
                break;

            case 'validades':
                $sql = "
                    SELECT 
                        e.*,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        DATEDIFF(e.data_validade, CURDATE()) as dias_para_vencer
                    FROM estoque_interno e
                    JOIN produtos p ON e.produto_id = p.id
                    WHERE e.is_alimenticio = 1 
                    AND e.data_validade IS NOT NULL
                    " . ($loja ? "AND p.loja = :loja" : "") . "
                    ORDER BY e.data_validade ASC
                ";
                break;

            case 'criticos':
                $sql = "
                    SELECT 
                        p.id,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        p.price,
                        COALESCE(SUM(e.quantidade), 0) as quantidade_estoque,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        lc.quantidade_faltante
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    LEFT JOIN lista_compras lc ON p.id = lc.produto_id AND lc.status = 'pendente'
                    WHERE p.active = 1
                    " . ($loja ? "AND p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, p.price, ec.estoque_minimo, ec.estoque_ideal, lc.quantidade_faltante
                    HAVING quantidade_estoque <= COALESCE(ec.estoque_minimo, 5)
                    ORDER BY quantidade_estoque ASC
                ";
                break;

            default: // completo
                $sql = "
                    SELECT 
                        'estoque' as tipo,
                        p.name as produto_nome,
                        p.sku,
                        p.loja,
                        COALESCE(SUM(e.quantidade), 0) as quantidade,
                        ec.estoque_minimo,
                        ec.estoque_ideal,
                        CASE 
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                            WHEN COALESCE(SUM(e.quantidade), 0) <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                            ELSE 'normal'
                        END as status
                    FROM produtos p
                    LEFT JOIN estoque_interno e ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON p.id = ec.produto_id
                    " . ($loja ? "WHERE p.loja = :loja" : "") . "
                    GROUP BY p.id, p.name, p.sku, p.loja, ec.estoque_minimo, ec.estoque_ideal
                ";
        }

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function gerarArquivoPDF($dados, $tipo) {
        // Configurações do PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="relatorio_' . $tipo . '_' . date('Y-m-d') . '.pdf"');

        // Iniciar buffer de saída
        ob_start();

        // HTML do PDF
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de ' . ucfirst($tipo) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .critico { background-color: #ffebee; }
        .baixo { background-color: #fff3e0; }
        .normal { background-color: #e8f5e8; }
        .total { font-weight: bold; background-color: #f5f5f5; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de ' . ucfirst($tipo) . '</h1>
        <p>Gerado em: ' . date('d/m/Y H:i') . '</p>
    </div>

    <div class="content">
        <table>
            <thead>
                <tr>';

        // Cabeçalho da tabela conforme o tipo
        switch ($tipo) {
            case 'estoque':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Estoque</th><th>Mínimo</th><th>Ideal</th><th>Status</th>';
                break;
            case 'compras':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Necessário</th><th>Em Estoque</th><th>Faltante</th><th>Prioridade</th><th>Status</th>';
                break;
            case 'movimentacao':
                echo '<th>Data</th><th>Produto</th><th>SKU</th><th>Tipo</th><th>Quantidade</th><th>Anterior</th><th>Nova</th><th>Motivo</th>';
                break;
            case 'validades':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Quantidade</th><th>Data Validade</th><th>Dias para Vencer</th>';
                break;
            case 'criticos':
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Estoque</th><th>Mínimo</th><th>Faltante</th><th>Preço Unit.</th>';
                break;
            default:
                echo '<th>Produto</th><th>SKU</th><th>Loja</th><th>Quantidade</th><th>Status</th>';
        }

        echo '</tr>
            </thead>
            <tbody>';

        // Dados da tabela
        foreach ($dados as $item) {
            $row_class = '';
            if ($tipo == 'estoque' || $tipo == 'completo') {
                $row_class = $item['status'] == 'crítico' ? 'critico' : 
                           ($item['status'] == 'baixo' ? 'baixo' : 'normal');
            }

            echo '<tr class="' . $row_class . '">';

            switch ($tipo) {
                case 'estoque':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . $item['estoque_minimo'] . '</td>';
                    echo '<td>' . $item['estoque_ideal'] . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
                    break;

                case 'compras':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade_necessaria'] . '</td>';
                    echo '<td>' . $item['quantidade_em_estoque_atual'] . '</td>';
                    echo '<td>' . $item['quantidade_faltante'] . '</td>';
                    echo '<td>' . ucfirst($item['prioridade']) . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
                    break;

                case 'movimentacao':
                    echo '<td>' . date('d/m/Y H:i', strtotime($item['data_movimentacao'])) . '</td>';
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['tipo_movimentacao']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . $item['quantidade_anterior'] . '</td>';
                    echo '<td>' . $item['quantidade_nova'] . '</td>';
                    echo '<td>' . htmlspecialchars($item['motivo']) . '</td>';
                    break;

                case 'validades':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . date('d/m/Y', strtotime($item['data_validade'])) . '</td>';
                    echo '<td>' . $item['dias_para_vencer'] . ' dias</td>';
                    break;

                case 'criticos':
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade_estoque'] . '</td>';
                    echo '<td>' . $item['estoque_minimo'] . '</td>';
                    echo '<td>' . $item['quantidade_faltante'] . '</td>';
                    echo '<td>R$ ' . number_format($item['price'], 2, ',', '.') . '</td>';
                    break;

                default:
                    echo '<td>' . htmlspecialchars($item['produto_nome']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['sku']) . '</td>';
                    echo '<td>' . ucfirst($item['loja']) . '</td>';
                    echo '<td>' . $item['quantidade'] . '</td>';
                    echo '<td>' . ucfirst($item['status']) . '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>
        </table>
    </div>

    <div class="footer">
        <p>Relatório gerado pelo Sistema Braziliana Shop Estoque - Página ' . date('d/m/Y H:i') . '</p>
    </div>
</body>
</html>';

        // Converter HTML para PDF usando a biblioteca DOMPDF (se disponível)
        // Por enquanto, vamos apenas exibir o HTML que pode ser salvo como PDF pelo navegador
        $html = ob_get_clean();
        
        // Se tiver a biblioteca mPDF, usaríamos:
        // require_once 'vendor/autoload.php';
        // $mpdf = new \Mpdf\Mpdf();
        // $mpdf->WriteHTML($html);
        // $mpdf->Output();
        
        // Por enquanto, apenas exibir o HTML
        echo $html;
    }
}
