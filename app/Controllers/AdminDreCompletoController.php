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
                echo json_encode(['success' => false, 'error' => __('admin.dre.not_authenticated', 'Não autenticado')]);
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
        $colMoeda = $pick(['moeda','currency']);
        $colStatus = $pick(['status']);
        $colPayGateway = $pick(['payment_gateway','gateway']);
        $colPayStatus = $pick(['payment_status']);
        $colFormaPgto = $pick(['forma_pagamento','payment_method']);
        $colCreatedAt = $pick(['created_at','data_criacao']);
        $deletedFilter = in_array('deleted_at', $cols, true) ? "AND p.deleted_at IS NULL" : "";

        // Guard: se colunas essenciais não existem, retornar vazio
        if (!$colTotal || !$colCreatedAt || !$colStatus) {
            echo json_encode(['success' => true, 'taxaUsdBrl' => \App\Core\ExchangeRate::getUsdToBrl(), 'periodo' => ['inicio' => $dateStart, 'fim' => $dateEnd], 'resumo' => ['total_entradas' => 0, 'total_entradas_brl' => 0, 'total_entradas_usd' => 0, 'total_despesas' => 0, 'total_despesas_brl' => 0, 'total_despesas_usd' => 0, 'total_descontos' => 0, 'total_descontos_brl' => 0, 'total_descontos_usd' => 0, 'total_peso_kg' => 0, 'resultado' => 0, 'margem' => 0, 'maior_categoria' => '', 'qtd_pedidos' => 0], 'meses' => [], 'gateways' => [], 'despesas_categoria' => [], 'despesas_favorecido' => [], 'entradas_detalhadas' => [], 'entradas_paginacao' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1], 'status_filter_dre' => '', 'operacional' => [], 'conciliacao' => ['total_creditos' => 0, 'total_debitos' => 0, 'saldo_final' => 0, 'qtd_lancamentos' => 0]], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Taxa USD→BRL
        $taxaUsdBrl = \App\Core\ExchangeRate::getUsdToBrl();

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
            $semFavorecido = str_replace("'", "''", __('admin.dre.no_payee', 'Sem favorecido'));
            $stDF = $this->db->prepare("SELECT COALESCE(NULLIF(favorecido,''),'{$semFavorecido}') as favorecido, UPPER(COALESCE(moeda,'BRL')) as moeda, COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL GROUP BY favorecido, moeda ORDER BY total DESC LIMIT 30");
            $stDF->execute([$dateStart, $dateEnd]);
            $despesasPorFavorecido = $stDF->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // === RESUMO MENSAL CONSOLIDADO ===
        // Montar mês a mês: entradas - saídas = resultado
        $meses = [];
        foreach ($entradasPorMes as $r) {
            $m = $r['mes'];
            if (!isset($meses[$m])) $meses[$m] = ['mes' => $m, 'entradas_brl' => 0, 'entradas_usd' => 0, 'despesas_brl' => 0, 'despesas_usd' => 0, 'qtd_pedidos' => 0, 'qtd_despesas' => 0];
            $val = (float)$r['total'];
            if ($r['moeda'] === 'USD') $meses[$m]['entradas_usd'] += $val;
            else $meses[$m]['entradas_brl'] += $val;
            $meses[$m]['qtd_pedidos'] += (int)$r['qtd'];
        }
        foreach ($despesasPorMes as $r) {
            $m = $r['mes'];
            if (!isset($meses[$m])) $meses[$m] = ['mes' => $m, 'entradas_brl' => 0, 'entradas_usd' => 0, 'despesas_brl' => 0, 'despesas_usd' => 0, 'qtd_pedidos' => 0, 'qtd_despesas' => 0];
            $val = (float)$r['total'];
            if ($r['moeda'] === 'USD') $meses[$m]['despesas_usd'] += $val;
            else $meses[$m]['despesas_brl'] += $val;
            $meses[$m]['qtd_despesas'] += (int)$r['qtd'];
        }
        ksort($meses);

        // Calcular totais
        $totalEntradasBrl = 0; $totalEntradasUsd = 0; $totalDespBrl = 0; $totalDespUsd = 0;
        foreach ($meses as &$m) {
            $m['entradas_total'] = $m['entradas_brl'] + ($m['entradas_usd'] * $taxaUsdBrl);
            $m['despesas_total'] = $m['despesas_brl'] + ($m['despesas_usd'] * $taxaUsdBrl);
            $m['resultado'] = $m['entradas_total'] - $m['despesas_total'];
            $totalEntradasBrl += $m['entradas_brl'];
            $totalEntradasUsd += $m['entradas_usd'];
            $totalDespBrl += $m['despesas_brl'];
            $totalDespUsd += $m['despesas_usd'];
        }
        unset($m);

        $totalEntradas = $totalEntradasBrl + ($totalEntradasUsd * $taxaUsdBrl);
        $totalDespesas = $totalDespBrl + ($totalDespUsd * $taxaUsdBrl);
        $resultado = $totalEntradas - $totalDespesas;

        // === TOTAL DESCONTOS ===
        $totalDescontosUsd = 0; $totalDescontosBrl = 0;
        $colDesconto = $pick(['desconto_global_aplicado','desconto','valor_desconto','discount']);
        if ($colDesconto !== '') {
            try {
                $moedaSelectD = $colMoeda ? "UPPER(COALESCE(p.{$colMoeda},'USD')) as moeda" : "'USD' as moeda";
                $stDesc = $this->db->prepare("SELECT {$moedaSelectD}, COALESCE(SUM(p.{$colDesconto}),0) as total FROM pedidos p WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY) AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses} {$deletedFilter} GROUP BY moeda");
                $stDesc->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                foreach ($stDesc->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    if ($r['moeda'] === 'USD') $totalDescontosUsd += (float)$r['total'];
                    else $totalDescontosBrl += (float)$r['total'];
                }
            } catch (\Exception $e) {}
        }
        $totalDescontos = $totalDescontosBrl + ($totalDescontosUsd * $taxaUsdBrl);

        // === TOTAL PESO (KG) ===
        $totalPesoKg = 0;
        $colPeso = $pick(['peso_total','peso','weight']);
        if ($colPeso !== '') {
            try {
                $stPeso = $this->db->prepare("SELECT COALESCE(SUM(p.{$colPeso}),0) as total FROM pedidos p WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY) AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses} {$deletedFilter}");
                $stPeso->execute([':ds' => $dateStart, ':de' => $dateEnd]);
                $totalPesoKg = (float)$stPeso->fetchColumn();
            } catch (\Exception $e) {}
        }

        // === CONCILIAÇÃO ===
        $conciliacao = [
            'total_creditos' => $totalEntradas,
            'total_debitos' => $totalDespesas,
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
            $semFavorecidoOp = str_replace("'", "''", __('admin.dre.no_payee', 'Sem favorecido'));
            $stOp = $this->db->prepare("SELECT COALESCE(NULLIF(favorecido,''),'{$semFavorecidoOp}') as pessoa, DATE_FORMAT(competencia,'%Y-%m') as mes, COALESCE(SUM(valor),0) as total FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL AND favorecido IS NOT NULL AND favorecido != '' GROUP BY favorecido, mes ORDER BY total DESC");
            $stOp->execute([$dateStart, $dateEnd]);
            $operacionalPorPessoa = $stOp->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Maior categoria de gasto
        $maiorCategoria = '';
        if (!empty($despesasPorCategoria)) {
            $catConsolidado = [];
            foreach ($despesasPorCategoria as $c) {
                $k = $c['categoria'] ?? __('admin.dre.no_category', 'Sem categoria');
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
                'total_despesas' => $totalDespesas,
                'total_despesas_brl' => $totalDespBrl,
                'total_despesas_usd' => $totalDespUsd,
                'total_descontos' => $totalDescontos,
                'total_descontos_brl' => $totalDescontosBrl,
                'total_descontos_usd' => $totalDescontosUsd,
                'total_peso_kg' => $totalPesoKg,
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
        $taxaUsdBrl = \App\Core\ExchangeRate::getUsdToBrl();
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

        $motP=function($p){$s=strtolower(trim($p['st']??''));$ps=strtolower(trim($p['ps']??''));if($s==='pagamento')return __('admin.dre.reason_payment_unconfirmed','Pagamento ainda nao confirmado');if($s==='carne_pagando')return __('admin.dre.reason_installment_ongoing','Carne em andamento');if($s==='pendente')return __('admin.dre.reason_order_pending','Pedido pendente');if($s==='cancelado'||$s==='cancelled')return __('admin.dre.reason_order_cancelled','Pedido cancelado');if($s==='processando')return __('admin.dre.reason_processing','Em processamento');if($ps==='pending')return __('admin.dre.reason_payment_pending','Pagamento pendente');return __('admin.dre.reason_status_unconfirmed','Status nao confirmado (').$s.')';};
        $motD=function($d){$s=strtolower(trim($d['status']??''));if($s==='prevista')return __('admin.dre.reason_expense_forecast','Despesa prevista, ainda nao paga');if($s==='a_vencer')return __('admin.dre.reason_expense_due','Despesa a vencer');if($s==='vencida')return __('admin.dre.reason_expense_overdue','Despesa vencida, nao paga');if(empty($d['data_pagamento']))return __('admin.dre.reason_no_payment','Sem pagamento realizado');return __('admin.dre.reason_status','Status: ').$s;};

        // CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="DRE_Gerencial_'.$dateStart.'_'.$dateEnd.'.csv"');
        $o=fopen('php://output','w'); fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
        $w=function($l)use($o){fwrite($o,$l."\r\n");};

        $w(__('admin.dre.csv_header', 'DEMONSTRATIVO DE RESULTADO GERENCIAL - BRAZILIANA SHOP'));
        $w(__('admin.dre.csv_period', 'Período')."{$sep}{$dateStart} a {$dateEnd}"); $w(__('admin.dre.csv_report_currency', 'Moeda do relatório')."{$sep}BRL"); $w(__('admin.dre.csv_rate_usd_brl', 'Taxa USD→BRL')."{$sep}".$fV($taxaUsdBrl)); $w(__('admin.dre.csv_generated_at', 'Gerado em')."{$sep}".date('d/m/Y H:i:s')); $w("");

        $w(__('admin.dre.csv_s1', '=== 1. RESUMO DA DRE REALIZADA ===')); $w(__('admin.dre.csv_col_description', 'Descrição')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        $w(__('admin.dre.csv_realized_revenue', 'Receita realizada')."{$sep}".$fV($rec)); $w(__('admin.dre.csv_realized_expenses', '(-) Despesas realizadas')."{$sep}".$fV($despR)); $w(__('admin.dre.csv_realized_result', '(=) Resultado realizado')."{$sep}".$fV($res)); $w(__('admin.dre.csv_realized_margin', 'Margem realizada')."{$sep}".$fV($mrg)."%"); $w("");

        $w(__('admin.dre.csv_s2', '=== 2. COMPOSIÇÃO DA RECEITA REALIZADA ===')); $w(__('admin.dre.csv_col_description', 'Descrição')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        $w(__('admin.dre.csv_products', 'Produtos')."{$sep}".$fV($subT)); $w(__('admin.dre.csv_services', 'Serviços')."{$sep}".$fV($srvT)); $w(__('admin.dre.csv_taxes_charged', 'Impostos cobrados')."{$sep}".$fV($impT)); $w(__('admin.dre.csv_shipping_charged', 'Frete cobrado')."{$sep}".$fV($frtT)); $w(__('admin.dre.csv_total_realized_revenue', 'Total da receita realizada')."{$sep}".$fV($rec)); $w(__('admin.dre.csv_qty_paid_orders', 'Quantidade de pedidos pagos')."{$sep}".count($pDre)); $w("");

        $w(__('admin.dre.csv_s3', '=== 3. COMPOSIÇÃO DAS DESPESAS REALIZADAS ===')); $w(__('admin.dre.csv_col_category', 'Categoria')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        foreach($dCat as $c=>$v)$w(str_replace($sep,' ',$c).$sep.$fV($v));
        $w(__('admin.dre.csv_total_realized_expenses', 'TOTAL DESPESAS REALIZADAS')."{$sep}".$fV($despR)); $w(__('admin.dre.csv_qty_paid_expenses', 'Quantidade de despesas pagas')."{$sep}".count($dDre)); $w("");

        $w(__('admin.dre.csv_s4', '=== 4. PEDIDOS CONSIDERADOS NA RECEITA REALIZADA ==='));
        $w("ID{$sep}".__('admin.dre.csv_col_number', 'Numero')."{$sep}".__('admin.dre.csv_col_date', 'Data')."{$sep}".__('admin.dre.csv_col_customer', 'Cliente')."{$sep}".__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_pay_status', 'Status Pgto')."{$sep}".__('admin.dre.csv_col_gateway', 'Gateway')."{$sep}".__('admin.dre.csv_col_pay_method', 'Forma Pgto')."{$sep}".__('admin.dre.csv_col_currency', 'Moeda')."{$sep}".__('admin.dre.csv_col_subtotal', 'Subtotal')."{$sep}".__('admin.dre.csv_col_services', 'Servicos')."{$sep}".__('admin.dre.csv_col_taxes', 'Impostos')."{$sep}".__('admin.dre.csv_col_shipping', 'Frete')."{$sep}".__('admin.dre.csv_col_total', 'Total')."{$sep}".__('admin.dre.csv_col_total_brl', 'Total BRL'));
        foreach($pDre as $p){$m=strtoupper(trim($p['mo']??'BRL'));$w($p['id'].$sep.str_replace($sep,' ',$p['numero']??'').$sep.substr($p['dt']??'',0,10).$sep.str_replace($sep,' ',$p['cli']??'').$sep.($p['st']??'').$sep.($p['ps']??'').$sep.($p['gw']??'').$sep.($p['fp']??'').$sep.$m.$sep.$fV($p['sub']).$sep.$fV($p['srv']).$sep.$fV($p['imp']).$sep.$fV($p['frt']).$sep.$fV($p['tot']).$sep.$fV($toB($p['tot'],$m)));}
        $w(__('admin.dre.csv_total_orders_considered', 'TOTAL PEDIDOS CONSIDERADOS')."{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}".$fV($rec)); $w("");

        $w(__('admin.dre.csv_s5', '=== 5. DESPESAS CONSIDERADAS NO RESULTADO REALIZADO ==='));
        $w("ID{$sep}".__('admin.dre.csv_col_description', 'Descrição')."{$sep}".__('admin.dre.csv_col_category', 'Categoria')."{$sep}".__('admin.dre.csv_col_type', 'Tipo')."{$sep}".__('admin.dre.csv_col_payee', 'Favorecido')."{$sep}".__('admin.dre.csv_col_competence', 'Competência')."{$sep}".__('admin.dre.csv_col_due', 'Vencimento')."{$sep}".__('admin.dre.csv_col_payment', 'Pagamento')."{$sep}".__('admin.dre.csv_col_currency', 'Moeda')."{$sep}".__('admin.dre.csv_col_value', 'Valor')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL')."{$sep}".__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_origin', 'Origem'));
        foreach($dDre as $d){$m=strtoupper(trim($d['moeda']??'BRL'));$w($d['id'].$sep.str_replace($sep,' ',$d['descricao']??'').$sep.str_replace($sep,' ',$d['cn']??'').$sep.($d['tipo']??'').$sep.str_replace($sep,' ',$d['favorecido']??'').$sep.($d['competencia']?date('m/Y',strtotime($d['competencia'])):'').$sep.($d['vencimento']?date('d/m/Y',strtotime($d['vencimento'])):'').$sep.($d['data_pagamento']?date('d/m/Y',strtotime($d['data_pagamento'])):'').$sep.$m.$sep.$fV($d['valor']).$sep.$fV($toB($d['valor'],$m)).$sep.($d['status']??'').$sep.($d['origem']??''));}
        $w(__('admin.dre.csv_total_expenses_considered', 'TOTAL DESPESAS CONSIDERADAS')."{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}".$fV($despR).$sep.$fV($despR)."{$sep}{$sep}"); $w("");

        $w(__('admin.dre.csv_s6', '=== 6. PEDIDOS FORA DA RECEITA REALIZADA ===')); $w(__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_quantity', 'Quantidade')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        foreach($fBS as $s=>$v)$w(str_replace($sep,' ',$s).$sep.$v['q'].$sep.$fV($v['v']));
        $w(__('admin.dre.csv_total_out_revenue', 'TOTAL FORA DA RECEITA REALIZADA')."{$sep}{$tqP}{$sep}".$fV($tfP)); $w("");

        $w(__('admin.dre.csv_s7', '=== 7. DESPESAS FORA DO RESULTADO REALIZADO ===')); $w(__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_quantity', 'Quantidade')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        foreach($dBS as $s=>$v)$w(str_replace($sep,' ',$s).$sep.$v['q'].$sep.$fV($v['v']));
        $w(__('admin.dre.csv_total_out_result', 'TOTAL FORA DO RESULTADO REALIZADO')."{$sep}{$tqD}{$sep}".$fV($tfD)); $w("");

        $w(__('admin.dre.csv_s8', '=== 8. DETALHAMENTO DOS PEDIDOS EXCLUÍDOS DA DRE ==='));
        $w("ID{$sep}".__('admin.dre.csv_col_number', 'Numero')."{$sep}".__('admin.dre.csv_col_date', 'Data')."{$sep}".__('admin.dre.csv_col_customer', 'Cliente')."{$sep}".__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_pay_status', 'Status Pgto')."{$sep}".__('admin.dre.csv_col_gateway', 'Gateway')."{$sep}".__('admin.dre.csv_col_pay_method', 'Forma Pgto')."{$sep}".__('admin.dre.csv_col_currency', 'Moeda')."{$sep}".__('admin.dre.csv_col_subtotal', 'Subtotal')."{$sep}".__('admin.dre.csv_col_services', 'Servicos')."{$sep}".__('admin.dre.csv_col_taxes', 'Impostos')."{$sep}".__('admin.dre.csv_col_shipping', 'Frete')."{$sep}".__('admin.dre.csv_col_total', 'Total')."{$sep}".__('admin.dre.csv_col_total_brl', 'Total BRL')."{$sep}".__('admin.dre.csv_col_exclusion_reason', 'Motivo da exclusão'));
        foreach($pFora as $p){$m=strtoupper(trim($p['mo']??'BRL'));$w($p['id'].$sep.str_replace($sep,' ',$p['numero']??'').$sep.substr($p['dt']??'',0,10).$sep.str_replace($sep,' ',$p['cli']??'').$sep.($p['st']??'').$sep.($p['ps']??'').$sep.($p['gw']??'').$sep.($p['fp']??'').$sep.$m.$sep.$fV($p['sub']).$sep.$fV($p['srv']).$sep.$fV($p['imp']).$sep.$fV($p['frt']).$sep.$fV($p['tot']).$sep.$fV($toB($p['tot'],$m)).$sep.$motP($p));}
        $w("");

        $w(__('admin.dre.csv_s9', '=== 9. DETALHAMENTO DAS DESPESAS EXCLUÍDAS DA DRE ==='));
        $w("ID{$sep}".__('admin.dre.csv_col_description', 'Descrição')."{$sep}".__('admin.dre.csv_col_category', 'Categoria')."{$sep}".__('admin.dre.csv_col_type', 'Tipo')."{$sep}".__('admin.dre.csv_col_payee', 'Favorecido')."{$sep}".__('admin.dre.csv_col_competence', 'Competência')."{$sep}".__('admin.dre.csv_col_due', 'Vencimento')."{$sep}".__('admin.dre.csv_col_payment', 'Pagamento')."{$sep}".__('admin.dre.csv_col_currency', 'Moeda')."{$sep}".__('admin.dre.csv_col_value', 'Valor')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL')."{$sep}".__('admin.dre.csv_col_status', 'Status')."{$sep}".__('admin.dre.csv_col_origin', 'Origem')."{$sep}".__('admin.dre.csv_col_exclusion_reason', 'Motivo da exclusão'));
        foreach($dFora as $d){$m=strtoupper(trim($d['moeda']??'BRL'));$w($d['id'].$sep.str_replace($sep,' ',$d['descricao']??'').$sep.str_replace($sep,' ',$d['cn']??'').$sep.($d['tipo']??'').$sep.str_replace($sep,' ',$d['favorecido']??'').$sep.($d['competencia']?date('m/Y',strtotime($d['competencia'])):'').$sep.($d['vencimento']?date('d/m/Y',strtotime($d['vencimento'])):'').$sep.($d['data_pagamento']?date('d/m/Y',strtotime($d['data_pagamento'])):'').$sep.$m.$sep.$fV($d['valor']).$sep.$fV($toB($d['valor'],$m)).$sep.($d['status']??'').$sep.($d['origem']??'').$sep.$motD($d));}
        $w("");

        $w(__('admin.dre.csv_s10', '=== 10. CONCILIAÇÃO DO PERÍODO ===')); $w(__('admin.dre.csv_col_description', 'Descrição')."{$sep}".__('admin.dre.csv_col_value_brl', 'Valor BRL'));
        $w(__('admin.dre.csv_realized_inflows', 'Entradas realizadas')."{$sep}".$fV($rec)); $w(__('admin.dre.csv_realized_outflows', 'Saídas realizadas')."{$sep}".$fV($despR)); $w(__('admin.dre.csv_period_balance', 'Saldo realizado do período')."{$sep}".$fV($res));
        $w(__('admin.dre.csv_orders_not_realized', 'Pedidos ainda não realizados')."{$sep}".$fV($tfP)); $w(__('admin.dre.csv_expenses_not_realized', 'Despesas ainda não realizadas')."{$sep}".$fV($tfD));
        $w(__('admin.dre.csv_qty_paid_orders', 'Quantidade de pedidos pagos')."{$sep}".count($pDre)); $w(__('admin.dre.csv_qty_orders_out', 'Quantidade de pedidos fora da DRE')."{$sep}{$tqP}");
        $w(__('admin.dre.csv_qty_paid_expenses', 'Quantidade de despesas pagas')."{$sep}".count($dDre)); $w(__('admin.dre.csv_qty_expenses_out', 'Quantidade de despesas fora da DRE')."{$sep}{$tqD}");

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
        set_time_limit(120); // Permitir até 2 min para consultar todos os tokens

        try {
            $auth = new AuthService();
            if (!isset($_SESSION['usuario_id'])) { echo json_encode(['error' => __('admin.dre.not_authenticated', 'Não autenticado')]); exit; }
        } catch (\Throwable $e) { echo json_encode(['error' => $e->getMessage()]); exit; }

        $force = ($_GET['force'] ?? '0') === '1';
        $dateStart = $_GET['date_start'] ?? null;
        $dateEnd = $_GET['date_end'] ?? null;

        // Tentar cache (só se não tiver filtro de datas customizado)
        if (!$force && !$dateStart && !$dateEnd) {
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
        $resultado = $this->executarConciliacao($dateStart, $dateEnd);
        $resultado['_from_cache'] = false;

        // Salvar cache (só se não tiver filtro customizado)
        if (!$dateStart && !$dateEnd) {
            $this->salvarCacheConciliacao($resultado);
        }

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
            echo json_encode(['error' => __('admin.dre.invalid_secret', 'Secret inválido')]);
            exit;
        }

        $resultado = $this->executarConciliacao(null, null);
        $this->salvarCacheConciliacao($resultado);

        echo json_encode(['success' => true, 'divergencias' => $resultado['resumo']['divergencias_total'] ?? 0, 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }

    private function executarConciliacao(?string $dateStart = null, ?string $dateEnd = null): array {
        // Usar datas do DRE se fornecidas, senão usar período padrão
        $desde = $dateStart ?: date('Y-01-01');
        $ate = $dateEnd ?: date('Y-m-d');

        $resultado = [
            'stripe' => $this->conciliacaoStripe($desde, $ate),
            'cambioreal' => $this->conciliacaoCambioReal('cambioreal', $desde, $ate),
            'cambioreal_taxas' => $this->conciliacaoCambioReal('cambioreal_taxas', $desde, $ate),
            'fluxo_caixa' => [],
            'agendamentos_futuros' => [],
            'resumo' => [],
            'taxa_conversao' => \App\Core\ExchangeRate::getUsdToBrl(),
            'totais_por_gateway' => [], // Totais da tabela pedidos por gateway (debug)
            '_timestamp' => time(),
        ];

        // Buscar taxa de conversão
        try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $resultado['taxa_conversao'] = $r; } catch (\Exception $e) {}

        // Buscar totais por gateway da tabela pedidos (mesma fonte do DRE)
        // para debug e para o comparativo
        try {
            $cols = [];
            try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $colTotal = in_array('total', $cols) ? 'total' : (in_array('valor_total', $cols) ? 'valor_total' : '');
            $colMoeda = in_array('moeda', $cols) ? 'moeda' : (in_array('currency', $cols) ? 'currency' : '');
            $colStatus = in_array('status', $cols) ? 'status' : '';
            $colPayGateway = in_array('payment_gateway', $cols) ? 'payment_gateway' : (in_array('gateway', $cols) ? 'gateway' : '');
            $colCreatedAt = in_array('created_at', $cols) ? 'created_at' : (in_array('data_criacao', $cols) ? 'data_criacao' : '');
            $deletedFilter = in_array('deleted_at', $cols) ? "AND p.deleted_at IS NULL" : "";
            $paidStatuses = "('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";

            if ($colTotal && $colStatus && $colPayGateway && $colCreatedAt) {
                $st = $this->db->prepare("SELECT LOWER(COALESCE(p.{$colPayGateway},'sem_gateway')) as gateway, " . ($colMoeda ? "UPPER(COALESCE(p.{$colMoeda},'USD'))" : "'USD'") . " as moeda, COUNT(*) as qtd, COALESCE(SUM(p.{$colTotal}),0) as total
                    FROM pedidos p
                    WHERE p.{$colCreatedAt} >= ? AND p.{$colCreatedAt} < DATE_ADD(?, INTERVAL 1 DAY)
                    AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}
                    {$deletedFilter}
                    GROUP BY gateway, moeda ORDER BY total DESC");
                $st->execute([$desde, $ate]);
                $resultado['totais_por_gateway'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}

        $resultado['resumo'] = [
            'stripe_saldo' => $resultado['stripe']['saldo'] ?? [],
            'cr_total_recebido' => ($resultado['cambioreal']['total_recebido_usd'] ?? 0) + ($resultado['cambioreal_taxas']['total_recebido_usd'] ?? 0),
            'divergencias_total' => count($resultado['stripe']['divergencias'] ?? []) + count($resultado['cambioreal']['divergencias'] ?? []) + count($resultado['cambioreal_taxas']['divergencias'] ?? []),
        ];

        // Montar fluxo de caixa (extrato) — usando período do DRE
        $resultado['fluxo_caixa'] = $this->montarFluxoCaixa($desde, $ate);
        $resultado['agendamentos_futuros'] = $this->montarAgendamentosFuturos();

        return $resultado;
    }

    /**
     * Monta o extrato de movimentação (entradas e saídas) no período do DRE
     */
    private function montarFluxoCaixa(?string $dateStart = null, ?string $dateEnd = null): array {
        $movimentos = [];
        $desde = $dateStart ?: date('Y-m-d', strtotime('-30 days'));
        $ate = $dateEnd ?: date('Y-m-d');

        try {
            // ENTRADAS: buscar TODOS os registros da pedido_pagamentos (sem filtro de data)
            // e filtrar por data em PHP usando a data do pedido associado
            $st = $this->db->query("SELECT pp.pedido_id, pp.valor, pp.gateway, pp.metodo, pp.moeda, pp.gateway_status, pp.status as pp_status,
                pp.created_at as pp_created, pp.updated_at as pp_updated,
                p.created_at as p_created,
                CONCAT('#', pp.pedido_id) as ref
                FROM pedido_pagamentos pp
                LEFT JOIN pedidos p ON p.id = pp.pedido_id
                ORDER BY pp.id DESC LIMIT 500");
            $allRows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($allRows as $r) {
                // Determinar data: preferir updated_at > created_at > pedido.created_at
                $data = $r['pp_updated'] ?? $r['pp_created'] ?? $r['p_created'] ?? null;
                if (!$data || $data === '0000-00-00 00:00:00') {
                    $data = $r['p_created'] ?? date('Y-m-d');
                }

                // Filtrar por período
                $dataDate = substr($data, 0, 10);
                if ($dataDate < $desde || $dataDate > $ate) continue;

                // Filtrar por status (approved ou gateway_status pago)
                $ppStatus = strtolower(trim($r['pp_status'] ?? ''));
                $gwStatus = strtoupper(trim($r['gateway_status'] ?? ''));
                $isPago = ($ppStatus === 'approved')
                    || in_array($gwStatus, ['SOLICITACAO_PAGO', 'SOLICITACAO_FINALIZADA', 'SUCCEEDED', 'PAID']);
                if (!$isPago) continue;

                $gw = $r['gateway'] ?? 'outro';
                $gwLabel = $gw === 'stripe' ? 'Stripe' : ($gw === 'cambioreal_taxas' ? __('admin.dre.gw_cr_fees', 'CR Taxas') : (in_array($gw, ['cambioreal','cambio_real']) ? __('admin.dre.gw_cr_products', 'CR Produtos') : ucfirst($gw)));
                $moeda = strtoupper(trim($r['moeda'] ?? 'USD'));
                if ($moeda === '' || $moeda === 'NULL') $moeda = 'USD';

                $movimentos[] = [
                    'data' => date('d/m/Y', strtotime($data)),
                    'data_sort' => $data,
                    'descricao' => __('admin.dre.order_word', 'Pedido') . ' ' . ($r['ref'] ?? '#' . $r['pedido_id']) . ' — ' . ucfirst($r['metodo'] ?? $gw),
                    'gateway' => $gwLabel,
                    'tipo' => 'entrada',
                    'valor' => (float)($r['valor'] ?? 0),
                    'moeda' => $moeda,
                ];
            }
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $gw = $r['gateway'] ?? 'outro';
                $gwLabel = $gw === 'stripe' ? 'Stripe' : ($gw === 'cambioreal_taxas' ? 'CR Taxas' : (in_array($gw, ['cambioreal','cambio_real']) ? 'CR Produtos' : ucfirst($gw)));
                $moeda = strtoupper(trim($r['moeda'] ?? 'USD'));
                if ($moeda === '' || $moeda === 'NULL') $moeda = 'USD';
                $movimentos[] = [
                    'data' => date('d/m/Y', strtotime($r['data'])),
                    'data_sort' => $r['data'],
                    'descricao' => 'Pedido ' . ($r['ref'] ?? '#' . $r['pedido_id']) . ' — ' . ucfirst($r['metodo'] ?? $gw),
                    'gateway' => $gwLabel,
                    'tipo' => 'entrada',
                    'valor' => (float)($r['valor'] ?? 0),
                    'moeda' => $moeda,
                ];
            }

            // ENTRADAS: parcelas de carnê pagas
            $st = $this->db->prepare("SELECT cp.valor_produtos, cp.valor_taxas, cp.boleto_produtos_pago_em, cp.boleto_taxas_pago_em, c.pedido_id, cp.numero_parcela
                FROM carne_parcelas cp
                JOIN carnes c ON cp.carne_id = c.id
                WHERE cp.status = 'paga' AND (cp.boleto_produtos_pago_em >= ? OR cp.boleto_taxas_pago_em >= ?)
                AND (cp.boleto_produtos_pago_em <= DATE_ADD(?, INTERVAL 1 DAY) OR cp.boleto_taxas_pago_em <= DATE_ADD(?, INTERVAL 1 DAY))
                ORDER BY COALESCE(cp.boleto_produtos_pago_em, cp.boleto_taxas_pago_em) DESC LIMIT 200");
            $st->execute([$desde, $desde, $ate, $ate]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                if ((float)$r['valor_produtos'] > 0 && $r['boleto_produtos_pago_em']) {
                    $movimentos[] = ['data' => date('d/m/Y', strtotime($r['boleto_produtos_pago_em'])), 'data_sort' => $r['boleto_produtos_pago_em'], 'descricao' => __('admin.dre.installment_order', 'Carnê Ped #') . $r['pedido_id'] . ' — ' . __('admin.dre.installment_short', 'Parc') . ' ' . $r['numero_parcela'] . ' (' . __('admin.dre.products_paren', 'Produtos') . ')', 'gateway' => __('admin.dre.gw_cr_products', 'CR Produtos'), 'tipo' => 'entrada', 'valor' => (float)$r['valor_produtos'], 'moeda' => 'BRL'];
                }
                if ((float)$r['valor_taxas'] > 0 && $r['boleto_taxas_pago_em']) {
                    $movimentos[] = ['data' => date('d/m/Y', strtotime($r['boleto_taxas_pago_em'])), 'data_sort' => $r['boleto_taxas_pago_em'], 'descricao' => __('admin.dre.installment_order', 'Carnê Ped #') . $r['pedido_id'] . ' — ' . __('admin.dre.installment_short', 'Parc') . ' ' . $r['numero_parcela'] . ' (' . __('admin.dre.fees_paren', 'Taxas') . ')', 'gateway' => __('admin.dre.gw_cr_fees', 'CR Taxas'), 'tipo' => 'entrada', 'valor' => (float)$r['valor_taxas'], 'moeda' => 'BRL'];
                }
            }

            // SAÍDAS: despesas pagas
            try {
                $st = $this->db->prepare("SELECT descricao, valor, moeda, pago_em, categoria FROM despesas WHERE status = 'paga' AND pago_em >= ? AND pago_em <= DATE_ADD(?, INTERVAL 1 DAY) ORDER BY pago_em DESC LIMIT 200");
                $st->execute([$desde, $ate]);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $movimentos[] = ['data' => date('d/m/Y', strtotime($r['pago_em'])), 'data_sort' => $r['pago_em'], 'descricao' => ($r['descricao'] ?? __('admin.dre.expense_word', 'Despesa')) . ($r['categoria'] ? ' (' . $r['categoria'] . ')' : ''), 'gateway' => __('admin.dre.outflow', 'Saída'), 'tipo' => 'saida', 'valor' => (float)($r['valor'] ?? 0), 'moeda' => $r['moeda'] ?? 'BRL'];
                }
            } catch (\Exception $e) {}

        } catch (\Exception $e) {
            error_log('[FLUXO_CAIXA] Erro: ' . $e->getMessage());
            // Retornar erro como movimentação para debug
            $movimentos[] = [
                'data' => date('d/m/Y'),
                'descricao' => 'ERRO: ' . $e->getMessage(),
                'gateway' => 'Sistema',
                'tipo' => 'entrada',
                'valor' => 0,
                'moeda' => 'BRL',
                'saldo_acumulado' => 0,
            ];
            return $movimentos;
        }

        // Ordenar por data (mais recente primeiro)
        usort($movimentos, function($a, $b) { return strcmp($b['data_sort'], $a['data_sort']); });

        // Calcular saldo acumulado (de baixo pra cima)
        $saldo = 0;
        $movimentos = array_reverse($movimentos);
        foreach ($movimentos as &$m) {
            if ($m['tipo'] === 'entrada') $saldo += $m['valor'];
            else $saldo -= $m['valor'];
            $m['saldo_acumulado'] = round($saldo, 2);
            unset($m['data_sort']);
        }
        return array_reverse($movimentos);
    }

    /**
     * Monta agendamentos futuros (parcelas a vencer, despesas futuras)
     */
    private function montarAgendamentosFuturos(): array {
        $agendamentos = [];
        $hoje = date('Y-m-d');
        $limite = date('Y-m-d', strtotime('+60 days'));

        try {
            // Parcelas de carnê a vencer (entradas futuras)
            $st = $this->db->prepare("SELECT cp.vencimento, cp.valor_total, c.pedido_id, cp.numero_parcela
                FROM carne_parcelas cp
                JOIN carnes c ON cp.carne_id = c.id
                WHERE cp.status IN ('pendente','aguardando_pagamento') AND cp.vencimento BETWEEN ? AND ?
                ORDER BY cp.vencimento ASC LIMIT 50");
            $st->execute([$hoje, $limite]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $agendamentos[] = ['vencimento' => date('d/m/Y', strtotime($r['vencimento'])), 'descricao' => __('admin.dre.installment_order', 'Carnê Ped #') . $r['pedido_id'] . ' — ' . __('admin.dre.installment_word', 'Parcela') . ' ' . $r['numero_parcela'], 'tipo' => 'entrada', 'valor' => (float)$r['valor_total'], 'moeda' => 'BRL'];
            }

            // Despesas futuras (saídas)
            try {
                $st = $this->db->prepare("SELECT vencimento, descricao, valor, moeda FROM despesas WHERE status IN ('prevista','a_vencer') AND vencimento BETWEEN ? AND ? ORDER BY vencimento ASC LIMIT 50");
                $st->execute([$hoje, $limite]);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $agendamentos[] = ['vencimento' => date('d/m/Y', strtotime($r['vencimento'])), 'descricao' => $r['descricao'] ?? __('admin.dre.expense_word', 'Despesa'), 'tipo' => 'saida', 'valor' => (float)($r['valor'] ?? 0), 'moeda' => $r['moeda'] ?? 'BRL'];
                }
            } catch (\Exception $e) {}

        } catch (\Exception $e) {}

        return $agendamentos;
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

    private function conciliacaoStripe(?string $dateStart = null, ?string $dateEnd = null): array {
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

            // 2. Últimas transações (período do DRE)
            $desde = $dateStart ? strtotime($dateStart) : strtotime('-30 days');
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

            // Calcular total recebido no Stripe (soma das transações)
            $result['total_recebido_usd'] = 0;
            foreach ($result['transacoes'] as $tx) {
                $result['total_recebido_usd'] += $tx['valor'];
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

    private function conciliacaoCambioReal(string $gateway, ?string $dateStart = null, ?string $dateEnd = null): array {
        $result = [
            'total_recebido_usd' => 0,
            'total_recebido_brl' => 0,
            'total_gateway_usd' => 0,
            'total_pedidos_brl' => 0, // Total da tabela pedidos (mesma fonte do DRE)
            'total_sistema' => 0,
            'moeda_principal' => 'USD',
            'total_registros' => 0,
            'total_consultados' => 0,
            'transacoes' => [],
            'divergencias' => [],
            'erro' => null,
        ];

        try {
            $desde = $dateStart ?: date('Y-m-d', strtotime('-30 days'));
            $ate = $dateEnd ?: date('Y-m-d');

            // Taxa de conversão
            $taxaUsdBrl = \App\Core\ExchangeRate::getUsdToBrl();

            // 1. Total da tabela PEDIDOS por gateway (mesma fonte do DRE "Total Entradas")
            // Isso garante consistência com o "Total Entradas (Sistema)"
            $paidStatuses = "('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";

            // Detectar colunas
            $cols = [];
            try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $colTotal = in_array('total', $cols) ? 'total' : (in_array('valor_total', $cols) ? 'valor_total' : '');
            $colMoeda = in_array('moeda', $cols) ? 'moeda' : (in_array('currency', $cols) ? 'currency' : '');
            $colStatus = in_array('status', $cols) ? 'status' : '';
            $colPayGateway = in_array('payment_gateway', $cols) ? 'payment_gateway' : (in_array('gateway', $cols) ? 'gateway' : '');
            $colCreatedAt = in_array('created_at', $cols) ? 'created_at' : (in_array('data_criacao', $cols) ? 'data_criacao' : '');
            $deletedFilter = in_array('deleted_at', $cols) ? "AND p.deleted_at IS NULL" : "";

            if ($colTotal && $colStatus && $colPayGateway && $colCreatedAt) {
                // Usar LIKE para pegar todas as variações do gateway (pix_cambioreal, boleto_cambioreal, etc)
                $gwLike = $gateway === 'cambioreal_taxas' ? '%cambioreal_taxa%' : '%cambioreal%';
                $gwNotLike = $gateway === 'cambioreal_taxas' ? '' : '%taxa%';

                $sql = "SELECT COALESCE(SUM(p.{$colTotal}), 0) as total, COUNT(*) as qtd, " . ($colMoeda ? "UPPER(COALESCE(p.{$colMoeda},'USD'))" : "'USD'") . " as moeda
                    FROM pedidos p
                    WHERE p.{$colCreatedAt} >= ? AND p.{$colCreatedAt} < DATE_ADD(?, INTERVAL 1 DAY)
                    AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}
                    AND LOWER(COALESCE(p.{$colPayGateway},'')) LIKE ?
                    " . ($gwNotLike ? "AND LOWER(COALESCE(p.{$colPayGateway},'')) NOT LIKE ?" : "") . "
                    {$deletedFilter}
                    GROUP BY moeda";

                $params = [$desde, $ate, $gwLike];
                if ($gwNotLike) $params[] = $gwNotLike;

                $st = $this->db->prepare($sql);
                $st->execute($params);
                $pedidosTotais = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($pedidosTotais as $pt) {
                    $val = (float)($pt['total'] ?? 0);
                    $moeda = strtoupper(trim($pt['moeda'] ?? 'USD'));
                    if ($moeda === 'BRL') {
                        $result['total_pedidos_brl'] += $val;
                    } else {
                        $result['total_pedidos_brl'] += $val * $taxaUsdBrl;
                        $result['total_recebido_usd'] += $val;
                    }
                    $result['total_registros'] += (int)($pt['qtd'] ?? 0);
                }
            }

            // 2. Somar da pedido_pagamentos (para referência e divergências)
            // Não filtrar por data pois created_at/updated_at podem ser NULL
            $st = $this->db->prepare("SELECT payment_id, pedido_id, valor, moeda, gateway_status, status FROM pedido_pagamentos WHERE gateway = ? AND payment_id IS NOT NULL AND payment_id != '' ORDER BY id DESC LIMIT 200");
            $st->execute([$gateway]);
            $todosRegistros = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Somar valores locais da pedido_pagamentos (para referência)
            foreach ($todosRegistros as $reg) {
                $val = (float)($reg['valor'] ?? 0);
                $moeda = strtoupper(trim($reg['moeda'] ?? 'USD'));
                $result['total_sistema'] += $val;
                if ($moeda === 'BRL') {
                    $result['total_recebido_brl'] += $val;
                    $result['moeda_principal'] = 'BRL';
                }
            }

            // 3. Somar parcelas de carnê pagas
            if ($gateway === 'cambioreal') {
                $stC = $this->db->prepare("SELECT SUM(cp.valor_produtos) as total, COUNT(*) as qtd FROM carne_parcelas cp JOIN carnes c ON cp.carne_id = c.id WHERE cp.status = 'paga' AND cp.boleto_produtos_pago_em >= ? AND cp.boleto_produtos_pago_em <= DATE_ADD(?, INTERVAL 1 DAY)");
                $stC->execute([$desde, $ate]);
                $r = $stC->fetch(\PDO::FETCH_ASSOC);
                if ($r && (float)($r['total'] ?? 0) > 0) {
                    $result['total_recebido_brl'] += (float)$r['total'];
                    $result['total_pedidos_brl'] += (float)$r['total']; // Carnê também conta
                }
            } elseif ($gateway === 'cambioreal_taxas') {
                $stC = $this->db->prepare("SELECT SUM(cp.valor_taxas) as total, COUNT(*) as qtd FROM carne_parcelas cp JOIN carnes c ON cp.carne_id = c.id WHERE cp.status = 'paga' AND cp.boleto_taxas_pago_em >= ? AND cp.boleto_taxas_pago_em <= DATE_ADD(?, INTERVAL 1 DAY)");
                $stC->execute([$desde, $ate]);
                $r = $stC->fetch(\PDO::FETCH_ASSOC);
                if ($r && (float)($r['total'] ?? 0) > 0) {
                    $result['total_recebido_brl'] += (float)$r['total'];
                    $result['total_pedidos_brl'] += (float)$r['total']; // Carnê também conta
                }
            }

            // 4. Consultar tokens na API do CR para divergências (amostra de 30)
            $paymentService = new \App\Services\PaymentService();
            $totalGatewayUsd = 0;
            $consultados = 0;

            $amostra = array_slice($todosRegistros, 0, 30);
            foreach ($amostra as $l) {
                $token = $l['payment_id'];
                $statusLocal = strtoupper(trim((string)($l['gateway_status'] ?? $l['status'] ?? '')));

                try {
                    if ($gateway === 'cambioreal_taxas') {
                        $resp = $paymentService->obterTransacaoCambioRealTaxas($token);
                    } else {
                        $resp = $paymentService->obterTransacaoCambioReal($token);
                    }
                    $data = $resp['data'] ?? [];
                    $statusGw = strtoupper(trim((string)($data['status'] ?? '')));
                    $consultados++;

                    $pagoGateway = in_array($statusGw, ['SOLICITACAO_PAGO', 'SOLICITACAO_FINALIZADA', 'ON_HOLD']);
                    $pagoLocal = in_array($statusLocal, ['SOLICITACAO_PAGO', 'SOLICITACAO_FINALIZADA', 'APPROVED']);

                    if ($pagoGateway) {
                        $valorGw = 0;
                        if (isset($data['transaction']['amount'])) {
                            $valorGw = (float)$data['transaction']['amount'];
                        } elseif (isset($data['amount'])) {
                            $valorGw = (float)$data['amount'];
                        } else {
                            $valorGw = (float)($l['valor'] ?? 0);
                        }
                        $totalGatewayUsd += $valorGw / $taxaUsdBrl;
                    }

                    if ($pagoGateway && !$pagoLocal) {
                        $result['divergencias'][] = ['tipo' => 'pago_gateway_nao_local', 'token' => $token, 'pedido_id' => $l['pedido_id'] ?? null, 'status_gateway' => $statusGw, 'status_local' => $statusLocal, 'msg' => 'Pago no CR mas não no sistema'];
                    } elseif (!$pagoGateway && $pagoLocal) {
                        $result['divergencias'][] = ['tipo' => 'pago_local_nao_gateway', 'token' => $token, 'pedido_id' => $l['pedido_id'] ?? null, 'status_gateway' => $statusGw, 'status_local' => $statusLocal, 'msg' => 'Pago no sistema mas não no CR'];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $result['total_consultados'] = $consultados;
            $result['total_gateway_usd'] = round($totalGatewayUsd, 2);

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
