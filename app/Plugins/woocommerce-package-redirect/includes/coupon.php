<?php

add_filter('woocommerce_coupon_discount_types', 'add_custom_coupon_type');

function add_custom_coupon_type($types) {
    $types['zerar_taxas'] = __('Zerar Taxas', 'textdomain');
    return $types;
}