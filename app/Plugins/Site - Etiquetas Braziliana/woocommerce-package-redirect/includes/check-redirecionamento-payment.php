<?php

add_action('check_redirecionamento_payment', 'check_redirecionamento_payment');

if (!wp_next_scheduled('check_redirecionamento_payment')) {
    wp_schedule_event(time(), 'daily', 'check_redirecionamento_payment');
}

function check_redirecionamento_payment() {
    $multa_options = get_option('multa_taxes');
    $dias_descarte = null;

    if (isset($multa_options['intervals']) && is_array($multa_options['intervals'])) {
        foreach ($multa_options['intervals'] as $interval) {
            if (isset($interval['type']) && $interval['type'] === 'discard') {
                if (isset($interval['start'])) {
                    $dias_descarte = intval($interval['start']);
                }
                break;
            }
        }
    }

    $args = array(
        'post_type'   => 'redirecionamento',
        'post_status' => 'publish',
        'meta_query'  => array(
            array(
                'key'     => '_status',
                'value'   => 'Pendente',
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $data_publicacao = get_the_date('Y-m-d', $post_id);

            if ($data_publicacao) {
                $dias_atraso = (strtotime(date('Y-m-d')) - strtotime($data_publicacao)) / (60 * 60 * 24);
                update_post_meta($post_id, '_dias_atraso', $dias_atraso);
                
                if ($dias_atraso >= 15) {
                    $dias_desde_15 = $dias_atraso - 15;

                    if ($dias_desde_15 % 5 === 0) {
                        $numero_suite = get_post_meta($post_id, '_numero_suite', true);
                        $user_query = new WP_User_Query(array(
                            'meta_key' => 'suite',
                            'meta_value' => $numero_suite,
                            'number' => 1
                        ));

                        if (!empty($user_query->get_results())) {
                            $user = $user_query->get_results()[0];
                            send_storage_email($post_id);
                        }
                    }
                }

                if (!is_null($dias_descarte) && $dias_atraso >= $dias_descarte) {
                    $product_id = get_post_meta($post_id, '_product_id', true);
                    wp_delete_post($product_id, true);

                    $numero_suite = get_post_meta($post_id, '_numero_suite', true);
                    $user_query = new WP_User_Query(array(
                        'meta_key' => 'suite',
                        'meta_value' => $numero_suite,
                        'number' => 1
                    ));

                    if (!empty($user_query->get_results())) {
                        $user = $user_query->get_results()[0];
                        $cliente_email = $user->user_email;

                        wp_mail(
                            $cliente_email,
                            'Produto Descartado',
                            'Seu produto foi descartado devido ao não pagamento dentro do prazo estabelecido.'
                        );
                    }

                    update_post_meta($post_id, '_status', 'Descarte');
                }
            }
        }
        wp_reset_postdata();
    }
}
