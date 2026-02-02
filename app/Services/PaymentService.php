<?php
namespace App\Services;

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
    
    public function __construct() {
        $this->pedidoModel = new PedidoEcommerce();
        $this->loadConfigurations();
    }
    
    private function loadConfigurations() {
        $this->asaasApiKey = (string) $this->getConfig('pagamentos', 'asaas_api_key', '');
        $this->asaasAmbiente = (string) $this->getConfig('pagamentos', 'asaas_ambiente', 'sandbox');
        $this->stripeApiKey = (string) $this->getConfig('pagamentos', 'stripe_secret_key', (string) $this->getConfig('pagamentos', 'stripe_api_key', ''));
        $this->stripePublishableKey = (string) $this->getConfig('pagamentos', 'stripe_publishable_key', (string) $this->getConfig('pagamentos', 'stripe_public_key', ''));
        $this->stripeAmbiente = (string) $this->getConfig('pagamentos', 'stripe_ambiente', 'test');
        $this->stripeEnabled = (string) $this->getConfig('pagamentos', 'stripe_enabled', '0');
        $this->stripeWebhookSecret = (string) $this->getConfig('pagamentos', 'stripe_webhook_secret', (string) $this->getConfig('pagamentos', 'stripe_webhook_signing_secret', ''));
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = \Config\Database::getConnection();

        // Tenta schema single-row em configuracoes_sistema (colunas diretas)
        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                $colName = null;
                if ($categoria === 'pagamentos') {
                    $direct = [
                        'asaas_api_key',
                        'asaas_ambiente',
                        'asaas_enabled',
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

        // Tenta schema chave/valor em configuracoes_sistema (sem coluna categoria)
        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        // Tenta schema chave/valor (configuracoes)
        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
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
            return $this->processarPagamentoAsaas($dadosPagamento, $valor, $descricao);
        }

        // Stripe via Elements: o pagamento é confirmado no frontend.
        // Aqui mantemos compatibilidade com fluxos antigos, mas para USD o recomendado é usar createPaymentIntent().
        return $this->processarPagamentoStripe($dadosPagamento, $valor, $descricao);
    }
    
    private function processarPagamentoAsaas($dados, $valor, $descricao) {
        $billingType = $dados['billingType'] ?? 'CREDIT_CARD';

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
                'cpfCnpj' => $dados['customer_document'],
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
        $payload = [
            'name' => $dados['customer_name'] ?? 'Cliente',
            'email' => $dados['customer_email'] ?? null,
            'cpfCnpj' => $dados['customer_document'] ?? null,
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

        $paymentId = (string) ($obj['id'] ?? '');
        if ($paymentId === '') {
            return ['status' => 'ignored'];
        }

        $gatewayStatus = strtoupper((string) ($obj['status'] ?? ''));

        if ($eventType === 'payment_intent.succeeded') {
            $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'approved', $gatewayStatus !== '' ? $gatewayStatus : 'SUCCEEDED');
            return ['status' => 'processed'];
        }

        if ($eventType === 'payment_intent.payment_failed' || $eventType === 'payment_intent.canceled') {
            $this->atualizarPagamentoPedidoPorGateway($paymentId, 'stripe', 'rejected', $gatewayStatus !== '' ? $gatewayStatus : 'FAILED');
            return ['status' => 'processed'];
        }

        return ['status' => 'ignored'];
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
        $pedido = $this->pedidoModel->find($pedidoId);
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
        $pedido = $this->pedidoModel->find($pedidoId);
        
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
