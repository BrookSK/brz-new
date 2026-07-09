<?php

function fees_apply_to_section_callback() {
    echo '<p>Selecione as categorias às quais as taxas serão aplicadas.</p>';
}

function get_woocommerce_categories() {
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ));

    return $terms;
}

function fees_apply_to_table_callback() {
    $options = get_option('fees_apply_to_categories');
    $categories = get_woocommerce_categories();

    ?>
    <table id="fees-apply-to-table">
        <tr>
            <th><?php _e('Categoria', 'taxes'); ?></th>
            <th><?php _e('Frete', 'taxes'); ?></th>
            <th><?php _e('Redirecionamento', 'taxes'); ?></th>
            <th><?php _e('Seguro', 'taxes'); ?></th>
            <th><?php _e('Serviço', 'taxes'); ?></th>
            <th><?php _e('Ações', 'taxes'); ?></th>
        </tr>
        <?php
        if (isset($options['categories']) && is_array($options['categories'])) {
            foreach ($options['categories'] as $key => $category) {
                ?>
                <tr>
                    <td>
                        <select name="fees_apply_to_categories[categories][<?php echo esc_attr($key); ?>][category]" required>
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category['category'], $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="checkbox" name="fees_apply_to_categories[categories][<?php echo esc_attr($key); ?>][shipping_fee]" <?php checked(!empty($category['shipping_fee'])); ?> /></td>
                    <td><input type="checkbox" name="fees_apply_to_categories[categories][<?php echo esc_attr($key); ?>][redirecionamento_fee]" <?php checked(!empty($category['redirecionamento_fee'])); ?> /></td>
                    <td><input type="checkbox" name="fees_apply_to_categories[categories][<?php echo esc_attr($key); ?>][insurance_fee]" <?php checked(!empty($category['insurance_fee'])); ?> /></td>
                    <td><input type="checkbox" name="fees_apply_to_categories[categories][<?php echo esc_attr($key); ?>][service_fee]" <?php checked(!empty($category['service_fee'])); ?> /></td>
                    <td><button type="button" class="remove-category button"><?php _e('Remover', 'taxes'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-fee-category" class="button"><?php _e('Adicionar Categoria', 'category_fees'); ?></button>

    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-fee-category').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td>
                            <select name="fees_apply_to_categories[categories][${uniqueKey}][category]" required>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                                        <?php echo esc_html(str_replace('`', '\`', $cat->name)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="checkbox" name="fees_apply_to_categories[categories][${uniqueKey}][shipping_fee]" /></td>
                        <td><input type="checkbox" name="fees_apply_to_categories[categories][${uniqueKey}][redirecionamento_fee]" /></td>
                        <td><input type="checkbox" name="fees_apply_to_categories[categories][${uniqueKey}][insurance_fee]" /></td>
                        <td><input type="checkbox" name="fees_apply_to_categories[categories][${uniqueKey}][service_fee]" /></td>
                        <td><button type="button" class="remove-category button"><?php _e('Remover', 'taxes'); ?></button></td>
                    </tr>
                `;
                $('#fees-apply-to-table').append(newRow);
                index++;
            });

            $(document).on('click', '.remove-category', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_fees_apply_to($input) {
    $output = array();
    if (isset($input['categories']) && is_array($input['categories'])) {
        foreach ($input['categories'] as $key => $category) {
            $output['categories'][$key] = array(
                'category'              => sanitize_text_field($category['category']),
                'shipping_fee'          => isset($category['shipping_fee']),
                'redirecionamento_fee'  => isset($category['redirecionamento_fee']),
                'insurance_fee'         => isset($category['insurance_fee']),
                'service_fee'           => isset($category['service_fee']),
            );
        }
    }
    return $output;
}
