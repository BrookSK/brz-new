<?php
namespace App\Models;

class LiveChatMessage extends Model {
    protected $table = 'live_chat_messages';

    /**
     * Retorna mensagens recentes de uma live (não ocultas)
     */
    public function getRecent(int $liveId, int $limit = 50, ?string $since = null): array {
        $sql = "SELECT m.*, u.nome AS user_name, u.name AS user_name_alt
                FROM {$this->table} m
                LEFT JOIN usuarios u ON u.id = m.user_id
                WHERE m.live_id = :live_id AND m.hidden = 0";
        
        $binds = [':live_id' => $liveId];

        if ($since !== null) {
            $sql .= " AND m.created_at > :since";
            $binds[':since'] = $since;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Retornar em ordem cronológica
        return array_reverse($messages);
    }

    /**
     * Retorna todas as mensagens (incluindo ocultas) para moderação
     */
    public function getAllForModeration(int $liveId, int $limit = 100): array {
        $stmt = $this->connection->prepare(
            "SELECT m.*, u.nome AS user_name, u.name AS user_name_alt
             FROM {$this->table} m
             LEFT JOIN usuarios u ON u.id = m.user_id
             WHERE m.live_id = :live_id
             ORDER BY m.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Oculta uma mensagem
     */
    public function hide(int $messageId): bool {
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET hidden = 1 WHERE id = :id"
        );
        $stmt->bindValue(':id', $messageId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Verifica rate limit (1 msg/s por usuário)
     */
    public function canSendMessage(int $liveId, int $userId): bool {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE live_id = :live_id AND user_id = :user_id 
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $stmt->bindValue(':live_id', $liveId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn() === 0;
    }
}
