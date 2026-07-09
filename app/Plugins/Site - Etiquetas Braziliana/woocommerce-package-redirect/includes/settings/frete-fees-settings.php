<?php

function frete_section_callback() {
    echo 'Defina as Taxas de Frete.';
}

function frete_field_callback() {
    $options = get_option('frete_taxes');
    ?>
    <h3><?php _e('Taxas', 'frete'); ?></h3>
    <table id="frete-intervals-table">
        <tr>
            <th><?php _e('Peso Mínimo (kg)', 'frete'); ?></th>
            <th><?php _e('Peso Máximo (kg)', 'frete'); ?></th>
            <th><?php _e('Taxa ($)', 'frete'); ?></th>
            <th><?php _e('Ações', 'frete'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'frete'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-frete-interval" class="button"><?php _e('Adicionar Intervalo', 'frete'); ?></button>

    <h3><?php _e('Taxas Exclusivas para Assinantes', 'frete'); ?></h3>
    <label>
        <input type="checkbox" name="frete_taxes[enable_subscriber_rates]" value="1" <?php checked(1, isset($options['enable_subscriber_rates']) ? $options['enable_subscriber_rates'] : 0); ?>>
        <?php _e('Ativar taxas exclusivas para assinantes', 'frete'); ?>
    </label>
    <table id="frete-subscriber-intervals-table">
        <tr>
            <th><?php _e('Peso Mínimo (kg)', 'frete'); ?></th>
            <th><?php _e('Peso Máximo (kg)', 'frete'); ?></th>
            <th><?php _e('Taxa ($)', 'frete'); ?></th>
            <th><?php _e('Ações', 'frete'); ?></th>
        </tr>
        <?php
        if (isset($options['subscriber_intervals']) && is_array($options['subscriber_intervals'])) {
            foreach ($options['subscriber_intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td><button type="button" class="remove-subscriber-interval button"><?php _e('Remover', 'frete'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-frete-subscriber-interval" class="button"><?php _e('Adicionar Intervalo', 'frete'); ?></button>

    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-frete-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'frete'); ?></button></td>
                    </tr>
                `;
                $('#frete-intervals-table').append(newRow);
                index++;
            });

            var subscriberIndex = <?php echo isset($options['subscriber_intervals']) ? count($options['subscriber_intervals']) : 0; ?>;
            $('#add-frete-subscriber-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="frete_taxes[subscriber_intervals][${uniqueKey}][rate]"></td>
                        <td><button type="button" class="remove-subscriber-interval button"><?php _e('Remover', 'frete'); ?></button></td>
                    </tr>
                `;
                $('#frete-subscriber-intervals-table').append(newRow);
                subscriberIndex++;
            });

            $(document).on('click', '.remove-interval', function() {
                $(this).closest('tr').remove();
            });

            $(document).on('click', '.remove-subscriber-interval', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function sanitize_frete_taxes($input) {
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
    $output['enable_subscriber_rates'] = isset($input['enable_subscriber_rates']) ? 1 : 0;
    if (isset($input['subscriber_intervals']) && is_array($input['subscriber_intervals'])) {
        foreach ($input['subscriber_intervals'] as $key => $interval) {
            $output['subscriber_intervals'][$key] = array(
                'start' => sanitize_text_field($interval['start']),
                'end'   => sanitize_text_field($interval['end']),
                'rate'  => sanitize_text_field($interval['rate'])
            );
        }
    }

    return $output;
}
