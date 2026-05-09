<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Live;
use App\Models\LiveProduct;
use App\Models\LiveFeaturedEvent;
use App\Models\LiveOrder;
use App\Models\Produto;
use App\Services\LiveStreamService;
use App\Services\LiveShoppingService;
use App\Services\LiveChatService;

class AdminLivesController {
    private $auth;
    private $liveModel;
    private $liveProductModel;
    private $liveOrderModel;
    private $featuredEventModel;
    private $streamService;
    private $shoppingService;
    private $chatService;

    public function __construct() {
        $this->auth = new \App\Services\AuthService();
        $this->auth->requerPerfis(['admin']);
        $this->liveModel = new Live();
        $this->liveProductModel = new LiveProduct();
        $this->liveOrderModel = new LiveOrder();
        $this->featuredEventModel = new LiveFeaturedEvent();
        $this->streamService = new LiveStreamService();
        $this->shoppingService = new LiveShoppingService();
        $this->chatService = new LiveChatService();
    }

    /**
     * Listagem de lives
     */
    public function index(Request $request) {
        $status = $request->getParam('status') ?? '';
        
        if ($status && in_array($status, ['scheduled', 'live', 'ended'])) {
            $lives = $this->liveModel->getByStatus($status);
        } else {
            $lives = $this->liveModel->getAllOrdered();
        }

        $quota = $this->shoppingService->checkQuota();
        $activePage = 'lives';
        $title = 'Lives';

        require __DIR__ . '/../Views/admin/lives/index.php';
    }

    /**
     * Form nova live
     */
    public function create(Request $request) {
        $live = null;
        $products = [];
        $eligibleProducts = $this->getEligibleProducts();
        $activePage = 'lives';
        $title = 'Nova Live';

        require __DIR__ . '/../Views/admin/lives/form.php';
    }

    /**
     * Salvar nova live
     */
    public function store(Request $request) {
        $data = [
            'title' => trim($request->getParam('title') ?? ''),
            'description' => trim($request->getParam('description') ?? ''),
            'scheduled_at' => $request->getParam('scheduled_at') ?: null,
            'ingest_method' => $request->getParam('ingest_method') ?? 'webrtc',
            'free_seconds' => (int) ($request->getParam('free_seconds') ?? 0),
            'unlock_price' => (float) ($request->getParam('unlock_price') ?? 0),
        ];

        if (empty($data['title'])) {
            $this->jsonResponse(['error' => 'Título é obrigatório'], 422);
            return;
        }

        // Upload de capa
        if (!empty($_FILES['cover']['tmp_name'])) {
            $data['cover_url'] = $this->uploadCover($_FILES['cover']);
        }

        $liveId = $this->liveModel->create($data);

        // Anexar produtos selecionados
        $productIds = $request->getParam('products') ?? [];
        if (is_array($productIds)) {
            foreach ($productIds as $pos => $pid) {
                $this->liveProductModel->create([
                    'live_id' => $liveId,
                    'product_id' => (int) $pid,
                    'position' => $pos,
                ]);
            }
        }

        header('Location: /admin/lives');
        exit;
    }

    /**
     * Form editar live
     */
    public function edit(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            echo 'Live não encontrada';
            return;
        }

        $products = $this->liveProductModel->getByLiveId($id);
        $eligibleProducts = $this->getEligibleProducts();
        $activePage = 'lives';
        $title = 'Editar Live';

        require __DIR__ . '/../Views/admin/lives/form.php';
    }

    /**
     * Atualizar live
     */
    public function update(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            $this->jsonResponse(['error' => 'Live não encontrada'], 404);
            return;
        }

        $data = [
            'title' => trim($request->getParam('title') ?? $live['title']),
            'description' => trim($request->getParam('description') ?? ''),
            'scheduled_at' => $request->getParam('scheduled_at') ?: null,
            'ingest_method' => $request->getParam('ingest_method') ?? $live['ingest_method'],
            'free_seconds' => (int) ($request->getParam('free_seconds') ?? 0),
            'unlock_price' => (float) ($request->getParam('unlock_price') ?? 0),
        ];

        if (!empty($_FILES['cover']['tmp_name'])) {
            $data['cover_url'] = $this->uploadCover($_FILES['cover']);
        }

        $this->liveModel->update($id, $data);

        header('Location: /admin/lives');
        exit;
    }

    /**
     * Excluir live
     */
    public function destroy(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            $this->jsonResponse(['error' => 'Live não encontrada'], 404);
            return;
        }

        if ($live['status'] === 'live') {
            $this->jsonResponse(['error' => 'Não é possível excluir uma live em andamento'], 422);
            return;
        }

        $this->liveModel->delete($id);
        $this->jsonResponse(['success' => true]);
    }

    /**
     * Página do estúdio (transmissão)
     */
    public function studio(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            echo 'Live não encontrada';
            return;
        }

        $products = $this->liveProductModel->getByLiveId($id);
        $activePage = 'lives';
        $title = 'Estúdio - ' . $live['title'];

        require __DIR__ . '/../Views/admin/lives/studio.php';
    }

    /**
     * Iniciar transmissão
     */
    public function start(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            $this->jsonResponse(['error' => 'Live não encontrada'], 404);
            return;
        }

        if ($live['status'] === 'live') {
            $this->jsonResponse(['error' => 'Live já está em andamento'], 422);
            return;
        }

        // Verificar cota
        $quota = $this->shoppingService->checkQuota();
        if (!$quota['can_stream']) {
            $this->jsonResponse(['error' => 'Cota mensal de streaming excedida'], 403);
            return;
        }

        // Criar live input no Cloudflare
        $cfResult = $this->streamService->createLiveInput($id, $live['title']);
        if (!$cfResult) {
            $this->jsonResponse(['error' => 'Erro ao criar transmissão no Cloudflare. Verifique as credenciais.'], 500);
            return;
        }

        // Atualizar live com dados do CF
        $this->liveModel->update($id, [
            'cf_live_input_id' => $cfResult['uid'],
            'cf_rtmps_url' => $cfResult['rtmps_url'],
            'cf_rtmps_key' => $cfResult['rtmps_key'],
            'cf_webrtc_url' => $cfResult['webrtc_url'],
            'cf_playback_url' => $cfResult['playback_hls'],
        ]);

        $this->liveModel->updateStatus($id, 'live', [
            'live_started_at' => date('Y-m-d H:i:s'),
        ]);

        $this->jsonResponse([
            'success' => true,
            'ingest_method' => $live['ingest_method'],
            'rtmps_url' => $cfResult['rtmps_url'],
            'rtmps_key' => $cfResult['rtmps_key'],
            'webrtc_url' => $cfResult['webrtc_url'],
            'playback_url' => $cfResult['playback_hls'],
        ]);
    }

    /**
     * Encerrar transmissão
     */
    public function stop(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            $this->jsonResponse(['error' => 'Live não encontrada'], 404);
            return;
        }

        if ($live['status'] !== 'live') {
            $this->jsonResponse(['error' => 'Live não está em andamento'], 422);
            return;
        }

        // Calcular minutos transmitidos
        $startedAt = strtotime($live['live_started_at']);
        $minutes = max(1, (int) ceil((time() - $startedAt) / 60));

        // Somar minutos na cota
        $this->shoppingService->addMinutesUsed($minutes);

        // Encerrar destaque ativo
        $this->shoppingService->unfeatureProduct($id);

        // Atualizar status
        $this->liveModel->updateStatus($id, 'ended', [
            'live_ended_at' => date('Y-m-d H:i:s'),
            'viewers_current' => 0,
        ]);

        $this->jsonResponse([
            'success' => true,
            'minutes_streamed' => $minutes,
        ]);
    }

    /**
     * Destacar/desdestcar produto
     */
    public function feature(Request $request, $id) {
        $productId = $request->getParam('product_id');

        if (empty($productId) || $productId === 'null') {
            $result = $this->shoppingService->unfeatureProduct($id);
        } else {
            $result = $this->shoppingService->featureProduct($id, (int) $productId);
        }

        $this->jsonResponse($result);
    }

    /**
     * Ocultar mensagem do chat
     */
    public function hideMessage(Request $request, $id, $msgId) {
        $result = $this->chatService->hideMessage((int) $msgId);
        $this->jsonResponse($result);
    }

    /**
     * Banir usuário da live
     */
    public function banUser(Request $request, $id, $userId) {
        $result = $this->chatService->banUser((int) $id, (int) $userId);
        $this->jsonResponse($result);
    }

    /**
     * Relatório de conversão
     */
    public function report(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            echo 'Live não encontrada';
            return;
        }

        $orders = $this->liveOrderModel->getByLiveId($id);
        $conversion = $this->liveOrderModel->getConversionReport($id);
        $featuredHistory = $this->featuredEventModel->getHistory($id);
        $activePage = 'lives';
        $title = 'Relatório - ' . $live['title'];

        require __DIR__ . '/../Views/admin/lives/report.php';
    }

    /**
     * Anexar produto à live
     */
    public function addProduct(Request $request, $id) {
        $productId = (int) $request->getParam('product_id');
        if ($productId <= 0) {
            $this->jsonResponse(['error' => 'Produto inválido'], 422);
            return;
        }

        $position = $this->liveProductModel->getNextPosition($id);

        try {
            $lpId = $this->liveProductModel->create([
                'live_id' => $id,
                'product_id' => $productId,
                'position' => $position,
                'override_name' => $request->getParam('override_name') ?: null,
                'override_price' => $request->getParam('override_price') ?: null,
            ]);
            $this->jsonResponse(['success' => true, 'id' => $lpId]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Produto já está nesta live'], 422);
        }
    }

    /**
     * Editar override de produto da live
     */
    public function updateProduct(Request $request, $id, $lpId) {
        $data = [];
        
        $fields = ['override_name', 'override_price', 'override_weight', 'override_image'];
        foreach ($fields as $field) {
            $val = $request->getParam($field);
            if ($val !== null) {
                $data[$field] = $val ?: null;
            }
        }

        $position = $request->getParam('position');
        if ($position !== null) {
            $data['position'] = (int) $position;
        }

        if (!empty($data)) {
            $this->liveProductModel->update($lpId, $data);
        }

        $this->jsonResponse(['success' => true]);
    }

    /**
     * Remover produto da live
     */
    public function removeProduct(Request $request, $id, $lpId) {
        $this->liveProductModel->delete($lpId);
        $this->jsonResponse(['success' => true]);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function getEligibleProducts(): array {
        $produto = new Produto();
        $pdo = $produto->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, COALESCE(name, nome) AS nome, COALESCE(price, preco) AS preco, 
                    COALESCE(sale_price, preco_promocao) AS preco_promocao,
                    foto_principal, COALESCE(images, imagens) AS imagens
             FROM produtos 
             WHERE is_live_eligible = 1 AND (active = 1 OR status = 'published')
             ORDER BY COALESCE(name, nome) ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function uploadCover(array $file): ?string {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) return null;

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'live_cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $_SERVER['DOCUMENT_ROOT'] . '/uploads/lives/' . $filename;

        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return '/uploads/lives/' . $filename;
        }

        return null;
    }

    private function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
