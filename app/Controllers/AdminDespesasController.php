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
            'competencia_de' => $request->getParam('competencia_de', ''),
            'competencia_ate' => $request->getParam('competencia_ate', ''),
            'forma_pagamento' => $request->getParam('forma_pagamento', ''),
            'busca' => $request->getParam('busca', ''),
            'rapido' => $request->getParam('rapido', ''),
        ];

        // Na aba "todas", só aplicar filtro de data se o usuário explicitamente filtrou
        // Nas outras abas, manter o padrão do mês atual
        if ($tab !== 'todas' && $filtros['competencia_de'] === '' && $filtros['competencia_ate'] === '') {
            $filtros['competencia_de'] = date('Y-m-01');
            $filtros['competencia_ate'] = date('Y-m-t');
        }

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

        // Gerar despesas automáticas das recorrências ativas (até o mês atual)
        $this->gerarDespesasRecorrentes();

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

        // Taxa para conversão visual
        $taxaUsdBrl = 5.85;
        try { $svc = new \App\Services\PedidoManualService(); $r = $svc->getTaxaConversaoUSDBRL(); if ($r > 1) $taxaUsdBrl = $r; } catch (\Exception $e) {}
        $data['taxaUsdBrl'] = $taxaUsdBrl;

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
        if ($tipo === 'por_hora') {
            return $this->criarPorHora($body);
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

    public function editar(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $body = $_POST;

        // Garantir que temos o ID correto
        $despesaId = (int)$id;
        if ($despesaId <= 0) {
            $despesaId = (int)$request->getParam('id', 0);
        }
        if ($despesaId <= 0) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('/\/editar\/(\d+)/', $path, $m)) {
                $despesaId = (int)$m[1];
            }
        }

        if ($despesaId <= 0) {
            $_SESSION['message'] = 'ID da despesa não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $descricao = trim((string)($body['descricao'] ?? ''));
        $categoriaId = !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null;
        $valor = (float)($body['valor'] ?? 0);
        $moeda = $body['moeda'] ?? 'BRL';
        $competencia = !empty($body['competencia']) ? $body['competencia'] . '-01' : null;
        $vencimento = !empty($body['vencimento']) ? $body['vencimento'] : null;
        $status = $body['status'] ?? 'prevista';
        $formaPagamento = !empty($body['forma_pagamento']) ? $body['forma_pagamento'] : null;
        $favorecido = !empty($body['favorecido']) ? $body['favorecido'] : null;
        $observacoes = !empty($body['observacoes']) ? $body['observacoes'] : null;

        if (empty($descricao)) {
            $_SESSION['message'] = 'Descrição não pode ser vazia.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $stmt = $this->db->prepare("UPDATE despesas SET descricao = ?, categoria_id = ?, valor = ?, moeda = ?, competencia = ?, vencimento = ?, status = ?, forma_pagamento = ?, favorecido = ?, observacoes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $descricao,
            $categoriaId,
            $valor,
            $moeda,
            $competencia,
            $vencimento,
            $status,
            $formaPagamento,
            $favorecido,
            $observacoes,
            $despesaId,
        ]);

        $_SESSION['message'] = 'Despesa atualizada com sucesso.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
    }

    public function editarRecorrencia(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();

        $body = $_POST;

        $recId = (int)$id;
        if ($recId <= 0) {
            $recId = (int)$request->getParam('id', 0);
        }
        if ($recId <= 0) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('/\/editar-recorrencia\/(\d+)/', $path, $m)) {
                $recId = (int)$m[1];
            }
        }

        if ($recId <= 0) {
            $_SESSION['message'] = 'ID da recorrência não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $descricao = trim((string)($body['descricao'] ?? ''));
        $categoriaId = !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null;
        $valor = (float)($body['valor'] ?? 0);
        $moeda = $body['moeda'] ?? 'BRL';
        $formaPagamento = !empty($body['forma_pagamento']) ? $body['forma_pagamento'] : null;
        $favorecido = !empty($body['favorecido']) ? $body['favorecido'] : null;

        if (empty($descricao)) {
            $_SESSION['message'] = 'Descrição não pode ser vazia.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $stmt = $this->db->prepare("UPDATE despesa_recorrencias SET descricao = ?, categoria_id = ?, valor = ?, moeda = ?, forma_pagamento = ?, favorecido = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $descricao,
            $categoriaId,
            $valor,
            $moeda,
            $formaPagamento,
            $favorecido,
            $recId,
        ]);

        $_SESSION['message'] = 'Recorrência atualizada com sucesso.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
    }

    public function pagarRecorrencia(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();
        $this->materializarRecorrencia((int)$id, 'paga');
    }

    public function cancelarRecorrencia(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTables();
        $this->materializarRecorrencia((int)$id, 'cancelada');
    }

    private function materializarRecorrencia(int $recId, string $status): void {
        // Buscar dados da recorrência
        $st = $this->db->prepare("SELECT * FROM despesa_recorrencias WHERE id = ? AND ativa = 1");
        $st->execute([$recId]);
        $rec = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$rec) {
            $_SESSION['message'] = 'Recorrência não encontrada.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $vencimento = $rec['proxima_geracao'] ?? date('Y-m-d');
        $competencia = date('Y-m-01', strtotime($vencimento));

        // Criar despesa real na tabela despesas
        $stmt = $this->db->prepare("INSERT INTO despesas (descricao, categoria_id, tipo, valor, moeda, competencia, vencimento, status, forma_pagamento, favorecido, recorrencia_id, origem, data_pagamento, criado_por) VALUES (?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, ?, 'recorrencia', ?, ?)");
        $stmt->execute([
            $rec['descricao'],
            $rec['categoria_id'],
            (float)$rec['valor'],
            $rec['moeda'] ?? 'BRL',
            $competencia,
            $vencimento,
            $status,
            $rec['forma_pagamento'],
            $rec['favorecido'],
            $recId,
            $status === 'paga' ? date('Y-m-d') : null,
            $_SESSION['usuario_id'] ?? null,
        ]);

        // Avançar proxima_geracao para o próximo período
        $freq = $rec['frequencia'] ?? 'mensal';
        $dia = (int)($rec['dia_vencimento'] ?? 1);
        $proxima = $vencimento;
        switch ($freq) {
            case 'semanal': $proxima = date('Y-m-d', strtotime($vencimento . ' +1 week')); break;
            case 'quinzenal': $proxima = date('Y-m-d', strtotime($vencimento . ' +2 weeks')); break;
            case 'mensal': $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($vencimento . ' +1 month')); break;
            case 'anual': $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($vencimento . ' +1 year')); break;
            default: $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($vencimento . ' +1 month'));
        }

        // Verificar se atingiu data_fim
        $dataFim = $rec['data_fim'] ?? null;
        if ($dataFim && $proxima > $dataFim) {
            // Desativar recorrência
            $this->db->prepare("UPDATE despesa_recorrencias SET ativa = 0, updated_at = NOW() WHERE id = ?")->execute([$recId]);
        } else {
            $this->db->prepare("UPDATE despesa_recorrencias SET proxima_geracao = ?, updated_at = NOW() WHERE id = ?")->execute([$proxima, $recId]);
        }

        $msg = $status === 'paga' ? 'Despesa marcada como paga e próxima geração avançada.' : 'Despesa cancelada para este período.';
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
    }

    // === PRIVATE ===

    /**
     * Gera despesas reais na tabela `despesas` para todas as recorrências ativas
     * cujo `proxima_geracao` já passou ou é o mês atual.
     * Gera até o final do mês seguinte para dar previsibilidade.
     */
    private function gerarDespesasRecorrentes(): void {
        try {
            // Gerar até o final do próximo mês
            $limiteGeracao = date('Y-m-t', strtotime('+1 month'));

            $stRec = $this->db->query("SELECT * FROM despesa_recorrencias WHERE ativa = 1");
            $recorrencias = $stRec ? $stRec->fetchAll(\PDO::FETCH_ASSOC) : [];

            foreach ($recorrencias as $rec) {
                $proxima = $rec['proxima_geracao'] ?? null;
                if (!$proxima) continue;

                $dataFim = $rec['data_fim'] ?? null;
                $dia = (int)($rec['dia_vencimento'] ?? 1);
                $freq = $rec['frequencia'] ?? 'mensal';
                $recId = (int)$rec['id'];

                // Gerar despesas enquanto proxima_geracao <= limite
                $maxIteracoes = 12; // segurança contra loop infinito
                $iteracao = 0;
                while ($proxima <= $limiteGeracao && $iteracao < $maxIteracoes) {
                    $iteracao++;

                    // Verificar se atingiu data_fim
                    if ($dataFim && $proxima > $dataFim) {
                        $this->db->prepare("UPDATE despesa_recorrencias SET ativa = 0, updated_at = NOW() WHERE id = ?")->execute([$recId]);
                        break;
                    }

                    // Verificar se já existe despesa para esta recorrência neste vencimento
                    $stCheck = $this->db->prepare("SELECT COUNT(*) FROM despesas WHERE recorrencia_id = ? AND vencimento = ? AND deleted_at IS NULL");
                    $stCheck->execute([$recId, $proxima]);
                    $existe = (int)$stCheck->fetchColumn() > 0;

                    if (!$existe) {
                        $competencia = date('Y-m-01', strtotime($proxima));
                        $status = ($proxima < date('Y-m-d')) ? 'vencida' : (($proxima === date('Y-m-d')) ? 'a_vencer' : 'prevista');

                        $stIns = $this->db->prepare("INSERT INTO despesas (descricao, categoria_id, tipo, valor, moeda, competencia, vencimento, status, forma_pagamento, favorecido, recorrencia_id, origem, criado_por) VALUES (?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, ?, 'recorrencia', ?)");
                        $stIns->execute([
                            $rec['descricao'],
                            $rec['categoria_id'],
                            (float)$rec['valor'],
                            $rec['moeda'] ?? 'BRL',
                            $competencia,
                            $proxima,
                            $status,
                            $rec['forma_pagamento'],
                            $rec['favorecido'],
                            $recId,
                            $rec['criado_por'],
                        ]);
                    }

                    // Avançar para o próximo período
                    switch ($freq) {
                        case 'semanal': $proxima = date('Y-m-d', strtotime($proxima . ' +1 week')); break;
                        case 'quinzenal': $proxima = date('Y-m-d', strtotime($proxima . ' +2 weeks')); break;
                        case 'anual': $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($proxima . ' +1 year')); break;
                        default: $proxima = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($proxima . ' +1 month'));
                    }
                }

                // Atualizar proxima_geracao na recorrência
                $this->db->prepare("UPDATE despesa_recorrencias SET proxima_geracao = ?, updated_at = NOW() WHERE id = ? AND ativa = 1")->execute([$proxima, $recId]);
            }
        } catch (\Exception $e) {
            // Silenciar erros para não quebrar a listagem
        }
    }

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

    private function criarPorHora(array $body) {
        $pessoaNome = trim($body['pessoa_nome'] ?? '');
        $horasTrabalhadas = (float)($body['horas_trabalhadas'] ?? 0);
        $valorHora = (float)($body['valor_hora'] ?? 0);
        $valorTotal = round($horasTrabalhadas * $valorHora, 2);

        if (empty($pessoaNome) || $horasTrabalhadas <= 0 || $valorHora <= 0) {
            $_SESSION['message'] = 'Preencha o nome da pessoa, horas trabalhadas e valor por hora.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/despesas?tab=todas');
            return;
        }

        $descricao = ($body['descricao'] ?? '') ?: "{$pessoaNome} - {$horasTrabalhadas}h × R$ " . number_format($valorHora, 2, ',', '.');

        $stmt = $this->db->prepare("INSERT INTO despesas (descricao, categoria_id, tipo, valor, moeda, competencia, vencimento, status, forma_pagamento, favorecido, observacoes, pessoa_nome, horas_trabalhadas, valor_hora, origem, criado_por) VALUES (:desc, :cat, 'por_hora', :valor, :moeda, :comp, :venc, :status, :fp, :fav, :obs, :pessoa, :horas, :vh, 'manual', :uid)");
        $stmt->execute([
            ':desc' => $descricao,
            ':cat' => !empty($body['categoria_id']) ? (int)$body['categoria_id'] : null,
            ':valor' => $valorTotal,
            ':moeda' => $body['moeda'] ?? 'BRL',
            ':comp' => !empty($body['competencia']) ? $body['competencia'] . '-01' : date('Y-m-01'),
            ':venc' => $body['vencimento'] ?? null,
            ':status' => $body['status'] ?? 'prevista',
            ':fp' => $body['forma_pagamento'] ?? null,
            ':fav' => $pessoaNome,
            ':obs' => $body['observacoes'] ?? null,
            ':pessoa' => $pessoaNome,
            ':horas' => $horasTrabalhadas,
            ':vh' => $valorHora,
            ':uid' => $_SESSION['usuario_id'] ?? null,
        ]);

        $_SESSION['message'] = "Despesa por hora criada: {$pessoaNome} ({$horasTrabalhadas}h × R$ " . number_format($valorHora, 2, ',', '.') . " = R$ " . number_format($valorTotal, 2, ',', '.') . ")";
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/despesas?tab=todas');
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

        $sql = "SELECT d.id, d.descricao, d.categoria_id, d.tipo, d.valor, d.moeda, d.competencia, d.vencimento, d.data_pagamento, d.status, d.forma_pagamento, d.favorecido, d.observacoes, d.comprovante_path, d.recorrencia_id, d.parcelamento_id, d.parcela_numero, d.comissao_pedido_id, d.comissao_usuario_id, d.comissao_regra, d.comissao_base, d.origem, d.criado_por, d.created_at, d.updated_at, d.deleted_at, 0 as is_virtual, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone FROM despesas d LEFT JOIN despesa_categorias c ON c.id = d.categoria_id WHERE " . implode(' AND ', $where) . " ORDER BY d.vencimento ASC, d.created_at DESC LIMIT 200";
        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { return []; }
    }

    private function getComissoes(array $filtros): array {
        // Puxar comissões usando a mesma lógica do AdminComissoesGlobalController
        // Período: ciclo dia 10 → dia 9 do mês seguinte
        $periodoParam = date('Y-m');
        $compDe = $filtros['competencia_de'] ?? '';
        if ($compDe !== '' && preg_match('/^\d{4}-\d{2}/', $compDe)) {
            $periodoParam = substr($compDe, 0, 7);
        }

        [$ano, $mes] = explode('-', $periodoParam);
        $ano = (int)$ano; $mes = (int)$mes;
        $dataInicio = sprintf('%04d-%02d-10', $ano, $mes);
        $proxMes = $mes === 12 ? 1 : $mes + 1;
        $proxAno = $mes === 12 ? $ano + 1 : $ano;
        $dataFim = sprintf('%04d-%02d-09', $proxAno, $proxMes);

        // Taxa USD→BRL
        $usdToBrl = 5.85;
        try {
            $svc = new \App\Services\PedidoManualService();
            $r = $svc->getTaxaConversaoUSDBRL();
            if ($r > 1) $usdToBrl = $r;
        } catch (\Exception $e) {}

        // Detectar colunas
        $cols = [];
        try { $st = $this->db->query('DESCRIBE pedidos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        $pick = function($candidates) use ($cols) { foreach ($candidates as $c) { if (in_array($c, $cols, true)) return $c; } return ''; };
        $colTotal = $pick(['valor_total', 'total', 'amount']);
        $colImpostos = $pick(['valor_impostos', 'impostos']);
        $colMoeda = $pick(['moeda', 'currency']);
        $colCreatedAt = $pick(['created_at', 'data_criacao']);
        $colOrigem = $pick(['origem_pedido', 'origem']);
        $colStatus = $pick(['status']);
        $colPayStatus = $pick(['payment_status', 'status_pagamento']);
        $colCriadoPor = $pick(['admin_criador_id', 'criado_por', 'vendedor_id', 'created_by']);
        $colSemComissao = $pick(['sem_comissao']);

        if (!$colTotal || !$colCreatedAt) return ['vendedores' => [], 'total' => 0, 'periodo' => $periodoParam, 'dataInicio' => $dataInicio, 'dataFim' => $dataFim, 'usdToBrl' => $usdToBrl];

        // Query pedidos manuais pagos no período
        $where = [];
        $params = [];

        if ($colOrigem && $colCriadoPor) {
            $where[] = "(LOWER(COALESCE(p.{$colOrigem},'')) IN ('manual','admin') OR (p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0))";
        } elseif ($colOrigem) {
            $where[] = "LOWER(COALESCE(p.{$colOrigem},'')) IN ('manual','admin')";
        } elseif ($colCriadoPor) {
            $where[] = "(p.{$colCriadoPor} IS NOT NULL AND p.{$colCriadoPor} > 0)";
        }

        if (in_array('deleted_at', $cols, true)) $where[] = "p.deleted_at IS NULL";

        $paidParts = [];
        if ($colStatus) $paidParts[] = "LOWER(COALESCE(p.{$colStatus},'')) IN ('pago','paid','approved','carne_pagando','etiqueta_gerada','produto_consolidado','em_transporte','enviado_ao_destinatario','entregue')";
        if ($colPayStatus) $paidParts[] = "LOWER(COALESCE(p.{$colPayStatus},'')) IN ('approved','paid','pago','confirmed','succeeded','success')";
        if (!empty($paidParts)) $where[] = '(' . implode(' OR ', $paidParts) . ')';

        if ($colSemComissao) $where[] = "(p.{$colSemComissao} IS NULL OR p.{$colSemComissao} = 0)";

        $where[] = "DATE(p.{$colCreatedAt}) >= :di";
        $params[':di'] = $dataInicio;
        $where[] = "DATE(p.{$colCreatedAt}) <= :df";
        $params[':df'] = $dataFim;

        $sql = "SELECT p.id, p.{$colTotal} AS valor_total"
            . ($colImpostos ? ", p.{$colImpostos} AS impostos" : ", 0 AS impostos")
            . ($colMoeda ? ", p.{$colMoeda} AS moeda" : ", 'BRL' AS moeda")
            . ($colCriadoPor ? ", p.{$colCriadoPor} AS criado_por" : ", 0 AS criado_por")
            . " FROM pedidos p WHERE " . implode(' AND ', $where)
            . " ORDER BY p.{$colCreatedAt} DESC LIMIT 2000";

        $rows = [];
        try { $st = $this->db->prepare($sql); $st->execute($params); $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        // Custo produtos por pedido
        $custoByPedido = [];
        $itensTable = 'pedido_itens';
        try {
            $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stT->execute(['pedido_itens']);
            if ((int)$stT->fetchColumn() === 0) { $stT->execute(['pedido_items']); if ((int)$stT->fetchColumn() > 0) $itensTable = 'pedido_items'; else $itensTable = ''; }
        } catch (\Exception $e) { $itensTable = ''; }

        if ($itensTable && !empty($rows)) {
            $ids = array_column($rows, 'id');
            $chunks = array_chunk($ids, 500);
            $colsIt = [];
            try { $st = $this->db->query("DESCRIBE {$itensTable}"); $colsIt = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $colCusto = in_array('custo_unitario', $colsIt, true) ? 'custo_unitario' : (in_array('cost', $colsIt, true) ? 'cost' : '');
            $colQtd = in_array('quantidade', $colsIt, true) ? 'quantidade' : (in_array('qty', $colsIt, true) ? 'qty' : '');
            if ($colCusto && $colQtd) {
                foreach ($chunks as $chunk) {
                    $in = implode(',', array_fill(0, count($chunk), '?'));
                    try {
                        $st = $this->db->prepare("SELECT pedido_id, SUM({$colCusto} * {$colQtd}) AS custo FROM {$itensTable} WHERE pedido_id IN ({$in}) GROUP BY pedido_id");
                        $st->execute($chunk);
                        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) { $custoByPedido[(int)$r['pedido_id']] = (float)($r['custo'] ?? 0); }
                    } catch (\Exception $e) {}
                }
            }
        }

        // Comissões de processamento
        $comProc = [];
        try {
            $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'comissoes_processamento'");
            $stT->execute();
            if ((int)($stT->fetchColumn() ?: 0) > 0) {
                $sqlP = "SELECT usuario_id, moeda, SUM(valor_comissao) AS total_comissao FROM comissoes_processamento WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY usuario_id, moeda";
                $stP = $this->db->prepare($sqlP);
                $stP->execute([$dataInicio, $dataFim]);
                foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $uid = (int)($r['usuario_id'] ?? 0);
                    $com = (float)($r['total_comissao'] ?? 0);
                    if (strtoupper(trim($r['moeda'] ?? '')) === 'USD') $com *= $usdToBrl;
                    $comProc[$uid] = ($comProc[$uid] ?? 0.0) + $com;
                }
            }
        } catch (\Exception $e) {}

        // Faixas de comissão
        $faixas = [['min' => 0, 'max' => 999999999, 'percent' => 5]];
        try {
            $stF = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'comissao_manual_faixas' LIMIT 1");
            $stF->execute();
            $raw = (string)($stF->fetchColumn() ?: '');
            if ($raw !== '') { $arr = json_decode($raw, true); if (is_array($arr) && !empty($arr)) $faixas = $arr; }
        } catch (\Exception $e) {
            try {
                $stF = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'comissao' AND chave = 'manual_faixas' LIMIT 1");
                $stF->execute();
                $raw = (string)($stF->fetchColumn() ?: '');
                if ($raw !== '') { $arr = json_decode($raw, true); if (is_array($arr) && !empty($arr)) $faixas = $arr; }
            } catch (\Exception $e2) {}
        }

        // Agrupar por vendedor
        $porVendedor = [];
        foreach ($rows as $r) {
            $uid = (int)($r['criado_por'] ?? 0);
            $m = strtoupper(trim($r['moeda'] ?? 'BRL'));
            if ($m === '') $m = 'BRL';
            $pid = (int)$r['id'];
            $fat = (float)($r['valor_total'] ?? 0);
            $imp = (float)($r['impostos'] ?? 0);
            $custo = (float)($custoByPedido[$pid] ?? 0);
            $liq = $fat - $custo - $imp;
            if ($m === 'USD') { $fat *= $usdToBrl; $imp *= $usdToBrl; $custo *= $usdToBrl; $liq *= $usdToBrl; }

            if (!isset($porVendedor[$uid])) $porVendedor[$uid] = ['faturado' => 0, 'impostos' => 0, 'custo' => 0, 'liquido' => 0, 'qtd' => 0];
            $porVendedor[$uid]['faturado'] += $fat;
            $porVendedor[$uid]['impostos'] += $imp;
            $porVendedor[$uid]['custo'] += $custo;
            $porVendedor[$uid]['liquido'] += $liq;
            $porVendedor[$uid]['qtd']++;
        }

        // Nomes
        $nomes = [];
        $uids = array_unique(array_merge(array_keys($porVendedor), array_keys($comProc)));
        if (!empty($uids)) {
            $in = implode(',', array_fill(0, count($uids), '?'));
            try { $st = $this->db->prepare("SELECT id, nome, email FROM usuarios WHERE id IN ({$in})"); $st->execute(array_values($uids)); foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $u) { $nomes[(int)$u['id']] = ['nome' => $u['nome'] ?? '', 'email' => $u['email'] ?? '']; } } catch (\Exception $e) {}
        }

        // Montar resultado
        $vendedores = [];
        $grandTotal = 0;
        $allUids = array_unique(array_merge(array_keys($porVendedor), array_keys($comProc)));
        foreach ($allUids as $uid) {
            if ($uid <= 0) continue;
            $t = $porVendedor[$uid] ?? ['faturado' => 0, 'impostos' => 0, 'custo' => 0, 'liquido' => 0, 'qtd' => 0];
            $pct = 0;
            foreach ($faixas as $f) { if ($t['faturado'] >= (float)($f['min'] ?? 0) && $t['faturado'] <= (float)($f['max'] ?? PHP_FLOAT_MAX)) { $pct = (float)($f['percent'] ?? 0); break; } }
            $comManual = max(0, $t['liquido']) * ($pct / 100);
            $comProcVal = (float)($comProc[$uid] ?? 0);
            $totalCom = $comManual + $comProcVal;
            $grandTotal += $totalCom;

            $vendedores[] = [
                'uid' => $uid,
                'nome' => $nomes[$uid]['nome'] ?? 'Vendedor #' . $uid,
                'email' => $nomes[$uid]['email'] ?? '',
                'pedidos' => $t['qtd'],
                'faturado' => $t['faturado'],
                'custo' => $t['custo'],
                'impostos' => $t['impostos'],
                'liquido' => $t['liquido'],
                'percentual' => $pct,
                'comissao_manual' => $comManual,
                'comissao_proc' => $comProcVal,
                'total_comissao' => $totalCom,
            ];
        }

        usort($vendedores, function($a, $b) { return $b['total_comissao'] <=> $a['total_comissao']; });

        return [
            'vendedores' => $vendedores,
            'total' => $grandTotal,
            'periodo' => $periodoParam,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'usdToBrl' => $usdToBrl,
        ];
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

        // Garantir colunas de despesa por hora
        try {
            $this->db->query("SELECT pessoa_nome FROM despesas LIMIT 1");
        } catch (\Exception $e) {
            $sqlFile = __DIR__ . '/../../database/migrations/170_add_despesas_por_hora.sql';
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
        fputcsv($out, ['ID', 'Descrição', 'Categoria', 'Tipo', 'Competência', 'Vencimento', 'Pagamento', 'Valor', 'Moeda', 'Status', 'Forma Pagamento', 'Favorecido', 'Origem', 'Pessoa (hora)', 'Horas', 'Valor/Hora'], ';');
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
                $d['pessoa_nome'] ?? '',
                $d['horas_trabalhadas'] ?? '',
                $d['valor_hora'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
