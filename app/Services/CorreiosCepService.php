<?php
namespace App\Services;

class CorreiosCepService {
    public function consultarPorCep(string $cep, array $config): array {
        $cep = preg_replace('/\D+/', '', (string) $cep);
        if (strlen($cep) !== 8) {
            return ['success' => false, 'error' => 'CEP inválido'];
        }

        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($baseUrl === '' || $token === '') {
            return ['success' => false, 'error' => 'Consulta CEP Correios não configurada (base_url/token).'];
        }

        $tokenFingerprint = substr(hash('sha256', $token), 0, 12);

        $base = rtrim($baseUrl, '/');
        $urls = [
            $base . '/v2/enderecos/' . rawurlencode($cep),
            $base . '/v2/enderecos?cep=' . rawurlencode($cep),
            $base . '/v1/enderecos/' . rawurlencode($cep),
            $base . '/v1/enderecos?cep=' . rawurlencode($cep),
        ];
        $makeHeaders = function (string $tok): array {
            return [
                'Accept: application/json',
                'Authorization: ' . (stripos($tok, 'bearer ') === 0 ? $tok : ('Bearer ' . $tok)),
            ];
        };

        $headers = $makeHeaders($token);

        $raw = null;
        $httpCode = null;
        try {
            $lastErr = '';
            $lastUrl = '';
            $didRefresh = false;
            foreach ($urls as $url) {
                $lastUrl = (string) $url;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);

                if ($raw === false || $raw === null) {
                    $lastErr = 'Falha na requisição: ' . $err;
                    continue;
                }

                $json = json_decode($raw, true);
                if (!is_array($json)) {
                    $lastErr = 'Resposta inválida (não-JSON).';
                    continue;
                }

                if (is_int($httpCode) && $httpCode >= 400) {
                    $msg = $this->extractErrorMessage($json);
                    $errMsg = $msg !== '' ? $msg : ('Erro HTTP ' . $httpCode . ' ao consultar CEP.');

                    // Token expirado: tentar renovar automaticamente uma vez
                    if (!$didRefresh && stripos($errMsg, 'GTW-007') !== false) {
                        $didRefresh = true;
                        try {
                            $tokSvc = new CorreiosTokenService();
                            $rTok = $tokSvc->getValidTokenFromSigep('cep');
                            if (!empty($rTok['success']) && !empty($rTok['token'])) {
                                $token = (string) $rTok['token'];
                                $headers = $makeHeaders($token);
                                // tentar novamente o mesmo endpoint com o token renovado
                                $ch2 = curl_init($url);
                                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 15);
                                curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
                                curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                                $raw2 = curl_exec($ch2);
                                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                                $err2 = curl_error($ch2);
                                curl_close($ch2);

                                if ($raw2 === false || $raw2 === null) {
                                    $lastErr = 'Falha na requisição: ' . $err2;
                                    continue;
                                }
                                $json2 = json_decode($raw2, true);
                                if (is_array($json2) && is_int($httpCode2) && $httpCode2 < 400) {
                                    $bairro = $this->extractBairro($json2);
                                    return [
                                        'success' => true,
                                        'http_code' => $httpCode2,
                                        'raw' => $json2,
                                        'bairro' => $bairro,
                                        'request_url' => $lastUrl,
                                        'token_fingerprint' => substr(hash('sha256', $token), 0, 12),
                                    ];
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    // Alguns ambientes retornam 403 GTW-008 para determinados endpoints
                    // mesmo com token válido para outros endpoints. Nesses casos, tentamos
                    // os próximos URLs antes de falhar.
                    if ((int) $httpCode === 403 && stripos($errMsg, 'GTW-008') !== false) {
                        $lastErr = $errMsg;
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => $errMsg,
                        'http_code' => $httpCode,
                        'raw' => $json,
                        'request_url' => $lastUrl,
                        'token_fingerprint' => $tokenFingerprint,
                    ];
                }

                $bairro = $this->extractBairro($json);
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'raw' => $json,
                    'bairro' => $bairro,
                    'request_url' => $lastUrl,
                    'token_fingerprint' => $tokenFingerprint,
                ];
            }

            return [
                'success' => false,
                'error' => $lastErr !== '' ? $lastErr : 'Falha na requisição.',
                'http_code' => $httpCode,
                'raw' => $raw,
                'request_url' => $lastUrl,
                'token_fingerprint' => $tokenFingerprint,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => $httpCode,
                'raw' => $raw,
                'token_fingerprint' => $tokenFingerprint,
            ];
        }
    }

    private function extractBairro(array $json): string {
        // A API pode retornar:
        // - objeto direto {bairro: ...}
        // - lista [ {bairro: ...} ]
        // - paginado {content: [ ... ]}
        // - variações de chaves
        $candidates = [];

        // Alguns endpoints retornam o endereço diretamente no root.
        $candidates[] = $json['bairro'] ?? null;
        $candidates[] = $json['nomeBairro'] ?? null;
        $candidates[] = $json['bairroDescricao'] ?? null;

        // Outros retornam dentro de um nó "endereco".
        if (isset($json['endereco']) && is_array($json['endereco'])) {
            $e = $json['endereco'];
            $candidates[] = $e['bairro'] ?? null;
            $candidates[] = $e['nomeBairro'] ?? null;
            $candidates[] = $e['bairroDescricao'] ?? null;
        }

        // Paginado / lista por v2.
        $lists = [];
        if (isset($json['content']) && is_array($json['content'])) $lists[] = $json['content'];
        if (isset($json['items']) && is_array($json['items'])) $lists[] = $json['items'];
        if (isset($json['results']) && is_array($json['results'])) $lists[] = $json['results'];
        if (isset($json['enderecos']) && is_array($json['enderecos'])) $lists[] = $json['enderecos'];
        if (isset($json[0]) && is_array($json[0])) $lists[] = $json;

        foreach ($lists as $arr) {
            if (!is_array($arr)) continue;
            foreach ($arr as $item) {
                if (!is_array($item)) continue;
                $candidates[] = $item['bairro'] ?? null;
                $candidates[] = $item['nomeBairro'] ?? null;
                $candidates[] = $item['bairroDescricao'] ?? null;
                if (isset($item['endereco']) && is_array($item['endereco'])) {
                    $e = $item['endereco'];
                    $candidates[] = $e['bairro'] ?? null;
                    $candidates[] = $e['nomeBairro'] ?? null;
                    $candidates[] = $e['bairroDescricao'] ?? null;
                }
            }
        }

        foreach ($candidates as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }

    private function extractErrorMessage(array $json): string {
        $keys = ['message', 'mensagem', 'error', 'erro', 'detail', 'descricao', 'msg'];
        foreach ($keys as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                return trim($json[$k]);
            }
        }

        if (isset($json['messages']) && is_array($json['messages']) && isset($json['messages'][0]) && is_string($json['messages'][0])) {
            return trim((string) $json['messages'][0]);
        }

        return '';
    }
}
