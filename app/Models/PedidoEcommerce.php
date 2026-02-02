<?php
namespace App\Models;

use App\Models\Carrinho;
use App\Models\Produto;
use App\Services\NotificationService;

class PedidoEcommerce extends Model {
    protected $table = 'pedidos';

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

    private function consumirEstoqueInternoEGerarCompras(int $pedidoId, int $usuarioId, array $itens): void {
        // Este método é best-effort: se tabelas não existirem, não interrompe a criação do pedido
        try {
            if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_movimentacao') || !$this->tableExists('lista_compras')) {
                return;
            }

            $temLojaEmProdutos = $this->columnExists('produtos', 'loja');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $temPedidoEmMov = $this->columnExists('estoque_movimentacao', 'pedido_id');
            $temUsuarioLoginEmMov = $this->columnExists('estoque_movimentacao', 'usuario_login');
            $temLojaEmLista = $this->columnExists('lista_compras', 'loja');
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');

            $usuarioLogin = null;
            try {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                if (!empty($_SESSION['usuario_email'])) {
                    $usuarioLogin = (string) $_SESSION['usuario_email'];
                }
            } catch (\Exception $e) {
            }

            // Prepared statements
            $stmtLocs = $this->connection->prepare('
                SELECT id, quantidade, galpao, prateleira
                FROM estoque_interno
                WHERE produto_id = :produto_id AND quantidade > 0
                ORDER BY
                    CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC,
                    data_compra ASC,
                    id ASC
            ');
            $stmtUpdStock = $this->connection->prepare('UPDATE estoque_interno SET quantidade = :quantidade WHERE id = :id LIMIT 1');

            $sqlMovCols = 'produto_id, tipo_movimentacao, quantidade, quantidade_anterior, quantidade_nova, motivo, usuario_id';
            $sqlMovVals = ':produto_id, :tipo_movimentacao, :quantidade, :quantidade_anterior, :quantidade_nova, :motivo, :usuario_id';
            if ($temPedidoEmMov) {
                $sqlMovCols .= ', pedido_id';
                $sqlMovVals .= ', :pedido_id';
            }
            if ($temUsuarioLoginEmMov) {
                $sqlMovCols .= ', usuario_login';
                $sqlMovVals .= ', :usuario_login';
            }
            $stmtMov = $this->connection->prepare('INSERT INTO estoque_movimentacao (' . $sqlMovCols . ') VALUES (' . $sqlMovVals . ')');

            $sqlListaSelect = 'SELECT id, quantidade_necessaria, quantidade_faltante FROM lista_compras WHERE produto_id = :produto_id AND status = \'pendente\'';
            if ($temLojaIdEmLista) {
                $sqlListaSelect .= ' AND COALESCE(loja_id, 0) = :loja_id';
            } elseif ($temLojaEmLista) {
                $sqlListaSelect .= ' AND COALESCE(loja, \'\') = :loja';
            }
            $sqlListaSelect .= ' LIMIT 1';
            $stmtListaGet = $this->connection->prepare($sqlListaSelect);

            $stmtListaUpd = null;
            $stmtListaIns = null;
            // update
            $sqlUpd = 'UPDATE lista_compras SET quantidade_necessaria = :quantidade_necessaria, quantidade_faltante = :quantidade_faltante';
            if ($temPedidoEmLista) {
                $sqlUpd .= ', pedido_id = :pedido_id';
            }
            $sqlUpd .= ' WHERE id = :id LIMIT 1';
            $stmtListaUpd = $this->connection->prepare($sqlUpd);
            // insert
            $cols = ['produto_id'];
            $vals = [':produto_id'];
            if ($temLojaIdEmLista) {
                $cols[] = 'loja_id';
                $vals[] = ':loja_id';
            } elseif ($temLojaEmLista) {
                $cols[] = 'loja';
                $vals[] = ':loja';
            }
            if ($temPedidoEmLista) {
                $cols[] = 'pedido_id';
                $vals[] = ':pedido_id';
            }
            $cols[] = 'quantidade_necessaria';
            $vals[] = ':quantidade_necessaria';
            $cols[] = 'quantidade_faltante';
            $vals[] = ':quantidade_faltante';
            $cols[] = 'prioridade';
            $vals[] = ':prioridade';
            $cols[] = 'status';
            $vals[] = '\'pendente\'';
            $cols[] = 'data_solicitacao';
            $vals[] = 'CURDATE()';
            $stmtListaIns = $this->connection->prepare('INSERT INTO lista_compras (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');

            $stmtProdutoLoja = null;
            if ($temLojaEmProdutos || $temLojaIdEmProdutos) {
                $selectParts = [];
                if ($temLojaIdEmProdutos) {
                    $selectParts[] = 'COALESCE(loja_id, 0) as loja_id';
                } else {
                    $selectParts[] = '0 as loja_id';
                }
                if ($temLojaEmProdutos) {
                    $selectParts[] = 'COALESCE(loja, \'\') as loja';
                } else {
                    $selectParts[] = '\'\' as loja';
                }
                $stmtProdutoLoja = $this->connection->prepare('SELECT ' . implode(', ', $selectParts) . ' FROM produtos WHERE id = :id LIMIT 1');
            }

            foreach ($itens as $item) {
                $produtoId = (int) ($item['produto_id'] ?? 0);
                $qtdPedido = (int) ($item['quantidade'] ?? 0);
                if ($produtoId <= 0 || $qtdPedido <= 0) {
                    continue;
                }

                $loja = '';
                $lojaId = 0;
                if ($stmtProdutoLoja) {
                    $stmtProdutoLoja->execute([':id' => $produtoId]);
                    $rowLoja = $stmtProdutoLoja->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($rowLoja)) {
                        $lojaId = (int) ($rowLoja['loja_id'] ?? 0);
                        $loja = (string) ($rowLoja['loja'] ?? '');
                    }
                }

                $restante = $qtdPedido;

                // Consumir estoque interno
                $stmtLocs->execute([':produto_id' => $produtoId]);
                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($locs as $loc) {
                    if ($restante <= 0) {
                        break;
                    }

                    $estoqueId = (int) ($loc['id'] ?? 0);
                    $qtdDisponivel = (int) ($loc['quantidade'] ?? 0);
                    if ($estoqueId <= 0 || $qtdDisponivel <= 0) {
                        continue;
                    }

                    $tirar = min($qtdDisponivel, $restante);
                    $novo = $qtdDisponivel - $tirar;
                    $stmtUpdStock->execute([':quantidade' => $novo, ':id' => $estoqueId]);

                    $gal = trim((string) ($loc['galpao'] ?? ''));
                    $pra = trim((string) ($loc['prateleira'] ?? ''));
                    $locFull = $gal;
                    if ($gal !== '' && $pra !== '') {
                        $locFull .= ' - ' . $pra;
                    } elseif ($pra !== '') {
                        $locFull = $pra;
                    }

                    $motivo = 'Saída para pedido #' . $pedidoId;
                    if ($locFull !== '') {
                        $motivo .= ' (' . $locFull . ')';
                    }

                    $params = [
                        ':produto_id' => $produtoId,
                        ':tipo_movimentacao' => 'saida',
                        ':quantidade' => $tirar,
                        ':quantidade_anterior' => $qtdDisponivel,
                        ':quantidade_nova' => $novo,
                        ':motivo' => $motivo,
                        ':usuario_id' => $usuarioId,
                    ];
                    if ($temPedidoEmMov) {
                        $params[':pedido_id'] = $pedidoId;
                    }
                    if ($temUsuarioLoginEmMov) {
                        $params[':usuario_login'] = $usuarioLogin;
                    }
                    $stmtMov->execute($params);

                    $restante -= $tirar;
                }

                // Gerar/atualizar lista de compras para faltante
                if ($restante > 0) {
                    $paramsGet = [':produto_id' => $produtoId];
                    if ($temLojaIdEmLista) {
                        $paramsGet[':loja_id'] = $lojaId;
                    } elseif ($temLojaEmLista) {
                        $paramsGet[':loja'] = $loja;
                    }
                    $stmtListaGet->execute($paramsGet);
                    $row = $stmtListaGet->fetch(\PDO::FETCH_ASSOC);

                    if ($row && isset($row['id'])) {
                        $id = (int) $row['id'];
                        $qn = (int) ($row['quantidade_necessaria'] ?? 0);
                        $qf = (int) ($row['quantidade_faltante'] ?? 0);
                        $qn += $qtdPedido;
                        $qf += $restante;

                        $paramsUpd = [
                            ':quantidade_necessaria' => $qn,
                            ':quantidade_faltante' => $qf,
                            ':id' => $id,
                        ];
                        if ($temPedidoEmLista) {
                            $paramsUpd[':pedido_id'] = $pedidoId;
                        }
                        $stmtListaUpd->execute($paramsUpd);
                    } else {
                        $paramsIns = [
                            ':produto_id' => $produtoId,
                            ':quantidade_necessaria' => $qtdPedido,
                            ':quantidade_faltante' => $restante,
                            ':prioridade' => 'alta',
                        ];
                        if ($temLojaIdEmLista) {
                            $paramsIns[':loja_id'] = $lojaId;
                        } elseif ($temLojaEmLista) {
                            $paramsIns[':loja'] = $loja;
                        }
                        if ($temPedidoEmLista) {
                            $paramsIns[':pedido_id'] = $pedidoId;
                        }
                        $stmtListaIns->execute($paramsIns);
                    }
                }
            }
        } catch (\Exception $e) {
            // Não interromper fluxo do pedido; registrar para diagnóstico
            error_log('Erro ao consumir estoque interno/gerar lista de compras: ' . $e->getMessage());
        }
    }

    public function getPedidos($usuarioId = null, $limite = 10, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($usuarioId)) {
            $where[] = 'p.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $usuarioId;
        }

        $itensTable = null;
        $itensCols = [];
        $colPedidoId = null;
        $colQtd = null;
        try {
            $st = $this->connection->query("SHOW TABLES LIKE 'pedido_itens'");
            if ($st && $st->fetchColumn()) {
                $itensTable = 'pedido_itens';
            } else {
                $st = $this->connection->query("SHOW TABLES LIKE 'pedido_items'");
                if ($st && $st->fetchColumn()) {
                    $itensTable = 'pedido_items';
                }
            }
            if ($itensTable) {
                $stCols = $this->connection->query('DESCRIBE ' . $itensTable);
                $itensCols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($itensCols)) {
                    if (in_array('pedido_id', $itensCols, true)) {
                        $colPedidoId = 'pedido_id';
                    }
                    if (in_array('quantidade', $itensCols, true)) {
                        $colQtd = 'quantidade';
                    } elseif (in_array('qty', $itensCols, true)) {
                        $colQtd = 'qty';
                    }
                }
            }
        } catch (\Exception $e) {
            $itensTable = null;
        }

        $select = 'p.*';
        $join = '';
        $group = '';
        if ($itensTable && $colPedidoId) {
            if ($colQtd) {
                $select .= ', COALESCE(SUM(pi.' . $colQtd . '), 0) AS total_itens';
            } else {
                $select .= ', COALESCE(COUNT(pi.id), 0) AS total_itens';
            }
            $join = ' LEFT JOIN ' . $itensTable . ' pi ON pi.' . $colPedidoId . ' = p.id';
            $group = ' GROUP BY p.id';
        }

        $sql = "SELECT {$select} FROM {$this->table} p{$join}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= $group;
        $sql .= ' ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset';

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', (int) $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalPedidosUsuario(int $usuarioId): int {
        if ($usuarioId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM {$this->table} p WHERE p.usuario_id = :usuario_id");
            $stmt->execute([':usuario_id' => $usuarioId]);
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getConfigKeyValue(string $key, $default = null) {
        try {
            $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row) && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        // Schema categoria + chave + valor
        try {
            $stmtCols = $this->connection->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                // Mantém compatibilidade: comissao_manual_faixas => categoria=comissao, chave=manual_faixas
                if ($key === 'comissao_manual_faixas') {
                    $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                    $stmt->execute(['comissao', 'manual_faixas']);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($row) && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmtCols = $this->connection->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (is_array($cols) && in_array($key, $cols, true)) {
                $stmt = $this->connection->query('SELECT ' . $key . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row) && array_key_exists('valor', $row)) {
                    return $row['valor'];
                }
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    public function getFaixasComissaoManual(): array {
        $raw = $this->getConfigKeyValue('comissao_manual_faixas', null);
        if ($raw === null || $raw === '') {
            return [
                ['min' => 0, 'max' => 999999999, 'percent' => 0],
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                ['min' => 0, 'max' => 999999999, 'percent' => 0],
            ];
        }

        $faixas = [];
        foreach ($decoded as $f) {
            if (!is_array($f)) continue;
            $min = isset($f['min']) ? (float) $f['min'] : 0.0;
            $max = array_key_exists('max', $f) ? (float) $f['max'] : 999999999.0;
            $percent = isset($f['percent']) ? (float) $f['percent'] : 0.0;
            $faixas[] = ['min' => $min, 'max' => $max, 'percent' => $percent];
        }

        if (empty($faixas)) {
            $faixas[] = ['min' => 0, 'max' => 999999999, 'percent' => 0];
        }

        usort($faixas, function($a, $b) {
            return ($a['min'] ?? 0) <=> ($b['min'] ?? 0);
        });

        return $faixas;
    }

    public function calcularPercentualComissaoManual(float $faturamento, array $faixas): float {
        foreach ($faixas as $f) {
            $min = (float) ($f['min'] ?? 0);
            $max = (float) ($f['max'] ?? 999999999);
            if ($faturamento >= $min && $faturamento <= $max) {
                return (float) ($f['percent'] ?? 0);
            }
        }
        return (float) (($faixas[count($faixas) - 1]['percent'] ?? 0));
    }

    public function getResumoComissoesPedidosManuais(int $usuarioId): array {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $this->getFaixasComissaoManual(),
            ];
        }

        $colsP = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE pedidos');
            $colsP = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsP = [];
        }

        if (!is_array($colsP) || !in_array('origem_pedido', $colsP, true)) {
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $this->getFaixasComissaoManual(),
            ];
        }

        $totalCol = 'total';
        foreach (['valor_total', 'total', 'amount', 'valor'] as $c) {
            if (in_array($c, $colsP, true)) {
                $totalCol = $c;
                break;
            }
        }

        $statusCol = null;
        foreach (['status', 'payment_status', 'status_pagamento', 'pagamento_status'] as $c) {
            if (in_array($c, $colsP, true)) {
                $statusCol = $c;
                break;
            }
        }

        $statusPaid = ['pago', 'paid', 'approved', 'confirmed', 'received', 'succeeded', 'success'];

        $where = 'usuario_id = :uid AND origem_pedido = :origem';
        if (!empty($statusCol)) {
            $where .= " AND LOWER(COALESCE({$statusCol}, '')) IN ('" . implode("','", $statusPaid) . "')";
        }

        $stmt = $this->connection->prepare("SELECT id, codigo_pedido, numero_pedido, created_at, {$totalCol} AS total_valor FROM pedidos WHERE {$where} ORDER BY created_at DESC");
        $stmt->execute([':uid' => $usuarioId, ':origem' => 'manual']);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($pedidos)) {
            $faixas = $this->getFaixasComissaoManual();
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $faixas,
            ];
        }

        $itensTable = null;
        try {
            $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute(['pedido_itens']);
            $hasItens = ((int) $stmtT->fetchColumn() > 0);
            $stmtT->execute(['pedido_items']);
            $hasItems = ((int) $stmtT->fetchColumn() > 0);
            if ($hasItens && !$hasItems) $itensTable = 'pedido_itens';
            elseif ($hasItems && !$hasItens) $itensTable = 'pedido_items';
            else $itensTable = 'pedido_itens';
        } catch (\Exception $e) {
            $itensTable = 'pedido_itens';
        }

        $colsItens = [];
        try {
            $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
            $colsItens = $stmtColsI ? $stmtColsI->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsItens = [];
        }

        $qtdCol = null;
        foreach (['quantidade', 'qty', 'qtd'] as $c) {
            if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                $qtdCol = $c;
                break;
            }
        }
        $produtoIdCol = (is_array($colsItens) && in_array('produto_id', $colsItens, true)) ? 'produto_id' : null;
        $pedidoIdCol = (is_array($colsItens) && in_array('pedido_id', $colsItens, true)) ? 'pedido_id' : null;

        $colsProdutos = [];
        try {
            $stmtColsPr = $this->connection->query('DESCRIBE produtos');
            $colsProdutos = $stmtColsPr ? $stmtColsPr->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsProdutos = [];
        }

        $custoCol = null;
        foreach (['custo', 'preco_custo', 'cost', 'valor_custo'] as $c) {
            if (is_array($colsProdutos) && in_array($c, $colsProdutos, true)) {
                $custoCol = $c;
                break;
            }
        }

        $totalFaturado = 0.0;
        foreach ($pedidos as $p) {
            $totalFaturado += (float) ($p['total_valor'] ?? 0);
        }

        $totalCusto = 0.0;
        $pedidoIds = array_map(fn($p) => (int) ($p['id'] ?? 0), $pedidos);
        $pedidoIds = array_values(array_filter($pedidoIds, fn($v) => $v > 0));

        $custoPorPedido = [];
        if (!empty($pedidoIds) && $pedidoIdCol && $produtoIdCol && $qtdCol && $custoCol) {
            $in = implode(',', array_fill(0, count($pedidoIds), '?'));
            $sqlItens = "SELECT i.{$pedidoIdCol} AS pedido_id, i.{$qtdCol} AS qtd, pr.{$custoCol} AS custo_unit FROM {$itensTable} i INNER JOIN produtos pr ON pr.id = i.{$produtoIdCol} WHERE i.{$pedidoIdCol} IN ({$in})";
            try {
                $stmtItens = $this->connection->prepare($sqlItens);
                $stmtItens->execute($pedidoIds);
                $rows = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    $q = (float) ($r['qtd'] ?? 0);
                    $cu = (float) ($r['custo_unit'] ?? 0);
                    $custo = $q * $cu;
                    if (!isset($custoPorPedido[$pid])) $custoPorPedido[$pid] = 0.0;
                    $custoPorPedido[$pid] += $custo;
                    $totalCusto += $custo;
                }
            } catch (\Exception $e) {
            }
        }

        $totalLiquido = $totalFaturado - $totalCusto;
        $faixas = $this->getFaixasComissaoManual();
        $percent = $this->calcularPercentualComissaoManual($totalFaturado, $faixas);
        $valorComissao = $totalLiquido * ($percent / 100.0);

        $pedidosOut = [];
        foreach ($pedidos as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $fat = (float) ($p['total_valor'] ?? 0);
            $custo = (float) ($custoPorPedido[$pid] ?? 0);
            $pedidosOut[] = [
                'id' => $pid,
                'codigo' => (string) ($p['codigo_pedido'] ?? ($p['numero_pedido'] ?? $pid)),
                'created_at' => (string) ($p['created_at'] ?? ''),
                'faturado' => $fat,
                'custo' => $custo,
                'liquido' => $fat - $custo,
            ];
        }

        return [
            'pedidos' => $pedidosOut,
            'total_faturado' => (float) $totalFaturado,
            'total_custo_produtos' => (float) $totalCusto,
            'total_liquido' => (float) $totalLiquido,
            'percentual_comissao' => (float) $percent,
            'valor_comissao' => (float) $valorComissao,
            'faixas' => $faixas,
        ];
    }

    public function getResumoComissoesPedidosManuaisPorAdminCriador(int $adminId): array {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $this->getFaixasComissaoManual(),
            ];
        }

        $colsP = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE pedidos');
            $colsP = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsP = [];
        }

        if (!is_array($colsP) || !in_array('origem_pedido', $colsP, true) || !in_array('admin_criador_id', $colsP, true)) {
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $this->getFaixasComissaoManual(),
            ];
        }

        $totalCol = null;
        foreach (['valor_total', 'total', 'amount', 'valor'] as $c) {
            if (in_array($c, $colsP, true)) {
                $totalCol = $c;
                break;
            }
        }
        if (!$totalCol) {
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $this->getFaixasComissaoManual(),
            ];
        }

        $statusCol = null;
        foreach (['status_pagamento', 'payment_status', 'status'] as $c) {
            if (in_array($c, $colsP, true)) {
                $statusCol = $c;
                break;
            }
        }

        $statusPaid = ['pago','paid','approved','aprovado','concluido','concluído'];

        $where = 'admin_criador_id = :aid AND origem_pedido = :origem';
        if (!empty($statusCol)) {
            $where .= " AND LOWER(COALESCE({$statusCol}, '')) IN ('" . implode("','", $statusPaid) . "')";
        }

        $stmt = $this->connection->prepare("SELECT id, codigo_pedido, numero_pedido, created_at, {$totalCol} AS total_valor FROM pedidos WHERE {$where} ORDER BY created_at DESC");
        $stmt->execute([':aid' => $adminId, ':origem' => 'manual']);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($pedidos)) {
            $faixas = $this->getFaixasComissaoManual();
            return [
                'pedidos' => [],
                'total_faturado' => 0.0,
                'total_custo_produtos' => 0.0,
                'total_liquido' => 0.0,
                'percentual_comissao' => 0.0,
                'valor_comissao' => 0.0,
                'faixas' => $faixas,
            ];
        }

        // Reaproveita a mesma lógica de custo por pedido
        $itensTable = null;
        try {
            $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute(['pedido_itens']);
            $hasItens = ((int) $stmtT->fetchColumn() > 0);
            $stmtT->execute(['pedido_items']);
            $hasItems = ((int) $stmtT->fetchColumn() > 0);
            if ($hasItens && !$hasItems) $itensTable = 'pedido_itens';
            elseif ($hasItems && !$hasItens) $itensTable = 'pedido_items';
            else $itensTable = 'pedido_itens';
        } catch (\Exception $e) {
            $itensTable = 'pedido_itens';
        }

        $colsItens = [];
        try {
            $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
            $colsItens = $stmtColsI ? $stmtColsI->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsItens = [];
        }

        $qtdCol = null;
        foreach (['quantidade', 'qty', 'qtd'] as $c) {
            if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                $qtdCol = $c;
                break;
            }
        }
        $produtoIdCol = (is_array($colsItens) && in_array('produto_id', $colsItens, true)) ? 'produto_id' : null;
        $pedidoIdCol = (is_array($colsItens) && in_array('pedido_id', $colsItens, true)) ? 'pedido_id' : null;

        $colsProdutos = [];
        try {
            $stmtColsPr = $this->connection->query('DESCRIBE produtos');
            $colsProdutos = $stmtColsPr ? $stmtColsPr->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsProdutos = [];
        }

        $custoCol = null;
        foreach (['custo', 'preco_custo', 'cost', 'valor_custo'] as $c) {
            if (is_array($colsProdutos) && in_array($c, $colsProdutos, true)) {
                $custoCol = $c;
                break;
            }
        }

        $totalFaturado = 0.0;
        foreach ($pedidos as $p) {
            $totalFaturado += (float) ($p['total_valor'] ?? 0);
        }

        $totalCusto = 0.0;
        $pedidoIds = array_map(fn($p) => (int) ($p['id'] ?? 0), $pedidos);
        $pedidoIds = array_values(array_filter($pedidoIds, fn($v) => $v > 0));

        $custoPorPedido = [];
        if (!empty($pedidoIds) && $pedidoIdCol && $produtoIdCol && $qtdCol && $custoCol) {
            $in = implode(',', array_fill(0, count($pedidoIds), '?'));
            $sqlItens = "SELECT i.{$pedidoIdCol} AS pedido_id, i.{$qtdCol} AS qtd, pr.{$custoCol} AS custo_unit FROM {$itensTable} i INNER JOIN produtos pr ON pr.id = i.{$produtoIdCol} WHERE i.{$pedidoIdCol} IN ({$in})";
            try {
                $stmtItens = $this->connection->prepare($sqlItens);
                $stmtItens->execute($pedidoIds);
                $rows = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    $q = (float) ($r['qtd'] ?? 0);
                    $cu = (float) ($r['custo_unit'] ?? 0);
                    $custo = $q * $cu;
                    if (!isset($custoPorPedido[$pid])) $custoPorPedido[$pid] = 0.0;
                    $custoPorPedido[$pid] += $custo;
                    $totalCusto += $custo;
                }
            } catch (\Exception $e) {
            }
        }

        $totalLiquido = $totalFaturado - $totalCusto;
        $faixas = $this->getFaixasComissaoManual();
        $percent = $this->calcularPercentualComissaoManual($totalFaturado, $faixas);
        $valorComissao = $totalLiquido * ($percent / 100.0);

        $pedidosOut = [];
        foreach ($pedidos as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $fat = (float) ($p['total_valor'] ?? 0);
            $custo = (float) ($custoPorPedido[$pid] ?? 0);
            $pedidosOut[] = [
                'id' => $pid,
                'codigo' => (string) ($p['codigo_pedido'] ?? ($p['numero_pedido'] ?? $pid)),
                'created_at' => (string) ($p['created_at'] ?? ''),
                'faturado' => $fat,
                'custo' => $custo,
                'liquido' => $fat - $custo,
            ];
        }

        return [
            'pedidos' => $pedidosOut,
            'total_faturado' => (float) $totalFaturado,
            'total_custo_produtos' => (float) $totalCusto,
            'total_liquido' => (float) $totalLiquido,
            'percentual_comissao' => (float) $percent,
            'valor_comissao' => (float) $valorComissao,
            'faixas' => $faixas,
        ];
    }

    public function criarPedidoAPartirDoCarrinho($carrinhoId, $usuarioId, $enderecoEntregaId, $enderecoCobrancaId, $dadosPagamento) {
        $this->connection->beginTransaction();
        
        try {
            // Obter carrinho
            $carrinhoModel = new Carrinho();
            $carrinho = $carrinhoModel->find($carrinhoId);
            $items = $carrinhoModel->getItems($carrinhoId);
            
            if (empty($items)) {
                throw new \Exception('Carrinho vazio');
            }
            
            // Gerar código do pedido
            $codigoPedido = $this->gerarCodigoPedido();
            
            // Calcular totais
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += ($item['preco'] * $item['quantidade']);
            }
            
            // Inserir pedido
            $cols = [];
            try {
                $stmtCols = $this->connection->query("DESCRIBE {$this->table}");
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                $cols = [];
            }

            $insertCols = [
                'usuario_id',
                'codigo_pedido',
                'status',
                'subtotal',
                'valor_total',
                'moeda',
                'endereco_entrega_id',
                'endereco_cobranca_id',
                'created_at',
            ];

            $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $insertCols) . ') VALUES (';
            $placeholders = [
                ':usuario_id',
                ':codigo_pedido',
                "'pendente'",
                ':subtotal',
                ':valor_total',
                ':moeda',
                ':endereco_entrega_id',
                ':endereco_cobranca_id',
                'NOW()',
            ];
            $sql .= implode(', ', $placeholders) . ')';

            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuarioId);
            $stmt->bindParam(':codigo_pedido', $codigoPedido);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->bindParam(':valor_total', $subtotal);
            $stmt->bindParam(':moeda', $dadosPagamento['moeda']);
            $stmt->bindParam(':endereco_entrega_id', $enderecoEntregaId);
            $stmt->bindParam(':endereco_cobranca_id', $enderecoCobrancaId);
            $stmt->execute();
            
            $pedidoId = $this->connection->lastInsertId();
            
            // Criar itens do pedido
            $pedidoItens = [];
            foreach ($items as $item) {
                $itemData = [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                    'subtotal' => $item['preco'] * $item['quantidade']
                ];
                $this->connection->prepare("
                    INSERT INTO pedido_items (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                    VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal)
                ")->execute($itemData);

                $pedidoItens[] = [
                    'produto_id' => (int) $item['produto_id'],
                    'quantidade' => (int) $item['quantidade'],
                ];
                
                // Atualizar estoque
                $produtoModel = new Produto();
                $produtoModel->updateEstoque($item['produto_id'], $item['quantidade']);
            }
            
            // Adicionar histórico de status
            $this->adicionarHistoricoStatus($pedidoId, null, 'pendente', 'Pedido criado aguardando confirmação do pagamento', $usuarioId);
            
            // Consumir estoque interno e gerar lista de compras por loja
            $this->consumirEstoqueInternoEGerarCompras((int) $pedidoId, (int) $usuarioId, $pedidoItens);

            $this->connection->commit();
            
            // Disparar evento
            $this->dispararEvento('novo_pedido', $pedidoId);
            
            return $pedidoId;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function gerarCodigoPedido() {
        do {
            $codigo = 'BZS' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $this->connection->prepare("SELECT id FROM {$this->table} WHERE codigo_pedido = :codigo");
            $stmt->bindParam(':codigo', $codigo);
            $stmt->execute();
        } while ($stmt->fetch());
        
        return $codigo;
    }

    public function adicionarHistoricoStatus($pedidoId, $statusAnterior, $statusNovo, $observacao, $usuarioId) {
        $stmt = $this->connection->prepare("
            INSERT INTO pedido_status_history (pedido_id, status_anterior, status_novo, observacoes, usuario_id) 
            VALUES (:pedido_id, :status_anterior, :status_novo, :observacoes, :usuario_id)
        ");
        $stmt->bindParam(':pedido_id', $pedidoId);
        $stmt->bindParam(':status_anterior', $statusAnterior);
        $stmt->bindParam(':status_novo', $statusNovo);
        $stmt->bindParam(':observacoes', $observacao);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
    }

    public function atualizarStatus($pedidoId, $novoStatus, $observacao = '', $usuarioId = null) {
        $this->connection->beginTransaction();
        
        try {
            // Obter status atual
            $pedidoAtual = $this->find($pedidoId);
            $statusAnterior = $pedidoAtual['status'];
            
            // Atualizar pedido
            $this->update($pedidoId, ['status' => $novoStatus]);
            
            // Adicionar histórico
            $this->adicionarHistoricoStatus($pedidoId, $statusAnterior, $novoStatus, $observacao, $usuarioId);
            
            $this->connection->commit();
            
            // Disparar evento
            $this->dispararEvento($this->mapearStatusParaEvento($novoStatus), $pedidoId);
            
            return true;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function mapearStatusParaEvento($status) {
        $mapeamento = [
            'pagamento' => 'novo_pedido',
            'pendente' => 'novo_pedido',
            'pago' => 'pedido_aprovado',
            'paid' => 'pedido_aprovado',
            'cancelado' => 'pedido_cancelado',
            'cancelled' => 'pedido_cancelado',
            'consolidado' => 'pedido_consolidado',
            'rascunho_etiqueta' => 'rascunho_etiqueta_gerado',
            'etiqueta_efetivada' => 'etiqueta_efetivada',
            'enviado' => 'pedido_enviado',
            'entregue' => 'pedido_entregue',
            'entrega_finalizada' => 'pedido_entregue'
        ];
        
        return $mapeamento[$status] ?? null;
    }

    public function getComDetalhes($pedidoId) {
        $joinPagamentos = '';
        $selectPagamentos = '';
        $selectFormaPagamento = '';
        $selectExtras = '';
        $selectClienteSuite = '';
        $joinUsuarioCliente = '';
        $colsP = [];
        $temJoinPagamentos = false;

        try {
            $stmtColsP = $this->connection->query("DESCRIBE {$this->table}");
            $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsP) && in_array('forma_pagamento', $colsP, true)) {
                $selectFormaPagamento = ', p.forma_pagamento as forma_pagamento';
            }
        } catch (\Exception $e) {
        }

        try {
            $stmtColsU = $this->connection->query('DESCRIBE usuarios');
            $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
            $colsC = [];
            try {
                $stmtColsC = $this->connection->query('DESCRIBE clientes');
                $colsC = $stmtColsC ? $stmtColsC->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsC = [];
            }

            $temClienteUsuarioId = (is_array($colsC) && in_array('usuario_id', $colsC, true));
            if ($temClienteUsuarioId) {
                $joinUsuarioCliente = 'LEFT JOIN usuarios uc ON c.usuario_id = uc.id';
            }

            if (is_array($colsU) && in_array('suite', $colsU, true)) {
                $selectClienteSuite = $temClienteUsuarioId
                    ? ', COALESCE(u.suite, uc.suite) AS cliente_suite'
                    : ', u.suite AS cliente_suite';
            } elseif (is_array($colsU) && in_array('switch', $colsU, true)) {
                $selectClienteSuite = $temClienteUsuarioId
                    ? ', COALESCE(u.`switch`, uc.`switch`) AS cliente_suite'
                    : ', u.`switch` AS cliente_suite';
            }
        } catch (\Exception $e) {
        }

        try {
            $stmtColsPg = $this->connection->query('DESCRIBE pagamentos');
            $colsPg = $stmtColsPg->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsPg) && !empty($colsPg)) {
                $joinPagamentos = 'LEFT JOIN pagamentos pg ON p.id = pg.pedido_id';
                $temJoinPagamentos = true;

                $metodoCol = null;
                foreach (['metodo', 'forma_pagamento', 'payment_method', 'tipo'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $metodoCol = $c;
                        break;
                    }
                }

                $statusCol = null;
                foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $statusCol = $c;
                        break;
                    }
                }

                $gatewayCol = null;
                foreach (['gateway', 'provedor', 'provider'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $gatewayCol = $c;
                        break;
                    }
                }

                $transacaoCol = null;
                foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $transacaoCol = $c;
                        break;
                    }
                }

                $dataCol = null;
                foreach (['data_pagamento', 'paid_at', 'data_confirmacao', 'updated_at', 'created_at'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $dataCol = $c;
                        break;
                    }
                }

                if (!empty($metodoCol)) {
                    $selectPagamentos .= ", pg.{$metodoCol} AS pagamento_metodo";
                }
                if (!empty($statusCol)) {
                    $selectPagamentos .= ", pg.{$statusCol} AS pagamento_status";
                }
                if (!empty($gatewayCol)) {
                    $selectPagamentos .= ", pg.{$gatewayCol} AS pagamento_gateway";
                }
                if (!empty($transacaoCol)) {
                    $selectPagamentos .= ", pg.{$transacaoCol} AS pagamento_transacao";
                }
                if (!empty($dataCol)) {
                    $selectPagamentos .= ", pg.{$dataCol} AS pagamento_data";
                }
            }
        } catch (\Exception $e) {
        }

        // Fallback: alguns schemas guardam dados de pagamento diretamente em pedidos
        $selectPagamentoNoPedido = '';
        if (!$temJoinPagamentos && is_array($colsP) && !empty($colsP)) {
            if (in_array('payment_status', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_status AS pagamento_status';
            }
            if (in_array('payment_gateway', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_gateway AS pagamento_gateway';
            }
            if (in_array('payment_id', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_id AS pagamento_transacao';
            }
            if (in_array('pago_em', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.pago_em AS pagamento_data';
            }
        }

        // Origem / admin criador (pedidos manuais)
        $joinAdminCriador = '';
        $selectAdminCriador = '';
        try {
            if (is_array($colsP) && in_array('admin_criador_id', $colsP, true)) {
                $joinAdminCriador = 'LEFT JOIN usuarios uadm ON p.admin_criador_id = uadm.id';
                $selectAdminCriador = ', COALESCE(uadm.nome, uadm.name) AS admin_criador_nome, uadm.email AS admin_criador_email';
            }
        } catch (\Exception $e) {
            $joinAdminCriador = '';
            $selectAdminCriador = '';
        }

        $selectExtras = $selectFormaPagamento . $selectPagamentos . $selectPagamentoNoPedido . $selectAdminCriador;

        // Adaptar query para a estrutura correta das tabelas
        $stmt = $this->connection->prepare("
            SELECT p.*, 
                   COALESCE(c.nome_razao_social, u.nome, u.name, p.nome) as cliente_nome,
                   COALESCE(c.email, u.email) as cliente_email,
                   COALESCE(c.telefone, u.telefone) as cliente_telefone{$selectClienteSuite}{$selectExtras},
                   e_ent.cep as cep_entrega, e_ent.endereco as endereco_entrega, 
                   e_ent.numero as numero_entrega, e_ent.complemento as complemento_entrega,
                   e_ent.bairro as bairro_entrega, e_ent.cidade as cidade_entrega, e_ent.estado as estado_entrega,
                   e_cob.cep as cep_cobranca
            FROM {$this->table} p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            {$joinUsuarioCliente}
            {$joinPagamentos}
            {$joinAdminCriador}
            LEFT JOIN enderecos e_ent ON p.endereco_entrega_id = e_ent.id
            LEFT JOIN enderecos e_cob ON p.endereco_cobranca_id = e_cob.id
            WHERE p.id = :id
        ");
        $stmt->bindParam(':id', $pedidoId);
        $stmt->execute();
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($pedido) {
            $pedido['items'] = [];
            $pedido['historico'] = [];

            // Obter itens do pedido (tolerante a schemas)
            try {
                $itensTable = 'pedido_itens';
                $temPedidoItens = false;
                $temPedidoItems = false;

                try {
                    $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_itens']);
                    $temPedidoItens = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItens = false;
                }
                try {
                    $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_items']);
                    $temPedidoItems = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItems = false;
                }

                if ($temPedidoItens && $temPedidoItems) {
                    $c1 = 0;
                    $c2 = 0;
                    try {
                        $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = :id');
                        $st->execute([':id' => $pedidoId]);
                        $c1 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c1 = 0;
                    }
                    try {
                        $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = :id');
                        $st->execute([':id' => $pedidoId]);
                        $c2 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c2 = 0;
                    }
                    $itensTable = ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
                } elseif ($temPedidoItems) {
                    $itensTable = 'pedido_items';
                } else {
                    $itensTable = 'pedido_itens';
                }

                $colsItens = [];
                try {
                    $stmtCols = $this->connection->query('DESCRIBE ' . $itensTable);
                    $colsItens = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsItens = [];
                }

                $pick = function(array $cands) use ($colsItens) {
                    foreach ($cands as $c) {
                        if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                            return $c;
                        }
                    }
                    return null;
                };

                $colPedidoId = $pick(['pedido_id']);
                $colProdutoId = $pick(['produto_id']);
                $colQtd = $pick(['quantidade', 'qty']);
                $colPrecoUnit = $pick(['preco_unitario', 'valor_unitario', 'price', 'preco']);
                $colSubtotal = $pick(['subtotal']);
                $colNomeProduto = $pick(['nome_produto', 'produto_nome', 'nome']);
                $colSku = $pick(['nome_produto_sku', 'sku']);

                if (!$colPedidoId) {
                    throw new \Exception('Tabela de itens sem pedido_id');
                }

                $selectParts = [];
                if ($pick(['id']) !== null) $selectParts[] = 'pi.id';
                if ($colProdutoId) $selectParts[] = 'pi.' . $colProdutoId . ' AS produto_id';
                if ($colQtd) $selectParts[] = 'pi.' . $colQtd . ' AS quantidade';
                if ($colPrecoUnit) $selectParts[] = 'pi.' . $colPrecoUnit . ' AS preco_unitario';
                if ($colSubtotal) $selectParts[] = 'pi.' . $colSubtotal . ' AS subtotal';
                if ($colNomeProduto) $selectParts[] = 'pi.' . $colNomeProduto . ' AS nome_produto';
                if ($colSku) $selectParts[] = 'pi.' . $colSku . ' AS nome_produto_sku';
                if ($pick(['created_at']) !== null) $selectParts[] = 'pi.created_at';
                $selectParts[] = "(SELECT pf.nome_arquivo FROM produto_fotos pf WHERE pf.produto_id = pi." . ($colProdutoId ?: 'produto_id') . " ORDER BY pf.principal DESC, pf.ordem ASC LIMIT 1) as imagem_principal";

                $sqlItens = 'SELECT ' . implode(", ", $selectParts) . ' FROM ' . $itensTable . ' pi WHERE pi.' . $colPedidoId . ' = :id ORDER BY pi.id';
                $stmtItens = $this->connection->prepare($sqlItens);
                $stmtItens->bindParam(':id', $pedidoId);
                $stmtItens->execute();
                $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($itens as &$item) {
                    $item['referencia'] = $item['referencia'] ?? ($item['nome_produto_sku'] ?? '');
                    $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
                    $item['descricao_produto'] = $item['descricao_produto'] ?? '';
                    if (empty($item['nome_produto'])) {
                        $pid = (int) ($item['produto_id'] ?? 0);
                        $item['nome_produto'] = $pid > 0 ? ('Produto #' . $pid) : 'Produto';
                    }
                    $q = (int) ($item['quantidade'] ?? 0);
                    $pu = (float) ($item['preco_unitario'] ?? 0);
                    if (!isset($item['subtotal']) || $item['subtotal'] === null) {
                        $item['subtotal'] = $pu * $q;
                    }
                }

                $pedido['items'] = $itens;
            } catch (\Exception $e) {
                $pedido['items'] = [];
            }

            // Obter histórico de status (tolerante a nome/name)
            try {
                $userNomeCol = 'nome';
                try {
                    $stmtUserCols = $this->connection->query('DESCRIBE usuarios');
                    $userCols = $stmtUserCols ? $stmtUserCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                    if (is_array($userCols) && !in_array('nome', $userCols, true) && in_array('name', $userCols, true)) {
                        $userNomeCol = 'name';
                    }
                } catch (\Exception $e) {
                }

                $stmtHist = $this->connection->prepare("\
                    SELECT psh.*, u.{$userNomeCol} as usuario_alterou 
                    FROM pedido_status_history psh 
                    LEFT JOIN usuarios u ON psh.usuario_id = u.id 
                    WHERE psh.pedido_id = :id 
                    ORDER BY psh.created_at DESC
                ");
                $stmtHist->bindParam(':id', $pedidoId);
                $stmtHist->execute();
                $pedido['historico'] = $stmtHist->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($pedido['historico'] as &$item) {
                    $item['novo_status'] = $item['status_novo'] ?? 'Status atualizado';
                    $item['observacao'] = $item['observacoes'] ?? 'Sem observação';
                    $item['usuario_alterou'] = $item['usuario_alterou'] ?? 'Sistema';
                }
            } catch (\Exception $e) {
                $pedido['historico'] = [];
            }
            
            // Garantir que o pedido tenha todos os campos necessários
            $pedido['codigo_pedido'] = $pedido['numero_pedido'] ?? 'PED-' . str_pad($pedidoId, 6, '0', STR_PAD_LEFT);

            // Normalizar status: alguns schemas usam status_pedido ou pedido_status
            $statusPedidoCol = 'status';
            if (isset($colsP) && is_array($colsP) && !in_array('status', $colsP, true)) {
                foreach (['status_pedido', 'pedido_status'] as $cand) {
                    if (in_array($cand, $colsP, true)) {
                        $statusPedidoCol = $cand;
                        break;
                    }
                }
            }

            if (!isset($pedido['status']) || $pedido['status'] === null || $pedido['status'] === '') {
                if (!empty($statusPedidoCol) && isset($pedido[$statusPedidoCol])) {
                    $pedido['status'] = $pedido[$statusPedidoCol];
                }
            }

            $pedido['status'] = $pedido['status'] ?? 'pendente';
            if (empty($pedido['status'])) {
                $pedido['status'] = 'pendente';
            }

            $stPag = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? null);
            if (is_string($stPag)) {
                $stPag = strtoupper(trim($stPag));
            }
            $pedido['status_pagamento_aprovado'] = (!empty($stPag) && in_array($stPag, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true));
            $pedido['subtotal_produtos'] = $pedido['subtotal'] ?? 0;
            $pedido['valor_frete'] = $pedido['frete'] ?? 0;
            $pedido['taxa_servico'] = $pedido['servicos'] ?? 0;
            $pedido['valor_impostos'] = $pedido['impostos'] ?? 0;
            $pedido['valor_total'] = $pedido['total'] ?? 0;
            
            // Garantir que os campos de endereço tenham valores padrão
            $pedido['endereco_entrega'] = $pedido['endereco_entrega'] ?? 'Não informado';
            $pedido['numero_entrega'] = $pedido['numero_entrega'] ?? '';
            $pedido['complemento_entrega'] = $pedido['complemento_entrega'] ?? '';
            $pedido['bairro_entrega'] = $pedido['bairro_entrega'] ?? '';
            $pedido['cidade_entrega'] = $pedido['cidade_entrega'] ?? '';
            $pedido['estado_entrega'] = $pedido['estado_entrega'] ?? '';
            $pedido['cep_entrega'] = $pedido['cep_entrega'] ?? '';
        }
        
        return $pedido;
    }

    public function getRastreamento($pedidoId) {
        try {
            $userNomeCol = 'nome';
            try {
                $stmtUserCols = $this->connection->query('DESCRIBE usuarios');
                $userCols = $stmtUserCols ? $stmtUserCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($userCols) && !in_array('nome', $userCols, true) && in_array('name', $userCols, true)) {
                    $userNomeCol = 'name';
                }
            } catch (\Exception $e) {
            }

            // Adaptar query para a estrutura correta da tabela pedido_status_historico
            $stmt = $this->connection->prepare("
                SELECT psh.*, u.{$userNomeCol} as usuario_alterou 
                FROM pedido_status_history psh 
                LEFT JOIN usuarios u ON psh.usuario_id = u.id 
                WHERE psh.pedido_id = :id 
                ORDER BY psh.created_at DESC
            ");
            $stmt->bindParam(':id', $pedidoId);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function dispararEvento($eventoNome, $pedidoId) {
        // Por enquanto, apenas registrar que o evento ocorreu
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");

        try {
            $service = new NotificationService();
            $service->notificarEventoPedido(is_string($eventoNome) ? $eventoNome : null, (int) $pedidoId);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES] Falha ao disparar notificacoes: ' . $e->getMessage());
        }
    }
}
