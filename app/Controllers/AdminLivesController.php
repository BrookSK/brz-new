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
        $sidebarActive = 'lives';

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/lives/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
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
        $sidebarActive = 'lives';

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/lives/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
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
        $sidebarActive = 'lives';

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/lives/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
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
        
        // Carregar mensagens do chat
        $chatModel = new \App\Models\LiveChatMessage();
        $chatMessages = $chatModel->getRecent((int)$id, 20);

        $activePage = 'lives';
        $title = 'Estúdio - ' . $live['title'];

        // Estúdio é full-screen, não usa layout admin
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        include __DIR__ . '/../Views/admin/lives/studio.php';
    }

    /**
     * Iniciar transmissão
     */
    public function start(Request $request, $id) {
        try {
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
            if (!$cfResult || empty($cfResult['uid'])) {
                $this->jsonResponse(['error' => 'Erro ao criar transmissão no Cloudflare. Verifique as credenciais.'], 500);
                return;
            }

            // Atualizar live com dados do CF
            // Se WebRTC, usar WHEP para playback (CF exige WHIP+WHEP juntos)
            // Se OBS/RTMPS, usar HLS
            $playbackUrl = '';
            if ($live['ingest_method'] === 'webrtc' && !empty($cfResult['webrtc_playback_url'])) {
                $playbackUrl = $cfResult['webrtc_playback_url'];
            } else {
                $playbackUrl = $cfResult['playback_hls'] ?: ($cfResult['webrtc_playback_url'] ?: '');
            }
            
            $this->liveModel->update($id, [
                'cf_live_input_id' => $cfResult['uid'],
                'cf_rtmps_url' => $cfResult['rtmps_url'],
                'cf_rtmps_key' => $cfResult['rtmps_key'],
                'cf_webrtc_url' => $cfResult['webrtc_url'],
                'cf_playback_url' => $playbackUrl,
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
                'whip_proxy_url' => '/api/live/' . $id . '/whip',
                'playback_url' => $playbackUrl,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
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

        // Atualizar status IMEDIATAMENTE (antes de chamar CF)
        $this->liveModel->updateStatus($id, 'ended', [
            'live_ended_at' => date('Y-m-d H:i:s'),
            'viewers_current' => 0,
        ]);

        // Somar minutos na cota
        $this->shoppingService->addMinutesUsed($minutes);

        // Encerrar destaque ativo
        $this->shoppingService->unfeatureProduct($id);

        // Responder imediatamente
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
        $sidebarActive = 'lives';

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/lives/report.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
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

    /**
     * Busca produtos por nome (AJAX autocomplete)
     */
    public function searchProducts(Request $request) {
        $q = trim($request->getParam('q') ?? '');
        if (mb_strlen($q) < 2) {
            $this->jsonResponse(['products' => []]);
            return;
        }

        $produto = new Produto();
        $pdo = $produto->getConnection();
        
        // Verificar quais colunas existem
        $cols = [];
        try {
            $stCols = $pdo->query('DESCRIBE produtos');
            $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $priceExpr = 'price';
        if (in_array('sale_price', $cols)) {
            $priceExpr = 'CASE WHEN sale_price > 0 THEN sale_price ELSE price END';
        }

        $imgCol = in_array('foto_principal', $cols) ? 'foto_principal' : 'NULL';
        $imagesCol = in_array('images', $cols) ? 'images' : 'NULL';

        $stmt = $pdo->prepare(
            "SELECT id, name, ({$priceExpr}) AS final_price, {$imgCol} AS foto_principal, {$imagesCol} AS images
             FROM produtos 
             WHERE active = 1 AND name LIKE :q
             ORDER BY name ASC
             LIMIT 20"
        );
        $stmt->execute([':q' => '%' . $q . '%']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $products = array_map(function($r) {
            $img = $r['foto_principal'] ?? '';
            if (empty($img) && !empty($r['images'])) {
                $decoded = json_decode($r['images'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    $img = is_array($decoded[0]) ? ($decoded[0]['url'] ?? $decoded[0]['src'] ?? '') : $decoded[0];
                } elseif (is_string($r['images']) && !empty($r['images'])) {
                    $img = explode(',', $r['images'])[0];
                }
            }
            // Garantir path completo
            if (!empty($img) && strpos($img, '/') === false && strpos($img, 'http') !== 0) {
                $img = '/uploads/produtos/' . $img;
            }
            return [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'price' => (float)($r['final_price'] ?? 0),
                'image' => $img,
            ];
        }, $rows);

        $this->jsonResponse(['products' => $products]);
    }

    /**
     * POST /api/live/{id}/whip — Proxy WHIP para Cloudflare (evita CORS)
     */
    public function whipProxy(Request $request, $id) {
        $live = $this->liveModel->find($id);
        if (!$live || empty($live['cf_webrtc_url'])) {
            http_response_code(404);
            echo 'Live not found or no WHIP URL';
            return;
        }

        // Ler SDP do body
        $sdp = file_get_contents('php://input');
        if (empty($sdp)) {
            http_response_code(400);
            echo 'Empty SDP';
            return;
        }

        // Repassar para o Cloudflare WHIP endpoint
        $whipUrl = $live['cf_webrtc_url'];
        
        $ch = curl_init($whipUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sdp,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/sdp'],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        http_response_code($httpCode ?: 502);
        header('Content-Type: application/sdp');
        echo $body;
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function getEligibleProducts(): array {
        $produto = new Produto();
        $pdo = $produto->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, name, price, sale_price, foto_principal, images
             FROM produtos 
             WHERE active = 1
             ORDER BY name ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Mapear para formato esperado pelas views
        return array_map(function($r) {
            return [
                'id' => $r['id'],
                'nome' => $r['name'],
                'preco' => (float)($r['sale_price'] ?: $r['price']),
                'foto_principal' => $r['foto_principal'] ?? '',
                'imagens' => $r['images'] ?? '',
            ];
        }, $rows);
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
