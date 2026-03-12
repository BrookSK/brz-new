<?php

add_action('wp_footer', 'add_cart_update_script_to_footer');
add_action('woocommerce_cart_contents', 'display_parallel_cart_items');
add_action('woocommerce_cart_item_name', 'add_extra_column_to_cart_item', 20, 3);
add_action('woocommerce_cart_updated', 'handle_cart_update_from_checkboxes');

function add_cart_update_script_to_footer() {
    if (is_cart()) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                function enableCartUpdateButton() {
                    $('input.select_item_checkbox').on('change', function() {
                        $('button[name="update_cart"]').prop('disabled', false);
                    });
                }

                enableCartUpdateButton();
                $(document.body).on('updated_cart_totals', function() {
                    enableCartUpdateButton();
                });
            });
        </script>
        <?php
    }
}

function display_parallel_cart_items() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $parallel_cart = get_user_meta($user_id, '_parallel_cart', true) ?: array();

    foreach ($parallel_cart as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        if (!$product) {
            continue;
        }
        $product_permalink = $product->is_visible() ? $product->get_permalink($cart_item) : '';
        $product_name = $product_permalink ? sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $product->get_name()) : $product->get_name();
        $quantity = $cart_item['quantity'];
        $product_price = $product->get_price();
        $line_total = $product->get_price() * $quantity;

        ?>
        <tr class="woocommerce-cart-form__cart-item fictitious-item" style="filter: grayscale(100%); opacity: 0.6;">
            <td class="product-remove">
                <a href="#" class="remove-fictitious-item jupiterx-icon-times" aria-label="Remove this item" data-product_id="fictitious_<?php echo esc_attr($cart_item_key); ?>" data-product_sku=""></a>
            </td>
            <td class="product-thumbnail">
                <?php echo $product->get_image(); ?>
                <p style="margin-bottom: 0; display: flex; align-items: center; gap: 5px;">
                    <input type="hidden" name="selected_items[<?php echo esc_attr($cart_item_key); ?>]" value="0" />
                    <input type="checkbox" name="selected_items[<?php echo esc_attr($cart_item_key); ?>]" class="select_item_checkbox" value="1" />
                    <?php echo $product_name; ?>
                    <p style="font-weight: 500; font-style: italic;"><i><span>*</span>O produto selecionado está reservado, mas ainda não foi adicionado ao carrinho. <br> Selecione o item e atualize o carrinho para adicionar.</i></p>
                </p>
            </td>
            <td class="product-price" data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                <?php echo wc_price($product_price); ?>
            </td>
            <td class="product-quantity" data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                <div class="quantity">
                    <label class="screen-reader-text" for="quantity_fictitious"><?php esc_html_e('Quantity', 'woocommerce'); ?></label>
                    <input type="number" id="quantity_fictitious" class="input-text qty text" name="cart[fictitious][qty]" value="<?php echo esc_html($quantity); ?>" size="4" min="1" step="1" autocomplete="off" disabled>
                </div>
            </td>
            <td class="product-subtotal" data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>">
                <?php echo wc_price($line_total); ?>
            </td>
        </tr>
        <?php
    }
}

function add_extra_column_to_cart_item($product_name, $cart_item, $cart_item_key) {
    if (is_cart()) {
        $product = wc_get_product($cart_item['product_id']);
        $product_permalink = $product->is_visible() ? $product->get_permalink($cart_item) : '';
        $product_name = $product_permalink ? sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $product->get_name()) : $product->get_name();

        ?>
        <p style="margin-bottom: 0; display: flex; align-items: center; gap: 5px">
            <input type="hidden" name="selected_items[<?php echo esc_attr($cart_item_key); ?>]" value="0" />
            <input type="checkbox" checked name="selected_items[<?php echo esc_attr($cart_item_key); ?>]" class="select_item_checkbox" value="1"/>
            <span style="margin-left: 10px;"><?php echo $product_name; ?></span>
        </p>
        <?php
    }
    else {
        echo $product_name;
    }
}

function handle_cart_update_from_checkboxes() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    static $is_updating = false;
    if ($is_updating) {
        return;
    }
    $is_updating = true;

    if (!is_user_logged_in()) {
        $is_updating = false;
        return;
    }

    $user_id = get_current_user_id();
    $parallel_cart = get_user_meta($user_id, '_parallel_cart', true);
    if (empty($parallel_cart)) {
        $parallel_cart = array();
    }

    $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : array();
    $cart = WC()->cart->get_cart();

    $items_to_add = array();
    $items_to_remove = array();

    foreach ($selected_items as $cart_item_key => $is_checked) {
        if ($is_checked) {
            if (isset($parallel_cart[$cart_item_key])) {
                $items_to_add[] = array(
                    'product_id' => $parallel_cart[$cart_item_key]['product_id'],
                    'quantity' => $parallel_cart[$cart_item_key]['quantity'],
                    'key' => $cart_item_key
                );
                unset($parallel_cart[$cart_item_key]);
            }
        } else {
            if (isset($cart[$cart_item_key])) {
                $cart_item = $cart[$cart_item_key];
                $parallel_cart[] = array(
                    'product_id' => $cart_item['product_id'],
                    'quantity' => $cart_item['quantity'],
                    'key' => $cart_item_key
                );
                $items_to_remove[] = $cart_item_key;
            }
        }
    }

    foreach ($items_to_add as $item) {
        WC()->cart->add_to_cart($item['product_id'], $item['quantity']);
    }

    foreach ($items_to_remove as $cart_item_key) {
        WC()->cart->remove_cart_item($cart_item_key);
    }

    if (!empty($parallel_cart)) {
        update_user_meta($user_id, '_parallel_cart', $parallel_cart);
    } else {
        delete_user_meta($user_id, '_parallel_cart');
    }

    if (empty($cart)) {
        foreach ($parallel_cart as $cart_item_key => $cart_item) {
            WC()->cart->add_to_cart($cart_item['product_id'], $cart_item['quantity']);
        }
        delete_user_meta($user_id, '_parallel_cart');
    }

    $is_updating = false;
}
