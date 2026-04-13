<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminRelatorioGeralController extends Controller {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $dateStart = $request->getParam('date_start', date('Y-m-01'));
        $dateEnd = $request->getParam('date_end', date('Y-m-d'));
        $statusFilter = $request->getParam('status', '');
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

        // WHERE
        $where = ["p.created_at >= :ds", "p.created_at < DATE_ADD(:de, INTERVAL 1 DAY)"];
        $params = [':ds' => $dateStart, ':de' => $dateEnd];

        // Excluir carnê e cancelados
        $where[] = "LOWER(COALESCE(p.status,'')) NOT IN ('carne_pagando','carne_aguardando','cancelado','cancelled','apagado','deleted','lixeira','trash')";
        if (in_array('deleted_at', $cols, true)) {
            $where[] = "p.deleted_at IS NULL";
        }

        if ($statusFilter !== '') {
            $where[] = "p.status = :st";
            $params[':st'] = $statusFilter;
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

        // Totais por status
        $sqlStatus = "SELECT p.status, COUNT(*) AS qtd, COALESCE(SUM(p.{$colTotal}), 0) AS total"
            . ($colSubtotal ? ", COALESCE(SUM(p.{$colSubtotal}), 0) AS subtotal" : '')
            . ($colServicos ? ", COALESCE(SUM(p.{$colServicos}), 0) AS servicos" : '')
            . ($colImpostos ? ", COALESCE(SUM(p.{$colImpostos}), 0) AS impostos" : '')
            . ($colFrete ? ", COALESCE(SUM(p.{$colFrete}), 0) AS frete" : '')
            . ($colImpostoLocal ? ", COALESCE(SUM(p.{$colImpostoLocal}), 0) AS imposto_local" : '')
            . " FROM pedidos p WHERE {$whereStr} GROUP BY p.status ORDER BY total DESC";
        $stmt2 = $this->db->prepare($sqlStatus);
        $stmt2->execute($params);
        $porStatus = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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

        // Status disponíveis para filtro
        $statusList = [];
        try {
            $st = $this->db->query("SELECT DISTINCT status FROM pedidos WHERE status IS NOT NULL AND status != '' ORDER BY status");
            $statusList = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {}

        // Taxa de conversão USD→BRL do sistema
        $taxaUsdBrl = 5.5;
        try {
            $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'configuracoes_moeda'];
            foreach ($tablesToTry as $t) {
                try {
                    $stT = $this->db->prepare('SHOW TABLES LIKE ?');
                    $stT->execute([$t]);
                    if (!$stT->fetchColumn()) continue;

                    if ($t === 'configuracoes_moeda') {
                        $stR = $this->db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem='USD' AND moeda_destino='BRL' ORDER BY data_atualizacao DESC LIMIT 1");
                        $r = (float)($stR->fetchColumn() ?: 0);
                        if ($r > 1) { $taxaUsdBrl = $r; break; }
                    } else {
                        $stCols = $this->db->query('DESCRIBE ' . $t);
                        $tCols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                        if (in_array('categoria', $tCols, true) && in_array('chave', $tCols, true)) {
                            $valCol = in_array('valor', $tCols, true) ? 'valor' : (in_array('value', $tCols, true) ? 'value' : '');
                            if ($valCol !== '') {
                                $stR = $this->db->prepare("SELECT {$valCol} FROM {$t} WHERE categoria='moeda' AND chave='taxa_conversao_usd_brl' LIMIT 1");
                                $stR->execute();
                                $r = (float)($stR->fetchColumn() ?: 0);
                                if ($r > 1) { $taxaUsdBrl = $r; break; }
                            }
                        }
                    }
                } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {}

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
        extract($data);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-geral/index.php';
        $content = ob_get_clean();

        $title = 'Relatório Geral';
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function pick(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return '';
    }
}
