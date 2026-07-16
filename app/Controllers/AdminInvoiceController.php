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
            $this->setFlash('Pedido inválido.', 'danger');
            $this->redirect('/admin/pedidos');
            return;
        }

        // Verificar se pedido existe
        $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido) {
            $this->setFlash('Pedido não encontrado.', 'danger');
            $this->redirect('/admin/pedidos');
            return;
        }

        // Verificar se já existe invoice ativo
        $invoiceExistente = $this->invoiceModel->getByPedido($pedidoId);
        if ($invoiceExistente && $invoiceExistente['status'] === 'liberado') {
            $this->setFlash('Este pedido já tem um invoice liberado.', 'warning');
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
                $this->setFlash('Pedido não possui itens.', 'danger');
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

        $this->setFlash('Invoice liberado com sucesso. O cliente pode agora conferir os dados.', 'success');
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
                    'nome_produto' => $row['nome'] ?? $row['produto_nome'] ?? $row['nome_produto'] ?? 'Produto',
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
                            if (empty($item['nome_produto']) || $item['nome_produto'] === 'Produto') {
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
}
