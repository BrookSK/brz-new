<?php
namespace App\Services;

use App\Models\PedidoEcommerce;

class NotificationService {
    private PedidoEcommerce $pedidoModel;

    public function __construct() {
        $this->pedidoModel = new PedidoEcommerce();
    }

    public function notificarEventoPedido(?string $eventoNome, int $pedidoId, array $extra = []): void {
        if (empty($eventoNome)) {
            return;
        }

        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        if (!is_array($pedido) || empty($pedido['id'])) {
            return;
        }

        $vars = $this->buildVars($pedido, $eventoNome, $extra);

        try {
            $this->enviarEmailPorEvento($eventoNome, $vars);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES][EMAIL] Falha ao enviar: ' . $e->getMessage());
        }

        try {
            $this->enviarWhatsAppPorWebhook($eventoNome, $vars);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES][WHATSAPP] Falha ao enviar: ' . $e->getMessage());
        }
    }

    private function buildVars(array $pedido, string $eventoNome, array $extra): array {
        $clienteNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
        $clienteEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));
        $clienteTelefone = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));

        $codigoPedido = (string) ($pedido['codigo_pedido'] ?? ($pedido['numero_pedido'] ?? $pedido['id']));
        $status = (string) ($pedido['status'] ?? '');
        $moeda = (string) ($pedido['moeda'] ?? '');
        $valorTotal = (string) ($pedido['total'] ?? ($pedido['valor_total'] ?? ''));

        $base = [
            'evento' => $eventoNome,
            'pedido_id' => (string) ($pedido['id'] ?? ''),
            'codigo_pedido' => $codigoPedido,
            'status' => $status,
            'moeda' => $moeda,
            'valor_total' => $valorTotal,
            'nome' => $clienteNome,
            'email' => $clienteEmail,
            'telefone' => $clienteTelefone,
            'data' => date('Y-m-d H:i:s'),
        ];

        foreach ($extra as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $base[(string) $k] = (string) $v;
            }
        }

        return $base;
    }

    private function enviarEmailPorEvento(string $eventoNome, array $vars): void {
        $enabled = $this->getConfig('email', 'enabled', '1');
        if ($enabled === '0' || strtolower($enabled) === 'false') {
            return;
        }

        $to = (string) ($vars['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $tpl = $this->getEmailTemplate($eventoNome);
        $subject = $this->renderTemplate((string) ($tpl['assunto'] ?? ''), $vars);
        $html = $this->renderTemplate((string) ($tpl['corpo_html'] ?? ''), $vars);

        if (trim($subject) === '') {
            $subject = 'Atualização do seu pedido ' . ($vars['codigo_pedido'] ?? '');
        }
        if (trim($html) === '') {
            $html = 'Olá ' . htmlspecialchars((string) ($vars['nome'] ?? ''), ENT_QUOTES, 'UTF-8') . ',<br>Seu pedido <strong>#' . htmlspecialchars((string) ($vars['codigo_pedido'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong> está com status <strong>' . htmlspecialchars((string) ($vars['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>.';
        }

        $fromEmail = (string) $this->getConfig('email', 'from', 'noreply@brazilianashop.com.br');
        $fromName = (string) $this->getConfig('email', 'from_name', 'Braziliana');

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . ' <' . $fromEmail . '>';

        @mail($to, $subject, $html, implode("\r\n", $headers));
    }

    private function enviarWhatsAppPorWebhook(string $eventoNome, array $vars): void {
        $webhookConfig = $this->getWebhookConfig($eventoNome);

        $url = (string) ($webhookConfig['url'] ?? '');
        if ($url === '') {
            $url = (string) $this->getConfig('notificacoes', 'whatsapp_webhook_url_' . $eventoNome, '');
            if ($url === '') {
                $url = (string) $this->getConfig('notificacoes', 'whatsapp_webhook_url', '');
            }
        }
        if ($url === '') {
            return;
        }

        $telefone = preg_replace('/\D+/', '', (string) ($vars['telefone'] ?? ''));
        if ($telefone === '') {
            return;
        }

        $template = (string) ($webhookConfig['template'] ?? '');
        if ($template === '') {
            $template = (string) $this->getConfig('notificacoes', 'whatsapp_template_' . $eventoNome, '');
        }
        if ($template === '') {
            $template = 'Olá {{nome}}, seu pedido #{{codigo_pedido}} está com status {{status}}.';
        }

        $message = $this->renderTemplate($template, $vars);

        $payload = [
            'channel' => 'whatsapp',
            'evento' => $eventoNome,
            'to' => $telefone,
            'message' => $message,
            'vars' => $vars,
        ];

        $campos = $webhookConfig['campos'] ?? [];
        if (is_array($campos) && !empty($campos)) {
            $payload = array_merge($payload, $campos);
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0',
        ];

        $extraHeadersJson = (string) ($webhookConfig['headers'] ?? '');
        if ($extraHeadersJson === '') {
            $extraHeadersJson = (string) $this->getConfig('notificacoes', 'whatsapp_webhook_headers_' . $eventoNome, '');
            if ($extraHeadersJson === '') {
                $extraHeadersJson = (string) $this->getConfig('notificacoes', 'whatsapp_webhook_headers', '');
            }
        }
        if ($extraHeadersJson !== '') {
            $decoded = json_decode($extraHeadersJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_string($k) && (is_string($v) || is_numeric($v))) {
                        $headers[] = $k . ': ' . $v;
                    }
                }
            }
        }

        $body = json_encode($payload);

        $disparoId = null;
        $webhookId = $webhookConfig['id'] ?? null;
        if (!empty($webhookId)) {
            $disparoId = $this->criarLogDisparo((int) $webhookId, (int) ($vars['pedido_id'] ?? 0), $body);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!empty($err)) {
                if (!empty($disparoId)) {
                    $this->finalizarLogDisparo((int) $disparoId, 0, (string) $err, 'erro');
                }
                throw new \Exception('Erro cURL: ' . $err);
            }
            if ($code < 200 || $code >= 300) {
                if (!empty($disparoId)) {
                    $this->finalizarLogDisparo((int) $disparoId, $code, (string) $resp, 'erro');
                }
                throw new \Exception('HTTP ' . $code . ': ' . (string) $resp);
            }

            if (!empty($disparoId)) {
                $this->finalizarLogDisparo((int) $disparoId, $code, (string) $resp, 'sucesso');
            }
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ]
        ]);
        $resp = @file_get_contents($url, false, $context);
        if (!empty($disparoId)) {
            $this->finalizarLogDisparo((int) $disparoId, 0, (string) $resp, 'sucesso');
        }
    }

    private function getWebhookConfig(string $eventoNome): array {
        $db = \Config\Database::getConnection();

        try {
            $stmt = $db->query('DESCRIBE webhooks');
            $cols = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!is_array($cols) || empty($cols)) {
                return [];
            }

            $hasEventoId = in_array('evento_id', $cols, true);
            $hasUrl = in_array('url', $cols, true);
            if (!$hasUrl) {
                return [];
            }

            $select = 'w.id, w.url';
            if (in_array('ativo', $cols, true)) {
                $select .= ', w.ativo';
            }
            if (in_array('headers', $cols, true)) {
                $select .= ', w.headers';
            }
            if (in_array('payload_template', $cols, true)) {
                $select .= ', w.payload_template';
            }

            if ($hasEventoId) {
                $sql = 'SELECT ' . $select . ', e.nome AS evento_nome FROM webhooks w LEFT JOIN eventos_sistema e ON e.id = w.evento_id WHERE e.nome = ? ORDER BY w.id DESC LIMIT 1';
                $st = $db->prepare($sql);
                $st->execute([$eventoNome]);
                $row = $st->fetch(\PDO::FETCH_ASSOC);
            } else {
                $sql = 'SELECT ' . $select . ' FROM webhooks w WHERE w.nome = ? ORDER BY w.id DESC LIMIT 1';
                $st = $db->prepare($sql);
                $st->execute([$eventoNome]);
                $row = $st->fetch(\PDO::FETCH_ASSOC);
            }

            if (!is_array($row) || empty($row['id']) || empty($row['url'])) {
                return [];
            }

            $template = '';
            $campos = [];
            $payloadTemplate = $row['payload_template'] ?? null;
            if (is_string($payloadTemplate) && $payloadTemplate !== '') {
                $decoded = json_decode($payloadTemplate, true);
                if (is_array($decoded)) {
                    if (isset($decoded['template'])) {
                        $template = (string) $decoded['template'];
                    }
                    if (isset($decoded['campos']) && is_array($decoded['campos'])) {
                        $campos = $decoded['campos'];
                    }
                }
            }

            $headers = '';
            $h = $row['headers'] ?? null;
            if (is_string($h) && $h !== '') {
                $headers = $h;
            }

            $ativo = $row['ativo'] ?? 1;
            if ((string) $ativo === '0') {
                return [];
            }

            return [
                'id' => (int) $row['id'],
                'url' => (string) $row['url'],
                'headers' => $headers,
                'template' => $template,
                'campos' => $campos,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function criarLogDisparo(int $webhookId, int $pedidoId, string $payloadJson): ?int {
        $db = \Config\Database::getConnection();
        try {
            $stmt = $db->query('DESCRIBE webhook_disparos');
            $cols = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!is_array($cols) || empty($cols) || !in_array('webhook_id', $cols, true)) {
                return null;
            }

            $sql = 'INSERT INTO webhook_disparos (webhook_id, pedido_id, payload, status, tentativas, disparado_em) VALUES (?, ?, ?, ?, ?, NOW())';
            $st = $db->prepare($sql);
            $st->execute([$webhookId, $pedidoId ?: null, $payloadJson, 'pendente', 1]);
            return (int) $db->lastInsertId();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function finalizarLogDisparo(int $disparoId, int $responseCode, string $responseBody, string $status): void {
        $db = \Config\Database::getConnection();
        try {
            $sql = 'UPDATE webhook_disparos SET response_code = ?, response_body = ?, status = ? WHERE id = ?';
            $st = $db->prepare($sql);
            $st->execute([$responseCode ?: null, $responseBody, $status, $disparoId]);
        } catch (\Exception $e) {
        }
    }

    private function getEmailTemplate(string $eventoNome): array {
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

        // Fallback por config
        return [
            'assunto' => (string) $this->getConfig('email_templates', $eventoNome . '_assunto', ''),
            'corpo_html' => (string) $this->getConfig('email_templates', $eventoNome . '_html', ''),
        ];
    }

    private function renderTemplate(string $tpl, array $vars): string {
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }
        return $out;
    }

    private function encodeHeaderName(string $name): string {
        $n = trim($name);
        if ($n === '') {
            return $n;
        }
        return '=?UTF-8?B?' . base64_encode($n) . '?=';
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = \Config\Database::getConnection();

        // Tentativa schema single-row em configuracoes_sistema (colunas diretas)
        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                $colName = null;
                if ($categoria === 'email') {
                    $direct = ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from', 'from_name', 'enabled', 'webhook_enabled', 'webhook_url'];
                    $mapped = [];
                    foreach ($direct as $k) {
                        $mapped[$k] = 'email_' . $k;
                    }
                    $mapped['enabled'] = 'email_enabled';
                    if (array_key_exists($chave, $mapped) && in_array($mapped[$chave], $cols, true)) {
                        $colName = $mapped[$chave];
                    }
                } elseif ($categoria === 'notificacoes') {
                    $direct = ['whatsapp_webhook_url', 'whatsapp_webhook_headers'];
                    $mapped = [];
                    foreach ($direct as $k) {
                        $mapped[$k] = 'notificacoes_' . $k;
                    }
                    if (str_starts_with($chave, 'whatsapp_template_')) {
                        $mapped[$chave] = 'notificacoes_' . $chave;
                    }
                    if (str_starts_with($chave, 'whatsapp_webhook_url_')) {
                        $mapped[$chave] = 'notificacoes_' . $chave;
                    }
                    if (str_starts_with($chave, 'whatsapp_webhook_headers_')) {
                        $mapped[$chave] = 'notificacoes_' . $chave;
                    }
                    if (array_key_exists($chave, $mapped) && in_array($mapped[$chave], $cols, true)) {
                        $colName = $mapped[$chave];
                    }
                }

                if (!empty($colName)) {
                    $stmt = $db->query('SELECT ' . $colName . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // Schema categoria+chave
        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            $stmt->execute([$categoria, $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        // Schema chave/valor em configuracoes_sistema (sem categoria)
        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        // Schema chave/valor em configuracoes
        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }
}
