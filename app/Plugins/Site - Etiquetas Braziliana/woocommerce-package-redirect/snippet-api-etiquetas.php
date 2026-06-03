<?php
/**
 * Snippet: API REST para integração do sistema Braziliana com o WordPress Etiquetas.
 * 
 * Coloque este código no plugin Code Snippets do WordPress (etiquetas.brazilianashop.com.br).
 * 
 * Ele expõe endpoints REST protegidos por chave de API para:
 * - Criar pacotes (etiquetas) 
 * - Criar containers (unitizadores)
 * - Criar faturas (CN38)
 * - Confirmar embarques (departures)
 * - Gerar PDFs (etiqueta, container, fatura)
 * - Listar pacotes/containers/faturas/embarques
 * 
 * URL base: https://etiquetas.brazilianashop.com.br/wp-json/brz/v1/
 */

// ============================================================
// CONFIGURAÇÃO - Altere a chave de API abaixo
// ============================================================
define('BRZ_API_KEY', 'hBkXYUYPe5bvjujrI4W9r5yCWSVekw7ao529uGYbkBEyknW4tPhsx8mHMIANyNXl');

// ============================================================
// REGISTRO DOS ENDPOINTS
// ============================================================
add_action('rest_api_init', function () {
    $namespace = 'brz/v1';

    // === PACOTES (Etiquetas) ===
    register_rest_route($namespace, '/packages/create', [
        'methods'  => 'POST',
        'callback' => 'brz_api_create_package',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/packages/pdf/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'brz_api_package_pdf',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/packages', [
        'methods'  => 'GET',
        'callback' => 'brz_api_list_packages',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    // === CONTAINERS (Unitizadores) ===
    register_rest_route($namespace, '/containers/create', [
        'methods'  => 'POST',
        'callback' => 'brz_api_create_container',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/containers/pdf/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'brz_api_container_pdf',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/containers', [
        'methods'  => 'GET',
        'callback' => 'brz_api_list_containers',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    // === FATURAS (CN38) ===
    register_rest_route($namespace, '/bills/create', [
        'methods'  => 'POST',
        'callback' => 'brz_api_create_bill',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/bills/pdf/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'brz_api_bill_pdf',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/bills', [
        'methods'  => 'GET',
        'callback' => 'brz_api_list_bills',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    // === EMBARQUES (Departures) ===
    register_rest_route($namespace, '/departures/create', [
        'methods'  => 'POST',
        'callback' => 'brz_api_create_departure',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/departures', [
        'methods'  => 'GET',
        'callback' => 'brz_api_list_departures',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    // === SALDO ===
    register_rest_route($namespace, '/balance', [
        'methods'  => 'GET',
        'callback' => 'brz_api_balance',
        'permission_callback' => 'brz_api_check_auth',
    ]);
});

// ============================================================
// AUTENTICAÇÃO
// ============================================================
function brz_api_check_auth(WP_REST_Request $request) {
    $key = $request->get_header('X-API-Key');
    if (empty($key)) {
        $key = $request->get_param('api_key');
    }
    if ($key !== BRZ_API_KEY) {
        return new WP_Error('unauthorized', 'Chave de API inválida.', ['status' => 401]);
    }
    return true;
}

// ============================================================
// SALDO
// ============================================================
function brz_api_balance(WP_REST_Request $request) {
    try {
        $correios = new WPR_Correios_Service();
        $balance = $correios->get_tracking_numbers_balance();
        return new WP_REST_Response(['success' => true, 'data' => $balance], 200);
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// ============================================================
// PACOTES - CRIAR
// ============================================================
function brz_api_create_package(WP_REST_Request $request) {
    $body = $request->get_json_params();

    $required = ['customerControlCode', 'totalWeight', 'packagingLength', 'packagingWidth', 'packagingHeight', 'items'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            return new WP_REST_Response(['success' => false, 'error' => "Campo obrigatório ausente: {$field}"], 400);
        }
    }

    // Montar dados do remetente a partir das configurações do plugin
    $sender_data = [
        'senderName' => get_option('wpr_correios_sender_name', ''),
        'senderAddress' => get_option('wpr_correios_sender_address', ''),
        'senderAddressNumber' => get_option('wpr_correios_sender_address_number', ''),
        'senderAddressComplement' => get_option('wpr_correios_sender_address_complement', ''),
        'senderZipCode' => get_option('wpr_correios_sender_zip_code', ''),
        'senderCityName' => get_option('wpr_correios_sender_city_name', ''),
        'senderState' => get_option('wpr_correios_sender_state', ''),
        'senderCountryCode' => get_option('wpr_correios_sender_country_code', 'US'),
        'senderEmail' => get_option('wpr_correios_sender_email', ''),
        'senderWebsite' => get_option('wpr_correios_sender_website', ''),
    ];

    // Montar dados do destinatário a partir do body
    $recipient_data = [
        'recipientName' => sanitize_text_field($body['recipientName'] ?? ''),
        'recipientDocumentType' => sanitize_text_field($body['recipientDocumentType'] ?? 'CPF'),
        'recipientDocumentNumber' => preg_replace('/\D/', '', $body['recipientDocumentNumber'] ?? ''),
        'recipientAddress' => sanitize_text_field($body['recipientAddress'] ?? ''),
        'recipientAddressNumber' => sanitize_text_field($body['recipientAddressNumber'] ?? ''),
        'recipientAddressComplement' => sanitize_text_field($body['recipientAddressComplement'] ?? ''),
        'recipientCityName' => sanitize_text_field($body['recipientCityName'] ?? ''),
        'recipientState' => sanitize_text_field($body['recipientState'] ?? ''),
        'recipientZipCode' => preg_replace('/\D/', '', $body['recipientZipCode'] ?? ''),
        'recipientEmail' => sanitize_email($body['recipientEmail'] ?? ''),
        'recipientPhoneNumber' => preg_replace('/\D/', '', $body['recipientPhoneNumber'] ?? ''),
    ];

    // Montar items
    $items = [];
    foreach ($body['items'] as $item) {
        $items[] = [
            'hsCode' => sanitize_text_field($item['hsCode'] ?? ''),
            'description' => sanitize_text_field($item['description'] ?? ''),
            'quantity' => intval($item['quantity'] ?? 1),
            'value' => floatval($item['value'] ?? 0),
        ];
    }

    $package_payload = array_merge($sender_data, $recipient_data, [
        'customerControlCode' => sanitize_text_field($body['customerControlCode']),
        'totalWeight' => intval($body['totalWeight']),
        'packagingLength' => floatval($body['packagingLength']),
        'packagingWidth' => floatval($body['packagingWidth']),
        'packagingHeight' => floatval($body['packagingHeight']),
        'distributionModality' => intval($body['distributionModality'] ?? 33162),
        'taxPaymentMethod' => sanitize_text_field($body['taxPaymentMethod'] ?? 'DDU'),
        'currency' => sanitize_text_field($body['currency'] ?? 'USD'),
        'nonNationalizationInstruction' => sanitize_text_field($body['nonNationalizationInstruction'] ?? 'RETURNTOORIGIN'),
        'packageRfidCode' => sanitize_text_field($body['packageRfidCode'] ?? ''),
        'freightPaidValue' => floatval($body['freightPaidValue'] ?? 0.01),
        'items' => $items,
    ]);

    if (!empty($body['insurancePaidValue'])) {
        $package_payload['insurancePaidValue'] = floatval($body['insurancePaidValue']);
    }

    $api_payload = ['packageList' => [$package_payload]];

    try {
        $correios = new WPR_Correios_Service();
        $response = $correios->create_package($api_payload);

        if (empty($response) || empty($response[0]->trackingNumber)) {
            return new WP_REST_Response(['success' => false, 'error' => 'API não retornou tracking number', 'raw' => $response], 500);
        }

        $tracking = $response[0]->trackingNumber;

        // Criar o post do tipo package no WordPress
        $post_id = wp_insert_post([
            'post_type' => 'package',
            'post_status' => 'publish',
            'post_title' => $body['customerControlCode'] . ' - ' . $tracking,
        ]);

        if (is_wp_error($post_id)) {
            return new WP_REST_Response([
                'success' => true,
                'tracking_number' => $tracking,
                'wp_post_id' => null,
                'warning' => 'Pacote criado no Correios mas falhou ao salvar no WordPress: ' . $post_id->get_error_message(),
            ], 200);
        }

        // Salvar metadados
        update_post_meta($post_id, '_package_order_id', $body['customerControlCode']);
        update_post_meta($post_id, '_correios_tracking_code', $tracking);
        update_post_meta($post_id, '_total_weight', intval($body['totalWeight']));
        update_post_meta($post_id, '_package_width', floatval($body['packagingWidth']));
        update_post_meta($post_id, '_package_height', floatval($body['packagingHeight']));
        update_post_meta($post_id, '_package_length', floatval($body['packagingLength']));
        update_post_meta($post_id, '_distribution_modality', $body['distributionModality'] ?? 33162);
        update_post_meta($post_id, '_tax_payment_method', $body['taxPaymentMethod'] ?? 'DDU');
        update_post_meta($post_id, '_currency', $body['currency'] ?? 'USD');
        update_post_meta($post_id, '_freight_paid_value', floatval($body['freightPaidValue'] ?? 0.01));
        update_post_meta($post_id, '_debug_request_body', $api_payload);
        update_post_meta($post_id, '_debug_response_body', $response);

        // Salvar dados do destinatário para uso no PDF
        update_post_meta($post_id, '_recipient_name', $recipient_data['recipientName']);
        update_post_meta($post_id, '_recipient_document_type', $recipient_data['recipientDocumentType']);
        update_post_meta($post_id, '_recipient_document_number', $recipient_data['recipientDocumentNumber']);
        update_post_meta($post_id, '_recipient_address', $recipient_data['recipientAddress']);
        update_post_meta($post_id, '_recipient_address_number', $recipient_data['recipientAddressNumber']);
        update_post_meta($post_id, '_recipient_address_complement', $recipient_data['recipientAddressComplement']);
        update_post_meta($post_id, '_recipient_city_name', $recipient_data['recipientCityName']);
        update_post_meta($post_id, '_recipient_state', $recipient_data['recipientState']);
        update_post_meta($post_id, '_recipient_zip_code', $recipient_data['recipientZipCode']);
        update_post_meta($post_id, '_recipient_email', $recipient_data['recipientEmail']);
        update_post_meta($post_id, '_recipient_phone_number', $recipient_data['recipientPhoneNumber']);
        update_post_meta($post_id, '_items_json', wp_json_encode($items));

        return new WP_REST_Response([
            'success' => true,
            'tracking_number' => $tracking,
            'wp_post_id' => $post_id,
        ], 200);

    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// ============================================================
// PACOTES - LISTAR
// ============================================================
function brz_api_list_packages(WP_REST_Request $request) {
    $per_page = intval($request->get_param('per_page') ?: 50);
    $page = intval($request->get_param('page') ?: 1);

    $args = [
        'post_type' => 'package',
        'posts_per_page' => min($per_page, 200),
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    // Filtro por tracking code
    if ($request->get_param('tracking_code')) {
        $args['meta_query'] = [[
            'key' => '_correios_tracking_code',
            'value' => sanitize_text_field($request->get_param('tracking_code')),
        ]];
    }

    // Filtro por sem container
    if ($request->get_param('without_container') === '1') {
        $args['meta_query'] = [
            'relation' => 'AND',
            ['key' => '_correios_tracking_code', 'compare' => 'EXISTS'],
            [
                'relation' => 'OR',
                ['key' => '_container_id', 'compare' => 'NOT EXISTS'],
                ['key' => '_container_id', 'value' => '', 'compare' => '='],
                ['key' => '_container_id', 'value' => '0', 'compare' => '='],
            ],
        ];
    }

    $query = new WP_Query($args);
    $packages = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $packages[] = [
                'wp_post_id' => $id,
                'order_id' => get_post_meta($id, '_package_order_id', true),
                'tracking_code' => get_post_meta($id, '_correios_tracking_code', true),
                'container_id' => get_post_meta($id, '_container_id', true),
                'total_weight' => get_post_meta($id, '_total_weight', true),
                'created_at' => get_the_date('Y-m-d H:i:s'),
            ];
        }
    }
    wp_reset_postdata();

    return new WP_REST_Response([
        'success' => true,
        'data' => $packages,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
    ], 200);
}

// ============================================================
// PACOTES - PDF
// ============================================================
function brz_api_package_pdf(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'package') {
        return new WP_REST_Response(['success' => false, 'error' => 'Pacote não encontrado'], 404);
    }

    $tracking_code = get_post_meta($post_id, '_correios_tracking_code', true);
    if (empty($tracking_code)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Pacote sem tracking code'], 400);
    }

    // Gerar PDF internamente usando a mesma lógica do plugin
    // Capturar output do Dompdf
    try {
        $pdf_content = brz_generate_package_pdf_content($post_id);
        if ($pdf_content === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF do pacote'], 500);
        }

        $response = new WP_REST_Response();
        $response->set_status(200);
        $response->set_headers([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="etiqueta_' . $tracking_code . '.pdf"',
            'Content-Length' => strlen($pdf_content),
        ]);

        // Para retornar binário via REST, usamos um hook
        add_filter('rest_pre_serve_request', function($served, $result) use ($pdf_content) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="etiqueta.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return $response;
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
}

/**
 * Gera o conteúdo PDF de um pacote (etiqueta) e retorna como string binária.
 */
function brz_generate_package_pdf_content($post_id) {
    if (!class_exists('Dompdf\\Dompdf')) {
        // Tentar carregar autoloader do plugin
        $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';
        if (file_exists($plugin_dir . '/vendor/autoload.php')) {
            require_once $plugin_dir . '/vendor/autoload.php';
        }
    }

    if (!class_exists('Dompdf\\Dompdf')) {
        return false;
    }

    $tracking_code = get_post_meta($post_id, '_correios_tracking_code', true);
    $order_id = get_post_meta($post_id, '_package_order_id', true);

    $recipient_name = get_post_meta($post_id, '_recipient_name', true);
    $recipient_document_type = get_post_meta($post_id, '_recipient_document_type', true);
    $recipient_document_number = get_post_meta($post_id, '_recipient_document_number', true);
    $recipient_address = get_post_meta($post_id, '_recipient_address', true);
    $recipient_address_number = get_post_meta($post_id, '_recipient_address_number', true);
    $recipient_address_complement = get_post_meta($post_id, '_recipient_address_complement', true);
    $recipient_city_name = get_post_meta($post_id, '_recipient_city_name', true);
    $recipient_state = get_post_meta($post_id, '_recipient_state', true);
    $recipient_zip_code = get_post_meta($post_id, '_recipient_zip_code', true);

    $width = get_post_meta($post_id, '_package_width', true);
    $height = get_post_meta($post_id, '_package_height', true);
    $length = get_post_meta($post_id, '_package_length', true);
    $total_weight = get_post_meta($post_id, '_total_weight', true);
    $tax_payment_method = get_post_meta($post_id, '_tax_payment_method', true) ?: 'DDU';

    $items_json = get_post_meta($post_id, '_items_json', true);
    $items = $items_json ? json_decode($items_json, true) : [];

    $sender_name = get_option('wpr_correios_sender_name', '');
    $sender_address = get_option('wpr_correios_sender_address', '');
    $sender_city = get_option('wpr_correios_sender_city_name', '');
    $sender_state = get_option('wpr_correios_sender_state', '');
    $sender_zip = get_option('wpr_correios_sender_zip_code', '');
    $sender_country = get_option('wpr_correios_sender_country_code', 'US');

    $return_company = get_option('wpr_correios_return_company', '');
    $return_street = get_option('wpr_correios_return_street', '');
    $return_neighborhood = get_option('wpr_correios_return_neighborhood', '');
    $return_zip = get_option('wpr_correios_return_zip_code', '');
    $return_city = get_option('wpr_correios_return_city', '');
    $return_uf = get_option('wpr_correios_return_uf', '');

    // Barcode
    $barcode_svg = '';
    if (class_exists('Milon\\Barcode\\DNS1D')) {
        $generator = new \Milon\Barcode\DNS1D();
        $generator->setStorPath(sys_get_temp_dir() . '/');
        $barcode_svg = '<img src="data:image/png;base64,' . $generator->getBarcodePNG($tracking_code, 'C128', 2, 50) . '" style="width:100%;height:50px;">';
    }

    $total_items_value = 0;
    $items_html = '';
    foreach ($items as $item) {
        $item_total = floatval($item['value'] ?? 0) * intval($item['quantity'] ?? 1);
        $total_items_value += $item_total;
        $items_html .= '<tr><td>' . esc_html($item['hsCode'] ?? '') . '</td><td>' . esc_html($item['description'] ?? '') . '</td><td>' . intval($item['quantity'] ?? 1) . '</td><td>$ ' . number_format(floatval($item['value'] ?? 0), 2) . '</td><td>$ ' . number_format($item_total, 2) . '</td></tr>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page { margin: 5mm; }
        body { font-family: Arial, sans-serif; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 3px; vertical-align: top; }
        .no-border td, .no-border th { border: none; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .barcode { text-align: center; padding: 5px; }
    </style></head><body>';
    
    $html .= '<table><tr><td class="center bold" colspan="4">ETIQUETA POSTAL - CORREIOS PACKET</td></tr>';
    $html .= '<tr><td colspan="2" class="bold">REMETENTE / SENDER</td><td colspan="2" class="bold">DESTINATÁRIO / RECIPIENT</td></tr>';
    $html .= '<tr><td colspan="2">' . esc_html($sender_name) . '<br>' . esc_html($sender_address) . '<br>' . esc_html($sender_city) . ', ' . esc_html($sender_state) . ' ' . esc_html($sender_zip) . '<br>' . esc_html($sender_country) . '</td>';
    $html .= '<td colspan="2">' . esc_html($recipient_name) . '<br>' . esc_html($recipient_address) . ', ' . esc_html($recipient_address_number) . ' ' . esc_html($recipient_address_complement) . '<br>' . esc_html($recipient_city_name) . '/' . esc_html($recipient_state) . '<br>CEP: ' . esc_html($recipient_zip_code) . '<br>' . esc_html($recipient_document_type) . ': ' . esc_html($recipient_document_number) . '</td></tr>';
    
    $html .= '<tr><td colspan="4" class="barcode">' . $barcode_svg . '<br><span class="bold">' . esc_html($tracking_code) . '</span></td></tr>';
    
    $html .= '<tr><td class="bold">Peso/Weight</td><td>' . number_format(floatval($total_weight) / 1000, 2) . ' kg</td>';
    $html .= '<td class="bold">Dimensões (CxLxA)</td><td>' . esc_html($length) . ' x ' . esc_html($width) . ' x ' . esc_html($height) . ' cm</td></tr>';
    $html .= '<tr><td class="bold">Método Tributário</td><td>' . esc_html($tax_payment_method) . '</td><td class="bold">Pedido</td><td>#' . esc_html($order_id) . '</td></tr>';
    
    $html .= '<tr><td colspan="4" class="bold center">DECLARAÇÃO ADUANEIRA / CUSTOMS DECLARATION</td></tr>';
    $html .= '<tr><th>NCM/HS</th><th>Descrição</th><th>Qtd</th><th>Valor Unit.</th><th>Total</th></tr>';
    $html .= $items_html;
    $html .= '<tr><td colspan="4" class="bold">TOTAL</td><td class="bold">$ ' . number_format($total_items_value, 2) . '</td></tr>';
    
    if ($return_company) {
        $html .= '<tr><td colspan="4" style="font-size:7pt;">Devolução/Return: ' . esc_html($return_company) . ' - ' . esc_html($return_street) . ', ' . esc_html($return_neighborhood) . ' - CEP ' . esc_html($return_zip) . ' - ' . esc_html($return_city) . '/' . esc_html($return_uf) . '</td></tr>';
    }
    
    $html .= '</table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper([0, 0, 283.5, 425.2]); // 100mm x 150mm
    $dompdf->loadHtml($html);
    $dompdf->render();

    return $dompdf->output();
}

// ============================================================
// CONTAINERS - CRIAR
// ============================================================
function brz_api_create_container(WP_REST_Request $request) {
    $body = $request->get_json_params();

    $required = ['dispatchNumber', 'trackingCodes'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            return new WP_REST_Response(['success' => false, 'error' => "Campo obrigatório ausente: {$field}"], 400);
        }
    }

    $tracking_codes = $body['trackingCodes'];
    if (!is_array($tracking_codes) || empty($tracking_codes)) {
        return new WP_REST_Response(['success' => false, 'error' => 'trackingCodes deve ser um array não vazio'], 400);
    }

    $dispatch_number = intval($body['dispatchNumber']);
    $origin_country = sanitize_text_field($body['originCountry'] ?? 'US');
    $origin_operator_name = sanitize_text_field($body['originOperatorName'] ?? 'USPS');
    $destination_operator_name = sanitize_text_field($body['destinationOperatorName'] ?? 'CWBA');
    $postal_category_code = sanitize_text_field($body['postalCategoryCode'] ?? 'A');
    $service_subclass_code = sanitize_text_field($body['serviceSubclassCode'] ?? 'NX');
    $unit_type = sanitize_text_field($body['unitType'] ?? '2');
    $awb = sanitize_text_field($body['awb'] ?? '');
    $triage_group = sanitize_text_field($body['triageGroup'] ?? '1');

    // Criar unitizador via API Correios
    $unit_data = [
        'dispatchNumber' => $dispatch_number,
        'originCountry' => $origin_country,
        'originOperatorName' => $origin_operator_name,
        'destinationOperatorName' => $destination_operator_name,
        'postalCategoryCode' => $postal_category_code,
        'serviceSubclassCode' => $service_subclass_code,
        'unitList' => [
            [
                'sequence' => 1,
                'unitType' => $unit_type,
                'trackingNumbers' => $tracking_codes,
            ]
        ]
    ];

    try {
        $correios = new WPR_Correios_Service();
        $response = $correios->create_unit($unit_data);

        if (empty($response) || empty($response[0]->unitCode)) {
            return new WP_REST_Response(['success' => false, 'error' => 'API não retornou unitCode', 'raw' => $response], 500);
        }

        $unit_code = $response[0]->unitCode;

        // Criar post do tipo container
        $post_id = wp_insert_post([
            'post_type' => 'container',
            'post_status' => 'publish',
            'post_title' => 'Container #' . $dispatch_number . ' - ' . $unit_code,
        ]);

        if (is_wp_error($post_id)) {
            return new WP_REST_Response([
                'success' => true,
                'unit_code' => $unit_code,
                'wp_post_id' => null,
                'warning' => 'Container criado no Correios mas falhou ao salvar no WordPress.',
            ], 200);
        }

        // Salvar metadados
        update_post_meta($post_id, '_dispatch_number', $dispatch_number);
        update_post_meta($post_id, '_origin_country', $origin_country);
        update_post_meta($post_id, '_origin_operator_name', $origin_operator_name);
        update_post_meta($post_id, '_destination_operator_name', $destination_operator_name);
        update_post_meta($post_id, '_postal_category_code', $postal_category_code);
        update_post_meta($post_id, '_service_subclass_code', $service_subclass_code);
        update_post_meta($post_id, '_unit_type', $unit_type);
        update_post_meta($post_id, '_awb', $awb);
        update_post_meta($post_id, '_triage_group', $triage_group);
        update_post_meta($post_id, '_tracking_codes', $tracking_codes);
        update_post_meta($post_id, '_unit_code', $unit_code);
        update_post_meta($post_id, '_debug_request_body', $unit_data);
        update_post_meta($post_id, '_debug_response_body', $response);

        // Vincular pacotes ao container
        foreach ($tracking_codes as $tc) {
            $pkg_query = new WP_Query([
                'post_type' => 'package',
                'meta_key' => '_correios_tracking_code',
                'meta_value' => $tc,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]);
            if ($pkg_query->have_posts()) {
                update_post_meta($pkg_query->posts[0], '_container_id', $post_id);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'unit_code' => $unit_code,
            'wp_post_id' => $post_id,
            'dispatch_number' => $dispatch_number,
            'tracking_codes' => $tracking_codes,
        ], 200);

    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// ============================================================
// CONTAINERS - LISTAR
// ============================================================
function brz_api_list_containers(WP_REST_Request $request) {
    $per_page = intval($request->get_param('per_page') ?: 50);
    $page = intval($request->get_param('page') ?: 1);

    $args = [
        'post_type' => 'container',
        'posts_per_page' => min($per_page, 200),
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    // Filtro: sem fatura
    if ($request->get_param('without_bill') === '1') {
        $args['meta_query'] = [
            'relation' => 'OR',
            ['key' => '_bill_id', 'compare' => 'NOT EXISTS'],
            ['key' => '_bill_id', 'value' => '', 'compare' => '='],
            ['key' => '_bill_id', 'value' => '0', 'compare' => '='],
        ];
    }

    $query = new WP_Query($args);
    $containers = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $containers[] = [
                'wp_post_id' => $id,
                'dispatch_number' => get_post_meta($id, '_dispatch_number', true),
                'unit_code' => get_post_meta($id, '_unit_code', true),
                'tracking_codes' => get_post_meta($id, '_tracking_codes', true) ?: [],
                'bill_id' => get_post_meta($id, '_bill_id', true),
                'triage_group' => get_post_meta($id, '_triage_group', true),
                'service_subclass_code' => get_post_meta($id, '_service_subclass_code', true),
                'created_at' => get_the_date('Y-m-d H:i:s'),
            ];
        }
    }
    wp_reset_postdata();

    return new WP_REST_Response([
        'success' => true,
        'data' => $containers,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
    ], 200);
}

// ============================================================
// CONTAINERS - PDF
// ============================================================
function brz_api_container_pdf(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'container') {
        return new WP_REST_Response(['success' => false, 'error' => 'Container não encontrado'], 404);
    }

    $unit_code = get_post_meta($post_id, '_unit_code', true);
    if (empty($unit_code)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Container sem unit_code'], 400);
    }

    // Chamar o generate_pdf da classe WPR_Container internamente
    // Usamos ob_start para capturar o output do Dompdf antes do exit
    try {
        // Simular o $_POST para a classe
        $_POST['post_id'] = $post_id;
        $_POST['generate_pdf'] = '1';

        ob_start();
        // Chamar diretamente a lógica de geração - o plugin já tem WPR_Container instanciado
        // Precisamos instanciar e chamar manualmente
        $container_instance = new WPR_Container();
        
        // Hook para capturar o PDF ao invés de fazer stream
        // Na verdade, o generate_pdf faz $dompdf->stream() que faz echo + exit
        // Vamos capturar com output buffering
        $pdf_content = brz_capture_container_pdf($post_id);
        ob_end_clean();

        if ($pdf_content === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF do container'], 500);
        }

        add_filter('rest_pre_serve_request', function($served, $result) use ($pdf_content, $unit_code) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="container_' . $unit_code . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return new WP_REST_Response();
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
}

/**
 * Captura o PDF do container sem fazer exit/stream.
 * Replica a lógica do WPR_Container->generate_pdf()
 */
function brz_capture_container_pdf($post_id) {
    if (!class_exists('Dompdf\\Dompdf')) {
        $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';
        if (file_exists($plugin_dir . '/vendor/autoload.php')) {
            require_once $plugin_dir . '/vendor/autoload.php';
        }
    }
    if (!class_exists('Dompdf\\Dompdf')) return false;

    $unit_code = get_post_meta($post_id, '_unit_code', true);
    $dispatch_number = get_post_meta($post_id, '_dispatch_number', true);
    $destination_operator_name = get_post_meta($post_id, '_destination_operator_name', true);
    $service_subclass_code = get_post_meta($post_id, '_service_subclass_code', true);
    $triage_group = get_post_meta($post_id, '_triage_group', true) ?: '1';
    $tracking_codes = get_post_meta($post_id, '_tracking_codes', true) ?: [];

    $bill_id = get_post_meta($post_id, '_bill_id', true);
    $cn38_code = $bill_id ? get_post_meta($bill_id, '_cn38_code', true) : '';

    $departure_id = $bill_id ? get_post_meta($bill_id, '_departure_id', true) : '';
    $flight_list = $departure_id ? (get_post_meta($departure_id, '_flight_list', true) ?: []) : [];
    $flight_number = $flight_list['flightNumber'] ?? '';
    $departure_date = $flight_list['departureDate'] ?? '';
    $departure_airport_code = $flight_list['departureAirportCode'] ?? '';
    $arrival_airport_code = $flight_list['arrivalAirportCode'] ?? '';
    $awb = get_post_meta($post_id, '_awb', true);

    // Calcular peso total dos pacotes
    $total_weight = 0;
    $packages_count = 0;
    $args = ['post_type' => 'package', 'meta_query' => [['key' => '_container_id', 'value' => $post_id]], 'posts_per_page' => -1];
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $total_weight += floatval(get_post_meta(get_the_ID(), '_total_weight', true));
            $packages_count++;
        }
    }
    wp_reset_postdata();

    $subclass_description = $service_subclass_code == 'NX' ? 'PACKET STANDARD' : 'PACKET EXPRESS';

    // Barcode
    $barcode_img = '';
    if (class_exists('Milon\\Barcode\\DNS1D')) {
        $generator = new \Milon\Barcode\DNS1D();
        $generator->setStorPath(sys_get_temp_dir() . '/');
        $barcode_img = '<img src="data:image/png;base64,' . $generator->getBarcodePNG($unit_code, 'C128', 2, 50) . '" style="width:80%;height:50px;">';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page { margin: 5mm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .en { font-size: 7pt; color: #555; }
    </style></head><body>';
    
    $html .= '<table>';
    $html .= '<tr><td class="center bold" colspan="3">' . esc_html($subclass_description) . ' - ETIQUETA UNITIZADOR</td></tr>';
    $html .= '<tr><td><p class="bold">N° do Despacho<br><span class="en">(Dispatch N°)</span></p><p>' . esc_html($cn38_code) . '</p></td>';
    $html .= '<td colspan="2"><p class="bold">Destino</p><p>' . esc_html($destination_operator_name) . ' - Grupo ' . esc_html($triage_group) . '</p></td></tr>';
    $html .= '<tr><td><p class="bold">N° Serial da Mala<br><span class="en">(Receptacle Serial Number)</span></p><p>' . esc_html($dispatch_number) . '</p></td>';
    $html .= '<td><p class="bold">N° Voo<br><span class="en">(Flight Number)</span></p><p>' . esc_html($flight_number) . '</p></td>';
    $html .= '<td><p class="bold">N° AWB</p><p>' . esc_html($awb) . '</p></td></tr>';
    $html .= '<tr><td><p class="bold">Data do Despacho<br><span class="en">(Date)</span></p><p>' . ($departure_date ? date('d/m/Y', strtotime($departure_date)) : '-') . '</p></td>';
    $html .= '<td><p class="bold">Aeroporto Origem<br><span class="en">(Departure)</span></p><p>' . esc_html($departure_airport_code) . '</p></td>';
    $html .= '<td><p class="bold">Aeroporto Destino<br><span class="en">(Offloading)</span></p><p>' . esc_html($arrival_airport_code) . '</p></td></tr>';
    $html .= '<tr><td><p class="bold">Qtd Itens<br><span class="en">(Quantity)</span></p><p>' . $packages_count . '</p></td>';
    $html .= '<td colspan="2" class="center" style="vertical-align:middle;">' . $barcode_img . '<br><span class="bold">' . esc_html($unit_code) . '</span></td></tr>';
    $html .= '<tr><td><p class="bold">Peso Kg<br><span class="en">(Weight Kg)</span></p><p>' . number_format($total_weight / 1000, 2) . '</p></td>';
    $html .= '<td colspan="2"></td></tr>';
    $html .= '</table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper([0, 0, 623.6, 311.8]); // ~220mm x 110mm
    $dompdf->loadHtml($html);
    $dompdf->render();

    return $dompdf->output();
}

// ============================================================
// FATURAS - CRIAR
// ============================================================
function brz_api_create_bill(WP_REST_Request $request) {
    $body = $request->get_json_params();

    $container_ids = $body['containerIds'] ?? [];
    if (!is_array($container_ids) || empty($container_ids)) {
        return new WP_REST_Response(['success' => false, 'error' => 'containerIds deve ser um array não vazio de IDs de containers do WordPress'], 400);
    }

    // Validar containers e pegar dispatch numbers
    $dispatch_numbers = [];
    foreach ($container_ids as $cid) {
        $cid = intval($cid);
        $post = get_post($cid);
        if (!$post || $post->post_type !== 'container') {
            return new WP_REST_Response(['success' => false, 'error' => "Container ID {$cid} não encontrado"], 400);
        }
        $unit_code = get_post_meta($cid, '_unit_code', true);
        if (empty($unit_code)) {
            return new WP_REST_Response(['success' => false, 'error' => "Container ID {$cid} não possui unit_code"], 400);
        }
        $bill_id = get_post_meta($cid, '_bill_id', true);
        if (!empty($bill_id)) {
            return new WP_REST_Response(['success' => false, 'error' => "Container ID {$cid} já está vinculado a uma fatura"], 400);
        }
        $dn = get_post_meta($cid, '_dispatch_number', true);
        $dispatch_numbers[] = intval($dn);
    }

    // Criar post da fatura
    $bill_post_id = wp_insert_post([
        'post_type' => 'bill',
        'post_status' => 'publish',
        'post_title' => 'Fatura - ' . implode(', ', $dispatch_numbers),
    ]);

    if (is_wp_error($bill_post_id)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Falha ao criar fatura no WordPress: ' . $bill_post_id->get_error_message()], 500);
    }

    // Vincular containers à fatura
    foreach ($container_ids as $cid) {
        update_post_meta(intval($cid), '_bill_id', $bill_post_id);
    }
    update_post_meta($bill_post_id, '_dispatch_numbers', $dispatch_numbers);

    // Chamar API Correios para gerar CN38
    $bill_data = ['dispatchNumbers' => $dispatch_numbers];

    try {
        $correios = new WPR_Correios_Service();
        update_post_meta($bill_post_id, '_debug_request_body', $bill_data);
        
        $response = $correios->create_bill_async($bill_data);
        $request_id = $response->requestId;

        // Polling para resultado
        $max_attempts = 60;
        $attempt = 0;
        $status = 'Processing';
        $status_response = null;
        
        while ($status === 'Processing' && $attempt < $max_attempts) {
            sleep(2);
            $status_response = $correios->check_bill_status($request_id);
            $status = $status_response->requestStatus ?? 'Error';
            $attempt++;
        }

        update_post_meta($bill_post_id, '_debug_response_body', $status_response);

        if ($status === 'Error') {
            $error_msg = $status_response->errorMessage ?? 'Erro desconhecido';
            update_post_meta($bill_post_id, '_debug_error_message', $error_msg);
            return new WP_REST_Response(['success' => false, 'error' => $error_msg, 'wp_post_id' => $bill_post_id], 500);
        }

        if ($status === 'Processing') {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Timeout: fatura ainda em processamento',
                'wp_post_id' => $bill_post_id,
                'request_id' => $request_id,
            ], 202);
        }

        $cn38_code = $status_response->cn38Code ?? '';
        update_post_meta($bill_post_id, '_cn38_code', $cn38_code);
        update_post_meta($bill_post_id, '_cn38_code_date', current_time('mysql'));

        return new WP_REST_Response([
            'success' => true,
            'cn38_code' => $cn38_code,
            'wp_post_id' => $bill_post_id,
            'dispatch_numbers' => $dispatch_numbers,
        ], 200);

    } catch (Exception $e) {
        update_post_meta($bill_post_id, '_debug_error_message', $e->getMessage());
        return new WP_REST_Response(['success' => false, 'error' => $e->getMessage(), 'wp_post_id' => $bill_post_id], 500);
    }
}

// ============================================================
// FATURAS - LISTAR
// ============================================================
function brz_api_list_bills(WP_REST_Request $request) {
    $per_page = intval($request->get_param('per_page') ?: 50);
    $page = intval($request->get_param('page') ?: 1);

    $args = [
        'post_type' => 'bill',
        'posts_per_page' => min($per_page, 200),
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    // Filtro: sem embarque
    if ($request->get_param('without_departure') === '1') {
        $args['meta_query'] = [
            'relation' => 'AND',
            ['key' => '_cn38_code', 'compare' => 'EXISTS'],
            ['key' => '_cn38_code', 'value' => '', 'compare' => '!='],
            [
                'relation' => 'OR',
                ['key' => '_departure_id', 'compare' => 'NOT EXISTS'],
                ['key' => '_departure_id', 'value' => '', 'compare' => '='],
                ['key' => '_departure_id', 'value' => '0', 'compare' => '='],
            ],
        ];
    }

    $query = new WP_Query($args);
    $bills = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $bills[] = [
                'wp_post_id' => $id,
                'cn38_code' => get_post_meta($id, '_cn38_code', true),
                'dispatch_numbers' => get_post_meta($id, '_dispatch_numbers', true) ?: [],
                'departure_id' => get_post_meta($id, '_departure_id', true),
                'created_at' => get_the_date('Y-m-d H:i:s'),
            ];
        }
    }
    wp_reset_postdata();

    return new WP_REST_Response([
        'success' => true,
        'data' => $bills,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
    ], 200);
}

// ============================================================
// FATURAS - PDF
// ============================================================
function brz_api_bill_pdf(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'bill') {
        return new WP_REST_Response(['success' => false, 'error' => 'Fatura não encontrada'], 404);
    }

    $cn38_code = get_post_meta($post_id, '_cn38_code', true);
    if (empty($cn38_code)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Fatura sem código CN38'], 400);
    }

    try {
        $pdf_content = brz_capture_bill_pdf($post_id);
        if ($pdf_content === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF da fatura'], 500);
        }

        add_filter('rest_pre_serve_request', function($served, $result) use ($pdf_content, $cn38_code) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="fatura_' . $cn38_code . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return new WP_REST_Response();
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
}

/**
 * Captura o PDF da fatura (Delivery Bill / CN38).
 */
function brz_capture_bill_pdf($post_id) {
    if (!class_exists('Dompdf\\Dompdf')) {
        $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';
        if (file_exists($plugin_dir . '/vendor/autoload.php')) {
            require_once $plugin_dir . '/vendor/autoload.php';
        }
    }
    if (!class_exists('Dompdf\\Dompdf')) return false;

    $cn38_code = get_post_meta($post_id, '_cn38_code', true);
    $cn38_code_date = get_post_meta($post_id, '_cn38_code_date', true);
    $departure_id = get_post_meta($post_id, '_departure_id', true);
    
    $flight_list = $departure_id ? (get_post_meta($departure_id, '_flight_list', true) ?: []) : [];
    $flight_number = $flight_list['flightNumber'] ?? '';
    $airline_code = $flight_list['airlineCode'] ?? '';
    $departure_date = $flight_list['departureDate'] ?? '';
    $departure_airport_code = $flight_list['departureAirportCode'] ?? '';
    $arrival_airport_code = $flight_list['arrivalAirportCode'] ?? '';

    // Buscar nome da companhia aérea
    $airline_name = $airline_code;
    try {
        $correios = new WPR_Correios_Service();
        $airlines = $correios->get_airline_list();
        if (is_array($airlines)) {
            foreach ($airlines as $al) {
                if (isset($al->code) && $al->code == $airline_code) {
                    $airline_name = $al->name;
                    break;
                }
            }
        }
    } catch (Exception $e) {}

    $sender_contract = get_option('wpr_correios_sender_contract', '');

    // Buscar containers vinculados a esta fatura
    $containers = [];
    $total_weight = 0;
    $container_query = new WP_Query(['post_type' => 'container', 'meta_query' => [['key' => '_bill_id', 'value' => $post_id]], 'posts_per_page' => -1]);
    if ($container_query->have_posts()) {
        while ($container_query->have_posts()) {
            $container_query->the_post();
            $cid = get_the_ID();
            $c_weight = 0;
            $pkg_query = new WP_Query(['post_type' => 'package', 'meta_query' => [['key' => '_container_id', 'value' => $cid]], 'posts_per_page' => -1]);
            if ($pkg_query->have_posts()) {
                while ($pkg_query->have_posts()) {
                    $pkg_query->the_post();
                    $c_weight += floatval(get_post_meta(get_the_ID(), '_total_weight', true));
                }
            }
            wp_reset_postdata();
            $containers[] = [
                'serial_number' => get_post_meta($cid, '_dispatch_number', true),
                'unit_code' => get_post_meta($cid, '_unit_code', true),
                'total_weight' => $c_weight,
                'service_subclass_code' => get_post_meta($cid, '_service_subclass_code', true),
                'unit_type' => get_post_meta($cid, '_unit_type', true),
            ];
            $total_weight += $c_weight;
        }
    }
    wp_reset_postdata();

    $origin_operator_name = !empty($containers) ? get_post_meta($container_query->posts[0]->ID ?? 0, '_origin_operator_name', true) : '';
    $subclass_description = (!empty($containers) && ($containers[0]['service_subclass_code'] ?? '') == 'NX') ? 'PACKET STANDARD' : 'PACKET EXPRESS';
    $unit_type = !empty($containers) ? ($containers[0]['unit_type'] ?? '2') : '2';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page { margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .en { font-size: 7pt; color: #555; }
    </style></head><body>';
    
    $html .= '<table><tr><td class="center bold" colspan="5">FATURA DE ENTREGA / DELIVERY BILL</td></tr>';
    $html .= '<tr><td colspan="2"><p class="bold">OPERADOR DE ORIGEM<br><span class="en">(Office of Origin)</span></p><p>' . esc_html($origin_operator_name) . '</p></td>';
    $html .= '<td colspan="3"><p class="bold">N° FATURA<br><span class="en">(Delivery Bill #)</span></p><p>' . esc_html($cn38_code) . '</p></td></tr>';
    $html .= '<tr><td colspan="2"><p class="bold">CIA AÉREA<br><span class="en">(Airline)</span></p><p>' . esc_html($airline_name) . '</p></td>';
    $html .= '<td colspan="3"><p class="bold">N° VOO<br><span class="en">(Flight #)</span></p><p>' . esc_html($flight_number) . '</p></td></tr>';
    $html .= '<tr><td colspan="2"><p class="bold">DATA<br><span class="en">(Date)</span></p><p>' . ($cn38_code_date ? date('d/m/Y', strtotime($cn38_code_date)) : '-') . '</p></td>';
    $html .= '<td colspan="3"><p class="bold">CONTRATO<br><span class="en">(Contract)</span></p><p>' . esc_html($sender_contract) . '</p></td></tr>';
    $html .= '<tr><td colspan="2"><p class="bold">DATA PARTIDA<br><span class="en">(Departure)</span></p><p>' . ($departure_date ? date('d/m/Y H:i', strtotime($departure_date)) : '-') . '</p></td>';
    $html .= '<td class="center"><p class="bold">SERVIÇO</p><p>' . esc_html($subclass_description) . '</p></td>';
    $html .= '<td class="center"><p class="bold">PARTIDA</p><p>' . esc_html($departure_airport_code) . '</p></td>';
    $html .= '<td class="center"><p class="bold">CHEGADA</p><p>' . esc_html($arrival_airport_code) . '</p></td></tr>';
    
    // Dados do despacho
    $html .= '<tr><td colspan="5" class="center bold">DADOS DO DESPACHO / DISPATCH DATA</td></tr>';
    $html .= '<tr><td class="bold">N° Despacho</td><td class="bold">N° Serial Mala</td><td class="bold">Peso Bruto (kg)</td><td class="bold">Lacre</td><td class="bold">Obs</td></tr>';
    
    foreach ($containers as $c) {
        $html .= '<tr><td>' . esc_html($cn38_code) . '</td><td>' . esc_html($c['serial_number']) . '</td><td>' . number_format($c['total_weight'] / 1000, 2) . '</td><td>' . esc_html($c['unit_code']) . '</td><td></td></tr>';
    }
    // Preencher linhas vazias
    for ($i = count($containers); $i < 6; $i++) {
        $html .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
    }
    
    $html .= '<tr><td class="bold">TOTAL</td><td>' . count($containers) . '</td><td>' . number_format($total_weight / 1000, 2) . '</td><td></td><td></td></tr>';
    $html .= '</table>';
    
    // Assinaturas
    $html .= '<table><tr><td class="center bold" colspan="3">ASSINATURAS / SIGNATURES</td></tr>';
    $html .= '<tr><td style="height:60px;"><p class="bold">Operador Origem</p></td><td><p class="bold">Transportador</p></td><td><p class="bold">Operador Destino</p></td></tr>';
    $html .= '</table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4');
    $dompdf->loadHtml($html);
    $dompdf->render();

    return $dompdf->output();
}

// ============================================================
// EMBARQUES - CRIAR
// ============================================================
function brz_api_create_departure(WP_REST_Request $request) {
    $body = $request->get_json_params();

    $required = ['billIds', 'flightNumber', 'airlineCode', 'departureDate', 'departureAirportCode', 'arrivalDate', 'arrivalAirportCode'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            return new WP_REST_Response(['success' => false, 'error' => "Campo obrigatório ausente: {$field}"], 400);
        }
    }

    $bill_ids = $body['billIds'];
    if (!is_array($bill_ids) || empty($bill_ids)) {
        return new WP_REST_Response(['success' => false, 'error' => 'billIds deve ser um array não vazio'], 400);
    }

    // Coletar CN38 codes das faturas
    $cn38_code_list = [];
    foreach ($bill_ids as $bid) {
        $bid = intval($bid);
        $post = get_post($bid);
        if (!$post || $post->post_type !== 'bill') {
            return new WP_REST_Response(['success' => false, 'error' => "Fatura ID {$bid} não encontrada"], 400);
        }
        $cn38 = get_post_meta($bid, '_cn38_code', true);
        if (empty($cn38)) {
            return new WP_REST_Response(['success' => false, 'error' => "Fatura ID {$bid} não possui código CN38"], 400);
        }
        $dep = get_post_meta($bid, '_departure_id', true);
        if (!empty($dep)) {
            return new WP_REST_Response(['success' => false, 'error' => "Fatura ID {$bid} já está vinculada a um embarque"], 400);
        }
        $cn38_code_list[] = $cn38;
    }

    // Montar flight list
    $flight_list = [
        'flightNumber' => intval($body['flightNumber']),
        'airlineCode' => sanitize_text_field($body['airlineCode']),
        'departureDate' => sanitize_text_field($body['departureDate']),
        'departureAirportCode' => strtoupper(sanitize_text_field($body['departureAirportCode'])),
        'arrivalDate' => sanitize_text_field($body['arrivalDate']),
        'arrivalAirportCode' => strtoupper(sanitize_text_field($body['arrivalAirportCode'])),
    ];

    // Criar post de embarque
    $dep_post_id = wp_insert_post([
        'post_type' => 'departure',
        'post_status' => 'publish',
        'post_title' => 'Embarque - Voo ' . $body['flightNumber'],
    ]);

    if (is_wp_error($dep_post_id)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Falha ao criar embarque no WordPress'], 500);
    }

    update_post_meta($dep_post_id, '_flight_list', $flight_list);
    update_post_meta($dep_post_id, '_cn38_code_list', $cn38_code_list);

    // Vincular faturas ao embarque
    foreach ($bill_ids as $bid) {
        update_post_meta(intval($bid), '_departure_id', $dep_post_id);
    }

    // Confirmar embarque via API Correios
    $departure_data = [
        'cn38CodeList' => $cn38_code_list,
        'flightList' => [$flight_list],
    ];

    try {
        $correios = new WPR_Correios_Service();
        update_post_meta($dep_post_id, '_debug_request_body', $departure_data);
        $response = $correios->confirm_departure($departure_data);
        
        if ($response) {
            update_post_meta($dep_post_id, '_debug_response_body', $response);
            update_post_meta($dep_post_id, '_departure_status', 'confirmed');

            return new WP_REST_Response([
                'success' => true,
                'wp_post_id' => $dep_post_id,
                'status' => 'confirmed',
                'cn38_codes' => $cn38_code_list,
                'flight' => $flight_list,
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => 'API não retornou resposta válida',
            'wp_post_id' => $dep_post_id,
        ], 500);

    } catch (Exception $e) {
        update_post_meta($dep_post_id, '_debug_error_message', $e->getMessage());
        return new WP_REST_Response([
            'success' => false,
            'error' => $e->getMessage(),
            'wp_post_id' => $dep_post_id,
        ], 500);
    }
}

// ============================================================
// EMBARQUES - LISTAR
// ============================================================
function brz_api_list_departures(WP_REST_Request $request) {
    $per_page = intval($request->get_param('per_page') ?: 50);
    $page = intval($request->get_param('page') ?: 1);

    $args = [
        'post_type' => 'departure',
        'posts_per_page' => min($per_page, 200),
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    $query = new WP_Query($args);
    $departures = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $flight = get_post_meta($id, '_flight_list', true) ?: [];
            $departures[] = [
                'wp_post_id' => $id,
                'status' => get_post_meta($id, '_departure_status', true),
                'cn38_codes' => get_post_meta($id, '_cn38_code_list', true) ?: [],
                'flight' => $flight,
                'created_at' => get_the_date('Y-m-d H:i:s'),
            ];
        }
    }
    wp_reset_postdata();

    return new WP_REST_Response([
        'success' => true,
        'data' => $departures,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
    ], 200);
}
