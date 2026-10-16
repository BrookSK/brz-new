<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

/**
 * Relatório Trimestral de Produtos Vendidos.
 *
 * Consolida o valor de produtos vendidos (SUBTOTAL dos itens) por mês, agrupado em
 * trimestres (Jan-Mar, Abr-Jun, Jul-Set, Out-Dez), com total por mês, total por
 * trimestre e total geral consolidado.
 *
 * Opções:
 *  - consolidar=brl : converte os valores em USD para BRL (pela taxa atual) e soma
 *    tudo numa moeda só, para uma visão consolidada.
 *  - format=pdf     : gera o relatório em PDF (via Dompdf; fallback para HTML imprimível).
 *
 * Regra de negócio: itens de REDIRECIONAMENTO (tipo_item = 'pacote_redirecionamento'
 * ou produto_id >= 999990) NÃO entram na soma — eles não representam venda de produto.
 */
class AdminRelatorioTrimestralController extends Controller {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        // Ano do relatório (default: ano corrente)
        $ano = (int) $request->getParam('ano', date('Y'));
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) date('Y');
        }

        // Moeda opcional (filtro). Vazio = todas.
        $moedaFilter = strtoupper(trim((string) $request->getParam('moeda', '')));

        // Consolidação de moeda: '' (nenhuma), 'brl' (tudo em real) ou 'usd' (tudo em dólar).
        // Usa a mesma taxa USD↔BRL do site (configuracoes_sistema), via ExchangeRate.
        $consolidar = strtolower(trim((string) $request->getParam('consolidar', '')));
        if (!in_array($consolidar, ['brl', 'usd'], true)) {
            $consolidar = '';
        }

        // Formato de saída
        $format = strtolower(trim((string) $request->getParam('format', 'html')));

        // Resolver tabela de itens (ambiente pode ter pedido_itens ou pedido_items)
        $itensTable = $this->resolveItensTable();

        // Resolver nome das colunas relevantes
        $colsItens = $this->describe($itensTable);
        $colSubtotalItem = $this->pick($colsItens, ['subtotal']);
        $colTipoItem = $this->pick($colsItens, ['tipo_item']);
        $colProdutoId = $this->pick($colsItens, ['produto_id']);

        $colsPedidos = $this->describe('pedidos');
        $colMoeda = $this->pick($colsPedidos, ['moeda', 'currency']);
        $temDeletedAt = in_array('deleted_at', $colsPedidos, true);

        // Definição dos trimestres
        $trimestres = [
            ['nome' => __('admin.report_quarterly.q1', '1º Trimestre (Jan - Mar)'), 'meses' => [1, 2, 3]],
            ['nome' => __('admin.report_quarterly.q2', '2º Trimestre (Abr - Jun)'), 'meses' => [4, 5, 6]],
            ['nome' => __('admin.report_quarterly.q3', '3º Trimestre (Jul - Set)'), 'meses' => [7, 8, 9]],
            ['nome' => __('admin.report_quarterly.q4', '4º Trimestre (Out - Dez)'), 'meses' => [10, 11, 12]],
        ];

        $nomesMeses = [
            1 => __('admin.months.jan', 'Janeiro'),
            2 => __('admin.months.feb', 'Fevereiro'),
            3 => __('admin.months.mar', 'Março'),
            4 => __('admin.months.apr', 'Abril'),
            5 => __('admin.months.may', 'Maio'),
            6 => __('admin.months.jun', 'Junho'),
            7 => __('admin.months.jul', 'Julho'),
            8 => __('admin.months.aug', 'Agosto'),
            9 => __('admin.months.sep', 'Setembro'),
            10 => __('admin.months.oct', 'Outubro'),
            11 => __('admin.months.nov', 'Novembro'),
            12 => __('admin.months.dec', 'Dezembro'),
        ];

        $taxaUsdBrl = \App\Core\ExchangeRate::getUsdToBrl();

        // Buscar o total de produtos vendidos por mês/moeda do ano inteiro
        $porMesMoeda = [];
        if ($colSubtotalItem !== '') {
            $porMesMoeda = $this->totaisPorMesMoeda(
                $itensTable, $colSubtotalItem, $colTipoItem, $colProdutoId,
                $colMoeda, $temDeletedAt, $ano, $moedaFilter
            );
        }

        // Se consolidar: converter todas as moedas para a moeda alvo e colapsar numa chave só.
        if ($consolidar !== '') {
            $porMesMoeda = $this->consolidarMoeda($porMesMoeda, strtoupper($consolidar), $taxaUsdBrl);
        }

        // Montar estrutura consolidada por trimestre
        $relatorio = [];
        $totalGeralPorMoeda = [];
        foreach ($trimestres as $tri) {
            $meses = [];
            $totalTriPorMoeda = [];
            foreach ($tri['meses'] as $m) {
                $valoresMoeda = $porMesMoeda[$m] ?? [];
                foreach ($valoresMoeda as $moeda => $valor) {
                    $totalTriPorMoeda[$moeda] = ($totalTriPorMoeda[$moeda] ?? 0) + $valor;
                    $totalGeralPorMoeda[$moeda] = ($totalGeralPorMoeda[$moeda] ?? 0) + $valor;
                }
                $meses[] = [
                    'mes' => $m,
                    'nome' => $nomesMeses[$m],
                    'por_moeda' => $valoresMoeda,
                ];
            }
            $relatorio[] = [
                'nome' => $tri['nome'],
                'meses' => $meses,
                'total_por_moeda' => $totalTriPorMoeda,
            ];
        }

        $data = [
            'ano' => $ano,
            'moedaFilter' => $moedaFilter,
            'consolidar' => $consolidar,
            'relatorio' => $relatorio,
            'totalGeralPorMoeda' => $totalGeralPorMoeda,
            'taxaUsdBrl' => $taxaUsdBrl,
            'itensTable' => $itensTable,
            'subtotalDisponivel' => $colSubtotalItem !== '',
        ];

        if ($format === 'pdf') {
            $this->renderPdf($data);
            return;
        }

        extract($data);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-trimestral/index.php';
        $content = ob_get_clean();

        $title = __('admin.report_quarterly.title', 'Relatório Trimestral de Vendas');
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Converte um mapa [mes => [moeda => valor]] para [mes => [moedaAlvo => total]],
     * aplicando a taxa USD↔BRL do site. moedaAlvo = 'BRL' ou 'USD'.
     *
     * - Alvo BRL: valores em BRL mantidos; demais (tratados como USD) multiplicados pela taxa.
     * - Alvo USD: valores em USD mantidos; demais (tratados como BRL) divididos pela taxa.
     */
    private function consolidarMoeda(array $porMesMoeda, string $moedaAlvo, float $taxa): array {
        $moedaAlvo = ($moedaAlvo === 'USD') ? 'USD' : 'BRL';
        $out = [];
        foreach ($porMesMoeda as $mes => $moedas) {
            $total = 0.0;
            foreach ($moedas as $moeda => $valor) {
                $moeda = strtoupper((string) $moeda);
                $valor = (float) $valor;
                if ($moedaAlvo === 'BRL') {
                    $total += ($moeda === 'BRL') ? $valor : ($valor * $taxa);
                } else { // USD
                    $total += ($moeda === 'USD') ? $valor : ($taxa > 0 ? ($valor / $taxa) : 0.0);
                }
            }
            $out[$mes] = [$moedaAlvo => $total];
        }
        return $out;
    }

    /**
     * Soma o subtotal dos itens de produto (excluindo redirecionamento), agrupado
     * por mês e moeda, para o ano informado.
     *
     * @return array<int, array<string, float>> [mes => [moeda => valor]]
     */
    private function totaisPorMesMoeda(
        string $itensTable, string $colSubtotalItem, string $colTipoItem, string $colProdutoId,
        string $colMoeda, bool $temDeletedAt, int $ano, string $moedaFilter
    ): array {
        $moedaExpr = ($colMoeda !== '') ? "UPPER(COALESCE(p.{$colMoeda}, 'USD'))" : "'USD'";

        $where = [
            'YEAR(p.created_at) = :ano',
            "LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash','cancelado','cancelled')",
        ];
        $params = [':ano' => $ano];

        if ($temDeletedAt) {
            $where[] = 'p.deleted_at IS NULL';
        }

        // Excluir itens de redirecionamento da soma de produtos vendidos
        if ($colTipoItem !== '') {
            $where[] = "COALESCE(pi.{$colTipoItem}, 'produto') <> 'pacote_redirecionamento'";
        }
        if ($colProdutoId !== '') {
            $where[] = "(pi.{$colProdutoId} IS NULL OR pi.{$colProdutoId} < 999990)";
        }

        if ($moedaFilter !== '' && $colMoeda !== '') {
            $where[] = "UPPER(p.{$colMoeda}) = :moeda";
            $params[':moeda'] = $moedaFilter;
        }

        $whereStr = implode(' AND ', $where);

        $sql = "SELECT MONTH(p.created_at) AS mes, {$moedaExpr} AS moeda, "
             . "COALESCE(SUM(pi.{$colSubtotalItem}), 0) AS total "
             . "FROM {$itensTable} pi "
             . "INNER JOIN pedidos p ON p.id = pi.pedido_id "
             . "WHERE {$whereStr} "
             . "GROUP BY MONTH(p.created_at), {$moedaExpr}";

        $out = [];
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $mes = (int) ($r['mes'] ?? 0);
                $moeda = strtoupper((string) ($r['moeda'] ?? 'USD'));
                $total = (float) ($r['total'] ?? 0);
                if ($mes < 1 || $mes > 12) continue;
                $out[$mes][$moeda] = ($out[$mes][$moeda] ?? 0) + $total;
            }
        } catch (\Throwable $e) {
            error_log('[RelatorioTrimestral] Erro na agregação: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Renderiza o relatório em PDF. Usa Dompdf quando disponível; caso contrário,
     * devolve o HTML com auto-impressão (o usuário salva como PDF pelo navegador).
     */
    private function renderPdf(array $data): void {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/admin/relatorio-trimestral/pdf.php';
        $html = (string) ob_get_clean();

        $filename = 'relatorio-trimestral-' . $ano . ($consolidar !== '' ? '-' . strtoupper($consolidar) : '');

        if ($this->bootDompdf()) {
            try {
                $dompdf = new \Dompdf\Dompdf([
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                echo $dompdf->output();
                return;
            } catch (\Throwable $e) {
                error_log('[RelatorioTrimestral] Falha ao gerar PDF via Dompdf: ' . $e->getMessage());
                // cai no fallback abaixo
            }
        }

        // Fallback: HTML imprimível (o usuário usa "Salvar como PDF" do navegador)
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        echo '<script>window.print();</script>';
    }

    private function bootDompdf(): bool {
        try {
            if (class_exists('Dompdf\\Dompdf')) {
                return true;
            }
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (!file_exists($autoload)) {
                return false;
            }
            require_once $autoload;
            return class_exists('Dompdf\\Dompdf');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveItensTable(): string {
        if ($this->tableExists('pedido_itens')) return 'pedido_itens';
        if ($this->tableExists('pedido_items')) return 'pedido_items';
        return 'pedido_itens';
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function describe(string $table): array {
        try {
            $st = $this->db->query('DESCRIBE ' . $table);
            return $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function pick(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return '';
    }
}
