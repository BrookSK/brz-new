<?php

add_action('template_redirect', 'add_redirecionamento_to_cart');
add_action('cart_updated', 'add_redirecionamento_to_cart');

function add_redirecionamento_to_cart() {
    if (!is_user_logged_in()) {
        return;
    }

    set_time_limit(300);

    global $wpdb;

    $user_id = get_current_user_id();
    $suite = get_user_meta($user_id, 'suite', true);

    $cache_key = 'redirecionamento_cart_' . $user_id;
    $results = get_transient($cache_key);

    if ($results === false) {
        $query = $wpdb->prepare(
            "
            SELECT p.ID AS post_id,
                   pm_product.meta_value AS product_id,
                   pm_quantidade.meta_value AS quantidade,
                   pm_fatura.meta_value AS fatura
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm_suite ON p.ID = pm_suite.post_id AND pm_suite.meta_key = '_numero_suite' AND pm_suite.meta_value = %s
            INNER JOIN {$wpdb->postmeta} AS pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status' AND pm_status.meta_value = 'Pendente'
            LEFT JOIN {$wpdb->postmeta} AS pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_product_id'
            LEFT JOIN {$wpdb->postmeta} AS pm_quantidade ON p.ID = pm_quantidade.post_id AND pm_quantidade.meta_key = '_quantidade'
            LEFT JOIN {$wpdb->postmeta} AS pm_fatura ON p.ID = pm_fatura.post_id AND pm_fatura.meta_key = '_fatura'
            WHERE p.post_type = 'redirecionamento' AND p.post_status = 'publish'
            ",
            $suite
        );

        $results = $wpdb->get_results($query);

        set_transient($cache_key, $results, 1 * MINUTE_IN_SECONDS);
    }

    $current_cart = WC()->cart->get_cart();
    $current_cart_map = [];
    foreach ($current_cart as $cart_item_key => $cart_item) {
        $current_cart_map[intval($cart_item['product_id'])] = [
            'key' => $cart_item_key,
            'quantity' => intval($cart_item['quantity']),
        ];
    }

    $parallel_cart = get_user_meta($user_id, '_parallel_cart', true);
    $parallel_cart_map = [];
    if (!empty($parallel_cart)) {
        foreach ($parallel_cart as $item) {
            $parallel_cart_map[intval($item['product_id'])] = true;
        }
    }

    $cart_updates = [];
    foreach ($results as $row) {
        $product_id = intval($row->product_id);
        $quantity = ($row->fatura == '1') ? 1 : intval($row->quantidade);

        if ($product_id) {
            $in_cart = isset($current_cart_map[$product_id]);
            $in_parallel_cart = isset($parallel_cart_map[$product_id]);

            if ($in_cart) {
                $current_quantity = $current_cart_map[$product_id]['quantity'];
                if ($current_quantity !== $quantity) {
                    $cart_updates[] = [
                        'type' => 'update',
                        'key' => $current_cart_map[$product_id]['key'],
                        'quantity' => $quantity,
                    ];
                }
            }
            elseif (!$in_parallel_cart) {
                $cart_updates[] = [
                    'type' => 'add',
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                ];
            }
        }
    }

    foreach ($cart_updates as $update) {
        if ($update['type'] === 'update') {
            WC()->cart->set_quantity($update['key'], $update['quantity'], true);
        }
        elseif ($update['type'] === 'add') {
            WC()->cart->add_to_cart($update['product_id'], $update['quantity']);
        }
    }
}
