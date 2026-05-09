<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Live;
use App\Models\LiveProduct;
use App\Models\LiveChatMessage;
use App\Services\LiveMetricsService;
use App\Services\LiveShoppingService;

/**
 * Server-Sent Events para realtime na live
 * Canais: featured, chat, metrics
 */
class LiveSseController {
    private $liveModel;
    private $liveProductModel;
    private $chatModel;
    private $metricsService;
    private $shoppingService;

    public function __construct() {
        $this->liveModel = new Live();
        $this->liveProductModel = new LiveProduct();
        $this->chatModel = new LiveChatMessage();
        $this->metricsService = new LiveMetricsService();
        $this->shoppingService = new LiveShoppingService();
    }

    /**
     * GET /api/live/{id}/events
     * Endpoint SSE — mantém conexão aberta e envia eventos
     */
    public function stream(Request $request, $id) {
        // Verificar acesso
        $perfil = $_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? 'cliente';
        if (!$this->shoppingService->isAccessible($perfil)) {
            http_response_code(404);
            return;
        }

        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            return;
        }

        // Configurar headers SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx
        header('Access-Control-Allow-Origin: *');

        // Desabilitar output buffering
        if (ob_get_level()) ob_end_clean();
        
        set_time_limit(0);
        ignore_user_abort(false);

        // Estado inicial
        $lastFeaturedProductId = $live['current_featured_product_id'];
        $lastChatId = $this->getLastChatId((int) $id);
        $lastMetricsHash = '';
        $iteration = 0;
        $maxIterations = 300; // 5 min (1 iter/s) — cliente reconecta

        // Enviar estado inicial
        $this->sendInitialState((int) $id, $live);

        while ($iteration < $maxIterations) {
            if (connection_aborted()) break;

            $iteration++;

            // Recarregar live do banco
            $live = $this->liveModel->find($id);
            if (!$live || $live['status'] === 'ended') {
                $this->sendEvent('ended', ['message' => 'A live foi encerrada']);
                break;
            }

            // Verificar mudança de produto em destaque
            $currentFeatured = $live['current_featured_product_id'];
            if ($currentFeatured !== $lastFeaturedProductId) {
                $lastFeaturedProductId = $currentFeatured;
                $this->sendFeaturedEvent((int) $id, $currentFeatured);
            }

            // Verificar novas mensagens de chat
            $newMessages = $this->getNewMessages((int) $id, $lastChatId);
            if (!empty($newMessages)) {
                $lastChatId = (int) end($newMessages)['id'];
                $this->sendEvent('chat', ['messages' => $newMessages]);
            }

            // Enviar métricas a cada 5 iterações (5s)
            if ($iteration % 5 === 0) {
                $metrics = $this->metricsService->getMetrics((int) $id);
                $metricsHash = md5(json_encode($metrics));
                if ($metricsHash !== $lastMetricsHash) {
                    $lastMetricsHash = $metricsHash;
                    $this->sendEvent('metrics', $metrics);
                }
            }

            // Heartbeat SSE a cada 15 iterações (15s)
            if ($iteration % 15 === 0) {
                $this->sendEvent('heartbeat', ['time' => time()]);
            }

            // Flush e esperar 1s
            if (ob_get_level()) ob_flush();
            flush();
            sleep(1);
        }

        // Fim da conexão — cliente deve reconectar
        $this->sendEvent('reconnect', ['message' => 'Reconecte']);
    }

    /**
     * GET /api/live/{id}/status (fallback polling)
     */
    public function status(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Live não encontrada']);
            return;
        }

        $metrics = $this->metricsService->getMetrics((int) $id);
        $messages = $this->chatModel->getRecent((int) $id, 20);

        $featured = null;
        if ($live['current_featured_product_id']) {
            $lp = $this->liveProductModel->getByLiveAndProduct(
                (int) $id, 
                (int) $live['current_featured_product_id']
            );
            if ($lp) {
                $featured = [
                    'product_id' => (int) $live['current_featured_product_id'],
                    'name' => $lp['display_name'],
                    'price' => (float) $lp['display_price'],
                    'image' => $lp['display_image'],
                    'description' => $lp['original_description'] ?? '',
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => $live['status'],
            'featured' => $featured,
            'metrics' => $metrics,
            'messages' => $messages,
        ], JSON_UNESCAPED_UNICODE);
    }

    // ─── Helpers SSE ────────────────────────────────────────────────

    private function sendEvent(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    private function sendInitialState(int $liveId, array $live): void {
        // Enviar produto em destaque atual
        $this->sendFeaturedEvent($liveId, $live['current_featured_product_id']);

        // Enviar últimas mensagens
        $messages = $this->chatModel->getRecent($liveId, 30);
        if (!empty($messages)) {
            $this->sendEvent('chat', ['messages' => $messages]);
        }

        // Enviar métricas
        $metrics = $this->metricsService->getMetrics($liveId);
        $this->sendEvent('metrics', $metrics);

        if (ob_get_level()) ob_flush();
        flush();
    }

    private function sendFeaturedEvent(int $liveId, ?int $productId): void {
        if (empty($productId)) {
            $this->sendEvent('featured', ['product_id' => null, 'name' => null]);
            return;
        }

        $lp = $this->liveProductModel->getByLiveAndProduct($liveId, $productId);
        if ($lp) {
            $this->sendEvent('featured', [
                'product_id' => $productId,
                'name' => $lp['display_name'],
                'price' => (float) $lp['display_price'],
                'image' => $lp['display_image'],
                'description' => $lp['original_description'] ?? '',
            ]);
        }
    }

    private function getLastChatId(int $liveId): int {
        $messages = $this->chatModel->getRecent($liveId, 1);
        if (!empty($messages)) {
            return (int) end($messages)['id'];
        }
        return 0;
    }

    private function getNewMessages(int $liveId, int $lastId): array {
        $pdo = \Config\Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT m.*, COALESCE(u.nome, u.name, u.email) AS user_name
             FROM live_chat_messages m
             LEFT JOIN usuarios u ON u.id = m.user_id
             WHERE m.live_id = :lid AND m.id > :last_id AND m.hidden = 0
             ORDER BY m.id ASC
             LIMIT 20"
        );
        $stmt->execute([':lid' => $liveId, ':last_id' => $lastId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
