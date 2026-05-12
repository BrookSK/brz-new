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

        $title = 'Gestão de Carnês';
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
            $_SESSION['message'] = 'Carnê não encontrado.';
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

        $title = 'Compras Carnê — Mensal';
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
            $_SESSION['message'] = 'Parcela não encontrada.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
        }

        $this->carneModel->atualizarParcela($parcelaId, [
            'reemissao_count' => $parcela['reemissao_count'] + 1,
            'status' => 'reemitida'
        ]);

        $this->carneModel->registrarHistorico($parcela['carne_id'], $parcelaId, 'boleto_reemitido',
            "Boleto reemitido pelo admin", null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = 'Boleto reemitido com sucesso.';
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

        $stmt = $this->db->prepare("SELECT carne_id FROM carne_compras_internas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $carneId = $stmt->fetchColumn();

        $this->carneModel->registrarHistorico($carneId, null, 'produto_comprado',
            'Produto marcado como comprado internamente', null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = 'Produto marcado como comprado.';
        $_SESSION['message_type'] = 'success';
        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }

    /**
     * Desfazer status da compra interna (voltar para aguardando_compra)
     */
    public function desfazerCompra(Request $request, $id) {
        $stmt = $this->db->prepare("SELECT carne_id, status FROM carne_compras_internas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $_SESSION['message'] = 'Registro não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes/compras-internas');
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET status = 'aguardando_compra', comprado_em = NULL, recebido_em = NULL WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        $carneId = (int) ($row['carne_id'] ?? 0);
        $this->carneModel->registrarHistorico($carneId, null, 'compra_desfeita',
            'Status da compra interna revertido para aguardando_compra', null, $_SESSION['usuario_id'] ?? null);

        $_SESSION['message'] = 'Status revertido para aguardando compra.';
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

        $_SESSION['message'] = 'Produto marcado como recebido.';
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
            $_SESSION['message'] = 'Crédito em carteira gerado com sucesso.';
        } else {
            $_SESSION['message'] = 'Ação inválida.';
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
            if (!$carne) { echo json_encode(['success' => false, 'error' => 'Carnê não encontrado']); return; }

            $body = $request->getBody();
            $valor = floatval($body['valor'] ?? 0);
            $obs = trim((string) ($body['observacoes'] ?? ''));
            if ($valor <= 0) { echo json_encode(['success' => false, 'error' => 'Valor deve ser maior que zero']); return; }

            $pedidoId = (int) $carne['pedido_id'];
            $adminId = (int) ($_SESSION['usuario_id'] ?? 0);
            $descricao = "Diferença Carnê #$id - Pedido #$pedidoId";

            $svc = new \App\Services\PaymentLinkService();
            $result = $svc->createLink([
                'currency' => 'BRL',
                'products' => [['name' => $descricao, 'value' => $valor]],
                'taxa_servico_valor' => 0, 'impostos_valor' => 0, 'descricao' => $descricao,
            ], $adminId);

            if (empty($result['success'])) { echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Falha ao criar link']); return; }

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
            $_SESSION['message'] = 'Carnê precisa estar quitado para liberar envio.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect("/admin/carnes/detalhes/{$id}");
        }

        $this->carneModel->update($id, ['envio_liberado' => 1, 'status' => 'liberado_envio']);
        $this->carneModel->registrarHistorico($id, null, 'envio_liberado',
            'Envio liberado pelo admin', null, $_SESSION['usuario_id'] ?? null);

        $this->carneService->dispararNotificacao($id, null, 'envio_liberado');

        $_SESSION['message'] = 'Envio liberado com sucesso.';
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

        $_SESSION['message'] = 'Notificação reenviada.';
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
            $_SESSION['message'] = 'Configurações salvas.';
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
        if ($taxaConv <= 1.01) $taxaConv = 5.85;

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

        $title = 'Compras do Carnê';
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
            $_SESSION['message'] = 'Parcela não encontrada.';
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
            $_SESSION['message'] = 'ID do pedido inválido.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        // Verificar se já existe carnê para esse pedido
        $stCheck = $this->db->prepare('SELECT id FROM carnes WHERE pedido_id = ? LIMIT 1');
        $stCheck->execute([$pedidoId]);
        if ($stCheck->fetchColumn()) {
            $_SESSION['message'] = 'Já existe um carnê para o pedido #' . $pedidoId . '.';
            $_SESSION['message_type'] = 'warning';
            $this->redirect('/admin/carnes');
            return;
        }

        // Buscar dados do pedido
        $stPed = $this->db->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
        $stPed->execute([$pedidoId]);
        $pedido = $stPed->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            $_SESSION['message'] = 'Pedido #' . $pedidoId . ' não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $clienteId = (int) ($pedido['usuario_id'] ?? 0);
        if ($clienteId <= 0) {
            $_SESSION['message'] = 'Pedido #' . $pedidoId . ' não tem cliente vinculado.';
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
        if ($taxaConv <= 1.01) $taxaConv = 5.85;

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
            $_SESSION['message'] = 'Pedido #' . $pedidoId . ' tem valores zerados. Não é possível criar carnê.';
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

            $_SESSION['message'] = 'Carnê criado com sucesso para o pedido #' . $pedidoId . ' (ID: ' . $carneId . ', ' . $qtdParcelas . ' parcelas, Produtos: R$ ' . number_format($subtotalProdutos, 2, ',', '.') . ', Taxas: R$ ' . number_format($totalTaxas, 2, ',', '.') . ')';
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
            $_SESSION['message'] = 'Erro ao criar carnê: ' . $e->getMessage();
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
            $_SESSION['message'] = 'Parcela não encontrada.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
            return;
        }

        $carneId = (int) $parcela['carne_id'];
        $carne = $this->carneModel->getCompleto($carneId);
        if (!$carne) {
            $_SESSION['message'] = 'Carnê não encontrado.';
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

        $assunto = "Cobrança - Parcela {$numeroParcela}/{$totalParcelas} do Carnê #{$carneId}";
        if ($statusParcela === 'em_atraso' || $statusParcela === 'vencida') {
            $assunto = "⚠️ Parcela em atraso - {$numeroParcela}/{$totalParcelas} do Carnê #{$carneId}";
        }

        // Renderizar template HTML
        $titulo = $assunto;
        $mensagem = 'Estamos entrando em contato para lembrar sobre o pagamento da parcela do seu carnê.';
        $detalhes = [
            'Carnê' => "#{$carneId} (Pedido #{$pedidoId})",
            'Parcela' => "{$numeroParcela} de {$totalParcelas}",
            'Vencimento' => $vencimento,
            'Produtos' => "R$ {$valorProdutos}",
            'Taxas' => "R$ {$valorTaxas}",
            'Total da parcela' => "R$ {$valorTotal}",
        ];
        $alerta = ($statusParcela === 'em_atraso' || $statusParcela === 'vencida') ? 'danger' : 'warning';
        $alertaMensagem = ($statusParcela === 'em_atraso' || $statusParcela === 'vencida')
            ? '<strong>⚠️ Atenção:</strong> Esta parcela está <strong>' . ($statusParcela === 'em_atraso' ? 'em atraso' : 'vencida') . '</strong>. Regularize o pagamento para evitar o cancelamento do seu carnê.'
            : '<strong>⏰ Lembrete:</strong> Realize o pagamento da sua parcela.';
        $ctaTexto = 'Ver meu carnê e pagar';

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
            $_SESSION['message'] = 'Email de cobrança enviado com sucesso para ' . htmlspecialchars($destinatarioEmail) . '.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Erro ao enviar email de cobrança: ' . ($erroMsg ?: 'erro desconhecido');
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect("/admin/carnes/detalhes/{$carneId}");
    }
}
