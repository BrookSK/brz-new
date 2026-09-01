<?php
namespace App\Controllers;

use App\Models\PedidoInvoice;
use App\Models\PedidoInvoiceItem;
use App\Models\PacoteRecebido;
use App\Services\AuthService;
use App\Core\Request;

/**
 * Controller admin para ações de invoice em pedidos
 * (Liberar, re-liberar, ver detalhes da contestação)
 */
class AdminInvoiceController extends Controller {
    private $invoiceModel;
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->invoiceModel = new PedidoInvoice();
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Liberar invoice de um pedido
     * POST /admin/pedidos/{id}/liberar-invoice
     */
    public function liberar(Request $request): void {
        $pedidoId = (int) ($request->getParams()['id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->setFlash(__('admin.invoice.invalid_order', 'Pedido inválido.'), 'danger');
            $this->redirect('/admin/pedidos');
            return;
        }

        // Verificar se pedido existe
        $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido) {
            $this->setFlash(__('admin.invoice.order_not_found', 'Pedido não encontrado.'), 'danger');
            $this->redirect('/admin/pedidos');
            return;
        }

        // Verificar se já existe invoice ativo
        $invoiceExistente = $this->invoiceModel->getByPedido($pedidoId);
        if ($invoiceExistente && $invoiceExistente['status'] === 'liberado') {
            $this->setFlash(__('admin.invoice.already_released', 'Este pedido já tem um invoice liberado.'), 'warning');
            $this->redirect('/admin/pedidos/' . $pedidoId);
            return;
        }

        // Se existe invoice contestado, re-liberar
        if ($invoiceExistente && $invoiceExistente['status'] === 'contestado') {
            $this->invoiceModel->reliberar((int) $invoiceExistente['id']);
        } else {
            // Buscar itens do pedido para copiar ao invoice
            $itens = $this->getItensPedido($pedidoId);
            if (empty($itens)) {
                $this->setFlash(__('admin.invoice.order_no_items', 'Pedido não possui itens.'), 'danger');
                $this->redirect('/admin/pedidos/' . $pedidoId);
                return;
            }
            $this->invoiceModel->liberarInvoice($pedidoId, $itens);
        }

        // Atualizar status do pedido
        $stPedido = $this->connection->prepare('UPDATE pedidos SET status = ? WHERE id = ?');
        $stPedido->execute(['invoice_liberado', $pedidoId]);

        // Atualizar status dos pacotes vinculados
        try {
            $stPacotes = $this->connection->prepare('UPDATE pacotes_recebidos SET status = ? WHERE pedido_id = ?');
            $stPacotes->execute(['invoice_liberado', $pedidoId]);
        } catch (\Throwable $e) {
        }

        // Enviar e-mail ao cliente informando que o invoice foi liberado
        $this->enviarEmailInvoiceLiberado($pedido, $pedidoId);

        $this->setFlash(__('admin.invoice.released_success', 'Invoice liberado com sucesso. O cliente pode agora conferir os dados.'), 'success');
        $this->redirect('/admin/pedidos/' . $pedidoId);
    }

    // ==================== Métodos privados ====================

    /**
     * Buscar itens do pedido para copiar ao invoice
     */
    private function getItensPedido(int $pedidoId): array {
        $itensTable = $this->getItensTable();
        $itens = [];

        try {
            $stmt = $this->connection->prepare("SELECT * FROM {$itensTable} WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $item = [
                    'pedido_item_id' => $row['id'] ?? null,
                    'pacote_id' => $row['pacote_id'] ?? null,
                    'nome_produto' => $row['nome'] ?? $row['produto_nome'] ?? $row['nome_produto'] ?? __('admin.invoice.product_default', 'Produto'),
                    'ncm' => $row['ncm'] ?? null,
                    'declaration_value' => $row['declaration_value'] ?? $row['valor_unitario'] ?? $row['preco_unitario'] ?? 0,
                    'peso_kg' => $row['peso_kg'] ?? $row['peso'] ?? 0,
                    'quantidade' => $row['quantidade'] ?? 1,
                    'tem_bateria' => $row['tem_bateria'] ?? 'N',
                    'tem_perfume' => $row['tem_perfume'] ?? 'N',
                    'foto_url' => $row['foto_url'] ?? null,
                ];

                // Se tem pacote_id, buscar dados complementares do pacote
                if (!empty($item['pacote_id'])) {
                    try {
                        $stPacote = $this->connection->prepare('SELECT nome, ncm, foto_url, peso_kg FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                        $stPacote->execute([$item['pacote_id']]);
                        $pacote = $stPacote->fetch(\PDO::FETCH_ASSOC);
                        if ($pacote) {
                            if (empty($item['nome_produto']) || $item['nome_produto'] === __('admin.invoice.product_default', 'Produto')) {
                                $item['nome_produto'] = $pacote['nome'];
                            }
                            if (empty($item['ncm'])) {
                                $item['ncm'] = $pacote['ncm'];
                            }
                            if (empty($item['foto_url'])) {
                                $item['foto_url'] = $pacote['foto_url'];
                            }
                            if (empty($item['peso_kg']) || (float) $item['peso_kg'] <= 0) {
                                $item['peso_kg'] = $pacote['peso_kg'];
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }

                $itens[] = $item;
            }
        } catch (\Throwable $e) {
            error_log('[AdminInvoice] Erro ao buscar itens: ' . $e->getMessage());
        }

        return $itens;
    }

    /**
     * Determinar nome da tabela de itens
     */
    private function getItensTable(): string {
        try {
            $stmt = $this->connection->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedido_itens' LIMIT 1");
            $stmt->execute();
            if ($stmt->fetchColumn()) return 'pedido_itens';
        } catch (\Throwable $e) {
        }
        try {
            $stmt = $this->connection->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedido_items' LIMIT 1");
            $stmt->execute();
            if ($stmt->fetchColumn()) return 'pedido_items';
        } catch (\Throwable $e) {
        }
        return 'pedido_itens';
    }

    private function setFlash(string $message, string $type = 'info'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }

    /**
     * Enviar e-mail ao cliente informando que o invoice foi liberado para conferência
     */
    private function enviarEmailInvoiceLiberado(array $pedido, int $pedidoId): void {
        try {
            $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
            if ($usuarioId <= 0) return;

            $stUser = $this->connection->prepare('SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1');
            $stUser->execute([$usuarioId]);
            $usuario = $stUser->fetch(\PDO::FETCH_ASSOC);
            if (!$usuario || empty($usuario['email'])) return;

            $nome = htmlspecialchars($usuario['nome'] ?? __('admin.invoice.email_customer', 'Cliente'));
            $linkInvoice = 'https://brazilianashop.com.br/minha-conta/invoice?pedido_id=' . $pedidoId;

            $emailTitle = __('admin.invoice.email_title', 'Confira os dados do seu envio');
            $emailGreeting = __('admin.invoice.email_greeting', 'Olá, ');
            $emailIntro = __('admin.invoice.email_intro', 'O invoice do seu pedido <strong>#{id}</strong> foi liberado para conferência.', ['id' => $pedidoId]);
            $emailBefore = __('admin.invoice.email_before_ship', 'Antes de enviarmos seu pacote, precisamos que você confira e confirme os dados que irão na etiqueta de envio (declaração aduaneira).');
            $emailWhatToDo = __('admin.invoice.email_what_to_do', 'O que você precisa fazer:');
            $emailItem1 = __('admin.invoice.email_item_name', 'Conferir o nome de cada produto (será usado na etiqueta)');
            $emailItem2 = __('admin.invoice.email_item_values', 'Verificar os valores declarados');
            $emailItem3 = __('admin.invoice.email_item_battery', 'Informar se contém bateria ou perfume/líquido');
            $emailItem4 = __('admin.invoice.email_item_address', 'Confirmar o endereço de entrega');
            $emailButton = __('admin.invoice.email_button', 'Conferir Invoice Agora');
            $emailImportant = __('admin.invoice.email_important', 'Importante:');
            $emailImportantText = __('admin.invoice.email_important_text', ' Seu pacote só será enviado após a confirmação dos dados. Se houver algo incorreto, você pode contestar e nossa equipe ajustará.');
            $emailFooter = __('admin.invoice.email_footer', 'Braziliana - Seu parceiro em compras internacionais');
            $emailSubject = __('admin.invoice.email_subject', 'Confira os dados do seu envio - Pedido #{id}', ['id' => $pedidoId]);

            $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #1a3a5c; padding: 20px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Braziliana</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #1a3a5c;">{$emailTitle}</h2>
        <p>{$emailGreeting}<strong>{$nome}</strong>!</p>
        <p>{$emailIntro}</p>
        <p>{$emailBefore}</p>
        
        <div style="background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <strong>{$emailWhatToDo}</strong><br>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>{$emailItem1}</li>
                <li>{$emailItem2}</li>
                <li>{$emailItem3}</li>
                <li>{$emailItem4}</li>
            </ul>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="{$linkInvoice}" style="background: #1a3a5c; color: #fff; text-decoration: none; padding: 14px 35px; border-radius: 5px; display: inline-block; font-size: 16px;">
                {$emailButton}
            </a>
        </p>

        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 25px 0;">
            <strong>{$emailImportant}</strong>{$emailImportantText}
        </div>
    </div>
    <div style="background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666;">
        {$emailFooter}
    </div>
</body></html>
HTML;

            $emailService = new \App\Services\EmailService();
            $emailService->send(
                $usuario['email'],
                $emailSubject,
                $html,
                'invoice_liberado_' . $pedidoId,
                [
                    'evento' => 'invoice_liberado',
                    'to_email' => $usuario['email'],
                    'subject' => $emailSubject,
                    'pedido_id' => $pedidoId,
                    'usuario_id' => $usuarioId,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[AdminInvoice] Erro ao enviar e-mail invoice liberado: ' . $e->getMessage());
        }
    }
}
