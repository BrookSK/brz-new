<?php

require 'utils/create-redirecionamento-product.php';
require 'utils/create-fatura-product.php';

require 'utils/get-user-by-suite.php';
require 'utils/get-ncm-options.php';
require 'utils/fatura-fields.php';

add_action('add_meta_boxes', 'add_redirecionamento_fields');
add_action('save_post', 'salvar_dados_personalizados_redirecionamento');
add_action('before_delete_post', 'delete_redirecionamento_product');
add_action('admin_notices', 'suite_not_found_notice');
add_action('admin_notices', 'editing_disabled_notice');
add_action('post_edit_form_tag', 'add_enctype_to_post_edit_form');

function add_enctype_to_post_edit_form() {
    global $post;
    if ($post->post_type == 'redirecionamento') {
        echo ' enctype="multipart/form-data"';
    }
}

function add_redirecionamento_fields() {
    if (isset($_GET['add_fatura'])) {
        add_meta_box(
            'info_redirecionamento',
            'Informações da Fatura',
            'redirecionamento_fields',
            'redirecionamento',
            'normal',
            'high'
        );
    }
    else {
        add_meta_box(
            'info_redirecionamento',
            'Informações do Redirecionamento',
            'redirecionamento_fields',
            'redirecionamento',
            'normal',
            'high'
        );
    }
}

function redirecionamento_fields($post) {
    if (isset($_GET['add_fatura']) || get_post_meta($post->ID, '_fatura', true)) {
        fatura_fields($post->ID);
        return;
    }
    
    $numero_suite = get_post_meta($post->ID, '_numero_suite', true);
    $nome = get_post_meta($post->ID, '_nome', true);
    $descricao = get_post_meta($post->ID, '_descricao', true);
    $fornecedor = get_post_meta($post->ID, '_fornecedor', true);
    $ncm = get_post_meta($post->ID, '_ncm', true);
    $recebimento = get_post_meta($post->ID, '_recebimento', true);
    $peso = get_post_meta($post->ID, '_peso', true);
    $quantidade = get_post_meta($post->ID, '_quantidade', true);
    $foto = get_post_meta($post->ID, '_foto', true);
    $status = get_post_meta($post->ID, '_status', true);

    $order_id = get_post_meta($post->ID, '_order_id', true);
    $readonly = (!$status || $status === 'Pendente') ? '' : 'readonly';

    $ncm_options = get_ncm_options();

    $usuario = get_user_by_suite($numero_suite);
    $nome_usuario = $usuario ? $usuario->display_name : '';
    $usuario_edit_link = $usuario ? get_edit_user_link($usuario->ID): '';
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
        .form-table td textarea,
        .form-table td select {
            width: 100%;
        }
    </style>

    <table class="form-table">
        <tr>
            <th><label for="numero_suite">Número de Suite*</label></th>
            <td>
                <input type="number" id="numero_suite" name="numero_suite" value="<?php echo esc_attr($numero_suite); ?>" <?php echo $readonly; ?> required />
                <?php if ($usuario) : ?>
                    <p id="usuario_nome"><strong>Usuário: </strong><a target="_blank" href="<?php echo $usuario_edit_link; ?>"><?php echo esc_html($nome_usuario); ?></a></p>
                <?php else : ?>
                    <p id="usuario_nome"></p>
                <?php endif; ?>
                <p id="usuario_erro" style="color: red; display: none;">Nenhum usuário encontrado para este número de suite.</p>
            </td>
        </tr>
        <tr>
            <th><label for="nome">Nome do Produto*</label></th>
            <td><input type="text" id="nome" name="nome" value="<?php echo esc_attr($nome); ?>" <?php echo $readonly; ?> required /></td>
        </tr>
        <tr>
            <th><label for="descricao">Observações</label></th>
            <td><textarea id="descricao" name="descricao" <?php echo $readonly; ?>><?php echo esc_textarea($descricao); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="fornecedor">Fornecedor*</label></th>
            <td><input type="text" id="fornecedor" name="fornecedor" value="<?php echo esc_attr($fornecedor); ?>" <?php echo $readonly; ?> required /></td>
        </tr>
        <tr>
            <th><label for="ncm">NCM*</label></th>
            <td>
                <select id="ncm" name="ncm" <?php echo $readonly === 'readonly' ? 'disabled' : ''; ?> required>
                    <option value="">Selecione o NCM</option>
                    <?php foreach ($ncm_options as $key => $value) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($ncm, $key); ?>>
                            <?php echo esc_html($key . ' - ' . $value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="recebimento">Recebimento*</label></th>
            <td>
                <input 
                    type="date" 
                    id="recebimento" 
                    name="recebimento" 
                    value="<?php echo esc_attr($recebimento ?: date('Y-m-d')); ?>" 
                    <?php echo $readonly; ?> 
                    required 
                />
            </td>
        </tr>
        <tr>
            <th><label for="peso">Peso (kg)*</label></th>
            <td><input type="number" id="peso" name="peso" value="<?php echo esc_attr($peso); ?>" step="0.01" <?php echo $readonly; ?> required /></td>
        </tr>
        <tr>
            <th><label for="quantidade">Quantidade*</label></th>
            <td><input type="number" id="quantidade" name="quantidade" value="<?php echo esc_attr($quantidade); ?>" <?php echo $readonly; ?> required /></td>
        </tr>
        <tr>
            <th><label for="foto">Foto*</label></th>
            <td>
                <div class="file-upload">
                    <input type="file" id="foto" name="foto" accept="image/*" <?php echo $readonly; ?> />
                    <img src="<?php echo esc_url($foto); ?>" alt="Foto do produto" style="max-width: 150px; height: auto; margin-top: 10px;"/>
                </div>
            </td>
        </tr>
    </table>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#foto').on('change', function(event) {
                var input = $(event.target);
                var label = input.next('label');
                var img = input.closest('.file-upload').find('img');
                
                if (input[0].files && input[0].files[0]) {
                    var reader = new FileReader();
                    
                    reader.onload = function(e) {
                        img.attr('src', e.target.result);
                    }
                    
                    reader.readAsDataURL(input[0].files[0]);
                    label.text('Imagem selecionada');
                } else {
                    img.attr('src', '');
                    label.text('Escolher imagem');
                }
            });

            $('#numero_suite').on('input', function() {
                var suiteNumber = $(this).val();

                if (suiteNumber !== '') {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_user_by_suite',
                            suite_number: suiteNumber,
                        },
                        success: function(response) {
                            var result = JSON.parse(response);
                            if (result.success) {
                                $('#usuario_nome').html('<strong>Usuário: </strong><a target="_blank" href="' + result.edit_link + '">' + result.display_name + '</a>');
                                $('#usuario_erro').hide();
                            } else {
                                $('#usuario_nome').text('');
                                $('#usuario_erro').show();
                            }
                        }
                    });
                } else {
                    $('#usuario_nome').text('');
                    $('#usuario_erro').hide();
                }
            });
        });
    </script>
    <?php
}

function salvar_dados_personalizados_redirecionamento($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) != 'redirecionamento') {
        return;
    }

    $status = get_post_meta($post_id, '_status', true);
    if ($status && ($status !== 'Pendente' && $status !== 'Descarte')) {
        add_filter('redirect_post_location', function($location) use ($post_id) {
            $post_type_page_url = admin_url('post.php?post=' . $post_id . '&action=edit');
            return add_query_arg('editing_disabled', 1, $post_type_page_url);
        });
        return;
    }

    if (isset($_GET['add_fatura']) || get_post_meta($post_id, '_fatura', true)) {
        handle_edit_fatura($post_id);
        return;
    }

    $fields = array(
        'numero_suite',
        'nome',
        'descricao',
        'fornecedor',
        'ncm',
        'recebimento',
        'peso',
        'quantidade',
    );
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }

    if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
        $uploaded_file = wp_handle_upload($_FILES['foto'], array('test_form' => false));
        $old_attachment_id = attachment_url_to_postid(get_post_meta($post_id, '_foto', true));
    
        if (isset($uploaded_file['file'])) {
            $file = $uploaded_file['file'];
            $url = $uploaded_file['url'];
            $title = sanitize_file_name(pathinfo($file, PATHINFO_FILENAME));
            $desc = '';
    
            $file_type = wp_check_filetype($file);
            $mime_type = $file_type['type'];
    
            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title'     => $title,
                'post_content'   => $desc,
                'post_status'    => 'inherit'
            );
    
            $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attachment_id, $file);
                wp_update_attachment_metadata($attachment_id, $attach_data);
    
                update_post_meta($post_id, '_foto', esc_url_raw($url));
                wp_delete_attachment($old_attachment_id, true);
            }
        }
    }
    
    if (isset($_POST['numero_suite'])) {
        update_post_meta($post_id, '_status', 'Pendente');
        $product_id = get_post_meta($post_id, '_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id);
        }
        
        $suite = sanitize_text_field($_POST['numero_suite']);
        if (!get_user_by_suite($suite)) {
            add_filter('redirect_post_location', function($location) use ($post_id) {
                $post_type_page_url = admin_url('post.php?post=' . $post_id . '&action=edit');
                return add_query_arg('suite_not_found', 1, $post_type_page_url);
            });
            return;
        }
        $product_id = create_redirecionamento_product($post_id);
        update_post_meta($post_id, '_product_id', $product_id);

        $user = get_user_by_suite($suite);
        if ($user) {
            send_redirecionamento_email($user, $post_id);
        }
    }
}

function delete_redirecionamento_product($post_id) {
    if (get_post_type($post_id) != 'redirecionamento') {
        return;
    }

    $product_id = get_post_meta($post_id, '_product_id', true);
    if ($product_id) {
        wp_delete_post($product_id, true);
    }
}

function suite_not_found_notice() {
    if (isset($_GET['suite_not_found'])) {
        echo '<div class="notice notice-warning is-dismissible"><p>Nenhum usuário foi encontrado com o número de suite fornecido.</p></div>';
    }
}

function editing_disabled_notice() {
    if (isset($_GET['editing_disabled'])) {
        echo '<div class="notice notice-error is-dismissible"><p>Não é possível alterar um redirecionamento após o seu pagamento.</p></div>';
    }
}



// Shortcode para formulário público de redirecionamento
add_shortcode('redirecionamento_form', function() {
    if (!is_user_logged_in()) {
        return '<div style="color:red;">Você precisa estar logado para criar um redirecionamento.</div>';
    }
    $fake_post = (object)[ 'ID' => 0 ];
    ob_start();
    // Mensagens de feedback
    if (isset($_GET['red_success'])) {
        echo '<div class="notice-success" style="color:green;">Redirecionamento criado com sucesso!</div>';
    }
    if (isset($_GET['red_error'])) {
        echo '<div class="notice-error" style="color:red;">Ocorreu um erro ao criar o redirecionamento.</div>';
    }
    echo '<form id="redirecionamento-public-form" method="post" enctype="multipart/form-data" action="' . admin_url('admin-post.php') . '" class="red-card-form">';
    echo '<input type="hidden" name="action" value="salvar_redirecionamento_admin">';
    wp_nonce_field('admin_redirecionamento_form', 'admin_redirecionamento_nonce');
    echo '<input type="hidden" name="redirect_to" value="'.esc_url($_SERVER['REQUEST_URI']).'">';
    redirecionamento_fields($fake_post);
    echo '<p id="usuario_nome"></p>';
    echo '<p id="usuario_erro" style="color: red; display: none;">Nenhum usuário encontrado para este número de suite.</p>';
    echo '<button type="submit" class="red-btn" style="color:#fff;">Enviar</button>';
    echo '<div id="red-loading-overlay" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:10;align-items:center;justify-content:center;"><div style="margin:auto;text-align:center;"><div class="spinner" style="margin:0 auto 10px;width:32px;height:32px;border:4px solid #0073aa;border-top:4px solid #fff;border-radius:50%;animation:spin 1s linear infinite;"></div><span style="color:#0073aa;font-weight:600;">Processando...</span></div></div>';
    echo '<div id="red-form-msg" style="display:none;margin-top:16px;text-align:center;font-size:1.1em;"></div>';
    echo '</form>';
    echo '<style>
    .red-card-form {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 16px 0 rgba(0,0,0,0.06);
        padding: 32px 24px;
        margin: 32px auto;
        max-width: 500px;
        font-family: inherit;
    }
    .red-card-form .form-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
    }
    .red-card-form th {
        text-align: left;
        padding-bottom: 4px;
        font-size: 1rem;
        color: #222;
        width: 32%;
        vertical-align: top;
    }
    .red-card-form td {
        padding-bottom: 4px;
    }
    .red-card-form input[type="text"],
    .red-card-form input[type="number"],
    .red-card-form input[type="date"],
    .red-card-form textarea,
    .red-card-form select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 1rem;
        background: #f9f9f9;
        transition: border 0.2s;
        margin-bottom: 2px;
    }
    .red-card-form input:focus,
    .red-card-form textarea:focus,
    .red-card-form select:focus {
        border: 1.5px solid #0073aa;
        outline: none;
        background: #fff;
    }
    .red-card-form textarea {
        min-height: 60px;
        resize: vertical;
    }
    .red-card-form .file-upload {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
    .red-card-form .file-upload input[type="file"] {
        padding: 0;
        border: none;
        background: transparent;
    }
    .red-card-form .file-upload img {
        max-width: 120px;
        border-radius: 6px;
        border: 1px solid #eee;
        margin-top: 6px;
        background: #fafafa;
        object-fit: contain;
    }
    .red-btn {
        width: 100%;
        padding: 14px 0;
        background: linear-gradient(90deg, #0073aa 60%, #0099cc 100%);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 1.15rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px 0 rgba(0,0,0,0.04);
        transition: background 0.2s, box-shadow 0.2s;
        margin-top: 18px;
    }
    .red-btn:hover, .red-btn:focus {
        background: linear-gradient(90deg, #005c8a 60%, #0073aa 100%);
        box-shadow: 0 4px 16px 0 rgba(0,115,170,0.10);
    }
    .notice-success, .notice-error {
        display: block;
        text-align: center;
        padding: 14px 0;
        border-radius: 6px;
        margin-bottom: 18px;
        font-size: 1.08rem;
        font-weight: 500;
    }
    .notice-success {
        background: #e8f5e9;
        color: #217a39;
        border: 1px solid #b9e6c6;
    }
    .notice-error {
        background: #ffebee;
        color: #b71c1c;
        border: 1px solid #f8bdbd;
    }
    @media (max-width: 600px) {
        .red-card-form {
            padding: 12px 4vw;
            max-width: 98vw;
        }
        .red-card-form th { font-size: 0.98rem; }
        .red-btn { font-size: 1rem; }
    }
    @keyframes spin {0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
    </style>';
    // JS para busca usuário por suite no shortcode
    echo '<script>var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
    echo '<script>
    jQuery(document).ready(function($) {
        var typingTimer;
        $("#numero_suite").on("input", function() {
            clearTimeout(typingTimer);
            $("#usuario_nome").text("");
            $("#usuario_erro").hide();
            var suiteNumber = $(this).val();
            if (suiteNumber !== "") {
                typingTimer = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "get_user_by_suite",
                            suite_number: suiteNumber,
                        },
                        success: function(response) {
                            var result = JSON.parse(response);
                            if (result.success) {
                                $("#usuario_nome").html("<strong>Usuário: </strong><a target=\"_blank\" href=\"" + result.edit_link + "\">" + result.display_name + "</a>");
                                $("#usuario_erro").hide();
                            } else {
                                $("#usuario_nome").text("");
                                $("#usuario_erro").show();
                            }
                        }
                    });
                }, 500);
            }
        });
    });
    </script>';
    // JS para loading e mensagem pós-submit
    echo '<script>
    jQuery(function($){
        var $form = $("#redirecionamento-public-form");
        var $overlay = $("#red-loading-overlay");
        var $msg = $("#red-form-msg");
        $form.on("submit", function(){
            $msg.hide();
            $overlay.show();
        });
        // Detecta sucesso/erro pós reload
        $(function(){
            var url = new URL(window.location.href);
            if(url.searchParams.get("red_success")){
                $msg.text("Redirecionamento adicionado com sucesso").css({color:"#217a39",background:"#e8f5e9",border:"1px solid #b9e6c6",display:"block",padding:"12px",borderRadius:"6px"}).show();
            }
            if(url.searchParams.get("red_error")){
                $msg.text("Ocorreu um erro ao criar o redirecionamento.").css({color:"#b71c1c",background:"#ffebee",border:"1px solid #f8bdbd",display:"block",padding:"12px",borderRadius:"6px"}).show();
            }
            $overlay.hide();
        });
    });
    </script>';
    return ob_get_clean();
});

// Handler seguro para processamento admin customizado do redirecionamento
add_action('admin_post_salvar_redirecionamento_admin', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }
    $redirect_url = isset($_POST['redirect_to']) && !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/');

    // Validação de nonce
    if (!isset($_POST['admin_redirecionamento_nonce']) || !wp_verify_nonce($_POST['admin_redirecionamento_nonce'], 'admin_redirecionamento_form')) {
        $redirect_url = remove_query_arg(['red_success', 'red_error'], $redirect_url);
        $redirect_url = add_query_arg('red_error', 1, $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
    $fields = ['numero_suite','nome','fornecedor','ncm','recebimento','peso','quantidade'];
    foreach ($fields as $field) {
        if (empty($_POST[$field])) {
            $redirect_url = remove_query_arg(['red_success', 'red_error'], $redirect_url);
            $redirect_url = add_query_arg('red_error', 1, $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }
    }
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $post_data = [
        'post_title'   => sanitize_text_field($_POST['nome']),
        'post_type'    => 'redirecionamento',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    ];
    if ($post_id > 0) {
        $post_data['ID'] = $post_id;
        $post_id = wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }
    if (is_wp_error($post_id) || !$post_id) {
        $redirect_url = remove_query_arg(['red_success', 'red_error'], $redirect_url);
        $redirect_url = add_query_arg('red_error', 1, $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
    foreach ($fields as $field) {
        update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
    }
    // Upload de foto
    if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $uploaded_file = wp_handle_upload($_FILES['foto'], array('test_form' => false));
        if (isset($uploaded_file['file']) && isset($uploaded_file['url'])) {
            $file = $uploaded_file['file'];
            $url = $uploaded_file['url'];
            $title = sanitize_file_name(pathinfo($file, PATHINFO_FILENAME));
            $file_type = wp_check_filetype($file);
            $mime_type = $file_type['type'];
    
            // Cria o attachment no banco
            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title'     => $title,
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
            if (!is_wp_error($attachment_id)) {
                // Força o post_parent
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_parent' => $post_id,
                ]);
                $attach_data = wp_generate_attachment_metadata($attachment_id, $file);
                wp_update_attachment_metadata($attachment_id, $attach_data);

                // Salva a URL no meta
                update_post_meta($post_id, '_foto', esc_url_raw($url));
                // NÃO define como imagem destacada para manter consistência com o fluxo do admin
            }
        }
    }
    update_post_meta($post_id, '_status', 'Pendente');
    
    $redirect_url = remove_query_arg(['red_success', 'red_error'], $redirect_url);
    $redirect_url = add_query_arg('red_success', 1, $redirect_url);
    wp_redirect($redirect_url);
    exit;
});
