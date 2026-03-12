<?php

add_action('wp_ajax_get_user_by_suite', 'get_user_by_suite_ajax');

function get_user_by_suite_ajax() {
    if (!current_user_can('administrator')) {
        echo json_encode(array('success' => false, 'message' => 'Acesso negado.'));
        wp_die();
    }

    if (isset($_POST['suite_number'])) {
        $suite_number = sanitize_text_field($_POST['suite_number']);
        $users = get_users(array(
            'meta_key' => 'suite',
            'meta_value' => $suite_number,
        ));

        if (!empty($users)) {
            $user = $users[0];
            echo json_encode(array(
                'success' => true,
                'display_name' => $user->display_name,
                'edit_link' => get_edit_user_link($user->ID),
            ));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Usuário não encontrado.'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Número de suíte não fornecido.'));
    }

    wp_die();
}
