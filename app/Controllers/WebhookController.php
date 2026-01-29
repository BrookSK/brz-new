<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\PaymentService;

class WebhookController extends Controller {
    private $paymentService;

    public function __construct() {
        $this->paymentService = new PaymentService();
    }

    public function asaas(Request $request) {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookAsaas($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function stripe(Request $request) {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookStripe($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
