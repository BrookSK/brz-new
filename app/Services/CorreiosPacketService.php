<?php
namespace App\Services;

use Config\Database;

class CorreiosPacketService {
    private const REFRESH_WINDOW_SECONDS = 25 * 60;

    public function createPackages(array $packageList): array {
        $cfg = $this->loadPacketConfig();

        if (empty($cfg['login']) || empty($cfg['password']) || empty($cfg['cartao_postagem'])) {
            return ['success' => false, 'error' => 'Correios Mundial (PACKET) não configurado (login/senha/cartão de postagem).'];
        }
        if (empty($packageList)) {
            return ['success' => false, 'error' => 'packageList vazio.'];
        }

        $tokenResp = $this->getValidToken($cfg);
        if (empty($tokenResp['success']) || empty($tokenResp['token'])) {
            return ['success' => false, 'error' => (string) ($tokenResp['error'] ?? 'Falha ao obter token.'), 'meta' => $tokenResp];
        }

        $token = (string) $tokenResp['token'];
        $baseUrl = $this->getPacketBaseUrl((string) ($cfg['ambiente'] ?? 'homologacao'));
        $url = rtrim($baseUrl, '/') . '/v1/packages';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $payload = ['packageList' => $packageList];

        $raw = null;
        $httpCode = null;
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERAGENT, 'brz-new/1.0 (+https://brazilianashop.com)');
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === null) {
                return ['success' => false, 'error' => 'Falha na requisição: ' . $err, 'http_code' => $httpCode, 'request_url' => $url];
            }

            $json = json_decode((string) $raw, true);
            if (!is_array($json)) {
                return ['success' => false, 'error' => 'Resposta inválida (não-JSON).', 'http_code' => $httpCode, 'raw' => $raw, 'request_url' => $url];
            }

            if (is_int($httpCode) && $httpCode >= 400) {
                $msg = $this->extractErrorMessage($json);
                if ($msg === '') {
                    $msg = 'Erro HTTP ' . $httpCode;
                }
                return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'raw' => $json, 'request_url' => $url];
            }

            return [
                'success' => true,
                'http_code' => $httpCode,
                'request_url' => $url,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'http_code' => $httpCode, 'raw' => $raw, 'request_url' => $url];
        }
    }

    public function getBalance(): array {
        $cfg = $this->loadPacketConfig();

        if (empty($cfg['login']) || empty($cfg['password']) || empty($cfg['cartao_postagem'])) {
            return ['success' => false, 'error' => 'Correios Mundial (PACKET) não configurado (login/senha/cartão de postagem).'];
        }

        $tokenResp = $this->getValidToken($cfg);
        if (empty($tokenResp['success']) || empty($tokenResp['token'])) {
            return ['success' => false, 'error' => (string) ($tokenResp['error'] ?? 'Falha ao obter token.'), 'meta' => $tokenResp];
        }

        $token = (string) $tokenResp['token'];
        $baseUrl = $this->getPacketBaseUrl((string) ($cfg['ambiente'] ?? 'homologacao'));
        $url = rtrim($baseUrl, '/') . '/v1/balance';

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $raw = null;
        $httpCode = null;
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERAGENT, 'brz-new/1.0 (+https://brazilianashop.com)');
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === null) {
                return ['success' => false, 'error' => 'Falha na requisição: ' . $err, 'http_code' => $httpCode];
            }

            $json = json_decode((string) $raw, true);
            if (!is_array($json)) {
                return ['success' => false, 'error' => 'Resposta inválida (não-JSON).', 'http_code' => $httpCode, 'raw' => $raw];
            }

            if (is_int($httpCode) && $httpCode >= 400) {
                $msg = $this->extractErrorMessage($json);
                if ($msg === '') {
                    $msg = 'Erro HTTP ' . $httpCode;
                }

                // Token expirado/inválido: tentar renovar uma vez e repetir
                if (in_array((int) $httpCode, [401, 403], true)) {
                    try {
                        $this->setEntregaConfigValue('correios_packet_token', '');
                        $this->setEntregaConfigValue('correios_packet_token_expira_em', '');
                        $tokenResp2 = $this->getValidToken($cfg, true);
                        if (!empty($tokenResp2['success']) && !empty($tokenResp2['token'])) {
                            $token2 = (string) $tokenResp2['token'];
                            $headers2 = [
                                'Accept: application/json',
                                'Authorization: Bearer ' . $token2,
                            ];

                            $ch2 = curl_init($url);
                            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 15);
                            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'GET');
                            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers2);
                            curl_setopt($ch2, CURLOPT_USERAGENT, 'brz-new/1.0 (+https://brazilianashop.com)');
                            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                            curl_setopt($ch2, CURLOPT_ENCODING, '');
                            $raw2 = curl_exec($ch2);
                            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                            $err2 = curl_error($ch2);
                            curl_close($ch2);

                            if ($raw2 === false || $raw2 === null) {
                                return ['success' => false, 'error' => 'Falha na requisição: ' . $err2, 'http_code' => $httpCode2];
                            }

                            $json2 = json_decode((string) $raw2, true);
                            if (is_array($json2) && is_int($httpCode2) && $httpCode2 < 400) {
                                return [
                                    'success' => true,
                                    'currentBalance' => $json2['currentBalance'] ?? null,
                                    'raw' => $json2,
                                    'http_code' => $httpCode2,
                                    'request_url' => $url,
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }

                return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'raw' => $json, 'request_url' => $url];
            }

            return [
                'success' => true,
                'currentBalance' => $json['currentBalance'] ?? null,
                'raw' => $json,
                'http_code' => $httpCode,
                'request_url' => $url,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'http_code' => $httpCode, 'raw' => $raw, 'request_url' => $url];
        }
    }

    private function getPacketBaseUrl(string $ambiente): string {
        $amb = strtolower(trim($ambiente));
        if ($amb === 'producao' || $amb === 'production') {
            return 'https://api.correios.com.br/packet';
        }
        return 'https://apihom.correios.com.br/packet';
    }

    private function getTokenBaseUrl(string $ambiente): string {
        $amb = strtolower(trim($ambiente));
        if ($amb === 'producao' || $amb === 'production') {
            return 'https://api.correios.com.br/token';
        }
        return 'https://apihom.correios.com.br/token';
    }

    private function loadPacketConfig(): array {
        $ambiente = (string) $this->getEntregaConfigValue('correios_packet_ambiente', 'homologacao');
        return [
            'ambiente' => $ambiente,
            'login' => (string) $this->getEntregaConfigValue('correios_packet_login', ''),
            'password' => (string) $this->getEntregaConfigValue('correios_packet_password', ''),
            'cartao_postagem' => (string) $this->getEntregaConfigValue('correios_packet_cartao_postagem', ''),
        ];
    }

    private function getValidToken(array $cfg, bool $forceRefresh = false): array {
        $existingToken = (string) $this->getEntregaConfigValue('correios_packet_token', '');
        $existingExp = (string) $this->getEntregaConfigValue('correios_packet_token_expira_em', '');

        if (!$forceRefresh && $existingToken !== '' && $existingExp !== '') {
            $expTs = strtotime($existingExp);
            if ($expTs !== false) {
                $now = time();
                if ($expTs - $now > self::REFRESH_WINDOW_SECONDS) {
                    return [
                        'success' => true,
                        'token' => $existingToken,
                        'expiraEm' => $existingExp,
                        'source' => 'cache',
                    ];
                }
            }
        }

        $resp = $this->requestNewToken(
            (string) ($cfg['login'] ?? ''),
            (string) ($cfg['password'] ?? ''),
            (string) ($cfg['cartao_postagem'] ?? ''),
            (string) ($cfg['ambiente'] ?? 'homologacao')
        );

        if (empty($resp['success'])) {
            return $resp;
        }

        $token = (string) ($resp['token'] ?? '');
        $expiraEm = (string) ($resp['expiraEm'] ?? '');

        if ($token !== '') {
            $this->setEntregaConfigValue('correios_packet_token', $token);
            if ($expiraEm !== '') {
                $this->setEntregaConfigValue('correios_packet_token_expira_em', $expiraEm);
            }
        }

        return [
            'success' => true,
            'token' => $token,
            'expiraEm' => $expiraEm,
            'source' => 'api',
        ];
    }

    private function requestNewToken(string $usuario, string $senha, string $cartaoPostagem, string $ambiente): array {
        $base = $this->getTokenBaseUrl($ambiente);
        $url = rtrim($base, '/') . '/v1/autentica/cartaopostagem';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $cartao = preg_replace('/\D+/', '', (string) $cartaoPostagem);
        $payload = [
            'numero' => $cartao,
        ];

        $raw = null;
        $httpCode = null;
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $usuario . ':' . $senha);
            curl_setopt($ch, CURLOPT_USERAGENT, 'brz-new/1.0 (+https://brazilianashop.com)');
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === null) {
                return ['success' => false, 'error' => 'Falha na requisição de token: ' . $err, 'http_code' => $httpCode, 'request_url' => $url];
            }

            $json = json_decode((string) $raw, true);
            if (!is_array($json)) {
                $snippet = substr(trim((string) $raw), 0, 400);
                if ($snippet === '') {
                    $snippet = '<empty>';
                }
                return ['success' => false, 'error' => 'Resposta inválida (não-JSON) ao solicitar token. BODY=' . $snippet, 'http_code' => $httpCode, 'request_url' => $url];
            }

            if (is_int($httpCode) && $httpCode >= 400) {
                $msg = $this->extractErrorMessage($json);
                if ($msg === '') {
                    $msg = 'Erro HTTP ' . $httpCode . ' ao solicitar token.';
                }
                if ((int) $httpCode === 401) {
                    $msg .= ' (Credenciais inválidas)';
                }
                return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'request_url' => $url, 'raw' => $json];
            }

            $token = (string) ($json['token'] ?? '');
            $expiraEm = (string) ($json['expiraEm'] ?? '');

            if (trim($token) === '') {
                return ['success' => false, 'error' => 'Resposta sem token.', 'http_code' => $httpCode, 'request_url' => $url, 'raw' => $json];
            }

            return [
                'success' => true,
                'token' => $token,
                'expiraEm' => $expiraEm,
                'http_code' => $httpCode,
                'request_url' => $url,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'http_code' => $httpCode, 'request_url' => $url, 'raw' => $raw];
        }
    }

    private function extractErrorMessage(array $json): string {
        foreach (['message', 'mensagem', 'msg', 'error', 'erro', 'detail', 'title', 'causa'] as $k) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                return trim($json[$k]);
            }
        }
        if (isset($json['msgs']) && is_array($json['msgs'])) {
            foreach ($json['msgs'] as $m) {
                if (is_string($m) && trim($m) !== '') {
                    return trim($m);
                }
            }
        }
        return '';
    }

    private function getEntregaConfigValue(string $key, string $default = ''): string {
        try {
            $pdo = Database::getConnection();
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];
            $mode = (string) ($tableInfo['mode'] ?? '');

            if ($mode === 'single_row') {
                $cols = [];
                try {
                    $st = $pdo->query('DESCRIBE ' . $table);
                    $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $cols = [];
                }

                $col = null;
                if (is_array($cols) && in_array('entrega_' . $key, $cols, true)) {
                    $col = 'entrega_' . $key;
                } elseif (is_array($cols) && in_array($key, $cols, true)) {
                    $col = $key;
                }
                if (!$col) {
                    return $default;
                }

                $idCol = (string) ($tableInfo['idCol'] ?? 'id');
                $stmt = $pdo->query('SELECT ' . $col . ' FROM ' . $table . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                $v = $stmt ? $stmt->fetchColumn() : false;
                return ($v === false || $v === null) ? $default : (string) $v;
            }

            if ($mode === 'categoria_chave') {
                $stmt = $pdo->prepare('SELECT ' . $tableInfo['valueCol'] . ' FROM ' . $table . ' WHERE ' . $tableInfo['categoriaCol'] . ' = ? AND ' . $tableInfo['chaveCol'] . ' = ? LIMIT 1');
                $stmt->execute(['entrega', $key]);
                $v = $stmt->fetchColumn();
                if ($v !== false && $v !== null) return (string) $v;

                $stmt = $pdo->prepare('SELECT ' . $tableInfo['valueCol'] . ' FROM ' . $table . ' WHERE ' . $tableInfo['categoriaCol'] . ' = ? AND ' . $tableInfo['chaveCol'] . ' = ? LIMIT 1');
                $stmt->execute(['entrega', 'entrega_' . $key]);
                $v = $stmt->fetchColumn();
                return ($v === false || $v === null) ? $default : (string) $v;
            }

            // chave_valor
            $fullKey = 'entrega_' . $key;
            $stmt = $pdo->prepare('SELECT ' . $tableInfo['valueCol'] . ' FROM ' . $table . ' WHERE ' . $tableInfo['keyCol'] . ' = ? LIMIT 1');
            $stmt->execute([$fullKey]);
            $v = $stmt->fetchColumn();
            if ($v !== false && $v !== null) return (string) $v;

            $stmt = $pdo->prepare('SELECT ' . $tableInfo['valueCol'] . ' FROM ' . $table . ' WHERE ' . $tableInfo['keyCol'] . ' = ? LIMIT 1');
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return ($v === false || $v === null) ? $default : (string) $v;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function setEntregaConfigValue(string $key, string $value): void {
        try {
            $pdo = Database::getConnection();
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];
            $mode = (string) ($tableInfo['mode'] ?? '');

            if ($mode === 'single_row') {
                $cols = [];
                try {
                    $st = $pdo->query('DESCRIBE ' . $table);
                    $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $cols = [];
                }

                $col = null;
                if (is_array($cols) && in_array('entrega_' . $key, $cols, true)) {
                    $col = 'entrega_' . $key;
                } elseif (is_array($cols) && in_array($key, $cols, true)) {
                    $col = $key;
                }
                if (!$col) {
                    return;
                }

                $idCol = (string) ($tableInfo['idCol'] ?? 'id');
                $id = null;
                try {
                    $st = $pdo->query('SELECT ' . $idCol . ' FROM ' . $table . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $id = $st ? $st->fetchColumn() : null;
                } catch (\Exception $e) {
                    $id = null;
                }
                if (!$id) {
                    return;
                }

                $stmt = $pdo->prepare('UPDATE ' . $table . ' SET ' . $col . ' = ? WHERE ' . $idCol . ' = ?');
                $stmt->execute([$value, $id]);
                return;
            }

            if ($mode === 'categoria_chave') {
                $catCol = $tableInfo['categoriaCol'];
                $keyCol = $tableInfo['chaveCol'];
                $valCol = $tableInfo['valueCol'];

                $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ?');
                $stmt->execute(['entrega', $key]);
                $exists = (int) ($stmt->fetchColumn() ?: 0) > 0;

                if ($exists) {
                    $stmt = $pdo->prepare('UPDATE ' . $table . ' SET ' . $valCol . ' = ? WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ?');
                    $stmt->execute([$value, 'entrega', $key]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . $catCol . ', ' . $keyCol . ', ' . $valCol . ') VALUES (?, ?, ?)');
                    $stmt->execute(['entrega', $key, $value]);
                }
                return;
            }

            // chave_valor
            $keyCol = $tableInfo['keyCol'];
            $valCol = $tableInfo['valueCol'];
            $fullKey = 'entrega_' . $key;

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $keyCol . ' = ?');
            $stmt->execute([$fullKey]);
            $exists = (int) ($stmt->fetchColumn() ?: 0) > 0;

            if ($exists) {
                $stmt = $pdo->prepare('UPDATE ' . $table . ' SET ' . $valCol . ' = ? WHERE ' . $keyCol . ' = ?');
                $stmt->execute([$value, $fullKey]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . $keyCol . ', ' . $valCol . ') VALUES (?, ?)');
                $stmt->execute([$fullKey, $value]);
            }
        } catch (\Exception $e) {
            return;
        }
    }

    private function getConfigTableInfo(\PDO $pdo): array {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        foreach ($tableCandidates as $t) {
            try {
                $st = $pdo->query("SHOW TABLES LIKE '" . $t . "'");
                if ($st && $st->fetchColumn()) {
                    $table = $t;
                    break;
                }
            } catch (\Exception $e) {
            }
        }

        if (!$table) {
            return ['table' => 'configuracoes_sistema', 'mode' => 'single_row', 'idCol' => 'id'];
        }

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE ' . $table);
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $colNames = array_map(fn($c) => (string) ($c['Field'] ?? ''), $cols);
        $hasCategoria = in_array('categoria', $colNames, true);
        $hasChave = in_array('chave', $colNames, true);
        $hasValor = in_array('valor', $colNames, true);

        if ($hasCategoria && $hasChave && $hasValor) {
            return [
                'table' => $table,
                'mode' => 'categoria_chave',
                'categoriaCol' => 'categoria',
                'chaveCol' => 'chave',
                'valueCol' => 'valor',
            ];
        }

        if (in_array('chave', $colNames, true) && $hasValor) {
            return [
                'table' => $table,
                'mode' => 'chave_valor',
                'keyCol' => 'chave',
                'valueCol' => 'valor',
            ];
        }

        $idCol = in_array('id', $colNames, true) ? 'id' : ($colNames[0] ?? 'id');
        return [
            'table' => $table,
            'mode' => 'single_row',
            'idCol' => $idCol,
        ];
    }
}
