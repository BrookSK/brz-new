<?php

add_action('admin_menu', 'addons_settings_menu');
add_action('admin_init', 'register_addon_settings');

add_action('woocommerce_after_cart_item_name', 'display_addons_checkbox', 10, 2);
add_action('woocommerce_before_calculate_totals', 'apply_addon_to_cart_item', 10, 1);
add_filter('woocommerce_get_item_data', 'display_addons_in_order', 10, 2);
add_action('wp_loaded', 'save_addons_in_session');

add_action('woocommerce_checkout_create_order_line_item', 'add_addons_to_order_meta', 10, 3);
add_action('woocommerce_thankyou', 'clear_addons_session_after_order');

add_action('woocommerce_before_cart_table', 'display_order_addons');
add_action('woocommerce_cart_updated', 'save_selected_order_addons');

function addons_settings_menu() {
    add_submenu_page(
        'woocommerce',
        'Gerenciar Adicionais',
        'Gerenciar Adicionais',
        'manage_woocommerce',
        'addons-settings',
        'render_addon_settings_page'
    );
}
function render_addon_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Gerenciar Adicionais', 'addon'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('addon_settings_group');
            do_settings_sections('addon-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function register_addon_settings() {
    register_setting('addon_settings_group', 'addon_settings', 'sanitize_addon_settings');

    add_settings_section(
        'addon_main_section',
        __('Adicionais do Carrinho', 'addon'),
        'addon_section_callback',
        'addon-settings'
    );

    add_settings_field(
        'addon_table',
        '',
        'addon_table_callback',
        'addon-settings',
        'addon_main_section'
    );
}

function addon_section_callback() {
    echo __('Defina os adicionais disponíveis para itens do carrinho.', 'addon');
}

function get_woocommerce_product_categories() {
    $exclude_category_slugs = array('fatura');
    
    $exclude_category_ids = array();
    foreach ($exclude_category_slugs as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if ($term) {
            $exclude_category_ids[] = $term->term_id;
        }
    }

    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'exclude'    => $exclude_category_ids,
    ));

    return $terms;
}

function addon_table_callback() {
    $options = get_option('addon_settings');
    $categories = get_woocommerce_product_categories();
    ?>
    <table id="addon-table">
        <thead>
            <tr>
                <th scope="col"><?php _e('Nome', 'addon'); ?></th>
                <th scope="col"><?php _e('Valor', 'addon'); ?></th>
                <th scope="col"><?php _e('Tipo de Valor', 'addon'); ?></th>
                <th scope="col" class="category-col"><?php _e('Categoria', 'addon'); ?></th>
                <th scope="col"><?php _e('Tipo', 'addon'); ?></th>
                <th scope="col"><?php _e('Ações', 'addon'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($options['addons']) && is_array($options['addons'])) {
                foreach ($options['addons'] as $key => $addon) {
                    ?>
                    <tr>
                        <td><input type="text" name="addon_settings[addons][<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($addon['name']); ?>" required /></td>
                        <td><input type="number" min="0" step="0.01" name="addon_settings[addons][<?php echo esc_attr($key); ?>][value]" value="<?php echo esc_attr($addon['value']); ?>" required /></td>
                        <td>
                            <select name="addon_settings[addons][<?php echo esc_attr($key); ?>][value_type]">
                                <option value="absolute" <?php selected($addon['value_type'], 'absolute'); ?>><?php _e('Absoluto ($)', 'addon'); ?></option>
                                <option value="percent" <?php selected($addon['value_type'], 'percent'); ?>><?php _e('Percentual (%)', 'addon'); ?></option>
                            </select>
                        </td>
                        <td class="category-col">
                            <select name="addon_settings[addons][<?php echo esc_attr($key); ?>][category]" class="category-select">
                                <option value=""><?php _e('Qualquer Categoria', 'addon'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($addon['category'], $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="addon_settings[addons][<?php echo esc_attr($key); ?>][type]" class="addon-type">
                                <option value="category" <?php selected($addon['type'], 'category'); ?>><?php _e('Categoria', 'addon'); ?></option>
                                <option value="cart" <?php selected($addon['type'], 'cart'); ?>><?php _e('Carrinho', 'addon'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-addon button"><?php _e('Remover', 'addon'); ?></button></td>
                    </tr>
                    <?php
                }
            }
            ?>
        </tbody>
    </table>

    <button type="button" id="add-addon" class="button"><?php _e('Criar Adicional', 'addon'); ?></button>
    
    <script>
        jQuery(document).ready(function($) {
            function toggleCategoryField() {
                $('#addon-table .addon-type').each(function() {
                    var selectedType = $(this).val();
                    var $row = $(this).closest('tr');
                    
                    if (selectedType === 'category') {
                        $row.find('.category-col select').show();
                    } else {
                        $row.find('.category-col select').hide();
                    }
                });
            }
            toggleCategoryField();

            $('#addon-table').on('change', '.addon-type', function() {
                toggleCategoryField();
            });

            $('#add-addon').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="text" name="addon_settings[addons][${uniqueKey}][name]" required /></td>
                        <td><input type="number" min="0" step="0.01" name="addon_settings[addons][${uniqueKey}][value]" required /></td>
                        <td>
                            <select name="addon_settings[addons][${uniqueKey}][value_type]">
                                <option value="absolute"><?php _e('Absoluto ($)', 'addon'); ?></option>
                                <option value="percent"><?php _e('Percentual (%)', 'addon'); ?></option>
                            </select>
                        </td>
                        <td class="category-col">
                            <select name="addon_settings[addons][${uniqueKey}][category]" class="category-select">
                                <option value=""><?php _e('Qualquer Categoria', 'addon'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>">
                                        <?php echo esc_html(str_replace('`', '\`', $category->name)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="addon_settings[addons][${uniqueKey}][type]" class="addon-type">
                                <option value="category"><?php _e('Categoria', 'addon'); ?></option>
                                <option value="cart"><?php _e('Carrinho', 'addon'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-addon button button-secondary"><?php _e('Remover', 'addon'); ?></button></td>
                    </tr>
                `;
                $('#addon-table tbody').append(newRow);
                toggleCategoryField();
            });

            $(document).on('click', '.remove-addon', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_addon_settings($input) {
    $output = array();
    if (isset($input['addons']) && is_array($input['addons'])) {
        foreach ($input['addons'] as $key => $addon) {
            $output['addons'][$key] = array(
                'name'       => sanitize_text_field($addon['name']),
                'value_type' => sanitize_text_field($addon['value_type']),
                'value'      => floatval(sanitize_text_field($addon['value'])),
                'category'   => sanitize_text_field($addon['category']),
                'type'       => sanitize_text_field($addon['type']),
            );
        }
    }
    return $output;
}

function display_addons_in_order($item_data, $cart_item) {
    if (!is_cart() && isset($cart_item['addons']) && !empty($cart_item['addons'])) {
        foreach ($cart_item['addons'] as $addon) {
            $item_data[] = array(
                'name'  => $addon['name'],
                'value' => wc_price($addon['value']),
            );
        }
    }
    return $item_data;
}

function hide_variations_on_cart_page() {
    if (is_cart()) {
        ?>
        <style>
            .woocommerce-cart .variation {
                display: none !important;
            }
            .addon-container p {
                margin-top: 12px;
                margin-bottom: 4px;
                font-size: 14px;
                font-weight: bold;
            }
            .addon-container label {
                display: flex;
                align-items: center;
                gap: 4px;
                margin-bottom: 2px;
                font-size: 12px;
            }
        </style>
        <?php
    }
}

function display_addons_checkbox($cart_item, $cart_item_key) {
    $product = wc_get_product($cart_item['product_id']);
    $addon_settings = get_option('addon_settings', array());
    $product_id = $cart_item['product_id'];
    $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    $product_price = get_post_meta($product_id, '_price', true);
    
    if (WC_Subscriptions_Product::is_subscription($product) || has_term('fatura', 'product_cat', $product_id)) {
        return;
    }

    $has_addons = false;
    if (isset($addon_settings['addons']) && !empty($addon_settings['addons'])) {
        foreach ($addon_settings['addons'] as $addon) {
            if ($addon['type'] === 'category' && (empty($addon['category']) || in_array($addon['category'], $product_categories))) {
                $has_addons = true;
                break;
            }
        }
    }

    if (!$has_addons) {
        return;
    }

    if (isset($addon_settings['addons']) && !empty($addon_settings['addons'])) {
        $nonce = wp_create_nonce('cart_addons_nonce');
        ?>
        <div class="addon-container" id="addon-container-<?php echo esc_attr($cart_item_key); ?>">
            <p>Adicionais do Produto</p>
            <?php
            foreach ($addon_settings['addons'] as $key => $addon) {
                if ($addon['type'] === 'category' && (empty($addon['category']) || in_array($addon['category'], $product_categories))) {
                    $checked = isset($cart_item['addons']) && in_array($key, array_column($cart_item['addons'], 'key')) ? 'checked' : '';
                    $price = $addon['value_type'] === 'percent' ?
                        ($addon['value'] / 100) * $product_price :
                        $addon['value'];
                    if (class_exists('WCCS')) {
                        global $WCCS;
                        $price = $WCCS->wccs_price_conveter($price);
                    }
                    $addon_value_text = wc_price($price);
                    ?>
                    <div class="addon-checkbox">
                        <label>
                            <input type="checkbox" id="addon-<?php echo esc_attr( $cart_item_key . '-' . $key ); ?>" name="cart_addons[<?php echo esc_attr( $cart_item_key ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $key ); ?>" <?php echo $checked; ?> style="margin-right: 10px;" />
                            <?php echo esc_html( $addon['name'] ); ?> <?php echo $addon_value_text; ?>
                        </label>
                    </div>
                    <?php
                }
            }
            ?>
            <input type="hidden" name="cart_addons[<?php echo esc_attr($cart_item_key); ?>][]" value="0" />
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateHiddenInput() {
                    var containerId = '#addon-container-<?php echo esc_js($cart_item_key); ?>';
                    var checkboxes = $(containerId + ' input[type="checkbox"]');
                    var hiddenInput = $(containerId + ' input[type="hidden"]');
                    
                    var anyChecked = checkboxes.is(':checked');
                    hiddenInput.val(anyChecked ? 'checked' : '');
                }
                updateHiddenInput();

                $(document).on('change', containerId + ' input[type="checkbox"]', function() {
                    updateHiddenInput();
                });
            });
        </script>
        <?php
    }
}

function apply_addon_to_cart_item($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (did_action('woocommerce_before_calculate_totals') > 1) {
        return;
    }
    
    $addon_settings = get_option('addon_settings');
    if (empty($addon_settings) || empty($addon_settings['addons'])) {
        return;
    }

    $cart_addons = WC()->session->get('cart_addons', array());
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        $product_id = $cart_item['product_id'];

        if (WC_Subscriptions_Product::is_subscription($product) || has_term('fatura', 'product_cat', $product_id)) {
            continue;
        }

        if (isset($cart_addons[$cart_item_key])) {
            $product_id = $product->get_id();
            $current_price = get_post_meta($product_id, '_price', true);
            $posted_addons = $cart_addons[$cart_item_key];
            $price_adjustment = 0;
            $new_addons = array();

            foreach ($posted_addons as $addon_key => $addon_value) {
                if ($addon_key != 0) {
                    $addon = $addon_settings['addons'][$addon_key];
                    $addon_price = $addon['value'];
                    if ($addon['value_type'] === 'percent') {
                        $addon_price = ($addon['value'] / 100) * $current_price;
                    }

                    $new_addons[$addon_key] = array(
                        'key'   => $addon_key,
                        'name'  => sanitize_text_field($addon['name']),
                        'value' => $addon_price,
                        'type'  => 'addon',
                    );
                    $price_adjustment += $addon_price;
                }
            }
        
            $cart_item['addons'] = !empty($new_addons) ? $new_addons : null;
            $cart_item['data']->set_price($current_price + $price_adjustment);
            
            if (empty($new_addons)) {
                unset($cart_item['addons']);
            }
        
            $cart->cart_contents[$cart_item_key] = $cart_item;
        }        
    }
}


function add_addons_to_order_meta($item, $cart_item_key, $cart_item) {
    if (isset($cart_item['addons']) && !empty($cart_item['addons'])) {
        $addons_data = array();
        foreach ($cart_item['addons'] as $addon) {
            $price = $addon['value'];
            if (class_exists('WCCS')) {
                global $WCCS;
                $price = $WCCS->wccs_price_conveter($price);
            }
            $addons_data[] = sprintf('%s: %s', esc_html($addon['name']), wc_price($price));
        }
        $addons_data_string = implode(', ', $addons_data);
        $item->add_meta_data(__('Adicionais', 'textdomain'), $addons_data_string, true);
    }
}

function save_addons_in_session() {
    if (isset($_POST['cart_addons'])) {
        WC()->session->set('cart_addons', $_POST['cart_addons']);
    }
}

function clear_addons_session_after_order($order_id) {
    WC()->session->set('cart_addons', array());
}

function display_order_addons() {
    $addon_settings = get_option('addon_settings', array());
    $cart = WC()->cart;
    if (!isset($addon_settings['addons']) || all_fatura_or_subscription($cart)) {
        return;
    }
    $order_addons = array_filter($addon_settings['addons'], function($addon) {
        return $addon['type'] === 'cart';
    });

    $selected_addons = WC()->session->get('order_addons', array());
    if (!empty($order_addons)) {
        ?>
        <div class="order-addons-section">
            <h2><strong><?php _e('Adicionais do Pedido', 'addon'); ?></h2>
            <table class="order-addons-table shop_table">
                <thead>
                    <tr>
                        <th><?php _e('Nome', 'addon'); ?></th>
                        <th><?php _e('Valor', 'addon'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($order_addons as $key => $addon) {
                        $price = $addon['value'];
                        if (class_exists('WCCS')) {
                            global $WCCS;
                            $price = $WCCS->wccs_price_conveter($price);
                        }
                        
                        if ($addon['value_type'] === 'percent') {
                            $cart_total = 0;
                            foreach ($cart->get_cart() as $cart_item) {
                                $product_id = $cart_item['product_id'];
                                $product_price = get_post_meta($product_id, '_price', true);
                                $quantity = $cart_item['quantity'];
                                $cart_total += $product_price * $quantity;
                            }
                            $addon_value_text = wc_price(($price / 100) * $cart_total);
                        } else {
                            $addon_value_text = wc_price($price);
                        }
                    ?>
                        <tr>
                            <td>
                                <label>
                                    <input type="checkbox" name="order_addons[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($key); ?>"
                                        <?php echo isset($selected_addons[$key]) ? 'checked' : ''; ?> />
                                    <?php echo esc_html($addon['name']); ?>
                                </label>
                            </td>
                            <td><?php echo $addon_value_text; ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <input type="hidden" name="order_addons_nonce" value="<?php echo wp_create_nonce('order_addons_nonce'); ?>" />
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function enableUpdateCartButton() {
                    $('.order-addons-table input[type="checkbox"]').on('change', function() {
                        $('button[name="update_cart"]').prop('disabled', false);
                    });
                }

                enableUpdateCartButton();

                $(document.body).on('updated_cart_totals', function() {
                    enableUpdateCartButton();
                });
            });
        </script>
        <?php
    }
}


function save_selected_order_addons() {
    if (isset($_POST['order_addons_nonce']) && wp_verify_nonce($_POST['order_addons_nonce'], 'order_addons_nonce')) {
        $order_addons = isset($_POST['order_addons']) ? $_POST['order_addons'] : array();
        WC()->session->set('order_addons', $order_addons);
    }
}