<?php

require "utils/fees-verification.php";
require "utils/verify-subscription.php";
require "utils/calculate-fees.php";

add_action('woocommerce_cart_calculate_fees', 'add_redirecionamento_fee');
add_action('woocommerce_cart_calculate_fees', 'add_service_fee');
add_action('woocommerce_cart_calculate_fees', 'add_shipping_fee');
add_action('woocommerce_cart_calculate_fees', 'add_insurance_fee');
add_action('woocommerce_cart_calculate_fees', 'add_fine_fee');
add_action('woocommerce_cart_calculate_fees', 'add_order_addons_fee');

add_action('woocommerce_cart_calculate_fees', 'apply_zerar_taxa_coupon_discount');

add_filter('wpr_fee_after_calculate', 'exchange_fees_currency_value');
add_filter('wpr_fee_product_value', 'apply_fees_to_specific_categories', 10, 3);
add_filter('wpr_fee_total_weight', 'add_extra_weight');

function add_redirecionamento_fee(WC_Cart $cart) {
    if ((is_admin() && !defined('DOING_AJAX'))) {
        return;
    }

    $options = get_option('redirecionamento_taxes');
    $intervals = isset($options['intervals']) ? $options['intervals'] : array();

    $total_weight = 0;
    foreach ($cart->get_cart() as $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        $weight = $product->get_weight() ? (float) $product->get_weight() : 0;
        $weight_value = $weight * $cart_item['quantity'];
        $total_weight += apply_filters('wpr_fee_product_value', $product, $weight_value, 'redirecionamento_fee');
    }
    $total_weight = apply_filters('wpr_fee_total_weight', $total_weight, 'redirecionamento_fee');

    $redirect_fee = calculate_fee_fixed($total_weight, $intervals);
    $redirect_fee = apply_filters('wpr_fee_after_calculate', $redirect_fee, 'redirecionamento_fee');
    
    if ($redirect_fee > 0) {
        $cart->add_fee(__('Taxa de Redirecionamento', 'woocommerce'), $redirect_fee, true);
    }
}

function add_service_fee(WC_Cart $cart) {
    if ((is_admin() && !defined('DOING_AJAX'))) {
        return;
    }

    $options = get_option('servico_taxes');
    $intervals = isset($options['intervals']) ? $options['intervals'] : array();
    if($options['enable_subscriber_rates'] && verify_subscription()) {
        $intervals = isset($options['subscriber_intervals']) ? $options['subscriber_intervals'] : array();
    }

    $total = 0;
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $product_price = get_post_meta($product_id, '_price', true);
        $total += apply_filters('wpr_fee_product_value', $product, $product_price,'service_fee');
    }

    $service_fee = calculate_fee_variable($total, $intervals);
    $service_fee = apply_filters('wpr_fee_after_calculate', $service_fee, 'service_fee');

    if ($service_fee > 0) {
        $cart->add_fee(__('Taxa de Serviço', 'woocommerce'), $service_fee, true);
    }
}

function add_shipping_fee(WC_Cart $cart) {
    if ((is_admin() && !defined('DOING_AJAX'))) {
        return;
    }
    
    $options = get_option('frete_taxes');
    $intervals = isset($options['intervals']) ? $options['intervals'] : array();

    $total_weight_site = 0;
    $total_weight_redirecionamento = 0;
    
    foreach ($cart->get_cart() as $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        $weight = $product->get_weight() ? (float) $product->get_weight() : 0;
        $weight_value = $weight * $cart_item['quantity'];
        if (has_term('redirecionamento', 'product_cat', $cart_item['product_id'])) {
            $total_weight_redirecionamento += apply_filters('wpr_fee_product_value', $product, $weight_value, 'shipping_fee');
        }
        else {
            $total_weight_site += apply_filters('wpr_fee_product_value', $product, $weight_value, 'shipping_fee');
        }
    }

    $total_weight = $total_weight_site + $total_weight_redirecionamento;
    $total_weight = apply_filters('wpr_fee_total_weight', $total_weight, 'shipping_fee');

    $shipping_fee = 0;
    if ($options['enable_subscriber_rates'] && verify_subscription()) {
        $subscriber_intervals = isset($options['subscriber_intervals']) ? $options['subscriber_intervals'] : array();
        
        if (any_redirecionamento($cart)) {
            $shipping_fee += calculate_fee_fixed($total_weight_redirecionamento, $intervals);
        }
        $shipping_fee += calculate_fee_fixed($total_weight_redirecionamento, $subscriber_intervals);
    }
    else {
        $shipping_fee += calculate_fee_fixed($total_weight, $intervals);
    }

    $shipping_fee = apply_filters('wpr_fee_after_calculate', $shipping_fee, 'shipping_fee');

    if ($shipping_fee > 0) {
        $cart->add_fee(__('Taxa de Frete', 'woocommerce'), $shipping_fee, true);
    }
}

function add_insurance_fee(WC_Cart $cart) {
    if ((is_admin() && !defined('DOING_AJAX'))) {
        return;
    }

    $options = get_option('seguro_taxes');
    $intervals = isset($options['intervals']) ? $options['intervals'] : array();

    $insurance_fee = 0;
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();

        $is_redirecionamento = has_term('redirecionamento', 'product_cat', $product_id);
        $declaration_value = get_post_meta($product_id, '_price', true);
        if ($is_redirecionamento && !has_term('fatura', 'product_cat', $product_id)) {
            if (isset($cart_item['declaration_value'])) {
                $declaration_value = $cart_item['declaration_value'];
            }
            else {
                continue;
            }
        }
        
        $item_insurance_fee = calculate_fee_variable($declaration_value, $intervals);
        $insurance_fee += apply_filters('wpr_fee_product_value', $product, $item_insurance_fee, 'insurance_fee');
    }

    $insurance_fee = apply_filters('wpr_fee_after_calculate', $insurance_fee, 'insurance_fee');

    if ($insurance_fee > 0) {
        $cart->add_fee(__('Taxa de Seguro', 'woocommerce'), $insurance_fee, true);
    }
}

function add_fine_fee(WC_Cart $cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $multa_options = get_option('multa_taxes');
    $fine_fee = 0;

    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if (has_term('redirecionamento', 'product_cat', $product->get_id())) {
            $redirecionamento_id = get_post_meta($product->get_id(), '_redirecionamento_id', true);
            $dias_atraso = get_post_meta($redirecionamento_id, '_dias_atraso', true);

            if ($dias_atraso && isset($multa_options['intervals']) && is_array($multa_options['intervals'])) {
                foreach ($multa_options['intervals'] as $interval) {
                    $start = isset($interval['start']) ? floatval($interval['start']) : 0;
                    $end = isset($interval['end']) && $interval['end'] !== '' ? floatval($interval['end']) : PHP_INT_MAX;

                    if ($dias_atraso > $start) {
                        $applicable_days = min($dias_atraso, $end) - $start;
                        if ($interval['type'] === 'fixed') {
                            $fine_fee += (floatval($interval['rate']) * $applicable_days) * $cart_item['quantity'];
                        }
                    }
                }
            }
        }
    }

    $fine_fee = apply_filters('wpr_fee_after_calculate', $fine_fee, 'fine_fee');

    if ($fine_fee > 0) {
        $cart->add_fee(__('Taxa de Armazenamento', 'woocommerce'), $fine_fee, false, '');
    }
}

function add_order_addons_fee(WC_Cart $cart) {
    $order_addons = WC()->session->get('order_addons', array());
    $addon_settings = get_option('addon_settings', array());

    if (!empty($order_addons)) {
        foreach ($order_addons as $addon_key) {
            if (isset($addon_settings['addons'][$addon_key])) {
                $addon = $addon_settings['addons'][$addon_key];
                $addon_name = sanitize_text_field($addon['name']);
                $addon_value = floatval($addon['value']);
                $is_percent = ($addon['value_type'] === 'percent');

                if ($is_percent) {
                    $total_cart_value = 0;
                    foreach ($cart->get_cart() as $cart_item) {
                        $product_id = $cart_item['product_id'];
                        $product_price = get_post_meta($product_id, '_price', true);
                        $quantity = $cart_item['quantity'];
                        $total_cart_value += $product_price * $quantity;
                    }

                    $addon_value = ($addon_value / 100) * $total_cart_value;
                }

                $addon_value = apply_filters('wpr_fee_after_calculate', $addon_value, 'addon_fee');
                
                if ($addon_value > 0) {
                    $cart->add_fee('Adicional - ' . $addon_name, $addon_value);
                }
            }
        }
    }
}

function apply_zerar_taxa_coupon_discount(WC_Cart $cart) {
    $applied_coupons = WC()->cart->get_applied_coupons();

    foreach ($applied_coupons as $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);

        $discount_type = $coupon->get_discount_type();
        if ($discount_type === 'zerar_taxas') {
            $cart->fees_api()->remove_all_fees();
        }
    }
}

function exchange_fees_currency_value($fee_value) {
    if (class_exists('WCCS') && $fee_value) {
        global $WCCS;
        $fee_value = $WCCS->wccs_price_conveter(floatval($fee_value));
    }
    return $fee_value;
}

function apply_fees_to_specific_categories($product, $product_value, $fee_name) {
    $appliable_categories = get_option('fees_apply_to_categories');
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));

    if (isset($appliable_categories['categories']) && is_array($appliable_categories['categories'])) {
        $has_appliable_category = false;

        foreach ($appliable_categories['categories'] as $category) {
            if (
                !empty($category['category']) &&
                in_array($category['category'], $product_categories) &&
                empty($category[$fee_name])
            ) {
                return 0;
            }
        }
    }

    return $product_value;
}

function add_extra_weight($total_weight) {
    $options = get_option('embalagem_taxes');
    $intervals = isset($options['intervals']) ? $options['intervals'] : array();

    $extra_weight = 0;
    foreach ($intervals as $interval) {
        $start = isset($interval['start']) ? floatval($interval['start']) : 0;
        $end = isset($interval['end']) && !empty($interval['end']) ? floatval($interval['end']) : PHP_INT_MAX;
        $additional_weight = isset($interval['rate']) ? floatval($interval['rate']) : 0;

        if ($total_weight >= $start && $total_weight <= $end) {
            $total_weight += $additional_weight;
        }
    }

    return $total_weight;
}
