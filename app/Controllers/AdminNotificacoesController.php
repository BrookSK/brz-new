<?php
namespace App\Controllers;

use App\Core\Request;

class AdminNotificacoesController extends Controller {
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $usuarioId = $_SESSION['usuario_id'] ?? ($_SESSION['user_id'] ?? null);
        $logado = $_SESSION['logado'] ?? null;
        $temSessao = ($logado === true) || (!empty($usuarioId));

        if (!$temSessao) {
            $this->json(['success' => false, 'error' => 'Não autenticado'], 401);
            exit;
        }

        $perfil = $_SESSION['usuario_perfil'] ?? ($_SESSION['perfil'] ?? ($_SESSION['user_perfil'] ?? null));
        $isAdmin = ($perfil === 'admin') || (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']);

        if (!$isAdmin) {
            $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
            exit;
        }
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            $this->ensureEventosSistemaTable($pdo);
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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

    public function logWebhook(Request $request, $logId) {
        $this->requireAdmin();

        $id = (int) $logId;
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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
            $fromName = 'Braziliana Shop';
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->ensureEventosSistemaTable($pdo);
            $this->ensureWebhooksTable($pdo);
            $this->ensureWebhookDisparosTable($pdo);

            $sql = 'SELECT w.id, w.url, w.metodo, w.headers, w.payload_template, w.ativo FROM webhooks w INNER JOIN eventos_sistema e ON e.id = w.evento_id WHERE e.nome = ? ORDER BY w.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$evento]);
            $webhook = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            if (empty($webhook['id']) || empty($webhook['url'])) {
                $this->json(['success' => false, 'error' => 'Webhook não configurado para este evento'], 404);
            }

            if ((string) ($webhook['ativo'] ?? '1') === '0') {
                $this->json(['success' => false, 'error' => 'Webhook está desativado'], 400);
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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

        $to = (string) ($_SESSION['usuario_email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Email do admin não encontrado na sessão'], 400);
        }

        $evento = (string) $request->getParam('evento', '');
        if ($evento === '') {
            $this->json(['success' => false, 'error' => 'Evento é obrigatório'], 400);
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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
            $fromName = 'Braziliana Shop';

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';

            $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
            if (!$ok) {
                $this->json(['success' => false, 'error' => 'Falha ao enviar e-mail (mail())'], 500);
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
