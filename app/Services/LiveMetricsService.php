<?php
namespace App\Services;

use App\Models\Live;
use Config\Database;

/**
 * Serviço de métricas da live
 * Viewers, likes, shares, heartbeat/freemium
 */
class LiveMetricsService {
    private $pdo;
    private $liveModel;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->liveModel = new Live();
    }

    /**
     * Registra heartbeat (freemium gate)
     */
    public function recordHeartbeat(int $liveId, int $userId, int $secondsWatched): array {
        // Buscar progresso atual
        $stmt = $this->pdo->prepare(
            "SELECT seconds_watched, last_heartbeat_at FROM live_watch_progress 
             WHERE live_id = :lid AND user_id = :uid"
        );
        $stmt->execute([':lid' => $liveId, ':uid' => $userId]);
        $progress = $stmt->fetch(\PDO::FETCH_ASSOC);

        $currentSeconds = $progress ? (int) $progress['seconds_watched'] : 0;

        // Ignorar pulos > 30s (anti-fraude)
        $increment = $secondsWatched - $currentSeconds;
        if ($increment > 30) {
            $increment = 10; // Assumir 10s (intervalo normal de heartbeat)
        }
        if ($increment < 0) {
            $increment = 0;
        }

        $newSeconds = $currentSeconds + $increment;

        // Upsert progresso
        $stmt2 = $this->pdo->prepare(
            "INSERT INTO live_watch_progress (live_id, user_id, seconds_watched, last_heartbeat_at)
             VALUES (:lid, :uid, :sec, NOW())
             ON DUPLICATE KEY UPDATE seconds_watched = :sec2, last_heartbeat_at = NOW()"
        );
        $stmt2->execute([
            ':lid' => $liveId,
            ':uid' => $userId,
            ':sec' => $newSeconds,
            ':sec2' => $newSeconds,
        ]);

        // Verificar freemium
        $live = $this->liveModel->find($liveId);
        $freeSeconds = (int) ($live['free_seconds'] ?? 0);

        if ($freeSeconds > 0 && $newSeconds >= $freeSeconds) {
            // Verificar se já pagou
            $stmt3 = $this->pdo->prepare(
                "SELECT status FROM live_paid_unlocks WHERE live_id = :lid AND user_id = :uid"
            );
            $stmt3->execute([':lid' => $liveId, ':uid' => $userId]);
            $unlock = $stmt3->fetch(\PDO::FETCH_ASSOC);

            if (!$unlock || $unlock['status'] !== 'paid') {
                return [
                    'can_continue' => false,
                    'seconds_watched' => $newSeconds,
                    'free_seconds' => $freeSeconds,
                    'unlock_price' => (float) ($live['unlock_price'] ?? 0),
                ];
            }
        }

        return [
            'can_continue' => true,
            'seconds_watched' => $newSeconds,
        ];
    }

    /**
     * Registra ou remove curtida (toggle)
     */
    public function addLike(int $liveId, int $userId, bool $unlike = false): array {
        if ($unlike) {
            // Remover like
            $stmt = $this->pdo->prepare(
                "DELETE FROM live_likes WHERE live_id = :lid AND user_id = :uid"
            );
            $stmt->execute([':lid' => $liveId, ':uid' => $userId]);

            // Decrementar contador global
            $stmt2 = $this->pdo->prepare(
                "UPDATE lives SET likes_count = GREATEST(0, likes_count - 1) WHERE id = :id"
            );
            $stmt2->execute([':id' => $liveId]);

            $live = $this->liveModel->find($liveId);
            return ['success' => true, 'liked' => false, 'total_likes' => (int)($live['likes_count'] ?? 0)];
        }

        // Rate limit: verificar últimas curtidas (10/s)
        if (!$this->canLike($liveId, $userId)) {
            return ['success' => false, 'error' => 'Rate limit'];
        }

        // Upsert no live_likes (incrementar count)
        $stmt = $this->pdo->prepare(
            "INSERT INTO live_likes (live_id, user_id, count)
             VALUES (:lid, :uid, 1)
             ON DUPLICATE KEY UPDATE count = count + 1, updated_at = NOW()"
        );
        $stmt->execute([':lid' => $liveId, ':uid' => $userId]);

        // Incrementar contador global
        $this->liveModel->incrementMetric($liveId, 'likes_count');

        // Retornar total
        $live = $this->liveModel->find($liveId);

        return [
            'success' => true,
            'liked' => true,
            'total_likes' => (int) ($live['likes_count'] ?? 0) + 1,
        ];
    }

    /**
     * Registra compartilhamento
     */
    public function addShare(int $liveId, int $userId, string $channel = 'link'): array {
        $stmt = $this->pdo->prepare(
            "INSERT INTO live_shares (live_id, user_id, channel) VALUES (:lid, :uid, :ch)"
        );
        $stmt->execute([':lid' => $liveId, ':uid' => $userId, ':ch' => $channel]);

        $this->liveModel->incrementMetric($liveId, 'shares_count');

        return ['success' => true];
    }

    /**
     * Atualiza contagem de viewers (baseado em heartbeats recentes)
     */
    public function updateViewerCount(int $liveId): int {
        // Contar usuários com heartbeat nos últimos 30s
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM live_watch_progress 
             WHERE live_id = :lid AND last_heartbeat_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)"
        );
        $stmt->execute([':lid' => $liveId]);
        $count = (int) $stmt->fetchColumn();

        $this->liveModel->updateViewers($liveId, $count);

        return $count;
    }

    /**
     * Retorna métricas atuais da live
     */
    public function getMetrics(int $liveId): array {
        $live = $this->liveModel->find($liveId);
        if (!$live) {
            return ['viewers' => 0, 'likes' => 0, 'shares' => 0];
        }

        // Atualizar viewers em tempo real
        $viewers = $this->updateViewerCount($liveId);

        return [
            'viewers' => $viewers,
            'viewers_peak' => (int) ($live['viewers_peak'] ?? 0),
            'likes' => (int) ($live['likes_count'] ?? 0),
            'shares' => (int) ($live['shares_count'] ?? 0),
        ];
    }

    /**
     * Rate limit para likes (máx 10 por segundo por usuário)
     * Usa session para simplicidade
     */
    private function canLike(int $liveId, int $userId): bool {
        $key = "live_like_{$liveId}_{$userId}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'window_start' => time()];
        }

        $data = &$_SESSION[$key];
        $now = time();

        // Reset janela a cada segundo
        if ($now > $data['window_start']) {
            $data['count'] = 0;
            $data['window_start'] = $now;
        }

        if ($data['count'] >= 10) {
            return false;
        }

        $data['count']++;
        return true;
    }
}
