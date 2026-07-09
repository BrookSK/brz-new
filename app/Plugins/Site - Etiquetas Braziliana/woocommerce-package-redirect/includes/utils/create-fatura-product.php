<?php

function create_fatura_product($post_id) {
    $fields = array(
        'numero_suite',
        'fatura_nome',
        'fatura_descricao',
        'fatura_valor'
    );
    $product_data = array();
    foreach ($fields as $field) {
        $product_data[$field] = get_post_meta($post_id, '_' . $field, true);
    }
    
    $product = new WC_Product();
    $product->set_name($product_data['fatura_nome']);
    $product->set_description($product_data['fatura_descricao']);
    $product->set_regular_price($product_data['fatura_valor']);
    $product->set_catalog_visibility('hidden');
    $product->set_sold_individually(true);
    add_fatura_category_to_product($product);
    $product->save();

    update_post_meta($product->get_id(), '_suite', $product_data['numero_suite']);
    update_post_meta($product->get_id(), '_fatura_id', $post_id);

    return $product->get_id();
}

function add_fatura_category_to_product($product) {
    $category_id = get_term_by('slug', 'fatura', 'product_cat');
    if ($category_id) {
        $product->set_category_ids(array($category_id->term_id));
    } else {
        $category = wp_insert_term('Fatura', 'product_cat');
        if (!is_wp_error($category)) {
            $product->set_category_ids(array($category['term_id']));
        }
    }
}