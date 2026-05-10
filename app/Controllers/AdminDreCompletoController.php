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

        // === ENTRADAS DE PEDIDOS DETALHADAS (últimos 50) ===
        $entradasDetalhadas = [];
        try {
            $sqlDet = "SELECT p.id, p.{$colCreatedAt} as data_pedido, p.{$colTotal} as total, p.{$colStatus} as status"
                . ($colMoeda ? ", p.{$colMoeda} as moeda" : ", 'BRL' as moeda")
                . ($colPayGateway ? ", p.{$colPayGateway} as gateway" : ", '' as gateway")
                . ($colFormaPgto ? ", p.{$colFormaPgto} as forma_pagamento" : ", '' as forma_pagamento")
                . ($colSubtotal ? ", p.{$colSubtotal} as subtotal" : ", 0 as subtotal")
                . ($colServicos ? ", p.{$colServicos} as servicos" : ", 0 as servicos")
                . ($colImpostos ? ", p.{$colImpostos} as impostos" : ", 0 as impostos")
                . " FROM pedidos p WHERE p.{$colCreatedAt} >= :ds AND p.{$colCreatedAt} < DATE_ADD(:de, INTERVAL 1 DAY)"
                . " AND LOWER(COALESCE(p.{$colStatus},'')) IN {$paidStatuses}"
                . " {$deletedFilter} ORDER BY p.{$colCreatedAt} DESC LIMIT 50";
            $stDet = $this->db->prepare($sqlDet);
            $stDet->execute([':ds' => $dateStart, ':de' => $dateEnd]);
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
            'operacional' => $operacionalPorPessoa,
            'conciliacao' => $conciliacao,
        ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        exit;
    }
}
