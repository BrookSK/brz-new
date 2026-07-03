<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminNotificacoesController extends Controller {
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
            $cfg['test_to'] = $get('test_to');
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
            $cfg['test_to'] = $getByFullKey('email_test_to');

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

            if (($cfg['test_to'] ?? '') === null || (string) ($cfg['test_to'] ?? '') === '') {
                $cfg['test_to'] = $getByFullKey('email_teste_para');
            }

            return $cfg;
        }

        if ($mode === 'single_row') {
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
            $cfg['test_to'] = $pick(['email_test_to', 'email_teste_para']);
            return $cfg;
        }

        return [];
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

            $boundary = 'b' . md5((string) microtime(true));
            $headers = [];
            $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
            $headers[] = 'To: <' . $to . '>';
            $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
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

    public function obterNotificacao(Request $request) {
        $this->requireAdmin();

        $evento = (string) $request->getParam('evento', '');
        if ($evento === '') {
            $this->json(['success' => false, 'error' => 'Evento é obrigatório'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEventosSistemaTable($pdo);
            $this->ensureWebhooksTable($pdo);

            $sql = 'SELECT w.id, w.url, w.metodo, w.headers, w.payload_template, w.ativo, w.retry_count FROM webhooks w INNER JOIN eventos_sistema e ON e.id = w.evento_id WHERE e.nome = ? ORDER BY w.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$evento]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            if (empty($row['id'])) {
                $this->json([
                    'success' => true,
                    'notificacao' => [
                        'evento' => $evento,
                        'url' => '',
                        'metodo' => 'POST',
                        'headers' => '',
                        'campos' => '',
                        'template' => '',
                        'ativo' => '1',
                        'retries' => '1',
                    ]
                ]);
            }

            $template = '';
            $campos = '';
            $pt = $row['payload_template'] ?? null;
            if (is_string($pt) && trim($pt) !== '') {
                $decoded = json_decode($pt, true);
                if (is_array($decoded)) {
                    if (isset($decoded['template']) && is_string($decoded['template'])) {
                        $template = (string) $decoded['template'];
                    }
                    if (isset($decoded['campos']) && is_array($decoded['campos'])) {
                        $campos = json_encode($decoded['campos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    }
                }
            }

            $headers = '';
            $hdr = $row['headers'] ?? null;
            if (is_string($hdr) && trim($hdr) !== '') {
                $decodedHdr = json_decode($hdr, true);
                if (is_array($decodedHdr)) {
                    $headers = json_encode($decodedHdr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                }
            }

            $ativo = ((string) ($row['ativo'] ?? '1') === '1') ? '1' : '0';
            $retryCount = (int) ($row['retry_count'] ?? 0);
            $retries = $retryCount > 0 ? '1' : '0';

            $this->json([
                'success' => true,
                'notificacao' => [
                    'evento' => $evento,
                    'url' => (string) ($row['url'] ?? ''),
                    'metodo' => (string) ($row['metodo'] ?? 'POST'),
                    'headers' => $headers,
                    'campos' => $campos,
                    'template' => $template,
                    'ativo' => $ativo,
                    'retries' => $retries,
                ]
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getCarneTestData(\PDO $pdo): array {
        try {
            $st = $pdo->prepare("
                SELECT c.id AS carne_id, c.pedido_id, c.quantidade_parcelas, c.total_geral, c.status AS carne_status,
                    c.created_at,
                    u.nome AS cliente_nome, u.email AS cliente_email, u.telefone AS cliente_telefone,
                    (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status = 'paga') AS parcelas_pagas,
                    (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('vencida','em_atraso')) AS parcelas_atrasadas,
                    (SELECT MIN(vencimento) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente')) AS proximo_vencimento,
                    (SELECT MAX(vencimento) FROM carne_parcelas WHERE carne_id = c.id) AS ultimo_vencimento,
                    (SELECT numero_parcela FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente') ORDER BY numero_parcela ASC LIMIT 1) AS numero_parcela,
                    (SELECT COALESCE(valor_produtos,0) + COALESCE(valor_taxas,0) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente') ORDER BY numero_parcela ASC LIMIT 1) AS valor_parcela,
                    (SELECT valor_produtos FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente') ORDER BY numero_parcela ASC LIMIT 1) AS valor_produtos,
                    (SELECT valor_taxas FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente') ORDER BY numero_parcela ASC LIMIT 1) AS valor_taxas,
                    (SELECT MIN(vencimento) FROM carne_parcelas WHERE carne_id = c.id) AS primeiro_vencimento
                FROM carnes c
                JOIN usuarios u ON u.id = c.cliente_id
                WHERE c.status IN ('em_andamento','ativo','com_atraso','aguardando_primeira_parcela')
                ORDER BY c.id DESC LIMIT 1
            ");
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return [];

            $parcelas_pagas = (int)($row['parcelas_pagas'] ?? 0);
            $total_parcelas = (int)($row['quantidade_parcelas'] ?? 0);
            $total_geral = (float)($row['total_geral'] ?? 0);
            $valor_pago_total = $total_parcelas > 0 ? round($total_geral * $parcelas_pagas / $total_parcelas, 2) : 0;

            return [
                'carne_id' => (string)($row['carne_id'] ?? ''),
                'pedido_id' => (string)($row['pedido_id'] ?? ''),
                'cliente_nome' => (string)($row['cliente_nome'] ?? ''),
                'cliente_email' => (string)($row['cliente_email'] ?? ''),
                'nome' => (string)($row['cliente_nome'] ?? ''),
                'email' => (string)($row['cliente_email'] ?? ''),
                'telefone' => (string)($row['cliente_telefone'] ?? ''),
                'telefone_limpo' => preg_replace('/\D/', '', (string)($row['cliente_telefone'] ?? '')),
                'quantidade_parcelas' => (string)$total_parcelas,
                'total_parcelas' => (string)$total_parcelas,
                'parcelas_pagas' => (string)$parcelas_pagas,
                'parcelas_atrasadas' => (string)($row['parcelas_atrasadas'] ?? '0'),
                'total_geral' => 'R$ ' . number_format($total_geral, 2, ',', '.'),
                'valor_parcela' => 'R$ ' . number_format((float)($row['valor_parcela'] ?? 0), 2, ',', '.'),
                'valor_produtos' => 'R$ ' . number_format((float)($row['valor_produtos'] ?? 0), 2, ',', '.'),
                'valor_taxas' => 'R$ ' . number_format((float)($row['valor_taxas'] ?? 0), 2, ',', '.'),
                'valor_pago_total' => 'R$ ' . number_format($valor_pago_total, 2, ',', '.'),
                'valor_restante' => 'R$ ' . number_format($total_geral - $valor_pago_total, 2, ',', '.'),
                'numero_parcela' => (string)($row['numero_parcela'] ?? '1'),
                'vencimento' => !empty($row['proximo_vencimento']) ? date('d/m/Y', strtotime($row['proximo_vencimento'])) : date('d/m/Y'),
                'primeiro_vencimento' => !empty($row['primeiro_vencimento']) ? date('d/m/Y', strtotime($row['primeiro_vencimento'])) : '',
                'ultimo_vencimento' => !empty($row['ultimo_vencimento']) ? date('d/m/Y', strtotime($row['ultimo_vencimento'])) : '',
                'dias_atraso' => '0',
                'data' => date('Y-m-d H:i:s'),
                'status' => (string)($row['carne_status'] ?? ''),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function ensureEventosSistemaTable(\PDO $pdo): void {
        try {
            $pdo->query('SELECT 1 FROM eventos_sistema LIMIT 1');
            return;
        } catch (\Exception $e) {
        }

        $sql = "CREATE TABLE IF NOT EXISTS eventos_sistema (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            ativo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);

        try {
            $pdo->exec('CREATE UNIQUE INDEX idx_eventos_sistema_nome ON eventos_sistema (nome)');
        } catch (\Exception $e) {
        }
    }

    private function ensureEmailTemplatesTable(\PDO $pdo): void {
        try {
            $pdo->query('SELECT 1 FROM email_templates LIMIT 1');
            // tabela já existe; ainda assim garantir template padrão do WExpress
            try {
                $this->ensureEventosSistemaTable($pdo);

                $evento = 'wexpress_label_created';
                $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
                $stmtEv->execute([$evento]);
                $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

                if ($eventoId <= 0) {
                    $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                    $stmtInsEv->execute([$evento, 'Etiqueta WExpress gerada (envio de tracking ao cliente)']);
                    $eventoId = (int) $pdo->lastInsertId();
                }

                $stTpl = $pdo->prepare('SELECT id FROM email_templates WHERE nome = ? ORDER BY id DESC LIMIT 1');
                $stTpl->execute([$evento]);
                $tplId = (int) ($stTpl->fetchColumn() ?: 0);
                if ($tplId <= 0) {
                    $assunto = 'Seu pedido #{{codigo_pedido}} foi enviado';
                    $html = 'Olá {{nome}},<br><br>Seu pedido <strong>#{{codigo_pedido}}</strong> já foi postado.<br><br><strong>Código de rastreio:</strong> {{courier_tracking_number}}<br><br>Você pode imprimir/visualizar a etiqueta aqui: <a href="{{label_url}}" target="_blank">{{label_url}}</a><br><br>Atenciosamente,<br>Braziliana';
                    $stIns = $pdo->prepare('INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
                    $stIns->execute([$eventoId, $evento, $assunto, $html]);
                }

                $evento = 'stamps_label_created';
                $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
                $stmtEv->execute([$evento]);
                $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

                if ($eventoId <= 0) {
                    $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                    $stmtInsEv->execute([$evento, 'Etiqueta Stamps gerada (envio de tracking ao cliente)']);
                    $eventoId = (int) $pdo->lastInsertId();
                }

                $stTpl = $pdo->prepare('SELECT id FROM email_templates WHERE nome = ? ORDER BY id DESC LIMIT 1');
                $stTpl->execute([$evento]);
                $tplId = (int) ($stTpl->fetchColumn() ?: 0);
                if ($tplId <= 0) {
                    $assunto = 'Seu pedido #{{codigo_pedido}} foi enviado';
                    $html = 'Olá {{nome}},<br><br>Seu pedido <strong>#{{codigo_pedido}}</strong> já foi postado.<br><br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Você pode imprimir/visualizar a etiqueta aqui: <a href="{{label_url}}" target="_blank">{{label_url}}</a><br><br>Atenciosamente,<br>Braziliana';
                    $stIns = $pdo->prepare('INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
                    $stIns->execute([$eventoId, $evento, $assunto, $html]);
                }
            } catch (\Exception $e) {
            }

            return;
        } catch (\Exception $e) {
        }

        $sql = "CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            evento_id INT NULL,
            nome VARCHAR(100) NOT NULL,
            assunto VARCHAR(255) NOT NULL,
            corpo_html LONGTEXT NOT NULL,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        )";
        $pdo->exec($sql);

        try {
            $pdo->exec('CREATE INDEX idx_email_templates_evento_id ON email_templates (evento_id)');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('CREATE INDEX idx_email_templates_nome ON email_templates (nome)');
        } catch (\Exception $e) {
        }

        // Seed template padrão do WExpress
        try {
            $this->ensureEventosSistemaTable($pdo);

            $evento = 'wexpress_label_created';
            $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
            $stmtEv->execute([$evento]);
            $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

            if ($eventoId <= 0) {
                $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                $stmtInsEv->execute([$evento, 'Etiqueta WExpress gerada (envio de tracking ao cliente)']);
                $eventoId = (int) $pdo->lastInsertId();
            }

            $stTpl = $pdo->prepare('SELECT id FROM email_templates WHERE nome = ? ORDER BY id DESC LIMIT 1');
            $stTpl->execute([$evento]);
            $tplId = (int) ($stTpl->fetchColumn() ?: 0);
            if ($tplId <= 0) {
                $assunto = 'Seu pedido #{{codigo_pedido}} foi enviado';
                $html = 'Olá {{nome}},<br><br>Seu pedido <strong>#{{codigo_pedido}}</strong> já foi postado.<br><br><strong>Código de rastreio:</strong> {{courier_tracking_number}}<br><br>Você pode imprimir/visualizar a etiqueta aqui: <a href="{{label_url}}" target="_blank">{{label_url}}</a><br><br>Atenciosamente,<br>Braziliana';
                $stIns = $pdo->prepare('INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
                $stIns->execute([$eventoId, $evento, $assunto, $html]);
            }

            $evento = 'stamps_label_created';
            $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
            $stmtEv->execute([$evento]);
            $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

            if ($eventoId <= 0) {
                $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                $stmtInsEv->execute([$evento, 'Etiqueta Stamps gerada (envio de tracking ao cliente)']);
                $eventoId = (int) $pdo->lastInsertId();
            }

            $stTpl = $pdo->prepare('SELECT id FROM email_templates WHERE nome = ? ORDER BY id DESC LIMIT 1');
            $stTpl->execute([$evento]);
            $tplId = (int) ($stTpl->fetchColumn() ?: 0);
            if ($tplId <= 0) {
                $assunto = 'Seu pedido #{{codigo_pedido}} foi enviado';
                $html = 'Olá {{nome}},<br><br>Seu pedido <strong>#{{codigo_pedido}}</strong> já foi postado.<br><br><strong>Código de rastreio:</strong> {{tracking_number}}<br><br>Você pode imprimir/visualizar a etiqueta aqui: <a href="{{label_url}}" target="_blank">{{label_url}}</a><br><br>Atenciosamente,<br>Braziliana';
                $stIns = $pdo->prepare('INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
                $stIns->execute([$eventoId, $evento, $assunto, $html]);
            }
        } catch (\Exception $e) {
        }
    }

    private function ensureWebhooksTable(\PDO $pdo): void {
        try {
            $pdo->query('SELECT 1 FROM webhooks LIMIT 1');
            return;
        } catch (\Exception $e) {
        }

        $sql = "CREATE TABLE IF NOT EXISTS webhooks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            url VARCHAR(255) NOT NULL,
            evento_id INT NULL,
            metodo VARCHAR(10) DEFAULT 'POST',
            headers TEXT NULL,
            payload_template TEXT NULL,
            ativo TINYINT(1) DEFAULT 1,
            retry_count INT DEFAULT 0,
            criado_por INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        )";
        $pdo->exec($sql);

        try {
            $pdo->exec('CREATE INDEX idx_webhooks_evento_id ON webhooks (evento_id)');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('CREATE INDEX idx_webhooks_nome ON webhooks (nome)');
        } catch (\Exception $e) {
        }
    }

    private function ensureWebhookDisparosTable(\PDO $pdo): void {
        try {
            $pdo->query('SELECT 1 FROM webhook_disparos LIMIT 1');
            return;
        } catch (\Exception $e) {
        }

        $sql = "CREATE TABLE IF NOT EXISTS webhook_disparos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            webhook_id INT NULL,
            pedido_id INT NULL,
            payload LONGTEXT NULL,
            response_code INT NULL,
            response_body LONGTEXT NULL,
            status VARCHAR(20) DEFAULT 'pendente',
            tentativas INT DEFAULT 1,
            disparado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);

        try {
            $pdo->exec('CREATE INDEX idx_webhook_disparos_webhook_id ON webhook_disparos (webhook_id)');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('CREATE INDEX idx_webhook_disparos_disparado_em ON webhook_disparos (disparado_em)');
        } catch (\Exception $e) {
        }
    }

    private function requireAdmin(): void {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
    }

    public function salvarNotificacao(Request $request) {
        $this->requireAdmin();

        $evento = (string) $request->getParam('evento', '');
        $url = (string) $request->getParam('webhook_url', '');
        $metodo = (string) $request->getParam('webhook_method', 'POST');
        $headers = (string) $request->getParam('webhook_headers', '');
        $campos = (string) $request->getParam('webhook_campos', '');
        $template = (string) $request->getParam('webhook_template', '');
        $ativo = (string) $request->getParam('webhook_ativo', '1');
        $retries = (string) $request->getParam('webhook_retries', '1');

        if ($evento === '' || $url === '') {
            $this->json(['success' => false, 'error' => 'Evento e URL são obrigatórios'], 400);
        }

        $metodo = strtoupper($metodo);
        if (!in_array($metodo, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            $metodo = 'POST';
        }

        $ativoBool = ($ativo === '1' || $ativo === 1 || $ativo === true) ? 1 : 0;
        $retryCount = ($retries === '1' || $retries === 1 || $retries === true) ? 3 : 0;

        $headersJson = null;
        if (trim($headers) !== '') {
            $decodedHeaders = json_decode($headers, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedHeaders)) {
                $this->json(['success' => false, 'error' => 'Headers inválidos (JSON)'], 400);
            }
            $headersJson = json_encode($decodedHeaders);
        }

        $camposJson = null;
        if (trim($campos) !== '') {
            $decodedCampos = json_decode($campos, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedCampos)) {
                $this->json(['success' => false, 'error' => 'Campos personalizados inválidos (JSON)'], 400);
            }
            $camposJson = json_encode($decodedCampos);
        }

        $payloadTemplate = null;
        if (trim($template) !== '' || $camposJson !== null) {
            $payloadTemplate = json_encode([
                'template' => $template,
                'campos' => $camposJson ? json_decode($camposJson, true) : new \stdClass(),
            ]);
        }

        $criadoPor = (int) ($_SESSION['usuario_id'] ?? 1);

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            $this->ensureEventosSistemaTable($pdo);
            $this->ensureEmailTemplatesTable($pdo);
            $this->ensureWebhooksTable($pdo);

            $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
            $stmtEv->execute([$evento]);
            $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

            if ($eventoId <= 0) {
                $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                $stmtInsEv->execute([$evento, 'Evento cadastrado via configurações']);
                $eventoId = (int) $pdo->lastInsertId();
            }

            $stmtCols = $pdo->query('DESCRIBE webhooks');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            $hasPatch = in_array('PATCH', $cols, true);

            $stmtW = $pdo->prepare('SELECT id FROM webhooks WHERE evento_id = ? ORDER BY id DESC LIMIT 1');
            $stmtW->execute([$eventoId]);
            $webhookId = (int) ($stmtW->fetchColumn() ?: 0);

            if ($webhookId > 0) {
                $sql = 'UPDATE webhooks SET nome = ?, url = ?, metodo = ?, headers = ?, payload_template = ?, ativo = ?, retry_count = ?, updated_at = NOW() WHERE id = ?';
                $st = $pdo->prepare($sql);
                $st->execute([
                    $evento,
                    $url,
                    $metodo,
                    $headersJson,
                    $payloadTemplate,
                    $ativoBool,
                    $retryCount,
                    $webhookId,
                ]);
            } else {
                $sql = 'INSERT INTO webhooks (nome, url, evento_id, metodo, headers, payload_template, ativo, retry_count, criado_por, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
                $st = $pdo->prepare($sql);
                $st->execute([
                    $evento,
                    $url,
                    $eventoId,
                    $metodo,
                    $headersJson,
                    $payloadTemplate,
                    $ativoBool,
                    $retryCount,
                    $criadoPor,
                ]);
                $webhookId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
            $this->json(['success' => true, 'webhook_id' => $webhookId, 'evento_id' => $eventoId]);
        } catch (\Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $e2) {
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function logsWebhook(Request $request) {
        $this->requireAdmin();

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureWebhookDisparosTable($pdo);

            $stmt = $pdo->query('DESCRIBE webhook_disparos');
            $cols = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!is_array($cols) || empty($cols) || !in_array('id', $cols, true)) {
                $this->json(['success' => true, 'logs' => []]);
            }

            $sql = 'SELECT d.id, d.disparado_em AS data_envio, d.status, d.response_body AS resposta FROM webhook_disparos d ORDER BY d.disparado_em DESC LIMIT 50';
            $st = $pdo->query($sql);
            $logs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'logs' => $logs]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function excluirLogWebhook(Request $request, $logId) {
        $this->requireAdmin();

        $id = (int) $logId;
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureWebhookDisparosTable($pdo);

            $st = $pdo->prepare('DELETE FROM webhook_disparos WHERE id = ?');
            $st->execute([$id]);

            $this->json(['success' => true, 'deleted' => $st->rowCount()]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function limparLogsWebhook(Request $request) {
        $this->requireAdmin();

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureWebhookDisparosTable($pdo);

            $st = $pdo->prepare('DELETE FROM webhook_disparos');
            $st->execute();

            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function logWebhook(Request $request, $logId) {
        $this->requireAdmin();

        $id = (int) $logId;
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureWebhookDisparosTable($pdo);
            $this->ensureWebhooksTable($pdo);

            $sql = 'SELECT d.id, d.disparado_em AS data_envio, d.status, w.url AS webhook_url, w.metodo, w.headers, d.payload, d.response_code, d.response_body AS resposta FROM webhook_disparos d LEFT JOIN webhooks w ON w.id = d.webhook_id WHERE d.id = ? LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $this->json(['success' => false, 'error' => 'Log não encontrado'], 404);
            }

            $this->json(['success' => true, 'log' => $row]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function testarEmail(Request $request) {
        $this->requireAdmin();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $to = (string) ($_SESSION['usuario_email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Email do admin não encontrado na sessão'], 400);
        }

        $fromEmail = (string) $request->getParam('email_remetente', '');
        $fromName = (string) $request->getParam('email_nome_remetente', '');

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $to;
        }
        if ($fromName === '') {
            $fromName = 'Braziliana';
        }

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';

        $subject = 'Teste de e-mail';
        $html = 'Teste de e-mail enviado em ' . date('Y-m-d H:i:s');

        $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$ok) {
            $this->json(['success' => false, 'error' => 'Falha ao enviar e-mail (mail())'], 500);
        }

        $this->json(['success' => true]);
    }

    public function testarWebhook(Request $request) {
        $this->requireAdmin();

        $evento = (string) $request->getParam('evento', '');
        if ($evento === '') {
            $this->json(['success' => false, 'error' => 'Evento é obrigatório'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEventosSistemaTable($pdo);
            $this->ensureWebhooksTable($pdo);
            $this->ensureWebhookDisparosTable($pdo);

            $sql = 'SELECT w.id, w.url, w.metodo, w.headers, w.payload_template, w.ativo FROM webhooks w INNER JOIN eventos_sistema e ON e.id = w.evento_id WHERE e.nome = ? ORDER BY w.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$evento]);
            $webhook = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            if (empty($webhook['id']) || empty($webhook['url'])) {
                $this->json(['success' => false, 'error' => 'Webhook não configurado para este evento. Salve a URL primeiro.'], 404);
            }

            $url = (string) $webhook['url'];
            $metodo = strtoupper((string) ($webhook['metodo'] ?? 'POST'));
            if (!in_array($metodo, ['POST', 'PUT', 'PATCH', 'GET'], true)) {
                $metodo = 'POST';
            }

            $headers = [
                'Content-Type: application/json',
                'User-Agent: brz-new/1.0',
            ];

            $hdr = $webhook['headers'] ?? null;
            if (is_string($hdr) && trim($hdr) !== '') {
                $decoded = json_decode($hdr, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        if (is_string($k) && (is_string($v) || is_numeric($v))) {
                            $headers[] = $k . ': ' . $v;
                        }
                    }
                }
            }

            $template = '';
            $campos = [];
            $pt = $webhook['payload_template'] ?? null;
            if (is_string($pt) && trim($pt) !== '') {
                $decoded = json_decode($pt, true);
                if (is_array($decoded)) {
                    if (isset($decoded['template']) && is_string($decoded['template'])) {
                        $template = $decoded['template'];
                    }
                    if (isset($decoded['campos']) && is_array($decoded['campos'])) {
                        $campos = $decoded['campos'];
                    }
                }
            }

            $payload = array_merge([
                'channel' => 'whatsapp',
                'evento' => $evento,
                'to' => '5511999999999',
                'message' => $template !== '' ? $template : 'Teste de webhook do evento ' . $evento,
                'vars' => [
                    'evento' => $evento,
                    'pedido_id' => 'TEST-123',
                    'codigo_pedido' => 'TEST-123',
                    'status' => 'teste',
                    'nome' => 'Cliente Teste',
                    'email' => 'teste@exemplo.com',
                    'telefone' => '5511999999999',
                    'data' => date('Y-m-d H:i:s'),
                ],
            ], $campos);

            // Para eventos de carnê, buscar dados reais de um carnê ativo
            if (str_starts_with($evento, 'carne_')) {
                $carneVars = $this->getCarneTestData($pdo);
                if (!empty($carneVars)) {
                    $payload['vars'] = array_merge($payload['vars'], $carneVars);
                    // Substituir variáveis {{...}} na mensagem
                    $msg = (string)($payload['message'] ?? '');
                    foreach ($carneVars as $k => $v) {
                        $msg = str_replace('{{' . $k . '}}', (string)$v, $msg);
                    }
                    $payload['message'] = $msg;
                    // Substituir variáveis nos campos personalizados
                    if (is_array($payload) && !empty($campos)) {
                        array_walk_recursive($payload, function(&$val) use ($carneVars) {
                            if (is_string($val)) {
                                foreach ($carneVars as $k => $v) {
                                    $val = str_replace('{{' . $k . '}}', (string)$v, $val);
                                }
                            }
                        });
                    }
                }
            }

            $body = json_encode($payload);

            $disparoId = null;
            try {
                $sqlIns = 'INSERT INTO webhook_disparos (webhook_id, pedido_id, payload, status, tentativas, disparado_em) VALUES (?, ?, ?, ?, ?, NOW())';
                $stIns = $pdo->prepare($sqlIns);
                $stIns->execute([(int) $webhook['id'], null, $body, 'pendente', 1]);
                $disparoId = (int) $pdo->lastInsertId();
            } catch (\Exception $e) {
            }

            $responseBody = '';
            $responseCode = 0;
            $status = 'sucesso';

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                if ($metodo !== 'GET') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                $err = curl_error($ch);
                $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if (!empty($err)) {
                    $status = 'erro';
                    $responseBody = (string) $err;
                } else {
                    $responseBody = (string) $resp;
                    if ($responseCode < 200 || $responseCode >= 300) {
                        $status = 'erro';
                    }
                }
            } else {
                $context = stream_context_create([
                    'http' => [
                        'method' => $metodo,
                        'header' => implode("\r\n", $headers),
                        'content' => $metodo === 'GET' ? '' : $body,
                        'ignore_errors' => true,
                        'timeout' => 15,
                    ]
                ]);
                $resp = @file_get_contents($url, false, $context);
                $responseBody = (string) $resp;
            }

            if (!empty($disparoId)) {
                try {
                    $sqlUp = 'UPDATE webhook_disparos SET response_code = ?, response_body = ?, status = ? WHERE id = ?';
                    $stUp = $pdo->prepare($sqlUp);
                    $stUp->execute([$responseCode ?: null, $responseBody, $status, $disparoId]);
                } catch (\Exception $e) {
                }
            }

            $this->json([
                'success' => $status === 'sucesso',
                'status' => $status,
                'http_code' => $responseCode,
                'response' => $responseBody,
                'log_id' => $disparoId,
            ], $status === 'sucesso' ? 200 : 500);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function salvarEmailTemplate(Request $request) {
        $this->requireAdmin();

        $evento = (string) $request->getParam('evento', '');
        $assunto = (string) $request->getParam('assunto', '');
        $corpoHtml = (string) $request->getParam('corpo_html', '');
        $ativo = (string) $request->getParam('ativo', '1');

        if ($evento === '' || trim($assunto) === '' || trim($corpoHtml) === '') {
            $this->json(['success' => false, 'error' => 'Evento, assunto e conteúdo são obrigatórios'], 400);
        }

        $ativoBool = ($ativo === '1' || $ativo === 1 || $ativo === true) ? 1 : 0;

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            $this->ensureEventosSistemaTable($pdo);

            $stmtEv = $pdo->prepare('SELECT id FROM eventos_sistema WHERE nome = ? LIMIT 1');
            $stmtEv->execute([$evento]);
            $eventoId = (int) ($stmtEv->fetchColumn() ?: 0);

            if ($eventoId <= 0) {
                $stmtInsEv = $pdo->prepare('INSERT INTO eventos_sistema (nome, descricao, ativo, created_at) VALUES (?, ?, 1, NOW())');
                $stmtInsEv->execute([$evento, 'Evento cadastrado via templates de e-mail']);
                $eventoId = (int) $pdo->lastInsertId();
            }

            $stmtTpl = $pdo->prepare('SELECT id FROM email_templates WHERE evento_id = ? AND nome = ? ORDER BY id DESC LIMIT 1');
            $stmtTpl->execute([$eventoId, $evento]);
            $templateId = (int) ($stmtTpl->fetchColumn() ?: 0);

            if ($templateId > 0) {
                $sql = 'UPDATE email_templates SET assunto = ?, corpo_html = ?, ativo = ?, updated_at = NOW() WHERE id = ?';
                $st = $pdo->prepare($sql);
                $st->execute([$assunto, $corpoHtml, $ativoBool, $templateId]);
            } else {
                $sql = 'INSERT INTO email_templates (evento_id, nome, assunto, corpo_html, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())';
                $st = $pdo->prepare($sql);
                $st->execute([$eventoId, $evento, $assunto, $corpoHtml, $ativoBool]);
                $templateId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
            $this->json(['success' => true, 'template_id' => $templateId, 'evento_id' => $eventoId]);
        } catch (\Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $e2) {
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listarEmailTemplates(Request $request) {
        $this->requireAdmin();

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEmailTemplatesTable($pdo);

            $stmt = $pdo->query('DESCRIBE email_templates');
            $cols = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!is_array($cols) || empty($cols) || !in_array('id', $cols, true)) {
                $this->json(['success' => true, 'templates' => []]);
            }

            $sql = 'SELECT t.id, t.nome AS evento, t.assunto, t.ativo, t.updated_at FROM email_templates t ORDER BY t.updated_at DESC, t.id DESC LIMIT 100';
            $st = $pdo->query($sql);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'templates' => $rows]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function obterEmailTemplate(Request $request) {
        $this->requireAdmin();

        $id = (int) $request->getParam('id', 0);
        $evento = (string) $request->getParam('evento', '');

        if ($id <= 0 && $evento === '') {
            $this->json(['success' => false, 'error' => 'Informe id ou evento'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEmailTemplatesTable($pdo);

            if ($id > 0) {
                $sql = 'SELECT t.id, t.nome AS evento, t.assunto, t.corpo_html, t.ativo, t.updated_at FROM email_templates t WHERE t.id = ? LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute([$id]);
            } else {
                $sql = 'SELECT t.id, t.nome AS evento, t.assunto, t.corpo_html, t.ativo, t.updated_at FROM email_templates t WHERE t.nome = ? ORDER BY t.id DESC LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute([$evento]);
            }

            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $this->json(['success' => false, 'error' => 'Template não encontrado'], 404);
            }

            $this->json(['success' => true, 'template' => $row]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function testarEmailTemplate(Request $request) {
        $this->requireAdmin();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionEmail = (string) ($_SESSION['usuario_email'] ?? '');

        $evento = (string) $request->getParam('evento', '');
        if ($evento === '') {
            $this->json(['success' => false, 'error' => 'Evento é obrigatório'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEmailTemplatesTable($pdo);

            $emailCfg = $this->getEmailConfigFromDb($pdo);
            $driver = strtolower(trim((string) ($emailCfg['driver'] ?? 'smtp')));

            $to = (string) $request->getParam('to', '');
            if ($to === '' && !empty($emailCfg['test_to'])) {
                $to = (string) $emailCfg['test_to'];
            }
            if ($to === '') {
                $to = $sessionEmail;
            }
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->json(['success' => false, 'error' => 'Informe um email válido para teste (campo Email de teste para), ou configure email_test_to'], 400);
            }

            $sql = 'SELECT t.id, t.nome AS evento, t.assunto, t.corpo_html, t.ativo FROM email_templates t WHERE t.nome = ? ORDER BY t.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$evento]);
            $tpl = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            if (empty($tpl['id'])) {
                $this->json(['success' => false, 'error' => 'Template não encontrado para este evento'], 404);
            }
            if ((string) ($tpl['ativo'] ?? '1') === '0') {
                $this->json(['success' => false, 'error' => 'Template está desativado'], 400);
            }

            $vars = $this->getEmailVarsTeste($evento);
            $subject = $this->renderMustacheLike((string) ($tpl['assunto'] ?? ''), $vars);
            $html = $this->renderMustacheLike((string) ($tpl['corpo_html'] ?? ''), $vars);

            $fromEmail = 'noreply@brazilianashop.com.br';
            $fromName = 'Braziliana';

            if (!empty($emailCfg['from']) && filter_var((string) $emailCfg['from'], FILTER_VALIDATE_EMAIL)) {
                $fromEmail = (string) $emailCfg['from'];
            }
            if (!empty($emailCfg['from_name'])) {
                $fromName = (string) $emailCfg['from_name'];
            }

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';

            if ($driver === 'smtp') {
                $this->sendSmtpEmail($emailCfg, $to, $subject, $html, $fromEmail, $fromName);
            } else {
                $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
                if (!$ok) {
                    $this->json(['success' => false, 'error' => 'Falha ao enviar e-mail (mail())'], 500);
                }
            }

            $this->json(['success' => true, 'to' => $to]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function renderMustacheLike(string $tpl, array $vars): string {
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }
        return $out;
    }

    private function getEmailVarsTeste(string $evento): array {
        $base = [
            'evento' => $evento,
            'pedido_id' => 'TEST-123',
            'codigo_pedido' => 'TEST-123',
            'numero_pedido' => 'TEST-123',
            'status' => 'teste',
            'moeda' => 'BRL',
            'valor_total' => '199.90',
            'total' => '199.90',
            'nome' => 'Cliente Teste',
            'cliente_nome' => 'Cliente Teste',
            'email' => 'teste@exemplo.com',
            'cliente_email' => 'teste@exemplo.com',
            'telefone' => '5511999999999',
            'cliente_telefone' => '5511999999999',
            'data' => date('Y-m-d H:i:s'),
            'data_pedido' => date('Y-m-d H:i:s'),
        ];

        if ($evento === 'pedido_enviado') {
            $base['codigo_rastreamento'] = 'BR123456789BR';
            $base['transportadora'] = 'Correios';
            $base['data_envio'] = date('Y-m-d H:i:s');
        }

        return $base;
    }
}
