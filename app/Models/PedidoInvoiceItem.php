<?php
namespace App\Models;

class PedidoInvoiceItem extends Model {
    protected $table = 'pedido_invoice_items';

    /**
     * Buscar itens por invoice
     */
    public function getByInvoice(int $invoiceId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE invoice_id = :iid ORDER BY id ASC"
        );
        $stmt->execute([':iid' => $invoiceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualizar item do invoice (edição pelo cliente)
     */
    public function atualizarItem(int $id, array $dados): bool {
        $allowed = ['nome_produto', 'declaration_value', 'tem_bateria', 'tem_perfume'];
        $data = [];
        foreach ($allowed as $campo) {
            if (array_key_exists($campo, $dados)) {
                $data[$campo] = $dados[$campo];
            }
        }
        if (empty($data)) return false;
        return $this->update($id, $data);
    }

    /**
     * Buscar itens por pedido (via join com pedido_invoices)
     */
    public function getByPedido(int $pedidoId): array {
        $stmt = $this->connection->prepare(
            "SELECT pii.* FROM {$this->table} pii
             INNER JOIN pedido_invoices pi ON pi.id = pii.invoice_id
             WHERE pi.pedido_id = :pid
             ORDER BY pii.id ASC"
        );
        $stmt->execute([':pid' => $pedidoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
