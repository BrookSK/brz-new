<?php
namespace App\Services;

class SupportTicketNotificationService {

    /**
     * Envia email de notificação de ticket (resposta admin → cliente ou cliente → admin)
     */
    public static function enviarEmailTicket(string $para, string $assuntoEmail, string $nomeDestinatario, int $ticketId, string $mensagem, string $autorNome, string $autorTipo): void {
        if (empty($para) || !filter_var($para, FILTER_VALIDATE_EMAIL)) return;

        $ticketUrl = ($autorTipo === 'admin')
            ? 'https://brazilianashop.com.br/meu-ticket/' . $ticketId
            : 'https://brazilianashop.com.br/admin/tickets/' . $ticketId;

        $corHeader = ($autorTipo === 'admin') ? '#1a2332' : '#0d6efd';
        $labelAutor = ($autorTipo === 'admin') ? 'Nossa equipe' : 'Cliente';
        $btnTexto = ($autorTipo === 'admin') ? 'Ver meu ticket' : 'Responder ticket';

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:30px 0;"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">'
            . '<tr><td style="background-color:' . $corHeader . ';padding:24px 30px;text-align:center;"><h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:600;">Braziliana Shop</h1></td></tr>'
            . '<tr><td style="padding:30px;">'
            . '<p style="font-size:16px;color:#333;margin:0 0 16px;">Ola, <strong>' . htmlspecialchars($nomeDestinatario) . '</strong>!</p>'
            . '<div style="background-color:#e8f4fd;border-left:4px solid #0d6efd;padding:12px 16px;margin-bottom:20px;border-radius:4px;">'
            . '<p style="margin:0;color:#084298;font-size:14px;"><strong>Nova mensagem no Ticket #' . $ticketId . '</strong></p>'
            . '</div>'
            . '<p style="font-size:14px;color:#555;margin:0 0 8px;"><strong>' . htmlspecialchars($autorNome) . '</strong> (' . $labelAutor . ') respondeu:</p>'
            . '<div style="background-color:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:16px;margin-bottom:24px;">'
            . '<p style="font-size:14px;color:#333;margin:0;white-space:pre-wrap;">' . htmlspecialchars($mensagem) . '</p>'
            . '</div>'
            . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:10px 0 24px;">'
            . '<a href="' . htmlspecialchars($ticketUrl) . '" style="display:inline-block;background-color:' . $corHeader . ';color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:14px;font-weight:600;">' . $btnTexto . '</a>'
            . '</td></tr></table>'
            . '<p style="font-size:13px;color:#888;margin:0;">Este e um email automatico. Responda diretamente pelo site.</p>'
            . '</td></tr>'
            . '<tr><td style="background-color:#f8f9fa;padding:20px 30px;text-align:center;border-top:1px solid #eee;">'
            . '<p style="font-size:12px;color:#999;margin:0;">Braziliana Shop - Ticket #' . $ticketId . '</p>'
            . '</td></tr></table></td></tr></table></body></html>';

        try {
            $emailService = new EmailService();
            $emailService->send($para, $assuntoEmail, $html, 'ticket_msg_' . $ticketId . '_' . time());
        } catch (\Exception $e) {
            error_log('[TICKET_EMAIL] Erro ao enviar email ticket #' . $ticketId . ': ' . $e->getMessage());
        }
    }

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
