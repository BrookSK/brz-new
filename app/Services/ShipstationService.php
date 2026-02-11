<?php
namespace App\Services;

class ShipstationService {
    private string $apiKey;
    private int $lastHttpCode = 0;

    public function __construct() {
        $this->apiKey = (string) $this->getConfig('entrega', 'shipstation_api_key', '');
    }

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    public function isEnabled(): bool {
        $v = strtolower(trim((string) $this->getConfig('entrega', 'shipstation_enabled', '0')));
        return ($v === '1' || $v === 'true' || $v === 'on' || $v === 'yes');
    }

    private function getApiBaseUrl(): string {
        return 'https://api.shipstation.com/v2';
    }

    private function requireApiKey(): void {
        if (trim($this->apiKey) === '') {
            throw new \Exception('ShipStation: configure shipstation_api_key em /admin/configuracoes > Entrega');
        }
    }

    public function createShipments(array $payload): array {
        if (!$this->isEnabled()) {
            throw new \Exception('ShipStation: integração desabilitada. Ative em /admin/configuracoes > Entrega');
        }
        $this->requireApiKey();

        $url = rtrim($this->getApiBaseUrl(), '/') . '/shipments';
        $headers = ['api-key: ' . $this->apiKey];
        return $this->curlJson('POST', $url, $payload, $headers);
    }

    public function purchaseLabelFromShipment(string $shipmentId, array $payload): array {
        if (!$this->isEnabled()) {
            throw new \Exception('ShipStation: integração desabilitada. Ative em /admin/configuracoes > Entrega');
        }
        $this->requireApiKey();

        $shipmentId = trim($shipmentId);
        if ($shipmentId === '') {
            throw new \Exception('ShipStation: shipment_id inválido');
        }

        $url = rtrim($this->getApiBaseUrl(), '/') . '/labels/shipment/' . rawurlencode($shipmentId);
        $headers = ['api-key: ' . $this->apiKey];
        return $this->curlJson('POST', $url, $payload, $headers);
    }

    private function curlJson(string $method, string $url, ?array $body, array $extraHeaders): array {
        if (!function_exists('curl_init')) {
            throw new \Exception('cURL não disponível no servidor');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];
        foreach ($extraHeaders as $h) {
            $headers[] = $h;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($body !== null) {
            $payload = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);

        $this->lastHttpCode = $code;

        if ($err !== '') {
            throw new \Exception('ShipStation: erro cURL: ' . $err);
        }

        $decoded = $raw !== false ? json_decode((string) $raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw];
        }

        if ($code >= 400) {
            $msg = '';
            if (isset($decoded['errors']) && is_array($decoded['errors']) && isset($decoded['errors'][0]) && is_array($decoded['errors'][0])) {
                $err0 = $decoded['errors'][0];
                if (!empty($err0['message']) && is_string($err0['message'])) {
                    $msg = (string) $err0['message'];
                }
            }
            foreach (['message', 'error', 'erro', 'detail', 'title'] as $k) {
                if (!empty($decoded[$k]) && is_string($decoded[$k])) {
                    $msg = (string) $decoded[$k];
                    break;
                }
            }
            if ($msg === '') {
                $msg = isset($decoded['raw']) ? (string) $decoded['raw'] : json_encode($decoded);
            }
            throw new \Exception('ShipStation HTTP ' . $code . ': ' . $msg);
        }

        return $decoded;
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = \Config\Database::getConnection();

        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $keyCandidates = ['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'];
        $valueCandidates = ['valor', 'value', 'conteudo', 'content', 'config_value'];

        foreach ($tableCandidates as $table) {
            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE ' . $table);
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                continue;
            }
            if (!is_array($cols) || empty($cols)) {
                continue;
            }

            $hasCategoria = in_array('categoria', $cols, true);
            $hasChave = in_array('chave', $cols, true);

            if ($hasCategoria && $hasChave) {
                $valueCol = null;
                foreach ($valueCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $valueCol = $c;
                        break;
                    }
                }
                if ($valueCol) {
                    try {
                        $stmt = $db->prepare('SELECT ' . $valueCol . ' AS v FROM ' . $table . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                        $stmt->execute([$categoria, $chave]);
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row && array_key_exists('v', $row)) {
                            return $row['v'];
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            $keyCol = null;
            foreach ($keyCandidates as $c) {
                if (in_array($c, $cols, true)) {
                    $keyCol = $c;
                    break;
                }
            }
            $valueCol = null;
            foreach ($valueCandidates as $c) {
                if (in_array($c, $cols, true)) {
                    $valueCol = $c;
                    break;
                }
            }
            if ($keyCol && $valueCol) {
                $fullKey = $categoria . '_' . $chave;
                try {
                    $stmt = $db->prepare('SELECT ' . $valueCol . ' AS v FROM ' . $table . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$fullKey]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('v', $row)) {
                        return $row['v'];
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return $default;
    }
}
