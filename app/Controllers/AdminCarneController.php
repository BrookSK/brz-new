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
     * Listagem de carnês
     */
    public function index(Request $request) {
        $filtros = [
            'status' => $request->getParam('status', ''),
            'cliente' => $request->getParam('cliente', ''),
            'pedido_id' => $request->getParam('pedido_id', ''),
            'com_atraso' => $request->getParam('com_atraso', ''),
            'liberado_compra' => $request->getParam('liberado_compra', ''),
            'liberado_envio' => $request->getParam('liberado_envio', '')
        ];

        $carnes = $this->carneModel->listarAdmin($filtros);
        require __DIR__ . '/../Views/admin/carne/index.php';
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

        echo json_encode([
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
        $qtdParcelas = (int) $request->getParam('quantidade_parcelas', 4);
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
            $this->redirect('/admin/carnes/detalhes/' . $carneId);
        } catch (\Exception $e) {
            error_log('[ADMIN CARNE] Erro ao recriar carnê pedido #' . $pedidoId . ': ' . $e->getMessage());
            $_SESSION['message'] = 'Erro ao criar carnê: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/carnes');
        }
    }
}
