<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminRelatorioGeralController extends Controller {
    private $db;

    /**
     * Mapa de normaliza├º├úo: status sin├┤nimos ÔåÆ status can├┤nico.
     * Garante que varia├º├Áes gravadas no banco sejam agrupadas corretamente.
     */
    private static array $statusSinonimos = [
        'consolidado'       => 'produto_consolidado',
        'caixa_fechada'     => 'produto_consolidado',
        'pagamento'         => 'processando',
        'paid'              => 'pago',
        'approved'          => 'pago',
        'cancelled'         => 'cancelado',
    ];

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    /**
     * Retorna a lista can├┤nica de status (mesma usada em AdminPedidosController).
     */
    private static function getStatusList(): array {
        return \App\Controllers\AdminPedidosController::getStatusList();
    }

    /**
     * Gera express├úo SQL CASE para normalizar status sin├┤nimos no agrupamento.
     */
    private function buildStatusNormalizeExpr(): string {
        $cases = [];
        foreach (self::$statusSinonimos as $sinonimo => $canonico) {
            $cases[] = "WHEN LOWER(p.status) = '{$sinonimo}' THEN '{$canonico}'";
        }
        return "CASE " . implode(' ', $cases) . " ELSE LOWER(COALESCE(p.status,'')) END";
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $dateStart = $request->getParam('date_start', date('Y-m-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));
        $statusFilter = $request->getParam('status', []);
        if (is_string($statusFilter)) {
            $statusFilter = $statusFilter !== '' ? [$statusFilter] : [];
        }
        $statusFilter = array_filter(array_map('trim', (array) $statusFilter));
        $moedaFilter = $request->getParam('moeda', '');

        $cols = [];
        try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        // Mapear colunas
        $colTotal = $this->pick($cols, ['total','valor_total']);
        $colSubtotal = $this->pick($cols, ['subtotal','subtotal_produtos']);
        $colServicos = $this->pick($cols, ['servicos','taxa_servico']);
        $colImpostos = $this->pick($cols, ['impostos','valor_impostos']);
        $colFrete = $this->pick($cols, ['frete','valor_frete']);
        $colMoeda = $this->pick($cols, ['moeda','currency']);
        $colImpostoLocal = $this->pick($cols, ['imposto_local']);
        $colTotalBrl = $this->pick($cols, ['valor_total_brl']);
        $colTaxaConversao = $this->pick($cols, ['taxa_conversao']);
        $colFormaPagamento = $this->pick($cols, ['forma_pagamento','payment_method']);
        $colOrigemPedido = $this->pick($cols, ['origem_pedido']);

        // WHERE ÔÇö excluir status irrelevantes para relat├│rio financeiro
        $where = ["p.created_at >= :ds", "p.created_at < DATE_ADD(:de, INTERVAL 1 DAY)"];
        $params = [':ds' => $dateStart, ':de' => $dateEnd];

        $where[] = "LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash')";
        if (in_array('deleted_at', $cols, true)) {
            $where[] = "p.deleted_at IS NULL";
        }

        // Filtro por status: considerar sin├┤nimos (suporta m├║ltiplos)
        if (!empty($statusFilter)) {
            $sinonimosDoFiltro = [];
            foreach ($statusFilter as $sf) {
                $sinonimosDoFiltro[] = $sf;
                // Incluir sin├┤nimos reversos
                foreach (self::$statusSinonimos as $sin => $canonico) {
                    if ($canonico === $sf) {
                        $sinonimosDoFiltro[] = $sin;
                    }
                }
                // Se o filtro ├® um sin├┤nimo, incluir o can├┤nico e seus pares
                if (isset(self::$statusSinonimos[$sf])) {
                    $canonico = self::$statusSinonimos[$sf];
                    $sinonimosDoFiltro[] = $canonico;
                    foreach (self::$statusSinonimos as $sin => $can) {
                        if ($can === $canonico) {
                            $sinonimosDoFiltro[] = $sin;
                        }
                    }
                }
            }
            $sinonimosDoFiltro = array_unique($sinonimosDoFiltro);
            $placeholders = [];
            foreach (array_values($sinonimosDoFiltro) as $i => $s) {
                $key = ':st' . $i;
                $placeholders[] = $key;
                $params[$key] = $s;
            }
            $where[] = "LOWER(COALESCE(p.status,'')) IN (" . implode(',', $placeholders) . ")";
        }

        if ($moedaFilter !== '' && $colMoeda !== '') {
            $where[] = "p.{$colMoeda} = :moeda";
            $params[':moeda'] = strtoupper($moedaFilter);
        }

        $whereStr = implode(' AND ', $where);

        // Totais gerais
        $selectSums = [];
        if ($colTotal !== '') $selectSums[] = "COALESCE(SUM(p.{$colTotal}), 0) AS total_geral";
        if ($colSubtotal !== '') $selectSums[] = "COALESCE(SUM(p.{$colSubtotal}), 0) AS total_subtotal";
        if ($colServicos !== '') $selectSums[] = "COALESCE(SUM(p.{$colServicos}), 0) AS total_servicos";
        if ($colImpostos !== '') $selectSums[] = "COALESCE(SUM(p.{$colImpostos}), 0) AS total_impostos";
        if ($colFrete !== '') $selectSums[] = "COALESCE(SUM(p.{$colFrete}), 0) AS total_frete";
        if ($colImpostoLocal !== '') $selectSums[] = "COALESCE(SUM(p.{$colImpostoLocal}), 0) AS total_imposto_local";
        if ($colTotalBrl !== '') $selectSums[] = "COALESCE(SUM(p.{$colTotalBrl}), 0) AS total_geral_brl";
        $selectSums[] = "COUNT(*) AS qtd_pedidos";

        $sql = "SELECT " . implode(', ', $selectSums) . " FROM pedidos p WHERE {$whereStr}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $totais = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Totais por status (agrupado por status NORMALIZADO + moeda para convers├úo correta)
        $porStatusRaw = [];
        $statusExpr = $this->buildStatusNormalizeExpr();
        $moedaSelect = ($colMoeda !== '') ? ", UPPER(COALESCE(p.{$colMoeda},'USD')) AS moeda" : ", 'USD' AS moeda";
        $moedaGroup = ($colMoeda !== '') ? ", UPPER(COALESCE(p.{$colMoeda},'USD'))" : '';
        $sqlStatus = "SELECT {$statusExpr} AS status{$moedaSelect}, COUNT(*) AS qtd, COALESCE(SUM(p.{$colTotal}), 0) AS total"
            . ($colSubtotal ? ", COALESCE(SUM(p.{$colSubtotal}), 0) AS subtotal" : '')
            . ($colServicos ? ", COALESCE(SUM(p.{$colServicos}), 0) AS servicos" : '')
            . ($colImpostos ? ", COALESCE(SUM(p.{$colImpostos}), 0) AS impostos" : '')
            . ($colFrete ? ", COALESCE(SUM(p.{$colFrete}), 0) AS frete" : '')
            . ($colImpostoLocal ? ", COALESCE(SUM(p.{$colImpostoLocal}), 0) AS imposto_local" : '')
            . " FROM pedidos p WHERE {$whereStr} GROUP BY {$statusExpr}{$moedaGroup} ORDER BY status, moeda";
        $stmt2 = $this->db->prepare($sqlStatus);
        $stmt2->execute($params);
        $porStatusRaw = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Consolidar por status (converter USDÔåÆBRL usando taxa ÔÇö feito na view)
        $porStatus = $porStatusRaw;

        // Totais por moeda
        $porMoeda = [];
        if ($colMoeda !== '') {
            $sqlMoeda = "SELECT p.{$colMoeda} AS moeda, COUNT(*) AS qtd, COALESCE(SUM(p.{$colTotal}), 0) AS total"
                . ($colSubtotal ? ", COALESCE(SUM(p.{$colSubtotal}), 0) AS subtotal" : '')
                . ($colServicos ? ", COALESCE(SUM(p.{$colServicos}), 0) AS servicos" : '')
                . ($colImpostos ? ", COALESCE(SUM(p.{$colImpostos}), 0) AS impostos" : '')
                . ($colFrete ? ", COALESCE(SUM(p.{$colFrete}), 0) AS frete" : '')
                . ($colImpostoLocal ? ", COALESCE(SUM(p.{$colImpostoLocal}), 0) AS imposto_local" : '')
                . " FROM pedidos p WHERE {$whereStr} GROUP BY p.{$colMoeda} ORDER BY total DESC";
            $stmt3 = $this->db->prepare($sqlMoeda);
            $stmt3->execute($params);
            $porMoeda = $stmt3->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // Totais por forma de pagamento
        $porPagamento = [];
        if ($colFormaPagamento !== '') {
            $sqlPag = "SELECT COALESCE(NULLIF(p.{$colFormaPagamento},''), 'N/A') AS forma, COUNT(*) AS qtd, COALESCE(SUM(p.{$colTotal}), 0) AS total"
                . " FROM pedidos p WHERE {$whereStr} GROUP BY forma ORDER BY total DESC";
            $stmt4 = $this->db->prepare($sqlPag);
            $stmt4->execute($params);
            $porPagamento = $stmt4->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // Status dispon├¡veis para filtro ÔÇö usar lista can├┤nica do sistema
        $statusList = self::getStatusList();

        // Taxa de convers├úo USDÔåÆBRL do sistema
        $taxaUsdBrl = 5.85;
        try {
            // Usar PedidoManualService que j├í tem a l├│gica robusta de busca
            $svc = new \App\Services\PedidoManualService();
            $r = $svc->getTaxaConversaoUSDBRL();
            if ($r > 1) {
                $taxaUsdBrl = $r;
            }
        } catch (\Exception $e) {
            // Fallback: buscar direto da tabela
            try {
                $stR = $this->db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                $r = (float)($stR->fetchColumn() ?: 0);
                if ($r > 1) { $taxaUsdBrl = $r; }
            } catch (\Exception $e2) {}
        }

        // Totais separados por moeda (para os cards)
        $totaisPorMoedaCards = [];
        if ($colMoeda !== '') {
            $sumFields = [];
            if ($colTotal !== '') $sumFields[] = "COALESCE(SUM(p.{$colTotal}), 0) AS total";
            if ($colSubtotal !== '') $sumFields[] = "COALESCE(SUM(p.{$colSubtotal}), 0) AS subtotal";
            if ($colServicos !== '') $sumFields[] = "COALESCE(SUM(p.{$colServicos}), 0) AS servicos";
            if ($colImpostos !== '') $sumFields[] = "COALESCE(SUM(p.{$colImpostos}), 0) AS impostos";
            if ($colFrete !== '') $sumFields[] = "COALESCE(SUM(p.{$colFrete}), 0) AS frete";
            if ($colImpostoLocal !== '') $sumFields[] = "COALESCE(SUM(p.{$colImpostoLocal}), 0) AS imposto_local";
            $sumFields[] = "COUNT(*) AS qtd";

            $sqlTM = "SELECT UPPER(COALESCE(p.{$colMoeda},'USD')) AS moeda, " . implode(', ', $sumFields)
                . " FROM pedidos p WHERE {$whereStr} GROUP BY moeda";
            $stTM = $this->db->prepare($sqlTM);
            $stTM->execute($params);
            foreach ($stTM->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $totaisPorMoedaCards[strtoupper($row['moeda'] ?? 'USD')] = $row;
            }
        }

        $data = compact('totais', 'porStatus', 'porMoeda', 'porPagamento', 'totaisPorMoedaCards', 'taxaUsdBrl', 'dateStart', 'dateEnd', 'statusFilter', 'moedaFilter', 'statusList');

        // === INTEGRA├ç├âO DESPESAS ===
        $despesasResumo = ['total_brl' => 0, 'total_usd' => 0, 'total' => 0, 'pago_brl' => 0, 'pago_usd' => 0, 'pago' => 0, 'aberto' => 0, 'por_categoria' => []];
        try {
            $stDespExists = $this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'despesas'");
            if ((int)$stDespExists->fetchColumn() > 0) {
                // Total despesas no per├¡odo por moeda (por compet├¬ncia)
                $stD = $this->db->prepare("SELECT UPPER(COALESCE(moeda,'BRL')) as moeda, COALESCE(SUM(valor),0) as total FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL GROUP BY UPPER(COALESCE(moeda,'BRL'))");
                $stD->execute([$dateStart, $dateEnd]);
                foreach ($stD->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    if (strtoupper($r['moeda']) === 'USD') $despesasResumo['total_usd'] = (float)$r['total'];
                    else $despesasResumo['total_brl'] = (float)$r['total'];
                }
                // Total convertido para BRL
                $despesasResumo['total'] = $despesasResumo['total_brl'] + ($despesasResumo['total_usd'] * $taxaUsdBrl);

                // Pago no per├¡odo por moeda
                $stDP = $this->db->prepare("SELECT UPPER(COALESCE(moeda,'BRL')) as moeda, COALESCE(SUM(valor),0) as total FROM despesas WHERE status = 'paga' AND data_pagamento >= ? AND data_pagamento <= ? AND deleted_at IS NULL GROUP BY UPPER(COALESCE(moeda,'BRL'))");
                $stDP->execute([$dateStart, $dateEnd]);
                foreach ($stDP->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    if (strtoupper($r['moeda']) === 'USD') $despesasResumo['pago_usd'] = (float)$r['total'];
                    else $despesasResumo['pago_brl'] = (float)$r['total'];
                }
                $despesasResumo['pago'] = $despesasResumo['pago_brl'] + ($despesasResumo['pago_usd'] * $taxaUsdBrl);

                // Em aberto
                $despesasResumo['aberto'] = $despesasResumo['total'] - $despesasResumo['pago'];
                if ($despesasResumo['aberto'] < 0) $despesasResumo['aberto'] = 0;

                // Por categoria (convertido para BRL)
                $stDC = $this->db->prepare("SELECT c.nome as categoria, c.cor, c.grupo, d.moeda, COALESCE(SUM(d.valor),0) as total, COUNT(*) as qtd FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE d.competencia >= ? AND d.competencia <= ? AND d.status != 'cancelada' AND d.deleted_at IS NULL GROUP BY d.categoria_id, d.moeda ORDER BY total DESC");
                $stDC->execute([$dateStart, $dateEnd]);
                $rawCats = $stDC->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                // Consolidar por categoria convertendo USDÔåÆBRL
                $catMap = [];
                foreach ($rawCats as $rc) {
                    $catNome = $rc['categoria'] ?? 'Sem categoria';
                    if (!isset($catMap[$catNome])) $catMap[$catNome] = ['categoria' => $catNome, 'cor' => $rc['cor'] ?? '#6b7280', 'grupo' => $rc['grupo'] ?? '', 'total' => 0, 'qtd' => 0];
                    $val = (float)($rc['total'] ?? 0);
                    if (strtoupper($rc['moeda'] ?? '') === 'USD') $val *= $taxaUsdBrl;
                    $catMap[$catNome]['total'] += $val;
                    $catMap[$catNome]['qtd'] += (int)($rc['qtd'] ?? 0);
                }
                usort($catMap, function($a, $b) { return $b['total'] <=> $a['total']; });
                $despesasResumo['por_categoria'] = array_values($catMap);
            }
        } catch (\Exception $e) {}

        $data['despesasResumo'] = $despesasResumo;

        // === DRE COMPLETO ÔÇö Concilia├º├úo por Gateway ===
        $dreGateways = [];
        try {
            $stGw = $this->db->prepare("
                SELECT 
                    LOWER(COALESCE(payment_gateway,'N/A')) as gateway,
                    COUNT(*) as qtd,
                    COALESCE(SUM(CASE WHEN UPPER(COALESCE(moeda,'BRL'))='USD' THEN total ELSE 0 END),0) as total_usd,
                    COALESCE(SUM(CASE WHEN UPPER(COALESCE(moeda,'BRL'))!='USD' THEN total ELSE 0 END),0) as total_brl,
                    COALESCE(SUM(CASE WHEN payment_status='approved' THEN 1 ELSE 0 END),0) as aprovados,
                    COALESCE(SUM(CASE WHEN payment_status='pending' OR payment_status IS NULL THEN 1 ELSE 0 END),0) as pendentes,
                    COALESCE(SUM(CASE WHEN payment_status='rejected' THEN 1 ELSE 0 END),0) as rejeitados,
                    COALESCE(SUM(CASE WHEN payment_status='refunded' THEN 1 ELSE 0 END),0) as estornados
                FROM pedidos 
                WHERE created_at >= :ds AND created_at < DATE_ADD(:de, INTERVAL 1 DAY)
                AND " . (in_array('deleted_at', $cols, true) ? "deleted_at IS NULL AND" : "") . "
                LOWER(COALESCE(status,'')) NOT IN ('apagado','deleted','lixeira','trash')
                GROUP BY LOWER(COALESCE(payment_gateway,'N/A'))
                ORDER BY total_brl DESC
            ");
            $stGw->execute([':ds' => $dateStart, ':de' => $dateEnd]);
            $dreGateways = $stGw->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Configura├º├Áes DRE (al├¡quotas)
        $dreConfig = ['imposto_percentual' => 15.0, 'taxa_gateway_percentual' => 0.0];
        try {
            $stCfg = $this->db->query("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('dre_imposto_percentual','dre_taxa_gateway_percentual')");
            foreach ($stCfg->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $cfg) {
                if ($cfg['chave'] === 'dre_imposto_percentual') $dreConfig['imposto_percentual'] = (float)$cfg['valor'];
                if ($cfg['chave'] === 'dre_taxa_gateway_percentual') $dreConfig['taxa_gateway_percentual'] = (float)$cfg['valor'];
            }
        } catch (\Exception $e) {}

        $data['dreGateways'] = $dreGateways;
        $data['dreConfig'] = $dreConfig;

        extract($data);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-geral/index.php';
        $content = ob_get_clean();

        $title = 'Relat├│rio Geral';
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function pick(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return '';
    }
}
