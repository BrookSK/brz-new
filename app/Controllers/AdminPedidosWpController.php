<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\WExpressService;
use App\Services\WooCommerceService;
use Config\Database;

class AdminPedidosWpController extends Controller {

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $busca = trim((string) ($request->getParam('busca') ?? ''));
        $page = (int) ($request->getParam('page') ?? 1);
        if ($page <= 0) $page = 1;
        $limite = (int) ($request->getParam('limit') ?? 50);
        if ($limite <= 0) $limite = 50;
        if ($limite > 200) $limite = 200;
        $offset = ($page - 1) * $limite;

        $pedidos = [];
        $total = 0;
        $erro = '';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo);

            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $where = ["p.post_type = 'shop_order'", "p.post_status <> 'trash'"];
            $params = [];

            if ($busca !== '') {
                $where[] = '(CAST(p.ID AS CHAR) LIKE :busca OR p.post_title LIKE :busca OR pm_mail.meta_value LIKE :busca OR CONCAT(COALESCE(pm_fn.meta_value,\'\'),\' \',COALESCE(pm_ln.meta_value,\'\')) LIKE :busca)';
                $params[':busca'] = '%' . $busca . '%';
            }

            $sql = "SELECT
                p.ID AS id,
                p.post_date AS created_at,
                p.post_status AS status,
                p.post_title AS numero_pedido,
                pm_total.meta_value AS order_total,
                pm_curr.meta_value AS currency,
                pm_mail.meta_value AS billing_email,
                pm_fn.meta_value AS billing_first_name,
                pm_ln.meta_value AS billing_last_name
            FROM {$prefix}posts p
            LEFT JOIN {$prefix}postmeta pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$prefix}postmeta pm_curr ON pm_curr.post_id = p.ID AND pm_curr.meta_key = '_order_currency'
            LEFT JOIN {$prefix}postmeta pm_mail ON pm_mail.post_id = p.ID AND pm_mail.meta_key = '_billing_email'
            LEFT JOIN {$prefix}postmeta pm_fn ON pm_fn.post_id = p.ID AND pm_fn.meta_key = '_billing_first_name'
            LEFT JOIN {$prefix}postmeta pm_ln ON pm_ln.post_id = p.ID AND pm_ln.meta_key = '_billing_last_name'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.post_date DESC
            LIMIT {$limite} OFFSET {$offset}";

            $st = $wpPdo->prepare($sql);
            foreach ($params as $k => $v) $st->bindValue($k, $v);
            $st->execute();
            $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sqlCount = "SELECT COUNT(*)
            FROM {$prefix}posts p
            LEFT JOIN {$prefix}postmeta pm_mail ON pm_mail.post_id = p.ID AND pm_mail.meta_key = '_billing_email'
            LEFT JOIN {$prefix}postmeta pm_fn ON pm_fn.post_id = p.ID AND pm_fn.meta_key = '_billing_first_name'
            LEFT JOIN {$prefix}postmeta pm_ln ON pm_ln.post_id = p.ID AND pm_ln.meta_key = '_billing_last_name'
            WHERE " . implode(' AND ', $where);

            $stC = $wpPdo->prepare($sqlCount);
            foreach ($params as $k => $v) $stC->bindValue($k, $v);
            $stC->execute();
            $total = (int) ($stC->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $erro = $e->getMessage();
            $pedidos = [];
            $total = 0;
        }

        $sidebarActive = 'pedidos-wp';
        $title = 'Pedidos (WordPress) - Braziliana Shop Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function gerarEtiquetaWexpress(Request $request, int $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->json(['success' => false, 'error' => 'Pedido inválido'], 400);
            return;
        }

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo);
            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $stP = $wpPdo->prepare("SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1");
            $stP->execute([$orderId]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$pedido) {
                $this->json(['success' => false, 'error' => 'Pedido não encontrado no WordPress'], 404);
                return;
            }

            $stM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
            $stM->execute([$orderId]);
            $rowsMeta = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $meta = [];
            foreach ($rowsMeta as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') continue;
                $meta[$k] = $r['meta_value'] ?? '';
            }

            $currency = strtoupper(trim((string) ($meta['_order_currency'] ?? 'USD')));
            if ($currency === '') $currency = 'USD';

            $billingCpf = (string) ($meta['_billing_cpf'] ?? ($meta['_billing_cnpj'] ?? ''));
            $billingDocDigits = (string) preg_replace('/\D+/', '', $billingCpf);
            if ($billingDocDigits === '') {
                $this->json(['success' => false, 'error' => 'Documento (CPF/CNPJ) não encontrado no pedido. Preencha o campo de CPF/CNPJ no WooCommerce antes de gerar a etiqueta.'], 400);
                return;
            }

            $nome = trim((string) (($meta['_shipping_first_name'] ?? '') . ' ' . ($meta['_shipping_last_name'] ?? '')));
            if ($nome === '') {
                $nome = trim((string) (($meta['_billing_first_name'] ?? '') . ' ' . ($meta['_billing_last_name'] ?? '')));
            }
            if ($nome === '') {
                $nome = 'Cliente';
            }
            $partes = preg_split('/\s+/', $nome) ?: [];
            $firstName = $partes[0] ?? $nome;
            $lastName = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';

            $shipAddress1 = trim((string) ($meta['_shipping_address_1'] ?? ''));
            $shipAddress2 = trim((string) ($meta['_shipping_address_2'] ?? ''));
            $shipNumber = trim((string) ($meta['_shipping_number'] ?? ($meta['shipping_number'] ?? '')));
            $shipCity = trim((string) ($meta['_shipping_city'] ?? ''));
            $shipState = trim((string) ($meta['_shipping_state'] ?? ''));
            $shipPostcode = preg_replace('/\D+/', '', (string) ($meta['_shipping_postcode'] ?? ''));
            $shipSuite = trim((string) ($meta['suite'] ?? ($meta['_shipping_suite'] ?? ($meta['shipping_suite'] ?? ''))));
            $shipNeighborhood = trim((string) ($meta['_shipping_neighborhood'] ?? ($meta['_shipping_bairro'] ?? ($meta['shipping_bairro'] ?? ''))));

            $addr2Parts = [];
            if ($shipAddress2 !== '') $addr2Parts[] = $shipAddress2;
            if ($shipSuite !== '') $addr2Parts[] = $shipSuite;
            if ($shipNeighborhood !== '') $addr2Parts[] = $shipNeighborhood;
            $addr2 = trim(implode(', ', $addr2Parts));

            if ($shipAddress1 === '' || $shipCity === '' || $shipState === '' || $shipPostcode === '') {
                $this->json(['success' => false, 'error' => 'Endereço de entrega incompleto no pedido (shipping). Corrija no WooCommerce antes de gerar a etiqueta.'], 400);
                return;
            }

            $email = (string) ($meta['_billing_email'] ?? '');
            $phone = (string) ($meta['_billing_phone'] ?? '');

            $stI = $wpPdo->prepare("SELECT order_item_id, order_item_name, order_item_type FROM {$prefix}woocommerce_order_items WHERE order_id = ? ORDER BY order_item_id ASC");
            $stI->execute([$orderId]);
            $orderItems = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stIM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}woocommerce_order_itemmeta WHERE order_item_id = ?");

            $items = [];
            $pesoTotalKg = 0.0;
            $usdToBrl = $this->getUsdToBrlRate($localPdo);
            $brlToUsd = ($usdToBrl > 0.000001) ? (1.0 / $usdToBrl) : 1.0;

            foreach ($orderItems as $oi) {
                if (strtolower((string) ($oi['order_item_type'] ?? '')) !== 'line_item') {
                    continue;
                }

                $itemId = (int) ($oi['order_item_id'] ?? 0);
                if ($itemId <= 0) continue;

                $stIM->execute([$itemId]);
                $metaItemRows = $stIM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $m = [];
                foreach ($metaItemRows as $mr) {
                    $k = (string) ($mr['meta_key'] ?? '');
                    if ($k === '') continue;
                    $m[$k] = $mr['meta_value'] ?? '';
                }

                $produtoId = (int) ($m['_product_id'] ?? 0);
                $variacaoId = (int) ($m['_variation_id'] ?? 0);
                $qtd = (int) ($m['_qty'] ?? 0);
                if ($qtd <= 0) $qtd = 1;

                $lineTotal = is_numeric($m['_line_total'] ?? null) ? (float) $m['_line_total'] : 0.0;
                $unit = $qtd > 0 ? round($lineTotal / $qtd, 2) : 0.0;
                if ($unit <= 0) $unit = 1.0;
                if ($currency === 'BRL') {
                    $unit = $unit * $brlToUsd;
                }

                $desc = trim((string) ($oi['order_item_name'] ?? 'item'));
                if ($desc === '') $desc = 'item';

                $ncm = '';
                $ncmCandidatesItem = ['_ncm', 'ncm', 'tariff_code', '_tariff_code', 'invoice_ncm', '_invoice_ncm', 'ncm_code', '_ncm_code'];
                foreach ($ncmCandidatesItem as $nk) {
                    $val = trim((string) ($m[$nk] ?? ''));
                    if ($val !== '') {
                        $ncm = $val;
                        break;
                    }
                }
                if ($ncm === '') {
                    $prodLookupId = $variacaoId > 0 ? $variacaoId : $produtoId;
                    if ($prodLookupId > 0) {
                        $ncmKeys = ['_ncm', 'ncm', '_woo_ncm', '_product_ncm', '_ncm_code'];
                        foreach ($ncmKeys as $nk) {
                            $stN = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1");
                            $stN->execute([(int) $prodLookupId, $nk]);
                            $val = (string) ($stN->fetchColumn() ?: '');
                            if (trim($val) !== '') {
                                $ncm = $val;
                                break;
                            }
                        }
                    }
                }

                $ncmDigits = preg_replace('/\D+/', '', (string) $ncm);
                if ($ncmDigits === '') {
                    $this->json(['success' => false, 'error' => 'NCM não encontrado para um ou mais itens do pedido. Cadastre o NCM nos produtos (ou no Invoice) antes de gerar a etiqueta.'], 400);
                    return;
                }

                $pesoKg = null;
                $weightCandidatesItem = ['peso', '_peso', 'weight', '_weight', 'peso_kg', '_peso_kg', 'invoice_weight', '_invoice_weight', 'invoice_peso', '_invoice_peso'];
                foreach ($weightCandidatesItem as $wk) {
                    $v = str_replace(',', '.', (string) ($m[$wk] ?? ''));
                    if (is_numeric($v) && (float) $v > 0) {
                        $pesoKg = (float) $v;
                        break;
                    }
                }
                if ($pesoKg === null) {
                    $prodLookupId = $variacaoId > 0 ? $variacaoId : $produtoId;
                    if ($prodLookupId > 0) {
                        $stW = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_weight' LIMIT 1");
                        $stW->execute([(int) $prodLookupId]);
                        $w = str_replace(',', '.', (string) ($stW->fetchColumn() ?: ''));
                        if (is_numeric($w) && (float) $w > 0) {
                            $pesoKg = (float) $w;
                        }
                    }
                }

                if ($pesoKg !== null && $pesoKg > 0) {
                    $pesoTotalKg += ($pesoKg * $qtd);
                }

                $items[] = [
                    'description' => $desc,
                    'quantity' => $qtd,
                    'unit_value' => round((float) $unit, 2),
                    'tariff_code' => (int) $ncmDigits,
                ];
            }

            if (empty($items)) {
                $this->json(['success' => false, 'error' => 'Sem itens no pedido'], 400);
                return;
            }

            if ($pesoTotalKg <= 0) {
                $pesoTotalKg = 1.0;
            }

            $svcWx = new WExpressService();
            $sender = $svcWx->getSender();
            if (!is_array($sender) || empty($sender)) {
                $err = $svcWx->getSenderJsonError();
                $this->json(['success' => false, 'error' => 'W-Express: configure o Sender (JSON) em Admin > Configurações > Entrega' . ($err ? (': ' . $err) : '')], 400);
                return;
            }

            $taxIdType = strlen($billingDocDigits) > 11 ? 'CNPJ' : 'CPF';
            $recipientType = ($taxIdType === 'CPF') ? 'individual' : 'business';

            $declared = is_numeric($meta['_order_total'] ?? null) ? (float) $meta['_order_total'] : 0.0;
            if ($declared <= 0) $declared = 1.0;
            if ($currency === 'BRL') {
                $declared = $declared * $brlToUsd;
            }

            $externalShippingId = (string) $orderId;
            $payload = [
                'shipment_purpose' => 'personal',
                'external_shipping_id' => $externalShippingId,
                'external_shipping_reference' => 'woo',
                'service_code' => $svcWx->getServiceCode(),
                'incoterms' => 'DDU',
                'dimensions_unit' => 'cm',
                'weight_unit' => 'g',
                'currency' => 'USD',
                'declared_value' => round((float) $declared, 2),
                'freight_value' => 0.01,
                'insurance_value' => 0,
                'invoice_number' => (string) $orderId,
                'packages' => [[
                    'weight' => round($pesoTotalKg * 1000, 2),
                    'width' => 10,
                    'length' => 15,
                    'height' => 10,
                ]],
                'sender' => $sender,
                'recipient' => [
                    'type' => $recipientType,
                    'business_name' => $recipientType === 'business' ? $nome : ' ',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'tax_id_type' => $taxIdType,
                    'tax_id' => $billingDocDigits,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => [
                        'address_number' => $shipNumber,
                        'address_line_1' => $shipAddress1,
                        'address_line_2' => $addr2,
                        'postal_code' => $shipPostcode,
                        'city' => $shipCity,
                        'state' => $shipState,
                        'country' => 'BR',
                    ],
                ],
                'items' => $items,
            ];

            $resp = $svcWx->createShipping($payload);

            $wxStatus = is_array($resp) ? (string) ($resp['shipping_status'] ?? '') : '';
            $wxShipId = is_array($resp) ? (string) ($resp['shipping_id'] ?? '') : '';
            $wxTrack = is_array($resp) ? (string) ($resp['wexpress_tracking_number'] ?? '') : '';
            $wxCourier = is_array($resp) ? (string) ($resp['courier_tracking_number'] ?? '') : '';

            if (trim($wxShipId) === '') {
                $this->json(['success' => false, 'error' => 'W-Express não retornou shipping_id', 'response' => $resp], 400);
                return;
            }

            $labelUrl = 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId);

            try {
                $this->savePedidoMeta($localPdo, $orderId, 'wp_wexpress_shipping_id', $wxShipId);
                $this->savePedidoMeta($localPdo, $orderId, 'wp_wexpress_label_url', $labelUrl);
                $this->savePedidoMeta($localPdo, $orderId, 'wp_wexpress_status', $wxStatus);
                if ($wxTrack !== '') $this->savePedidoMeta($localPdo, $orderId, 'wp_wexpress_tracking_number', $wxTrack);
                if ($wxCourier !== '') $this->savePedidoMeta($localPdo, $orderId, 'wp_courier_tracking_number', $wxCourier);
            } catch (\Throwable $e) {
            }

            $woo = new WooCommerceService();
            $woo->updateOrderMeta($orderId, [
                'wexpress_shipping_id' => $wxShipId,
                'wexpress_label_url' => $labelUrl,
                'wexpress_status' => $wxStatus,
                'wexpress_tracking_number' => $wxTrack,
                'courier_tracking_number' => $wxCourier,
            ]);

            $this->json([
                'success' => true,
                'shipping_id' => $wxShipId,
                'wexpress_status' => $wxStatus,
                'wexpress_tracking_number' => $wxTrack,
                'courier_tracking_number' => $wxCourier,
                'label_url' => $labelUrl,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function detalhes(Request $request, int $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $pedido = null;
        $meta = [];
        $itens = [];
        $erro = '';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo);

            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $stP = $wpPdo->prepare("SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1");
            $stP->execute([(int) $id]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$pedido) {
                throw new \RuntimeException('Pedido não encontrado no WordPress');
            }

            $stM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
            $stM->execute([(int) $id]);
            $rowsMeta = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rowsMeta as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') continue;
                $meta[$k] = $r['meta_value'] ?? '';
            }

            $stI = $wpPdo->prepare("SELECT order_item_id, order_item_name, order_item_type FROM {$prefix}woocommerce_order_items WHERE order_id = ? ORDER BY order_item_id ASC");
            $stI->execute([(int) $id]);
            $orderItems = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stIM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}woocommerce_order_itemmeta WHERE order_item_id = ?");

            foreach ($orderItems as $oi) {
                if (strtolower((string) ($oi['order_item_type'] ?? '')) !== 'line_item') {
                    continue;
                }

                $itemId = (int) ($oi['order_item_id'] ?? 0);
                if ($itemId <= 0) continue;

                $stIM->execute([$itemId]);
                $metaItemRows = $stIM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $m = [];
                foreach ($metaItemRows as $mr) {
                    $k = (string) ($mr['meta_key'] ?? '');
                    if ($k === '') continue;
                    $m[$k] = $mr['meta_value'] ?? '';
                }

                $produtoId = (int) ($m['_product_id'] ?? 0);
                $variacaoId = (int) ($m['_variation_id'] ?? 0);
                $qtd = (int) ($m['_qty'] ?? 0);
                $lineTotal = (float) ($m['_line_total'] ?? 0);
                $lineSubtotal = (float) ($m['_line_subtotal'] ?? 0);

                $unit = 0.0;
                if ($qtd > 0) {
                    $unit = round($lineTotal / $qtd, 2);
                }

                $sku = '';
                $ncm = '';

                $prodLookupId = $variacaoId > 0 ? $variacaoId : $produtoId;
                if ($prodLookupId > 0) {
                    $stSku = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_sku' LIMIT 1");
                    $stSku->execute([(int) $prodLookupId]);
                    $sku = (string) ($stSku->fetchColumn() ?: '');

                    $ncmKeys = ['_ncm', 'ncm', '_woo_ncm', '_product_ncm', '_ncm_code'];
                    foreach ($ncmKeys as $nk) {
                        $stN = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1");
                        $stN->execute([(int) $prodLookupId, $nk]);
                        $n = (string) ($stN->fetchColumn() ?: '');
                        if (trim($n) !== '') {
                            $ncm = $n;
                            break;
                        }
                    }
                }

                $itens[] = [
                    'order_item_id' => $itemId,
                    'nome' => (string) ($oi['order_item_name'] ?? ''),
                    'produto_id' => $produtoId,
                    'variacao_id' => $variacaoId,
                    'sku' => $sku,
                    'ncm' => $ncm,
                    'quantidade' => $qtd,
                    'preco_unitario' => $unit,
                    'subtotal' => $lineSubtotal,
                    'total' => $lineTotal,
                ];
            }
        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        $sidebarActive = 'pedidos-wp';
        $title = 'Pedido WP - Detalhes - Braziliana Shop Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp_detalhes.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function getWpPdo(\PDO $localPdo): array {
        $cfg = $this->getWpConfig($localPdo);

        $host = (string) ($cfg['db_host'] ?? '');
        $dbname = (string) ($cfg['db_name'] ?? '');
        $user = (string) ($cfg['db_user'] ?? '');
        $pass = (string) ($cfg['db_pass'] ?? '');
        $prefix = (string) ($cfg['table_prefix'] ?? 'wp_');

        $host = trim($host);
        $dbname = trim($dbname);
        $user = trim($user);
        $prefix = trim($prefix);

        $port = null;
        if ($host !== '' && strpos($host, ':') !== false) {
            $parts = explode(':', $host, 2);
            $host = trim((string) ($parts[0] ?? ''));
            $portPart = trim((string) ($parts[1] ?? ''));
            if ($portPart !== '' && ctype_digit($portPart)) {
                $port = (int) $portPart;
            }
        }

        if ($prefix === '') $prefix = 'wp_';

        if ($host === '' || $dbname === '' || $user === '') {
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['pdo' => $pdo, 'prefix' => $prefix];
    }

    private function getUsdToBrlRate(\PDO $pdo): float {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'sistema' AND chave = 'usd_brl_rate' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && is_numeric($v)) {
                $r = (float) $v;
                if ($r > 0.000001) return $r;
            }
        } catch (\Throwable $e) {
        }

        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'sistema_usd_brl_rate' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && is_numeric($v)) {
                $r = (float) $v;
                if ($r > 0.000001) return $r;
            }
        } catch (\Throwable $e) {
        }

        return 5.5;
    }

    private function savePedidoMeta(\PDO $pdo, int $pedidoId, string $metaKey, $metaValue): void {
        $pedidoId = (int) $pedidoId;
        $metaKey = trim($metaKey);
        if ($pedidoId <= 0 || $metaKey === '') return;

        try {
            $st = $pdo->prepare("INSERT INTO pedido_meta (pedido_id, meta_key, meta_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = CURRENT_TIMESTAMP");
            $st->execute([$pedidoId, $metaKey, is_scalar($metaValue) ? (string) $metaValue : json_encode($metaValue)]);
        } catch (\Throwable $e) {
        }
    }

    private function getWpConfig(\PDO $pdo): array {
        $out = ['table_prefix' => 'wp_'];

        // Este projeto normalmente salva configs em configuracoes_sistema.
        // Vamos ler de forma robusta tanto no formato categoria+chave quanto no formato chave/valor.
        try {
            $st = $pdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $st->execute();
            $has = (bool) $st->fetchColumn();
            if (!$has) {
                return $out;
            }
        } catch (\Exception $e) {
            return $out;
        }

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE configuracoes_sistema');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);
        if ($hasCategoria) {
            try {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                $map = [
                    'db_host' => 'db_host',
                    'db_name' => 'db_name',
                    'db_user' => 'db_user',
                    'db_pass' => 'db_pass',
                    'table_prefix' => 'table_prefix',
                ];
                foreach ($map as $outKey => $chave) {
                    $st->execute(['wordpress', $chave]);
                    $val = $st->fetchColumn();
                    if ($val !== false && $val !== null) {
                        $out[$outKey] = (string) $val;
                    }
                }
                return $out;
            } catch (\Exception $e) {
                // cai para tentativa chave/valor abaixo
            }
        }

        if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
            try {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $map = [
                    'db_host' => 'wordpress_db_host',
                    'db_name' => 'wordpress_db_name',
                    'db_user' => 'wordpress_db_user',
                    'db_pass' => 'wordpress_db_pass',
                    'table_prefix' => 'wordpress_table_prefix',
                ];
                foreach ($map as $outKey => $key) {
                    $st->execute([$key]);
                    $val = $st->fetchColumn();
                    if ($val !== false && $val !== null) {
                        $out[$outKey] = (string) $val;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // fallback: schema single-row (colunas diretas)
        if (!empty($cols) && in_array('id', $cols, true) && !in_array('chave', $cols, true) && !in_array('categoria', $cols, true)) {
            try {
                $st = $pdo->query('SELECT * FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $row = $st ? ($st->fetch(\PDO::FETCH_ASSOC) ?: []) : [];
                $out['db_host'] = (string) ($row['wordpress_db_host'] ?? '');
                $out['db_name'] = (string) ($row['wordpress_db_name'] ?? '');
                $out['db_user'] = (string) ($row['wordpress_db_user'] ?? '');
                $out['db_pass'] = (string) ($row['wordpress_db_pass'] ?? '');
                $out['table_prefix'] = (string) ($row['wordpress_table_prefix'] ?? 'wp_');
            } catch (\Exception $e) {
            }
        }

        return $out;
    }
}
