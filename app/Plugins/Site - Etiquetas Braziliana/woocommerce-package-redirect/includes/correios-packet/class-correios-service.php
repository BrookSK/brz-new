<?php

class WPR_Correios_Service {
    private $numero_cartao_postagem;
    private $username;
    private $password;
    private $token;
    private $base_url = 'https://api.correios.com.br';
    private $test_url = 'https://apihom.correios.com.br';
    private $test_mode;
    private $url;

    public function __construct() {
        $this->numero_cartao_postagem = get_option('wpr_correios_numero');
        $this->username = get_option('wpr_correios_username');
        $this->password = get_option('wpr_correios_password');
        $this->test_mode = get_option('wpr_correios_test_mode', '0') === '1';
        $this->url = $this->test_mode ? $this->test_url : $this->base_url;

        $this->token = $this->get_token();
    }

    private function get_token() {
        $url = $this->url . '/token/v1/autentica/cartaopostagem';
        $payload = json_encode(['numero' => $this->numero_cartao_postagem]);

        $response = $this->post($url, $payload, [
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json',
        ]);

        if ($response && isset($response->token)) {
            return $response->token;
        }
        
        $error_msg = 'Erro ao obter o token dos Correios';
        if ($response && isset($response->msgs) && is_array($response->msgs)) {
            $error_msg .= ': ' . implode("; ", $response->msgs);
        } elseif ($response && isset($response->message)) {
            $error_msg .= ': ' . $response->message;
        }
        throw new Exception($error_msg);
    }

    public function get_tracking_numbers_balance() {
        $url = $this->url . '/packet/v1/packages/tracking-numbers/balance';
        $response = $this->get($url);

        if ($response) {
            return $response;
        } else {
            throw new Exception('Erro ao recuperar o saldo de códigos de rastreio');
        }
    }

    public function create_package($package_data) {
        $url = $this->url . '/packet/v1/packages';
        try {
            $response = $this->post($url, json_encode($package_data));
        } catch (Exception $e) {
            throw $e;
        }
        
        if ($response && isset($response->packageResponseList)) {
            return $response->packageResponseList;
        }
        
        $error_msg = 'Erro ao criar o pacote';
        if ($response && isset($response->msgs) && is_array($response->msgs)) {
            $error_msg .= ': ' . implode("; ", $response->msgs);
        } elseif ($response && isset($response->message)) {
            $error_msg .= ': ' . $response->message;
        }
        throw new Exception($error_msg);
    }

    public function create_unit($unit_data) {
        $url = $this->url . '/packet/v1/units';
        $response = $this->post($url, json_encode($unit_data));

        if ($response && isset($response->unitResponseList)) {
            return $response->unitResponseList;
        }
        
        $error_msg = 'Erro ao criar o unitizador';
        if ($response && isset($response->msgs) && is_array($response->msgs)) {
            $error_msg .= ': ' . implode("; ", $response->msgs);
        } elseif ($response && isset($response->message)) {
            $error_msg .= ': ' . $response->message;
        }
        throw new Exception($error_msg);
    }

    public function cancel_unit($unit_code) {
        $url = $this->url . '/packet/v1/units/';
        $response = $this->delete($url . $unit_code);

        // API dos Correios retorna "1" em caso de sucesso no cancelamento
        // e um objeto com msgs em caso de erro
        if ($response === false) {
            throw new Exception('Erro de conexão ao cancelar o unitizador');
        }
        
        // Se resposta é um inteiro (1 = sucesso), retornar silenciosamente
        if (is_numeric($response)) {
            return; // Cancelamento bem-sucedido
        }
        
        // Se é um objeto com msgs, é erro
        if (is_object($response) && isset($response->msgs)) {
            throw new Exception('Erro ao cancelar o unitizador: ' . implode("; ", $response->msgs));
        }
        
        // Qualquer outro caso com mensagem de erro
        if (is_object($response) && isset($response->message)) {
            throw new Exception('Erro ao cancelar o unitizador: ' . $response->message);
        }
    }

    public function create_bill_async($bill_data) {
        $url = $this->url . '/packet/v1/cn38request';
        $response = $this->post($url, json_encode($bill_data));

        if ($response && isset($response->requestId)) {
            $status_response = $this->check_bill_status($response->requestId);
            return $status_response;
        }
        
        $error_msg = 'Erro ao criar a fatura';
        if ($response && isset($response->msgs) && is_array($response->msgs)) {
            $error_msg .= ': ' . implode("; ", $response->msgs);
        } elseif ($response && isset($response->message)) {
            $error_msg .= ': ' . $response->message;
        }
        throw new Exception($error_msg);
    }

    function check_bill_status($request_id) {
        $url = $this->url . '/packet/v1/cn38request?requestId=' . $request_id;
        $response = $this->get($url);

        if ($response) {
            return $response;
        }
        
        throw new Exception('Erro ao consultar status da fatura');
    }

    public function get_airline_list() {
        $url = $this->url . '/packet/v1/cn38request/departure/airlines';
        $response = $this->get($url);

        if ($response) {
            return $response;
        }
    }

    public function confirm_departure($departure_data) {
        $url = $this->url . '/packet/v1/cn38request/departure';
        $response = $this->put($url, json_encode($departure_data));

        if (!isset($response->msgs)) {
            return $response;
        } else {
            throw new Exception('Erro ao confirmar o embarque: ' . implode("; ", $response->msgs));
        }
    }
    
    private function post($url, $payload, $headers = []) {
        $default_headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
        
        $response = wp_remote_post($url, [
            'method'  => 'POST',
            'body'    => $payload,
            'headers' => array_merge($default_headers, $headers),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            error_log('Erro na requisição POST: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        if (strpos($content_type, 'application/json') !== false) {
            return json_decode($body);
        }
        
        return $body;
    }

    private function put($url, $payload, $headers = []) {
        $default_headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    
        $response = wp_remote_post($url, [
            'method'  => 'PUT',
            'body'    => $payload,
            'headers' => array_merge($default_headers, $headers),
            'timeout' => 15,
        ]);
    
        if (is_wp_error($response)) {
            error_log('Erro na requisição POST: ' . $response->get_error_message());
            return false;
        }
    
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
    
        if (strpos($content_type, 'application/json') !== false) {
            return json_decode($body);
        }
    
        return $body;
    }
    
    private function get($url, $headers = []) {
        $default_headers = [
            'Authorization' => 'Bearer ' . $this->token,
        ];
        
        $response = wp_remote_get($url, [
            'headers' => array_merge($default_headers, $headers),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('Erro na requisição GET: ' . $response->get_error_message());
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    private function delete($url, $headers = []) {
        $default_headers = [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    
        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => array_merge($default_headers, $headers),
            'timeout' => 15,
        ]);
    
        if (is_wp_error($response)) {
            error_log('Erro na requisição DELETE: ' . $response->get_error_message());
            return false;
        }
    
        return json_decode(wp_remote_retrieve_body($response));
    }
    
}