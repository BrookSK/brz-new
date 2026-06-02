<?php

add_action('woocommerce_thankyou', 'update_redirecionamento_status_on_order', 10, 1);
add_action('woocommerce_thankyou', 'update_fatura_status_on_order', 10, 1);

function update_redirecionamento_status_on_order($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        if (has_term('redirecionamento', 'product_cat', $product_id)) {
            $redirecionamento_id = get_post_meta($product_id, '_redirecionamento_id', true);
            
            if ($redirecionamento_id) {
                update_post_meta($redirecionamento_id, '_status', 'Pedido criado');
                update_post_meta($redirecionamento_id, '_order_id', $order_id);
            }
        }
    }
}

function update_fatura_status_on_order($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        if (has_term('fatura', 'product_cat', $product_id)) {
            $fatura_id = get_post_meta($product_id, '_fatura_id', true);
            
            if ($fatura_id) {
                update_post_meta($fatura_id, '_status', 'Pedido criado');

                $order_id = get_post_meta($fatura_id, '_order_id', true);
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_status('wc-fatura-paga', 'Status alterado para Fatura paga.');
                }
            }
            wp_delete_post($product_id, true);
        }
    }
}