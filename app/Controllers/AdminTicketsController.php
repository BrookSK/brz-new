<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\SupportTicketNotificationService;
use App\Models\PedidoEcommerce;

class AdminTicketsController extends Controller {

    private function getPdo(): \PDO {
        return \Config\Database::getConnection();
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
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

    private function getLoggedUserId(): int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (int) ($_SESSION['usuario_id'] ?? 0);
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
            $stT = $pdo->query("SHOW TABLES LIKE 'support_ticket_message_attachments'");
            $has = (bool) ($stT && $stT->fetchColumn());
            if (!$has) return [];
        } catch (\Exception $e) {
            return [];
        }

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

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $pdo = $this->getPdo();

        $status = strtolower(trim((string) ($request->getParam('status') ?? 'open')));
        if (!in_array($status, ['open', 'closed', 'all'], true)) {
            $status = 'open';
        }

        $where = '';
        $params = [];
        if ($status !== 'all') {
            $where = ' WHERE t.status = ? ';
            $params[] = $status;
        }

        $sql = 'SELECT t.*, u.nome AS usuario_nome, u.email AS usuario_email '
            . 'FROM support_tickets t '
            . 'INNER JOIN usuarios u ON u.id = t.usuario_id '
            . $where
            . 'ORDER BY t.updated_at DESC, t.created_at DESC';

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $tickets = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $sidebarActive = 'tickets';
        ob_start();
        include __DIR__ . '/../Views/admin/tickets.php';
        $content = ob_get_clean();
        $title = 'Tickets';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function ver(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();

        $st = $pdo->prepare('SELECT t.*, u.nome AS usuario_nome, u.email AS usuario_email FROM support_tickets t INNER JOIN usuarios u ON u.id = t.usuario_id WHERE t.id = ? LIMIT 1');
        $st->execute([$id]);
        $ticket = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$ticket) {
            echo '<div class="alert alert-danger">Ticket não encontrado.</div>';
            exit;
        }

        // Dados do cliente (tabela usuarios pode variar)
        $clienteResumo = [];
        try {
            $colsU = [];
            try {
                $stU = $pdo->query('DESCRIBE usuarios');
                $colsU = $stU ? ($stU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsU = [];
            }

            $clienteId = (int) ($ticket['usuario_id'] ?? 0);
            if ($clienteId > 0 && is_array($colsU) && !empty($colsU)) {
                $select = ['id', 'nome', 'email'];

                $colTelefone = $this->pickColumn($colsU, ['telefone', 'celular', 'whatsapp', 'phone']);
                $colCpf = $this->pickColumn($colsU, ['cpf', 'documento', 'cpf_cnpj', 'cnpj', 'document']);
                $colCreated = $this->pickColumn($colsU, ['created_at', 'data_cadastro', 'cadastrado_em']);

                if ($colTelefone) $select[] = $colTelefone;
                if ($colCpf) $select[] = $colCpf;
                if ($colCreated) $select[] = $colCreated;

                $sqlCliente = 'SELECT ' . implode(', ', array_unique($select)) . ' FROM usuarios WHERE id = ? LIMIT 1';
                $stC = $pdo->prepare($sqlCliente);
                $stC->execute([$clienteId]);
                $clienteResumo = $stC->fetch(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $clienteResumo = [];
        }

        // Compras anteriores (últimos pedidos do mesmo usuário)
        $comprasAnteriores = [];
        try {
            $colsP = [];
            try {
                $stPcols = $pdo->query('DESCRIBE pedidos');
                $colsP = $stPcols ? ($stPcols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsP = [];
            }

            $clienteId = (int) ($ticket['usuario_id'] ?? 0);
            $pedidoIdAtual = (int) ($ticket['pedido_id'] ?? 0);
            if ($clienteId > 0 && is_array($colsP) && !empty($colsP)) {
                $colUser = $this->pickColumn($colsP, ['usuario_id', 'cliente_id', 'user_id']);
                $colCodigo = $this->pickColumn($colsP, ['codigo_pedido', 'numero_pedido']);
                $colStatus = $this->pickColumn($colsP, ['status', 'status_pedido', 'pedido_status']);
                $colTotal = $this->pickColumn($colsP, ['total', 'valor_total', 'valor', 'amount']);
                $colCreated = $this->pickColumn($colsP, ['created_at', 'data_pedido', 'data_criacao']);

                if ($colUser) {
                    $select = ['id'];
                    if ($colCodigo) $select[] = $colCodigo . ' AS codigo_pedido';
                    if ($colStatus) $select[] = $colStatus . ' AS status';
                    if ($colTotal) $select[] = $colTotal . ' AS total';
                    if ($colCreated) $select[] = $colCreated . ' AS created_at';

                    $where = ' WHERE ' . $colUser . ' = ? ';
                    $params = [$clienteId];
                    if ($pedidoIdAtual > 0) {
                        $where .= ' AND id <> ? ';
                        $params[] = $pedidoIdAtual;
                    }
                    $order = $colCreated ? $colCreated : 'id';

                    $sqlPrev = 'SELECT ' . implode(', ', $select) . ' FROM pedidos ' . $where . ' ORDER BY ' . $order . ' DESC LIMIT 8';
                    $stPrev = $pdo->prepare($sqlPrev);
                    $stPrev->execute($params);
                    $comprasAnteriores = $stPrev->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            }
        } catch (\Exception $e) {
            $comprasAnteriores = [];
        }

        // Detalhes do pedido relacionado ao ticket
        $pedidoDetalhes = [];
        try {
            $pedidoId = (int) ($ticket['pedido_id'] ?? 0);
            if ($pedidoId > 0) {
                $pedidoModel = new PedidoEcommerce();
                $pedidoDetalhes = $pedidoModel->getComDetalhes($pedidoId);
                if (!is_array($pedidoDetalhes)) {
                    $pedidoDetalhes = [];
                }
            }
        } catch (\Exception $e) {
            $pedidoDetalhes = [];
        }

        $stM = $pdo->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stM->execute([$id]);
        $messages = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $attachmentsByMessage = $this->loadAttachmentsByTicket($pdo, (int) $id);
        foreach ($messages as &$m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid > 0 && isset($attachmentsByMessage[$mid])) {
                $m['attachments'] = $attachmentsByMessage[$mid];
            }

            $tipo = (string) ($m['autor_tipo'] ?? '');
            $auid = (int) ($m['autor_usuario_id'] ?? 0);
            if ($tipo === 'admin') {
                $m['autor_nome'] = $this->getUserNameById($pdo, $auid);
            }
        }
        unset($m);

        $sidebarActive = 'tickets';
        ob_start();
        include __DIR__ . '/../Views/admin/ticket.php';
        $content = ob_get_clean();
        $title = 'Ticket #' . $id;
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function enviarMensagem(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $adminUid = $this->getLoggedUserId();
        $id = (int) ($id ?? $request->getParam('id'));
        $msg = trim((string) ($request->getParam('mensagem') ?? ''));

        if ($msg === '') {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $pdo = $this->getPdo();
        $st = $pdo->prepare('SELECT status FROM support_tickets WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $status = (string) ($st->fetchColumn() ?: '');
        if ($status !== 'open') {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stP = $pdo->prepare('SELECT pedido_id FROM support_tickets WHERE id = ? LIMIT 1');
            $stP->execute([$id]);
            $pedidoId = (int) ($stP->fetchColumn() ?: 0);

            $stIns = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
            $stIns->execute([$id, 'admin', $adminUid, $msg]);
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

            $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')->execute([$id]);
            $pdo->commit();

            try {
                $not = new SupportTicketNotificationService();
                $not->notificarRespostaAdmin($pedidoId, (int) $id, (int) $adminUid, (string) $msg);
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }

        header('Location: /admin/tickets/' . $id);
        exit;
    }

    public function fechar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();
        $pdo->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);
        header('Location: /admin/tickets/' . $id);
        exit;
    }

    public function reabrir(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();
        $pdo->prepare("UPDATE support_tickets SET status = 'open', closed_at = NULL, updated_at = NOW() WHERE id = ?")->execute([$id]);
        header('Location: /admin/tickets/' . $id);
        exit;
    }
}
