<?php
namespace App\Controllers;

use App\Core\Request;
use Config\Database;

/**
 * Controller leve para polling de lives
 * Não instancia serviços pesados — acessa banco direto
 */
class LivePollController {

    /**
     * GET /api/live/{id}/poll?since=X
     */
    public function poll(Request $request, $id) {
        try {
            $pdo = Database::getConnection();
            $liveId = (int) $id;
            $sinceId = (int) ($request->getParam('since') ?? 0);

            // Buscar live
            $stLive = $pdo->prepare("SELECT status, likes_count, shares_count, viewers_current, current_featured_product_id FROM lives WHERE id = ? LIMIT 1");
            $stLive->execute([$liveId]);
            $live = $stLive->fetch(\PDO::FETCH_ASSOC);

            if (!$live) {
                $this->json(['error' => 'Not found'], 404);
                return;
            }

            // Mensagens novas
            $stmt = $pdo->prepare(
                "SELECT m.id, m.user_id, m.content, m.created_at, 
                        COALESCE(u.nome, u.name, u.email) AS user_name
                 FROM live_chat_messages m
                 LEFT JOIN usuarios u ON u.id = m.user_id
                 WHERE m.live_id = ? AND m.id > ? AND m.hidden = 0
                 ORDER BY m.id ASC LIMIT 20"
            );
            $stmt->execute([$liveId, $sinceId]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // IDs ocultos
            $stHidden = $pdo->prepare("SELECT id FROM live_chat_messages WHERE live_id = ? AND hidden = 1 ORDER BY id DESC LIMIT 50");
            $stHidden->execute([$liveId]);
            $hiddenIds = $stHidden->fetchAll(\PDO::FETCH_COLUMN);

            // Métricas
            $metrics = [
                'viewers' => (int)($live['viewers_current'] ?? 0),
                'likes' => (int)($live['likes_count'] ?? 0),
                'shares' => (int)($live['shares_count'] ?? 0),
            ];

            // Produto em destaque
            $featured = null;
            if (!empty($live['current_featured_product_id'])) {
                $stFeat = $pdo->prepare(
                    "SELECT lp.product_id, COALESCE(lp.override_name, p.name) AS name,
                            COALESCE(lp.override_price, CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END) AS price,
                            COALESCE(lp.override_image, p.foto_principal) AS image
                     FROM live_products lp
                     LEFT JOIN produtos p ON p.id = lp.product_id
                     WHERE lp.live_id = ? AND lp.product_id = ? LIMIT 1"
                );
                $stFeat->execute([$liveId, (int)$live['current_featured_product_id']]);
                $featRow = $stFeat->fetch(\PDO::FETCH_ASSOC);
                if ($featRow) {
                    $featured = [
                        'product_id' => (int)$live['current_featured_product_id'],
                        'name' => $featRow['name'] ?? '',
                        'price' => (float)($featRow['price'] ?? 0),
                        'image' => $featRow['image'] ?? '',
                    ];
                }
            }

            $this->json([
                'status' => $live['status'],
                'messages' => $messages,
                'hidden_ids' => $hiddenIds,
                'metrics' => $metrics,
                'featured' => $featured,
            ]);

        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

    /**
     * POST /api/live/{id}/whep — Proxy WHEP para Cloudflare (evita CORS)
     */
    public function whepProxy(Request $request, $id) {
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->prepare("SELECT cf_playback_url FROM lives WHERE id = ? LIMIT 1");
            $st->execute([(int)$id]);
            $playbackUrl = $st->fetchColumn();

            if (empty($playbackUrl)) {
                http_response_code(404);
                echo 'No playback URL';
                return;
            }

            $sdp = file_get_contents('php://input');
            if (empty($sdp)) {
                http_response_code(400);
                echo 'Empty SDP';
                return;
            }

            $ch = curl_init($playbackUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $sdp,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/sdp'],
                CURLOPT_HEADER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $body = substr($response, $headerSize);

            http_response_code($httpCode ?: 502);
            header('Content-Type: application/sdp');
            echo $body;
        } catch (\Exception $e) {
            http_response_code(500);
            echo $e->getMessage();
        }
    }
