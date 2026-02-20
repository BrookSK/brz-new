<?php
namespace App\Services;

use Config\Database;

class CorreiosTokenService {
    private const REFRESH_WINDOW_SECONDS = 25 * 60;

    public function getValidTokenFromSigep(string $finalidade = 'geral'): array {
        $cfg = $this->loadSigepCreds();

        // Credenciais do token (Meu Correios) podem ser diferentes do SIGEP
        $usuario = trim((string) $this->getEntregaConfigValue('correios_token_usuario', (string) ($cfg['usuario'] ?? '')));
        $senha = trim((string) $this->getEntregaConfigValue('correios_token_senha', (string) ($cfg['senha'] ?? '')));
        $cartao = (string) ($cfg['cartao'] ?? '');
        $contrato = (string) ($cfg['contrato'] ?? '');
        $ambiente = $this->resolveTokenAmbiente((string) ($cfg['ambiente'] ?? 'homologacao'));

        if (trim($usuario) === '' || trim($senha) === '' || trim($cartao) === '') {
            return ['success' => false, 'error' => 'Configuração incompleta para gerar token (usuario/senha do Meu Correios + cartão de postagem).'];
        }

        $existingToken = (string) $this->getEntregaConfigValue('correios_token', '');
        $existingExp = (string) $this->getEntregaConfigValue('correios_token_expira_em', '');

        if ($existingToken !== '' && $existingExp !== '') {
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

        $resp = $this->requestNewTokenCartaoPostagem($usuario, $senha, $cartao, $contrato, $ambiente);
        if (empty($resp['success'])) {
            $errMsg = (string) ($resp['error'] ?? '');
            // Fallback: quando a autorização é por contrato e não por cartão
            if ($contrato !== '' && stripos($errMsg, 'TOK-003') !== false) {
                $resp2 = $this->requestNewTokenContrato($usuario, $senha, $contrato, $ambiente);
                if (!empty($resp2['success'])) {
                    $resp = $resp2;
                }
            }
        }
        if (empty($resp['success'])) {
            return $resp;
        }

        $token = (string) ($resp['token'] ?? '');
        $expiraEm = (string) ($resp['expiraEm'] ?? '');

        if ($token !== '') {
            $this->setEntregaConfigValue('correios_token', $token);
            if ($expiraEm !== '') {
                $this->setEntregaConfigValue('correios_token_expira_em', $expiraEm);
            }

            // Compat: preencher tokens já existentes usados pelos serviços
            $this->setEntregaConfigValue('correios_tracking_token', $token);
            $this->setEntregaConfigValue('correios_prepostagem_token', $token);
            $this->setEntregaConfigValue('correios_cep_token', $token);
        }

        return [
            'success' => true,
            'token' => $token,
            'expiraEm' => $expiraEm,
            'source' => 'api',
        ];
    }

    private function resolveTokenAmbiente(string $fallback): string {
        $override = strtolower(trim((string) $this->getEntregaConfigValue('correios_token_ambiente', '')));
        if ($override === 'producao' || $override === 'homologacao') {
            return $override;
        }

        $trackingBaseUrl = strtolower(trim((string) $this->getEntregaConfigValue('correios_tracking_base_url', '')));
        if ($trackingBaseUrl !== '') {
            if (strpos($trackingBaseUrl, 'api.correios.com.br') !== false) {
                return 'producao';
            }
            if (strpos($trackingBaseUrl, 'apihom.correios.com.br') !== false) {
                return 'homologacao';
            }
        }

        $fallback = strtolower(trim($fallback));
        return ($fallback === 'producao' || $fallback === 'homologacao') ? $fallback : 'homologacao';
    }

    private function requestNewTokenCartaoPostagem(string $usuario, string $senha, string $cartao, string $contrato, string $ambiente): array {
        $isProd = (strtolower(trim($ambiente)) === 'producao');
        $bases = $isProd
            ? ['https://api.correios.com.br/token', 'https://apihom.correios.com.br/token']
            : ['https://apihom.correios.com.br/token', 'https://api.correios.com.br/token'];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $cartao = preg_replace('/\D+/', '', (string) $cartao);
        $contrato = preg_replace('/\D+/', '', (string) $contrato);

        $payload = [
            'numero' => $cartao,
            'dr' => 0,
        ];
        if (trim($contrato) !== '') {
            $payload['contrato'] = $contrato;
        }

        $raw = null;
        $httpCode = null;
        $lastErr = '';
        $lastMeta = [];
        $attemptErrors = [];

        foreach ($bases as $base) {
            $url = rtrim($base, '/') . '/v1/autentica/cartaopostagem';
            try {
                $respHeaders = [];
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
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$respHeaders) {
                    $len = strlen($line);
                    $line = trim($line);
                    if ($line === '' || strpos($line, ':') === false) {
                        return $len;
                    }
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $v = trim($v);
                    if ($k !== '') {
                        if (!isset($respHeaders[$k])) {
                            $respHeaders[$k] = $v;
                        }
                    }
                    return $len;
                });
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
                $err = curl_error($ch);
                curl_close($ch);

                $lastMeta = [
                    'request_url' => $url,
                    'http_code' => $httpCode,
                    'content_type' => $contentType,
                ];

                if ($raw === false || $raw === null) {
                    $lastErr = 'Falha na requisição de token: ' . $err;
                    continue;
                }

                $json = json_decode((string) $raw, true);
                if (!is_array($json)) {
                    $snippet = substr(trim((string) $raw), 0, 400);
                    if ($snippet === '') {
                        $snippet = '<empty>';
                    }
                    if ($contentType === '') {
                        $contentType = 'n/a';
                    }
                    $hdrSnippet = '';
                    if (is_array($respHeaders) && !empty($respHeaders)) {
                        $allow = ['www-authenticate', 'server', 'date', 'x-request-id', 'x-correlation-id'];
                        $parts = [];
                        foreach ($allow as $hk) {
                            if (isset($respHeaders[$hk]) && $respHeaders[$hk] !== '') {
                                $parts[] = $hk . '=' . $respHeaders[$hk];
                            }
                        }
                        if (!empty($parts)) {
                            $hdrSnippet = ' HDR=' . implode(';', $parts);
                        }
                    }
                    $errMsg = 'Resposta inválida (não-JSON) ao solicitar token.'
                        . (is_int($httpCode) ? (' HTTP ' . $httpCode) : '')
                        . ' CT=' . $contentType
                        . ' BODY=' . $snippet
                        . $hdrSnippet
                        . ' URL=' . $url;
                    $attemptErrors[] = $errMsg;
                    if ($lastErr === '') {
                        $lastErr = $errMsg;
                    }
                    continue;
                }

                if (is_int($httpCode) && $httpCode >= 400) {
                    $msg = $this->extractErrorMessage($json);
                    $errMsg = $msg !== '' ? $msg : ('Erro HTTP ' . $httpCode . ' ao solicitar token.');
                    if ((int) $httpCode === 401) {
                        $errMsg .= ' (Credenciais Basic inválidas. Verifique usuário/senha do Meu Correios nas configurações.)';
                    }
                    $lastMeta['raw'] = $json;
                    $attemptErrors[] = $errMsg . ' URL=' . $url;
                    // Preferir erro em JSON (mais confiável) ao invés de sobrescrever por tentativas seguintes
                    $lastErr = $errMsg;
                    continue;
                }

                $token = (string) ($json['token'] ?? '');
                $expiraEm = (string) ($json['expiraEm'] ?? '');

                if (trim($token) === '') {
                    $lastErr = 'Resposta sem token.';
                    $lastMeta['raw'] = $json;
                    continue;
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
                $errMsg = $e->getMessage();
                $attemptErrors[] = $errMsg . ' URL=' . $url;
                if ($lastErr === '') {
                    $lastErr = $errMsg;
                }
                $lastMeta['request_url'] = $url;
                continue;
            }
        }

        return array_merge([
            'success' => false,
            'error' => ($lastErr !== '' ? $lastErr : 'Falha ao solicitar token.'),
            'attempt_errors' => $attemptErrors,
        ], $lastMeta);
    }

    private function requestNewTokenContrato(string $usuario, string $senha, string $contrato, string $ambiente): array {
        $isProd = (strtolower(trim($ambiente)) === 'producao');
        $bases = $isProd
            ? ['https://api.correios.com.br/token', 'https://apihom.correios.com.br/token']
            : ['https://apihom.correios.com.br/token', 'https://api.correios.com.br/token'];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $contrato = preg_replace('/\D+/', '', (string) $contrato);
        $payload = [
            'numero' => $contrato,
            'dr' => 0,
        ];

        $raw = null;
        $httpCode = null;
        $lastErr = '';
        $lastMeta = [];
        $attemptErrors = [];

        foreach ($bases as $base) {
            $url = rtrim($base, '/') . '/v1/autentica/contrato';
            try {
                $respHeaders = [];
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
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$respHeaders) {
                    $len = strlen($line);
                    $line = trim($line);
                    if ($line === '' || strpos($line, ':') === false) {
                        return $len;
                    }
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $v = trim($v);
                    if ($k !== '') {
                        if (!isset($respHeaders[$k])) {
                            $respHeaders[$k] = $v;
                        }
                    }
                    return $len;
                });
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
                $err = curl_error($ch);
                curl_close($ch);

                $lastMeta = [
                    'request_url' => $url,
                    'http_code' => $httpCode,
                    'content_type' => $contentType,
                ];

                if ($raw === false || $raw === null) {
                    $lastErr = 'Falha na requisição de token: ' . $err;
                    continue;
                }

                $json = json_decode((string) $raw, true);
                if (!is_array($json)) {
                    $snippet = substr(trim((string) $raw), 0, 400);
                    if ($snippet === '') {
                        $snippet = '<empty>';
                    }
                    if ($contentType === '') {
                        $contentType = 'n/a';
                    }
                    $hdrSnippet = '';
                    if (is_array($respHeaders) && !empty($respHeaders)) {
                        $allow = ['www-authenticate', 'server', 'date', 'x-request-id', 'x-correlation-id'];
                        $parts = [];
                        foreach ($allow as $hk) {
                            if (isset($respHeaders[$hk]) && $respHeaders[$hk] !== '') {
                                $parts[] = $hk . '=' . $respHeaders[$hk];
                            }
                        }
                        if (!empty($parts)) {
                            $hdrSnippet = ' HDR=' . implode(';', $parts);
                        }
                    }
                    $errMsg = 'Resposta inválida (não-JSON) ao solicitar token.'
                        . (is_int($httpCode) ? (' HTTP ' . $httpCode) : '')
                        . ' CT=' . $contentType
                        . ' BODY=' . $snippet
                        . $hdrSnippet
                        . ' URL=' . $url;
                    $attemptErrors[] = $errMsg;
                    if ($lastErr === '') {
                        $lastErr = $errMsg;
                    }
                    continue;
                }

                if (is_int($httpCode) && $httpCode >= 400) {
                    $msg = $this->extractErrorMessage($json);
                    $errMsg = $msg !== '' ? $msg : ('Erro HTTP ' . $httpCode . ' ao solicitar token.');
                    if ((int) $httpCode === 401) {
                        $errMsg .= ' (Credenciais Basic inválidas. Verifique usuário/senha do Meu Correios nas configurações.)';
                    }
                    $lastMeta['raw'] = $json;
                    $attemptErrors[] = $errMsg . ' URL=' . $url;
                    $lastErr = $errMsg;
                    continue;
                }

                $token = (string) ($json['token'] ?? '');
                $expiraEm = (string) ($json['expiraEm'] ?? '');

                if (trim($token) === '') {
                    $lastErr = 'Resposta sem token.';
                    $lastMeta['raw'] = $json;
                    continue;
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
                $errMsg = $e->getMessage();
                $attemptErrors[] = $errMsg . ' URL=' . $url;
                if ($lastErr === '') {
                    $lastErr = $errMsg;
                }
                $lastMeta['request_url'] = $url;
                continue;
            }
        }

        return array_merge([
            'success' => false,
            'error' => ($lastErr !== '' ? $lastErr : 'Falha ao solicitar token.'),
            'attempt_errors' => $attemptErrors,
        ], $lastMeta);
    }

    private function loadSigepCreds(): array {
        $enabled = (string) $this->getEntregaConfigValue('sigep_enabled', '0');
        return [
            'enabled' => $enabled === '1',
            'ambiente' => (string) $this->getEntregaConfigValue('sigep_ambiente', 'homologacao'),
            'usuario' => (string) $this->getEntregaConfigValue('sigep_usuario', ''),
            'senha' => (string) $this->getEntregaConfigValue('sigep_senha', ''),
            'contrato' => (string) $this->getEntregaConfigValue('sigep_numero_contrato', ''),
            'cartao' => (string) $this->getEntregaConfigValue('sigep_cartao_postagem', ''),
        ];
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
                if (in_array('entrega_' . $key, $cols, true)) {
                    $col = 'entrega_' . $key;
                } elseif (in_array($key, $cols, true)) {
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
                if (in_array('entrega_' . $key, $cols, true)) {
                    $col = 'entrega_' . $key;
                } elseif (in_array($key, $cols, true)) {
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
