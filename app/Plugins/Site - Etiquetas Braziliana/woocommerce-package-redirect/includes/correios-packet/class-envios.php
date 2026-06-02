<?php

use Dompdf\Dompdf;
use Dompdf\Options;
use Milon\Barcode\DNS1D;

class WPR_Envios
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_wpr_envios_menu']);
        add_action('admin_init', [$this, 'wpr_settings_init']);
        add_action('init', [$this, 'register_custom_post_type']);
        add_action('wp_ajax_get_order_data', [$this, 'ajax_get_order_data']);
        add_action('add_meta_boxes', [$this, 'add_package_meta_boxes']);
        add_action('save_post', [$this, 'save_package_meta']);
        add_action('admin_notices', [$this, 'show_package_request_errors']);

        add_filter('manage_package_posts_columns', [$this, 'set_package_columns']);
        add_action('manage_package_posts_custom_column', [$this, 'populate_package_columns'], 10, 2);

        add_action('before_delete_post', [$this, 'on_package_delete']);
        add_filter('post_row_actions', [$this, 'remove_delete_action'], 10, 2);
    }

    public function set_package_columns($columns)
    {
        unset($columns['title']);
        unset($columns['date']);

        $columns['order_id'] = __('ID do Pedido', 'wpr');
        $columns['tracking_code'] = __('Código de Rastreio', 'wpr');
        $columns['container_id'] = __('ID do Container', 'wpr');
        $columns['date'] = __('Data', 'wpr');
        return $columns;
    }

    public function populate_package_columns($column, $post_id)
    {
        switch ($column) {
            case 'order_id':
                $order_id = get_post_meta($post_id, '_package_order_id', true);
                if ($order_id) {
                    echo 'Pedido <a target="_blank" href="' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')) . '">#' . esc_html($order_id) . '</a>';
                } else {
                    echo '<span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                }
                break;

            case 'tracking_code':
                $tracking_code = get_post_meta($post_id, '_correios_tracking_code', true);
                echo $tracking_code ? esc_html($tracking_code) : '<span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                break;

            case 'container_id':
                $container_id = get_post_meta($post_id, '_container_id', true);
                if ($container_id) {
                    echo '<a target="_blank" href="' . esc_url(admin_url('post.php?post=' . $container_id . '&action=edit')) . '">' . esc_html($container_id) . '</a>';
                } else {
                    echo '<span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                }
                break;
        }
    }

    public function add_wpr_envios_menu()
    {
        add_menu_page(
            'Envios',
            'Envios',
            'manage_options',
            'envios',
            [$this, 'wpr_page_content'],
            'dashicons-admin-site',
            25
        );

        add_submenu_page(
            'envios',
            'Configurações',
            'Configurações',
            'manage_options',
            'envios-config',
            [$this, 'wpr_config_page_content']
        );
    }

    public function wpr_config_page_content()
    {
        ?>
        <div class="wrap">
            <h1>Configurações de Envios</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpr_config_group');
                do_settings_sections('envios-config');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function wpr_settings_init()
    {
        add_settings_section(
            'wpr_access_config_section',
            'Configurações de Acesso',
            null,
            'envios-config'
        );

        $this->add_settings_field('wpr_correios_username', 'Username', 'text', 'wpr_access_config_section');
        $this->add_settings_field('wpr_correios_password', 'Password', 'password', 'wpr_access_config_section');
        $this->add_settings_field('wpr_correios_numero', 'Cartão de Postagem', 'text', 'wpr_access_config_section');
        $this->add_settings_field('wpr_correios_test_mode', 'Modo de Teste', 'checkbox', 'wpr_access_config_section');
        $this->add_settings_field('wpr_correios_debug_mode', 'Modo de Debug', 'checkbox', 'wpr_access_config_section');

        add_settings_section(
            'wpr_sender_config_section',
            'Dados do Remetente',
            null,
            'envios-config'
        );

        $this->add_settings_field('wpr_correios_sender_name', 'Nome do Remetente', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_address', 'Endereço', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_address_number', 'Número do Endereço', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_address_complement', 'Complemento do Endereço', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_zip_code', 'Zip Code', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_city_name', 'Cidade', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_state', 'Estado', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_country_code', 'Código do País', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_email', 'Email', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_website', 'Website', 'text', 'wpr_sender_config_section');
        $this->add_settings_field('wpr_correios_sender_contract', 'Contrato', 'text', 'wpr_sender_config_section');

        add_settings_section(
            'wpr_return_config_section',
            'Dados de Devolução',
            null,
            'envios-config'
        );

        $this->add_settings_field('wpr_correios_return_company', 'Nome da Empresa no Brasil', 'text', 'wpr_return_config_section');
        $this->add_settings_field('wpr_correios_return_street', 'Logradouro', 'text', 'wpr_return_config_section');
        $this->add_settings_field('wpr_correios_return_neighborhood', 'Bairro', 'text', 'wpr_return_config_section');
        $this->add_settings_field('wpr_correios_return_zip_code', 'CEP', 'text', 'wpr_return_config_section');
        $this->add_settings_field('wpr_correios_return_city', 'CEP', 'text', 'wpr_return_config_section');
        $this->add_settings_field('wpr_correios_return_uf', 'UF', 'text', 'wpr_return_config_section');

        $options = [
            'wpr_correios_username', 'wpr_correios_password', 'wpr_correios_numero', 
            'wpr_correios_test_mode', 'wpr_correios_debug_mode',
            'wpr_correios_sender_name', 'wpr_correios_sender_address', 
            'wpr_correios_sender_address_number', 'wpr_correios_sender_address_complement', 
            'wpr_correios_sender_zip_code', 'wpr_correios_sender_city_name', 
            'wpr_correios_sender_state', 'wpr_correios_sender_country_code', 
            'wpr_correios_sender_email', 'wpr_correios_sender_website', 'wpr_correios_sender_contract',
            'wpr_correios_return_company', 'wpr_correios_return_street',
            'wpr_correios_return_neighborhood', 'wpr_correios_return_zip_code',
            'wpr_correios_return_zip_code', 'wpr_correios_return_city',
            'wpr_correios_return_city', 'wpr_correios_return_uf',
        ];
        foreach ($options as $option) {
            register_setting('wpr_config_group', $option);
        }
    }

    private function add_settings_field($field_id, $label, $type = 'text', $section)
    {
        add_settings_field(
            $field_id,
            $label,
            function () use ($field_id, $type) {
                $value = get_option($field_id, '');
                if ($type === 'checkbox') {
                    echo '<input type="checkbox" value="1" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" ' . checked('1', $value, false) . ' />';
                    echo '<label for="' . esc_attr($field_id) . '"> Habilitar</label>';
                } elseif ($type === 'password') {
                    echo '<input type="password" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" />';
                } else {
                    echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" />';
                }
            },
            'envios-config',
            $section
        );
    }

    public function register_custom_post_type()
    {
        $labels = array(
            'name'                  => 'Pacotes',
            'singular_name'         => 'Pacote',
            'menu_name'             => 'Pacotes',
            'name_admin_bar'        => 'Pacote',
            'add_new'               => 'Adicionar Novo',
            'add_new_item'          => 'Adicionar Novo Pacote',
            'new_item'              => 'Novo Pacote',
            'edit_item'             => 'Editar Pacote',
            'view_item'             => 'Ver Pacote',
            'all_items'             => 'Pacotes',
            'search_items'          => 'Procurar Pacotes',
            'not_found'             => 'Nenhum pacote encontrado.',
            'not_found_in_trash'    => 'Nenhum pacote encontrado na lixeira.',
            'archives'              => 'Arquivos de Pacotes',
            'insert_into_item'      => 'Inserir no pacote',
            'uploaded_to_this_item' => 'Carregado para este pacote',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'envios',
            'query_var'          => false,
            'rewrite'            => array('slug' => 'package'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array(''),
        );

        register_post_type('package', $args);
    }

    function ajax_get_order_data() {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'ID do pedido não fornecido.']);
        }
    
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
    
        if (!$order) {
            wp_send_json_error(['message' => 'Pedido não encontrado.']);
        }
    
        $recipient_data = [
            'recipient_name'               => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'recipient_document_type'      => get_post_meta($order_id, '_billing_document_type', true),
            'recipient_document_number'    => get_post_meta($order_id, '_billing_cpf', true),
            'recipient_address'            => $order->get_shipping_address_1(),
            'recipient_address_complement' => $order->get_shipping_address_2(),
            'recipient_address_number'     => get_post_meta($order_id, '_shipping_number', true),
            'recipient_city_name'          => $order->get_shipping_city(),
            'recipient_state'              => $order->get_shipping_state(),
            'recipient_zip_code'           => $order->get_shipping_postcode(),
            'recipient_email'              => $order->get_billing_email(),
            'recipient_phone_number'       => $order->get_billing_phone(),
        ];
    
        $order_items = [];
        $total_weight = get_order_weight($order_id);
    
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $item_weight = floatval($product->get_weight()) * intval($item->get_quantity());
    
            $order_items[] = [
                'name'     => $item->get_meta('_product_name'),
                'quantity' => $item->get_quantity(),
                'value'    => $item->get_meta('_declaration_value') ?: $item->get_total() ?: $product->get_price(),
                'ncm'      => $item->get_meta('_ncm'),
                'weight'   => $product->get_weight(),
            ];
        }

        $freight_paid_value = 0;
        $insurance_paid_value = 0;

        foreach ($order->get_fees() as $fee_id => $fee) {
            $fee_name = $fee->get_name();
            $fee_total = $fee->get_total();

            if ($fee_name === 'Taxa de Frete') {
                $freight_paid_value = $fee_total;
            } elseif ($fee_name === 'Taxa de Seguro') {
                $insurance_paid_value = $fee_total;
            }
        }

        if ($order->get_currency() === 'BRL') {
            $_woocs_order_rate = $order->get_meta('_wccs_currency_rate', true);
            if($_woocs_order_rate > 0) {
                $freight_paid_value /= $_woocs_order_rate;
                $insurance_paid_value /= $_woocs_order_rate;
            }
        }
        
        wp_send_json_success([
            'recipient_name'               => $recipient_data['recipient_name'],
            'recipient_document_type'      => $recipient_data['recipient_document_type'],
            'recipient_document_number'    => $recipient_data['recipient_document_number'],
            'recipient_address'            => $recipient_data['recipient_address'],
            'recipient_address_complement' => $recipient_data['recipient_address_complement'],
            'recipient_address_number'     => $recipient_data['recipient_address_number'],
            'recipient_city_name'          => $recipient_data['recipient_city_name'],
            'recipient_state'              => $recipient_data['recipient_state'],
            'recipient_zip_code'           => $recipient_data['recipient_zip_code'],
            'recipient_email'              => $recipient_data['recipient_email'],
            'recipient_phone_number'       => $recipient_data['recipient_phone_number'],
            'items'                        => $order_items,
            'total_weight'                 => $total_weight,
            'freight_paid_value'           => $freight_paid_value,
            'insurance_paid_value'         => $insurance_paid_value,
        ]);
    }    
    


    public function add_package_meta_boxes()
    {
        add_meta_box(
            'package_details',
            'Detalhes do Pacote',
            [$this, 'render_package_meta_box'],
            'package',
            'normal',
            'high'
        );
    }

    public function render_package_meta_box($post) {
        $debug_mode = get_option('wpr_correios_debug_mode', false);

        $order_id = get_post_meta($post->ID, '_package_order_id', true);
        $order = wc_get_order($order_id);

        $order_items = [];
        if($order) {
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $order_items[] = [
                    'name'     => $item->get_meta('_product_name'),
                    'quantity' => $item->get_quantity(),
                    'value'    => $item->get_meta('_declaration_value') ?: $item->get_total() ?: $product->get_price(),
                    'ncm'      => $item->get_meta('_ncm'),
                    'weight'   => $product->get_weight(),
                ];
            }
        }

        $tracking_code = get_post_meta($post->ID, '_correios_tracking_code', true);
        $readonly = $tracking_code ? 'readonly' : '';

        $recipient_name = '';
        $recipient_document_type = '';
        $recipient_document_number = '';
        $recipient_address = '';
        $recipient_address_complement = '';
        $recipient_address_number = '';
        $recipient_city_name = '';
        $recipient_state = '';
        $recipient_zip_code = '';
        $recipient_email = '';
        $recipient_phone_number = '';
        if ($order) {
            $recipient_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
            $recipient_document_type = get_post_meta($order_id, '_billing_document_type', true);
            $recipient_document_number = get_post_meta($order_id, '_billing_cpf', true);
            $recipient_address = $order->get_shipping_address_1();
            $recipient_address_complement = $order->get_shipping_address_2();
            $recipient_address_number = get_post_meta($order_id, '_shipping_number', true);
            $recipient_city_name = $order->get_shipping_city();
            $recipient_state = $order->get_shipping_state();
            $recipient_zip_code = $order->get_shipping_postcode();
            $recipient_email = $order->get_billing_email();
            $recipient_phone_number = $order->get_billing_phone();
        }

        $width = get_post_meta($post->ID, '_package_width', true);
        $height = get_post_meta($post->ID, '_package_height', true);
        $length = get_post_meta($post->ID, '_package_length', true);
        $distribution_modality = get_post_meta($post->ID, '_distribution_modality', true);
        $tax_payment_method = get_post_meta($post->ID, '_tax_payment_method', true);
        $currency = get_post_meta($post->ID, '_currency', true);
        $non_nationalization_instruction = get_post_meta($post->ID, '_non_nationalization_instruction', true);
        $package_rfid_code = get_post_meta($post->ID, '_package_rfid_code', true);
        $total_weight = get_post_meta($post->ID, '_total_weight', true);
        $freight_paid_value = get_post_meta($post->ID, '_freight_paid_value', true);
        $insurance_paid_value = get_post_meta($post->ID, '_insurance_paid_value', true);
    
        ?>
        <style>
            .form-table {
                width: 100%;
                border-collapse: collapse;
            }
            .form-table th, .form-table td {
                padding: 8px;
                vertical-align: top;
            }
            .form-table th {
                width: 20%;
                text-align: left;
            }
            .form-table td input, .form-table td textarea, .form-table td select {
                width: 100%;
            }
    
            .form-table #load_order_data {
                margin-top: 4px;
            }
    
            .form-table .section-title {
                font-size: 16px;
                background-color: #eaeaea;
                text-align: center;
                padding: 10px;
                border: 1px solid #ccc;
            }
        </style>

        <?php if (!empty($tracking_code)) : ?>
            <div>
                <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                <button type="button" class="button-secondary" id="generate_pdf">Gerar Etiqueta</button>
            </div>
        <?php endif; ?>

        <table class="form-table">            
            <tr>
                <th><label for="package_order_id">ID do Pedido*</label></th>
                <td>
                    <input required type="number" id="package_order_id" name="package_order_id" value="<?php echo esc_attr($order_id); ?>" <?php echo $readonly; ?> />
                    <button type="button" id="load_order_data" <?php echo $readonly ? 'disabled' : ''; ?> >Carregar Dados do Pedido</button>
                </td>
            </tr>
    
            <tr><th colspan="2" class="section-title">Informações do Destinatário</th></tr>
            <tr>
            <th><label for="recipient_name">Nome do Destinatário</label></th>
                <td><input type="text" id="recipient_name" name="recipient_name" value="<?php echo esc_attr($recipient_name); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_document_type">Tipo de Documento</label></th>
                <td><input type="text" id="recipient_document_type" name="recipient_document_type" value="<?php echo esc_attr($recipient_document_type); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_document_number">Número do Documento</label></th>
                <td><input type="text" id="recipient_document_number" name="recipient_document_number" value="<?php echo esc_attr($recipient_document_number); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_address">Endereço do Destinatário</label></th>
                <td><input type="text" id="recipient_address" name="recipient_address" value="<?php echo esc_attr($recipient_address); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_address_number">Número do Endereço</label></th>
                <td><input type="text" id="recipient_address_number" name="recipient_address_number" value="<?php echo esc_attr($recipient_address_number); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_address_complement">Complemento do Endereço</label></th>
                <td><input type="text" id="recipient_address_complement" name="recipient_address_complement" value="<?php echo esc_attr($recipient_address_complement); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_city_name">Cidade do Destinatário</label></th>
                <td><input type="text" id="recipient_city_name" name="recipient_city_name" value="<?php echo esc_attr($recipient_city_name); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_state">Estado do Destinatário</label></th>
                <td><input type="text" id="recipient_state" name="recipient_state" value="<?php echo esc_attr($recipient_state); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_zip_code">CEP do Destinatário</label></th>
                <td><input type="text" id="recipient_zip_code" name="recipient_zip_code" value="<?php echo esc_attr($recipient_zip_code); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_email">E-mail do Destinatário</label></th>
                <td><input type="email" id="recipient_email" name="recipient_email" value="<?php echo esc_attr($recipient_email); ?>" readonly /></td>
            </tr>
            <tr>
                <th><label for="recipient_phone_number">Telefone do Destinatário</label></th>
                <td><input type="text" id="recipient_phone_number" name="recipient_phone_number" value="<?php echo esc_attr($recipient_phone_number); ?>" readonly /></td>
            </tr>
    
            <tr><th colspan="2" class="section-title">Informações de Envio</th></tr>
            <tr>
                <th><label for="currency">Moeda*</label></th>
                <td>
                    <select id="currency" name="currency" required <?php echo $readonly ? 'disabled' : ''; ?>>
                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - Dólar Americano</option>
                        <!-- <option value="BRL" <?php // selected($currency, 'BRL'); ?>>BRL - Real Brasileiro</option> -->
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="freight_paid_value">Valor do Frete*</label></th>
                <td><input required type="number" id="freight_paid_value" name="freight_paid_value" value="<?php echo esc_attr($freight_paid_value); ?>" step="0.01" min="0" max="999999" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="insurance_paid_value">Valor do Seguro</label></th>
                <td><input type="number" id="insurance_paid_value" name="insurance_paid_value" value="<?php echo esc_attr($insurance_paid_value); ?>" step="0.01" min="0" max="999999" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="distribution_modality">Modalidade de Distribuição*</label></th>
                <td>
                    <select id="distribution_modality" name="distribution_modality" required <?php echo $readonly ? 'disabled' : ''; ?>>
                        <option value="33162" <?php selected($distribution_modality, '33162'); ?>>PACKET STANDARD</option>
                        <option value="33170" <?php selected($distribution_modality, '33170'); ?>>PACKET EXPRESS</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="tax_payment_method">Forma de Pagamento do Imposto*</label></th>
                <td>
                    <select id="tax_payment_method" name="tax_payment_method" required <?php echo $readonly ? 'disabled' : ''; ?>>
                        <option value="DDU" <?php selected($tax_payment_method, 'DDU'); ?>>DDU - Pagamento Posterior</option>
                        <option value="DDP" <?php selected($tax_payment_method, 'DDP'); ?>>DDP - Antecipação de Tributos</option>
                        <option value="PRC" <?php selected($tax_payment_method, 'PRC'); ?>>PRC - Programa Remessa Conforme</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="non_nationalization_instruction">Instrução de Não Nacionalização*</label></th>
                <td>
                    <select id="non_nationalization_instruction" name="non_nationalization_instruction" required <?php echo $readonly ? 'disabled' : ''; ?>>
                        <option value="RETURNTOORIGIN" <?php selected($non_nationalization_instruction, 'RETURNTOORIGIN'); ?>>Devolver à Origem</option>
                        <option value="TREATASABANDONED" <?php selected($non_nationalization_instruction, 'TREATASABANDONED'); ?>>Tratar como Abandonado</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="package_rfid_code">Código RFID do Pacote</label></th>
                <td><input type="text" id="package_rfid_code" name="package_rfid_code" value="<?php echo esc_attr($package_rfid_code); ?>" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="total_weight">Peso Total (g)*</label></th>
                <td><input required type="number" id="total_weight" name="total_weight" value="<?php echo esc_attr($total_weight); ?>" step="0.01" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="package_width">Largura da Caixa (cm)*</label></th>
                <td><input required type="number" id="package_width" name="package_width" value="<?php echo esc_attr($width); ?>" step="0.01" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="package_height">Altura da Caixa (cm)*</label></th>
                <td><input required type="number" id="package_height" name="package_height" value="<?php echo esc_attr($height); ?>" step="0.01" <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="package_length">Comprimento da Caixa (cm)*</label></th>
                <td><input required type="number" id="package_length" name="package_length" value="<?php echo esc_attr($length); ?>" step="0.01" <?php echo $readonly; ?> /></td>
            </tr>

            <!-- <tr>
                <th><label for="provisioned_tax_value">Valor do Imposto de Importação</label></th>
                <td><input type="number" id="provisioned_tax_value" name="provisioned_tax_value" value="<?php // echo esc_attr($provisionedTaxValue); ?>" step="0.01" min="0.00" max="3000" /></td>
            </tr>
            <tr>
                <th><label for="provisioned_icms_value">Valor do ICMS</label></th>
                <td><input type="number" id="provisioned_icms_value" name="provisioned_icms_value" value="<?php // echo esc_attr($provisionedIcmsValue); ?>" step="0.01" min="0.00" max="3000" /></td>
            </tr>
            <tr>
                <th><label for="sender_code_ece">Código do Cliente (CNPJ ou TIN)</label></th>
                <td><input type="text" id="sender_code_ece" name="sender_code_ece" value="<?php // echo esc_attr($senderCodeEce); ?>" maxlength="20" /></td>
            </tr>
            <tr>
                <th><label for="general_description">Descrição Geral dos Bens</label></th>
                <td><textarea id="general_description" name="general_description" maxlength="500"><?php // echo esc_textarea($generalDescription); ?></textarea></td>
            </tr> -->

            <?php if ($debug_mode) : ?>            
                <tr><th colspan="2" class="section-title">Informações de Depuração</th></tr>
                <tr>
                    <th><label for="_debug_request_body">Request Body</label></th>
                    <td><pre id="_debug_request_body"><?php print_r(get_post_meta($post->ID, '_debug_request_body', true)); ?></pre></td>
                </tr>
                <tr>
                    <th><label for="_debug_response_body">Response Body</label></th>
                    <td><pre id="_debug_response_body"><?php print_r(get_post_meta($post->ID, '_debug_response_body', true)); ?></pre></td>
                </tr>
                <tr>
                    <th><label for="_debug_error_message">Error Message</label></th>
                    <td><pre id="_debug_error_message"><?php print_r(get_post_meta($post->ID, '_debug_error_message', true)); ?></pre></td>
                </tr>
            <?php endif; ?>

        </table>

        <table class="widefat alternate striped list-table" style="width: 100%; overflow-x: auto;">
            <thead>
                <tr>
                    <th colspan="9" style="text-align: center; font-weight: bold;">
                        <?php _e('Produtos', 'woocommerce'); ?>
                    </th>
                </tr>
                <tr>
                    <th><strong><?php _e('NCM', 'woocommerce'); ?></strong></</th>
                    <th><strong><?php _e('Descrição', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Preço ($)', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Peso (kg)', 'woocommerce'); ?></strong></th>
                    <th><strong><?php _e('Quantidade', 'woocommerce'); ?></strong></th>
                </tr>
            </thead>
            <tbody id="order_items_container">   
            <?php
                foreach ($order_items as $item) :
                    $ncm = isset($item['ncm']) ? $item['ncm'] : 'N/A';
                    $description = isset($item['name']) ? $item['name'] : 'N/A';
                    $value = isset($item['value']) ? floatval($item['value']) : 0;
                    $weight = isset($item['weight']) ? floatval($item['weight']) : 0;
                    $quantity = isset($item['quantity']) ? $item['quantity'] : 0;
                ?>
                    <tr>
                        <td><?php echo esc_html($ncm); ?></td>
                        <td><?php echo esc_html($description); ?></td>
                        <td><?php echo esc_html(number_format($value, 2, ',', '.')); ?></td>
                        <td><?php echo esc_html(number_format($weight, 2, ',', '.')); ?></td>
                        <td><?php echo esc_html($quantity); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#load_order_data').on('click', function() {
                    var orderId = $('#package_order_id').val();

                    if (!orderId) {
                        alert('Por favor, insira um ID de pedido válido.');
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'get_order_data',
                            order_id: orderId,
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#recipient_name').val(response.data.recipient_name);
                                $('#recipient_document_type').val(response.data.recipient_document_type);
                                $('#recipient_document_number').val(response.data.recipient_document_number);
                                $('#recipient_address').val(response.data.recipient_address);
                                $('#recipient_address_number').val(response.data.recipient_address_number);
                                $('#recipient_address_complement').val(response.data.recipient_address_complement);
                                $('#recipient_city_name').val(response.data.recipient_city_name);
                                $('#recipient_state').val(response.data.recipient_state);
                                $('#recipient_zip_code').val(response.data.recipient_zip_code);
                                $('#recipient_email').val(response.data.recipient_email);
                                $('#recipient_phone_number').val(response.data.recipient_phone_number);

                                $('#freight_paid_value').val(response.data.freight_paid_value);
                                $('#insurance_paid_value').val(response.data.insurance_paid_value);
                                $('#total_weight').val(response.data.total_weight);

                                var itemsHtml = '';
                                response.data.items.forEach(function(item) {
                                    itemsHtml += 
                                        '<tr>' +
                                            '<td>' + item.ncm + '</td>' +
                                            '<td>' + item.name + '</td>' +
                                            '<td>' + item.value + '</td>' +
                                            '<td>' + item.weight + '</td>' +
                                            '<td>' + item.quantity + '</td>' +
                                        '</tr>';
                                });
                                $('#order_items_container').html(itemsHtml);
                            } else {
                                alert('Erro ao carregar os dados do pedido: ' + response.data.message);
                            }
                        },
                        error: function() {
                            alert('Ocorreu um erro ao processar a solicitação.');
                        }
                    });
                });
                $('#generate_pdf').on('click', function() {
                    var $form = $(this).closest('form');
                    var $hiddenInput = $('<input>', {
                        type: 'hidden',
                        name: 'generate_pdf',
                        value: '1'
                    });
                    $form.append($hiddenInput); 
                    $form.submit();
                });

            });
        </script>
        <?php
    }

    function check_existing_order_before_insert($data, $postarr) {
        if ($data['post_type'] === 'package' && isset($_POST['package_order_id'])) {
            $order_id = intval(sanitize_text_field($_POST['package_order_id']));
            
            if (get_post_type($order_id) !== 'shop_order') {
                set_transient('package_request_errors', 'O ID fornecido não é de um Pedido válido', 5);
            }
        }
        return $data;
    }

    public function save_package_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }
        
        if (get_post_type($post_id) !== 'package') {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == '1') {
            $this->generate_pdf($_POST['post_id']);
        }

        $tracking_code = get_post_meta($post_id, '_correios_tracking_code', 1);
        if ($tracking_code) {
            return;
        }
        
        if (isset($_POST['package_width']) && !empty($_POST['package_width'])) {
            update_post_meta($post_id, '_package_width', floatval(sanitize_text_field($_POST['package_width'])));
        }
        
        if (isset($_POST['package_height']) && !empty($_POST['package_height'])) {
            update_post_meta($post_id, '_package_height', floatval(sanitize_text_field($_POST['package_height'])));
        }
        
        if (isset($_POST['package_length']) && !empty($_POST['package_length'])) {
            update_post_meta($post_id, '_package_length', floatval(sanitize_text_field($_POST['package_length'])));
        }
        
        if (isset($_POST['distribution_modality']) && !empty($_POST['distribution_modality'])) {
            update_post_meta($post_id, '_distribution_modality', sanitize_text_field($_POST['distribution_modality']));
        }
        
        if (isset($_POST['tax_payment_method']) && !empty($_POST['tax_payment_method'])) {
            update_post_meta($post_id, '_tax_payment_method', sanitize_text_field($_POST['tax_payment_method']));
        }
        
        if (isset($_POST['currency']) && !empty($_POST['currency'])) {
            update_post_meta($post_id, '_currency', sanitize_text_field($_POST['currency']));
        }
        
        if (isset($_POST['non_nationalization_instruction']) && !empty($_POST['non_nationalization_instruction'])) {
            update_post_meta($post_id, '_non_nationalization_instruction', sanitize_text_field($_POST['non_nationalization_instruction']));
        }
        
        if (isset($_POST['package_rfid_code']) && !empty($_POST['package_rfid_code'])) {
            update_post_meta($post_id, '_package_rfid_code', sanitize_text_field($_POST['package_rfid_code']));
        }
        
        if (isset($_POST['total_weight']) && !empty($_POST['total_weight'])) {
            update_post_meta($post_id, '_total_weight', floatval(sanitize_text_field($_POST['total_weight'])));
        }
        
        if (isset($_POST['freight_paid_value']) && !empty($_POST['freight_paid_value'])) {
            update_post_meta($post_id, '_freight_paid_value', floatval(sanitize_text_field($_POST['freight_paid_value'])));
        }
        
        if (isset($_POST['insurance_paid_value']) && !empty($_POST['insurance_paid_value'])) {
            update_post_meta($post_id, '_insurance_paid_value', floatval(sanitize_text_field($_POST['insurance_paid_value'])));
        }
        
        if (isset($_POST['provisioned_tax_value']) && !empty($_POST['provisioned_tax_value'])) {
            update_post_meta($post_id, '_provisioned_tax_value', floatval(sanitize_text_field($_POST['provisioned_tax_value'])));
        }
        
        if (isset($_POST['provisioned_icms_value']) && !empty($_POST['provisioned_icms_value'])) {
            update_post_meta($post_id, '_provisioned_icms_value', floatval(sanitize_text_field($_POST['provisioned_icms_value'])));
        }
        
        if (isset($_POST['sender_code_ece']) && !empty($_POST['sender_code_ece'])) {
            update_post_meta($post_id, '_sender_code_ece', sanitize_text_field($_POST['sender_code_ece']));
        }
        
        if (isset($_POST['general_description']) && !empty($_POST['general_description'])) {
            update_post_meta($post_id, '_general_description', sanitize_textarea_field($_POST['general_description']));
        }

        if (isset($_POST['package_order_id']) && !empty($_POST['package_order_id'])) {
            $order_id = intval(sanitize_text_field($_POST['package_order_id']));
            update_post_meta($post_id, '_package_order_id', $order_id);
            $this->create_package($order_id, $post_id);
        } else {
            set_transient('package_request_errors', 'O ID do Pedido é obrigatório.', 5);
        }
    }

    private function create_package($order_id, $post_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $sender_data = [
            'senderName' => get_option('wpr_correios_sender_name', ''),
            'senderAddress' => get_option('wpr_correios_sender_address', ''),
            'senderAddressNumber' => get_option('wpr_correios_sender_address_number', ''),
            'senderAddressComplement' => get_option('wpr_correios_sender_address_complement', ''),
            'senderZipCode' => get_option('wpr_correios_sender_zip_code', ''),
            'senderCityName' => get_option('wpr_correios_sender_city_name', ''),
            'senderState' => get_option('wpr_correios_sender_state', ''),
            'senderCountryCode' => get_option('wpr_correios_sender_country_code', 'US'),
            'senderEmail' => get_option('wpr_correios_sender_email', ''),
            'senderWebsite' => get_option('wpr_correios_sender_website', ''),
        ];        
    
        $recipient_data = [
            'customerControlCode' => $order_id,
            'recipientName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'recipientDocumentType' => 'CPF',
            'recipientDocumentNumber' => preg_replace('/\D/', '', get_post_meta($order_id, '_billing_cpf', true)),
            'recipientAddress' => $order->get_shipping_address_1(),
            'recipientAddressNumber' => get_post_meta($order_id, '_shipping_number', true),
            'recipientAddressComplement' => $order->get_shipping_address_2(),
            'recipientCityName' => $order->get_shipping_city(),
            'recipientState' => $order->get_shipping_state(),
            'recipientCountryCode' => $order->get_shipping_country(),
            'recipientZipCode' => preg_replace('/\D/', '', $order->get_shipping_postcode()),
        ];
    
        $order_items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_items[] = [
                'hsCode' => $item->get_meta('_ncm'),
                'description' => $item->get_meta('_product_name'),
                'quantity' => $item->get_quantity(),
                'value' => $item->get_meta('_declaration_value') ?: $item->get_total(),
            ];
        }
    
        $package_data = [
            'packageList' => [
                array_merge(
                    $sender_data,
                    $recipient_data,
                    [
                        'packagingWidth' => get_post_meta($post_id, '_package_width', true) ?: 0,
                        'packagingHeight' => get_post_meta($post_id, '_package_height', true) ?: 0,
                        'packagingLength' => get_post_meta($post_id, '_package_length', true) ?: 0,
                        'totalWeight' => get_post_meta($post_id, '_total_weight', true) ?: 0,
                        'distributionModality' => get_post_meta($post_id, '_distribution_modality', true) ?: 33162,
                        'taxPaymentMethod' => get_post_meta($post_id, '_tax_payment_method', true) ?: 'DDU',
                        'currency' => get_post_meta($post_id, '_currency', true) ?: 'USD',
                        'nonNationalizationInstruction' => get_post_meta($post_id, '_non_nationalization_instruction', true) ?: 'RETURNTOORIGIN',
                        'freightPaidValue' => get_post_meta($post_id, '_freight_paid_value', true) ?: 0,
                        'insurancePaidValue' => get_post_meta($post_id, '_insurance_paid_value', true) ?: null,
                        'provisionedTaxValue' => get_post_meta($post_id, '_provisioned_tax_value', true) ?: null,
                        'provisionedIcmsValue' => get_post_meta($post_id, '_provisioned_icms_value', true) ?: null,
                        'senderCodeEce' => get_post_meta($post_id, '_sender_code_ece', true) ?: null,
                        'generalDescription' => get_post_meta($post_id, '_general_description', true) ?: null,
                        'items' => $order_items
                    ]
                )
            ]
        ];
    
        try {
            $correios_service = new WPR_Correios_Service();
            update_post_meta($post_id, '_debug_request_body', $package_data);
            $response = $correios_service->create_package($package_data);
            if ($response) {
                update_post_meta($post_id, '_debug_response_body', $response);
                update_post_meta($post_id, '_correios_tracking_code', $response[0]->trackingNumber);
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            update_post_meta($post_id, '_debug_error_message', $error_message);
            set_transient('package_request_errors', $error_message, 60);
        }
    }
    

    public function show_package_request_errors()
    {
        $message = get_transient('package_request_errors');

        if ($message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            delete_transient('package_request_errors');
        }
    }

    private function generate_pdf($package_id) {
        if (!is_user_logged_in()) {
            wp_die('Você precisa estar logado para gerar o PDF.');
        }

        $order_id = get_post_meta($package_id, '_package_order_id', true);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Pedido não encontrado. ID do pedido: ' . $order_id);
        }

        $tracking_code = get_post_meta($package_id, '_correios_tracking_code', true);

        $recipient_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $recipient_document_type = get_post_meta($order_id, '_billing_document_type', true);
        $recipient_document_number = get_post_meta($order_id, '_billing_cpf', true);
        $recipient_address = $order->get_shipping_address_1();
        $recipient_address_complement = $order->get_shipping_address_2();
        $recipient_address_number = get_post_meta($order_id, '_shipping_number', true);
        $recipient_city_name = $order->get_shipping_city();
        $recipient_state = $order->get_shipping_state();
        $recipient_zip_code = $order->get_shipping_postcode();
        $recipient_email = $order->get_billing_email();
        $recipient_phone_number = $order->get_billing_phone();

        $width = get_post_meta($package_id, '_package_width', true);
        $height = get_post_meta($package_id, '_package_height', true);
        $length = get_post_meta($package_id, '_package_length', true);

        $distribution_modality = get_post_meta($package_id, '_distribution_modality', true);
        $modality_description = '';
        $modality_image_path = '';

        if ($distribution_modality == '33162') {
            $modality_description = 'PACKET STANDARD';
            $modality_image_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/packet-standard.png';
        } elseif ($distribution_modality == '33170') {
            $modality_description = 'PACKET EXPRESS';
            $modality_image_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/packet-express.png';
        }
        
        $tax_payment_method = get_post_meta($package_id, '_tax_payment_method', true);
        $currency = get_post_meta($package_id, '_currency', true);
        $non_nationalization_instruction = get_post_meta($package_id, '_non_nationalization_instruction', true);
        $package_rfid_code = get_post_meta($package_id, '_package_rfid_code', true);
        $total_weight = get_post_meta($package_id, '_total_weight', true);

        $freight_paid_value = get_post_meta($package_id, '_freight_paid_value', true);
        $insurance_paid_value = get_post_meta($package_id, '_insurance_paid_value', true) ?: 0;

        $items = [];
        $total_weight = get_post_meta($package_id, '_total_weight', true) / 1000;
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $weight = $product->get_weight();
            $quantity = $item->get_quantity();
            $items[] = [
                'hsCode' => $item->get_meta('_ncm'),
                'description' => $item->get_meta('_product_name'),
                'quantity' => $quantity,
                'value' => $item->get_meta('_declaration_value') ?: $item->get_total(),
                'weight' => $weight,
            ];
        }
        $item_weight = $total_weight / count($items);
        $items_suplementary = array_slice($items, 3);
        $items = array_slice($items, 0, 3);

        $sender_name = get_option('wpr_correios_sender_name', '');
        $sender_address = get_option('wpr_correios_sender_address', '');
        $sender_address_number = get_option('wpr_correios_sender_address_number', '');
        $sender_address_complement = get_option('wpr_correios_sender_address_complement', '');
        $sender_zip_code = get_option('wpr_correios_sender_zip_code', '');
        $sender_city_name = get_option('wpr_correios_sender_city_name', '');
        $sender_state = get_option('wpr_correios_sender_state', '');
        $sender_country_code = get_option('wpr_correios_sender_country_code', '');
        $sender_email = get_option('wpr_correios_sender_email', '');
        $sender_website = get_option('wpr_correios_sender_website', '');
        $sender_contract = get_option('wpr_correios_sender_contract', '');

        $return_company_name = get_option('wpr_correios_return_company', 'N/A');
        $return_street = get_option('wpr_correios_return_street', 'N/A');
        $return_neighborhood = get_option('wpr_correios_return_neighborhood', 'N/A');
        $return_zip_code = get_option('wpr_correios_return_zip_code', 'N/A');
        $return_city = get_option('wpr_correios_return_city', 'N/A');
        $return_uf = get_option('wpr_correios_return_uf', 'N/A');

        $logo_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/logo-transamerica.png';
        $correios_logo_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/logo-correios.png';

        $barcode_generator = new DNS1D();
        $barcode_generator->setStorPath(__DIR__.'/cache/');

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        ob_start();

        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Etiqueta - <?php echo $tracking_code; ?></title>
            <style>
                @page {
                    margin: 0;
                    width: 100mm;
                    height: 150mm;
                }
                body {
                    font-family: Arial, sans-serif;
                }
                .page {
                    page-break-after: always;
                }
                .page:last-child {
                    page-break-after: avoid;
                } 
                body p {
                    font-size: 8pt;
                    margin: 0;
                }
                .header {
                    margin: 2.5mm;
                    position: relative;
                }
                .header p, .header strong {
                    margin-top: 10px;
                    font-size: 10pt;
                    line-height: 2px;
                }
                .header .logo-container {
                    width: 20mm;
                    height: 20mm;
                }
                .header .logo-container img {
                    vertical-align: middle;
                    width: 100%;
                }
                .header .left .logo-container img {
                    margin-top: 40%;
                }
                .header .right {
                    position: absolute;
                    top: 0;
                    right: 0;
                    float: right;
                    display: block;
                }
                .header .right .logo-container {
                    margin-left: auto;
                }
              	.header .right .logo-container img {
                    vertical-align: middle;
                    width: auto;
                  	height: 100%;
                }
                .header .correios-service-info {
                    text-align: right;
                }

                .tracking-number {
                    position: relative;
                    text-align: center;
                    height: 18mm;
                    width: 80mm;
                    margin-bottom: 6mm;
                    margin-left: 5mm;
                    padding: 0;
                }
                .tracking-number p {
                    font-size: 15pt;
                }
                .tracking-number .bar-code-container {
                    height: 100%;
                    width: 100%;
                }
                .tracking-number .bar-code-container img {
                    width: 100%;
                    height: 100%;
                }
                .tracking-number .service-class {
                    position: absolute;
                    font-size: 20pt;
                    top: 9mm;
                    left: 85mm;
                }
                
                .recipient {
                    width: 100%;
                }
                .recipient p, .instructions p, .customs-declaration p {
                    font-size: 8pt;
                    line-height: 10pt;
                    padding: 0;
                }
                .recipient .recipient-sign {
                    margin: 0 2.5mm;
                    margin-bottom: 2mm;
                    position: relative;
                    height: 24px;
                    width: 100%;
                }
                .recipient .recipient-sign .document {
                    position: absolute;
                    right: 30mm;
                    bottom: -5px;
                    background-color: white;
                    padding: 0 1px 0.5px 1px;
                }
                .recipient .signature-container {
                    position: relative;
                    width: 95mm;
                }
                .recipient .signature-container .line {
                    position: absolute;
                    width: 80mm;
                    left: 55px;
                    border-bottom: solid 1px black;
                }
                .recipient .recipient-data {
                    width: 99.5%;
                    padding: 0;
                    border: solid 1px black;
                    height: 26mm;
                    position: relative;
                }
                .recipient .recipient-data p {
                    padding-left: 2.5mm;
                    font-size: 8.5pt;
                }
                .recipient .recipient-data .left {
					width: 48mm;
                }
                .recipient .recipient-data .section-title {
                    color: white;
                    background-color: black;
                    padding: 3px 2.5mm 3px 2.5mm;
                    display: inline-block;
                    margin-bottom: 1.5mm;
                }
                .recipient .recipient-data .right {
                    position: absolute;
                    top: 2mm;
                    right: 0.4mm;
                    margin-right: 5mm;
                    height: 18mm;
                    width: 40mm;
                }
                .recipient .recipient-data .bar-code-container {
                    height: 100%;
                    width: 100%;
                    margin-bottom: 1.5mm;
                }
                .recipient .recipient-data .bar-code-container img {
                    width: 100%;
                    height: 100%;
                }

                .recipient .recipient-data .right div {
                    height: 100%;
                    width: 100%;
                }
                .recipient .recipient-data .right p {
                    text-align: center;
                }

                .instructions {
                    margin: 0 2.5mm;
                    position: relative;
                    height: 26mm;
                    margin-bottom: 10px;
                }
                .instructions .non-nationalization-policy {
                    position: relative;
                    width: 100%;
                    height: 10pt;
                }
                .instructions .non-nationalization-policy .square {
                    position: absolute;
                    left: 0;
                    top: 1pt;
                    height: 6pt;
                    width: 8pt;
                    border: solid 1px black;
                    font-size: 5pt;
                    text-align: center;
                }
                .instructions .non-nationalization-policy p {
                    position: absolute;
                    left: 4mm;
                    top: 0;
                }
                .instructions .return-section {
                    width: 60mm;
                    line-height: 0;
                }
                .instructions .return-section .note {
                    font-size: 6pt;
                    line-height: 6px;
                }
                .instructions .return-section .title {
                    text-align: center;
                    display: inline-block;
                    width: 100%;
                    margin: 0;
                }
                .instructions .return-section .title .line {
                    display: inline-block;
                    vertical-align: middle;
                    width: 30%;
                    height: 1px;
                    background-color: black;
                    margin-top: 4px;
                }
                .instructions .return-section p {
                    line-height: 12px;
                }
                .instructions .return-section .title p {
                    display: inline-block;
                    vertical-align: middle;
                    margin: 4px 4px 0 4px;
                }
                .instructions .right {
                    position: absolute;
                    top: 5mm;
                    right: 2.5mm;
                    display: block;
                }
                .instructions .right p {
                    font-size: 10pt;
                }
                .instructions .complaints p {
                    line-height: 9px;
                    font-size: 6pt;
                }

                .customs-declaration {
                    margin: 1mm 2.5mm 2.5mm 2.5mm;
                }
                .customs-declaration table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .customs-declaration th, .customs-declaration td {
                    border: 1px solid #000;
                    font-size: 6pt;
                    font-weight: normal;
                    padding: 0;
                    text-align: left;
                    word-wrap: break-word;
                    word-break: break-word;
                }
                .customs-declaration .table-header, .customs-declaration .sh {
                    word-wrap: nowrap;
                    word-break: nowrap;
                    white-space: nowrap;
                }

                .suplementary.customs-declaration {
                    margin: 0;
                    width: 100%;
                    height: 100%;
                }
                .suplementary.customs-declaration th, .suplementary.customs-declaration td {
                    padding: 2;
                    font-size: 6pt;
                    text-align: center;
                    vertical-align: middle;
                }

                .to-sender {
                    padding: 2.5mm;
                }
                .to-sender th, .to-sender td {
                    font-size: 10pt;
                    padding: 5px 40px 5px 5px;
                }
            </style>
        </head>
        <body>
            <div class="page">
                <div class="header">
                    <div class="left">
                        <div class="logo-container">
                            <img src="<?php echo image_to_base64($logo_path); ?>" alt=" ">
                        </div>
                        <div class="order-info">
                            <p>Order #: <?php echo $order_id; ?></p>
                            <p><?php echo $tax_payment_method; ?></p>
                        </div>
                        <div class="logo-container" style="display: inline-block; position: absolute; top: -7.5mm; left: 25mm;">
                            <img src="<?php echo image_to_base64($correios_logo_path); ?>" alt=" ">
                        </div>
                    </div>
                    <div class="right">
                        <div class="logo-container">
                            <img src="<?php echo image_to_base64($modality_image_path); ?>" alt=" ">
                        </div>
                        <div class="correios-service-info">
                            <p><strong><?php echo $modality_description; ?></strong></p>
                            <p>Contrato <strong><?php echo $sender_contract; ?></strong></p>
                        </div>
                    </div>
                </div>
                <div class="tracking-number">
                    <p><strong><?php echo $tracking_code; ?></strong></p>
                    <div class="bar-code-container">
                        <img src="data:image/png;base64,<?php echo $barcode_generator->getBarcodePNG($tracking_code, 'C128'); ?>" alt=" ">
                    </div>
                    <p class="service-class"><strong>US</strong></p>
                </div>
                <div class="recipient">
                    <div class="recipient-sign">
                        <div class="signature-container">
                            <p>Recebedor:</p>
                            <div class="line"></div>
                        </div>
                        <div class="signature-container">
                            <p>Assinatura:</p>
                            <div class="line"></div>
                        </div>
                        <p class="document">Documento:</p>
                    </div>
                    <div class="recipient-data">
                        <div class="left">
                            <p class="section-title"><strong>DESTINATÁRIO</strong></p>
                            <p><?php echo $recipient_name; ?></p>
                            <p><?php echo $recipient_address; ?>, <?php echo $recipient_address_number; ?></p>
                            <p><?php echo $recipient_address_complement; ?></p>
                            <p><?php echo $recipient_city_name; ?>/<?php echo $recipient_state; ?></p>
                        </div>
                        <div class="right">
                            <div class="bar-code-container">
                                <img src="data:image/png;base64,<?php echo $barcode_generator->getBarcodePNG(preg_replace('/\D/', '', $recipient_zip_code), 'C128'); ?>" alt=" ">
                            </div>
                            <p style="font-size: 15pt;"><strong><?php echo $recipient_zip_code; ?></strong></p>
                        </div>
                    </div>
                </div>
                <div class="instructions">
                    <div class="left">
                        <p><strong>Instrução do Remetente no caso de não nacionalização:</strong></p>
                        <div class="non-nationalization-policy">
                            <div class="square"><?php echo $non_nationalization_instruction === 'RETURNTOORIGIN' ? 'X' : '' ?></div>
                            <p>Retorno à origem</p>
                        </div>
                
                        <div class="complaints">
                            <p>Dúvidas e reclamações:</p>
                            <p><?php echo $sender_email; ?> / <?php echo $sender_website; ?></p>
                        </div>
                        <div class="return-section">
                            <div class="title">
                                <div class="line"></div>
                                <p><strong>DEVOLUÇÃO:</strong></p>
                                <div class="line"></div>
                            </div>
                            <p class="note">(Em caso de não entrega ao remetente, entregar para:)</p>
                            <p><?php echo esc_html($return_company_name); ?></p>
                            <p><?php echo esc_html($return_street) . ' / ' . esc_html($return_neighborhood); ?></p>
                            <p><?php echo esc_html($return_zip_code) . ' - ' . esc_html($return_city) . '/' . esc_html($return_uf); ?></p>
                        </div>
                    </div>
                    <div class="right">
                        <p><strong>Remetente:</strong></p>
                        <p><?php echo $sender_name; ?></p>
                        <p><?php echo $sender_address_number; ?> <?php echo $sender_address; ?></p>
                        <p><?php echo $sender_city_name; ?></p>
                        <p><?php echo $sender_country_code; ?></p>
                        <p><strong>Braziliana</strong></p>
                    </div>
                </div>
                <div class="customs-declaration">
                    <table>
                        <tr>
                            <th colspan="3"><strong>Declaração para Alfândega</strong></th>
                            <th colspan="3">Pode ser aberto Ex Officio 1/1</th>
                        </tr>
                        <thead>
                            <tr>
                                <th class="table-header sh">Cod SH</th>
                                <th class="table-header">Qtde</th>
                                <th class="table-header">Descrição do Conteúdo</th>
                                <th class="table-header">Peso KG</th>
                                <th class="table-header">Unit USD</th>
                                <th class="table-header">Valor USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_items_value = 0;
                            foreach ($items as $item) {
                                $item_total = $item['value'] * $item['quantity'];
                                $total_items_value += $item_total;
                            ?>
                            <tr>
                                <td><?php echo $item['hsCode']; ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['description']; ?></td>
                                <td><?php echo number_format($item_weight / $item['quantity'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($item['value'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($item_total, 2, ',', '.'); ?></td>
                            </tr>
                            <?php } ?>
                            <tr>
                                <th colspan="5">Frete USD:</th>
                                <td><?php echo number_format($freight_paid_value, 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <th colspan="5">Seguro USD:</th>
                                <td><?php echo number_format($insurance_paid_value, 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <th colspan="5">Total USD (Mercadorias + Frete + Seguro):</th>
                                <?php
                                $total_usd = $total_items_value + $freight_paid_value + $insurance_paid_value;
                                ?>
                                <td><?php echo number_format($total_usd, 2, ',', '.'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="page">
                <div class="to-sender">
                    <table border="1" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr>
                                <th colspan="5">AO REMETENTE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">MUDOU-SE</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">ENDEREÇO INSUFICIENTE</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">NÃO EXISTE O Nº INDICADO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">FALECIDO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">DESCONHECIDO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">RECUSADO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">AUSENTE</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">NÃO PROCURADO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">OUTROS:</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">INFORMAÇÃO PRESTADA PELO PORTEIRO OU SÍNDICO</td>
                            </tr>
                            <tr>
                                <td colspan="2"></td>
                                <td colspan="3">REINTEGRADO AO SERVIÇO POSTAL EM _____/_____/_________</td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 30px;" colspan="2">DATA:</td>
                                <td style="padding-bottom: 30px;" colspan="3">RUBRICA:</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($items_suplementary)) : ?>
                <div class="page">
                    <div class="suplementary customs-declaration">
                        <table>
                            <thead>
                                <tr>
                                    <th colspan="4"><strong>SUPPLEMENTARY</strong></th>
                                    <th colspan="2"><strong><?php echo $tracking_code; ?></strong></th>
                                </tr>
                                <tr><th colspan="6">CUSTOMS DECLARATION</th></tr>
                                <tr>
                                    <th>COD.SH</th>
                                    <th>QUANTITY</th>
                                    <th>DESCRIPTION</th>
                                    <th>WEIGHT (KG)</th>
                                    <th>UNI (U$)</th>
                                    <th>VALUE (U$)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_linhas = count($items_suplementary);
                                $total_items_value = 0;
                                $total_items_weight = 0;

                                for ($i = 0; $i < $total_linhas; $i++) {
                                    if (isset($items_suplementary[$i])) {
                                        $item = $items_suplementary[$i];
                                        $hsCode = $item['hsCode'];
                                        $description = $item['description'];
                                        $quantity = $item['quantity'];
                                        $value = $item['value'];
                                        $weight = $item_weight / $quantity;
                                        $item_total = $value * $quantity;
                                        $weight_total = $weight * $quantity;

                                        $total_items_weight += $weight_total;
                                        $total_items_value += $item_total;
                                        ?>
                                        <tr>
                                            <td><?php echo $hsCode; ?></td>
                                            <td><?php echo $quantity; ?></td>
                                            <td><?php echo $description; ?></td>
                                            <td><?php echo number_format($weight, 2, ',', '.'); ?></td>
                                            <td><?php echo number_format($value, 2, ',', '.'); ?></td>
                                            <td><?php echo number_format($item_total, 2, ',', '.'); ?></td>
                                        </tr>
                                        <?php
                                    } else {
                                        ?>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                                <tr>
                                    <td>TOTAL</td>
                                    <td></td>
                                    <td></td>
                                    <td><?php echo number_format($total_items_weight, 2, ',', '.'); ?></td>
                                    <td></td>
                                    <td><?php echo number_format($total_items_value, 2, ',', '.'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif;?>
        </body>
        </html>
        <?php

        $html = ob_get_clean();
        $dompdf->setPaper(array(0, 0, 283.5, 425.2));
        $dompdf->loadHtml($html);
        $dompdf->render();
        
        $dompdf->stream("etiqueta_$tracking_code.pdf", array("Attachment" => false));
        exit;
    }

    public function on_package_delete($post_id)
    {
        if (get_post_type($post_id) !== 'package') {
            return;
        }

        $container_id = get_post_meta($post_id, '_container_id', true);
        if (!empty($container_id)) {
            $error_message = 'Não é possível deletar o pacote, pois ele está associado a um container.';
            set_transient('package_request_errors', $error_message, 5);
            wp_redirect(admin_url('edit.php?post_status=trash&post_type=package'));
            exit();
        }
    }

    public function remove_delete_action($actions, $post) {
        if ($post->post_type === 'package') {
            if (get_post_meta($post->ID, '_container_id', true)) {
                unset($actions['delete']);
            }
        }
        return $actions;
    }
}

new WPR_Envios();