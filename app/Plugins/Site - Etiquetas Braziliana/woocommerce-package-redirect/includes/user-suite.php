<?php

add_action('register_form', 'usp_add_suite_field');
add_action('user_register', 'usp_save_suite_field');
add_action('show_user_profile', 'usp_show_suite_field');
add_action('edit_user_profile', 'usp_show_suite_field');
add_action('personal_options_update', 'usp_save_suite_field_profile');
add_action('edit_user_profile_update', 'usp_save_suite_field_profile');
add_action('woocommerce_account_dashboard', 'usp_display_suite_in_my_account');
add_filter('manage_users_columns', 'usp_add_suite_column');
add_action('manage_users_custom_column', 'usp_show_suite_column_content', 10, 3);
add_filter('manage_users_sortable_columns', 'usp_make_suite_column_sortable');
add_action('restrict_manage_users', 'usp_add_suite_search');
add_action('pre_get_users', 'usp_filter_users_by_suite');
add_action('admin_head', 'usp_admin_styles');

function usp_add_suite_field() {
    ?>
    <p>
        <label for="suite"><?php _e('Suite', 'user-suite-plugin') ?><br />
        <input type="text" name="suite" id="suite" class="input" value="" size="25" readonly /></label>
    </p>
    <?php
}

function usp_save_suite_field($user_id) {
    if (!get_user_meta($user_id, 'suite', true)) {
        global $wpdb;

        $highest_suite = $wpdb->get_var("SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM $wpdb->usermeta WHERE meta_key = 'suite'");
        $new_suite_number = $highest_suite ? $highest_suite + 1 : 1;

        update_user_meta($user_id, 'suite', $new_suite_number);
    }
}


function usp_show_suite_field($user) {
    ?>
    <h3><?php _e('Informações da Suite', 'user-suite-plugin'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="suite"><?php _e('Suite', 'user-suite-plugin'); ?></label></th>
            <td>
                <input type="text" name="suite" id="suite" value="<?php echo esc_attr(get_the_author_meta('suite', $user->ID)); ?>" class="regular-text" readonly /><br />
                <span class="description"><?php _e('Este é o número exclusivo da suite do usuário.', 'user-suite-plugin'); ?></span>
            </td>
        </tr>
    </table>
    <?php
}

function usp_save_suite_field_profile($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    update_user_meta($user_id, 'suite', sanitize_text_field($_POST['suite']));
}

function usp_display_suite_in_my_account() {
    $user_id = get_current_user_id();
    if ($user_id) {
        $suite_number = get_user_meta($user_id, 'suite', true);
        if ($suite_number) {
            echo '<p>' . __('Suite: ', 'user-suite-plugin') . esc_html($suite_number) . '</p>';
        }
    }
}

function usp_add_suite_column($columns) {
    $columns['suite'] = __('Suite', 'user-suite-plugin');
    return $columns;
}

function usp_show_suite_column_content($value, $column_name, $user_id) {
    if ($column_name == 'suite') {
        return get_user_meta($user_id, 'suite', true);
    }
    return $value;
}

function usp_make_suite_column_sortable($columns) {
    $columns['suite'] = 'suite';
    return $columns;
}

function usp_add_suite_search() {
    $suite_value = isset($_GET['suite']) ? esc_attr($_GET['suite']) : '';
    ?>
    <form method="get" action="" style="margin-top: 4px;">
        <div class="alignleft actions">
            <label for="suite-search-input" class="screen-reader-text"><?php _e('Search Suite:', 'user-suite-plugin'); ?></label>
            <input type="search" id="suite-search-input" name="suite" value="<?php echo $suite_value; ?>" placeholder="Busca de Suite">
            <?php submit_button(__('Search Suite'), '', '', false, array('id' => 'search-submit')); ?>
        </div>
    </form>
    <?php
}

function usp_filter_users_by_suite($query) {
    global $pagenow;

    if ($pagenow === 'users.php' && isset($_GET['suite']) && !empty($_GET['suite'])) {
        $suite = sanitize_text_field($_GET['suite']);
        $meta_query = array(
            array(
                'key' => 'suite',
                'value' => $suite,
                'compare' => 'LIKE'
            )
        );
        $query->set('meta_query', $meta_query);
    }
}

function usp_admin_styles() {
    ?>
    <style>
        .tablenav.top .actions {
            display: flex;
            align-items: center;
        }
        .tablenav.top .actions #search-submit {
            margin-left: 10px;
        }
    </style>
    <?php
}