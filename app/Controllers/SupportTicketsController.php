<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\SupportTicketNotificationService;
use App\Services\AuthService;

class SupportTicketsController extends Controller {

    private function getPdo(): \PDO {
        return \Config\Database::getConnection();
    }

    private function getUploadsDirTickets(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/tickets/',
            $docRoot . '/uploads/tickets/',
            __DIR__ . '/../../public/uploads/tickets/',
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '' && (is_dir($c) || @mkdir($c, 0775, true))) {
                return rtrim($c, '/\\') . DIRECTORY_SEPARATOR;
            }
        }
        return rtrim((string) $candidates[0], '/\\') . DIRECTORY_SEPARATOR;
    }

    private function getUserNameById(\PDO $pdo, int $uid): string {
        if ($uid <= 0) return '';
        try {
            $st = $pdo->prepare('SELECT nome FROM usuarios WHERE id = ? LIMIT 1');
            $st->execute([$uid]);
            return (string) ($st->fetchColumn() ?: '');
        } catch (\Exception $e) {
            return '';
        }
    }

    private function loadAttachmentsByTicket(\PDO $pdo, int $ticketId): array {
        if ($ticketId <= 0) return [];
        try {
            $st = $pdo->prepare('SELECT * FROM support_ticket_message_attachments WHERE ticket_id = ? ORDER BY id ASC');
            $st->execute([$ticketId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $byMsg = [];
            foreach ($rows as $r) {
                $mid = (int) ($r['message_id'] ?? 0);
                if ($mid <= 0) continue;
                if (!isset($byMsg[$mid])) $byMsg[$mid] = [];
                $byMsg[$mid][] = $r;
            }
            return $byMsg;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function getPedidoOwnerUserId(\PDO $pdo, int $pedidoId): int {
        if ($pedidoId <= 0) {
            return 0;
        }

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE pedidos');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $colUsuario = $this->pickColumn($cols, ['usuario_id', 'user_id', 'cliente_id']);
        if (!$colUsuario) {
            return 0;
        }

        $st = $pdo->prepare('SELECT ' . $colUsuario . ' FROM pedidos WHERE id = ? LIMIT 1');
        $st->execute([$pedidoId]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function getLoggedUserId(): int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    private function getUsuarioLogadoRow(\PDO $pdo, int $uid): array {
        if ($uid <= 0) {
            return [];
        }
        try {
            $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
            $st->execute([$uid]);
            return $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        $uid = $this->getLoggedUserId();
        $pdo = $this->getPdo();
        $usuario = $this->getUsuarioLogadoRow($pdo, $uid);

        $st = $pdo->prepare('SELECT * FROM support_tickets WHERE usuario_id = ? ORDER BY updated_at DESC, created_at DESC');
        $st->execute([$uid]);
        $tickets = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('usuario/meus-tickets', [
            'usuario' => $usuario,
            'tickets' => $tickets,
        ]);
    }

    public function abrirPorPedido(Request $request, $pedidoId = null) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        $uid = $this->getLoggedUserId();
        $pedidoId = (int) ($pedidoId ?? $request->getParam('pedido_id'));

        if ($pedidoId <= 0) {
            header('Location: /meus-pedidos');
            exit;
        }

        $pdo = $this->getPdo();
        $ownerId = $this->getPedidoOwnerUserId($pdo, $pedidoId);
        if ($ownerId !== $uid) {
            echo '<div class="alert alert-danger">Acesso negado.</div>';
            exit;
        }

        // Se já houver ticket aberto para o pedido, redireciona.
        $stFind = $pdo->prepare("SELECT id FROM support_tickets WHERE usuario_id = ? AND pedido_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stFind->execute([$uid, $pedidoId]);
        $existingOpen = (int) ($stFind->fetchColumn() ?: 0);
        if ($existingOpen > 0) {
            header('Location: /meu-ticket/' . $existingOpen);
            exit;
        }

        // GET: exibe formulário de criação
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $usuario = $this->getUsuarioLogadoRow($pdo, $uid);
            $this->view('usuario/ticket_novo', [
                'usuario' => $usuario,
                'pedidoId' => $pedidoId,
                'assunto' => 'Suporte do Pedido #' . $pedidoId,
            ]);
            return;
        }

        // POST: cria ticket + primeira mensagem
        $motivo = trim((string) ($request->getParam('motivo') ?? ''));
        $assunto = trim((string) ($request->getParam('assunto') ?? ''));
        $mensagem = trim((string) ($request->getParam('mensagem') ?? ''));

        if ($motivo === '' || $assunto === '' || $mensagem === '') {
            $usuario = $this->getUsuarioLogadoRow($pdo, $uid);
            $this->view('usuario/ticket_novo', [
                'usuario' => $usuario,
                'pedidoId' => $pedidoId,
                'motivo' => $motivo,
                'assunto' => $assunto !== '' ? $assunto : ('Suporte do Pedido #' . $pedidoId),
                'mensagem' => $mensagem,
                'error' => 'Preencha motivo, assunto e descrição do problema.',
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $colsTickets = [];
            try {
                $st = $pdo->query('DESCRIBE support_tickets');
                $colsTickets = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsTickets = [];
            }

            if (in_array('motivo', $colsTickets, true)) {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, motivo, status) VALUES (?, ?, ?, ?, 'open')");
                $stIns->execute([$uid, $pedidoId, $assunto, $motivo]);
            } else {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, status) VALUES (?, ?, ?, 'open')");
                $stIns->execute([$uid, $pedidoId, $assunto]);
            }
            $ticketId = (int) $pdo->lastInsertId();

            $stMsg = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
            $stMsg->execute([$ticketId, 'cliente', $uid, $mensagem]);
            $messageId = (int) $pdo->lastInsertId();

            // anexos (opcional)
            $hasAttachTable = false;
            try {
                $stT = $pdo->query("SHOW TABLES LIKE 'support_ticket_message_attachments'");
                $hasAttachTable = (bool) ($stT && $stT->fetchColumn());
            } catch (\Exception $e) {
                $hasAttachTable = false;
            }

            if ($hasAttachTable && isset($_FILES['imagens']) && is_array($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadsDir = $this->getUploadsDirTickets();
                $webDir = '/uploads/tickets/';
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                foreach ($_FILES['imagens']['name'] as $k => $origName) {
                    if (($_FILES['imagens']['error'][$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $tmp = (string) ($_FILES['imagens']['tmp_name'][$k] ?? '');
                    if ($tmp === '' || !is_uploaded_file($tmp)) continue;
                    $size = (int) ($_FILES['imagens']['size'][$k] ?? 0);
                    if ($size <= 0 || $size > (5 * 1024 * 1024)) continue;

                    $mime = (string) $finfo->file($tmp);
                    if (!isset($allowed[$mime])) continue;
                    $ext = $allowed[$mime];

                    $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $origName);
                    $fname = 't' . (int) $ticketId . '_m' . (int) $messageId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $clean;
                    if (strpos($fname, '.') === false) {
                        $fname .= '.' . $ext;
                    }
                    $abs = $uploadsDir . $fname;
                    $rel = $webDir . $fname;

                    if (!@move_uploaded_file($tmp, $abs)) {
                        continue;
                    }

                    $stA = $pdo->prepare('INSERT INTO support_ticket_message_attachments (message_id, ticket_id, file_path, original_name, mime_type) VALUES (?, ?, ?, ?, ?)');
                    $stA->execute([$messageId, $ticketId, $rel, (string) $origName, $mime]);
                }
            }

            $pdo->commit();

            try {
                $not = new SupportTicketNotificationService();
                $not->notificarTicketCriado((int) $pedidoId, (int) $ticketId, [
                    'assunto' => $assunto,
                    'motivo' => $motivo,
                ]);
            } catch (\Exception $e) {
            }

            header('Location: /meu-ticket/' . $ticketId);
            exit;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $usuario = $this->getUsuarioLogadoRow($pdo, $uid);
            $this->view('usuario/ticket_novo', [
                'usuario' => $usuario,
                'pedidoId' => $pedidoId,
                'motivo' => $motivo,
                'assunto' => $assunto,
                'mensagem' => $mensagem,
                'error' => 'Não foi possível criar o ticket.',
            ]);
            return;
        }
    }

    public function ver(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        $uid = $this->getLoggedUserId();
        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();
        $usuario = $this->getUsuarioLogadoRow($pdo, $uid);

        $st = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? AND usuario_id = ? LIMIT 1');
        $st->execute([$id, $uid]);
        $ticket = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$ticket) {
            echo '<div class="alert alert-danger">Ticket não encontrado.</div>';
            exit;
        }

        $stM = $pdo->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stM->execute([(int) $id]);
        $messages = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $attachmentsByMessage = $this->loadAttachmentsByTicket($pdo, (int) $id);
        foreach ($messages as &$m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid > 0 && isset($attachmentsByMessage[$mid])) {
                $m['attachments'] = $attachmentsByMessage[$mid];
            }
            if (((string) ($m['autor_tipo'] ?? '')) === 'admin') {
                $adminId = (int) ($m['autor_usuario_id'] ?? 0);
                $m['autor_nome'] = $this->getUserNameById($pdo, $adminId);
            }
        }
        unset($m);

        $this->view('usuario/ticket', [
            'usuario' => $usuario,
            'ticket' => $ticket,
            'messages' => $messages,
            'isAdmin' => false,
        ]);
    }

    public function enviarMensagem(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        $uid = $this->getLoggedUserId();
        $id = (int) ($id ?? $request->getParam('id'));
        $msg = trim((string) ($request->getParam('mensagem') ?? ''));

        if ($msg === '') {
            header('Location: /meu-ticket/' . $id);
            exit;
        }

        $pdo = $this->getPdo();
        $st = $pdo->prepare('SELECT id, status FROM support_tickets WHERE id = ? AND usuario_id = ? LIMIT 1');
        $st->execute([$id, $uid]);
        $ticket = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$ticket) {
            echo '<div class="alert alert-danger">Ticket não encontrado.</div>';
            exit;
        }

        if ((string) ($ticket['status'] ?? '') !== 'open') {
            echo '<div class="alert alert-warning">Este ticket está fechado.</div>';
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stIns = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
            $stIns->execute([(int) $id, 'cliente', $uid, $msg]);
            $messageId = (int) $pdo->lastInsertId();

            // anexos (opcional)
            $hasAttachTable = false;
            try {
                $stT = $pdo->query("SHOW TABLES LIKE 'support_ticket_message_attachments'");
                $hasAttachTable = (bool) ($stT && $stT->fetchColumn());
            } catch (\Exception $e) {
                $hasAttachTable = false;
            }
            if ($hasAttachTable && isset($_FILES['imagens']) && is_array($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadsDir = $this->getUploadsDirTickets();
                $webDir = '/uploads/tickets/';
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                foreach ($_FILES['imagens']['name'] as $k => $origName) {
                    if (($_FILES['imagens']['error'][$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $tmp = (string) ($_FILES['imagens']['tmp_name'][$k] ?? '');
                    if ($tmp === '' || !is_uploaded_file($tmp)) continue;
                    $size = (int) ($_FILES['imagens']['size'][$k] ?? 0);
                    if ($size <= 0 || $size > (5 * 1024 * 1024)) continue;

                    $mime = (string) $finfo->file($tmp);
                    if (!isset($allowed[$mime])) continue;
                    $ext = $allowed[$mime];

                    $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $origName);
                    $fname = 't' . (int) $id . '_m' . (int) $messageId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $clean;
                    if (strpos($fname, '.') === false) {
                        $fname .= '.' . $ext;
                    }
                    $abs = $uploadsDir . $fname;
                    $rel = $webDir . $fname;

                    if (!@move_uploaded_file($tmp, $abs)) {
                        continue;
                    }

                    $stA = $pdo->prepare('INSERT INTO support_ticket_message_attachments (message_id, ticket_id, file_path, original_name, mime_type) VALUES (?, ?, ?, ?, ?)');
                    $stA->execute([$messageId, (int) $id, $rel, (string) $origName, $mime]);
                }
            }

            $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
            $pdo->commit();
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }

        header('Location: /meu-ticket/' . $id);
        exit;
    }

    public function fechar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerAutenticacao();

        $uid = $this->getLoggedUserId();
        $id = (int) ($id ?? $request->getParam('id'));
        if ($id <= 0) {
            header('Location: /meus-tickets');
            exit;
        }

        $pdo = $this->getPdo();
        $st = $pdo->prepare('SELECT id FROM support_tickets WHERE id = ? AND usuario_id = ? LIMIT 1');
        $st->execute([$id, $uid]);
        $ok = (int) ($st->fetchColumn() ?: 0);
        if ($ok <= 0) {
            header('Location: /meus-tickets');
            exit;
        }

        $pdo->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);
        header('Location: /meu-ticket/' . $id);
        exit;
    }
}
