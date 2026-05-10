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
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $dateStart = $request->getParam('date_start', date('Y-01-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));

        // Limpar qualquer output buffer anterior
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

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

        // Taxa USD→BRL
        $taxaUsdBrl = 5.85;
        try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $taxaUsdBrl = $r; } catch (\Exception $e) {}

        // === ENTRADAS DE PEDIDOS (pagos) ===
        $paidStatuses = "('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";
        $sqlEntradas = "SELECT 
            DATE_FORMAT(p.{$colCreatedAt}, '%Y-%m') as mes,
            UPPER(COALESCE(p.{$colMoeda},'BRL')) as moeda,
            COUNT(*) as qtd,
            COALESCE(SUM(p.{$colTotal}),0) as total,
            COALESCE(SUM(p.{$colSubtotal}),0) as subtotal,
            COALESCE(SUM(p.{$colServicos}),0) as servicos,
            COALESCE(SUM(p.{$colImpostos}),0) as impostos,
            COALESCE(SUM(p.{$colFrete}),0) as frete
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
            LOWER(COALESCE(p.{$colPayGateway},'sem_gateway')) as gateway,
            UPPER(COALESCE(p.{$colMoeda},'BRL')) as moeda,
            COUNT(*) as qtd,
            COALESCE(SUM(p.{$colTotal}),0) as total,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='approved' THEN 1 ELSE 0 END),0) as aprovados,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},'')) IN ('pending','') OR p.{$colPayStatus} IS NULL THEN 1 ELSE 0 END),0) as pendentes,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='rejected' THEN 1 ELSE 0 END),0) as rejeitados,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.{$colPayStatus},''))='refunded' THEN 1 ELSE 0 END),0) as estornados
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
        $w("Receita realizada{$sep}".$fV($rec)); $w("(-) Despesas realizadas{$sep}".$fV($despR)); $w("(=) Resultado realizado{$sep}".$fV($res)); $w("Margem realizada{$sep}".$fV($mrg)."%"); $w("");

        $w("=== 2. COMPOSIÇÃO DA RECEITA REALIZADA ==="); $w("Descrição{$sep}Valor BRL");
        $w("Produtos{$sep}".$fV($subT)); $w("Serviços{$sep}".$fV($srvT)); $w("Impostos cobrados{$sep}".$fV($impT)); $w("Frete cobrado{$sep}".$fV($frtT)); $w("Total da receita realizada{$sep}".$fV($rec)); $w("Quantidade de pedidos pagos{$sep}".count($pDre)); $w("");

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
        $w("Entradas realizadas{$sep}".$fV($rec)); $w("Saídas realizadas{$sep}".$fV($despR)); $w("Saldo realizado do período{$sep}".$fV($res));
        $w("Pedidos ainda não realizados{$sep}".$fV($tfP)); $w("Despesas ainda não realizadas{$sep}".$fV($tfD));
        $w("Quantidade de pedidos pagos{$sep}".count($pDre)); $w("Quantidade de pedidos fora da DRE{$sep}{$tqP}");
        $w("Quantidade de despesas pagas{$sep}".count($dDre)); $w("Quantidade de despesas fora da DRE{$sep}{$tqD}");

        fclose($o); exit;
    }
}

        $dateStart = $request->getParam('date_start', date('Y-01-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));

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
        $colCliente = $pick(['cliente_nome','nome']);
        $colNumero = $pick(['numero_pedido','codigo_pedido']);
        $deletedFilter = in_array('deleted_at', $cols, true) ? "AND p.deleted_at IS NULL" : "";

        $taxaUsdBrl = 5.85;
        try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $taxaUsdBrl = $r; } catch (\Exception $e) {}

        $filename = 'DRE_Completo_' . $dateStart . '_' . $dateEnd . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $sep = ';';
        $fmtV = function($v) { return number_format((float)($v ?? 0), 2, ',', ''); };

        // CABEÇALHO
        fwrite($out, "DEMONSTRATIVO DE RESULTADO COMPLETO - BRAZILIANA SHOP{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "Período: {$dateStart} a {$dateEnd}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "Taxa USD→BRL: " . number_format($taxaUsdBrl, 4, ',', '') . "{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "Gerado em: " . date('d/m/Y H:i:s') . "{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "\r\n");

        // === TODOS OS PEDIDOS ===
        fwrite($out, "=== PEDIDOS DO PERÍODO ==={$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "ID{$sep}Numero{$sep}Data{$sep}Cliente{$sep}Status{$sep}Status Pgto{$sep}Gateway{$sep}Forma Pgto{$sep}Moeda{$sep}Subtotal{$sep}Servicos{$sep}Impostos{$sep}Frete{$sep}Total{$sep}Total BRL\r\n");

        $paidStatuses = "('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue','processando')";
        $sqlAll = "SELECT p.id"
            . ($colNumero ? ", p.{$colNumero} as numero" : ", '' as numero")
            . ", p.{$colCreatedAt} as data_pedido"
            . ($colCliente ? ", p.{$colCliente} as cliente" : ", '' as cliente")
            . ", p.{$colStatus} as status"
            . ($colPayStatus ? ", p.{$colPayStatus} as pay_status" : ", '' as pay_status")
            . ($colPayGateway ? ", p.{$colPayGateway} as gateway" : ", '' as gateway")
            . ($colFormaPgto ? ", p.{$colFormaPgto} as forma_pgto" : ", '' as forma_pgto")
            . ($colMoeda ? ", p.{$colMoeda} as moeda" : ", 'BRL' as moeda")
            . ($colSubtotal ? ", p.{$colSubtotal} as subtotal" : ", 0 as subtotal")
            . ($colServicos ? ", p.{$colServicos} as servicos" : ", 0 as servicos")
            . ($colImpostos ? ", p.{$colImpostos} as impostos" : ", 0 as impostos")
            . ($colFrete ? ", p.{$colFrete} as frete" : ", 0 as frete")
            . ", p.{$colTotal} as total"
            . " FROM pedidos p WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)"
            . " AND LOWER(COALESCE(p.{$colStatus},'')) NOT IN ('apagado','deleted','lixeira','trash')"
            . " {$deletedFilter} ORDER BY p.{$colCreatedAt} DESC";

        $totalPedBrl = 0; $totalPedUsd = 0; $qtdPed = 0;
        try {
            $st = $this->db->prepare($sqlAll);
            $st->execute([':ds' => $dateStart, ':de' => $dateEnd]);
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $moeda = strtoupper(trim($row['moeda'] ?? 'BRL'));
                $total = (float)($row['total'] ?? 0);
                $totalBrl = $moeda === 'USD' ? $total * $taxaUsdBrl : $total;
                if ($moeda === 'USD') $totalPedUsd += $total; else $totalPedBrl += $total;
                $qtdPed++;
                fwrite($out, $row['id'] . $sep . ($row['numero'] ?? '') . $sep . substr($row['data_pedido'] ?? '', 0, 10) . $sep . str_replace($sep, ' ', $row['cliente'] ?? '') . $sep . ($row['status'] ?? '') . $sep . ($row['pay_status'] ?? '') . $sep . ($row['gateway'] ?? '') . $sep . ($row['forma_pgto'] ?? '') . $sep . $moeda . $sep . $fmtV($row['subtotal']) . $sep . $fmtV($row['servicos']) . $sep . $fmtV($row['impostos']) . $sep . $fmtV($row['frete']) . $sep . $fmtV($total) . $sep . $fmtV($totalBrl) . "\r\n");
            }
        } catch (\Exception $e) {}

        fwrite($out, "TOTAL PEDIDOS{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$fmtV($totalPedBrl + $totalPedUsd)}{$sep}" . $fmtV($totalPedBrl + ($totalPedUsd * $taxaUsdBrl)) . "\r\n");
        fwrite($out, "Qtd: {$qtdPed}{$sep}USD: " . $fmtV($totalPedUsd) . "{$sep}BRL: " . $fmtV($totalPedBrl) . "\r\n");
        fwrite($out, "\r\n");

        // === DESPESAS ===
        fwrite($out, "=== DESPESAS DO PERÍODO ==={$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}\r\n");
        fwrite($out, "ID{$sep}Descrição{$sep}Categoria{$sep}Tipo{$sep}Favorecido{$sep}Competência{$sep}Vencimento{$sep}Pagamento{$sep}Moeda{$sep}Valor{$sep}Valor BRL{$sep}Status{$sep}Origem\r\n");

        $totalDespBrl = 0; $totalDespUsd = 0; $qtdDesp = 0;
        try {
            $stD = $this->db->prepare("SELECT d.*, c.nome as cat_nome FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE d.competencia >= ? AND d.competencia <= ? AND d.status != 'cancelada' AND d.deleted_at IS NULL ORDER BY d.vencimento ASC");
            $stD->execute([$dateStart, $dateEnd]);
            while ($row = $stD->fetch(\PDO::FETCH_ASSOC)) {
                $moeda = strtoupper(trim($row['moeda'] ?? 'BRL'));
                $valor = (float)($row['valor'] ?? 0);
                $valorBrl = $moeda === 'USD' ? $valor * $taxaUsdBrl : $valor;
                if ($moeda === 'USD') $totalDespUsd += $valor; else $totalDespBrl += $valor;
                $qtdDesp++;
                fwrite($out, ($row['id'] ?? '') . $sep . str_replace($sep, ' ', $row['descricao'] ?? '') . $sep . str_replace($sep, ' ', $row['cat_nome'] ?? '') . $sep . ($row['tipo'] ?? '') . $sep . str_replace($sep, ' ', $row['favorecido'] ?? '') . $sep . ($row['competencia'] ? date('m/Y', strtotime($row['competencia'])) : '') . $sep . ($row['vencimento'] ? date('d/m/Y', strtotime($row['vencimento'])) : '') . $sep . ($row['data_pagamento'] ? date('d/m/Y', strtotime($row['data_pagamento'])) : '') . $sep . $moeda . $sep . $fmtV($valor) . $sep . $fmtV($valorBrl) . $sep . ($row['status'] ?? '') . $sep . ($row['origem'] ?? '') . "\r\n");
            }
        } catch (\Exception $e) {}

        fwrite($out, "TOTAL DESPESAS{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}{$sep}" . $fmtV($totalDespBrl + $totalDespUsd) . $sep . $fmtV($totalDespBrl + ($totalDespUsd * $taxaUsdBrl)) . "\r\n");
        fwrite($out, "Qtd: {$qtdDesp}{$sep}USD: " . $fmtV($totalDespUsd) . "{$sep}BRL: " . $fmtV($totalDespBrl) . "\r\n");
        fwrite($out, "\r\n");

        // === DRE RESULTADO ===
        $receitaBrl = $totalPedBrl + ($totalPedUsd * $taxaUsdBrl);
        $despesaBrl = $totalDespBrl + ($totalDespUsd * $taxaUsdBrl);
        $resultado = $receitaBrl - $despesaBrl;
        $margem = $receitaBrl > 0 ? round($resultado / $receitaBrl * 100, 1) : 0;

        fwrite($out, "=== DRE - RESULTADO ==={$sep}{$sep}\r\n");
        fwrite($out, "Descrição{$sep}Valor (R\$)\r\n");
        fwrite($out, "RECEITA BRUTA (pedidos){$sep}" . $fmtV($receitaBrl) . "\r\n");
        fwrite($out, "  Pedidos em BRL{$sep}" . $fmtV($totalPedBrl) . "\r\n");
        fwrite($out, "  Pedidos em USD (×{$taxaUsdBrl}){$sep}" . $fmtV($totalPedUsd * $taxaUsdBrl) . "\r\n");
        fwrite($out, "(-) DESPESAS TOTAIS{$sep}" . $fmtV($despesaBrl) . "\r\n");
        fwrite($out, "  Despesas em BRL{$sep}" . $fmtV($totalDespBrl) . "\r\n");
        if ($totalDespUsd > 0) fwrite($out, "  Despesas em USD (×{$taxaUsdBrl}){$sep}" . $fmtV($totalDespUsd * $taxaUsdBrl) . "\r\n");
        fwrite($out, "(=) RESULTADO LÍQUIDO{$sep}" . $fmtV($resultado) . "\r\n");
        fwrite($out, "Margem (%){$sep}{$margem}%\r\n");
        fwrite($out, "\r\n");

        // === CONCILIAÇÃO ===
        fwrite($out, "=== CONCILIAÇÃO ==={$sep}{$sep}\r\n");
        fwrite($out, "Total de créditos (entradas){$sep}" . $fmtV($receitaBrl) . "\r\n");
        fwrite($out, "Total de débitos (despesas){$sep}" . $fmtV($despesaBrl) . "\r\n");
        fwrite($out, "Saldo final{$sep}" . $fmtV($resultado) . "\r\n");
        fwrite($out, "Qtd pedidos{$sep}{$qtdPed}\r\n");
        fwrite($out, "Qtd despesas{$sep}{$qtdDesp}\r\n");
        fwrite($out, "Total lançamentos{$sep}" . ($qtdPed + $qtdDesp) . "\r\n");

        fclose($out);
        exit;
    }
}
