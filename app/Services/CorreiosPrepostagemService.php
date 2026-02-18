<?php
namespace App\Services;

class CorreiosPrepostagemService {
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->token = trim($token);
    }

    public function criarPrepostagem(array $payload): array {
        return $this->requestJson('POST', '/v1/prepostagens', $payload);
    }

    public function solicitarRotuloAssincronoPdf(array $payload): array {
        return $this->requestJson('POST', '/v1/prepostagens/rotulo/assincrono/pdf', $payload);
    }

    public function baixarRotuloPorRecibo(string $idRecibo): array {
        $idRecibo = trim($idRecibo);
        if ($idRecibo === '') {
            return ['success' => false, 'error' => 'idRecibo inválido'];
        }
        return $this->requestJson('GET', '/v1/prepostagens/rotulo/download/assincrono/' . rawurlencode($idRecibo), null);
    }

    private function requestJson(string $method, string $path, ?array $body): array {
        $url = $this->baseUrl . $path;

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($this->token !== '') {
            $val = $this->token;
            if (stripos($val, 'bearer ') !== 0) {
                $val = 'Bearer ' . $val;
            }
            $headers[] = 'Authorization: ' . $val;
        }

        $raw = null;
        $httpCode = null;
        $curlErr = '';

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($body !== null) {
                $jsonBody = json_encode($body);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = (string) curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === null) {
                return [
                    'success' => false,
                    'http_code' => $httpCode,
                    'error' => 'Falha na requisição: ' . $curlErr,
                    'raw' => null,
                ];
            }

            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'http_code' => $httpCode,
                    'error' => 'Resposta inválida (não-JSON).',
                    'raw' => $raw,
                ];
            }

            if (is_int($httpCode) && $httpCode >= 400) {
                $msg = $this->extractErrorMessage($decoded);
                $isGeneric = false;
                if ($msg !== '') {
                    $tmp = trim($msg);
                    if (preg_match('/^[A-Za-z0-9_\\\\]+Exception\:?$/', $tmp)) {
                        $isGeneric = true;
                    }
                }

                if ($msg === '' || $isGeneric) {
                    $msg = 'Erro HTTP ' . $httpCode;
                    $enc = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    if (is_string($enc) && $enc !== '') {
                        $msg .= ': ' . $enc;
                    }
                }
                return [
                    'success' => false,
                    'http_code' => $httpCode,
                    'error' => $msg,
                    'raw' => $decoded,
                ];
            }

            return [
                'success' => true,
                'http_code' => $httpCode,
                'data' => $decoded,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'error' => $e->getMessage(),
                'raw' => $raw,
            ];
        }
    }

    private function extractErrorMessage(array $json): string {
        $cands = [];
        foreach (['message', 'mensagem', 'msg', 'error', 'erro', 'detail', 'title', 'causa'] as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                $cands[] = trim($json[$k]);
            }
        }

        foreach (['exception', 'exceptionMessage', 'exceptionName', 'errorMessage'] as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                $cands[] = trim($json[$k]);
            }
        }

        if (isset($json['msgs']) && is_array($json['msgs'])) {
            $parts = [];
            foreach ($json['msgs'] as $m) {
                if (is_string($m) && trim($m) !== '') {
                    $parts[] = trim($m);
                }
            }
            if (!empty($parts)) {
                $cands[] = implode(' | ', $parts);
            }
        }

        foreach (['erros', 'errors', 'mensagens'] as $k) {
            if (isset($json[$k]) && is_array($json[$k])) {
                $parts = [];
                foreach ($json[$k] as $row) {
                    if (is_string($row) && trim($row) !== '') {
                        $parts[] = trim($row);
                        continue;
                    }
                    if (is_array($row)) {
                        foreach (['mensagem', 'message', 'erro', 'error', 'detail', 'campo', 'field'] as $kk) {
                            if (isset($row[$kk]) && is_string($row[$kk]) && trim($row[$kk]) !== '') {
                                $parts[] = trim($row[$kk]);
                            }
                        }
                    }
                }
                if (!empty($parts)) {
                    $cands[] = implode(' | ', array_values(array_unique($parts)));
                }
            }
        }

        return $cands[0] ?? '';
    }
}
