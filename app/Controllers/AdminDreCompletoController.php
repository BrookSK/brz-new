<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminDreCompletoController extends Controller {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    /**
     * Retorna todos os dados do DRE Completo como JSON para a aba no financeiro
     */
    public function dados(Request $request) {
        // Limpar qualquer output buffer anterior ANTES de tudo
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $auth = new AuthService();
            if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
                echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                exit;
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Auth: ' . $e->getMessage()]);
            exit;
        }

        $dateStart = $request->getParam('date_start', date('Y-01-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));

        try {

        $cols = [];
        try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        $pick = function($candidates) use ($cols) { foreach ($candidates as $c) { if (in_array($c, $cols, true)) return $c; } return ''; };
        $colTotal = $pick(['total','valor_total']);
        $colSubtotal = $pick(['subtotal','subtotal_produtos']);
        $colServicos = $pick(['servicos','taxa_servico']);
        $colImpostos = $pick(['impostos','valor_impostos']);
        $colFrete = $pick(['frete','valor_frete']);
        $colImpostoLocal = $pick(['imposto_local']);
        $colMoeda = $pick(['moeda','currency']);
        $colStatus = $pick(['status']);
        $colPayGateway = $pick(['payment_gateway','gateway']);
        $colPayStatus = $pick(['payment_status']);
        $colFormaPgto = $pick(['forma_pagamento','payment_method']);
        $colCreatedAt = $pick(['created_at','data_criacao']);
        $deletedFilter = in_array('deleted_at', $cols, true) ? "AND p.deleted_at IS NULL" : "";

        // Guard: se colunas essenciais não existem, retornar vazio
        if (!$colTotal || !$colCreatedAt || !$colStatus) {
            echo json_encode(['success' => true, 'taxaUsdBrl' => 5.85, 'periodo' => ['inicio' => $dateStart, 'fim' => $dateEnd], 'resumo' => ['total_entradas' => 0, 'total_entradas_brl' => 0, 'total_entradas_usd' => 0, 'total_despesas' => 0, 'total_despesas_brl' => 0, 'total_despesas_usd' => 0, 'resultado' => 0, 'margem' => 0, 'maior_categoria' => '', 'qtd_pedidos' => 0], 'meses' => [], 'gateways' => [], 'despesas_categoria' => [], 'despesas_favorecido' => [], 'entradas_detalhadas' => [], 'entradas_paginacao' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1], 'status_filter_dre' => '', 'operacional' => [], 'conciliacao' => ['total_creditos' => 0, 'total_debitos' => 0, 'saldo_final' => 0, 'qtd_lancamentos' => 0]], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Taxa USD→BRL
        $taxaUsdBrl = 5.85;
        try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $taxaUsdBrl = $r; } catch (\Exception $e) {}

        // === ENTRADAS DE PEDIDOS (pagos) ===
        $paidStatuses = "('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";
        $sqlEntradas = "SELECT 
            DATE_FORMAT(p.{$colCreatedAt}, '%Y-%m') as mes,
            " . ($colMoeda ? "UPPER(COALESCE(p.{$colMoeda},'BRL')) as moeda" : "'BRL' as moeda") . ",
            COUNT(*) as qtd,
            COALESCE(SUM(p.{$colTotal}),0) as total,
            " . ($colSubtotal ? "COALESCE(SUM(p.{$colSubtotal}),0) as subtotal" : "0 as subtotal") . ",
            " . ($colServicos ? "COALESCE(SUM(p.{$colServicos}),0) as servicos" : "0 as servicos") . ",
            " . ($colImpostos ? "COALESCE(SUM(p.{$colImpostos}),0) as impostos" : "0 as impostos") . ",
            " . ($colImpostoLocal ? "COALESCE(SUM(p.{$colImpostoLocal}),0) as imposto_local" : "0 as imposto_local") . ",
            " . ($colFrete ? "COALESCE(SUM(p.{$colFrete}),0) as frete" : "0 as frete") . "
            FROM pedidos p
            WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)
            AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}
            AND LOWER(COALESCE(p.{$colStatus},'')) NOT IN ('cancelado','cancelled','deleted','lixeira','trash')
            {$deletedFilter}
            GROUP BY mes, moeda ORDER BY mes";

        $entradasPorMes = [];
        try {
            $st = $this->db->prepare($sqlEntradas);
            $st->execute([':ds' => $dateStart, ':de' => $dateEnd]);
            $entradasPorMes = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // === ENTRADAS POR GATEWAY ===
        $sqlGateways = "SELECT 
            " . ($colPayGateway ? "LOWER(COALESCE(p.{$colPayGateway},'sem_gateway')) as gateway" : "'sem_gateway' as gateway") . ",
            " . ($colMoeda ? "UPPER(COALESCE(p.{$colMoeda},'BRL')) as moeda" : "'BRL' as moeda") . ",
            COUNT(*) as qtd,
            COALESCE(SUM(p.{$colTotal}),0) as total,
            " . ($colPayStatus ? "COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='approved' THEN 1 ELSE 0 END),0) as aprovados" : "0 as aprovados") . ",
            " . ($colPayStatus ? "COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},'')) IN ('pending','') OR p.{$colPayStatus} IS NULL THEN 1 ELSE 0 END),0) as pendentes" : "COUNT(*) as pendentes") . ",
            " . ($colPayStatus ? "COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='rejected' THEN 1 ELSE 0 END),0) as rejeitados" : "0 as rejeitados") . ",
            " . ($colPayStatus ? "COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='refunded' THEN 1 ELSE 0 END),0) as estornados" : "0 as estornados") . "
            FROM pedidos p
            WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)
            AND LOWER(COALESCE(p.{$colStatus},'')) NOT IN ('apagado','deleted','lixeira','trash')
            {$deletedFilter}
            GROUP BY gateway, moeda ORDER BY total DESC";

        $gatewayData = [];
        try { $st = $this->db->prepare($sqlGateways); $st->execute([':ds' => $dateStart, ':de' => $dateEnd]); $gatewayData = $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        // === DESPESAS POR MÊS E CATEGORIA ===
        $despesasPorMes = [];
        $despesasPorCategoria = [];
        $despesasPorFavorecido = [];
        try {
            $stD = $this->db->prepare("SELECT DATE_FORMAT(competencia,'%Y-%m') as mes, UPPER(COALESCE(moeda,'BRL')) as moeda, COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL GROUP BY mes, moeda ORDER BY mes");
            $stD->execute([$dateStart, $dateEnd]);
            $despesasPorMes = $stD->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        try {
            $stDC = $this->db->prepare("SELECT c.nome as categoria, c.grupo, c.cor, UPPER(COALESCE(d.moeda,'BRL')) as moeda, COALESCE(SUM(d.valor),0) as total, COUNT(*) as qtd FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE d.competencia >= ? AND d.competencia <= ? AND d.status != 'cancelada' AND d.deleted_at IS NULL GROUP BY d.categoria_id, d.moeda ORDER BY total DESC");
            $stDC->execute([$dateStart, $dateEnd]);
            $despesasPorCategoria = $stDC->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        try {
            $stDF = $this->db->prepare("SELECT COALESCE(NULLIF(favorecido,''),'Sem favorecido') as favorecido, UPPER(COALESCE(moeda,'BRL')) as moeda, COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL GROUP BY favorecido, moeda ORDER BY total DESC LIMIT 30");
            $stDF->execute([$dateStart, $dateEnd]);
            $despesasPorFavorecido = $stDF->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // === DESCONTOS E PROMOÇÕES ===
        // Calcular total de descontos dos pedidos pagos no período
        // Método: para cada item vendido, se o produto tem sale_price < price, a diferença é o desconto
        $totalDescontos = 0;
        $itensTable = null;
        try {
            try { $this->db->query("SELECT 1 FROM pedido_itens LIMIT 1"); $itensTable = 'pedido_itens'; } catch (\Exception $e) {
                try { $this->db->query("SELECT 1 FROM pedido_items LIMIT 1"); $itensTable = 'pedido_items'; } catch (\Exception $e2) {}
            }
            if ($itensTable) {
                $colsItens = [];
                try { $stI = $this->db->query("DESCRIBE {$itensTable}"); $colsItens = $stI ? $stI->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $colPrecoItem = in_array('preco', $colsItens, true) ? 'preco' : (in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : (in_array('price', $colsItens, true) ? 'price' : ''));
                $colQtdItem = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : 'quantidade');
                $colProdutoIdItem = in_array('produto_id', $colsItens, true) ? 'produto_id' : 'product_id';
                
                $colPrecoOriginal = in_array('price', $cols, true) ? 'price' : (in_array('valor', $cols, true) ? 'valor' : '');
                $hasSalePrice = in_array('sale_price', $cols, true);
                
                if ($colPrecoItem && $colPrecoOriginal && $hasSalePrice) {
                    // Método 1: Produtos com sale_price ativa que foram vendidos no período
                    // O desconto é (price - sale_price) * quantidade vendida
                    $sqlDesc = "SELECT COALESCE(SUM((prod.{$colPrecoOriginal} - prod.sale_price) * i.{$colQtdItem}), 0) as total_desconto
                        FROM {$itensTable} i
                        INNER JOIN pedidos p ON p.id = i.pedido_id
                        INNER JOIN produtos prod ON prod.id = i.{$colProdutoIdItem}
                        WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)
                        AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}
                        {$deletedFilter}
                        AND prod.sale_price > 0
                        AND prod.{$colPrecoOriginal} > prod.sale_price";
                    $stDesc = $this->db->prepare($sqlDesc);
                    $stDesc->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                    $totalDescontos = (float)($stDesc->fetchColumn() ?: 0);
                } elseif ($colPrecoItem && $colPrecoOriginal) {
                    // Fallback: comparar preço original com preço cobrado no item
                    $sqlDesc = "SELECT COALESCE(SUM((prod.{$colPrecoOriginal} - i.{$colPrecoItem}) * i.{$colQtdItem}), 0) as total_desconto
                        FROM {$itensTable} i
                        INNER JOIN pedidos p ON p.id = i.pedido_id
                        INNER JOIN produtos prod ON prod.id = i.{$colProdutoIdItem}
                        WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)
                        AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}
                        {$deletedFilter}
                        AND prod.{$colPrecoOriginal} > i.{$colPrecoItem}
                        AND (prod.{$colPrecoOriginal} - i.{$colPrecoItem}) > 0.01";
                    $stDesc = $this->db->prepare($sqlDesc);
                    $stDesc->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                    $totalDescontos = (float)($stDesc->fetchColumn() ?: 0);
                }
            }
        } catch (\Throwable $e) { $totalDescontos = 0; }

        // === COMISSÕES ===
        // Calcular total de comissões pagas no período (mesma lógica do AdminComissoesGlobalController)
        $totalComissoes = 0;
        try {
            $colOrigem = in_array('origem_pedido', $cols, true) ? 'origem_pedido' : (in_array('origem', $cols, true) ? 'origem' : '');
            $colCriadoPor = in_array('admin_criador_id', $cols, true) ? 'admin_criador_id' : (in_array('criado_por', $cols, true) ? 'criado_por' : (in_array('vendedor_id', $cols, true) ? 'vendedor_id' : (in_array('created_by', $cols, true) ? 'created_by' : '')));
            $colSemComissao = in_array('sem_comissao', $cols, true) ? 'sem_comissao' : '';

            if ($colCriadoPor || $colOrigem) {
                // Buscar faixas de comissão
                $faixas = [['min' => 0, 'max' => 999999999, 'percent' => 0]];
                try {
                    $tables = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
                    foreach ($tables as $t) {
                        $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                        $stT->execute([$t]);
                        if ((int)($stT->fetchColumn() ?: 0) === 0) continue;
                        $tCols = [];
                        try { $stC = $this->db->query("DESCRIBE {$t}"); $tCols = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        if (in_array('categoria', $tCols, true) && in_array('chave', $tCols, true)) {
                            $valC = in_array('valor', $tCols, true) ? 'valor' : (in_array('value', $tCols, true) ? 'value' : '');
                            if ($valC) {
                                $st2 = $this->db->prepare("SELECT {$valC} FROM {$t} WHERE categoria = 'comissao' AND chave = 'manual_faixas' LIMIT 1");
                                $st2->execute();
                                $raw = (string)($st2->fetchColumn() ?: '');
                                if ($raw !== '') { $arr = json_decode($raw, true); if (is_array($arr)) { $faixas = $arr; break; } }
                            }
                        }
                    }
                } catch (\Exception $e) {}

                // Buscar pedidos manuais pagos no período
                $whereC = [];
                if ($colOrigem && $colCriadoPor) {
                    $whereC[] = "(LOWER(COALESCE(p.{$colOrigem},'')) IN ('manual','admin') OR (p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0))";
                } elseif ($colOrigem) {
                    $whereC[] = "LOWER(COALESCE(p.{$colOrigem},'')) IN ('manual','admin')";
                } elseif ($colCriadoPor) {
                    $whereC[] = "(p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0)";
                }
                if (in_array('deleted_at', $cols, true)) $whereC[] = "p.deleted_at IS NULL";
                $whereC[] = "LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}";
                if ($colSemComissao) $whereC[] = "(p.{$colSemComissao} IS NULL OR p.{$colSemComissao} = 0)";
                $whereC[] = "p.{$colCreatedAt} >= :ds";
                $whereC[] = "p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)";

                $sqlC = "SELECT p.id" . ($colTotal ? ", p.{$colTotal} AS valor_total" : "") . ($colImpostos ? ", p.{$colImpostos} AS impostos" : "") . ($colMoeda ? ", p.{$colMoeda} AS moeda" : "") . ($colCriadoPor ? ", p.{$colCriadoPor} AS criado_por" : ", 0 AS criado_por") . " FROM pedidos p WHERE " . implode(' AND ', $whereC) . " LIMIT 2000";
                $stC = $this->db->prepare($sqlC);
                $stC->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                $rowsC = $stC->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // Custo por pedido
                $custoByPedido = [];
                if ($itensTable && !empty($rowsC)) {
                    $ids = array_column($rowsC, 'id');
                    $chunks = array_chunk($ids, 500);
                    $colsIt = [];
                    try { $stI2 = $this->db->query("DESCRIBE {$itensTable}"); $colsIt = $stI2 ? $stI2->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                    $cPreco = in_array('preco', $colsIt, true) ? 'preco' : (in_array('preco_unitario', $colsIt, true) ? 'preco_unitario' : (in_array('price', $colsIt, true) ? 'price' : ''));
                    $cQtd = in_array('quantidade', $colsIt, true) ? 'quantidade' : (in_array('qty', $colsIt, true) ? 'qty' : 'quantidade');
                    if ($cPreco) {
                        foreach ($chunks as $chunk) {
                            $in = implode(',', array_fill(0, count($chunk), '?'));
                            $stCu = $this->db->prepare("SELECT pedido_id, SUM({$cPreco} * {$cQtd}) as custo FROM {$itensTable} WHERE pedido_id IN ({$in}) GROUP BY pedido_id");
                            $stCu->execute($chunk);
                            foreach ($stCu->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $cu) {
                                $custoByPedido[(int)$cu['pedido_id']] = (float)$cu['custo'];
                            }
                        }
                    }
                }

                // Agrupar por vendedor e calcular comissão
                $porVendedor = [];
                foreach ($rowsC as $r) {
                    $uid = (int)($r['criado_por'] ?? 0);
                    if ($uid <= 0) continue;
                    $m = strtoupper(trim((string)($r['moeda'] ?? 'BRL')));
                    if ($m === '') $m = 'BRL';
                    $fat = (float)($r['valor_total'] ?? 0);
                    $imp = (float)($r['impostos'] ?? 0);
                    $custo = (float)($custoByPedido[(int)$r['id']] ?? 0);
                    $liq = $fat - $custo - $imp;
                    if ($m === 'USD') { $fat *= $taxaUsdBrl; $liq *= $taxaUsdBrl; }
                    if (!isset($porVendedor[$uid])) $porVendedor[$uid] = ['faturado' => 0.0, 'liquido' => 0.0];
                    $porVendedor[$uid]['faturado'] += $fat;
                    $porVendedor[$uid]['liquido'] += $liq;
                }

                foreach ($porVendedor as $uid => $t) {
                    $pct = 0.0;
                    foreach ($faixas as $f) {
                        if ($t['faturado'] >= (float)($f['min'] ?? 0) && $t['faturado'] <= (float)($f['max'] ?? PHP_FLOAT_MAX)) {
                            $pct = (float)($f['percent'] ?? 0); break;
                        }
                    }
                    $totalComissoes += max(0.0, $t['liquido']) * ($pct / 100);
                }

                // Comissões de processamento
                try {
                    $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'comissoes_processamento'");
                    $stT->execute();
                    if ((int)($stT->fetchColumn() ?: 0) > 0) {
                        $sqlP = "SELECT moeda, SUM(valor_comissao) AS total_comissao FROM comissoes_processamento WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY moeda";
                        $stP = $this->db->prepare($sqlP);
                        $stP->execute([$dateStart, $dateEnd]);
                        foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                            $com = (float)($r['total_comissao'] ?? 0);
                            if (strtoupper(trim($r['moeda'] ?? 'BRL')) === 'USD') $com *= $taxaUsdBrl;
                            $totalComissoes += $com;
                        }
                    }
                } catch (\Exception $e) {}
            }
        } catch (\Throwable $e) { $totalComissoes = 0; }

        // === RESUMO MENSAL CONSOLIDADO ===
        // Receita operacional = total do pedido (tudo que entra)
        // Composição: produtos + impostos brasil + imposto local + taxa de serviço
        $meses = [];
        foreach ($entradasPorMes as $r) {
            $m = $r['mes'];
            if (!isset($meses[$m])) $meses[$m] = ['mes' => $m, 'entradas_brl' => 0, 'entradas_usd' => 0, 'custo_produtos_brl' => 0, 'custo_produtos_usd' => 0, 'custo_impostos_br_brl' => 0, 'custo_impostos_br_usd' => 0, 'custo_imposto_local_brl' => 0, 'custo_imposto_local_usd' => 0, 'taxa_servico_brl' => 0, 'taxa_servico_usd' => 0, 'despesas_brl' => 0, 'despesas_usd' => 0, 'qtd_pedidos' => 0, 'qtd_despesas' => 0];
            $valTotal = (float)$r['total'];
            $valServicos = (float)($r['servicos'] ?? 0);
            $valSubtotal = (float)($r['subtotal'] ?? 0);
            $valImpostos = (float)($r['impostos'] ?? 0);
            $valImpostoLocal = (float)($r['imposto_local'] ?? 0);
            $valFrete = (float)($r['frete'] ?? 0);
            
            // Se não tem coluna de serviços, estimar como total - subtotal - impostos - imposto_local - frete
            if ($valServicos <= 0 && $valTotal > 0) {
                $valServicos = $valTotal - $valSubtotal - $valImpostos - $valImpostoLocal - $valFrete;
                if ($valServicos < 0) $valServicos = 0;
            }
            
            if ($r['moeda'] === 'USD') {
                $meses[$m]['entradas_usd'] += $valTotal;
                $meses[$m]['custo_produtos_usd'] += $valSubtotal;
                $meses[$m]['custo_impostos_br_usd'] += $valImpostos;
                $meses[$m]['custo_imposto_local_usd'] += $valImpostoLocal;
                $meses[$m]['taxa_servico_usd'] += $valServicos;
            } else {
                $meses[$m]['entradas_brl'] += $valTotal;
                $meses[$m]['custo_produtos_brl'] += $valSubtotal;
                $meses[$m]['custo_impostos_br_brl'] += $valImpostos;
                $meses[$m]['custo_imposto_local_brl'] += $valImpostoLocal;
                $meses[$m]['taxa_servico_brl'] += $valServicos;
            }
            $meses[$m]['qtd_pedidos'] += (int)$r['qtd'];
        }
        foreach ($despesasPorMes as $r) {
            $m = $r['mes'];
            if (!isset($meses[$m])) $meses[$m] = ['mes' => $m, 'entradas_brl' => 0, 'entradas_usd' => 0, 'custo_produtos_brl' => 0, 'custo_produtos_usd' => 0, 'custo_impostos_br_brl' => 0, 'custo_impostos_br_usd' => 0, 'custo_imposto_local_brl' => 0, 'custo_imposto_local_usd' => 0, 'taxa_servico_brl' => 0, 'taxa_servico_usd' => 0, 'despesas_brl' => 0, 'despesas_usd' => 0, 'qtd_pedidos' => 0, 'qtd_despesas' => 0];
            $val = (float)$r['total'];
            if ($r['moeda'] === 'USD') $meses[$m]['despesas_usd'] += $val;
            else $meses[$m]['despesas_brl'] += $val;
            $meses[$m]['qtd_despesas'] += (int)$r['qtd'];
        }
        ksort($meses);

        // Calcular totais
        $totalEntradasBrl = 0; $totalEntradasUsd = 0; $totalDespBrl = 0; $totalDespUsd = 0;
        $totalCustoProdutosBrl = 0; $totalCustoProdutosUsd = 0;
        $totalCustoImpostosBrBrl = 0; $totalCustoImpostosBrUsd = 0;
        $totalCustoImpostoLocalBrl = 0; $totalCustoImpostoLocalUsd = 0;
        $totalTaxaServicoBrl = 0; $totalTaxaServicoUsd = 0;
        foreach ($meses as &$m) {
            $m['entradas_total'] = $m['entradas_brl'] + ($m['entradas_usd'] * $taxaUsdBrl);
            $m['custo_produtos_total'] = $m['custo_produtos_brl'] + ($m['custo_produtos_usd'] * $taxaUsdBrl);
            $m['custo_impostos_br_total'] = $m['custo_impostos_br_brl'] + ($m['custo_impostos_br_usd'] * $taxaUsdBrl);
            $m['custo_imposto_local_total'] = $m['custo_imposto_local_brl'] + ($m['custo_imposto_local_usd'] * $taxaUsdBrl);
            $m['taxa_servico_total'] = $m['taxa_servico_brl'] + ($m['taxa_servico_usd'] * $taxaUsdBrl);
            $m['despesas_total'] = $m['despesas_brl'] + ($m['despesas_usd'] * $taxaUsdBrl);
            $m['resultado'] = $m['entradas_total'] - $m['custo_produtos_total'] - $m['custo_impostos_br_total'] - $m['custo_imposto_local_total'] - $m['despesas_total'];
            $totalEntradasBrl += $m['entradas_brl'];
            $totalEntradasUsd += $m['entradas_usd'];
            $totalDespBrl += $m['despesas_brl'];
            $totalDespUsd += $m['despesas_usd'];
            $totalCustoProdutosBrl += $m['custo_produtos_brl'];
            $totalCustoProdutosUsd += $m['custo_produtos_usd'];
            $totalCustoImpostosBrBrl += $m['custo_impostos_br_brl'];
            $totalCustoImpostosBrUsd += $m['custo_impostos_br_usd'];
            $totalCustoImpostoLocalBrl += $m['custo_imposto_local_brl'];
            $totalCustoImpostoLocalUsd += $m['custo_imposto_local_usd'];
            $totalTaxaServicoBrl += $m['taxa_servico_brl'];
            $totalTaxaServicoUsd += $m['taxa_servico_usd'];
        }
        unset($m);

        $totalEntradas = $totalEntradasBrl + ($totalEntradasUsd * $taxaUsdBrl);
        $totalDespesas = $totalDespBrl + ($totalDespUsd * $taxaUsdBrl);
        $totalCustoProdutos = $totalCustoProdutosBrl + ($totalCustoProdutosUsd * $taxaUsdBrl);
        $totalCustoImpostosBr = $totalCustoImpostosBrBrl + ($totalCustoImpostosBrUsd * $taxaUsdBrl);
        $totalCustoImpostoLocal = $totalCustoImpostoLocalBrl + ($totalCustoImpostoLocalUsd * $taxaUsdBrl);
        $totalTaxaServico = $totalTaxaServicoBrl + ($totalTaxaServicoUsd * $taxaUsdBrl);
        // Resultado = Receita - todos os custos (produtos + impostos + imposto local + descontos + AWB + comissões + despesas)
        // === AWB & TRANSPORTE (peso total * $4.80/kg) ===
        $totalAwbUsd = 0;
        try {
            if ($itensTable) {
                $colPesoP = in_array('weight', $cols, true) ? 'weight' : (in_array('peso', $cols, true) ? 'peso' : '');
                $colsProdAwb = [];
                try { $stPrAwb = $this->db->query("DESCRIBE produtos"); $colsProdAwb = $stPrAwb ? $stPrAwb->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                if (!$colPesoP) $colPesoP = in_array('weight', $colsProdAwb, true) ? 'weight' : (in_array('peso', $colsProdAwb, true) ? 'peso' : '');
                $cQtdAwb = in_array('quantidade', $colsItens ?? [], true) ? 'quantidade' : 'quantidade';
                $cProdIdAwb = in_array('produto_id', $colsItens ?? [], true) ? 'produto_id' : 'product_id';
                if ($colPesoP) {
                    $sqlAwb = "SELECT COALESCE(SUM(prod.{$colPesoP} * i.{$cQtdAwb}), 0) FROM {$itensTable} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$cProdIdAwb} WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY) AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses} {$deletedFilter}";
                    $stAwb = $this->db->prepare($sqlAwb);
                    $stAwb->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                    $pesoTotal = (float)($stAwb->fetchColumn() ?: 0);
                    $totalAwbUsd = $pesoTotal * 4.80;
                }
            }
        } catch (\Throwable $e) {}
        $totalAwbBrl = $totalAwbUsd * $taxaUsdBrl;

        // === LAST MILE BRASIL (peso * R$10/kg para pedidos destino Brasil) ===
        $totalLastMileBrl = 0;
        try {
            if ($itensTable) {
                $colPesoLm = in_array('weight', $colsProdAwb ?? [], true) ? 'weight' : (in_array('peso', $colsProdAwb ?? [], true) ? 'peso' : '');
                if (!$colPesoLm) {
                    $colsProdLm = []; try { $stPrLm = $this->db->query("DESCRIBE produtos"); $colsProdLm = $stPrLm ? $stPrLm->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                    $colPesoLm = in_array('weight', $colsProdLm, true) ? 'weight' : (in_array('peso', $colsProdLm, true) ? 'peso' : '');
                }
                $colPaisLm = '';
                foreach (['pais_entrega','pais','country','shipping_country','pais_destino'] as $cp) { if (in_array($cp, $cols, true)) { $colPaisLm = $cp; break; } }
                $cQtdLm = in_array('quantidade', $colsItens ?? [], true) ? 'quantidade' : 'quantidade';
                $cProdIdLm = in_array('produto_id', $colsItens ?? [], true) ? 'produto_id' : 'product_id';
                if ($colPesoLm && $colPaisLm) {
                    $sqlLm = "SELECT COALESCE(SUM(prod.{$colPesoLm} * i.{$cQtdLm}), 0) FROM {$itensTable} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$cProdIdLm} WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY) AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses} {$deletedFilter} AND UPPER(COALESCE(p.{$colPaisLm},'')) IN ('BR','BRASIL','BRAZIL')";
                    $stLm = $this->db->prepare($sqlLm);
                    $stLm->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                    $pesoTotalBr = (float)($stLm->fetchColumn() ?: 0);
                    $totalLastMileBrl = $pesoTotalBr * 10.0;
                }
            }
        } catch (\Throwable $e) {}

        $totalDeducoes = $totalCustoProdutos + $totalCustoImpostosBr + $totalCustoImpostoLocal + $totalDescontos + $totalAwbBrl + $totalLastMileBrl + $totalComissoes + $totalDespesas;
        $resultado = $totalEntradas - $totalDeducoes;
        // === CONCILIAÇÃO ===
        $conciliacao = [
            'total_creditos' => $totalEntradas,
            'total_debitos' => $totalDeducoes,
            'saldo_final' => $resultado,
            'qtd_lancamentos' => array_sum(array_column(array_values($meses), 'qtd_pedidos')) + array_sum(array_column(array_values($meses), 'qtd_despesas')),
        ];

        // === ENTRADAS DE PEDIDOS DETALHADAS (com paginação) ===
        $page = max(1, (int)$request->getParam('page', 1));
        $perPage = 10;
        $statusFilterDre = $request->getParam('status_dre', '');
        $entradasDetalhadas = [];
        $totalEntradasCount = 0;

        try {
            $whereDet = "p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)"
                . " AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}"
                . " {$deletedFilter}";
            $paramsDet = [':ds' => $dateStart, ':de' => $dateEnd];

            if ($statusFilterDre !== '') {
                $whereDet .= " AND LOWER(COALESCE(p.{$colStatus},'')) = :st_dre";
                $paramsDet[':st_dre'] = strtolower($statusFilterDre);
            }

            // Count
            $stCount = $this->db->prepare("SELECT COUNT(*) FROM pedidos p WHERE {$whereDet}");
            $stCount->execute($paramsDet);
            $totalEntradasCount = (int)$stCount->fetchColumn();

            // Paginated
            $offset = ($page - 1) * $perPage;
            $sqlDet = "SELECT p.id, p.{$colCreatedAt} as data_pedido, p.{$colTotal} as total, p.{$colStatus} as status"
                . ($colMoeda ? ", p.{$colMoeda} as moeda" : ", 'BRL' as moeda")
                . ($colPayGateway ? ", p.{$colPayGateway} as gateway" : ", '' as gateway")
                . ($colFormaPgto ? ", p.{$colFormaPgto} as forma_pagamento" : ", '' as forma_pagamento")
                . ($colSubtotal ? ", p.{$colSubtotal} as subtotal" : ", 0 as subtotal")
                . ($colServicos ? ", p.{$colServicos} as servicos" : ", 0 as servicos")
                . ($colImpostos ? ", p.{$colImpostos} as impostos" : ", 0 as impostos")
                . " FROM pedidos p WHERE {$whereDet}"
                . " ORDER BY p.{$colCreatedAt} DESC LIMIT {$perPage} OFFSET {$offset}";
            $stDet = $this->db->prepare($sqlDet);
            $stDet->execute($paramsDet);
            $entradasDetalhadas = $stDet->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // === OPERACIONAL POR PESSOA/MÊS (despesas com favorecido agrupadas) ===
        $operacionalPorPessoa = [];
        try {
            $stOp = $this->db->prepare("SELECT COALESCE(NULLIF(favorecido,''),'Sem favorecido') as pessoa, DATE_FORMAT(competencia,'%Y-%m') as mes, COALESCE(SUM(valor),0) as total FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL AND favorecido IS NOT NULL AND favorecido != '' GROUP BY favorecido, mes ORDER BY total DESC");
            $stOp->execute([$dateStart, $dateEnd]);
            $operacionalPorPessoa = $stOp->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Maior categoria de gasto
        $maiorCategoria = '';
        if (!empty($despesasPorCategoria)) {
            $catConsolidado = [];
            foreach ($despesasPorCategoria as $c) {
                $k = $c['categoria'] ?? 'Sem categoria';
                $v = (float)($c['total'] ?? 0);
                if (($c['moeda'] ?? '') === 'USD') $v *= $taxaUsdBrl;
                $catConsolidado[$k] = ($catConsolidado[$k] ?? 0) + $v;
            }
            arsort($catConsolidado);
            $maiorCategoria = array_key_first($catConsolidado) ?? '';
        }

        echo json_encode([
            'success' => true,
            'taxaUsdBrl' => $taxaUsdBrl,
            'periodo' => ['inicio' => $dateStart, 'fim' => $dateEnd],
            'resumo' => [
                'total_entradas' => $totalEntradas,
                'total_entradas_brl' => $totalEntradasBrl,
                'total_entradas_usd' => $totalEntradasUsd,
                'custo_produtos' => $totalCustoProdutos,
                'custo_impostos_br' => $totalCustoImpostosBr,
                'custo_imposto_local' => $totalCustoImpostoLocal,
                'taxa_servico' => $totalTaxaServico,
                'total_descontos' => $totalDescontos,
                'total_awb' => $totalAwbBrl,
                'total_awb_usd' => $totalAwbUsd,
                'total_lastmile' => $totalLastMileBrl,
                'total_comissoes' => $totalComissoes,
                'total_despesas' => $totalDespesas,
                'total_despesas_brl' => $totalDespBrl,
                'total_despesas_usd' => $totalDespUsd,
                'resultado' => $resultado,
                'margem' => $totalEntradas > 0 ? round($resultado / $totalEntradas * 100, 1) : 0,
                'maior_categoria' => $maiorCategoria,
                'qtd_pedidos' => array_sum(array_column(array_values($meses), 'qtd_pedidos')),
            ],
            'meses' => array_values($meses),
            'gateways' => $gatewayData,
            'despesas_categoria' => $despesasPorCategoria,
            'despesas_favorecido' => $despesasPorFavorecido,
            'entradas_detalhadas' => $entradasDetalhadas,
            'entradas_paginacao' => ['page' => $page, 'per_page' => $perPage, 'total' => $totalEntradasCount, 'total_pages' => max(1, ceil($totalEntradasCount / $perPage))],
            'status_filter_dre' => $statusFilterDre,
            'operacional' => $operacionalPorPessoa,
            'conciliacao' => $conciliacao,
        ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * Exporta DRE Gerencial — 10 blocos fixos
     */
    public function exportar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $dateStart = $request->getParam('date_start', date('Y-01-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));
        $cols = []; try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $pick = function($c) use ($cols) { foreach ($c as $x) { if (in_array($x, $cols, true)) return $x; } return ''; };
        $colTotal=$pick(['total','valor_total']); $colSubtotal=$pick(['subtotal','subtotal_produtos']); $colServicos=$pick(['servicos','taxa_servico']); $colImpostos=$pick(['impostos','valor_impostos']); $colFrete=$pick(['frete','valor_frete']); $colMoeda=$pick(['moeda','currency']); $colStatus=$pick(['status']); $colPayGateway=$pick(['payment_gateway','gateway']); $colPayStatus=$pick(['payment_status']); $colFormaPgto=$pick(['forma_pagamento','payment_method']); $colCreatedAt=$pick(['created_at','data_criacao']); $colCliente=$pick(['cliente_nome','nome']); $colNumero=$pick(['numero_pedido','codigo_pedido']);
        $delF = in_array('deleted_at', $cols, true) ? "AND p.deleted_at IS NULL" : "";
        $taxaUsdBrl = 5.85; try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $taxaUsdBrl = $r; } catch (\Exception $e) {}
        $fV = function($v) { return number_format((float)($v??0), 2, ',', ''); };
        $toB = function($v, $m) use ($taxaUsdBrl) { return strtoupper(trim($m??'BRL'))==='USD' ? (float)$v*$taxaUsdBrl : (float)$v; };
        $sep = ';';

        // Buscar pedidos
        $sql = "SELECT p.id".($colNumero?", p.{$colNumero} as numero":", '' as numero").", p.{$colCreatedAt} as dt".($colCliente?", p.{$colCliente} as cli":", '' as cli").", p.{$colStatus} as st".($colPayStatus?", p.{$colPayStatus} as ps":", '' as ps").($colPayGateway?", p.{$colPayGateway} as gw":", '' as gw").($colFormaPgto?", p.{$colFormaPgto} as fp":", '' as fp").($colMoeda?", p.{$colMoeda} as mo":", 'BRL' as mo").($colSubtotal?", p.{$colSubtotal} as sub":", 0 as sub").($colServicos?", p.{$colServicos} as srv":", 0 as srv").($colImpostos?", p.{$colImpostos} as imp":", 0 as imp").($colFrete?", p.{$colFrete} as frt":", 0 as frt").", p.{$colTotal} as tot FROM pedidos p WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY) AND LOWER(COALESCE(p.{$colStatus},'')) NOT IN ('apagado','deleted','lixeira','trash') {$delF} ORDER BY p.{$colCreatedAt} DESC";
        $allP = []; try { $st = $this->db->prepare($sql); $st->execute([':ds'=>$dateStart,':de'=>$dateEnd]); $allP = $st->fetchAll(\PDO::FETCH_ASSOC)?:[]; } catch (\Exception $e) {}

        $pDre=[]; $pFora=[];
        foreach ($allP as $p) { $s=strtolower(trim($p['st']??'')); $ps=strtolower(trim($p['ps']??'')); if ($s==='pago' && in_array($ps,['approved','succeeded'],true)) $pDre[]=$p; else $pFora[]=$p; }

        // Buscar despesas
        $allD=[]; try { $st=$this->db->prepare("SELECT d.*,c.nome as cn FROM despesas d LEFT JOIN despesa_categorias c ON c.id=d.categoria_id WHERE d.competencia>=? AND d.competencia<=? AND d.deleted_at IS NULL ORDER BY d.vencimento"); $st->execute([$dateStart,$dateEnd]); $allD=$st->fetchAll(\PDO::FETCH_ASSOC)?:[]; } catch (\Exception $e) {}
        $dDre=[]; $dFora=[];
        foreach ($allD as $d) { if (strtolower(trim($d['status']??''))==='paga') $dDre[]=$d; else $dFora[]=$d; }

        // Totais
        $rec=0;$subT=0;$srvT=0;$impT=0;$frtT=0;
        foreach ($pDre as $p) { $m=$p['mo']??'BRL'; $rec+=$toB($p['tot'],$m); $subT+=$toB($p['sub'],$m); $srvT+=$toB($p['srv'],$m); $impT+=$toB($p['imp'],$m); $frtT+=$toB($p['frt'],$m); }
        $despR=0; $dCat=[];
        foreach ($dDre as $d) { $v=$toB($d['valor'],$d['moeda']??'BRL'); $despR+=$v; $c=$d['cn']??'Sem categoria'; $dCat[$c]=($dCat[$c]??0)+$v; }
        arsort($dCat);
        $res=$rec-$despR; $mrg=$rec>0?round($res/$rec*100,2):0;

        $fBS=[]; foreach ($pFora as $p) { $s=$p['st']??'?'; if(!isset($fBS[$s]))$fBS[$s]=['q'=>0,'v'=>0]; $fBS[$s]['q']++; $fBS[$s]['v']+=$toB($p['tot'],$p['mo']??'BRL'); }
        $dBS=[]; foreach ($dFora as $d) { $s=$d['status']??'?'; if(!isset($dBS[$s]))$dBS[$s]=['q'=>0,'v'=>0]; $dBS[$s]['q']++; $dBS[$s]['v']+=$toB($d['valor'],$d['moeda']??'BRL'); }
        $tfP=0;$tqP=0; foreach($fBS as $v){$tfP+=$v['v'];$tqP+=$v['q'];}
        $tfD=0;$tqD=0; foreach($dBS as $v){$tfD+=$v['v'];$tqD+=$v['q'];}

        $motP=function($p){$s=strtolower(trim($p['st']??''));$ps=strtolower(trim($p['ps']??''));if($s==='pagamento')return'Pagamento ainda nao confirmado';if($s==='carne_pagando')return'Carne em andamento';if($s==='pendente')return'Pedido pendente';if($s==='cancelado'||$s==='cancelled')return'Pedido cancelado';if($s==='processando')return'Em processamento';if($ps==='pending')return'Pagamento pendente';return'Status nao confirmado ('.$s.')';};
        $motD=function($d){$s=strtolower(trim($d['status']??''));if($s==='prevista')return'Despesa prevista, ainda nao paga';if($s==='a_vencer')return'Despesa a vencer';if($s==='vencida')return'Despesa vencida, nao paga';if(empty($d['data_pagamento']))return'Sem pagamento realizado';return'Status: '.$s;};

        // CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="DRE_Gerencial_'.$dateStart.'_'.$dateEnd.'.csv"');
        $o=fopen('php://output','w'); fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
        $w=function($l)use($o){fwrite($o,$l."\r\n");};

        $w("DEMONSTRATIVO DE RESULTADO GERENCIAL - BRAZILIANA SHOP");
        $w("Período{$sep}{$dateStart} a {$dateEnd}"); $w("Moeda do relatório{$sep}BRL"); $w("Taxa USD→BRL{$sep}".$fV($taxaUsdBrl)); $w("Gerado em{$sep}".date('d/m/Y H:i:s')); $w("");

        $w("=== 1. RESUMO DA DRE REALIZADA ==="); $w("Descrição{$sep}Valor BRL");
        $w("Receita Operacional (total pedidos){$sep}".$fV($rec)); $w("  Custo de Produtos{$sep}".$fV($subT)); $w("  Custo de Impostos Brasil{$sep}".$fV($impT)); $w("  Taxa de Serviço{$sep}".$fV($srvT)); $w("(-) Despesas{$sep}".$fV($despR)); $w("(=) Resultado{$sep}".$fV($res)); $w("Margem{$sep}".$fV($mrg)."%"); $w("");

        $w("=== 2. COMPOSIÇÃO DA RECEITA OPERACIONAL ==="); $w("Descrição{$sep}Valor BRL");
        $w("Custo de Produtos (subtotal){$sep}".$fV($subT)); $w("Custo de Impostos Brasil{$sep}".$fV($impT)); $w("Taxa de Serviço{$sep}".$fV($srvT)); $w("Total Receita Operacional{$sep}".$fV($rec)); $w("Quantidade de pedidos pagos{$sep}".count($pDre)); $w("");

        $w("=== 3. COMPOSIÇÃO DAS DESPESAS REALIZADAS ==="); $w("Categoria{$sep}Valor BRL");
        foreach($dCat as $c=>$v)$w(str_replace($sep,' ',$c).$sep.$fV($v));
        $w("TOTAL DESPESAS REALIZADAS{$sep}".$fV($despR)); $w("Quantidade de despesas pagas{$sep}".count($dDre)); $w("");

        $w("=== 4. PEDIDOS CONSIDERADOS NA RECEITA REALIZADA ===");
        $w("ID{$sep}Numero{$sep}Data{$sep}Cliente{$sep}Status{$sep}Status Pgto{$sep}Gateway{$sep}Forma Pgto{$sep}Moeda{$sep}Subtotal{$sep}Servicos{$sep}Impostos{$sep}Frete{$sep}Total{$sep}Total BRL");
        foreach($pDre as $p){$m=strtoupper(trim($p['mo']??'BRL'));$w($p['id'].$sep.str_replace($sep,' ',$p['numero']??'').$sep.substr($p['dt']??'',0,10).$sep.str_replace($sep,' ',$p['cli']??'').$sep.($p['st']??'').$sep.($p['ps']??'').$sep.($p['gw']??'').$sep.($p['fp']??'').$sep.$m.$sep.$fV($p['sub']).$sep.$fV($p['srv']).$sep.$fV($p['imp']).$sep.$fV($p['frt']).$sep.$fV($p['tot']).$sep.$fV($toB($p['tot'],$m)));}
        $w("TOTAL PEDIDOS CONSIDERADOS{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}".$fV($rec)); $w("");

        $w("=== 5. DESPESAS CONSIDERADAS NO RESULTADO REALIZADO ===");
        $w("ID{$sep}Descrição{$sep}Categoria{$sep}Tipo{$sep}Favorecido{$sep}Competência{$sep}Vencimento{$sep}Pagamento{$sep}Moeda{$sep}Valor{$sep}Valor BRL{$sep}Status{$sep}Origem");
        foreach($dDre as $d){$m=strtoupper(trim($d['moeda']??'BRL'));$w($d['id'].$sep.str_replace($sep,' ',$d['descricao']??'').$sep.str_replace($sep,' ',$d['cn']??'').$sep.($d['tipo']??'').$sep.str_replace($sep,' ',$d['favorecido']??'').$sep.($d['competencia']?date('m/Y',strtotime($d['competencia'])):'').$sep.($d['vencimento']?date('d/m/Y',strtotime($d['vencimento'])):'').$sep.($d['data_pagamento']?date('d/m/Y',strtotime($d['data_pagamento'])):'').$sep.$m.$sep.$fV($d['valor']).$sep.$fV($toB($d['valor'],$m)).$sep.($d['status']??'').$sep.($d['origem']??''));}
        $w("TOTAL DESPESAS CONSIDERADAS{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}".$fV($despR).$sep.$fV($despR)."{$sep}{$sep}"); $w("");

        $w("=== 6. PEDIDOS FORA DA RECEITA REALIZADA ==="); $w("Status{$sep}Quantidade{$sep}Valor BRL");
        foreach($fBS as $s=>$v)$w(str_replace($sep,' ',$s).$sep.$v['q'].$sep.$fV($v['v']));
        $w("TOTAL FORA DA RECEITA REALIZADA{$sep}{$tqP}{$sep}".$fV($tfP)); $w("");

        $w("=== 7. DESPESAS FORA DO RESULTADO REALIZADO ==="); $w("Status{$sep}Quantidade{$sep}Valor BRL");
        foreach($dBS as $s=>$v)$w(str_replace($sep,' ',$s).$sep.$v['q'].$sep.$fV($v['v']));
        $w("TOTAL FORA DO RESULTADO REALIZADO{$sep}{$tqD}{$sep}".$fV($tfD)); $w("");

        $w("=== 8. DETALHAMENTO DOS PEDIDOS EXCLUÍDOS DA DRE ===");
        $w("ID{$sep}Numero{$sep}Data{$sep}Cliente{$sep}Status{$sep}Status Pgto{$sep}Gateway{$sep}Forma Pgto{$sep}Moeda{$sep}Subtotal{$sep}Servicos{$sep}Impostos{$sep}Frete{$sep}Total{$sep}Total BRL{$sep}Motivo da exclusão");
        foreach($pFora as $p){$m=strtoupper(trim($p['mo']??'BRL'));$w($p['id'].$sep.str_replace($sep,' ',$p['numero']??'').$sep.substr($p['dt']??'',0,10).$sep.str_replace($sep,' ',$p['cli']??'').$sep.($p['st']??'').$sep.($p['ps']??'').$sep.($p['gw']??'').$sep.($p['fp']??'').$sep.$m.$sep.$fV($p['sub']).$sep.$fV($p['srv']).$sep.$fV($p['imp']).$sep.$fV($p['frt']).$sep.$fV($p['tot']).$sep.$fV($toB($p['tot'],$m)).$sep.$motP($p));}
        $w("");

        $w("=== 9. DETALHAMENTO DAS DESPESAS EXCLUÍDAS DA DRE ===");
        $w("ID{$sep}Descrição{$sep}Categoria{$sep}Tipo{$sep}Favorecido{$sep}Competência{$sep}Vencimento{$sep}Pagamento{$sep}Moeda{$sep}Valor{$sep}Valor BRL{$sep}Status{$sep}Origem{$sep}Motivo da exclusão");
        foreach($dFora as $d){$m=strtoupper(trim($d['moeda']??'BRL'));$w($d['id'].$sep.str_replace($sep,' ',$d['descricao']??'').$sep.str_replace($sep,' ',$d['cn']??'').$sep.($d['tipo']??'').$sep.str_replace($sep,' ',$d['favorecido']??'').$sep.($d['competencia']?date('m/Y',strtotime($d['competencia'])):'').$sep.($d['vencimento']?date('d/m/Y',strtotime($d['vencimento'])):'').$sep.($d['data_pagamento']?date('d/m/Y',strtotime($d['data_pagamento'])):'').$sep.$m.$sep.$fV($d['valor']).$sep.$fV($toB($d['valor'],$m)).$sep.($d['status']??'').$sep.($d['origem']??'').$sep.$motD($d));}
        $w("");

        $w("=== 10. CONCILIAÇÃO DO PERÍODO ==="); $w("Descrição{$sep}Valor BRL");
        $w("Receita Operacional (total pedidos){$sep}".$fV($rec)); $w("  Custo de Produtos{$sep}".$fV($subT)); $w("  Custo de Impostos Brasil{$sep}".$fV($impT)); $w("  Despesas Operacionais{$sep}".$fV($despR)); $w("(-) Total Deduções{$sep}".$fV($subT+$impT+$despR)); $w("(=) Resultado{$sep}".$fV($res));
        $w("Pedidos ainda não realizados{$sep}".$fV($tfP)); $w("Despesas ainda não realizadas{$sep}".$fV($tfD));
        $w("Quantidade de pedidos pagos{$sep}".count($pDre)); $w("Quantidade de pedidos fora da DRE{$sep}{$tqP}");
        $w("Quantidade de despesas pagas{$sep}".count($dDre)); $w("Quantidade de despesas fora da DRE{$sep}{$tqD}");

        fclose($o); exit;
    }

    /**
     * Conciliação Financeira — consulta APIs dos gateways e compara com dados locais
     * Se tem cache recente (< 12h), usa o cache. Senão consulta ao vivo.
     * Parâmetro ?force=1 força consulta ao vivo.
     */
    public function conciliacao(Request $request) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $auth = new AuthService();
            if (!isset($_SESSION['usuario_id'])) { echo json_encode(['error' => 'Não autenticado']); exit; }
        } catch (\Throwable $e) { echo json_encode(['error' => $e->getMessage()]); exit; }

        $force = ($_GET['force'] ?? '0') === '1';

        // Tentar cache
        if (!$force) {
            try {
                $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'conciliacao_cache' LIMIT 1");
                $st->execute();
                $cached = $st->fetchColumn();
                if ($cached) {
                    $data = json_decode($cached, true);
                    if ($data && !empty($data['_timestamp']) && (time() - $data['_timestamp']) < 43200) { // 12h
                        $data['_from_cache'] = true;
                        $data['_cache_age'] = date('d/m/Y H:i', $data['_timestamp']);
                        echo json_encode($data, JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }
            } catch (\Exception $e) {}
        }

        // Consultar ao vivo
        $resultado = $this->executarConciliacao();
        $resultado['_from_cache'] = false;

        // Salvar cache
        $this->salvarCacheConciliacao($resultado);

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Endpoint de cron — roda a conciliação e salva no cache
     * Chamar via: curl https://brazilianashop.com.br/admin/dre-completo/cron-conciliacao?secret=SEU_CRON_SECRET
     */
    public function cronConciliacao(Request $request) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        // Validar secret do cron
        $secret = $_GET['secret'] ?? '';
        $cronSecret = '';
        try {
            $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'cron_secret' LIMIT 1");
            $st->execute();
            $cronSecret = trim((string)($st->fetchColumn() ?: ''));
        } catch (\Exception $e) {}

        if ($cronSecret === '' || $secret !== $cronSecret) {
            echo json_encode(['error' => 'Secret inválido']);
            exit;
        }

        $resultado = $this->executarConciliacao();
        $this->salvarCacheConciliacao($resultado);

        echo json_encode(['success' => true, 'divergencias' => $resultado['resumo']['divergencias_total'] ?? 0, 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }

    private function executarConciliacao(): array {
        $resultado = [
            'stripe' => $this->conciliacaoStripe(),
            'cambioreal' => $this->conciliacaoCambioReal('cambioreal'),
            'cambioreal_taxas' => $this->conciliacaoCambioReal('cambioreal_taxas'),
            'resumo' => [],
            '_timestamp' => time(),
        ];

        $resultado['resumo'] = [
            'stripe_saldo' => $resultado['stripe']['saldo'] ?? [],
            'cr_total_recebido' => ($resultado['cambioreal']['total_recebido_usd'] ?? 0) + ($resultado['cambioreal_taxas']['total_recebido_usd'] ?? 0),
            'divergencias_total' => count($resultado['stripe']['divergencias'] ?? []) + count($resultado['cambioreal']['divergencias'] ?? []) + count($resultado['cambioreal_taxas']['divergencias'] ?? []),
        ];

        return $resultado;
    }

    private function salvarCacheConciliacao(array $data): void {
        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $st = $this->db->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'conciliacao_cache'");
            $st->execute();
            if ((int)$st->fetchColumn() > 0) {
                $this->db->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'conciliacao_cache'")->execute([$json]);
            } else {
                $this->db->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('conciliacao_cache', ?)")->execute([$json]);
            }
        } catch (\Exception $e) {
            error_log('[CONCILIACAO] Erro ao salvar cache: ' . $e->getMessage());
        }
    }

    private function conciliacaoStripe(): array {
        $result = ['saldo' => [], 'transacoes' => [], 'divergencias' => [], 'erro' => null];

        try {
            // Buscar chave Stripe
            $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave IN ('pagamentos_stripe_secret_key','stripe_secret_key') AND valor != '' LIMIT 1");
            $st->execute();
            $stripeKey = trim((string)($st->fetchColumn() ?: ''));
            if ($stripeKey === '') { $result['erro'] = 'Stripe não configurado'; return $result; }

            // 1. Saldo atual
            $saldoResp = $this->stripeRequest($stripeKey, 'GET', '/v1/balance');
            if (!empty($saldoResp['available'])) {
                foreach ($saldoResp['available'] as $b) {
                    $result['saldo'][] = ['moeda' => strtoupper($b['currency']), 'disponivel' => $b['amount'] / 100, 'pendente' => 0];
                }
            }
            if (!empty($saldoResp['pending'])) {
                foreach ($saldoResp['pending'] as $b) {
                    foreach ($result['saldo'] as &$s) {
                        if ($s['moeda'] === strtoupper($b['currency'])) { $s['pendente'] = $b['amount'] / 100; break; }
                    }
                }
            }

            // 2. Últimas transações (últimos 30 dias)
            $desde = strtotime('-30 days');
            $txResp = $this->stripeRequest($stripeKey, 'GET', '/v1/balance_transactions?limit=100&created[gte]=' . $desde . '&type=charge');
            $transacoes = $txResp['data'] ?? [];

            foreach ($transacoes as $tx) {
                $result['transacoes'][] = [
                    'id' => $tx['id'],
                    'valor' => ($tx['amount'] ?? 0) / 100,
                    'moeda' => strtoupper($tx['currency'] ?? 'USD'),
                    'taxa' => ($tx['fee'] ?? 0) / 100,
                    'liquido' => ($tx['net'] ?? 0) / 100,
                    'descricao' => $tx['description'] ?? '',
                    'data' => date('Y-m-d H:i', $tx['created'] ?? time()),
                    'source' => $tx['source'] ?? '',
                ];
            }

            // 3. Comparar com pedido_pagamentos local
            $stLocal = $this->db->prepare("SELECT payment_id, pedido_id, valor, gateway_status FROM pedido_pagamentos WHERE gateway = 'stripe' AND created_at >= ? ORDER BY created_at DESC LIMIT 200");
            $stLocal->execute([date('Y-m-d', $desde)]);
            $locais = $stLocal->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $localMap = [];
            foreach ($locais as $l) { $localMap[$l['payment_id']] = $l; }

            // Transações no Stripe que não estão no sistema
            foreach ($result['transacoes'] as $tx) {
                $source = $tx['source'];
                if ($source && !isset($localMap[$source])) {
                    $result['divergencias'][] = ['tipo' => 'no_gateway_sem_local', 'gateway' => 'stripe', 'id' => $source, 'valor' => $tx['valor'], 'data' => $tx['data'], 'msg' => 'Recebido no Stripe mas sem registro local'];
                }
            }

            // Pagamentos locais marcados como pagos mas sem correspondência no Stripe
            $stripeIds = array_column($result['transacoes'], 'source');
            foreach ($locais as $l) {
                if (in_array($l['gateway_status'], ['paid','succeeded','approved']) && $l['payment_id'] && !in_array($l['payment_id'], $stripeIds)) {
                    $result['divergencias'][] = ['tipo' => 'local_sem_gateway', 'gateway' => 'stripe', 'id' => $l['payment_id'], 'pedido_id' => $l['pedido_id'], 'valor' => $l['valor'], 'msg' => 'Marcado como pago localmente mas não encontrado no Stripe'];
                }
            }

        } catch (\Exception $e) {
            $result['erro'] = $e->getMessage();
        }

        return $result;
    }

    private function conciliacaoCambioReal(string $gateway): array {
        $result = ['total_recebido_usd' => 0, 'total_recebido_brl' => 0, 'transacoes' => [], 'divergencias' => [], 'erro' => null];

        try {
            // Buscar tokens de pagamento dos últimos 30 dias
            $desde = date('Y-m-d', strtotime('-30 days'));
            $st = $this->db->prepare("SELECT payment_id, pedido_id, valor, gateway_status, metodo, created_at FROM pedido_pagamentos WHERE gateway = ? AND created_at >= ? AND payment_id IS NOT NULL AND payment_id != '' ORDER BY created_at DESC LIMIT 200");
            $st->execute([$gateway, $desde]);
            $locais = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Também buscar parcelas do carnê
            if ($gateway === 'cambioreal') {
                $stCarne = $this->db->prepare("SELECT cp.boleto_produtos_id_externo as payment_id, c.pedido_id, cp.valor_produtos as valor, cp.status as gateway_status, cp.created_at FROM carne_parcelas cp JOIN carnes c ON cp.carne_id = c.id WHERE cp.boleto_produtos_id_externo IS NOT NULL AND cp.boleto_produtos_id_externo != '' AND cp.created_at >= ? ORDER BY cp.created_at DESC LIMIT 100");
                $stCarne->execute([$desde]);
                $carneParcelas = $stCarne->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $locais = array_merge($locais, $carneParcelas);
            } elseif ($gateway === 'cambioreal_taxas') {
                $stCarne = $this->db->prepare("SELECT cp.boleto_taxas_id_externo as payment_id, c.pedido_id, cp.valor_taxas as valor, cp.status as gateway_status, cp.created_at FROM carne_parcelas cp JOIN carnes c ON cp.carne_id = c.id WHERE cp.boleto_taxas_id_externo IS NOT NULL AND cp.boleto_taxas_id_externo != '' AND cp.created_at >= ? ORDER BY cp.created_at DESC LIMIT 100");
                $stCarne->execute([$desde]);
                $carneParcelas = $stCarne->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $locais = array_merge($locais, $carneParcelas);
            }

            // Consultar status de cada token na API do Câmbio Real
            $paymentService = new \App\Services\PaymentService();
            $consultados = 0;
            $maxConsultas = 50; // Limitar para não travar

            foreach ($locais as $l) {
                $token = $l['payment_id'];
                $statusLocal = strtoupper(trim((string)($l['gateway_status'] ?? '')));

                $tx = ['token' => $token, 'pedido_id' => $l['pedido_id'] ?? null, 'valor_local' => (float)($l['valor'] ?? 0), 'status_local' => $statusLocal, 'status_gateway' => null, 'valor_gateway' => null, 'data' => $l['created_at'] ?? ''];

                // Consultar API (com limite)
                if ($consultados < $maxConsultas) {
                    try {
                        $resp = $paymentService->obterTransacaoCambioReal($token);
                        $data = $resp['data'] ?? [];
                        $tx['status_gateway'] = strtoupper(trim((string)($data['status'] ?? '')));
                        $tx['valor_gateway'] = (float)($data['amount'] ?? 0);
                        $tx['moeda'] = strtoupper($data['currency'] ?? 'USD');
                        $tx['beneficiary'] = (float)($data['beneficiary'] ?? 0);
                        $consultados++;

                        // Somar recebidos
                        if (in_array($tx['status_gateway'], ['SOLICITACAO_PAGO','SOLICITACAO_FINALIZADA'])) {
                            $result['total_recebido_usd'] += $tx['beneficiary'] ?: $tx['valor_gateway'];
                        }
                    } catch (\Exception $e) {
                        $tx['status_gateway'] = 'ERRO: ' . $e->getMessage();
                    }
                }

                $result['transacoes'][] = $tx;

                // Detectar divergências
                if ($tx['status_gateway'] !== null) {
                    $pagoGateway = in_array($tx['status_gateway'], ['SOLICITACAO_PAGO','SOLICITACAO_FINALIZADA','ON_HOLD']);
                    $pagoLocal = in_array($statusLocal, ['SOLICITACAO_PAGO','SOLICITACAO_FINALIZADA','PAGA','PAID','APPROVED']);

                    if ($pagoGateway && !$pagoLocal) {
                        $result['divergencias'][] = ['tipo' => 'pago_gateway_nao_local', 'token' => $token, 'pedido_id' => $l['pedido_id'] ?? null, 'status_gateway' => $tx['status_gateway'], 'status_local' => $statusLocal, 'msg' => 'Pago no Câmbio Real mas NÃO marcado como pago no sistema'];
                    } elseif (!$pagoGateway && $pagoLocal) {
                        $result['divergencias'][] = ['tipo' => 'pago_local_nao_gateway', 'token' => $token, 'pedido_id' => $l['pedido_id'] ?? null, 'status_gateway' => $tx['status_gateway'], 'status_local' => $statusLocal, 'msg' => 'Marcado como pago no sistema mas NÃO pago no Câmbio Real'];
                    }
                }
            }

            $result['total_consultados'] = $consultados;
            $result['total_registros'] = count($locais);

        } catch (\Exception $e) {
            $result['erro'] = $e->getMessage();
        }

        return $result;
    }

    private function stripeRequest(string $key, string $method, string $path): array {
        $url = 'https://api.stripe.com' . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$resp, true) ?: [];
        if ($code < 200 || $code >= 300) {
            throw new \Exception('Stripe HTTP ' . $code . ': ' . ($data['error']['message'] ?? 'Erro desconhecido'));
        }
        return $data;
    }
}
