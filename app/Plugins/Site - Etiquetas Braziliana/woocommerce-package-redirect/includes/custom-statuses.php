<?php

add_action('init', 'register_custom_order_statuses');
add_filter('wc_order_statuses', 'add_custom_order_statuses');
add_action('woocommerce_order_status_changed', 'sync_order_status_to_redirecionamento', 10, 3);
add_action('admin_head', 'custom_order_status_colors');

function custom_order_status_colors() {
    ?>
    <style>
        .order-status.status-enviado {
            background: #ff6b8e;
            color: white;
        }
        .order-status.status-fatura-pendente {
            background: #a46497;
            color: white;
        }
        .order-status.status-fatura-paga {
            background: #21759b;
            color: white;
        }
        .order-status.status-invoice-liberado {
            background: #FDFD96;
            color: #94660c;
        }
        .order-status.status-invoice-fechado {
            background: #77DD77;
            color: white;
        }
        .order-status.status-invoice-ct {
            background: #999;
            color: white;
        }
    </style>
    <?php
}

function register_custom_order_statuses() {
    register_post_status('wc-enviado', array(
        'label'                     => 'Enviado',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Enviado (%s)', 'Enviado (%s)')
    ));

    register_post_status('wc-fatura-pendente', array(
        'label'                     => 'Fatura adicional pendente',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Fatura adicional pendente (%s)', 'Fatura adicional pendente (%s)')
    ));

    register_post_status('wc-fatura-paga', array(
        'label'                     => 'Fatura adicional paga',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Fatura adicional paga (%s)', 'Fatura adicional paga (%s)')
    ));

    register_post_status('wc-invoice-liberado', array(
        'label'                     => 'Invoice enviado',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Invoice enviado (%s)', 'Invoice enviado (%s)')
    ));

    register_post_status('wc-invoice-fechado', array(
        'label'                     => 'Invoice confirmado',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Invoice confirmado (%s)', 'Invoice confirmado (%s)')
    ));

    register_post_status('wc-invoice-ct', array(
        'label'                     => 'Invoice contestado',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Invoice contestado (%s)', 'Invoice contestado (%s)')
    ));
}

function add_custom_order_statuses($order_statuses) {
    $new_order_statuses = array();

    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;

        if ('wc-completed' === $key) {
            $new_order_statuses['wc-enviado'] = 'Enviado';
            $new_order_statuses['wc-fatura-pendente'] = 'Fatura adicional pendente';
            $new_order_statuses['wc-fatura-paga'] = 'Fatura adicional paga';
            $new_order_statuses['wc-invoice-liberado'] = 'Invoice enviado';
            $new_order_statuses['wc-invoice-fechado'] = 'Invoice confirmado';
            $new_order_statuses['wc-invoice-ct'] = 'Invoice contestado';
        }
    }

    return $new_order_statuses;
}

function sync_order_status_to_redirecionamento($order_id, $old_status, $new_status) {
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $redirecionamento_id = get_post_meta($product_id, '_redirecionamento_id', true);

        if ($redirecionamento_id) {
            switch ($new_status) {
                case 'enviado':
                    update_post_meta($redirecionamento_id, '_status', 'Enviado');
                    break;
                case 'fatura-pendente':
                    update_post_meta($redirecionamento_id, '_status', 'Fatura adicional pendente');
                    break;
                case 'fatura-paga':
                    update_post_meta($redirecionamento_id, '_status', 'Fatura adicional paga');
                    break;
                case 'invoice-liberado':
                    update_post_meta($redirecionamento_id, '_status', 'Invoice enviado');
                    break;
                case 'invoice-fechado':
                    update_post_meta($redirecionamento_id, '_status', 'Invoice confirmado');
                    break;
                case 'invoice-ct':
                    update_post_meta($redirecionamento_id, '_status', 'Invoice contestado');
                    break;
            }
        }
    }
}
