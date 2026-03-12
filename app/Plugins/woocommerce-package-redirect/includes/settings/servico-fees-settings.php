<?php

function servico_section_callback() {
    echo 'Defina as Taxas de Serviço.';
}

function servico_field_callback() {
    $options = get_option('servico_taxes');
    ?>
    <h3><?php _e('Taxas', 'servico'); ?></h3>
    <table id="service-intervals-table">
        <tr>
            <th><?php _e('Valor Mínimo ($)', 'servico'); ?></th>
            <th><?php _e('Valor Máximo ($)', 'servico'); ?></th>
            <th><?php _e('Taxa', 'servico'); ?></th>
            <th><?php _e('Tipo de Taxa', 'servico'); ?></th>
            <th><?php _e('Ações', 'servico'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="servico_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="servico_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" name="servico_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td>
                        <select name="servico_taxes[intervals][<?php echo esc_attr($key); ?>][type]">
                            <option value="fixed" <?php selected($interval['type'], 'fixed'); ?>><?php _e('Absoluto ($)', 'servico'); ?></option>
                            <option value="percentage" <?php selected($interval['type'], 'percentage'); ?>><?php _e('Percentual (%)', 'servico'); ?></option>
                        </select>
                    </td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'servico'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-service-interval" class="button"><?php _e('Adicionar Intervalo', 'servico'); ?></button>

    <h3><?php _e('Taxas Exclusivas para Assinantes', 'servico'); ?></h3>
    <label>
        <input type="checkbox" name="servico_taxes[enable_subscriber_rates]" value="1" <?php checked(1, isset($options['enable_subscriber_rates']) ? $options['enable_subscriber_rates'] : 0); ?>>
        <?php _e('Ativar taxas exclusivas para assinantes', 'servico'); ?>
    </label>
    <table id="service-subscriber-intervals-table">
        <tr>
            <th><?php _e('Valor Mínimo ($)', 'servico'); ?></th>
            <th><?php _e('Valor Máximo ($)', 'servico'); ?></th>
            <th><?php _e('Taxa', 'servico'); ?></th>
            <th><?php _e('Tipo de Taxa', 'servico'); ?></th>
            <th><?php _e('Ações', 'servico'); ?></th>
        </tr>
        <?php
        if (isset($options['subscriber_intervals']) && is_array($options['subscriber_intervals'])) {
            foreach ($options['subscriber_intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" step="0.01" name="servico_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="servico_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" name="servico_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td>
                        <select name="servico_taxes[subscriber_intervals][<?php echo esc_attr($key); ?>][type]">
                            <option value="fixed" <?php selected($interval['type'], 'fixed'); ?>><?php _e('Absoluto ($)', 'servico'); ?></option>
                            <option value="percentage" <?php selected($interval['type'], 'percentage'); ?>><?php _e('Percentual (%)', 'servico'); ?></option>
                        </select>
                    </td>
                    <td><button type="button" class="remove-subscriber-interval button"><?php _e('Remover', 'servico'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-service-subscriber-interval" class="button"><?php _e('Adicionar Intervalo', 'servico'); ?></button>
    
    <script>
        jQuery(document).ready(function($) {
            var index = <?php echo isset($options['intervals']) ? count($options['intervals']) : 0; ?>;
            $('#add-service-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td>
                            <select name="servico_taxes[intervals][${uniqueKey}][type]">
                                <option value="fixed"><?php _e('Absoluto ($)', 'servico'); ?></option>
                                <option value="percentage"><?php _e('Percentual (%)', 'servico'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'servico'); ?></button></td>
                    </tr>
                `;
                $('#service-intervals-table').append(newRow);
                index++;
            });

            var subscriberIndex = <?php echo isset($options['subscriber_intervals']) ? count($options['subscriber_intervals']) : 0; ?>;
            $('#add-service-subscriber-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[subscriber_intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[subscriber_intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" step="0.01" name="servico_taxes[subscriber_intervals][${uniqueKey}][rate]"></td>
                        <td>
                            <select name="servico_taxes[subscriber_intervals][${uniqueKey}][type]">
                                <option value="fixed"><?php _e('Absoluto ($)', 'servico'); ?></option>
                                <option value="percentage"><?php _e('Percentual (%)', 'servico'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-subscriber-interval button"><?php _e('Remover', 'servico'); ?></button></td>
                    </tr>
                `;
                $('#service-subscriber-intervals-table').append(newRow);
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

function sanitize_servico_taxes($input) {
    $output = array();
    if (isset($input['intervals']) && is_array($input['intervals'])) {
        foreach ($input['intervals'] as $key => $interval) {
            $output['intervals'][$key] = array(
                'start' => sanitize_text_field($interval['start']),
                'end' => sanitize_text_field($interval['end']),
                'rate' => sanitize_text_field($interval['rate']),
                'type' => sanitize_text_field($interval['type']),
            );
        }
    }
    $output['enable_subscriber_rates'] = isset($input['enable_subscriber_rates']) ? 1 : 0;
    if (isset($input['subscriber_intervals']) && is_array($input['subscriber_intervals'])) {
        foreach ($input['subscriber_intervals'] as $key => $interval) {
            $output['subscriber_intervals'][$key] = array(
                'start' => sanitize_text_field($interval['start']),
                'end' => sanitize_text_field($interval['end']),
                'rate' => sanitize_text_field($interval['rate']),
                'type' => sanitize_text_field($interval['type']),
            );
        }
    }
    return $output;
}
