<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Live;
use App\Models\CustomerPaymentMethod;
use App\Services\LiveShoppingService;
use App\Services\LiveChatService;
use App\Services\LiveMetricsService;

/**
 * API REST para clientes durante a live
 * Heartbeat, chat, like, share, buy, payment methods
 */
class LiveApiController {
    private $liveModel;
    private $shoppingService;
    private $chatService;
    private $metricsService;

    public function __construct() {
        $this->liveModel = new Live();
        $this->shoppingService = new LiveShoppingService();
        $this->chatService = new LiveChatService();
        $this->metricsService = new LiveMetricsService();
    }

    /**
     * Verifica autenticação do cliente
     */
    private function requireAuth(): ?int {
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['error' => 'Não autenticado'], 401);
            return null;
        }
        return $userId;
    }

    /**
     * Verifica acesso ao módulo
     */
    private function checkModuleAccess(): bool {
        $perfil = $_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? 'cliente';
        if (!$this->shoppingService->isAccessible($perfil)) {
            $this->json(['error' => 'Módulo indisponível'], 404);
            return false;
        }
        return true;
    }

    /**
     * POST /api/live/{id}/heartbeat
     */
    public function heartbeat(Request $request, $id) {
        if (!$this->checkModuleAccess()) return;
        $userId = $this->requireAuth();
        if (!$userId) return;

        $secondsWatched = (int) ($request->getParam('seconds_watched') ?? 0);
        $result = $this->metricsService->recordHeartbeat((int) $id, $userId, $secondsWatched);

        $this->json($result);
    }

    /**
     * POST /api/live/{id}/chat
     */
    public function chat(Request $request, $id) {
        if (!$this->checkModuleAccess()) return;
        $userId = $this->requireAuth();
        if (!$userId) return;

        $content = trim($request->getParam('content') ?? '');
        if (empty($content)) {
            $this->json(['error' => 'Mensagem vazia'], 422);
            return;
        }

        $result = $this->chatService->sendMessage((int) $id, $userId, $content);
        $this->json($result, $result['success'] ? 200 : 429);
    }

    /**
     * POST /api/live/{id}/like
     */
    public function like(Request $request, $id) {
        if (!$this->checkModuleAccess()) return;
        $userId = $this->requireAuth();
        if (!$userId) return;

        $action = $request->getParam('action') ?? 'like';
        $unlike = ($action === 'unlike');

        $result = $this->metricsService->addLike((int) $id, $userId, $unlike);
        $this->json($result);
    }

    /**
     * POST /api/live/{id}/share
     */
    public function share(Request $request, $id) {
        if (!$this->checkModuleAccess()) return;
        $userId = $this->requireAuth();
        if (!$userId) return;

        $channel = $request->getParam('channel') ?? 'link';
        $result = $this->metricsService->addShare((int) $id, $userId, $channel);
        $this->json($result);
    }

    /**
     * POST /api/live/{id}/buy
     * Compra 1-clique
     */
    public function buy(Request $request, $id) {
        if (!$this->checkModuleAccess()) return;
        $userId = $this->requireAuth();
        if (!$userId) return;

        $productId = (int) ($request->getParam('product_id') ?? 0);
        $idempotencyKey = trim($request->getParam('idempotency_key') ?? '');

        if ($productId <= 0) {
            $this->json(['error' => 'Produto inválido'], 422);
            return;
        }

        if (empty($idempotencyKey)) {
            $this->json(['error' => 'Idempotency key obrigatória'], 422);
            return;
        }

        $result = $this->shoppingService->buyProduct((int) $id, $productId, $userId, $idempotencyKey);

        $statusCode = 200;
        if (!$result['success']) {
            $statusCode = $result['code'] ?? 400;
        }

        $this->json($result, $statusCode);
    }

    /**
     * GET /api/me/payment-methods
     */
    public function paymentMethods(Request $request) {
        $userId = $this->requireAuth();
        if (!$userId) return;

        $model = new CustomerPaymentMethod();
        $methods = $model->getByUserId($userId);

        $this->json(['methods' => $methods]);
    }

    /**
     * POST /api/me/payment-methods
     */
    public function storePaymentMethod(Request $request) {
        $userId = $this->requireAuth();
        if (!$userId) return;

        $gateway = trim($request->getParam('gateway') ?? '');
        $token = trim($request->getParam('token') ?? '');
        $brand = trim($request->getParam('brand') ?? '');
        $lastFour = trim($request->getParam('last_four') ?? '');
        $holderName = trim($request->getParam('holder_name') ?? '');
        $expiryMonth = $request->getParam('expiry_month');
        $expiryYear = $request->getParam('expiry_year');

        if (empty($gateway) || empty($token)) {
            $this->json(['error' => 'Gateway e token são obrigatórios'], 422);
            return;
        }

        $model = new CustomerPaymentMethod();

        // Se é o primeiro cartão, definir como default
        $existing = $model->getByUserId($userId);
        $isDefault = empty($existing) ? 1 : 0;

        $id = $model->create([
            'user_id' => $userId,
            'gateway' => $gateway,
            'token' => $token,
            'brand' => $brand,
            'last_four' => $lastFour,
            'holder_name' => $holderName,
            'expiry_month' => $expiryMonth ?: null,
            'expiry_year' => $expiryYear ?: null,
            'is_default' => $isDefault,
        ]);

        $this->json(['success' => true, 'id' => $id, 'is_default' => (bool) $isDefault]);
    }

    /**
     * PATCH /api/me/payment-methods/{id}/default
     */
    public function setDefaultPaymentMethod(Request $request, $pmId) {
        $userId = $this->requireAuth();
        if (!$userId) return;

        $model = new CustomerPaymentMethod();
        $model->setDefault($userId, (int) $pmId);

        $this->json(['success' => true]);
    }

    /**
     * DELETE /api/me/payment-methods/{id}
     */
    public function deletePaymentMethod(Request $request, $pmId) {
        $userId = $this->requireAuth();
        if (!$userId) return;

        $model = new CustomerPaymentMethod();
        $model->deleteByUser($userId, (int) $pmId);

        $this->json(['success' => true]);
    }

    // ─── Helper ─────────────────────────────────────────────────────

    private function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
