<?php

function embalagem_section_callback() {
    echo 'Defina os Pesos Adicionais para cada intervalo de peso.';
}

function embalagem_field_callback() {
    $options = get_option('embalagem_taxes');
    ?>
    <table id="embalagem-intervals-table">
        <tr>
            <th><?php _e('Peso Mínimo (kg)', 'embalagem'); ?></th>
            <th><?php _e('Peso Máximo (kg)', 'embalagem'); ?></th>
            <th><?php _e('Peso Adicional (kg)', 'embalagem'); ?></th>
            <th><?php _e('Ações', 'embalagem'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'embalagem'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-embalagem-interval" class="button"><?php _e('Adicionar Intervalo', 'embalagem'); ?></button>
    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-embalagem-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="embalagem_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'embalagem'); ?></button></td>
                    </tr>
                `;
                $('#embalagem-intervals-table').append(newRow);
                index++;
            });

            $(document).on('click', '.remove-interval', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_embalagem_taxes($input) {
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
