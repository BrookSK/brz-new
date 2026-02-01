<?php
namespace App\Services;

class AsaasLinkService {
    private $apiKey;
    private $ambiente;

    public function __construct(?string $apiKey = null, ?string $ambiente = null) {
        $this->apiKey = $apiKey ?? (string) $this->getConfig('pagamentos', 'asaas_api_key', '');
        $this->ambiente = $ambiente ?? (string) $this->getConfig('pagamentos', 'asaas_ambiente', 'sandbox');
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = \Config\Database::getConnection();

        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                $colName = null;
                if ($categoria === 'pagamentos') {
                    $direct = [
                        'asaas_api_key',
                        'asaas_ambiente',
                        'asaas_enabled',
                    ];
                    if (in_array($chave, $direct, true) && in_array($chave, $cols, true)) {
                        $colName = $chave;
                    }
                }

                if (!empty($colName)) {
                    $stmt = $db->query('SELECT ' . $colName . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            $stmt->execute([$categoria, $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    private function getBaseUrl(): string {
        $amb = strtolower(trim((string) $this->ambiente));
        if ($amb === 'production' || $amb === 'prod' || $amb === 'live') {
            return 'https://www.asaas.com/api/v3';
        }
        return 'https://sandbox.asaas.com/api/v3';
    }

    private function request(string $method, string $path, ?array $body = null): array {
        if (empty($this->apiKey)) {
            throw new \Exception('Asaas não configurado (API Key ausente)');
        }

        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $this->apiKey,
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = $body !== null ? json_encode($body) : null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                throw new \Exception('Erro de conexão com Asaas: ' . $err);
            }

            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = is_array($decoded) ? json_encode($decoded) : (string) $respBody;
                throw new \Exception('Erro Asaas HTTP ' . $httpCode . ': ' . $msg);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'ignore_errors' => true,
            ]
        ]);
        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function criarCobrancaDiferenca(string $customerId, float $valor, string $descricao, ?string $dueDate = null, ?string $externalReference = null, string $billingType = 'BOLETO'): array {
        if ($valor <= 0) {
            throw new \Exception('Valor inválido');
        }

        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => (float) $valor,
            'dueDate' => $dueDate ?? date('Y-m-d', strtotime('+1 day')),
            'description' => $descricao,
        ];
        if (!empty($externalReference)) {
            $payload['externalReference'] = $externalReference;
        }

        return $this->request('POST', '/payments', $payload);
    }
}
