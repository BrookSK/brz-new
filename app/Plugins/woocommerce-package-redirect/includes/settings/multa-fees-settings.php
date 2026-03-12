<?php

function multa_section_callback() {
    echo 'Defina suas taxas diárias.';
}

function multa_field_callback() {
    $options = get_option('multa_taxes');
    ?>
    <table id="multa-intervals-table">
        <tr>
            <th><?php _e('Dia de Atraso Mínimo', 'multa'); ?></th>
            <th><?php _e('Dia de Atraso Máximo', 'multa'); ?></th>
            <th><?php _e('Taxa de Armazenamento', 'multa'); ?></th>
            <th><?php _e('Tipo de Taxa', 'multa'); ?></th>
            <th><?php _e('Ações', 'multa'); ?></th>
        </tr>
        <?php
        if (isset($options['intervals']) && is_array($options['intervals'])) {
            foreach ($options['intervals'] as $key => $interval) {
                ?>
                <tr>
                    <td><input type="number" min="0" name="multa_taxes[intervals][<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($interval['start']); ?>"></td>
                    <td><input type="number" min="0" class="end-field" name="multa_taxes[intervals][<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($interval['end']); ?>"></td>
                    <td><input type="number" min="0" class="rate-field" name="multa_taxes[intervals][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($interval['rate']); ?>"></td>
                    <td>
                        <select class="type-select" name="multa_taxes[intervals][<?php echo esc_attr($key); ?>][type]">
                            <option value="fixed" <?php selected($interval['type'], 'fixed'); ?>><?php _e('Absoluto ($)', 'multa'); ?></option>
                            <option value="discard" <?php selected($interval['type'], 'discard'); ?>><?php _e('Descarte', 'multa'); ?></option>
                        </select>
                    </td>
                    <td><button type="button" class="remove-interval button"><?php _e('Remover', 'multa'); ?></button></td>
                </tr>
                <?php
            }
        }
        ?>
    </table>
    <button type="button" id="add-multa-interval" class="button"><?php _e('Adicionar Intervalo', 'multa'); ?></button>
    <script>
        jQuery(document).ready(function($) {
            function toggleFieldsBasedOnType() {
                $('#multa-intervals-table .type-select').each(function() {
                    var selectedType = $(this).val();
                    var $row = $(this).closest('tr');
                    if (selectedType === 'discard') {
                        $row.find('.end-field, .rate-field').hide();
                    } else {
                        $row.find('.end-field, .rate-field').show();
                    }
                });
            }

            toggleFieldsBasedOnType();

            $(document).on('change', '.type-select', function() {
                toggleFieldsBasedOnType();
            });

            $('#add-multa-interval').on('click', function() {
                var uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                var newRow = `
                    <tr>
                        <td><input type="number" min="0" name="multa_taxes[intervals][${uniqueKey}][start]"></td>
                        <td><input type="number" min="0" class="end-field" name="multa_taxes[intervals][${uniqueKey}][end]"></td>
                        <td><input type="number" min="0" class="rate-field" name="multa_taxes[intervals][${uniqueKey}][rate]"></td>
                        <td>
                            <select class="type-select" name="multa_taxes[intervals][${uniqueKey}][type]">
                                <option value="fixed"><?php _e('Absoluto ($)', 'multa'); ?></option>
                                <option value="discard"><?php _e('Descarte', 'multa'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-interval button"><?php _e('Remover', 'multa'); ?></button></td>
                    </tr>
                `;
                $('#multa-intervals-table').append(newRow);
                toggleFieldsBasedOnType();
            });

            $(document).on('click', '.remove-interval', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}


function sanitize_multa_taxes($input) {
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
