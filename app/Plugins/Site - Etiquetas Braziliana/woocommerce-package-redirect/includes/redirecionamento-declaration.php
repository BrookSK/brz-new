<?php

add_action('woocommerce_after_cart_item_name', 'add_declaration_value_field_to_cart_item', 10, 2);
add_action('woocommerce_before_calculate_totals', 'save_declaration_value_field_from_cart');
add_action('woocommerce_checkout_create_order_line_item', 'add_declaration_value_to_order_item_meta', 10, 4);
add_action('template_redirect', 'check_declaration_fields_before_checkout');
// add_action('wp_head', 'add_declaration_value_styles');

function add_declaration_value_field_to_cart_item($cart_item, $cart_item_key) {
    $declaration_value = isset($cart_item['declaration_value']) ? number_format((float)$cart_item['declaration_value'], 2, '.', '') : '';
    $product = $cart_item['data'];
    if (has_term('redirecionamento', 'product_cat', $product->get_id())) {
        ?>
        <div class="declaration-value-field">
            <label for="declaration_value_<?php echo $cart_item_key; ?>"><?php _e('Declaração de valor ($)'); ?></label>
            <input type="number" min="1" step="0.01" name="declaration_value[<?php echo $cart_item_key; ?>]" id="declaration_value_<?php echo $cart_item_key; ?>" value="<?php echo esc_attr($declaration_value); ?>" placeholder="<?php _e('Insira o valor do produto ($)'); ?>">
        </div>
        <?php
    }
}

function add_declaration_value_styles() {
    ?>
    <style>
        .declaration-value-field {
            margin-top: 10px;
            font-size: 14px;
        }
        .declaration-value-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .declaration-value-field input[type="number"] {
            width: 100%;
            max-width: 200px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .woocommerce-cart table.cart img {
            width: auto; 
            box-shadow: none;
        }
    </style>
    <?php
}

function save_declaration_value_field_from_cart($cart) {
    if (isset($_POST['declaration_value']) && is_array($_POST['declaration_value'])) {
        foreach ($_POST['declaration_value'] as $cart_item_key => $declaration_value) {
            if (!empty($declaration_value) && isset($cart->cart_contents[$cart_item_key])) {
                $declaration_value = floatval(sanitize_text_field($declaration_value));
                if ($declaration_value < 1) {
                    $declaration_value = 1;
                }
                $cart->cart_contents[$cart_item_key]['declaration_value'] = number_format($declaration_value, 2, '.', '');
            } else {
                unset($cart->cart_contents[$cart_item_key]['declaration_value']);
            }
        }
    }
}

function add_declaration_value_to_order_item_meta($item, $cart_item_key, $values, $order) {
    if (isset($values['declaration_value'])) {
        $item->add_meta_data('Declaração de valor', '$' . number_format((float)$values['declaration_value'], 2, '.', ''), true);
    }
}

function check_declaration_fields_before_checkout() {
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
        $missing_declaration = false;

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (has_term('redirecionamento', 'product_cat', $product->get_id()) && empty($cart_item['declaration_value'])) {
                $missing_declaration = true;
                break;
            }
        }

        if ($missing_declaration) {
            wc_add_notice(__('Por favor, preencha todos os campos de declaração de valor e atualize o carrinho clicando no botão.', 'woocommerce'), 'error');
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
    }
}
