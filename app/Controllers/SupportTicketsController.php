<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\SupportTicketNotificationService;
use App\Services\AuthService;

class SupportTicketsController extends Controller {

    private function getPdo(): \PDO {
        return \Config\Database::getConnection();
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$table]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function markClienteSeen(\PDO $pdo, int $ticketId, int $uid): void {
        if ($ticketId <= 0 || $uid <= 0) return;
        if (!$this->tableExists($pdo, 'support_ticket_views')) return;
        try {
            $pdo->prepare(
                'INSERT INTO support_ticket_views (ticket_id, viewer_type, viewer_user_id, last_seen_at, updated_at) '
                . 'VALUES (?, ?, ?, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE last_seen_at = NOW(), updated_at = NOW()'
            )->execute([(int) $ticketId, 'cliente', (int) $uid]);
        } catch (\Exception $e) {
        }
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

        $sql = 'SELECT * FROM support_tickets WHERE usuario_id = ? ORDER BY COALESCE(updated_at, created_at) ASC, created_at ASC';
        $st = $pdo->prepare($sql);
        $st->execute([$uid]);
        $tickets = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Ao abrir a aba/lista, some a bolha de notificação (marca como visto para o cliente)
        foreach ($tickets as $t) {
            $this->markClienteSeen($pdo, (int) ($t['id'] ?? 0), $uid);
        }

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
            echo '<div class="alert alert-danger">' . htmlspecialchars(__('common.access_denied', 'Acesso negado.'), ENT_QUOTES, 'UTF-8') . '</div>';
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
                'assunto' => __('ticket_new.default_subject', 'Suporte do Pedido #{id}', ['id' => (int) $pedidoId]),
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
                'assunto' => $assunto !== '' ? $assunto : __('ticket_new.default_subject', 'Suporte do Pedido #{id}', ['id' => (int) $pedidoId]),
                'mensagem' => $mensagem,
                'error' => __('ticket_new.error_required_fields', 'Preencha motivo, assunto e descrição do problema.'),
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
                'error' => __('ticket_new.error_create_failed', 'Não foi possível criar o ticket.'),
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

        if ($id > 0) {
            $this->markClienteSeen($pdo, $id, $uid);
        }

        $st = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? AND usuario_id = ? LIMIT 1');
        $st->execute([$id, $uid]);
        $ticket = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$ticket) {
            echo '<div class="alert alert-danger">' . htmlspecialchars(__('ticket.not_found', 'Ticket não encontrado.'), ENT_QUOTES, 'UTF-8') . '</div>';
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
            echo '<div class="alert alert-danger">' . htmlspecialchars(__('ticket.not_found', 'Ticket não encontrado.'), ENT_QUOTES, 'UTF-8') . '</div>';
            exit;
        }

        if ((string) ($ticket['status'] ?? '') !== 'open') {
            echo '<div class="alert alert-warning">' . htmlspecialchars(__('ticket.closed_warning', 'Este ticket está fechado.'), ENT_QUOTES, 'UTF-8') . '</div>';
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

        $decision = trim((string) ($request->getParam('closure_decision') ?? $request->getParam('decisao') ?? ''));
        $colsTickets = [];
        try {
            $stCols = $pdo->query('DESCRIBE support_tickets');
            $colsTickets = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsTickets = [];
        }
        $hasDecisionCol = in_array('closure_decision', $colsTickets, true);
        $hasClosedByTypeCol = in_array('closed_by_type', $colsTickets, true);
        $hasClosedByUidCol = in_array('closed_by_user_id', $colsTickets, true);

        if ($hasDecisionCol && $decision === '') {
            header('Location: /meu-ticket/' . $id . '?closure_error=1');
            exit;
        }

        $set = ["status = 'closed'", 'closed_at = NOW()', 'updated_at = NOW()'];
        $params = [];
        if ($hasDecisionCol) {
            $set[] = 'closure_decision = ?';
            $params[] = $decision;
        }
        if ($hasClosedByTypeCol) {
            $set[] = 'closed_by_type = ?';
            $params[] = 'cliente';
        }
        if ($hasClosedByUidCol) {
            $set[] = 'closed_by_user_id = ?';
            $params[] = (int) $uid;
        }
        $params[] = $id;

        $pdo->prepare('UPDATE support_tickets SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

        try {
            $stHasMsg = $pdo->query("SHOW TABLES LIKE 'support_ticket_messages'");
            $hasMsg = (bool) ($stHasMsg && $stHasMsg->fetchColumn());
            if ($hasMsg && $decision !== '') {
                $stMsg = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
                $stMsg->execute([(int) $id, 'cliente', (int) $uid, (string) (__('ticket.closure_message_prefix', 'Encerramento do ticket: ') . $decision)]);
            }
        } catch (\Exception $e) {
        }

        header('Location: /meu-ticket/' . $id);
        exit;
    }
}
