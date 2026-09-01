<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Carne;
use App\Services\CarneService;
use Config\Database;

class AdminCarneController extends Controller {
    private $carneModel;
    private $carneService;
    private $db;

    public function __construct() {
        $this->carneModel = new Carne();
        $this->carneService = new CarneService();
        $this->db = Database::getConnection();
    }

    /**
     * Listagem de carnês (painel completo)
     */
    public function index(Request $request) {
        $filtros = [
            'status' => $request->getParam('status', ''),
            'cliente' => $request->getParam('cliente', ''),
            'pedido_id' => $request->getParam('pedido_id', ''),
            'com_atraso' => $request->getParam('com_atraso', ''),
            'liberado_compra' => $request->getParam('liberado_compra', ''),
            'liberado_envio' => $request->getParam('liberado_envio', ''),
            'tab' => $request->getParam('tab', 'carnes'),
        ];

        // Handle combined "filtro_rapido" dropdown
        $filtroRapido = $request->getParam('filtro_rapido', '');
        if ($filtroRapido === 'com_atraso') $filtros['com_atraso'] = '1';
        elseif ($filtroRapido === 'liberado_compra') $filtros['liberado_compra'] = '1';
        elseif ($filtroRapido === 'liberado_envio') $filtros['liberado_envio'] = '1';

        $carnes = $this->carneModel->listarAdmin($filtros);

        // Stats para os cards do topo
        $stats = $this->computeStats($carnes);

        // Compras internas pendentes
        $comprasPendentes = [];
        try {
            $stCp = $this->db->prepare("
                SELECT ci.*, c.pedido_id, u.nome as cliente_nome
                FROM carne_compras_internas ci
                JOIN carnes c ON c.id = ci.carne_id
                JOIN usuarios u ON u.id = c.cliente_id
                WHERE ci.status = 'aguardando_compra'
                ORDER BY ci.created_at DESC LIMIT 50
            ");
            $stCp->execute();
            $comprasPendentes = $stCp->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Envios pendentes
        $enviosPendentes = [];
        try {
            $stEn = $this->db->prepare("
                SELECT c.*, u.nome as cliente_nome, u.email as cliente_email,
                    p.status as pedido_status
                FROM carnes c
                JOIN usuarios u ON u.id = c.cliente_id
                LEFT JOIN pedidos p ON p.id = c.pedido_id
                WHERE c.status IN ('quitado','liberado_envio')
                AND c.envio_liberado = 1
                ORDER BY c.updated_at DESC LIMIT 50
            ");
            $stEn->execute();
            $enviosPendentes = $stEn->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Cobranças (parcelas vencidas/em atraso)
        $cobrancas = [];
        try {
            $stCob = $this->db->prepare("
                SELECT cp.*, c.pedido_id, c.cliente_id, u.nome as cliente_nome, u.email as cliente_email
                FROM carne_parcelas cp
                JOIN carnes c ON c.id = cp.carne_id
                JOIN usuarios u ON u.id = c.cliente_id
                LEFT JOIN pedidos p ON p.id = c.pedido_id
                WHERE cp.status IN ('vencida','em_atraso','aguardando_pagamento')
                AND cp.vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND (p.deleted_at IS NULL)
                AND p.status NOT IN ('cancelado','cancelled','deleted','lixeira','trash')
                ORDER BY cp.vencimento ASC LIMIT 50
            ");
            $stCob->execute();
            $cobrancas = $stCob->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Atividade recente (histórico)
        $atividadeRecente = [];
        try {
            $stAr = $this->db->prepare("
                SELECT ch.*, c.pedido_id, u.nome as cliente_nome
                FROM carne_historico ch
                JOIN carnes c ON c.id = ch.carne_id
                JOIN usuarios u ON u.id = c.cliente_id
                ORDER BY ch.created_at DESC LIMIT 10
            ");
            $stAr->execute();
            $atividadeRecente = $stAr->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $title = __('admin.installment.title', 'Gestão de Carnês');
        $sidebarActive = 'carnes';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/carne/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Calcula estatísticas dos carnês para os cards do dashboard
     */
    private function computeStats(array $carnes): array {
        $stats = [
            'total' => count($carnes),
            'total_financiado' => 0,
            'total_recebido' => 0,
            'total_aberto' => 0,
            'total_atraso' => 0,
            'aguardando_primeira' => 0,
            'com_atraso' => 0,
            'quitados' => 0,
            'compras_pendentes' => 0,
            'envios_pendentes' => 0,
            'vence_7_dias' => 0,
        ];

        foreach ($carnes as $c) {
            $stats['total_financiado'] += (float)($c['total_geral'] ?? 0);
            $status = $c['status'] ?? '';
            if ($status === 'aguardando_primeira_parcela') $stats['aguardando_primeira']++;
            if (in_array($status, ['com_atraso', 'inadimplente'])) $stats['com_atraso']++;
            if ($status === 'quitado') $stats['quitados']++;
            if (!empty($c['envio_liberado']) && $status !== 'encerrado') $stats['envios_pendentes']++;
        }

        // Recebido e em aberto
        try {
            $stRec = $this->db->query("SELECT COALESCE(SUM(valor_total),0) FROM carne_parcelas WHERE status = 'paga'");
            $stats['total_recebido'] = (float)($stRec->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        $stats['total_aberto'] = $stats['total_financiado'] - $stats['total_recebido'];
        if ($stats['total_aberto'] < 0) $stats['total_aberto'] = 0;

        // Atraso
        try {
            $stAt = $this->db->query("SELECT COALESCE(SUM(valor_total),0) FROM carne_parcelas WHERE status IN ('vencida','em_atraso')");
            $stats['total_atraso'] = (float)($stAt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        // Compras pendentes
        try {
            $stCp = $this->db->query("SELECT COUNT(*) FROM carne_compras_internas WHERE status = 'aguardando_compra'");
            $stats['compras_pendentes'] = (int)($stCp->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        // Vencem em 7 dias
        try {
            $stV7 = $this->db->query("SELECT COUNT(*) FROM carne_parcelas WHERE status IN ('pendente','aguardando_pagamento') AND vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
            $stats['vence_7_dias'] = (int)($stV7->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        return $stats;
    }

    /**
     * Detalhe do carnê
     */
    public function detalhes(Request $request, $id) {
        $carne = $this->carneModel->getCompleto($id);
        if (!$carne) {
            $_SESSION['message'] = __('admin.installment.not_found', 'Carnê não encontrado.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
        }

        $historico = $this->carneModel->getHistorico($id);
        $notificacoes = $this->carneModel->getNotificacoes($id);

        $stmt = $this->db->prepare("SELECT * FROM carne_compras_internas WHERE carne_id = :cid");
        $stmt->execute([':cid' => $id]);
        $compraInterna = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Carregar itens do pedido com foto do produto
        $itensPedido = [];
        $pedidoId = (int)($carne['pedido_id'] ?? 0);
        if ($pedidoId > 0) {
            try {
                $itensTable = 'pedido_itens';
                $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stT->execute(['pedido_itens']);
                if ((int)$stT->fetchColumn() === 0) {
                    $stT->execute(['pedido_items']);
                    if ((int)$stT->fetchColumn() > 0) $itensTable = 'pedido_items';
                }

                $colsItens = [];
                try { $st = $this->db->query("DESCRIBE {$itensTable}"); $colsItens = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $colProdId = in_array('produto_id', $colsItens, true) ? 'produto_id' : 'product_id';
                $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : 'qty';
                $colNome = in_array('nome_produto', $colsItens, true) ? 'nome_produto' : (in_array('product_name', $colsItens, true) ? 'product_name' : '');
                $colPreco = in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : (in_array('price', $colsItens, true) ? 'price' : '');
                $colSubtotal = in_array('subtotal', $colsItens, true) ? 'subtotal' : '';

                $colsProd = [];
                try { $st = $this->db->query("DESCRIBE produtos"); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $colProdNome = in_array('name', $colsProd, true) ? 'name' : (in_array('nome', $colsProd, true) ? 'nome' : 'name');
                $colProdFoto = in_array('foto_principal', $colsProd, true) ? 'foto_principal' : (in_array('imagem', $colsProd, true) ? 'imagem' : '');

                $selectCols = "it.{$colProdId} as produto_id, it.{$colQtd} as quantidade";
                if ($colNome) $selectCols .= ", it.{$colNome} as nome_item";
                if ($colPreco) $selectCols .= ", it.{$colPreco} as preco_unitario";
                if ($colSubtotal) $selectCols .= ", it.{$colSubtotal} as subtotal";
                $selectCols .= ", p.{$colProdNome} as produto_nome";
                if ($colProdFoto) $selectCols .= ", p.{$colProdFoto} as produto_imagem";

                $sql = "SELECT {$selectCols} FROM {$itensTable} it LEFT JOIN produtos p ON p.id = it.{$colProdId} WHERE it.pedido_id = :pid";
                $stIt = $this->db->prepare($sql);
                $stIt->execute([':pid' => $pedidoId]);
                $itensPedido = $stIt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // Fallback: buscar foto de produto_fotos se não tem
                foreach ($itensPedido as &$item) {
                    $img = trim((string)($item['produto_imagem'] ?? ''));
                    if ($img === '' && !empty($item['produto_id'])) {
                        try {
                            $stF = $this->db->prepare('SELECT nome_arquivo FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, ordem ASC LIMIT 1');
                            $stF->execute([(int)$item['produto_id']]);
                            $img = trim((string)($stF->fetchColumn() ?: ''));
                        } catch (\Exception $e) {}
                    }
                    if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') {
                        $img = '/uploads/produtos/' . $img;
                    }
                    $item['produto_imagem'] = $img;
                    $item['nome_display'] = (string)($item['nome_item'] ?? ($item['produto_nome'] ?? 'Produto'));
                }
                unset($item);
            } catch (\Exception $e) {}
        }

        // Buscar dados do pedido para conversão de moeda na view
        $pedido = [];
        if ($pedidoId > 0) {
            try {
                $stPed = $this->db->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
                $stPed->execute([$pedidoId]);
                $pedido = $stPed->fetch(\PDO::FETCH_ASSOC) ?: [];
                // Buscar taxa de conversão se não estiver no pedido
                if (empty($pedido['taxa_conversao'])) {
                    try {
                        $stTx = $this->db->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                        $stTx->execute();
                        $tx = (float) ($stTx->fetchColumn() ?: 0);
                        if ($tx > 1.01) $pedido['taxa_conversao'] = $tx;
                    } catch (\Exception $e) {}
                }
            } catch (\Exception $e) {}
        }

        require __DIR__ . '/../Views/admin/carne/detalhes.php';
    }

    /**
     * Lista de compras internas do Carnê
     */
    public function comprasInternas(Request $request) {
        $filtros = ['status' => $request->getParam('status', '')];
        $compras = $this->carneModel->listarComprasInternas($filtros);
        require __DIR__ . '/../Views/admin/carne/compras-internas.php';
    }

    /**
     * Lista de compras internas do Carnê — separada por mês
     */
    public function comprasMensal(Request $request) {
        $filtros = ['status' => $request->getParam('status', '')];
        $compras = $this->carneModel->listarComprasInternas($filtros);

        // Agrupar por mês (baseado na data de criação)
        $porMes = [];
        foreach ($compras as $ci) {
            $data = $ci['created_at'] ?? $ci['criado_em'] ?? date('Y-m-d');
            $mesKey = date('Y-m', strtotime($data));
            if (!isset($porMes[$mesKey])) $porMes[$mesKey] = [];
            $porMes[$mesKey][] = $ci;
        }
        // Ordenar meses (mais recente primeiro)
        krsort($porMes);

        $mesAtual = $request->getParam('mes', '');

        $title = __('admin.installment.purchases_monthly_title', 'Compras Carnê — Mensal');
        $sidebarActive = 'carnes';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/carne/compras-mensal.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Reemitir boleto (admin)
     */
    public function reemitirBoleto(Request $request, $parcelaId) {
        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $_SESSION['message'] = __('admin.installment.installment_not_found', 'Parcela não encontrada.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
        }

        $this->carneModel->atualizarParcela($parcelaId, [
            'reemissao_count' => $parcela['reemissao_count'] + 1,
            'status' => 'reemitida'
        ]);

        $this->carneModel->registrarHistorico($parcela['carne_id'], $parcelaId, 'boleto_reemitido',
            "Boleto reemitido pelo admin", null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = __('admin.installment.boleto_reissued', 'Boleto reemitido com sucesso.');
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$parcela['carne_id']}");
    }

    /**
     * Marcar produto como comprado internamente
     */
    public function marcarComprado(Request $request, $id) {
        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET status = 'comprado', comprado_em = NOW() WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        $stmt = $this->db->prepare("SELECT carne_id, pedido_id FROM carne_compras_internas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $rowCi = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $carneId = (int) ($rowCi['carne_id'] ?? 0);
        $pedidoId = (int) ($rowCi['pedido_id'] ?? 0);

        // Espelhar a compra na lista_compras do pedido e atualizar o status do pedido.
        // (Fluxo isolado do carnê — o fluxo normal de compras ignora pedidos de carnê de proposito.)
        $this->marcarListaComprasCarne($pedidoId, 'comprado');
        $this->atualizarStatusPedidoCarne($pedidoId);

        $this->carneModel->registrarHistorico($carneId, null, 'produto_comprado',
            'Produto marcado como comprado internamente', null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = __('admin.installment.product_marked_bought', 'Produto marcado como comprado.');
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * Marca (ou reverte) os itens do pedido de carnê na lista_compras.
     * $novoStatus: 'comprado' ou 'pendente'.
     *
     * Como um pedido de carnê é integralmente de carnê (todos os itens pertencem ao
     * mesmo carnê), quando confirmamos que o pedido é de carnê tratamos TODOS os
     * registros da lista_compras daquele pedido — independentemente de tipo_compra —
     * para cobrir também itens inseridos por fluxos legados sem a marca 'carne'.
     * Se não for possível confirmar que é carnê, restringe a tipo_compra = 'carne'.
     */
    private function marcarListaComprasCarne(int $pedidoId, string $novoStatus): void {
        if ($pedidoId <= 0) {
            return;
        }
        if (!in_array($novoStatus, ['comprado', 'pendente'], true)) {
            return;
        }

        try {
            if (!$this->carneTableExists('lista_compras')) {
                return;
            }
            $temTipoCompra = $this->carneColumnExists('lista_compras', 'tipo_compra');
            $temQtdFaltante = $this->carneColumnExists('lista_compras', 'quantidade_faltante');

            // Pedido de carnê: abrange o pedido inteiro. Caso contrário, restringe por tipo_compra.
            $ehCarne = $this->pedidoEhCarne($pedidoId);
            $whereTipo = ($ehCarne || !$temTipoCompra) ? '' : " AND tipo_compra = 'carne'";
            // Excluir itens virtuais de pacote/redirecionamento (produto_id >= 999990),
            // que nunca são "comprados" — mesmo guard usado no fluxo normal de compras.
            $excluiVirtuais = " AND (produto_id IS NULL OR produto_id < 999990)";

            if ($novoStatus === 'comprado') {
                $setQtd = $temQtdFaltante ? ", quantidade_faltante = 0" : '';
                $sql = "UPDATE lista_compras SET status = 'comprado'{$setQtd}
                        WHERE pedido_id = :pid AND status = 'pendente'" . $whereTipo . $excluiVirtuais;
                $st = $this->db->prepare($sql);
                $st->execute([':pid' => $pedidoId]);

                // Caso legado: se o pedido não tem NENHUM item na lista_compras
                // (compra liberada antes da inserção automática), criar os itens já como comprados
                // a partir de pedido_itens — assim o status do pedido pode ser recalculado.
                $stCnt = $this->db->prepare("SELECT COUNT(*) FROM lista_compras WHERE pedido_id = :pid" . $whereTipo . $excluiVirtuais);
                $stCnt->execute([':pid' => $pedidoId]);
                if ((int) $stCnt->fetchColumn() === 0) {
                    $this->inserirItensCarneComprados($pedidoId, $temTipoCompra, $temQtdFaltante);
                }
            } else {
                // Reverter para pendente: restaura quantidade_faltante = quantidade_necessaria quando possivel
                $setQtd = $temQtdFaltante ? ", quantidade_faltante = COALESCE(NULLIF(quantidade_necessaria,0), quantidade_faltante, 1)" : '';
                $sql = "UPDATE lista_compras SET status = 'pendente'{$setQtd}
                        WHERE pedido_id = :pid AND status = 'comprado'" . $whereTipo . $excluiVirtuais;
                $st = $this->db->prepare($sql);
                $st->execute([':pid' => $pedidoId]);
            }
        } catch (\Exception $e) {
            error_log('[CarneCompras] Erro ao sincronizar lista_compras (pedido ' . $pedidoId . '): ' . $e->getMessage());
        }
    }

    /**
     * Confirma se um pedido é de carnê (forma_pagamento = carne_braziliana OU existe
     * registro em carnes para o pedido). Usado para decidir o escopo da sincronização.
     */
    private function pedidoEhCarne(int $pedidoId): bool {
        if ($pedidoId <= 0) {
            return false;
        }
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM carnes WHERE pedido_id = :pid");
            $st->execute([':pid' => $pedidoId]);
            if ((int) $st->fetchColumn() > 0) {
                return true;
            }
            $st2 = $this->db->prepare("SELECT LOWER(COALESCE(forma_pagamento,'')) FROM pedidos WHERE id = :pid LIMIT 1");
            $st2->execute([':pid' => $pedidoId]);
            return strtolower(trim((string) ($st2->fetchColumn() ?: ''))) === 'carne_braziliana';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Insere os itens do pedido na lista_compras já como 'comprado' (para casos legados
     * onde a compra do carnê foi concluída sem os itens terem sido registrados na lista).
     */
    private function inserirItensCarneComprados(int $pedidoId, bool $temTipoCompra, bool $temQtdFaltante): void {
        if ($pedidoId <= 0) {
            return;
        }
        try {
            $stItens = $this->db->prepare("SELECT produto_id, quantidade FROM pedido_itens WHERE pedido_id = ? AND produto_id IS NOT NULL AND produto_id > 0 AND produto_id < 999990");
            $stItens->execute([$pedidoId]);
            $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($itens)) {
                return;
            }

            $temPedidoId = $this->carneColumnExists('lista_compras', 'pedido_id');
            $temDataSolic = $this->carneColumnExists('lista_compras', 'data_solicitacao');

            foreach ($itens as $item) {
                $prodId = (int) ($item['produto_id'] ?? 0);
                $qtd = (int) ($item['quantidade'] ?? 1);
                if ($prodId <= 0 || $qtd <= 0) continue;

                // Evitar duplicata: só insere se ainda não houver registro para este produto/pedido.
                $sqlCheck = "SELECT COUNT(*) FROM lista_compras WHERE produto_id = ?";
                $paramsCheck = [$prodId];
                if ($temPedidoId) { $sqlCheck .= " AND pedido_id = ?"; $paramsCheck[] = $pedidoId; }
                $stCheck = $this->db->prepare($sqlCheck);
                $stCheck->execute($paramsCheck);
                if ((int) $stCheck->fetchColumn() > 0) {
                    continue;
                }

                $cols = ['produto_id', 'quantidade_necessaria', 'prioridade', 'status'];
                $vals = ['?', '?', "'media'", "'comprado'"];
                $params = [$prodId, $qtd];
                if ($temQtdFaltante) { $cols[] = 'quantidade_faltante'; $vals[] = '0'; }
                if ($temPedidoId) { $cols[] = 'pedido_id'; $vals[] = '?'; $params[] = $pedidoId; }
                if ($temTipoCompra) { $cols[] = 'tipo_compra'; $vals[] = "'carne'"; }
                if ($temDataSolic) { $cols[] = 'data_solicitacao'; $vals[] = 'CURDATE()'; }

                $sqlIns = 'INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
                $this->db->prepare($sqlIns)->execute($params);
            }
        } catch (\Exception $e) {
            error_log('[CarneCompras] Erro ao inserir itens comprados (pedido ' . $pedidoId . '): ' . $e->getMessage());
        }
    }

    /**
     * Recalcula o status do pedido de carnê com base na lista_compras.
     * - Nenhum item pendente  -> 'itens_comprados'
     * - Alguns pendentes      -> 'itens_parcialmente_comprados'
     * - Todos pendentes       -> 'pago' (reverte, sem rebaixar estagios posteriores)
     * Nunca rebaixa pedidos que ja avancaram (em_transporte, entregue, etc.).
     */
    private function atualizarStatusPedidoCarne(int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

        try {
            // Status atual do pedido
            $stCur = $this->db->prepare("SELECT status FROM pedidos WHERE id = :pid LIMIT 1");
            $stCur->execute([':pid' => $pedidoId]);
            $statusAtual = strtolower(trim((string) ($stCur->fetchColumn() ?: '')));

            // Só mexe se o pedido estiver em um dos estados da etapa de compra.
            // Para carnê, o pedido fica em 'carne_pagando'/'carne_aguardando' durante a compra
            // (a compra é liberada já na 1ª parcela paga, antes de quitar). 'pago' também
            // é aceito para o caso de carnê quitado. Nunca rebaixa estágios posteriores
            // (produto_consolidado, em_transporte, entregue, etc.).
            $statusEditaveis = ['carne_pagando', 'carne_aguardando', 'pago', 'itens_parcialmente_comprados', 'itens_comprados'];
            if (!in_array($statusAtual, $statusEditaveis, true)) {
                return;
            }

            if (!$this->carneTableExists('lista_compras')) {
                return;
            }

            $temTipoCompra = $this->carneColumnExists('lista_compras', 'tipo_compra');
            // Pedido de carnê abrange o pedido inteiro (todos os itens são do carnê);
            // caso contrário restringe por tipo_compra = 'carne'.
            $ehCarne = $this->pedidoEhCarne($pedidoId);
            $whereTipo = ($ehCarne || !$temTipoCompra) ? '' : " AND tipo_compra = 'carne'";
            // Excluir itens virtuais de pacote/redirecionamento (produto_id >= 999990) da contagem.
            $excluiVirtuais = " AND (produto_id IS NULL OR produto_id < 999990)";

            // Total de itens na lista e quantos ainda estao pendentes.
            $stTot = $this->db->prepare("SELECT COUNT(*) FROM lista_compras WHERE pedido_id = :pid" . $whereTipo . $excluiVirtuais);
            $stTot->execute([':pid' => $pedidoId]);
            $totalItens = (int) $stTot->fetchColumn();

            if ($totalItens <= 0) {
                return;
            }

            $stPend = $this->db->prepare("SELECT COUNT(*) FROM lista_compras WHERE pedido_id = :pid AND status = 'pendente'" . $whereTipo . $excluiVirtuais);
            $stPend->execute([':pid' => $pedidoId]);
            $pendentes = (int) $stPend->fetchColumn();

            if ($pendentes <= 0) {
                $novoStatus = 'itens_comprados';
            } elseif ($pendentes < $totalItens) {
                $novoStatus = 'itens_parcialmente_comprados';
            } else {
                // Tudo pendente novamente (ex.: após desfazer): volta ao estado anterior
                // à compra. Para carnê, esse estado é 'carne_pagando' (ou 'pago' se quitado).
                $novoStatus = $this->statusPreCompraDoPedido($pedidoId);
            }

            if ($novoStatus !== $statusAtual) {
                $stUp = $this->db->prepare("UPDATE pedidos SET status = :st WHERE id = :pid LIMIT 1");
                $stUp->execute([':st' => $novoStatus, ':pid' => $pedidoId]);
            }
        } catch (\Exception $e) {
            error_log('[CarneCompras] Erro ao atualizar status do pedido ' . $pedidoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Determina o status "pré-compra" de um pedido de carnê (usado ao desfazer a compra).
     * Carnê quitado (todas as parcelas pagas) -> 'pago'; caso contrário -> 'carne_pagando'.
     * Para pedidos que não são de carnê, retorna 'pago'.
     */
    private function statusPreCompraDoPedido(int $pedidoId): string {
        try {
            $st = $this->db->prepare("SELECT forma_pagamento FROM pedidos WHERE id = :pid LIMIT 1");
            $st->execute([':pid' => $pedidoId]);
            $formaPag = strtolower(trim((string) ($st->fetchColumn() ?: '')));

            $stC = $this->db->prepare("SELECT id, quantidade_parcelas FROM carnes WHERE pedido_id = :pid LIMIT 1");
            $stC->execute([':pid' => $pedidoId]);
            $carne = $stC->fetch(\PDO::FETCH_ASSOC) ?: [];

            $ehCarne = ($formaPag === 'carne_braziliana') || !empty($carne);
            if (!$ehCarne) {
                return 'pago';
            }

            $carneId = (int) ($carne['id'] ?? 0);
            $totalParcelas = (int) ($carne['quantidade_parcelas'] ?? 0);
            $parcelasPagas = 0;
            if ($carneId > 0) {
                $stP = $this->db->prepare("SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = :cid AND status = 'paga'");
                $stP->execute([':cid' => $carneId]);
                $parcelasPagas = (int) $stP->fetchColumn();
            }

            if ($totalParcelas > 0 && $parcelasPagas >= $totalParcelas) {
                return 'pago';
            }
            return 'carne_pagando';
        } catch (\Exception $e) {
            return 'carne_pagando';
        }
    }

    /**
     * Reconcilia pedidos de carnê já marcados como comprados/recebidos em
     * carne_compras_internas cujo pedido/lista_compras ainda não refletem a compra.
     * Corrige casos anteriores à implementação da sincronização automática.
     * Idempotente e seguro: só age sobre pedidos ainda em etapa de compra.
     */
    private function reconciliarStatusPedidosCarne(): void {
        try {
            if (!$this->carneTableExists('carne_compras_internas') || !$this->carneTableExists('lista_compras')) {
                return;
            }

            // Pedidos de carnê marcados como comprados/recebidos internamente,
            // mas com pedido ainda na etapa de compra (status não sincronizado).
            // Inclui os status próprios do carnê (carne_pagando/carne_aguardando).
            $sql = "SELECT DISTINCT ci.pedido_id
                    FROM carne_compras_internas ci
                    INNER JOIN pedidos p ON p.id = ci.pedido_id
                    WHERE ci.status IN ('comprado', 'recebido')
                      AND ci.pedido_id IS NOT NULL AND ci.pedido_id > 0
                      AND p.status IN ('carne_pagando', 'carne_aguardando', 'pago', 'itens_parcialmente_comprados')";
            $st = $this->db->query($sql);
            $pedidoIds = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            foreach ($pedidoIds as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) continue;
                // Espelha a compra na lista_compras e recalcula o status do pedido.
                $this->marcarListaComprasCarne($pid, 'comprado');
                $this->atualizarStatusPedidoCarne($pid);
            }
        } catch (\Exception $e) {
            error_log('[CarneCompras] Erro na reconciliação de status: ' . $e->getMessage());
        }
    }

    /**
     * Helper local: verifica existencia de tabela (sem depender de metodos privados de outros controllers).
     */
    private function carneTableExists(string $table): bool {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $st->execute([$table]);
            return ((int) $st->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper local: verifica existencia de coluna.
     */
    private function carneColumnExists(string $table, string $column): bool {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $st->execute([$table, $column]);
            return ((int) $st->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Desfazer status da compra interna (voltar para aguardando_compra)
     */
    public function desfazerCompra(Request $request, $id) {
        $stmt = $this->db->prepare("SELECT carne_id, status FROM carne_compras_internas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $_SESSION['message'] = __('admin.installment.record_not_found', 'Registro não encontrado.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes/compras-internas');
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET status = 'aguardando_compra', comprado_em = NULL, recebido_em = NULL WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        $carneId = (int) ($row['carne_id'] ?? 0);

        // Reverter a lista_compras e o status do pedido (fluxo isolado do carnê).
        $pedidoIdDesfazer = 0;
        try {
            $stPed = $this->db->prepare("SELECT pedido_id FROM carne_compras_internas WHERE id = :id");
            $stPed->execute([':id' => $id]);
            $pedidoIdDesfazer = (int) ($stPed->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        if ($pedidoIdDesfazer <= 0 && $carneId > 0) {
            try {
                $stPed2 = $this->db->prepare("SELECT pedido_id FROM carnes WHERE id = :cid LIMIT 1");
                $stPed2->execute([':cid' => $carneId]);
                $pedidoIdDesfazer = (int) ($stPed2->fetchColumn() ?: 0);
            } catch (\Exception $e) {}
        }
        $this->marcarListaComprasCarne($pedidoIdDesfazer, 'pendente');
        $this->atualizarStatusPedidoCarne($pedidoIdDesfazer);

        $this->carneModel->registrarHistorico($carneId, null, 'compra_desfeita',
            'Status da compra interna revertido para aguardando_compra', null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = __('admin.installment.status_reverted', 'Status revertido para aguardando compra.');
        $_SESSION['message_type'] = 'success';
        $this->redirect($carneId > 0 ? "/admin/carnes/detalhes/{$carneId}" : '/admin/carnes/compras-internas');
    }

    /**
     * Marcar produto como recebido internamente
     */
    public function marcarRecebido(Request $request, $id) {
        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET status = 'recebido', recebido_em = NOW() WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        $stmt = $this->db->prepare("SELECT carne_id FROM carne_compras_internas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $carneId = $stmt->fetchColumn();

        $this->carneModel->registrarHistorico($carneId, null, 'produto_recebido',
            'Produto marcado como recebido internamente', null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = __('admin.installment.product_marked_received', 'Produto marcado como recebido.');
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * Marcar produto indisponível
     */
    public function produtoIndisponivel(Request $request, $id) {
        $acao = $request->getParam('acao', '');
        $valor = floatval($request->getParam('valor', 0));
        $obs = $request->getParam('observacoes', '');

        if ($acao === 'credito_carteira') {
            $this->carneService->gerarCreditoCarteira($id, $valor, $obs, $_SESSION['usuario_id'] ?? null);
            $_SESSION['message'] = __('admin.installment.wallet_credit_generated', 'Crédito em carteira gerado com sucesso.');
        } else {
            $_SESSION['message'] = __('admin.installment.invalid_action', 'Ação inválida.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect("/admin/carnes/detalhes/{$id}");
            return;
        }

        // Marcar produto como indisponível na compra interna
        try {
            $stmt = $this->db->prepare("
                UPDATE carne_compras_internas SET 
                    status = 'produto_indisponivel', produto_indisponivel = 1,
                    acao_indisponibilidade = :acao, valor_credito = :val, observacoes = :obs
                WHERE carne_id = :cid
            ");
            $stmt->execute([':acao' => $acao, ':val' => $valor, ':obs' => $obs, ':cid' => $id]);
        } catch (\Exception $e) {}

        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$id}");
    }

    /**
     * Gerar link de diferença (AJAX)
     */
    public function gerarLinkDiferenca(Request $request, $id) {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $carne = $this->carneModel->find($id);
            if (!$carne) { echo json_encode(['success' => false, 'error' => __('admin.installment.not_found_short', 'Carnê não encontrado')]); return; }

            $body = $request->getBody();
            $valor = floatval($body['valor'] ?? 0);
            $obs = trim((string) ($body['observacoes'] ?? ''));
            if ($valor <= 0) { echo json_encode(['success' => false, 'error' => __('admin.installment.value_gt_zero', 'Valor deve ser maior que zero')]); return; }

            $pedidoId = (int) $carne['pedido_id'];
            $adminId = (int) ($_SESSION['usuario_id'] ?? 0);
            $descricao = "Diferença Carnê #$id - Pedido #$pedidoId";

            $svc = new \App\Services\PaymentLinkService();
            $result = $svc->createLink([
                'currency' => 'BRL',
                'products' => [['name' => $descricao, 'value' => $valor]],
                'taxa_servico_valor' => 0, 'impostos_valor' => 0, 'descricao' => $descricao,
            ], $adminId);

            if (empty($result['success'])) { echo json_encode(['success' => false, 'error' => $result['error'] ?? __('admin.installment.create_link_failed', 'Falha ao criar link')]); return; }

            // Registrar no pedido
            try {
                $colsPed = [];
                try { $stC = $this->db->query('DESCRIBE pedidos'); $colsPed = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                foreach (['diferenca_link_id'=>'INT NULL','diferenca_valor'=>'DECIMAL(10,2) NULL','diferenca_vendedor_id'=>'INT NULL','diferenca_created_at'=>'DATETIME NULL'] as $col=>$tipo) {
                    if (!in_array($col, $colsPed, true)) { try { $this->db->exec("ALTER TABLE pedidos ADD COLUMN {$col} {$tipo}"); } catch (\Exception $e) {} }
                }
                $this->db->prepare('UPDATE pedidos SET diferenca_link_id=?, diferenca_valor=?, diferenca_vendedor_id=?, diferenca_created_at=NOW() WHERE id=?')
                    ->execute([(int)$result['id'], $valor, $adminId, $pedidoId]);
            } catch (\Exception $e) {}

            $this->carneModel->registrarHistorico($id, null, 'link_diferenca',
                "Link de diferença: R$ " . number_format($valor, 2, ',', '.') . ($obs ? " — $obs" : ''),
                ['link_id'=>$result['id'],'url'=>$result['public_url']??'','valor'=>$valor], $adminId);

            try {
                $this->db->prepare("UPDATE carne_compras_internas SET produto_indisponivel=1, acao_indisponibilidade='link_diferenca', observacoes=? WHERE carne_id=?")
                    ->execute([$obs ?: "Diferença R$ ".number_format($valor,2,',','.'), $id]);
            } catch (\Exception $e) {}

            echo json_encode(['success'=>true, 'link_url'=>$result['public_url']??'', 'link_id'=>$result['id']??0, 'valor'=>$valor]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function liberarEnvio(Request $request, $id) {
        $carne = $this->carneModel->find($id);
        if (!$carne || $carne['status'] !== 'quitado') {
            $_SESSION['message'] = __('admin.installment.must_be_paid_to_ship', 'Carnê precisa estar quitado para liberar envio.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect("/admin/carnes/detalhes/{$id}");
        }

        $this->carneModel->update($id, ['envio_liberado' => 1, 'status' => 'liberado_envio']);
        $this->carneModel->registrarHistorico($id, null, 'envio_liberado',
            'Envio liberado pelo admin', null, $_SESSION['usuario_id'] ?? null);

        $this->carneService->dispararNotificacao($id, null, 'envio_liberado');

        $_SESSION['message'] = __('admin.installment.shipping_released', 'Envio liberado com sucesso.');
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$id}");
    }

    /**
     * Reenviar notificação
     */
    public function reenviarNotificacao(Request $request, $carneId) {
        $evento = $request->getParam('evento', 'carne_criado');
        $parcelaId = $request->getParam('parcela_id') ?: null;

        $this->carneService->dispararNotificacao($carneId, $parcelaId, $evento);

        $_SESSION['message'] = __('admin.installment.notification_resent', 'Notificação reenviada.');
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * Configurações do Carnê
     */
    public function configuracoes(Request $request) {
        if ($request->getMethod() === 'POST') {
            $campos = [
                'carne_ativo', 'carne_somente_admin', 'carne_max_parcelas', 'carne_valor_minimo', 'carne_dias_vencimento',
                'carne_meses_atraso_cancelamento', 'carne_dias_aviso_cancelamento',
                'carne_webhook_url', 'carne_webhook_ativo', 'carne_email_ativo',
                'carne_eventos_webhook', 'carne_eventos_email', 'cron_secret'
            ];
            foreach ($campos as $campo) {
                $val = $request->getParam($campo);
                if ($val !== null) {
                    // Tentar UPDATE primeiro (evita erro com colunas NOT NULL sem default)
                    $stmt = $this->db->prepare("UPDATE configuracoes_sistema SET valor = :valor WHERE chave = :chave");
                    $stmt->execute([':valor' => $val, ':chave' => $campo]);
                    if ($stmt->rowCount() === 0) {
                        // Registro não existe, tentar INSERT ignorando erros de colunas extras
                        try {
                            $this->db->prepare("INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES (?, ?)")->execute([$campo, $val]);
                        } catch (\Exception $e) {
                            // Se falhar por colunas obrigatórias, ignorar silenciosamente
                        }
                    }
                }
            }
            $_SESSION['message'] = __('admin.installment.settings_saved', 'Configurações salvas.');
            $_SESSION['message_type'] = 'success';
            $this->redirect('/admin/carnes/configuracoes');
        }

        $stmt = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave LIKE 'carne_%' OR chave = 'cron_secret'");
        $stmt->execute();
        $config = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        require __DIR__ . '/../Views/admin/carne/configuracoes.php';
    }

    /**
     * GET /admin/carnes/buscar-pedido?pedido_id=X
     * Retorna dados do pedido para o formulário de recriar carnê.
     */
    public function buscarPedido(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        if ($pedidoId <= 0) {
            echo json_encode(['error' => 'ID do pedido inválido.']);
            return;
        }

        $stPed = $this->db->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
        $stPed->execute([$pedidoId]);
        $pedido = $stPed->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            echo json_encode(['error' => 'Pedido #' . $pedidoId . ' não encontrado.']);
            return;
        }

        // Verificar se já tem carnê
        $stCheck = $this->db->prepare('SELECT id FROM carnes WHERE pedido_id = ? LIMIT 1');
        $stCheck->execute([$pedidoId]);
        $carneExistente = $stCheck->fetchColumn();

        // Buscar cliente
        $clienteId = (int) ($pedido['usuario_id'] ?? 0);
        $clienteNome = '';
        $clienteEmail = '';
        if ($clienteId > 0) {
            $stUser = $this->db->prepare('SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1');
            $stUser->execute([$clienteId]);
            $user = $stUser->fetch(\PDO::FETCH_ASSOC) ?: [];
            $clienteNome = (string) ($user['nome'] ?? '');
            $clienteEmail = (string) ($user['email'] ?? '');
        }

        // Câmbio
        $taxaConv = 1.0;
        try {
            $stTx = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'usd_brl_rate' LIMIT 1");
            $stTx->execute();
            $txVal = (float) str_replace(',', '.', (string) ($stTx->fetchColumn() ?: '0'));
            if ($txVal > 1.01) $taxaConv = $txVal;
        } catch (\Exception $e) {}
        if ($taxaConv <= 1.01) $taxaConv = \App\Core\ExchangeRate::getUsdToBrl();

        $subUsd = (float) ($pedido['subtotal_produtos'] ?? ($pedido['subtotal'] ?? 0));
        $svcUsd = (float) ($pedido['taxa_servico'] ?? ($pedido['servicos'] ?? 0));
        $impUsd = (float) ($pedido['valor_impostos'] ?? ($pedido['impostos'] ?? 0));
        $impLocal = (float) ($pedido['imposto_local'] ?? 0);
        $taxasUsd = $svcUsd + $impUsd + $impLocal;

        // Tentar encontrar quantidade de parcelas no pedido_meta
        $parcelasSugeridas = null;
        try {
            // Tentar pedido_meta
            $stMeta = $this->db->prepare("SELECT meta_value FROM pedido_meta WHERE pedido_id = ? AND meta_key IN ('carne_parcelas','quantidade_parcelas','parcelas') LIMIT 1");
            $stMeta->execute([$pedidoId]);
            $metaVal = $stMeta->fetchColumn();
            if ($metaVal && (int) $metaVal > 0 && (int) $metaVal <= 12) {
                $parcelasSugeridas = (int) $metaVal;
            }
        } catch (\Exception $e) {}

        // Tentar campo direto no pedido
        if (!$parcelasSugeridas) {
            foreach (['carne_parcelas', 'quantidade_parcelas', 'parcelas'] as $col) {
                if (isset($pedido[$col]) && (int) $pedido[$col] > 0 && (int) $pedido[$col] <= 12) {
                    $parcelasSugeridas = (int) $pedido[$col];
                    break;
                }
            }
        }

        // Tentar no observacao/metadata do pedido (pode ter sido salvo como JSON)
        if (!$parcelasSugeridas) {
            foreach (['observacao', 'metadata', 'dados_extras'] as $col) {
                if (!empty($pedido[$col]) && is_string($pedido[$col])) {
                    $decoded = @json_decode($pedido[$col], true);
                    if (is_array($decoded)) {
                        foreach (['carne_parcelas', 'quantidade_parcelas', 'parcelas'] as $k) {
                            if (isset($decoded[$k]) && (int) $decoded[$k] > 0 && (int) $decoded[$k] <= 12) {
                                $parcelasSugeridas = (int) $decoded[$k];
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL'))));
        $isBrl = ($moedaPedido === 'BRL');

        echo json_encode([
            'pedido_id' => $pedidoId,
            'forma_pagamento' => (string) ($pedido['forma_pagamento'] ?? ''),
            'moeda' => $moedaPedido,
            'cliente_nome' => $clienteNome,
            'cliente_email' => $clienteEmail,
            'subtotal_usd' => round($subUsd, 2),
            'taxas_usd' => round($taxasUsd, 2),
            'subtotal_brl' => $isBrl ? round($subUsd, 2) : round($subUsd * $taxaConv, 2),
            'taxas_brl' => $isBrl ? round($taxasUsd, 2) : round($taxasUsd * $taxaConv, 2),
            'total_brl' => $isBrl ? round($subUsd + $taxasUsd, 2) : round(($subUsd + $taxasUsd) * $taxaConv, 2),
            'cambio' => $taxaConv,
            'parcelas_sugeridas' => $parcelasSugeridas,
            'ja_tem_carne' => !empty($carneExistente),
            'carne_id' => $carneExistente ? (int) $carneExistente : null,
        ]);
    }

    /**
     * Compras do Carnê — Produtos agrupados por mês
     */
    public function compras(Request $request) {
        // Reconciliar casos legados: carnês já marcados como 'comprado'/'recebido'
        // cujo pedido/lista_compras ainda não refletem a compra (bug anterior à correção).
        $this->reconciliarStatusPedidosCarne();

        $filtroStatus = $request->getParam('status', '');
        $filtroTipo = $request->getParam('tipo', '');
        $filtroParcelas = $request->getParam('parcelas', '');

        // Detectar tabela de itens
        $itensTable = 'pedido_itens';
        try {
            $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stT->execute(['pedido_itens']);
            if ((int) $stT->fetchColumn() === 0) {
                $stT->execute(['pedido_items']);
                if ((int) $stT->fetchColumn() > 0) {
                    $itensTable = 'pedido_items';
                }
            }
        } catch (\Exception $e) {}

        // Detectar colunas da tabela de itens
        $colsItens = [];
        try { $st = $this->db->query("DESCRIBE {$itensTable}"); $colsItens = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $colProdutoId = in_array('produto_id', $colsItens, true) ? 'produto_id' : (in_array('product_id', $colsItens, true) ? 'product_id' : 'produto_id');
        $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : 'quantidade');
        $colNomeProd = in_array('nome_produto', $colsItens, true) ? 'nome_produto' : (in_array('product_name', $colsItens, true) ? 'product_name' : '');

        // Detectar colunas de produtos
        $colsProd = [];
        try { $st = $this->db->query("DESCRIBE produtos"); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $colProdNome = in_array('name', $colsProd, true) ? 'name' : (in_array('nome', $colsProd, true) ? 'nome' : 'name');
        $colProdFoto = in_array('foto_principal', $colsProd, true) ? 'foto_principal' : (in_array('imagem', $colsProd, true) ? 'imagem' : '');

        // Detectar coluna vencimento em carne_parcelas
        $colVenc = 'vencimento';
        try {
            $colsParc = [];
            $st = $this->db->query("DESCRIBE carne_parcelas");
            $colsParc = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (!in_array('vencimento', $colsParc, true) && in_array('data_vencimento', $colsParc, true)) $colVenc = 'data_vencimento';
        } catch (\Exception $e) {}

        // Verificar se carne_compras_internas existe
        $temComprasInternas = false;
        try {
            $stT = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'carne_compras_internas'");
            $stT->execute();
            $temComprasInternas = ((int) $stT->fetchColumn()) > 0;
        } catch (\Exception $e) {}

        // Verificar deleted_at em pedidos
        $temDeletedAt = false;
        try {
            $stD = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'deleted_at'");
            $stD->execute();
            $temDeletedAt = ((int) $stD->fetchColumn()) > 0;
        } catch (\Exception $e) {}

        $compras = [];
        $stats = ['total' => 0, 'aguardando' => 0, 'comprado' => 0, 'recebido' => 0];

        // Buscar itens de pedidos de carnê (via tabela carnes → pedido_itens)
        $statusFilter = '';
        $params = [];
        if ($temComprasInternas && !empty($filtroStatus)) {
            $statusFilter = " AND ci.status = :status";
            $params[':status'] = $filtroStatus;
        }

        // Filtro por tipo (primeira parcela paga/pendente)
        $tipoFilter = '';
        if ($filtroTipo === 'primeira_paga') {
            $tipoFilter = " AND (SELECT COALESCE(cp_f.boleto_produtos_pago,0) FROM carne_parcelas cp_f WHERE cp_f.carne_id = c.id AND cp_f.numero_parcela = 1 LIMIT 1) = 1";
        } elseif ($filtroTipo === 'primeira_pendente') {
            $tipoFilter = " AND (SELECT COALESCE(cp_f.boleto_produtos_pago,0) FROM carne_parcelas cp_f WHERE cp_f.carne_id = c.id AND cp_f.numero_parcela = 1 LIMIT 1) = 0";
        } elseif ($filtroTipo === 'quitado') {
            $tipoFilter = " AND c.status = 'quitado'";
        } elseif ($filtroTipo === 'com_atraso') {
            $tipoFilter = " AND c.status IN ('com_atraso','inadimplente')";
        }

        // Filtro por quantidade de parcelas
        $parcelasFilter = '';
        if ($filtroParcelas !== '' && (int) $filtroParcelas > 0) {
            $parcelasFilter = " AND c.quantidade_parcelas = :parcelas";
            $params[':parcelas'] = (int) $filtroParcelas;
        }

        $deletedFilter = $temDeletedAt ? 'AND ped.deleted_at IS NULL' : '';

        // REGRA: Mostrar APENAS carnês com pelo menos 1 parcela paga
        $parcelaPagaFilter = "AND (SELECT COUNT(*) FROM carne_parcelas cpf WHERE cpf.carne_id = c.id AND cpf.status = 'paga') > 0";

        if ($temComprasInternas) {
            $sql = "
                SELECT 
                    ci.id, ci.carne_id, ci.status as status_compra, ci.comprado_em, ci.recebido_em, ci.created_at,
                    c.pedido_id, c.total_geral, c.quantidade_parcelas, c.status as carne_status,
                    u.nome as cliente_nome, u.email as cliente_email,
                    it.{$colProdutoId} as produto_id, it.{$colQtd} as quantidade,
                    " . ($colNomeProd ? "it.{$colNomeProd} as item_nome," : '') . "
                    p.{$colProdNome} as produto_nome,
                    " . ($colProdFoto ? "p.{$colProdFoto} as produto_imagem," : "'' as produto_imagem,") . "
                    (SELECT COUNT(*) FROM carne_parcelas cp WHERE cp.carne_id = c.id AND cp.status = 'paga') as parcelas_pagas,
                    (SELECT MIN(cp2.{$colVenc}) FROM carne_parcelas cp2 WHERE cp2.carne_id = c.id) as data_inicio,
                    (SELECT MAX(cp3.{$colVenc}) FROM carne_parcelas cp3 WHERE cp3.carne_id = c.id) as data_fim_estimada,
                    (SELECT cp1.status FROM carne_parcelas cp1 WHERE cp1.carne_id = c.id AND cp1.numero_parcela = 1 LIMIT 1) as status_primeira_parcela,
                    (SELECT COALESCE(cp1b.boleto_produtos_pago,0) FROM carne_parcelas cp1b WHERE cp1b.carne_id = c.id AND cp1b.numero_parcela = 1 LIMIT 1) as primeira_parcela_produtos_pago,
                    (SELECT COALESCE(cp1c.boleto_taxas_pago,0) FROM carne_parcelas cp1c WHERE cp1c.carne_id = c.id AND cp1c.numero_parcela = 1 LIMIT 1) as primeira_parcela_taxas_pago
                FROM carne_compras_internas ci
                JOIN carnes c ON ci.carne_id = c.id
                JOIN usuarios u ON c.cliente_id = u.id
                JOIN pedidos ped ON ped.id = c.pedido_id
                LEFT JOIN {$itensTable} it ON it.pedido_id = c.pedido_id
                LEFT JOIN produtos p ON p.id = it.{$colProdutoId}
                WHERE 1=1 {$statusFilter} {$tipoFilter} {$parcelasFilter}
                {$deletedFilter}
                {$parcelaPagaFilter}
                AND LOWER(COALESCE(ped.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','excluido','excluída','deleted','lixeira','trash')
                GROUP BY ci.carne_id, it.{$colProdutoId}
                ORDER BY c.quantidade_parcelas ASC, ci.created_at DESC
            ";
        } else {
            // Fallback: buscar direto dos pedidos de carnê (sem carne_compras_internas)
            $sql = "
                SELECT 
                    c.id as id, c.id as carne_id, 'aguardando_compra' as status_compra, NULL as comprado_em, NULL as recebido_em, c.created_at,
                    c.pedido_id, c.total_geral, c.quantidade_parcelas, c.status as carne_status,
                    u.nome as cliente_nome, u.email as cliente_email,
                    it.{$colProdutoId} as produto_id, it.{$colQtd} as quantidade,
                    " . ($colNomeProd ? "it.{$colNomeProd} as item_nome," : '') . "
                    p.{$colProdNome} as produto_nome,
                    " . ($colProdFoto ? "p.{$colProdFoto} as produto_imagem," : "'' as produto_imagem,") . "
                    (SELECT COUNT(*) FROM carne_parcelas cp WHERE cp.carne_id = c.id AND cp.status = 'paga') as parcelas_pagas,
                    (SELECT MIN(cp2.{$colVenc}) FROM carne_parcelas cp2 WHERE cp2.carne_id = c.id) as data_inicio,
                    (SELECT MAX(cp3.{$colVenc}) FROM carne_parcelas cp3 WHERE cp3.carne_id = c.id) as data_fim_estimada,
                    (SELECT cp1.status FROM carne_parcelas cp1 WHERE cp1.carne_id = c.id AND cp1.numero_parcela = 1 LIMIT 1) as status_primeira_parcela,
                    (SELECT COALESCE(cp1b.boleto_produtos_pago,0) FROM carne_parcelas cp1b WHERE cp1b.carne_id = c.id AND cp1b.numero_parcela = 1 LIMIT 1) as primeira_parcela_produtos_pago,
                    (SELECT COALESCE(cp1c.boleto_taxas_pago,0) FROM carne_parcelas cp1c WHERE cp1c.carne_id = c.id AND cp1c.numero_parcela = 1 LIMIT 1) as primeira_parcela_taxas_pago
                FROM carnes c
                JOIN usuarios u ON c.cliente_id = u.id
                JOIN pedidos ped ON ped.id = c.pedido_id
                LEFT JOIN {$itensTable} it ON it.pedido_id = c.pedido_id
                LEFT JOIN produtos p ON p.id = it.{$colProdutoId}
                WHERE 1=1 {$tipoFilter} {$parcelasFilter}
                {$deletedFilter}
                {$parcelaPagaFilter}
                AND LOWER(COALESCE(ped.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','excluido','excluída','deleted','lixeira','trash')
                GROUP BY c.id, it.{$colProdutoId}
                ORDER BY c.quantidade_parcelas ASC, c.created_at DESC
            ";
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $compras = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('[CarneCompras] Erro: ' . $e->getMessage());
            $compras = [];
        }

        // Calcular stats e enriquecer imagens
        foreach ($compras as &$c) {
            $stats['total']++;
            $st = $c['status_compra'] ?? '';
            if ($st === 'aguardando_compra') $stats['aguardando']++;
            elseif ($st === 'comprado') $stats['comprado']++;
            elseif ($st === 'recebido') $stats['recebido']++;

            // Fallback imagem: buscar de produto_fotos se não tem
            $img = trim((string) ($c['produto_imagem'] ?? ''));
            if ($img === '' && !empty($c['produto_id'])) {
                try {
                    $stF = $this->db->prepare('SELECT nome_arquivo FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, ordem ASC LIMIT 1');
                    $stF->execute([(int) $c['produto_id']]);
                    $img = trim((string) ($stF->fetchColumn() ?: ''));
                } catch (\Exception $e) {}
            }
            $c['produto_imagem'] = $img;
        }
        unset($c);

        // Agrupar por mês (baseado na data de criação da compra interna)
        $porMes = [];
        foreach ($compras as $c) {
            $data = $c['created_at'] ?? date('Y-m-d');
            $mesKey = date('Y-m', strtotime($data));
            if (!isset($porMes[$mesKey])) $porMes[$mesKey] = [];
            $porMes[$mesKey][] = $c;
        }
        krsort($porMes);

        $title = __('admin.installment.purchases_title', 'Compras do Carnê');
        $sidebarActive = 'carnes-compras';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/carne/compras.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Reconciliação: verifica status real dos pagamentos no Câmbio Real
     * e corrige parcelas marcadas como pagas que na verdade expiraram
     */
    public function reconciliar(Request $request) {
        $auth = new \App\Services\AuthService();
        $auth->requerPerfis(['admin']);

        $paymentService = new \App\Services\PaymentService();
        $corrigidos = 0;
        $verificados = 0;
        $erros = 0;

        try {
            // Buscar TODAS as parcelas que têm ID externo do Câmbio Real (pra verificar e corrigir em ambas direções)
            $sql = "SELECT cp.id, cp.carne_id, cp.numero_parcela, cp.status,
                        cp.boleto_produtos_id_externo, cp.boleto_produtos_pago,
                        cp.boleto_taxas_id_externo, cp.boleto_taxas_pago
                    FROM carne_parcelas cp
                    WHERE (cp.boleto_produtos_id_externo IS NOT NULL AND cp.boleto_produtos_id_externo != '')
                    ORDER BY cp.id DESC
                    LIMIT 500";

            $stmt = $this->db->query($sql);
            $parcelas = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            foreach ($parcelas as $p) {
                $prodId = trim((string) ($p['boleto_produtos_id_externo'] ?? ''));
                $taxaId = trim((string) ($p['boleto_taxas_id_externo'] ?? ''));
                $prodPago = (int) ($p['boleto_produtos_pago'] ?? 0);
                $taxaPago = (int) ($p['boleto_taxas_pago'] ?? 0);
                $parcelaId = (int) $p['id'];

                $prodRealPago = false;
                $taxaRealPago = false;

                // Verificar produto no Câmbio Real
                if ($prodId !== '') {
                    $verificados++;
                    try {
                        $cr = $paymentService->checkCambioRealPaymentStatus($prodId);
                        $prodRealPago = !empty($cr['paid']);
                    } catch (\Exception $e) {
                        $erros++;
                        continue;
                    }
                }

                // Verificar taxa no Câmbio Real
                if ($taxaId !== '') {
                    $verificados++;
                    try {
                        $cr = $paymentService->checkCambioRealPaymentStatus($taxaId);
                        $taxaRealPago = !empty($cr['paid']);
                    } catch (\Exception $e) {
                        $erros++;
                        continue;
                    }
                }

                // Corrigir discrepâncias (em ambas direções)
                $precisaCorrigir = false;
                $updates = [];

                // Se marcado como pago mas na verdade expirou
                if ($prodPago && !$prodRealPago && $prodId !== '') {
                    $updates[] = 'boleto_produtos_pago = 0';
                    $updates[] = 'boleto_produtos_pago_em = NULL';
                    $precisaCorrigir = true;
                }
                if ($taxaPago && !$taxaRealPago && $taxaId !== '') {
                    $updates[] = 'boleto_taxas_pago = 0';
                    $updates[] = 'boleto_taxas_pago_em = NULL';
                    $precisaCorrigir = true;
                }

                // Se NÃO marcado como pago mas na verdade FOI pago (re-confirmar)
                if (!$prodPago && $prodRealPago && $prodId !== '') {
                    $updates[] = 'boleto_produtos_pago = 1';
                    $updates[] = 'boleto_produtos_pago_em = NOW()';
                    $precisaCorrigir = true;
                }
                if (!$taxaPago && $taxaRealPago && $taxaId !== '') {
                    $updates[] = 'boleto_taxas_pago = 1';
                    $updates[] = 'boleto_taxas_pago_em = NOW()';
                    $precisaCorrigir = true;
                }

                if ($precisaCorrigir) {
                    // Determinar novo status baseado no estado REAL
                    $novoProdPago = $prodRealPago ? 1 : 0;
                    $novoTaxaPago = $taxaRealPago ? 1 : 0;

                    if ($novoProdPago && $novoTaxaPago) {
                        $novoStatus = 'paga';
                    } elseif ($novoProdPago || $novoTaxaPago) {
                        $novoStatus = 'parcialmente_paga';
                    } else {
                        $novoStatus = 'aguardando_pagamento';
                    }

                    $updates[] = "status = '{$novoStatus}'";
                    $updates[] = "updated_at = NOW()";

                    $this->db->prepare("UPDATE carne_parcelas SET " . implode(', ', $updates) . " WHERE id = ?")
                        ->execute([$parcelaId]);

                    // Registrar no histórico
                    try {
                        $this->carneModel->registrarHistorico(
                            (int) $p['carne_id'], $parcelaId, 'reconciliacao',
                            "Parcela {$p['numero_parcela']} corrigida: pagamento expirado no Câmbio Real. Status: {$p['status']} → {$novoStatus}"
                        );
                    } catch (\Exception $e) {}

                    $corrigidos++;
                }

                // Rate limit: não sobrecarregar a API
                usleep(200000); // 200ms entre requests
            }
        } catch (\Exception $e) {
            if ($request->getParam('json')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $_SESSION['flash_error'] = 'Erro na reconciliação: ' . $e->getMessage();
            header('Location: /admin/carnes');
            exit;
        }

        if ($request->getParam('json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'verificados' => $verificados, 'corrigidos' => $corrigidos, 'erros' => $erros]);
            exit;
        }

        $_SESSION['flash_success'] = "Reconciliação concluída: {$verificados} verificados, {$corrigidos} corrigidos, {$erros} erros.";
        header('Location: /admin/carnes');
        exit;
    }

    /**
     * Logs do sistema de carnê
     */
    public function logs(Request $request) {
        $filtros = [
            'carne_id' => $request->getParam('carne_id', ''),
            'pedido_id' => $request->getParam('pedido_id', ''),
            'tipo' => $request->getParam('tipo', ''),
        ];

        $where = ['1=1'];
        $params = [];
        if (!empty($filtros['carne_id'])) { $where[] = 'cl.carne_id = ?'; $params[] = (int) $filtros['carne_id']; }
        if (!empty($filtros['pedido_id'])) { $where[] = 'cl.pedido_id = ?'; $params[] = (int) $filtros['pedido_id']; }
        if (!empty($filtros['tipo'])) { $where[] = 'cl.tipo = ?'; $params[] = $filtros['tipo']; }

        $stmt = $this->db->prepare('SELECT cl.* FROM carne_logs cl WHERE ' . implode(' AND ', $where) . ' ORDER BY cl.created_at DESC LIMIT 500');
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        require __DIR__ . '/../Views/admin/carne/logs.php';
    }

    /**
     * POST /admin/carnes/marcar-parcela-paga/{parcelaId}
     * Marca uma parcela como paga manualmente (produtos e/ou taxas).
     */
    public function marcarParcelaPaga(Request $request, $parcelaId) {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $_SESSION['message'] = __('admin.installment.installment_not_found', 'Parcela não encontrada.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $tipo = (string) $request->getParam('tipo', 'ambos'); // 'produtos', 'taxas', 'ambos'
        $carneId = (int) ($parcela['carne_id'] ?? 0);

        if ($tipo === 'produtos' || $tipo === 'ambos') {
            $this->carneModel->registrarPagamentoBoleto($parcelaId, 'produtos');
            $this->carneModel->registrarLog($carneId, null, (int) $parcelaId, 'pagamento_confirmado', "Pagamento PRODUTOS marcado manualmente pelo admin (parcela #{$parcela['numero_parcela']})", '');
        }
        if ($tipo === 'taxas' || $tipo === 'ambos') {
            $this->carneModel->registrarPagamentoBoleto($parcelaId, 'taxas');
            $this->carneModel->registrarLog($carneId, null, (int) $parcelaId, 'pagamento_confirmado', "Pagamento TAXAS marcado manualmente pelo admin (parcela #{$parcela['numero_parcela']})", '');
        }

        $this->carneModel->registrarHistorico($carneId, $parcelaId, 'pagamento_manual',
            "Parcela {$parcela['numero_parcela']} marcada como paga manualmente ({$tipo})", null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = "Parcela {$parcela['numero_parcela']} marcada como paga ({$tipo}).";
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * POST /admin/carnes/recriar
     * Recria carnê para pedidos que ficaram sem registro na tabela carnes.
     */
    public function recriarCarne(Request $request) {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        $qtdParcelas = (int) ($request->getParam('quantidade_parcelas', 0) ?: $request->getParam('parcelas', 0));

        // Se não veio no POST, tentar buscar do pedido_meta
        if ($qtdParcelas < 1 && $pedidoId > 0) {
            try {
                $dbM = \Config\Database::getConnection();
                $stM = $dbM->prepare("SELECT meta_value FROM pedido_meta WHERE pedido_id = ? AND meta_key = 'carne_parcelas' LIMIT 1");
                $stM->execute([$pedidoId]);
                $metaVal = $stM->fetchColumn();
                if ($metaVal) $qtdParcelas = (int) $metaVal;
            } catch (\Exception $e) {}
        }

        if ($qtdParcelas < 1 || $qtdParcelas > 12) $qtdParcelas = 4;

        if ($pedidoId <= 0) {
            $_SESSION['message'] = __('admin.installment.invalid_order_id', 'ID do pedido inválido.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        // Verificar se já existe carnê para esse pedido
        $stCheck = $this->db->prepare('SELECT id FROM carnes WHERE pedido_id = ? LIMIT 1');
        $stCheck->execute([$pedidoId]);
        if ($stCheck->fetchColumn()) {
            $_SESSION['message'] = __('admin.installment.already_exists_for_order', 'Já existe um carnê para o pedido #{id}.', ['id' => $pedidoId]);
            $_SESSION['message_type'] = 'warning';
            $this->redirect('/admin/carnes');
            return;
        }

        // Buscar dados do pedido
        $stPed = $this->db->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
        $stPed->execute([$pedidoId]);
        $pedido = $stPed->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            $_SESSION['message'] = __('admin.installment.order_not_found', 'Pedido #{id} não encontrado.', ['id' => $pedidoId]);
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $clienteId = (int) ($pedido['usuario_id'] ?? 0);
        if ($clienteId <= 0) {
            $_SESSION['message'] = __('admin.installment.order_no_customer', 'Pedido #{id} não tem cliente vinculado.', ['id' => $pedidoId]);
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        // Calcular valores
        $taxaConv = 1.0;
        try {
            $stTx = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'usd_brl_rate' LIMIT 1");
            $stTx->execute();
            $txVal = (float) str_replace(',', '.', (string) ($stTx->fetchColumn() ?: '0'));
            if ($txVal > 1.01) $taxaConv = $txVal;
        } catch (\Exception $e) {}
        if ($taxaConv <= 1.01) $taxaConv = \App\Core\ExchangeRate::getUsdToBrl();

        $subUsd = (float) ($pedido['subtotal_produtos'] ?? ($pedido['subtotal'] ?? 0));
        $svcUsd = (float) ($pedido['taxa_servico'] ?? ($pedido['servicos'] ?? 0));
        $impUsd = (float) ($pedido['valor_impostos'] ?? ($pedido['impostos'] ?? 0));
        $impLocal = (float) ($pedido['imposto_local'] ?? 0);

        // Se o pedido já está em BRL, os valores já estão em reais — NÃO multiplicar pelo câmbio
        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL'))));
        if ($moedaPedido === 'BRL') {
            $subtotalProdutos = round($subUsd, 2);
            $totalTaxas = round($svcUsd + $impUsd + $impLocal, 2);
        } else {
            $subtotalProdutos = round($subUsd * $taxaConv, 2);
            $totalTaxas = round(($svcUsd + $impUsd + $impLocal) * $taxaConv, 2);
        }

        if ($subtotalProdutos <= 0 && $totalTaxas <= 0) {
            $_SESSION['message'] = __('admin.installment.order_zero_values', 'Pedido #{id} tem valores zerados. Não é possível criar carnê.', ['id' => $pedidoId]);
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        // Buscar dados do cliente
        $stUser = $this->db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stUser->execute([$clienteId]);
        $user = $stUser->fetch(\PDO::FETCH_ASSOC) ?: [];

        try {
            $carneId = $this->carneService->criarCarne(
                $pedidoId,
                $clienteId,
                $subtotalProdutos,
                $totalTaxas,
                $qtdParcelas,
                [
                    'nome' => (string) ($user['nome'] ?? ($user['name'] ?? '')),
                    'email' => (string) ($user['email'] ?? ''),
                    'documento' => (string) ($user['documento'] ?? ($user['cpf'] ?? '')),
                    'data_nascimento' => (string) ($user['data_nascimento'] ?? ($user['birth_date'] ?? '')),
                    'telefone' => (string) ($user['telefone'] ?? ($user['phone'] ?? '')),
                    'cep' => (string) ($user['cep'] ?? ($user['zip_code'] ?? '')),
                    'endereco' => (string) ($user['endereco'] ?? ($user['street'] ?? '')),
                    'numero' => (string) ($user['numero'] ?? ($user['number'] ?? '')),
                    'bairro' => (string) ($user['bairro'] ?? ($user['district'] ?? '')),
                    'cidade' => (string) ($user['cidade'] ?? ($user['city'] ?? '')),
                    'estado' => (string) ($user['estado'] ?? ($user['state'] ?? '')),
                ]
            );

            $_SESSION['message'] = __('admin.installment.created_success', 'Carnê criado com sucesso para o pedido #{oid} (ID: {cid}, {qty} parcelas, Produtos: R$ {prod}, Taxas: R$ {fees})', ['oid' => $pedidoId, 'cid' => $carneId, 'qty' => $qtdParcelas, 'prod' => number_format($subtotalProdutos, 2, ',', '.'), 'fees' => number_format($totalTaxas, 2, ',', '.')]);
            $_SESSION['message_type'] = 'success';
            // Redirecionar de volta ao pedido se veio de lá
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, '/admin/pedidos/detalhes/') !== false) {
                $this->redirect('/admin/pedidos/detalhes/' . $pedidoId);
            } else {
                $this->redirect('/admin/carnes/detalhes/' . $carneId);
            }
        } catch (\Exception $e) {
            error_log('[ADMIN CARNE] Erro ao recriar carnê pedido #' . $pedidoId . ': ' . $e->getMessage());
            $_SESSION['message'] = __('admin.installment.create_error', 'Erro ao criar carnê: ') . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, '/admin/pedidos/detalhes/') !== false) {
                $this->redirect('/admin/pedidos/detalhes/' . $pedidoId);
            } else {
                $this->redirect('/admin/carnes');
            }
        }
    }

    /**
     * Enviar email de cobrança para uma parcela pendente/em atraso
     * POST /admin/carnes/enviar-cobranca/{parcelaId}
     */
    public function enviarCobranca(Request $request, $parcelaId) {
        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $_SESSION['message'] = __('admin.installment.installment_not_found', 'Parcela não encontrada.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $carneId = (int) $parcela['carne_id'];
        $carne = $this->carneModel->getCompleto($carneId);
        if (!$carne) {
            $_SESSION['message'] = __('admin.installment.not_found', 'Carnê não encontrado.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $destinatarioEmail = $carne['cliente_email'] ?? '';
        $destinatarioNome = $carne['cliente_nome'] ?? '';
        $status = 'enviado';
        $erroMsg = null;

        // Montar dados para o template
        $numeroParcela = (int) ($parcela['numero_parcela'] ?? 0);
        $totalParcelas = (int) ($carne['quantidade_parcelas'] ?? 0);
        $valorTotal = number_format((float) ($parcela['valor_total'] ?? 0), 2, ',', '.');
        $valorProdutos = number_format((float) ($parcela['valor_produtos'] ?? 0), 2, ',', '.');
        $valorTaxas = number_format((float) ($parcela['valor_taxas'] ?? 0), 2, ',', '.');
        $vencimento = date('d/m/Y', strtotime($parcela['vencimento']));
        $statusParcela = $parcela['status'] ?? 'pendente';
        $pedidoId = (int) ($carne['pedido_id'] ?? 0);

        // URL para o cliente acessar o carnê
        $baseUrl = rtrim(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br'), '/');
        $urlMeuCarne = $baseUrl . '/meu-carne/' . $carneId;
        $clienteNome = $destinatarioNome;

        $assunto = __('admin.installment.email_subject', 'Cobrança - Parcela {n}/{total} do Carnê #{id}', ['n' => $numeroParcela, 'total' => $totalParcelas, 'id' => $carneId]);
        if ($statusParcela === 'em_atraso' || $statusParcela === 'vencida') {
            $assunto = __('admin.installment.email_subject_overdue', '⚠️ Parcela em atraso - {n}/{total} do Carnê #{id}', ['n' => $numeroParcela, 'total' => $totalParcelas, 'id' => $carneId]);
        }

        // Renderizar template HTML
        $titulo = $assunto;
        $mensagem = __('admin.installment.email_message', 'Estamos entrando em contato para lembrar sobre o pagamento da parcela do seu carnê.');
        $detalhes = [
            __('admin.installment.email_detail_installment_book', 'Carnê') => "#{$carneId} (" . __('admin.installment.order_word_short', 'Pedido') . " #{$pedidoId})",
            __('admin.installment.email_detail_installment', 'Parcela') => __('admin.installment.email_installment_of', '{n} de {total}', ['n' => $numeroParcela, 'total' => $totalParcelas]),
            __('admin.installment.email_detail_due', 'Vencimento') => $vencimento,
            __('admin.installment.email_detail_products', 'Produtos') => "R$ {$valorProdutos}",
            __('admin.installment.email_detail_fees', 'Taxas') => "R$ {$valorTaxas}",
            __('admin.installment.email_detail_total', 'Total da parcela') => "R$ {$valorTotal}",
        ];
        $alerta = ($statusParcela === 'em_atraso' || $statusParcela === 'vencida') ? 'danger' : 'warning';
        $alertaMensagem = ($statusParcela === 'em_atraso' || $statusParcela === 'vencida')
            ? '<strong>⚠️ ' . __('admin.installment.email_attention', 'Atenção:') . '</strong> ' . __('admin.installment.email_overdue_notice', 'Esta parcela está <strong>{status}</strong>. Regularize o pagamento para evitar o cancelamento do seu carnê.', ['status' => ($statusParcela === 'em_atraso' ? __('admin.installment.status_late', 'em atraso') : __('admin.installment.status_expired', 'vencida'))])
            : '<strong>⏰ ' . __('admin.installment.email_reminder', 'Lembrete:') . '</strong> ' . __('admin.installment.email_pay_notice', 'Realize o pagamento da sua parcela.');
        $ctaTexto = __('admin.installment.email_cta', 'Ver meu carnê e pagar');

        ob_start();
        include __DIR__ . '/../Views/emails/carne-notificacao.php';
        $htmlEmail = ob_get_clean();

        // Enviar email via EmailService
        try {
            $emailService = new \App\Services\EmailService();
            $emailService->send($destinatarioEmail, $assunto, $htmlEmail);
        } catch (\Exception $e) {
            $status = 'erro';
            $erroMsg = $e->getMessage();
            error_log('[CARNE] Erro ao enviar email cobrança parcela #' . $parcelaId . ': ' . $e->getMessage());
        }

        // Registrar no histórico do carnê
        $this->carneModel->registrarHistorico($carneId, $parcelaId, 'cobranca_enviada',
            "Email de cobrança " . ($status === 'enviado' ? 'enviado' : 'falhou') . " para parcela #{$numeroParcela}",
            null, $_SESSION['usuario_id'] ?? null);

        // Registrar na tabela email_logs
        try {
            $corpoResumo = "Cobrança da parcela {$numeroParcela}/{$totalParcelas} no valor de R$ {$valorTotal} com vencimento em {$vencimento}";

            $stmt = $this->db->prepare("
                INSERT INTO email_logs (tipo, destinatario_email, destinatario_nome, assunto, corpo_resumo, status, erro_mensagem, carne_id, parcela_id, pedido_id, created_at)
                VALUES (:tipo, :email, :nome, :assunto, :corpo, :status, :erro, :carne_id, :parcela_id, :pedido_id, NOW())
            ");
            $stmt->execute([
                ':tipo' => 'cobranca',
                ':email' => $destinatarioEmail,
                ':nome' => $destinatarioNome,
                ':assunto' => $assunto,
                ':corpo' => $corpoResumo,
                ':status' => $status,
                ':erro' => $erroMsg,
                ':carne_id' => $carneId,
                ':parcela_id' => $parcelaId,
                ':pedido_id' => $pedidoId ?: null,
            ]);
        } catch (\Exception $e) {
            error_log('[EMAIL_LOG] Erro ao registrar log: ' . $e->getMessage());
        }

        if ($status === 'enviado') {
            $_SESSION['message'] = __('admin.installment.billing_email_sent', 'Email de cobrança enviado com sucesso para ') . htmlspecialchars($destinatarioEmail) . '.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = __('admin.installment.billing_email_error', 'Erro ao enviar email de cobrança: ') . ($erroMsg ?: __('admin.installment.unknown_error', 'erro desconhecido'));
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * Arquivar/desarquivar um carnê manualmente
     */
    public function arquivar(Request $request, $id) {
        $auth = new \App\Services\AuthService();
        $auth->requerPerfis(['admin']);
        $id = (int) $id;
        $arquivar = (int) ($request->getParam('arquivar', 1));

        // Arquivar carnê
        $this->db->prepare("UPDATE carnes SET arquivado = ? WHERE id = ?")->execute([$arquivar, $id]);

        // Arquivar/desarquivar pedido associado
        $stPed = $this->db->prepare("SELECT pedido_id FROM carnes WHERE id = ? LIMIT 1");
        $stPed->execute([$id]);
        $pedidoId = (int) $stPed->fetchColumn();
        if ($pedidoId > 0) {
            $this->db->prepare("UPDATE pedidos SET arquivado = ? WHERE id = ?")->execute([$arquivar, $pedidoId]);
        }

        $_SESSION['message'] = $arquivar ? 'Carnê arquivado com sucesso.' : 'Carnê desarquivado.';
        $_SESSION['message_type'] = 'success';
        $this->redirect($arquivar ? '/admin/carnes' : '/admin/carnes/arquivados');
    }

    /**
     * Listagem de carnês arquivados
     */
    public function arquivados(Request $request) {
        $auth = new \App\Services\AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $carnes = $this->carneModel->listarAdmin(['incluir_arquivados' => true, 'status' => 'cancelado']);

        // Filtrar apenas os arquivados
        $carnes = array_filter($carnes, fn($c) => !empty($c['arquivado']));

        $title = __('admin.installment.archived_title', 'Carnês Arquivados');
        $sidebarActive = 'carnes';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/carne/arquivados.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }
}
