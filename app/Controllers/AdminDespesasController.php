<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminDespesasController extends Controller {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $tab = $request->getParam('tab', 'visao-geral');
        $filtros = [
            'status' => $request->getParam('status', ''),
            'categoria' => $request->getParam('categoria', ''),
            'tipo' => $request->getParam('tipo', ''),
            'competencia_de' => $request->getParam('competencia_de', date('Y-m-01')),
            'competencia_ate' => $request->getParam('competencia_ate', date('Y-m-t')),
            'forma_pagamento' => $request->getParam('forma_pagamento', ''),
            'busca' => $request->getParam('busca', ''),
            'rapido' => $request->getParam('rapido', ''),
        ];

        $this->ensureTables();

        // Exportação CSV
        if ($request->getParam('export') === 'csv') {
            $despesas = $this->listarDespesas($filtros);
            return $this->exportCSV($despesas);
        }

        // Categorias
        $categorias = [];
        try { $st = $this->db->query("SELECT * FROM despesa_categorias WHERE ativa = 1 ORDER BY nome"); $categorias = $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        // Stats do mês
        $stats = $this->getStats($filtros);

        // Despesas
        $despesas = $this->listarDespesas($filtros);

        // Recorrências
        $recorrencias = [];
        try { $st = $this->db->query("SELECT r.*, c.nome as categoria_nome, c.cor as categoria_cor FROM despesa_recorrencias r LEFT JOIN despesa_categorias c ON c.id = r.categoria_id WHERE r.ativa = 1 ORDER BY r.proxima_geracao ASC"); $recorrencias = $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        // Parcelamentos
        $parcelamentos = [];
        try { $st = $this->db->query("SELECT p.*, c.nome as categoria_nome, c.cor as categoria_cor FROM despesa_parcelamentos p LEFT JOIN despesa_categorias c ON c.id = p.categoria_id WHERE p.status = 'em_andamento' ORDER BY p.created_at DESC"); $parcelamentos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        // Comissões (do sistema)
        $comissoes = $this->getComissoes($filtros);

        $data = compact('tab', 'filtros', 'categorias', 'stats', 'despesas', 'recorrencias', 'parcelamentos', 'comissoes');

        $title = 'Despesas';
        $sidebarActive = 'despesas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        extract($data);
        require __DIR__ . '/../Views/admin/despesas/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function criar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $body = $request->getBody();
        if (empty($body)) $body = $_POST;

        $tipo = $body['tipo'] ?? 'avulsa';

        if ($tipo === 'recorrente') {
            return $this->criarRecorrencia($body);
        }
        if ($tipo === 'parcelada') {
            return $this->criarParcelamento($body);
        }

        $stmt = $this->db->prepare("INSERT INTO despesas (descricao, categoria_id, tipo, valor, moeda, competencia, vencimento, status, forma_pagamento, favorecido, observacoes, origem, criado_por) VALUES (:desc, :cat, :tipo, :valor, :moeda, :comp, :venc, :status, :fp, :fav, :obs, 'manual', :uid)");
        $stmt->execute([
            ':desc' => $body['descricao'] ?? '',
            ':cat' => !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null,
            ':tipo' => $tipo,
            ':valor' => (float)($body['valor'] ?? 0),
            ':moeda' => $body['moeda'] ?? 'BRL',
            ':comp' => !empty($body['competencia']) ? $body['competencia'] . '-01' : date('Y-m-01'),
            ':venc' => $body['vencimento'] ?? null,
            ':status' => $body['status'] ?? 'prevista',
            ':fp' => $body['forma_pagamento'] ?? null,
            ':fav' => $body['favorecido'] ?? null,
            ':obs' => $body['observacoes'] ?? null,
            ':uid' => $_SESSION['usuario_id'] ?? null,
        ]);

        $_SESSION['message'] = 'Despesa criada com sucesso.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
    }

    public function marcarPaga(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $stmt = $this->db->prepare("UPDATE despesas SET status = 'paga', data_pagamento = CURDATE(), updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id]);

        // Atualizar parcelamento se for parcela
        $stP = $this->db->prepare("SELECT parcelamento_id FROM despesas WHERE id = ?");
        $stP->execute([(int)$id]);
        $parcId = (int)($stP->fetchColumn() ?: 0);
        if ($parcId > 0) {
            $this->db->prepare("UPDATE despesa_parcelamentos SET parcelas_pagas = (SELECT COUNT(*) FROM despesas WHERE parcelamento_id = ? AND status = 'paga'), saldo_restante = valor_total - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE parcelamento_id = ? AND status = 'paga') WHERE id = ?")->execute([$parcId, $parcId, $parcId]);
            // Verificar se quitou
            $stQ = $this->db->prepare("SELECT quantidade_parcelas, parcelas_pagas FROM despesa_parcelamentos WHERE id = ?");
            $stQ->execute([$parcId]);
            $parc = $stQ->fetch(\PDO::FETCH_ASSOC);
            if ($parc && (int)$parc['parcelas_pagas'] >= (int)$parc['quantidade_parcelas']) {
                $this->db->prepare("UPDATE despesa_parcelamentos SET status = 'quitado' WHERE id = ?")->execute([$parcId]);
            }
        }

        $_SESSION['message'] = 'Despesa marcada como paga.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
    }

    public function cancelar(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $this->db->prepare("UPDATE despesas SET status = 'cancelada', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([(int)$id]);

        $_SESSION['message'] = 'Despesa cancelada.';
        $_SESSION['message_type'] = 'warning';
        $this->redirect('/admin/despesas?tab=todas');
    }

    public function excluir(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $this->db->prepare("UPDATE despesas SET deleted_at = NOW() WHERE id = ?")->execute([(int)$id]);

        $_SESSION['message'] = 'Despesa excluída.';
        $_SESSION['message_type'] = 'info';
        $this->redirect('/admin/despesas?tab=todas');
    }

    // === PRIVATE ===

    private function criarRecorrencia(array $body) {
        $stmt = $this->db->prepare("INSERT INTO despesa_recorrencias (descricao, categoria_id, valor, moeda, frequencia, dia_vencimento, forma_pagamento, favorecido, data_inicio, data_fim, observacoes, proxima_geracao, criado_por) VALUES (:desc, :cat, :valor, :moeda, :freq, :dia, :fp, :fav, :inicio, :fim, :obs, :prox, :uid)");
        $dataInicio = $body['data_inicio'] ?? date('Y-m-d');
        $dia = (int)($body['dia_vencimento'] ?? 1);
        $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT));
        if ($proxima < date('Y-m-d')) $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime('+1 month'));

        $stmt->execute([
            ':desc' => $body['descricao'] ?? '',
            ':cat' => !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null,
            ':valor' => (float)($body['valor'] ?? 0),
            ':moeda' => $body['moeda'] ?? 'BRL',
            ':freq' => $body['frequencia'] ?? 'mensal',
            ':dia' => $dia,
            ':fp' => $body['forma_pagamento'] ?? null,
            ':fav' => $body['favorecido'] ?? null,
            ':inicio' => $dataInicio,
            ':fim' => !empty($body['data_fim']) ? $body['data_fim'] : null,
            ':obs' => $body['observacoes'] ?? null,
            ':prox' => $proxima,
            ':uid' => $_SESSION['usuario_id'] ?? null,
        ]);

        $_SESSION['message'] = 'Recorrência criada com sucesso.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=recorrentes');
    }

    private function criarParcelamento(array $body) {
        $valorTotal = (float)($body['valor_total'] ?? 0);
        $qtdParcelas = max(1, (int)($body['quantidade_parcelas'] ?? 1));
        $valorParcela = round($valorTotal / $qtdParcelas, 2);
        $dataPrimeira = $body['data_primeira_parcela'] ?? date('Y-m-d');

        $stmt = $this->db->prepare("INSERT INTO despesa_parcelamentos (descricao, categoria_id, valor_total, quantidade_parcelas, valor_parcela, moeda, data_primeira_parcela, forma_pagamento, favorecido, saldo_restante, observacoes, criado_por) VALUES (:desc, :cat, :vt, :qp, :vp, :moeda, :dp, :fp, :fav, :sr, :obs, :uid)");
        $stmt->execute([
            ':desc' => $body['descricao'] ?? '',
            ':cat' => !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null,
            ':vt' => $valorTotal,
            ':qp' => $qtdParcelas,
            ':vp' => $valorParcela,
            ':moeda' => $body['moeda'] ?? 'BRL',
            ':dp' => $dataPrimeira,
            ':fp' => $body['forma_pagamento'] ?? null,
            ':fav' => $body['favorecido'] ?? null,
            ':sr' => $valorTotal,
            ':obs' => $body['observacoes'] ?? null,
            ':uid' => $_SESSION['usuario_id'] ?? null,
        ]);
        $parcId = (int)$this->db->lastInsertId();

        // Gerar parcelas
        for ($i = 1; $i <= $qtdParcelas; $i++) {
            $venc = date('Y-m-d', strtotime($dataPrimeira . ' +' . ($i - 1) . ' months'));
            $valor = ($i === $qtdParcelas) ? round($valorTotal - ($valorParcela * ($qtdParcelas - 1)), 2) : $valorParcela;
            $comp = date('Y-m-01', strtotime($venc));

            $this->db->prepare("INSERT INTO despesas (descricao, categoria_id, tipo, valor, moeda, competencia, vencimento, status, forma_pagamento, favorecido, parcelamento_id, parcela_numero, origem, criado_por) VALUES (:desc, :cat, 'parcelada', :valor, :moeda, :comp, :venc, 'prevista', :fp, :fav, :pid, :pn, 'parcelamento', :uid)")->execute([
                ':desc' => ($body['descricao'] ?? '') . " ({$i}/{$qtdParcelas})",
                ':cat' => !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null,
                ':valor' => $valor,
                ':moeda' => $body['moeda'] ?? 'BRL',
                ':comp' => $comp,
                ':venc' => $venc,
                ':fp' => $body['forma_pagamento'] ?? null,
                ':fav' => $body['favorecido'] ?? null,
                ':pid' => $parcId,
                ':pn' => $i,
                ':uid' => $_SESSION['usuario_id'] ?? null,
            ]);
        }

        $_SESSION['message'] = "Parcelamento criado com {$qtdParcelas} parcelas.";
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=parceladas');
    }

    private function getStats(array $filtros): array {
        $mesAtual = date('Y-m-01');
        $mesFim = date('Y-m-t');
        $stats = ['total_mes' => 0, 'pago_mes' => 0, 'aberto' => 0, 'vencido' => 0, 'proximos_30' => 0, 'comissoes' => 0, 'qtd_aberto' => 0, 'qtd_vencido' => 0, 'qtd_proximos' => 0, 'qtd_comissoes' => 0];

        try {
            $st = $this->db->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE competencia >= ? AND competencia <= ? AND status != 'cancelada' AND deleted_at IS NULL");
            $st->execute([$mesAtual, $mesFim]);
            $stats['total_mes'] = (float)$st->fetchColumn();
        } catch (\Exception $e) {}

        try {
            $st = $this->db->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status = 'paga' AND data_pagamento >= ? AND data_pagamento <= ? AND deleted_at IS NULL");
            $st->execute([$mesAtual, $mesFim]);
            $stats['pago_mes'] = (float)$st->fetchColumn();
        } catch (\Exception $e) {}

        try {
            $st = $this->db->query("SELECT COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE status IN ('prevista','a_vencer') AND deleted_at IS NULL");
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            $stats['aberto'] = (float)($r['total'] ?? 0);
            $stats['qtd_aberto'] = (int)($r['qtd'] ?? 0);
        } catch (\Exception $e) {}

        try {
            $st = $this->db->query("SELECT COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE status = 'vencida' AND deleted_at IS NULL");
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            $stats['vencido'] = (float)($r['total'] ?? 0);
            $stats['qtd_vencido'] = (int)($r['qtd'] ?? 0);
        } catch (\Exception $e) {}

        try {
            $prox30 = date('Y-m-d', strtotime('+30 days'));
            $st = $this->db->prepare("SELECT COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE vencimento BETWEEN CURDATE() AND ? AND status IN ('prevista','a_vencer') AND deleted_at IS NULL");
            $st->execute([$prox30]);
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            $stats['proximos_30'] = (float)($r['total'] ?? 0);
            $stats['qtd_proximos'] = (int)($r['qtd'] ?? 0);
        } catch (\Exception $e) {}

        try {
            $st = $this->db->prepare("SELECT COALESCE(SUM(valor),0) as total, COUNT(*) as qtd FROM despesas WHERE tipo = 'comissao' AND status IN ('prevista','a_vencer') AND deleted_at IS NULL");
            $st->execute();
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            $stats['comissoes'] = (float)($r['total'] ?? 0);
            $stats['qtd_comissoes'] = (int)($r['qtd'] ?? 0);
        } catch (\Exception $e) {}

        return $stats;
    }

    private function listarDespesas(array $filtros): array {
        $where = ['d.deleted_at IS NULL'];
        $params = [];

        if (!empty($filtros['status'])) {
            $where[] = 'd.status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['categoria'])) {
            $where[] = 'd.categoria_id = :cat';
            $params[':cat'] = (int)$filtros['categoria'];
        }
        if (!empty($filtros['tipo'])) {
            $where[] = 'd.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['forma_pagamento'])) {
            $where[] = 'd.forma_pagamento = :fp';
            $params[':fp'] = $filtros['forma_pagamento'];
        }
        if (!empty($filtros['busca'])) {
            $where[] = '(d.descricao LIKE :busca OR d.favorecido LIKE :busca2)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
            $params[':busca2'] = '%' . $filtros['busca'] . '%';
        }

        // Filtros rápidos
        $rapido = $filtros['rapido'] ?? '';
        if ($rapido === 'vencidas') $where[] = "d.status = 'vencida'";
        elseif ($rapido === 'hoje') $where[] = "d.vencimento = CURDATE() AND d.status != 'paga'";
        elseif ($rapido === '7dias') $where[] = "d.vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND d.status IN ('prevista','a_vencer')";
        elseif ($rapido === 'pagas') $where[] = "d.status = 'paga'";
        elseif ($rapido === 'fixas') $where[] = "d.tipo = 'fixa'";
        elseif ($rapido === 'parcelas') $where[] = "d.tipo = 'parcelada' AND d.status != 'paga'";
        elseif ($rapido === 'comissoes') $where[] = "d.tipo = 'comissao'";

        if (!empty($filtros['competencia_de']) && empty($rapido)) {
            $where[] = 'd.competencia >= :comp_de';
            $params[':comp_de'] = $filtros['competencia_de'];
        }
        if (!empty($filtros['competencia_ate']) && empty($rapido)) {
            $where[] = 'd.competencia <= :comp_ate';
            $params[':comp_ate'] = $filtros['competencia_ate'] . '-31';
        }

        $sql = "SELECT d.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE " . implode(' AND ', $where) . " ORDER BY d.vencimento ASC, d.created_at DESC LIMIT 200";
        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { return []; }
    }

    private function getComissoes(array $filtros): array {
        // Puxar comissões da tabela despesas com tipo = comissao
        try {
            $st = $this->db->query("SELECT d.*, c.nome as categoria_nome FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE d.tipo = 'comissao' AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 100");
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { return []; }
    }

    private function ensureTables() {
        try {
            $st = $this->db->query("SELECT 1 FROM despesas LIMIT 1");
        } catch (\Exception $e) {
            // Tabela não existe — criar schema
            $sqlFile = __DIR__ . '/../../database/migrations/160_create_despesas_schema.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if ($stmt !== '' && stripos($stmt, '--') !== 0) {
                        try { $this->db->exec($stmt); } catch (\Exception $ex) {}
                    }
                }
            }
        }
    }

    private function exportCSV(array $despesas): void {
        $filename = 'despesas_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        // BOM UTF-8
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        // Header
        fputcsv($out, ['ID', 'Descrição', 'Categoria', 'Tipo', 'Competência', 'Vencimento', 'Pagamento', 'Valor', 'Moeda', 'Status', 'Forma Pagamento', 'Favorecido', 'Origem'], ';');
        foreach ($despesas as $d) {
            fputcsv($out, [
                $d['id'] ?? '',
                $d['descricao'] ?? '',
                $d['categoria_nome'] ?? '',
                $d['tipo'] ?? '',
                $d['competencia'] ? date('m/Y', strtotime($d['competencia'])) : '',
                $d['vencimento'] ? date('d/m/Y', strtotime($d['vencimento'])) : '',
                $d['data_pagamento'] ? date('d/m/Y', strtotime($d['data_pagamento'])) : '',
                number_format((float)($d['valor'] ?? 0), 2, ',', ''),
                $d['moeda'] ?? 'BRL',
                ucfirst(str_replace('_', ' ', $d['status'] ?? '')),
                $d['forma_pagamento'] ?? '',
                $d['favorecido'] ?? '',
                $d['origem'] ?? 'manual',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
