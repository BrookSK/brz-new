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

    public function cambiorealTaxas(Request $request) {
        // Câmbio Real Taxas envia x-www-form-urlencoded por padrão (igual à conta Produtos)
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
            $result = $this->paymentService->processarWebhookCambioRealTaxas($payload);
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

    /**
     * Webhook do QuickBooks Online.
     *
     * O QB envia um POST JSON para esta URL quando eventos ocorrem
     * (invoice paga, payment criado, customer atualizado, etc.).
     *
     * Verificação: header "intuit-signature" contém HMAC-SHA256 do body
     * usando o "Verifier Token" configurado no app QuickBooks.
     *
     * Endpoint a cadastrar no developer.intuit.com:
     *   https://seusite.com/webhook/quickbooks
     *
     * Eventos tratados:
     *   - Invoice: Payment (pago) → registra pagamento no QB e atualiza pedido local
     *   - Payment: Created       → confirma pagamento no mapeamento local
     */
    public function quickbooks(Request $request) {
        $raw = (string) file_get_contents('php://input');

        // 1. Verificar assinatura
        $signature  = (string) ($_SERVER['HTTP_INTUIT_SIGNATURE'] ?? '');
        $verifier   = $this->getQbVerifierToken();

        if ($verifier !== '' && $signature !== '') {
            $expected = base64_encode(hash_hmac('sha256', $raw, $verifier, true));
            if (!hash_equals($expected, $signature)) {
                http_response_code(401);
                $this->json(['success' => false, 'error' => 'Assinatura inválida'], 401);
                return;
            }
        }

        // 2. Decodificar payload
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => 'Payload inválido'], 400);
            return;
        }

        // 3. Processar eventos
        try {
            $result = $this->processarEventosQuickBooks($payload);
            // QB exige HTTP 200 para considerar entregue
            http_response_code(200);
            $this->json(['success' => true, 'processed' => $result]);
        } catch (\Throwable $e) {
            // Retornar 200 mesmo em erro para evitar reenvios infinitos do QB
            // O erro fica registrado no log interno
            error_log('[QB Webhook] Erro: ' . $e->getMessage());
            http_response_code(200);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Processa o array de eventos recebidos do QuickBooks.
     * Estrutura do payload QB:
     * {
     *   "eventNotifications": [{
     *     "realmId": "123456",
     *     "dataChangeEvent": {
     *       "entities": [
     *         { "name": "Invoice", "id": "42", "operation": "Update", "lastUpdated": "..." },
     *         { "name": "Payment", "id": "7",  "operation": "Create", "lastUpdated": "..." }
     *       ]
     *     }
     *   }]
     * }
     */
    private function processarEventosQuickBooks(array $payload): array
    {
        $processed = [];
        $notifications = $payload['eventNotifications'] ?? [];

        foreach ($notifications as $notification) {
            $realmId  = (string) ($notification['realmId'] ?? '');
            $entities = $notification['dataChangeEvent']['entities'] ?? [];

            foreach ($entities as $entity) {
                $name      = (string) ($entity['name']      ?? '');
                $qbId      = (string) ($entity['id']        ?? '');
                $operation = (string) ($entity['operation'] ?? '');

                $key = $name . ':' . $qbId . ':' . $operation;

                try {
                    $result = $this->tratarEntidadeQuickBooks($name, $qbId, $operation, $realmId);
                    $processed[$key] = $result;
                } catch (\Throwable $e) {
                    $processed[$key] = ['erro' => $e->getMessage()];
                    error_log('[QB Webhook] Entidade ' . $key . ': ' . $e->getMessage());
                }
            }
        }

        return $processed;
    }

    /**
     * Trata uma entidade específica recebida no webhook.
     */
    private function tratarEntidadeQuickBooks(string $name, string $qbId, string $operation, string $realmId): array
    {
        $qb  = new \App\Services\QuickBooksService();
        $pdo = \Config\Database::getConnection();

        switch ($name) {
            case 'Payment':
                // Um pagamento foi criado/atualizado no QB
                // Buscar o mapeamento local pelo qb_payment_id
                if ($operation === 'Create' || $operation === 'Update') {
                    $stmt = $pdo->prepare(
                        'SELECT * FROM quickbooks_pedido_map WHERE qb_payment_id = ? LIMIT 1'
                    );
                    $stmt->execute([$qbId]);
                    $mapa = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if ($mapa) {
                        // Atualizar sincronizado_em
                        $pdo->prepare(
                            'UPDATE quickbooks_pedido_map SET sincronizado_em = NOW() WHERE id = ?'
                        )->execute([$mapa['id']]);

                        return ['pedido_id' => $mapa['pedido_id'], 'acao' => 'payment_synced'];
                    }
                }
                return ['acao' => 'payment_sem_mapeamento_local'];

            case 'Invoice':
                // Uma invoice foi atualizada — verificar se foi paga (Balance = 0)
                if ($operation === 'Update') {
                    try {
                        $invoice = $qb->getInvoice($qbId);
                        $inv     = $invoice['Invoice'] ?? [];
                        $balance = (float) ($inv['Balance'] ?? -1);
                        $total   = (float) ($inv['TotalAmt'] ?? 0);

                        // Buscar pedido local pelo qb_invoice_id
                        $stmt = $pdo->prepare(
                            'SELECT * FROM quickbooks_pedido_map WHERE qb_invoice_id = ? LIMIT 1'
                        );
                        $stmt->execute([$qbId]);
                        $mapa = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($mapa && $balance === 0.0 && $total > 0) {
                            // Invoice totalmente paga — atualizar sincronizado_em
                            $pdo->prepare(
                                'UPDATE quickbooks_pedido_map SET sincronizado_em = NOW() WHERE id = ?'
                            )->execute([$mapa['id']]);

                            // Registrar no log de sync
                            $uid = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
                            $pdo->prepare(
                                'INSERT INTO quickbooks_sync_log
                                 (entidade, entidade_id, qb_id, acao, status, payload, usuario_id)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)'
                            )->execute([
                                'invoice',
                                $mapa['pedido_id'],
                                $qbId,
                                'webhook_paid',
                                'success',
                                json_encode(['balance' => $balance, 'total' => $total], JSON_UNESCAPED_UNICODE),
                                $uid,
                            ]);

                            return [
                                'pedido_id' => $mapa['pedido_id'],
                                'acao'      => 'invoice_paga',
                                'total'     => $total,
                            ];
                        }

                        return ['acao' => 'invoice_atualizada', 'balance' => $balance];
                    } catch (\Throwable $e) {
                        return ['acao' => 'invoice_fetch_erro', 'erro' => $e->getMessage()];
                    }
                }
                return ['acao' => 'invoice_operacao_ignorada'];

            default:
                // Entidade não tratada — apenas logar
                return ['acao' => 'ignorado'];
        }
    }

    /**
     * Retorna o Verifier Token do QuickBooks configurado no sistema.
     */
    private function getQbVerifierToken(): string
    {
        try {
            $pdo  = \Config\Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT valor FROM configuracoes_sistema WHERE chave = 'qb_verifier_token' LIMIT 1"
            );
            $stmt->execute();
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
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
