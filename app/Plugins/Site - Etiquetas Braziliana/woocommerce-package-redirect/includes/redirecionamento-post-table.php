<?php

add_filter('manage_redirecionamento_posts_columns', 'set_custom_edit_redirecionamento_columns');
add_action('manage_redirecionamento_posts_custom_column', 'custom_redirecionamento_column', 10, 2);

add_action('restrict_manage_posts', 'add_search_by_numero_suite');
add_filter('post_row_actions', 'remove_quick_edit_action', 10, 2);
add_filter('page_row_actions', 'remove_quick_edit_action', 10, 2);
add_filter('bulk_actions-edit-redirecionamento', 'remove_bulk_actions_redirecionamento', 20, 1);

add_filter('pre_get_posts', 'filter_redirecionamento_by_numero_suite');
add_action('pre_get_posts', 'filter_redirecionamento_by_status');
add_action('restrict_manage_posts', 'add_status_filter_to_redirecionamento');

add_action('pre_get_posts', 'hide_fatura_redirecionamentos_from_admin');
add_action('pre_get_posts', 'hide_redirecionamento_products');
// add_filter('get_terms', 'hide_redirecionamento_category', 10, 3);
add_filter('wcfm_products_args', 'filter_custom_products', 10, 1);

function set_custom_edit_redirecionamento_columns($columns) {
    unset($columns['title']);
    unset($columns['date']);
    
    $columns['nome'] = __('Nome');
    $columns['numero_suite'] = __('Suite / Usuário');
    $columns['fornecedor'] = __('Fornecedor');
    $columns['recebimento'] = __('Recebimento');
    $columns['peso'] = __('Peso (kg)');
    $columns['quantidade'] = __('Quantidade');
    $columns['foto'] = __('Foto');
    $columns['status'] = __('Status');
    
    return $columns;
}

function custom_redirecionamento_column($column, $post_id) {
    switch ($column) {
        case 'nome':
            $nome = get_post_meta($post_id, '_nome', true);
            echo '<a href="' . get_edit_post_link($post_id) . '">' . esc_html($nome) . '</a>';
            break;
        case 'numero_suite':
            $numero_suite = get_post_meta($post_id, '_numero_suite', true);
            if ($numero_suite) {
                $user_query = new WP_User_Query(array(
                    'meta_key' => 'suite',
                    'meta_value' => $numero_suite,
                    'fields' => array('ID', 'display_name')
                ));
                
                if (!empty($user_query->results)) {
                    $user = $user_query->results[0];
                    $user_link = get_edit_user_link($user->ID);
                    echo '<strong>' . esc_html($numero_suite) . '</strong> - <a href="' . esc_url($user_link) . '">' . esc_html($user->display_name) . '</a>';
                } else {
                    echo '<strong>' . esc_html($numero_suite) . '</strong>';
                }
            } else {
                echo __('Nenhum número de suíte');
            }
            break;
        case 'fornecedor':
            echo get_post_meta($post_id, '_fornecedor', true);
            break;
        case 'recebimento':
            $timestamp = strtotime(get_post_meta($post_id, '_recebimento', true));
            echo wp_date('d/m/Y', $timestamp);
            break;
        case 'peso':
            echo get_post_meta($post_id, '_peso', true);
            break;
        case 'quantidade':
            echo get_post_meta($post_id, '_quantidade', true);
            break;
        case 'foto':
            $foto = get_post_meta($post_id, '_foto', true);
            if ($foto) {
                ?>
                <img src="<?php echo esc_url($foto); ?>" style="width: 60px; height: auto;" />
                <?php
            } else {
                echo __('Sem imagem');
            }
            break;
        case 'status':
            $status = get_post_meta($post_id, '_status', true);
            $order_id = get_post_meta($post_id, '_order_id', true);
            $order_link = admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
            
            if ($order_id) {
                echo '<strong>' . esc_html($status) . '</strong> - <a href="' . esc_url($order_link) . '" target="_blank">Ver Pedido</a>';
            } else {
                echo '<strong>' . esc_html($status) . '</strong>';
            }
            break;
    }
}

function hide_redirecionamento_products($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $screen = get_current_screen();
    if (isset($screen->post_type) && $screen->post_type == 'product') {
        $category = get_term_by('name', 'Redirecionamento', 'product_cat');
        if ($category) {
            $category_id = $category->term_id;

            $tax_query = $query->get('tax_query') ? $query->get('tax_query') : array();
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_id,
                'operator' => 'NOT IN'
            );
            $query->set('tax_query', $tax_query);
        }
    }
}

function hide_redirecionamento_category($terms, $taxonomies, $args) {
    if (in_array('product_cat', $taxonomies)) {
        if (is_admin()) {
            $category = get_term_by('name', 'Redirecionamento', 'product_cat');
            if ($category) {
                $category_id = $category->term_id;
                foreach ($terms as $key => $term) {
                    if ($term->term_id == $category_id) {
                        unset($terms[$key]);
                    }
                }
            }
        }
    }
    return $terms;
}


function add_search_by_numero_suite() {
    global $post_type;
    
    if ($post_type == 'redirecionamento') {
        $value = isset($_GET['numero_suite']) ? sanitize_text_field($_GET['numero_suite']) : '';
        echo '<input type="text" name="numero_suite" value="' . esc_attr($value) . '" placeholder="' . __('Número de Suite', 'text-domain') . '" />';
    }
}

function filter_redirecionamento_by_numero_suite($query) {
    global $pagenow;

    if (is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'redirecionamento') {
        $numero_suite = isset($_GET['numero_suite']) ? sanitize_text_field($_GET['numero_suite']) : '';

        if (!empty($numero_suite)) {
            if (preg_match('/^\d+$/', $numero_suite)) {
                $meta_query = $query->get('meta_query');
                
                if (!$meta_query) {
                    $meta_query = array();
                }

                
                $meta_query[] = array(
                    'key'     => '_numero_suite',
                    'value'   => $numero_suite,
                    'compare' => '='
                );
                
                $query->set('meta_query', $meta_query);
            } else {
                global $wpdb;

                $suites = $wpdb->get_col($wpdb->prepare(
                    "SELECT meta_value 
                     FROM $wpdb->usermeta 
                     WHERE meta_key = 'suite' 
                     AND user_id IN (
                         SELECT ID 
                         FROM $wpdb->users 
                         WHERE user_login LIKE %s 
                         OR display_name LIKE %s
                     )",
                    '%' . $wpdb->esc_like($numero_suite) . '%',
                    '%' . $wpdb->esc_like($numero_suite) . '%'
                ));

                if (!empty($suites)) {
                    $meta_query = $query->get('meta_query');
                    
                    if (!$meta_query) {
                        $meta_query = array();
                    }

                    $meta_query[] = array(
                        'key'     => '_numero_suite',
                        'value'   => $suites,
                        'compare' => 'IN'
                    );

                    $query->set('meta_query', $meta_query);
                } else {
                    $query->set('meta_query', array());
                }
            }
        }
    }
}

function remove_quick_edit_action($actions, $post) {
    if ($post->post_type === 'redirecionamento') {
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}

function add_status_filter_to_redirecionamento() {
    global $typenow;

    if ($typenow === 'redirecionamento') {
        $selected_status = isset($_GET['redirecionamento_status']) ? sanitize_text_field($_GET['redirecionamento_status']) : '';

        $statuses = array(
            '' => 'Todos os Status',
            'Pendente' => 'Pendente',
            'Pedido criado' => 'Pedido criado',
            'Enviado' => 'Enviado',
            'Fatura pendente' => 'Fatura pendente',
            'Fatura paga' => 'Fatura paga',
            'Invoice liberado' => 'Invoice liberado',
            'Invoice fechado' => 'Invoice fechado',
            'Descarte' => 'Descarte',
        );

        echo '<select name="redirecionamento_status">';
        foreach ($statuses as $status => $label) {
            $selected = ($status === $selected_status) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}

function filter_redirecionamento_by_status($query) {
    global $typenow;

    if ($typenow === 'redirecionamento' && isset($_GET['redirecionamento_status']) && $_GET['redirecionamento_status'] != '') {
        $meta_query = array(
            array(
                'key' => '_status',
                'value' => sanitize_text_field($_GET['redirecionamento_status']),
                'compare' => '='
            )
        );
        $query->set('meta_query', $meta_query);
    }
}

function remove_bulk_actions_redirecionamento($actions) {
    unset($actions['edit']);
    unset($actions['trash']);
    unset($actions['delete']);

    return $actions;
}

function hide_fatura_redirecionamentos_from_admin($query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'redirecionamento') {
        $existing_meta_query = $query->get('meta_query');

        $new_meta_query = array(
            array(
                'key' => '_fatura',
                'value' => 'true',
                'compare' => 'NOT EXISTS'
            )
        );

        if (!empty($existing_meta_query)) {
            $meta_query = array_merge($existing_meta_query, $new_meta_query);
        } else {
            $meta_query = $new_meta_query;
        }

        $query->set('meta_query', $meta_query);
    }
}

function search_by_nome_in_redirecionamento($query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'redirecionamento') {
        $search_term = $query->get('s');
        
        if (!empty($search_term)) {
            $meta_query = array(
                array(
                    'key'     => '_nome',
                    'value'   => $search_term,
                    'compare' => 'LIKE'
                )
            );
            
            $existing_meta_query = $query->get('meta_query');
            if ($existing_meta_query) {
                $meta_query = array_merge($existing_meta_query, $meta_query);
            }
            
            $query->set('meta_query', $meta_query);
        }
        $query->set('s', '');
    }
}

add_action('pre_get_posts', 'search_by_nome_in_redirecionamento');

function filter_custom_products($args) {
    $args['tax_query'][] = array(
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => array('redirecionamento', 'fatura'),
        'operator' => 'NOT IN'
    );
    return $args;
}