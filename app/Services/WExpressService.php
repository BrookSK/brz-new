<?php
namespace App\Services;

class WExpressService {
    private $apiKey;
    private $baseUrl = 'https://api.wexpress.com/v1';

    public function __construct() {
        $this->apiKey = $_ENV['WEXPRESS_API_KEY'] ?? 'test_key';
    }

    public function criarEnvio($dados) {
        $endpoint = $this->baseUrl . '/shipments';
        
        $payload = [
            'origin' => [
                'address' => $dados['endereco_origem'],
                'city' => $dados['cidade_origem'],
                'state' => $dados['estado_origem'],
                'zip' => $dados['cep_origem'],
                'country' => 'US'
            ],
            'destination' => [
                'address' => $dados['endereco_destino'],
                'city' => $dados['cidade_destino'],
                'state' => $dados['estado_destino'],
                'zip' => $dados['cep_destino'],
                'country' => 'BR'
            ],
            'packages' => $dados['pacotes'],
            'service_type' => 'express_international',
            'customs_value' => $dados['valor_aduaneiro']
        ];

        $response = $this->makeRequest('POST', $endpoint, $payload);
        return $response;
    }

    public function rastrearEnvio($trackingCode) {
        $endpoint = $this->baseUrl . '/tracking/' . $trackingCode;
        $response = $this->makeRequest('GET', $endpoint);
        return $response;
    }

    public function calcularFrete($dados) {
        $endpoint = $this->baseUrl . '/rates';
        
        $payload = [
            'origin' => [
                'zip' => $dados['cep_origem'],
                'country' => 'US'
            ],
            'destination' => [
                'zip' => $dados['cep_destino'],
                'country' => 'BR'
            ],
            'weight' => $dados['peso'],
            'dimensions' => [
                'length' => $dados['comprimento'],
                'width' => $dados['largura'],
                'height' => $dados['altura']
            ]
        ];

        $response = $this->makeRequest('POST', $endpoint, $payload);
        return $response;
    }

    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro na requisição: ' . $error);
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception('API Error: ' . ($responseData['message'] ?? 'Unknown error'));
        }

        return $responseData;
    }

    public function simularCriacaoEnvio($dados) {
        return [
            'success' => true,
            'tracking_code' => 'WE' . strtoupper(uniqid()),
            'estimated_delivery' => date('Y-m-d', strtotime('+7 days')),
            'cost' => 85.50,
            'status' => 'created'
        ];
    }

    public function simularRastreamento($trackingCode) {
        return [
            'success' => true,
            'tracking_code' => $trackingCode,
            'status' => 'in_transit',
            'events' => [
                [
                    'date' => '2024-01-20 10:30:00',
                    'location' => 'Miami, FL',
                    'description' => 'Pacote coletado',
                    'status' => 'picked_up'
                ],
                [
                    'date' => '2024-01-20 14:15:00',
                    'location' => 'Miami International Airport',
                    'description' => 'Pacote em trânsito',
                    'status' => 'in_transit'
                ]
            ]
        ];
    }
}
