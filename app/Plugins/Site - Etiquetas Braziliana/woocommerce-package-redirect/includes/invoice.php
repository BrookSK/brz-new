<?php

use Dompdf\Dompdf;
use Dompdf\Options;

add_action('post_edit_form_tag', 'add_enctype_to_order_edit_form');
add_shortcode('edit_invoice', 'edit_invoice_shortcode');

add_action('template_redirect', 'handle_invoice_update');
add_action('woocommerce_order_details_after_order_table', 'add_invoice_edit_link');

add_filter('woocommerce_order_actions', 'add_generate_pdf_action');
add_action('woocommerce_order_action_generate_pdf', 'generate_pdf_for_order');
add_filter('manage_edit-shop_order_columns', 'add_pdf_generated_column');
add_action('manage_shop_order_posts_custom_column', 'show_pdf_generated_column_data', 20, 2);

add_filter('woocommerce_order_actions', 'add_release_invoice_action');
add_action('woocommerce_order_action_release_invoice', 'release_invoice_for_order');

add_action('add_meta_boxes', 'add_invoice_meta_box');
add_action('save_post_shop_order', 'save_invoice_section_to_order', 10, 1);
add_filter('woocommerce_order_item_get_formatted_meta_data', 'hide_custom_order_item_meta', 10, 2);

function add_enctype_to_order_edit_form() {
    global $post;
    if (isset($post->post_type) && $post->post_type === 'shop_order') {
        echo ' enctype="multipart/form-data"';
    }
}

function edit_invoice_shortcode($atts) {
    if (!is_user_logged_in()) {
        return 'Você precisa estar logado para visualizar seu invoice.';
    }

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $order = wc_get_order($order_id);

    if (!$order || $order->get_user_id() != get_current_user_id()) {
        return 'Invoice não encontrado ou você não tem permissão para visualizá-lo.';
    }
    
    $items = $order->get_items();
    $attached_images = get_post_meta($order_id, '_invoice_images', true);
    $attached_images = is_array($attached_images) ? $attached_images : [];
    
    $cpf = $order->get_meta('_billing_cpf');
    $cellphone = $order->get_meta('_billing_phone');
    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $email = $order->get_billing_email();
    $full_name = $first_name . ' ' . $last_name;
    
    $shipping_address_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
    $shipping_number = $order->get_meta('_shipping_number') ?: $order->get_meta('_billing_number');
    $shipping_neighborhood = $order->get_meta('_shipping_neighborhood') ?: $order->get_meta('_billing_neighborhood');
    $shipping_address_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
    $shipping_city = $order->get_shipping_city() ?: $order->get_billing_city();
    $shipping_state = $order->get_shipping_state() ?: $order->get_billing_state();
    $shipping_postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
    $shipping_country = $order->get_shipping_country() ?: $order->get_billing_country();
    
    $is_editable = ($order->get_status() === 'invoice-liberado');

    ob_start();
    ?>
    <div class="woocommerce-notices-wrapper">
        <?php wc_print_notices(); ?>
    </div>
    <div class="woocommerce edit-invoice-container">
        <div class="woocommerce-customer-details">
            <h3><?php _e('Informações do Cliente', 'woocommerce'); ?></h3>
            <table class="woocommerce-customer-info">
            <tbody>
                <tr>
                    <th><?php _e('Nome', 'woocommerce'); ?></th>
                    <td><?php echo esc_html($full_name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('CPF', 'woocommerce'); ?></th>
                    <td><?php echo esc_html($cpf); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Celular', 'woocommerce'); ?></th>
                    <td><?php echo esc_html($cellphone); ?></td>
                </tr>
                <tr>
                    <th><?php _e('E-mail', 'woocommerce'); ?></th>
                    <td><?php echo esc_html($email); ?></td>
                </tr>
            </tbody>
        </table>

        </div>
        <form method="post" enctype="multipart/form-data">
            <h3><?php _e('Endereço de Entrega', 'woocommerce'); ?></h3>
            <table class="edit-invoice-shipping">
                <tbody>
                    <tr>
                        <td><?php _e('Endereço', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_address_1" value="<?php echo esc_attr($shipping_address_1); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Número', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_number" value="<?php echo esc_attr($shipping_number); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Bairro', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_neighborhood" value="<?php echo esc_attr($shipping_neighborhood); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Complemento', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_address_2" value="<?php echo esc_attr($shipping_address_2); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Cidade', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_city" value="<?php echo esc_attr($shipping_city); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Estado', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_state" value="<?php echo esc_attr($shipping_state); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('CEP', 'woocommerce'); ?></td>
                        <td>
                            <input type="text" name="shipping_postcode" value="<?php echo esc_attr($shipping_postcode); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> pattern="\d{5}-\d{3}" class="input-text" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <h3><?php _e('Itens do Pedido', 'woocommerce'); ?></h3>
            <table class="shop_table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php _e('Nome', 'woocommerce'); ?></th>
                        <th><?php _e('Preço ($)', 'woocommerce'); ?></th>
                        <th><?php _e('Quantidade', 'woocommerce'); ?></th>
                        <th><?php _e('Peso (kg)', 'woocommerce'); ?></th>
                        <th><?php _e('Bateria', 'woocommerce'); ?></th>
                        <th><?php _e('Perfume', 'woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item_id => $item) : ?>
                        <?php
                        $product = $item->get_product();
                        if (!$product) {
                            continue;
                        }
                        $product_id = $product->get_id();
                        if (WC_Subscriptions_Product::is_subscription($product) || has_term('fatura', 'product_cat', $product_id)) {
                            continue;
                        }
                        $product_name = $item->get_meta('_product_name') ?: $item->get_name();
                        $declaration_value = $item->get_meta('_declaration_value') ?: $item->get_meta('Declaração de valor') ?: $item->get_total();
                        $declaration_value = str_replace(array('$', 'R$', '€'), '', $declaration_value);
                        $declaration_value = number_format(floatval($declaration_value), 2, '.', '');
                        $bateria = $item->get_meta('_bateria');
                        $perfume = $item->get_meta('_perfume');
                        $quantity = $item->get_quantity();
                        $weight = $product->get_weight();
                        $product_image = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
                        $product_image = is_array($product_image) ? $product_image[0] : null;
                        ?>
                        <tr>
                            <td>
                                <?php if ($product_image) : ?>
                                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" style="max-width: 50px; max-height: 50px;" />
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="product_name[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($product_name); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                            </td>
                            <td>
                                <input type="number" step="0.01" min="1" name="declaration_value[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($declaration_value); ?>" <?php echo $is_editable ? '' : 'readonly'; ?> class="input-text" />
                            </td>
                            <td>
                                <?php echo esc_html($quantity); ?>
                            </td>
                            <td>
                                <?php echo esc_html($weight); ?>
                            </td>
                            <td>
                                <select name="product_bateria[<?php echo esc_attr($item_id); ?>]" <?php echo $is_editable ? '' : 'disabled'; ?> class="select">
                                    <option value="N" <?php selected($bateria, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($bateria, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                            <td>
                                <select name="product_perfume[<?php echo esc_attr($item_id); ?>]" <?php echo $is_editable ? '' : 'disabled'; ?> class="select">
                                    <option value="N" <?php selected($perfume, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($perfume, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3><?php _e('Imagens', 'woocommerce'); ?></h3>
            <div id="image-preview-container" style="display: flex; flex-wrap: wrap;">
                <?php foreach ($attached_images as $image_url) : ?>
                    <div class="image-preview-item" style="position: relative; margin: 8px;">
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 120px; max-height: 120px;" />
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($is_editable) : ?>
                <button type="button" id="show-contest-form" class="button wp-element-button"><?php _e('Contestar', 'woocommerce'); ?></button>
                <div id="contest-form" style="display: none; margin-top: 20px;">
                    <table class="form-table" style="width: 100%; max-width: 600px; margin: 0 auto;">
                        <tr>
                            <th style="text-align: left; vertical-align: top; padding-right: 10px;">
                                <label for="contest-reason"><?php _e('Motivo da Contestação:', 'woocommerce'); ?></label>
                            </th>
                            <td>
                                <textarea id="contest-reason" name="invoice_contest" placeholder="<?php _e('Insira suas observações ou motivos para contestar o invoice.', 'woocommerce'); ?>" style="width: 100%; height: 100px; box-sizing: border-box; padding: 10px;"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: right; padding-top: 10px;">
                                <input type="hidden" name="invoice_contest_nonce" value="<?php echo wp_create_nonce('contest_invoice_nonce'); ?>" />
                                <button type="submit" id="send-contest" class="button wp-element-button"><?php _e('Enviar Contestação', 'woocommerce'); ?></button>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            <input type="hidden" name="invoice_nonce" value="<?php echo wp_create_nonce('save_invoice'); ?>" />
            <button type="submit" id="save-invoice" class="button wp-element-button error" <?php echo $is_editable ? '' : 'disabled'; ?>><?php _e('Finalizar', 'woocommerce'); ?></button>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#show-contest-form').on('click', function() {
                $('#contest-form').slideToggle();
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

function handle_invoice_update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_nonce']) && wp_verify_nonce($_POST['invoice_nonce'], 'save_invoice')) {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $order = wc_get_order($order_id);

        if ($order && $order->get_user_id() == get_current_user_id()) {
            if ($order->get_status() !== 'invoice-liberado') {
                wp_die('O invoice não pode ser editado no momento.');
            }

            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                foreach ($_POST['product_name'] as $item_id => $product_name) {
                    wc_update_order_item_meta($item_id, '_product_name', sanitize_text_field($product_name));
                }
            }

            if (isset($_POST['declaration_value']) && is_array($_POST['declaration_value'])) {
                foreach ($_POST['declaration_value'] as $item_id => $product_price) {
                    $item = new WC_Order_Item_Product($item_id);
                    $value =
                        floatval(str_replace(
                            array('$', 'R$', '€'), '',
                            wc_get_order_item_meta($item_id, 'Declaração de valor', true)
                        )) ?:
                        $item->get_total();
                    $new_value = floatval($product_price);

                    if ($new_value >= 1 && $new_value <= floatval($value)) {
                        wc_update_order_item_meta($item_id, '_declaration_value', $new_value);
                    }
                }
            }

            if (isset($_POST['product_bateria']) && is_array($_POST['product_bateria'])) {
                foreach ($_POST['product_bateria'] as $item_id => $bateria) {
                    wc_update_order_item_meta($item_id, '_bateria', sanitize_text_field($bateria));
                }
            }

            if (isset($_POST['product_perfume']) && is_array($_POST['product_perfume'])) {
                foreach ($_POST['product_perfume'] as $item_id => $perfume) {
                    wc_update_order_item_meta($item_id, '_perfume', sanitize_text_field($perfume));
                }
            }

            if (isset($_POST['shipping_address_1'])) {
                $order->set_shipping_address_1(sanitize_text_field($_POST['shipping_address_1']));
                $order->set_billing_address_1(sanitize_text_field($_POST['shipping_address_1']));
            }
            if (isset($_POST['shipping_address_2'])) {
                $order->set_shipping_address_2(sanitize_text_field($_POST['shipping_address_2']));
                $order->set_billing_address_2(sanitize_text_field($_POST['shipping_address_2']));
            }
            if (isset($_POST['shipping_city'])) {
                $order->set_shipping_city(sanitize_text_field($_POST['shipping_city']));
                $order->set_billing_state(sanitize_text_field($_POST['shipping_state']));
            }
            if (isset($_POST['shipping_state'])) {
                $order->set_shipping_state(sanitize_text_field($_POST['shipping_state']));
                $order->set_billing_state(sanitize_text_field($_POST['shipping_state']));
            }
            if (isset($_POST['shipping_postcode'])) {
                $order->set_shipping_postcode(sanitize_text_field($_POST['shipping_postcode']));
                $order->set_billing_postcode(sanitize_text_field($_POST['shipping_postcode']));
            }
            if (isset($_POST['shipping_country'])) {
                $order->set_shipping_country(sanitize_text_field($_POST['shipping_country']));
                $order->set_billing_country(sanitize_text_field($_POST['shipping_country']));
            }
            if (isset($_POST['shipping_number'])) {
                $order->update_meta_data('_shipping_number', sanitize_text_field($_POST['shipping_number']));
                $order->update_meta_data('_billing_number', sanitize_text_field($_POST['shipping_number']));
            }
            if (isset($_POST['shipping_neighborhood'])) {
                $order->update_meta_data('_shipping_neighborhood', sanitize_text_field($_POST['shipping_neighborhood']));
                $order->update_meta_data('_billing_neighborhood', sanitize_text_field($_POST['shipping_neighborhood']));
            }

            $order->update_status('wc-invoice-fechado', 'Invoice conferido e fechado.');
            wc_add_notice(__('Invoice atualizado com sucesso!', 'woocommerce'), 'success');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['invoice_contest']) &&
        !empty($_POST['invoice_contest']) &&
        isset($_POST['invoice_contest_nonce']) &&
        wp_verify_nonce($_POST['invoice_contest_nonce'], 'contest_invoice_nonce')
    ) {
        update_post_meta($order_id, '_invoice_contest', $_POST['invoice_contest']);
        $order->update_status('wc-invoice-ct', 'Invoice contestado devido a: ' . $_POST['invoice_contest']);
        wc_add_notice(__('Contestação enviada com sucesso!', 'woocommerce'), 'success');
    }
}


function add_invoice_edit_link($order) {
    $order_id = $order->get_id();
    $edit_invoice_url = add_query_arg('order_id', $order_id, site_url('conferir-invoice'));
    echo '<a href="' . esc_url($edit_invoice_url) . '">' . __('Conferir Invoice', 'text-domain') . '</a>';
}

function add_generate_pdf_action($actions) {
    $actions['generate_pdf'] = __('Gerar PDF', 'woocommerce');
    return $actions;
}

function generate_pdf_for_order($order) {
    if (!is_user_logged_in()) {
        wp_die('Você precisa estar logado para gerar o PDF.');
    }

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf = new Dompdf();

    ob_start();
    
    $logo_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/logo-braziliana.png';

    $order_category = array();
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) continue;

        if (has_term('redirecionamento', 'product_cat', $product->get_id())) {
            $order_category[] = '- Redirecionamento';
            break;
        }
        elseif (has_term('grupo-de-compras', 'product_cat', $product->get_id())) {
            $order_category[] = '- Grupo de Compras';
        }
        else {
            $order_category[] = '- Compra do Site';
        }
    }

    $shipping_address = $order->get_shipping_address_1();
    $shipping_address .= $order->get_shipping_address_2() ? ', ' . $order->get_shipping_address_2() : '';
    $shipping_address .= $order->get_shipping_city() ? ', ' . $order->get_shipping_city() : '';
    $shipping_address .= $order->get_shipping_state() ? ', ' . $order->get_shipping_state() : '';
    $shipping_address .= $order->get_shipping_postcode() ? ', ' . $order->get_shipping_postcode() : '';
    $shipping_address .= $order->get_shipping_country() ? ', ' . $order->get_shipping_country() : '';

    $order_date = $order->get_date_created()->date('d/m/Y');
    $accept_replacement = get_post_meta($order->get_id(), '_accept_product_replacement', true);

    $currency_code = $order->get_currency();
    $currency_symbol = get_woocommerce_currency_symbol($currency_code);

    ?>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { position: relative; width: 100%; height: 60px; }
            .header img { width: 150px; }
            .header p { margin-top: 0; position: absolute; float: right; top: 0; }
            .section-title { font-weight: bold; font-size: 18px; margin-top: 20px; }
            .info-label { font-weight: bold; }
            .table { width: 100%; border-collapse: collapse; }
            .table th, .table td { padding: 8px; border: 1px solid #ddd; word-wrap: break-word; max-width: 150px; }
            .table .product-image { width: 0; padding: 0; }
            .img-container { width: 60px; height: 60px; overflow: hidden; }
            .img-container img { width: 100%; height: 100%; object-fit: cover; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .line { font-size: 12pt; font-weight: 400; margin-left: 8px; }
        </style>
    </head>
    <body>
        <div class="header">
            <img src="<?php echo image_to_base64($logo_path); ?>" alt="">
            <p><?php echo implode("<br/>", $order_category); ?></p>
        </div>
        <h2 style="text-align: center;">Invoice - Pedido #<?php echo $order->get_id(); ?></h2>

        <p class="section-title">Endereço de Destino</p>
        <table class="info-table">
            <tr>
                <td>Nome:</td>
                <td><?php echo $order->get_formatted_billing_full_name(); ?></td>
            </tr>
            <tr>
                <td>CPF:</td>
                <td><?php echo $order->get_meta('_billing_cpf'); ?></td>
            </tr>
            <tr>
                <td>Suite:</td>
                <td><?php echo get_user_meta($order->get_user_id(), 'suite', true); ?></td>
            </tr>
            <tr>
                <td>Celular:</td>
                <td><?php echo $order->get_meta('_billing_phone'); ?></td>
            </tr>
            <tr>
                <td>Email:</td>
                <td><?php echo $order->get_billing_email(); ?></td>
            </tr>
            <tr>
                <td>Endereço:</td>
                <td><?php echo $shipping_address; ?></td>
            </tr>
            <tr>
                <td>Data do Pedido:</td>
                <td><?php echo $order_date; ?></td>
            </tr>
            <tr>
                <td>Aceita substituição:</td>
                <td>
                    <strong><?php echo $accept_replacement === 'yes' ? '<span style="color: green;">Sim</span>' : '<span style="color: red;">Não</span>'; ?></strong>
                </td>
            </tr>
        </table>

        <h3 class="section-title">Declaração Aduaneira</h3>
        <table class="table">
            <tr>
                <th class="product-image">Foto</th>
                <th>Produto</th>
                <th>Qtde</th>
                <th>Peso (kg)</th>
                <th>Preço (<?php echo $currency_symbol; ?>)</th>
                <th>Total (<?php echo $currency_symbol; ?>)</th>
            </tr>
            <?php 
            $total = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product ||
                    WC_Subscriptions_Product::is_subscription($product) ||
                    has_term('fatura', 'product_cat', $product->get_id())
                ) {
                    continue;
                }

                $ncm = $item->get_meta('_ncm') ?: "<NCM>";
                $product_name = $item->get_meta('_product_name');
                $quantity = $item->get_quantity();
                $weight = $product->get_weight();
                $declaration_value = floatval(str_replace(array('$', 'R$', '€'), '', $item->get_meta('_declaration_value', true)));
                $formatted_declaration_value = number_format($declaration_value, 2);
                $item_total = number_format($quantity * $declaration_value, 2);
                $total += $item_total;
                $attachment_id = $product->get_image_id();
                $thumbnail_path = get_attached_file($attachment_id);
                                
                ?>
                <tr>
                    <td class="product-image"><div class="img-container">
                        <?php if (!empty($thumbnail_path)) : ?>
                            <img src="<?php echo image_to_base64($thumbnail_path); ?>" /></img>
                        <?php endif; ?>
                    </div>
                    <td><?php echo "{$ncm} - {$product_name}"; ?></td></td>
                    <td><?php echo $quantity; ?></td>
                    <td><?php echo $weight; ?></td>
                    <td><?php echo $formatted_declaration_value; ?></td>
                    <td><?php echo $item_total; ?></td>
                </tr>
                <?php

                $fees_total = 0;
            }
            foreach ($order->get_fees() as $fee) {
                $fee_total = $fee->get_total();
                $fees_total += $fee_total;
                ?>
                <tr>
                    <td colspan="5"><strong><?php echo $fee->get_name(); ?></strong></td>
                    <td><?php echo number_format($fee_total, 2); ?></td>
                </tr>
                <?php
            }
            ?>
            <tr class="total-row">
                <td colspan="5"><strong>Total</strong></td>
                <td><?php echo number_format($total + $fees_total, 2); ?></td>
            </tr>
        </table>

        <p class="section-title">Verificado por: <span class="line">______________________________</span></p>
    </body>
    </html>
    <?php

    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $dompdf->stream('invoice_' . $order->get_id() . '.pdf', array('Attachment' => false));
    update_post_meta($order->get_id(), '_pdf_generated', true);
    exit;
}


function add_pdf_generated_column($columns) {
    $columns['pdf_generated'] = __('Impresso', 'woocommerce');
    return $columns;
}

function show_pdf_generated_column_data($column, $post_id) {
    if ($column === 'pdf_generated') {
        $pdf_generated = get_post_meta($post_id, '_pdf_generated', true);
        echo $pdf_generated
            ? '<strong style="color: green;">Sim</strong>'
            : '<strong style="color: red;">Não</strong>';
    }
}

function add_release_invoice_action($actions) {
    $actions['release_invoice'] = __('Liberar Invoice', 'woocommerce');
    return $actions;
}

function release_invoice_for_order($order) {
    // $order_id = $order->get_id();
    
    // $customer_email = $order->get_billing_email();
    // $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    
    // $subject = 'Invoice liberado';
    // $headers = array('Content-Type: text/html; charset=UTF-8');
    // wp_mail(
    //     $customer_email,
    //     $subject,
    //     "Seu invoice para o pedido {$order_id} foi liberada e está disponível para visualização.",
    //     $headers
    // );
    $order->update_status('invoice-liberado', __('Invoice foi liberado para o cliente.', 'woocommerce'));
}

function add_invoice_meta_box() {
    add_meta_box(
        'invoice_meta_box',
        __('Invoice', 'woocommerce'),
        'add_invoice_section_to_order_admin_page',
        'shop_order',
        'advanced',
        'high'
    );
}

function add_invoice_section_to_order_admin_page($post) {
    $order = wc_get_order($post->ID);

    if (!$order || !$order->get_id()) {
        return;
    }

    $items = $order->get_items();
    $attached_images = get_post_meta($order->get_id(), '_invoice_images', true);
    $attached_images = is_array($attached_images) ? $attached_images : [];
    $ncm_options = get_ncm_options();

    ?>
    <style>
    #invoice_meta_box .order_data_column {
        width: 100%;
        overflow-x: auto;
    }

    #invoice_meta_box .order_data_column table {
        width: 100%;
        table-layout: auto;
        border-collapse: collapse;
    }

    #invoice_meta_box .order_data_column th,
    #invoice_meta_box .order_data_column td {
        padding: 8px;
        text-align: left;
    }

    #invoice_meta_box .order_data_column img {
        max-width: 50px;
        height: auto;
    }
    </style>
    <div class="order_data_column" style="width: 100%;">
        <table class="widefat alternate striped list-table" style="width: 100%; overflow-x: auto;">
            <thead>
                <tr>
                    <th colspan="9" style="text-align: center; font-weight: bold;">
                        <?php _e('Produtos', 'woocommerce'); ?>
                    </th>
                </tr>
                <tr>
                    <th></th>
                    <th><strong><?php _e('ID', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('NCM', 'woocommerce'); ?></strong></</th>
                    <th><strong><?php _e('Nome', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Preço ($)', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Peso (kg)', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Quantidade', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Bateria', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Perfume', 'woocommerce'); ?></strong></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item_id => $item) : ?>
                    <?php
                    $product = $item->get_product();
                    if (!$product) {
                        continue;
                    }
                    $product_id = $product->get_id();
                    if (WC_Subscriptions_Product::is_subscription($product) || has_term('fatura', 'product_cat', $product_id)) {
                        continue;
                    }
                    if ($product && has_term('Redirecionamento', 'product_cat', $product->get_id())) :
                        $redirecionamento_id = get_post_meta($product->get_id(), '_redirecionamento_id', true);
                        $ncm = !empty($item->get_meta('_ncm')) ? $item->get_meta('_ncm') : get_post_meta($redirecionamento_id, '_ncm', true);
                        $product_name = !empty($item->get_meta('_product_name')) ? $item->get_meta('_product_name') : $item->get_name();
                        $quantity = $item->get_quantity();
                        $max_value = $item->get_meta('Declaração de valor') ?: $item->get_total() / $quantity;
                        $declaration_value = !empty($item->get_meta('_declaration_value')) ? $item->get_meta('_declaration_value') : $item->get_meta('Declaração de valor');
                        $declaration_value = str_replace(array('$', 'R$', '€'), '', $declaration_value);
                        $declaration_value = number_format(floatval($declaration_value), 2, '.', '');
                        $bateria = $item->get_meta('_bateria');
                        $perfume = $item->get_meta('_perfume');
                        $weight = $product->get_weight();
                        $redirecionamento_link = get_edit_post_link($redirecionamento_id);
                        $product_image = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
                        $product_image = is_array($product_image) ? $product_image[0] : null;
                        ?>
                        <tr>
                            <td>
                                <?php if ($product_image) : ?>
                                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" style="max-width: 50px; max-height: 50px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($redirecionamento_link); ?>" target="_blank"><?php echo esc_html($redirecionamento_id); ?></a>
                            </td>
                            <td>
                                <select name="ncm[<?php echo esc_attr($item_id); ?>]" style="width: 100px;">
                                    <option value=""><?php _e('Selecione o NCM', 'woocommerce'); ?></option>
                                    <?php foreach ($ncm_options as $key => $value) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($ncm, $key); ?>>
                                            <?php echo esc_html($key . ' - ' . $value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="product_name[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($product_name); ?>" />
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="declaration_value[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($declaration_value); ?>" />
                            </td>
                            <td>
                                <?php echo esc_html($weight); ?>
                            </td>
                            <td>
                                <?php echo esc_html($quantity); ?>
                            </td>
                            <td>
                                <select name="product_bateria[<?php echo esc_attr($item_id); ?>]">
                                    <option value="N" <?php selected($bateria, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($bateria, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                            <td>
                                <select name="product_perfume[<?php echo esc_attr($item_id); ?>]">
                                    <option value="N" <?php selected($perfume, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($perfume, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php else :
                        $product_name = $item->get_meta('_product_name') ?: $item->get_name();
                        $ncm = $item->get_meta('_ncm');
                        $quantity = $item->get_quantity();
                        $declaration_value = $item->get_meta('_declaration_value') ?: $item->get_total() / $quantity;
                        // $declaration_value = str_replace(array('$', 'R$', '€'), '', $declaration_value);
                        if (!$item->get_meta('_declaration_value') && $order->get_currency() === 'BRL') {
                            $_woocs_order_rate = $order->get_meta('_wccs_currency_rate', true);
                            if($_woocs_order_rate > 0) {
                                $declaration_value /= $_woocs_order_rate;
                            }
                        }
                        $declaration_value = number_format(floatval($declaration_value), 2, '.', '');
                        $bateria = $item->get_meta('_bateria');
                        $perfume = $item->get_meta('_perfume');
                        $weight = $product->get_weight();
                        $product_id = $product->get_id();
                        $product_link = get_edit_post_link($product_id);
                        $product_image = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
                        $product_image = is_array($product_image) ? $product_image[0] : null;
                    ?>
                        <tr>
                            <td>
                                <?php if ($product_image) : ?>
                                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" style="max-width: 50px; max-height: 50px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($product_link); ?>" target="_blank"><?php echo esc_html($product_id); ?></a>
                            </td>
                            <td>
                                <select name="ncm[<?php echo esc_attr($item_id); ?>]" style="width: 100px;">
                                    <option value=""><?php _e('Selecione o NCM', 'woocommerce'); ?></option>
                                    <?php foreach ($ncm_options as $key => $value) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($ncm, $key); ?>>
                                            <?php echo esc_html($key . ' - ' . $value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="product_name[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($product_name); ?>" />
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="declaration_value[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($declaration_value); ?>" />
                            </td>
                            <td>
                                <?php echo esc_html($weight); ?>
                            </td>
                            <td>
                                <?php echo esc_html($quantity); ?>
                            </td>
                            <td>
                                <select name="product_bateria[<?php echo esc_attr($item_id); ?>]">
                                    <option value="N" <?php selected($bateria, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($bateria, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                            <td>
                                <select name="product_perfume[<?php echo esc_attr($item_id); ?>]">
                                    <option value="N" <?php selected($perfume, 'N'); ?>><?php _e('Não', 'woocommerce'); ?></option>
                                    <option value="S" <?php selected($perfume, 'S'); ?>><?php _e('Sim', 'woocommerce'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($order->get_meta('_invoice_contest'))) : ?>
        <p><strong>Contestação:</strong> <?php echo $order->get_meta('_invoice_contest'); ?></p>
        <?php endif; ?>
        <h3><?php _e('Anexar Imagens', 'woocommerce'); ?></h4>
        <input type="file" id="invoice_image_upload" name="invoice_image_upload[]" multiple="multiple" accept="image/*" />
        <div id="image-preview-container" style="display: flex; flex-wrap: wrap;">
            <?php foreach ($attached_images as $image_url) : ?>
                <div class="image-preview-item" style="position: relative; margin: 8px;">
                    <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 120px; max-height: 120px; display: block;" />
                    <button type="button" class="button remove-image-button" data-url="<?php echo esc_attr($image_url); ?>" style="position: absolute; top: 0; right: 2px; font-size: 22px; line-height: 1; padding: 0; border: none; background: transparent; color: #ff0000; cursor: pointer;">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#invoice_image_upload').on('change', function() {
                if (this.files) {
                    $.each(this.files, function(index, file) {
                        var reader = new FileReader();

                        reader.onload = function(e) {
                            var img = $('<img />', {
                                'src': e.target.result,
                                'style': 'max-width: 120px; max-height: 120px; display: block;'
                            });
                            var removeButton = $('<button />', {
                                'type': 'button',
                                'class': 'button remove-image-button',
                                'text': '×',
                                'style': 'position: absolute; top: 0; right: 2px; font-size: 22px; line-height: 1; padding: 0; border: none; background: transparent; color: #ff0000; cursor: pointer;'
                            });
                            var item = $('<div />', {
                                'class': 'image-preview-item',
                                'style': 'position: relative; margin: 5px;'
                            }).append(img).append(removeButton);
                            $('#image-preview-container').append(item);
                        }

                        reader.readAsDataURL(file);
                    });
                }
            });

            $('#image-preview-container').on('click', '.remove-image-button', function() {
                $(this).parent().remove();
                var imageUrl = $(this).data('url');
                if (imageUrl) {
                    $('<input />', {
                        'type': 'hidden',
                        'name': 'remove_image_url[]',
                        'value': imageUrl
                    }).appendTo('form');
                }
            });
        });
    </script>
    <?php
}

function save_invoice_section_to_order($order_id) {
    if (!is_numeric($order_id)) {
        return;
    }
    $order = wc_get_order($order_id);

    if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
        foreach ($_POST['product_name'] as $item_id => $product_name) {
            wc_update_order_item_meta($item_id, '_product_name', sanitize_text_field($product_name));
        }
    }

    if (isset($_POST['ncm']) && is_array($_POST['ncm'])) {
        foreach ($_POST['ncm'] as $item_id => $product_name) {
            wc_update_order_item_meta($item_id, '_ncm', sanitize_text_field($product_name));
        }
    }

    if (isset($_POST['declaration_value']) && is_array($_POST['declaration_value'])) {
        foreach ($_POST['declaration_value'] as $item_id => $product_price) {
            $value = wc_get_order_item_meta($item_id, 'Declaração de valor', true);
            if (!empty($value)) {
                $value = str_replace(array('$', 'R$', '€'), '', $value);
            }
            else {
                $value = $order->get_item($item_id)->get_product()->get_price();
                echo $value;
            }

            $new_value = floatval($product_price);
            if ($new_value <= floatval($value)) {
                wc_update_order_item_meta($item_id, '_declaration_value', $new_value);
            }
        }
    }

    if (isset($_POST['product_bateria']) && is_array($_POST['product_bateria'])) {
        foreach ($_POST['product_bateria'] as $item_id => $bateria) {
            wc_update_order_item_meta($item_id, '_bateria', sanitize_text_field($bateria));
        }
    }

    if (isset($_POST['product_perfume']) && is_array($_POST['product_perfume'])) {
        foreach ($_POST['product_perfume'] as $item_id => $perfume) {
            wc_update_order_item_meta($item_id, '_perfume', sanitize_text_field($perfume));
        }
    }
    
    if (isset($_FILES['invoice_image_upload']) && !empty($_FILES['invoice_image_upload']['name'][0])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded_images = array();
        $file_count = count($_FILES['invoice_image_upload']['name']);
        $files = $_FILES['invoice_image_upload'];

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['name'][$i]) {
                $file = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                );
                
                $_FILES = array('invoice_image_upload' => $file);
                $attachment_id = media_handle_upload('invoice_image_upload', 0);
                if (!is_wp_error($attachment_id)) {
                    $uploaded_images[] = wp_get_attachment_url($attachment_id);
                }
            }
        }

        if (!empty($uploaded_images)) {
            $existing_images = get_post_meta($order_id, '_invoice_images', true);
            $existing_images = is_array($existing_images) ? $existing_images : array();
            $all_images = array_merge($existing_images, $uploaded_images);
            update_post_meta($order_id, '_invoice_images', $all_images);
        }
    }

    if (isset($_POST['remove_image_url']) && is_array($_POST['remove_image_url'])) {
        $remove_urls = $_POST['remove_image_url'];
        $existing_images = get_post_meta($order_id, '_invoice_images', true);
        $existing_images = is_array($existing_images) ? $existing_images : array();
        
        foreach ($remove_urls as $url) {
            $attachment_id = attachment_url_to_postid($url);
            if ($attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
        }
        $remaining_images = array_diff($existing_images, $remove_urls);
        update_post_meta($order_id, '_invoice_images', $remaining_images);
    }
}

function hide_custom_order_item_meta($formatted_meta, $item) {
    $hidden_meta_keys = array('_declaration_value', '_bateria', '_perfume', '_product_name', '_ncm');

    foreach ($formatted_meta as $key => $meta) {
        if (in_array($meta->key, $hidden_meta_keys)) {
            unset($formatted_meta[$key]);
        }
    }

    return $formatted_meta;
}

add_action('woocommerce_review_order_before_submit', 'add_product_replacement_checkbox');
function add_product_replacement_checkbox() {
    ?>
    <p class="form-row validate-required">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
        <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="accept_product_replacement" id="accept_product_replacement">
            <span>Autorizo a substituição de qualquer produto fora de estoque por opção igual ou melhor qualidade, caso o preço seja o mesmo (poderão ocorrer variações de fragrâncias, sabor , cor , marca etc). Confirmo minha autorização</span>
        </label>
    </p>
    <?php
}

add_action('woocommerce_checkout_update_order_meta', 'save_product_replacement_checkbox');
function save_product_replacement_checkbox($order_id) {
    if (isset($_POST['accept_product_replacement'])) {
        update_post_meta($order_id, '_accept_product_replacement', 'yes');
    } else {
        update_post_meta($order_id, '_accept_product_replacement', 'no');
    }
}