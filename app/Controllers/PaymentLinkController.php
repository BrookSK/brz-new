<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\PaymentLinkService;
use App\Services\PaymentService;

class PaymentLinkController extends Controller {
    public function index(Request $request) {
        $token = (string) $request->getParam('token', '');
        $svc = new PaymentLinkService();
        $link = $svc->findLinkByToken($token);

        if (!is_array($link) || empty($link['id'])) {
            $this->renderNotFound('Link de pagamento não encontrado');
            return;
        }

        if (!$this->isLinkActive($link)) {
            $this->renderNotFound('Link de pagamento expirado ou desativado');
            return;
        }

        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        if ($currency === '') $currency = 'USD';

        $descricao = trim((string) ($link['descricao'] ?? ''));
        if ($descricao === '') $descricao = 'Pagamento';

        $total = (float) ($link['total_valor'] ?? 0);
        $produto = (float) ($link['produto_valor'] ?? 0);
        $taxa = (float) ($link['taxa_servico_valor'] ?? 0);
        $impostos = (float) ($link['impostos_valor'] ?? 0);

        // Montar itens compatíveis com o checkout atual
        $items = [];
        $subtotalView = 0.0;
        if ($produto > 0) {
            $items[] = [
                'nome' => $descricao,
                'quantidade' => 1,
                'subtotal' => $produto,
            ];
            $subtotalView = (float) $produto;
        } else {
            // Sem produtos: o total é só taxa + impostos
            // Mostrar como item único e zerar taxa/impostos separados para não duplicar
            $itemNome = $descricao;
            if (stripos($itemNome, 'taxa') === false && stripos($itemNome, 'imposto') === false) {
                $itemNome .= ' (taxa de serviço + impostos)';
            }
            $items[] = [
                'nome' => $itemNome,
                'quantidade' => 1,
                'subtotal' => $total,
            ];
            $subtotalView = (float) $total;
            $taxa = 0;
            $impostos = 0;
        }

        $isPaymentLinkTaxaOnly = ($produto <= 0);

        // Checkout usa valores base em USD e converte via exchangeRates.
        // No Payment Link em BRL, os valores já estão em BRL e NÃO devem ser convertidos.
        // Ainda assim, precisamos da cotação USD->BRL real para o preview de taxas (envio/IOF/tarifa/VET).
        $rateBRL = 5.5;
        try {
            $dbTx = \Config\Database::getConnection();
            $stTx = $dbTx->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
            $stTx->execute();
            $v = (string) ($stTx->fetchColumn() ?: '0');
            $tx2 = (float) str_replace(',', '.', $v);
            if ($tx2 > 1.01) {
                $rateBRL = $tx2;
            }
        } catch (\Exception $e) {
        }

        $exchangeRates = [
            'USD' => 1.0,
            'BRL' => ($currency === 'BRL') ? 1.0 : $rateBRL,
        ];

        $paySvc = new PaymentService();

        $this->view('checkout/index', [
            'is_payment_link' => true,
            'is_payment_link_taxa_only' => $isPaymentLinkTaxaOnly,
            'carrinho' => [],
            'items' => $items,
            'subtotal' => $subtotalView,
            'peso_clube_total' => 0,
            'subtotal_clube' => 0,
            'desconto_clube' => 0,
            'cashback_clube_estimado' => 0,
            'peso_total' => 0,
            'usuario' => [],
            'perfil_ok' => true,
            'termos_ok' => true,
            'campos_faltando' => [],
            'enderecos' => [],
            'endereco_prefill' => [
                'pais' => 'BR',
                'cep' => '',
                'endereco' => '',
                'numero' => '',
                'complemento' => '',
                'bairro' => '',
                'cidade' => '',
                'estado' => '',
            ],
            'moeda' => $currency,
            'frete' => 0,
            'taxa_servico' => $taxa,
            'impostos' => $impostos,
            'total' => $total,
            'pix_desconto_taxa_servico_percent' => 0,
            'cobra_impostos_br' => true,
            'frete_gratis' => true,
            'exchange_rates' => $exchangeRates,
            'cambioreal_rate_brl' => $rateBRL,
            'stripe_publishable_key' => $paySvc->getStripePublishableKey(),
            'stripe_enabled' => $paySvc->isStripeEnabled(),
            'entrega_fora_br' => false,
            'mensagem_entrega_fora_br' => 'A entrega para fora do Brasil não inclui impostos brasileiros. A tributação local é responsabilidade do cliente.',
            'checkout_endpoint' => '/pagar/' . rawurlencode((string) $token),
            'cambioreal_app_id' => $paySvc->getCambioRealAppId(),
            'cambioreal_app_public' => $paySvc->getCambioRealAppPublic(),
            'cambioreal_base_url' => $paySvc->getCambioRealBaseUrlPublic(),
        ]);
        exit;
    }

    public function processar(Request $request) {
        $token = (string) $request->getParam('token', '');

        try {
            if (!headers_sent()) {
                $tok = trim((string) $token);
                $mask = $tok;
                if (strlen($tok) > 16) {
                    $mask = substr($tok, 0, 8) . '...' . substr($tok, -6);
                }
                header('X-PaymentLink-Processar: 1');
                header('X-PaymentLink-Token: ' . $mask);
                header('X-PaymentLink-Path: ' . (string) $request->getPath());
            }
        } catch (\Throwable $e) {
        }

        $svc = new PaymentLinkService();
        $link = $svc->findLinkByToken($token);

        if (!is_array($link) || empty($link['id'])) {
            $this->renderNotFound('Link de pagamento não encontrado');
            return;
        }
        if (!$this->isLinkActive($link)) {
            $this->renderNotFound('Link de pagamento expirado ou desativado');
            return;
        }

        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        if ($currency === '') $currency = 'USD';

        $nome = trim((string) $request->getParam('nome', ''));
        $email = trim((string) $request->getParam('email', ''));
        $documento = trim((string) $request->getParam('documento', ''));
        $telefone = trim((string) $request->getParam('telefone', ''));

        $forma = (string) $request->getParam('forma_pagamento', '');
        $forma = strtolower(trim($forma));

        // Persistir dados completos preenchidos no checkout rápido (endereço/entrega/destinatário/etc.)
        $formSnapshot = [
            'nome' => $nome,
            'email' => $email,
            'documento' => $documento,
            'telefone' => $telefone,
            'telefone_ddi' => (string) $request->getParam('telefone_ddi', ''),
            'telefone_numero' => (string) $request->getParam('telefone_numero', ''),
            'telefone_ddi_outro' => (string) $request->getParam('telefone_ddi_outro', ''),
            'data_nascimento' => (string) $request->getParam('data_nascimento', ''),
            'forma_pagamento' => $forma,
            'entrega_para_outro' => (string) $request->getParam('entrega_para_outro', ''),
            'destinatario_nome' => (string) $request->getParam('destinatario_nome', ''),
            'destinatario_documento' => (string) $request->getParam('destinatario_documento', ''),
            'destinatario_telefone' => (string) $request->getParam('destinatario_telefone', ''),
            'pais' => (string) $request->getParam('pais', ''),
            'cep' => (string) $request->getParam('cep', ''),
            'endereco' => (string) $request->getParam('endereco', ''),
            'numero' => (string) $request->getParam('numero', ''),
            'complemento' => (string) $request->getParam('complemento', ''),
            'bairro' => (string) $request->getParam('bairro', ''),
            'cidade' => (string) $request->getParam('cidade', ''),
            'estado' => (string) $request->getParam('estado', ''),
            'estado_text' => (string) $request->getParam('estado_text', ''),
            'endereco_selecionado' => (string) $request->getParam('endereco_selecionado', ''),
        ];
        $formSnapshotJson = json_encode($formSnapshot, JSON_UNESCAPED_UNICODE);

        $isAjax = (string) $request->getParam('ajax', '');
        $isAjax = ($isAjax === '1' || strtolower($isAjax) === 'true' || strtolower($isAjax) === 'on');

        $customer = [
            'name' => $nome,
            'email' => $email,
            'document' => $documento,
            'phone' => $telefone,
        ];

        $linkId = (int) ($link['id'] ?? 0);
        $descricao = trim((string) ($link['descricao'] ?? 'Pagamento'));
        if ($descricao === '') $descricao = 'Pagamento';

        // USD -> Stripe
        if ($currency === 'USD') {
            $attempt = $svc->createPaymentAttempt($linkId, $customer, 'credit_card', 'pagamento', [
                'produto_valor' => (float) ($link['produto_valor'] ?? 0),
                'taxa_servico_valor' => (float) ($link['taxa_servico_valor'] ?? 0),
                'impostos_valor' => (float) ($link['impostos_valor'] ?? 0),
                'total_valor' => (float) ($link['total_valor'] ?? 0),
            ]);
            if (empty($attempt['success'])) {
                $this->renderNotFound((string) ($attempt['error'] ?? 'Falha ao iniciar pagamento'));
                return;
            }

            $paymentAttemptId = (int) ($attempt['id'] ?? 0);
            if ($paymentAttemptId > 0 && $formSnapshotJson) {
                $svc->updatePaymentAttempt($paymentAttemptId, [
                    'metadata' => json_encode(['form' => $formSnapshot], JSON_UNESCAPED_UNICODE),
                ]);
            }
            $total = (float) ($link['total_valor'] ?? 0);

            $paySvc = new PaymentService();
            $base = \App\Core\Url::base();
            $successUrl = rtrim($base, '/') . '/pagar/' . rawurlencode((string) $token) . '?success=1';
            $cancelUrl = rtrim($base, '/') . '/pagar/' . rawurlencode((string) $token) . '?cancel=1';

            $session = $paySvc->createStripeCheckoutSessionForPaymentLink($paymentAttemptId, $total, $descricao, ['email' => $email], $successUrl, $cancelUrl);
            if (empty($session['success'])) {
                $svc->updatePaymentAttempt($paymentAttemptId, [
                    'status' => 'failed',
                    'gateway' => 'stripe',
                    'metadata' => json_encode(['error' => $session['error'] ?? ''], JSON_UNESCAPED_UNICODE),
                ]);
                $this->renderNotFound((string) ($session['error'] ?? 'Falha ao criar sessão Stripe'));
                return;
            }

            $svc->updatePaymentAttempt($paymentAttemptId, [
                'status' => 'pending',
                'gateway' => 'stripe',
                'gateway_payment_id' => (string) ($session['payment_intent_id'] ?? ($session['session_id'] ?? '')),
                'invoice_url' => (string) ($session['url'] ?? ''),
                'raw_response' => json_encode($session['raw'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            if ($isAjax) {
                $this->json([
                    'success' => true,
                    'redirect' => (string) ($session['url'] ?? '/pagar/' . rawurlencode((string) $token)),
                ]);
                return;
            }

            header('Location: ' . (string) ($session['url'] ?? '/pagar/' . rawurlencode((string) $token)));
            exit;
        }

        // BRL -> Câmbio Real Produtos + Câmbio Real Taxas
        $estado = trim((string) $request->getParam('estado', ''));
        $cidade = trim((string) $request->getParam('cidade', ''));
        $cep = trim((string) $request->getParam('cep', ''));
        $bairro = trim((string) $request->getParam('bairro', ''));
        $endereco = trim((string) $request->getParam('endereco', ''));
        $numero = trim((string) $request->getParam('numero', ''));
        $dataNascimento = trim((string) $request->getParam('data_nascimento', ''));

        $client = [
            'name' => $nome,
            'email' => $email,
            'document' => $documento,
            'birth_date' => $dataNascimento,
            'phone' => $telefone,
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'address' => [
                'state' => $estado,
                'city' => $cidade,
                'zip_code' => $cep,
                'district' => $bairro,
                'street' => $endereco,
                'number' => $numero,
            ]
        ];

        $produtoValor = (float) ($link['produto_valor'] ?? 0);
        $taxaValor = (float) ($link['taxa_servico_valor'] ?? 0);
        $impostosValor = (float) ($link['impostos_valor'] ?? 0);
        $valorTaxas = round(max(0.0, $taxaValor + $impostosValor), 2);

        $products = [];
        try {
            $pj = (string) ($link['products_json'] ?? '');
            if ($pj !== '') {
                $decoded = json_decode($pj, true);
                if (is_array($decoded)) {
                    $products = $decoded;
                }
            }
        } catch (\Throwable $e) {
            $products = [];
        }

        $paySvc = new PaymentService();

        $resultBlocks = [];
        $resultAttemptIds = [];

        // 1) Produto via Câmbio Real
        if ($produtoValor > 0) {
            $metodoCr = 'pix';
            if ($forma === 'boleto') $metodoCr = 'boleto';
            if ($forma === 'cartao_credito' || $forma === 'cartao_debito') $metodoCr = ($forma === 'cartao_debito') ? 'debit_card' : 'credit_card';

            $attemptProduto = $svc->createPaymentAttempt($linkId, $customer, $metodoCr, 'produto', [
                'produto_valor' => (float) $produtoValor,
                'taxa_servico_valor' => 0.0,
                'impostos_valor' => 0.0,
                'total_valor' => (float) $produtoValor,
            ]);
            if (empty($attemptProduto['success'])) {
                $this->renderNotFound((string) ($attemptProduto['error'] ?? 'Falha ao iniciar pagamento')); return;
            }
            $attemptProdutoId = (int) ($attemptProduto['id'] ?? 0);
            if ($attemptProdutoId > 0) $resultAttemptIds[] = $attemptProdutoId;
            if ($attemptProdutoId > 0 && $formSnapshotJson) {
                $svc->updatePaymentAttempt($attemptProdutoId, [
                    'metadata' => json_encode(['form' => $formSnapshot], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $card = [];
            if ($metodoCr === 'credit_card' || $metodoCr === 'debit_card') {
                $installments = (int) $request->getParam('installments', 1);
                if ($installments < 1) $installments = 1;
                if ($installments > 12) $installments = 12;
                if ($metodoCr === 'debit_card') {
                    $installments = 1;
                }
                $card = [
                    'token' => (string) $request->getParam('cambioreal_card_token', ''),
                    'brand' => (string) $request->getParam('cambioreal_card_brand', ''),
                    'bin' => (string) $request->getParam('cambioreal_card_bin', ''),
                    'dfp_id' => (string) $request->getParam('cambioreal_card_dfp_id', ''),
                    'holder' => (string) $request->getParam('card_holder_name', ''),
                    'installments' => $installments,
                    'type' => ($metodoCr === 'debit_card') ? 'debit' : 'credit',
                ];
            }

            $cr = $paySvc->createCambioRealDirectPaymentForPaymentLink($attemptProdutoId, $produtoValor, $descricao . ' (produtos)', $client, $metodoCr, $card, $products);
            if (empty($cr['success'])) {
                $svc->updatePaymentAttempt($attemptProdutoId, [
                    'status' => 'failed',
                    'gateway' => 'cambioreal',
                    'metadata' => json_encode(['error' => $cr['error'] ?? ''], JSON_UNESCAPED_UNICODE),
                    'raw_response' => json_encode($cr['raw'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
                $this->renderNotFound((string) ($cr['error'] ?? 'Falha ao criar pagamento Câmbio Real')); return;
            }

            $svc->updatePaymentAttempt($attemptProdutoId, array_merge([
                'status' => 'pending',
                'gateway' => 'cambioreal',
                'gateway_payment_id' => (string) ($cr['payment_id'] ?? ''),
                'raw_response' => json_encode($cr['raw'] ?? [], JSON_UNESCAPED_UNICODE),
            ], $cr['persist'] ?? []));

            $resultBlocks[] = $cr;
        }

        // 2) Taxa+Impostos via Câmbio Real Taxas (substituiu AppMax)
        if ($valorTaxas > 0) {
            $billingType = 'BOLETO';
            if ($forma === 'pix') $billingType = 'PIX';
            if ($forma === 'cartao_credito' || $forma === 'cartao_debito') $billingType = 'CREDIT_CARD';

            $attemptTaxa = $svc->createPaymentAttempt($linkId, $customer, strtolower($billingType), 'taxa_servico', [
                'produto_valor' => 0.0,
                'taxa_servico_valor' => (float) $taxaValor,
                'impostos_valor' => (float) $impostosValor,
                'total_valor' => (float) $valorTaxas,
            ]);
            if (empty($attemptTaxa['success'])) {
                $this->renderNotFound((string) ($attemptTaxa['error'] ?? 'Falha ao iniciar pagamento')); return;
            }
            $attemptTaxaId = (int) ($attemptTaxa['id'] ?? 0);
            if ($attemptTaxaId > 0) $resultAttemptIds[] = $attemptTaxaId;
            if ($attemptTaxaId > 0 && $formSnapshotJson) {
                $svc->updatePaymentAttempt($attemptTaxaId, [
                    'metadata' => json_encode(['form' => $formSnapshot], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $descricaoTaxa = $descricao . ' (taxas e impostos)';

            // Montar dados de cartão se necessário
            $cardTaxas = [];
            if ($billingType === 'CREDIT_CARD') {
                $installments = (int) $request->getParam('installments', 1);
                if ($installments < 1) $installments = 1;
                if ($installments > 12) $installments = 12;
                $cardTaxas = [
                    'token' => (string) $request->getParam('cambioreal_card_token', ''),
                    'brand' => (string) $request->getParam('cambioreal_card_brand', ''),
                    'bin' => (string) $request->getParam('cambioreal_card_bin', ''),
                    'dfp_id' => (string) $request->getParam('cambioreal_card_dfp_id', ''),
                    'holder' => (string) $request->getParam('card_holder_name', ''),
                    'installments' => $installments,
                    'type' => 'credit',
                ];
            }

            // Chamar o método correto da Câmbio Real Taxas conforme forma de pagamento
            if ($billingType === 'PIX') {
                $crTaxas = $paySvc->createCambioRealTaxasPixPayment($attemptTaxaId, $valorTaxas, $descricaoTaxa, $client);
            } elseif ($billingType === 'CREDIT_CARD') {
                $crTaxas = $paySvc->createCambioRealTaxasCartaoPayment($attemptTaxaId, $valorTaxas, $descricaoTaxa, $client, $cardTaxas);
            } else {
                $crTaxas = $paySvc->createCambioRealTaxasBoletoPayment($attemptTaxaId, $valorTaxas, $descricaoTaxa, $client);
            }

            if (empty($crTaxas['success'])) {
                $svc->updatePaymentAttempt($attemptTaxaId, [
                    'status' => 'failed',
                    'gateway' => 'cambioreal_taxas',
                    'raw_response' => json_encode($crTaxas['raw'] ?? $crTaxas, JSON_UNESCAPED_UNICODE),
                ]);
                $this->renderNotFound((string) ($crTaxas['error'] ?? 'Falha ao criar pagamento de taxas')); return;
            }

            $pix = (isset($crTaxas['pix']) && is_array($crTaxas['pix'])) ? $crTaxas['pix'] : null;
            $persist = [
                'invoice_url' => (string) ($crTaxas['invoice_url'] ?? ''),
                'bank_slip_url' => (string) ($crTaxas['bank_slip_url'] ?? ''),
                'digitable_line' => (string) ($crTaxas['digitable_line'] ?? ''),
                'pix_payload' => is_array($pix) ? (string) ($pix['payload'] ?? '') : '',
                'pix_encoded_image' => is_array($pix) ? (string) ($pix['encodedImage'] ?? '') : '',
            ];

            $svc->updatePaymentAttempt($attemptTaxaId, array_merge([
                'status' => 'pending',
                'gateway' => 'cambioreal_taxas',
                'gateway_payment_id' => (string) ($crTaxas['payment_id'] ?? ''),
                'raw_response' => json_encode($crTaxas['raw'] ?? [], JSON_UNESCAPED_UNICODE),
            ], $persist));

            // imposto como item lógico (mesmo payment_id)
            if ($impostosValor > 0) {
                $attemptImp = $svc->createPaymentAttempt($linkId, $customer, strtolower($billingType), 'imposto', [
                    'produto_valor' => 0.0,
                    'taxa_servico_valor' => 0.0,
                    'impostos_valor' => (float) $impostosValor,
                    'total_valor' => (float) $impostosValor,
                ]);
                if (!empty($attemptImp['success'])) {
                    $attemptImpId = (int) ($attemptImp['id'] ?? 0);
                    if ($attemptImpId > 0) $resultAttemptIds[] = $attemptImpId;
                    $svc->updatePaymentAttempt($attemptImpId, [
                        'status' => 'pending',
                        'gateway' => 'cambioreal_taxas',
                        'gateway_payment_id' => (string) ($crTaxas['payment_id'] ?? ''),
                        'metadata' => json_encode(['gateway_status' => 'SPLIT_ITEM'], JSON_UNESCAPED_UNICODE),
                    ]);
                    if ($attemptImpId > 0 && $formSnapshotJson) {
                        $svc->updatePaymentAttempt($attemptImpId, [
                            'metadata' => json_encode(['gateway_status' => 'SPLIT_ITEM', 'form' => $formSnapshot], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }

            $resultBlocks[] = ['success' => true, 'gateway' => 'cambioreal_taxas', 'billingType' => $billingType, 'payment_id' => (string) ($crTaxas['payment_id'] ?? ''), 'persist' => $persist, 'raw' => $crTaxas['raw'] ?? []];
        }

        if ($isAjax) {
            $qs = 'ids=' . rawurlencode(implode(',', array_values(array_unique(array_filter($resultAttemptIds)))));
            $this->json([
                'success' => true,
                'redirect' => '/pagar/' . rawurlencode((string) $token) . '/resultado?' . $qs,
            ]);
            return;
        }

        $this->renderResultPage($token, $link, $resultBlocks);
    }

    public function resultado(Request $request) {
        $token = (string) $request->getParam('token', '');
        $svc = new PaymentLinkService();
        $link = $svc->findLinkByToken($token);

        if (!is_array($link) || empty($link['id'])) {
            $this->renderNotFound('Link de pagamento não encontrado');
            return;
        }
        if (!$this->isLinkActive($link)) {
            $this->renderNotFound('Link de pagamento expirado ou desativado');
            return;
        }

        $idsRaw = (string) $request->getParam('ids', '');
        $ids = [];
        foreach (preg_split('/\s*,\s*/', $idsRaw) as $p) {
            $v = (int) $p;
            if ($v > 0) $ids[] = $v;
        }
        $ids = array_values(array_unique($ids));

        $blocks = [];
        foreach ($ids as $id) {
            $row = $svc->findPaymentAttemptById((int) $id);
            if (!is_array($row)) continue;
            if ((int) ($row['payment_link_id'] ?? 0) !== (int) ($link['id'] ?? 0)) continue;

            $persist = [
                'pix_payload' => (string) ($row['pix_payload'] ?? ''),
                'pix_encoded_image' => (string) ($row['pix_encoded_image'] ?? ''),
                'bank_slip_url' => (string) ($row['bank_slip_url'] ?? ''),
                'digitable_line' => (string) ($row['digitable_line'] ?? ''),
                'invoice_url' => (string) ($row['invoice_url'] ?? ''),
            ];

            // Se pix_encoded_image está vazio, tentar extrair do raw_response
            if ($persist['pix_encoded_image'] === '' && !empty($row['raw_response'])) {
                $raw = @json_decode((string) $row['raw_response'], true);
                if (is_array($raw)) {
                    $data = is_array($raw['data'] ?? null) ? (array) $raw['data'] : $raw;
                    $tx   = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];
                    $candidates = [
                        $tx['barcode'] ?? '', $tx['qr_code_base64'] ?? '', $tx['qrCodeBase64'] ?? '',
                        $tx['qrcode_base64'] ?? '', $tx['qr_code'] ?? '', $tx['qrcode'] ?? '',
                        $tx['pix_qrcode_base64'] ?? '', $tx['pix_qrcode'] ?? '',
                        $tx['encodedImage'] ?? '', $tx['encoded_image'] ?? '',
                        $data['barcode'] ?? '', $data['qr_code_base64'] ?? '', $data['qrCodeBase64'] ?? '',
                        $data['qrcode'] ?? '', $data['encodedImage'] ?? '',
                    ];
                    foreach ($candidates as $c) {
                        $c = trim((string) $c);
                        if ($c !== '') {
                            // Remover prefixo data:image se presente
                            $c = preg_replace('#^data:image/[^;]+;base64,#', '', $c);
                            $persist['pix_encoded_image'] = trim($c);
                            break;
                        }
                    }
                }
            }
            $blocks[] = [
                'gateway' => (string) ($row['gateway'] ?? ''),
                'gateway_payment_id' => (string) ($row['gateway_payment_id'] ?? ''),
                'persist' => $persist,
            ];
        }

        $this->renderResultPage($token, $link, $blocks);
    }

    public function comprovante(Request $request) {
        $token = (string) $request->getParam('token', '');
        $svc = new PaymentLinkService();
        $link = $svc->findLinkByToken($token);

        if (!is_array($link) || empty($link['id'])) {
            $this->renderNotFound('Link de pagamento não encontrado');
            return;
        }
        if (!$this->isLinkActive($link)) {
            $this->renderNotFound('Link de pagamento expirado ou desativado');
            return;
        }

        $linkId = (int) ($link['id'] ?? 0);
        $payments = $svc->listPaymentsByLinkId($linkId, 200);

        $descricao = trim((string) ($link['descricao'] ?? 'Pagamento'));
        if ($descricao === '') $descricao = 'Pagamento';
        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        $produtoValor = (float) ($link['produto_valor'] ?? 0);
        $taxaValor = (float) ($link['taxa_servico_valor'] ?? 0);
        $impostosValor = (float) ($link['impostos_valor'] ?? 0);
        $totalValor = (float) ($link['total_valor'] ?? 0);

        $form = null;
        foreach ($payments as $p) {
            $meta = (string) ($p['metadata'] ?? '');
            if ($meta === '') continue;
            $j = json_decode($meta, true);
            if (is_array($j) && isset($j['form']) && is_array($j['form'])) {
                $form = $j['form'];
                break;
            }
        }

        $hasAny = !empty($payments);
        $hasFailure = false;
        $allPaid = $hasAny;
        foreach ($payments as $p) {
            $st = strtolower(trim((string) ($p['status'] ?? '')));
            if ($st === 'failed' || $st === 'canceled' || $st === 'cancelled' || $st === 'error' || $st === 'refused') {
                $hasFailure = true;
            }
            if (!in_array($st, ['paid','approved','success','succeeded','completed'], true)) {
                $allPaid = false;
            }
        }

        $statusLabel = 'Pendente';
        $statusClass = 'warning';
        if ($hasFailure) {
            $statusLabel = 'Pagamento com falha';
            $statusClass = 'danger';
        } elseif ($allPaid) {
            $statusLabel = 'Pagamento aprovado';
            $statusClass = 'success';
        }

        $nome = is_array($form) ? trim((string) ($form['nome'] ?? '')) : '';
        $email = is_array($form) ? trim((string) ($form['email'] ?? '')) : '';
        $doc = is_array($form) ? trim((string) ($form['documento'] ?? '')) : '';
        $tel = is_array($form) ? trim((string) ($form['telefone'] ?? '')) : '';
        $dn = is_array($form) ? trim((string) ($form['data_nascimento'] ?? '')) : '';
        $addr = is_array($form) ? trim((string) ($form['endereco'] ?? '')) : '';
        $num = is_array($form) ? trim((string) ($form['numero'] ?? '')) : '';
        $bairro = is_array($form) ? trim((string) ($form['bairro'] ?? '')) : '';
        $cidade = is_array($form) ? trim((string) ($form['cidade'] ?? '')) : '';
        $estado = is_array($form) ? trim((string) ($form['estado'] ?? ($form['estado_text'] ?? ''))) : '';
        $cep = is_array($form) ? trim((string) ($form['cep'] ?? '')) : '';
        $pais = is_array($form) ? trim((string) ($form['pais'] ?? '')) : '';

        $destNome = is_array($form) ? trim((string) ($form['destinatario_nome'] ?? '')) : '';
        $destDoc = is_array($form) ? trim((string) ($form['destinatario_documento'] ?? '')) : '';
        $destTel = is_array($form) ? trim((string) ($form['destinatario_telefone'] ?? '')) : '';

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Comprovante</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
            . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
            . '<style>@media print{.no-print{display:none!important} body{background:#fff!important} .card{box-shadow:none!important}}</style>'
            . '</head><body style="background:#f6f8fb;">'
            . '<div class="container py-4" style="max-width:980px;">'
            . '<div class="d-flex justify-content-between align-items-center mb-3 no-print">'
            . '<a class="btn btn-outline-secondary" href="/pagar/' . htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') . '/resultado"><i class="fas fa-arrow-left"></i> Voltar</a>'
            . '<button class="btn btn-primary" type="button" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / Salvar em PDF</button>'
            . '</div>'
            . '<div class="card shadow-sm mb-3"><div class="card-body">'
            . '<div class="d-flex justify-content-between align-items-start">'
            . '<div><h3 class="mb-1">' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</h3><div class="text-muted">Comprovante</div></div>'
            . '<div class="text-end"><span class="badge bg-' . htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span><div class="small text-muted mt-1">' . date('d/m/Y H:i') . '</div></div>'
            . '</div>'
            . '</div></div>';

        echo '<div class="card shadow-sm mb-3"><div class="card-body">'
            . '<div class="row g-2">'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Produto</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($produtoValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Taxa</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($taxaValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Impostos</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($impostosValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Total</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($totalValor, 2, ',', '.') . '</strong></div></div>'
            . '</div>'
            . '</div></div>';

        echo '<div class="row g-3">'
            . '<div class="col-md-6"><div class="card shadow-sm"><div class="card-body">'
            . '<div class="fw-bold mb-2">Pagador</div>';

        $pagadorLinha = trim($nome);
        if ($pagadorLinha === '' && $email !== '') $pagadorLinha = $email;
        if ($pagadorLinha === '') $pagadorLinha = '-';

        echo '<div><strong>' . htmlspecialchars($pagadorLinha, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        if ($email !== '') echo '<div class="small text-muted">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($doc !== '') echo '<div class="small">Documento: ' . htmlspecialchars($doc, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($tel !== '') echo '<div class="small">Telefone: ' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($dn !== '') echo '<div class="small">Nascimento: ' . htmlspecialchars($dn, ENT_QUOTES, 'UTF-8') . '</div>';

        echo '</div></div></div>'
            . '<div class="col-md-6"><div class="card shadow-sm"><div class="card-body">'
            . '<div class="fw-bold mb-2">Endereço</div>';

        $addr1 = trim($addr . ($num !== '' ? ', ' . $num : '') . ($bairro !== '' ? ' - ' . $bairro : ''));
        $addr2 = trim($cidade . ($estado !== '' ? '/' . $estado : '') . ($cep !== '' ? ' - ' . $cep : ''));
        $addr3 = trim($pais);
        if ($addr1 === '' && $addr2 === '' && $addr3 === '') {
            echo '<div class="text-muted">-</div>';
        } else {
            if ($addr1 !== '') echo '<div>' . htmlspecialchars($addr1, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($addr2 !== '') echo '<div>' . htmlspecialchars($addr2, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($addr3 !== '') echo '<div>' . htmlspecialchars($addr3, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if ($destNome !== '' || $destDoc !== '' || $destTel !== '') {
            echo '<hr><div class="fw-bold mb-2">Destinatário</div>';
            if ($destNome !== '') echo '<div>' . htmlspecialchars($destNome, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($destDoc !== '') echo '<div class="small">Documento: ' . htmlspecialchars($destDoc, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($destTel !== '') echo '<div class="small">Telefone: ' . htmlspecialchars($destTel, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo '</div></div></div></div>';

        echo '<div class="card shadow-sm mt-3"><div class="card-body">'
            . '<div class="fw-bold mb-2">Pagamentos</div>';

        if (empty($payments)) {
            echo '<div class="text-muted">Nenhum pagamento encontrado.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr>'
                . '<th>ID</th><th>Componente</th><th>Gateway</th><th>Status</th><th>Payment ID</th><th>Criado</th>'
                . '</tr></thead><tbody>';
            foreach ($payments as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $comp = htmlspecialchars((string) ($p['componente'] ?? ''), ENT_QUOTES, 'UTF-8');
                $gw = htmlspecialchars((string) ($p['gateway'] ?? ''), ENT_QUOTES, 'UTF-8');
                $st = htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $gpid = htmlspecialchars((string) ($p['gateway_payment_id'] ?? ''), ENT_QUOTES, 'UTF-8');
                $created = htmlspecialchars((string) ($p['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                echo '<tr><td>' . $pid . '</td><td>' . $comp . '</td><td>' . $gw . '</td><td>' . $st . '</td><td class="text-truncate" style="max-width:240px;">' . ($gpid !== '' ? $gpid : '-') . '</td><td>' . $created . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</div></div>';

        echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
        exit;
    }

    private function isLinkActive(array $link): bool {
        $status = strtolower(trim((string) ($link['status'] ?? 'active')));
        if ($status !== 'active') return false;
        $exp = trim((string) ($link['expires_at'] ?? ''));
        if ($exp !== '') {
            $ts = strtotime($exp);
            if ($ts !== false && $ts < time()) {
                return false;
            }
        }
        return true;
    }

    private function renderNotFound(string $msg): void {
        http_response_code(404);
        try {
            if (!headers_sent()) {
                $m = trim((string) $msg);
                if (strlen($m) > 120) {
                    $m = substr($m, 0, 120);
                }
                header('X-PaymentLink-NotFound: 1');
                header('X-PaymentLink-Reason: ' . $m);
            }
        } catch (\Throwable $e) {
        }
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><title>Pagamento</title></head><body style="background:#f6f8fb;"><div class="container py-5" style="max-width:720px;"><div class="alert alert-danger">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div></div></body></html>';
        exit;
    }

    private function renderResultPage(string $token, array $link, array $blocks): void {
        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        $descricao = trim((string) ($link['descricao'] ?? 'Pagamento'));
        if ($descricao === '') $descricao = 'Pagamento';

        $produtoValor = (float) ($link['produto_valor'] ?? 0);
        $taxaValor = (float) ($link['taxa_servico_valor'] ?? 0);
        $impostosValor = (float) ($link['impostos_valor'] ?? 0);
        $totalValor = (float) ($link['total_valor'] ?? 0);

        // Consolidar blocos duplicados (ex.: cambioreal_taxas taxa + imposto com mesmo payment_id)
        $normalizedBlocks = [];
        foreach ($blocks as $b) {
            $gwKey = strtolower(trim((string) ($b['gateway'] ?? '')));
            $pidKey = trim((string) ($b['gateway_payment_id'] ?? ''));
            $groupKey = $gwKey . '|' . $pidKey;
            if (!isset($normalizedBlocks[$groupKey])) {
                $normalizedBlocks[$groupKey] = $b;
            } else {
                $p1 = is_array($normalizedBlocks[$groupKey]['persist'] ?? null) ? (array) $normalizedBlocks[$groupKey]['persist'] : [];
                $p2 = is_array($b['persist'] ?? null) ? (array) $b['persist'] : [];
                $merged = $p1;
                foreach (['pix_payload','pix_encoded_image','bank_slip_url','digitable_line','invoice_url'] as $k) {
                    if ((string) ($merged[$k] ?? '') === '' && (string) ($p2[$k] ?? '') !== '') {
                        $merged[$k] = (string) $p2[$k];
                    }
                }
                $normalizedBlocks[$groupKey]['persist'] = $merged;
            }
        }
        $blocks = array_values($normalizedBlocks);

        // Fallback: se pix_encoded_image ainda vazio, tentar extrair do raw do bloco
        foreach ($blocks as &$b) {
            $persist = is_array($b['persist'] ?? null) ? (array) $b['persist'] : [];
            if ((string) ($persist['pix_encoded_image'] ?? '') === '' && !empty($b['raw'])) {
                $raw = is_array($b['raw']) ? $b['raw'] : @json_decode((string) $b['raw'], true);
                if (is_array($raw)) {
                    $data = is_array($raw['data'] ?? null) ? (array) $raw['data'] : $raw;
                    $tx   = is_array($data['transaction'] ?? null) ? (array) $data['transaction'] : [];
                    $candidates = [
                        $tx['barcode'] ?? '', $tx['qr_code_base64'] ?? '', $tx['qrCodeBase64'] ?? '',
                        $tx['qrcode_base64'] ?? '', $tx['qr_code'] ?? '', $tx['qrcode'] ?? '',
                        $tx['pix_qrcode_base64'] ?? '', $tx['pix_qrcode'] ?? '',
                        $tx['encodedImage'] ?? '', $tx['encoded_image'] ?? '',
                        $data['barcode'] ?? '', $data['qr_code_base64'] ?? '', $data['qrCodeBase64'] ?? '',
                        $data['qrcode'] ?? '', $data['encodedImage'] ?? '',
                    ];
                    foreach ($candidates as $c) {
                        $c = trim((string) $c);
                        if ($c !== '') {
                            $c = preg_replace('#^data:image/[^;]+;base64,#', '', $c);
                            $persist['pix_encoded_image'] = trim($c);
                            break;
                        }
                    }
                }
                $b['persist'] = $persist;
            }
        }
        unset($b);

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pagamento</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
            . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
            . '</head><body style="background:#f6f8fb;">'
            . '<div class="container py-4" style="max-width:980px;">'
            . '<div class="d-flex justify-content-between align-items-center mb-3">'
            . '<div><h3 class="mb-0">' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</h3><div class="text-muted">Link de pagamento</div></div>'
            . '<a class="btn btn-outline-secondary" href="/pagar/' . htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-arrow-left"></i> Voltar</a>'
            . '</div>';

        echo '<div class="card mb-3 shadow-sm"><div class="card-body">'
            . '<div class="row g-2">'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Produto</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($produtoValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Taxa</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($taxaValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Impostos</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($impostosValor, 2, ',', '.') . '</strong></div></div>'
            . '<div class="col-6 col-md-3"><div class="small text-muted">Total</div><div><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($totalValor, 2, ',', '.') . '</strong></div></div>'
            . '</div>'
            . '</div></div>';

        if (empty($blocks)) {
            echo '<div class="alert alert-warning">Nenhuma cobrança foi gerada.</div>';
        }

        foreach ($blocks as $b) {
            $gw = htmlspecialchars((string) ($b['gateway'] ?? ''), ENT_QUOTES, 'UTF-8');
            $persist = is_array($b['persist'] ?? null) ? (array) $b['persist'] : [];
            $pixPayload = (string) ($persist['pix_payload'] ?? '');
            $pixImg = (string) ($persist['pix_encoded_image'] ?? '');
            $boletoUrl = (string) ($persist['bank_slip_url'] ?? '');
            $digitable = (string) ($persist['digitable_line'] ?? '');
            $invoice = (string) ($persist['invoice_url'] ?? '');

            // Evitar renderizar cards vazios
            if ($pixPayload === '' && $pixImg === '' && $boletoUrl === '' && $digitable === '' && $invoice === '') {
                continue;
            }

            echo '<div class="card mb-3 shadow-sm"><div class="card-body">'
                . '<div class="mb-2"><strong>Gateway:</strong> ' . ($gw !== '' ? $gw : '-') . '</div>';

            if ($pixPayload !== '' || $pixImg !== '') {
                echo '<div class="row g-3">'
                    . '<div class="col-md-6">';
                if ($pixImg !== '') {
                    $pixImgTrim = trim($pixImg);
                    $isSvgRaw = (stripos($pixImgTrim, '<svg') !== false);
                    $isDataUri = (stripos($pixImgTrim, 'data:image') === 0);
                    $isUrl = (stripos($pixImgTrim, 'http://') === 0 || stripos($pixImgTrim, 'https://') === 0);

                    if ($isDataUri || $isUrl) {
                        echo '<div class="border rounded p-3 bg-white text-center"><img alt="PIX" style="max-width: 320px; width:100%;" src="' . htmlspecialchars($pixImgTrim, ENT_QUOTES, 'UTF-8') . '"></div>';
                    } elseif ($isSvgRaw) {
                        $svgB64 = base64_encode($pixImgTrim);
                        echo '<div class="border rounded p-3 bg-white text-center"><img alt="PIX" style="max-width: 320px; width:100%;" src="data:image/svg+xml;base64,' . htmlspecialchars($svgB64, ENT_QUOTES, 'UTF-8') . '"></div>';
                    } else {
                        $isBase64Like = (bool) preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $pixImgTrim);
                        if ($isBase64Like) {
                            echo '<div class="border rounded p-3 bg-white text-center"><img alt="PIX" style="max-width: 320px; width:100%;" src="data:image/png;base64,' . htmlspecialchars($pixImgTrim, ENT_QUOTES, 'UTF-8') . '"></div>';
                        } else {
                            echo '<div class="alert alert-warning small mb-0">QR Code não pôde ser exibido automaticamente. Use o código PIX copia e cola ao lado.</div>';
                        }
                    }
                }
                echo '</div><div class="col-md-6">'
                    . '<div class="mb-2"><strong>PIX copia e cola</strong></div>'
                    . '<textarea class="form-control" rows="5" readonly>' . htmlspecialchars($pixPayload, ENT_QUOTES, 'UTF-8') . '</textarea>'
                    . '</div></div>';
            }

            if ($boletoUrl !== '' || $digitable !== '') {
                if ($boletoUrl !== '') {
                    echo '<div class="mb-2"><a class="btn btn-outline-primary" target="_blank" href="' . htmlspecialchars($boletoUrl, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-file-invoice"></i> Abrir boleto</a></div>';
                }
                if ($digitable !== '') {
                    echo '<div class="mb-2"><strong>Linha digitável</strong><textarea class="form-control" rows="2" readonly>' . htmlspecialchars($digitable, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                }
            }

            if ($invoice !== '' && $boletoUrl === '') {
                echo '<div class="mb-2"><a class="btn btn-outline-secondary" target="_blank" href="' . htmlspecialchars($invoice, ENT_QUOTES, 'UTF-8') . '">Abrir link</a></div>';
            }

            echo '</div></div>';
        }

        echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
        exit;
    }
}
