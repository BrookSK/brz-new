<?php
namespace App\Services;

class EmailService {
    private function getConfigTableInfo(\PDO $pdo): array {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        foreach ($tableCandidates as $t) {
            try {
                $stmtTable = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmtTable->execute([$t]);
                if ($stmtTable->fetchColumn()) {
                    $table = $t;
                    break;
                }
            } catch (\Exception $e) {
            }
        }

        if (!$table) {
            return [];
        }

        $stmt = $pdo->query('DESCRIBE ' . $table);
        $describeRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $cols = [];
        foreach ($describeRows as $r) {
            $field = (string) ($r['Field'] ?? '');
            if ($field !== '') {
                $cols[] = $field;
            }
        }

        $hasCategoria = in_array('categoria', $cols, true);
        $hasChave = in_array('chave', $cols, true);
        if ($hasCategoria && $hasChave) {
            $valueCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : null);
            if (!$valueCol) {
                return ['table' => $table, 'mode' => 'categoria_chave'];
            }
            $updatedAtCol = in_array('updated_at', $cols, true) ? 'updated_at' : '';
            return [
                'table' => $table,
                'mode' => 'categoria_chave',
                'categoriaCol' => 'categoria',
                'chaveCol' => 'chave',
                'valueCol' => $valueCol,
                'updatedAtCol' => $updatedAtCol,
            ];
        }

        if (!$hasCategoria && !$hasChave && in_array('id', $cols, true)) {
            return [
                'table' => $table,
                'mode' => 'single_row',
                'idCol' => 'id',
                'idVal' => 1,
                'cols' => $cols,
            ];
        }

        $keyCandidates = ['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'];
        $valueCandidates = ['valor', 'value', 'conteudo', 'content', 'config_value'];

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
        if (!$keyCol || !$valueCol) {
            return [];
        }

        $updatedAtCol = in_array('updated_at', $cols, true) ? 'updated_at' : '';
        return [
            'table' => $table,
            'mode' => 'chave_valor',
            'keyCol' => $keyCol,
            'valueCol' => $valueCol,
            'updatedAtCol' => $updatedAtCol,
        ];
    }

    private function getEmailConfigFromDb(\PDO $pdo): array {
        $ti = $this->getConfigTableInfo($pdo);
        if (!is_array($ti) || empty($ti['table']) || empty($ti['mode'])) {
            return [];
        }

        $mode = (string) $ti['mode'];
        $table = (string) $ti['table'];

        $getByFullKey = function (string $fullKey) use ($pdo, $ti, $table): ?string {
            $keyCol = (string) ($ti['keyCol'] ?? '');
            $valueCol = (string) ($ti['valueCol'] ?? '');
            if ($keyCol === '' || $valueCol === '') {
                return null;
            }
            $st = $pdo->prepare("SELECT {$valueCol} FROM {$table} WHERE {$keyCol} = ? ORDER BY {$keyCol} DESC LIMIT 1");
            $st->execute([$fullKey]);
            $v = $st->fetchColumn();
            return $v !== false ? (string) $v : null;
        };

        $cfg = [];

        if ($mode === 'categoria_chave') {
            $catCol = (string) ($ti['categoriaCol'] ?? 'categoria');
            $keyCol = (string) ($ti['chaveCol'] ?? 'chave');
            $valueCol = (string) ($ti['valueCol'] ?? 'valor');
            $get = function (string $chave) use ($pdo, $table, $catCol, $keyCol, $valueCol): ?string {
                $st = $pdo->prepare("SELECT {$valueCol} FROM {$table} WHERE {$catCol} = 'email' AND {$keyCol} = ? LIMIT 1");
                $st->execute([$chave]);
                $v = $st->fetchColumn();
                return $v !== false ? (string) $v : null;
            };

            $cfg['driver'] = $get('driver');
            $cfg['host'] = $get('host');
            $cfg['port'] = $get('port');
            $cfg['username'] = $get('username');
            $cfg['password'] = $get('password');
            $cfg['encryption'] = $get('encryption');
            $cfg['from'] = $get('from');
            $cfg['from_name'] = $get('from_name');
            $cfg['enabled'] = $get('enabled');
            return $cfg;
        }

        if ($mode === 'chave_valor') {
            $cfg['driver'] = $getByFullKey('email_driver');
            $cfg['host'] = $getByFullKey('email_host');
            $cfg['port'] = $getByFullKey('email_port');
            $cfg['username'] = $getByFullKey('email_username');
            $cfg['password'] = $getByFullKey('email_password');
            $cfg['encryption'] = $getByFullKey('email_encryption');
            $cfg['from'] = $getByFullKey('email_from');
            $cfg['from_name'] = $getByFullKey('email_from_name');
            $cfg['enabled'] = $getByFullKey('email_enabled');

            if (($cfg['host'] ?? '') === null || (string) ($cfg['host'] ?? '') === '') {
                $cfg['host'] = $getByFullKey('smtp_host');
            }
            if (($cfg['port'] ?? '') === null || (string) ($cfg['port'] ?? '') === '') {
                $cfg['port'] = $getByFullKey('smtp_port');
            }
            if (($cfg['username'] ?? '') === null || (string) ($cfg['username'] ?? '') === '') {
                $cfg['username'] = $getByFullKey('smtp_usuario');
            }
            if (($cfg['password'] ?? '') === null || (string) ($cfg['password'] ?? '') === '') {
                $cfg['password'] = $getByFullKey('smtp_senha');
            }
            if (($cfg['encryption'] ?? '') === null || (string) ($cfg['encryption'] ?? '') === '') {
                $cfg['encryption'] = $getByFullKey('smtp_criptografia');
            }
            if (($cfg['from'] ?? '') === null || (string) ($cfg['from'] ?? '') === '') {
                $cfg['from'] = $getByFullKey('email_remetente');
            }
            if (($cfg['from_name'] ?? '') === null || (string) ($cfg['from_name'] ?? '') === '') {
                $cfg['from_name'] = $getByFullKey('email_nome_remetente');
            }

            return $cfg;
        }

        if ($mode === 'single_row') {
            $row = [];
            try {
                $idCol = (string) ($ti['idCol'] ?? 'id');
                $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$idCol} ASC LIMIT 1");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $row = [];
            }

            $pick = function (array $cands) use ($row): ?string {
                foreach ($cands as $c) {
                    if (array_key_exists($c, $row)) {
                        $v = $row[$c] ?? null;
                        if ($v !== null && $v !== '') {
                            return (string) $v;
                        }
                    }
                }
                foreach ($cands as $c) {
                    if (array_key_exists($c, $row)) {
                        $v = $row[$c] ?? null;
                        return $v !== null ? (string) $v : null;
                    }
                }
                return null;
            };

            $cfg['driver'] = $pick(['email_driver']);
            $cfg['host'] = $pick(['email_host', 'smtp_host']);
            $cfg['port'] = $pick(['email_port', 'smtp_port']);
            $cfg['username'] = $pick(['email_username', 'smtp_usuario', 'smtp_user', 'smtp_username']);
            $cfg['password'] = $pick(['email_password', 'smtp_senha', 'smtp_pass', 'smtp_password']);
            $cfg['encryption'] = $pick(['email_encryption', 'smtp_criptografia', 'smtp_secure', 'smtp_encryption']);
            $cfg['from'] = $pick(['email_from', 'email_remetente', 'smtp_from']);
            $cfg['from_name'] = $pick(['email_from_name', 'email_nome_remetente', 'smtp_from_name']);
            $cfg['enabled'] = $pick(['email_enabled']);
            return $cfg;
        }

        return [];
    }

    private function encodeHeaderName(string $name): string {
        $n = trim($name);
        if ($n === '') {
            return $n;
        }
        return '=?UTF-8?B?' . base64_encode($n) . '?=';
    }

    private function smtpReadLine($fp): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (strlen($line) < 4) {
                break;
            }
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return $data;
    }

    private function smtpExpect($fp, array $codes): string {
        $resp = $this->smtpReadLine($fp);
        $code = (int) substr(trim($resp), 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \Exception('SMTP resposta inesperada: ' . trim($resp));
        }
        return $resp;
    }

    private function smtpCmd($fp, string $cmd, array $okCodes): string {
        fwrite($fp, $cmd . "\r\n");
        return $this->smtpExpect($fp, $okCodes);
    }

    private function sendSmtpEmail(array $cfg, string $to, string $subject, string $html, string $fromEmail, string $fromName): void {
        $host = (string) ($cfg['host'] ?? '');
        $port = (int) ((string) ($cfg['port'] ?? '587'));
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        $enc = strtolower(trim((string) ($cfg['encryption'] ?? 'tls')));

        if ($host === '' || $port <= 0) {
            throw new \Exception('SMTP host/porta não configurados');
        }

        $remote = $host;
        $crypto = false;
        if ($enc === 'ssl') {
            $remote = 'ssl://' . $host;
            $crypto = true;
        }

        $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
        if (!$fp) {
            throw new \Exception('Falha ao conectar no SMTP: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, 15);

        try {
            $this->smtpExpect($fp, [220]);

            $localhost = 'localhost';
            $this->smtpCmd($fp, 'EHLO ' . $localhost, [250]);

            if ($enc === 'tls' && !$crypto) {
                $this->smtpCmd($fp, 'STARTTLS', [220]);
                $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new \Exception('Falha ao iniciar STARTTLS');
                }
                $this->smtpCmd($fp, 'EHLO ' . $localhost, [250]);
            }

            if ($user !== '') {
                $this->smtpCmd($fp, 'AUTH LOGIN', [334]);
                $this->smtpCmd($fp, base64_encode($user), [334]);
                $this->smtpCmd($fp, base64_encode($pass), [235]);
            }

            $this->smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->smtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCmd($fp, 'DATA', [354]);

            $headers = [];
            $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . ' <' . $fromEmail . '>';
            $headers[] = 'To: <' . $to . '>';
            $headers[] = 'Subject: ' . $this->encodeHeaderName($subject);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $html;
            $data = str_replace("\r\n.", "\r\n..", $data);

            fwrite($fp, $data . "\r\n.\r\n");
            $this->smtpExpect($fp, [250]);
            $this->smtpCmd($fp, 'QUIT', [221]);
        } finally {
            fclose($fp);
        }
    }

    private function ensureEmailDedupeTable(\PDO $pdo): void {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'email_event_log'");
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }
        } catch (\Exception $e) {
        }

        $sql = "CREATE TABLE IF NOT EXISTS email_event_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dedupe_key VARCHAR(190) NOT NULL,
            evento VARCHAR(64) NULL,
            to_email VARCHAR(190) NULL,
            subject VARCHAR(255) NULL,
            pedido_id INT NULL,
            usuario_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dedupe_key (dedupe_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $pdo->exec($sql);
    }

    private function reserveDedupeKey(\PDO $pdo, string $dedupeKey, array $meta): bool {
        if ($dedupeKey === '') {
            return true;
        }

        $this->ensureEmailDedupeTable($pdo);

        $evento = $meta['evento'] ?? null;
        $to = $meta['to_email'] ?? null;
        $subject = $meta['subject'] ?? null;
        $pedidoId = $meta['pedido_id'] ?? null;
        $usuarioId = $meta['usuario_id'] ?? null;

        try {
            $st = $pdo->prepare('INSERT INTO email_event_log (dedupe_key, evento, to_email, subject, pedido_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?)');
            $st->execute([
                $dedupeKey,
                $evento,
                $to,
                $subject,
                ($pedidoId !== null ? (int) $pedidoId : null),
                ($usuarioId !== null ? (int) $usuarioId : null),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function send(string $to, string $subject, string $html, string $dedupeKey = '', array $meta = []): void {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $pdo = \Config\Database::getConnection();
        $cfg = $this->getEmailConfigFromDb($pdo);

        $enabled = strtolower(trim((string) ($cfg['enabled'] ?? '1')));
        if ($enabled === '0' || $enabled === 'false') {
            return;
        }

        $driver = strtolower(trim((string) ($cfg['driver'] ?? 'smtp')));

        $fromEmail = 'noreply@brazilianashop.com.br';
        $fromName = 'Braziliana';
        if (!empty($cfg['from']) && filter_var((string) $cfg['from'], FILTER_VALIDATE_EMAIL)) {
            $fromEmail = (string) $cfg['from'];
        }
        if (!empty($cfg['from_name'])) {
            $fromName = (string) $cfg['from_name'];
        }

        $meta = is_array($meta) ? $meta : [];
        $meta['evento'] = $meta['evento'] ?? null;
        $meta['to_email'] = $to;
        $meta['subject'] = $subject;

        if ($dedupeKey !== '') {
            $ok = $this->reserveDedupeKey($pdo, $dedupeKey, $meta);
            if (!$ok) {
                return;
            }
        }

        if ($driver === 'smtp') {
            $this->sendSmtpEmail($cfg, $to, $subject, $html, $fromEmail, $fromName);
            return;
        }

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;

        $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$ok) {
            throw new \Exception('Falha ao enviar e-mail (mail())');
        }
    }

    public function getTemplate(string $eventoNome): array {
        $db = \Config\Database::getConnection();

        try {
            $stmt = $db->query('DESCRIBE email_templates');
            $cols = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                $nomeCol = in_array('nome', $cols, true) ? 'nome' : (in_array('evento', $cols, true) ? 'evento' : null);
                $assuntoCol = in_array('assunto', $cols, true) ? 'assunto' : null;
                $htmlCol = in_array('corpo_html', $cols, true) ? 'corpo_html' : (in_array('html', $cols, true) ? 'html' : null);
                $ativoCol = in_array('ativo', $cols, true) ? 'ativo' : null;

                if (!empty($nomeCol) && !empty($assuntoCol) && !empty($htmlCol)) {
                    $sql = 'SELECT ' . $assuntoCol . ' AS assunto, ' . $htmlCol . ' AS corpo_html FROM email_templates WHERE ' . $nomeCol . ' = ?';
                    if (!empty($ativoCol)) {
                        $sql .= ' AND ' . $ativoCol . ' = 1';
                    }
                    $sql .= ' ORDER BY id DESC LIMIT 1';
                    $st = $db->prepare($sql);
                    $st->execute([$eventoNome]);
                    $row = $st->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        return $row;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return ['assunto' => '', 'corpo_html' => ''];
    }

    public function renderTemplate(string $tpl, array $vars): string {
        $out = $tpl;

        $isTruthy = static function ($v): bool {
            if ($v === null) {
                return false;
            }
            if (is_bool($v)) {
                return $v;
            }
            if (is_numeric($v)) {
                return (float) $v !== 0.0;
            }
            $s = trim((string) $v);
            if ($s === '') {
                return false;
            }
            $sLower = strtolower($s);
            if ($sLower === '0' || $sLower === 'false' || $sLower === 'null') {
                return false;
            }
            return true;
        };

        // Suporte simples a blocos {{#if var}}...{{/if}}
        $guard = 0;
        while (strpos($out, '{{#if') !== false && $guard < 100) {
            $guard++;
            $out = preg_replace_callback('/\{\{#if\s+([a-zA-Z0-9_\.\-]+)\s*\}\}(.*?)\{\{\/if\}\}/s', function ($m) use ($vars, $isTruthy) {
                $key = (string) ($m[1] ?? '');
                $inner = (string) ($m[2] ?? '');
                $val = $vars[$key] ?? null;
                return $isTruthy($val) ? $inner : '';
            }, $out);
        }

        foreach ($vars as $k => $v) {
            $val = (string) $v;
            $out = str_replace('{{' . $k . '}}', $val, $out);
            $out = str_replace('{{ ' . $k . ' }}', $val, $out);
        }

        // Se for texto simples (sem tags), converte quebras de linha para <br>
        if (trim($out) !== '' && !preg_match('/<\s*\w+[\s>]/', $out)) {
            $out = nl2br($out);
        }

        return $out;
    }
}
