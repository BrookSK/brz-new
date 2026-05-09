<?php
namespace App\Services;

use App\Models\LiveChatMessage;
use Config\Database;

/**
 * Serviço de chat ao vivo
 * Envio, moderação, rate limiting
 */
class LiveChatService {
    private $pdo;
    private $chatModel;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->chatModel = new LiveChatMessage();
    }

    /**
     * Envia mensagem no chat
     */
    public function sendMessage(int $liveId, int $userId, string $content): array {
        // Verificar se usuário está banido
        if ($this->isUserBanned($liveId, $userId)) {
            return ['success' => false, 'error' => 'Você foi removido desta live'];
        }

        // Rate limit: 1 msg/s
        if (!$this->chatModel->canSendMessage($liveId, $userId)) {
            return ['success' => false, 'error' => 'Aguarde antes de enviar outra mensagem'];
        }

        // Sanitizar conteúdo
        $content = trim($content);
        if (empty($content) || mb_strlen($content) > 500) {
            return ['success' => false, 'error' => 'Mensagem inválida (máx. 500 caracteres)'];
        }

        // Escapar HTML
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $msgId = $this->chatModel->create([
            'live_id' => $liveId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        // Buscar nome do usuário
        $userName = $this->getUserName($userId);

        return [
            'success' => true,
            'message' => [
                'id' => $msgId,
                'user_id' => $userId,
                'user_name' => $userName,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Oculta uma mensagem (moderação)
     */
    public function hideMessage(int $messageId): array {
        $this->chatModel->hide($messageId);
        return ['success' => true];
    }

    /**
     * Bane um usuário da live
     */
    public function banUser(int $liveId, int $userId): array {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO live_bans (live_id, user_id) VALUES (:lid, :uid)"
        );
        $stmt->execute([':lid' => $liveId, ':uid' => $userId]);

        // Ocultar todas as mensagens do usuário nesta live
        $stmt2 = $this->pdo->prepare(
            "UPDATE live_chat_messages SET hidden = 1 WHERE live_id = :lid AND user_id = :uid"
        );
        $stmt2->execute([':lid' => $liveId, ':uid' => $userId]);

        return ['success' => true];
    }

    /**
     * Verifica se usuário está banido
     */
    public function isUserBanned(int $liveId, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM live_bans WHERE live_id = :lid AND user_id = :uid LIMIT 1"
        );
        $stmt->execute([':lid' => $liveId, ':uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Retorna mensagens recentes
     */
    public function getRecentMessages(int $liveId, int $limit = 50, ?string $since = null): array {
        return $this->chatModel->getRecent($liveId, $limit, $since);
    }

    /**
     * Retorna nome do usuário
     */
    private function getUserName(int $userId): string {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(nome, name, email) AS display_name FROM usuarios WHERE id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $name = $stmt->fetchColumn();
        return $name ?: 'Anônimo';
    }
}
