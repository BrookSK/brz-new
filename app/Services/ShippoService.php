<?php
namespace App\Services;

use Config\Database;

/**
 * Serviço de integração com a API Shippo para geração de etiquetas de envio internacional.
 * Suporta envios para o mundo todo, exceto Brasil.
 *
 * @see https://docs.goshippo.com
 */
class ShippoService {
    private const BASE_URL = 'https://api.goshippo.com';

    private ?string $apiToken = null;

    /**
     * Carrega configuração do Shippo do banco de dados (tabela configuracoes_sistema).
     */
    private function loadConfig(): array {
        try {
            $pdo = Database::getConnection();
            $config = [];

            // Detectar estrutura da tabela
            $cols = [];
            try {
                $stCols = $pdo->query('DESCRIBE configuracoes_sistema');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                return [];
            }

            $temCategoria = in_array('categoria', $cols, true);
            $keyCol = in_array('chave', $cols, true) ? 'chave' : (in_array('key', $cols, true) ? 'key' : 'chave');
            $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : 'valor');

            if ($temCategoria) {
                $stmt = $pdo->prepare("SELECT {$keyCol}, {$valCol} FROM configuracoes_sistema WHERE categoria = 'shippo'");
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    $k = $row[$keyCol] ?? '';
                    $v = $row[$valCol] ?? '';
                    $config['shippo_' . $k] = $v;
                }
            }

            // Fallback: buscar por chave com prefixo shippo_
            if (empty($config)) {
                try {
                    $stmt2 = $pdo->prepare("SELECT {$keyCol}, {$valCol} FROM configuracoes_sistema WHERE {$keyCol} LIKE 'shippo_%'");
                    $stmt2->execute();
                    $rows2 = $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows2 as $row) {
                        $config[$row[$keyCol]] = $row[$valCol] ?? '';
                    }
                } catch (\Exception $e) {}
            }

            return $config;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtém o token da API Shippo.
     */
    private function getApiToken(): string {
        if ($this->apiToken !== null) {
            return $this->apiToken;
        }
        $cfg = $this->loadConfig();
        $this->apiToken = (string) ($cfg['shippo_api_token'] ?? '');
        return $this->apiToken;
    }

    /**
     * Retorna todas as configurações da Shippo (para uso no controller).
     */
    public function getShippoConfig(): array {
        return $this->loadConfig();
    }

    /**
     * Verifica se a integração está configurada.
     */
    public function isConfigured(): bool {
        return $this->getApiToken() !== '';
    }

    /**
     * Faz uma requisição GET à API Shippo.
     */
    private function get(string $endpoint, array $params = []): array {
        $token = $this->getApiToken();
        if ($token === '') {
            return ['success' => false, 'error' => 'Shippo API Token não configurado.'];
        }

        $url = self::BASE_URL . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url, null, $token);
    }

    /**
     * Faz uma requisição POST à API Shippo.
     */
    private function post(string $endpoint, array $data): array {
        $token = $this->getApiToken();
        if ($token === '') {
            return ['success' => false, 'error' => 'Shippo API Token não configurado.'];
        }

        $url = self::BASE_URL . $endpoint;
        return $this->request('POST', $url, $data, $token);
    }

    /**
     * Executa requisição HTTP via cURL.
     */
    private function request(string $method, string $url, ?array $data, string $token): array {
        $headers = [
            'Authorization: ShippoToken ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'brz-new/1.0 (+https://brazilianashop.com)');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            return ['success' => false, 'error' => 'Falha na requisição: ' . $err, 'http_code' => $httpCode];
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Resposta inválida (não-JSON).', 'http_code' => $httpCode, 'raw' => $raw];
        }

        if ($httpCode >= 400) {
            $msg = $this->extractError($json);
            return ['success' => false, 'error' => $msg ?: ('Erro HTTP ' . $httpCode), 'http_code' => $httpCode, 'raw' => $json];
        }

        return ['success' => true, 'http_code' => $httpCode, 'data' => $json];
    }

    /**
     * Extrai mensagem de erro da resposta da API.
     */
    private function extractError(array $json): string {
        if (isset($json['detail'])) {
            return (string) $json['detail'];
        }
        if (isset($json['non_field_errors']) && is_array($json['non_field_errors'])) {
            return implode('; ', $json['non_field_errors']);
        }
        if (isset($json['__all__']) && is_array($json['__all__'])) {
            return implode('; ', $json['__all__']);
        }
        // Iterar por campos com erros
        $errors = [];
        foreach ($json as $field => $msgs) {
            if (is_array($msgs)) {
                foreach ($msgs as $m) {
                    $errors[] = $field . ': ' . (is_string($m) ? $m : json_encode($m));
                }
            }
        }
        return implode('; ', $errors);
    }

    /**
     * Cria um Shipment na Shippo e retorna as rates disponíveis.
     *
     * @param array $addressFrom Endereço de origem
     * @param array $addressTo Endereço de destino
     * @param array $parcel Dimensões e peso do pacote
     * @param array $customsDeclaration Declaração aduaneira (para envios internacionais)
     * @return array ['success' => bool, 'data' => [...rates...], 'shipment_id' => string]
     */
    public function createShipment(
        array $addressFrom,
        array $addressTo,
        array $parcel,
        array $customsDeclaration = [],
        array $carrierAccounts = []
    ): array {
        $payload = [
            'address_from' => $addressFrom,
            'address_to' => $addressTo,
            'parcels' => [$parcel],
            'async' => false,
            'intent' => 'PURCHASE',
            'extra' => ['is_return' => false],
        ];

        if (!empty($customsDeclaration)) {
            $payload['customs_declaration'] = $customsDeclaration;
        }

        // Restringe as cotações às contas selecionadas pelo operador. Sem isso,
        // a Shippo pode devolver rates de qualquer carrier habilitada na conta.
        $carrierAccounts = array_values(array_filter(array_map('strval', $carrierAccounts)));
        if (!empty($carrierAccounts)) {
            $payload['carrier_accounts'] = $carrierAccounts;
        }

        $result = $this->post('/shipments', $payload);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];
        return [
            'success' => true,
            'shipment_id' => $data['object_id'] ?? '',
            'rates' => $data['rates'] ?? [],
            'data' => $data,
        ];
    }

    /**
     * Compra uma etiqueta (Transaction) com base em um rate_id.
     *
     * @param string $rateId ID do rate escolhido
     * @param string $labelFileType Tipo do arquivo (PDF Letter 8.5x11, PNG, ZPLII)
     * @return array ['success' => bool, 'tracking_number' => string, 'label_url' => string, ...]
     */
    public function purchaseLabel(string $rateId, string $labelFileType = 'PDF'): array {
        $payload = [
            'rate' => $rateId,
            'label_file_type' => $labelFileType,
            'async' => false,
        ];

        $result = $this->post('/transactions', $payload);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];

        if (($data['status'] ?? '') !== 'SUCCESS') {
            $msgs = $data['messages'] ?? [];
            $errorMsg = 'Falha ao gerar etiqueta.';
            if (!empty($msgs)) {
                $parts = [];
                foreach ($msgs as $m) {
                    $parts[] = ($m['text'] ?? '') ?: ($m['source'] ?? '');
                }
                $errorMsg = implode('; ', array_filter($parts));
            }
            return ['success' => false, 'error' => $errorMsg, 'data' => $data];
        }

        return [
            'success' => true,
            'transaction_id' => $data['object_id'] ?? '',
            'tracking_number' => $data['tracking_number'] ?? '',
            'label_url' => $data['label_url'] ?? '',
            'tracking_url' => $data['tracking_url_provider'] ?? '',
            'eta' => $data['eta'] ?? '',
            'rate' => $data['rate'] ?? [],
            'data' => $data,
        ];
    }

    /**
     * Cria etiqueta em chamada única (single call) - combina shipment + transaction.
     *
     * @param array $addressFrom Endereço de origem
     * @param array $addressTo Endereço de destino
     * @param array $parcel Dimensões e peso
     * @param string $servicelevelToken Token do nível de serviço (ex: usps_priority)
     * @param string $carrierAccount ID da carrier account
     * @param array $customsDeclaration Declaração aduaneira
     * @param string $labelFileType Tipo do arquivo da etiqueta
     * @return array
     */
    public function createLabelSingleCall(
        array $addressFrom,
        array $addressTo,
        array $parcel,
        string $servicelevelToken,
        string $carrierAccount,
        array $customsDeclaration = [],
        string $labelFileType = 'PDF'
    ): array {
        $shipment = [
            'address_from' => $addressFrom,
            'address_to' => $addressTo,
            'parcels' => [$parcel],
            'extra' => ['is_return' => false],
        ];

        if (!empty($customsDeclaration)) {
            $shipment['customs_declaration'] = $customsDeclaration;
        }

        $payload = [
            'shipment' => $shipment,
            'carrier_account' => $carrierAccount,
            'servicelevel_token' => $servicelevelToken,
            'label_file_type' => $labelFileType,
            'async' => false,
        ];

        $result = $this->post('/transactions', $payload);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];

        if (($data['status'] ?? '') !== 'SUCCESS') {
            $msgs = $data['messages'] ?? [];
            $errorMsg = 'Falha ao gerar etiqueta.';
            if (!empty($msgs)) {
                $parts = [];
                foreach ($msgs as $m) {
                    $parts[] = ($m['text'] ?? '') ?: ($m['source'] ?? '');
                }
                $errorMsg = implode('; ', array_filter($parts));
            }
            return ['success' => false, 'error' => $errorMsg, 'data' => $data];
        }

        return [
            'success' => true,
            'transaction_id' => $data['object_id'] ?? '',
            'tracking_number' => $data['tracking_number'] ?? '',
            'label_url' => $data['label_url'] ?? '',
            'tracking_url' => $data['tracking_url_provider'] ?? '',
            'eta' => $data['eta'] ?? '',
            'rate' => $data['rate'] ?? [],
            'data' => $data,
        ];
    }

    /**
     * Consulta tarifas de frete em tempo real (live rates) e retorna a opção
     * mais barata normalizada, para uso como ESTIMATIVA no checkout de países
     * fora dos Estados Unidos e Brasil.
     *
     * Fluxo: cria um Shipment (POST /shipments) com origem padrão + destino +
     * pacote informado e escolhe a menor rate retornada pela Shippo.
     *
     * IMPORTANTE: este método retorna apenas uma ESTIMATIVA. O acréscimo de 30%
     * (regra comercial da Braziliana) deve ser aplicado por quem consome este
     * método, não aqui, para manter a responsabilidade de cálculo separada.
     *
     * @param array $addressTo Endereço de destino (name, street1, city, state, zip, country, ...)
     * @param array $parcel    Pacote: length, width, height, distance_unit, weight, mass_unit
     * @param array $customsDeclaration Declaração aduaneira opcional (internacional)
     * @param array $carrierAccounts    IDs de carrier accounts para restringir a cotação (opcional)
     * @return array {
     *   success: bool,
     *   amount: float,            // valor da tarifa mais barata (moeda original da Shippo)
     *   currency: string,         // moeda retornada pela Shippo (ex: USD)
     *   provider: string,         // transportadora (ex: USPS, UPS)
     *   servicelevel: string,     // nome do serviço (ex: Priority Mail International)
     *   servicelevel_token: string,
     *   estimated_days: int|null,
     *   rate_id: string,
     *   shipment_id: string,
     *   rates: array,             // todas as rates disponíveis (normalizadas)
     *   error?: string
     * }
     */
    public function getLiveRate(
        array $addressTo,
        array $parcel,
        array $customsDeclaration = [],
        array $carrierAccounts = []
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Shippo API Token não configurado.'];
        }

        $addressFrom = $this->getDefaultAddressFrom();

        $result = $this->createShipment(
            $addressFrom,
            $addressTo,
            $parcel,
            $customsDeclaration,
            $carrierAccounts
        );

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Falha ao consultar tarifas na Shippo.',
            ];
        }

        $rawRates = is_array($result['rates'] ?? null) ? $result['rates'] : [];
        if (empty($rawRates)) {
            return [
                'success' => false,
                'error' => 'Nenhuma tarifa de frete disponível para o destino informado.',
                'shipment_id' => (string) ($result['shipment_id'] ?? ''),
            ];
        }

        // Normaliza e ordena as rates da mais barata para a mais cara.
        $rates = [];
        foreach ($rawRates as $r) {
            $amount = (float) ($r['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $rates[] = [
                'rate_id' => (string) ($r['object_id'] ?? ''),
                'amount' => $amount,
                'currency' => (string) ($r['currency'] ?? 'USD'),
                'provider' => (string) ($r['provider'] ?? ''),
                'servicelevel' => (string) ($r['servicelevel']['name'] ?? ($r['servicelevel_name'] ?? '')),
                'servicelevel_token' => (string) ($r['servicelevel']['token'] ?? ($r['servicelevel_token'] ?? '')),
                'estimated_days' => isset($r['estimated_days']) ? (int) $r['estimated_days'] : null,
            ];
        }

        if (empty($rates)) {
            return [
                'success' => false,
                'error' => 'Nenhuma tarifa de frete válida foi retornada pela Shippo.',
                'shipment_id' => (string) ($result['shipment_id'] ?? ''),
            ];
        }

        usort($rates, static function ($a, $b) {
            return $a['amount'] <=> $b['amount'];
        });

        $cheapest = $rates[0];

        return [
            'success' => true,
            'amount' => (float) $cheapest['amount'],
            'currency' => (string) $cheapest['currency'],
            'provider' => (string) $cheapest['provider'],
            'servicelevel' => (string) $cheapest['servicelevel'],
            'servicelevel_token' => (string) $cheapest['servicelevel_token'],
            'estimated_days' => $cheapest['estimated_days'],
            'rate_id' => (string) $cheapest['rate_id'],
            'shipment_id' => (string) ($result['shipment_id'] ?? ''),
            'rates' => $rates,
        ];
    }

    /**
     * Obtém detalhes de um rastreamento.
     */
    public function getTracking(string $carrier, string $trackingNumber): array {
        return $this->get('/tracks/' . urlencode($carrier) . '/' . urlencode($trackingNumber));
    }

    /**
     * Lista carrier accounts configuradas.
     */
    public function listCarrierAccounts(): array {
        return $this->get('/carrier_accounts');
    }

    /**
     * Valida um endereço via API da Shippo.
     */
    public function validateAddress(array $address): array {
        $address['validate'] = true;
        return $this->post('/addresses', $address);
    }

    /**
     * Monta o endereço de origem padrão (Braziliana Shop nos EUA).
     */
    public function getDefaultAddressFrom(): array {
        $cfg = $this->loadConfig();
        return [
            'name' => $cfg['shippo_sender_name'] ?? 'Braziliana Shop',
            'company' => $cfg['shippo_sender_company'] ?? 'Braziliana Shop LLC',
            'street1' => $cfg['shippo_sender_street1'] ?? '',
            'street2' => $cfg['shippo_sender_street2'] ?? '',
            'city' => $cfg['shippo_sender_city'] ?? '',
            'state' => $cfg['shippo_sender_state'] ?? '',
            'zip' => $cfg['shippo_sender_zip'] ?? '',
            'country' => $cfg['shippo_sender_country'] ?? 'US',
            'phone' => $cfg['shippo_sender_phone'] ?? '',
            'email' => $cfg['shippo_sender_email'] ?? '',
        ];
    }

    /**
     * Monta payload de declaração aduaneira para envios internacionais.
     *
     * @param array $items Lista de itens [{description, quantity, net_weight, value_amount, value_currency, origin_country, tariff_number}]
     * @param string $contentsType Tipo de conteúdo (MERCHANDISE, GIFT, DOCUMENTS, SAMPLE, RETURN, OTHER)
     * @param string $nonDeliveryOption Opção de não-entrega (ABANDON, RETURN)
     * @return array Payload da customs declaration
     */
    public function buildCustomsDeclaration(array $items, string $contentsType = 'MERCHANDISE', string $nonDeliveryOption = 'RETURN'): array {
        $customsItems = [];
        foreach ($items as $item) {
            $customsItems[] = [
                'description' => $item['description'] ?? 'Merchandise',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'net_weight' => (string) ($item['net_weight'] ?? '0.1'),
                'mass_unit' => $item['mass_unit'] ?? 'kg',
                'value_amount' => (string) ($item['value_amount'] ?? '10.00'),
                'value_currency' => $item['value_currency'] ?? 'USD',
                'origin_country' => $item['origin_country'] ?? 'US',
                'tariff_number' => $item['tariff_number'] ?? '',
            ];
        }

        return [
            'contents_type' => $contentsType,
            'non_delivery_option' => $nonDeliveryOption,
            'certify' => true,
            'certify_signer' => 'Braziliana Shop',
            'items' => $customsItems,
        ];
    }
}
