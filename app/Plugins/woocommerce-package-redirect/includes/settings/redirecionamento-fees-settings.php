<?php

function redirecionamento_section_callback() {
    echo 'Defina as Taxas de Redirecionamento.';
}

function redirecionamento_field_callback() {
    $options = get_option('redirecionamento_taxes');
    ?>
    <table id="weight-intervals-table">
        <tr>
            <th><?php _e('Peso Mínimo (kg)', 'redirecionamento'); ?></th>
            <th><?php _e('Peso Máximo (kg)', 'redirecionamento'); ?></th>
            <th><?php _e('Taxa ($)', 'redirecionamento'); ?></th>
            <th><?php _e('Ações', 'redirecionamento'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'redirecionamento'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-redirecionamento-interval" class="button"><?php _e('Adicionar Intervalo', 'redirecionamento'); ?></button>
    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-redirecionamento-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="redirecionamento_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'redirecionamento'); ?></button></td>
                    </tr>
                `;
                $('#weight-intervals-table').append(newRow);
                index++;
            });

            $(document).on('click', '.remove-interval', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_redirecionamento_taxes($input) {
    $output = array();
    if (isset($input['intervals']) && is_array($input['intervals'])) {
        foreach ($input['intervals'] as $key => $interval) {
            $output['intervals'][$key] = array(
                'start' => sanitize_text_field($interval['start']),
                'end'   => sanitize_text_field($interval['end']),
                'rate'  => sanitize_text_field($interval['rate'])
            );
        }
    }
    return $output;
}