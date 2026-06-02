<?php

function seguro_section_callback() {
    echo 'Defina as Taxas de Seguro.';
}

function seguro_field_callback() {
    $options = get_option('seguro_taxes');
    ?>
    <table id="seguro-intervals-table">
        <tr>
            <th><?php _e('Valor Declarado Mínimo ($)', 'seguro'); ?></th>
            <th><?php _e('Valor Declarado Máximo ($)', 'seguro'); ?></th>
            <th><?php _e('Taxa', 'seguro'); ?></th>
            <th><?php _e('Tipo de Taxa', 'seguro'); ?></th>
            <th><?php _e('Ações', 'seguro'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="seguro_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="seguro_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" name="seguro_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td>
                        <select name="seguro_taxes[intervals][<?php echo esc_attr($key); ?>][type]">
                            <option value="fixed" <?php selected($interval['type'], 'fixed'); ?>><?php _e('Absoluto ($)', 'seguro'); ?></option>
                            <option value="percentage" <?php selected($interval['type'], 'percentage'); ?>><?php _e('Percentual (%)', 'seguro'); ?></option>
                        </select>
                    </td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'seguro'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-seguro-interval" class="button"><?php _e('Adicionar Intervalo', 'seguro'); ?></button>
    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-seguro-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" step="0.01" name="seguro_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" step="0.01" name="seguro_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" step="0.01" min="0" name="seguro_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td>
                            <select name="seguro_taxes[intervals][${uniqueKey}][type]">
                                <option value="fixed"><?php _e('Absoluto ($)', 'seguro'); ?></option>
                                <option value="percentage"><?php _e('Percentual (%)', 'seguro'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'seguro'); ?></button></td>
                    </tr>
                `;
                $('#seguro-intervals-table').append(newRow);
                index++;
            });

            $(document).on('click', '.remove-interval', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_seguro_taxes($input) {
    $output = array();
    if (isset($input['intervals']) && is_array($input['intervals'])) {
        foreach ($input['intervals'] as $key => $interval) {
            $output['intervals'][$key] = array(
                'start' => sanitize_text_field($interval['start']),
                'end'   => sanitize_text_field($interval['end']),
                'rate'  => sanitize_text_field($interval['rate']),
                'type'  => sanitize_text_field($interval['type'])
            );
        }
    }
    return $output;
}
