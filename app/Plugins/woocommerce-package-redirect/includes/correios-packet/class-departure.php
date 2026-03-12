<?php

class WPR_Departure
{
    public function __construct()
    {
        add_action('init', [$this, 'register_departure_post_type']);
        add_filter('manage_departure_posts_columns', [$this, 'add_departure_columns']);
        add_action('manage_departure_posts_custom_column', [$this, 'render_departure_columns'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_departure_meta_boxes']);
        add_action('save_post', [$this, 'save_departure_meta']);
    }

    public function register_departure_post_type()
    {
        $labels = array(
            'name'                  => 'Embarques',
            'singular_name'         => 'Embarque',
            'menu_name'             => 'Embarques',
            'name_admin_bar'        => 'Embarque',
            'add_new'               => 'Adicionar Novo',
            'add_new_item'          => 'Adicionar Novo Embarque',
            'new_item'              => 'Novo Embarque',
            'edit_item'             => 'Editar Embarque',
            'view_item'             => 'Ver Embarque',
            'all_items'             => 'Embarques',
            'search_items'          => 'Procurar Embarques',
            'not_found'             => 'Nenhum embarque encontrado.',
            'not_found_in_trash'    => 'Nenhum embarque encontrado na lixeira.',
            'archives'              => 'Arquivos de Embarques',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'envios',
            'query_var'          => false,
            'rewrite'            => array('slug' => 'departure'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array(''),
        );

        register_post_type('departure', $args);
    }

    public function add_departure_columns($columns)
    {
        unset($columns['title']);
        unset($columns['date']);

        $columns['flight_number'] = 'Número do Voo';
        $columns['airline_code'] = 'Companhia Aérea';
        $columns['departure_date'] = 'Data de Partida';
        $columns['arrival_date'] = 'Data de Chegada';
        $columns['departure_airport_code'] = 'Aeroporto de Partida';
        $columns['arrival_airport_code'] = 'Aeroporto de Chegada';
        $columns['status'] = 'Status';
        $columns['date'] = 'Data';

        return $columns;
    }

    public function render_departure_columns($column, $post_id)
    {
        $flight_list = get_post_meta($post_id, '_flight_list', true);
        $departure_status = get_post_meta($post_id, '_departure_status', true);

        switch ($column) {
            case 'flight_number':
                echo esc_html($flight_list['flight_number'] ?? '');
                break;
            case 'airline_code':
                echo esc_html($flight_list['airline_code'] ?? '');
                break;
            case 'departure_date':
                $departure_date = $flight_list['departure_date'] ?? '';
                echo $departure_date ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($departure_date))) : '';
                break;
            case 'arrival_date':
                $arrival_date = $flight_list['arrival_date'] ?? '';
                echo $arrival_date ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($arrival_date))) : '';
                break;
            case 'departure_airport_code':
                echo esc_html($flight_list['departure_airport_code'] ?? '');
                break;
            case 'arrival_airport_code':
                echo esc_html($flight_list['arrival_airport_code'] ?? '');
                break;
            case 'status':
                echo $departure_status === 'confirmed' 
                    ? '<span style="color: green; font-weight: bold;">Confirmado</span>' 
                    : '<span style="color: red; font-weight: bold;">Erro</span>';
                break;
        }
    }

    public function add_departure_meta_boxes()
    {
        add_meta_box(
            'departure_details',
            'Detalhes do Embarque',
            [$this, 'render_departure_meta_box'],
            'departure',
            'normal',
            'high'
        );
    }
    public function render_departure_meta_box($post)
    {
        $debug_mode = get_option('wpr_correios_debug_mode', false);

        $correios_service = new WPR_Correios_Service();
        $airlines_list = $correios_service->get_airline_list();
    
        $cn38_code_list = get_post_meta($post->ID, '_cn38_code_list', true) ?: array();
        $flight_list = get_post_meta($post->ID, '_flight_list', true) ?: array();
    
        $departure_confirmed = get_post_meta($post->ID, '_departure_status', true);
        $readonly = $departure_confirmed ? 'readonly' : '';
        $disabled = $departure_confirmed ? 'disabled' : '';
    
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
                <th><label for="flight_number">Número do Voo*</label></th>
                <td>
                    <input type="number" id="flight_number" name="flight_number" value="<?php echo esc_attr(isset($flight_list['flightNumber']) ? $flight_list['flightNumber'] : ''); ?>" max="999999" required <?php echo $readonly; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="airline_code">Código da Companhia Aérea*</label></th>
                <td>
                    <select id="airline_code" name="airline_code" required <?php echo $disabled; ?>>
                        <option value="">Selecione uma companhia aérea</option>
                        <?php foreach ($airlines_list as $airline): ?>
                            <option value="<?php echo esc_attr($airline->code); ?>" <?php selected(isset($flight_list['airlineCode']) ? $flight_list['airlineCode'] : '', $airline->code); ?>>
                                <?php echo esc_html($airline->name) . ' (' . esc_html($airline->code) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="departure_date">Data de Partida*</label></th>
                <td>
                    <input 
                        type="datetime-local" 
                        id="departure_date" 
                        name="departure_date" 
                        value="<?php echo esc_attr(isset($flight_list['departureDate']) ? date('Y-m-d\TH:i', strtotime($flight_list['departureDate'])) : ''); ?>" 
                        required 
                        <?php echo $readonly; ?> 
                    />
                </td>
            </tr>
            <tr>
                <th><label for="departure_airport_code">Código do Aeroporto de Partida*</label></th>
                <td>
                    <input type="text" id="departure_airport_code" name="departure_airport_code" value="<?php echo esc_attr(isset($flight_list['departureAirportCode']) ? $flight_list['departureAirportCode'] : ''); ?>" minlength="2" maxlength="3" required <?php echo $readonly; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="arrival_date">Data de Chegada*</label></th>
                <td>
                    <input 
                        type="datetime-local" 
                        id="arrival_date" 
                        name="arrival_date" 
                        value="<?php echo esc_attr(isset($flight_list['arrivalDate']) ? date('Y-m-d\TH:i', strtotime($flight_list['arrivalDate'])) : ''); ?>" 
                        required 
                        <?php echo $readonly; ?> 
                    />
                </td>
            </tr>
            <tr>
                <th><label for="arrival_airport_code">Código do Aeroporto de Chegada*</label></th>
                <td>
                    <input type="text" id="arrival_airport_code" name="arrival_airport_code" value="<?php echo esc_attr(isset($flight_list['arrivalAirportCode']) ? $flight_list['arrivalAirportCode'] : ''); ?>" minlength="2" maxlength="3" required <?php echo $readonly; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="bill_ids">Códigos de Fatura (CN38)*</label></th>
                <td>
                    <select id="bill_ids" name="bill_ids[]" multiple required <?php echo $disabled; ?>>
                        <?php
                        $meta_query = array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_departure_id',
                                'compare' => 'NOT EXISTS',
                            ),
                        );
                        if (!empty($cn38_code_list)) {
                            $meta_query[] = array(
                                'key'     => '_cn38_code',
                                'value'   => $cn38_code_list,
                                'compare' => 'IN',
                            );
                        }
    
                        $bills = get_posts(array(
                            'post_type'      => 'bill',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                            'meta_query'     => $meta_query,
                        ));
    
                        if (!empty($bills)) {
                            foreach ($bills as $bill) {
                                $bill_id = $bill->ID;
                                $cn38_code = get_post_meta($bill_id, '_cn38_code', true);
                                $selected = in_array($cn38_code, $cn38_code_list) ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($bill_id); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($cn38_code); ?>
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
                    <p class="description">Segure Ctrl para selecionar múltiplos códigos de fatura.</p>
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
        <?php
    }    

    public function save_departure_meta($post_id)
    {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'departure') {
            return;
        }

        $departure_confirmed = get_post_meta($post_id, '_departure_status', 1);
        if ($departure_confirmed) {
            return;
        }

        $flight_list = array();
        
        if (isset($_POST['flight_number'])) {
            $flight_list['flightNumber'] = intval($_POST['flight_number']);
        }

        if (isset($_POST['airline_code'])) {
            $flight_list['airlineCode'] = sanitize_text_field($_POST['airline_code']);
        }

        if (isset($_POST['departure_date'])) {
            $flight_list['departureDate'] = gmdate('Y-m-d\TH:i:s\Z', strtotime(sanitize_text_field($_POST['departure_date'])));
        }

        if (isset($_POST['departure_airport_code'])) {
            $flight_list['departureAirportCode'] = strtoupper(sanitize_text_field($_POST['departure_airport_code']));
        }

        if (isset($_POST['arrival_date'])) {
            $flight_list['arrivalDate'] = gmdate('Y-m-d\TH:i:s\Z', strtotime(sanitize_text_field($_POST['arrival_date'])));
        }

        if (isset($_POST['arrival_airport_code'])) {
            $flight_list['arrivalAirportCode'] = strtoupper(sanitize_text_field($_POST['arrival_airport_code']));
        }

        if (!empty($flight_list)) {
            update_post_meta($post_id, '_flight_list', $flight_list);
        }

        if (isset($_POST['bill_ids'])) {
            $cn38_code_list = [];
            foreach ($_POST['bill_ids'] as $bill_id) {
                $cn38_code = get_post_meta($bill_id, '_cn38_code', true);
                $cn38_code_list[] = $cn38_code;
                update_post_meta($bill_id, '_departure_id', $post_id);
            }
            update_post_meta($post_id, '_cn38_code_list', $cn38_code_list);

            $this->confirm_departure($post_id);
        }
    }

    public function confirm_departure($post_id)
    {
        $cn38_code_list = get_post_meta($post_id, '_cn38_code_list', true);
        $flight_list = get_post_meta($post_id, '_flight_list', true);

        $departure_data = [
            'cn38CodeList' => $cn38_code_list,
            'flightList'   => array($flight_list),
        ];

        try {
            $correios_service = new WPR_Correios_Service();
            update_post_meta($post_id, '_debug_request_body', $departure_data);
            $response = $correios_service->confirm_departure($departure_data);
            if($response) {
                update_post_meta($post_id, '_debug_response_body', $response);
                update_post_meta($post_id, '_departure_status', 'confirmed');
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            update_post_meta($post_id, '_debug_error_message', $error_message);
            set_transient('departure_request_errors', $error_message, 60 * 5);
        }
    }

}

new WPR_Departure();