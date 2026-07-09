<?php

function fatura_fields($post_id) {
    $status = get_post_meta($post_id, '_status', true);

    $order_id = get_post_meta($post_id, '_order_id', true);
    $fatura_nome = get_post_meta($post_id, '_fatura_nome', true);
    $fatura_valor = get_post_meta($post_id, '_fatura_valor', true);
    $fatura_descricao = get_post_meta($post_id, '_fatura_descricao', true);

    $readonly = $status === 'Pendente' ? '' : 'readonly';
    $order = wc_get_order($order_id);

    ?>
    <style>
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }

        .form-table th,
        .form-table td {
            padding: 8px;
            vertical-align: top;
        }

        .form-table th {
            width: 20%;
            text-align: left;
        }

        .form-table td input,
        .form-table td textarea {
            width: 100%;
        }
    </style>

    <table class="form-table">
        <tr>
            <th><label for="order_id">Número do Pedido*</label></th>
            <td>
                <input type="number" id="order_id" name="order_id" value="<?php echo esc_attr($order_id); ?>" <?php echo $readonly; ?> required />
                <?php if ($order_id) : ?>
                    <?php if ($order) : ?>
                        <a href="<?php echo esc_url($order->get_edit_order_url()); ?>" class="order-link" target="_blank">Ver Pedido</a>
                    <?php else : ?>
                        <p class="error-message">Pedido não encontrado.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="fatura_nome">Motivo*</label></th>
            <td><input type="text" id="fatura_nome" name="fatura_nome" value="<?php echo esc_attr($fatura_nome); ?>" <?php echo $readonly; ?> required /></td>
        </tr>
        <tr>
            <th><label for="fatura_valor">Valor Adicional*</label></th>
            <td><input type="number" step="0.01" id="fatura_valor" name="fatura_valor" value="<?php echo esc_attr($fatura_valor); ?>" <?php echo $readonly; ?> step="0.01" required /></td>
        </tr>
        <tr>
            <th><label for="fatura_descricao">Observações</label></th>
            <td><textarea id="fatura_descricao" name="fatura_descricao" <?php echo $readonly; ?>><?php echo esc_textarea($fatura_descricao); ?></textarea></td>
        </tr>
    </table>
    <?php
}