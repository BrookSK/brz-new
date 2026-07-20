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

    register_rest_route($namespace, '/packages/fix-meta/(?P<id>\d+)', [
        'methods'  => 'POST',
        'callback' => 'brz_api_fix_package_meta',
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

    // === DELETAR/DESVINCULAR ===
    register_rest_route($namespace, '/containers/delete/(?P<id>\d+)', [
        'methods'  => 'POST, DELETE',
        'callback' => 'brz_api_delete_container',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/bills/delete/(?P<id>\d+)', [
        'methods'  => 'POST, DELETE',
        'callback' => 'brz_api_delete_bill',
        'permission_callback' => 'brz_api_check_auth',
    ]);

    register_rest_route($namespace, '/departures/delete/(?P<id>\d+)', [
        'methods'  => 'POST, DELETE',
        'callback' => 'brz_api_delete_departure',
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
        $test_mode = get_option('wpr_correios_test_mode', '0') === '1';
        $ambiente = $test_mode ? 'HOMOLOGACAO' : 'PRODUCAO';
        $api_url = $test_mode ? 'https://apihom.correios.com.br' : 'https://api.correios.com.br';
        $username = get_option('wpr_correios_username', '');
        $cartao = get_option('wpr_correios_numero', '');
        return new WP_REST_Response([
            'success' => true,
            'data' => $balance,
            'ambiente' => $ambiente,
            'api_url' => $api_url,
            'credenciais' => [
                'username' => $username,
                'cartao_postagem' => $cartao,
            ],
        ], 200);
    } catch (Exception $e) {
        $test_mode = get_option('wpr_correios_test_mode', '0') === '1';
        $ambiente = $test_mode ? 'HOMOLOGACAO' : 'PRODUCAO';
        $api_url = $test_mode ? 'https://apihom.correios.com.br' : 'https://api.correios.com.br';
        $username = get_option('wpr_correios_username', '');
        $cartao = get_option('wpr_correios_numero', '');
        return new WP_REST_Response([
            'success' => false,
            'error' => $e->getMessage(),
            'ambiente' => $ambiente,
            'api_url' => $api_url,
            'credenciais' => [
                'username' => $username,
                'cartao_postagem' => $cartao,
            ],
        ], 500);
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
        $test_mode = get_option('wpr_correios_test_mode', '0') === '1';
        $ambiente = $test_mode ? 'HOMOLOGACAO' : 'PRODUCAO';
        $api_url = $test_mode ? 'https://apihom.correios.com.br' : 'https://api.correios.com.br';
        error_log('[BRZ-ETIQUETAS] Criando pacote | Ambiente: ' . $ambiente . ' | URL: ' . $api_url . '/packet/v1/packages | CustomerControlCode: ' . ($body['customerControlCode'] ?? ''));
        
        $response = $correios->create_package($api_payload);

        if (empty($response) || empty($response[0]->trackingNumber)) {
            error_log('[BRZ-ETIQUETAS] ERRO | Ambiente: ' . $ambiente . ' | API não retornou tracking number | Response: ' . json_encode($response));
            return new WP_REST_Response(['success' => false, 'error' => 'API não retornou tracking number', 'raw' => $response, 'ambiente' => $ambiente], 500);
        }

        $tracking = $response[0]->trackingNumber;
        error_log('[BRZ-ETIQUETAS] SUCESSO | Ambiente: ' . $ambiente . ' | Tracking: ' . $tracking . ' | CustomerControlCode: ' . ($body['customerControlCode'] ?? ''));

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
        if (!empty($body['pedidoIdLocal'])) {
            update_post_meta($post_id, '_pedido_id_local', intval($body['pedidoIdLocal']));
        }
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
            'ambiente' => $ambiente,
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
                'recipient_name' => get_post_meta($id, '_recipient_name', true),
                'pedido_id_local' => get_post_meta($id, '_pedido_id_local', true),
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
// PACOTES - FIX META (corrigir items e pedido_id_local)
// ============================================================
function brz_api_fix_package_meta(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'package') {
        return new WP_REST_Response(['success' => false, 'error' => 'Pacote não encontrado'], 404);
    }

    $body = $request->get_json_params();
    $fixed = [];

    // Fix pedido_id_local: recebe do painel e atualiza
    if (!empty($body['pedidoIdLocal'])) {
        $pid = intval($body['pedidoIdLocal']);
        update_post_meta($post_id, '_pedido_id_local', $pid);
        update_post_meta($post_id, '_package_order_id', $pid);
        $fixed[] = 'pedido_id_local=' . $pid;
    }

    // Fix items_json: se estiver vazio, usar items do body OU reconstruir do _debug_request_body
    $items_json = get_post_meta($post_id, '_items_json', true);
    $items_decoded = $items_json ? json_decode($items_json, true) : [];
    if (empty($items_decoded)) {
        // Primeiro: tentar dos items enviados pelo painel
        if (!empty($body['items']) && is_array($body['items'])) {
            update_post_meta($post_id, '_items_json', wp_json_encode($body['items']));
            $fixed[] = 'items_json (from request body, ' . count($body['items']) . ' items)';
        } else {
            // Segundo: tentar do _debug_request_body
            $debug_request = get_post_meta($post_id, '_debug_request_body', true);
            if (is_array($debug_request) && !empty($debug_request['packageList'][0]['items'])) {
                $items_from_debug = $debug_request['packageList'][0]['items'];
                update_post_meta($post_id, '_items_json', wp_json_encode($items_from_debug));
                $fixed[] = 'items_json (from debug_request_body, ' . count($items_from_debug) . ' items)';
            } else {
                $fixed[] = 'items_json FAILED - no source found';
            }
        }
    }

    // Fix recipient_name: se recebido do painel
    if (!empty($body['recipientName'])) {
        $current = get_post_meta($post_id, '_recipient_name', true);
        if (empty($current)) {
            update_post_meta($post_id, '_recipient_name', sanitize_text_field($body['recipientName']));
            $fixed[] = 'recipient_name';
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'fixed' => $fixed,
        'wp_post_id' => $post_id,
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

    // Fix automático: se _items_json está vazio, reconstruir a partir do _debug_request_body
    $items_json = get_post_meta($post_id, '_items_json', true);
    $items_decoded = $items_json ? json_decode($items_json, true) : [];
    if (empty($items_decoded)) {
        $debug_request = get_post_meta($post_id, '_debug_request_body', true);
        if (is_array($debug_request) && !empty($debug_request['packageList'][0]['items'])) {
            $items_from_debug = $debug_request['packageList'][0]['items'];
            update_post_meta($post_id, '_items_json', wp_json_encode($items_from_debug));
        }
    }

    // Fix automático: se _pedido_id_local não existe, tentar extrair do request body
    $pedido_id_local = get_post_meta($post_id, '_pedido_id_local', true);
    if (empty($pedido_id_local)) {
        $debug_request = get_post_meta($post_id, '_debug_request_body', true);
        if (is_array($debug_request) && !empty($debug_request['packageList'][0]['pedidoIdLocal'])) {
            $pedido_id_local = intval($debug_request['packageList'][0]['pedidoIdLocal']);
            update_post_meta($post_id, '_pedido_id_local', $pedido_id_local);
        }
    }

    // Atualizar _package_order_id para exibir o ID local na etiqueta (Order #)
    if (!empty($pedido_id_local)) {
        update_post_meta($post_id, '_package_order_id', $pedido_id_local);
    }

    try {
        $envios = new WPR_Envios();
        $pdf_content = $envios->generate_pdf_output($post_id);

        if (empty($pdf_content)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF do pacote'], 500);
        }

        add_filter('rest_pre_serve_request', function($served) use ($pdf_content, $tracking_code) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="etiqueta_' . $tracking_code . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return new WP_REST_Response();
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
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

    try {
        $container = new WPR_Container();
        $pdf_content = $container->generate_pdf_output($post_id);

        if (empty($pdf_content)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF do container'], 500);
        }

        add_filter('rest_pre_serve_request', function($served) use ($pdf_content, $unit_code) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="container_' . $unit_code . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return new WP_REST_Response();
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
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

    $departure_id = get_post_meta($post_id, '_departure_id', true);
    if (empty($departure_id)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Fatura sem embarque vinculado (necessário para PDF)'], 400);
    }

    try {
        $bill = new WPR_Bill();
        $pdf_content = $bill->generate_pdf_output($post_id);

        if (empty($pdf_content)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF da fatura'], 500);
        }

        add_filter('rest_pre_serve_request', function($served) use ($pdf_content, $cn38_code) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="fatura_' . $cn38_code . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;
            return true;
        }, 10, 2);

        return new WP_REST_Response();
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
    }
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
        
        update_post_meta($dep_post_id, '_debug_response_body', $response);

        // confirm_departure retorna null/false em caso de sucesso (HTTP 200 sem body)
        // ou retorna objeto com msgs em caso de erro
        if ($response === null || $response === false || $response === '') {
            // Sucesso - API retornou 200 sem body (comportamento documentado)
            update_post_meta($dep_post_id, '_departure_status', 'confirmed');
            return new WP_REST_Response([
                'success' => true,
                'wp_post_id' => $dep_post_id,
                'status' => 'confirmed',
                'cn38_codes' => $cn38_code_list,
                'flight' => $flight_list,
            ], 200);
        }

        if (is_object($response) && isset($response->msgs)) {
            $error_msg = implode('; ', (array) $response->msgs);
            update_post_meta($dep_post_id, '_departure_status', 'error');
            update_post_meta($dep_post_id, '_debug_error_message', $error_msg);
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Erro ao confirmar embarque: ' . $error_msg,
                'wp_post_id' => $dep_post_id,
            ], 400);
        }

        // Se chegou aqui com resposta, consideramos sucesso (API pode retornar objeto vazio)
        update_post_meta($dep_post_id, '_departure_status', 'confirmed');
        return new WP_REST_Response([
            'success' => true,
            'wp_post_id' => $dep_post_id,
            'status' => 'confirmed',
            'cn38_codes' => $cn38_code_list,
            'flight' => $flight_list,
            'raw_response' => $response,
        ], 200);

    } catch (Exception $e) {
        update_post_meta($dep_post_id, '_departure_status', 'error');
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
                'error_message' => get_post_meta($id, '_debug_error_message', true) ?: null,
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


// ============================================================
// CONTAINERS - DELETAR/DESVINCULAR
// ============================================================
function brz_api_delete_container(WP_REST_Request $request) {
    $prev_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    
    try {
        global $wpdb;
        $post_id = intval($request->get_param('id'));
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'container') {
            restore_error_handler();
            return new WP_REST_Response(['success' => false, 'error' => 'Container não encontrado'], 404);
        }

        $bill_id = get_post_meta($post_id, '_bill_id', true);
        if (!empty($bill_id)) {
            restore_error_handler();
            return new WP_REST_Response(['success' => false, 'error' => 'Container vinculado a fatura. Delete a fatura primeiro.'], 400);
        }

        $unit_code = get_post_meta($post_id, '_unit_code', true);
        $tracking_codes = get_post_meta($post_id, '_tracking_codes', true);

        // Desvincular pacotes deste container
        if (is_array($tracking_codes)) {
            foreach ($tracking_codes as $tc) {
                $pkg_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_correios_tracking_code' AND meta_value = %s LIMIT 1",
                    $tc
                ));
                if ($pkg_id) {
                    $wpdb->delete($wpdb->postmeta, ['post_id' => $pkg_id, 'meta_key' => '_container_id']);
                }
            }
        }

        // Remover TODOS os metadados via SQL direto (evita o bug do clone de Exception)
        $wpdb->delete($wpdb->postmeta, ['post_id' => $post_id]);
        
        // Deletar o post direto via SQL tambem
        $wpdb->delete($wpdb->posts, ['ID' => $post_id]);
        
        // Limpar cache
        clean_post_cache($post_id);

        restore_error_handler();
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Container deletado e pacotes desvinculados.',
            'unit_code' => $unit_code ?: null,
        ], 200);
        
    } catch (\Throwable $e) {
        restore_error_handler();
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Erro interno: ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine(),
        ], 500);
    }
}

// ============================================================
// FATURAS - DELETAR/DESVINCULAR
// ============================================================
function brz_api_delete_bill(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'bill') {
        return new WP_REST_Response(['success' => false, 'error' => 'Fatura não encontrada'], 404);
    }

    // Desvincular containers desta fatura
    $containers = get_posts([
        'post_type' => 'container',
        'meta_key' => '_bill_id',
        'meta_value' => $post_id,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    foreach ($containers as $cid) {
        delete_post_meta($cid, '_bill_id');
    }

    // Desvincular embarque se tiver
    $departure_id = get_post_meta($post_id, '_departure_id', true);
    if ($departure_id) {
        delete_post_meta($post_id, '_departure_id');
    }

    // Deletar o post da fatura
    wp_delete_post($post_id, true);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Fatura deletada e containers desvinculados.',
        'containers_unlinked' => count($containers),
    ], 200);
}


// ============================================================
// EMBARQUES - DELETAR
// ============================================================
function brz_api_delete_departure(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'departure') {
        return new WP_REST_Response(['success' => false, 'error' => 'Embarque não encontrado'], 404);
    }

    // Desvincular faturas deste embarque
    $cn38_codes = get_post_meta($post_id, '_cn38_code_list', true) ?: [];
    $bills = get_posts([
        'post_type' => 'bill',
        'meta_key' => '_departure_id',
        'meta_value' => $post_id,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    foreach ($bills as $bid) {
        delete_post_meta($bid, '_departure_id');
    }

    // Deletar o post do embarque
    wp_delete_post($post_id, true);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Embarque deletado e faturas desvinculadas.',
        'bills_unlinked' => count($bills),
    ], 200);
}
