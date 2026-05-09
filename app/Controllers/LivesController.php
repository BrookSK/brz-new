<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Live;
use App\Models\LiveProduct;
use App\Models\LiveFeaturedEvent;
use App\Services\LiveShoppingService;
use App\Services\LiveStreamService;

/**
 * Controller público para clientes assistirem lives
 */
class LivesController {
    private $liveModel;
    private $liveProductModel;
    private $featuredEventModel;
    private $shoppingService;
    private $streamService;

    public function __construct() {
        $this->liveModel = new Live();
        $this->liveProductModel = new LiveProduct();
        $this->featuredEventModel = new LiveFeaturedEvent();
        $this->shoppingService = new LiveShoppingService();
        $this->streamService = new LiveStreamService();
    }

    /**
     * Página de programação de lives (listagem pública)
     */
    public function index(Request $request) {
        if (!$this->checkAccess()) return;

        $liveAtiva = $this->liveModel->getActive();
        $agendadas = $this->liveModel->getByStatus('scheduled', 10);
        $encerradas = $this->liveModel->getByStatus('ended', 12);

        // Para encerradas, só mostrar as que têm gravação
        $encerradas = array_filter($encerradas, function($l) {
            return !empty($l['recording_url']);
        });

        $title = 'Lives - Braziliana';

        include __DIR__ . '/../Views/lives/index.php';
    }

    /**
     * Verifica se módulo está acessível
     */
    private function checkAccess(): bool {
        $perfil = $_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? 'cliente';
        if (!$this->shoppingService->isAccessible($perfil)) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><body><h1>Página não encontrada</h1></body></html>';
            return false;
        }
        return true;
    }

    /**
     * Retorna live ativa (ao vivo agora)
     */
    public function liveNow(Request $request) {
        if (!$this->checkAccess()) return;

        $live = $this->liveModel->getActive();

        if (!$live) {
            // Sem live ativa — mostrar página informativa ou redirecionar
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['live' => null, 'message' => 'Nenhuma live no momento']);
            return;
        }

        // Redirecionar para a live
        header('Location: /lives/' . $live['id']);
        exit;
    }

    /**
     * Página da live (player TikTok-style)
     */
    public function watch(Request $request, $id) {
        if (!$this->checkAccess()) return;

        $live = $this->liveModel->find($id);
        if (!$live) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><body><h1>Live não encontrada</h1></body></html>';
            return;
        }

        // Se live ainda não começou, mostrar página de espera
        if ($live['status'] === 'scheduled') {
            $title = $live['title'] . ' - Em breve';
        } else {
            $title = $live['title'];
        }

        // Dados para a view
        $products = $this->liveProductModel->getByLiveId($id);
        $featuredHistory = $this->featuredEventModel->getHistory($id);
        $playbackUrl = $live['cf_playback_url'] ?? '';
        
        // Gerar URL assinada se necessário
        if (!empty($playbackUrl)) {
            $playbackUrl = $this->streamService->generateSignedPlaybackUrl($playbackUrl);
        }

        // Dados do usuário logado
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        $isLoggedIn = $userId > 0;

        // Verificar se tem cartão cadastrado
        $hasCard = false;
        if ($isLoggedIn) {
            $paymentMethod = new \App\Models\CustomerPaymentMethod();
            $defaultCard = $paymentMethod->getDefault($userId);
            $hasCard = !empty($defaultCard);
        }

        require __DIR__ . '/../Views/lives/watch.php';
    }

    /**
     * Verifica se há live ativa (para banner no site)
     * Retorna JSON para uso via AJAX
     */
    public function checkLiveStatus(Request $request) {
        $perfil = $_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? 'cliente';
        if (!$this->shoppingService->isAccessible($perfil)) {
            header('Content-Type: application/json');
            echo json_encode(['has_live' => false]);
            return;
        }

        $live = $this->liveModel->getActive();

        header('Content-Type: application/json');
        if ($live) {
            echo json_encode([
                'has_live' => true,
                'live_id' => $live['id'],
                'title' => $live['title'],
                'viewers' => (int) ($live['viewers_current'] ?? 0),
            ]);
        } else {
            echo json_encode(['has_live' => false]);
        }
    }
}
