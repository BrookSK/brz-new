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

    // Verificar se é um pacote criado via API (tem _recipient_name nos meta)
    // ou um pacote criado via WooCommerce (tem _package_order_id com order válida)
    $order_id = get_post_meta($post_id, '_package_order_id', true);
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;

    if ($order) {
        // Pacote original do WooCommerce - usar a lógica nativa do plugin
        // Simular o generate_pdf da WPR_Envios, mas com output() em vez de stream()
        $pdf_content = brz_generate_native_package_pdf($post_id, $order);
    } else {
        // Pacote criado via API - usar metadados salvos
        $pdf_content = brz_generate_api_package_pdf($post_id);
    }

    if ($pdf_content === false || empty($pdf_content)) {
        return new WP_REST_Response(['success' => false, 'error' => 'Falha ao gerar PDF'], 500);
    }

    add_filter('rest_pre_serve_request', function($served) use ($pdf_content, $tracking_code) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="etiqueta_' . $tracking_code . '.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        echo $pdf_content;
        return true;
    }, 10, 2);

    return new WP_REST_Response();
}

/**
 * Gera PDF para pacote criado via WooCommerce (tem order real).
 * Replica exatamente a lógica do WPR_Envios->generate_pdf() mas com $dompdf->output().
 */
function brz_generate_native_package_pdf($package_id, $order) {
    $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';
    if (!class_exists('Dompdf\\Dompdf')) {
        if (file_exists($plugin_dir . '/vendor/autoload.php')) {
            require_once $plugin_dir . '/vendor/autoload.php';
        }
    }
    if (!class_exists('Dompdf\\Dompdf') || !class_exists('Milon\\Barcode\\DNS1D')) return false;

    $order_id = $order->get_id();
    $tracking_code = get_post_meta($package_id, '_correios_tracking_code', true);

    $recipient_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
    $recipient_document_type = get_post_meta($order_id, '_billing_document_type', true);
    $recipient_document_number = get_post_meta($order_id, '_billing_cpf', true);
    $recipient_address = $order->get_shipping_address_1();
    $recipient_address_complement = $order->get_shipping_address_2();
    $recipient_address_number = get_post_meta($order_id, '_shipping_number', true);
    $recipient_city_name = $order->get_shipping_city();
    $recipient_state = $order->get_shipping_state();
    $recipient_zip_code = $order->get_shipping_postcode();

    $width = get_post_meta($package_id, '_package_width', true);
    $height = get_post_meta($package_id, '_package_height', true);
    $length = get_post_meta($package_id, '_package_length', true);
    $total_weight = get_post_meta($package_id, '_total_weight', true);
    $distribution_modality = get_post_meta($package_id, '_distribution_modality', true);
    $tax_payment_method = get_post_meta($package_id, '_tax_payment_method', true) ?: 'DDU';
    $freight_paid_value = get_post_meta($package_id, '_freight_paid_value', true);
    $insurance_paid_value = get_post_meta($package_id, '_insurance_paid_value', true) ?: 0;

    $modality_description = ($distribution_modality == '33170') ? 'PACKET EXPRESS' : 'PACKET STANDARD';
    $modality_image_path = ($distribution_modality == '33170') 
        ? $plugin_dir . '/assets/images/packet-express.png' 
        : $plugin_dir . '/assets/images/packet-standard.png';

    $items = [];
    $total_weight_kg = floatval($total_weight) / 1000;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $items[] = [
            'hsCode' => $item->get_meta('_ncm'),
            'description' => $item->get_meta('_product_name') ?: $item->get_name(),
            'quantity' => $item->get_quantity(),
            'value' => $item->get_meta('_declaration_value') ?: $item->get_total(),
            'weight' => $product ? $product->get_weight() : 0,
        ];
    }

    return brz_render_package_pdf_html($package_id, $tracking_code, $recipient_name, $recipient_document_type, $recipient_document_number, $recipient_address, $recipient_address_number, $recipient_address_complement, $recipient_city_name, $recipient_state, $recipient_zip_code, $width, $height, $length, $total_weight, $distribution_modality, $modality_description, $modality_image_path, $tax_payment_method, $freight_paid_value, $insurance_paid_value, $items, $order_id);
}

/**
 * Gera PDF para pacote criado via API (sem WooCommerce order).
 * Usa metadados salvos no post.
 */
function brz_generate_api_package_pdf($post_id) {
    $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';
    if (!class_exists('Dompdf\\Dompdf')) {
        if (file_exists($plugin_dir . '/vendor/autoload.php')) {
            require_once $plugin_dir . '/vendor/autoload.php';
        }
    }
    if (!class_exists('Dompdf\\Dompdf') || !class_exists('Milon\\Barcode\\DNS1D')) return false;

    $tracking_code = get_post_meta($post_id, '_correios_tracking_code', true);
    $order_id = get_post_meta($post_id, '_package_order_id', true);

    $recipient_name = get_post_meta($post_id, '_recipient_name', true);
    $recipient_document_type = get_post_meta($post_id, '_recipient_document_type', true) ?: 'CPF';
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
    $distribution_modality = get_post_meta($post_id, '_distribution_modality', true) ?: '33162';
    $tax_payment_method = get_post_meta($post_id, '_tax_payment_method', true) ?: 'DDU';
    $freight_paid_value = get_post_meta($post_id, '_freight_paid_value', true) ?: 0;
    $insurance_paid_value = get_post_meta($post_id, '_insurance_paid_value', true) ?: 0;

    $modality_description = ($distribution_modality == '33170') ? 'PACKET EXPRESS' : 'PACKET STANDARD';
    $modality_image_path = ($distribution_modality == '33170') 
        ? $plugin_dir . '/assets/images/packet-express.png' 
        : $plugin_dir . '/assets/images/packet-standard.png';

    $items_json = get_post_meta($post_id, '_items_json', true);
    $items = $items_json ? json_decode($items_json, true) : [];
    // Converter formato para o que o template espera
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'hsCode' => $item['hsCode'] ?? '',
            'description' => $item['description'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
            'value' => $item['value'] ?? 0,
            'weight' => 0,
        ];
    }

    return brz_render_package_pdf_html($post_id, $tracking_code, $recipient_name, $recipient_document_type, $recipient_document_number, $recipient_address, $recipient_address_number, $recipient_address_complement, $recipient_city_name, $recipient_state, $recipient_zip_code, $width, $height, $length, $total_weight, $distribution_modality, $modality_description, $modality_image_path, $tax_payment_method, $freight_paid_value, $insurance_paid_value, $formatted_items, $order_id);
}

/**
 * Renderiza o HTML da etiqueta e gera o PDF usando exatamente o mesmo template do plugin.
 * Retorna o PDF como string binária.
 */
function brz_render_package_pdf_html($package_id, $tracking_code, $recipient_name, $recipient_document_type, $recipient_document_number, $recipient_address, $recipient_address_number, $recipient_address_complement, $recipient_city_name, $recipient_state, $recipient_zip_code, $width, $height, $length, $total_weight, $distribution_modality, $modality_description, $modality_image_path, $tax_payment_method, $freight_paid_value, $insurance_paid_value, $items, $order_id) {
    
    $plugin_dir = WP_PLUGIN_DIR . '/woocommerce-package-redirect';

    $sender_name = get_option('wpr_correios_sender_name', '');
    $sender_address = get_option('wpr_correios_sender_address', '');
    $sender_address_number = get_option('wpr_correios_sender_address_number', '');
    $sender_zip_code = get_option('wpr_correios_sender_zip_code', '');
    $sender_city_name = get_option('wpr_correios_sender_city_name', '');
    $sender_state = get_option('wpr_correios_sender_state', '');
    $sender_country_code = get_option('wpr_correios_sender_country_code', 'US');
    $sender_contract = get_option('wpr_correios_sender_contract', '');

    $return_company_name = get_option('wpr_correios_return_company', '');
    $return_street = get_option('wpr_correios_return_street', '');
    $return_neighborhood = get_option('wpr_correios_return_neighborhood', '');
    $return_zip_code = get_option('wpr_correios_return_zip_code', '');
    $return_city = get_option('wpr_correios_return_city', '');
    $return_uf = get_option('wpr_correios_return_uf', '');

    $logo_path = $plugin_dir . '/assets/images/logo-transamerica.png';
    $correios_logo_path = $plugin_dir . '/assets/images/logo-correios.png';

    $barcode_generator = new \Milon\Barcode\DNS1D();
    $barcode_generator->setStorPath(sys_get_temp_dir() . '/');

    $total_weight_kg = floatval($total_weight) / 1000;
    $item_weight = count($items) > 0 ? $total_weight_kg / count($items) : 0;
    $items_suplementary = array_slice($items, 3);
    $items_main = array_slice($items, 0, 3);

    // Gerar usando o HTML inline (mesmo estilo do plugin)
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta - <?php echo $tracking_code; ?></title>
    <style>
        @page { margin: 0; width: 100mm; height: 150mm; }
        body { font-family: Arial, sans-serif; }
        body p { font-size: 8pt; margin: 0; }
        .header { margin: 2.5mm; position: relative; }
        .header p, .header strong { margin-top: 10px; font-size: 10pt; line-height: 2px; }
        .header .logo-container { width: 20mm; height: 20mm; }
        .header .logo-container img { vertical-align: middle; width: 100%; }
        .header .left .logo-container img { margin-top: 40%; }
        .header .right { position: absolute; top: 0; right: 0; }
        .header .right .logo-container { margin-left: auto; }
        .header .right .logo-container img { vertical-align: middle; width: auto; height: 100%; }
        .header .correios-service-info { text-align: right; }
        .tracking-number { position: relative; text-align: center; height: 18mm; width: 80mm; margin-bottom: 6mm; margin-left: 5mm; }
        .tracking-number p { font-size: 15pt; }
        .tracking-number .bar-code-container { height: 100%; width: 100%; }
        .tracking-number .bar-code-container img { width: 100%; height: 100%; }
        .tracking-number .service-class { position: absolute; font-size: 20pt; top: 9mm; left: 85mm; }
        .recipient { width: 100%; }
        .recipient p { font-size: 8pt; line-height: 10pt; }
        .recipient .recipient-data { width: 99.5%; border: solid 1px black; height: 26mm; position: relative; }
        .recipient .recipient-data p { padding-left: 2.5mm; font-size: 8.5pt; }
        .recipient .recipient-data .section-title { color: white; background-color: black; padding: 3px 2.5mm; display: inline-block; margin-bottom: 1.5mm; }
        .recipient .recipient-data .right { position: absolute; top: 2mm; right: 0.4mm; margin-right: 5mm; height: 18mm; width: 40mm; }
        .recipient .recipient-data .right .bar-code-container { height: 100%; width: 100%; margin-bottom: 1.5mm; }
        .recipient .recipient-data .right .bar-code-container img { width: 100%; height: 100%; }
        .recipient .recipient-data .right p { text-align: center; }
        .instructions { margin: 0 2.5mm; position: relative; height: 26mm; margin-bottom: 10px; }
        .instructions .return-section { width: 60mm; line-height: 0; }
        .instructions .return-section p { line-height: 12px; }
        .instructions .return-section .note { font-size: 6pt; line-height: 6px; }
        .instructions .right { position: absolute; top: 5mm; right: 2.5mm; }
        .instructions .right p { font-size: 10pt; }
        .customs-declaration { margin: 1mm 2.5mm 2.5mm 2.5mm; }
        .customs-declaration table { width: 100%; border-collapse: collapse; }
        .customs-declaration th, .customs-declaration td { border: 1px solid #000; font-size: 6pt; font-weight: normal; padding: 0; text-align: left; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <div class="logo-container">
                <img src="<?php echo function_exists('image_to_base64') ? image_to_base64($logo_path) : ''; ?>" alt=" ">
            </div>
            <div style="display: inline-block; position: absolute; top: -7.5mm; left: 25mm;">
                <div class="logo-container">
                    <img src="<?php echo function_exists('image_to_base64') ? image_to_base64($correios_logo_path) : ''; ?>" alt=" ">
                </div>
            </div>
        </div>
        <div class="right">
            <div class="logo-container">
                <img src="<?php echo function_exists('image_to_base64') ? image_to_base64($modality_image_path) : ''; ?>" alt=" ">
            </div>
            <div class="correios-service-info">
                <p><strong><?php echo $modality_description; ?></strong></p>
                <p>Contrato <strong><?php echo $sender_contract; ?></strong></p>
            </div>
        </div>
    </div>
    <div class="tracking-number">
        <p><strong><?php echo $tracking_code; ?></strong></p>
        <div class="bar-code-container">
            <img src="data:image/png;base64,<?php echo $barcode_generator->getBarcodePNG($tracking_code, 'C128'); ?>" alt=" ">
        </div>
        <p class="service-class"><strong>US</strong></p>
    </div>
    <div class="recipient">
        <div class="recipient-data">
            <div class="left" style="width:48mm;">
                <p class="section-title"><strong>DESTINATÁRIO</strong></p>
                <p><?php echo esc_html($recipient_name); ?></p>
                <p><?php echo esc_html($recipient_address); ?>, <?php echo esc_html($recipient_address_number); ?></p>
                <p><?php echo esc_html($recipient_address_complement); ?></p>
                <p><?php echo esc_html($recipient_city_name); ?>/<?php echo esc_html($recipient_state); ?></p>
                <p><?php echo esc_html($recipient_zip_code); ?></p>
            </div>
            <div class="right">
                <div class="bar-code-container">
                    <img src="data:image/png;base64,<?php echo $barcode_generator->getBarcodePNG($recipient_zip_code, 'C128'); ?>" alt=" ">
                </div>
                <p><?php echo esc_html($recipient_zip_code); ?></p>
            </div>
        </div>
    </div>
    <div class="instructions">
        <div class="return-section">
            <p class="note">Em caso de não entrega devolver para:</p>
            <p><?php echo esc_html($return_company_name); ?></p>
            <p><?php echo esc_html($return_street); ?> - <?php echo esc_html($return_neighborhood); ?></p>
            <p>CEP <?php echo esc_html($return_zip_code); ?> - <?php echo esc_html($return_city); ?>/<?php echo esc_html($return_uf); ?></p>
        </div>
        <div class="right">
            <p><strong>Peso: <?php echo number_format($total_weight_kg, 2); ?> kg</strong></p>
        </div>
    </div>
    <div class="customs-declaration">
        <table>
            <tr><th>NCM</th><th>Descrição</th><th>Qtd</th><th>Peso(kg)</th><th>Valor($)</th></tr>
            <?php foreach ($items_main as $item): ?>
            <tr>
                <td><?php echo esc_html($item['hsCode'] ?? ''); ?></td>
                <td><?php echo esc_html($item['description'] ?? ''); ?></td>
                <td><?php echo intval($item['quantity'] ?? 1); ?></td>
                <td><?php echo number_format(floatval($item['weight'] ?? $item_weight), 2); ?></td>
                <td><?php echo number_format(floatval($item['value'] ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="4"><strong>Frete/Freight</strong></td><td><?php echo number_format(floatval($freight_paid_value), 2); ?></td></tr>
            <?php if (floatval($insurance_paid_value) > 0): ?>
            <tr><td colspan="4"><strong>Seguro/Insurance</strong></td><td><?php echo number_format(floatval($insurance_paid_value), 2); ?></td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
        <?php
        $html = ob_get_clean();

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper([0, 0, 283.5, 425.2]); // 100mm x 150mm
    $dompdf->loadHtml($html);
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Gera cookies de autenticação de admin para requisições internas (loopback).
 */
function brz_get_admin_cookies() {
    $user_id = 1;
    $expiration = time() + 3600;
    $cookie_value = wp_generate_auth_cookie($user_id, $expiration, 'auth');
    $logged_in_value = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');
    $secure = is_ssl();
    $auth_cookie_name = $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
    return [
        new WP_Http_Cookie(['name' => $auth_cookie_name, 'value' => $cookie_value]),
        new WP_Http_Cookie(['name' => LOGGED_IN_COOKIE, 'value' => $logged_in_value]),
    ];
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

    // Gerar o PDF chamando a mesma classe do plugin mas com output() ao invés de stream()
    try {
        wp_set_current_user(1);
        $_POST['post_id'] = $post_id;
        $_POST['generate_pdf'] = '1';

        // A classe WPR_Container já está instanciada pelo plugin
        // Vamos chamar generate_pdf via Reflection para pegar o output
        // Porém generate_pdf faz exit() - precisamos de outra abordagem
        
        // Fazer loopback autenticado
        $admin_url = admin_url('post.php?post=' . $post_id . '&action=edit');
        $response = wp_remote_post($admin_url, [
            'method' => 'POST',
            'timeout' => 30,
            'cookies' => brz_get_admin_cookies(),
            'body' => [
                'post_ID' => $post_id,
                'post_type' => 'container',
                'generate_pdf' => '1',
                'post_id' => $post_id,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha: ' . $response->get_error_message()], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (strpos($content_type, 'application/pdf') !== false && strlen($body) > 100) {
            add_filter('rest_pre_serve_request', function($served) use ($body, $unit_code) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="container_' . $unit_code . '.pdf"');
                header('Content-Length: ' . strlen($body));
                echo $body;
                return true;
            }, 10, 2);
            return new WP_REST_Response();
        }

        return new WP_REST_Response(['success' => false, 'error' => 'WordPress não retornou PDF válido do container', 'content_type' => $content_type, 'body_length' => strlen($body)], 500);
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro: ' . $e->getMessage()], 500);
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
        wp_set_current_user(1);
        
        $admin_url = admin_url('post.php?post=' . $post_id . '&action=edit');
        $response = wp_remote_post($admin_url, [
            'method' => 'POST',
            'timeout' => 30,
            'cookies' => brz_get_admin_cookies(),
            'body' => [
                'post_ID' => $post_id,
                'post_type' => 'bill',
                'generate_pdf' => '1',
                'post_id' => $post_id,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Falha: ' . $response->get_error_message()], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (strpos($content_type, 'application/pdf') !== false && strlen($body) > 100) {
            add_filter('rest_pre_serve_request', function($served) use ($body, $cn38_code) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="fatura_' . $cn38_code . '.pdf"');
                header('Content-Length: ' . strlen($body));
                echo $body;
                return true;
            }, 10, 2);
            return new WP_REST_Response();
        }

        return new WP_REST_Response(['success' => false, 'error' => 'WordPress não retornou PDF válido da fatura', 'content_type' => $content_type], 500);
    } catch (Exception $e) {
        return new WP_REST_Response(['success' => false, 'error' => 'Erro: ' . $e->getMessage()], 500);
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
