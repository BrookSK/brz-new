<?php
namespace App\Controllers;

use App\Models\PedidoInvoice;
use App\Models\PedidoInvoiceItem;
use App\Models\PacoteRecebido;
use App\Services\AuthService;
use App\Core\Request;

class InvoiceController extends Controller {
    private $invoiceModel;
    private $invoiceItemModel;
    private $connection;
    private $authService;

    public function __construct() {
        $this->authService = new AuthService();
        $this->invoiceModel = new PedidoInvoice();
        $this->invoiceItemModel = new PedidoInvoiceItem();
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Tela do cliente para conferir invoice
     * GET /minha-conta/invoice?pedido_id=X
     */
    public function conferir(Request $request): void {
        $usuario = $this->authService->getUsuarioLogado();
        if (!$usuario || empty($usuario['id'])) {
            $this->redirect('/login');
            return;
        }

        $pedidoId = (int) ($request->getParams()['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->redirect('/meus-pedidos');
            return;
        }

        // Verificar se o pedido pertence ao usuario
        $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$pedidoId, $usuario['id']]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido) {
            $this->redirect('/meus-pedidos');
            return;
        }

        // Verificar se status permite acesso (somente invoice_liberado)
        if ($pedido['status'] !== 'invoice_liberado') {
            $this->setFlash('Este pedido não está disponível para conferência de invoice.', 'warning');
            $this->redirect('/meus-pedidos');
            return;
        }

        // Buscar invoice
        $invoice = $this->invoiceModel->getByPedido($pedidoId);
        if (!$invoice || $invoice['status'] !== 'liberado') {
            $this->setFlash('Invoice não encontrado ou já processado.', 'warning');
            $this->redirect('/meus-pedidos');
            return;
        }

        // Buscar itens do invoice
        $itens = $this->invoiceItemModel->getByInvoice((int) $invoice['id']);

        // Buscar endereço de entrega do pedido
        $endereco = $this->getEnderecoEntrega($pedido);

        // NCM options para referência
        $ncmOptions = PacoteRecebido::getNcmOptions();

        $this->view('cliente.invoice', compact(
            'pedido', 'invoice', 'itens', 'endereco', 'usuario', 'ncmOptions'
        ));
    }

    /**
     * Finalizar invoice (confirmar dados)
     * POST /minha-conta/invoice/finalizar
     */
    public function finalizar(Request $request): void {
        $usuario = $this->authService->getUsuarioLogado();
        if (!$usuario || empty($usuario['id'])) {
            $this->json(['success' => false, 'message' => 'Não autorizado'], 401);
            return;
        }

        $params = $request->getParams();
        $invoiceId = (int) ($params['invoice_id'] ?? 0);
        $pedidoId = (int) ($params['pedido_id'] ?? 0);

        if ($invoiceId <= 0 || $pedidoId <= 0) {
            $this->setFlash('Dados inválidos.', 'danger');
            $this->redirect('/meus-pedidos');
            return;
        }

        // Verificar propriedade
        $stmt = $this->connection->prepare('SELECT id FROM pedidos WHERE id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$pedidoId, $usuario['id']]);
        if (!$stmt->fetchColumn()) {
            $this->redirect('/meus-pedidos');
            return;
        }

        // Verificar invoice
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice || $invoice['pedido_id'] != $pedidoId || $invoice['status'] !== 'liberado') {
            $this->setFlash('Invoice não pode ser finalizado.', 'danger');
            $this->redirect('/meus-pedidos');
            return;
        }

        // Atualizar itens do invoice (editados pelo cliente)
        $itens = $params['itens'] ?? [];
        if (is_array($itens)) {
            foreach ($itens as $itemId => $dados) {
                $itemId = (int) $itemId;
                if ($itemId <= 0) continue;

                $updateData = [];
                if (isset($dados['nome_produto'])) {
                    $updateData['nome_produto'] = trim($dados['nome_produto']);
                }
                if (isset($dados['declaration_value'])) {
                    $val = (float) $dados['declaration_value'];
                    // Verificar limite: valor não pode ser maior que o original
                    $itemOriginal = $this->invoiceItemModel->find($itemId);
                    if ($itemOriginal && $val <= (float) $itemOriginal['declaration_value']) {
                        $updateData['declaration_value'] = $val;
                    }
                }
                if (isset($dados['tem_bateria'])) {
                    $updateData['tem_bateria'] = ($dados['tem_bateria'] === 'S') ? 'S' : 'N';
                }
                if (isset($dados['tem_perfume'])) {
                    $updateData['tem_perfume'] = ($dados['tem_perfume'] === 'S') ? 'S' : 'N';
                }
                if (isset($dados['ncm']) && trim($dados['ncm']) !== '') {
                    $updateData['ncm'] = trim($dados['ncm']);
                }

                if (!empty($updateData)) {
                    $this->invoiceItemModel->update($itemId, $updateData);
                }
            }
        }

        // Atualizar endereço de entrega se editado
        $enderecoNovo = $params['endereco'] ?? [];
        if (is_array($enderecoNovo) && !empty($enderecoNovo['rua'])) {
            $this->atualizarEnderecoEntrega($pedidoId, $enderecoNovo);
        }

        // Confirmar invoice
        $this->invoiceModel->confirmar($invoiceId);

        // Atualizar status do pedido
        $stPedido = $this->connection->prepare('UPDATE pedidos SET status = ? WHERE id = ?');
        $stPedido->execute(['invoice_confirmado', $pedidoId]);

        // Atualizar status dos pacotes vinculados
        $this->atualizarStatusPacotes($pedidoId, 'invoice_confirmado');

        // Notificar admins
        $this->notificarAdmins($pedidoId, $usuario, 'confirmado');

        $this->setFlash('Invoice confirmado com sucesso! Seus dados foram salvos para a etiqueta de envio.', 'success');
        $this->redirect('/meus-pedidos');
    }

    /**
     * Contestar invoice
     * POST /minha-conta/invoice/contestar
     */
    public function contestar(Request $request): void {
        $usuario = $this->authService->getUsuarioLogado();
        if (!$usuario || empty($usuario['id'])) {
            $this->redirect('/login');
            return;
        }

        $params = $request->getParams();
        $invoiceId = (int) ($params['invoice_id'] ?? 0);
        $pedidoId = (int) ($params['pedido_id'] ?? 0);
        $motivo = trim($params['motivo'] ?? '');

        if ($invoiceId <= 0 || $pedidoId <= 0 || $motivo === '') {
            $this->setFlash('Informe o motivo da contestação.', 'danger');
            $this->redirect('/minha-conta/invoice?pedido_id=' . $pedidoId);
            return;
        }

        // Verificar propriedade
        $stmt = $this->connection->prepare('SELECT id FROM pedidos WHERE id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$pedidoId, $usuario['id']]);
        if (!$stmt->fetchColumn()) {
            $this->redirect('/meus-pedidos');
            return;
        }

        // Verificar invoice
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice || $invoice['pedido_id'] != $pedidoId || $invoice['status'] !== 'liberado') {
            $this->setFlash('Invoice não pode ser contestado.', 'danger');
            $this->redirect('/meus-pedidos');
            return;
        }

        // Contestar
        $this->invoiceModel->contestar($invoiceId, $motivo);

        // Atualizar status do pedido
        $stPedido = $this->connection->prepare('UPDATE pedidos SET status = ? WHERE id = ?');
        $stPedido->execute(['invoice_contestado', $pedidoId]);

        // Atualizar status dos pacotes vinculados
        $this->atualizarStatusPacotes($pedidoId, 'invoice_contestado');

        // Notificar admins
        $this->notificarAdmins($pedidoId, $usuario, 'contestado', $motivo);

        $this->setFlash('Invoice contestado. Nossa equipe irá analisar e ajustar.', 'info');
        $this->redirect('/meus-pedidos');
    }

    // ==================== Métodos privados ====================

    private function getEnderecoEntrega(array $pedido): array {
        // Tentar buscar de endereco_entrega JSON ou campos individuais
        $endereco = [];

        // Tentar buscar da tabela enderecos vinculada ao pedido
        try {
            $cols = [];
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (in_array('endereco_entrega', $cols, true) && !empty($pedido['endereco_entrega'])) {
                $decoded = json_decode($pedido['endereco_entrega'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            // Campos diretos no pedido
            $enderecoFields = ['rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep', 'pais'];
            $prefixes = ['entrega_', 'endereco_', ''];
            foreach ($prefixes as $prefix) {
                $found = false;
                foreach ($enderecoFields as $f) {
                    $key = $prefix . $f;
                    if (in_array($key, $cols, true) && !empty($pedido[$key])) {
                        $endereco[$f] = $pedido[$key];
                        $found = true;
                    }
                }
                if ($found) break;
            }
        } catch (\Throwable $e) {
        }

        return $endereco;
    }

    private function atualizarEnderecoEntrega(int $pedidoId, array $endereco): void {
        try {
            $cols = [];
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (in_array('endereco_entrega', $cols, true)) {
                $json = json_encode($endereco, JSON_UNESCAPED_UNICODE);
                $st = $this->connection->prepare('UPDATE pedidos SET endereco_entrega = ? WHERE id = ?');
                $st->execute([$json, $pedidoId]);
            }
        } catch (\Throwable $e) {
            error_log('[Invoice] Erro ao atualizar endereço: ' . $e->getMessage());
        }
    }

    private function atualizarStatusPacotes(int $pedidoId, string $status): void {
        try {
            $st = $this->connection->prepare('UPDATE pacotes_recebidos SET status = ? WHERE pedido_id = ?');
            $st->execute([$status, $pedidoId]);
        } catch (\Throwable $e) {
            error_log('[Invoice] Erro ao atualizar pacotes: ' . $e->getMessage());
        }
    }

    /**
     * Notificar todos os admins/vendedores sobre ação no invoice
     */
    private function notificarAdmins(int $pedidoId, array $cliente, string $acao, string $motivo = ''): void {
        try {
            // Buscar todos os admins/vendedores
            $st = $this->connection->prepare("SELECT email, nome FROM usuarios WHERE perfil IN ('admin', 'vendedor') AND email IS NOT NULL AND email != ''");
            $st->execute();
            $admins = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            if (empty($admins)) return;

            $nomeCliente = htmlspecialchars($cliente['nome'] ?? 'Cliente');
            $emailCliente = htmlspecialchars($cliente['email'] ?? '');
            $linkPedido = 'https://brazilianashop.com.br/admin/pedidos/editar/' . $pedidoId;

            if ($acao === 'confirmado') {
                $assunto = 'Invoice Confirmado - Pedido #' . $pedidoId;
                $corpo = '<h2 style="color:#28a745;">Invoice Confirmado</h2>'
                    . '<p>O cliente <strong>' . $nomeCliente . '</strong> (' . $emailCliente . ') confirmou os dados do invoice do pedido <strong>#' . $pedidoId . '</strong>.</p>'
                    . '<p><strong>O pedido está pronto para gerar etiqueta.</strong></p>';
            } else {
                $assunto = 'Invoice Contestado - Pedido #' . $pedidoId;
                $motivoHtml = htmlspecialchars($motivo);
                $corpo = '<h2 style="color:#dc3545;">Invoice Contestado</h2>'
                    . '<p>O cliente <strong>' . $nomeCliente . '</strong> (' . $emailCliente . ') contestou o invoice do pedido <strong>#' . $pedidoId . '</strong>.</p>'
                    . '<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;padding:15px;margin:15px 0;"><strong>Motivo:</strong><br>' . $motivoHtml . '</div>'
                    . '<p>Ajuste os dados e re-libere o invoice.</p>';
            }

            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;">'
                . '<div style="background:#1a3a5c;padding:20px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:22px;">Braziliana</h1></div>'
                . '<div style="padding:30px 20px;">' . $corpo
                . '<p style="text-align:center;margin-top:25px;"><a href="' . $linkPedido . '" style="background:#1a3a5c;color:#fff;text-decoration:none;padding:12px 30px;border-radius:5px;display:inline-block;">Ver Pedido no Admin</a></p>'
                . '</div></body></html>';

            $emailService = new \App\Services\EmailService();
            foreach ($admins as $admin) {
                try {
                    $emailService->send(
                        $admin['email'],
                        $assunto,
                        $html,
                        'invoice_' . $acao . '_admin_' . $pedidoId . '_' . md5($admin['email']),
                        ['evento' => 'invoice_' . $acao . '_admin', 'pedido_id' => $pedidoId]
                    );
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            error_log('[Invoice] Erro ao notificar admins: ' . $e->getMessage());
        }
    }

    private function setFlash(string $message, string $type = 'info'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
}
