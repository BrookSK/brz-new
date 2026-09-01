<?php
namespace App\Controllers;

use App\Models\PedidoFaturaAdicional;
use App\Services\AuthService;
use App\Core\Request;

class AdminFaturasAdicionaisController extends Controller {
    private $model;
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->model = new PedidoFaturaAdicional();
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Listagem com filtros e paginação
     */
    public function index(Request $request): void {
        $filtros = [
            'pedido_id' => $request->getParams()['pedido_id'] ?? '',
            'status' => $request->getParams()['status'] ?? '',
        ];
        $pagina = (int) ($request->getParams()['pagina'] ?? 1);
        if ($pagina < 1) $pagina = 1;

        $resultado = $this->model->listar($filtros, $pagina);

        $faturas = $resultado['registros'];
        $total = $resultado['total'];
        $totalPaginas = $resultado['total_paginas'];
        $pedido_id = $filtros['pedido_id'];
        $status = $filtros['status'];

        $this->view('admin.faturas-adicionais.index', compact(
            'faturas', 'total', 'totalPaginas', 'pagina',
            'pedido_id', 'status'
        ));
    }

    /**
     * Criar fatura adicional
     */
    public function criar(Request $request): void {
        $params = $request->getParams();
        $pedidoId = (int) ($params['pedido_id'] ?? 0);
        $motivo = trim($params['motivo'] ?? '');
        $valor = (float) ($params['valor'] ?? 0);
        $descricao = trim($params['descricao'] ?? '');

        if ($pedidoId <= 0 || $motivo === '' || $valor <= 0) {
            $this->setFlash(__('admin.additional_invoices.fill_required_fields', 'Preencha todos os campos obrigatórios.'), 'danger');
            $this->redirect('/admin/faturas-adicionais');
            return;
        }

        // Verificar se pedido existe
        $stmt = $this->connection->prepare('SELECT id, usuario_id, status FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            $this->setFlash(__('admin.additional_invoices.order_not_found', 'Pedido não encontrado.'), 'danger');
            $this->redirect('/admin/faturas-adicionais');
            return;
        }

        // Criar a fatura adicional
        $faturaId = $this->model->create([
            'pedido_id' => $pedidoId,
            'motivo' => $motivo,
            'valor' => $valor,
            'descricao' => $descricao,
            'status' => 'pendente',
        ]);

        // Atualizar status do pedido para fatura_pendente
        $stUpdate = $this->connection->prepare('UPDATE pedidos SET status = ? WHERE id = ?');
        $stUpdate->execute(['fatura_pendente', $pedidoId]);

        // Adicionar item ao carrinho do cliente automaticamente
        $this->adicionarFaturaAoCarrinho((int) $pedido['usuario_id'], (int) $faturaId, $motivo, $valor);

        $this->setFlash(__('admin.additional_invoices.created_success', 'Fatura adicional criada. Item adicionado ao carrinho do cliente.'), 'success');
        $this->redirect('/admin/faturas-adicionais');
    }

    /**
     * Cancelar fatura
     */
    public function cancelar(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/faturas-adicionais');
            return;
        }

        $fatura = $this->model->find($id);
        if (!$fatura || $fatura['status'] !== 'pendente') {
            $this->setFlash(__('admin.additional_invoices.cannot_cancel', 'Fatura não pode ser cancelada.'), 'danger');
            $this->redirect('/admin/faturas-adicionais');
            return;
        }

        $this->model->update($id, ['status' => 'cancelado']);

        // Remover item do carrinho se existir
        $this->removerFaturaDoCarrinho($id);

        $this->setFlash(__('admin.additional_invoices.cancelled_success', 'Fatura cancelada com sucesso.'), 'success');
        $this->redirect('/admin/faturas-adicionais');
    }

    // ==================== Métodos privados ====================

    /**
     * Adicionar fatura adicional ao carrinho do cliente
     */
    private function adicionarFaturaAoCarrinho(int $usuarioId, int $faturaId, string $motivo, float $valor): void {
        try {
            $db = $this->connection;

            // Detectar nome da coluna de preço
            $cols = [];
            try {
                $st = $db->query('DESCRIBE carrinho_items');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }
            $unitCol = in_array('preco_unitario', $cols, true) ? 'preco_unitario' : 'valor_unitario';

            // Buscar ou criar carrinho do usuario
            $stCart = $db->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1');
            $stCart->execute([$usuarioId]);
            $cartId = (int) $stCart->fetchColumn();

            if ($cartId <= 0) {
                $stNew = $db->prepare("INSERT INTO carrinhos (usuario_id, moeda, expira_em) VALUES (?, 'USD', '2099-12-31 23:59:59')");
                $stNew->execute([$usuarioId]);
                $cartId = (int) $db->lastInsertId();
            }

            // Verificar se já existe item da fatura no carrinho
            $stCheck = $db->prepare('SELECT id FROM carrinho_items WHERE carrinho_id = ? AND fatura_adicional_id = ? LIMIT 1');
            $stCheck->execute([$cartId, $faturaId]);
            if ($stCheck->fetchColumn()) {
                return; // Já existe
            }

            // Inserir item
            $stIns = $db->prepare(
                "INSERT INTO carrinho_items (carrinho_id, produto_id, quantidade, {$unitCol}, subtotal, tipo_item, fatura_adicional_id, nome_item)
                 VALUES (?, 0, 1, ?, ?, 'fatura_adicional', ?, ?)"
            );
            $stIns->execute([$cartId, $valor, $valor, $faturaId, __('admin.additional_invoices.cart_item_label', 'Fatura Adicional:') . ' ' . $motivo]);
        } catch (\Throwable $e) {
            error_log('[FaturasAdicionais] Erro ao adicionar ao carrinho: ' . $e->getMessage());
        }
    }

    /**
     * Remover fatura do carrinho
     */
    private function removerFaturaDoCarrinho(int $faturaId): void {
        try {
            $stmt = $this->connection->prepare("DELETE FROM carrinho_items WHERE fatura_adicional_id = ? AND tipo_item = 'fatura_adicional'");
            $stmt->execute([$faturaId]);
        } catch (\Throwable $e) {
            error_log('[FaturasAdicionais] Erro ao remover do carrinho: ' . $e->getMessage());
        }
    }

    /**
     * Helper para mensagem flash na session
     */
    private function setFlash(string $message, string $type = 'info'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
}
