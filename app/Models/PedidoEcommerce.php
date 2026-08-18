<?php
namespace App\Models;

use App\Services\NotificationService;

class PedidoEcommerce {
    private \PDO $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    public function getResumoComissoesPedidosManuaisTodos(): array {
        $resumoBase = [
            'pedidos' => [],
            'total_faturado' => 0.0,
            'total_custo_produtos' => 0.0,
            'total_liquido' => 0.0,
            'percentual_comissao' => 0.0,
            'valor_comissao' => 0.0,
            'faixas' => [],
            'por_moeda' => [
                'USD' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
                'BRL' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
            ],
        ];

        try {
            $cols = $this->getTableColumns('pedidos');

            $colOrigem = $this->pickColumn($cols, ['origem_pedido', 'origem', 'tipo']);
            $colMoeda = $this->pickColumn($cols, ['moeda', 'currency']);
            $colCodigo = $this->pickColumn($cols, ['codigo_pedido', 'numero_pedido']);
            $colCreatedAt = $this->pickColumn($cols, ['created_at', 'data_criacao', 'data_pedido']);
            $colStatus = $this->pickColumn($cols, ['status', 'status_pedido', 'pedido_status']);
            $colPaymentStatus = $this->pickColumn($cols, ['payment_status', 'status_pagamento']);
            $colValorTotal = $this->pickColumn($cols, ['valor_total', 'total', 'valor', 'amount']);
            $colImpostos = $this->pickColumn($cols, ['valor_impostos', 'impostos']);

            $where = [];
            $params = [];

            if ($colOrigem) {
                $where[] = 'LOWER(COALESCE(p.' . $colOrigem . ", '')) = 'manual'";
            }

            $paidParts = [];
            if ($colStatus) {
                $paidParts[] = 'LOWER(COALESCE(p.' . $colStatus . ", '')) IN ('pago','paid','approved','aprovado')";
            }
            if ($colPaymentStatus) {
                $paidParts[] = 'LOWER(COALESCE(p.' . $colPaymentStatus . ", '')) IN ('approved','paid','pago','aprovado','confirmed','received','succeeded','success')";
            }
            if (!empty($paidParts)) {
                $where[] = '(' . implode(' OR ', $paidParts) . ')';
            }

            // Excluir pedidos marcados como "já lançado no vendas" (sem comissão)
            $colSemComissao = $this->pickColumn($cols, ['sem_comissao']);
            if ($colSemComissao) {
                $where[] = '(p.' . $colSemComissao . ' IS NULL OR p.' . $colSemComissao . ' = 0)';
            }

            if (empty($where)) {
                return $resumoBase;
            }

            $select = ['p.id'];
            if ($colCodigo) $select[] = 'p.' . $colCodigo . ' AS codigo';
            if ($colCreatedAt) $select[] = 'p.' . $colCreatedAt . ' AS created_at';
            if ($colMoeda) $select[] = 'p.' . $colMoeda . ' AS moeda';
            if ($colValorTotal) $select[] = 'p.' . $colValorTotal . ' AS valor_total';
            if ($colImpostos) $select[] = 'p.' . $colImpostos . ' AS impostos';

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($colCreatedAt ? ('p.' . $colCreatedAt) : 'p.id') . ' DESC';
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $pedidosOut = [];
            foreach ($rows as $r) {
                $pid = (int) ($r['id'] ?? 0);
                if ($pid <= 0) continue;

                $moeda = strtoupper(trim((string) ($r['moeda'] ?? 'BRL')));
                if ($moeda === '') $moeda = 'BRL';
                $fat = (float) ($r['valor_total'] ?? 0);
                $impostos = (float) ($r['impostos'] ?? 0);

                $custo = 0.0;
                try {
                    $temPedidoItens = $this->tableExists('pedido_itens');
                    $temPedidoItems = $this->tableExists('pedido_items');
                    $itensTable = $temPedidoItens ? 'pedido_itens' : ($temPedidoItems ? 'pedido_items' : null);
                    if ($itensTable) {
                        $colsItens = $this->getTableColumns($itensTable);
                        $colPedidoId = $this->pickColumn($colsItens, ['pedido_id']);
                        $colProdutoId = $this->pickColumn($colsItens, ['produto_id']);
                        $colQtd = $this->pickColumn($colsItens, ['quantidade', 'qty']);
                        if ($colPedidoId && $colProdutoId && $colQtd) {
                            $colsProd = $this->getTableColumns('produtos');
                            $colCusto = $this->pickColumn($colsProd, ['preco_custo', 'custo', 'cost_price', 'valor_custo']);
                            if ($colCusto) {
                                $stC = $this->connection->prepare('SELECT SUM(COALESCE(pr.' . $colCusto . ',0) * COALESCE(pi.' . $colQtd . ',0)) AS custo_total FROM ' . $itensTable . ' pi INNER JOIN produtos pr ON pr.id = pi.' . $colProdutoId . ' WHERE pi.' . $colPedidoId . ' = ?');
                                $stC->execute([$pid]);
                                $custo = (float) ($stC->fetchColumn() ?: 0);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $custo = 0.0;
                }

                $liq = $fat - $custo - $impostos;
                $pedidosOut[] = [
                    'id' => $pid,
                    'codigo' => (string) ($r['codigo'] ?? $pid),
                    'created_at' => $r['created_at'] ?? null,
                    'moeda' => $moeda,
                    'faturado' => $fat,
                    'impostos' => $impostos,
                    'custo' => $custo,
                    'liquido' => $liq,
                ];

                if (!isset($resumoBase['por_moeda'][$moeda])) {
                    $resumoBase['por_moeda'][$moeda] = ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []];
                }
                $resumoBase['por_moeda'][$moeda]['total_faturado'] += $fat;
                $resumoBase['por_moeda'][$moeda]['total_custo_produtos'] += $custo;
                $resumoBase['por_moeda'][$moeda]['total_liquido'] += $liq;
                $resumoBase['por_moeda'][$moeda]['pedidos'][] = end($pedidosOut);
            }

            $resumoBase['pedidos'] = $pedidosOut;

            $totFat = 0.0;
            $totCus = 0.0;
            $totLiq = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => $t) {
                $totFat += (float) ($t['total_faturado'] ?? 0);
                $totCus += (float) ($t['total_custo_produtos'] ?? 0);
                $totLiq += (float) ($t['total_liquido'] ?? 0);
            }
            $resumoBase['total_faturado'] = $totFat;
            $resumoBase['total_custo_produtos'] = $totCus;
            $resumoBase['total_liquido'] = $totLiq;

            $faixas = $this->getComissaoManualFaixasConfig();
            $resumoBase['faixas'] = $faixas;

            $usdBrlRate = $this->getConfigNumber('sistema', 'usd_brl_rate', 0.0);
            if ($usdBrlRate <= 0) {
                // Fallback: usar PedidoManualService
                try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $usdBrlRate = $r; } catch (\Exception $e) {}
            }
            if ($usdBrlRate <= 0) {
                $usdBrlRate = 5.85; // fallback final
            }

            $faturadoBrlParaFaixa = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => $t) {
                $m = strtoupper(trim((string) $m));
                $fatMoeda = (float) ($t['total_faturado'] ?? 0);
                if ($m === 'USD' && $usdBrlRate > 0) {
                    $fatMoeda *= $usdBrlRate;
                }
                $faturadoBrlParaFaixa += $fatMoeda;
            }

            $percent = $this->resolvePercentualPorFaixas($faturadoBrlParaFaixa, $faixas);
            $resumoBase['percentual_comissao'] = $percent;
            $resumoBase['valor_comissao'] = ($resumoBase['total_liquido'] * ($percent / 100));

            // Aplicar percentual por moeda também
            foreach ($resumoBase['por_moeda'] as $m => &$dados) {
                $dados['percentual_comissao'] = $percent;
                $dados['valor_comissao'] = max(0, (float)$dados['total_liquido']) * ($percent / 100);
            }
            unset($dados);

            return $resumoBase;
        } catch (\Exception $e) {
            return $resumoBase;
        }
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$table]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function resolvePedidoStatusHistoryUsuarioColumn(): string {
        try {
            if (!$this->tableExists('pedido_status_history')) {
                return 'usuario_id';
            }

            $stmtCols = $this->connection->query('DESCRIBE pedido_status_history');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (!is_array($cols)) {
                return 'usuario_id';
            }

            if (in_array('alterado_por', $cols, true)) {
                return 'alterado_por';
            }
            if (in_array('usuario_id', $cols, true)) {
                return 'usuario_id';
            }
        } catch (\Exception $e) {
        }

        return 'usuario_id';
    }

    private function getTableColumns(string $table): array {
        try {
            $stmtCols = $this->connection->query('DESCRIBE ' . $table);
            return $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
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

    private function getConfigValue(string $categoria, string $chave, $default = null) {
        try {
            $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
            foreach ($tableCandidates as $table) {
                if (!$this->tableExists($table)) {
                    continue;
                }

                $cols = $this->getTableColumns($table);
                if (empty($cols)) {
                    continue;
                }

                // mode: categoria/chave
                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = $this->pickColumn($cols, ['valor', 'value', 'conteudo', 'content', 'config_value']);
                    if ($valCol) {
                        $orderCol = in_array('id', $cols, true) ? 'id' : (in_array('updated_at', $cols, true) ? 'updated_at' : 'chave');
                        $st = $this->connection->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE categoria = ? AND chave = ? ORDER BY ' . $orderCol . ' DESC LIMIT 1');
                        $st->execute([$categoria, $chave]);
                        $v = $st->fetchColumn();
                        if ($v !== false && $v !== null) {
                            return $v;
                        }
                    }
                }

                // mode: chave/valor (fullKey)
                $keyCol = $this->pickColumn($cols, ['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro']);
                $valCol = $this->pickColumn($cols, ['valor', 'value', 'conteudo', 'content', 'config_value']);
                if ($keyCol && $valCol) {
                    $fullKey = $categoria . '_' . $chave;
                    $orderCol = in_array('id', $cols, true) ? 'id' : (in_array('updated_at', $cols, true) ? 'updated_at' : $keyCol);
                    $st = $this->connection->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? ORDER BY ' . $orderCol . ' DESC LIMIT 1');
                    $st->execute([$fullKey]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null) {
                        return $v;
                    }
                }

                // mode: single row table
                if (in_array('id', $cols, true) && !in_array('categoria', $cols, true) && !in_array('chave', $cols, true)) {
                    if (in_array($chave, $cols, true)) {
                        $st = $this->connection->query('SELECT ' . $chave . ' FROM ' . $table . ' ORDER BY id ASC LIMIT 1');
                        $v = $st ? $st->fetchColumn() : null;
                        if ($v !== false && $v !== null) {
                            return $v;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    private function getConfigNumber(string $categoria, string $chave, float $default = 0.0): float {
        $v = $this->getConfigValue($categoria, $chave, null);
        if ($v === null) {
            return $default;
        }
        $s = str_replace(',', '.', trim((string) $v));
        return is_numeric($s) ? (float) $s : $default;
    }

    private function getComissaoManualFaixasConfig(): array {
        // Buscar faixas usando a mesma lógica do AdminComissoesGlobalController
        try {
            // Tentar com categoria + chave
            $st = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'comissao' AND chave = 'manual_faixas' LIMIT 1");
            $st->execute();
            $raw = (string)($st->fetchColumn() ?: '');
            if ($raw !== '') { $arr = json_decode($raw, true); if (is_array($arr) && !empty($arr)) return $arr; }

            // Tentar com chave direta
            $st2 = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'comissao_manual_faixas' LIMIT 1");
            $st2->execute();
            $raw2 = (string)($st2->fetchColumn() ?: '');
            if ($raw2 !== '') { $arr2 = json_decode($raw2, true); if (is_array($arr2) && !empty($arr2)) return $arr2; }

            // Tentar tabela configuracoes
            try {
                $st3 = $this->connection->prepare("SELECT valor FROM configuracoes WHERE chave = 'comissao_manual_faixas' LIMIT 1");
                $st3->execute();
                $raw3 = (string)($st3->fetchColumn() ?: '');
                if ($raw3 !== '') { $arr3 = json_decode($raw3, true); if (is_array($arr3) && !empty($arr3)) return $arr3; }
            } catch (\Exception $e) {}
        } catch (\Exception $e) {}

        return [['min' => 0, 'max' => 999999999, 'percent' => 0]];
    }

    private function resolvePercentualPorFaixas(float $faturadoBrl, array $faixas): float {
        $f = max(0.0, (float) $faturadoBrl);
        foreach ($faixas as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            $min = isset($fx['min']) ? (float) $fx['min'] : 0.0;
            $max = isset($fx['max']) ? (float) $fx['max'] : 0.0;
            $percent = isset($fx['percent']) ? (float) $fx['percent'] : 0.0;
            if ($max <= 0) {
                $max = 999999999.0;
            }
            if ($f >= $min && $f <= $max) {
                return max(0.0, $percent);
            }
        }
        return 0.0;
    }

    public function getPedidos(int $usuarioId, int $limit = 10, int $offset = 0): array {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) return [];
        if ($limit < 1) $limit = 10;
        if ($offset < 0) $offset = 0;

        try {
            $cols = $this->getTableColumns('pedidos');

            $colUsuarioId = $this->pickColumn($cols, ['usuario_id', 'user_id']);
            if (!$colUsuarioId) {
                return [];
            }

            $colCreatedAt = $this->pickColumn($cols, ['created_at', 'data_criacao', 'data_pedido']);
            $orderCol = $colCreatedAt ? ('p.' . $colCreatedAt) : 'p.id';

            $select = ['p.id'];
            if (in_array('codigo_pedido', $cols, true)) $select[] = 'p.codigo_pedido';
            if (in_array('numero_pedido', $cols, true)) $select[] = 'p.numero_pedido';
            if ($colCreatedAt) $select[] = 'p.' . $colCreatedAt . ' AS created_at';
            if (in_array('updated_at', $cols, true)) $select[] = 'p.updated_at';
            foreach (['status', 'payment_status', 'status_pagamento', 'moeda', 'currency', 'valor_total', 'total', 'valor', 'amount'] as $c) {
                if (in_array($c, $cols, true)) {
                    $select[] = 'p.' . $c;
                }
            }

            foreach (['taxa_conversao', 'exchange_rate', 'conversion_rate', 'moeda_original', 'moeda_original', 'valor_total_brl', 'total_brl'] as $c) {
                if (in_array($c, $cols, true)) {
                    $select[] = 'p.' . $c;
                }
            }

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p WHERE p.' . $colUsuarioId . ' = :uid';

            // Filtrar pedidos deletados (soft delete)
            if (in_array('deleted_at', $cols, true)) {
                $sql .= ' AND p.deleted_at IS NULL';
            }

            $sql .= ' ORDER BY ' . $orderCol . ' DESC LIMIT :lim OFFSET :off';
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':uid', $usuarioId, \PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                if (empty($r['created_at']) && !empty($r['data_criacao'])) {
                    $r['created_at'] = $r['data_criacao'];
                }
                if (empty($r['codigo_pedido']) && !empty($r['numero_pedido'])) {
                    $r['codigo_pedido'] = $r['numero_pedido'];
                }
                if (empty($r['codigo_pedido'])) {
                    $r['codigo_pedido'] = 'PED-' . str_pad((string) ((int) ($r['id'] ?? 0)), 6, '0', STR_PAD_LEFT);
                }
                if (!isset($r['total_itens'])) {
                    $r['total_itens'] = 0;
                }

                $moeda = strtoupper((string) ($r['moeda'] ?? ($r['currency'] ?? 'BRL')));
                if ($moeda === '') {
                    $moeda = 'BRL';
                }
                $r['moeda'] = $moeda;

                $taxaConversao = null;
                foreach (['taxa_conversao', 'exchange_rate', 'conversion_rate'] as $c) {
                    if (array_key_exists($c, $r)) {
                        $taxaConversao = (float) ($r[$c] ?? 0);
                        break;
                    }
                }
                if ($taxaConversao === null || $taxaConversao <= 0) {
                    $taxaConversao = 1.0;
                }

                if ($moeda === 'BRL' && $taxaConversao <= 1.01) {
                    try {
                        $rateFromConfig = 0.0;
                        foreach (['configuracoes_sistema', 'configuracoes', 'settings', 'config'] as $tbl) {
                            if (!$this->tableExists($tbl)) {
                                continue;
                            }
                            $colsCfg = $this->getTableColumns($tbl);
                            $colChave = $this->pickColumn($colsCfg, ['chave', 'key', 'nome', 'config_key', 'slug', 'parametro']);
                            $colValor = $this->pickColumn($colsCfg, ['valor', 'value', 'conteudo', 'content', 'config_value']);
                            if (!$colChave || !$colValor) {
                                continue;
                            }

                            $keys = ['usd_brl_rate', 'sistema_usd_brl_rate'];
                            foreach ($keys as $k) {
                                try {
                                    $stCfg = $this->connection->prepare('SELECT ' . $colValor . ' AS v FROM ' . $tbl . ' WHERE ' . $colChave . ' = ? LIMIT 1');
                                    $stCfg->execute([$k]);
                                    $val = $stCfg->fetchColumn();
                                    $v = (float) str_replace(',', '.', (string) ($val ?? '0'));
                                    if ($v > 1.01) {
                                        $rateFromConfig = $v;
                                        break 2;
                                    }
                                } catch (\Exception $e) {
                                }
                            }
                        }
                        if ($rateFromConfig > 1.01) {
                            $taxaConversao = $rateFromConfig;
                        }

                        if ($this->tableExists('configuracoes_moeda')) {
                            $stTx = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                            $stTx->execute();
                            $txRow = $stTx->fetch(\PDO::FETCH_ASSOC);
                            $tx = (float) ($txRow['taxa_conversao'] ?? 0);
                            if ($tx > 1.01) {
                                $taxaConversao = $tx;
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }

                $r['taxa_conversao'] = $taxaConversao;

                // Ajuste opcional: se existirem colunas *_brl, preferir elas para exibição na listagem
                // (mantém compatibilidade com schemas que salvam total em USD + total_brl em BRL)
                if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                    $valorTotalBRL = null;
                    foreach (['valor_total_brl', 'total_brl'] as $c) {
                        if (array_key_exists($c, $r)) {
                            $v = (float) ($r[$c] ?? 0);
                            if ($v > 0) {
                                $valorTotalBRL = $v;
                                break;
                            }
                        }
                    }

                    if ($valorTotalBRL !== null) {
                        $totalField = null;
                        foreach (['valor_total', 'total', 'valor', 'amount'] as $c) {
                            if (array_key_exists($c, $r)) {
                                $totalField = $c;
                                break;
                            }
                        }
                        if ($totalField !== null) {
                            $r[$totalField] = $valorTotalBRL;
                            $r['valor_total'] = $valorTotalBRL;
                        }
                    }
                }
            }
            unset($r);

            // total_itens: SUM(quantidade) por pedido
            $ids = [];
            foreach ($rows as $r) {
                $pid = (int) ($r['id'] ?? 0);
                if ($pid > 0) $ids[$pid] = true;
            }
            $ids = array_keys($ids);
            if (!empty($ids)) {
                $temPedidoItens = $this->tableExists('pedido_itens');
                $temPedidoItems = $this->tableExists('pedido_items');
                $itensTable = $temPedidoItens ? 'pedido_itens' : ($temPedidoItems ? 'pedido_items' : null);
                if ($itensTable) {
                    $colsItens = $this->getTableColumns($itensTable);
                    $colPedidoId = $this->pickColumn($colsItens, ['pedido_id']);
                    $colQtd = $this->pickColumn($colsItens, ['quantidade', 'qty']);
                    if ($colPedidoId && $colQtd) {
                        try {
                            $in = implode(',', array_fill(0, count($ids), '?'));
                            $st = $this->connection->prepare('SELECT ' . $colPedidoId . ' AS pid, SUM(COALESCE(' . $colQtd . ',0)) AS total_itens FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' IN (' . $in . ') GROUP BY ' . $colPedidoId);
                            $st->execute($ids);
                            $map = [];
                            foreach (($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $row) {
                                $map[(int) ($row['pid'] ?? 0)] = (int) ($row['total_itens'] ?? 0);
                            }
                            foreach ($rows as &$r) {
                                $pid = (int) ($r['id'] ?? 0);
                                if ($pid > 0 && isset($map[$pid])) {
                                    $r['total_itens'] = $map[$pid];
                                }
                            }
                            unset($r);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }

            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getTotalPedidosUsuario(int $usuarioId): int {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) return 0;

        try {
            $cols = $this->getTableColumns('pedidos');
            $colUsuarioId = $this->pickColumn($cols, ['usuario_id', 'user_id']);
            if (!$colUsuarioId) {
                return 0;
            }

            $sql = 'SELECT COUNT(*) FROM pedidos WHERE ' . $colUsuarioId . ' = ?';

            // Filtrar pedidos deletados (soft delete)
            if (in_array('deleted_at', $cols, true)) {
                $sql .= ' AND deleted_at IS NULL';
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$usuarioId]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            return 0;
        }
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
                'faixas' => [],
                'por_moeda' => [
                    'USD' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
                    'BRL' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
                ],
            ];
        }

        $resumoBase = [
            'pedidos' => [],
            'total_faturado' => 0.0,
            'total_custo_produtos' => 0.0,
            'total_liquido' => 0.0,
            'percentual_comissao' => 0.0,
            'valor_comissao' => 0.0,
            'faixas' => [],
            'por_moeda' => [
                'USD' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
                'BRL' => ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []],
            ],
        ];

        try {
            $cols = $this->getTableColumns('pedidos');

            $colOrigem = $this->pickColumn($cols, ['origem_pedido', 'origem', 'tipo']);
            $colAdminCriador = $this->pickColumn($cols, ['admin_criador_id', 'admin_creator_id', 'admin_id', 'created_by_admin_id', 'criador_admin_id']);
            $colMoeda = $this->pickColumn($cols, ['moeda', 'currency']);
            $colCodigo = $this->pickColumn($cols, ['codigo_pedido', 'numero_pedido']);
            $colCreatedAt = $this->pickColumn($cols, ['created_at', 'data_criacao', 'data_pedido']);
            $colStatus = $this->pickColumn($cols, ['status', 'status_pedido', 'pedido_status']);
            $colPaymentStatus = $this->pickColumn($cols, ['payment_status', 'status_pagamento']);
            $colValorTotal = $this->pickColumn($cols, ['valor_total', 'total', 'valor', 'amount']);
            $colImpostos = $this->pickColumn($cols, ['valor_impostos', 'impostos']);

            if (!$colAdminCriador) {
                return $resumoBase;
            }

            $where = [];
            $params = [];
            $where[] = 'p.' . $colAdminCriador . ' = :admin_id';
            $params[':admin_id'] = $adminId;

            if ($colOrigem) {
                $where[] = 'LOWER(COALESCE(p.' . $colOrigem . ", '')) = 'manual'";
            }

            // Considerar apenas pedidos pagos
            $paidParts = [];
            if ($colStatus) {
                $paidParts[] = 'LOWER(COALESCE(p.' . $colStatus . ", '')) IN ('pago','paid','approved','aprovado')";
            }
            if ($colPaymentStatus) {
                $paidParts[] = 'LOWER(COALESCE(p.' . $colPaymentStatus . ", '')) IN ('approved','paid','pago','aprovado','confirmed','received','succeeded','success')";
            }
            if (!empty($paidParts)) {
                $where[] = '(' . implode(' OR ', $paidParts) . ')';
            }

            // Excluir pedidos marcados como "já lançado no vendas" (sem comissão)
            $colSemComissao = $this->pickColumn($cols, ['sem_comissao']);
            if ($colSemComissao) {
                $where[] = '(p.' . $colSemComissao . ' IS NULL OR p.' . $colSemComissao . ' = 0)';
            }

            $select = ['p.id'];
            if ($colCodigo) $select[] = 'p.' . $colCodigo . ' AS codigo';
            if ($colCreatedAt) $select[] = 'p.' . $colCreatedAt . ' AS created_at';
            if ($colMoeda) $select[] = 'p.' . $colMoeda . ' AS moeda';
            if ($colValorTotal) $select[] = 'p.' . $colValorTotal . ' AS valor_total';
            if ($colImpostos) $select[] = 'p.' . $colImpostos . ' AS impostos';

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($colCreatedAt ? ('p.' . $colCreatedAt) : 'p.id') . ' DESC';
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $pedidosOut = [];
            foreach ($rows as $r) {
                $pid = (int) ($r['id'] ?? 0);
                if ($pid <= 0) continue;
                $moeda = strtoupper(trim((string) ($r['moeda'] ?? 'BRL')));
                if ($moeda === '') $moeda = 'BRL';
                $fat = (float) ($r['valor_total'] ?? 0);
                $impostos = (float) ($r['impostos'] ?? 0);

                // custo (tolerante): tenta somar custo_unitario * qtd nos itens
                $custo = 0.0;
                try {
                    $temPedidoItens = $this->tableExists('pedido_itens');
                    $temPedidoItems = $this->tableExists('pedido_items');
                    $itensTable = $temPedidoItens ? 'pedido_itens' : ($temPedidoItems ? 'pedido_items' : null);
                    if ($itensTable) {
                        $colsItens = $this->getTableColumns($itensTable);
                        $colPedidoId = $this->pickColumn($colsItens, ['pedido_id']);
                        $colProdutoId = $this->pickColumn($colsItens, ['produto_id']);
                        $colQtd = $this->pickColumn($colsItens, ['quantidade', 'qty']);
                        if ($colPedidoId && $colProdutoId && $colQtd) {
                            // custo do produto (tolerante)
                            $colsProd = $this->getTableColumns('produtos');
                            $colCusto = $this->pickColumn($colsProd, ['preco_custo', 'custo', 'cost_price', 'valor_custo']);
                            if ($colCusto) {
                                $stC = $this->connection->prepare('SELECT SUM(COALESCE(pr.' . $colCusto . ',0) * COALESCE(pi.' . $colQtd . ',0)) AS custo_total FROM ' . $itensTable . ' pi INNER JOIN produtos pr ON pr.id = pi.' . $colProdutoId . ' WHERE pi.' . $colPedidoId . ' = ?');
                                $stC->execute([$pid]);
                                $custo = (float) ($stC->fetchColumn() ?: 0);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $custo = 0.0;
                }

                $liq = $fat - $custo - $impostos;
                $pedidosOut[] = [
                    'id' => $pid,
                    'codigo' => (string) ($r['codigo'] ?? $pid),
                    'created_at' => $r['created_at'] ?? null,
                    'moeda' => $moeda,
                    'faturado' => $fat,
                    'custo' => $custo,
                    'liquido' => $liq,
                ];

                if (!isset($resumoBase['por_moeda'][$moeda])) {
                    $resumoBase['por_moeda'][$moeda] = ['total_faturado' => 0.0, 'total_custo_produtos' => 0.0, 'total_liquido' => 0.0, 'percentual_comissao' => 0.0, 'valor_comissao' => 0.0, 'pedidos' => []];
                }
                $resumoBase['por_moeda'][$moeda]['total_faturado'] += $fat;
                $resumoBase['por_moeda'][$moeda]['total_custo_produtos'] += $custo;
                $resumoBase['por_moeda'][$moeda]['total_liquido'] += $liq;
                $resumoBase['por_moeda'][$moeda]['pedidos'][] = end($pedidosOut);
            }

            $resumoBase['pedidos'] = $pedidosOut;
            // Campos agregados "legado" (mantém compatibilidade com AdminPedidosController)
            // Somar BRL + USD (quando existir)
            $totFat = 0.0;
            $totCus = 0.0;
            $totLiq = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => $t) {
                $totFat += (float) ($t['total_faturado'] ?? 0);
                $totCus += (float) ($t['total_custo_produtos'] ?? 0);
                $totLiq += (float) ($t['total_liquido'] ?? 0);
            }
            $resumoBase['total_faturado'] = $totFat;
            $resumoBase['total_custo_produtos'] = $totCus;
            $resumoBase['total_liquido'] = $totLiq;

            $faixas = $this->getComissaoManualFaixasConfig();
            $resumoBase['faixas'] = $faixas;

            $usdBrlRate = $this->getConfigNumber('sistema', 'usd_brl_rate', 0.0);
            if ($usdBrlRate <= 0) {
                $usdBrlRate = 0.0;
            }

            // Percentual é definido pela soma do faturado (convertido para BRL quando possível)
            $faturadoBrlParaFaixa = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => $t) {
                $m = strtoupper(trim((string) $m));
                $fatMoeda = (float) ($t['total_faturado'] ?? 0);
                if ($m === 'BRL') {
                    $faturadoBrlParaFaixa += $fatMoeda;
                } elseif ($m === 'USD' && $usdBrlRate > 0) {
                    $faturadoBrlParaFaixa += ($fatMoeda * $usdBrlRate);
                }
            }

            $percent = $this->resolvePercentualPorFaixas($faturadoBrlParaFaixa, $faixas);
            $resumoBase['percentual_comissao'] = $percent;

            $valorComissaoTotal = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => &$t) {
                $totalLiquidoMoeda = (float) ($t['total_liquido'] ?? 0);
                $valorComissaoMoeda = max(0.0, $totalLiquidoMoeda) * ($percent / 100.0);
                $t['percentual_comissao'] = $percent;
                $t['valor_comissao'] = $valorComissaoMoeda;
                $valorComissaoTotal += $valorComissaoMoeda;
            }
            unset($t);
            $resumoBase['valor_comissao'] = $valorComissaoTotal;

            return $resumoBase;
        } catch (\Exception $e) {
            return $resumoBase;
        }
    }

    public function getComDetalhes($pedidoId) {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return null;

        $pedido = null;
        try {
            $cols = $this->getTableColumns('pedidos');
            $sql = 'SELECT * FROM pedidos WHERE id = :id';
            if (in_array('deleted_at', $cols, true)) {
                $sql .= ' AND deleted_at IS NULL';
            }
            $sql .= ' LIMIT 1';
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $pedido = null;
        }

        if (!$pedido) return null;

        // Normalizar aliases comuns (status/pagamento) para uso consistente nas views
        try {
            // status do pedido
            $altStatus = null;
            foreach (['status_pedido', 'pedido_status'] as $k) {
                if (array_key_exists($k, $pedido) && $pedido[$k] !== null && $pedido[$k] !== '') {
                    $altStatus = $pedido[$k];
                    break;
                }
            }
            if ($altStatus !== null) {
                $pedido['status'] = $altStatus;
            } elseif (!array_key_exists('status', $pedido) || $pedido['status'] === null || $pedido['status'] === '') {
                $pedido['status'] = '';
            }

            // forma/metodo de pagamento
            if (!array_key_exists('pagamento_metodo', $pedido) || $pedido['pagamento_metodo'] === null || $pedido['pagamento_metodo'] === '') {
                if (array_key_exists('forma_pagamento', $pedido) && $pedido['forma_pagamento'] !== null && $pedido['forma_pagamento'] !== '') {
                    $pedido['pagamento_metodo'] = $pedido['forma_pagamento'];
                }
            }

            // gateway
            if (!array_key_exists('pagamento_gateway', $pedido) || $pedido['pagamento_gateway'] === null || $pedido['pagamento_gateway'] === '') {
                foreach (['payment_gateway', 'gateway'] as $k) {
                    if (array_key_exists($k, $pedido) && $pedido[$k] !== null && $pedido[$k] !== '') {
                        $pedido['pagamento_gateway'] = $pedido[$k];
                        break;
                    }
                }
            }

            // transação
            if (!array_key_exists('pagamento_transacao', $pedido) || $pedido['pagamento_transacao'] === null || $pedido['pagamento_transacao'] === '') {
                foreach (['payment_id', 'transaction_id', 'codigo_transacao'] as $k) {
                    if (array_key_exists($k, $pedido) && $pedido[$k] !== null && $pedido[$k] !== '') {
                        $pedido['pagamento_transacao'] = $pedido[$k];
                        break;
                    }
                }
            }

            // status de pagamento (fonte preferida: pagamento_status, senão payment_status/status_pagamento)
            $altPgStatus = null;
            foreach (['pagamento_status', 'payment_status', 'status_pagamento'] as $k) {
                if (array_key_exists($k, $pedido) && $pedido[$k] !== null && $pedido[$k] !== '') {
                    $altPgStatus = $pedido[$k];
                    break;
                }
            }
            if ($altPgStatus !== null) {
                $pedido['pagamento_status'] = $altPgStatus;
            }

            // manter também payment_status para telas que leem esse campo
            if (!array_key_exists('payment_status', $pedido) || $pedido['payment_status'] === null || $pedido['payment_status'] === '') {
                if (array_key_exists('pagamento_status', $pedido) && $pedido['pagamento_status'] !== null && $pedido['pagamento_status'] !== '') {
                    $pedido['payment_status'] = $pedido['pagamento_status'];
                } elseif (array_key_exists('status_pagamento', $pedido) && $pedido['status_pagamento'] !== null && $pedido['status_pagamento'] !== '') {
                    $pedido['payment_status'] = $pedido['status_pagamento'];
                }
            }

            // data de pagamento
            if (!array_key_exists('pagamento_data', $pedido) || $pedido['pagamento_data'] === null || $pedido['pagamento_data'] === '') {
                foreach (['pago_em', 'paid_at', 'data_pagamento'] as $k) {
                    if (array_key_exists($k, $pedido) && $pedido[$k] !== null && $pedido[$k] !== '') {
                        $pedido['pagamento_data'] = $pedido[$k];
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // Normalizar totais + endereço para o formato esperado nas views do usuário
        try {
            $colsPedido = $this->getTableColumns('pedidos');

            // Tracking / etiqueta
            try {
                $pedido['tracking_code'] = $pedido['tracking_code'] ?? null;
                $pedido['tracking_source'] = $pedido['tracking_source'] ?? null;
                $pedido['tracking_label_url'] = $pedido['tracking_label_url'] ?? null;

                $colTracking = $this->pickColumn($colsPedido, ['tracking_code', 'codigo_rastreio', 'rastreamento', 'tracking']);
                $trk = '';
                if ($colTracking && array_key_exists($colTracking, $pedido)) {
                    $trk = trim((string) ($pedido[$colTracking] ?? ''));
                }

                $trkFonte = '';
                $trkUrl = '';

                if ($trk !== '') {
                    $trkFonte = 'Pedido';
                }

                if ($trk === '') {
                    if ($this->tableExists('shipstation_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT tracking_number, label_url, carrier_code FROM shipstation_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([$pedidoId]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $t = trim((string) ($row['tracking_number'] ?? ''));
                            if ($t !== '') {
                                $trk = $t;
                                $trkFonte = 'ShipStation' . (!empty($row['carrier_code']) ? (' (' . trim((string) $row['carrier_code']) . ')') : '');
                                $trkUrl = trim((string) ($row['label_url'] ?? ''));
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($trk === '') {
                    if ($this->tableExists('stamps_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT tracking_number, label_url, carrier FROM stamps_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([$pedidoId]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $t = trim((string) ($row['tracking_number'] ?? ''));
                            if ($t !== '') {
                                $trk = $t;
                                $trkFonte = 'Stamps' . (!empty($row['carrier']) ? (' (' . trim((string) $row['carrier']) . ')') : '');
                                $trkUrl = trim((string) ($row['label_url'] ?? ''));
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($trk === '') {
                    if ($this->tableExists('correios_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT codigo_etiqueta FROM correios_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([$pedidoId]);
                            $t = trim((string) ($st->fetchColumn() ?: ''));
                            if ($t !== '') {
                                $trk = $t;
                                $trkFonte = 'Correios';
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($trk === '') {
                    if ($this->tableExists('correios_packet_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT tracking_number FROM correios_packet_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([$pedidoId]);
                            $t = trim((string) ($st->fetchColumn() ?: ''));
                            if ($t !== '') {
                                $trk = $t;
                                $trkFonte = 'Correios Mundial (PACKET)';
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($trk === '') {
                    if ($this->tableExists('remessa_janela_pedidos')) {
                        try {
                            $st = $this->connection->prepare('SELECT courier_tracking_number, wexpress_tracking_number, wexpress_status FROM remessa_janela_pedidos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([$pedidoId]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $courier = trim((string) ($row['courier_tracking_number'] ?? ''));
                            $wx = trim((string) ($row['wexpress_tracking_number'] ?? ''));
                            $wxStatus = trim((string) ($row['wexpress_status'] ?? ''));
                            if ($courier !== '' || $wx !== '') {
                                $trk = $courier !== '' ? $courier : $wx;
                                $trkFonte = 'W-Express' . ($wxStatus !== '' ? (' (' . $wxStatus . ')') : '');
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($trk !== '') {
                    $pedido['tracking_code'] = $trk;
                    $pedido['tracking_source'] = $trkFonte !== '' ? $trkFonte : null;
                    $pedido['tracking_label_url'] = $trkUrl !== '' ? $trkUrl : null;
                }
            } catch (\Exception $e) {
            }

            $moeda = strtoupper((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL')));
            $moeda = trim($moeda);
            if ($moeda === '') {
                $moeda = 'BRL';
            }

            // Normalizar aliases comuns de moeda
            if (in_array($moeda, ['US$', 'USD$'], true) || str_contains($moeda, 'DOLAR') || str_contains($moeda, 'DÓLAR')) {
                $moeda = 'USD';
            }
            if (in_array($moeda, ['R$', 'BRL$'], true) || str_contains($moeda, 'REAL') || str_contains($moeda, 'REAIS')) {
                $moeda = 'BRL';
            }

            $pedido['moeda'] = $moeda;

            $taxaConversao = null;
            foreach (['taxa_conversao', 'exchange_rate', 'conversion_rate'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $taxaConversao = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($taxaConversao === null || $taxaConversao <= 0) {
                $taxaConversao = 1.0;
            }

            // Se o pedido é BRL mas a taxa veio como 1.0, tentar buscar na tabela configuracoes_moeda
            if ($moeda === 'BRL' && $taxaConversao <= 1.01) {
                try {
                    // 1) Tentar configuracoes_sistema/configuracoes/settings/config (chave/valor)
                    $rateFromConfig = 0.0;
                    foreach (['configuracoes_sistema', 'configuracoes', 'settings', 'config'] as $tbl) {
                        if (!$this->tableExists($tbl)) {
                            continue;
                        }
                        $colsCfg = $this->getTableColumns($tbl);
                        $colChave = $this->pickColumn($colsCfg, ['chave', 'key', 'nome', 'config_key', 'slug', 'parametro']);
                        $colValor = $this->pickColumn($colsCfg, ['valor', 'value', 'conteudo', 'content', 'config_value']);
                        if (!$colChave || !$colValor) {
                            continue;
                        }

                        // formatos aceitos
                        $keys = ['usd_brl_rate', 'sistema_usd_brl_rate'];
                        foreach ($keys as $k) {
                            try {
                                $stCfg = $this->connection->prepare('SELECT ' . $colValor . ' AS v FROM ' . $tbl . ' WHERE ' . $colChave . ' = ? LIMIT 1');
                                $stCfg->execute([$k]);
                                $val = $stCfg->fetchColumn();
                                $v = (float) str_replace(',', '.', (string) ($val ?? '0'));
                                if ($v > 1.01) {
                                    $rateFromConfig = $v;
                                    break 2;
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }
                    if ($rateFromConfig > 1.01) {
                        $taxaConversao = $rateFromConfig;
                    }

                    if ($this->tableExists('configuracoes_moeda')) {
                        $stTx = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                        $stTx->execute();
                        $txRow = $stTx->fetch(\PDO::FETCH_ASSOC);
                        $tx = (float) ($txRow['taxa_conversao'] ?? 0);
                        if ($tx > 1.01) {
                            $taxaConversao = $tx;
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            $enderecoEntregaId = (int) ($pedido['endereco_entrega_id'] ?? 0);

            // Fallback: se não veio endereço no pedido, tentar puxar endereço principal do usuário (apenas para exibição)
            if (
                $enderecoEntregaId <= 0
                && $this->tableExists('enderecos')
                && (trim((string) ($pedido['endereco_entrega'] ?? '')) === '')
                && (trim((string) ($pedido['cep_entrega'] ?? '')) === '')
            ) {
                try {
                    $uid = (int) ($pedido['usuario_id'] ?? 0);
                    if ($uid > 0) {
                        $colsEnd = $this->getTableColumns('enderecos');
                        if (in_array('usuario_id', $colsEnd, true)) {
                            $orderBy = 'id DESC';
                            if (in_array('principal', $colsEnd, true)) {
                                $orderBy = 'principal DESC, id DESC';
                            }
                            $stE = $this->connection->prepare('SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY ' . $orderBy . ' LIMIT 1');
                            $stE->execute([$uid]);
                            $rowE = $stE->fetch(\PDO::FETCH_ASSOC);
                            if (is_array($rowE) && !empty($rowE)) {
                                $pedido['endereco_entrega'] = $rowE['endereco'] ?? ($rowE['logradouro'] ?? ($pedido['endereco_entrega'] ?? null));
                                $pedido['numero_entrega'] = $rowE['numero'] ?? ($pedido['numero_entrega'] ?? null);
                                $pedido['complemento_entrega'] = $rowE['complemento'] ?? ($pedido['complemento_entrega'] ?? null);
                                $pedido['bairro_entrega'] = $rowE['bairro'] ?? ($pedido['bairro_entrega'] ?? null);
                                $pedido['cidade_entrega'] = $rowE['cidade'] ?? ($pedido['cidade_entrega'] ?? null);
                                $pedido['estado_entrega'] = $rowE['estado'] ?? ($pedido['estado_entrega'] ?? null);
                                $pedido['cep_entrega'] = $rowE['cep'] ?? ($pedido['cep_entrega'] ?? null);

                                $pedido['endereco'] = $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? null);
                                $pedido['numero'] = $pedido['numero_entrega'] ?? ($pedido['numero'] ?? null);
                                $pedido['complemento'] = $pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? null);
                                $pedido['bairro'] = $pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? null);
                                $pedido['cidade'] = $pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? null);
                                $pedido['estado'] = $pedido['estado_entrega'] ?? ($pedido['estado'] ?? null);
                                $pedido['cep'] = $pedido['cep_entrega'] ?? ($pedido['cep'] ?? null);
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            $pedido['taxa_conversao'] = $taxaConversao;

            $deveConverterUSDParaBRL = false;
            if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                // Preferir sinal explícito do schema, quando existir
                $moedaOriginal = '';
                foreach (['moeda_original', 'currency_original', 'original_currency'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $moedaOriginal = strtoupper(trim((string) ($pedido[$c] ?? '')));
                        break;
                    }
                }

                // Fallback: alguns schemas mantêm moeda=BRL, mas a coluna currency pode indicar USD
                // (nesses casos os valores frequentemente estão em USD e precisam ser convertidos)
                if ($moedaOriginal === '' && array_key_exists('currency', $pedido)) {
                    $cur = strtoupper(trim((string) ($pedido['currency'] ?? '')));
                    if ($cur !== '' && $cur !== $moeda) {
                        $moedaOriginal = $cur;
                    }
                }

                // Normalizar aliases
                if (in_array($moedaOriginal, ['US$', 'USD$'], true) || str_contains($moedaOriginal, 'DOLAR') || str_contains($moedaOriginal, 'DÓLAR')) {
                    $moedaOriginal = 'USD';
                }
                if (in_array($moedaOriginal, ['R$', 'BRL$'], true) || str_contains($moedaOriginal, 'REAL') || str_contains($moedaOriginal, 'REAIS')) {
                    $moedaOriginal = 'BRL';
                }

                if ($moedaOriginal === 'USD') {
                    $deveConverterUSDParaBRL = true;
                }
            }

            // Se identificou que os valores estão em USD, mas não há taxa válida, não pode exibir como BRL.
            // Nessa situação, preferir exibir como USD para evitar “valor em dólar com prefixo de real”.
            if ($moeda === 'BRL' && $deveConverterUSDParaBRL && $taxaConversao <= 1.01) {
                $moeda = 'USD';
                $pedido['moeda'] = 'USD';
                $pedido['__forced_usd_display_due_missing_rate'] = true;
                $deveConverterUSDParaBRL = false;
            }

            $subtotalProdutos = null;
            foreach (['subtotal_produtos', 'subtotal'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $subtotalProdutos = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($subtotalProdutos === null) {
                $subtotalProdutos = 0.0;
            }

            // Se houver colunas específicas em BRL, usar como fonte em pedidos BRL
            $subtotalProdutosBRL = null;
            if ($moeda === 'BRL') {
                foreach (['subtotal_produtos_brl', 'subtotal_brl'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $v = (float) ($pedido[$c] ?? 0);
                        if ($v > 0) {
                            $subtotalProdutosBRL = $v;
                            break;
                        }
                    }
                }
            }

            $valorFrete = null;
            foreach (['valor_frete', 'frete', 'frete_manual'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $valorFrete = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($valorFrete === null) {
                $valorFrete = 0.0;
            }

            $valorFreteBRL = null;
            if ($moeda === 'BRL') {
                foreach (['valor_frete_brl', 'frete_brl'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $v = (float) ($pedido[$c] ?? 0);
                        if ($v > 0) {
                            $valorFreteBRL = $v;
                            break;
                        }
                    }
                }
            }

            $taxaServico = null;
            foreach (['taxa_servico', 'servicos', 'service_fee'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $taxaServico = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($taxaServico === null) {
                $taxaServico = 0.0;
            }

            $taxaServicoBRL = null;
            if ($moeda === 'BRL') {
                foreach (['taxa_servico_brl', 'servicos_brl'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $v = (float) ($pedido[$c] ?? 0);
                        if ($v > 0) {
                            $taxaServicoBRL = $v;
                            break;
                        }
                    }
                }
            }

            $valorImpostos = null;
            foreach (['valor_impostos', 'impostos', 'taxes'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $valorImpostos = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($valorImpostos === null) {
                $valorImpostos = 0.0;
            }

            $valorImpostosBRL = null;
            if ($moeda === 'BRL') {
                foreach (['valor_impostos_brl', 'impostos_brl', 'taxes_brl'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $v = (float) ($pedido[$c] ?? 0);
                        if ($v > 0) {
                            $valorImpostosBRL = $v;
                            break;
                        }
                    }
                }
            }

            $valorTotal = null;
            foreach (['valor_total', 'total', 'valor', 'amount'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $valorTotal = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($valorTotal === null || $valorTotal <= 0) {
                $valorTotal = $subtotalProdutos + $valorFrete + $taxaServico + $valorImpostos;
            }

            $valorTotalBRL = null;
            if ($moeda === 'BRL') {
                foreach (['valor_total_brl', 'total_brl', 'amount_brl'] as $c) {
                    if (array_key_exists($c, $pedido)) {
                        $v = (float) ($pedido[$c] ?? 0);
                        if ($v > 0) {
                            $valorTotalBRL = $v;
                            break;
                        }
                    }
                }
            }

            if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                // Preferência: valores explicitamente em BRL quando existirem
                if ($valorTotalBRL !== null) {
                    $valorTotal = $valorTotalBRL;
                }

                if ($subtotalProdutosBRL !== null) {
                    $subtotalProdutos = $subtotalProdutosBRL;
                }

                if ($valorFreteBRL !== null) {
                    $valorFrete = $valorFreteBRL;
                }

                if ($taxaServicoBRL !== null) {
                    $taxaServico = $taxaServicoBRL;
                }

                if ($valorImpostosBRL !== null) {
                    $valorImpostos = $valorImpostosBRL;
                }
            }

            $pedido['subtotal_produtos'] = $subtotalProdutos;
            $pedido['valor_frete'] = $valorFrete;
            $pedido['taxa_servico'] = $taxaServico;
            $pedido['valor_impostos'] = $valorImpostos;
            $pedido['valor_total'] = $valorTotal;

            // Campos esperados por views (checkout/conclusao.php e outras)
            $pedido['subtotal'] = $subtotalProdutos;
            $pedido['frete'] = $valorFrete;
            $pedido['impostos'] = $valorImpostos;
            $pedido['servicos'] = $taxaServico;
            $pedido['total'] = $valorTotal;

            $pedido['__converted_to_brl'] = ($moeda === 'BRL' && $taxaConversao > 1.01 && $deveConverterUSDParaBRL);

            // Endereço de entrega: aceitar diferentes nomes de colunas
            $endereco = $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ($pedido['endereco_envio'] ?? null));
            $numero = $pedido['numero_entrega'] ?? ($pedido['numero'] ?? ($pedido['numero_envio'] ?? null));
            $complemento = $pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? null);
            $bairro = $pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? null);
            $cidade = $pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? null);
            $estado = $pedido['estado_entrega'] ?? ($pedido['estado'] ?? null);
            $cep = $pedido['cep_entrega'] ?? ($pedido['cep'] ?? null);

            $pedido['endereco_entrega'] = $endereco;
            $pedido['numero_entrega'] = $numero;
            $pedido['complemento_entrega'] = $complemento;
            $pedido['bairro_entrega'] = $bairro;
            $pedido['cidade_entrega'] = $cidade;
            $pedido['estado_entrega'] = $estado;
            $pedido['cep_entrega'] = $cep;

            // Aliases usados por views legadas (ex: checkout/conclusao.php)
            $pedido['endereco'] = $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? null);
            $pedido['numero'] = $pedido['numero_entrega'] ?? ($pedido['numero'] ?? null);
            $pedido['complemento'] = $pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? null);
            $pedido['bairro'] = $pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? null);
            $pedido['cidade'] = $pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? null);
            $pedido['estado'] = $pedido['estado_entrega'] ?? ($pedido['estado'] ?? null);
            $pedido['cep'] = $pedido['cep_entrega'] ?? ($pedido['cep'] ?? null);

            // Se houver endereco_entrega_id, buscar dados completos em enderecos
            $enderecoEntregaId = (int) ($pedido['endereco_entrega_id'] ?? 0);
            if ($enderecoEntregaId > 0 && $this->tableExists('enderecos')) {
                try {
                    $colsEnd = $this->getTableColumns('enderecos');
                    $colId = $this->pickColumn($colsEnd, ['id']);
                    if ($colId) {
                        $stE = $this->connection->prepare('SELECT * FROM enderecos WHERE id = ? LIMIT 1');
                        $stE->execute([$enderecoEntregaId]);
                        $rowE = $stE->fetch(\PDO::FETCH_ASSOC);
                        if (is_array($rowE) && !empty($rowE)) {
                            $pedido['endereco_entrega'] = $rowE['endereco'] ?? ($rowE['logradouro'] ?? ($pedido['endereco_entrega'] ?? null));
                            $pedido['numero_entrega'] = $rowE['numero'] ?? ($pedido['numero_entrega'] ?? null);
                            $pedido['complemento_entrega'] = $rowE['complemento'] ?? ($pedido['complemento_entrega'] ?? null);
                            $pedido['bairro_entrega'] = $rowE['bairro'] ?? ($pedido['bairro_entrega'] ?? null);
                            $pedido['cidade_entrega'] = $rowE['cidade'] ?? ($pedido['cidade_entrega'] ?? null);
                            $pedido['estado_entrega'] = $rowE['estado'] ?? ($pedido['estado_entrega'] ?? null);
                            $pedido['cep_entrega'] = $rowE['cep'] ?? ($pedido['cep_entrega'] ?? null);

                            // País do endereço
                            $paisEnd = $rowE['pais'] ?? ($rowE['country'] ?? ($rowE['country_code'] ?? ($rowE['pais_code'] ?? null)));
                            if ($paisEnd !== null && trim((string) $paisEnd) !== '') {
                                $pedido['pais_entrega'] = trim((string) $paisEnd);
                                $pedido['pais'] = trim((string) $paisEnd);
                            }

                            // atualizar aliases após buscar endereço
                            $pedido['endereco'] = $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? null);
                            $pedido['numero'] = $pedido['numero_entrega'] ?? ($pedido['numero'] ?? null);
                            $pedido['complemento'] = $pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? null);
                            $pedido['bairro'] = $pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? null);
                            $pedido['cidade'] = $pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? null);
                            $pedido['estado'] = $pedido['estado_entrega'] ?? ($pedido['estado'] ?? null);
                            $pedido['cep'] = $pedido['cep_entrega'] ?? ($pedido['cep'] ?? null);
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            // Cliente: preencher campos esperados pela conclusão
            // Importante: priorizar dados informados no checkout (salvos no pedido) antes de usar dados atuais do usuário.
            if (empty($pedido['cliente_nome']) || empty($pedido['cliente_email']) || empty($pedido['cliente_telefone']) || empty($pedido['cliente_cpf_cnpj'])) {
                // Fallback para colunas do próprio pedido (antes de puxar do usuário)
                if (empty($pedido['cliente_nome'])) {
                    $pedido['cliente_nome'] = $pedido['nome'] ?? ($pedido['customer_name'] ?? '');
                }
                if (empty($pedido['cliente_email'])) {
                    $pedido['cliente_email'] = $pedido['email'] ?? ($pedido['customer_email'] ?? '');
                }
                if (empty($pedido['cliente_telefone'])) {
                    $pedido['cliente_telefone'] = $pedido['telefone'] ?? ($pedido['customer_phone'] ?? '');
                }

                $uid = (int) ($pedido['usuario_id'] ?? 0);
                if ($uid > 0 && $this->tableExists('usuarios')) {
                    try {
                        $colsU = $this->getTableColumns('usuarios');
                        $colNome = $this->pickColumn($colsU, ['nome', 'name', 'full_name']);
                        $colEmail = $this->pickColumn($colsU, ['email']);
                        $colTel = $this->pickColumn($colsU, ['telefone', 'phone', 'celular', 'mobile', 'whatsapp']);
                        $colDoc = $this->pickColumn($colsU, ['cpf_cnpj', 'cpfCnpj', 'documento', 'document', 'cpf', 'cnpj']);
                        $colSuite = $this->pickColumn($colsU, ['suite']);
                        $sel = ['id'];
                        if ($colNome) $sel[] = $colNome . ' AS nome';
                        if ($colEmail) $sel[] = $colEmail . ' AS email';
                        if ($colTel) $sel[] = $colTel . ' AS telefone';
                        if ($colDoc) $sel[] = $colDoc . ' AS documento';
                        if ($colSuite) $sel[] = $colSuite . ' AS suite';
                        $stU = $this->connection->prepare('SELECT ' . implode(', ', $sel) . ' FROM usuarios WHERE id = ? LIMIT 1');
                        $stU->execute([$uid]);
                        $rowU = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
                        if (empty($pedido['cliente_nome']) && !empty($rowU['nome'])) {
                            $pedido['cliente_nome'] = $rowU['nome'];
                        }
                        if (empty($pedido['cliente_email']) && !empty($rowU['email'])) {
                            $pedido['cliente_email'] = $rowU['email'];
                        }
                        if (empty($pedido['cliente_telefone']) && !empty($rowU['telefone'])) {
                            $pedido['cliente_telefone'] = $rowU['telefone'];
                        }
                        if (empty($pedido['cliente_cpf_cnpj']) && !empty($rowU['documento'])) {
                            $pedido['cliente_cpf_cnpj'] = $rowU['documento'];
                        }
                        if (empty($pedido['cliente_suite']) && !empty($rowU['suite'])) {
                            $pedido['cliente_suite'] = $rowU['suite'];
                        }
                    } catch (\Exception $e) {
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $pedido['items'] = [];
        $pedido['historico'] = [];

        // Itens do pedido (tolerante a schemas)
        try {
            $temPedidoItens = $this->tableExists('pedido_itens');
            $temPedidoItems = $this->tableExists('pedido_items');
            $itensTable = $temPedidoItens ? 'pedido_itens' : ($temPedidoItems ? 'pedido_items' : 'pedido_itens');

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
            }

            $colsItens = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE ' . $itensTable);
                $colsItens = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsItens = [];
            }

            $ncmCol = null;
            try {
                if ($this->tableExists('produtos')) {
                    $colsProd = $this->getTableColumns('produtos');
                    foreach (['ncm', 'tariff_code', 'ncm_code', 'codigo_ncm', 'ncm_produto'] as $c) {
                        if (is_array($colsProd) && in_array($c, $colsProd, true)) {
                            $ncmCol = $c;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                $ncmCol = null;
            }

            $pesoCol = null;
            try {
                if ($this->tableExists('produtos')) {
                    $colsProd = $this->getTableColumns('produtos');
                    foreach (['weight', 'peso', 'peso_kg', 'product_weight'] as $c) {
                        if (is_array($colsProd) && in_array($c, $colsProd, true)) {
                            $pesoCol = $c;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                $pesoCol = null;
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
            $colProdutoVariacaoId = $pick(['produto_variacao_id']);
            $colQtd = $pick(['quantidade', 'qty']);
            $colPrecoUnit = $pick(['preco_unitario', 'valor_unitario', 'price', 'preco']);
            $colValorUnitAlt = null;
            if ($colPrecoUnit === 'preco_unitario' && $pick(['valor_unitario']) !== null) {
                $colValorUnitAlt = 'valor_unitario';
            }
            $colSubtotal = $pick(['subtotal']);
            $colNomeProduto = $pick(['nome_produto', 'produto_nome', 'nome']);
            $colSku = $pick(['nome_produto_sku', 'sku']);
            $colUrlOriginalItem = $pick(['url_original', 'url', 'link', 'produto_url', 'url_produto', 'original_url']);

            if (!$colPedidoId) {
                throw new \Exception('Tabela de itens sem pedido_id');
            }

            $selectParts = [];
            if ($pick(['id']) !== null) $selectParts[] = 'pi.id';
            if ($colProdutoId) $selectParts[] = 'pi.' . $colProdutoId . ' AS produto_id';
            if ($colProdutoVariacaoId) $selectParts[] = 'pi.' . $colProdutoVariacaoId . ' AS produto_variacao_id';
            if ($colQtd) $selectParts[] = 'pi.' . $colQtd . ' AS quantidade';
            if ($colPrecoUnit) $selectParts[] = 'pi.' . $colPrecoUnit . ' AS preco_unitario';
            if ($colValorUnitAlt) $selectParts[] = 'pi.' . $colValorUnitAlt . ' AS valor_unitario_alt';
            if ($colSubtotal) $selectParts[] = 'pi.' . $colSubtotal . ' AS subtotal';
            if ($colNomeProduto) $selectParts[] = 'pi.' . $colNomeProduto . ' AS nome_produto';
            if ($colSku) $selectParts[] = 'pi.' . $colSku . ' AS nome_produto_sku';
            if ($colUrlOriginalItem) $selectParts[] = 'pi.' . $colUrlOriginalItem . ' AS url_original';
            if ($pick(['created_at']) !== null) $selectParts[] = 'pi.created_at';

            // Colunas de oferta gratuita
            if ($pick(['is_free_offer']) !== null) $selectParts[] = 'pi.is_free_offer';
            if ($pick(['free_offer_original_price']) !== null) $selectParts[] = 'pi.free_offer_original_price';
            if ($pick(['free_offer_exempt_tax']) !== null) $selectParts[] = 'pi.free_offer_exempt_tax';
            if ($pick(['free_offer_tax_teorico']) !== null) $selectParts[] = 'pi.free_offer_tax_teorico';
            if ($pick(['free_offer_taxa_servico']) !== null) $selectParts[] = 'pi.free_offer_taxa_servico';

            // Colunas de valor informado pelo cliente (assessoria)
            if ($pick(['valor_informado_cliente']) !== null) $selectParts[] = 'pi.valor_informado_cliente';
            if ($pick(['observacao_cliente']) !== null) $selectParts[] = 'pi.observacao_cliente';
            if ($pick(['valor_real_conferencia']) !== null) $selectParts[] = 'pi.valor_real_conferencia';
            if ($pick(['conferido_em']) !== null) $selectParts[] = 'pi.conferido_em';
            if ($pick(['conferido_por']) !== null) $selectParts[] = 'pi.conferido_por';

            // Colunas de pacote/redirecionamento
            if ($pick(['tipo_item']) !== null) $selectParts[] = 'pi.tipo_item';
            if ($pick(['pacote_id']) !== null) $selectParts[] = 'pi.pacote_id';
            if ($pick(['foto_url']) !== null) $selectParts[] = 'pi.foto_url';
            if ($pick(['nome_item']) !== null) $selectParts[] = 'pi.nome_item';
            if ($pick(['declaration_value']) !== null) $selectParts[] = 'pi.declaration_value';
            if ($pick(['comprovante_url']) !== null) $selectParts[] = 'pi.comprovante_url';
            if ($pick(['produto_ncm']) !== null) $selectParts[] = 'pi.produto_ncm';

            // Fallback: buscar nome do produto na tabela produtos quando nome_produto do item estiver vazio
            if ($colProdutoId) {
                try {
                    $colsProdNome = $this->getTableColumns('produtos');
                    $colNomeProd = $this->pickColumn($colsProdNome, ['name', 'nome', 'titulo', 'title']);
                    if ($colNomeProd) {
                        $selectParts[] = '(SELECT pr2.' . $colNomeProd . ' FROM produtos pr2 WHERE pr2.id = pi.' . $colProdutoId . ' LIMIT 1) AS nome_produto_fallback';
                    }
                } catch (\Exception $e) {}
            }

            if ($ncmCol && $colProdutoId) {
                // Para produtos normais: JOIN na tabela produtos
                // Para pacotes (produto_id >= 999990): usar ncm da própria coluna do item
                if ($pick(['ncm']) !== null) {
                    $selectParts[] = "COALESCE(pi.ncm, (SELECT pr." . $ncmCol . " FROM produtos pr WHERE pr.id = pi." . $colProdutoId . " AND pi." . $colProdutoId . " < 999990 LIMIT 1), '') AS ncm";
                } else {
                    $selectParts[] = '(SELECT pr.' . $ncmCol . ' FROM produtos pr WHERE pr.id = pi.' . $colProdutoId . ' LIMIT 1) AS ncm';
                }
            } else {
                $selectParts[] = "'' AS ncm";
            }

            if ($pesoCol && $colProdutoId) {
                // Para pacotes (produto_id >= 999990): usar peso_kg da própria coluna do item se existir
                if ($pick(['peso_kg']) !== null) {
                    $selectParts[] = "COALESCE(pi.peso_kg, (SELECT pr." . $pesoCol . " FROM produtos pr WHERE pr.id = pi." . $colProdutoId . " AND pi." . $colProdutoId . " < 999990 LIMIT 1)) AS peso_kg";
                } else {
                    $selectParts[] = '(SELECT pr.' . $pesoCol . ' FROM produtos pr WHERE pr.id = pi.' . $colProdutoId . ' LIMIT 1) AS peso_kg';
                }
            } else {
                $selectParts[] = 'NULL AS peso_kg';
            }
            $selectParts[] = "(SELECT pf.nome_arquivo FROM produto_fotos pf WHERE pf.produto_id = pi." . ($colProdutoId ?: 'produto_id') . " ORDER BY pf.principal DESC, pf.ordem ASC LIMIT 1) as imagem_principal";

            // URL original do produto (fallback via produtos)
            if (!$colUrlOriginalItem && $this->tableExists('produtos')) {
                try {
                    $colsProd = $this->getTableColumns('produtos');
                    $colUrlProduto = $this->pickColumn($colsProd, ['url_original', 'url', 'link', 'produto_url', 'url_produto', 'original_url', 'url_externa']);
                    if ($colUrlProduto) {
                        $selectParts[] = "(SELECT p." . $colUrlProduto . " FROM produtos p WHERE p.id = pi." . ($colProdutoId ?: 'produto_id') . " LIMIT 1) AS url_original";
                    }
                } catch (\Exception $e) {
                }
            }

            $sqlItens = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $itensTable . ' pi WHERE pi.' . $colPedidoId . ' = :id ORDER BY pi.id';
            $stmtItens = $this->connection->prepare($sqlItens);
            $stmtItens->execute([':id' => $pedidoId]);
            $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Fallback de imagem via tabela produtos (quando não há produto_fotos)
            $stmtProdImg = null;
            try {
                if ($this->tableExists('produtos')) {
                    $colsProd = $this->getTableColumns('produtos');
                    $colImgProd = $this->pickColumn($colsProd, ['foto_principal', 'capa', 'imagem', 'image']);
                    if ($colImgProd) {
                        $stmtProdImg = $this->connection->prepare('SELECT ' . $colImgProd . ' AS imagem FROM produtos WHERE id = ? LIMIT 1');
                    }
                }
            } catch (\Exception $e) {
                $stmtProdImg = null;
            }

            // Descrições de variação
            $variacaoDescById = [];
            if (!empty($itens) && $colProdutoVariacaoId) {
                $pvIds = [];
                foreach ($itens as $it) {
                    $pvi = (int) ($it['produto_variacao_id'] ?? 0);
                    if ($pvi > 0) {
                        $pvIds[$pvi] = true;
                    }
                }
                $pvIds = array_keys($pvIds);
                if (!empty($pvIds)) {
                    try {
                        $in = implode(',', array_fill(0, count($pvIds), '?'));
                        $sqlVar = '
                            SELECT pvi.produto_variacao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor
                            FROM produto_variacao_itens pvi
                            INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                            INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                            WHERE pvi.produto_variacao_id IN (' . $in . ')
                            ORDER BY pvi.produto_variacao_id ASC, vt.nome ASC, vo.valor ASC
                        ';
                        $stVar = $this->connection->prepare($sqlVar);
                        $stVar->execute($pvIds);
                        $rows = $stVar->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        $tmpPairs = [];
                        foreach ($rows as $r) {
                            $vid = (int) ($r['produto_variacao_id'] ?? 0);
                            if ($vid <= 0) continue;
                            $tn = (string) ($r['tipo_nome'] ?? '');
                            $ov = (string) ($r['opcao_valor'] ?? '');
                            if ($tn === '' || $ov === '') continue;
                            if (!isset($tmpPairs[$vid])) $tmpPairs[$vid] = [];
                            $tmpPairs[$vid][] = $tn . '=' . $ov;
                        }
                        foreach ($tmpPairs as $vid => $parts) {
                            $variacaoDescById[(int) $vid] = implode(' / ', $parts);
                        }
                    } catch (\Exception $e) {
                        $variacaoDescById = [];
                    }
                }
            }

            $sumItensSubtotal = 0.0;

            foreach ($itens as &$item) {
                $item['referencia'] = $item['referencia'] ?? ($item['nome_produto_sku'] ?? '');
                $img = '';
                if (array_key_exists('imagem_principal', $item)) {
                    $img = trim((string) ($item['imagem_principal'] ?? ''));
                }
                if ($img === '' && $stmtProdImg && !empty($item['produto_id'])) {
                    try {
                        $stmtProdImg->execute([(int) $item['produto_id']]);
                        $img = trim((string) ($stmtProdImg->fetchColumn() ?: ''));
                    } catch (\Exception $e) {
                        $img = '';
                    }
                }
                $item['imagem'] = ($img !== '') ? $img : 'placeholder.jpg';

                // Para itens de pacote/redirecionamento, usar foto_url da tabela de itens
                $tipoItemPedido = $item['tipo_item'] ?? 'produto';
                if (($tipoItemPedido === 'pacote_redirecionamento' || (int) ($item['produto_id'] ?? 0) >= 999990) && $item['imagem'] === 'placeholder.jpg') {
                    $fotoUrlItem = trim((string) ($item['foto_url'] ?? ''));
                    if ($fotoUrlItem !== '') {
                        $item['imagem'] = $fotoUrlItem;
                    }
                }

                // Para itens de pacote, usar nome_item se nome_produto estiver vazio ou genérico
                if (($tipoItemPedido === 'pacote_redirecionamento' || (int) ($item['produto_id'] ?? 0) >= 999990)) {
                    $nomeAtual = trim((string) ($item['nome_produto'] ?? ''));
                    if ($nomeAtual === '' || strpos($nomeAtual, 'Produto #') === 0) {
                        $nomeFallback = trim((string) ($item['nome_item'] ?? ($item['nome'] ?? '')));
                        if ($nomeFallback !== '') {
                            $item['nome_produto'] = $nomeFallback;
                        } else {
                            // Buscar do pacote diretamente
                            $pacIdItem = (int) ($item['pacote_id'] ?? 0);
                            if ($pacIdItem > 0) {
                                try {
                                    $stPN = $this->connection->prepare('SELECT nome FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                                    $stPN->execute([$pacIdItem]);
                                    $nomeP = (string) ($stPN->fetchColumn() ?: '');
                                    if ($nomeP !== '') $item['nome_produto'] = $nomeP;
                                } catch (\Throwable $e) {}
                            }
                        }
                    }
                }
                if ((float) ($item['preco_unitario'] ?? 0) <= 0 && isset($item['valor_unitario_alt'])) {
                    $vuAlt = (float) ($item['valor_unitario_alt'] ?? 0);
                    if ($vuAlt > 0) {
                        $item['preco_unitario'] = $vuAlt;
                    }
                }
                // Para pacotes de redirecionamento: usar declaration_value como preço se preco_unitario é 0
                if ((float) ($item['preco_unitario'] ?? 0) <= 0 && !empty($item['declaration_value'])) {
                    $item['preco_unitario'] = (float) $item['declaration_value'];
                    $item['subtotal'] = (float) $item['declaration_value'] * (int) ($item['quantidade'] ?? 1);
                }
                if (!array_key_exists('ncm', $item) || $item['ncm'] === null) {
                    $item['ncm'] = '';
                }
                if (!isset($item['url_original']) || $item['url_original'] === null) {
                    $item['url_original'] = '';
                }
                $item['url_original'] = trim((string) $item['url_original']);
                $pid = (int) ($item['produto_id'] ?? 0);
                $nomeAtual = trim((string) ($item['nome_produto'] ?? ''));
                $ehFallbackGenerico = $nomeAtual === '' || ($pid > 0 && $nomeAtual === 'Produto #' . $pid) || $nomeAtual === 'Produto';
                if ($ehFallbackGenerico) {
                    $fallbackNome = trim((string) ($item['nome_produto_fallback'] ?? ''));
                    $item['nome_produto'] = $fallbackNome !== '' ? $fallbackNome : ($nomeAtual !== '' ? $nomeAtual : ($pid > 0 ? ('Produto #' . $pid) : 'Produto'));
                }

                // Alias usado por checkout/conclusao.php
                if (empty($item['nome']) && !empty($item['nome_produto'])) {
                    $item['nome'] = $item['nome_produto'];
                }

                $pvId = (int) ($item['produto_variacao_id'] ?? 0);
                if ($pvId > 0) {
                    $desc = (string) ($variacaoDescById[$pvId] ?? '');
                    $item['variacao_descricao'] = $desc !== '' ? $desc : null;
                    $item['variacao_label'] = $desc;
                    if ($desc !== '') {
                        $attrs = [];
                        foreach (explode(' / ', $desc) as $part) {
                            $part = trim($part);
                            if ($part === '') continue;
                            $p = explode('=', $part, 2);
                            if (count($p) === 2) {
                                $k = trim((string) $p[0]);
                                $v = trim((string) $p[1]);
                                if ($k !== '' && $v !== '') {
                                    $attrs[$k] = $v;
                                }
                            }
                        }
                        $item['variacao_atributos'] = $attrs;
                    }
                }

                $q = (int) ($item['quantidade'] ?? 0);
                $pu = (float) ($item['preco_unitario'] ?? 0);
                if (!isset($item['subtotal']) || $item['subtotal'] === null) {
                    $item['subtotal'] = $pu * $q;
                }

                $sumItensSubtotal += (float) ($item['subtotal'] ?? 0);
            }
            unset($item);

            // Normalizar itens para bater com a moeda do pedido exibida na tela.
            // Regra de negócio desejada:
            // - Se o pedido foi pago em BRL, exibir itens convertidos em BRL.
            // - Se o pedido foi pago em USD, exibir itens em USD (sem conversão).
            if ($moeda === 'BRL' && $taxaConversao > 1.01 && !empty($itens)) {
                $subPedido = (float) ($pedido['subtotal_produtos'] ?? ($pedido['subtotal'] ?? 0));
                $diffNoConv = abs($subPedido - $sumItensSubtotal);
                $diffConv = abs($subPedido - ($sumItensSubtotal * $taxaConversao));

                // Se converter os itens aproxima mais do subtotal do pedido, então itens estavam em USD.
                if ($diffConv + 0.01 < $diffNoConv) {
                    foreach ($itens as &$it) {
                        // Não converter itens de pacote/redirecionamento (valor já é USD e deve permanecer USD)
                        $tipoIt = $it['tipo_item'] ?? 'produto';
                        $pidIt = (int) ($it['produto_id'] ?? 0);
                        if ($tipoIt === 'pacote_redirecionamento' || $pidIt >= 999990) {
                            continue;
                        }
                        $it['preco_unitario'] = ((float) ($it['preco_unitario'] ?? 0)) * $taxaConversao;
                        $it['subtotal'] = ((float) ($it['subtotal'] ?? 0)) * $taxaConversao;
                    }
                    unset($it);
                    $pedido['__converted_items_to_brl'] = true;
                }
            }

            $pedido['items'] = $itens;

            // Enriquecer itens com status de compra da lista_compras
            try {
                $temListaCompras = $this->tableExists('lista_compras');
                $temPedidoIdLista = false;
                if ($temListaCompras) {
                    $colsLista = $this->getTableColumns('lista_compras');
                    $temPedidoIdLista = in_array('pedido_id', $colsLista, true);
                }
                if ($temPedidoIdLista && !empty($itens)) {
                    $stmtCompra = $this->connection->prepare(
                        "SELECT produto_id, status, quantidade_faltante FROM lista_compras WHERE pedido_id = :pedido_id ORDER BY id ASC"
                    );
                    $stmtCompra->execute([':pedido_id' => $pedidoId]);
                    $compraRows = $stmtCompra->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    // Calcular por produto: quantos registros comprados + total de quantidade pendente
                    $compraInfoMap = []; // produto_id => ['comprado_count' => N, 'pendente_total' => N]
                    foreach ($compraRows as $cr) {
                        $pid = (int) ($cr['produto_id'] ?? 0);
                        $st = (string) ($cr['status'] ?? '');
                        if ($pid <= 0) continue;
                        if (!isset($compraInfoMap[$pid])) {
                            $compraInfoMap[$pid] = ['comprado_count' => 0, 'pendente_total' => 0];
                        }
                        if ($st === 'comprado') {
                            $compraInfoMap[$pid]['comprado_count']++;
                        } elseif ($st === 'pendente') {
                            $qf = (int) ($cr['quantidade_faltante'] ?? 0);
                            $compraInfoMap[$pid]['pendente_total'] += max($qf, 1);
                        }
                    }

                    // Contar total de itens por produto no pedido
                    $totalItensPorProduto = [];
                    foreach ($pedido['items'] as $it) {
                        $pid = (int) ($it['produto_id'] ?? 0);
                        if ($pid <= 0) continue;
                        $totalItensPorProduto[$pid] = ($totalItensPorProduto[$pid] ?? 0) + 1;
                    }

                    // Determinar quantos itens foram "satisfeitos" (comprados) por produto
                    // = total de itens no pedido - quantidade pendente na lista_compras
                    $satisfeitosPorProduto = [];
                    foreach ($compraInfoMap as $pid => $info) {
                        $totalItens = $totalItensPorProduto[$pid] ?? 0;
                        $satisfeitos = $totalItens - $info['pendente_total'];
                        // Somar também registros explicitamente marcados como comprado
                        $satisfeitos = max($satisfeitos, $info['comprado_count']);
                        $satisfeitosPorProduto[$pid] = max(0, min($satisfeitos, $totalItens));
                    }

                    // Atribuir compra_status a cada item
                    $compraUsed = [];
                    foreach ($pedido['items'] as &$it) {
                        $pid = (int) ($it['produto_id'] ?? 0);
                        if ($pid <= 0 || !isset($compraInfoMap[$pid])) continue;
                        if (!isset($compraUsed[$pid])) $compraUsed[$pid] = 0;
                        if ($compraUsed[$pid] < ($satisfeitosPorProduto[$pid] ?? 0)) {
                            $it['compra_status'] = 'comprado';
                            $compraUsed[$pid]++;
                        } else {
                            $it['compra_status'] = 'pendente';
                        }
                    }
                    unset($it);
                }
            } catch (\Exception $e) {
                // Silenciar — não impede exibição do pedido
            }
        } catch (\Exception $e) {
            $pedido['items'] = [];
        }

        // Código do pedido (fallback)
        if (empty($pedido['codigo_pedido']) && !empty($pedido['numero_pedido'])) {
            $pedido['codigo_pedido'] = $pedido['numero_pedido'];
        }
        if (empty($pedido['codigo_pedido'])) {
            $pedido['codigo_pedido'] = 'PED-' . str_pad((string) $pedidoId, 6, '0', STR_PAD_LEFT);
        }

        // Histórico (se existir)
        try {
            if ($this->tableExists('pedido_status_history')) {
                $usuarioCol = $this->resolvePedidoStatusHistoryUsuarioColumn();
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
                    LEFT JOIN usuarios u ON psh.{$usuarioCol} = u.id
                    WHERE psh.pedido_id = :id
                    ORDER BY psh.created_at DESC
                ");
                $stmtHist->execute([':id' => $pedidoId]);
                $pedido['historico'] = $stmtHist->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $pedido['historico'] = [];
        }

        return $pedido;
    }

    public function getRastreamento($pedidoId) {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return [];

        $eventos = [];
        $pedidoCreatedGlobal = null;
        $pedidoUpdatedGlobal = null;
        $pushEvento = function ($status, $createdAt, $observacao = '', $usuario = 'Sistema') use (&$eventos) {
            $st = trim((string) $status);
            $dt = $createdAt;
            if ($st === '') {
                return;
            }
            if (is_string($dt)) {
                $dt = trim($dt);
                if ($dt === '') {
                    $dt = null;
                }
            }
            $eventos[] = [
                'novo_status' => $st,
                'status_novo' => $st,
                'observacao' => (string) $observacao,
                'created_at' => $dt,
                'usuario_alterou' => (string) $usuario,
            ];
        };

        $getTs = function ($row, $keyCandidates) {
            if (!is_array($row)) {
                return null;
            }
            foreach ($keyCandidates as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    return $row[$k];
                }
            }
            return null;
        };

        try {
            $usuarioCol = $this->resolvePedidoStatusHistoryUsuarioColumn();
            $userNomeCol = 'nome';
            try {
                $stmtUserCols = $this->connection->query('DESCRIBE usuarios');
                $userCols = $stmtUserCols ? $stmtUserCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($userCols) && !in_array('nome', $userCols, true) && in_array('name', $userCols, true)) {
                    $userNomeCol = 'name';
                }
            } catch (\Exception $e) {
            }

            $stmt = $this->connection->prepare("\
                SELECT psh.*, u.{$userNomeCol} as usuario_alterou
                FROM pedido_status_history psh
                LEFT JOIN usuarios u ON psh.{$usuarioCol} = u.id
                WHERE psh.pedido_id = :id
                ORDER BY psh.created_at DESC
            ");
            $stmt->execute([':id' => $pedidoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) {
                foreach ($rows as $r) {
                    $st = $r['status_novo'] ?? ($r['novo_status'] ?? '');
                    $dt = $getTs($r, ['created_at', 'data_hora', 'data', 'date']);
                    $obs = (string) ($r['observacao'] ?? '');
                    $usr = (string) ($r['usuario_alterou'] ?? 'Sistema');
                    $pushEvento($st, $dt, $obs, $usr);
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        // Fallback: tabela rastreamento (quando existir)
        try {
            if ($this->tableExists('rastreamento')) {
                $colsR = $this->getTableColumns('rastreamento');
                if (is_array($colsR) && !empty($colsR)) {
                    $colPedido = $this->pickColumn($colsR, ['pedido_id', 'order_id']);
                    $colEtapa = $this->pickColumn($colsR, ['novo_status', 'etapa', 'status', 'status_novo']);
                    $colDesc = $this->pickColumn($colsR, ['observacao', 'descricao', 'description', 'mensagem', 'message']);
                    $colLocal = $this->pickColumn($colsR, ['local', 'location']);
                    $colData = $this->pickColumn($colsR, ['created_at', 'data_hora', 'data', 'date']);

                    if ($colPedido) {
                        $select = [];
                        if ($colEtapa) $select[] = $colEtapa . ' AS etapa';
                        if ($colDesc) $select[] = $colDesc . ' AS descricao';
                        if ($colLocal) $select[] = $colLocal . ' AS local';
                        if ($colData) $select[] = $colData . ' AS created_at';
                        if (empty($select)) {
                            $select[] = '*';
                        }

                        $orderBy = $colData ? (' ORDER BY ' . $colData . ' DESC') : ' ORDER BY id DESC';
                        $st = $this->connection->prepare('SELECT ' . implode(', ', $select) . ' FROM rastreamento WHERE ' . $colPedido . ' = ?' . $orderBy);
                        $st->execute([$pedidoId]);
                        $rrows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        $out = [];
                        foreach ($rrows as $r) {
                            $etapa = trim((string) ($r['etapa'] ?? ($r[$colEtapa] ?? '')));
                            $desc = trim((string) ($r['descricao'] ?? ($r[$colDesc] ?? '')));
                            $local = trim((string) ($r['local'] ?? ($r[$colLocal] ?? '')));
                            $dt = $r['created_at'] ?? ($colData ? ($r[$colData] ?? null) : null);

                            $obs = $desc;
                            if ($local !== '') {
                                $obs = ($obs !== '' ? ($obs . ' - ' . $local) : $local);
                            }

                            $out[] = [
                                'status_novo' => $etapa,
                                'novo_status' => $etapa,
                                'observacao' => $obs,
                                'created_at' => $dt,
                                'usuario_alterou' => 'Sistema',
                            ];
                        }

                        if (!empty($out)) {
                            foreach ($out as $ev) {
                                $pushEvento($ev['novo_status'] ?? ($ev['status_novo'] ?? ''), $ev['created_at'] ?? null, $ev['observacao'] ?? '', $ev['usuario_alterou'] ?? 'Sistema');
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // Eventos sintéticos a partir do pedido (criado/pago) e tabelas de etiqueta
        try {
            $colsP = $this->getTableColumns('pedidos');
            $colStatus = $this->pickColumn($colsP, ['status', 'status_pedido', 'pedido_status']);
            $colCreated = $this->pickColumn($colsP, ['created_at', 'data_criacao', 'data_pedido']);
            $colUpdated = $this->pickColumn($colsP, ['updated_at', 'data_atualizacao', 'updated']);
            $colPagoEm = $this->pickColumn($colsP, ['pago_em', 'paid_at', 'data_pagamento']);
            $colPaymentStatus = $this->pickColumn($colsP, ['payment_status', 'status_pagamento']);

            $select = ['id'];
            if ($colStatus) $select[] = $colStatus . ' AS st';
            if ($colCreated) $select[] = $colCreated . ' AS created_at';
            if ($colUpdated) $select[] = $colUpdated . ' AS updated_at';
            if ($colPagoEm) $select[] = $colPagoEm . ' AS pago_em';
            if ($colPaymentStatus) $select[] = $colPaymentStatus . ' AS payment_status';
            $st = $this->connection->prepare('SELECT ' . implode(', ', $select) . ' FROM pedidos WHERE id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            $pr = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            $pedidoCreated = $getTs($pr, ['created_at']);
            $pedidoUpdated = $getTs($pr, ['updated_at']);
            $pedidoCreatedGlobal = $pedidoCreated;
            $pedidoUpdatedGlobal = $pedidoUpdated;
            if ($pedidoCreated) {
                $pushEvento('pedido_criado', $pedidoCreated, '', 'Sistema');
            }

            $pagoEmVal = $getTs($pr, ['pago_em']);
            $pstatusVal = strtolower(trim((string) ($pr['payment_status'] ?? '')));
            $isPaid = ($pagoEmVal !== null && $pagoEmVal !== '') || in_array($pstatusVal, ['approved','aprovado','paid','pago','succeeded','success','received','confirmed'], true);
            if ($isPaid) {
                $pushEvento('pago', $pagoEmVal ?: $pedidoCreated, '', 'Sistema');
            }

            $stVal = trim((string) ($pr['st'] ?? ''));
            if ($stVal !== '') {
                $pushEvento($stVal, $pedidoUpdated ?: $pedidoCreated, '', 'Sistema');
            }
        } catch (\Exception $e) {
        }

        try {
            $labelTables = [
                ['table' => 'shipstation_etiquetas', 'status' => 'enviado', 'cols' => ['updated_at','created_at','generated_at','created','data_hora','data_criacao','data'], 'extra' => ['label_url' => 'label_url', 'tracking_number' => 'tracking_number']],
                ['table' => 'stamps_etiquetas', 'status' => 'enviado', 'cols' => ['updated_at','created_at','generated_at','created','data_hora','data_criacao','data'], 'extra' => ['label_url' => 'label_url', 'tracking_number' => 'tracking_number']],
                ['table' => 'correios_etiquetas', 'status' => 'enviado', 'cols' => ['updated_at','created_at','gerado_em','data_criacao','data_hora','data'], 'extra' => ['codigo_etiqueta' => 'codigo_etiqueta']],
                ['table' => 'remessa_janela_pedidos', 'status' => 'em_transporte', 'cols' => ['updated_at','created_at','gerado_em','data_criacao','data_hora','data'], 'extra' => ['wexpress_status' => 'wexpress_status', 'wexpress_tracking_number' => 'wexpress_tracking_number', 'courier_tracking_number' => 'courier_tracking_number']],
            ];

            foreach ($labelTables as $lt) {
                $t = (string) ($lt['table'] ?? '');
                if ($t === '' || !$this->tableExists($t)) {
                    continue;
                }
                $colsT = $this->getTableColumns($t);
                if (empty($colsT)) {
                    continue;
                }
                $colPedido = $this->pickColumn($colsT, ['pedido_id', 'order_id']);
                if (!$colPedido) {
                    continue;
                }
                $dtCol = $this->pickColumn($colsT, (array) ($lt['cols'] ?? ['updated_at','created_at']));
                $fields = [];
                $fields[] = $dtCol ? ($dtCol . ' AS dt') : 'NULL AS dt';
                foreach ((array) ($lt['extra'] ?? []) as $alias => $col) {
                    if (in_array($col, $colsT, true)) {
                        $fields[] = $col . ' AS ' . $alias;
                    }
                }
                $sql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $t . ' WHERE ' . $colPedido . ' = ? ORDER BY ' . ($dtCol ? $dtCol : 'id') . ' DESC LIMIT 1';
                $st = $this->connection->prepare($sql);
                $st->execute([$pedidoId]);
                $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                $dt = $row['dt'] ?? null;
                if (($dt === null || $dt === '') && ($pedidoUpdatedGlobal || $pedidoCreatedGlobal)) {
                    $dt = $pedidoUpdatedGlobal ?: $pedidoCreatedGlobal;
                }
                $obsParts = [];
                foreach (['tracking_number','codigo_etiqueta','wexpress_tracking_number','courier_tracking_number','wexpress_status'] as $k) {
                    $v = trim((string) ($row[$k] ?? ''));
                    if ($v !== '') {
                        $obsParts[] = $v;
                    }
                }
                $obs = '';
                if ($t !== 'remessa_janela_pedidos' && !empty($obsParts)) {
                    $obs = 'Código: ' . $obsParts[0];
                }
                $pushEvento((string) ($lt['status'] ?? 'enviado'), $dt, $obs, 'Sistema');
            }
        } catch (\Exception $e) {
        }

        if (empty($eventos)) {
            return [];
        }

        usort($eventos, function ($a, $b) {
            $da = isset($a['created_at']) ? strtotime((string) $a['created_at']) : 0;
            $db = isset($b['created_at']) ? strtotime((string) $b['created_at']) : 0;
            if ($da === $db) {
                return 0;
            }
            return ($da > $db) ? -1 : 1;
        });

        // Deduplicar eventos idênticos
        $uniq = [];
        $seen = [];
        foreach ($eventos as $ev) {
            $k = strtolower(trim((string) ($ev['novo_status'] ?? ''))) . '|' . trim((string) ($ev['created_at'] ?? '')) . '|' . trim((string) ($ev['observacao'] ?? ''));
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $uniq[] = $ev;
        }

        return $uniq;
    }

    /**
     * Status que exigem medidas (peso, altura, largura, comprimento) preenchidas.
     */
    private const STATUSES_EXIGEM_MEDIDAS = [
        'produto_consolidado',
        'consolidado',
        'etiqueta_gerada',
        'em_transporte',
        'aguardando_liberacao_aduaneira',
        'enviado_ao_destinatario',
        'enviado',
        'entregue',
    ];

    public function atualizarStatus(int $pedidoId, string $novoStatus, ?string $observacao = null, $usuarioId = null): bool {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return false;

        try {
            // Validar medidas obrigatórias para status de "ciclo fechado"
            $novoStatusKey = strtolower(trim($novoStatus));
            if (in_array($novoStatusKey, self::STATUSES_EXIGEM_MEDIDAS, true)) {
                $stM = $this->connection->prepare('SELECT peso_total, altura, largura, comprimento FROM pedidos WHERE id = ? LIMIT 1');
                $stM->execute([$pedidoId]);
                $medidas = $stM->fetch(\PDO::FETCH_ASSOC) ?: [];

                $peso = (float) ($medidas['peso_total'] ?? 0);
                $altura = (float) ($medidas['altura'] ?? 0);
                $largura = (float) ($medidas['largura'] ?? 0);
                $comprimento = (float) ($medidas['comprimento'] ?? 0);

                if ($peso <= 0 || $altura <= 0 || $largura <= 0 || $comprimento <= 0) {
                    error_log("[PEDIDO #{$pedidoId}] Tentativa de alterar status para '{$novoStatus}' bloqueada: medidas não preenchidas (peso={$peso}, alt={$altura}, larg={$largura}, comp={$comprimento}).");
                    return false;
                }
            }

            // Capturar status anterior para o histórico
            $statusAnterior = null;
            try {
                $stPrev = $this->connection->prepare('SELECT status FROM pedidos WHERE id = ? LIMIT 1');
                $stPrev->execute([$pedidoId]);
                $statusAnterior = $stPrev->fetchColumn() ?: null;
            } catch (\Exception $e) {}

            // Garantir que status seja VARCHAR (não ENUM) para aceitar todos os valores
            try {
                $stDesc = $this->connection->query("DESCRIBE pedidos");
                $rows = $stDesc ? $stDesc->fetchAll(\PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    if (strtolower((string)($row['Field'] ?? '')) === 'status' && strpos(strtolower((string)($row['Type'] ?? '')), 'enum') !== false) {
                        $this->connection->exec("ALTER TABLE pedidos MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'pendente'");
                        break;
                    }
                }
            } catch (\Exception $e) {}

            $st = $this->connection->prepare('UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?');
            $st->execute([$novoStatus, $pedidoId]);

            if ($this->tableExists('pedido_status_history')) {
                try {
                    $usuarioCol = $this->resolvePedidoStatusHistoryUsuarioColumn();
                    $uid = $usuarioId !== null ? (int) $usuarioId : null;

                    // Detectar nome correto da coluna de status novo (novo_status ou status_novo)
                    $stmtCols = $this->connection->query('DESCRIBE pedido_status_history');
                    $colsH = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    $colStatusNovo = in_array('novo_status', $colsH, true) ? 'novo_status' : 'status_novo';
                    $hasStatusAnterior = in_array('status_anterior', $colsH, true);

                    $fields = ['pedido_id', $colStatusNovo, 'observacao', $usuarioCol, 'created_at'];
                    $vals = ['?', '?', '?', '?', 'NOW()'];
                    $bind = [$pedidoId, $novoStatus, $observacao, $uid];

                    if ($hasStatusAnterior) {
                        $fields[] = 'status_anterior';
                        $vals[] = '?';
                        $bind[] = $statusAnterior;
                    }

                    $stH = $this->connection->prepare('INSERT INTO pedido_status_history (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $vals) . ')');
                    $stH->execute($bind);
                } catch (\Exception $e) {
                    error_log("[PEDIDO #{$pedidoId}] Falha ao gravar histórico de status: " . $e->getMessage());
                }
            }

            // Se marcou como pago, inserir itens na lista_compras
            $paidValuesEc = ['pago','paid','approved','aprovado','concluido','concluído','confirmed','received','succeeded','success'];
            if (in_array(strtolower(trim((string) $novoStatus)), $paidValuesEc, true)) {
                try {
                    $temListaEc = false;
                    try {
                        $stTbl = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                        $stTbl->execute(['lista_compras']);
                        $temListaEc = ((int) $stTbl->fetchColumn() > 0);
                    } catch (\Exception $e) {}

                    if ($temListaEc) {
                        $colsListaEc = [];
                        try { $stD = $this->connection->query('DESCRIBE lista_compras'); $colsListaEc = $stD ? $stD->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        $temPedidoIdLista = in_array('pedido_id', $colsListaEc, true);
                        $temProdutoIdLista = in_array('produto_id', $colsListaEc, true);

                        if ($temPedidoIdLista && $temProdutoIdLista) {
                            // Determinar tabela de itens
                            $itensTableEc = null;
                            try {
                                $stTbl2 = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                                $stTbl2->execute(['pedido_itens']);
                                if ((int) $stTbl2->fetchColumn() > 0) $itensTableEc = 'pedido_itens';
                                else {
                                    $stTbl2->execute(['pedido_items']);
                                    if ((int) $stTbl2->fetchColumn() > 0) $itensTableEc = 'pedido_items';
                                }
                            } catch (\Exception $e) {}

                            if ($itensTableEc) {
                                // Limpar pendências antigas
                                try { $this->connection->prepare("DELETE FROM lista_compras WHERE pedido_id = ? AND status = 'pendente'")->execute([$pedidoId]); } catch (\Exception $e) {}

                                // Buscar reservas
                                $temReservasEc = false;
                                $temPedIdRes = false; $temProdIdRes = false; $temQtdRes = false; $temStatusRes = false;
                                try {
                                    $stTbl->execute(['estoque_reservas']);
                                    $temReservasEc = ((int) $stTbl->fetchColumn() > 0);
                                    if ($temReservasEc) {
                                        $stDR = $this->connection->query('DESCRIBE estoque_reservas');
                                        $colsRes = $stDR ? $stDR->fetchAll(\PDO::FETCH_COLUMN) : [];
                                        $temPedIdRes = in_array('pedido_id', $colsRes, true);
                                        $temProdIdRes = in_array('produto_id', $colsRes, true);
                                        $temQtdRes = in_array('quantidade_reservada', $colsRes, true);
                                        $temStatusRes = in_array('status', $colsRes, true);
                                    }
                                } catch (\Exception $e) {}

                                $stIt = $this->connection->prepare('SELECT produto_id, quantidade FROM ' . $itensTableEc . ' WHERE pedido_id = ?');
                                $stIt->execute([$pedidoId]);
                                $itensEc = $stIt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                                foreach ($itensEc as $it) {
                                    $produtoId = (int) ($it['produto_id'] ?? 0);
                                    $qtdPedido = (int) ($it['quantidade'] ?? 0);
                                    if ($produtoId <= 0 || $qtdPedido <= 0) continue;

                                    // Pular produtos de desapego (não entram na lista de compras)
                                    try {
                                        $colsProdEC = [];
                                        try { $stCPEC = $this->connection->query('DESCRIBE produtos'); $colsProdEC = $stCPEC ? $stCPEC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) { $colsProdEC = []; }
                                        if (in_array('desapego', $colsProdEC, true)) {
                                            $stDesapEC = $this->connection->prepare('SELECT desapego FROM produtos WHERE id = ? LIMIT 1');
                                            $stDesapEC->execute([$produtoId]);
                                            if ((int) ($stDesapEC->fetchColumn() ?: 0) === 1) continue;
                                        }
                                    } catch (\Throwable $e) {}

                                    $qtdReservada = 0;
                                    if ($temPedIdRes && $temProdIdRes && $temQtdRes) {
                                        try {
                                            $sqlR = 'SELECT COALESCE(SUM(quantidade_reservada),0) FROM estoque_reservas WHERE pedido_id = ? AND produto_id = ?';
                                            if ($temStatusRes) $sqlR .= " AND status = 'ativa'";
                                            $stR = $this->connection->prepare($sqlR);
                                            $stR->execute([$pedidoId, $produtoId]);
                                            $qtdReservada = (int) ($stR->fetchColumn() ?: 0);
                                        } catch (\Exception $e) {}
                                    }

                                    $faltante = $qtdPedido - $qtdReservada;
                                    if ($faltante <= 0) continue;

                                    // Pular se já comprado
                                    try {
                                        $stQC = $this->connection->prepare("SELECT COUNT(*) FROM lista_compras WHERE pedido_id = ? AND produto_id = ? AND status = 'comprado'");
                                        $stQC->execute([$pedidoId, $produtoId]);
                                        if ((int) ($stQC->fetchColumn() ?: 0) > 0) continue;
                                    } catch (\Exception $e) {}

                                    $colsIns = ['produto_id', 'pedido_id'];
                                    $valsIns = [':produto_id', ':pedido_id'];
                                    $paramsIns = [':produto_id' => $produtoId, ':pedido_id' => $pedidoId];

                                    if (in_array('quantidade_faltante', $colsListaEc, true)) {
                                        $colsIns[] = 'quantidade_faltante'; $valsIns[] = ':q'; $paramsIns[':q'] = $faltante;
                                    } elseif (in_array('quantidade_necessaria', $colsListaEc, true)) {
                                        $colsIns[] = 'quantidade_necessaria'; $valsIns[] = ':q'; $paramsIns[':q'] = $faltante;
                                    }
                                    if (in_array('status', $colsListaEc, true)) {
                                        $colsIns[] = 'status'; $valsIns[] = "'pendente'";
                                    }
                                    if (in_array('data_solicitacao', $colsListaEc, true)) {
                                        $colsIns[] = 'data_solicitacao'; $valsIns[] = 'CURDATE()';
                                    }

                                    $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $colsIns) . ') VALUES (' . implode(',', $valsIns) . ')')->execute($paramsIns);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("[PEDIDO #{$pedidoId}] Erro ao inserir na lista_compras: " . $e->getMessage());
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("[PEDIDO #{$pedidoId}] Erro ao atualizar status para '{$novoStatus}': " . $e->getMessage());
            return false;
        }
    }

    public function dispararEvento($eventoNome, $pedidoId) {
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");

        try {
            $service = new NotificationService();
            $service->notificarEventoPedido(is_string($eventoNome) ? $eventoNome : null, (int) $pedidoId);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES] Falha ao disparar notificacoes: ' . $e->getMessage());
        }

        // Sincronizar com QuickBooks automaticamente ao criar pedido
        if ($eventoNome === 'novo_pedido') {
            try {
                $qbService = new \App\Services\QuickBooksService();
                if ($qbService->isConectado()) {
                    $pedido = $this->getComDetalhes((int) $pedidoId);
                    if ($pedido) {
                        $itens = $pedido['items'] ?? [];
                        $qbService->criarInvoiceDePedido($pedido, $itens, $pedido);
                        error_log("[QUICKBOOKS] Invoice criada automaticamente para pedido #{$pedidoId}");
                    }
                }
            } catch (\Exception $e) {
                error_log('[QUICKBOOKS] Erro ao sincronizar pedido #' . $pedidoId . ': ' . $e->getMessage());
            }
        }

        // Processar devoluções de impostos de brindes quando pagamento é aprovado
        if ($eventoNome === 'pagamento_aprovado') {
            try {
                $brindeService = new \App\Services\BrindeService();
                $resultado = $brindeService->processarDevolucoesParaPedido((int) $pedidoId);
                if ($resultado['processados'] > 0) {
                    error_log('[BRINDE] Devoluções processadas (webhook) pedido #' . $pedidoId . ': ' . $resultado['processados'] . ' itens, US$ ' . number_format($resultado['valor_total'], 2));
                }
            } catch (\Exception $e) {
                error_log('[BRINDE] Erro ao processar devoluções pedido #' . $pedidoId . ': ' . $e->getMessage());
            }
        }
    }
}
