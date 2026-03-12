<?php

add_action('admin_menu', 'add_custom_submenu_page');
add_action('admin_head', 'highlight_active_submenu');

function highlight_active_submenu() {
    global $parent_file, $submenu_file, $post;

    if (isset($_GET['add_fatura']) && $_GET['add_fatura'] === 'true') {
        $parent_file = 'edit.php?post_type=redirecionamento';
        $submenu_file = 'post-new.php?post_type=redirecionamento&add_fatura=true';
    }
    if(isset($_GET['edit_fatura']) && $_GET['edit_fatura'] === 'true') {
        $parent_file = 'edit.php?post_type=redirecionamento';
        $submenu_file = 'view_faturas';
    }
}

function add_custom_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=redirecionamento',
        'Adicionar Fatura',
        'Adicionar Fatura',
        'manage_woocommerce',
        'post-new.php?post_type=redirecionamento&add_fatura=true'
    );

    add_submenu_page(
        'edit.php?post_type=redirecionamento',
        'Visualizar Faturas',
        'Visualizar Faturas',
        'manage_woocommerce',
        'view_faturas',
        'view_faturas_page'
    );
}

function view_faturas_page() {
    verify_subscription();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Faturas</h1>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=redirecionamento&add_fatura=true')); ?>" class="page-title-action">Adicionar Fatura</a>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">Descrição</th>
                    <th scope="col">Valor</th>
                    <th scope="col">Status</th>
                    <th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = array(
                    'post_type'   => 'redirecionamento',
                    'meta_query'  => array(
                        array(
                            'key'     => '_fatura',
                            'value'   => true,
                            'compare' => '='
                        )
                    )
                );
                
                $query = new WP_Query($args);
                if ($query->have_posts()) :
                    while ($query->have_posts()) : $query->the_post();
                        $fatura_nome = get_post_meta(get_the_ID(), '_fatura_nome', true);
                        $fatura_descricao = get_post_meta(get_the_ID(), '_fatura_descricao', true);
                        $fatura_valor = get_post_meta(get_the_ID(), '_fatura_valor', true);
                        $fatura_status = get_post_meta(get_the_ID(), '_status', true);
                        ?>
                        <tr>
                            <td><a href="<?php echo add_query_arg('edit_fatura', 'true', get_edit_post_link()); ?>"><?php echo esc_html($fatura_nome); ?></a></td>
                            <td><?php echo esc_html($fatura_descricao); ?></td>
                            <td><?php echo esc_html(number_format($fatura_valor, 2, ',', '.')); ?></td>
                            <td><strong><?php echo esc_html($fatura_status); ?></strong></td>
                            <td>
                                <form action="" method="post" style="display:inline;">
                                    <?php wp_nonce_field('delete_fatura_' . get_the_ID()); ?>
                                    <input type="hidden" name="action" value="delete_fatura">
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                                    <input type="submit" class="button button-secondary" value="Deletar" onclick="return confirm('Tem certeza de que deseja excluir esta fatura?');">
                                </form>
                            </td>
                        </tr>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <tr>
                        <td colspan="5">Nenhuma fatura encontrada.</td>
                    </tr>
                    <?php
                endif;
                ?>
            </tbody>
        </table>
    </div>
    <?php

    if (isset($_POST['action']) && $_POST['action'] === 'delete_fatura' && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        if (wp_verify_nonce($_POST['_wpnonce'], 'delete_fatura_' . $post_id)) {
            wp_delete_post($post_id, true);
            echo '<div class="notice notice-success is-dismissible"><p>Fatura excluída com sucesso.</p></div>';
            wp_redirect(admin_url('edit.php?post_type=redirecionamento&page=view_faturas'));
            exit;
        }
    }
}

function handle_edit_fatura($post_id) {
    update_post_meta($post_id, '_fatura', true);
    update_post_meta($post_id, '_status', 'Pendente');

    $product_id = get_post_meta($post_id, '_product_id', true);
    if ($product_id) {
        wp_delete_post($product_id);
    }

    $current_order_id = get_post_meta($post_id, '_order_id', true);
    $current_order = wc_get_order($current_order_id);
    
    $previous_status = '';
    if ($current_order) {
        $previous_status = get_post_meta($post_id, '_order_previous_status', true);
    }
    
    if (isset($_POST['fatura_nome'])) {
        update_post_meta($post_id, '_fatura_nome', sanitize_text_field($_POST['fatura_nome']));
    }
    if (isset($_POST['fatura_descricao'])) {
        update_post_meta($post_id, '_fatura_descricao', sanitize_textarea_field($_POST['fatura_descricao']));
    }
    if (isset($_POST['fatura_valor'])) {
        update_post_meta($post_id, '_fatura_valor', floatval(sanitize_text_field($_POST['fatura_valor'])));
    }
    if (isset($_POST['order_id'])) {
        $order_id = sanitize_text_field($_POST['order_id']);
        update_post_meta($post_id, '_order_id', $order_id);
        $order = wc_get_order($order_id);
        if ($order) {
            update_post_meta($post_id, '_order_previous_status', $order->get_status());
            $order->update_status('wc-fatura-pendente', 'Status alterado para Fatura pendente.');
            
            $user_id = $order->get_user_id();
            $user = get_userdata($user_id);
            if ($user_id) {
                // wp_mail(
                //     $user->user_email,
                //     'Fatura Disponível',
                //     'A fatura referente ao seu pedido já está disponível no seu carrinho.'
                // );
                $numero_suite = get_user_meta($user_id, 'suite', true);
                if ($numero_suite) {
                    update_post_meta($post_id, '_numero_suite', $numero_suite);
                    $product_id = create_fatura_product($post_id);
                    update_post_meta($post_id, '_product_id', $product_id);
                }
            }
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Pedido com o ID fornecido não encontrado. Status da fatura não foi alterado.</p></div>';
                });
            }
            if ($current_order && $current_order_id !== $order_id) {
            $current_order->update_status($previous_status, 'Status revertido devido a mudança de fatura.');
        }
    }
}