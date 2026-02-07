<?php
namespace App\Services;

class SupportTicketNotificationService {

    public function notificarTicketCriado(int $pedidoId, int $ticketId, array $ticket = []): void {
        if ($pedidoId <= 0 || $ticketId <= 0) {
            return;
        }

        $extra = [
            'ticket_id' => (string) $ticketId,
            'ticket_assunto' => (string) ($ticket['assunto'] ?? ''),
            'ticket_motivo' => (string) ($ticket['motivo'] ?? ''),
            'ticket_url' => '/meu-ticket/' . (int) $ticketId,
        ];

        try {
            $svc = new NotificationService();
            $svc->notificarEventoPedido('ticket_created', $pedidoId, $extra);
        } catch (\Exception $e) {
        }
    }

    public function notificarRespostaAdmin(int $pedidoId, int $ticketId, int $adminUserId, string $mensagem): void {
        if ($pedidoId <= 0 || $ticketId <= 0) {
            return;
        }

        $adminNome = '';
        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT nome FROM usuarios WHERE id = ? LIMIT 1');
            $st->execute([(int) $adminUserId]);
            $adminNome = (string) ($st->fetchColumn() ?: '');
        } catch (\Exception $e) {
            $adminNome = '';
        }

        $extra = [
            'ticket_id' => (string) $ticketId,
            'ticket_url' => '/meu-ticket/' . (int) $ticketId,
            'admin_nome' => $adminNome,
            'ticket_mensagem' => $mensagem,
        ];

        try {
            $svc = new NotificationService();
            $svc->notificarEventoPedido('ticket_admin_reply', $pedidoId, $extra);
        } catch (\Exception $e) {
        }
    }
}
