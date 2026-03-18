<?php
namespace App\Services;

use App\Core\Url;

use App\Models\PedidoEcommerce;

class PaymentService {
    private $asaasApiKey;
    private $stripeApiKey;
    private $stripePublishableKey;
    private $stripeEnabled;
    private $stripeAmbiente;
    private $stripeWebhookSecret;
    private $pedidoModel;
    private $asaasAmbiente;
    private $appmaxEnabled;
    private $appmaxClientId;
    private $appmaxClientSecret;
    private $appmaxAppId;
    private $appmaxV3AccessToken;
    private $appmaxAmbiente;
    private $appmaxBaseUrl;
    private $appmaxAccessToken;
    private $appmaxAccessTokenExpiresAt;
    private $mercadoPagoEnabled;
    private $mercadoPagoAccessToken;
    private $mercadoPagoSellerAccessToken;

    private $cambioRealEnabled;
    private $cambioRealAppId;
    private $cambioRealAppSecret;
    private $cambioRealAppPublic;
    private $cambioRealBaseUrl;
    
    private function garantirTabelaPedidoPagamentos(): void {
        try {
            $db = \Config\Database::getConnection();
            $db->exec("CREATE TABLE IF NOT EXISTS `pedido_pagamentos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `pedido_id` int(11) NOT NULL,
                `componente` varchar(30) NOT NULL,
                `gateway` varchar(30) NOT NULL,
                `metodo` varchar(30) DEFAULT NULL,
                `moeda` varchar(3) NOT NULL DEFAULT 'BRL',
                `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
                `payment_id` varchar(255) DEFAULT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'pending',
                `gateway_status` varchar(80) DEFAULT NULL,
                `invoice_url` text,
                `bank_slip_url` text,
                `digitable_line` text,
                `pix_encoded_image` longtext,
                `pix_payload` longtext,
                `metadata` json DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pp_pedido` (`pedido_id`),
                KEY `idx_pp_pedido_comp` (`pedido_id`, `componente`),
                UNIQUE KEY `uk_pp_gateway_payment_comp` (`gateway`, `payment_id`, `componente`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Migração best-effort: versões antigas tinham UNIQUE(gateway,payment_id)
            // e isso impede múltiplos componentes (ex.: produto+imposto) no mesmo payment_id.
            try {
                $db->exec('ALTER TABLE pedido_pagamentos DROP INDEX uk_pp_gateway_payment');
            } catch (\Exception $e) {
            }

            try {
                $db->exec('ALTER TABLE pedido_pagamentos ADD UNIQUE KEY uk_pp_gateway_payment_comp (gateway, payment_id, componente)');
            } catch (\Exception $e) {
            }

        } catch (\Exception $e) {
        }

    }

    private function atualizarPaymentLinkPaymentPorGatewayPaymentId(string $paymentId, string $gateway, string $paymentStatusInterno, string $gatewayStatus = ''): void {
        $paymentId = trim((string) $paymentId);
        $gateway = strtolower(trim((string) $gateway));
        if ($paymentId === '' || $gateway === '') return;

        // internal -> status do histórico
        $st = strtolower(trim((string) $paymentStatusInterno));
        $histStatus = $st;
        if (in_array($st, ['approved', 'aprovado', 'paid', 'pago', 'succeeded', 'success'], true)) {
            $histStatus = 'paid';
        } elseif (in_array($st, ['rejected', 'failed', 'canceled', 'cancelled'], true)) {
            $histStatus = 'failed';
        } elseif (in_array($st, ['refunded'], true)) {
            $histStatus = 'refunded';
        } elseif ($st === '') {
            $histStatus = 'pending';
        }

        try {
            $pls = new \App\Services\PaymentLinkService();
            $db = \Config\Database::getConnection();
            $stSel = $db->prepare('SELECT id FROM payment_link_payments WHERE LOWER(gateway) = ? AND gateway_payment_id = ? ORDER BY id DESC LIMIT 5');
            $stSel->execute([$gateway, $paymentId]);
            $ids = $stSel->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            foreach ($ids as $id) {
                $pls->updatePaymentAttempt((int) $id, [
                    'status' => $histStatus,
                    'metadata' => json_encode(['gateway_status' => $gatewayStatus], JSON_UNESCAPED_UNICODE),
                ]);
            }
        } catch (\Exception $e) {
        }
    }

    public function createCambioRealDirectPaymentProdutoBoleto(
        int $pedidoId,
        float $valorBrlOriginal,
        string $descricao,
        array $client
    ): array {
        $pedidoId = (int) $pedidoId;
        $valorBrlOriginal = (float) $valorBrlOriginal;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if ($valorBrlOriginal <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }
        if (!$this->isCambioRealEnabled()) {
            return ['success' => false, 'error' => 'Câmbio Real está desabilitado.'];
        }

        $clientName = (string) ($client['name'] ?? ($client['nome'] ?? ''));
        $clientEmail = (string) ($client['email'] ?? '');
        $clientDoc = (string) ($client['document'] ?? ($client['documento'] ?? ''));
        $clientBirth = (string) ($client['birth_date'] ?? ($client['data_nascimento'] ?? ''));
        $clientPhone = (string) ($client['phone'] ?? ($client['telefone'] ?? ''));
        $clientIp = (string) ($client['ip'] ?? '127.0.0.1');
        $addr = is_array($client['address'] ?? null) ? (array) $client['address'] : [];

        $payload = [
            'order_id' => (string) $pedidoId,
            'amount' => round($valorBrlOriginal, 2),
            'currency' => 'BRL',
            'payment_method' => 'boleto',
            'client' => [
                'name' => $clientName,
                'email' => $clientEmail,
                'document' => $clientDoc,
                'birth_date' => $clientBirth,
                'phone' => $clientPhone,
                'ip' => $clientIp,
                'address' => [
                    'state' => (string) ($addr['state'] ?? ($addr['estado'] ?? '')),
                    'city' => (string) ($addr['city'] ?? ($addr['cidade'] ?? '')),
                    'zip_code' => (string) ($addr['zip_code'] ?? ($addr['cep'] ?? '')),
                    'district' => (string) ($addr['district'] ?? ($addr['bairro'] ?? '')),
                    'street' => (string) ($addr['street'] ?? ($addr['endereco'] ?? '')),
                    'number' => (string) ($addr['number'] ?? ($addr['numero'] ?? '')),
                ],
            ],
            'duplicate' => 0,
            'take_rates' => 1,
            'products' => [
                [
                    'descricao' => $descricao,
                    'base_value' => round($valorBrlOriginal, 2),
                    'valor' => round($valorBrlOriginal, 2),
                    'qty' => 1,
                    'ref' => (string) $pedidoId,
                ]
            ],
        ];

        try {
            $resp = $this->cambioRealRequest('POST', '/service/v2/checkout/request', $payload);
            $data = is_array($resp['data'] ?? null) ? (array) $resp['data'] : [];
            $status = strtolower(trim((string) ($resp['status'] ?? '')));
            if ($status !== '' && $status !== 'success') {
                $msg = (string) ($resp['message'] ?? 'Falha ao criar boleto no Câmbio Real (Direct API)');
                return ['success' => false, 'error' => 'Câmbio Real: ' . $msg];
            }

            $paymentId = (string) ($data['id'] ?? '');
            $code = (string) ($data['code'] ?? '');
            $tx = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];

            $gatewayStatus = strtoupper(trim((string) ($tx['status'] ?? ($tx['payment_status'] ?? 'PENDING'))));
            $ticketUrl = (string) ($tx['ticket_url'] ?? '');
            $digitableLine = (string) ($tx['digitable_line'] ?? ($tx['linha_digitavel'] ?? ($tx['barcode_number'] ?? '')));

            if ($paymentId === '') {
                return ['success' => false, 'error' => 'Câmbio Real: resposta inválida (id ausente)'];
            }

            $this->registrarPedidoPagamentoSplit([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'cambioreal',
                'metodo' => 'boleto',
                'moeda' => 'BRL',
                'valor' => round($valorBrlOriginal, 2),
                'payment_id' => $paymentId,
                'status' => 'pending',
                'invoice_url' => $ticketUrl,
                'bank_slip_url' => $ticketUrl,
                'digitable_line' => $digitableLine,
                'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : 'PENDING',
                'metadata' => json_encode([
                    'raw' => $resp,
                    'code' => $code,
                    'amount_brl_sent' => round($valorBrlOriginal, 2),
                    'take_rates_sent' => 1,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'code' => $code,
                'invoice_url' => $ticketUrl,
                'bank_slip_url' => $ticketUrl,
                'digitable_line' => $digitableLine,
                'raw' => $resp,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createStripeCheckoutSessionForPaymentLink(int $paymentLinkPaymentId, float $valorUsd, string $descricao, array $customer = [], ?string $successUrl = null, ?string $cancelUrl = null): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $paymentLinkPaymentId = (int) $paymentLinkPaymentId;
        if ($paymentLinkPaymentId <= 0) {
            return ['success' => false, 'error' => 'Payment Link Payment inválido.'];
        }

        $amountCents = (int) round($valorUsd * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para cobrança.'];
        }

        $base = Url::base();
        if ($successUrl === null || trim((string) $successUrl) === '') {
            $successUrl = rtrim($base, '/') . '/pagar?stripe=success';
        }
        if ($cancelUrl === null || trim((string) $cancelUrl) === '') {
            $cancelUrl = rtrim($base, '/') . '/pagar?stripe=cancel';
        }

        $body = [
            'mode' => 'payment',
            'success_url' => (string) $successUrl,
            'cancel_url' => (string) $cancelUrl,
            'client_reference_id' => 'payment_link_payment:' . (string) $paymentLinkPaymentId,
            'metadata[payment_link_payment_id]' => (string) $paymentLinkPaymentId,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][product_data][name]' => $descricao !== '' ? $descricao : ('Payment Link #' . $paymentLinkPaymentId),
        ];

        if (!empty($customer['email'])) {
            $body['customer_email'] = (string) $customer['email'];
        }

        try {
            $session = $this->stripeRequest('POST', '/v1/checkout/sessions', $body);
            $id = (string) ($session['id'] ?? '');
            $url = (string) ($session['url'] ?? '');
            $paymentIntentId = (string) ($session['payment_intent'] ?? '');
            if ($id === '' || $url === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar Checkout Session.'];
            }

            return [
                'success' => true,
                'session_id' => $id,
                'url' => $url,
                'payment_intent_id' => $paymentIntentId,
                'raw' => $session,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCambioRealDirectPaymentForPaymentLink(
        int $paymentLinkPaymentId,
        float $valorBrlOriginal,
        string $descricao,
        array $client,
        string $paymentMethod,
        array $card = [],
        array $products = []
    ): array {
        $paymentLinkPaymentId = (int) $paymentLinkPaymentId;
        $valorBrlOriginal = (float) $valorBrlOriginal;
        $paymentMethod = strtolower(trim((string) $paymentMethod));

        if ($paymentLinkPaymentId <= 0) {
            return ['success' => false, 'error' => 'Identificador inválido'];
        }
        if ($valorBrlOriginal <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }
        if (!$this->isCambioRealEnabled()) {
            return ['success' => false, 'error' => 'Câmbio Real está desabilitado.'];
        }
        if (!in_array($paymentMethod, ['pix', 'boleto', 'credit_card', 'debit_card'], true)) {
            return ['success' => false, 'error' => 'Método inválido'];
        }

        $clientName = (string) ($client['name'] ?? ($client['nome'] ?? ''));
        $clientEmail = (string) ($client['email'] ?? '');
        $clientDoc = (string) ($client['document'] ?? ($client['documento'] ?? ''));
        $clientBirth = (string) ($client['birth_date'] ?? ($client['data_nascimento'] ?? ''));
        $clientPhone = (string) ($client['phone'] ?? ($client['telefone'] ?? ''));
        $clientIp = (string) ($client['ip'] ?? '127.0.0.1');
        $addr = is_array($client['address'] ?? null) ? (array) $client['address'] : [];

        $productsPayload = [];
        $sum = 0.0;
        if (!empty($products)) {
            foreach ($products as $i => $p) {
                if (!is_array($p)) continue;
                $name = trim((string) ($p['name'] ?? ($p['descricao'] ?? '')));
                $rawValue = $p['value'] ?? ($p['valor'] ?? 0);
                if (is_string($rawValue)) {
                    $sv = trim($rawValue);
                    $sv = str_replace(' ', '', $sv);
                    $lastComma = strrpos($sv, ',');
                    $lastDot = strrpos($sv, '.');
                    $decSep = null;
                    if ($lastComma !== false || $lastDot !== false) {
                        $decSep = ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) ? ',' : '.';
                    }
                    if ($decSep !== null) {
                        $thousandsSep = $decSep === ',' ? '.' : ',';
                        $sv = str_replace($thousandsSep, '', $sv);
                        if ($decSep === ',') $sv = str_replace(',', '.', $sv);
                    }
                    $sv = preg_replace('/[^0-9.\-]/', '', $sv);
                    $rawValue = $sv;
                }
                $value = (float) $rawValue;
                $qty = (int) ($p['qty'] ?? ($p['quantidade'] ?? 1));
                if ($qty <= 0) $qty = 1;
                if ($qty > 99) $qty = 99;
                if ($value < 0) $value = 0.0;
                if ($name === '' || $value <= 0) continue;
                $lineTotal = round($value * $qty, 2);
                $sum += $lineTotal;
                $productsPayload[] = [
                    'descricao' => $name,
                    'base_value' => round($value, 2),
                    'valor' => round($value, 2),
                    'qty' => $qty,
                    'ref' => 'PAYLINK_' . (string) $paymentLinkPaymentId . '_' . (string) $i,
                ];
            }
        }
        $sum = round($sum, 2);
        if (!empty($productsPayload) && $sum > 0) {
            $valorBrlOriginal = $sum;
        }
        if (empty($productsPayload)) {
            $productsPayload = [
                [
                    'descricao' => $descricao,
                    'base_value' => round($valorBrlOriginal, 2),
                    'valor' => round($valorBrlOriginal, 2),
                    'qty' => 1,
                    'ref' => 'PAYLINK_' . (string) $paymentLinkPaymentId,
                ]
            ];
        }

        $payload = [
            'order_id' => 'PAYLINK_' . (string) $paymentLinkPaymentId,
            'amount' => round($valorBrlOriginal, 2),
            'currency' => 'BRL',
            'payment_method' => $paymentMethod,
            'client' => [
                'name' => $clientName,
                'email' => $clientEmail,
                'document' => $clientDoc,
                'birth_date' => $clientBirth,
                'phone' => $clientPhone,
                'ip' => $clientIp,
                'address' => [
                    'state' => (string) ($addr['state'] ?? ($addr['estado'] ?? '')),
                    'city' => (string) ($addr['city'] ?? ($addr['cidade'] ?? '')),
                    'zip_code' => (string) ($addr['zip_code'] ?? ($addr['cep'] ?? '')),
                    'district' => (string) ($addr['district'] ?? ($addr['bairro'] ?? '')),
                    'street' => (string) ($addr['street'] ?? ($addr['endereco'] ?? '')),
                    'number' => (string) ($addr['number'] ?? ($addr['numero'] ?? '')),
                ],
            ],
            'duplicate' => 0,
            'take_rates' => 1,
            'products' => $productsPayload,
        ];

        if (in_array($paymentMethod, ['credit_card', 'debit_card'], true)) {
            $token = trim((string) ($card['token'] ?? ''));
            $brand = trim((string) ($card['brand'] ?? ''));
            $bin = trim((string) ($card['bin'] ?? ''));
            $dfpId = trim((string) ($card['dfp_id'] ?? ''));
            $holder = trim((string) ($card['holder'] ?? ($card['card_holder'] ?? '')));
            if ($token === '' || $brand === '' || $bin === '' || $dfpId === '' || $holder === '') {
                return ['success' => false, 'error' => 'Dados do cartão incompletos'];
            }

            $payload['card'] = [
                'token' => $token,
                'brand' => $brand,
                'bin' => $bin,
                'dfp_id' => $dfpId,
                'holder' => $holder,
                'installments' => (int) ($card['installments'] ?? 1),
            ];
        }

        try {
            $resp = $this->cambioRealRequest('POST', '/service/v2/checkout/request', $payload);
            $data = is_array($resp['data'] ?? null) ? (array) $resp['data'] : [];
            $status = strtolower(trim((string) ($resp['status'] ?? '')));
            if ($status !== '' && $status !== 'success') {
                $msg = (string) ($resp['message'] ?? 'Falha ao criar pagamento no Câmbio Real (Direct API)');
                return ['success' => false, 'error' => $msg, 'raw' => $resp];
            }

            $tx = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];
            $paymentId = (string) ($data['token'] ?? ($tx['token'] ?? ($data['id'] ?? '')));

            $persist = [];
            if ($paymentMethod === 'pix') {
                $persist['pix_payload'] = (string) ($tx['number'] ?? '');
                $persist['pix_encoded_image'] = (string) ($tx['barcode'] ?? '');
            }
            if ($paymentMethod === 'boleto') {
                $persist['bank_slip_url'] = (string) ($tx['ticket_url'] ?? ($tx['boleto_url'] ?? ''));
                $persist['digitable_line'] = (string) ($tx['digitable_line'] ?? ($tx['linha_digitavel'] ?? ''));
            }

            return [
                'success' => true,
                'gateway' => 'cambioreal',
                'payment_id' => $paymentId,
                'persist' => $persist,
                'raw' => $resp,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCambioRealDirectPaymentProdutoCartao(
        int $pedidoId,
        float $valorBrlOriginal,
        float $amountUsdCalc,
        string $descricao,
        array $client,
        array $card
    ): array {
        $pedidoId = (int) $pedidoId;
        $valorBrlOriginal = (float) $valorBrlOriginal;
        $amountUsdCalc = (float) $amountUsdCalc;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if ($valorBrlOriginal <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }
        if (!$this->isCambioRealEnabled()) {
            return ['success' => false, 'error' => 'Câmbio Real está desabilitado.'];
        }

        $orderId = (string) $pedidoId;
        $paymentMethod = 'credit_card';

        $token = trim((string) ($card['token'] ?? ''));
        $brand = trim((string) ($card['brand'] ?? ''));
        $bin = preg_replace('/\D+/', '', (string) ($card['bin'] ?? ''));
        $dfpId = trim((string) ($card['dfp_id'] ?? ''));
        $holder = trim((string) ($card['holder'] ?? ''));
        $installments = (int) ($card['installments'] ?? 1);
        if ($installments <= 0) $installments = 1;
        $type = strtolower(trim((string) ($card['type'] ?? 'credit')));
        if (!in_array($type, ['credit', 'debit'], true)) {
            $type = 'credit';
        }
        if ($type === 'debit') {
            $paymentMethod = 'debit_card';
        }

        if ($token === '' || $brand === '' || $bin === '' || $dfpId === '' || $holder === '') {
            return ['success' => false, 'error' => 'Câmbio Real: token/brand/bin/dfp_id/holder são obrigatórios para Direct API'];
        }

        $clientName = (string) ($client['name'] ?? ($client['nome'] ?? ''));
        $clientEmail = (string) ($client['email'] ?? '');
        $clientDoc = (string) ($client['document'] ?? ($client['documento'] ?? ''));
        $clientBirth = (string) ($client['birth_date'] ?? ($client['data_nascimento'] ?? ''));
        $clientPhone = (string) ($client['phone'] ?? ($client['telefone'] ?? ''));
        $clientIp = (string) ($client['ip'] ?? '127.0.0.1');

        $addr = is_array($client['address'] ?? null) ? (array) $client['address'] : [];

        $payload = [
            'order_id' => $orderId,
            'amount' => round($valorBrlOriginal, 2),
            'currency' => 'BRL',
            'payment_method' => $paymentMethod,
            'client' => [
                'name' => $clientName,
                'email' => $clientEmail,
                'document' => $clientDoc,
                'birth_date' => $clientBirth,
                'phone' => $clientPhone,
                'ip' => $clientIp,
                'address' => [
                    'state' => (string) ($addr['state'] ?? ($addr['estado'] ?? '')),
                    'city' => (string) ($addr['city'] ?? ($addr['cidade'] ?? '')),
                    'zip_code' => (string) ($addr['zip_code'] ?? ($addr['cep'] ?? '')),
                    'district' => (string) ($addr['district'] ?? ($addr['bairro'] ?? '')),
                    'street' => (string) ($addr['street'] ?? ($addr['endereco'] ?? '')),
                    'number' => (string) ($addr['number'] ?? ($addr['numero'] ?? '')),
                ],
            ],
            'card' => [
                'bin' => $bin,
                'brand' => $brand,
                'country' => 'BR',
                'dfp_id' => $dfpId,
                'holder' => $holder,
                'installments' => $installments,
                'token' => $token,
                'type' => $type,
            ],
            'duplicate' => 0,
            'take_rates' => 1,
            'products' => [
                [
                    'descricao' => $descricao,
                    'base_value' => round($valorBrlOriginal, 2),
                    'valor' => round($valorBrlOriginal, 2),
                    'qty' => 1,
                    'ref' => (string) $pedidoId,
                ]
            ],
        ];

        try {
            $resp = $this->cambioRealRequest('POST', '/service/v2/checkout/request', $payload);
            $data = is_array($resp['data'] ?? null) ? (array) $resp['data'] : [];
            $status = strtolower(trim((string) ($resp['status'] ?? '')));
            if ($status !== '' && $status !== 'success') {
                $msg = (string) ($resp['message'] ?? 'Falha ao criar transação no Câmbio Real (Direct API)');
                return ['success' => false, 'error' => 'Câmbio Real: ' . $msg];
            }

            $paymentId = (string) ($data['id'] ?? '');
            $code = (string) ($data['code'] ?? '');
            $tx = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];
            $gatewayStatus = strtoupper(trim((string) ($tx['status'] ?? ($tx['payment_status'] ?? 'PENDING'))));
            $ticketUrl = (string) ($tx['ticket_url'] ?? '');

            if ($paymentId === '') {
                return ['success' => false, 'error' => 'Câmbio Real: resposta inválida (id ausente)'];
            }

            $this->registrarPedidoPagamentoSplit([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'cambioreal',
                'metodo' => ($type === 'debit' ? 'cartao_debito' : 'cartao_credito'),
                'moeda' => 'BRL',
                'valor' => round($valorBrlOriginal, 2),
                'payment_id' => $paymentId,
                'status' => 'pending',
                'invoice_url' => $ticketUrl,
                'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : 'PENDING',
                'metadata' => json_encode([
                    'raw' => $resp,
                    'code' => $code,
                    'amount_brl_sent' => round($valorBrlOriginal, 2),
                    'amount_usd_calc' => round($amountUsdCalc, 2),
                    'take_rates_sent' => 1,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'code' => $code,
                'invoice_url' => $ticketUrl,
                'raw' => $resp,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCambioRealPixPaymentProduto(int $pedidoId, float $amountUsd, float $valorBrlOriginal, string $descricao, array $customer = [], ?string $successUrl = null, ?string $errorUrl = null): array {
        $pedidoId = (int) $pedidoId;
        $amountUsd = (float) $amountUsd;
        $valorBrlOriginal = (float) $valorBrlOriginal;

        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if ($amountUsd <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }
        if ($valorBrlOriginal <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }

        $payload = [
            'order_id' => (string) ($pedidoId . '-produto'),
            // Para PIX, queremos que o QR gere exatamente o valor em BRL do seu checkout.
            // Se enviar USD, o gateway converte com a taxa dele e o valor em BRL pode divergir.
            'amount' => round($valorBrlOriginal, 2),
            'currency' => 'BRL',
            'payment_method' => 'pix',
            'client' => (array) $customer,
            'duplicate' => 0,
            // Garante que taxas/custos/IOF não sejam repassados ao cliente via acréscimo no PIX.
            // Quando take_rates=0, o comportamento pode seguir o padrão do Painel e aumentar o valor cobrado.
            'take_rates' => 1,
            'products' => [
                [
                    'descricao' => $descricao !== '' ? $descricao : ('Pedido #' . $pedidoId . ' (produtos)'),
                    'base_value' => round($valorBrlOriginal, 2),
                    'valor' => round($valorBrlOriginal, 2),
                    'qty' => 1,
                    'ref' => (string) $pedidoId,
                ]
            ],
        ];

        try {
            $resp = $this->cambioRealRequest('POST', '/service/v2/checkout/request', $payload);
            $data = is_array($resp['data'] ?? null) ? (array) $resp['data'] : [];
            $tx = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];

            $status = strtolower(trim((string) ($resp['status'] ?? '')));
            if ($status !== '' && $status !== 'success') {
                $msg = (string) ($resp['message'] ?? 'Falha ao criar PIX no Câmbio Real');
                return ['success' => false, 'error' => 'Câmbio Real: ' . $msg];
            }

            $paymentId = (string) ($data['id'] ?? '');
            if ($paymentId === '') {
                $paymentId = (string) ($data['code'] ?? '');
            }

            $pixPayload = trim((string) ($tx['number'] ?? ''));
            $pixImg = trim((string) ($tx['barcode'] ?? ''));
            $invoiceUrl = trim((string) ($tx['ticket_url'] ?? ''));
            $gatewayStatus = trim((string) ($tx['code'] ?? ($data['code'] ?? 'AGUARDANDO_CLIENTE')));

            if ($pixImg !== '') {
                // No Direct API o barcode geralmente vem como data:image/svg+xml;base64,...
                $pixImg = preg_replace('#^data:image/[^;]+;base64,#', '', $pixImg);
                $pixImg = trim((string) $pixImg);
            }

            if ($paymentId === '') {
                return ['success' => false, 'error' => 'Câmbio Real: resposta inválida ao criar PIX (id ausente).'];
            }

            $this->registrarPedidoPagamentoSplit([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'cambioreal',
                'metodo' => 'pix',
                'moeda' => 'BRL',
                'valor' => round($valorBrlOriginal, 2),
                'payment_id' => $paymentId,
                'status' => 'pending',
                'invoice_url' => $invoiceUrl,
                'pix_encoded_image' => $pixImg,
                'pix_payload' => $pixPayload,
                'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : 'AGUARDANDO_CLIENTE',
                'metadata' => json_encode([
                    'raw' => $resp,
                    'amount_brl_sent' => round($valorBrlOriginal, 2),
                    'transaction_amount_brl_returned' => (float) ($tx['amount'] ?? 0),
                    'take_rates_sent' => 1,
                    'amount_usd_calc' => round($amountUsd, 2),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'invoice_url' => $invoiceUrl,
                'pix' => [
                    'encodedImage' => $pixImg !== '' ? $pixImg : null,
                    'payload' => $pixPayload !== '' ? $pixPayload : null,
                ],
                'raw' => $resp,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function isMercadoPagoEnabled(): bool {
        $v = strtolower(trim((string) $this->mercadoPagoEnabled));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    private function isCambioRealEnabled(): bool {
        $v = strtolower(trim((string) $this->cambioRealEnabled));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    public function getCambioRealAppId(): string {
        return (string) ($this->cambioRealAppId ?? '');
    }

    public function getCambioRealAppPublic(): string {
        return (string) ($this->cambioRealAppPublic ?? '');
    }

    public function getCambioRealBaseUrlPublic(): string {
        return (string) $this->getCambioRealBaseUrl();
    }

    private function getCambioRealBaseUrl(): string {
        $base = trim((string) ($this->cambioRealBaseUrl ?? ''));
        if ($base !== '') {
            return rtrim($base, '/');
        }
        return 'https://sandbox.cambioreal.com';
    }

    private function cambioRealRequest(string $method, string $path, ?array $body = null): array {
        if (!$this->isCambioRealEnabled()) {
            throw new \Exception('Câmbio Real está desativado');
        }
        if (empty($this->cambioRealAppId) || empty($this->cambioRealAppSecret)) {
            throw new \Exception('Câmbio Real não configurado (APP ID/SECRET ausentes)');
        }

        $url = $this->getCambioRealBaseUrl() . '/' . ltrim($path, '/');
        $auth = base64_encode((string) $this->cambioRealAppId . ':' . (string) $this->cambioRealAppSecret);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $auth,
            'X-APP-ID: ' . (string) $this->cambioRealAppId,
            'X-APP-SECRET: ' . (string) $this->cambioRealAppSecret,
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($payload === false) {
                throw new \Exception('Câmbio Real: falha ao codificar payload (JSON inválido)');
            }
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com Câmbio Real: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                if (in_array($httpCode, [401, 403], true)) {
                    throw new \Exception('Câmbio Real: credenciais inválidas (APP ID/SECRET) ou Base URL incorreta.');
                }
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                // Evita retornar respostas potencialmente sensíveis para o usuário final
                $msgSafe = (string) $httpCode;
                try {
                    $msgSafe = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : (string) $httpCode;
                } catch (\Exception $e) {
                }
                throw new \Exception('Erro Câmbio Real HTTP ' . $httpCode . ': ' . $msgSafe);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
            $raw = (string) $respBody;
            $head = substr($raw, 0, 300);
            return [
                '__raw_len' => strlen($raw),
                '__raw_head' => $head,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function createCambioRealCheckoutRequestProduto(int $pedidoId, float $valorBrl, string $descricao, array $customer = [], ?string $successUrl = null, ?string $errorUrl = null): array {
        $pedidoId = (int) $pedidoId;
        $valorBrl = (float) $valorBrl;

        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if ($valorBrl <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }
        if (!$this->isCambioRealEnabled()) {
            return ['success' => false, 'error' => 'Câmbio Real está desabilitado.'];
        }

        $base = Url::base();
        if ($successUrl === null || trim((string) $successUrl) === '') {
            $successUrl = rtrim($base, '/') . '/checkout/conclusao/' . $pedidoId . '?cambioreal=success';
        }
        if ($errorUrl === null || trim((string) $errorUrl) === '') {
            $errorUrl = rtrim($base, '/') . '/checkout/conclusao/' . $pedidoId . '?cambioreal=error';
        }

        $nome = (string) ($customer['name'] ?? ($customer['nome'] ?? 'Cliente'));
        $email = (string) ($customer['email'] ?? ($customer['customer_email'] ?? ($customer['email_address'] ?? '')));
        $phone = (string) ($customer['phone_number'] ?? ($customer['telefone'] ?? ($customer['phone'] ?? '')));

        $nome = trim($nome);
        if ($nome === '') {
            $nome = 'Cliente';
        }

        $email = trim((string) $email);
        // remove espaços internos que às vezes vêm de autocomplete/cópia
        $email = preg_replace('/\s+/', '', $email);
        // remove caracteres invisíveis/controle que podem passar pelo trim
        $email = preg_replace('/[\x00-\x1F\x7F]/', '', $email);
        $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $email);
        $email = strtolower($email);
        // força um subset seguro de caracteres (evita unicode estranho em domínio/local-part)
        $email = preg_replace('/[^a-z0-9_\-\.\+@]/', '', $email);
        if ($email === '') {
            return ['success' => false, 'error' => 'Câmbio Real: e-mail do cliente é obrigatório. Verifique o campo E-mail.'];
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            // tenta pegar algum fallback comum sem quebrar o fluxo
            $alt = '';
            foreach (['usuario_email', 'user_email', 'mail'] as $k) {
                if (!empty($customer[$k]) && is_string($customer[$k])) {
                    $alt = trim((string) $customer[$k]);
                    $alt = preg_replace('/\s+/', '', $alt);
                    break;
                }
            }
            if ($alt !== '' && filter_var($alt, FILTER_VALIDATE_EMAIL) !== false) {
                $email = $alt;
            } else {
                return ['success' => false, 'error' => 'Câmbio Real: e-mail do cliente inválido. Verifique o campo E-mail.'];
            }
        }

        try {
            $masked = $email;
            $at = strpos($masked, '@');
            if ($at !== false) {
                $local = substr($masked, 0, $at);
                $domain = substr($masked, $at);
                if (strlen($local) > 2) {
                    $local = substr($local, 0, 2) . '***';
                } else {
                    $local = '***';
                }
                $masked = $local . $domain;
            } else {
                $masked = '***';
            }
            error_log('[CÂMBIOREAL] Cliente email (mask): ' . $masked);

            $hex = bin2hex((string) $email);
            $hexHead = substr($hex, 0, 60);
            error_log('[CÂMBIOREAL] Cliente email bytes: len=' . strlen((string) $email) . ' hex_head=' . $hexHead);
        } catch (\Exception $e) {
        }

        $phone = trim((string) $phone);
        $phoneDigits = $phone;
        if ($phoneDigits !== '') {
            $phoneDigits = preg_replace('/\D+/', '', $phoneDigits);
        }
        $phoneE164 = '';
        if ($phoneDigits !== '') {
            // Tenta padronizar para +E164 (melhor compatibilidade com validações do gateway)
            if (strpos($phoneDigits, '55') === 0) {
                $phoneE164 = '+' . $phoneDigits;
            } elseif (strlen($phoneDigits) >= 10) {
                $phoneE164 = '+55' . $phoneDigits;
            }
        }

        // Alguns ambientes exigem telefone válido; sem isso a API pode retornar mensagens genéricas.
        if ($phoneDigits === '' || strlen($phoneDigits) < 10) {
            return ['success' => false, 'error' => 'Câmbio Real: telefone do cliente inválido. Verifique o campo Telefone.'];
        }

        $cpf = '';
        try {
            $cpf = (string) ($customer['cpf'] ?? ($customer['documento'] ?? ($customer['cpfCnpj'] ?? '')));
            $cpf = preg_replace('/\D+/', '', $cpf);
        } catch (\Exception $e) {
            $cpf = '';
        }

        $client = [
            'name' => $nome,
            'email' => $email,
            'email_address' => $email,
        ];

        // Alguns ambientes validam phone no formato internacional.
        $client['phone_number'] = $phoneE164 !== '' ? $phoneE164 : $phoneDigits;
        $client['phone1'] = $phoneE164 !== '' ? $phoneE164 : $phoneDigits;

        // Conforme documentação do gateway, pode ser exigido em algumas contas.
        if ($cpf !== '') {
            $client['cpf'] = $cpf;
        }

        $payload = [
            'order_id' => (string) ($pedidoId . '-produto'),
            'amount' => round($valorBrl, 2),
            'currency' => 'BRL',
            'client' => $client,
            'duplicate' => 0,
            'take_rates' => 1,
            'url_callback' => (string) $successUrl,
            'url_error' => (string) $errorUrl,
            'products' => [
                [
                    'descricao' => $descricao !== '' ? $descricao : ('Pedido #' . $pedidoId . ' (produtos)'),
                    'base_value' => round($valorBrl, 2),
                    'valor' => round($valorBrl, 2),
                    'qty' => 1,
                    'ref' => (string) $pedidoId,
                ]
            ],
        ];

        try {
            $resp = $this->cambioRealRequest('POST', '/service/v1/checkout/request', $payload);
            $data = is_array($resp['data'] ?? null) ? $resp['data'] : [];

            // Alguns retornos do Câmbio Real vêm como erro lógico (status/message/errors) mesmo com HTTP 200.
            // Nesse caso, não há token/checkout e precisamos mostrar a mensagem real.
            try {
                $hasLogicalError = false;
                if (is_array($resp) && (isset($resp['errors']) || isset($resp['error']) || isset($resp['message']))) {
                    // Trata como erro lógico quando 'data' não existe OU vem vazio/nulo.
                    $dataMissingOrEmpty = false;
                    if (!array_key_exists('data', $resp)) {
                        $dataMissingOrEmpty = true;
                    } else {
                        $dv = $resp['data'];
                        if ($dv === null || $dv === '' || $dv === [] || (is_array($dv) && count($dv) === 0)) {
                            $dataMissingOrEmpty = true;
                        }
                    }
                    if ($dataMissingOrEmpty) {
                        $hasLogicalError = true;
                    }
                }
                if ($hasLogicalError) {
                    $msg = '';
                    if (!empty($resp['message'])) {
                        if (is_string($resp['message'])) {
                            $msg = (string) $resp['message'];
                        } elseif (is_scalar($resp['message'])) {
                            $msg = (string) $resp['message'];
                        } elseif (is_array($resp['message'])) {
                            $msg = (string) json_encode($resp['message'], JSON_UNESCAPED_UNICODE);
                        }
                    }
                    if ($msg === '' && !empty($resp['error'])) {
                        if (is_string($resp['error'])) {
                            $msg = (string) $resp['error'];
                        } elseif (is_scalar($resp['error'])) {
                            $msg = (string) $resp['error'];
                        } elseif (is_array($resp['error'])) {
                            $msg = (string) json_encode($resp['error'], JSON_UNESCAPED_UNICODE);
                        }
                    }

                    $errDetails = '';
                    if (!empty($resp['errors']) && is_array($resp['errors'])) {
                        $first = reset($resp['errors']);
                        if (is_string($first)) {
                            $errDetails = $first;
                        } elseif (is_array($first)) {
                            if (!empty($first['message']) && is_string($first['message'])) {
                                $errDetails = (string) $first['message'];
                            } elseif (!empty($first['error']) && is_string($first['error'])) {
                                $errDetails = (string) $first['error'];
                            } elseif (!empty($first['detail']) && is_string($first['detail'])) {
                                $errDetails = (string) $first['detail'];
                            }

                            if ($errDetails === '' && !empty($first['field']) && is_string($first['field'])) {
                                $errDetails = 'Campo inválido: ' . (string) $first['field'];
                            }

                            if ($errDetails === '') {
                                try {
                                    $errDetails = (string) json_encode($first, JSON_UNESCAPED_UNICODE);
                                } catch (\Exception $e) {
                                    $errDetails = '';
                                }
                            }
                        }
                    }
                    $full = trim($msg . ($errDetails !== '' ? (' - ' . $errDetails) : ''));
                    if ($full === '') {
                        $full = 'Falha ao criar checkout no Câmbio Real.';
                    }

                    try {
                        error_log('[CÂMBIOREAL] Erro lógico em checkout/request: ' . json_encode([
                            'status' => $resp['status'] ?? null,
                            'message' => $resp['message'] ?? null,
                            'errors_keys' => is_array($resp['errors'] ?? null) ? array_keys($resp['errors']) : null,
                            'first_error' => isset($first) ? $first : null,
                        ], JSON_UNESCAPED_UNICODE));
                    } catch (\Exception $e) {
                    }

                    return [
                        'success' => false,
                        'error' => 'Câmbio Real: ' . $full,
                    ];
                }
            } catch (\Exception $e) {
            }

            $token = '';
            foreach (['token', 'checkout_token', 'checkoutToken', 'id'] as $k) {
                if ($token === '' && !empty($data[$k]) && is_string($data[$k])) {
                    $token = (string) $data[$k];
                }
                if ($token === '' && !empty($resp[$k]) && is_string($resp[$k])) {
                    $token = (string) $resp[$k];
                }
            }

            $checkoutUrl = '';
            foreach (['checkout', 'checkout_url', 'checkoutUrl', 'ticket', 'url', 'link', 'redirect_url', 'redirectUrl'] as $k) {
                if ($checkoutUrl === '' && !empty($data[$k]) && is_string($data[$k])) {
                    $checkoutUrl = (string) $data[$k];
                }
                if ($checkoutUrl === '' && !empty($resp[$k]) && is_string($resp[$k])) {
                    $checkoutUrl = (string) $resp[$k];
                }
                if ($checkoutUrl === '' && !empty($data[$k]) && is_array($data[$k])) {
                    $arr = $data[$k];
                    foreach (['url', 'link', 'href', 'checkout', 'ticket'] as $kk) {
                        if ($checkoutUrl === '' && !empty($arr[$kk]) && is_string($arr[$kk])) {
                            $checkoutUrl = (string) $arr[$kk];
                        }
                    }
                }
                if ($checkoutUrl === '' && !empty($resp[$k]) && is_array($resp[$k])) {
                    $arr = $resp[$k];
                    foreach (['url', 'link', 'href', 'checkout', 'ticket'] as $kk) {
                        if ($checkoutUrl === '' && !empty($arr[$kk]) && is_string($arr[$kk])) {
                            $checkoutUrl = (string) $arr[$kk];
                        }
                    }
                }
            }

            $code = (string) (($data['code'] ?? '') !== '' ? ($data['code'] ?? '') : ($resp['code'] ?? ''));
            $id = (string) (($data['id'] ?? '') !== '' ? ($data['id'] ?? '') : ($resp['id'] ?? ''));

            if ($token === '' || $checkoutUrl === '') {
                // Se o gateway retornou 'message/errors', isso é mais útil do que "resposta inválida".
                try {
                    $msg = '';
                    if (!empty($resp['message']) && is_string($resp['message'])) {
                        $msg = (string) $resp['message'];
                    }
                    $errDetails = '';
                    if (!empty($resp['errors']) && is_array($resp['errors'])) {
                        $first = reset($resp['errors']);
                        if (is_string($first)) {
                            $errDetails = (string) $first;
                        } elseif (is_array($first) && !empty($first['message']) && is_string($first['message'])) {
                            $errDetails = (string) $first['message'];
                        }
                    }
                    $full = trim($msg . ($errDetails !== '' ? (' - ' . $errDetails) : ''));
                    if ($full !== '') {
                        return [
                            'success' => false,
                            'error' => 'Câmbio Real: ' . $full,
                        ];
                    }
                } catch (\Exception $e) {
                }

                $respKeys = [];
                $dataKeys = [];
                try {
                    $respKeys = is_array($resp) ? array_keys($resp) : [];
                    $dataKeys = is_array($data) ? array_keys($data) : [];
                } catch (\Exception $e) {
                }

                try {
                    $safe = [
                        'resp_keys' => $respKeys,
                        'data_keys' => $dataKeys,
                        'has_raw' => is_array($resp) && (isset($resp['__raw_len']) || isset($resp['__raw_head'])),
                        'raw_len' => is_array($resp) ? ($resp['__raw_len'] ?? null) : null,
                        'raw_head' => is_array($resp) ? ($resp['__raw_head'] ?? null) : null,
                    ];
                    error_log('[CÂMBIOREAL] Resposta inválida em checkout/request: ' . json_encode($safe, JSON_UNESCAPED_UNICODE));
                } catch (\Exception $e) {
                }

                $keysMsg = '';
                try {
                    $keysMsg = ' (keys=' . implode(',', array_slice($respKeys, 0, 20)) . '; data=' . implode(',', array_slice($dataKeys, 0, 20)) . ')';
                } catch (\Exception $e) {
                    $keysMsg = '';
                }
                return [
                    'success' => false,
                    'error' => 'Câmbio Real: resposta inválida ao criar solicitação de pagamento.' . $keysMsg,
                    'debug_keys' => [
                        'resp' => $respKeys,
                        'data' => $dataKeys,
                    ],
                ];
            }

            $this->registrarPedidoPagamentoSplit([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'cambioreal',
                'metodo' => 'checkout',
                'moeda' => 'BRL',
                'valor' => round($valorBrl, 2),
                'payment_id' => $token,
                'status' => 'pending',
                'invoice_url' => $checkoutUrl,
                'gateway_status' => 'AGUARDANDO_CLIENTE',
                'metadata' => json_encode(['raw' => $resp, 'code' => $code, 'id' => $id], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'payment_id' => $token,
                'invoice_url' => $checkoutUrl,
                'code' => $code,
                'raw' => $resp,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function obterTransacaoCambioReal(string $token): array {
        $token = trim((string) $token);
        if ($token === '') {
            throw new \Exception('Câmbio Real: token inválido');
        }
        return $this->cambioRealRequest('GET', '/service/v1/checkout/get/' . rawurlencode($token), null);
    }

    public function processarWebhookCambioReal(array $payload): array {
        $token = '';
        if (!empty($payload['token']) && is_string($payload['token'])) {
            $token = (string) $payload['token'];
        }
        if ($token === '' && !empty($payload['data']['token']) && is_string($payload['data']['token'])) {
            $token = (string) $payload['data']['token'];
        }
        if ($token === '' && !empty($payload['code']) && is_string($payload['code'])) {
            $token = (string) $payload['code'];
        }
        if ($token === '' && !empty($payload['data']['code']) && is_string($payload['data']['code'])) {
            $token = (string) $payload['data']['code'];
        }
        if ($token === '' && !empty($payload['id']) && (is_string($payload['id']) || is_numeric($payload['id']))) {
            $token = (string) $payload['id'];
        }
        if ($token === '' && !empty($payload['data']['id']) && (is_string($payload['data']['id']) || is_numeric($payload['data']['id']))) {
            $token = (string) $payload['data']['id'];
        }
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'Câmbio Real: token ausente no webhook'];
        }

        $tx = $this->obterTransacaoCambioReal($token);
        $data = is_array($tx['data'] ?? null) ? $tx['data'] : [];
        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        $paymentMethod = strtolower(trim((string) ($data['payment_method'] ?? '')));

        $internal = 'pending';
        $statusNorm = strtoupper(trim((string) $status));
        if (
            in_array($statusNorm, ['SOLICITACAO_PAGO', 'SOLICITACAO_FINALIZADA', 'SOLICITACAO_FINALIZADA '], true) ||
            str_contains($statusNorm, 'PAGO') ||
            str_contains($statusNorm, 'PAG') ||
            str_contains($statusNorm, 'COMPENS') ||
            str_contains($statusNorm, 'CONFIRM') ||
            in_array($statusNorm, ['PAID', 'CONFIRMED', 'APPROVED', 'COMPLETED', 'COMPENSADA', 'COMPENSADO'], true)
        ) {
            $internal = 'approved';
        } elseif (in_array($statusNorm, ['REFUNDED'], true) || str_contains($statusNorm, 'REFUND')) {
            $internal = 'refunded';
        } elseif (
            str_contains($statusNorm, 'CANCEL') ||
            str_contains($statusNorm, 'RECUS') ||
            str_contains($statusNorm, 'INVALID') ||
            str_contains($statusNorm, 'EXPIR')
        ) {
            $internal = 'rejected';
        }

        $metodoNorm = $paymentMethod;
        if ($metodoNorm === 'credit_card') {
            $metodoNorm = 'cartao_credito';
        } elseif ($metodoNorm === 'debit_card') {
            $metodoNorm = 'cartao_debito';
        }

        $this->atualizarSplitPorGatewayPaymentId($token, 'cambioreal', $internal, $statusNorm);
        if ($metodoNorm !== '') {
            try {
                $this->garantirTabelaPedidoPagamentos();
                $db = \Config\Database::getConnection();
                $st = $db->prepare('UPDATE pedido_pagamentos SET metodo = :m, gateway_status = :gs, metadata = :md, updated_at = NOW() WHERE gateway = :g AND payment_id = :pid');
                $st->execute([
                    ':m' => (string) $metodoNorm,
                    ':gs' => (string) $statusNorm,
                    ':md' => json_encode(['raw' => $tx], JSON_UNESCAPED_UNICODE),
                    ':g' => 'cambioreal',
                    ':pid' => (string) $token,
                ]);
            } catch (\Exception $e) {
            }
        }

        return [
            'success' => true,
            'gateway' => 'cambioreal',
            'payment_id' => $token,
            'payment_status' => $internal,
            'gateway_status' => $statusNorm,
            'payment_method' => $metodoNorm,
        ];
    }

    private function mapearStatusCambioRealParaInterno(string $status): array {
        $statusNorm = strtoupper(trim((string) $status));
        $internal = 'pending';
        if (
            in_array($statusNorm, ['SOLICITACAO_PAGO', 'SOLICITACAO_FINALIZADA', 'SOLICITACAO_FINALIZADA '], true) ||
            str_contains($statusNorm, 'PAGO') ||
            str_contains($statusNorm, 'PAG') ||
            str_contains($statusNorm, 'COMPENS') ||
            str_contains($statusNorm, 'CONFIRM') ||
            in_array($statusNorm, ['PAID', 'CONFIRMED', 'APPROVED', 'COMPLETED', 'COMPENSADA', 'COMPENSADO'], true)
        ) {
            $internal = 'approved';
        } elseif (in_array($statusNorm, ['REFUNDED'], true) || str_contains($statusNorm, 'REFUND')) {
            $internal = 'refunded';
        } elseif (
            str_contains($statusNorm, 'CANCEL') ||
            str_contains($statusNorm, 'RECUS') ||
            str_contains($statusNorm, 'INVALID') ||
            str_contains($statusNorm, 'EXPIR')
        ) {
            $internal = 'rejected';
        }
        return ['internal' => $internal, 'status_norm' => $statusNorm];
    }

    public function atualizarStatusPagamentoCambioRealSplitPorPedido(int $pedidoId): array {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }

        $this->garantirTabelaPedidoPagamentos();
        $db = \Config\Database::getConnection();
        $st = $db->prepare("SELECT id, payment_id, gateway_status, status FROM pedido_pagamentos WHERE pedido_id = :p AND gateway = 'cambioreal' ORDER BY id ASC");
        $st->execute([':p' => $pedidoId]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return ['success' => true, 'skipped' => true, 'gateway' => 'cambioreal', 'message' => 'Sem pagamentos Câmbio Real neste pedido'];
        }

        $results = [];
        foreach ($rows as $r) {
            $pid = trim((string) ($r['payment_id'] ?? ''));
            if ($pid === '') {
                continue;
            }
            try {
                $tx = $this->obterTransacaoCambioReal($pid);
                $data = is_array($tx['data'] ?? null) ? (array) $tx['data'] : [];
                $stGateway = (string) ($data['status'] ?? ($data['payment_status'] ?? ($data['transaction_status'] ?? '')));
                $mapped = $this->mapearStatusCambioRealParaInterno($stGateway);
                $this->atualizarSplitPorGatewayPaymentId($pid, 'cambioreal', (string) $mapped['internal'], (string) $mapped['status_norm']);
                $results[] = ['payment_id' => $pid, 'internal' => $mapped['internal'], 'gateway_status' => $mapped['status_norm']];
            } catch (\Exception $e) {
                $results[] = ['payment_id' => $pid, 'error' => $e->getMessage()];
            }
        }

        return ['success' => true, 'gateway' => 'cambioreal', 'results' => $results];
    }

    public function atualizarStatusPagamentoAppmaxSplitPorPedido(int $pedidoId): array {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }

        $this->garantirTabelaPedidoPagamentos();
        $db = \Config\Database::getConnection();
        $st = $db->prepare("SELECT id, payment_id, gateway_status, status FROM pedido_pagamentos WHERE pedido_id = :p AND gateway = 'appmax' ORDER BY id ASC");
        $st->execute([':p' => $pedidoId]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return ['success' => true, 'skipped' => true, 'gateway' => 'appmax', 'message' => 'Sem pagamentos AppMax neste pedido'];
        }

        $results = [];
        foreach ($rows as $r) {
            $orderId = trim((string) ($r['payment_id'] ?? ''));
            if ($orderId === '' || !ctype_digit($orderId)) {
                $results[] = ['payment_id' => $orderId, 'error' => 'payment_id/order_id inválido'];
                continue;
            }
            try {
                $raw = $this->appmaxRequest('GET', 'order/' . $orderId, null);
                $data = is_array($raw) ? ($raw['data'] ?? $raw) : [];
                $status = '';
                if (is_array($data)) {
                    $status = (string) ($data['status'] ?? ($data['order_status'] ?? ($data['order']['status'] ?? '')));
                }
                $statusNorm = strtoupper(trim($status));

                $internal = 'pending';
                if ($statusNorm !== '') {
                    if (str_contains($statusNorm, 'APROV') || str_contains($statusNorm, 'PAID') || str_contains($statusNorm, 'PAGO')) {
                        $internal = 'approved';
                    } elseif (str_contains($statusNorm, 'REFUND') || str_contains($statusNorm, 'ESTORN')) {
                        $internal = 'refunded';
                    } elseif (str_contains($statusNorm, 'CANCEL') || str_contains($statusNorm, 'RECUS') || str_contains($statusNorm, 'REJECT')) {
                        $internal = 'rejected';
                    }
                }

                $this->atualizarSplitPorGatewayPaymentId($orderId, 'appmax', $internal, $statusNorm !== '' ? $statusNorm : $internal);
                $results[] = ['payment_id' => $orderId, 'internal' => $internal, 'gateway_status' => $statusNorm, 'raw' => $raw];
            } catch (\Exception $e) {
                $results[] = ['payment_id' => $orderId, 'error' => $e->getMessage()];
            }
        }

        return ['success' => true, 'gateway' => 'appmax', 'results' => $results];
    }

    private function mercadoPagoTokenForRequestPath(string $path): string {
        $path = (string) $path;

        // Marketplace Split: pagamentos do produto (Payments API) devem ser feitos
        // com o access token do vendedor (OAuth), quando disponível.
        if (!empty($this->mercadoPagoSellerAccessToken) && substr($path, 0, 12) === '/v1/payments') {
            return (string) $this->mercadoPagoSellerAccessToken;
        }

        return (string) $this->mercadoPagoAccessToken;
    }

    private function mercadoPagoRequest(string $method, string $path, ?array $body = null, array $extraHeaders = []): array {
        if (!$this->isMercadoPagoEnabled()) {
            throw new \Exception('Mercado Pago está desativado');
        }
        $token = $this->mercadoPagoTokenForRequestPath($path);
        if (empty($token)) {
            throw new \Exception('Mercado Pago não configurado (access token ausente)');
        }

        $url = 'https://api.mercadopago.com' . $path;
        $headers = [
            'Authorization: Bearer ' . (string) $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        if (!empty($extraHeaders)) {
            foreach ($extraHeaders as $h) {
                if (!is_string($h)) {
                    continue;
                }
                $h = trim($h);
                if ($h === '') {
                    continue;
                }
                $headers[] = $h;
            }
        }
        $payload = $body !== null ? json_encode($body) : null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com Mercado Pago: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro Mercado Pago HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function createMercadoPagoPixPaymentProduto(int $pedidoId, float $valorBrl, string $descricao, array $payer = [], float $applicationFeeBrl = 0.0): array {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if (!$this->isMercadoPagoEnabled()) {
            return ['success' => false, 'error' => 'Mercado Pago está desabilitado.'];
        }
        if (empty($this->mercadoPagoAccessToken)) {
            return ['success' => false, 'error' => 'Mercado Pago não configurado (access token ausente).'];
        }
        $valorBrl = (float) $valorBrl;
        if ($valorBrl <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }

        $applicationFeeBrl = (float) $applicationFeeBrl;
        if ($applicationFeeBrl < 0) {
            $applicationFeeBrl = 0.0;
        }

        $base = Url::base();
        $notificationUrl = rtrim($base, '/') . '/webhook/mercadopago';

        $payerEmail = '';
        if (!empty($payer['email']) && is_string($payer['email'])) {
            $payerEmail = trim((string) $payer['email']);
        }
        if ($payerEmail === '') {
            // Mercado Pago normalmente exige payer.email
            $payerEmail = 'cliente@brazilianashop.com';
        }

        $payload = [
            'transaction_amount' => (float) $valorBrl,
            'description' => $descricao !== '' ? $descricao : ('Pedido #' . $pedidoId . ' (produto)'),
            'payment_method_id' => 'pix',
            'external_reference' => (string) $pedidoId,
            'notification_url' => $notificationUrl,
            'payer' => [
                'email' => $payerEmail,
            ],
        ];

        try {
            $idemKey = substr(hash('sha256', 'pix-produto|' . (string) $pedidoId . '|' . (string) round($valorBrl, 2) . '|' . (string) $descricao), 0, 32);
            $resp = $this->mercadoPagoRequest('POST', '/v1/payments', $payload, ['X-Idempotency-Key: ' . $idemKey]);
            $paymentId = (string) ($resp['id'] ?? '');
            if ($paymentId === '') {
                return ['success' => false, 'error' => 'Mercado Pago: resposta inválida ao criar pagamento PIX.'];
            }

            $qrPayload = (string) ($resp['point_of_interaction']['transaction_data']['qr_code'] ?? '');
            $qrBase64 = (string) ($resp['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '');

            // Persistir registro split como pending
            $this->upsertPedidoPagamento([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'mercadopago',
                'metodo' => 'pix',
                'moeda' => 'BRL',
                'valor' => (float) $valorBrl,
                'payment_id' => $paymentId,
                'status' => 'pending',
                'pix_encoded_image' => $qrBase64,
                'pix_payload' => $qrPayload,
                'metadata' => json_encode(['raw' => $resp, 'application_fee' => $applicationFeeBrl], JSON_UNESCAPED_UNICODE),
            ]);

            if ($applicationFeeBrl > 0) {
                $this->upsertPedidoPagamento([
                    'pedido_id' => $pedidoId,
                    'componente' => 'imposto',
                    'gateway' => 'mercadopago',
                    'metodo' => 'pix',
                    'moeda' => 'BRL',
                    'valor' => (float) $applicationFeeBrl,
                    'payment_id' => $paymentId,
                    'status' => 'pending',
                    'gateway_status' => 'APPLICATION_FEE',
                    'metadata' => json_encode(['raw' => $resp, 'application_fee' => $applicationFeeBrl], JSON_UNESCAPED_UNICODE),
                ]);
            }

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'pix' => [
                    'encodedImage' => $qrBase64,
                    'payload' => $qrPayload,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createStripePaymentIntentCarteiraRecargaCardBrl(int $recargaId, float $valorBrl, string $descricao, array $customer = []): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $amountCents = (int) round(((float) $valorBrl) * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para recarga.'];
        }

        $body = [
            'amount' => (string) $amountCents,
            'currency' => 'brl',
            'description' => $descricao,
            'metadata[carteira_recarga_id]' => (string) $recargaId,
            'payment_method_types[0]' => 'card',
        ];

        if (!empty($customer['email'])) {
            $body['receipt_email'] = (string) $customer['email'];
        }

        if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
            foreach ($customer['metadata'] as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') continue;
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $body['metadata[' . $k . ']'] = (string) $v;
            }
        }

        try {
            $pi = $this->stripeRequest('POST', '/v1/payment_intents', $body);
            $id = (string) ($pi['id'] ?? '');
            $clientSecret = (string) ($pi['client_secret'] ?? '');
            if ($id === '' || $clientSecret === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar PaymentIntent.'];
            }

            return [
                'success' => true,
                'payment_intent_id' => $id,
                'client_secret' => $clientSecret,
                'status' => (string) ($pi['status'] ?? ''),
                'raw' => $pi,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createStripePaymentIntentCarteiraRecargaPixBrl(int $recargaId, float $valorBrl, string $descricao, array $customer = []): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $amountCents = (int) round(((float) $valorBrl) * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para recarga.'];
        }

        // Para Pix, confirmamos no backend para obter o QR/payload imediatamente.
        $body = [
            'amount' => (string) $amountCents,
            'currency' => 'brl',
            'description' => $descricao,
            'metadata[carteira_recarga_id]' => (string) $recargaId,
            'payment_method_types[0]' => 'pix',
            'confirm' => 'true',
            'payment_method_data[type]' => 'pix',
        ];

        if (!empty($customer['email'])) {
            $body['receipt_email'] = (string) $customer['email'];
            $body['payment_method_data[billing_details][email]'] = (string) $customer['email'];
        }

        $billingName = '';
        if (!empty($customer['name'])) {
            $billingName = trim((string) $customer['name']);
        }
        if ($billingName === '') {
            $billingName = $descricao !== '' ? $descricao : ('Recarga Carteira #' . $recargaId);
        }
        $body['payment_method_data[billing_details][name]'] = $billingName;

        $taxId = '';
        if (!empty($customer['tax_id'])) {
            $taxId = preg_replace('/\D+/', '', (string) $customer['tax_id']);
        }
        if ($taxId !== '') {
            $body['payment_method_data[billing_details][tax_id]'] = $taxId;
        }

        if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
            foreach ($customer['metadata'] as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') continue;
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $body['metadata[' . $k . ']'] = (string) $v;
            }
        }

        try {
            $pi = $this->stripeRequest('POST', '/v1/payment_intents', $body);
            $id = (string) ($pi['id'] ?? '');
            $clientSecret = (string) ($pi['client_secret'] ?? '');
            if ($id === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar PaymentIntent (id ausente).'];
            }

            $pix = null;
            $next = $pi['next_action'] ?? null;
            if (is_array($next)) {
                $pixObj = $next['pix_display_qr_code'] ?? null;
                if (is_array($pixObj)) {
                    $pix = [
                        'hosted_instructions_url' => (string) ($pixObj['hosted_instructions_url'] ?? ''),
                        'expires_at' => $pixObj['expires_at'] ?? null,
                        'copy_paste' => (string) ($pixObj['data'] ?? ($pixObj['pix_code'] ?? '')),
                        'image_url_png' => (string) ($pixObj['image_url_png'] ?? ''),
                        'image_url_svg' => (string) ($pixObj['image_url_svg'] ?? ''),
                    ];
                }
            }

            return [
                'success' => true,
                'payment_intent_id' => $id,
                'client_secret' => $clientSecret,
                'status' => (string) ($pi['status'] ?? ''),
                'pix' => $pix,
                'raw' => $pi,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createMercadoPagoCheckoutPreferenceProduto(int $pedidoId, float $valorBrl, string $descricao, array $payer = [], ?string $successUrl = null, ?string $failureUrl = null, ?string $pendingUrl = null): array {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }
        if (!$this->isMercadoPagoEnabled()) {
            return ['success' => false, 'error' => 'Mercado Pago está desabilitado.'];
        }
        if (empty($this->mercadoPagoAccessToken)) {
            return ['success' => false, 'error' => 'Mercado Pago não configurado (access token ausente).'];
        }
        if ($valorBrl <= 0) {
            return ['success' => false, 'error' => 'Valor inválido'];
        }

        $base = Url::base();
        if ($successUrl === null || trim((string) $successUrl) === '') {
            $successUrl = rtrim($base, '/') . '/pedido/detalhes/' . $pedidoId . '?mp=success';
        }
        if ($failureUrl === null || trim((string) $failureUrl) === '') {
            $failureUrl = rtrim($base, '/') . '/pedido/detalhes/' . $pedidoId . '?mp=failure';
        }
        if ($pendingUrl === null || trim((string) $pendingUrl) === '') {
            $pendingUrl = rtrim($base, '/') . '/pedido/detalhes/' . $pedidoId . '?mp=pending';
        }

        $notificationUrl = rtrim($base, '/') . '/webhook/mercadopago';

        $payload = [
            'items' => [
                [
                    'title' => $descricao !== '' ? $descricao : ('Pedido #' . $pedidoId . ' (produto)'),
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => (float) $valorBrl,
                ]
            ],
            'external_reference' => (string) $pedidoId,
            'notification_url' => $notificationUrl,
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $failureUrl,
                'pending' => $pendingUrl,
            ],
            'auto_return' => 'approved',
        ];

        if (!empty($payer)) {
            $payload['payer'] = $payer;
        }

        try {
            $pref = $this->mercadoPagoRequest('POST', '/checkout/preferences', $payload);
            $prefId = (string) ($pref['id'] ?? '');
            $initPoint = (string) ($pref['init_point'] ?? ($pref['sandbox_init_point'] ?? ''));
            if ($prefId === '' || $initPoint === '') {
                return ['success' => false, 'error' => 'Mercado Pago: resposta inválida ao criar preferência.'];
            }

            // Persistir registro split como pending (payment_id real virá no webhook)
            $this->upsertPedidoPagamento([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'mercadopago',
                'metodo' => 'checkout_pro',
                'moeda' => 'BRL',
                'valor' => (float) $valorBrl,
                'status' => 'pending',
                'invoice_url' => $initPoint,
                'metadata' => json_encode(['preference_id' => $prefId], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'preference_id' => $prefId,
                'init_point' => $initPoint,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function processarWebhookMercadoPago(array $payload): array {
        // Mercado Pago envia notificações em formatos diferentes.
        // Preferimos buscar o payment_id e consultar /v1/payments/{id}.
        $paymentId = '';
        if (isset($payload['data']['id']) && (is_string($payload['data']['id']) || is_numeric($payload['data']['id']))) {
            $paymentId = (string) $payload['data']['id'];
        } elseif (isset($payload['id']) && (is_string($payload['id']) || is_numeric($payload['id']))) {
            $paymentId = (string) $payload['id'];
        }
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return ['status' => 'ignored'];
        }

        $pay = $this->mercadoPagoRequest('GET', '/v1/payments/' . rawurlencode($paymentId), null);
        $status = strtolower(trim((string) ($pay['status'] ?? '')));
        $statusDetail = strtoupper(trim((string) ($pay['status_detail'] ?? '')));
        $externalRef = trim((string) ($pay['external_reference'] ?? ''));

        $internal = 'pending';
        if ($status === 'approved') {
            $internal = 'approved';
        } elseif (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
            $internal = 'rejected';
        } elseif (in_array($status, ['refunded', 'charged_back'], true)) {
            $internal = 'refunded';
        }

        $pedidoId = (ctype_digit($externalRef) ? (int) $externalRef : 0);
        if ($pedidoId > 0) {
            // Atualiza todos os componentes vinculados a esse payment_id (produto e, se existir, imposto)
            $this->atualizarSplitPorGatewayPaymentId($paymentId, 'mercadopago', $internal, $statusDetail !== '' ? $statusDetail : strtoupper($status));

            // Se ainda não existe registro, criar pelo menos o componente produto.
            $this->upsertPedidoPagamento([
                'pedido_id' => $pedidoId,
                'componente' => 'produto',
                'gateway' => 'mercadopago',
                'metodo' => 'checkout_pro',
                'moeda' => 'BRL',
                'valor' => (float) ($pay['transaction_amount'] ?? 0),
                'payment_id' => $paymentId,
                'status' => $internal,
                'gateway_status' => ($statusDetail !== '' ? $statusDetail : strtoupper($status)),
                'metadata' => json_encode(['raw' => $pay], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // Também tenta atualizar via (gateway,payment_id) caso já exista registro
        $this->atualizarSplitPorGatewayPaymentId($paymentId, 'mercadopago', $internal, $statusDetail !== '' ? $statusDetail : strtoupper($status));

        return ['status' => 'processed', 'payment_id' => $paymentId, 'pedido_id' => $pedidoId, 'payment_status' => $internal];
    }

    private function upsertPedidoPagamento(array $row): void {
        try {
            $pedidoId = (int) ($row['pedido_id'] ?? 0);
            $componente = strtolower(trim((string) ($row['componente'] ?? '')));
            $gateway = strtolower(trim((string) ($row['gateway'] ?? '')));
            if ($pedidoId <= 0 || $componente === '' || $gateway === '') {
                return;
            }
            $this->garantirTabelaPedidoPagamentos();
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $st = $db->query('DESCRIBE pedido_pagamentos');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }
            if (empty($cols)) {
                return;
            }

            $allowed = [
                'pedido_id','componente','gateway','metodo','moeda','valor','payment_id','status','gateway_status',
                'invoice_url','bank_slip_url','digitable_line','pix_encoded_image','pix_payload','metadata'
            ];

            $data = [];
            foreach ($allowed as $k) {
                if (in_array($k, $cols, true) && array_key_exists($k, $row)) {
                    $data[$k] = $row[$k];
                }
            }
            $data['pedido_id'] = $pedidoId;
            $data['componente'] = $componente;
            $data['gateway'] = $gateway;

            $existingId = 0;
            try {
                if (in_array('payment_id', $cols, true) && !empty($data['payment_id'])) {
                    $st = $db->prepare('SELECT id FROM pedido_pagamentos WHERE gateway = :g AND payment_id = :pid AND componente = :c LIMIT 1');
                    $st->execute([':g' => $gateway, ':pid' => (string) $data['payment_id'], ':c' => $componente]);
                    $existingId = (int) ($st->fetchColumn() ?: 0);
                }
                if ($existingId <= 0) {
                    $st = $db->prepare('SELECT id FROM pedido_pagamentos WHERE pedido_id = :p AND componente = :c ORDER BY id DESC LIMIT 1');
                    $st->execute([':p' => $pedidoId, ':c' => $componente]);
                    $existingId = (int) ($st->fetchColumn() ?: 0);
                }
            } catch (\Exception $e) {
                $existingId = 0;
            }

            if ($existingId > 0) {
                $set = [];
                $params = [':id' => $existingId];
                foreach ($data as $k => $v) {
                    if (in_array($k, ['pedido_id','componente','gateway'], true)) {
                        continue;
                    }
                    $set[] = $k . ' = :' . $k;
                    $params[':' . $k] = $v;
                }
                if (!empty($set)) {
                    $sql = 'UPDATE pedido_pagamentos SET ' . implode(', ', $set) . ' WHERE id = :id';
                    $stUp = $db->prepare($sql);
                    $stUp->execute($params);
                }
            } else {
                $insCols = [];
                $insVals = [];
                $params = [];
                foreach ($data as $k => $v) {
                    $insCols[] = $k;
                    $insVals[] = ':' . $k;
                    $params[':' . $k] = $v;
                }
                $sql = 'INSERT INTO pedido_pagamentos (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insVals) . ')';
                $stIns = $db->prepare($sql);
                $stIns->execute($params);
            }

            $this->recalcularStatusPagamentoPedidoSplit($pedidoId);
        } catch (\Exception $e) {
        }
    }

    private function liberarBloqueiosCarteiraExpirados(\PDO $db, int $usuarioId): void {
        try {
            if ($usuarioId <= 0) return;
            $this->garantirTabelaCarteiraRecargas($db);

            $stmt = $db->prepare("SELECT id, moeda, valor
                FROM carteira_recargas
                WHERE usuario_id = :uid
                  AND origem = 'clube_quick_checkout'
                  AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')
                  AND unlocked_at IS NULL
                  AND locked_until IS NOT NULL
                  AND locked_until <= NOW()");
            $stmt->execute([':uid' => $usuarioId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($rows)) return;

            foreach ($rows as $r) {
                $rid = (int) ($r['id'] ?? 0);
                if ($rid <= 0) continue;
                $moeda = strtoupper(trim((string) ($r['moeda'] ?? 'USD')));
                $valor = (float) ($r['valor'] ?? 0);
                if ($valor <= 0) continue;

                $saldoBloqCol = ($moeda === 'BRL') ? 'saldo_brl_bloqueado' : 'saldo_usd_bloqueado';

                try {
                    $stLock = $db->prepare('SELECT saldo_usd_bloqueado, saldo_brl_bloqueado FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                    $stLock->execute([$usuarioId]);
                    $w = $stLock->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $bloqAtual = (float) ($w[$saldoBloqCol] ?? 0);
                    if ($bloqAtual < 0) $bloqAtual = 0.0;
                    $dec = $valor;
                    if ($dec > $bloqAtual) $dec = $bloqAtual;
                    if ($dec > 0) {
                        $stDec = $db->prepare('UPDATE carteiras SET ' . $saldoBloqCol . ' = ' . $saldoBloqCol . ' - :v, updated_at = NOW() WHERE usuario_id = :uid');
                        $stDec->execute([':v' => $dec, ':uid' => $usuarioId]);
                    }

                    $stMark = $db->prepare('UPDATE carteira_recargas SET unlocked_at = NOW(), updated_at = NOW() WHERE id = :id AND unlocked_at IS NULL');
                    $stMark->execute([':id' => $rid]);
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
        }
    }

    public function registrarPedidoPagamentoSplit(array $row): void {
        $this->upsertPedidoPagamento($row);
    }

    private function obterStatusSplitPorPedido(int $pedidoId): array {
        try {
            $this->garantirTabelaPedidoPagamentos();
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT componente, status FROM pedido_pagamentos WHERE pedido_id = :p');
            $st->execute([':p' => $pedidoId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $map = [];
            foreach ($rows as $r) {
                $c = strtolower(trim((string) ($r['componente'] ?? '')));
                if ($c === '') continue;
                $map[$c] = strtolower(trim((string) ($r['status'] ?? 'pending')));
            }
            return $map;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function recalcularStatusPagamentoPedidoSplit(int $pedidoId): void {
        try {
            $pedidoId = (int) $pedidoId;
            if ($pedidoId <= 0) return;

            $stMap = $this->obterStatusSplitPorPedido($pedidoId);
            if (empty($stMap)) {
                return;
            }

            $produto = $stMap['produto'] ?? null;
            $taxa = $stMap['taxa_servico'] ?? ($stMap['taxa'] ?? null);
            $imposto = $stMap['imposto'] ?? null;

            // Se ainda não temos os dois, não mexer (split incompleto)
            if ($produto === null || $taxa === null) {
                return;
            }

            $produtoOk = ($produto === 'approved');
            $taxaOk = ($taxa === 'approved');
            $impostoOk = ($imposto === null ? true : ($imposto === 'approved'));
            $statusAgregado = ($produtoOk && $taxaOk && $impostoOk) ? 'approved' : 'pending';

            $db = \Config\Database::getConnection();
            $colsP = [];
            try {
                $stColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stColsP ? ($stColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }
            if (empty($colsP)) return;

            $set = [];
            $params = [':id' => $pedidoId];

            if (in_array('payment_gateway', $colsP, true)) {
                $set[] = 'payment_gateway = :payment_gateway';
                $params[':payment_gateway'] = 'split';
            }
            if (in_array('payment_status', $colsP, true)) {
                $set[] = 'payment_status = :payment_status';
                $params[':payment_status'] = $statusAgregado;
            }

            if ($statusAgregado === 'approved') {
                if (in_array('pago_em', $colsP, true)) {
                    $set[] = 'pago_em = :pago_em';
                    $params[':pago_em'] = date('Y-m-d H:i:s');
                }
                if (in_array('status', $colsP, true)) {
                    $set[] = 'status = :status';
                    $params[':status'] = 'pago';
                }
            }

            if (!empty($set)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stUp = $db->prepare($sql);
                $stUp->execute($params);
            }

            if ($statusAgregado === 'approved') {
                // Garante cashback/efeitos apenas quando ambos pagos
                $this->creditarCashbackClubePorPedidoAprovado($db, (int) $pedidoId);
            }
        } catch (\Exception $e) {
        }
    }

    private function atualizarSplitPorGatewayPaymentId(string $paymentId, string $gateway, string $paymentStatusInterno, string $gatewayStatus = ''): bool {
        try {
            $paymentId = trim((string) $paymentId);
            $gateway = strtolower(trim((string) $gateway));
            if ($paymentId === '' || $gateway === '') {
                return false;
            }

            $this->garantirTabelaPedidoPagamentos();
            $db = \Config\Database::getConnection();

            $stFind = $db->prepare('SELECT id, pedido_id FROM pedido_pagamentos WHERE gateway = :g AND payment_id = :pid');
            $stFind->execute([':g' => $gateway, ':pid' => $paymentId]);
            $rows = $stFind->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($rows)) {
                return false;
            }

            $pedidoId = 0;
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                $pedidoId = (int) ($r['pedido_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $set = ['status = :st'];
                $params = [':st' => $paymentStatusInterno, ':id' => $id];
                if ($gatewayStatus !== '') {
                    $set[] = 'gateway_status = :gst';
                    $params[':gst'] = $gatewayStatus;
                }
                $sql = 'UPDATE pedido_pagamentos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stUp = $db->prepare($sql);
                $stUp->execute($params);
            }

            if ($pedidoId > 0) {
                $this->recalcularStatusPagamentoPedidoSplit($pedidoId);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function __construct() {
        $this->pedidoModel = new PedidoEcommerce();
        $this->loadConfigurations();
    }

    private function garantirCarteiraUsuario(\PDO $db, int $usuarioId): void {
        if ($usuarioId <= 0) {
            return;
        }

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `carteiras` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `saldo_usd` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl` decimal(10,2) DEFAULT 0.00,
                    `saldo_usd_bloqueado` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl_bloqueado` decimal(10,2) DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_usuario_id` (`usuario_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
        }

        try {
            $cols = [];
            try {
                $st = $db->query('DESCRIBE carteiras');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }
            $toAdd = [
                'saldo_usd_bloqueado' => "ALTER TABLE carteiras ADD COLUMN saldo_usd_bloqueado decimal(10,2) DEFAULT 0.00",
                'saldo_brl_bloqueado' => "ALTER TABLE carteiras ADD COLUMN saldo_brl_bloqueado decimal(10,2) DEFAULT 0.00",
            ];
            foreach ($toAdd as $c => $sql) {
                if (!is_array($cols) || !in_array($c, $cols, true)) {
                    try { $db->exec($sql); } catch (\Exception $e) {}
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)');
            $stmt->execute([(int) $usuarioId]);
        } catch (\Exception $e) {
        }
    }

    private function carteiraHasUpdatedAt(\PDO $db): bool {
        try {
            $st = $db->query('DESCRIBE carteiras');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            return (is_array($cols) && in_array('updated_at', $cols, true));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function debitarCashbackClubePorPedidoEstornado(\PDO $db, int $pedidoId): void {
        try {
            $pedidoId = (int) $pedidoId;
            if ($pedidoId <= 0) {
                return;
            }

            $colsP = [];
            try {
                $stColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stColsP ? ($stColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }
            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $usuarioCol = null;
            foreach (['usuario_id', 'user_id'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $usuarioCol = $c;
                    break;
                }
            }
            if ($usuarioCol === null) {
                return;
            }

            $select = ['id', $usuarioCol];
            foreach (['moeda', 'currency'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $select[] = $c;
                    break;
                }
            }

            $stP = $db->prepare('SELECT ' . implode(', ', array_unique($select)) . ' FROM pedidos WHERE id = ? LIMIT 1');
            $stP->execute([$pedidoId]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
            $usuarioId = (int) ($pedido[$usuarioCol] ?? 0);
            if ($usuarioId <= 0) {
                return;
            }

            $moeda = 'BRL';
            foreach (['moeda', 'currency'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $moeda = strtoupper(trim((string) ($pedido[$c] ?? 'BRL')));
                    break;
                }
            }
            if (!in_array($moeda, ['BRL', 'USD'], true)) {
                $moeda = 'BRL';
            }

            $this->garantirCarteiraUsuario($db, $usuarioId);
            $this->garantirTabelaTransacoesCarteira($db);

            $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';
            $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';

            $startedTx = false;
            try {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                    $startedTx = true;
                }

                // Idempotência: se já debitou o cashback desse pedido, não repetir
                $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'debito' AND descricao LIKE :desc LIMIT 1");
                $stmtChk->execute([
                    ':uid' => $usuarioId,
                    ':desc' => '%Estorno Cashback Clube - Pedido #' . (int) $pedidoId . '%',
                ]);
                $ja = (int) ($stmtChk->fetchColumn() ?: 0);
                if ($ja > 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                // Buscar quanto foi creditado de cashback no passado (pela transação de crédito)
                $stmtCred = $db->prepare(
                    'SELECT COALESCE(' . $valorCol . ',0) AS v ' .
                    'FROM transacoes_carteira ' .
                    "WHERE usuario_id = :uid AND tipo = 'credito' AND descricao LIKE :desc " .
                    'ORDER BY id ASC LIMIT 1'
                );
                $stmtCred->execute([
                    ':uid' => $usuarioId,
                    ':desc' => '%Cashback Clube - Pedido #' . (int) $pedidoId . '%',
                ]);
                $valorCred = (float) ($stmtCred->fetchColumn() ?: 0);
                if ($valorCred <= 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                // Lock carteira
                $stmtLock = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                $stmtLock->execute([$usuarioId]);
                $rowW = $stmtLock->fetch(\PDO::FETCH_ASSOC) ?: [];
                $saldoAtual = (float) ($rowW[$saldoCol] ?? 0);
                if ($saldoAtual < 0) {
                    $saldoAtual = 0.0;
                }

                $debito = $valorCred;
                if ($debito > $saldoAtual) {
                    $debito = $saldoAtual;
                }
                if ($debito <= 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                $stmtUpd = $db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' - :valor, updated_at = NOW() WHERE usuario_id = :uid');
                $stmtUpd->execute([':valor' => $debito, ':uid' => $usuarioId]);

                try {
                    $desc = 'Estorno Cashback Clube - Pedido #' . $pedidoId;
                    $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'debito\', :valor, :desc, NOW())');
                    $stmtTx->execute([
                        ':uid' => $usuarioId,
                        ':valor' => $debito,
                        ':desc' => $desc,
                    ]);
                } catch (\Exception $e) {
                }

                if ($startedTx) {
                    $db->commit();
                }
            } catch (\Exception $e) {
                if ($startedTx && $db->inTransaction()) {
                    $db->rollBack();
                }
                return;
            }
        } catch (\Exception $e) {
            return;
        }
    }

    private function debitarRendimentoClubePorPedidoEstornado(\PDO $db, int $pedidoId): void {
        try {
            $pedidoId = (int) $pedidoId;
            if ($pedidoId <= 0) {
                return;
            }

            // Resolver usuario do pedido
            $colsP = [];
            try {
                $stColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stColsP ? ($stColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }
            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $usuarioCol = null;
            foreach (['usuario_id', 'user_id'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $usuarioCol = $c;
                    break;
                }
            }
            if ($usuarioCol === null) {
                return;
            }

            $stP = $db->prepare('SELECT id, ' . $usuarioCol . ' AS usuario_id FROM pedidos WHERE id = ? LIMIT 1');
            $stP->execute([$pedidoId]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
            $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                return;
            }

            // Ler configuração para descobrir a chave do período
            $intervaloValorRaw = $this->getConfig('clube', 'rendimento_intervalo_valor', '30');
            $intervaloValor = (int) str_replace(',', '.', trim((string) ($intervaloValorRaw ?? '30')));
            if ($intervaloValor <= 0) {
                $intervaloValor = 30;
            }
            $intervaloUnidade = (string) $this->getConfig('clube', 'rendimento_intervalo_unidade', 'dia');
            $periodKey = $this->computeRendimentoPeriodoKey($intervaloValor, $intervaloUnidade);

            $this->garantirCarteiraUsuario($db, $usuarioId);
            $this->garantirTabelaTransacoesCarteira($db);

            $startedTx = false;
            try {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                    $startedTx = true;
                }

                $descCredito = 'Rendimento Clube - ' . $periodKey;
                $descDebito = 'Estorno Rendimento Clube - ' . $periodKey . ' - Pedido #' . $pedidoId;

                // Idempotência por pedido/período
                $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'debito' AND descricao = :desc LIMIT 1");
                $stmtChk->execute([':uid' => $usuarioId, ':desc' => $descDebito]);
                $ja = (int) ($stmtChk->fetchColumn() ?: 0);
                if ($ja > 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                // Buscar o rendimento creditado no período (se existir)
                $stmtCred = $db->prepare(
                    "SELECT COALESCE(valor_usd,0) AS valor_usd, COALESCE(valor_brl,0) AS valor_brl " .
                    "FROM transacoes_carteira " .
                    "WHERE usuario_id = :uid AND tipo = 'credito' AND descricao = :desc " .
                    "ORDER BY id ASC LIMIT 1"
                );
                $stmtCred->execute([':uid' => $usuarioId, ':desc' => $descCredito]);
                $rowCred = $stmtCred->fetch(\PDO::FETCH_ASSOC) ?: [];
                $valorUsd = (float) ($rowCred['valor_usd'] ?? 0);
                $valorBrl = (float) ($rowCred['valor_brl'] ?? 0);

                $moeda = null;
                $valor = 0.0;
                if ($valorUsd > 0) {
                    $moeda = 'USD';
                    $valor = $valorUsd;
                } elseif ($valorBrl > 0) {
                    $moeda = 'BRL';
                    $valor = $valorBrl;
                }

                if ($moeda === null || $valor <= 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
                $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

                // Lock carteira
                $stmtLock = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                $stmtLock->execute([$usuarioId]);
                $rowW = $stmtLock->fetch(\PDO::FETCH_ASSOC) ?: [];
                $saldoAtual = (float) ($rowW[$saldoCol] ?? 0);
                if ($saldoAtual < 0) {
                    $saldoAtual = 0.0;
                }

                $debito = $valor;
                if ($debito > $saldoAtual) {
                    $debito = $saldoAtual;
                }
                if ($debito <= 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                $stmtUpd = $db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' - :valor, updated_at = NOW() WHERE usuario_id = :uid');
                $stmtUpd->execute([':valor' => $debito, ':uid' => $usuarioId]);

                try {
                    $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'debito\', :valor, :desc, NOW())');
                    $stmtTx->execute([
                        ':uid' => $usuarioId,
                        ':valor' => $debito,
                        ':desc' => $descDebito,
                    ]);
                } catch (\Exception $e) {
                }

                if ($startedTx) {
                    $db->commit();
                }
            } catch (\Exception $e) {
                if ($startedTx && $db->inTransaction()) {
                    $db->rollBack();
                }
                return;
            }
        } catch (\Exception $e) {
            return;
        }
    }

    private function pickPedidoItensTable(\PDO $db, int $pedidoId = 0): string {
        $temPedidoItens = false;
        $temPedidoItems = false;

        try {
            $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['pedido_itens']);
            $temPedidoItens = (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            $temPedidoItens = false;
        }

        try {
            $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['pedido_items']);
            $temPedidoItems = (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            $temPedidoItems = false;
        }

        if ($temPedidoItens && !$temPedidoItems) return 'pedido_itens';
        if ($temPedidoItems && !$temPedidoItens) return 'pedido_items';
        if (!$temPedidoItens && !$temPedidoItems) return 'pedido_itens';

        if ($pedidoId > 0) {
            $c1 = 0;
            $c2 = 0;
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = ?');
                $st->execute([(int) $pedidoId]);
                $c1 = (int) ($st->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $c1 = 0;
            }
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = ?');
                $st->execute([(int) $pedidoId]);
                $c2 = (int) ($st->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $c2 = 0;
            }
            return ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
        }

        return 'pedido_itens';
    }

    private function creditarCashbackClubePorPedidoAprovado(\PDO $db, int $pedidoId): void {
        try {
            $pedidoId = (int) $pedidoId;
            if ($pedidoId <= 0) {
                return;
            }

            $cashbackPctRaw = $this->getConfig('clube', 'cashback_percent', null);
            if ($cashbackPctRaw === null) {
                $cashbackPctRaw = $this->getConfig('clube', 'clube_cashback_percent', null);
            }
            $cashbackPct = (float) str_replace(',', '.', trim((string) ($cashbackPctRaw ?? '0')));
            if ($cashbackPct <= 0) {
                return;
            }

            $colsP = [];
            try {
                $stColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stColsP ? ($stColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }
            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $select = ['id'];
            $usuarioCol = null;
            foreach (['usuario_id', 'user_id'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $usuarioCol = $c;
                    $select[] = $c;
                    break;
                }
            }
            foreach (['moeda', 'currency'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $select[] = $c;
                    break;
                }
            }
            foreach (['desconto', 'discount'] as $c) {
                if (in_array($c, $colsP, true)) {
                    $select[] = $c;
                    break;
                }
            }

            if ($usuarioCol === null) {
                return;
            }

            $stP = $db->prepare('SELECT ' . implode(', ', array_unique($select)) . ' FROM pedidos WHERE id = ? LIMIT 1');
            $stP->execute([$pedidoId]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
            $usuarioId = (int) ($pedido[$usuarioCol] ?? 0);
            if ($usuarioId <= 0) {
                return;
            }

            $moeda = 'BRL';
            foreach (['moeda', 'currency'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $moeda = strtoupper(trim((string) ($pedido[$c] ?? 'BRL')));
                    break;
                }
            }
            if (!in_array($moeda, ['BRL', 'USD'], true)) {
                $moeda = 'BRL';
            }

            $descontoTotal = 0.0;
            foreach (['desconto', 'discount'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $descontoTotal = (float) ($pedido[$c] ?? 0);
                    break;
                }
            }
            if ($descontoTotal < 0) {
                $descontoTotal = 0.0;
            }

            $itensTable = $this->pickPedidoItensTable($db, $pedidoId);
            $colsItens = [];
            try {
                $stColsI = $db->query('DESCRIBE ' . $itensTable);
                $colsItens = $stColsI ? ($stColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsItens = [];
            }

            $colPedidoId = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : null;
            $colProdutoId = in_array('produto_id', $colsItens, true) ? 'produto_id' : null;
            $colSubtotal = null;
            foreach (['subtotal', 'valor_total', 'total', 'amount'] as $c) {
                if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                    $colSubtotal = $c;
                    break;
                }
            }
            if ($colPedidoId === null || $colProdutoId === null || $colSubtotal === null) {
                return;
            }

            $prodCols = [];
            try {
                $stColsProd = $db->query('DESCRIBE produtos');
                $prodCols = $stColsProd ? ($stColsProd->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $prodCols = [];
            }
            if (!is_array($prodCols) || !in_array('clube_ativo', $prodCols, true)) {
                return;
            }

            $stSum = $db->prepare(
                'SELECT SUM(COALESCE(i.' . $colSubtotal . ',0)) AS subtotal_clube ' .
                'FROM ' . $itensTable . ' i ' .
                'INNER JOIN produtos p ON p.id = i.' . $colProdutoId . ' ' .
                'WHERE i.' . $colPedidoId . ' = :pid AND COALESCE(p.clube_ativo,0) = 1'
            );
            $stSum->execute([':pid' => $pedidoId]);
            $subtotalClube = (float) ($stSum->fetchColumn() ?: 0);
            if ($subtotalClube <= 0) {
                return;
            }

            $descontoAplicado = $descontoTotal;
            if ($descontoAplicado > $subtotalClube) {
                $descontoAplicado = $subtotalClube;
            }
            if ($descontoAplicado < 0) {
                $descontoAplicado = 0.0;
            }

            $cashbackBase = $subtotalClube - $descontoAplicado;
            if ($cashbackBase <= 0) {
                return;
            }

            $cashback = $cashbackBase * ($cashbackPct / 100.0);
            if ($cashback <= 0) {
                return;
            }

            $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
            $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

            $this->garantirCarteiraUsuario($db, $usuarioId);
            $this->garantirTabelaTransacoesCarteira($db);

            $startedTx = false;
            try {
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                    $startedTx = true;
                }

                $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'credito' AND descricao LIKE :desc LIMIT 1");
                $stmtChk->execute([
                    ':uid' => $usuarioId,
                    ':desc' => '%Cashback Clube - Pedido #' . (int) $pedidoId . '%',
                ]);
                $ja = (int) ($stmtChk->fetchColumn() ?: 0);
                if ($ja > 0) {
                    if ($startedTx) {
                        $db->commit();
                    }
                    return;
                }

                $stmtLock = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                $stmtLock->execute([$usuarioId]);

                $stmtUpd = $db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' + :valor, updated_at = NOW() WHERE usuario_id = :uid');
                $stmtUpd->execute([':valor' => $cashback, ':uid' => $usuarioId]);

                try {
                    $desc = 'Cashback Clube - Pedido #' . $pedidoId;
                    $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'credito\', :valor, :desc, NOW())');
                    $stmtTx->execute([
                        ':uid' => $usuarioId,
                        ':valor' => $cashback,
                        ':desc' => $desc,
                    ]);
                } catch (\Exception $e) {
                }

                if ($startedTx) {
                    $db->commit();
                }
            } catch (\Exception $e) {
                if ($startedTx && $db->inTransaction()) {
                    $db->rollBack();
                }
                return;
            }
        } catch (\Exception $e) {
            return;
        }
    }

    public function creditarCashbackClubePorPedidoPago(int $pedidoId): void {
        try {
            $db = \Config\Database::getConnection();
            $this->creditarCashbackClubePorPedidoAprovado($db, (int) $pedidoId);
        } catch (\Exception $e) {
        }
    }

    private function getUsdBrlRate(): float {
        $rate = 5.5;
        try {
            $v = $this->getConfig('sistema', 'usd_brl_rate', null);
            if ($v === null) {
                $v = $this->getConfig('sistema', 'sistema_usd_brl_rate', null);
            }
            $r = (float) str_replace(',', '.', trim((string) ($v ?? '')));
            if ($r > 0) {
                $rate = $r;
            }
        } catch (\Exception $e) {
        }

        if ($rate <= 0) {
            $rate = 5.5;
        }
        return (float) $rate;
    }

    private function computeRendimentoPeriodoKey(int $intervaloValor, string $intervaloUnidade): string {
        $intervaloValor = (int) $intervaloValor;
        if ($intervaloValor <= 0) {
            $intervaloValor = 30;
        }
        $u = strtolower(trim((string) $intervaloUnidade));
        if (!in_array($u, ['minuto', 'hora', 'dia', 'mes'], true)) {
            $u = 'dia';
        }

        $now = new \DateTime('now');

        if ($u === 'mes') {
            $year = (int) $now->format('Y');
            $month = (int) $now->format('n');
            $idx = (int) floor(($month - 1) / $intervaloValor);
            $bucketMonth = ($idx * $intervaloValor) + 1;
            if ($bucketMonth < 1) $bucketMonth = 1;
            if ($bucketMonth > 12) $bucketMonth = 12;
            return sprintf('%04d-%02d', $year, $bucketMonth);
        }

        if ($u === 'dia') {
            $year = (int) $now->format('Y');
            $dayOfYear = (int) $now->format('z'); // 0-based
            $idx = (int) floor($dayOfYear / $intervaloValor);
            $bucketDay = ($idx * $intervaloValor);
            $d = new \DateTime($year . '-01-01 00:00:00');
            $d->modify('+' . $bucketDay . ' days');
            return $d->format('Y-m-d');
        }

        if ($u === 'hora') {
            $ts = (int) $now->format('U');
            $bucketSeconds = $intervaloValor * 3600;
            $bucketStart = (int) (floor($ts / $bucketSeconds) * $bucketSeconds);
            $d = new \DateTime('@' . $bucketStart);
            $d->setTimezone($now->getTimezone());
            return $d->format('Y-m-d H:00');
        }

        // minuto
        $ts = (int) $now->format('U');
        $bucketSeconds = $intervaloValor * 60;
        $bucketStart = (int) (floor($ts / $bucketSeconds) * $bucketSeconds);
        $d = new \DateTime('@' . $bucketStart);
        $d->setTimezone($now->getTimezone());
        return $d->format('Y-m-d H:i');
    }

    public function processarRendimentoClube(): array {
        $out = [
            'success' => true,
            'processed' => 0,
            'eligible' => 0,
            'credited' => 0,
            'skipped_idempotent' => 0,
            'skipped_invalid' => 0,
            'errors' => 0,
            'error_samples' => [],
        ];

        try {
            $db = \Config\Database::getConnection();

            $pctRaw = $this->getConfig('clube', 'rendimento_percent', null);
            $pct = (float) str_replace(',', '.', trim((string) ($pctRaw ?? '0')));
            if ($pct <= 0) {
                return $out;
            }

            $intervaloValorRaw = $this->getConfig('clube', 'rendimento_intervalo_valor', '30');
            $intervaloValor = (int) str_replace(',', '.', trim((string) ($intervaloValorRaw ?? '30')));
            if ($intervaloValor <= 0) {
                $intervaloValor = 30;
            }

            $intervaloUnidade = (string) $this->getConfig('clube', 'rendimento_intervalo_unidade', 'dia');
            $periodKey = $this->computeRendimentoPeriodoKey($intervaloValor, $intervaloUnidade);

            $minUsd = 39.0;
            $rate = $this->getUsdBrlRate();
            if ($rate <= 0) {
                $rate = 5.5;
            }

            $this->garantirTabelaTransacoesCarteira($db);

            $hasUpdatedAt = $this->carteiraHasUpdatedAt($db);

            $stmtWallets = $db->query('SELECT usuario_id, saldo_usd, saldo_brl FROM carteiras');
            $wallets = $stmtWallets ? ($stmtWallets->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

            foreach ($wallets as $w) {
                $out['processed']++;
                $usuarioId = (int) ($w['usuario_id'] ?? 0);
                if ($usuarioId <= 0) {
                    $out['skipped_invalid']++;
                    continue;
                }

                $saldoUsd = (float) ($w['saldo_usd'] ?? 0);
                $saldoBrl = (float) ($w['saldo_brl'] ?? 0);
                $equivUsd = (float) $saldoUsd;
                if ($saldoBrl > 0 && $rate > 0) {
                    $equivUsd = (float) ($saldoUsd + ($saldoBrl / $rate));
                }

                if ($equivUsd + 0.00001 < $minUsd) {
                    continue;
                }
                $out['eligible']++;

                $creditoUsd = $equivUsd * ($pct / 100.0);
                if ($creditoUsd <= 0) {
                    continue;
                }

                $creditMoeda = ($saldoUsd >= ($saldoBrl > 0 && $rate > 0 ? ($saldoBrl / $rate) : 0.0)) ? 'USD' : 'BRL';
                $valorCredito = $creditoUsd;
                if ($creditMoeda === 'BRL') {
                    $valorCredito = $creditoUsd * $rate;
                }

                if ($valorCredito <= 0) {
                    continue;
                }

                $saldoCol = ($creditMoeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
                $valorCol = ($creditMoeda === 'BRL') ? 'valor_brl' : 'valor_usd';

                $startedTx = false;
                try {
                    if (!$db->inTransaction()) {
                        $db->beginTransaction();
                        $startedTx = true;
                    }

                    $desc = 'Rendimento Clube - ' . $periodKey;
                    $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'credito' AND descricao = :desc LIMIT 1");
                    $stmtChk->execute([':uid' => $usuarioId, ':desc' => $desc]);
                    $exists = (int) ($stmtChk->fetchColumn() ?: 0);
                    if ($exists > 0) {
                        if ($startedTx) {
                            $db->commit();
                        }
                        $out['skipped_idempotent']++;
                        continue;
                    }

                    $this->garantirCarteiraUsuario($db, $usuarioId);

                    $stmtLock = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                    $stmtLock->execute([$usuarioId]);

                    $sqlUpd = 'UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' + :valor' . ($hasUpdatedAt ? ', updated_at = NOW()' : '') . ' WHERE usuario_id = :uid';
                    $stmtUpd = $db->prepare($sqlUpd);
                    $stmtUpd->execute([':valor' => $valorCredito, ':uid' => $usuarioId]);

                    try {
                        $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'credito\', :valor, :desc, NOW())');
                        $stmtTx->execute([
                            ':uid' => $usuarioId,
                            ':valor' => $valorCredito,
                            ':desc' => $desc,
                        ]);
                    } catch (\Exception $e) {
                    }

                    if ($startedTx) {
                        $db->commit();
                    }

                    $out['credited']++;
                } catch (\Exception $e) {
                    if ($startedTx && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    $out['errors']++;
                    if (is_array($out['error_samples']) && count($out['error_samples']) < 5) {
                        $out['error_samples'][] = $e->getMessage();
                    }
                }
            }

            return $out;
        } catch (\Exception $e) {
            $out['success'] = false;
            $out['errors']++;
            return $out;
        }
    }

    private function garantirTabelaTransacoesCarteira(\PDO $db): void {
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `transacoes_carteira` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `tipo` enum('credito','debito','conversao') NOT NULL,
                    `valor_usd` decimal(10,2) DEFAULT 0.00,
                    `valor_brl` decimal(10,2) DEFAULT 0.00,
                    `taxa_conversao` decimal(10,6) DEFAULT 1.000000,
                    `descricao` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_usuario_id` (`usuario_id`),
                    KEY `idx_tipo` (`tipo`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
        }
    }

    public function estornarPagamentoCarteiraPorPedido(int $pedidoId, ?float $valor = null, string $motivo = ''): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'carteira') {
            return ['success' => false, 'error' => 'Gateway não é Carteira'];
        }

        $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL')));
        if (!in_array($moeda, ['BRL', 'USD'], true)) {
            $moeda = 'BRL';
        }

        $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) ($pedido['cliente_id'] ?? 0);
        }
        if ($usuarioId <= 0) {
            return ['success' => false, 'error' => 'Pedido sem usuário vinculado para estorno'];
        }

        $totalPedido = (float) ($pedido['total'] ?? ($pedido['valor_total'] ?? 0));
        $valorEstorno = $valor !== null ? (float) $valor : $totalPedido;
        if ($valorEstorno <= 0) {
            return ['success' => false, 'error' => 'Valor de estorno inválido'];
        }

        $db = \Config\Database::getConnection();
        $this->garantirCarteiraUsuario($db, $usuarioId);
        $this->garantirTabelaTransacoesCarteira($db);

        $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
        $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

        $db->beginTransaction();
        try {
            // Idempotência: se já houver um crédito de estorno para este pedido, não repetir.
            $alreadyRefunded = false;
            try {
                $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'credito' AND descricao LIKE :desc LIMIT 1");
                $stmtChk->execute([
                    ':uid' => $usuarioId,
                    ':desc' => '%Estorno do Pedido #' . (int) $pedidoId . '%',
                ]);
                $alreadyRefunded = ((int) ($stmtChk->fetchColumn() ?: 0)) > 0;
            } catch (\Exception $e) {
                $alreadyRefunded = false;
            }

            if (!$alreadyRefunded) {
                $stmt = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                $stmt->execute([$usuarioId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

                $stmtUpd = $db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' + :valor, updated_at = NOW() WHERE usuario_id = :uid');
                $stmtUpd->execute([':valor' => $valorEstorno, ':uid' => $usuarioId]);

                try {
                    $desc = 'Estorno do Pedido #' . $pedidoId;
                    if (trim($motivo) !== '') {
                        $desc .= ' - ' . trim($motivo);
                    }
                    $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'credito\', :valor, :desc, NOW())');
                    $stmtTx->execute([
                        ':uid' => $usuarioId,
                        ':valor' => $valorEstorno,
                        ':desc' => $desc,
                    ]);
                } catch (\Exception $e) {
                }
            }

            try {
                $this->debitarCashbackClubePorPedidoEstornado($db, (int) $pedidoId);
            } catch (\Exception $e) {
            }

            try {
                $this->debitarRendimentoClubePorPedidoEstornado($db, (int) $pedidoId);
            } catch (\Exception $e) {
            }

            $this->atualizarPagamentoPedidoPorPedidoId((int) $pedidoId, 'carteira', 'refunded', 'refunded');

            if ($db->inTransaction()) {
                $db->commit();
            }
            return [
                'success' => true,
                'gateway' => 'carteira',
                'pedido_id' => (int) $pedidoId,
                'usuario_id' => (int) $usuarioId,
                'moeda' => $moeda,
                'valor' => $valorEstorno,
                'idempotent' => $alreadyRefunded,
                'status' => 'refunded',
            ];
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function devolverEstoquePorPedido(\PDO $db, int $pedidoId): void {
        if ($pedidoId <= 0) {
            return;
        }

        try {
            // Detectar tabela de itens
            $itensTable = '';
            foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
                try {
                    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                    $st->execute([$t]);
                    if ((int) ($st->fetchColumn() ?: 0) > 0) {
                        $itensTable = $t;
                        break;
                    }
                } catch (\Exception $e) {
                }
            }
            if ($itensTable === '') {
                return;
            }

            $itens = [];
            try {
                $stItens = $db->prepare('SELECT produto_id, quantidade FROM ' . $itensTable . ' WHERE pedido_id = ?');
                $stItens->execute([(int) $pedidoId]);
                $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $itens = [];
            }
            if (empty($itens)) {
                return;
            }

            // Detectar coluna de estoque em produtos
            $cols = [];
            try {
                $stCols = $db->query('DESCRIBE produtos');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }
            $stockCol = '';
            if (is_array($cols) && in_array('stock', $cols, true)) {
                $stockCol = 'stock';
            } elseif (is_array($cols) && in_array('estoque', $cols, true)) {
                $stockCol = 'estoque';
            }
            if ($stockCol === '') {
                return;
            }

            $setUpdatedAt = (is_array($cols) && in_array('updated_at', $cols, true));
            $sqlUpd = 'UPDATE produtos SET ' . $stockCol . ' = ' . $stockCol . ' + :qtd';
            if ($setUpdatedAt) {
                $sqlUpd .= ', updated_at = NOW()';
            }
            $sqlUpd .= ' WHERE id = :pid';
            $stUpd = $db->prepare($sqlUpd);
            foreach ($itens as $it) {
                $pid = (int) ($it['produto_id'] ?? 0);
                $qtd = (int) ($it['quantidade'] ?? 0);
                if ($pid <= 0 || $qtd <= 0) {
                    continue;
                }
                try {
                    $stUpd->execute([':qtd' => $qtd, ':pid' => $pid]);
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
            return;
        }
    }

    public function cancelarPagamentoCarteiraPorPedido(int $pedidoId): array {
        // Para carteira, "cancelar" no gateway não existe: tratamos como estorno total.
        return $this->estornarPagamentoCarteiraPorPedido($pedidoId, null, 'Cancelamento do pedido');
    }

    private function garantirTabelaCarteiraRecargas(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `carteira_recargas` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` int(11) NOT NULL,
                `moeda` varchar(3) NOT NULL DEFAULT 'USD',
                `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                `origem` varchar(40) DEFAULT NULL,
                `public_token` varchar(64) DEFAULT NULL,
                `pagador_nome` varchar(191) DEFAULT NULL,
                `pagador_email` varchar(191) DEFAULT NULL,
                `pagador_documento` varchar(30) DEFAULT NULL,
                `metodo` varchar(20) DEFAULT NULL,
                `usd_brl_rate` decimal(10,6) DEFAULT NULL,
                `valor_brl` decimal(10,2) DEFAULT NULL,
                `gateway` varchar(20) DEFAULT NULL,
                `payment_id` varchar(191) DEFAULT NULL,
                `invoice_url` text,
                `status` varchar(30) NOT NULL DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `paid_at` timestamp NULL DEFAULT NULL,
                `locked_until` timestamp NULL DEFAULT NULL,
                `unlocked_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_usuario_id` (`usuario_id`),
                KEY `idx_public_token` (`public_token`),
                KEY `idx_gateway_payment` (`gateway`, `payment_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }

        try {
            $cols = [];
            try {
                $st = $db->query('DESCRIBE carteira_recargas');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $toAdd = [
                'origem' => "ALTER TABLE carteira_recargas ADD COLUMN origem varchar(40) DEFAULT NULL",
                'public_token' => "ALTER TABLE carteira_recargas ADD COLUMN public_token varchar(64) DEFAULT NULL",
                'pagador_nome' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_nome varchar(191) DEFAULT NULL",
                'pagador_email' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_email varchar(191) DEFAULT NULL",
                'pagador_documento' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_documento varchar(30) DEFAULT NULL",
                'metodo' => "ALTER TABLE carteira_recargas ADD COLUMN metodo varchar(20) DEFAULT NULL",
                'usd_brl_rate' => "ALTER TABLE carteira_recargas ADD COLUMN usd_brl_rate decimal(10,6) DEFAULT NULL",
                'valor_brl' => "ALTER TABLE carteira_recargas ADD COLUMN valor_brl decimal(10,2) DEFAULT NULL",
                'locked_until' => "ALTER TABLE carteira_recargas ADD COLUMN locked_until timestamp NULL DEFAULT NULL",
                'unlocked_at' => "ALTER TABLE carteira_recargas ADD COLUMN unlocked_at timestamp NULL DEFAULT NULL",
            ];

            foreach ($toAdd as $c => $sql) {
                if (!is_array($cols) || !in_array($c, $cols, true)) {
                    try { $db->exec($sql); } catch (\Exception $e) {}
                }
            }

            try {
                $db->exec("CREATE INDEX idx_public_token ON carteira_recargas (public_token)");
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
        }
    }

    public function createStripePaymentIntentCarteiraRecarga(int $recargaId, float $valorUsd, string $descricao, array $customer = []): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $amountCents = (int) round($valorUsd * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para recarga.'];
        }

        $body = [
            'amount' => (string) $amountCents,
            'currency' => 'usd',
            'description' => $descricao,
            'metadata[carteira_recarga_id]' => (string) $recargaId,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        if (!empty($customer['email'])) {
            $body['receipt_email'] = (string) $customer['email'];
        }

        try {
            $pi = $this->stripeRequest('POST', '/v1/payment_intents', $body);
            $id = (string) ($pi['id'] ?? '');
            $clientSecret = (string) ($pi['client_secret'] ?? '');
            if ($id === '' || $clientSecret === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar PaymentIntent.'];
            }

            return [
                'success' => true,
                'payment_intent_id' => $id,
                'client_secret' => $clientSecret,
                'status' => (string) ($pi['status'] ?? ''),
                'raw' => $pi,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function tentarCreditarCarteiraPorRecarga(string $gateway, string $paymentId, string $paymentStatusInterno, string $gatewayStatus = ''): void {
        $gateway = strtolower(trim($gateway));
        $paymentId = trim($paymentId);
        if ($gateway === '' || $paymentId === '') {
            return;
        }

        $aprovado = in_array($paymentStatusInterno, ['approved', 'paid', 'succeeded'], true);
        if (!$aprovado) {
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $this->garantirTabelaCarteiraRecargas($db);
            $this->garantirTabelaTransacoesCarteira($db);

            $stmt = $db->prepare('SELECT * FROM carteira_recargas WHERE gateway = :g AND payment_id = :pid ORDER BY id DESC LIMIT 1');
            $stmt->execute([':g' => $gateway, ':pid' => $paymentId]);
            $recarga = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($recarga) || empty($recarga['id'])) {
                return;
            }

            $recargaId = (int) ($recarga['id'] ?? 0);
            $usuarioId = (int) ($recarga['usuario_id'] ?? 0);
            $moeda = strtoupper(trim((string) ($recarga['moeda'] ?? 'USD')));
            $valor = (float) ($recarga['valor'] ?? 0);
            $statusAtual = strtolower(trim((string) ($recarga['status'] ?? 'pending')));

            if ($recargaId <= 0 || $usuarioId <= 0 || $valor <= 0) {
                return;
            }
            if (in_array($statusAtual, ['paid', 'approved', 'credited'], true)) {
                return;
            }
            if (!in_array($moeda, ['BRL', 'USD'], true)) {
                $moeda = 'USD';
            }

            $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
            $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

            $db->beginTransaction();
            try {
                $this->garantirCarteiraUsuario($db, $usuarioId);
                $this->liberarBloqueiosCarteiraExpirados($db, $usuarioId);

                $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'credito' AND descricao LIKE :desc LIMIT 1");
                $stmtChk->execute([
                    ':uid' => $usuarioId,
                    ':desc' => '%Recarga Carteira #' . $recargaId . '%',
                ]);
                $ja = (int) ($stmtChk->fetchColumn() ?: 0);
                if ($ja > 0) {
                    $stUp = $db->prepare("UPDATE carteira_recargas SET status = 'paid', paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = :id");
                    $stUp->execute([':id' => $recargaId]);
                    $db->commit();
                    return;
                }

                $stmtLock = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
                $stmtLock->execute([$usuarioId]);

                $origem = strtolower(trim((string) ($recarga['origem'] ?? '')));
                $isLockedFlow = ($origem === 'clube_quick_checkout');
                $saldoBloqCol = ($moeda === 'BRL') ? 'saldo_brl_bloqueado' : 'saldo_usd_bloqueado';

                $sqlUpd = 'UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' + :valor';
                if ($isLockedFlow) {
                    $sqlUpd .= ', ' . $saldoBloqCol . ' = ' . $saldoBloqCol . ' + :valor';
                }
                $sqlUpd .= ', updated_at = NOW() WHERE usuario_id = :uid';
                $stmtUpd = $db->prepare($sqlUpd);
                $stmtUpd->execute([':valor' => $valor, ':uid' => $usuarioId]);

                try {
                    $desc = 'Recarga Carteira #' . $recargaId;
                    $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'credito\', :valor, :desc, NOW())');
                    $stmtTx->execute([
                        ':uid' => $usuarioId,
                        ':valor' => $valor,
                        ':desc' => $desc,
                    ]);
                } catch (\Exception $e) {
                }

                $stUp = $db->prepare("UPDATE carteira_recargas SET status = 'paid', paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = :id");
                $stUp->execute([':id' => $recargaId]);

                if ($isLockedFlow) {
                    try {
                        $stLockRec = $db->prepare("UPDATE carteira_recargas
                            SET locked_until = COALESCE(locked_until, DATE_ADD(NOW(), INTERVAL 6 MONTH)), updated_at = NOW()
                            WHERE id = :id");
                        $stLockRec->execute([':id' => $recargaId]);
                    } catch (\Exception $e) {
                    }
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                return;
            }
        } catch (\Exception $e) {
            return;
        }
    }

    public function createStripeCheckoutSession(int $pedidoId, float $valorUsd, string $descricao, array $customer = [], ?string $successUrl = null, ?string $cancelUrl = null): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $amountCents = (int) round($valorUsd * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para cobrança.'];
        }

        $base = Url::base();
        if ($successUrl === null || trim((string) $successUrl) === '') {
            $successUrl = rtrim($base, '/') . '/pedido/detalhes/' . $pedidoId . '?stripe=success';
        }
        if ($cancelUrl === null || trim((string) $cancelUrl) === '') {
            $cancelUrl = rtrim($base, '/') . '/pedido/detalhes/' . $pedidoId . '?stripe=cancel';
        }

        $body = [
            'mode' => 'payment',
            'success_url' => (string) $successUrl,
            'cancel_url' => (string) $cancelUrl,
            'client_reference_id' => (string) $pedidoId,
            'metadata[pedido_id]' => (string) $pedidoId,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][product_data][name]' => $descricao !== '' ? $descricao : ('Pedido #' . $pedidoId),
        ];

        if (!empty($customer['email'])) {
            $body['customer_email'] = (string) $customer['email'];
        }

        try {
            $session = $this->stripeRequest('POST', '/v1/checkout/sessions', $body);
            $id = (string) ($session['id'] ?? '');
            $url = (string) ($session['url'] ?? '');
            $paymentIntentId = (string) ($session['payment_intent'] ?? '');
            if ($id === '' || $url === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar Checkout Session.'];
            }

            return [
                'success' => true,
                'session_id' => $id,
                'url' => $url,
                'payment_intent_id' => $paymentIntentId,
                'raw' => $session,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function isPedidoEntregaBrasil(\PDO $db, int $pedidoId): bool {
        try {
            if ($pedidoId <= 0) {
                return true;
            }

            $colsPed = [];
            try {
                $stCols = $db->query('DESCRIBE pedidos');
                $colsPed = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPed = [];
            }

            $pais = '';

            if (is_array($colsPed) && in_array('endereco_entrega_id', $colsPed, true)) {
                $hasEnderecos = false;
                try {
                    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                    $st->execute(['enderecos']);
                    $hasEnderecos = ((int) $st->fetchColumn()) > 0;
                } catch (\Exception $e) {
                    $hasEnderecos = false;
                }

                if ($hasEnderecos) {
                    $colsEnd = [];
                    try {
                        $stColsE = $db->query('DESCRIBE enderecos');
                        $colsEnd = $stColsE ? ($stColsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsEnd = [];
                    }
                    if (is_array($colsEnd) && in_array('pais', $colsEnd, true)) {
                        $stP = $db->prepare("SELECT UPPER(TRIM(COALESCE(e.pais,''))) AS pais FROM pedidos p LEFT JOIN enderecos e ON e.id = p.endereco_entrega_id WHERE p.id = ? LIMIT 1");
                        $stP->execute([(int) $pedidoId]);
                        $pais = (string) ($stP->fetchColumn() ?: '');
                    }
                }
            }

            if ($pais === '' && is_array($colsPed)) {
                foreach (['pais', 'country', 'shipping_country', 'pais_entrega', 'country_entrega', 'pais_destino'] as $c) {
                    if (in_array($c, $colsPed, true)) {
                        try {
                            $stP = $db->prepare('SELECT UPPER(TRIM(COALESCE(' . $c . ",''))) AS pais FROM pedidos WHERE id = ? LIMIT 1");
                            $stP->execute([(int) $pedidoId]);
                            $pais = (string) ($stP->fetchColumn() ?: '');
                        } catch (\Exception $e) {
                            $pais = '';
                        }
                        break;
                    }
                }
            }

            if ($pais === '') {
                return true;
            }

            $pais = strtoupper(trim($pais));
            return in_array($pais, ['BR', 'BRA', 'BRASIL', 'BRAZIL'], true);
        } catch (\Exception $e) {
            return true;
        }
    }

    private function isDebugEnabled(): bool {
        $v = '';
        if (isset($_ENV['APP_DEBUG'])) {
            $v = (string) $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $v = (string) $_SERVER['APP_DEBUG'];
        }
        $v = strtolower(trim($v));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    private function appmaxLog(string $tag, $data): void {
        if (!$this->isDebugEnabled()) {
            return;
        }
        try {
            $msg = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            if (is_string($msg) && strlen($msg) > 4000) {
                $msg = substr($msg, 0, 4000) . '...';
            }
            error_log('[APPMAX]' . $tag . ' ' . (string) $msg);
        } catch (\Exception $e) {
        }
    }

    private function isAppmaxEnabled(): bool {
        $v = strtolower(trim((string) $this->appmaxEnabled));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    private function appmaxRequest(string $method, string $path, ?array $body = null): array {
        if (!$this->isAppmaxEnabled()) {
            throw new \Exception('AppMax está desativado');
        }

        if (empty($this->appmaxV3AccessToken)) {
            throw new \Exception('AppMax não configurado (access-token ausente)');
        }

        $baseUrl = '';
        if (!empty($this->appmaxBaseUrl)) {
            $baseUrl = (string) $this->appmaxBaseUrl;
        } else {
            $amb = strtolower(trim((string) $this->appmaxAmbiente));
            if ($amb === 'homolog' || $amb === 'homologacao' || $amb === 'sandbox' || $amb === 'test') {
                $baseUrl = 'https://homolog.sandboxappmax.com.br/api/v3';
            } else {
                $baseUrl = 'https://admin.appmax.com.br/api/v3';
            }
        }
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payloadArr = is_array($body) ? $body : [];
        // API v3 usa access-token no corpo da requisição.
        if (!array_key_exists('access-token', $payloadArr)) {
            $payloadArr['access-token'] = (string) $this->appmaxV3AccessToken;
        }

        $logBody = $payloadArr;
        if (is_array($logBody) && array_key_exists('access-token', $logBody)) {
            $logBody['access-token'] = '***';
        }
        $this->appmaxLog('[' . strtoupper($method) . ' ' . $path . '][REQ]', $logBody);
        $payload = json_encode($payloadArr);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com AppMax: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            $this->appmaxLog('[' . strtoupper($method) . ' ' . $path . '][RESP][' . $httpCode . ']', $decoded);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro AppMax HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        $this->appmaxLog('[' . strtoupper($method) . ' ' . $path . '][RESP]', $decoded);
        return is_array($decoded) ? $decoded : [];
    }

    private function appmaxCreateCustomer(array $dados, array $products = []): int {
        $nome = trim((string) ($dados['customer_name'] ?? ''));
        $email = trim((string) ($dados['customer_email'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($dados['customer_phone'] ?? ''));
        $doc = preg_replace('/\D+/', '', (string) ($dados['customer_document'] ?? ''));

        $firstName = $nome;
        $lastName = '';
        if (strpos($nome, ' ') !== false) {
            $parts = preg_split('/\s+/', $nome);
            $firstName = (string) array_shift($parts);
            $lastName = trim(implode(' ', $parts));
        }
        if ($lastName === '') {
            $lastName = $firstName;
        }

        $ip = (string) ($dados['customer_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

        $payload = [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'telephone' => $phone,
            'postcode' => (string) ($dados['customer_zipcode'] ?? ''),
            'address_street' => (string) ($dados['customer_address'] ?? ''),
            'address_street_number' => (string) ($dados['customer_address_number'] ?? ''),
            'address_street_complement' => (string) ($dados['customer_address_complement'] ?? ''),
            'address_street_district' => (string) ($dados['customer_province'] ?? ''),
            'address_city' => (string) ($dados['customer_city'] ?? ''),
            'address_state' => (string) ($dados['customer_state'] ?? ''),
            'ip' => $ip,
        ];

        if (!empty($products)) {
            // A doc aceita products com product_sku/product_qty (produto de interesse)
            $payload['products'] = array_map(function ($p) {
                if (!is_array($p)) return $p;
                $sku = (string) ($p['sku'] ?? ($p['product_sku'] ?? ''));
                $qty = (int) ($p['quantity'] ?? ($p['qty'] ?? ($p['product_qty'] ?? 1)));
                return [
                    'product_sku' => $sku,
                    'product_qty' => $qty,
                ];
            }, $products);
        }

        $created = $this->appmaxRequest('POST', 'customer', $payload);
        $customerId = 0;
        if (isset($created['data']['customer_id'])) {
            $customerId = (int) $created['data']['customer_id'];
        } elseif (isset($created['customer_id'])) {
            $customerId = (int) $created['customer_id'];
        } elseif (isset($created['data']['id'])) {
            $customerId = (int) $created['data']['id'];
        } elseif (isset($created['data']['customer']['id'])) {
            $customerId = (int) $created['data']['customer']['id'];
        }
        if ($customerId <= 0) {
            $msg = '';
            foreach (['message', 'mensagem', 'error', 'erro'] as $k) {
                if (!empty($created[$k]) && is_string($created[$k])) {
                    $msg = $created[$k];
                    break;
                }
            }
            if ($msg === '' && !empty($created['data']['message']) && is_string($created['data']['message'])) {
                $msg = (string) $created['data']['message'];
            }
            $details = '';
            try {
                $details = json_encode($created, JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                $details = '';
            }
            throw new \Exception('AppMax: customer_id não retornado' . ($msg !== '' ? (' - ' . $msg) : '') . ($details !== '' ? (' | response=' . $details) : ''));
        }
        return $customerId;
    }

    private function appmaxCreateOrder(int $customerId, int $productsValueCents, int $discountValueCents, int $shippingValueCents, array $products): int {
        $total = round(((float) ($productsValueCents + $shippingValueCents - $discountValueCents)) / 100, 2);
        $shipping = round(((float) $shippingValueCents) / 100, 2);
        $discount = round(((float) $discountValueCents) / 100, 2);

        // A AppMax pode rejeitar shipping=0 e, em alguns cenários, valores muito pequenos podem ser truncados.
        // Garantir mínimo de 1.00.
        if ($shipping < 0) {
            $shipping = 0.0;
        }

        $payloadProducts = [];
        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $sku = (string) ($p['sku'] ?? '');
            $name = (string) ($p['name'] ?? ($p['titulo'] ?? ''));
            $qty = (int) ($p['quantity'] ?? ($p['qty'] ?? 1));
            $unitCents = (int) ($p['unit_value'] ?? ($p['unit_value_cents'] ?? 0));
            $price = $unitCents > 0 ? round(((float) $unitCents) / 100, 2) : null;

            $row = [
                'sku' => $sku,
                'name' => $name !== '' ? $name : $sku,
                'qty' => $qty > 0 ? $qty : 1,
            ];
            if ($price !== null) {
                $row['price'] = $price;
            }
            $payloadProducts[] = $row;
        }

        $freightType = (string) ($products[0]['freight_type'] ?? ($products[0]['frete_tipo'] ?? ''));
        if (trim($freightType) === '') {
            $freightType = 'normal';
        }

        $payload = [
            'total' => $total,
            'products' => $payloadProducts,
            'shipping' => $shipping,
            'customer_id' => $customerId,
            'discount' => $discount,
            'freight_type' => $freightType,
        ];

        $created = $this->appmaxRequest('POST', 'order', $payload);
        $orderId = 0;
        if (isset($created['data']['order_id'])) {
            $orderId = (int) $created['data']['order_id'];
        } elseif (isset($created['order_id'])) {
            $orderId = (int) $created['order_id'];
        } elseif (isset($created['data']['id'])) {
            $orderId = (int) $created['data']['id'];
        } elseif (isset($created['data']['order']['id'])) {
            $orderId = (int) $created['data']['order']['id'];
        }
        if ($orderId <= 0) {
            $msg = '';
            foreach (['message', 'mensagem', 'error', 'erro'] as $k) {
                if (!empty($created[$k]) && is_string($created[$k])) {
                    $msg = $created[$k];
                    break;
                }
            }
            if ($msg === '' && !empty($created['data']['message']) && is_string($created['data']['message'])) {
                $msg = (string) $created['data']['message'];
            }
            $details = '';
            try {
                $details = json_encode($created, JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                $details = '';
            }
            throw new \Exception('AppMax: order_id não retornado' . ($msg !== '' ? (' - ' . $msg) : '') . ($details !== '' ? (' | response=' . $details) : ''));
        }
        return $orderId;
    }

    private function processarPagamentoAppmax($dados, $valor, $descricao) {
        if (!$this->isAppmaxEnabled()) {
            throw new \Exception('AppMax está desativado');
        }

        $forma = strtoupper(trim((string) ($dados['billingType'] ?? '')));
        if ($forma === '') {
            $forma = strtoupper(trim((string) ($dados['forma_pagamento'] ?? '')));
        }

        // Normalizar formas do checkout
        if ($forma === 'PIX' || $forma === 'PXD') {
            $forma = 'PIX';
        } elseif ($forma === 'BOLETO') {
            $forma = 'BOLETO';
        } elseif ($forma === 'CARTAO_CREDITO' || $forma === 'CARTAO' || $forma === 'CREDIT_CARD') {
            $forma = 'CREDIT_CARD';
        }

        $products = (array) ($dados['products'] ?? []);
        $productsValueCents = (int) ($dados['products_value_cents'] ?? 0);
        if ($productsValueCents <= 0) {
            $productsValueCents = (int) round(((float) $valor) * 100);
        }

        $shippingValueCents = (int) ($dados['shipping_value_cents'] ?? 0);
        $discountValueCents = (int) ($dados['discount_value_cents'] ?? 0);

        $customerId = $this->appmaxCreateCustomer($dados, $products);

        // Tenta criar order sem alterar shipping (inclusive 0). Se AppMax rejeitar por shipping=0,
        // faz retry com ajuste mínimo (movendo R$ 1,00 dos produtos para o frete, sem alterar o total).
        $orderId = 0;
        $origProductsValueCents = $productsValueCents;
        $origShippingValueCents = $shippingValueCents;
        try {
            $orderId = $this->appmaxCreateOrder($customerId, $productsValueCents, $discountValueCents, $shippingValueCents, $products);
        } catch (\Exception $e) {
            $msg = strtolower((string) $e->getMessage());
            $shouldRetry = ($origShippingValueCents <= 0) && (str_contains($msg, '422') || str_contains($msg, 'http 422') || str_contains($msg, 'unprocessable'));
            $shouldRetry = $shouldRetry && (str_contains($msg, 'shipping') || str_contains($msg, 'frete'));

            if (!$shouldRetry) {
                throw $e;
            }

            $productsValueCents = $origProductsValueCents;
            $shippingValueCents = $origShippingValueCents;

            $need = 100 - max(0, $shippingValueCents);
            if ($need > 0) {
                if ($productsValueCents > $need) {
                    $productsValueCents -= $need;
                    $shippingValueCents += $need;
                } else {
                    $shippingValueCents = 100;
                }
            }

            $orderId = $this->appmaxCreateOrder($customerId, $productsValueCents, $discountValueCents, $shippingValueCents, $products);
        }

        $result = [
            'success' => true,
            // usamos order_id como payment_id para vínculo simples no banco (webhooks geralmente referenciam order)
            'payment_id' => (string) $orderId,
            'status' => 'pending',
            'billingType' => $forma,
            'appmax' => [
                'customer_id' => $customerId,
                'order_id' => $orderId,
            ],
        ];

        $doc = preg_replace('/\D+/', '', (string) ($dados['customer_document'] ?? ''));

        if ($forma === 'PIX') {
            $pixResp = $this->appmaxRequest('POST', 'payment/pix', [
                'cart' => [
                    'order_id' => $orderId,
                ],
                'customer' => [
                    'customer_id' => $customerId,
                ],
                'payment' => [
                    'pix' => [
                        'document_number' => $doc,
                    ],
                ],
            ]);
            $result['appmax']['pix_response'] = $pixResp;

            // Normalização para as views (padrão legado Asaas)
            $pixData = $pixResp['data'] ?? $pixResp;
            if (is_array($pixData)) {
                $encodedImage = '';
                $payload = '';
                $expirationDate = '';

                $findFirstString = function ($data, array $keys) use (&$findFirstString): string {
                    if (!is_array($data)) {
                        return '';
                    }
                    foreach ($keys as $k) {
                        if (array_key_exists($k, $data) && is_string($data[$k]) && trim($data[$k]) !== '') {
                            return trim($data[$k]);
                        }
                    }
                    foreach ($data as $v) {
                        if (is_array($v)) {
                            $found = $findFirstString($v, $keys);
                            if ($found !== '') {
                                return $found;
                            }
                        }
                    }
                    return '';
                };

                // Tentar chaves diretas e também recursivamente no payload completo
                $encodedImage = $findFirstString($pixData, [
                    'encodedImage',
                    'encoded_image',
                    'qr_code_base64',
                    'qrCodeBase64',
                    'qrcode_base64',
                    'qr_code',
                    'qrcode',
                    'base64',
                    'image_base64',
                    'pix_qrcode_base64',
                    'pix_qrcode',
                    'pixQrCode',
                    'pix_qr_code',
                    'pix_qr',
                ]);
                $payload = $findFirstString($pixData, [
                    'payload',
                    'emv',
                    'copy_paste',
                    'copyPaste',
                    'brcode',
                    'br_code',
                    'pixCopiaECola',
                    'pix_copia_cola',
                    'pix_emv',
                    'pix_brcode',
                    'copia_e_cola',
                ]);
                $expirationDate = $findFirstString($pixData, [
                    'expirationDate',
                    'expiration_date',
                    'expires_at',
                    'expiresAt',
                ]);

                // Se vier como data:image/png;base64,..., remover prefixo
                if ($encodedImage !== '') {
                    $encodedImage = preg_replace('#^data:image/[^;]+;base64,#', '', $encodedImage);
                    $encodedImage = trim((string) $encodedImage);
                }
                if ($payload !== '') {
                    $payload = trim((string) $payload);
                }

                if ($encodedImage === '' && $payload === '') {
                    try {
                        $keysTop = is_array($pixResp) ? implode(',', array_slice(array_keys($pixResp), 0, 30)) : '';
                        $json = '';
                        try {
                            $json = json_encode($pixResp, JSON_UNESCAPED_UNICODE);
                        } catch (\Exception $e) {
                            $json = '';
                        }
                        if (is_string($json) && strlen($json) > 2500) {
                            $json = substr($json, 0, 2500) . '...';
                        }
                        error_log('[APPMAX][PIX] Não foi possível extrair QR/payload. top_keys=' . $keysTop . ' response_head=' . $json);
                    } catch (\Exception $e) {
                    }
                }

                $result['pix'] = [
                    'encodedImage' => $encodedImage !== '' ? $encodedImage : null,
                    'payload' => $payload !== '' ? $payload : null,
                    'expirationDate' => $expirationDate !== '' ? $expirationDate : null,
                ];
            }
            return $result;
        }

        if ($forma === 'BOLETO') {
            $bolResp = $this->appmaxRequest('POST', 'payment/boleto', [
                'cart' => [
                    'order_id' => $orderId,
                ],
                'customer' => [
                    'customer_id' => $customerId,
                ],
                'payment' => [
                    'Boleto' => [
                        'document_number' => $doc,
                    ],
                ],
            ]);
            $result['appmax']['boleto_response'] = $bolResp;

            // Normalização para as views (padrão legado Asaas)
            $bolData = $bolResp['data'] ?? $bolResp;
            if (is_array($bolData)) {
                $bankSlipUrl = (string) ($bolData['bankSlipUrl'] ?? ($bolData['pdf'] ?? ($bolData['url'] ?? '')));
                $invoiceUrl = (string) ($bolData['invoiceUrl'] ?? '');
                $digitableLine = (string) ($bolData['digitableLine'] ?? ($bolData['linha_digitavel'] ?? ($bolData['line'] ?? '')));
                if ($invoiceUrl !== '') {
                    $result['invoiceUrl'] = $invoiceUrl;
                }
                if ($bankSlipUrl !== '') {
                    $result['bankSlipUrl'] = $bankSlipUrl;
                }
                if ($digitableLine !== '') {
                    $result['digitableLine'] = $digitableLine;
                }
            }
            return $result;
        }

        if ($forma === 'CREDIT_CARD') {
            $cc = [
                'number' => preg_replace('/\D+/', '', (string) ($dados['card_number'] ?? '')),
                'cvv' => (string) ($dados['card_cvv'] ?? ''),
                'month' => (int) ($dados['card_expiry_month'] ?? 0),
                'year' => (int) ($dados['card_expiry_year'] ?? 0),
                'document_number' => $doc,
                'name' => (string) ($dados['card_holder_name'] ?? ($dados['customer_name'] ?? '')),
                'installments' => (int) ($dados['installments'] ?? 1),
                'soft_descriptor' => (string) ($dados['soft_descriptor'] ?? ($this->appmaxAppId !== '' ? $this->appmaxAppId : 'BRZ')),
            ];
            if (!empty($dados['token'])) {
                $cc['token'] = (string) $dados['token'];
            }
            if (!empty($dados['upsell_hash'])) {
                $cc['upsell_hash'] = (string) $dados['upsell_hash'];
            }

            $ccResp = $this->appmaxRequest('POST', 'payment/credit-card', [
                'cart' => [
                    'order_id' => $orderId,
                ],
                'customer' => [
                    'customer_id' => $customerId,
                ],
                'payment' => [
                    'CreditCard' => $cc,
                ],
            ]);
            $result['appmax']['credit_card_response'] = $ccResp;
            return $result;
        }

        throw new \Exception('AppMax: forma de pagamento inválida');
    }
    
    private function loadConfigurations() {
        $this->asaasApiKey = (string) $this->getConfig('pagamentos', 'asaas_api_key', '');
        $this->asaasAmbiente = (string) $this->getConfig('pagamentos', 'asaas_ambiente', 'sandbox');
        $this->stripeApiKey = (string) $this->getConfig('pagamentos', 'stripe_secret_key', (string) $this->getConfig('pagamentos', 'stripe_api_key', ''));
        $this->stripePublishableKey = (string) $this->getConfig('pagamentos', 'stripe_publishable_key', (string) $this->getConfig('pagamentos', 'stripe_public_key', ''));
        $this->stripeAmbiente = (string) $this->getConfig('pagamentos', 'stripe_ambiente', 'test');
        $this->stripeEnabled = (string) $this->getConfig('pagamentos', 'stripe_enabled', '0');
        $this->stripeWebhookSecret = (string) $this->getConfig('pagamentos', 'stripe_webhook_secret', (string) $this->getConfig('pagamentos', 'stripe_webhook_signing_secret', ''));

        $this->appmaxEnabled = (string) $this->getConfig('pagamentos', 'appmax_enabled', '0');
        $this->appmaxClientId = (string) $this->getConfig('pagamentos', 'appmax_client_id', '');
        $this->appmaxClientSecret = (string) $this->getConfig('pagamentos', 'appmax_client_secret', '');
        $this->appmaxAppId = (string) $this->getConfig('pagamentos', 'appmax_app_id', '');
        // API v3: access-token (fornecido pela AppMax). Mantém fallback para instalações antigas.
        $this->appmaxV3AccessToken = (string) $this->getConfig('pagamentos', 'appmax_access_token', (string) $this->appmaxAppId);
        $this->appmaxAmbiente = (string) $this->getConfig('pagamentos', 'appmax_ambiente', 'production');
        // Se vazio, a URL será determinada automaticamente pelo appmax_ambiente.
        $this->appmaxBaseUrl = (string) $this->getConfig('pagamentos', 'appmax_base_url', '');
        $this->appmaxAccessToken = null;
        $this->appmaxAccessTokenExpiresAt = null;

        $this->mercadoPagoEnabled = (string) $this->getConfig('pagamentos', 'mercadopago_enabled', '0');
        $this->mercadoPagoAccessToken = (string) $this->getConfig('pagamentos', 'mercadopago_access_token', '');
        $this->mercadoPagoSellerAccessToken = (string) $this->getConfig('pagamentos', 'mercadopago_seller_access_token', '');

        $this->cambioRealEnabled = (string) $this->getConfig('pagamentos', 'cambioreal_enabled', '0');
        $this->cambioRealAppId = (string) $this->getConfig('pagamentos', 'cambioreal_app_id', '');
        $this->cambioRealAppSecret = (string) $this->getConfig('pagamentos', 'cambioreal_app_secret', '');
        $this->cambioRealAppPublic = (string) $this->getConfig('pagamentos', 'cambioreal_app_public', '');
        $this->cambioRealBaseUrl = (string) $this->getConfig('pagamentos', 'cambioreal_base_url', '');
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = \Config\Database::getConnection();

        // Tenta schema single-row em configuracoes_sistema (colunas diretas)
        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                // Se a tabela tem colunas 'chave'/'valor', então ela está no formato key/value.
                // Nesse caso, NÃO devemos tentar ler como single-row com colunas diretas.
                if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                    throw new \Exception('configuracoes_sistema está em formato chave/valor');
                }

                $colName = null;
                if ($categoria === 'pagamentos') {
                    $direct = [
                        'asaas_api_key',
                        'asaas_ambiente',
                        'asaas_enabled',
                        'appmax_enabled',
                        'appmax_client_id',
                        'appmax_client_secret',
                        'appmax_app_id',
                        'cambioreal_enabled',
                        'cambioreal_app_id',
                        'cambioreal_app_secret',
                        'cambioreal_app_public',
                        'cambioreal_base_url',
                        'stripe_secret_key',
                        'stripe_publishable_key',
                        'stripe_ambiente',
                        'stripe_enabled',
                        'stripe_api_key',
                    ];
                    if (in_array($chave, $direct, true) && in_array($chave, $cols, true)) {
                        $colName = $chave;
                    }
                }

                if (!empty($colName)) {
                    $stmt = $db->query('SELECT ' . $colName . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // Tenta schema categoria+chave (configuracoes_sistema)
        try {
            $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1");
            $stmt->execute([$categoria, $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        foreach ($tablesToTry as $table) {
            try {
                $stmtT = $db->prepare('SHOW TABLES LIKE ?');
                $stmtT->execute([$table]);
                if (!$stmtT->fetchColumn()) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE ' . $table);
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (!is_array($cols)) {
                    $cols = [];
                }
            } catch (\Exception $e) {
                $cols = [];
            }

            $valueCol = null;
            foreach (['valor', 'value', 'conteudo', 'content', 'config_value'] as $vc) {
                if (in_array($vc, $cols, true)) {
                    $valueCol = $vc;
                    break;
                }
            }
            if (!$valueCol) {
                continue;
            }

            // Schema categoria+chave
            if (in_array('categoria', $cols, true)) {
                $keyCol = null;
                foreach (['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'] as $kc) {
                    if (in_array($kc, $cols, true)) {
                        $keyCol = $kc;
                        break;
                    }
                }
                if ($keyCol) {
                    try {
                        $orderCol = null;
                        if (in_array('updated_at', $cols, true)) {
                            $orderCol = 'updated_at';
                        } elseif (in_array('id', $cols, true)) {
                            $orderCol = 'id';
                        }
                        $sql = 'SELECT ' . $valueCol . ' AS valor FROM ' . $table . ' WHERE categoria = ? AND ' . $keyCol . ' = ?';
                        if ($orderCol) {
                            $sql .= ' ORDER BY ' . $orderCol . ' DESC';
                        }
                        $sql .= ' LIMIT 1';
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$categoria, $chave]);
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row && array_key_exists('valor', $row)) {
                            return $row['valor'];
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            // Schema chave/valor (sem categoria)
            $keyCol = null;
            foreach (['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'] as $kc) {
                if (in_array($kc, $cols, true)) {
                    $keyCol = $kc;
                    break;
                }
            }
            if ($keyCol) {
                try {
                    $key = $categoria . '_' . $chave;
                    $orderCol = null;
                    if (in_array('updated_at', $cols, true)) {
                        $orderCol = 'updated_at';
                    } elseif (in_array('id', $cols, true)) {
                        $orderCol = 'id';
                    }
                    $sql = 'SELECT ' . $valueCol . ' AS valor FROM ' . $table . ' WHERE ' . $keyCol . ' = ?';
                    if ($orderCol) {
                        $sql .= ' ORDER BY ' . $orderCol . ' DESC';
                    }
                    $sql .= ' LIMIT 1';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$key]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                } catch (\Exception $e) {
                }

                // Fallback: algumas instalações usam chave sem prefixo de categoria (ex: stripe_enabled)
                try {
                    $orderCol = null;
                    if (in_array('updated_at', $cols, true)) {
                        $orderCol = 'updated_at';
                    } elseif (in_array('id', $cols, true)) {
                        $orderCol = 'id';
                    }
                    $sql = 'SELECT ' . $valueCol . ' AS valor FROM ' . $table . ' WHERE ' . $keyCol . ' = ?';
                    if ($orderCol) {
                        $sql .= ' ORDER BY ' . $orderCol . ' DESC';
                    }
                    $sql .= ' LIMIT 1';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$chave]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return $default;
    }

    private function getAsaasBaseUrl(): string {
        $amb = strtolower(trim((string) $this->asaasAmbiente));
        if ($amb === 'production' || $amb === 'prod' || $amb === 'live') {
            return 'https://www.asaas.com/api/v3';
        }
        return 'https://sandbox.asaas.com/api/v3';
    }

    private function isAsaasSandbox(): bool {
        $amb = strtolower(trim((string) $this->asaasAmbiente));
        return !($amb === 'production' || $amb === 'prod' || $amb === 'live');
    }

    private function asaasRequest(string $method, string $path, ?array $body = null): array {
        if (empty($this->asaasApiKey)) {
            throw new \Exception('Asaas não configurado (API Key ausente)');
        }

        $url = rtrim($this->getAsaasBaseUrl(), '/') . '/' . ltrim($path, '/');

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $this->asaasApiKey,
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com Asaas: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro Asaas HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    public function processarPagamento($dadosPagamento, $valor, $moeda, $descricao = '') {
        if ($moeda === 'BRL') {
            $forceGateway = strtolower(trim((string) ($dadosPagamento['force_gateway'] ?? '')));
            if ($forceGateway === 'appmax') {
                return $this->processarPagamentoAppmax($dadosPagamento, $valor, $descricao);
            }
            if ($forceGateway === 'asaas') {
                return $this->processarPagamentoAsaas($dadosPagamento, $valor, $descricao);
            }

            // Padrão BRL: sempre AppMax (PIX/BOLETO/CC/CD). Asaas só quando forçado explicitamente.
            return $this->processarPagamentoAppmax($dadosPagamento, $valor, $descricao);
        }

        // Stripe via Elements: o pagamento é confirmado no frontend.
        // Aqui mantemos compatibilidade com fluxos antigos, mas para USD o recomendado é usar createPaymentIntent().
        return $this->processarPagamentoStripe($dadosPagamento, $valor, $descricao);
    }
    
    private function processarPagamentoAsaas($dados, $valor, $descricao) {
        $billingType = $dados['billingType'] ?? 'CREDIT_CARD';

        $docDigits = preg_replace('/\D+/', '', (string) ($dados['customer_document'] ?? ''));

        $customerId = $dados['customer_id'] ?? null;
        if (empty($customerId)) {
            $customerId = $this->criarOuReutilizarClienteAsaas($dados);
        }

        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => (float) $valor,
            'dueDate' => $dados['dueDate'] ?? date('Y-m-d', strtotime('+1 day')),
            'description' => $descricao,
            'externalReference' => $dados['externalReference'] ?? null,
        ];

        if ($billingType === 'CREDIT_CARD') {
            $payload['creditCard'] = [
                'holderName' => $dados['card_holder_name'],
                'number' => preg_replace('/\D/', '', (string) $dados['card_number']),
                'expiryMonth' => $dados['card_expiry_month'],
                'expiryYear' => $dados['card_expiry_year'],
                'ccv' => $dados['card_cvv']
            ];
            $payload['creditCardHolderInfo'] = [
                'name' => $dados['customer_name'],
                'email' => $dados['customer_email'],
                'cpfCnpj' => $docDigits,
                'postalCode' => $dados['customer_zipcode'],
                'addressNumber' => $dados['customer_address_number'],
                'addressComplement' => $dados['customer_address_complement'] ?? '',
                'mobilePhone' => $dados['customer_phone']
            ];
        }

        $asaasPayment = $this->asaasRequest('POST', '/payments', $payload);

        $result = [
            'success' => true,
            'payment_id' => $asaasPayment['id'] ?? null,
            'status' => $asaasPayment['status'] ?? null,
            'invoiceUrl' => $asaasPayment['invoiceUrl'] ?? null,
            'bankSlipUrl' => $asaasPayment['bankSlipUrl'] ?? null,
            'digitableLine' => $asaasPayment['digitableLine'] ?? null,
            'billingType' => $asaasPayment['billingType'] ?? $billingType,
        ];

        if (($asaasPayment['billingType'] ?? $billingType) === 'PIX' && !empty($result['payment_id'])) {
            try {
                $pix = $this->asaasRequest('GET', '/payments/' . $result['payment_id'] . '/pixQrCode');
                $result['pix'] = [
                    'encodedImage' => $pix['encodedImage'] ?? null,
                    'payload' => $pix['payload'] ?? null,
                    'expirationDate' => $pix['expirationDate'] ?? null,
                ];
            } catch (\Exception $e) {
            }
        }

        return $result;
    }

    private function criarOuReutilizarClienteAsaas(array $dados): string {
        // Tentativa simples: criar cliente sempre (Asaas lida bem, mas pode duplicar)
        // Em produção, ideal é armazenar customer_id no seu banco.
        $docDigits = preg_replace('/\D+/', '', (string) ($dados['customer_document'] ?? ''));
        $payload = [
            'name' => $dados['customer_name'] ?? 'Cliente',
            'email' => $dados['customer_email'] ?? null,
            'cpfCnpj' => $docDigits !== '' ? $docDigits : null,
            'mobilePhone' => $dados['customer_phone'] ?? null,
        ];

        if (!empty($dados['customer_zipcode'])) {
            $payload['postalCode'] = $dados['customer_zipcode'];
        }
        if (!empty($dados['customer_address'])) {
            $payload['address'] = $dados['customer_address'];
        }
        if (!empty($dados['customer_address_number'])) {
            $payload['addressNumber'] = $dados['customer_address_number'];
        }
        if (!empty($dados['customer_address_complement'])) {
            $payload['complement'] = $dados['customer_address_complement'];
        }
        if (!empty($dados['customer_province'])) {
            $payload['province'] = $dados['customer_province'];
        }

        $created = $this->asaasRequest('POST', '/customers', $payload);
        if (empty($created['id'])) {
            throw new \Exception('Asaas: falha ao criar cliente');
        }
        return (string) $created['id'];
    }
    
    private function processarPagamentoStripe($dados, $valor, $descricao) {
        // Simulação de integração com Stripe
        // Em produção, implementar chamada real à API do Stripe
        
        // Converter para centavos (Stripe usa centavos)
        $amountCents = intval($valor * 100);
        
        $payload = [
            'amount' => $amountCents,
            'currency' => 'usd',
            'description' => $descricao,
            'payment_method' => $dados['payment_method_id'] ?? null,
            'confirmation_method' => 'manual',
            'confirm' => true
        ];
        
        // Simulação de resposta
        $response = [
            'success' => true,
            'payment_id' => 'pi_' . uniqid(),
            'status' => 'pending',
            'charge_id' => 'ch_' . uniqid(),
            'amount' => $valor,
        ];
        
        return $response;
    }
    
    public function processarWebhookAsaas($payload) {
        $evento = (string) ($payload['event'] ?? '');
        $paymentId = (string) ($payload['payment']['id'] ?? '');
        $status = strtoupper((string) ($payload['payment']['status'] ?? ''));

        if (empty($paymentId)) {
            return ['status' => 'ignored'];
        }

        $internal = $this->mapearStatusAsaasParaInterno($status, $evento);
        $this->atualizarPagamentoPedidoPorGateway($paymentId, 'asaas', $internal, $status);

        return ['status' => 'processed'];
    }
    
    public function processarWebhookStripe($payload) {
        $eventType = (string) ($payload['type'] ?? '');
        $obj = $payload['data']['object'] ?? null;
        if (!is_array($obj)) {
            return ['status' => 'ignored'];
        }

        $paymentId = trim((string) ($obj['id'] ?? ''));

        // Disputa / Chargeback (Stripe)
        // Referências comuns:
        // - charge.dispute.created
        // - charge.dispute.funds_withdrawn
        // - charge.dispute.closed
        // - charge.dispute.funds_reinstated
        if (str_starts_with($eventType, 'charge.dispute.')) {
            $disputeStatus = strtolower(trim((string) ($obj['status'] ?? '')));
            $reason = (string) ($obj['reason'] ?? '');

            // Resolver charge_id, payment_intent e/ou metadata (pedido)
            $chargeId = trim((string) ($obj['charge'] ?? ($obj['charge_id'] ?? '')));
            $paymentIntentId = trim((string) ($obj['payment_intent'] ?? ''));

            $internal = 'dispute';
            if (in_array($disputeStatus, ['lost'], true)) {
                $internal = 'chargeback';
            } elseif (in_array($disputeStatus, ['won'], true)) {
                // disputa encerrada a favor do lojista
                $internal = 'approved';
            } elseif (in_array($eventType, ['charge.dispute.funds_withdrawn'], true)) {
                $internal = 'chargeback';
            }

            // Tenta localizar e atualizar pedido
            try {
                $db = \Config\Database::getConnection();
                $colsP = [];
                try {
                    $stmtColsP = $db->query('DESCRIBE pedidos');
                    $colsP = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsP = [];
                }

                $pedidoId = 0;
                if (is_array($colsP) && in_array('payment_id', $colsP, true) && in_array('payment_gateway', $colsP, true)) {
                    // Algumas implementações salvam payment_intent em payment_id
                    if ($paymentIntentId !== '') {
                        $st = $db->prepare('SELECT id FROM pedidos WHERE payment_gateway = ? AND payment_id = ? LIMIT 1');
                        $st->execute(['stripe', $paymentIntentId]);
                        $pedidoId = (int) ($st->fetchColumn() ?: 0);
                    }
                }

                // Fallback: tentar na tabela pagamentos por charge_id/payment_intent
                if ($pedidoId <= 0) {
                    try {
                        $stmtColsPg = $db->query('DESCRIBE pagamentos');
                        $colsPg = $stmtColsPg ? ($stmtColsPg->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        if (is_array($colsPg) && in_array('pedido_id', $colsPg, true)) {
                            $transacaoCol = null;
                            foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id', 'charge_id'] as $c) {
                                if (in_array($c, $colsPg, true)) {
                                    $transacaoCol = $c;
                                    break;
                                }
                            }
                            if ($transacaoCol && ($chargeId !== '' || $paymentIntentId !== '')) {
                                $needle = $paymentIntentId !== '' ? $paymentIntentId : $chargeId;
                                $st = $db->prepare('SELECT pedido_id FROM pagamentos WHERE ' . $transacaoCol . ' = ? LIMIT 1');
                                $st->execute([$needle]);
                                $pedidoId = (int) ($st->fetchColumn() ?: 0);
                            }
                        }
                    } catch (\Exception $e) {
                        $pedidoId = 0;
                    }
                }

                if ($pedidoId > 0) {
                    // Atualiza apenas payment_status (e efeitos financeiros quando aplicável)
                    $gatewayStatus = $disputeStatus !== '' ? strtoupper($disputeStatus) : strtoupper($eventType);
                    $this->atualizarPagamentoPedidoPorPedidoId((int) $pedidoId, 'stripe', $internal, $gatewayStatus);
                    return [
                        'status' => 'processed',
                        'event' => $eventType,
                        'internal' => $internal,
                        'pedido_id' => (int) $pedidoId,
                        'charge_id' => $chargeId,
                        'payment_intent' => $paymentIntentId,
                        'reason' => $reason,
                    ];
                }
            } catch (\Exception $e) {
                // não falhar webhook
            }

            return [
                'status' => 'ignored',
                'event' => $eventType,
                'internal' => $internal,
                'charge_id' => $chargeId,
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];
        }

        $gatewayStatus = strtoupper((string) ($obj['status'] ?? ''));

        if ($eventType === 'payment_intent.succeeded') {
            $this->tentarCreditarCarteiraPorRecarga('stripe', $paymentId, 'approved', $gatewayStatus !== '' ? $gatewayStatus : 'SUCCEEDED');
            $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'approved', $gatewayStatus !== '' ? $gatewayStatus : 'SUCCEEDED');
            return ['status' => 'processed'];
        }

        if ($eventType === 'payment_intent.payment_failed' || $eventType === 'payment_intent.canceled') {
            $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'rejected', $gatewayStatus !== '' ? $gatewayStatus : 'FAILED');
            return ['status' => 'processed'];
        }

        return ['status' => 'ignored'];
    }

    public function processarWebhookAppmax($payload) {
        $event = '';
        foreach (['event', 'evento', 'type', 'nome', 'name'] as $k) {
            if (!empty($payload[$k]) && is_string($payload[$k])) {
                $event = (string) $payload[$k];
                break;
            }
        }
        $eventNorm = strtoupper(trim($event));

        $data = $payload['data'] ?? $payload['payload'] ?? $payload;
        if (!is_array($data)) {
            $data = $payload;
        }

        $externalReference = '';
        foreach (['external_reference', 'externalReference', 'external_key', 'externalKey', 'reference', 'pedido_id', 'order_id', 'orderId'] as $k) {
            if (isset($data[$k]) && (is_string($data[$k]) || is_numeric($data[$k]))) {
                $externalReference = (string) $data[$k];
                break;
            }
        }

        $paymentId = '';
        foreach (['payment_id', 'paymentId', 'transaction_id', 'transactionId', 'id'] as $k) {
            if (isset($data[$k]) && (is_string($data[$k]) || is_numeric($data[$k]))) {
                $paymentId = (string) $data[$k];
                break;
            }
        }

        $internal = 'pending';
        // Eventos oficiais (docs): OrderApproved, OrderPaid, OrderRefund, PaymentNotAuthorized, etc.
        if (in_array($eventNorm, ['ORDERAPPROVED', 'ORDERPAID'], true) || str_contains($eventNorm, 'APPROVED') || str_contains($eventNorm, 'PAID')) {
            $internal = 'approved';
        } elseif (in_array($eventNorm, ['ORDERREFUND'], true) || str_contains($eventNorm, 'REFUND')) {
            $internal = 'refunded';
        } elseif (in_array($eventNorm, ['PAYMENTNOTAUTHORIZED', 'PAYMENTNOTAUTHORIZEDWITHDELAY(60M)'], true) || str_contains($eventNorm, 'NOTAUTHORIZED') || str_contains($eventNorm, 'NOT_AUTHORIZED')) {
            $internal = 'rejected';
        }

        // Para AppMax, normalmente atualizamos pelo order_id.
        if ($paymentId !== '' && ctype_digit($paymentId)) {
            $this->tentarCreditarCarteiraPorRecarga('appmax', $paymentId, $internal, $eventNorm !== '' ? $eventNorm : $internal);
            $this->atualizarPagamentoPedidoPorGateway($paymentId, 'appmax', $internal, $eventNorm !== '' ? $eventNorm : $internal);
            return ['status' => 'processed', 'match' => 'payment_id'];
        }

        if ($externalReference !== '' && ctype_digit($externalReference)) {
            $pid = (int) $externalReference;
            if ($pid > 0) {
                $this->atualizarPagamentoPedidoPorPedidoId($pid, 'appmax', $internal, $eventNorm !== '' ? $eventNorm : $internal);
                return ['status' => 'processed', 'match' => 'pedido_id'];
            }
        }

        // Fallback: tenta extrair order_id/id do payload
        $orderId = '';
        foreach (['order_id', 'orderId', 'id'] as $k) {
            if (isset($data[$k]) && (is_string($data[$k]) || is_numeric($data[$k]))) {
                $orderId = (string) $data[$k];
                break;
            }
        }
        if ($orderId !== '' && ctype_digit($orderId)) {
            $this->tentarCreditarCarteiraPorRecarga('appmax', $orderId, $internal, $eventNorm !== '' ? $eventNorm : $internal);
            $this->atualizarPagamentoPedidoPorGateway($orderId, 'appmax', $internal, $eventNorm !== '' ? $eventNorm : $internal);
            return ['status' => 'processed', 'match' => 'order_id'];
        }

        return ['status' => 'ignored'];
    }

    private function atualizarPagamentoPedidoPorPedidoId(int $pedidoId, string $gateway, string $paymentStatusInterno, string $gatewayStatus = ''): void {
        try {
            $db = \Config\Database::getConnection();
            $colsP = [];
            try {
                $stmtColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
            }
            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $set = [];
            $params = ['id' => $pedidoId];

            if (in_array('payment_gateway', $colsP, true)) {
                $set[] = 'payment_gateway = :payment_gateway';
                $params['payment_gateway'] = $gateway;
            }

            if (in_array('payment_status', $colsP, true)) {
                $set[] = 'payment_status = :payment_status';
                $params['payment_status'] = $paymentStatusInterno;
            }

            $aprovado = ($paymentStatusInterno === 'approved');
            // Se este pedido usa split (produto + taxa), não marcar como pago aqui.
            // O status agregado é recalculado a partir da tabela pedido_pagamentos.
            $splitMap = $this->obterStatusSplitPorPedido((int) $pedidoId);
            $hasSplit = (!empty($splitMap));
            $hasSplitBoth = ($hasSplit && (array_key_exists('produto', $splitMap)) && (array_key_exists('taxa_servico', $splitMap) || array_key_exists('taxa', $splitMap)));

            if ($aprovado && !$hasSplitBoth && in_array('pago_em', $colsP, true)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = date('Y-m-d H:i:s');
            }

            if ($aprovado && !$hasSplitBoth && in_array('status', $colsP, true)) {
                $set[] = 'status = :status';
                $params['status'] = 'pago';
            }

            if (!empty($set)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmtUp = $db->prepare($sql);
                $stmtUp->execute($params);
            }

            if ($aprovado && !$hasSplitBoth) {
                $this->creditarCashbackClubePorPedidoAprovado($db, (int) $pedidoId);
            }

            if ($paymentStatusInterno === 'refunded') {
                $this->debitarCashbackClubePorPedidoEstornado($db, (int) $pedidoId);
                $this->debitarRendimentoClubePorPedidoEstornado($db, (int) $pedidoId);
                $this->devolverEstoquePorPedido($db, (int) $pedidoId);
                try {
                    $this->pedidoModel->dispararEvento('pagamento_estornado', $pedidoId);
                } catch (\Exception $e) {
                }
            }

            if ($paymentStatusInterno === 'rejected') {
                try {
                    $this->pedidoModel->dispararEvento('pagamento_cancelado', $pedidoId);
                } catch (\Exception $e) {
                }
            }

            try {
                $stmtColsPg = $db->query('DESCRIBE pagamentos');
                $colsPg = $stmtColsPg->fetchAll(\PDO::FETCH_COLUMN);
                if (is_array($colsPg) && in_array('pedido_id', $colsPg, true)) {
                    $updates = [];
                    $paramsPg = ['pedido_id' => $pedidoId];
                    foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                        if (in_array($c, $colsPg, true)) {
                            $updates[] = "$c = :pg_status";
                            $paramsPg['pg_status'] = $gatewayStatus !== '' ? $gatewayStatus : $paymentStatusInterno;
                            break;
                        }
                    }
                    foreach (['gateway', 'provedor', 'provider'] as $c) {
                        if (in_array($c, $colsPg, true)) {
                            $updates[] = "$c = :pg_gateway";
                            $paramsPg['pg_gateway'] = $gateway;
                            break;
                        }
                    }
                    if (!empty($updates)) {
                        $sqlPg = 'UPDATE pagamentos SET ' . implode(', ', $updates) . ' WHERE pedido_id = :pedido_id';
                        $stmtUpPg = $db->prepare($sqlPg);
                        $stmtUpPg->execute($paramsPg);
                    }
                }
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
            return;
        }
    }

    private function appmaxRequestToken(): array {
        if (empty($this->appmaxClientId) || empty($this->appmaxClientSecret)) {
            throw new \Exception('AppMax não configurado (client_id/client_secret ausentes)');
        }

        $url = 'https://auth.appmax.com.br/oauth2/token';
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->appmaxClientId,
            'client_secret' => $this->appmaxClientSecret,
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com AppMax Auth: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro AppMax Auth HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getAppmaxAccessToken(): string {
        $enabled = ($this->appmaxEnabled === '1' || strtolower((string) $this->appmaxEnabled) === 'true');
        if (!$enabled) {
            throw new \Exception('AppMax desativado');
        }

        $now = time();
        if (!empty($this->appmaxAccessToken) && !empty($this->appmaxAccessTokenExpiresAt) && $now < ((int) $this->appmaxAccessTokenExpiresAt - 30)) {
            return (string) $this->appmaxAccessToken;
        }

        $tok = $this->appmaxRequestToken();
        $accessToken = (string) ($tok['access_token'] ?? '');
        $expiresIn = (int) ($tok['expires_in'] ?? 0);
        if ($accessToken === '') {
            throw new \Exception('AppMax: access_token não retornado');
        }

        $this->appmaxAccessToken = $accessToken;
        $this->appmaxAccessTokenExpiresAt = $expiresIn > 0 ? ($now + $expiresIn) : ($now + 3600);
        return $accessToken;
    }

    private function mapearStatusAsaasParaInterno(string $status, string $evento = ''): string {
        $st = strtoupper(trim($status));
        $ev = strtoupper(trim($evento));

        if (in_array($st, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true)) {
            return 'approved';
        }

        if (in_array($st, ['REFUNDED'], true) || str_contains($ev, 'REFUND')) {
            return 'refunded';
        }

        if (in_array($st, ['CANCELED', 'CANCELLED', 'DELETED'], true) || str_contains($ev, 'CANCEL') || str_contains($ev, 'DELET')) {
            return 'rejected';
        }

        if (in_array($st, ['OVERDUE'], true) || str_contains($ev, 'OVERDUE')) {
            return 'pending';
        }

        if (in_array($st, ['PENDING'], true)) {
            return 'pending';
        }

        return 'pending';
    }

    private function atualizarPagamentoPedidoPorGateway(string $paymentId, string $gateway, string $paymentStatusInterno, string $gatewayStatus = ''): void {
        try {
            // Payment Links: atualizar histórico quando encontrar match por gateway_payment_id
            try {
                $this->atualizarPaymentLinkPaymentPorGatewayPaymentId($paymentId, $gateway, $paymentStatusInterno, $gatewayStatus);
            } catch (\Exception $e) {
            }

            // Primeiro: se existe split payment com este (gateway,payment_id), atualizar split e recalcular.
            if ($this->atualizarSplitPorGatewayPaymentId($paymentId, $gateway, $paymentStatusInterno, $gatewayStatus)) {
                return;
            }

            $db = \Config\Database::getConnection();

            $colsP = [];
            try {
                $stmtColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
            }

            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $pedidoId = null;

            // Primeiro, tenta localizar via colunas em pedidos (schema completo)
            if (in_array('payment_id', $colsP, true) && in_array('payment_gateway', $colsP, true)) {
                $stmt = $db->prepare("SELECT id FROM pedidos WHERE payment_id = :payment_id AND payment_gateway = :gateway LIMIT 1");
                $stmt->bindParam(':payment_id', $paymentId);
                $stmt->bindParam(':gateway', $gateway);
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    $pedidoId = (int) $row['id'];
                }
            }

            // Fallback: localizar via tabela pagamentos (schema onde pagamentos guarda transação/gateway)
            if (empty($pedidoId)) {
                try {
                    $stmtColsPg = $db->query('DESCRIBE pagamentos');
                    $colsPg = $stmtColsPg->fetchAll(\PDO::FETCH_COLUMN);

                    if (is_array($colsPg) && in_array('pedido_id', $colsPg, true)) {
                        $gatewayCol = null;
                        foreach (['gateway', 'provedor', 'provider'] as $c) {
                            if (in_array($c, $colsPg, true)) {
                                $gatewayCol = $c;
                                break;
                            }
                        }

                        $transacaoCol = null;
                        foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id'] as $c) {
                            if (in_array($c, $colsPg, true)) {
                                $transacaoCol = $c;
                                break;
                            }
                        }

                        if (!empty($transacaoCol)) {
                            $sql = 'SELECT pedido_id FROM pagamentos WHERE ' . $transacaoCol . ' = :payment_id';
                            if (!empty($gatewayCol)) {
                                $sql .= ' AND ' . $gatewayCol . ' = :gateway';
                            }
                            $sql .= ' LIMIT 1';

                            $stmt = $db->prepare($sql);
                            $stmt->bindParam(':payment_id', $paymentId);
                            if (!empty($gatewayCol)) {
                                $stmt->bindParam(':gateway', $gateway);
                            }
                            $stmt->execute();
                            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                            if ($row && !empty($row['pedido_id'])) {
                                $pedidoId = (int) $row['pedido_id'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            if (empty($pedidoId)) {
                return;
            }

            $set = [];
            $params = ['id' => $pedidoId];

            if (in_array('payment_status', $colsP, true)) {
                $set[] = 'payment_status = :payment_status';
                $params['payment_status'] = $paymentStatusInterno;
            }

            $aprovado = ($paymentStatusInterno === 'approved');
            if ($aprovado && in_array('pago_em', $colsP, true)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = date('Y-m-d H:i:s');
            }

            if ($aprovado && in_array('status', $colsP, true)) {
                $pedidoStatusPago = 'pago';
                try {
                    $stmtEnum = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
                    $stmtEnum->execute(['pedidos', 'status']);
                    $colType = (string) ($stmtEnum->fetchColumn() ?: '');
                    $colTypeLower = strtolower($colType);

                    if (str_starts_with($colTypeLower, 'enum(')) {
                        $inside = trim(substr($colType, 5));
                        $inside = rtrim($inside, ')');
                        $rawVals = array_filter(array_map('trim', explode(',', $inside)));
                        $vals = [];
                        foreach ($rawVals as $rv) {
                            $rv = trim($rv);
                            $rv = trim($rv, "\"' ");
                            if ($rv !== '') {
                                $vals[] = $rv;
                            }
                        }

                        $candidates = ['pago', 'paid', 'aprovado', 'approved'];
                        foreach ($candidates as $cand) {
                            if (in_array($cand, $vals, true)) {
                                $pedidoStatusPago = $cand;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                }

                $set[] = 'status = :status';
                $params['status'] = $pedidoStatusPago;
            }

            if (!empty($set)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmtUp = $db->prepare($sql);
                $stmtUp->execute($params);
            }

            // Atualizar também a linha em pagamentos, quando existir
            try {
                $stmtColsPg = $db->query('DESCRIBE pagamentos');
                $colsPg = $stmtColsPg->fetchAll(\PDO::FETCH_COLUMN);
                if (is_array($colsPg) && in_array('pedido_id', $colsPg, true)) {
                    $updates = [];
                    $paramsPg = ['pedido_id' => $pedidoId];

                    foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                        if (in_array($c, $colsPg, true)) {
                            $updates[] = "$c = :pg_status";
                            $paramsPg['pg_status'] = $gatewayStatus !== '' ? $gatewayStatus : $paymentStatusInterno;
                            break;
                        }
                    }

                    if (!empty($updates)) {
                        $sqlPg = 'UPDATE pagamentos SET ' . implode(', ', $updates) . ' WHERE pedido_id = :pedido_id';
                        $stmtUpPg = $db->prepare($sqlPg);
                        $stmtUpPg->execute($paramsPg);
                    }
                }
            } catch (\Exception $e) {
            }

            if ($aprovado) {
                $this->creditarCashbackClubePorPedidoAprovado($db, (int) $pedidoId);
                $this->pedidoModel->dispararEvento('pagamento_aprovado', $pedidoId);
                $pagoEm = null;
                if (!empty($params['pago_em'])) {
                    try {
                        $pagoEm = new \DateTime((string) $params['pago_em']);
                    } catch (\Exception $e) {
                        $pagoEm = null;
                    }
                }
                if (!$pagoEm) {
                    $pagoEm = new \DateTime('now');
                }
                $this->inserirPedidoNaJanelaRemessa($db, (int) $pedidoId, $pagoEm);
            }

            if ($paymentStatusInterno === 'refunded') {
                $this->debitarCashbackClubePorPedidoEstornado($db, (int) $pedidoId);
                $this->debitarRendimentoClubePorPedidoEstornado($db, (int) $pedidoId);
                $this->devolverEstoquePorPedido($db, (int) $pedidoId);
                $this->pedidoModel->dispararEvento('pagamento_estornado', $pedidoId);
            }

            if ($paymentStatusInterno === 'rejected') {
                $this->pedidoModel->dispararEvento('pagamento_cancelado', $pedidoId);
            }
        } catch (\Exception $e) {
            // Webhook não deve retornar 4xx por causa de erro interno/schema
            return;
        }
    }
    
    private function confirmarPagamento($paymentId, $gateway) {
        if (empty($paymentId) || empty($gateway)) {
            return;
        }
        $this->atualizarPagamentoPedidoPorGateway((string) $paymentId, (string) $gateway, 'approved', 'CONFIRMED');
    }

    private function ensureRemessaJanelaForDate(\PDO $db, \DateTime $dt): ?int {
        try {
            $dtStr = $dt->format('Y-m-d H:i:s');
            $stmt = $db->prepare('SELECT id FROM remessa_janelas WHERE data_inicio <= ? AND data_fim >= ? ORDER BY data_inicio DESC LIMIT 1');
            $stmt->execute([$dtStr, $dtStr]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }

            $stmtLast = $db->query('SELECT data_fim FROM remessa_janelas ORDER BY data_inicio DESC LIMIT 1');
            $lastFim = $stmtLast ? $stmtLast->fetchColumn() : null;

            if (!empty($lastFim)) {
                $start = new \DateTime((string) $lastFim);
                $start->modify('+1 second');
            } else {
                $start = new \DateTime($dt->format('Y-m-d 00:00:00'));
            }

            while (true) {
                $end = (clone $start);
                $end->modify('+12 days');
                $end->setTime(23, 59, 59);

                $status = ($end < $dt) ? 'finalizada' : 'aberta';

                $stIns = $db->prepare('INSERT INTO remessa_janelas (data_inicio, data_fim, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                $stIns->execute([
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s'),
                    $status,
                ]);

                if ($start <= $dt && $end >= $dt) {
                    $newId = (int) $db->lastInsertId();
                    return $newId > 0 ? $newId : null;
                }

                $start = (clone $end);
                $start->modify('+1 second');
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    private function inserirPedidoNaJanelaRemessa(\PDO $db, int $pedidoId, \DateTime $pagoEm): void {
        try {
            if (!$this->isPedidoEntregaBrasil($db, (int) $pedidoId)) {
                return;
            }

            $janelaId = $this->ensureRemessaJanelaForDate($db, $pagoEm);
            if (empty($janelaId)) {
                return;
            }
            $st = $db->prepare('INSERT IGNORE INTO remessa_janela_pedidos (janela_id, pedido_id, created_at) VALUES (?, ?, NOW())');
            $st->execute([(int) $janelaId, (int) $pedidoId]);
        } catch (\Exception $e) {
        }
    }

    public function obterPagamentoAsaas(string $paymentId): array {
        return $this->asaasRequest('GET', '/payments/' . $paymentId);
    }

    public function obterPixQrCodeAsaas(string $paymentId): array {
        return $this->asaasRequest('GET', '/payments/' . $paymentId . '/pixQrCode');
    }

    public function reemitirCobrancaAsaasPorPedido(int $pedidoId): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            throw new \Exception('Pedido não encontrado');
        }

        $gateway = (string) ($pedido['payment_gateway'] ?? '');
        $paymentId = (string) ($pedido['payment_id'] ?? '');
        if ($gateway !== 'asaas' || empty($paymentId)) {
            throw new \Exception('Pedido sem pagamento Asaas');
        }

        $payment = $this->obterPagamentoAsaas($paymentId);
        $billingType = strtoupper((string) ($payment['billingType'] ?? ''));
        if (!in_array($billingType, ['PIX', 'BOLETO'], true)) {
            throw new \Exception('Reemissão disponível apenas para PIX e BOLETO');
        }

        $status = strtoupper((string) ($payment['status'] ?? ''));
        $precisaCriarNova = false;
        if (in_array($status, ['OVERDUE', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
            $precisaCriarNova = true;
        }

        $novoPaymentId = $paymentId;
        $novoPayment = $payment;

        if ($precisaCriarNova) {
            $payload = [
                'customer' => $payment['customer'] ?? null,
                'billingType' => $billingType,
                'value' => isset($payment['value']) ? (float) $payment['value'] : null,
                'dueDate' => date('Y-m-d', strtotime('+1 day')),
                'description' => $payment['description'] ?? ('Pedido #' . $pedidoId),
                'externalReference' => (string) $pedidoId,
            ];

            if (empty($payload['customer']) || empty($payload['value'])) {
                throw new \Exception('Não foi possível reemitir: dados incompletos no Asaas');
            }

            $novoPayment = $this->asaasRequest('POST', '/payments', $payload);
            $novoPaymentId = (string) ($novoPayment['id'] ?? '');
            if (empty($novoPaymentId)) {
                throw new \Exception('Asaas: falha ao criar nova cobrança');
            }

            $this->atualizarPedidoComNovoPagamentoAsaas($pedidoId, $novoPaymentId, (string) ($novoPayment['status'] ?? 'PENDING'));
        }

        $pixQrCode = null;
        if ($billingType === 'PIX' && !empty($novoPaymentId)) {
            try {
                $pixQrCode = $this->obterPixQrCodeAsaas($novoPaymentId);
            } catch (\Exception $e) {
            }
        }

        return [
            'success' => true,
            'billingType' => $billingType,
            'payment' => $novoPayment,
            'payment_id' => $novoPaymentId,
            'pixQrCode' => $pixQrCode,
            'recreated' => $precisaCriarNova,
        ];
    }

    private function atualizarPedidoComNovoPagamentoAsaas(int $pedidoId, string $paymentId, string $gatewayStatus): void {
        $db = \Config\Database::getConnection();
        $colsP = [];
        try {
            $stmtColsP = $db->query('DESCRIBE pedidos');
            $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
        }

        if (!is_array($colsP) || empty($colsP)) {
            return;
        }

        $set = [];
        $params = ['id' => $pedidoId];

        if (in_array('payment_gateway', $colsP, true)) {
            $set[] = 'payment_gateway = :payment_gateway';
            $params['payment_gateway'] = 'asaas';
        }

        if (in_array('payment_id', $colsP, true)) {
            $set[] = 'payment_id = :payment_id';
            $params['payment_id'] = $paymentId;
        }

        $internal = $this->mapearStatusAsaasParaInterno(strtoupper((string) $gatewayStatus));
        if (in_array('payment_status', $colsP, true)) {
            $set[] = 'payment_status = :payment_status';
            $params['payment_status'] = $internal;
        }

        if (in_array('pago_em', $colsP, true)) {
            $set[] = 'pago_em = :pago_em';
            $params['pago_em'] = null;
        }

        if (in_array('status', $colsP, true)) {
            $set[] = 'status = :status';
            $params['status'] = 'pendente';
        }

        if (empty($set)) {
            return;
        }

        $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmtUp = $db->prepare($sql);
        $stmtUp->execute($params);
    }
    
    public function estornarPagamento($pedidoId, $motivo = '') {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        
        if (!$pedido || $pedido['payment_status'] !== 'approved') {
            throw new \Exception('Pagamento não pode ser estornado');
        }
        
        if ($pedido['payment_gateway'] === 'asaas') {
            return $this->estornarPagamentoAsaas($pedido['payment_id'], $motivo);
        } else {
            return $this->estornarPagamentoStripe($pedido['payment_id'], $motivo);
        }
    }
    
    private function estornarPagamentoAsaas($paymentId, $motivo) {
        // Simulação de estorno Asaas
        return [
            'success' => true,
            'refund_id' => 'ref_' . uniqid(),
            'amount' => 0,
            'status' => 'REFUNDED'
        ];
    }
    
    private function estornarPagamentoStripe($paymentId, $motivo) {
        // Simulação de estorno Stripe
        return [
            'success' => true,
            'refund_id' => 're_' . uniqid(),
            'amount' => 0,
            'status' => 'succeeded'
        ];
    }
    
    public function criarPaymentMethod($dados, $gateway) {
        if ($gateway === 'asaas') {
            return $this->criarPaymentMethodAsaas($dados);
        } else {
            return $this->criarPaymentMethodStripe($dados);
        }
    }
    
    private function criarPaymentMethodAsaas($dados) {
        // Simulação de criação de payment method Asaas
        return [
            'success' => true,
            'payment_method_id' => 'card_' . uniqid(),
            'brand' => 'MASTERCARD',
            'last4' => '4242'
        ];
    }
    
    private function criarPaymentMethodStripe($dados) {
        // Simulação de criação de payment method Stripe
        return [
            'success' => true,
            'payment_method_id' => 'pm_' . uniqid(),
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2025
            ]
        ];
    }

    public function getStripePublishableKey(): string {
        return $this->stripePublishableKey;
    }

    public function getStripeWebhookSecret(): string {
        return $this->stripeWebhookSecret;
    }

    public function isStripeEnabled(): bool {
        $v = strtolower(trim((string) $this->stripeEnabled));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    public function createStripePaymentIntent(int $pedidoId, float $valorUsd, string $descricao, array $customer = []): array {
        if (!$this->isStripeEnabled()) {
            return ['success' => false, 'error' => 'Stripe está desabilitado.'];
        }
        if (empty($this->stripeApiKey)) {
            return ['success' => false, 'error' => 'Stripe não configurado (Secret Key ausente).'];
        }

        $amountCents = (int) round($valorUsd * 100);
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'Valor inválido para cobrança.'];
        }

        $metadata = [
            'pedido_id' => (string) $pedidoId,
        ];

        $body = [
            'amount' => (string) $amountCents,
            'currency' => 'usd',
            'description' => $descricao,
            'metadata[pedido_id]' => (string) $pedidoId,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        if (!empty($customer['email'])) {
            $body['receipt_email'] = (string) $customer['email'];
        }

        try {
            $pi = $this->stripeRequest('POST', '/v1/payment_intents', $body);
            $id = (string) ($pi['id'] ?? '');
            $clientSecret = (string) ($pi['client_secret'] ?? '');
            if ($id === '' || $clientSecret === '') {
                return ['success' => false, 'error' => 'Stripe: resposta inválida ao criar PaymentIntent.'];
            }

            return [
                'success' => true,
                'payment_intent_id' => $id,
                'client_secret' => $clientSecret,
                'status' => (string) ($pi['status'] ?? ''),
                'raw' => $pi,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function retrieveStripePaymentIntent(string $paymentIntentId): array {
        if (empty($this->stripeApiKey)) {
            throw new \Exception('Stripe não configurado (Secret Key ausente).');
        }
        return $this->stripeRequest('GET', '/v1/payment_intents/' . rawurlencode($paymentIntentId), null);
    }

    public function retrieveStripeCheckoutSession(string $checkoutSessionId): array {
        if (empty($this->stripeApiKey)) {
            throw new \Exception('Stripe não configurado (Secret Key ausente).');
        }
        $id = trim($checkoutSessionId);
        if ($id === '') {
            throw new \Exception('Stripe: checkout session id vazio.');
        }
        // Expand do payment_intent para facilitar
        return $this->stripeRequest('GET', '/v1/checkout/sessions/' . rawurlencode($id) . '?expand[]=payment_intent', null);
    }

    private function normalizeStripePaymentIntentIdFromStoredId(string $storedPaymentId): array {
        $pid = trim($storedPaymentId);
        if ($pid === '') {
            return ['payment_intent_id' => '', 'source' => 'empty', 'session' => null];
        }
        if (str_starts_with($pid, 'pi_')) {
            return ['payment_intent_id' => $pid, 'source' => 'payment_intent', 'session' => null];
        }
        if (str_starts_with($pid, 'cs_')) {
            $session = $this->retrieveStripeCheckoutSession($pid);
            $pi = (string) ($session['payment_intent']['id'] ?? ($session['payment_intent'] ?? ''));
            $pi = trim($pi);
            return ['payment_intent_id' => $pi, 'source' => 'checkout_session', 'session' => $session];
        }
        // Unknown id format; try as PI
        return ['payment_intent_id' => $pid, 'source' => 'unknown', 'session' => null];
    }

    private function atualizarPedidoStripeComPaymentIntent(int $pedidoId, string $paymentIntentId, ?array $session = null): void {
        $pi = trim($paymentIntentId);
        if ($pedidoId <= 0 || $pi === '') {
            return;
        }
        try {
            $db = \Config\Database::getConnection();
            $stmtCols = $db->query('DESCRIBE pedidos');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (!is_array($cols) || empty($cols)) {
                return;
            }

            $set = [];
            $params = [':id' => $pedidoId];

            if (in_array('payment_gateway', $cols, true)) {
                $set[] = 'payment_gateway = :pg';
                $params[':pg'] = 'stripe';
            }

            if (in_array('payment_id', $cols, true)) {
                $set[] = 'payment_id = :pid';
                $params[':pid'] = $pi;
            }

            if (is_array($session) && in_array('payment_invoice_url', $cols, true)) {
                $url = (string) ($session['url'] ?? '');
                $url = trim($url);
                if ($url !== '') {
                    $set[] = 'payment_invoice_url = :inv';
                    $params[':inv'] = $url;
                }
            }

            if (empty($set)) {
                return;
            }

            $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
            $st = $db->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
        }
    }

    public function atualizarStatusPagamentoStripePorPedido(int $pedidoId): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'stripe') {
            return ['success' => false, 'error' => 'Gateway não é Stripe'];
        }

        $storedPaymentId = (string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? ($pedido['pagamento_id'] ?? '')));
        $storedPaymentId = trim($storedPaymentId);
        if ($storedPaymentId === '') {
            return ['success' => false, 'error' => 'Pedido sem payment_id'];
        }

        $norm = $this->normalizeStripePaymentIntentIdFromStoredId($storedPaymentId);
        $paymentId = (string) ($norm['payment_intent_id'] ?? '');
        $source = (string) ($norm['source'] ?? '');
        $session = $norm['session'] ?? null;
        if ($paymentId === '') {
            return ['success' => false, 'error' => 'Stripe: não foi possível resolver o payment_intent a partir do payment_id'];
        }

        if ($source === 'checkout_session') {
            $this->atualizarPedidoStripeComPaymentIntent($pedidoId, $paymentId, is_array($session) ? $session : null);
        }

        $pi = $this->retrieveStripePaymentIntent($paymentId);
        $st = strtolower(trim((string) ($pi['status'] ?? '')));

        $internal = 'pending';
        if (in_array($st, ['succeeded'], true)) {
            $internal = 'approved';
        } elseif (in_array($st, ['canceled'], true)) {
            $internal = 'rejected';
        } elseif (in_array($st, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing', 'requires_capture'], true)) {
            $internal = 'pending';
        }

        $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', $internal, strtoupper($st));

        return [
            'success' => true,
            'gateway' => 'stripe',
            'payment_id' => $paymentId,
            'payment_status' => $internal,
            'stripe_status' => $st,
            'source' => $source,
            'raw' => $pi,
        ];
    }

    public function cancelarPagamentoStripePorPedido(int $pedidoId): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'stripe') {
            return ['success' => false, 'error' => 'Gateway não é Stripe'];
        }

        $storedPaymentId = (string) ($pedido['payment_id'] ?? '');
        $storedPaymentId = trim($storedPaymentId);
        if ($storedPaymentId === '') {
            return ['success' => false, 'error' => 'Pedido sem payment_id'];
        }

        $norm = $this->normalizeStripePaymentIntentIdFromStoredId($storedPaymentId);
        $paymentId = (string) ($norm['payment_intent_id'] ?? '');
        $source = (string) ($norm['source'] ?? '');
        $session = $norm['session'] ?? null;

        // Fallback: algumas versões da API podem não retornar payment_intent no expand.
        if ($paymentId === '' && str_starts_with($storedPaymentId, 'cs_')) {
            try {
                $s = $this->stripeRequest('GET', '/v1/checkout/sessions/' . rawurlencode($storedPaymentId), null);
                $pi = (string) ($s['payment_intent']['id'] ?? ($s['payment_intent'] ?? ''));
                $pi = trim($pi);
                if ($pi !== '') {
                    $paymentId = $pi;
                    $source = 'checkout_session';
                    $session = is_array($s) ? $s : null;
                }
            } catch (\Exception $e) {
            }
        }

        if ($paymentId === '') {
            return ['success' => false, 'error' => 'Stripe: não foi possível resolver o payment_intent a partir do payment_id: ' . $storedPaymentId];
        }
        if ($source === 'checkout_session') {
            $this->atualizarPedidoStripeComPaymentIntent($pedidoId, $paymentId, is_array($session) ? $session : null);
        }

        $resp = $this->stripeRequest('POST', '/v1/payment_intents/' . rawurlencode($paymentId) . '/cancel', []);
        $st = strtolower(trim((string) ($resp['status'] ?? 'canceled')));
        $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'rejected', strtoupper($st !== '' ? $st : 'CANCELED'));

        return [
            'success' => true,
            'gateway' => 'stripe',
            'payment_id' => $paymentId,
            'stripe_status' => $st,
            'source' => $source,
            'raw' => $resp,
        ];
    }

    public function estornarPagamentoStripePorPedido(int $pedidoId, string $motivo = ''): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'stripe') {
            return ['success' => false, 'error' => 'Gateway não é Stripe'];
        }

        $storedPaymentId = (string) ($pedido['payment_id'] ?? '');
        $storedPaymentId = trim($storedPaymentId);
        if ($storedPaymentId === '') {
            return ['success' => false, 'error' => 'Pedido sem payment_id'];
        }

        $norm = $this->normalizeStripePaymentIntentIdFromStoredId($storedPaymentId);
        $paymentId = (string) ($norm['payment_intent_id'] ?? '');
        $source = (string) ($norm['source'] ?? '');
        $session = $norm['session'] ?? null;
        if ($paymentId === '') {
            return ['success' => false, 'error' => 'Stripe: não foi possível resolver o payment_intent a partir do payment_id'];
        }
        if ($source === 'checkout_session') {
            $this->atualizarPedidoStripeComPaymentIntent($pedidoId, $paymentId, is_array($session) ? $session : null);
        }

        $body = [
            'payment_intent' => $paymentId,
        ];
        if (trim($motivo) !== '') {
            $body['metadata[motivo]'] = trim($motivo);
        }

        $refund = $this->stripeRequest('POST', '/v1/refunds', $body);
        $st = strtolower(trim((string) ($refund['status'] ?? '')));
        $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'refunded', strtoupper($st !== '' ? $st : 'REFUNDED'));

        return [
            'success' => true,
            'gateway' => 'stripe',
            'payment_id' => $paymentId,
            'refund_id' => (string) ($refund['id'] ?? ''),
            'refund_status' => $st,
            'source' => $source,
            'raw' => $refund,
        ];
    }

    public function atualizarStatusPagamentoAppmaxPorPedido(int $pedidoId): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'appmax') {
            return ['success' => false, 'error' => 'Gateway não é AppMax'];
        }

        $orderId = trim((string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? '')));
        if ($orderId === '' || !ctype_digit($orderId)) {
            return ['success' => false, 'error' => 'AppMax: pedido sem payment_id/order_id numérico'];
        }

        // Melhor esforço: consultar status do pedido na AppMax
        $raw = $this->appmaxRequest('GET', 'order/' . $orderId, null);
        $data = is_array($raw) ? ($raw['data'] ?? $raw) : [];
        $status = '';
        if (is_array($data)) {
            $status = (string) ($data['status'] ?? ($data['order_status'] ?? ($data['order']['status'] ?? '')));
        }
        $statusNorm = strtoupper(trim($status));

        $internal = 'pending';
        if ($statusNorm !== '') {
            if (str_contains($statusNorm, 'APROV') || str_contains($statusNorm, 'PAID') || str_contains($statusNorm, 'PAGO')) {
                $internal = 'approved';
            } elseif (str_contains($statusNorm, 'REFUND') || str_contains($statusNorm, 'ESTORN')) {
                $internal = 'refunded';
            } elseif (str_contains($statusNorm, 'CANCEL') || str_contains($statusNorm, 'RECUS') || str_contains($statusNorm, 'REJECT')) {
                $internal = 'rejected';
            }
        }

        $this->atualizarPagamentoPedidoPorGateway($orderId, 'appmax', $internal, $statusNorm !== '' ? $statusNorm : $internal);

        return [
            'success' => true,
            'gateway' => 'appmax',
            'payment_id' => $orderId,
            'payment_status' => $internal,
            'appmax_status' => $statusNorm,
            'raw' => $raw,
        ];
    }

    public function estornarPagamentoAppmaxPorPedido(int $pedidoId, ?float $valor = null): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'appmax') {
            return ['success' => false, 'error' => 'Gateway não é AppMax'];
        }

        $orderId = trim((string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? '')));
        if ($orderId === '' || !ctype_digit($orderId)) {
            return ['success' => false, 'error' => 'AppMax: pedido sem payment_id/order_id numérico'];
        }

        $payload = [
            'order_id' => (int) $orderId,
        ];
        if ($valor !== null && $valor > 0) {
            $payload['refund_type'] = 'partial';
            $payload['refund_amount'] = (float) $valor;
        } else {
            $payload['refund_type'] = 'total';
        }

        $resp = $this->appmaxRequest('POST', 'refund', $payload);

        $this->atualizarPagamentoPedidoPorGateway($orderId, 'appmax', 'refunded', 'REFUND');

        return [
            'success' => true,
            'gateway' => 'appmax',
            'payment_id' => $orderId,
            'refund_type' => (string) ($payload['refund_type'] ?? 'total'),
            'refund_amount' => $payload['refund_amount'] ?? null,
            'raw' => $resp,
        ];
    }

    public function cancelarPagamentoAppmaxPorPedido(int $pedidoId): array {
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gateway !== 'appmax') {
            return ['success' => false, 'error' => 'Gateway não é AppMax'];
        }

        $orderId = trim((string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? '')));
        if ($orderId === '' || !ctype_digit($orderId)) {
            return ['success' => false, 'error' => 'AppMax: pedido sem payment_id/order_id numérico'];
        }

        $current = strtolower(trim((string) ($pedido['payment_status'] ?? ($pedido['pagamento_status'] ?? 'pending'))));
        $isApproved = in_array($current, ['approved', 'aprovado', 'paid', 'pago', 'succeeded', 'success'], true);

        // A documentação pública expõe estorno via /refund. Não há endpoint oficial de "cancel".
        // Melhor esforço:
        // - Se já aprovado: solicitar estorno total (equivalente operacional ao cancelamento)
        // - Se não aprovado: marcar como rejected internamente
        if ($isApproved) {
            $refund = $this->estornarPagamentoAppmaxPorPedido($pedidoId, null);
            return [
                'success' => (bool) ($refund['success'] ?? false),
                'gateway' => 'appmax',
                'payment_id' => $orderId,
                'action' => 'refund_total',
                'result' => $refund,
            ];
        }

        $this->atualizarPagamentoPedidoPorGateway($orderId, 'appmax', 'rejected', 'CANCELED');
        return [
            'success' => true,
            'gateway' => 'appmax',
            'payment_id' => $orderId,
            'action' => 'mark_rejected',
        ];
    }

    private function stripeRequest(string $method, string $path, ?array $body): array {
        $url = 'https://api.stripe.com' . $path;
        $headers = [
            'Authorization: Bearer ' . $this->stripeApiKey,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = '';
        if ($body !== null) {
            $payload = http_build_query($body);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if (strtoupper($method) !== 'GET' && $payload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com Stripe: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro Stripe HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => (strtoupper($method) !== 'GET') ? $payload : '',
                'ignore_errors' => true,
            ]
        ]);

        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    public function validarDadosPagamento($dados) {
        $erros = [];
        
        // Validações comuns
        if (empty($dados['customer_name'])) {
            $erros[] = 'Nome do cliente é obrigatório';
        }
        
        if (empty($dados['customer_email'])) {
            $erros[] = 'E-mail do cliente é obrigatório';
        } elseif (!filter_var($dados['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        if (empty($dados['customer_document'])) {
            $erros[] = 'Documento do cliente é obrigatório';
        }
        
        if (empty($dados['customer_phone'])) {
            $erros[] = 'Telefone do cliente é obrigatório';
        }
        
        // Validações de cartão (se aplicável)
        // Para Stripe Elements, NÃO validamos nem recebemos dados de cartão no backend.
        if (isset($dados['billingType']) && $dados['billingType'] === 'CREDIT_CARD' && !isset($dados['stripe_elements'])) {
            if (empty($dados['card_holder_name'])) {
                $erros[] = 'Nome no cartão é obrigatório';
            }
            
            if (empty($dados['card_number'])) {
                $erros[] = 'Número do cartão é obrigatório';
            } elseif (!$this->validarNumeroCartao($dados['card_number'], !$this->isAsaasSandbox())) {
                $erros[] = 'Número do cartão inválido';
            }
            
            if (empty($dados['card_expiry_month']) || empty($dados['card_expiry_year'])) {
                $erros[] = 'Data de validade do cartão é obrigatória';
            } elseif (!$this->validarValidadeCartao($dados['card_expiry_month'], $dados['card_expiry_year'])) {
                $erros[] = 'Cartão expirado';
            }
            
            if (empty($dados['card_cvv'])) {
                $erros[] = 'CVV do cartão é obrigatório';
            } elseif (!preg_match('/^\d{3,4}$/', $dados['card_cvv'])) {
                $erros[] = 'CVV inválido';
            }
        }
        
        return $erros;
    }
    
    private function validarNumeroCartao($numero, bool $validarLuhn = true) {
        // Remover espaços e caracteres não numéricos
        $numero = preg_replace('/\D/', '', $numero);
        
        // Verificar se tem entre 13 e 19 dígitos
        if (strlen($numero) < 13 || strlen($numero) > 19) {
            return false;
        }

        if (!$validarLuhn) {
            return true;
        }
        
        // Algoritmo de Luhn
        $soma = 0;
        $alternar = false;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = intval($numero[$i]);
            
            if ($alternar) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            
            $soma += $digito;
            $alternar = !$alternar;
        }
        
        return $soma % 10 === 0;
    }
    
    private function validarValidadeCartao($mes, $ano) {
        $mes = intval($mes);
        $ano = intval($ano);
        $anoAtual = date('Y');
        $mesAtual = date('n');
        
        if ($ano < $anoAtual) {
            return false;
        }
        
        if ($ano == $anoAtual && $mes < $mesAtual) {
            return false;
        }
        
        return $mes >= 1 && $mes <= 12;
    }
}
