<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\CorreiosPacketService;

class AdminCorreiosMundialController extends Controller {
    private CorreiosPacketService $svc;

    public function __construct() {
        $this->svc = new CorreiosPacketService();
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->view('admin/correios-mundial', []);
    }

    public function balance(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $r = $this->svc->getBalance();
        if (empty($r['success'])) {
            $this->json([
                'success' => false,
                'error' => (string) ($r['error'] ?? 'Falha ao consultar saldo.'),
                'http_code' => $r['http_code'] ?? null,
                'request_url' => $r['request_url'] ?? null,
            ], 400);
            return;
        }

        $this->json([
            'success' => true,
            'currentBalance' => $r['currentBalance'] ?? null,
            'http_code' => $r['http_code'] ?? null,
            'request_url' => $r['request_url'] ?? null,
        ]);
    }
}
