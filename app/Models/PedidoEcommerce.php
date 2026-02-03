<?php
namespace App\Models;

use App\Services\NotificationService;

class PedidoEcommerce {
    private \PDO $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$table]);
            return ((int) $st->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
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

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p WHERE p.' . $colUsuarioId . ' = :uid ORDER BY ' . $orderCol . ' DESC LIMIT :lim OFFSET :off';
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

                    $totalField = null;
                    foreach (['valor_total', 'total', 'valor', 'amount'] as $c) {
                        if (array_key_exists($c, $r)) {
                            $totalField = $c;
                            break;
                        }
                    }

                    if ($totalField !== null) {
                        $baseTotal = (float) ($r[$totalField] ?? 0);
                        if ($valorTotalBRL !== null) {
                            $r[$totalField] = $valorTotalBRL;
                            $r['valor_total'] = $valorTotalBRL;
                        } else {
                            $moedaOriginal = strtoupper(trim((string) ($r['moeda_original'] ?? '')));
                            $deveConverter = ($moedaOriginal === 'USD');
                            if (!$deveConverter && $baseTotal > 0 && $baseTotal <= 2000) {
                                $deveConverter = true;
                            }
                            if ($deveConverter) {
                                $conv = $baseTotal * $taxaConversao;
                                $r[$totalField] = $conv;
                                $r['valor_total'] = $conv;
                            }
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

            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM pedidos WHERE ' . $colUsuarioId . ' = ?');
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

            $select = ['p.id'];
            if ($colCodigo) $select[] = 'p.' . $colCodigo . ' AS codigo';
            if ($colCreatedAt) $select[] = 'p.' . $colCreatedAt . ' AS created_at';
            if ($colMoeda) $select[] = 'p.' . $colMoeda . ' AS moeda';
            if ($colValorTotal) $select[] = 'p.' . $colValorTotal . ' AS valor_total';

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

                $liq = $fat - $custo;
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

            // Comissão (se futuramente houver regra, calcular aqui). Por enquanto zero para evitar erro.
            $resumoBase['percentual_comissao'] = 0.0;
            $resumoBase['valor_comissao'] = 0.0;
            foreach ($resumoBase['por_moeda'] as $m => &$t) {
                $t['percentual_comissao'] = 0.0;
                $t['valor_comissao'] = 0.0;
            }
            unset($t);

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
            $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $pedido = null;
        }

        if (!$pedido) return null;

        // Normalizar totais + endereço para o formato esperado nas views do usuário
        try {
            $colsPedido = $this->getTableColumns('pedidos');

            $moeda = strtoupper((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL')));
            if ($moeda === '') $moeda = 'BRL';
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
                if ($moedaOriginal === 'USD') {
                    $deveConverterUSDParaBRL = true;
                }
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

            // Se não há coluna BRL, decidir conversão por heurística
            if ($moeda === 'BRL' && $taxaConversao > 1.01 && !$deveConverterUSDParaBRL) {
                // Heurística: totals "baixos" + taxa>1 normalmente indicam USD salvo com moeda BRL
                if ($valorTotal > 0 && $valorTotal <= 2000 && $subtotalProdutos <= 2000) {
                    $deveConverterUSDParaBRL = true;
                }
            }

            if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                // Preferência: valores explicitamente em BRL quando existirem
                if ($valorTotalBRL !== null) {
                    $valorTotal = $valorTotalBRL;
                } elseif ($deveConverterUSDParaBRL) {
                    $valorTotal = $valorTotal * $taxaConversao;
                }

                if ($subtotalProdutosBRL !== null) {
                    $subtotalProdutos = $subtotalProdutosBRL;
                } elseif ($deveConverterUSDParaBRL) {
                    $subtotalProdutos = $subtotalProdutos * $taxaConversao;
                }

                if ($valorFreteBRL !== null) {
                    $valorFrete = $valorFreteBRL;
                } elseif ($deveConverterUSDParaBRL) {
                    $valorFrete = $valorFrete * $taxaConversao;
                }

                if ($taxaServicoBRL !== null) {
                    $taxaServico = $taxaServicoBRL;
                } elseif ($deveConverterUSDParaBRL) {
                    $taxaServico = $taxaServico * $taxaConversao;
                }

                if ($valorImpostosBRL !== null) {
                    $valorImpostos = $valorImpostosBRL;
                } elseif ($deveConverterUSDParaBRL) {
                    $valorImpostos = $valorImpostos * $taxaConversao;
                }
            }

            $pedido['subtotal_produtos'] = $subtotalProdutos;
            $pedido['valor_frete'] = $valorFrete;
            $pedido['taxa_servico'] = $taxaServico;
            $pedido['valor_impostos'] = $valorImpostos;
            $pedido['valor_total'] = $valorTotal;

            // Aliases legados usados em algumas telas (ex.: checkout/conclusao)
            $pedido['subtotal'] = $subtotalProdutos;
            $pedido['frete'] = $valorFrete;
            $pedido['servicos'] = $taxaServico;
            $pedido['impostos'] = $valorImpostos;
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

            // Aliases esperados por algumas views (ex.: checkout/conclusao)
            if (!array_key_exists('endereco', $pedido) || $pedido['endereco'] === null) $pedido['endereco'] = $pedido['endereco_entrega'] ?? '';
            if (!array_key_exists('numero', $pedido) || $pedido['numero'] === null) $pedido['numero'] = $pedido['numero_entrega'] ?? '';
            if (!array_key_exists('bairro', $pedido) || $pedido['bairro'] === null) $pedido['bairro'] = $pedido['bairro_entrega'] ?? '';
            if (!array_key_exists('cidade', $pedido) || $pedido['cidade'] === null) $pedido['cidade'] = $pedido['cidade_entrega'] ?? '';
            if (!array_key_exists('estado', $pedido) || $pedido['estado'] === null) $pedido['estado'] = $pedido['estado_entrega'] ?? '';
            if (!array_key_exists('cep', $pedido) || $pedido['cep'] === null) $pedido['cep'] = $pedido['cep_entrega'] ?? '';

            if (!array_key_exists('cliente_nome', $pedido) || $pedido['cliente_nome'] === null || $pedido['cliente_nome'] === '') {
                $pedido['cliente_nome'] = (string) ($pedido['nome'] ?? ($pedido['nome_cliente'] ?? ($pedido['customer_name'] ?? ($pedido['cliente'] ?? ''))));
            }
            if (!array_key_exists('cliente_email', $pedido) || $pedido['cliente_email'] === null || $pedido['cliente_email'] === '') {
                $pedido['cliente_email'] = (string) ($pedido['email'] ?? ($pedido['email_cliente'] ?? ($pedido['customer_email'] ?? ($pedido['cliente_email'] ?? ''))));
            }
            if (!array_key_exists('cliente_telefone', $pedido) || $pedido['cliente_telefone'] === null || $pedido['cliente_telefone'] === '') {
                $pedido['cliente_telefone'] = (string) ($pedido['telefone'] ?? ($pedido['telefone_cliente'] ?? ($pedido['customer_phone'] ?? ($pedido['cliente_telefone'] ?? ''))));
            }

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
                        }
                    }
                } catch (\Exception $e) {
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
            if ($colSubtotal) $selectParts[] = 'pi.' . $colSubtotal . ' AS subtotal';
            if ($colNomeProduto) $selectParts[] = 'pi.' . $colNomeProduto . ' AS nome_produto';
            if ($colSku) $selectParts[] = 'pi.' . $colSku . ' AS nome_produto_sku';
            if ($colUrlOriginalItem) $selectParts[] = 'pi.' . $colUrlOriginalItem . ' AS url_original';
            if ($pick(['created_at']) !== null) $selectParts[] = 'pi.created_at';
            if ($ncmCol && $colProdutoId) {
                $selectParts[] = '(SELECT pr.' . $ncmCol . ' FROM produtos pr WHERE pr.id = pi.' . $colProdutoId . ' LIMIT 1) AS ncm';
            } else {
                $selectParts[] = "'' AS ncm";
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

            foreach ($itens as &$item) {
                $item['referencia'] = $item['referencia'] ?? ($item['nome_produto_sku'] ?? '');
                $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
<<<<<<< HEAD
                if (!isset($item['url_original']) || $item['url_original'] === null) {
                    $item['url_original'] = '';
                }
                $item['url_original'] = trim((string) $item['url_original']);
=======
                if (!array_key_exists('ncm', $item) || $item['ncm'] === null) {
                    $item['ncm'] = '';
                }
>>>>>>> 25e4dfe28914548118d204286e6c5d495d83b7a4
                $pid = (int) ($item['produto_id'] ?? 0);
                if (empty($item['nome_produto'])) {
                    $item['nome_produto'] = $pid > 0 ? ('Produto #' . $pid) : 'Produto';
                }
                if (!array_key_exists('nome', $item) || $item['nome'] === null || $item['nome'] === '') {
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

                // Se normalizou pedido para BRL mas itens vieram em USD, converter aqui também
                if (!empty($pedido['__converted_to_brl']) && !empty($pedido['taxa_conversao']) && (float) $pedido['taxa_conversao'] > 1.01) {
                    $tx = (float) $pedido['taxa_conversao'];
                    $item['preco_unitario'] = ((float) ($item['preco_unitario'] ?? 0)) * $tx;
                    $item['subtotal'] = ((float) ($item['subtotal'] ?? 0)) * $tx;
                }
            }
            unset($item);

            $pedido['items'] = $itens;
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

            $stmt = $this->connection->prepare("\
                SELECT psh.*, u.{$userNomeCol} as usuario_alterou
                FROM pedido_status_history psh
                LEFT JOIN usuarios u ON psh.usuario_id = u.id
                WHERE psh.pedido_id = :id
                ORDER BY psh.created_at DESC
            ");
            $stmt->execute([':id' => $pedidoId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function atualizarStatus(int $pedidoId, string $novoStatus, ?string $observacao = null, $usuarioId = null): bool {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return false;

        try {
            $st = $this->connection->prepare('UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?');
            $st->execute([$novoStatus, $pedidoId]);

            if ($this->tableExists('pedido_status_history')) {
                try {
                    $stH = $this->connection->prepare('INSERT INTO pedido_status_history (pedido_id, status_anterior, status_novo, observacao, usuario_id, created_at) VALUES (?, NULL, ?, ?, ?, NOW())');
                    $uid = $usuarioId !== null ? (int) $usuarioId : null;
                    $stH->execute([$pedidoId, $novoStatus, $observacao, $uid]);
                } catch (\Exception $e) {
                }
            }
            return true;
        } catch (\Exception $e) {
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
    }
}
