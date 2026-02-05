<?php
namespace App\Services;

class StampsService {
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $ambiente;
    private int $lastHttpCode = 0;

    public function __construct() {
        $this->clientId = (string) $this->getConfig('entrega', 'stamps_client_id', '');
        $this->clientSecret = (string) $this->getConfig('entrega', 'stamps_client_secret', '');
        $this->refreshToken = (string) $this->getConfig('entrega', 'stamps_refresh_token', '');
        $this->ambiente = (string) $this->getConfig('entrega', 'stamps_ambiente', 'staging');
    }

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    public function isEnabled(): bool {
        $v = strtolower(trim((string) $this->getConfig('entrega', 'stamps_enabled', '0')));
        return ($v === '1' || $v === 'true' || $v === 'on' || $v === 'yes');
    }

    public function isStaging(): bool {
        $a = strtolower(trim($this->ambiente));
        return ($a === 'staging' || $a === 'teste' || $a === 'test' || $a === 'sandbox' || $a === 'testing');
    }

    private function getAuthBaseUrl(): string {
        return $this->isStaging() ? 'https://signin.testing.stampsendicia.com' : 'https://signin.stampsendicia.com';
    }

    private function getApiBaseUrl(): string {
        return $this->isStaging() ? 'https://api.testing.stampsendicia.com/sera' : 'https://api.stampsendicia.com/sera';
    }

    private function ensureSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    private function getCachedAccessToken(): ?string {
        $this->ensureSession();
        $token = isset($_SESSION['stamps_access_token']) ? (string) $_SESSION['stamps_access_token'] : '';
        $exp = isset($_SESSION['stamps_access_token_expires_at']) ? (int) $_SESSION['stamps_access_token_expires_at'] : 0;
        if ($token === '' || $exp <= 0) {
            return null;
        }
        if (time() + 60 >= $exp) {
            return null;
        }
        return $token;
    }

    private function cacheAccessToken(string $token, int $expiresInSec): void {
        $this->ensureSession();
        $_SESSION['stamps_access_token'] = $token;
        $_SESSION['stamps_access_token_expires_at'] = time() + max(30, $expiresInSec);
    }

    public function refreshAccessToken(): string {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new \Exception('Stamps: configure client_id/client_secret/refresh_token em /admin/configuracoes > Entrega');
        }

        $url = rtrim($this->getAuthBaseUrl(), '/') . '/oauth/token';
        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ];

        $resp = $this->curlJson('POST', $url, $payload, []);
        $token = is_array($resp) ? (string) ($resp['access_token'] ?? '') : '';
        $expiresIn = (int) (is_array($resp) ? ($resp['expires_in'] ?? 0) : 0);
        if ($token === '') {
            throw new \Exception('Stamps: access_token não retornou.');
        }
        $this->cacheAccessToken($token, $expiresIn > 0 ? $expiresIn : 1800);
        return $token;
    }

    private function getAccessToken(): string {
        $cached = $this->getCachedAccessToken();
        if ($cached !== null) {
            return $cached;
        }
        return $this->refreshAccessToken();
    }

    public function createLabel(array $payload): array {
        if (!$this->isEnabled()) {
            throw new \Exception('Stamps: integração desabilitada. Ative em /admin/configuracoes > Entrega');
        }

        $token = $this->getAccessToken();
        $base = rtrim($this->getApiBaseUrl(), '/');
        $url = $base . '/v1/labels';

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

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
            throw new \Exception('Stamps: erro cURL: ' . $err);
        }

        $decoded = $raw !== false ? json_decode((string) $raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw];
        }

        if ($code >= 400) {
            $msg = '';
            foreach (['message', 'error', 'erro', 'detail', 'title'] as $k) {
                if (!empty($decoded[$k]) && is_string($decoded[$k])) {
                    $msg = (string) $decoded[$k];
                    break;
                }
            }
            if ($msg === '') {
                $msg = isset($decoded['raw']) ? (string) $decoded['raw'] : json_encode($decoded);
            }
            throw new \Exception('Stamps HTTP ' . $code . ': ' . $msg);
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
                    'stamps_enabled',
                    'stamps_ambiente',
                    'stamps_client_id',
                    'stamps_client_secret',
                    'stamps_refresh_token',
                    'stamps_from_address_json',
                    'stamps_service_type',
                    'stamps_packaging_type',
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
