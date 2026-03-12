<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\PaymentService;

class WebhookController extends Controller {
    private $paymentService;

    public function __construct() {
        $this->paymentService = new PaymentService();
    }

    public function mercadopago(Request $request) {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookMercadoPago($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cambioreal(Request $request) {
        // CambioReal envia x-www-form-urlencoded por padrão
        $payload = [];
        try {
            $payload = $request->getParams();
            if (!is_array($payload)) {
                $payload = [];
            }
        } catch (\Exception $e) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookCambioReal($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
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
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function appmax(Request $request) {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookAppmax($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function stripe(Request $request) {
        $raw = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = $this->paymentService->getStripeWebhookSecret();

        if (empty($secret)) {
            $this->json(['success' => false, 'error' => 'Stripe webhook não configurado (signing secret ausente).'], 500);
            return;
        }

        if (!$this->verifyStripeSignature((string) $sigHeader, (string) $raw, (string) $secret, 300)) {
            $this->json(['success' => false, 'error' => 'Assinatura inválida do webhook Stripe.'], 400);
            return;
        }

        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = $this->paymentService->processarWebhookStripe($payload);
            $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function verifyStripeSignature(string $sigHeader, string $payload, string $secret, int $toleranceSeconds = 300): bool {
        // Stripe-Signature: t=timestamp,v1=signature,...
        if ($sigHeader === '' || $secret === '') {
            return false;
        }

        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $p) {
            $kv = explode('=', trim($p), 2);
            if (count($kv) !== 2) continue;
            $k = trim($kv[0]);
            $v = trim($kv[1]);
            if ($k === 't') {
                $timestamp = ctype_digit($v) ? (int) $v : null;
            } elseif ($k === 'v1') {
                if ($v !== '') {
                    $signatures[] = $v;
                }
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        if ($toleranceSeconds > 0) {
            $age = abs(time() - $timestamp);
            if ($age > $toleranceSeconds) {
                return false;
            }
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (function_exists('hash_equals')) {
                if (hash_equals($expected, $sig)) {
                    return true;
                }
            } else {
                if ($expected === $sig) {
                    return true;
                }
            }
        }

        return false;
    }
}
