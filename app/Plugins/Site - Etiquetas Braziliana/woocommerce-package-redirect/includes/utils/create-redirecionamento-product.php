<?php

function create_redirecionamento_product($post_id) {
    $fields = array(
        'numero_suite',
        'nome',
        'descricao',
        'fornecedor',
        'ncm',
        'recebimento',
        'peso',
        'quantidade',
        'foto',
    );
    $product_data = array();
    foreach ($fields as $field) {
        $product_data[$field] = get_post_meta($post_id, '_' . $field, true);
    }
    
    $product = new WC_Product();
    $product->set_name($product_data['nome']);
    $product->set_description($product_data['descricao']);
    $product->set_regular_price('0');
    $product->set_catalog_visibility('hidden');
    $product->set_weight($product_data['peso']);
    $product->set_stock_quantity($product_data['quantidade']);
    add_redirecionamento_category_to_product($product);
    if ($product_data['foto']) {
        $attachment_id = attachment_url_to_postid($product_data['foto']);
        $product->set_image_id($attachment_id);
    }
    $product->save();

    update_post_meta($product->get_id(), '_suite', $product_data['numero_suite']);
    update_post_meta($product->get_id(), '_redirecionamento_id', $post_id);

    return $product->get_id();
}

function add_redirecionamento_category_to_product($product) {
    $category_id = get_term_by('slug', 'redirecionamento', 'product_cat');
    if ($category_id) {
        $product->set_category_ids(array($category_id->term_id));
    } else {
        $category = wp_insert_term('Redirecionamento', 'product_cat');
        if (!is_wp_error($category)) {
            $product->set_category_ids(array($category['term_id']));
        }
    }
}