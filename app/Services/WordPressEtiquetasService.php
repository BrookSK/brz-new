<?php
namespace App\Services;

/**
 * Service para integração com o WordPress Etiquetas (etiquetas.brazilianashop.com.br).
 * 
 * Faz requisições para a REST API exposta pelo snippet no WordPress
 * para criar pacotes (etiquetas), containers, faturas e embarques.
 * 
 * O WordPress é responsável por:
 * - Chamar a API dos Correios PACKET
 * - Manter o registro dos pacotes/containers/faturas/embarques
 * - Gerar os PDFs das etiquetas
 */
class WordPressEtiquetasService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        // Configurações - podem ser movidas para configuracoes_sistema se preferir
        $this->baseUrl = rtrim($this->getConfig('wp_etiquetas_url', 'https://etiquetas.brazilianashop.com.br'), '/');
        $this->apiKey = $this->getConfig('wp_etiquetas_api_key', 'hBkXYUYPe5bvjujrI4W9r5yCWSVekw7ao529uGYbkBEyknW4tPhsx8mHMIANyNXl');
        $this->timeout = 120; // 2 minutos para operações que fazem polling
    }

    // =========================================================
    // SALDO
    // =========================================================

    /**
     * Consultar saldo de códigos de rastreio disponíveis.
     */
    public function getBalance(): array
    {
        return $this->get('/wp-json/brz/v1/balance');
    }

    // =========================================================
    // PACOTES (Etiquetas)
    // =========================================================

    /**
     * Criar um pacote (etiqueta) no WordPress/Correios.
     * 
     * @param array $data Dados do pacote:
     *   - customerControlCode (string): código do pedido
     *   - totalWeight (int): peso em gramas
     *   - packagingLength (float): comprimento em cm
     *   - packagingWidth (float): largura em cm
     *   - packagingHeight (float): altura em cm
     *   - recipientName, recipientDocumentType, recipientDocumentNumber, etc.
     *   - items (array): [{hsCode, description, quantity, value}]
     *   - freightPaidValue (float): valor do frete
     *   - insurancePaidValue (float, optional): valor do seguro
     *   - distributionModality (int): 33162=Standard, 33170=Express
     *   - taxPaymentMethod (string): DDU, DDP, PRC
     *   - currency (string): USD
     */
    public function createPackage(array $data): array
    {
        return $this->post('/wp-json/brz/v1/packages/create', $data);
    }

    /**
     * Criar pacotes em massa.
     * 
     * @param array $packages Array de dados de pacotes (mesmo formato de createPackage)
     * @return array Resultados individuais para cada pacote
     */
    public function createPackagesBatch(array $packages): array
    {
        $results = [];
        foreach ($packages as $idx => $pkg) {
            $result = [
                'index' => $idx,
                'customerControlCode' => $pkg['customerControlCode'] ?? '',
                'success' => false,
                'error' => '',
                'tracking_number' => '',
                'wp_post_id' => null,
            ];

            try {
                $resp = $this->createPackage($pkg);
                if (!empty($resp['success'])) {
                    $result['success'] = true;
                    $result['tracking_number'] = $resp['tracking_number'] ?? '';
                    $result['wp_post_id'] = $resp['wp_post_id'] ?? null;
                } else {
                    $result['error'] = $resp['error'] ?? 'Erro desconhecido';
                }
            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Listar pacotes do WordPress.
     */
    public function listPackages(array $params = []): array
    {
        $query = http_build_query($params);
        return $this->get('/wp-json/brz/v1/packages' . ($query ? '?' . $query : ''));
    }

    /**
     * Listar pacotes sem container (disponíveis para containerização).
     */
    public function listPackagesWithoutContainer(): array
    {
        return $this->listPackages(['without_container' => '1', 'per_page' => 200]);
    }

    // =========================================================
    // CONTAINERS (Unitizadores)
    // =========================================================

    /**
     * Criar um container (unitizador) no WordPress/Correios.
     * 
     * @param array $data Dados do container:
     *   - dispatchNumber (int): número da remessa
     *   - trackingCodes (array): códigos de rastreio dos pacotes
     *   - originCountry (string): US (default)
     *   - originOperatorName (string): ex. USPS
     *   - destinationOperatorName (string): CWBA ou SAOD
     *   - postalCategoryCode (string): A, B, C, D
     *   - serviceSubclassCode (string): NX ou IX
     *   - unitType (string): 1 (saco) ou 2 (caixa)
     *   - awb (string): número AWB
     *   - triageGroup (string): 1 a 5
     */
    public function createContainer(array $data): array
    {
        return $this->post('/wp-json/brz/v1/containers/create', $data);
    }

    /**
     * Listar containers do WordPress.
     */
    public function listContainers(array $params = []): array
    {
        $query = http_build_query($params);
        return $this->get('/wp-json/brz/v1/containers' . ($query ? '?' . $query : ''));
    }

    /**
     * Listar containers sem fatura (disponíveis para faturamento).
     */
    public function listContainersWithoutBill(): array
    {
        return $this->listContainers(['without_bill' => '1', 'per_page' => 200]);
    }

    // =========================================================
    // FATURAS (CN38)
    // =========================================================

    /**
     * Criar uma fatura (CN38) no WordPress/Correios.
     * 
     * @param array $data Dados da fatura:
     *   - containerIds (array): IDs dos containers no WordPress
     */
    public function createBill(array $data): array
    {
        return $this->post('/wp-json/brz/v1/bills/create', $data, 180); // timeout maior para polling
    }

    /**
     * Listar faturas do WordPress.
     */
    public function listBills(array $params = []): array
    {
        $query = http_build_query($params);
        return $this->get('/wp-json/brz/v1/bills' . ($query ? '?' . $query : ''));
    }

    /**
     * Listar faturas sem embarque (disponíveis para embarque).
     */
    public function listBillsWithoutDeparture(): array
    {
        return $this->listBills(['without_departure' => '1', 'per_page' => 200]);
    }

    // =========================================================
    // EMBARQUES (Departures)
    // =========================================================

    /**
     * Criar e confirmar um embarque no WordPress/Correios.
     * 
     * @param array $data Dados do embarque:
     *   - billIds (array): IDs das faturas no WordPress
     *   - flightNumber (int): número do voo
     *   - airlineCode (string): código da companhia aérea
     *   - departureDate (string): data de partida ISO 8601
     *   - departureAirportCode (string): código IATA do aeroporto de partida
     *   - arrivalDate (string): data de chegada ISO 8601
     *   - arrivalAirportCode (string): código IATA do aeroporto de chegada
     */
    public function createDeparture(array $data): array
    {
        return $this->post('/wp-json/brz/v1/departures/create', $data);
    }

    /**
     * Listar embarques do WordPress.
     */
    public function listDepartures(array $params = []): array
    {
        $query = http_build_query($params);
        return $this->get('/wp-json/brz/v1/departures' . ($query ? '?' . $query : ''));
    }

    // =========================================================
    // DELETAR/DESVINCULAR
    // =========================================================

    /**
     * Deletar container e desvincular pacotes.
     */
    public function deleteContainer(int $wpPostId): array
    {
        return $this->delete('/wp-json/brz/v1/containers/delete/' . $wpPostId);
    }

    /**
     * Deletar fatura e desvincular containers.
     */
    public function deleteBill(int $wpPostId): array
    {
        return $this->delete('/wp-json/brz/v1/bills/delete/' . $wpPostId);
    }

    /**
     * Deletar embarque e desvincular faturas.
     */
    public function deleteDeparture(int $wpPostId): array
    {
        return $this->delete('/wp-json/brz/v1/departures/delete/' . $wpPostId);
    }

    private function delete(string $path): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'brz-system/1.0');

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Debug log
        error_log('[BRZ-WP-DELETE] URL=' . $url . ' | HTTP=' . $httpCode . ' | raw=' . substr((string)$raw, 0, 1000));

        if ($raw === false || $raw === null) {
            return ['success' => false, 'error' => 'Falha na conexão: ' . $err, 'http_code' => $httpCode];
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Resposta não-JSON do WordPress (HTTP ' . $httpCode . ')', 'http_code' => $httpCode, 'raw' => substr((string) $raw, 0, 500)];
        }

        // Normalizar resposta de erro do WordPress REST API (formato: {code, message, data})
        if (!isset($json['success'])) {
            if (isset($json['code']) && isset($json['message'])) {
                return ['success' => false, 'error' => $json['message'] . ' (wp_code: ' . $json['code'] . ')', 'http_code' => $httpCode];
            }
            if ($httpCode >= 400) {
                return ['success' => false, 'error' => $json['message'] ?? $json['error'] ?? 'Erro HTTP ' . $httpCode, 'http_code' => $httpCode];
            }
        }

        // Garantir que sempre tenha a chave 'error' quando success=false
        if (isset($json['success']) && !$json['success'] && !isset($json['error'])) {
            $json['error'] = $json['message'] ?? 'Erro desconhecido';
        }

        return $json;
    }

    // =========================================================
    // PDFs - DOWNLOAD DIRETO
    // =========================================================

    /**
     * Baixar PDF da etiqueta de um pacote.
     * Retorna o conteúdo binário do PDF ou array com erro.
     */
    public function downloadPackagePdf(int $wpPostId): array|string
    {
        return $this->downloadPdf('/wp-json/brz/v1/packages/pdf/' . $wpPostId);
    }

    /**
     * Baixar PDF do container (etiqueta unitizador).
     */
    public function downloadContainerPdf(int $wpPostId): array|string
    {
        return $this->downloadPdf('/wp-json/brz/v1/containers/pdf/' . $wpPostId);
    }

    /**
     * Baixar PDF da fatura (Delivery Bill).
     */
    public function downloadBillPdf(int $wpPostId): array|string
    {
        return $this->downloadPdf('/wp-json/brz/v1/bills/pdf/' . $wpPostId);
    }

    /**
     * Faz GET em um endpoint de PDF e retorna o conteúdo binário.
     * Retorna string (PDF raw) em caso de sucesso, ou array com erro.
     */
    private function downloadPdf(string $path): array|string
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'brz-system/1.0');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            return ['success' => false, 'error' => 'Falha na conexão: ' . $err, 'http_code' => $httpCode];
        }

        // Se o content-type é PDF, retornar o binário direto
        if (strpos($contentType, 'application/pdf') !== false) {
            return $raw; // string binária do PDF
        }

        // Se não é PDF, provavelmente é um JSON de erro
        $json = json_decode((string) $raw, true);
        if (is_array($json)) {
            return $json; // array com success/error
        }

        return ['success' => false, 'error' => 'Resposta inesperada do WordPress', 'http_code' => $httpCode];
    }

    // =========================================================
    // HTTP HELPERS
    // =========================================================

    private function get(string $path, int $timeoutOverride = 0): array
    {
        $url = $this->baseUrl . $path;
        $timeout = $timeoutOverride > 0 ? $timeoutOverride : 30;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'brz-system/1.0');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            return ['success' => false, 'error' => 'Falha na conexão com WordPress: ' . $err, 'http_code' => $httpCode];
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Resposta inválida do WordPress (não-JSON)', 'http_code' => $httpCode, 'raw' => substr((string) $raw, 0, 500)];
        }

        return $json;
    }

    private function post(string $path, array $data, int $timeoutOverride = 0): array
    {
        $url = $this->baseUrl . $path;
        $timeout = $timeoutOverride > 0 ? $timeoutOverride : $this->timeout;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'brz-system/1.0');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            return ['success' => false, 'error' => 'Falha na conexão com WordPress: ' . $err, 'http_code' => $httpCode];
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Resposta inválida do WordPress (não-JSON)', 'http_code' => $httpCode, 'raw' => substr((string) $raw, 0, 500)];
        }

        return $json;
    }

    // =========================================================
    // CONFIG HELPER
    // =========================================================

    private function getConfig(string $key, string $default = ''): string
    {
        try {
            $pdo = \Config\Database::getConnection();
            
            // Tentar buscar em configuracoes_sistema
            $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        } catch (\Exception $e) {
            // Se não existir a tabela ou a chave, usa o default
        }

        return $default;
    }
}
