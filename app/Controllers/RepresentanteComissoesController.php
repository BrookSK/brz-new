<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class RepresentanteComissoesController extends Controller {

    private function getPdo(): \PDO {
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
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

    private function getUsdBrlRate(\PDO $pdo): float {
        try {
            if (!$this->tableExists($pdo, 'configuracoes_sistema')) {
                return 5.5;
            }
            $stmt = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                try {
                    $stmt->execute([$k]);
                    $val = $stmt->fetchColumn();
                    if ($val !== false && $val !== null && trim((string) $val) !== '') {
                        $r = (float) str_replace(',', '.', trim((string) $val));
                        if ($r > 0) {
                            return $r;
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
        }
        return 5.5;
    }

    private function formatMoney(float $v, string $moeda): string {
        $sym = ($moeda === 'BRL') ? 'R$' : '$';
        return $sym . ' ' . number_format($v, 2, ',', '.');
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        $perfil = '';
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $repEmail = (string) ($_SESSION['usuario_email'] ?? '');

        $resumo = [
            'percentual' => 0.0,
            'totais' => [
                'USD' => ['venda' => 0.0, 'custo' => 0.0, 'liquido' => 0.0, 'comissao' => 0.0],
                'BRL' => ['venda' => 0.0, 'custo' => 0.0, 'liquido' => 0.0, 'comissao' => 0.0],
            ],
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

            $moedaCol = $this->pickColumn($pCols, ['moeda', 'currency']);
            $codigoCol = $this->pickColumn($pCols, ['numero_pedido', 'codigo_pedido', 'codigo', 'code']);
            $dataCol = $this->pickColumn($pCols, ['created_at', 'data_criacao', 'data_pedido', 'data']);

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

            $selCodigo = $codigoCol ? ("p.{$codigoCol}") : 'p.id';
            $selData = $dataCol ? ("p.{$dataCol}") : 'p.id';
            $selMoeda = $moedaCol ? ("p.{$moedaCol}") : "'USD'";

            $sql = "SELECT p.id AS pedido_id,\n"
                . "       {$selCodigo} AS codigo,\n"
                . "       {$selData} AS data,\n"
                . "       {$selMoeda} AS moeda,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(i.{$colPreco},0)) AS venda_total,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(pr.{$costCol},0)) AS custo_total_usd\n"
                . "FROM {$itensTable} i\n"
                . "INNER JOIN pedidos p ON p.id = i.{$colPedidoId}\n"
                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                . $wherePaid
                . $whereRep
                . "\nGROUP BY p.id, codigo, data, moeda\n"
                . "ORDER BY p.id DESC\n"
                . "LIMIT 200";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $percent = (float) ($resumo['percentual'] ?? 0);
            $rate = $this->getUsdBrlRate($pdo);
            foreach ($rows as $r) {
                $moeda = strtoupper(trim((string) ($r['moeda'] ?? 'USD')));
                if (!in_array($moeda, ['USD', 'BRL'], true)) {
                    $moeda = 'USD';
                }

                $venda = (float) ($r['venda_total'] ?? 0);
                $custoUsd = (float) ($r['custo_total_usd'] ?? 0);
                $custo = ($moeda === 'BRL') ? ($custoUsd * $rate) : $custoUsd;

                $liq = $venda - $custo;
                $com = $liq * ($percent / 100.0);

                $resumo['totais'][$moeda]['venda'] += $venda;
                $resumo['totais'][$moeda]['custo'] += $custo;
                $resumo['totais'][$moeda]['liquido'] += $liq;
                $resumo['totais'][$moeda]['comissao'] += $com;

                $resumo['linhas'][] = [
                    'pedido_id' => (int) ($r['pedido_id'] ?? 0),
                    'codigo' => (string) ($r['codigo'] ?? ''),
                    'data' => (string) ($r['data'] ?? ''),
                    'moeda' => $moeda,
                    'venda' => $venda,
                    'custo' => $custo,
                    'liquido' => $liq,
                    'comissao' => $com,
                ];
            }
        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();

        echo '<div class="pt-3">'
            . '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">'
            . '<h1 class="page-title">Comissões</h1>'
            . '<div class="d-flex gap-2">'
            . '<a href="/admin/representante/produtos" class="btn btn-outline-primary"><i class="fas fa-box"></i> Produtos</a>'
            . '<a href="/admin/produtos/cadastro-representante" class="btn btn-primary"><i class="fas fa-plus"></i> Novo produto</a>'
            . '</div>'
            . '</div>';

        if (!empty($erro)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $totUsd = $resumo['totais']['USD'] ?? ['venda' => 0, 'custo' => 0, 'liquido' => 0, 'comissao' => 0];
        $totBrl = $resumo['totais']['BRL'] ?? ['venda' => 0, 'custo' => 0, 'liquido' => 0, 'comissao' => 0];

        echo '<div class="row g-3 mb-3">'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Percentual</div><div class="h4 mb-0">' . number_format((float) ($resumo['percentual'] ?? 0), 2, ',', '.') . '%</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Comissão (USD)</div><div class="h4 mb-0">' . htmlspecialchars($this->formatMoney((float) ($totUsd['comissao'] ?? 0), 'USD'), ENT_QUOTES, 'UTF-8') . '</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Comissão (BRL)</div><div class="h4 mb-0">' . htmlspecialchars($this->formatMoney((float) ($totBrl['comissao'] ?? 0), 'BRL'), ENT_QUOTES, 'UTF-8') . '</div></div></div></div>'
            . '<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Taxa USD→BRL</div><div class="h4 mb-0">' . number_format((float) ($rate ?? 0), 4, ',', '.') . '</div></div></div></div>'
            . '</div>';

        if (empty($resumo['linhas'])) {
            echo '<div class="alert alert-info">Nenhuma venda paga encontrada para seus produtos (ou percentual ainda não configurado).</div>';
        } else {
            echo '<div class="card"><div class="card-body">'
                . '<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">'
                . '<thead><tr><th>Pedido</th><th>Data</th><th>Moeda</th><th>Venda</th><th>Custo</th><th>Líquido</th><th>Comissão</th></tr></thead><tbody>';
            foreach ($resumo['linhas'] as $l) {
                $m = (string) ($l['moeda'] ?? 'USD');
                $codigoPedido = htmlspecialchars((string) ($l['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
                $pedidoId = (int) ($l['pedido_id'] ?? 0);
                $pedidoCell = $perfil === 'representante'
                    ? $codigoPedido
                    : ('<a href="/admin/pedidos/detalhes/' . $pedidoId . '" target="_blank">' . $codigoPedido . '</a>');
                echo '<tr>'
                    . '<td>' . $pedidoCell . '</td>'
                    . '<td>' . htmlspecialchars((string) ($l['data'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($this->formatMoney((float) ($l['venda'] ?? 0), $m), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($this->formatMoney((float) ($l['custo'] ?? 0), $m), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($this->formatMoney((float) ($l['liquido'] ?? 0), $m), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="fw-semibold">' . htmlspecialchars($this->formatMoney((float) ($l['comissao'] ?? 0), $m), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table></div></div></div>';
        }

        echo '<div class="small text-muted mt-3">Regra: comissão = (venda - custo) * percentual. Considera apenas pedidos pagos.</div>';
        echo '</div>';

        $content = ob_get_clean();
        $sidebarActive = ($perfil === 'representante') ? 'rep_comissoes' : '';
        $title = 'Comissões - Representante';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }
}
