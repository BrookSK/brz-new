<?php

function all_fatura($cart) {
    foreach ($cart->get_cart() as $cart_item) {
        if (!has_term('fatura', 'product_cat', $cart_item['product_id'])) {
            return false;
        }
    }
    return true;
}

function any_redirecionamento($cart) {
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term('redirecionamento', 'product_cat', $cart_item['product_id'])) {
            return true;
        }
    }
    return false;
}

function all_redirecionamento($cart) {
    foreach ($cart->get_cart() as $cart_item) {
        if (!has_term('redirecionamento', 'product_cat', $cart_item['product_id'])) {
            return false;
        }
    }
    return true;
}

function all_subscription($cart) {
    if (class_exists('WC_Subscriptions_Product')) {
        foreach ($cart->get_cart() as $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if (!$product || !WC_Subscriptions_Product::is_subscription($product)) {
                return false;
            }
        }
    }
    return true;
}

function all_fatura_or_subscription($cart) {
    if (class_exists('WC_Subscriptions_Product')) {
        foreach ($cart->get_cart() as $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if (!$product || (!WC_Subscriptions_Product::is_subscription($product) && !has_term('fatura', 'product_cat', $cart_item['product_id']))) {
                return false;
            }
        }
    }
    return true;
}

function all_fatura_or_subscription_or_redirecionamento($cart) {
    if (class_exists('WC_Subscriptions_Product')) {
        foreach ($cart->get_cart() as $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if (!$product || (
                    !WC_Subscriptions_Product::is_subscription($product) &&
                    !has_term('fatura', 'product_cat', $cart_item['product_id']) &&
                    !has_term('redirecionamento', 'product_cat', $cart_item['product_id'])
                )
            ) {
                return false;
            }
        }
    }
    return true;
}