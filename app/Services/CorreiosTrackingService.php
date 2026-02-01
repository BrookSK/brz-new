<?php
namespace App\Services;

class CorreiosTrackingService {
    public function rastrear(string $codigo, array $config): array {
        $codigo = strtoupper(trim($codigo));
        $enabled = (string) ($config['enabled'] ?? '0');
        if ($enabled !== '1') {
            return ['success' => false, 'error' => 'Rastreamento dos Correios está desabilitado.'];
        }

        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $headerName = trim((string) ($config['header'] ?? 'Authorization'));
        if ($baseUrl === '' || $token === '') {
            return ['success' => false, 'error' => 'Rastreamento dos Correios não configurado (base_url/token).'];
        }

        $url = $this->buildUrl($baseUrl, $codigo);
        $headers = $this->buildHeaders($headerName, $token);

        $raw = null;
        $httpCode = null;
        try {
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
                return ['success' => false, 'error' => 'Falha na requisição: ' . $err, 'http_code' => $httpCode];
            }

            $json = json_decode($raw, true);
            if (!is_array($json)) {
                return ['success' => false, 'error' => 'Resposta inválida (não-JSON).', 'http_code' => $httpCode, 'raw' => $raw];
            }

            $eventos = $this->extractEventos($json);

            return [
                'success' => true,
                'codigo' => $codigo,
                'http_code' => $httpCode,
                'eventos' => $eventos,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'http_code' => $httpCode, 'raw' => $raw];
        }
    }

    private function buildUrl(string $baseUrl, string $codigo): string {
        if (strpos($baseUrl, '{codigo}') !== false) {
            return str_replace('{codigo}', rawurlencode($codigo), $baseUrl);
        }

        $u = rtrim($baseUrl, '/');
        return $u . '/' . rawurlencode($codigo);
    }

    private function buildHeaders(string $headerName, string $token): array {
        $headers = ['Accept: application/json'];
        $val = $token;
        if (strcasecmp($headerName, 'Authorization') === 0 && stripos($token, 'bearer ') !== 0) {
            $val = 'Bearer ' . $token;
        }

        $headers[] = $headerName . ': ' . $val;
        return $headers;
    }

    private function extractEventos(array $json): array {
        $candidates = [];

        if (isset($json['eventos']) && is_array($json['eventos'])) {
            $candidates = $json['eventos'];
        } elseif (isset($json['objetos'][0]['eventos']) && is_array($json['objetos'][0]['eventos'])) {
            $candidates = $json['objetos'][0]['eventos'];
        } elseif (isset($json[0]) && is_array($json[0])) {
            $candidates = $json;
        }

        $out = [];
        foreach ($candidates as $ev) {
            if (!is_array($ev)) continue;

            $etapa = (string) ($ev['status'] ?? ($ev['descricao'] ?? ($ev['evento'] ?? '')));
            $descricao = (string) ($ev['descricao'] ?? ($ev['detalhe'] ?? ($ev['mensagem'] ?? $etapa)));

            $local = '';
            if (isset($ev['unidade']['nome'])) {
                $local = (string) $ev['unidade']['nome'];
            } elseif (isset($ev['local'])) {
                $local = (string) $ev['local'];
            }

            $dataHora = '';
            if (!empty($ev['dataHora'])) {
                $dataHora = (string) $ev['dataHora'];
            } elseif (!empty($ev['data']) && !empty($ev['hora'])) {
                $dataHora = (string) ($ev['data'] . ' ' . $ev['hora']);
            } elseif (!empty($ev['data_hora'])) {
                $dataHora = (string) $ev['data_hora'];
            }

            $out[] = [
                'etapa' => $etapa,
                'descricao' => $descricao,
                'local' => $local,
                'data_hora' => $dataHora,
            ];
        }

        return $out;
    }
}
