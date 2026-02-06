<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminTicketsController extends Controller {

    private function getPdo(): \PDO {
        return \Config\Database::getConnection();
    }

    private function getLoggedUserId(): int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (int) ($_SESSION['usuario_id'] ?? 0);
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

        $stM = $pdo->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stM->execute([$id]);
        $messages = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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

        $stIns = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, \"admin\", ?, ?)');
        $stIns->execute([$id, $adminUid, $msg]);

        $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')->execute([$id]);

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
