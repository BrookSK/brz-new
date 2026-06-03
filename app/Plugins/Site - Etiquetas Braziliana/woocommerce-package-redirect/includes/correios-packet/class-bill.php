<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class WPR_Bill
{
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_post_type']);
        add_action('add_meta_boxes', [$this, 'add_bill_meta_boxes']);
        add_action('save_post', [$this, 'save_bill_meta']);
        add_filter('manage_bill_posts_columns', [$this, 'set_bill_columns']);
        add_action('manage_bill_posts_custom_column', [$this, 'populate_bill_columns'], 10, 2);
        add_action('before_delete_post', [$this, 'on_bill_delete']);
        add_filter('post_row_actions', [$this, 'remove_delete_action'], 10, 2);
    }

    public function set_bill_columns($columns)
    {
        unset($columns['title']);
        unset($columns['date']);

        $columns['cn38_code'] = __('Código da Fatura', 'wpr');
        $columns['departure_id'] = __('Embarque', 'wpr');
        $columns['date'] = __('Data', 'wpr');

        return $columns;
    }

    public function populate_bill_columns($column, $post_id)
    {
        switch ($column) {
            case 'cn38_code':
                $cn38_code = get_post_meta($post_id, '_cn38_code', true);
                if ($cn38_code) {
                    echo esc_html($cn38_code);
                } else {
                    echo '<br><span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                }
                break;
        
            case 'departure_id':
                $departure_id = get_post_meta($post_id, '_departure_id', true);
                if ($departure_id) {
                    echo '<a target="_blank" href="' . esc_url(admin_url('post.php?post=' . $departure_id . '&action=edit')) . '">' . esc_html($departure_id) . '</a>';
                } else {
                    echo '<br><span style="color: red;">' . __('N/A', 'text-domain') . '</span>';
                }
                break;
        }        
    }


    public function register_custom_post_type()
    {
        $labels = array(
            'name'                  => 'Faturas',
            'singular_name'         => 'Fatura',
            'menu_name'             => 'Faturas',
            'name_admin_bar'        => 'Fatura',
            'add_new'               => 'Adicionar Nova',
            'add_new_item'          => 'Adicionar Nova Fatura',
            'new_item'              => 'Nova Fatura',
            'edit_item'             => 'Editar Fatura',
            'view_item'             => 'Ver Fatura',
            'all_items'             => 'Faturas',
            'search_items'          => 'Procurar Faturas',
            'not_found'             => 'Nenhuma fatura encontrada.',
            'not_found_in_trash'    => 'Nenhuma fatura encontrada na lixeira.',
            'archives'              => 'Arquivos de Faturas',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'envios',
            'query_var'          => false,
            'rewrite'            => array('slug' => 'bill'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array(''),
        );

        register_post_type('bill', $args);
    }

    public function add_bill_meta_boxes()
    {
        add_meta_box(
            'bill_details',
            'Detalhes da Fatura',
            [$this, 'render_bill_meta_box'],
            'bill',
            'normal',
            'high'
        );
    }

    public function render_bill_meta_box($post)
    {
        $debug_mode = get_option('wpr_correios_debug_mode', false);

        $departure_id = get_post_meta($post->ID, '_departure_id', true);

        $dispatch_numbers = get_post_meta($post->ID, '_dispatch_numbers', true) ?: array();
        $cn38_code = get_post_meta($post->ID, '_cn38_code', true);
        $disabled = $cn38_code ? 'disabled' : '';

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

        <?php if (!empty($departure_id)) : ?>
            <div>
                <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                <button type="button" class="button-secondary" id="generate_pdf">Gerar Etiqueta</button>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="dispatch_numbers">Números de Remessa*</label></th>
                <td>
                    <select id="container_ids" name="container_ids[]" multiple required <?php echo $disabled; ?>>
                        <?php
                        $meta_query = array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_bill_id',
                                'compare' => 'NOT EXISTS',
                            ),
                        );
                        if (!empty($dispatch_numbers)) {
                            $meta_query[] = array(
                                'key'     => '_dispatch_number',
                                'value'   => $dispatch_numbers,
                                'compare' => 'IN',
                            );
                        }

                        $containers = get_posts(array(
                            'post_type'      => 'container',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                            'meta_query'     => $meta_query,
                        ));

                        if (!empty($containers)) {
                            foreach ($containers as $container) {
                                $container_id = $container->ID;
                                $dispatch_number = get_post_meta($container_id, '_dispatch_number', true);
                                $selected = in_array($dispatch_number, $dispatch_numbers) ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($container_id); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($dispatch_number); ?>
                                </option>
                                <?php
                            }
                        } else {
                            ?>
                            <option value="">Nenhum container disponível</option>
                            <?php
                        }
                        ?>
                    </select>
                    <p class="description">Segure Ctrl para selecionar múltiplos números de remessa.</p>
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

        <p style="color: red; font-weight: bold;">
            Aviso: Esta operação é irreversível e acarretará em custos.
        </p>

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


    public function save_bill_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'bill') {
            return;
        }

        if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == '1') {
            $this->generate_pdf($_POST['post_id']);
            return;
        }

        if (isset($_POST['container_ids'])) {
            $dispatch_numbers = [];
            foreach ($_POST['container_ids'] as $container_id) {
                $dispatch_number = get_post_meta($container_id, '_dispatch_number', true);
                $dispatch_numbers[] = $dispatch_number;
                update_post_meta($container_id, '_bill_id', $post_id);
            }
            update_post_meta($post_id, '_dispatch_numbers', $dispatch_numbers);

            $this->create_bill($post_id);
        }
    }

    public function create_bill($post_id)
    {
        $dispatch_numbers = get_post_meta($post_id, '_dispatch_numbers', true) ?: array();

        $bill_data = [
            'dispatchNumbers' => $dispatch_numbers,
        ];

        try {
            $correios_service = new WPR_Correios_Service();
            $response = $correios_service->create_bill_async($bill_data);
            $request_id = $response->requestId;
            update_post_meta($post_id, '_debug_request_body', $bill_data);

            do {
                $status_response = $correios_service->check_bill_status($request_id);
                $status = $status_response->requestStatus;
            } while ($status == 'Processing');
            update_post_meta($post_id, '_debug_response_body', $status_response);

            if ($status === 'Error') {
                $error_message = sanitize_text_field($status_response->errorMessage);
                throw new Exception('Erro ao criar a fatura: ' . $error_message);
            }
            
            $cn38_code = $status_response->cn38Code;
            $current_date = current_time('mysql');
            update_post_meta($post_id, '_cn38_code', $cn38_code);
            update_post_meta($post_id, '_cn38_code_date', $current_date);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            update_post_meta($post_id, '_debug_error_message', $error_message);
            set_transient('package_request_errors', $error_message, 60 * 5);
        }
    }

    private function generate_pdf($post_id, $output_only = false) {
        if (!$output_only && !is_user_logged_in()) {
            wp_die('Você precisa estar logado para gerar o PDF.');
        }

        
        $departure_id = get_post_meta($post_id, '_departure_id', true);
        
        $departure_status = get_post_meta($departure_id, '_departure_status', true);
        $flight_list = get_post_meta($departure_id, '_flight_list', true);
        $cn38_code_list = get_post_meta($departure_id, '_cn38_code_list', true);
        $flight_number = isset($flight_list['flightNumber']) ? $flight_list['flightNumber'] : null;
        $airline_code = isset($flight_list['airlineCode']) ? $flight_list['airlineCode'] : null;
        $departure_date = isset($flight_list['departureDate']) ? $flight_list['departureDate'] : null;
        $departure_airport_code = isset($flight_list['departureAirportCode']) ? $flight_list['departureAirportCode'] : null;
        $arrival_date = isset($flight_list['arrivalDate']) ? $flight_list['arrivalDate'] : null;
        $arrival_airport_code = isset($flight_list['arrivalAirportCode']) ? $flight_list['arrivalAirportCode'] : null;
        
        $correios_service = new WPR_Correios_Service();
        $airlines_list = $correios_service->get_airline_list();
        $airline = $airline_code ? current(array_filter($airlines_list, fn($airline) => $airline->code == $airline_code)) : null;
        $airline_name = $airline->name;
        $cn38_code = get_post_meta($post_id, '_cn38_code', true);
        $cn38_code_date = get_post_meta($post_id, '_cn38_code_date', true);
        
        $args = [
            'post_type'  => 'container',
            'meta_query' => [
                [
                    'key'     => '_bill_id',
                    'value'   => $post_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
        ];
        
        $containers = [];
        $packages = [];
        $total_containers_weight = 0;
        
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $container_id = get_the_ID();

                $serial_number = get_post_meta($container_id, '_dispatch_number', true);
                $origin_country = get_post_meta($container_id, '_origin_country', true);
                $origin_operator_name = get_post_meta($container_id, '_origin_operator_name', true);
                $destination_operator_name = get_post_meta($container_id, '_destination_operator_name', true);
                $postal_category_code = get_post_meta($container_id, '_postal_category_code', true);
                $service_subclass_code = get_post_meta($container_id, '_service_subclass_code', true);
                $unit_type = get_post_meta($container_id, '_unit_type', true);
                $unit_code = get_post_meta($container_id, '_unit_code', true);
                
                $package_args = [
                    'post_type'  => 'package',
                    'meta_query' => [
                        [
                            'key'     => '_container_id',
                            'value'   => $container_id,
                            'compare' => '='
                            ]
                        ],
                        'posts_per_page' => -1,
                    ];
                    
                $total_weight = 0;
                $package_query = new WP_Query($package_args);
                if ($package_query->have_posts()) {
                    while ($package_query->have_posts()) {
                        $package_query->the_post();
                        $package_id = get_the_ID();
                        $total_weight += get_post_meta($package_id, '_total_weight', true);

                        $packages[] = [
                            'id' => $package_id,
                        ];
                    }
                }
                wp_reset_postdata();

                $containers[] = [
                    'id' => $container_id,
                    'serial_number' => $serial_number,
                    'origin_country' => $origin_country,
                    'origin_operator_name' => $origin_operator_name,
                    'destination_operator_name' => $destination_operator_name,
                    'postal_category_code' => $postal_category_code,
                    'service_subclass_code' => $service_subclass_code,
                    'unit_type' => $unit_type,
                    'unit_code' => $unit_code,
                    'total_weight' => $total_weight,
                ];
                $total_containers_weight += $total_weight;
            }
        }
        wp_reset_postdata();

        $container_id = $containers[0]['id'];
        $dispatch_number = get_post_meta($container_id, '_dispatch_number', true);
        $origin_country = get_post_meta($container_id, '_origin_country', true);
        $origin_operator_name = get_post_meta($container_id, '_origin_operator_name', true);
        $destination_operator_name = get_post_meta($container_id, '_destination_operator_name', true);
        $postal_category_code = get_post_meta($container_id, '_postal_category_code', true);
        $service_subclass_code = get_post_meta($container_id, '_service_subclass_code', true);
        $unit_type = get_post_meta($container_id, '_unit_type', true);
        $tracking_codes = get_post_meta($container_id, '_tracking_codes', true) ?: array();
        $subclass_description = $service_subclass_code == 'NX' ? 'PACKET STANDARD' : 'PACKET EXPRESS';

        $sender_contract = get_option('wpr_correios_sender_contract', '');
    
        $logo_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__), 1)) . 'assets/images/logo-transamerica.png';
    
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                @page { margin: 0px; width: 220mm; height: 110mm; }
                body { font-family: Arial, sans-serif; font-size: 8pt; padding: 4mm; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                td { padding: 5px; border: 1px solid #000; vertical-align: top; }
                p { margin: 0; }

                .bold { font-weight: bold; }
                .center { text-align: center; }
                .vertical-middle { vertical-align: middle; }
                .en { font-size: 7pt; color: #555; }
    
                .header { margin-bottom: 15px; text-align: center; }
                .logo-container { width: 20mm; }
                .logo-container img { width: 100%; }
            </style>
        </head>
        <body>
            <table>
                <!-- Header -->
                <tr class="header">
                    <td>
                        <div class="logo-container">
                            <img src="<?php echo image_to_base64($logo_path); ?>" alt="Logo">
                        </div>
                    </td>
                    <td>
                        <p class="bold">FATURA DE ENTREGA<br><span class="en">(Delivery Bill)</span></p>
                    </td>
                    <td>
                        <p class="bold">1 de 1</p>
                    </td>
                </tr>
            </table>
            <table>
                <!-- Main Info Section -->
                <tr>
                    <td rowspan="2" colspan="2">
                        <p class="bold">OPERADOR DE ORIGEM<br><span class="en">(Office of Origin)</span></p>
                        <p><?php echo $origin_operator_name; ?></p>
                    </td>
                    <td colspan="3">
                        <p class="bold">N° FATURA DE ENTREGA<br><span class="en">(Delivery Bill #)</span></p>
                        <p><?php echo $cn38_code; ?></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <p class="bold">N° DO CONTRATO<br><span class="en">(Contract Number)</span></p>
                        <p><?php echo $sender_contract; ?></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p class="bold">CIA AÉREA<br><span class="en">(Airline)</span></p>
                        <p><?php echo $airline_name; ?></p>
                    </td>
                    <td colspan="3">
                        <p class="bold">N° VOO<br><span class="en">(Flight #)</span></p>
                        <p><?php echo $flight_number; ?></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p class="bold">DATA<br><span class="en">(Date)</span></p>
                        <p><?php echo date_i18n('d/m/Y', strtotime($cn38_code_date)); ?></p>
                    </td>
                    <td colspan="3">
                        <p class="bold">HORA<br><span class="en">(Time)</span></p>
                        <p><?php echo date_i18n('H:i', strtotime($departure_date)); ?></p>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" colspan="2">
                        <p class="bold">DATA DE PARTIDA<br><span class="en">(Date of Departure)</span></p>
                        <p><?php echo date_i18n('d/m/Y', strtotime($departure_date)); ?></p>
                    </td>
                    <td style="position: relative; padding: 12px 5px;" colspan="3">
                        <p class="bold">MODALIDADE DE SERVIÇO<br><span class="en">(Service Modality)</span></p>
                        <div style="position: absolute; top: 4px; right: 5px;">
                            <p class="bold vertical-middle"><input class="vertical-middle" type="checkbox" <?php echo $unit_type == '2' ? "checked" : ""; ?>> Acima de 30 kg</p>
                            <p class="bold vertical-middle"><input class="vertical-middle" type="checkbox" <?php echo $unit_type == '1' ? "checked" : ""; ?>> Abaixo de 30 kg</p>
                        </div>
                        <p><?php echo $subclass_description; ?></p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 5px;" colspan="3">
                        <div style="display: inline-block; margin-right: 65%;">
                            <label class="bold vertical-middle">DDU<input style="margin-left: 10px;" class="vertical-middle" type="checkbox" checked></label>
                        </div>
                        <div style="display: inline-block;">
                            <label class="bold vertical-middle">PRC<input style="margin-left: 10px;" class="vertical-middle" type="checkbox"></label>
                        </div>
                    </td>
                </tr>

                <!-- Airport Section -->
                <tr>
                    <td class="center" colspan="2">
                        <p class="bold">AEROPORTO DE PARTIDA<br><span class="en">(Airport of Departure)</span></p>
                        <p><?php echo $departure_airport_code; ?></p>
                    </td>
                    <td class="center" colspan="2">
                        <p class="bold">AEROPORTO DE TRANSBORDO<br><span class="en">(Airport of Transshipment)</span></p>
                        <p></p>
                    </td>
                    <td class="center">
                        <p class="bold">AEROPORTO DE CHEGADA<br><span class="en">(Airport of Offloading)</span></p>
                        <p><?php echo $arrival_airport_code; ?></p>
                    </td>
                </tr>

                <!-- Dispatch Data Section -->
                <tr>
                    <td colspan="5" style="text-align: center;">
                        <p class="bold">DADOS DO DESPACHO<br><span class="en">(Dispatch Data)</span></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">N° DO DESPACHO<br><span class="en">(Dispatch #)</span></p>
                    </td>
                    <td>
                        <p class="bold">N° SERIAL DE MALA<br><span class="en">(Receptacle Serial #)</span></p>
                    </td>
                    <td>
                        <p class="bold">PESO BRUTO DA MALA kg<br><span class="en">(Gross Weight of Bags kg)</span></p>
                    </td>
                    <td>
                        <p class="bold">N° DO LACRE DA MALA<br><span class="en">(Bag/Box Seal #)</span></p>
                    </td>
                    <td>
                        <p class="bold">OBSERVAÇÕES<br><span class="en">(Observations)</span></p>
                    </td>
                </tr>

                <?php for ($i = 0; $i < 12; $i++) : ?>
                    <?php if (isset($containers[$i])) : ?>
                    <tr>
                        <td>
                            <p><?php echo $cn38_code; ?></p>
                        </td>
                        <td>
                            <p><?php echo $containers[$i]['serial_number']; ?></p>
                        </td>
                        <td>
                            <p><?php echo number_format($containers[$i]['total_weight'] / 1000, 2); ?></p>
                        </td>
                        <td>
                            <p><?php echo $containers[$i]['unit_code']; ?></p>
                        </td>
                        <td>
                            <p></p>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <td><br></td>
                        <td><br></td>
                        <td><br></td>
                        <td><br></td>
                        <td><br></td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>

                <tr>
                    <td>
                        <p class="bold">SUBTOTAL</p>
                    </td>
                    <td>
                        <p><?php echo count($containers); ?></p>
                    </td>
                    <td>
                        <p><?php echo number_format($total_containers_weight / 1000, 2); ?></p>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td>
                        <p class="bold">TOTAL</p>
                    </td>
                    <td>
                        <p><?php echo count($containers); ?></p>
                    </td>
                    <td>
                        <p><?php echo number_format($total_containers_weight / 1000, 2); ?></p>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            </table>

            <!-- Signatures Section -->
            <table>
                <tr>
                    <td colspan="3" style="text-align: center;">
                        <p class="bold">ASSINATURA DOS OPERADORES<br><span class="en">(Signature)</span></p>
                    </td>
                </tr>
                <tr>
                    <td style="padding-bottom: 60px;">
                        <p class="bold">OPERADOR DE ORIGEM<br><span class="en">(Dispatching Office of Exchange)</span></p>
                    </td>
                    <td>
                        <p class="bold">TRANSPORTADOR<br><span class="en">(The Official of the Carrier or Airport)</span></p>
                    </td>
                    <td>
                        <p class="bold">OPERADOR DE DESTINO<br><span class="en">(Office of Exchange of Destination)</span></p>
                    </td>
                </tr>
            </table>
        </body>
</html>
        <?php
    
        $html = ob_get_clean();
        $dompdf->setPaper(array(0, 0, 595.3, 841.9));
        $dompdf->loadHtml($html);
        $dompdf->render();

        if ($output_only) {
            return $dompdf->output();
        }
        
        $dompdf->stream("fatura_de_entrega.pdf", array("Attachment" => false));
        exit;
    }

    /**
     * Gera o PDF da fatura e retorna como string binária.
     */
    public function generate_pdf_output($post_id) {
        return $this->generate_pdf($post_id, true);
    }

    public function on_bill_delete($post_id)
    {
        if (get_post_type($post_id) !== 'bill') {
            return;
        }

        $cn38_code = get_post_meta($post_id, '_cn38_code', true);
        if (!empty($cn38_code)) {
            $error_message = 'Não é possível deletar uma fatura já criada.';
            set_transient('package_request_errors', $error_message, 5);
            wp_redirect(admin_url('edit.php?post_status=trash&post_type=bill'));
            exit();
        }
    }

    public function remove_delete_action($actions, $post) {
        if ($post->post_type === 'bill') {
            if (get_post_meta($post->ID, '_cn38_code', true)) {
                unset($actions['delete']);
            }
        }
        return $actions;
    }
}

new WPR_Bill();
