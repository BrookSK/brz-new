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
                'carne_ativo', 'carne_somente_admin', 'carne_max_parcelas', 'carne_dias_vencimento',
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
}
