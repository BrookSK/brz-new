<?php
namespace App\Models;

class LiveOrder extends Model {
    protected $table = 'live_orders';

    /**
     * Verifica se já existe pedido com a mesma idempotency key
     */
    public function findByIdempotencyKey(string $key): ?array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE idempotency_key = :key LIMIT 1"
        );
        $stmt->bindValue(':key', $key);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Retorna pedidos de uma live com dados do produto e pedido
     */
    public function getByLiveId(int $liveId): array {
        $stmt = $this->connection->prepare(
            "SELECT lo.*, 
                    p.id AS pedido_id, p.total AS pedido_total, p.status AS pedido_status,
                    pr.name AS produto_nome
             FROM {$this->table} lo
             LEFT JOIN pedidos p ON p.id = lo.order_id
             LEFT JOIN produtos pr ON pr.id = lo.product_id
             WHERE lo.live_id = :live_id
             ORDER BY lo.created_at DESC"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Relatório de conversão por produto
     */
    public function getConversionReport(int $liveId): array {
        $stmt = $this->connection->prepare(
            "SELECT lo.product_id,
                    pr.name AS produto_nome,
                    COUNT(*) AS total_pedidos,
                    SUM(CASE WHEN p.status IN ('paid','pago','aprovado') THEN 1 ELSE 0 END) AS pedidos_pagos,
                    SUM(CASE WHEN p.status IN ('paid','pago','aprovado') THEN p.total ELSE 0 END) AS faturamento
             FROM {$this->table} lo
             LEFT JOIN pedidos p ON p.id = lo.order_id
             LEFT JOIN produtos pr ON pr.id = lo.product_id
             WHERE lo.live_id = :live_id
             GROUP BY lo.product_id
             ORDER BY total_pedidos DESC"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
