<?php
namespace App\Models;

class Live extends Model {
    protected $table = 'lives';

    /**
     * Retorna a live ativa (status = 'live')
     */
    public function getActive() {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE status = 'live' ORDER BY live_started_at DESC LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Retorna lives por status
     */
    public function getByStatus(string $status, int $limit = 50, int $offset = 0): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE status = :status ORDER BY 
             CASE WHEN status = 'live' THEN live_started_at 
                  WHEN status = 'scheduled' THEN scheduled_at 
                  ELSE live_ended_at END DESC 
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todas as lives ordenadas (ao vivo primeiro, depois agendadas, depois encerradas)
     */
    public function getAllOrdered(int $limit = 50, int $offset = 0): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} 
             ORDER BY FIELD(status, 'live', 'scheduled', 'ended'),
                      CASE WHEN status = 'live' THEN live_started_at 
                           WHEN status = 'scheduled' THEN scheduled_at 
                           ELSE live_ended_at END DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza o produto em destaque
     */
    public function setFeaturedProduct(int $liveId, ?int $productId): bool {
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET current_featured_product_id = :pid WHERE id = :id"
        );
        $stmt->bindValue(':pid', $productId, $productId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':id', $liveId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Atualiza status da live
     */
    public function updateStatus(int $liveId, string $status, array $extra = []): bool {
        $sets = ['status = :status'];
        $binds = [':status' => $status, ':id' => $liveId];

        foreach ($extra as $col => $val) {
            $sets[] = "$col = :$col";
            $binds[":$col"] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($binds);
    }

    /**
     * Incrementa contadores de métricas
     */
    public function incrementMetric(int $liveId, string $metric, int $amount = 1): bool {
        $allowed = ['likes_count', 'shares_count', 'viewers_peak', 'viewers_current'];
        if (!in_array($metric, $allowed, true)) return false;

        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET $metric = $metric + :amount WHERE id = :id"
        );
        $stmt->bindValue(':amount', $amount, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $liveId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Atualiza viewers_current e viewers_peak se necessário
     */
    public function updateViewers(int $liveId, int $currentViewers): bool {
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET 
                viewers_current = :current,
                viewers_peak = GREATEST(viewers_peak, :peak)
             WHERE id = :id"
        );
        $stmt->bindValue(':current', $currentViewers, \PDO::PARAM_INT);
        $stmt->bindValue(':peak', $currentViewers, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $liveId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Retorna lives encerradas sem gravação (para cron de archive)
     */
    public function getEndedWithoutRecording(int $limit = 10): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = 'ended' 
               AND recording_url IS NULL 
               AND cf_live_input_id IS NOT NULL
             ORDER BY live_ended_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
