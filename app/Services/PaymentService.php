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
    private $appmaxEnabled;
    private $appmaxClientId;
    private $appmaxClientSecret;
    private $appmaxAppId;
    private $appmaxV3AccessToken;
    private $appmaxAmbiente;
    private $appmaxBaseUrl;
    private $appmaxAccessToken;
    private $appmaxAccessTokenExpiresAt;
    
    public function __construct() {
        $this->pedidoModel = new PedidoEcommerce();
        $this->loadConfigurations();
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
        $total = round(((float) $productsValueCents) / 100, 2);
        $shipping = round(((float) $shippingValueCents) / 100, 2);
        $discount = round(((float) $discountValueCents) / 100, 2);

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

        $payload = [
            'total' => $total,
            'products' => $payloadProducts,
            'shipping' => $shipping,
            'customer_id' => $customerId,
            'discount' => $discount,
            'freight_type' => (string) ($products[0]['freight_type'] ?? ($products[0]['frete_tipo'] ?? '')),
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
        $orderId = $this->appmaxCreateOrder($customerId, $productsValueCents, $discountValueCents, $shippingValueCents, $products);

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

                // Alguns retornos vem aninhados
                $pixInner = null;
                if (!empty($pixData['pix']) && is_array($pixData['pix'])) {
                    $pixInner = $pixData['pix'];
                } elseif (!empty($pixData['data']['pix']) && is_array($pixData['data']['pix'])) {
                    $pixInner = $pixData['data']['pix'];
                }

                $candidates = [$pixData];
                if (is_array($pixInner)) {
                    $candidates[] = $pixInner;
                }

                foreach ($candidates as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    if ($encodedImage === '') {
                        $encodedImage = (string) (
                            $c['encodedImage'] ??
                            $c['qr_code_base64'] ??
                            $c['qrCodeBase64'] ??
                            $c['qr_code'] ??
                            $c['qrcode'] ??
                            $c['qrcode_base64'] ??
                            $c['base64'] ??
                            ''
                        );
                    }
                    if ($payload === '') {
                        $payload = (string) (
                            $c['payload'] ??
                            $c['emv'] ??
                            $c['copy_paste'] ??
                            $c['brcode'] ??
                            $c['pixCopiaECola'] ??
                            ''
                        );
                    }
                    if ($expirationDate === '') {
                        $expirationDate = (string) (
                            $c['expirationDate'] ??
                            $c['expiration_date'] ??
                            $c['expires_at'] ??
                            ''
                        );
                    }
                }

                // Se vier como data:image/png;base64,..., remover prefixo
                if ($encodedImage !== '') {
                    $encodedImage = preg_replace('#^data:image/[^;]+;base64,#', '', $encodedImage);
                    $encodedImage = trim((string) $encodedImage);
                }
                if ($payload !== '') {
                    $payload = trim((string) $payload);
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
            return $this->processarPagamentoAppmax($dadosPagamento, $valor, $descricao);
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
            if ($aprovado && in_array('pago_em', $colsP, true)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = date('Y-m-d H:i:s');
            }

            if ($aprovado && in_array('status', $colsP, true)) {
                $set[] = 'status = :status';
                $params['status'] = 'pago';
            }

            if (!empty($set)) {
                $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmtUp = $db->prepare($sql);
                $stmtUp->execute($params);
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
