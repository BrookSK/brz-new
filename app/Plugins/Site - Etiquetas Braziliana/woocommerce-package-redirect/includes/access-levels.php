<?php

add_action('admin_menu', 'register_plugin_settings_page');
add_action('admin_notices', 'check_access_levels_limits');
add_action('woocommerce_checkout_process', 'restrict_new_orders_creation');
add_action('save_post_product', 'restrict_new_product_creation', 10, 3);
add_action('user_register', 'restrict_new_user_creation');

function register_plugin_settings_page() {
    add_menu_page(
        __('Configurações de Acesso', 'textdomain'),
        __('Níveis de Acesso', 'textdomain'),
        'manage_options',
        'access-level-settings',
        'render_access_levels_settings_page',
        'dashicons-admin-network',
        100
    );
}

function render_access_levels_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['save_access_levels'])) {
        $access_levels = array(
            'orders_limit' => intval($_POST['orders_limit']),
            'products_limit' => intval($_POST['products_limit']),
            'users_limit' => intval($_POST['users_limit']),
        );
        update_option('plugin_access_levels', $access_levels);
        echo '<div class="updated"><p>' . __('Configurações salvas.', 'textdomain') . '</p></div>';
    }

    $access_levels = get_option('plugin_access_levels', array(
        'orders_limit' => 0,
        'products_limit' => 0,
        'users_limit' => 0,
    ));

    ?>
    <div class="wrap">
        <h1><?php _e('Configurações de Níveis de Acesso', 'textdomain'); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Limite de Pedidos', 'textdomain'); ?></th>
                    <td><input type="number" name="orders_limit" value="<?php echo esc_attr($access_levels['orders_limit']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Limite de Produtos', 'textdomain'); ?></th>
                    <td><input type="number" name="products_limit" value="<?php echo esc_attr($access_levels['products_limit']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Limite de Usuários', 'textdomain'); ?></th>
                    <td><input type="number" name="users_limit" value="<?php echo esc_attr($access_levels['users_limit']); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(__('Salvar Configurações', 'textdomain'), 'primary', 'save_access_levels'); ?>
        </form>
    </div>
    <?php
}

function check_access_levels_limits() {
    $access_levels = get_option('plugin_access_levels', array(
        'orders_limit' => 0,
        'products_limit' => 0,
        'users_limit' => 0,
    ));

    if ($access_levels['orders_limit'] > 0) {
        $order_count = wc_get_orders(array('return' => 'ids', 'limit' => -1));
        if (count($order_count) >= $access_levels['orders_limit']) {
            echo '<div class="notice notice-warning"><p>' . __('Você atingiu o limite de pedidos permitido pelo seu plano.', 'textdomain') . '</p></div>';
        }
    }

    if ($access_levels['products_limit'] > 0) {
        $product_count = wp_count_posts('product')->publish;
        if ($product_count >= $access_levels['products_limit']) {
            echo '<div class="notice notice-warning"><p>' . __('Você atingiu o limite de produtos permitido pelo seu plano.', 'textdomain') . '</p></div>';
        }
    }

    if ($access_levels['users_limit'] > 0) {
        $user_count = count_users();
        if ($user_count['total_users'] >= $access_levels['users_limit']) {
            echo '<div class="notice notice-warning"><p>' . __('Você atingiu o limite de usuários permitido pelo seu plano.', 'textdomain') . '</p></div>';
        }
    }
}

function restrict_new_orders_creation() {
    $access_levels = get_option('plugin_access_levels', array(
        'orders_limit' => 0,
    ));

    if ($access_levels['orders_limit'] > 0) {
        $order_count = wc_get_orders(array('return' => 'ids', 'limit' => -1));
        if (count($order_count) >= $access_levels['orders_limit']) {
            wc_add_notice(__('Você atingiu o limite de pedidos permitido pelo seu plano.', 'textdomain'), 'error');
        }
    }
}

function restrict_new_product_creation($post_id, $post, $update) {
    if ($update) {
        return;
    }

    $access_levels = get_option('plugin_access_levels', array(
        'products_limit' => 0,
    ));

    if ($access_levels['products_limit'] > 0) {
        $product_count = wp_count_posts('product')->publish;
        if ($product_count >= $access_levels['products_limit']) {
            wp_die(__('Você atingiu o limite de produtos permitido pelo seu plano.', 'textdomain'));
        }
    }
}

function restrict_new_user_creation($user_id) {
    $access_levels = get_option('plugin_access_levels', array(
        'users_limit' => 0,
    ));

    if ($access_levels['users_limit'] > 0) {
        $user_count = count_users();
        if ($user_count['total_users'] >= $access_levels['users_limit']) {
            wp_delete_user($user_id);
            wp_die(__('Você atingiu o limite de usuários permitido pelo seu plano.', 'textdomain'));
        }
    }
}
