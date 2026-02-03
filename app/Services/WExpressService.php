<?php
namespace App\Services;

class WExpressService {
    private string $apiKey;
    private string $ambiente;
    private string $serviceCode;
    private ?array $sender;
    private ?string $senderJsonError;
    private int $lastHttpCode = 0;

    public function __construct() {
        $this->apiKey = (string) $this->getConfig('entrega', 'wexpress_api_key', '');
        $this->ambiente = (string) $this->getConfig('entrega', 'wexpress_ambiente', 'sandbox');
        $this->serviceCode = (string) $this->getConfig('entrega', 'wexpress_service_code', 'wexpress_correios_std');

        $senderJson = (string) $this->getConfig('entrega', 'wexpress_sender_json', '');
        $senderJson = trim($senderJson);
        $this->senderJsonError = null;
        $decoded = $senderJson !== '' ? json_decode($senderJson, true) : null;
        if ($senderJson !== '' && !is_array($decoded)) {
            $this->senderJsonError = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON inválido';
        }
        $this->sender = is_array($decoded) ? $decoded : null;
    }

    public function getServiceCode(): string {
        return $this->serviceCode;
    }

    public function getSender(): ?array {
        return $this->sender;
    }

    public function getSenderJsonError(): ?string {
        return $this->senderJsonError;
    }

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    public function createShipping(array $payload): array {
        return $this->request('POST', '/shipping/', $payload);
    }

    public function getShipping(string $wexpressId): array {
        $wexpressId = trim($wexpressId);
        if ($wexpressId === '') {
            throw new \Exception('WExpressID inválido');
        }
        return $this->request('GET', '/shipping/' . rawurlencode($wexpressId));
    }

    private function getBaseUrl(): string {
        $amb = strtolower(trim($this->ambiente));
        if ($amb === 'production' || $amb === 'prod' || $amb === 'live') {
            return 'https://api.wexpress.me';
        }
        // Se houver base de sandbox distinta, ajustar aqui. Por ora, seguir a doc do endpoint /shipping.
        return 'https://api.wexpress.me';
    }

    private function request(string $method, string $path, ?array $body = null): array {
        if (empty($this->apiKey)) {
            throw new \Exception('W-Express não configurado (API Key ausente)');
        }

        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-key: ' . $this->apiKey,
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body);
        }

        if (!function_exists('curl_init')) {
            throw new \Exception('cURL não disponível no servidor');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $respBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;

        if ($err) {
            throw new \Exception('Erro ao chamar W-Express: ' . $err);
        }

        $decoded = $respBody !== false ? json_decode((string) $respBody, true) : null;
        if (!is_array($decoded)) {
            $decoded = ['raw' => $respBody];
        }

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? ($decoded['raw'] ?? 'Erro desconhecido');
            throw new \Exception('W-Express HTTP ' . $httpCode . ': ' . $msg);
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

            if (!$hasCategoria && !$hasChave && in_array('id', $cols, true)) {
                $direct = [
                    'wexpress_api_key',
                    'wexpress_ambiente',
                    'wexpress_enabled',
                    'wexpress_service_code',
                    'wexpress_sender_json',
                ];
                if ($categoria === 'entrega' && in_array($chave, $direct, true) && in_array($chave, $cols, true)) {
                    try {
                        $stmt = $db->query('SELECT ' . $chave . ' AS v FROM ' . $table . ' ORDER BY id ASC LIMIT 1');
                        $row = $stmt ? ($stmt->fetch(\PDO::FETCH_ASSOC) ?: null) : null;
                        if (is_array($row) && array_key_exists('v', $row)) {
                            return $row['v'];
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

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
