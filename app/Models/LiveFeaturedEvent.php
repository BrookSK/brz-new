<?php
namespace App\Models;

class LiveFeaturedEvent extends Model {
    protected $table = 'live_featured_events';

    /**
     * Retorna o evento de destaque ativo (sem ended_at)
     */
    public function getActiveEvent(int $liveId): ?array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} 
             WHERE live_id = :live_id AND ended_at IS NULL
             ORDER BY started_at DESC LIMIT 1"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Encerra o evento de destaque ativo
     */
    public function endActiveEvent(int $liveId): bool {
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET ended_at = NOW() 
             WHERE live_id = :live_id AND ended_at IS NULL"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Retorna histórico de destaques de uma live
     */
    public function getHistory(int $liveId): array {
        $stmt = $this->connection->prepare(
            "SELECT fe.*, 
                    COALESCE(lp.override_name, p.name, p.nome) AS product_name,
                    COALESCE(lp.override_price, p.sale_price, p.price, p.preco) AS product_price,
                    COALESCE(lp.override_image, p.foto_principal) AS product_image
             FROM {$this->table} fe
             LEFT JOIN live_products lp ON lp.live_id = fe.live_id AND lp.product_id = fe.product_id
             LEFT JOIN produtos p ON p.id = fe.product_id
             WHERE fe.live_id = :live_id
             ORDER BY fe.started_at ASC"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
