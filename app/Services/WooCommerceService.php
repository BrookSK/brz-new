<?php
namespace App\Services;

class WooCommerceService {
    private string $storeUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private int $lastHttpCode = 0;
    private bool $sslVerify = true;
    private string $caBundlePath = '';

    public function __construct() {
        $this->storeUrl = (string) $this->getConfig('woocommerce', 'store_url', '');
        $this->consumerKey = (string) $this->getConfig('woocommerce', 'consumer_key', '');
        $this->consumerSecret = (string) $this->getConfig('woocommerce', 'consumer_secret', '');
        $sslVerifyRaw = $this->getConfig('woocommerce', 'ssl_verify', null);
        $this->caBundlePath = trim((string) $this->getConfig('woocommerce', 'ca_bundle_path', ''));

        $this->storeUrl = rtrim(trim($this->storeUrl), '/');
        $this->consumerKey = trim($this->consumerKey);
        $this->consumerSecret = trim($this->consumerSecret);

        if ($sslVerifyRaw !== null) {
            $v = strtolower(trim((string) $sslVerifyRaw));
            $this->sslVerify = !in_array($v, ['0', 'false', 'no', 'off'], true);
        }
    }

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    public function isConfigured(): bool {
        return $this->storeUrl !== '' && $this->consumerKey !== '' && $this->consumerSecret !== '';
    }

    public function updateOrderMeta(int $orderId, array $meta): array {
        if ($orderId <= 0) {
            throw new \Exception('Order ID inválido');
        }
        if (!$this->isConfigured()) {
            throw new \Exception('WooCommerce REST não configurado (Store URL / Consumer Key / Consumer Secret)');
        }

        $metaData = [];
        foreach ($meta as $k => $v) {
            $key = trim((string) $k);
            if ($key === '') continue;
            $metaData[] = ['key' => $key, 'value' => $v];
        }

        return $this->request('PUT', '/wp-json/wc/v3/orders/' . $orderId, [
            'meta_data' => $metaData,
        ]);
    }

    public function addOrderNote(int $orderId, string $note, bool $customerNote = false): array {
        if ($orderId <= 0) {
            throw new \Exception('Order ID inválido');
        }
        if (!$this->isConfigured()) {
            throw new \Exception('WooCommerce REST não configurado (Store URL / Consumer Key / Consumer Secret)');
        }

        $note = trim($note);
        if ($note === '') {
            throw new \Exception('Nota vazia');
        }

        return $this->request('POST', '/wp-json/wc/v3/orders/' . $orderId . '/notes', [
            'note' => $note,
            'customer_note' => $customerNote,
        ]);
    }

    private function request(string $method, string $path, ?array $body = null): array {
        $url = $this->storeUrl . '/' . ltrim($path, '/');

        if (!function_exists('curl_init')) {
            throw new \Exception('cURL não disponível no servidor');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ];

        $payload = $body !== null ? json_encode($body) : null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERPWD, $this->consumerKey . ':' . $this->consumerSecret);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        if (!$this->sslVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if ($this->caBundlePath !== '') {
                curl_setopt($ch, CURLOPT_CAINFO, $this->caBundlePath);
            }
        }
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $respBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;

        if ($err !== '') {
            throw new \Exception('Erro ao chamar WooCommerce: ' . $err);
        }

        $decoded = $respBody !== false ? json_decode((string) $respBody, true) : null;
        if (!is_array($decoded)) {
            $decoded = ['raw' => $respBody];
        }

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? ($decoded['raw'] ?? 'Erro desconhecido');
            throw new \Exception('WooCommerce HTTP ' . $httpCode . ': ' . $msg);
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
