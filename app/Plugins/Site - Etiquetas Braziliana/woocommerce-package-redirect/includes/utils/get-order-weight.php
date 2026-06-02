<?php

function get_order_weight($order_id) {
    if ( ! $order_id ) {
        return '';
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return '';
    }

    $total_weight = 0;
    
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        if ( $product ) {
            $weight = $product->get_weight();
            $quantity = $item->get_quantity();
            $total_weight += (float) $weight * (int) $quantity;
        }
    }
    
    return $total_weight * 1000;
}