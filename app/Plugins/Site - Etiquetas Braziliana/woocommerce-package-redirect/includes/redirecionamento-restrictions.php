<?php

add_action('woocommerce_product_query', 'hide_redirecionamento_products_from_shop_and_search');
add_action('template_redirect', 'restrict_redirecionamento_product_page_access');
add_filter('woocommerce_add_to_cart_validation', 'prevent_user_add_redirecionamento_to_cart', 10, 3);

function get_restricted_category_ids() {
    $restricted_categories = array('Redirecionamento', 'Fatura');
    $category_ids = array();

    foreach ($restricted_categories as $category_name) {
        $category = get_term_by('name', $category_name, 'product_cat');
        if ($category) {
            $category_ids[] = $category->term_id;
        }
    }

    return $category_ids;
}

function hide_redirecionamento_products_from_shop_and_search($query) {
    $category_ids = get_restricted_category_ids();
    if (!empty($category_ids)) {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_ids,
            'operator' => 'NOT IN',
        );
        $query->set('tax_query', $tax_query);
    }
}

function restrict_redirecionamento_product_page_access() {
    if (is_product()) {
        global $post;
        $categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'ids'));
        $restricted_category_ids = get_restricted_category_ids();
        if (!empty($restricted_category_ids) && array_intersect($categories, $restricted_category_ids)) {
            wp_redirect(home_url());
            exit;
        }
    }
}

function prevent_user_add_redirecionamento_to_cart($passed, $product_id, $quantity) {
    $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    $restricted_category_ids = get_restricted_category_ids();
    if (!empty($restricted_category_ids) && array_intersect($categories, $restricted_category_ids)) {
        wc_add_notice(__('Este produto não pode ser adicionado ao carrinho.', 'woocommerce'), 'error');
        return false;
    }
    return $passed;
}
?>