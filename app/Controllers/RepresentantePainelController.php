<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class RepresentantePainelController extends Controller {

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

    private function getProdutosDoRepresentante(\PDO $pdo, int $repId): array {
        if ($repId <= 0) {
            return [];
        }
        if (!$this->tableExists($pdo, 'produtos')) {
            return [];
        }

        $cols = $this->getColumns($pdo, 'produtos');
        if (!in_array('representante_id', $cols, true)) {
            return [];
        }

        $colActive = in_array('active', $cols, true) ? 'active' : (in_array('ativo', $cols, true) ? 'ativo' : null);
        $colNome = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'id');
        $colSku = in_array('sku', $cols, true) ? 'sku' : null;
        $colFoto = in_array('foto_principal', $cols, true) ? 'foto_principal' : null;

        $select = 'id, ' . $colNome . ' AS nome';
        if ($colSku) $select .= ', ' . $colSku . ' AS sku';
        if ($colActive) $select .= ', ' . $colActive . ' AS active';
        if ($colFoto) $select .= ', ' . $colFoto . ' AS foto_principal';

        $st = $pdo->prepare('SELECT ' . $select . ' FROM produtos WHERE representante_id = ? ORDER BY id DESC LIMIT 50');
        $st->execute([$repId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
        if ($perfil !== 'representante') {
            $this->redirect('/minha-conta');
        }

        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $usuarioNome = (string) ($_SESSION['usuario_nome'] ?? '');

        $lojaUrl = '';
        $resumo = [
            'percentual' => 0.0,
            'rate' => 0.0,
            'totais' => [
                'USD' => ['venda' => 0.0, 'comissao' => 0.0],
                'BRL' => ['venda' => 0.0, 'comissao' => 0.0],
            ],
            'vendas_qtd' => 0,
        ];
        $erro = '';
        $produtos = [];

        try {
            $pdo = $this->getPdo();

            if ($this->tableExists($pdo, 'usuarios')) {
                $uCols = $this->getColumns($pdo, 'usuarios');
                $slugCol = $this->pickColumn($uCols, ['representante_slug']);
                if ($slugCol) {
                    $st = $pdo->prepare('SELECT ' . $slugCol . ' FROM usuarios WHERE id = ? LIMIT 1');
                    $st->execute([$repId]);
                    $slug = trim((string) ($st->fetchColumn() ?: ''));
                    if ($slug !== '') {
                        $lojaUrl = '/produtos/rep-' . $slug;
                    }
                }
            }

            $resumo['percentual'] = $this->getPercentualRepresentante($pdo, $repId);
            $resumo['rate'] = $this->getUsdBrlRate($pdo);

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
            $moedaCol = $this->pickColumn($pCols, ['moeda', 'currency']);

            $repIdCol = $this->pickColumn($prCols, ['representante_id']);
            $costCol = $this->pickColumn($prCols, ['cost_price', 'preco_custo', 'valor_custo']);

            if (!$colPedidoId || !$colProdutoId || !$colQtd || !$colPreco || !$pedidoStatusCol || !$repIdCol || !$costCol) {
                throw new \Exception('Colunas necessárias não encontradas para calcular comissão.');
            }

            $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);
            $selMoeda = $moedaCol ? ("p.{$moedaCol}") : "'USD'";

            $sql = "SELECT p.id AS pedido_id,\n"
                . "       {$selMoeda} AS moeda,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(i.{$colPreco},0)) AS venda_total,\n"
                . "       SUM(COALESCE(i.{$colQtd},0) * COALESCE(pr.{$costCol},0)) AS custo_total_usd\n"
                . "FROM {$itensTable} i\n"
                . "INNER JOIN pedidos p ON p.id = i.{$colPedidoId}\n"
                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                . $wherePaid
                . " AND pr.{$repIdCol} = :repId\n"
                . "GROUP BY p.id, moeda\n"
                . "ORDER BY p.id DESC\n"
                . "LIMIT 200";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':repId', $repId, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $resumo['vendas_qtd'] = count($rows);

            $percent = (float) ($resumo['percentual'] ?? 0);
            $rate = (float) ($resumo['rate'] ?? 0);
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
                $resumo['totais'][$moeda]['comissao'] += $com;
            }

            $produtos = $this->getProdutosDoRepresentante($pdo, $repId);

        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        ob_start();

        echo '<div class="container py-4">'
            . '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">'
            . '<div>'
            . '<div class="text-muted small">Meu Painel</div>'
            . '<h1 class="h3 mb-0">' . htmlspecialchars($usuarioNome !== '' ? $usuarioNome : 'Representante', ENT_QUOTES, 'UTF-8') . '</h1>'
            . '</div>'
            . '<div class="d-flex gap-2">'
            . '<a class="btn btn-outline-primary" href="/admin/representante/produtos"><i class="fas fa-box me-1"></i>Gerenciar produtos</a>'
            . '<a class="btn btn-primary" href="/admin/produtos/cadastro-representante"><i class="fas fa-plus me-1"></i>Adicionar produto</a>'
            . '</div>'
            . '</div>';

        if ($erro !== '') {
            echo '<div class="alert alert-danger">' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo '<div class="row g-3">'
            . '<div class="col-lg-4">'
            . '<div class="card h-100"><div class="card-body">'
            . '<div class="text-muted small mb-1">Minha Loja</div>';

        if ($lojaUrl !== '') {
            echo '<div class="fw-semibold mb-2">Página pública</div>'
                . '<a href="' . htmlspecialchars($lojaUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($lojaUrl, ENT_QUOTES, 'UTF-8') . '</a>'
                . '<div class="text-muted small mt-2">Compartilhe esse link para divulgar seus produtos.</div>';
        } else {
            echo '<div class="text-muted">Link da loja indisponível.</div>';
        }

        echo '</div></div>'
            . '</div>';

        $totUsd = $resumo['totais']['USD'] ?? ['venda' => 0, 'comissao' => 0];
        $totBrl = $resumo['totais']['BRL'] ?? ['venda' => 0, 'comissao' => 0];

        echo '<div class="col-lg-8">'
            . '<div class="row g-3">'
            . '<div class="col-md-4"><div class="card h-100"><div class="card-body">'
            . '<div class="text-muted small">Vendas (pagas)</div>'
            . '<div class="h4 mb-0">' . (int) ($resumo['vendas_qtd'] ?? 0) . '</div>'
            . '</div></div></div>'
            . '<div class="col-md-4"><div class="card h-100"><div class="card-body">'
            . '<div class="text-muted small">Comissão (USD)</div>'
            . '<div class="h4 mb-0">' . htmlspecialchars($this->formatMoney((float) ($totUsd['comissao'] ?? 0), 'USD'), ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></div></div>'
            . '<div class="col-md-4"><div class="card h-100"><div class="card-body">'
            . '<div class="text-muted small">Comissão (BRL)</div>'
            . '<div class="h4 mb-0">' . htmlspecialchars($this->formatMoney((float) ($totBrl['comissao'] ?? 0), 'BRL'), ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></div></div>'
            . '<div class="col-12"><div class="card"><div class="card-body">'
            . '<div class="row g-2">'
            . '<div class="col-md-6"><div class="text-muted small">Vendas (USD)</div><div class="fw-semibold">' . htmlspecialchars($this->formatMoney((float) ($totUsd['venda'] ?? 0), 'USD'), ENT_QUOTES, 'UTF-8') . '</div></div>'
            . '<div class="col-md-6"><div class="text-muted small">Vendas (BRL)</div><div class="fw-semibold">' . htmlspecialchars($this->formatMoney((float) ($totBrl['venda'] ?? 0), 'BRL'), ENT_QUOTES, 'UTF-8') . '</div></div>'
            . '</div>'
            . '<div class="text-muted small mt-3">Comissão é calculada por pedido pago, respeitando a moeda do pagamento.</div>'
            . '</div></div></div>'
            . '</div>'
            . '</div>'
            . '</div>';

        echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-2">'
            . '<h2 class="h5 mb-0">Meus Produtos</h2>'
            . '</div>';

        if (empty($produtos)) {
            echo '<div class="card"><div class="card-body text-muted">Nenhum produto cadastrado ainda.</div></div>';
        } else {
            echo '<div class="card"><div class="card-body">'
                . '<div class="table-responsive">'
                . '<table class="table table-sm align-middle mb-0">'
                . '<thead><tr><th>Produto</th><th>Status</th><th class="text-end">Ações</th></tr></thead><tbody>';
            foreach ($produtos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $nome = (string) ($p['nome'] ?? '');
                $sku = (string) ($p['sku'] ?? '');
                $ativo = (int) ($p['active'] ?? 0);
                $status = $ativo ? 'Publicado' : 'Despublicado';
                $badge = $ativo ? 'bg-success' : 'bg-secondary';

                $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
                $skuEsc = htmlspecialchars($sku, ENT_QUOTES, 'UTF-8');
                $urlEditar = '/admin/representante/produtos/editar/' . $pid;

                echo '<tr>'
                    . '<td>'
                    . '<div class="fw-semibold">' . $nomeEsc . '</div>'
                    . ($skuEsc !== '' ? ('<div class="text-muted small">SKU: ' . $skuEsc . '</div>') : '')
                    . '</td>'
                    . '<td><span class="badge ' . $badge . '">' . $status . '</span></td>'
                    . '<td class="text-end">'
                    . '<div class="d-inline-flex gap-2">'
                    . '<a class="btn btn-sm btn-outline-warning" href="' . htmlspecialchars($urlEditar, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-edit"></i></a>'
                    . '<form method="POST" action="/admin/representante/produtos/toggle-publicacao/' . $pid . '">' 
                    . '<button type="submit" class="btn btn-sm ' . ($ativo ? 'btn-outline-secondary' : 'btn-outline-success') . '">' 
                    . ($ativo ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>')
                    . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table></div></div></div>';
        }

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Meu Painel';
        include __DIR__ . '/../Views/layouts/main.php';
        exit;
    }
}
