<?php

require 'utils/send-tracking-code.php';

add_action('woocommerce_admin_order_data_after_order_details', 'add_tracking_code_field_to_order_admin_page');
add_action('woocommerce_process_shop_order_meta', 'save_tracking_code_meta', 100, 2);
add_action('woocommerce_order_details_after_order_table', 'display_tracking_code_to_customer');
add_filter('woocommerce_order_actions', 'add_send_tracking_code_action');
add_action('woocommerce_order_action_send_tracking_code', 'handle_send_tracking_code_action');

function add_tracking_code_field_to_order_admin_page($order) {
    $tracking_code = get_post_meta($order->get_id(), '_tracking_code', true);
    ?>
    <div class="form-field form-field-wide">
        <label for="tracking_code"><?php _e('Código de Rastreio:', 'woocommerce'); ?></label>
        <input type="text" name="tracking_code" id="tracking_code" value="<?php echo esc_attr($tracking_code); ?>">
    </div>
    <?php
}

function save_tracking_code_meta($post_id, $post) {
    $tracking_code = isset($_POST['tracking_code']) ? sanitize_text_field($_POST['tracking_code']) : '';
    update_post_meta($post_id, '_tracking_code', $tracking_code);
}

function add_send_tracking_code_action($actions) {
    $actions['send_tracking_code'] = __('Enviar Código de Rastreio', 'woocommerce');
    return $actions;
}

function handle_send_tracking_code_action($order) {
    $tracking_code = get_post_meta($order->get_id(), '_tracking_code', true);
    if ($tracking_code) {
        send_order_tracking_code_email($order, $tracking_code);
        send_order_tracking_code_whatsapp($order, $tracking_code);
        $order->update_status('wc-enviado', 'Código de rastreio enviado.');
    }
}

function display_tracking_code_to_customer($order) {
    $tracking_code = get_post_meta($order->get_id(), '_tracking_code', true);
    if ($tracking_code) {
        ?>
        <p><strong>Código de Rastreio:</strong> <?php echo esc_html($tracking_code); ?></p>
        <?php
    }
}