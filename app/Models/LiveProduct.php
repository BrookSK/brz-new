<?php
namespace App\Models;

class LiveProduct extends Model {
    protected $table = 'live_products';

    /**
     * Retorna produtos de uma live com dados do produto original
     */
    public function getByLiveId(int $liveId): array {
        $stmt = $this->connection->prepare(
            "SELECT lp.*, 
                    COALESCE(lp.override_name, p.name) AS display_name,
                    COALESCE(lp.override_price, CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END) AS display_price,
                    COALESCE(lp.override_weight, p.weight) AS display_weight,
                    COALESCE(lp.override_image, p.foto_principal) AS display_image,
                    p.name AS original_name,
                    p.price AS original_price,
                    COALESCE(p.description, '') AS original_description
             FROM {$this->table} lp
             LEFT JOIN produtos p ON p.id = lp.product_id
             WHERE lp.live_id = :live_id
             ORDER BY lp.position ASC"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um produto específico da live
     */
    public function getByLiveAndProduct(int $liveId, int $productId): ?array {
        $stmt = $this->connection->prepare(
            "SELECT lp.*, 
                    COALESCE(lp.override_name, p.name) AS display_name,
                    COALESCE(lp.override_price, CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END) AS display_price,
                    COALESCE(lp.override_weight, p.weight) AS display_weight,
                    COALESCE(lp.override_image, p.foto_principal) AS display_image,
                    COALESCE(p.description, '') AS original_description
             FROM {$this->table} lp
             LEFT JOIN produtos p ON p.id = lp.product_id
             WHERE lp.live_id = :live_id AND lp.product_id = :product_id
             LIMIT 1"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza posições dos produtos (reordenar)
     */
    public function updatePositions(int $liveId, array $orderedIds): bool {
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET position = :pos WHERE id = :id AND live_id = :live_id"
        );
        foreach ($orderedIds as $pos => $id) {
            $stmt->execute([':pos' => $pos, ':id' => $id, ':live_id' => $liveId]);
        }
        return true;
    }

    /**
     * Retorna a próxima posição disponível
     */
    public function getNextPosition(int $liveId): int {
        $stmt = $this->connection->prepare(
            "SELECT COALESCE(MAX(position), -1) + 1 FROM {$this->table} WHERE live_id = :live_id"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
