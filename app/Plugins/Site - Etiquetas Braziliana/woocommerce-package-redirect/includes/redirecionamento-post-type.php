<?php

add_action( 'init', 'register_redirecionamento_post_type' );

function register_redirecionamento_post_type() {
    $labels = array(
        'name'               => _x( 'Redirecionamentos', 'post type general name' ),
        'singular_name'      => _x( 'Redirecionamento', 'post type singular name' ),
        'menu_name'          => _x( 'Redirecionamentos', 'admin menu' ),
        'name_admin_bar'     => _x( 'Redirecionamento', 'add new on admin bar' ),
        'add_new'            => _x( 'Adicionar Novo', 'redirecionamento' ),
        'add_new_item'       => __( 'Adicionar Novo Redirecionamento' ),
        'new_item'           => __( 'Novo Redirecionamento' ),
        'edit_item'          => __( 'Editar Redirecionamento' ),
        'view_item'          => __( 'Ver Redirecionamento' ),
        'all_items'          => __( 'Todos os Redirecionamentos' ),
        'search_items'       => __( 'Buscar Redirecionamentos' ),
        'parent_item_colon'  => __( 'Redirecionamentos Pai:' ),
        'not_found'          => __( 'Nenhum redirecionamento encontrado.' ),
        'not_found_in_trash' => __( 'Nenhum redirecionamento encontrado na lixeira.' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'redirecionamento' ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'menu_icon'          => 'dashicons-archive',
        'supports'           => array('')
    );

    register_post_type( 'redirecionamento', $args );
}