<?php
namespace App\Models;

class PedidoInvoice extends Model {
    protected $table = 'pedido_invoices';

    /**
     * Buscar invoice pelo pedido
     */
    public function getByPedido(int $pedidoId): ?array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE pedido_id = :pid ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':pid' => $pedidoId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Criar invoice e copiar itens do pedido
     */
    public function liberarInvoice(int $pedidoId, array $itens): int {
        // Criar registro do invoice
        $invoiceId = $this->create([
            'pedido_id' => $pedidoId,
            'status' => 'liberado',
            'liberado_em' => date('Y-m-d H:i:s'),
        ]);

        // Copiar itens para pedido_invoice_items
        foreach ($itens as $item) {
            $stmt = $this->connection->prepare(
                "INSERT INTO pedido_invoice_items 
                (invoice_id, pedido_item_id, pacote_id, nome_produto, ncm, declaration_value, peso_kg, quantidade, tem_bateria, tem_perfume, foto_url)
                VALUES (:invoice_id, :pedido_item_id, :pacote_id, :nome_produto, :ncm, :declaration_value, :peso_kg, :quantidade, :tem_bateria, :tem_perfume, :foto_url)"
            );
            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':pedido_item_id' => $item['pedido_item_id'] ?? null,
                ':pacote_id' => $item['pacote_id'] ?? null,
                ':nome_produto' => $item['nome_produto'] ?? $item['nome'] ?? 'Produto',
                ':ncm' => $item['ncm'] ?? null,
                ':declaration_value' => $item['declaration_value'] ?? 0,
                ':peso_kg' => $item['peso_kg'] ?? 0,
                ':quantidade' => $item['quantidade'] ?? 1,
                ':tem_bateria' => $item['tem_bateria'] ?? 'N',
                ':tem_perfume' => $item['tem_perfume'] ?? 'N',
                ':foto_url' => $item['foto_url'] ?? null,
            ]);
        }

        return (int) $invoiceId;
    }

    /**
     * Confirmar invoice
     */
    public function confirmar(int $invoiceId): bool {
        return $this->update($invoiceId, [
            'status' => 'confirmado',
            'confirmado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Contestar invoice
     */
    public function contestar(int $invoiceId, string $motivo): bool {
        return $this->update($invoiceId, [
            'status' => 'contestado',
            'contestacao_motivo' => $motivo,
            'contestado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Re-liberar invoice (após ajuste de contestação)
     */
    public function reliberar(int $invoiceId): bool {
        return $this->update($invoiceId, [
            'status' => 'liberado',
            'contestacao_motivo' => null,
            'contestado_em' => null,
            'confirmado_em' => null,
            'liberado_em' => date('Y-m-d H:i:s'),
        ]);
    }
}
