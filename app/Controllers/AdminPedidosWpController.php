<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\WExpressService;
use App\Services\WooCommerceService;
use Config\Database;

class AdminPedidosWpController extends Controller {

    private const SOURCES = ['br', 'red', 'us'];

    public function estatisticas(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $viewParam = strtolower(trim((string) ($request->getParam('view') ?? 'stats')));
        $view = in_array($viewParam, ['stats', 'missing'], true) ? $viewParam : 'stats';

        $missingFieldParam = strtolower(trim((string) ($request->getParam('missing_field') ?? 'any')));
        $missingField = in_array($missingFieldParam, ['any', 'uf', 'cidade', 'bairro'], true) ? $missingFieldParam : 'any';

        $sourceParam = strtolower(trim((string) ($request->getParam('source') ?? 'br')));
        $source = in_array($sourceParam, array_merge(self::SOURCES, ['all']), true) ? $sourceParam : 'br';

        $startRaw = trim((string) ($request->getParam('start') ?? ''));
        $endRaw = trim((string) ($request->getParam('end') ?? ''));
        $statusRaw = trim((string) ($request->getParam('status') ?? ''));
        $hideEmpty = (string) ($request->getParam('hide_empty') ?? '') === '1';
        $top = (int) ($request->getParam('top') ?? 20);
        if ($top <= 0) $top = 20;
        if ($top > 200) $top = 200;

        $page = (int) ($request->getParam('page') ?? 1);
        if ($page <= 0) $page = 1;
        $limite = (int) ($request->getParam('limit') ?? 50);
        if ($limite <= 0) $limite = 50;
        if ($limite > 200) $limite = 200;
        $offset = ($page - 1) * $limite;

        $statusList = [];
        if ($statusRaw !== '') {
            $parts = preg_split('/\s*,\s*/', $statusRaw) ?: [];
            foreach ($parts as $p) {
                $p = strtolower(trim((string) $p));
                if ($p === '') continue;
                if (!preg_match('/^(wc-[a-z0-9_-]+|[a-z0-9_-]+)$/', $p)) continue;
                $statusList[] = $p;
            }
            $statusList = array_values(array_unique($statusList));
        }

        $start = null;
        $end = null;
        if ($startRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw)) {
            $start = $startRaw . ' 00:00:00';
        }
        if ($endRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw)) {
            $end = $endRaw . ' 23:59:59';
        }

        $stats = [
            'total' => 0,
            'sp_capital_total' => 0,
            'por_uf' => [],
            'por_cidade' => [],
            'por_bairro' => [],
        ];
        $missingOrders = [];
        $missingTotal = 0;
        $erro = '';

        try {
            $localPdo = Database::getConnection();

            $sourcesToRun = $source === 'all' ? self::SOURCES : [$source];
            if ($view === 'missing') {
                if ($source === 'all') {
                    $target = $page * $limite;
                    $merged = [];
                    $sumTotal = 0;
                    foreach (self::SOURCES as $src) {
                        $res = $this->fetchWpMissingOrders($localPdo, $src, $start, $end, $statusList, $missingField, $target, 0);
                        $rows = $res['rows'] ?? [];
                        if (is_array($rows)) {
                            foreach ($rows as $r) {
                                if (!is_array($r)) continue;
                                $r['source'] = $src;
                                $merged[] = $r;
                            }
                        }
                        $sumTotal += (int) ($res['total'] ?? 0);
                    }

                    usort($merged, function ($a, $b) {
                        $da = strtotime((string) ($a['created_at'] ?? ''));
                        $db = strtotime((string) ($b['created_at'] ?? ''));
                        if ($da === $db) return 0;
                        return $da > $db ? -1 : 1;
                    });

                    $missingOrders = array_slice($merged, $offset, $limite);
                    $missingTotal = $sumTotal;
                } else {
                    $res = $this->fetchWpMissingOrders($localPdo, $source, $start, $end, $statusList, $missingField, $limite, $offset);
                    $missingOrders = $res['rows'] ?? [];
                    if (!is_array($missingOrders)) $missingOrders = [];
                    foreach ($missingOrders as &$r) {
                        if (is_array($r)) $r['source'] = $source;
                    }
                    unset($r);
                    $missingTotal = (int) ($res['total'] ?? 0);
                }
            } else {
                foreach ($sourcesToRun as $src) {
                    $wp = $this->getWpPdo($localPdo, $src);
                    $prefix = $wp['prefix'];
                    $wpPdo = $wp['pdo'];

                    $partial = $this->fetchWpShippingStats($wpPdo, $prefix, $start, $end, $top, $statusList, $hideEmpty);

                    $stats['total'] += (int) ($partial['total'] ?? 0);
                    $stats['sp_capital_total'] += (int) ($partial['sp_capital_total'] ?? 0);
                    $stats['por_uf'] = $this->mergeStatsList($stats['por_uf'], $partial['por_uf'] ?? []);
                    $stats['por_cidade'] = $this->mergeStatsList($stats['por_cidade'], $partial['por_cidade'] ?? []);
                    $stats['por_bairro'] = $this->mergeStatsList($stats['por_bairro'], $partial['por_bairro'] ?? []);
                }

                $stats['por_uf'] = $this->sortStatsList($stats['por_uf']);
                $stats['por_cidade'] = $this->sortStatsList($stats['por_cidade']);
                $stats['por_bairro'] = $this->sortStatsList($stats['por_bairro']);

                if ($top > 0) {
                    $stats['por_cidade'] = array_slice($stats['por_cidade'], 0, $top);
                    $stats['por_bairro'] = array_slice($stats['por_bairro'], 0, $top);
                }
            }

        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        $sidebarActive = 'wp-estatisticas';
        $title = 'Estatísticas (WP) - Braziliana Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp_estatisticas.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function fetchWpMissingOrders(\PDO $localPdo, string $source, ?string $start, ?string $end, array $statusList, string $missingField, int $limite, int $offset): array {
        $wp = $this->getWpPdo($localPdo, $source);
        $prefix = $wp['prefix'];
        $wpPdo = $wp['pdo'];

        $where = ["p.post_type = 'shop_order'", "p.post_status <> 'trash'"];
        $params = [];

        if ($start !== null) {
            $where[] = 'p.post_date >= :start';
            $params[':start'] = $start;
        }
        if ($end !== null) {
            $where[] = 'p.post_date <= :end';
            $params[':end'] = $end;
        }

        if (!empty($statusList)) {
            $placeholders = [];
            foreach (array_values($statusList) as $i => $st) {
                $ph = ':st' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $st;
            }
            $where[] = 'p.post_status IN (' . implode(',', $placeholders) . ')';
        }

        $limite = (int) $limite;
        if ($limite <= 0) $limite = 50;
        if ($limite > 2000) $limite = 2000;
        $offset = (int) $offset;
        if ($offset < 0) $offset = 0;

        $metaSql = "
            SELECT
                post_id,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_state','shipping_state') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_state','billing_state') THEN meta_value END), ''))
                ) AS ship_state,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_city','shipping_city') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_city','billing_city') THEN meta_value END), ''))
                ) AS ship_city,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_neighborhood','shipping_neighborhood','_shipping_bairro','shipping_bairro','shipping_bairro_name','_shipping_bairro_name') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_neighborhood','billing_neighborhood','_billing_bairro','billing_bairro','billing_bairro_name','_billing_bairro_name') THEN meta_value END), ''))
                ) AS ship_neighborhood
            FROM {$prefix}postmeta
            WHERE meta_key IN (
                '_shipping_state','shipping_state','_shipping_city','shipping_city','_shipping_neighborhood','shipping_neighborhood','_shipping_bairro','shipping_bairro','shipping_bairro_name','_shipping_bairro_name',
                '_billing_state','billing_state','_billing_city','billing_city','_billing_neighborhood','billing_neighborhood','_billing_bairro','billing_bairro','billing_bairro_name','_billing_bairro_name'
            )
            GROUP BY post_id
        ";

        $baseFrom = "
            FROM {$prefix}posts p
            LEFT JOIN ({$metaSql}) sm ON sm.post_id = p.ID
            WHERE " . implode(' AND ', $where) . "
        ";

        $missingField = strtolower(trim($missingField));
        if (!in_array($missingField, ['any', 'uf', 'cidade', 'bairro'], true)) {
            $missingField = 'any';
        }

        if ($missingField === 'uf') {
            $baseFrom .= " AND COALESCE(TRIM(sm.ship_state), '') = ''\n";
        } elseif ($missingField === 'cidade') {
            $baseFrom .= " AND COALESCE(TRIM(sm.ship_city), '') = ''\n";
        } elseif ($missingField === 'bairro') {
            $baseFrom .= " AND COALESCE(TRIM(sm.ship_neighborhood), '') = ''\n";
        } else {
            $baseFrom .= " AND (\n"
                . "   COALESCE(TRIM(sm.ship_state), '') = ''\n"
                . "   OR COALESCE(TRIM(sm.ship_city), '') = ''\n"
                . "   OR COALESCE(TRIM(sm.ship_neighborhood), '') = ''\n"
                . ")\n";
        }

        $baseFrom .= "
        ";

        $sql = "SELECT
            p.ID AS id,
            p.post_date AS created_at,
            p.post_status AS status,
            COALESCE(TRIM(sm.ship_state), '') AS ship_state,
            COALESCE(TRIM(sm.ship_city), '') AS ship_city,
            COALESCE(TRIM(sm.ship_neighborhood), '') AS ship_neighborhood
        {$baseFrom}
        ORDER BY p.post_date DESC
        LIMIT {$limite} OFFSET {$offset}";

        $st = $wpPdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stC = $wpPdo->prepare('SELECT COUNT(*) ' . $baseFrom);
        foreach ($params as $k => $v) $stC->bindValue($k, $v);
        $stC->execute();
        $total = (int) ($stC->fetchColumn() ?: 0);

        return ['rows' => $rows, 'total' => $total];
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $busca = trim((string) ($request->getParam('busca') ?? ''));
        $sourceParam = strtolower(trim((string) ($request->getParam('source') ?? 'br')));
        $source = in_array($sourceParam, array_merge(self::SOURCES, ['all']), true) ? $sourceParam : 'br';
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
            if ($source === 'all') {
                $target = $page * $limite;
                $merged = [];
                $sumTotal = 0;

                foreach (self::SOURCES as $src) {
                    $res = $this->fetchWpPedidos($localPdo, $src, $busca, $target, 0);
                    $rows = $res['rows'] ?? [];
                    if (is_array($rows)) {
                        foreach ($rows as $r) {
                            if (!is_array($r)) continue;
                            $r['source'] = $src;
                            $merged[] = $r;
                        }
                    }
                    $sumTotal += (int) ($res['total'] ?? 0);
                }

                usort($merged, function ($a, $b) {
                    $da = strtotime((string) ($a['created_at'] ?? ''));
                    $db = strtotime((string) ($b['created_at'] ?? ''));
                    if ($da === $db) return 0;
                    return $da > $db ? -1 : 1;
                });

                $pedidos = array_slice($merged, $offset, $limite);
                $total = $sumTotal;
            } else {
                $res = $this->fetchWpPedidos($localPdo, $source, $busca, $limite, $offset);
                $pedidos = $res['rows'] ?? [];
                if (!is_array($pedidos)) $pedidos = [];
                foreach ($pedidos as &$r) {
                    if (is_array($r)) $r['source'] = $source;
                }
                unset($r);
                $total = (int) ($res['total'] ?? 0);
            }
        } catch (\Exception $e) {
            $erro = $e->getMessage();
            $pedidos = [];
            $total = 0;
        }

        $sidebarActive = 'pedidos-wp';
        $title = 'Pedidos (WordPress) - Braziliana Admin';

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

        $sourceParam = strtolower(trim((string) ($request->getParam('source') ?? 'br')));
        $source = in_array($sourceParam, self::SOURCES, true) ? $sourceParam : 'br';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo, $source);
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

            $woo = new WooCommerceService($source);
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

        $sourceParam = strtolower(trim((string) ($request->getParam('source') ?? 'br')));
        $source = in_array($sourceParam, self::SOURCES, true) ? $sourceParam : 'br';

        $pedido = null;
        $meta = [];
        $itens = [];
        $erro = '';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo, $source);

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

                $ncmCandidatesItem = ['_ncm', 'ncm', 'tariff_code', '_tariff_code', 'invoice_ncm', '_invoice_ncm', 'ncm_code', '_ncm_code'];
                foreach ($ncmCandidatesItem as $nk) {
                    $val = trim((string) ($m[$nk] ?? ''));
                    if ($val !== '') {
                        $ncm = $val;
                        break;
                    }
                }

                $prodLookupId = $variacaoId > 0 ? $variacaoId : $produtoId;
                if ($prodLookupId > 0) {
                    $stSku = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_sku' LIMIT 1");
                    $stSku->execute([(int) $prodLookupId]);
                    $sku = (string) ($stSku->fetchColumn() ?: '');

                    if ($ncm === '') {
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
        $title = 'Pedido WP - Detalhes - Braziliana Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp_detalhes.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function fetchWpPedidos(\PDO $localPdo, string $source, string $busca, int $limite, int $offset): array {
        $wp = $this->getWpPdo($localPdo, $source);
        $prefix = $wp['prefix'];
        $wpPdo = $wp['pdo'];

        $where = ["p.post_type = 'shop_order'", "p.post_status <> 'trash'"];
        $params = [];

        if ($busca !== '') {
            $where[] = "(CAST(p.ID AS CHAR) LIKE :busca
                OR p.post_title LIKE :busca
                OR pm_mail.meta_value LIKE :busca
                OR CONCAT(COALESCE(pm_fn.meta_value,''),' ',COALESCE(pm_ln.meta_value,'')) LIKE :busca
                OR EXISTS (
                    SELECT 1 FROM {$prefix}postmeta pmn
                    WHERE pmn.post_id = p.ID
                      AND pmn.meta_key IN ('_order_number','_order_number_formatted','_order_number_full','_order_number_display','_wc_order_number','order_number')
                      AND CAST(pmn.meta_value AS CHAR) LIKE :busca
                )
            )";
            $params[':busca'] = '%' . $busca . '%';
        }

        $limite = (int) $limite;
        if ($limite <= 0) $limite = 50;
        if ($limite > 2000) $limite = 2000;
        $offset = (int) $offset;
        if ($offset < 0) $offset = 0;

        $sql = "SELECT
            p.ID AS id,
            p.post_date AS created_at,
            p.post_status AS status,
            p.post_title AS numero_pedido,
            COALESCE(pm_on.meta_value, pm_onf.meta_value, pm_ond.meta_value, pm_onfull.meta_value, pm_wcon.meta_value, pm_onplain.meta_value) AS order_number_display,
            pm_total.meta_value AS order_total,
            pm_curr.meta_value AS currency,
            pm_mail.meta_value AS billing_email,
            pm_fn.meta_value AS billing_first_name,
            pm_ln.meta_value AS billing_last_name
        FROM {$prefix}posts p
        LEFT JOIN {$prefix}postmeta pm_on ON pm_on.post_id = p.ID AND pm_on.meta_key = '_order_number'
        LEFT JOIN {$prefix}postmeta pm_onf ON pm_onf.post_id = p.ID AND pm_onf.meta_key = '_order_number_formatted'
        LEFT JOIN {$prefix}postmeta pm_ond ON pm_ond.post_id = p.ID AND pm_ond.meta_key = '_order_number_display'
        LEFT JOIN {$prefix}postmeta pm_onfull ON pm_onfull.post_id = p.ID AND pm_onfull.meta_key = '_order_number_full'
        LEFT JOIN {$prefix}postmeta pm_wcon ON pm_wcon.post_id = p.ID AND pm_wcon.meta_key = '_wc_order_number'
        LEFT JOIN {$prefix}postmeta pm_onplain ON pm_onplain.post_id = p.ID AND pm_onplain.meta_key = 'order_number'
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
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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

        return ['rows' => $rows, 'total' => $total];
    }

    private function fetchWpShippingStats(\PDO $wpPdo, string $prefix, ?string $start, ?string $end, int $top, array $statusList, bool $hideEmpty): array {
        $where = ["p.post_type = 'shop_order'", "p.post_status <> 'trash'"];
        $params = [];

        if ($start !== null) {
            $where[] = 'p.post_date >= :start';
            $params[':start'] = $start;
        }
        if ($end !== null) {
            $where[] = 'p.post_date <= :end';
            $params[':end'] = $end;
        }

        if (!empty($statusList)) {
            $placeholders = [];
            foreach (array_values($statusList) as $i => $st) {
                $ph = ':st' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $st;
            }
            $where[] = 'p.post_status IN (' . implode(',', $placeholders) . ')';
        }

        $metaSql = "
            SELECT
                post_id,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_state','shipping_state') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_state','billing_state') THEN meta_value END), ''))
                ) AS ship_state,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_city','shipping_city') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_city','billing_city') THEN meta_value END), ''))
                ) AS ship_city,
                COALESCE(
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_shipping_neighborhood','shipping_neighborhood','_shipping_bairro','shipping_bairro','shipping_bairro_name','_shipping_bairro_name') THEN meta_value END), '')),
                    MAX(NULLIF(TRIM(CASE WHEN meta_key IN ('_billing_neighborhood','billing_neighborhood','_billing_bairro','billing_bairro','billing_bairro_name','_billing_bairro_name') THEN meta_value END), ''))
                ) AS ship_neighborhood
            FROM {$prefix}postmeta
            WHERE meta_key IN (
                '_shipping_state','shipping_state','_shipping_city','shipping_city','_shipping_neighborhood','shipping_neighborhood','_shipping_bairro','shipping_bairro','shipping_bairro_name','_shipping_bairro_name',
                '_billing_state','billing_state','_billing_city','billing_city','_billing_neighborhood','billing_neighborhood','_billing_bairro','billing_bairro','billing_bairro_name','_billing_bairro_name'
            )
            GROUP BY post_id
        ";

        $baseFrom = "
            FROM {$prefix}posts p
            LEFT JOIN ({$metaSql}) sm ON sm.post_id = p.ID
            WHERE " . implode(' AND ', $where) . "
        ";

        $stT = $wpPdo->prepare('SELECT COUNT(*) ' . $baseFrom);
        foreach ($params as $k => $v) $stT->bindValue($k, $v);
        $stT->execute();
        $total = (int) ($stT->fetchColumn() ?: 0);

        $stSP = $wpPdo->prepare(
            "SELECT COUNT(*) {$baseFrom}
             AND UPPER(TRIM(COALESCE(sm.ship_state,''))) = 'SP'
             AND REPLACE(REPLACE(LOWER(TRIM(COALESCE(sm.ship_city,''))), 'ã','a'), 'á','a') = 'sao paulo'"
        );
        foreach ($params as $k => $v) $stSP->bindValue($k, $v);
        $stSP->execute();
        $spCapitalTotal = (int) ($stSP->fetchColumn() ?: 0);

        $sqlUf = "
            SELECT
                UPPER(TRIM(COALESCE(sm.ship_state, ''))) AS label,
                COUNT(*) AS total
            {$baseFrom}
            GROUP BY UPPER(TRIM(COALESCE(sm.ship_state, '')))
            ORDER BY total DESC
        ";
        $stUf = $wpPdo->prepare($sqlUf);
        foreach ($params as $k => $v) $stUf->bindValue($k, $v);
        $stUf->execute();
        $rowsUf = $stUf->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $sqlCidade = "
            SELECT
                TRIM(COALESCE(sm.ship_city, '')) AS label,
                COUNT(*) AS total
            {$baseFrom}
            GROUP BY TRIM(COALESCE(sm.ship_city, ''))
            ORDER BY total DESC
            LIMIT " . (int) max(1, min(2000, $top * 20));
        $stC = $wpPdo->prepare($sqlCidade);
        foreach ($params as $k => $v) $stC->bindValue($k, $v);
        $stC->execute();
        $rowsCidade = $stC->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $sqlBairro = "
            SELECT
                TRIM(COALESCE(sm.ship_neighborhood, '')) AS label,
                COUNT(*) AS total
            {$baseFrom}
            GROUP BY TRIM(COALESCE(sm.ship_neighborhood, ''))
            ORDER BY total DESC
            LIMIT " . (int) max(1, min(2000, $top * 20));
        $stB = $wpPdo->prepare($sqlBairro);
        foreach ($params as $k => $v) $stB->bindValue($k, $v);
        $stB->execute();
        $rowsBairro = $stB->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => $total,
            'sp_capital_total' => $spCapitalTotal,
            'por_uf' => $this->rowsToStatsList($rowsUf, $total, $hideEmpty),
            'por_cidade' => $this->rowsToStatsList($rowsCidade, $total, $hideEmpty),
            'por_bairro' => $this->rowsToStatsList($rowsBairro, $total, $hideEmpty),
        ];
    }

    private function rowsToStatsList(array $rows, int $total, bool $hideEmpty): array {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $label = trim((string) ($r['label'] ?? ''));
            $count = (int) ($r['total'] ?? 0);
            if ($label === '') {
                if ($hideEmpty) {
                    continue;
                }
                $label = '(vazio)';
            }
            if ($count <= 0) continue;
            $pct = $total > 0 ? round(($count / $total) * 100, 2) : 0.0;
            $out[] = ['label' => $label, 'total' => $count, 'pct' => $pct];
        }
        return $out;
    }

    private function mergeStatsList(array $base, array $add): array {
        $map = [];
        foreach ($base as $row) {
            if (!is_array($row)) continue;
            $label = (string) ($row['label'] ?? '');
            if ($label === '') continue;
            $map[$label] = (int) ($row['total'] ?? 0);
        }
        foreach ($add as $row) {
            if (!is_array($row)) continue;
            $label = (string) ($row['label'] ?? '');
            if ($label === '') continue;
            $map[$label] = (int) ($map[$label] ?? 0) + (int) ($row['total'] ?? 0);
        }

        $out = [];
        foreach ($map as $label => $count) {
            $out[] = ['label' => (string) $label, 'total' => (int) $count];
        }
        return $out;
    }

    private function sortStatsList(array $rows): array {
        usort($rows, function ($a, $b) {
            $ta = (int) (is_array($a) ? ($a['total'] ?? 0) : 0);
            $tb = (int) (is_array($b) ? ($b['total'] ?? 0) : 0);
            if ($ta === $tb) return 0;
            return $ta > $tb ? -1 : 1;
        });
        return array_values($rows);
    }

    private function getWpPdo(\PDO $localPdo, string $source = 'br'): array {
        $cfg = $this->getWpConfig($localPdo, $source);

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

    private function getWpConfig(\PDO $pdo, string $source = 'br'): array {
        $out = ['table_prefix' => 'wp_'];

        $source = strtolower(trim($source));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'br';
        }

        $cat = 'wordpress_' . $source;

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
                    $val = null;
                    $st->execute([$cat, $chave]);
                    $v1 = $st->fetchColumn();
                    if ($v1 !== false && $v1 !== null) {
                        $val = $v1;
                    } elseif ($source === 'br') {
                        $st->execute(['wordpress', $chave]);
                        $v2 = $st->fetchColumn();
                        if ($v2 !== false && $v2 !== null) {
                            $val = $v2;
                        }
                    }
                    if ($val !== null) {
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
                    'db_host' => 'db_host',
                    'db_name' => 'db_name',
                    'db_user' => 'db_user',
                    'db_pass' => 'db_pass',
                    'table_prefix' => 'table_prefix',
                ];
                foreach ($map as $outKey => $suffix) {
                    $val = null;

                    // Primeiro tenta por origem: wordpress_red_db_host, etc.
                    $st->execute([$cat . '_' . $suffix]);
                    $v1 = $st->fetchColumn();
                    if ($v1 !== false && $v1 !== null) {
                        $val = $v1;
                    } elseif ($source === 'br') {
                        // Compatibilidade: wordpress_db_host (genérico) para BR
                        $st->execute(['wordpress_' . $suffix]);
                        $v2 = $st->fetchColumn();
                        if ($v2 !== false && $v2 !== null) {
                            $val = $v2;
                        }
                    }

                    if ($val !== null) {
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
