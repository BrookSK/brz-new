<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\CarneService;

class WebhookCarneController extends Controller {
    private $carneService;

    public function __construct() {
        $this->carneService = new CarneService();
    }

    /**
     * Webhook da Câmbio Real (boleto de produtos)
     */
    public function cambioReal(Request $request) {
        $input = $request->getBody();
        if (empty($input)) {
            $this->json(['error' => 'Payload vazio'], 400);
        }

        $idExterno = $input['id'] ?? $input['payment_id'] ?? $input['boleto_id'] ?? null;
        $status = $input['status'] ?? '';

        if (!$idExterno || !in_array($status, ['paid', 'confirmed', 'approved', 'pago'])) {
            $this->json(['message' => 'Evento ignorado']);
            return;
        }

        $result = $this->carneService->processarPagamentoBoleto($idExterno, 'produtos');
        $this->json(['success' => $result]);
    }

    /**
     * Webhook da Appmax (boleto de taxas)
     */
    public function appmax(Request $request) {
        $input = $request->getBody();
        if (empty($input)) {
            $this->json(['error' => 'Payload vazio'], 400);
        }

        $idExterno = $input['id'] ?? $input['payment_id'] ?? $input['boleto_id'] ?? null;
        $status = $input['status'] ?? '';

        if (!$idExterno || !in_array($status, ['paid', 'confirmed', 'approved', 'pago'])) {
            $this->json(['message' => 'Evento ignorado']);
            return;
        }

        $result = $this->carneService->processarPagamentoBoleto($idExterno, 'taxas');
        $this->json(['success' => $result]);
    }
}
