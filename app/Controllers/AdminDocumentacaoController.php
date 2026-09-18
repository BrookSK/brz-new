<?php
namespace App\Controllers;

use App\Core\Request;

class AdminDocumentacaoController {

    public function webhookTicket(Request $request) {
        $auth = new \App\Services\AuthService();
        $auth->requerPerfis(['admin']);

        $title = __('admin.docs.webhook_ticket_title', 'Documentação - Webhook Ticket');
        $sidebarActive = 'documentacao-webhook';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/documentacao/webhook-ticket.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }
}
