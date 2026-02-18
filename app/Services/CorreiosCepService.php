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

        $base = rtrim($baseUrl, '/');
        $urls = [
            $base . '/v1/enderecos?cep=' . rawurlencode($cep),
            $base . '/v1/enderecos/' . rawurlencode($cep),
        ];
        $headers = [
            'Accept: application/json',
            'Authorization: ' . (stripos($token, 'bearer ') === 0 ? $token : ('Bearer ' . $token)),
        ];

        $raw = null;
        $httpCode = null;
        try {
            $lastErr = '';
            foreach ($urls as $url) {
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
                    return [
                        'success' => false,
                        'error' => $msg !== '' ? $msg : ('Erro HTTP ' . $httpCode . ' ao consultar CEP.'),
                        'http_code' => $httpCode,
                        'raw' => $json,
                    ];
                }

                $bairro = $this->extractBairro($json);
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'raw' => $json,
                    'bairro' => $bairro,
                ];
            }

            return ['success' => false, 'error' => $lastErr !== '' ? $lastErr : 'Falha na requisição.', 'http_code' => $httpCode, 'raw' => $raw];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'http_code' => $httpCode, 'raw' => $raw];
        }
    }

    private function extractBairro(array $json): string {
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

        // Outros retornam uma lista.
        if (isset($json[0]) && is_array($json[0])) {
            $candidates[] = $json[0]['bairro'] ?? null;
            $candidates[] = $json[0]['nomeBairro'] ?? null;
            $candidates[] = $json[0]['bairroDescricao'] ?? null;
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
