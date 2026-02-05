<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class RepresentanteComissoesController extends Controller {

    private function getPdo(): \PDO {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getColumns(\PDO $pdo, string $table): array {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function detectItensTable(\PDO $pdo): ?string {
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function normalizePaidWhere(?string $pedidoStatusCol): string {
        if (!$pedidoStatusCol) {
            return '';
        }
        // Considera pago quando status/payment_status indica aprovação
        return " WHERE LOWER(COALESCE(p.{$pedidoStatusCol},'')) IN ('pago','paid','approved','aprovado','confirmed','received','succeeded','success') ";
    }

    private function getPercentualRepresentante(\PDO $pdo, int $repId): float {
        if ($repId <= 0) return 0.0;
        if (!$this->tableExists($pdo, 'representante_comissoes')) return 0.0;
        try {
            $stmt = $pdo->prepare('SELECT percentual FROM representante_comissoes WHERE representante_id = ? AND ativo = 1 LIMIT 1');
            $stmt->execute([$repId]);
            return (float) ($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $repEmail = (string) ($_SESSION['usuario_email'] ?? '');

        $resumo = [
            'percentual' => 0.0,
            'total_venda' => 0.0,
            'total_custo' => 0.0,
            'total_liquido' => 0.0,
            'total_comissao' => 0.0,
            'linhas' => [],
        ];

        try {
            $pdo = $this->getPdo();

            $resumo['percentual'] = $this->getPercentualRepresentante($pdo, $repId);

            $itensTable = $this->detectItensTable($pdo);
            if (!$itensTable || !$this->tableExists($pdo, 'pedidos') || !$this->tableExists($pdo, 'produtos')) {
                throw new \Exception('Schema incompleto para calcular comissões.');
            }

            $iCols = $this->getColumns($pdo, $itensTable);
            $pCols = $this->getColumns($pdo, 'pedidos');
            $prCols = $this->getColumns($pdo, 'produtos');

            $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
            $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
            $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
            $colPreco = $this->pickColumn($iCols, ['preco_unitario', 'price', 'valor_unitario']);

            $pedidoStatusCol = $this->pickColumn($pCols, ['payment_status', 'status', 'status_pagamento', 'pagamento_status']);

            $repIdCol = $this->pickColumn($prCols, ['representante_id']);
            $repEmailCol = $this->pickColumn($prCols, ['representante_email']);
            $costCol = $this->pickColumn($prCols, ['cost_price', 'preco_custo', 'valor_custo']);

            if (!$colPedidoId || !$colProdutoId || !$colQtd || !$colPreco || !$pedidoStatusCol || (!$repIdCol && !$repEmailCol) || !$costCol) {
                throw new \Exception('Colunas necessárias não encontradas para calcular comissão.');
            }

            $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);

            $whereRep = '';
            $params = [];
            if ($repIdCol) {
                $whereRep = " AND pr.{$repIdCol} = :repId";
                $params[':repId'] = $repId;
            } else {
                $whereRep = " AND pr.{$repEmailCol} = :repEmail";
                $params[':repEmail'] = $repEmail;
            }

            // soma por pedido/produto
            $sql = "SELECT p.id AS pedido_id,\n"
                . "       COALESCE(p.numero_pedido, p.codigo_pedido, p.id) AS codigo,\n"
                . "       COALESCE(p.created_at, p.data_criacao, p.data_pedido) AS data,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(i.{$colPreco},0)) AS venda_total,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(pr.{$costCol},0)) AS custo_total\n"
                . "FROM {$itensTable} i\n"
                . "INNER JOIN pedidos p ON p.id = i.{$colPedidoId}\n"
                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                . $wherePaid
                . $whereRep
                . "\nGROUP BY p.id, codigo, data\n"
                . "ORDER BY p.id DESC\n"
                . "LIMIT 200";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $percent = (float) ($resumo['percentual'] ?? 0);
            foreach ($rows as $r) {
                $venda = (float) ($r['venda_total'] ?? 0);
                $custo = (float) ($r['custo_total'] ?? 0);
                $liq = $venda - $custo;
                $com = $liq * ($percent / 100.0);

                $resumo['total_venda'] += $venda;
                $resumo['total_custo'] += $custo;
                $resumo['total_liquido'] += $liq;
                $resumo['total_comissao'] += $com;

                $resumo['linhas'][] = [
                    'pedido_id' => (int) ($r['pedido_id'] ?? 0),
                    'codigo' => (string) ($r['codigo'] ?? ''),
                    'data' => (string) ($r['data'] ?? ''),
                    'venda' => $venda,
                    'custo' => $custo,
                    'liquido' => $liq,
                    'comissao' => $com,
                ];
            }
        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comissões - Representante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="fas fa-percentage me-2"></i>Comissões</h3>
        <div class="d-flex gap-2">
            <a href="/admin/representante/produtos" class="btn btn-outline-primary"><i class="fas fa-box me-1"></i>Produtos</a>
            <a href="/admin/produtos/cadastro-representante" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Novo produto</a>
        </div>
    </div>';

        if (!empty($erro)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo '<div class="row g-3 mb-3">'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Percentual</div><div class="h4 mb-0">' . number_format((float) ($resumo['percentual'] ?? 0), 2, ',', '.') . '%</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Total venda</div><div class="h4 mb-0">$ ' . number_format((float) ($resumo['total_venda'] ?? 0), 2, ',', '.') . '</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Total custo</div><div class="h4 mb-0">$ ' . number_format((float) ($resumo['total_custo'] ?? 0), 2, ',', '.') . '</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Comissão estimada</div><div class="h4 mb-0">$ ' . number_format((float) ($resumo['total_comissao'] ?? 0), 2, ',', '.') . '</div></div></div></div>'
            . '</div>';

        if (empty($resumo['linhas'])) {
            echo '<div class="alert alert-info">Nenhuma venda paga encontrada para seus produtos (ou percentual ainda não configurado).</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
                . '<thead><tr><th>Pedido</th><th>Data</th><th>Venda</th><th>Custo</th><th>Líquido</th><th>Comissão</th></tr></thead><tbody>';
            foreach ($resumo['linhas'] as $l) {
                echo '<tr>'
                    . '<td><a href="/admin/pedidos/detalhes/' . (int) ($l['pedido_id'] ?? 0) . '" target="_blank">' . htmlspecialchars((string) ($l['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</a></td>'
                    . '<td>' . htmlspecialchars((string) ($l['data'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>$ ' . number_format((float) ($l['venda'] ?? 0), 2, ',', '.') . '</td>'
                    . '<td>$ ' . number_format((float) ($l['custo'] ?? 0), 2, ',', '.') . '</td>'
                    . '<td>$ ' . number_format((float) ($l['liquido'] ?? 0), 2, ',', '.') . '</td>'
                    . '<td class="fw-semibold">$ ' . number_format((float) ($l['comissao'] ?? 0), 2, ',', '.') . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '<div class="small text-muted mt-3">Regra: comissão = (venda - custo) * percentual. Considera apenas pedidos pagos.</div>';

        echo '</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
}
