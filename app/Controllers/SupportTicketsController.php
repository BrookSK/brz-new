<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class SupportTicketsController extends Controller {

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

        $stFind = $pdo->prepare("SELECT id FROM support_tickets WHERE usuario_id = ? AND pedido_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stFind->execute([$uid, $pedidoId]);
        $existing = (int) ($stFind->fetchColumn() ?: 0);
        if ($existing > 0) {
            header('Location: /meu-ticket/' . $existing);
            exit;
        }

        $assunto = 'Suporte do Pedido #' . $pedidoId;
        $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, status) VALUES (?, ?, ?, 'open')");
        $stIns->execute([$uid, $pedidoId, $assunto]);
        $ticketId = (int) $pdo->lastInsertId();

        header('Location: /meu-ticket/' . $ticketId);
        exit;
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

        $stIns = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
        $stIns->execute([(int) $id, 'cliente', $uid, $msg]);

        $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')->execute([(int) $id]);

        header('Location: /meu-ticket/' . $id);
        exit;
    }
}
