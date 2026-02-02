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

            if (is_int($httpCode) && $httpCode >= 400) {
                $msg = $this->extractErrorMessage($json);
                return [
                    'success' => false,
                    'error' => $msg !== '' ? $msg : ('Erro HTTP ' . $httpCode . ' ao consultar rastreamento.'),
                    'http_code' => $httpCode,
                    'raw' => $json,
                ];
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

        // Suporte ao endpoint do Packet Service (query string)
        // Ex.: https://api.correios.com.br/packet/v1/packages?trackingNumber=
        if (stripos($baseUrl, 'trackingNumber=') !== false) {
            // Se já terminar com trackingNumber= (ou trackingNumber=%s), apenas concatenar o código
            if (preg_match('/trackingNumber=\s*$/i', $baseUrl)) {
                return $baseUrl . rawurlencode($codigo);
            }

            // Se a URL já tiver o parâmetro preenchido, substitui o valor
            $parts = explode('trackingNumber=', $baseUrl, 2);
            if (count($parts) === 2) {
                $prefix = $parts[0] . 'trackingNumber=';
                // remove valor antigo até & (se houver)
                $suffix = $parts[1];
                $suffix = preg_replace('/^[^&]*/', '', $suffix);
                return $prefix . rawurlencode($codigo) . $suffix;
            }
        }

        // Fallback: tratar como endpoint baseado em path
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
        } elseif (isset($json['packages'][0]['events']) && is_array($json['packages'][0]['events'])) {
            $candidates = $json['packages'][0]['events'];
        } elseif (isset($json['package']['events']) && is_array($json['package']['events'])) {
            $candidates = $json['package']['events'];
        } elseif (isset($json[0]) && is_array($json[0])) {
            $candidates = $json;
        }

        $out = [];
        foreach ($candidates as $ev) {
            if (!is_array($ev)) continue;

            $etapa = (string) ($ev['status'] ?? ($ev['descricao'] ?? ($ev['evento'] ?? ($ev['eventType'] ?? ($ev['eventDescription'] ?? '')))));
            $descricao = (string) ($ev['descricao'] ?? ($ev['detalhe'] ?? ($ev['mensagem'] ?? ($ev['eventDescription'] ?? $etapa))));

            $local = '';
            if (isset($ev['unidade']['nome'])) {
                $local = (string) $ev['unidade']['nome'];
            } elseif (isset($ev['eventLocation'])) {
                $local = (string) $ev['eventLocation'];
            } elseif (isset($ev['location'])) {
                $local = (string) $ev['location'];
            } elseif (isset($ev['local'])) {
                $local = (string) $ev['local'];
            }

            $dataHora = '';
            if (!empty($ev['dataHora'])) {
                $dataHora = (string) $ev['dataHora'];
            } elseif (!empty($ev['eventDate']) && !empty($ev['eventTime'])) {
                $dataHora = (string) ($ev['eventDate'] . ' ' . $ev['eventTime']);
            } elseif (!empty($ev['eventDateTime'])) {
                $dataHora = (string) $ev['eventDateTime'];
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

    private function extractErrorMessage(array $json): string {
        $cands = [];
        foreach (['message', 'mensagem', 'msg', 'msgs', 'error', 'erro', 'detail', 'details', 'title'] as $k) {
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

        if (isset($json['errors']) && is_array($json['errors'])) {
            $parts = [];
            foreach ($json['errors'] as $e) {
                if (is_string($e) && trim($e) !== '') {
                    $parts[] = trim($e);
                } elseif (is_array($e)) {
                    foreach (['message', 'mensagem', 'detail', 'error'] as $k) {
                        if (isset($e[$k]) && is_string($e[$k]) && trim($e[$k]) !== '') {
                            $parts[] = trim($e[$k]);
                            break;
                        }
                    }
                }
            }
            if (!empty($parts)) {
                $cands[] = implode(' | ', $parts);
            }
        }

        return $cands[0] ?? '';
    }
}
