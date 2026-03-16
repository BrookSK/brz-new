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

        $paySvc = new PaymentService();
        $crAppId = $paySvc->getCambioRealAppId();
        $crAppPublic = $paySvc->getCambioRealAppPublic();
        $crBaseUrl = $paySvc->getCambioRealBaseUrlPublic();

        $crIsSandbox = stripos((string) $crBaseUrl, 'sandbox.cambioreal.com') !== false;

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagamento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body style="background:#f6f8fb;">
<div class="container py-4" style="max-width: 980px;">
  <div class="mb-3">
    <h3 class="mb-0">' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</h3>
    <div class="text-muted">Total: <strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($total, 2, ',', '.') . '</strong></div>
  </div>';

        echo '<div class="row g-4">
            <div class="col-lg-6">
              <div class="card shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Dados do cliente</h5>
                  <form method="POST" action="/pagar/' . htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') . '/processar" id="paylinkForm">
                    <input type="hidden" name="token" value="' . htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') . '">
                    <input type="hidden" name="currency" value="' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '">

                    <div class="row">
                      <div class="col-12 mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" required>
                      </div>
                      <div class="col-12 mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Documento (CPF/CNPJ)</label>
                        <input type="text" class="form-control" name="documento" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="telefone" required>
                      </div>
                    </div>';

        if ($currency === 'BRL') {
            echo '<hr>
                    <h6 class="mb-2">Endereço</h6>
                    <div class="row">
                      <div class="col-md-4 mb-3">
                        <label class="form-label">CEP</label>
                        <input type="text" class="form-control" name="cep" required>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label">Estado</label>
                        <input type="text" class="form-control" name="estado" required>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label">Cidade</label>
                        <input type="text" class="form-control" name="cidade" required>
                      </div>
                      <div class="col-md-8 mb-3">
                        <label class="form-label">Endereço</label>
                        <input type="text" class="form-control" name="endereco" required>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label">Número</label>
                        <input type="text" class="form-control" name="numero" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Bairro</label>
                        <input type="text" class="form-control" name="bairro" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Data de nascimento</label>
                        <input type="date" class="form-control" name="data_nascimento">
                      </div>
                    </div>';
        }

        echo '<hr>
                    <h5 class="card-title">Pagamento</h5>';

        if ($currency === 'USD') {
            echo '<div class="alert alert-info">Pagamento em USD é realizado via <strong>Stripe (cartão)</strong>.</div>
                    <input type="hidden" name="forma_pagamento" value="cartao_credito">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-credit-card"></i> Pagar com cartão</button>';
        } else {
            echo '<div class="mb-3">
                      <label class="form-label">Método</label>
                      <select class="form-select" name="forma_pagamento" id="forma_pagamento" required>
                        <option value="pix">PIX</option>
                        <option value="boleto">Boleto</option>
                        <option value="cartao_credito">Cartão de crédito</option>
                        <option value="cartao_debito">Cartão de débito</option>
                      </select>
                    </div>

                    <div id="cardBox" style="display:none;">
                      <div class="row">
                        <div class="col-12 mb-3">
                          <label class="form-label">Nome no cartão</label>
                          <input type="text" class="form-control" name="card_holder_name" id="card_holder_name">
                        </div>
                        <div class="col-md-8 mb-3">
                          <label class="form-label">Número do cartão</label>
                          <input type="text" class="form-control" name="card_number" id="card_number" autocomplete="off">
                        </div>
                        <div class="col-md-4 mb-3">
                          <label class="form-label">CVV</label>
                          <input type="text" class="form-control" name="card_cvv" id="card_cvv" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label class="form-label">Mês</label>
                          <input type="text" class="form-control" name="card_expiry_month" id="card_expiry_month" placeholder="MM">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label class="form-label">Ano</label>
                          <input type="text" class="form-control" name="card_expiry_year" id="card_expiry_year" placeholder="AAAA">
                        </div>
                      </div>
                      <input type="hidden" name="cambioreal_card_type" id="cambioreal_card_type" value="credit">
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="btnPagar"><i class="fas fa-lock"></i> Gerar pagamento</button>';
        }

        echo '</form>
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="card shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Resumo</h5>
                  <div class="d-flex justify-content-between"><span>Produto</span><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($produto, 2, ',', '.') . '</strong></div>
                  <div class="d-flex justify-content-between"><span>Taxa de serviço</span><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($taxa, 2, ',', '.') . '</strong></div>
                  <div class="d-flex justify-content-between"><span>Impostos</span><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($impostos, 2, ',', '.') . '</strong></div>
                  <hr>
                  <div class="d-flex justify-content-between"><span>Total</span><strong>' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . ' ' . number_format($total, 2, ',', '.') . '</strong></div>

                  <div class="mt-3 text-muted small">Após enviar, você verá as instruções de pagamento (PIX/boletos/links) nesta página.</div>
                </div>
              </div>
            </div>
        </div>';

        echo '</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';

        if ($currency === 'BRL') {
            echo '<script>
                window.CAMBIOREAL_DIRECT = { appId: ' . json_encode((string) $crAppId, JSON_UNESCAPED_UNICODE) . ', appPublic: ' . json_encode((string) $crAppPublic, JSON_UNESCAPED_UNICODE) . ', baseUrl: ' . json_encode((string) $crBaseUrl, JSON_UNESCAPED_UNICODE) . ' };
            </script>';
            $scriptSrc = $crIsSandbox ? 'https://sandbox.cambioreal.com/js/card-hash.js' : 'https://www.cambioreal.com/js/card-hash.js';
            echo '<script src="' . htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8') . '"></script>';
            echo '<script>
                (function(){
                    const sel = document.getElementById("forma_pagamento");
                    const cardBox = document.getElementById("cardBox");
                    const typeInp = document.getElementById("cambioreal_card_type");
                    if (sel) {
                        const refresh = function(){
                            const v = String(sel.value || "");
                            const isCard = (v === "cartao_credito" || v === "cartao_debito");
                            if (cardBox) cardBox.style.display = isCard ? "block" : "none";
                            if (typeInp) typeInp.value = (v === "cartao_debito") ? "debit" : "credit";
                        };
                        sel.addEventListener("change", refresh);
                        refresh();
                    }

                    async function attachCambioRealCardHash(fd){
                        const v = String(sel ? sel.value : "");
                        if (!(v === "cartao_credito" || v === "cartao_debito")) return;

                        const cfg = (window.CAMBIOREAL_DIRECT || {});
                        const appId = String(cfg.appId || "").trim();
                        const appPublic = String(cfg.appPublic || "").trim();
                        const baseUrl = String(cfg.baseUrl || "").trim();
                        const sandbox = baseUrl.indexOf("sandbox.cambioreal.com") !== -1;
                        if (!appId || !appPublic || typeof CardHash !== "function") return;

                        const number = String((document.getElementById("card_number") && document.getElementById("card_number").value) ? document.getElementById("card_number").value : "").replace(/\D/g, "");
                        const holder = String((document.getElementById("card_holder_name") && document.getElementById("card_holder_name").value) ? document.getElementById("card_holder_name").value : "").trim();
                        const cvv = String((document.getElementById("card_cvv") && document.getElementById("card_cvv").value) ? document.getElementById("card_cvv").value : "").replace(/\D/g, "");
                        const mm = String((document.getElementById("card_expiry_month") && document.getElementById("card_expiry_month").value) ? document.getElementById("card_expiry_month").value : "").replace(/\D/g, "");
                        const yyyy = String((document.getElementById("card_expiry_year") && document.getElementById("card_expiry_year").value) ? document.getElementById("card_expiry_year").value : "").replace(/\D/g, "");
                        if (!number || !holder || !cvv || !mm || !yyyy) return;

                        const dfpId = "PAYLINK_" + String(Date.now());
                        const cardHashLib = new CardHash(appId, appPublic, dfpId, sandbox);
                        const expYY = yyyy.length === 4 ? yyyy.substring(2) : yyyy;
                        const expiration = (mm.padStart(2, "0") + expYY.padStart(2, "0")).replace(/\D/g, "");

                        const token = await cardHashLib.generateCardHash({ card_number: number, card_holder: holder, card_expiration: expiration, card_cvv: cvv });
                        const brand = String(cardHashLib.detectBrand(number) || "").trim();
                        const bin = String(number.substring(0, 6) || "").replace(/\D/g, "");
                        if (!token || !brand || !bin) return;

                        fd.set("cambioreal_card_token", token);
                        fd.set("cambioreal_card_brand", brand);
                        fd.set("cambioreal_card_bin", bin);
                        fd.set("cambioreal_card_dfp_id", dfpId);
                    }

                    const form = document.getElementById("paylinkForm");
                    if (form) {
                        form.addEventListener("submit", async function(e){
                            try {
                                const v = String(sel ? sel.value : "");
                                if (v === "cartao_credito" || v === "cartao_debito") {
                                    e.preventDefault();
                                    const fd = new FormData(form);
                                    await attachCambioRealCardHash(fd);
                                    fetch(form.action, { method: "POST", body: fd }).then(r => r.text()).then(html => { document.open(); document.write(html); document.close(); });
                                }
                            } catch (err) {
                            }
                        }, { capture: true });
                    }
                })();
            </script>';
        }

        echo '</body></html>';
        exit;
    }

    public function processar(Request $request) {
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

        $nome = trim((string) $request->getParam('nome', ''));
        $email = trim((string) $request->getParam('email', ''));
        $documento = trim((string) $request->getParam('documento', ''));
        $telefone = trim((string) $request->getParam('telefone', ''));

        $forma = (string) $request->getParam('forma_pagamento', '');
        $forma = strtolower(trim($forma));

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
            $attempt = $svc->createPaymentAttempt($linkId, $customer, 'credit_card', 'pagamento');
            if (empty($attempt['success'])) {
                $this->renderNotFound((string) ($attempt['error'] ?? 'Falha ao iniciar pagamento'));
                return;
            }

            $paymentAttemptId = (int) ($attempt['id'] ?? 0);
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

            header('Location: ' . (string) ($session['url'] ?? '/pagar/' . rawurlencode((string) $token)));
            exit;
        }

        // BRL -> Câmbio Real + AppMax
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
        $valorAppmax = round(max(0.0, $taxaValor + $impostosValor), 2);

        $paySvc = new PaymentService();

        $resultBlocks = [];

        // 1) Produto via Câmbio Real
        if ($produtoValor > 0) {
            $metodoCr = 'pix';
            if ($forma === 'boleto') $metodoCr = 'boleto';
            if ($forma === 'cartao_credito' || $forma === 'cartao_debito') $metodoCr = ($forma === 'cartao_debito') ? 'debit_card' : 'credit_card';

            $attemptProduto = $svc->createPaymentAttempt($linkId, $customer, $metodoCr, 'produto');
            if (empty($attemptProduto['success'])) {
                $this->renderNotFound((string) ($attemptProduto['error'] ?? 'Falha ao iniciar pagamento')); return;
            }
            $attemptProdutoId = (int) ($attemptProduto['id'] ?? 0);

            $card = [];
            if ($metodoCr === 'credit_card' || $metodoCr === 'debit_card') {
                $card = [
                    'token' => (string) $request->getParam('cambioreal_card_token', ''),
                    'brand' => (string) $request->getParam('cambioreal_card_brand', ''),
                    'bin' => (string) $request->getParam('cambioreal_card_bin', ''),
                    'dfp_id' => (string) $request->getParam('cambioreal_card_dfp_id', ''),
                    'holder' => (string) $request->getParam('card_holder_name', ''),
                    'installments' => 1,
                    'type' => ($metodoCr === 'debit_card') ? 'debit' : 'credit',
                ];
            }

            $cr = $paySvc->createCambioRealDirectPaymentForPaymentLink($attemptProdutoId, $produtoValor, $descricao . ' (produtos)', $client, $metodoCr, $card);
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

        // 2) Taxa+Impostos via AppMax
        if ($valorAppmax > 0) {
            $billingType = 'BOLETO';
            if ($forma === 'pix') $billingType = 'PIX';
            if ($forma === 'cartao_credito' || $forma === 'cartao_debito') $billingType = 'CREDIT_CARD';

            $attemptTaxa = $svc->createPaymentAttempt($linkId, $customer, strtolower($billingType), 'taxa_servico');
            if (empty($attemptTaxa['success'])) {
                $this->renderNotFound((string) ($attemptTaxa['error'] ?? 'Falha ao iniciar pagamento')); return;
            }
            $attemptTaxaId = (int) ($attemptTaxa['id'] ?? 0);

            $productsValueCents = (int) round($valorAppmax * 100);
            $descricaoTaxa = $descricao . ' (taxas e impostos)';
            $products = [[
                'sku' => 'PAYLINK_TAXA_' . (string) $attemptTaxaId,
                'name' => $descricaoTaxa,
                'quantity' => 1,
                'unit_value' => $productsValueCents,
                'type' => 'service',
                'freight_type' => 'normal',
            ]];

            $dadosPagamento = [
                'billingType' => $billingType,
                'customer_name' => $nome,
                'customer_email' => $email,
                'customer_phone' => $telefone,
                'customer_document' => $documento,
                'externalReference' => 'PAYLINK_' . (string) $attemptTaxaId,
                'products' => $products,
                'products_value_cents' => $productsValueCents,
                'shipping_value_cents' => 0,
                'discount_value_cents' => 0,
            ];

            if ($billingType === 'CREDIT_CARD') {
                $dadosPagamento['card_holder_name'] = (string) $request->getParam('card_holder_name', '');
                $dadosPagamento['card_number'] = (string) $request->getParam('card_number', '');
                $dadosPagamento['card_expiry_month'] = (string) $request->getParam('card_expiry_month', '');
                $dadosPagamento['card_expiry_year'] = (string) $request->getParam('card_expiry_year', '');
                $dadosPagamento['card_cvv'] = (string) $request->getParam('card_cvv', '');
            }

            $appmax = $paySvc->processarPagamento($dadosPagamento, $valorAppmax, 'BRL', $descricaoTaxa);
            if (empty($appmax['payment_id'])) {
                $svc->updatePaymentAttempt($attemptTaxaId, [
                    'status' => 'failed',
                    'gateway' => 'appmax',
                    'raw_response' => json_encode($appmax, JSON_UNESCAPED_UNICODE),
                ]);
                $this->renderNotFound('Falha ao criar pagamento AppMax'); return;
            }

            $pix = (isset($appmax['pix']) && is_array($appmax['pix'])) ? $appmax['pix'] : null;
            $persist = [
                'invoice_url' => (string) ($appmax['invoiceUrl'] ?? ''),
                'bank_slip_url' => (string) ($appmax['bankSlipUrl'] ?? ''),
                'digitable_line' => (string) ($appmax['digitableLine'] ?? ''),
                'pix_payload' => is_array($pix) ? (string) ($pix['payload'] ?? '') : '',
                'pix_encoded_image' => is_array($pix) ? (string) ($pix['encodedImage'] ?? '') : '',
            ];

            $svc->updatePaymentAttempt($attemptTaxaId, array_merge([
                'status' => (string) ($appmax['status'] ?? 'pending'),
                'gateway' => 'appmax',
                'gateway_payment_id' => (string) ($appmax['payment_id'] ?? ''),
                'raw_response' => json_encode($appmax, JSON_UNESCAPED_UNICODE),
            ], $persist));

            // imposto como item lógico (mesmo payment_id)
            if ($impostosValor > 0) {
                $attemptImp = $svc->createPaymentAttempt($linkId, $customer, strtolower($billingType), 'imposto');
                if (!empty($attemptImp['success'])) {
                    $attemptImpId = (int) ($attemptImp['id'] ?? 0);
                    $svc->updatePaymentAttempt($attemptImpId, [
                        'status' => (string) ($appmax['status'] ?? 'pending'),
                        'gateway' => 'appmax',
                        'gateway_payment_id' => (string) ($appmax['payment_id'] ?? ''),
                        'metadata' => json_encode(['gateway_status' => 'SPLIT_ITEM'], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            $resultBlocks[] = ['success' => true, 'gateway' => 'appmax', 'billingType' => $billingType, 'payment_id' => (string) ($appmax['payment_id'] ?? ''), 'persist' => $persist, 'raw' => $appmax];
        }

        $this->renderResultPage($token, $link, $resultBlocks);
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
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><title>Pagamento</title></head><body style="background:#f6f8fb;"><div class="container py-5" style="max-width:720px;"><div class="alert alert-danger">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div></div></body></html>';
        exit;
    }

    private function renderResultPage(string $token, array $link, array $blocks): void {
        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        $descricao = trim((string) ($link['descricao'] ?? 'Pagamento'));
        if ($descricao === '') $descricao = 'Pagamento';

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pagamento</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
            . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
            . '</head><body style="background:#f6f8fb;">'
            . '<div class="container py-4" style="max-width:980px;">'
            . '<div class="d-flex justify-content-between align-items-center mb-3">'
            . '<div><h3 class="mb-0">' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</h3><div class="text-muted">Link de pagamento</div></div>'
            . '<a class="btn btn-outline-secondary" href="/pagar/' . htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-arrow-left"></i> Voltar</a>'
            . '</div>';

        if (empty($blocks)) {
            echo '<div class="alert alert-warning">Nenhuma cobrança foi gerada.</div>';
        }

        foreach ($blocks as $b) {
            $gw = htmlspecialchars((string) ($b['gateway'] ?? ''), ENT_QUOTES, 'UTF-8');
            echo '<div class="card mb-3 shadow-sm"><div class="card-body">'
                . '<div class="mb-2"><strong>Gateway:</strong> ' . ($gw !== '' ? $gw : '-') . '</div>';

            $persist = is_array($b['persist'] ?? null) ? (array) $b['persist'] : [];
            $pixPayload = (string) ($persist['pix_payload'] ?? '');
            $pixImg = (string) ($persist['pix_encoded_image'] ?? '');
            $boletoUrl = (string) ($persist['bank_slip_url'] ?? '');
            $digitable = (string) ($persist['digitable_line'] ?? '');
            $invoice = (string) ($persist['invoice_url'] ?? '');

            if ($pixPayload !== '' || $pixImg !== '') {
                echo '<div class="row g-3">'
                    . '<div class="col-md-6">';
                if ($pixImg !== '') {
                    $mime = (stripos($pixImg, '<svg') !== false) ? 'image/svg+xml' : 'image/png';
                    echo '<div class="border rounded p-3 bg-white text-center"><img alt="PIX" style="max-width: 320px; width:100%;" src="data:' . $mime . ';base64,' . htmlspecialchars($pixImg, ENT_QUOTES, 'UTF-8') . '"></div>';
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
