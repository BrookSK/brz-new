<?php

use Dompdf\Dompdf;
use Dompdf\Options;
use Milon\Barcode\DNS1D;

class WPR_Container
{
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_post_type']);
        add_action('add_meta_boxes', [$this, 'add_container_meta_boxes']);
        add_action('save_post', [$this, 'save_container_meta']);
        add_filter('manage_container_posts_columns', [$this, 'set_container_columns']);
        add_action('manage_container_posts_custom_column', [$this, 'populate_container_columns'], 10, 2);
        add_action('before_delete_post', [$this, 'on_container_delete']);
        add_filter('post_row_actions', [$this, 'remove_delete_action'], 10, 2);
    }

    public function set_container_columns($columns)
    {
        unset($columns['title']);
        unset($columns['date']);

        $columns['unit_code'] = __('Código do Unitizador', 'text-domain');
        $columns['tracking_codes'] = __('Códigos de Rastreio', 'text-domain');
        $columns['bill_id'] = __('ID da Fatura', 'text-domain');
        $columns['date'] = __('Data', 'wpr');

        return $columns;
    }

    public function populate_container_columns($column, $post_id)
    {
        switch ($column) {
            case 'unit_code':
                $unit_code = get_post_meta($post_id, '_unit_code', true);
                echo esc_html($unit_code ? $unit_code : __('N/A', 'text-domain'));
                break;
            case 'tracking_codes':
                $tracking_codes = get_post_meta($post_id, '_tracking_codes', true);
                
                if (!empty($tracking_codes) && is_array($tracking_codes)) {
                    echo esc_html(implode(', ', array_map('esc_html', $tracking_codes)));
                } else {
                    echo esc_html(__('Nenhum conteúdo disponível', 'text-domain'));
                }
                break;
            case 'bill_id':
                $bill_id = get_post_meta($post_id, '_bill_id', true);
                if ($bill_id) {
                    echo '<a target="_blank" href="' . esc_url(admin_url('post.php?post=' . $bill_id . '&action=edit')) . '">' . esc_html($bill_id) . '</a>';
                } else {
                    echo '<span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                }
                break;                
        }
    }


    public function register_custom_post_type()
    {
        $labels = array(
            'name'                  => 'Containers',
            'singular_name'         => 'Container',
            'menu_name'             => 'Containers',
            'name_admin_bar'        => 'Container',
            'add_new'               => 'Adicionar Novo',
            'add_new_item'          => 'Adicionar Novo Container',
            'new_item'              => 'Novo Container',
            'edit_item'             => 'Editar Container',
            'view_item'             => 'Ver Container',
            'all_items'             => 'Containers',
            'search_items'          => 'Procurar Containers',
            'not_found'             => 'Nenhum container encontrado.',
            'not_found_in_trash'    => 'Nenhum container encontrado na lixeira.',
            'archives'              => 'Arquivos de Containers',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'envios',
            'query_var'          => false,
            'rewrite'            => array('slug' => 'container'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array(''),
        );

        register_post_type('container', $args);
    }

    public function add_container_meta_boxes()
    {
        add_meta_box(
            'container_details',
            'Detalhes do Container',
            [$this, 'render_container_meta_box'],
            'container',
            'normal',
            'high'
        );
    }

    public function render_container_meta_box($post)
    {
        $debug_mode = get_option('wpr_correios_debug_mode', false);

        $dispatch_number = get_post_meta($post->ID, '_dispatch_number', true);
        $origin_country = get_post_meta($post->ID, '_origin_country', true);
        $origin_operator_name = get_post_meta($post->ID, '_origin_operator_name', true);
        $destination_operator_name = get_post_meta($post->ID, '_destination_operator_name', true);
        $postal_category_code = get_post_meta($post->ID, '_postal_category_code', true);
        $service_subclass_code = get_post_meta($post->ID, '_service_subclass_code', true);
        $unit_list = get_post_meta($post->ID, '_unit_list', true);
        $unit_type = get_post_meta($post->ID, '_unit_type', true) ?: "";
        $awb = get_post_meta($post->ID, '_awb', true);
        $tracking_codes = get_post_meta($post->ID, '_tracking_codes', true) ?: array();
        $triage_group = get_post_meta($post->ID, '_triage_group', true);

        $unit_code = get_post_meta($post->ID, '_unit_code', true);
        $readonly = $unit_code ? 'readonly' : '';
        $disabled = $unit_code ? 'disabled' : '';

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

        <?php if (!empty($unit_code)) : ?>
            <div>
                <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                <button type="button" class="button-secondary" id="generate_pdf">Gerar Etiqueta</button>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="dispatch_number">Número da Remessa*</label></th>
                <td><input type="number" id="dispatch_number" name="dispatch_number" value="<?php echo esc_attr($dispatch_number); ?>" required <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="origin_country">País de Origem*</label></th>
                <td><input type="text" id="origin_country" name="origin_country" value="US" required readonly /></td>
            </tr>
            <tr>
                <th><label for="origin_operator_name">Nome do Operador de Origem*</label></th>
                <td><input type="text" id="origin_operator_name" name="origin_operator_name" value="<?php echo esc_attr($origin_operator_name); ?>" required <?php echo $readonly; ?> /></td>
            </tr>
            <tr>
                <th><label for="destination_operator_name">Nome do Operador de Destino*</label></th>
                <td>
                    <select id="destination_operator_name" name="destination_operator_name" required <?php echo $disabled; ?>>
                        <option value="">Selecione o operador</option>
                        <option value="CWBA" <?php selected($destination_operator_name, 'CWBA'); ?>>CWBA - Curitiba</option>
                        <option value="SAOD" <?php selected($destination_operator_name, 'SAOD'); ?>>SAOD - Guarulhos</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="postal_category_code">Código da Categoria Postal*</label></th>
                <td>
                    <select id="postal_category_code" name="postal_category_code" required <?php echo $disabled; ?>>
                        <option value="">Selecione a categoria postal</option>
                        <option value="A" <?php selected($postal_category_code, 'A'); ?>>A – Airmail ou Priority Mail</option>
                        <option value="B" <?php selected($postal_category_code, 'B'); ?>>B – S.A.L Mail ou Non-Priority Mail</option>
                        <option value="C" <?php selected($postal_category_code, 'C'); ?>>C – Surface Mail ou Non-Priority Mail</option>
                        <option value="D" <?php selected($postal_category_code, 'D'); ?>>D – Priority Mail enviado por transporte terrestre</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="service_subclass_code">Código da Subclasse do Serviço*</label></th>
                <td>
                    <select id="service_subclass_code" name="service_subclass_code" required <?php echo $disabled; ?>>
                        <option value="">Selecione a subclasse de serviço</option>
                        <option value="NX" <?php selected($service_subclass_code, 'NX'); ?>>NX – Serviço padrão</option>
                        <option value="IX" <?php selected($service_subclass_code, 'IX'); ?>>IX – Serviço expresso</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="unit_type">Tipo de Unidade*</label></th>
                <td>
                    <select id="unit_type" name="unit_type" required <?php echo $disabled; ?>>
                        <option value="">Selecione o tipo de unidade</option>
                        <option value="1" <?php selected($unit_type, '1'); ?>>1 - Saco até 30kg</option>
                        <option value="2" <?php selected($unit_type, '2'); ?>>2 - Caixa com base pallet até 500kg</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="awb">N° AWB</label></th>
                <td><input type="text" id="awb" name="awb" value="<?php echo esc_attr($awb); ?>" /></td>
            </tr>
            <tr>
                <th><label for="triage_group">Grupos de Triagem</label></th>
                <td>
                    <select id="triage_group" name="triage_group" required <?php echo $disabled; ?>>
                        <option value="">Selecione o grupo</option>
                        <option value="1" <?php selected($triage_group, '1'); ?>>1- São Paulo/SP</option>
                        <option value="2" <?php selected($triage_group, '2'); ?>>2 - Valinhos/SP</option>
                        <option value="3" <?php selected($triage_group, '3'); ?>>3 - Rio de Janeiro/RJ</option>
                        <option value="4" <?php selected($triage_group, '4'); ?>>4 - Curitiba/PR</option>
                        <option value="5" <?php selected($triage_group, '5'); ?>>5 - Curitiba/PR</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="package_ids">Pacotes*</label></th>
                <td>
                    <select id="package_ids" name="package_ids[]" multiple required <?php echo $disabled; ?>>
                        <?php
                        $meta_query = array(
                            'relation' => 'AND',
                            array(
                                'relation' => 'OR',
                                array(
                                    'key'     => '_container_id',
                                    'compare' => 'NOT EXISTS',
                                ),
                            ),
                            array(
                                'key'       => '_correios_tracking_code',
                                'compare'   => 'EXISTS',
                            )
                        );

                        if (!empty($tracking_codes)) {
                            $meta_query[0][] = array(
                                'key'     => '_correios_tracking_code',
                                'value'   => $tracking_codes,
                                'compare' => 'IN',
                            );
                        }

                        $packages = get_posts(array(
                            'post_type'      => 'package',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                            'meta_query'     => $meta_query,
                        ));

                        if (!empty($packages)) {
                            foreach ($packages as $package) {
                                $package_id = $package->ID;
                                $order_id = get_post_meta($package_id, '_package_order_id', true);
                                $correios_tracking_code = get_post_meta($package_id, '_correios_tracking_code', true);

                                $package_name = $order_id . ' - ' . $correios_tracking_code;
                                $selected = in_array($correios_tracking_code, $tracking_codes) ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($package_id); ?>" <?php echo $selected; ?>>Pedido #<?php echo esc_html($package_name); ?></option>
                                <?php
                            }
                        } else {
                            ?>
                            <option value="">Nenhum pacote disponível</option>
                            <?php
                        }
                        ?>
                    </select>

                    <p class="description">Segure Ctrl para selecionar múltiplos pacotes.</p>
                </td>
            </tr>

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

        <script type="text/javascript">
            jQuery(document).ready(function($) {
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


    public function save_container_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'container') {
            return;
        }

        if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == '1') {
            $this->generate_pdf($_POST['post_id']);
            return;
        }

        if (isset($_POST['awb'])) {
            update_post_meta($post_id, '_awb', sanitize_text_field($_POST['awb']));
        }

        $unit_code = get_post_meta($post_id, '_unit_code', 1);
        if ($unit_code) {
            return;
        }

        if (isset($_POST['dispatch_number'])) {
            update_post_meta($post_id, '_dispatch_number', intval(sanitize_text_field($_POST['dispatch_number'])));
        }
        if (isset($_POST['origin_country'])) {
            update_post_meta($post_id, '_origin_country', sanitize_text_field($_POST['origin_country']));
        }
        if (isset($_POST['origin_operator_name'])) {
            update_post_meta($post_id, '_origin_operator_name', sanitize_text_field($_POST['origin_operator_name']));
        }
        if (isset($_POST['destination_operator_name'])) {
            update_post_meta($post_id, '_destination_operator_name', sanitize_text_field($_POST['destination_operator_name']));
        }
        if (isset($_POST['postal_category_code'])) {
            update_post_meta($post_id, '_postal_category_code', sanitize_text_field($_POST['postal_category_code']));
        }
        if (isset($_POST['service_subclass_code'])) {
            update_post_meta($post_id, '_service_subclass_code', sanitize_text_field($_POST['service_subclass_code']));
        }
        if (isset($_POST['unit_type'])) {
            update_post_meta($post_id, '_unit_type', sanitize_text_field($_POST['unit_type']));
        }
        if (isset($_POST['triage_group'])) {
            update_post_meta($post_id, '_triage_group', sanitize_text_field($_POST['triage_group']));
        }
        if (isset($_POST['package_ids'])) {            
            $tracking_codes = [];
            foreach ($_POST['package_ids'] as $package_id) {
                $correios_tracking_code = get_post_meta($package_id, '_correios_tracking_code', true);
                $tracking_codes[] = $correios_tracking_code;
                update_post_meta($package_id, '_container_id', $post_id);
            }
            update_post_meta($post_id, '_tracking_codes', $tracking_codes);
            
            $this->create_unit($post_id);
        }
    }

    private function create_unit($post_id)
    {
        $dispatch_number = get_post_meta($post_id, '_dispatch_number', true);
        $origin_country = get_post_meta($post_id, '_origin_country', true);
        $origin_operator_name = get_post_meta($post_id, '_origin_operator_name', true);
        $destination_operator_name = get_post_meta($post_id, '_destination_operator_name', true);
        $postal_category_code = get_post_meta($post_id, '_postal_category_code', true);
        $service_subclass_code = get_post_meta($post_id, '_service_subclass_code', true);
        $unit_type = get_post_meta($post_id, '_unit_type', true);
        $tracking_codes = get_post_meta($post_id, '_tracking_codes', true) ?: array();

        $unit_data = [
            'dispatchNumber' => $dispatch_number,
            'originCountry' => $origin_country ?: 'US',
            'originOperatorName' => $origin_operator_name,
            'destinationOperatorName' => $destination_operator_name ?: 'CWBA',
            'postalCategoryCode' => $postal_category_code ?: 'A',
            'serviceSubclassCode' => $service_subclass_code ?: 'NX',
            'unitList' => [
                [
                    'sequence' => 1,
                    'unitType' => $unit_type ?: '2',
                    'trackingNumbers' => $tracking_codes,
                ]
            ]
        ];

        try {
            $correios_service = new WPR_Correios_Service();
            update_post_meta($post_id, '_debug_request_body', $unit_data);
            $response = $correios_service->create_unit($unit_data);
            if ($response) {
                update_post_meta($post_id, '_debug_response_body', $response);
                update_post_meta($post_id, '_unit_code', $response[0]->unitCode);
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            update_post_meta($post_id, '_debug_error_message', $error_message);
            set_transient('package_request_errors', $error_message, 5);
        }
    }

    private function generate_pdf($post_id) {
        if (!is_user_logged_in()) {
            wp_die('Você precisa estar logado para gerar o PDF.');
        }

        $correios_packet_groups = $this->get_packet_groups();
        $selected_packet_group_number = get_post_meta($post_id, '_triage_group', true) ?: '1';
        $selected_packet_group = $correios_packet_groups['packet_standard_grupo_' . $selected_packet_group_number];

        $bill_id = get_post_meta($post_id, '_bill_id', true);
        $departure_id = get_post_meta($bill_id, '_departure_id', true);

        $departure_status = get_post_meta($departure_id, '_departure_status', true);
        $flight_list = get_post_meta($departure_id, '_flight_list', true);
        $cn38_code_list = get_post_meta($departure_id, '_cn38_code_list', true);
        $flight_number = isset($flight_list['flightNumber']) ? $flight_list['flightNumber'] : null;
        $airline_code = isset($flight_list['airlineCode']) ? $flight_list['airlineCode'] : null;
        $departure_date = isset($flight_list['departureDate']) ? $flight_list['departureDate'] : null;
        $departure_airport_code = isset($flight_list['departureAirportCode']) ? $flight_list['departureAirportCode'] : null;
        $arrival_date = isset($flight_list['arrivalDate']) ? $flight_list['arrivalDate'] : null;
        $arrival_airport_code = isset($flight_list['arrivalAirportCode']) ? $flight_list['arrivalAirportCode'] : null;
        
        $cn38_code = get_post_meta($bill_id, '_cn38_code', true);
        $serial_number = get_post_meta($post_id, '_dispatch_number', true);
        $origin_country = get_post_meta($post_id, '_origin_country', true);
        $origin_operator_name = get_post_meta($post_id, '_origin_operator_name', true);
        $destination_operator_name = get_post_meta($post_id, '_destination_operator_name', true);
        $postal_category_code = get_post_meta($post_id, '_postal_category_code', true);
        $service_subclass_code = get_post_meta($post_id, '_service_subclass_code', true);
        $unit_type = get_post_meta($post_id, '_unit_type', true);
        $unit_code = get_post_meta($post_id, '_unit_code', true);
        $awb = get_post_meta($post_id, '_awb', true);

        $args = [
            'post_type'  => 'package',
            'meta_query' => [
                [
                    'key'     => '_container_id',
                    'value'   => $post_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
        ];

        $packages = [];
        $total_weight = 0;
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $package_id = get_the_ID();
                $tracking_code = get_post_meta($package_id, '_correios_tracking_code', true);
                $total_weight += get_post_meta($package_id, '_total_weight', true);

                $packages[] = [
                    'id'            => $package_id,
                    'tracking_code' => $tracking_code,
                ];
            }
        }
        wp_reset_postdata();
        $tax_payment_method = get_post_meta($packages[0]['id'], '_tax_payment_method', true);
        
        $service_subclass_code = get_post_meta($post_id, '_service_subclass_code', true);
        $subclass_description = '';
        $subclass_image_path = '';
        if ($service_subclass_code == 'NX') {
            $subclass_description = 'PACKET STANDARD';
            $subclass_image_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/packet-standard.png';
        } elseif ($service_subclass_code == 'IX') {
            $subclass_description = 'PACKET EXPRESS';
            $subclass_image_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/packet-express.png';
        }
        
        $logo_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/logo-transamerica.png';
        $correios_logo_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/logo-correios.png';
    
        $barcode_generator = new DNS1D();
        $barcode_generator->setStorPath(__DIR__.'/cache/');
    
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">    
            <title>Etiqueta (Unitizador) - <?php echo $unit_code; ?></title>
            <style>
                @page { margin: 0px; }
                body { font-family: Arial, sans-serif; font-size: 8pt; }
                table { width: 118.5%; border-collapse: collapse; margin-bottom: 10px;   }
                td { height: auto; padding: 0.75mm; border: 1px solid #000; vertical-align: top; }
                p { margin: 0; }
                .center { vertical-align: center; }
                .block { position: relative; display: block; }

                .logo-container { width: 30mm; height: 20mm; }
                .logo-container img { width: 100%; }

                .header { position: relative; }
                .header .right { position: absolute; right: 10px; top: 10px; }
                .header .logo-container { width: 20mm; height: 20mm; display: inline-block; vertical-align: middle; }
                .header .logo-container img { width: 100%; display: inline-block; }
                .header .subclass-description { font-size: 15pt; display: inline-block; vertical-align: middle; }

                .barcode { text-align: center; font-size: 12px; padding: 5px 0; vertical-align: middle; }
                .barcode img { width: 120mm; height: 18mm; margin-bottom: 10px; }
                .bold { font-weight: bold; }
                .en { font-size: 7pt; color: #555; }

                .group { position: relative; line-height: 16px; margin: 0; padding: 10px; }
                .group .right { position: absolute; right: -4px; top: 42px; }
                .group .right p { padding: 15px 60px; font-size: 20pt; color: white; background-color: black; }
                .service { padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td style="width: 30mm;">
                        <div class="logo-container">
                            <img src="<?php echo image_to_base64($logo_path); ?>" alt="Logo">
                        </div>
                    </td>
                    <td colspan="2" class="header">
                        <div class="left">
                            <div class="logo-container">
                                <img src="<?php echo image_to_base64($correios_logo_path); ?>" alt=" ">
                            </div>
                        </div>                        
                        <div class="right">
                            <div class="logo-container">
                                <img src="<?php echo image_to_base64($subclass_image_path); ?>" alt=" ">
                            </div>
                            <span class="subclass-description bold"><?php echo $subclass_description; ?></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">N° do Despacho<br><span class="en">(Dispatch N°)</span></p>
                        <p><?php echo $cn38_code; ?></p>
                    </td>
                    <td colspan="2" class="bold">
                        <div class="group">
                            <div class="left">
                                <p><?php echo $selected_packet_group["company"]; ?></p>
                                <p><?php echo $selected_packet_group["center"]; ?></p>
                                <p><?php echo $selected_packet_group["location"]; ?></p>
                                <p>CEP: <?php echo $selected_packet_group["zipcode"]; ?> - CNPJ: <?php echo $selected_packet_group["cnpj"]; ?></p>
                            </div>
                            <div class="right">
                                <p><?php echo $selected_packet_group_number; ?></p>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">N° Serial da Mala <span class="en">(Receptacle Serial Number)</span></p>
                        <p><?php echo $serial_number; ?></p>
                    </td>
                    <td>
                        <p class="bold">N° Voo <span class="en">(Flight Number)</span></p>
                        <p><?php echo $flight_number; ?></p>
                    </td>
                    <td>
                        <p class="bold">N° AWB <span class="en">(AWB#)</span></p>
                        <p><?php echo $awb; ?></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">Data do Despacho<br><span class="en">(Date)</span></p>
                        <p><?php echo date_i18n('d/m/Y', strtotime($departure_date)); ?></p>
                    </td>
                    <td>
                        <p class="bold">Aeroporto de Origem <span class="en">(Airport of Departure)</span></p>
                        <p><?php echo $departure_airport_code; ?></p>
                    </td>
                    <td>
                        <p class="bold">Aeroporto de Destino <span class="en">(Airport of Offloading)</span></p>
                        <p><?php echo $arrival_airport_code; ?></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">Quantidade de Itens<br><span class="en">(Quantity)</span></p>
                        <p><?php echo count($packages); ?></p>
                    </td>
                    <td rowspan="3" colspan="2" class="barcode">
                        <div>
                            <img src="data:image/png;base64,<?php echo $barcode_generator->getBarcodePNG($unit_code, 'C128'); ?>" alt=" ">
                            <p class="bold"><?php echo $unit_code; ?></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">Peso Kg<br><span class="en">(Weight Kg)</span></p>
                        <p><?php echo number_format($total_weight / 1000, 2); ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="service">
                        <p class="bold">Service</p>
                        <p><?php echo $tax_payment_method; ?></p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    
        $html = ob_get_clean();
        $dompdf->setPaper(array(0, 0, 623.6, 311.8));
        $dompdf->loadHtml($html);
        $dompdf->render();
        
        $dompdf->stream("etiqueta_unitizador_$unit_code.pdf", array("Attachment" => false));
        exit;
    }
    
    public function on_container_delete($post_id)
    {
        if (get_post_type($post_id) !== 'container') {
            return;
        }

        $bill_id = get_post_meta($post_id, '_bill_id', true);
        if (!empty($bill_id)) {
            $error_message = 'Não é possível deletar o container, pois ele está associado a uma fatura.';
            set_transient('package_request_errors', $error_message, 5);
            wp_redirect(admin_url('edit.php?post_status=trash&post_type=container'));
            exit();
        }

        $unit_code = get_post_meta($post_id, '_unit_code', 1);
        if ($unit_code) {
            $correios_service = new WPR_Correios_Service();
            try {
                $correios_service->cancel_unit($unit_code);
            } catch (Exception $e) {
                $error_message = $e;
                set_transient('package_request_errors', $error_message, 5);
                wp_redirect(admin_url('edit.php?post_status=trash&post_type=container'));
                exit();
            }
        }

        $tracking_codes = get_post_meta($post_id, '_tracking_codes', true) ?: [];
        foreach ($tracking_codes as $tracking_code) {
            $package_id = $this->get_package_id_by_tracking_code($tracking_code);
            if ($package_id) {
                delete_post_meta($package_id, '_container_id');
            }
        }
    }

    public function remove_delete_action($actions, $post) {
        if ($post->post_type === 'container') {
            if (get_post_meta($post->ID, '_bill_id', true)) {
                unset($actions['delete']);
            }
        }
        return $actions;
    }

    private function get_package_id_by_tracking_code($tracking_code)
    {
        $query = new WP_Query([
            'post_type'  => 'package',
            'meta_key'   => '_correios_tracking_code',
            'meta_value' => $tracking_code,
            'fields'     => 'ids',
            'posts_per_page' => 1,
        ]);

        return $query->have_posts() ? $query->posts[0] : null;
    }

    private function get_packet_groups() {
        return [
            "packet_express" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional do Rio de Janeiro – SE/RJ",
                "location" => "Ponta do Galeão, s/n, 2º andar – TECA Correios",
                "city" => "Galeão",
                "zipcode" => "21941-974",
                "region" => "Ilha do Governador, Rio de Janeiro/RJ",
                "cnpj" => "34.028.316/7189-93"
            ],
            "packet_standard_grupo_1" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de São Paulo – SE/SPM",
                "location" => "Rua Mergenthaler, 568, bloco III, 5º andar, Vila Leopoldina",
                "zipcode" => "05311-900",
                "region" => "São Paulo/SP",
                "cnpj" => "34.028.316/7105-85"
            ],
            "packet_standard_grupo_2" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional em Valinhos - SE/SPI",
                "location" => "Rua Clark, 3401, Macuco",
                "zipcode" => "13279-400",
                "region" => "Valinhos/SP",
                "cnpj" => "34.028.316/9395-74"
            ],
            "packet_standard_grupo_3" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional do Rio de Janeiro – SE/RJ",
                "location" => "Ponta do Galeão, s/n, 2º andar – TECA Correios",
                "city" => "Galeão",
                "zipcode" => "21941-974",
                "region" => "Ilha do Governador, Rio de Janeiro/RJ",
                "cnpj" => "34.028.316/7189-93"
            ],
            "packet_standard_grupo_4" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de Curitiba - SE/PR",
                "location" => "Rua Salgado Filho, 476, Jardim Amélia",
                "zipcode" => "83330-972",
                "region" => "Pinhais/PR",
                "cnpj" => "34.028.316/9148-22"
            ],
            "packet_standard_grupo_5" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de Curitiba - SE/PR",
                "location" => "Rua Salgado Filho, 476, Jardim Amélia",
                "zipcode" => "83330-972",
                "region" => "Pinhais/PR",
                "cnpj" => "34.028.316/9148-22"
            ]
        ];        
    }
}

new WPR_Container();
