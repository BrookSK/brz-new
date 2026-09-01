<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminComissoesGlobalController
{
    private $connection;

    public function __construct()
    {
        $this->connection = \Config\Database::getConnection();
    }

    private function pick(array $cols, array $candidates): string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return '';
    }

    private function getCols(string $table): array
    {
        try {
            $st = $this->connection->query('DESCRIBE ' . $table);
            return $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fmt(float $v, string $moeda): string
    {
        $moeda = strtoupper(trim($moeda));
        if ($moeda === 'USD') return '$ ' . number_format($v, 2, '.', ',');
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    /** Busca taxa USD→BRL da tabela de configurações de moeda */
    private function getUsdToBrl(): float
    {
        try {
            $st = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
            $st->execute();
            $v = (float)($st->fetchColumn() ?: 0);
            if ($v > 1.0) return $v;
        } catch (\Exception $e) {}
        try {
            foreach (['configuracoes_sistema', 'configuracoes', 'settings'] as $t) {
                $st = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $st->execute([$t]);
                if ((int)($st->fetchColumn() ?: 0) === 0) continue;
                $cols = $this->getCols($t);
                $kc = $this->pick($cols, ['chave', 'key', 'config_key']);
                $vc = $this->pick($cols, ['valor', 'value']);
                if ($kc && $vc) {
                    $st2 = $this->connection->prepare("SELECT {$vc} FROM {$t} WHERE {$kc} IN ('taxa_usd_brl','usd_brl','cambio_usd') LIMIT 1");
                    $st2->execute();
                    $v = (float)($st2->fetchColumn() ?: 0);
                    if ($v > 1.0) return $v;
                }
            }
        } catch (\Exception $e) {}
        return 5.5;
    }

    /**
     * Dado "YYYY-MM", retorna [data_inicio, data_fim] no ciclo dia 10 → dia 9 do mês seguinte.
     */
    private function periodoDoMes(string $mesAno): array
    {
        [$ano, $mes] = explode('-', $mesAno);
        $ano = (int)$ano; $mes = (int)$mes;
        $inicio = sprintf('%04d-%02d-10', $ano, $mes);
        $proxMes = $mes === 12 ? 1 : $mes + 1;
        $proxAno = $mes === 12 ? $ano + 1 : $ano;
        $fim = sprintf('%04d-%02d-09', $proxAno, $proxMes);
        return [$inicio, $fim];
    }

    // ─── Tela 1: Comissões de todos os vendedores ───
    public function comissoesTodas(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        // Período: seletor de mês/ano (ciclo dia 10 → dia 9)
        $periodoParam = (string)$request->getParam('periodo', '');
        $dataInicio   = (string)$request->getParam('data_inicio', '');
        $dataFim      = (string)$request->getParam('data_fim', '');

        if ($periodoParam !== '' && preg_match('/^\d{4}-\d{2}$/', $periodoParam)) {
            [$dataInicio, $dataFim] = $this->periodoDoMes($periodoParam);
        } elseif ($dataInicio === '' && $dataFim === '') {
            $periodoParam = date('Y-m');
            [$dataInicio, $dataFim] = $this->periodoDoMes($periodoParam);
        }

        $usdToBrl = $this->getUsdToBrl();

        $cols = $this->getCols('pedidos');
        $colTotal = $this->pick($cols, ['valor_total', 'total', 'amount']);
        $colImpostos = $this->pick($cols, ['valor_impostos', 'impostos']);
        $colMoeda = $this->pick($cols, ['moeda', 'currency']);
        $colCreatedAt = $this->pick($cols, ['created_at', 'data_criacao', 'data_pedido']);
        $colOrigem = $this->pick($cols, ['origem_pedido', 'origem', 'tipo']);
        $colStatus = $this->pick($cols, ['status', 'status_pedido']);
        $colPayStatus = $this->pick($cols, ['payment_status', 'status_pagamento']);
        $colCriadoPor = $this->pick($cols, ['admin_criador_id', 'criado_por', 'vendedor_id', 'created_by']);
        $colSemComissao = $this->pick($cols, ['sem_comissao']);

        // Buscar pedidos manuais pagos
        $where = [];
        $params = [];

        if ($colOrigem && $colCriadoPor) {
            $where[] = "(LOWER(COALESCE(p.{$colOrigem}, '')) IN ('manual','admin') OR p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0)";
        } elseif ($colOrigem) {
            $where[] = "(LOWER(COALESCE(p.{$colOrigem}, '')) IN ('manual','admin'))";
        } elseif ($colCriadoPor) {
            $where[] = "(p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0)";
        }

        // Excluir pedidos deletados/na lixeira
        if (in_array('deleted_at', $cols, true)) {
            $where[] = "p.deleted_at IS NULL";
        }

        $paidParts = [];
        if ($colStatus) $paidParts[] = "LOWER(COALESCE(p.{$colStatus}, '')) IN ('pago','paid','approved','aprovado','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";
        if ($colPayStatus) $paidParts[] = "LOWER(COALESCE(p.{$colPayStatus}, '')) IN ('approved','paid','pago','aprovado','confirmed','received','succeeded','success')";
        if (!empty($paidParts)) $where[] = '(' . implode(' OR ', $paidParts) . ')';

        if ($colSemComissao) $where[] = "(p.{$colSemComissao} IS NULL OR p.{$colSemComissao} = 0)";

        if ($dataInicio !== '' && $colCreatedAt) {
            $where[] = "DATE(p.{$colCreatedAt}) >= :di";
            $params[':di'] = $dataInicio;
        }
        if ($dataFim !== '' && $colCreatedAt) {
            $where[] = "DATE(p.{$colCreatedAt}) <= :df";
            $params[':df'] = $dataFim;
        }

        $sql = 'SELECT p.id'
            . ($colTotal ? ", p.{$colTotal} AS valor_total" : '')
            . ($colImpostos ? ", p.{$colImpostos} AS impostos" : '')
            . ($colMoeda ? ", p.{$colMoeda} AS moeda" : '')
            . ($colCreatedAt ? ", p.{$colCreatedAt} AS created_at" : '')
            . ($colCriadoPor ? ", p.{$colCriadoPor} AS criado_por" : '')
            . ' FROM pedidos p';
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . ($colCreatedAt ? "p.{$colCreatedAt} DESC" : 'p.id DESC') . ' LIMIT 2000';

        $rows = [];
        try {
            $st = $this->connection->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $rows = [];
        }

        // Custo dos produtos por pedido
        $custoByPedido = [];
        $itensTable = $this->detectItensTable();
        if ($itensTable && !empty($rows)) {
            $custoByPedido = $this->calcCustoProdutosByPedido($itensTable, array_column($rows, 'id'));
        }

        // Comissões de processamento — converter tudo para BRL
        $comProc = []; // [uid => float em BRL]
        try {
            $stT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'comissoes_processamento'");
            $stT->execute();
            if ((int)($stT->fetchColumn() ?: 0) > 0) {
                $sqlP = 'SELECT usuario_id, moeda, SUM(valor_comissao) AS total_comissao FROM comissoes_processamento';
                $wpP = []; $ppP = [];
                if ($dataInicio !== '') { $wpP[] = 'DATE(created_at) >= ?'; $ppP[] = $dataInicio; }
                if ($dataFim !== '')    { $wpP[] = 'DATE(created_at) <= ?'; $ppP[] = $dataFim; }
                if (!empty($wpP)) $sqlP .= ' WHERE ' . implode(' AND ', $wpP);
                $sqlP .= ' GROUP BY usuario_id, moeda';
                $stP = $this->connection->prepare($sqlP);
                $stP->execute($ppP);
                foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $uid = (int)($r['usuario_id'] ?? 0);
                    $m   = strtoupper(trim((string)($r['moeda'] ?? 'BRL')));
                    $com = (float)($r['total_comissao'] ?? 0);
                    if ($m === 'USD') $com *= $usdToBrl;
                    $comProc[$uid] = ($comProc[$uid] ?? 0.0) + $com;
                }
            }
        } catch (\Exception $e) {}

        // Faixas de comissão manual
        $faixas = $this->getComissaoManualFaixas();

        // Agrupar por vendedor — tudo convertido para BRL
        $porVendedor = []; // [uid => ['faturado','impostos','custo','liquido','qtd']]
        foreach ($rows as $r) {
            $uid  = (int)($r['criado_por'] ?? 0);
            $m    = strtoupper(trim((string)($r['moeda'] ?? 'BRL')));
            if ($m === '') $m = 'BRL';
            $pid  = (int)($r['id'] ?? 0);
            $fat  = (float)($r['valor_total'] ?? 0);
            $imp  = (float)($r['impostos'] ?? 0);
            $custo = (float)($custoByPedido[$pid] ?? 0);
            $liq  = $fat - $custo - $imp;
            if ($m === 'USD') { $fat *= $usdToBrl; $imp *= $usdToBrl; $custo *= $usdToBrl; $liq *= $usdToBrl; }

            if (!isset($porVendedor[$uid])) {
                $porVendedor[$uid] = ['faturado' => 0.0, 'impostos' => 0.0, 'custo' => 0.0, 'liquido' => 0.0, 'qtd' => 0];
            }
            $porVendedor[$uid]['faturado'] += $fat;
            $porVendedor[$uid]['impostos'] += $imp;
            $porVendedor[$uid]['custo']    += $custo;
            $porVendedor[$uid]['liquido']  += $liq;
            $porVendedor[$uid]['qtd']++;
        }

        // Nomes dos vendedores
        $nomes = [];
        $uids = array_unique(array_merge(array_keys($porVendedor), array_keys($comProc)));
        if (!empty($uids)) {
            $in = implode(',', array_fill(0, count($uids), '?'));
            try {
                $st = $this->connection->prepare("SELECT id, nome, email FROM usuarios WHERE id IN ({$in})");
                $st->execute(array_values($uids));
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $u) {
                    $nomes[(int)$u['id']] = trim(($u['nome'] ?? '') . ' (' . ($u['email'] ?? '') . ')');
                }
            } catch (\Exception $e) {}
        }

        // Vendas orgânicas (uid = 0)
        $organico = $porVendedor[0] ?? null;
        unset($porVendedor[0]);

        // ── Render ───────────────────────────────────────────────────────────
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();

        echo '<div class="pt-3">';
        echo '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-3 border-bottom pb-2">';
        echo '<h1 class="page-title">' . __('admin.commissions_global.title', 'Comissões - Visão Global') . '</h1>';
        echo '</div>';

        echo '<div class="alert alert-light border mb-4 small">';
        echo '<strong>' . __('admin.commissions_global.formula_label', 'Fórmula:') . '</strong> ' . __('admin.commissions_global.formula_text', 'Bruto = total vendido (convertido para R$) | Líquido = Bruto − Custo − Impostos | Comissão = Líquido × % da faixa');
        echo ' &nbsp;|&nbsp; <strong>' . __('admin.commissions_global.exchange_label', 'Câmbio:') . '</strong> 1 USD = R$ ' . number_format($usdToBrl, 4, ',', '.');
        echo '</div>';

        // Filtro por período (seletor de mês)
        $periodoSel = $periodoParam;
        if ($periodoSel === '' && $dataInicio !== '') {
            $dt = \DateTime::createFromFormat('Y-m-d', $dataInicio);
            if ($dt && (int)$dt->format('d') === 10) $periodoSel = $dt->format('Y-m');
        }

        // Gerar lista de períodos: últimos 13 meses
        $periodos = [];
        $hoje = new \DateTime();
        $diaHoje = (int)$hoje->format('d');
        $mesBase = (int)$hoje->format('m');
        $anoBase = (int)$hoje->format('Y');
        if ($diaHoje < 10) {
            $mesBase--;
            if ($mesBase <= 0) { $mesBase = 12; $anoBase--; }
        }
        for ($i = 0; $i <= 12; $i++) {
            $mm = $mesBase - $i; $aa = $anoBase;
            while ($mm <= 0) { $mm += 12; $aa--; }
            $key = sprintf('%04d-%02d', $aa, $mm);
            [$di, $df] = $this->periodoDoMes($key);
            $label = date('M/Y', mktime(0, 0, 0, $mm, 1, $aa)) . ' (' . date('d/m', strtotime($di)) . '–' . date('d/m', strtotime($df)) . ')';
            $periodos[$key] = $label;
        }

        echo '<div class="card mb-4"><div class="card-body">';
        echo '<form method="GET" class="row g-2 align-items-end">';
        echo '<div class="col-md-5"><label class="form-label fw-semibold">' . __('admin.commissions_global.commission_period', 'Período de comissão') . '</label>';
        echo '<select class="form-select" name="periodo" onchange="this.form.submit()">';
        foreach ($periodos as $k => $label) {
            echo '<option value="' . htmlspecialchars($k) . '"' . ($k === $periodoSel ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-auto d-grid"><button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>' . __('common.filter', 'Filtrar') . '</button></div>';
        echo '<div class="col-md-auto align-self-end"><small class="text-muted">' . __('admin.commissions_global.from', 'De') . ' <strong>' . date('d/m/Y', strtotime($dataInicio)) . '</strong> ' . __('admin.commissions_global.to', 'até') . ' <strong>' . date('d/m/Y', strtotime($dataFim)) . '</strong></small></div>';
        echo '</form></div></div>';

        // Vendas orgânicas
        if (!empty($organico)) {
            echo '<div class="card mb-4"><div class="card-header bg-success bg-opacity-10"><strong><i class="fas fa-leaf me-1"></i>' . __('admin.commissions_global.organic_sales', 'Vendas Orgânicas (sem vendedor atribuído)') . '</strong></div><div class="card-body">';
            echo $organico['qtd'] . ' ' . __('admin.commissions_global.orders_word', 'pedidos') . ' | ' . __('admin.commissions_global.gross', 'Bruto:') . ' <strong>' . $this->fmt($organico['faturado'], 'BRL') . '</strong>';
            echo ' | ' . __('admin.commissions_global.cost', 'Custo:') . ' ' . $this->fmt($organico['custo'], 'BRL');
            echo ' | ' . __('admin.commissions_global.taxes', 'Impostos:') . ' ' . $this->fmt($organico['impostos'], 'BRL');
            echo ' | ' . __('admin.commissions_global.net', 'Líquido:') . ' <strong>' . $this->fmt($organico['liquido'], 'BRL') . '</strong>';
            echo '</div></div>';
        }

        // Tabela por vendedor — tudo em BRL
        echo '<div class="card mb-4"><div class="card-header"><strong>' . __('admin.commissions_global.by_seller_header', 'Comissões por Vendedor (Pedidos Manuais + Processamento) — valores em R$') . '</strong></div><div class="card-body">';
        echo '<div class="table-responsive"><table class="table table-hover table-sm">';
        echo '<thead><tr>'
            . '<th>' . __('admin.commissions_global.th_seller', 'Vendedor') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_orders', 'Pedidos') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_gross', 'Bruto (R$)') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_product_cost', 'Custo Produto') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_taxes', 'Impostos') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_net', 'Líquido') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_commission_pct', '% Comissão') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_manual_commission', 'Comissão Manual') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_proc_commission', 'Comissão Proc.') . '</th>'
            . '<th class="text-end">' . __('admin.commissions_global.th_total_commission', 'Total Comissão') . '</th>'
            . '</tr></thead><tbody>';

        $allUids = array_unique(array_merge(array_keys($porVendedor), array_keys($comProc)));
        sort($allUids);
        $grandTotal = 0.0;

        foreach ($allUids as $uid) {
            if ($uid <= 0) continue;
            $nome = $nomes[$uid] ?? (__('admin.commissions_global.seller_label', 'Vendedor #') . $uid);
            $t = $porVendedor[$uid] ?? ['faturado' => 0.0, 'impostos' => 0.0, 'custo' => 0.0, 'liquido' => 0.0, 'qtd' => 0];
            $pct        = $this->resolvePercentualPorFaixas($t['faturado'], $faixas);
            $comManual  = max(0.0, $t['liquido']) * ($pct / 100);
            $comProcVal = (float)($comProc[$uid] ?? 0);
            $totalCom   = $comManual + $comProcVal;
            $grandTotal += $totalCom;

            echo '<tr>';
            echo '<td>' . htmlspecialchars($nome) . '</td>';
            echo '<td class="text-end">' . $t['qtd'] . '</td>';
            echo '<td class="text-end">' . $this->fmt($t['faturado'], 'BRL') . '</td>';
            echo '<td class="text-end">' . $this->fmt($t['custo'], 'BRL') . '</td>';
            echo '<td class="text-end">' . $this->fmt($t['impostos'], 'BRL') . '</td>';
            echo '<td class="text-end">' . $this->fmt($t['liquido'], 'BRL') . '</td>';
            echo '<td class="text-end">' . number_format($pct, 2, ',', '.') . '%</td>';
            echo '<td class="text-end">' . $this->fmt($comManual, 'BRL') . '</td>';
            echo '<td class="text-end">' . $this->fmt($comProcVal, 'BRL') . '</td>';
            echo '<td class="text-end fw-bold">' . $this->fmt($totalCom, 'BRL') . '</td>';
            echo '</tr>';
        }

        echo '</tbody><tfoot><tr class="table-dark">';
        echo '<td colspan="9" class="text-end fw-bold">' . __('admin.commissions_global.grand_total', 'Total Geral') . '</td>';
        echo '<td class="text-end fw-bold">' . $this->fmt($grandTotal, 'BRL') . '</td>';
        echo '</tr></tfoot></table></div></div></div>';

        echo '</div>';
        $content = ob_get_clean();
        $sidebarActive = 'comissoes-global';
        $title = __('admin.commissions_global.page_title', 'Comissões Global - Admin');
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    // ─── Tela 2: Resumo Financeiro Global ───
    public function resumoFinanceiro(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $dataInicio = (string) $request->getParam('data_inicio', '');
        $dataFim = (string) $request->getParam('data_fim', '');
        $status = (string) $request->getParam('status', '');
        $moedaFiltro = strtoupper(trim((string) $request->getParam('moeda', '')));
        if ($moedaFiltro !== '' && !in_array($moedaFiltro, ['USD', 'BRL'], true)) $moedaFiltro = '';

        $cols = $this->getCols('pedidos');
        $colTotal = $this->pick($cols, ['valor_total', 'total', 'amount']);
        $colImpostos = $this->pick($cols, ['valor_impostos', 'impostos']);
        $colTaxa = $this->pick($cols, ['taxa_servico']);
        $colSubtotal = $this->pick($cols, ['subtotal_produtos', 'subtotal']);
        $colMoeda = $this->pick($cols, ['moeda', 'currency']);
        $colCreatedAt = $this->pick($cols, ['created_at', 'data_criacao', 'data_pedido']);
        $colStatus = $this->pick($cols, ['status', 'status_pedido']);
        $colFrete = $this->pick($cols, ['frete', 'frete_manual', 'shipping']);
        $colTaxaConv = $this->pick($cols, ['taxa_conversao']);
        $colCodigo = $this->pick($cols, ['numero_pedido', 'codigo_pedido', 'codigo']);

        $where = [];
        $params = [];
        if ($dataInicio !== '' && $colCreatedAt) { $where[] = "DATE(p.{$colCreatedAt}) >= :di"; $params[':di'] = $dataInicio; }
        if ($dataFim !== '' && $colCreatedAt) { $where[] = "DATE(p.{$colCreatedAt}) <= :df"; $params[':df'] = $dataFim; }
        if ($status !== '' && $colStatus) { $where[] = "LOWER(p.{$colStatus}) = :st"; $params[':st'] = strtolower($status); }
        if ($moedaFiltro !== '' && $colMoeda) { $where[] = "UPPER(COALESCE(p.{$colMoeda}, 'BRL')) = :m"; $params[':m'] = $moedaFiltro; }

        $sel = ['p.id'];
        if ($colTotal) $sel[] = "p.{$colTotal} AS valor_total";
        if ($colImpostos) $sel[] = "p.{$colImpostos} AS impostos";
        if ($colTaxa) $sel[] = "p.{$colTaxa} AS taxa_servico";
        if ($colSubtotal) $sel[] = "p.{$colSubtotal} AS subtotal_produtos";
        if ($colMoeda) $sel[] = "p.{$colMoeda} AS moeda";
        if ($colCreatedAt) $sel[] = "p.{$colCreatedAt} AS created_at";
        if ($colStatus) $sel[] = "p.{$colStatus} AS status";
        if ($colFrete) $sel[] = "p.{$colFrete} AS frete";
        if ($colTaxaConv) $sel[] = "p.{$colTaxaConv} AS taxa_conversao";
        if ($colCodigo) $sel[] = "p.{$colCodigo} AS codigo";

        $sql = 'SELECT ' . implode(', ', $sel) . ' FROM pedidos p';
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . ($colCreatedAt ? "p.{$colCreatedAt} DESC" : 'p.id DESC') . ' LIMIT 2000';

        $rows = [];
        try {
            $st = $this->connection->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { $rows = []; }

        // Custo dos produtos
        $custoByPedido = [];
        $itensTable = $this->detectItensTable();
        if ($itensTable && !empty($rows)) {
            $custoByPedido = $this->calcCustoProdutosByPedido($itensTable, array_column($rows, 'id'));
        }

        // Totais por moeda
        $totais = [];
        $pedidosOut = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['id'] ?? 0);
            $m = strtoupper(trim((string) ($r['moeda'] ?? 'BRL')));
            if ($m === '') $m = 'BRL';

            $total = (float) ($r['valor_total'] ?? 0);
            $imp = (float) ($r['impostos'] ?? 0);
            $taxa = (float) ($r['taxa_servico'] ?? 0);
            $sub = (float) ($r['subtotal_produtos'] ?? 0);
            $frete = (float) ($r['frete'] ?? 0);
            $custo = (float) ($custoByPedido[$pid] ?? 0);

            if (!isset($totais[$m])) $totais[$m] = ['qtd' => 0, 'total' => 0, 'impostos' => 0, 'taxa_servico' => 0, 'subtotal_produtos' => 0, 'frete' => 0, 'custo_produtos' => 0];
            $totais[$m]['qtd']++;
            $totais[$m]['total'] += $total;
            $totais[$m]['impostos'] += $imp;
            $totais[$m]['taxa_servico'] += $taxa;
            $totais[$m]['subtotal_produtos'] += $sub;
            $totais[$m]['frete'] += $frete;
            $totais[$m]['custo_produtos'] += $custo;

            $pedidosOut[] = [
                'id' => $pid,
                'codigo' => (string) ($r['codigo'] ?? $pid),
                'status' => (string) ($r['status'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'moeda' => $m,
                'total' => $total,
                'impostos' => $imp,
                'taxa_servico' => $taxa,
                'subtotal_produtos' => $sub,
                'frete' => $frete,
                'custo_produtos' => $custo,
            ];
        }

        // Render
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();

        echo '<div class="pt-3">';
        echo '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-3 border-bottom pb-2">';
        echo '<h1 class="page-title">' . __('admin.commissions_global.summary_title', 'Resumo Financeiro Global') . '</h1>';
        echo '</div>';

        // Filtros
        echo '<div class="card mb-4"><div class="card-body">';
        echo '<form method="GET" class="row g-2 align-items-end">';
        echo '<div class="col-md-2"><label class="form-label">' . __('admin.commissions_global.start_date', 'Data início') . '</label><input type="date" class="form-control" name="data_inicio" value="' . htmlspecialchars($dataInicio) . '"></div>';
        echo '<div class="col-md-2"><label class="form-label">' . __('admin.commissions_global.end_date', 'Data fim') . '</label><input type="date" class="form-control" name="data_fim" value="' . htmlspecialchars($dataFim) . '"></div>';
        echo '<div class="col-md-2"><label class="form-label">' . __('common.status', 'Status') . '</label><input type="text" class="form-control" name="status" value="' . htmlspecialchars($status) . '" placeholder="' . htmlspecialchars(__('admin.commissions_global.status_placeholder', 'ex: pago'), ENT_QUOTES, 'UTF-8') . '"></div>';
        echo '<div class="col-md-2"><label class="form-label">' . __('admin.commissions_global.currency', 'Moeda') . '</label><select class="form-select" name="moeda">';
        echo '<option value="">' . __('admin.commissions_global.all_currencies', 'Todas') . '</option>';
        echo '<option value="USD"' . ($moedaFiltro === 'USD' ? ' selected' : '') . '>USD</option>';
        echo '<option value="BRL"' . ($moedaFiltro === 'BRL' ? ' selected' : '') . '>BRL</option>';
        echo '</select></div>';
        echo '<div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">' . __('common.filter', 'Filtrar') . '</button></div>';
        echo '</form></div></div>';

        // Cards de resumo por moeda
        foreach ($totais as $m => $t) {
            $lucro = $t['total'] - $t['impostos'] - $t['taxa_servico'] - $t['custo_produtos'] - $t['frete'];
            echo '<div class="card mb-3"><div class="card-header"><strong>' . htmlspecialchars($m) . ' - ' . $t['qtd'] . ' ' . __('admin.commissions_global.orders_word', 'pedidos') . '</strong></div>';
            echo '<div class="card-body"><div class="row g-3">';
            $cards = [
                [__('admin.commissions_global.card_total_collected', 'Total Arrecadado'), $t['total'], 'fas fa-dollar-sign', 'primary'],
                [__('admin.commissions_global.card_subtotal_products', 'Subtotal Produtos'), $t['subtotal_produtos'], 'fas fa-box', 'info'],
                [__('admin.commissions_global.card_taxes', 'Impostos'), $t['impostos'], 'fas fa-landmark', 'warning'],
                [__('admin.commissions_global.card_service_fee', 'Taxa de Serviço'), $t['taxa_servico'], 'fas fa-concierge-bell', 'secondary'],
                [__('admin.commissions_global.card_shipping', 'Frete'), $t['frete'], 'fas fa-truck', 'dark'],
                [__('admin.commissions_global.card_product_cost_usd', 'Custo Produtos (USD)'), $t['custo_produtos'], 'fas fa-coins', 'danger'],
            ];
            foreach ($cards as $c) {
                echo '<div class="col-md-2"><div class="border rounded p-3 text-center">';
                echo '<div class="text-muted small"><i class="' . $c[2] . ' me-1"></i>' . $c[0] . '</div>';
                echo '<div class="fs-5 fw-bold">' . $this->fmt($c[1], $m) . '</div>';
                echo '</div></div>';
            }
            echo '</div></div></div>';
        }

        if (empty($totais)) {
            echo '<div class="alert alert-info">' . __('admin.commissions_global.no_orders_filters', 'Nenhum pedido encontrado com os filtros selecionados.') . '</div>';
        }

        // Tabela detalhada
        if (!empty($pedidosOut)) {
            echo '<div class="card mb-4"><div class="card-header"><strong>' . __('admin.commissions_global.detail_by_order', 'Detalhamento por Pedido') . '</strong></div><div class="card-body">';
            echo '<div class="table-responsive"><table class="table table-sm table-hover">';
            echo '<thead><tr><th>ID</th><th>' . __('admin.commissions_global.th_code', 'Código') . '</th><th>' . __('common.status', 'Status') . '</th><th>' . __('admin.commissions_global.th_date', 'Data') . '</th><th>' . __('admin.commissions_global.th_currency', 'Moeda') . '</th><th class="text-end">' . __('common.total', 'Total') . '</th><th class="text-end">' . __('admin.commissions_global.th_subtotal_prod', 'Subtotal Prod.') . '</th><th class="text-end">' . __('admin.commissions_global.th_taxes', 'Impostos') . '</th><th class="text-end">' . __('admin.commissions_global.th_service_fee_short', 'Taxa Serviço') . '</th><th class="text-end">' . __('admin.commissions_global.card_shipping', 'Frete') . '</th><th class="text-end">' . __('admin.commissions_global.th_cost_prod', 'Custo Prod.') . '</th></tr></thead><tbody>';
            foreach ($pedidosOut as $r) {
                $m = $r['moeda'];
                echo '<tr>';
                echo '<td><a href="/admin/pedidos/detalhes/' . $r['id'] . '">' . $r['id'] . '</a></td>';
                echo '<td>' . htmlspecialchars($r['codigo']) . '</td>';
                echo '<td>' . htmlspecialchars($r['status']) . '</td>';
                echo '<td>' . (!empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-') . '</td>';
                echo '<td>' . htmlspecialchars($m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['total'], $m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['subtotal_produtos'], $m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['impostos'], $m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['taxa_servico'], $m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['frete'], $m) . '</td>';
                echo '<td class="text-end">' . $this->fmt($r['custo_produtos'], $m) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></div></div>';
        }

        echo '</div>';
        $content = ob_get_clean();
        $sidebarActive = 'resumo-financeiro';
        $title = __('admin.commissions_global.summary_page_title', 'Resumo Financeiro - Admin');
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    // ─── Helpers ───

    private function detectItensTable(): ?string
    {
        try {
            $st = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $st->execute(['pedido_itens']);
            $t1 = (int) ($st->fetchColumn() ?: 0) > 0;
            $st->execute(['pedido_items']);
            $t2 = (int) ($st->fetchColumn() ?: 0) > 0;
            if ($t1) return 'pedido_itens';
            if ($t2) return 'pedido_items';
        } catch (\Exception $e) {}
        return null;
    }

    private function calcCustoProdutosByPedido(string $itensTable, array $pedidoIds): array
    {
        $result = [];
        $pedidoIds = array_values(array_unique(array_filter(array_map('intval', $pedidoIds), fn($v) => $v > 0)));
        if (empty($pedidoIds)) return $result;

        try {
            $iCols = $this->getCols($itensTable);
            $colPedidoId = $this->pick($iCols, ['pedido_id']);
            $colProdutoId = $this->pick($iCols, ['produto_id']);
            $colQtd = $this->pick($iCols, ['quantidade', 'qty', 'qtd']);
            if (!$colPedidoId || !$colProdutoId || !$colQtd) return $result;

            $pCols = $this->getCols('produtos');
            $colCusto = $this->pick($pCols, ['preco_custo', 'custo', 'cost_price', 'valor_custo']);
            if (!$colCusto) return $result;

            $in = implode(',', array_fill(0, count($pedidoIds), '?'));
            $sql = "SELECT i.{$colPedidoId} AS pedido_id, SUM(COALESCE(pr.{$colCusto},0) * COALESCE(i.{$colQtd},0)) AS custo"
                . " FROM {$itensTable} i INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}"
                . " WHERE i.{$colPedidoId} IN ({$in}) GROUP BY i.{$colPedidoId}";
            $st = $this->connection->prepare($sql);
            $st->execute($pedidoIds);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $result[(int) $r['pedido_id']] = (float) ($r['custo'] ?? 0);
            }
        } catch (\Exception $e) {}
        return $result;
    }

    private function getComissaoManualFaixas(): array
    {
        try {
            $tables = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
            foreach ($tables as $t) {
                $st = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $st->execute([$t]);
                if ((int) ($st->fetchColumn() ?: 0) === 0) continue;

                $cols = $this->getCols($t);
                if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                    $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                    if ($valCol) {
                        $st2 = $this->connection->prepare("SELECT {$valCol} FROM {$t} WHERE categoria = 'comissao' AND chave = 'manual_faixas' LIMIT 1");
                        $st2->execute();
                        $raw = (string) ($st2->fetchColumn() ?: '');
                        if ($raw !== '') {
                            $arr = json_decode($raw, true);
                            if (is_array($arr)) return $arr;
                        }
                    }
                }

                $keyCol = $this->pick($cols, ['chave', 'key', 'nome', 'config_key']);
                $valCol = $this->pick($cols, ['valor', 'value', 'conteudo']);
                if ($keyCol && $valCol) {
                    $st2 = $this->connection->prepare("SELECT {$valCol} FROM {$t} WHERE {$keyCol} = 'comissao_manual_faixas' LIMIT 1");
                    $st2->execute();
                    $raw = (string) ($st2->fetchColumn() ?: '');
                    if ($raw !== '') {
                        $arr = json_decode($raw, true);
                        if (is_array($arr)) return $arr;
                    }
                }
            }
        } catch (\Exception $e) {}
        return [['min' => 0, 'max' => 999999999, 'percent' => 0]];
    }

    private function resolvePercentualPorFaixas(float $faturado, array $faixas): float
    {
        foreach ($faixas as $f) {
            $min = (float) ($f['min'] ?? 0);
            $max = (float) ($f['max'] ?? PHP_FLOAT_MAX);
            if ($faturado >= $min && $faturado <= $max) {
                return (float) ($f['percent'] ?? 0);
            }
        }
        return 0.0;
    }
}
