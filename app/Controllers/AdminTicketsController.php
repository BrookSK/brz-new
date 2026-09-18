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

    private function markVendorChatSeen(\PDO $pdo, int $ticketId, int $vendedorId, int $viewerUid): void {
        if ($ticketId <= 0 || $vendedorId <= 0 || $viewerUid <= 0) return;
        if (!$this->tableExists($pdo, 'support_ticket_vendor_views')) return;
        try {
            $pdo->prepare(
                'INSERT INTO support_ticket_vendor_views (ticket_id, vendedor_id, viewer_type, viewer_user_id, last_seen_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE last_seen_at = NOW(), updated_at = NOW()'
            )->execute([(int) $ticketId, (int) $vendedorId, 'suporte', (int) $viewerUid]);
        } catch (\Exception $e) {
        }
    }

    private function getUploadsDirVendorChat(): array {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $absCandidates = [
            $docRoot . '/public/uploads/tickets/vendor-chat/',
            $docRoot . '/uploads/tickets/vendor-chat/',
            __DIR__ . '/../../public/uploads/tickets/vendor-chat/',
        ];

        $abs = '';
        foreach ($absCandidates as $c) {
            if (is_string($c) && $c !== '' && (is_dir($c) || @mkdir($c, 0775, true))) {
                $abs = rtrim($c, '/\\') . DIRECTORY_SEPARATOR;
                break;
            }
        }
        if ($abs === '') {
            $abs = rtrim((string) $absCandidates[0], '/\\') . DIRECTORY_SEPARATOR;
        }

        return [
            'abs' => $abs,
            'web' => '/uploads/tickets/vendor-chat/',
        ];
    }

    private function getUserRoleOrPerfil(): string {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $p = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
            $r = strtolower(trim((string) ($_SESSION['usuario_role'] ?? '')));
            return $p !== '' ? $p : $r;
        } catch (\Exception $e) {
            return '';
        }
    }

    private function getUploadsDirTicketFiles(): array {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $absCandidates = [
            $docRoot . '/public/uploads/tickets/files/',
            $docRoot . '/uploads/tickets/files/',
            __DIR__ . '/../../public/uploads/tickets/files/',
        ];

        $abs = '';
        foreach ($absCandidates as $c) {
            if (is_string($c) && $c !== '' && (is_dir($c) || @mkdir($c, 0775, true))) {
                $abs = rtrim($c, '/\\') . DIRECTORY_SEPARATOR;
                break;
            }
        }
        if ($abs === '') {
            $abs = rtrim((string) $absCandidates[0], '/\\') . DIRECTORY_SEPARATOR;
        }

        return [
            'abs' => $abs,
            'web' => '/uploads/tickets/files/',
        ];
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $st->execute([$table, $column]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
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

    private function markAdminSeen(\PDO $pdo, int $ticketId, int $adminUid): void {
        if ($ticketId <= 0 || $adminUid <= 0) return;
        if (!$this->tableExists($pdo, 'support_ticket_views')) return;
        try {
            $pdo->prepare(
                'INSERT INTO support_ticket_views (ticket_id, viewer_type, viewer_user_id, last_seen_at, updated_at) '
                . 'VALUES (?, ?, ?, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE last_seen_at = NOW(), updated_at = NOW()'
            )->execute([(int) $ticketId, 'admin', (int) $adminUid]);
        } catch (\Exception $e) {
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

        $dateFrom = trim((string) ($request->getParam('date_from') ?? ''));
        $dateTo = trim((string) ($request->getParam('date_to') ?? ''));

        $clienteTipo = strtolower(trim((string) ($request->getParam('cliente_tipo') ?? '')));
        if (!in_array($clienteTipo, ['', 'admin', 'suporte', 'vendedor'], true)) {
            $clienteTipo = '';
        }

        $atendenteId = (int) ($request->getParam('atendente_id') ?? 0);

        $whereParts = [];
        $params = [];
        if ($status !== 'all') {
            $whereParts[] = 't.status = ?';
            $params[] = $status;
        }
        if ($dateFrom !== '') {
            $whereParts[] = 'DATE(COALESCE(t.updated_at, t.created_at)) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $whereParts[] = 'DATE(COALESCE(t.updated_at, t.created_at)) <= ?';
            $params[] = $dateTo;
        }

        if ($clienteTipo !== '') {
            $whereParts[] = '(
                LOWER(COALESCE(u.perfil, u.role, \'\')) = ?
                OR LOWER(COALESCE(u.role, u.perfil, \'\')) = ?
            )';
            $params[] = $clienteTipo;
            $params[] = $clienteTipo;
        }

        $hasAttendantTypeCol = $this->columnExists($pdo, 'support_tickets', 'attendant_type');
        $hasAttendantUserIdCol = $this->columnExists($pdo, 'support_tickets', 'attendant_user_id');
        if ($atendenteId > 0 && $hasAttendantUserIdCol) {
            $whereParts[] = 't.attendant_user_id = ?';
            $params[] = $atendenteId;
        }
        $where = '';
        if (!empty($whereParts)) {
            $where = ' WHERE ' . implode(' AND ', $whereParts) . ' ';
        }

        $sql = 'SELECT t.*, u.nome AS usuario_nome, u.email AS usuario_email, a.nome AS atendente_nome '
            . 'FROM support_tickets t '
            . 'INNER JOIN usuarios u ON u.id = t.usuario_id '
            . 'LEFT JOIN usuarios a ON a.id = t.attendant_user_id '
            . $where
            . 'ORDER BY COALESCE(t.updated_at, t.created_at) ASC, t.created_at ASC';

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $tickets = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Lista de atendentes para filtro (admin/suporte)
        $atendentes = [];
        try {
            $stA = $pdo->prepare("SELECT id, COALESCE(nome, name) AS nome, email FROM usuarios WHERE (LOWER(COALESCE(role,'')) IN ('admin','suporte') OR LOWER(COALESCE(perfil,'')) IN ('admin','suporte')) ORDER BY COALESCE(nome, name) ASC");
            $stA->execute();
            $atendentes = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $atendentes = [];
        }

        // Detectar tickets com mensagens não lidas do cliente (para exibir bolinha vermelha)
        $adminUid = $this->getLoggedUserId();
        $unreadTicketIds = [];
        try {
            if ($adminUid > 0 && $this->tableExists($pdo, 'support_ticket_views')) {
                $sqlUn = "
                    SELECT m.ticket_id
                    FROM (
                        SELECT ticket_id, MAX(created_at) AS last_client_msg_at
                        FROM support_ticket_messages
                        WHERE autor_tipo = 'cliente'
                        GROUP BY ticket_id
                    ) m
                    LEFT JOIN support_ticket_views v
                        ON v.ticket_id = m.ticket_id AND v.viewer_type = 'admin' AND v.viewer_user_id = ?
                    WHERE v.last_seen_at IS NULL OR m.last_client_msg_at > v.last_seen_at
                ";
                $stUn = $pdo->prepare($sqlUn);
                $stUn->execute([$adminUid]);
                $unreadTicketIds = array_column($stUn->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'ticket_id');
                $unreadTicketIds = array_map('intval', $unreadTicketIds);
            }
        } catch (\Exception $e) {
            $unreadTicketIds = [];
        }

        // Marcar flag has_unread em cada ticket
        foreach ($tickets as &$_t) {
            $_t['has_unread'] = in_array((int) ($_t['id'] ?? 0), $unreadTicketIds, true);
        }
        unset($_t);

        $sidebarActive = 'tickets';
        ob_start();
        include __DIR__ . '/../Views/admin/tickets.php';
        $content = ob_get_clean();
        $title = __('admin.tickets.page_title', 'Tickets');
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function ver(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();

        $adminUid = $this->getLoggedUserId();
        if ($adminUid > 0 && $id > 0) {
            $this->markAdminSeen($pdo, $id, $adminUid);
            // Marcar mensagens do cliente como lidas pelo admin
            try {
                $cols = [];
                try { $stC = $pdo->query('DESCRIBE support_ticket_messages'); $cols = $stC ? ($stC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : []; } catch (\Exception $e) { $cols = []; }
                if (in_array('read_at', $cols, true)) {
                    $pdo->prepare("UPDATE support_ticket_messages SET read_at = NOW(), read_by_user_id = ? WHERE ticket_id = ? AND autor_tipo = 'cliente' AND read_at IS NULL")->execute([$adminUid, $id]);
                }
            } catch (\Exception $e) {}
        }

        $st = $pdo->prepare('SELECT t.*, u.nome AS usuario_nome, u.email AS usuario_email FROM support_tickets t INNER JOIN usuarios u ON u.id = t.usuario_id WHERE t.id = ? LIMIT 1');
        $st->execute([$id]);
        $ticket = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$ticket) {
            echo '<div class="alert alert-danger">' . __('admin.tickets.ticket_not_found', 'Ticket não encontrado.') . '</div>';
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

        // Histórico de tickets (do pedido e do cliente)
        $ticketsDoPedido = [];
        $ticketsDoCliente = [];
        try {
            $clienteId = (int) ($ticket['usuario_id'] ?? 0);
            $pedidoIdAtual = (int) ($ticket['pedido_id'] ?? 0);

            if ($pedidoIdAtual > 0) {
                $stTp = $pdo->prepare('SELECT id, pedido_id, assunto, status, COALESCE(updated_at, created_at) AS atualizado_em FROM support_tickets WHERE pedido_id = ? AND id <> ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 10');
                $stTp->execute([(int) $pedidoIdAtual, (int) $id]);
                $ticketsDoPedido = $stTp->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }

            if ($clienteId > 0) {
                $stTc = $pdo->prepare('SELECT id, pedido_id, assunto, status, COALESCE(updated_at, created_at) AS atualizado_em FROM support_tickets WHERE usuario_id = ? AND id <> ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 20');
                $stTc->execute([(int) $clienteId, (int) $id]);
                $ticketsDoCliente = $stTc->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $ticketsDoPedido = [];
            $ticketsDoCliente = [];
        }

        // Detalhes do pedido relacionado ao ticket
        $pedidoDetalhes = [];
        try {
            if (!empty($ticket['pedido_id'])) {
                $pedidoModel = new PedidoEcommerce();
                $pedidoDetalhes = $pedidoModel->getComDetalhes((int) $ticket['pedido_id']) ?: [];
            }
        } catch (\Exception $e) {
            $pedidoDetalhes = [];
        }

        $pedidoManual = false;
        try {
            $pedidoManual = is_array($pedidoDetalhes) && strtolower(trim((string) ($pedidoDetalhes['origem_pedido'] ?? ''))) === 'manual';
        } catch (\Exception $e) {
            $pedidoManual = false;
        }

        $vendedores = [];
        if ($pedidoManual) {
            try {
                $stV = $pdo->prepare("SELECT id, COALESCE(nome, name) AS nome, email FROM usuarios WHERE (LOWER(COALESCE(role,'')) = 'vendedor' OR LOWER(COALESCE(perfil,'')) = 'vendedor') AND COALESCE(ativo, active, 1) = 1 ORDER BY COALESCE(nome, name) ASC");
                $stV->execute();
                $vendedores = $stV->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $vendedores = [];
            }
        }

        $selectedVendedorId = (int) ($request->getParam('vendedor_id') ?? 0);
        if ($selectedVendedorId <= 0 && $pedidoManual && is_array($pedidoDetalhes)) {
            $selectedVendedorId = (int) ($pedidoDetalhes['admin_criador_id'] ?? 0);
        }

        $vendorChatMessages = [];
        try {
            if ($pedidoManual && $selectedVendedorId > 0 && $this->tableExists($pdo, 'support_ticket_vendor_messages')) {
                $stVC = $pdo->prepare('SELECT * FROM support_ticket_vendor_messages WHERE ticket_id = ? AND vendedor_id = ? ORDER BY id ASC');
                $stVC->execute([(int) $id, (int) $selectedVendedorId]);
                $vendorChatMessages = $stVC->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $vendorChatMessages = [];
        }

        // anexos do chat interno (por vendedor)
        try {
            if (!empty($vendorChatMessages) && $this->tableExists($pdo, 'support_ticket_vendor_message_attachments')) {
                $stA = $pdo->prepare('SELECT * FROM support_ticket_vendor_message_attachments WHERE ticket_id = ? AND vendedor_id = ? ORDER BY id ASC');
                $stA->execute([(int) $id, (int) $selectedVendedorId]);
                $rowsA = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $byMsg = [];
                foreach ($rowsA as $r) {
                    $mid = (int) ($r['message_id'] ?? 0);
                    if ($mid <= 0) continue;
                    if (!isset($byMsg[$mid])) $byMsg[$mid] = [];
                    $byMsg[$mid][] = $r;
                }
                foreach ($vendorChatMessages as &$vm) {
                    $mid = (int) ($vm['id'] ?? 0);
                    if ($mid > 0 && isset($byMsg[$mid])) {
                        $vm['attachments'] = $byMsg[$mid];
                    }
                }
                unset($vm);
            }
        } catch (\Exception $e) {
        }

        // badge: existe mensagem do vendedor não vista?
        $vendorChatHasUnread = false;
        try {
            $viewerUid = (int) $this->getLoggedUserId();
            if ($pedidoManual && $selectedVendedorId > 0 && $viewerUid > 0 && $this->tableExists($pdo, 'support_ticket_vendor_messages')) {
                $lastSeen = null;
                if ($this->tableExists($pdo, 'support_ticket_vendor_views')) {
                    $stLs = $pdo->prepare("SELECT last_seen_at FROM support_ticket_vendor_views WHERE ticket_id = ? AND vendedor_id = ? AND viewer_type = 'suporte' AND viewer_user_id = ? LIMIT 1");
                    $stLs->execute([(int) $id, (int) $selectedVendedorId, (int) $viewerUid]);
                    $lastSeen = $stLs->fetchColumn();
                }

                if ($lastSeen) {
                    $stUn = $pdo->prepare("SELECT COUNT(*) FROM support_ticket_vendor_messages WHERE ticket_id = ? AND vendedor_id = ? AND sender_type = 'vendedor' AND created_at > ?");
                    $stUn->execute([(int) $id, (int) $selectedVendedorId, (string) $lastSeen]);
                } else {
                    $stUn = $pdo->prepare("SELECT COUNT(*) FROM support_ticket_vendor_messages WHERE ticket_id = ? AND vendedor_id = ? AND sender_type = 'vendedor'");
                    $stUn->execute([(int) $id, (int) $selectedVendedorId]);
                }

                $vendorChatHasUnread = ((int) ($stUn->fetchColumn() ?: 0)) > 0;
            }
        } catch (\Exception $e) {
            $vendorChatHasUnread = false;
        }

        $stM = $pdo->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stM->execute([$id]);
        $messages = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $ticketFiles = [];
        try {
            if ($this->tableExists($pdo, 'support_ticket_files')) {
                $stF = $pdo->prepare('SELECT * FROM support_ticket_files WHERE ticket_id = ? ORDER BY id DESC');
                $stF->execute([(int) $id]);
                $ticketFiles = $stF->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $ticketFiles = [];
        }

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
        $title = __('admin.tickets.ticket_number', 'Ticket #{n}', ['n'=>$id]);
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function contatarVendedor(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        if ($id <= 0) {
            header('Location: /admin/tickets');
            exit;
        }

        $vendedorId = (int) ($request->getParam('vendedor_id') ?? 0);
        $msg = trim((string) ($request->getParam('mensagem') ?? ''));
        if ($vendedorId <= 0 || $msg === '') {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $pdo = $this->getPdo();

        // buscar ticket + pedido
        $ticket = null;
        try {
            $stT = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? LIMIT 1');
            $stT->execute([$id]);
            $ticket = $stT->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $ticket = null;
        }
        if (!$ticket) {
            header('Location: /admin/tickets');
            exit;
        }

        $pedidoId = (int) ($ticket['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        // garantir que é pedido manual
        $origem = '';
        try {
            $stP = $pdo->prepare('SELECT origem_pedido FROM pedidos WHERE id = ? LIMIT 1');
            $stP->execute([$pedidoId]);
            $origem = strtolower(trim((string) ($stP->fetchColumn() ?: '')));
        } catch (\Exception $e) {
            $origem = '';
        }
        if ($origem !== 'manual') {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        // pegar vendedor
        $vendNome = '';
        $vendEmail = '';
        try {
            $stU = $pdo->prepare("SELECT COALESCE(nome, name) AS nome, email FROM usuarios WHERE id = ? LIMIT 1");
            $stU->execute([$vendedorId]);
            $u = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
            $vendNome = (string) ($u['nome'] ?? '');
            $vendEmail = (string) ($u['email'] ?? '');
        } catch (\Exception $e) {
        }

        // registrar em internal_notes (sem novas migrations)
        $adminUid = $this->getLoggedUserId();
        $adminNome = '';
        try {
            $adminNome = (string) ($_SESSION['usuario_nome'] ?? '');
        } catch (\Exception $e) {
            $adminNome = '';
        }
        $stamp = date('Y-m-d H:i:s');
        $log = "[" . __('admin.tickets.contact_vendor_tag', 'CONTATO VENDEDOR') . "] {$stamp} | " . __('admin.tickets.log_by', 'por:') . " {$adminNome} (#{$adminUid}) | " . __('admin.tickets.log_to', 'para:') . " {$vendNome} (#{$vendedorId}) {$vendEmail}\n{$msg}\n";

        // gravar chat interno por vendedor, quando a tabela existir
        $vendorMessageId = 0;
        try {
            if ($this->tableExists($pdo, 'support_ticket_vendor_messages')) {
                $stIns = $pdo->prepare('INSERT INTO support_ticket_vendor_messages (ticket_id, vendedor_id, sender_type, sender_user_id, mensagem) VALUES (?, ?, ?, ?, ?)');
                $stIns->execute([(int) $id, (int) $vendedorId, 'suporte', (int) $adminUid, (string) $msg]);
                $vendorMessageId = (int) $pdo->lastInsertId();
            }
        } catch (\Exception $e) {
        }

        // upload de anexos (documentos) no chat interno
        try {
            if ($vendorMessageId > 0 && $this->tableExists($pdo, 'support_ticket_vendor_message_attachments') && isset($_FILES['documentos']) && is_array($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                $dirs = $this->getUploadsDirVendorChat();
                $absDir = (string) ($dirs['abs'] ?? '');
                $webDir = (string) ($dirs['web'] ?? '/uploads/tickets/vendor-chat/');
                if ($absDir !== '') {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $blockedExt = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm'];
                    $max = 20 * 1024 * 1024;

                    foreach ($_FILES['documentos']['name'] as $k => $origName) {
                        if (($_FILES['documentos']['error'][$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                        $tmp = (string) ($_FILES['documentos']['tmp_name'][$k] ?? '');
                        if ($tmp === '' || !is_uploaded_file($tmp)) continue;
                        $size = (int) ($_FILES['documentos']['size'][$k] ?? 0);
                        if ($size <= 0 || $size > $max) continue;

                        $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $origName);
                        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                        if ($ext !== '' && in_array($ext, $blockedExt, true)) continue;

                        $mime = (string) $finfo->file($tmp);
                        $fname = 't' . (int) $id . '_v' . (int) $vendedorId . '_m' . (int) $vendorMessageId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $clean;
                        $abs = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
                        $rel = rtrim($webDir, '/') . '/' . $fname;

                        if (!@move_uploaded_file($tmp, $abs)) continue;

                        $stA = $pdo->prepare('INSERT INTO support_ticket_vendor_message_attachments (message_id, ticket_id, vendedor_id, file_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $stA->execute([(int) $vendorMessageId, (int) $id, (int) $vendedorId, $rel, (string) $origName, $mime, $size]);
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/tickets/' . $id . '?vendedor_id=' . (int) $vendedorId);
        exit;
    }

    public function marcarVendorChatVisto(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        $vendedorId = (int) ($request->getParam('vendedor_id') ?? 0);
        if ($id <= 0 || $vendedorId <= 0) {
            header('Location: /admin/tickets/' . (int) $id);
            exit;
        }

        $pdo = $this->getPdo();
        $viewerUid = (int) $this->getLoggedUserId();
        $this->markVendorChatSeen($pdo, (int) $id, (int) $vendedorId, (int) $viewerUid);

        header('Location: /admin/tickets/' . (int) $id . '?vendedor_id=' . (int) $vendedorId);
        exit;
    }

    public function uploadArquivoTicket(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $adminUid = $this->getLoggedUserId();
        $id = (int) ($id ?? $request->getParam('id'));
        if ($id <= 0) {
            header('Location: /admin/tickets');
            exit;
        }

        $pdo = $this->getPdo();
        if (!$this->tableExists($pdo, 'support_ticket_files')) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        if (!isset($_FILES['arquivo']) || !is_array($_FILES['arquivo'])) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        if (($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $tmp = (string) ($_FILES['arquivo']['tmp_name'] ?? '');
        $origName = (string) ($_FILES['arquivo']['name'] ?? '');
        $size = (int) ($_FILES['arquivo']['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        // 20MB
        if ($size > (20 * 1024 * 1024)) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);

        // bloquear uploads perigosos por extensão
        $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $origName);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        $blockedExt = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm'];
        if ($ext !== '' && in_array($ext, $blockedExt, true)) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $dirs = $this->getUploadsDirTicketFiles();
        $absDir = (string) ($dirs['abs'] ?? '');
        $webDir = (string) ($dirs['web'] ?? '/uploads/tickets/files/');
        if ($absDir === '') {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $fname = 't' . (int) $id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $clean;
        if ($fname === '' || $fname === null) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $abs = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
        $rel = rtrim($webDir, '/') . '/' . $fname;

        if (!@move_uploaded_file($tmp, $abs)) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        try {
            $st = $pdo->prepare('INSERT INTO support_ticket_files (ticket_id, uploader_type, uploader_user_id, file_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([(int) $id, 'admin', (int) $adminUid, $rel, $origName, $mime, $size]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/tickets/' . $id);
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

            // Define atendente do ticket (admin/suporte)
            $hasAttendantTypeCol = $this->columnExists($pdo, 'support_tickets', 'attendant_type');
            $hasAttendantUserIdCol = $this->columnExists($pdo, 'support_tickets', 'attendant_user_id');
            if ($hasAttendantTypeCol || $hasAttendantUserIdCol) {
                $set = [];
                $p = [];
                if ($hasAttendantTypeCol) {
                    $set[] = 'attendant_type = ?';
                    $p[] = (string) $this->getUserRoleOrPerfil();
                }
                if ($hasAttendantUserIdCol) {
                    $set[] = 'attendant_user_id = ?';
                    $p[] = (int) $adminUid;
                }
                $p[] = (int) $id;
                if (!empty($set)) {
                    $pdo->prepare('UPDATE support_tickets SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($p);
                }
            }
            $pdo->commit();

            try {
                $not = new SupportTicketNotificationService();
                $not->notificarRespostaAdmin($pedidoId, (int) $id, (int) $adminUid, (string) $msg);
            } catch (\Exception $e) {
            }

            // Email: notificar cliente que o admin respondeu
            try {
                $stCli = $pdo->prepare("SELECT t.usuario_id, u.nome, u.email FROM support_tickets t LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE t.id = ? LIMIT 1");
                $stCli->execute([$id]);
                $cliData = $stCli->fetch(\PDO::FETCH_ASSOC);
                if ($cliData && !empty($cliData['email'])) {
                    $adminNome = '';
                    try { $stAdm = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $stAdm->execute([$adminUid]); $adminNome = (string)($stAdm->fetchColumn() ?: __('admin.tickets.braziliana_team', 'Equipe Braziliana')); } catch (\Exception $e) { $adminNome = __('admin.tickets.braziliana_team', 'Equipe Braziliana'); }
                    SupportTicketNotificationService::enviarEmailTicket(
                        (string)$cliData['email'],
                        __('admin.tickets.email_new_reply_subject', 'Nova resposta no Ticket #{n} - Braziliana', ['n'=>$id]),
                        (string)($cliData['nome'] ?? __('admin.tickets.customer', 'Cliente')),
                        (int)$id,
                        $msg,
                        $adminNome,
                        'admin'
                    );
                }
            } catch (\Exception $e) {}

            // Webhook: notificar automação que o ticket recebeu primeira resposta (dentro de 30 min)
            try {
                $stWh = $pdo->prepare("SELECT webhook_aguardando_resposta, webhook_resposta_deadline, webhook_resposta_enviada, webhook_resposta_url FROM support_tickets WHERE id = ? LIMIT 1");
                $stWh->execute([$id]);
                $whData = $stWh->fetch(\PDO::FETCH_ASSOC);

                if ($whData
                    && (int)($whData['webhook_aguardando_resposta'] ?? 0) === 1
                    && (int)($whData['webhook_resposta_enviada'] ?? 0) === 0
                    && !empty($whData['webhook_resposta_url'])
                ) {
                    $deadline = (string)($whData['webhook_resposta_deadline'] ?? '');
                    $dentroDoDeadline = ($deadline === '' || strtotime($deadline) >= time());

                    if ($dentroDoDeadline) {
                        $whUrl = (string)$whData['webhook_resposta_url'];
                        $whPayload = [
                            'evento' => 'ticket_respondido',
                            'ticket_id' => (int)$id,
                            'mensagem_resposta' => $msg,
                            'respondido_por' => (int)$adminUid,
                            'respondido_em' => date('Y-m-d H:i:s'),
                        ];

                        // Buscar dados do cliente do ticket
                        try {
                            $stCli = $pdo->prepare("SELECT t.usuario_id, u.nome, u.email, u.telefone, u.suite FROM support_tickets t LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE t.id = ? LIMIT 1");
                            $stCli->execute([$id]);
                            $cli = $stCli->fetch(\PDO::FETCH_ASSOC);
                            if ($cli) {
                                $whPayload['usuario_id'] = (int)($cli['usuario_id'] ?? 0);
                                $whPayload['cliente_nome'] = (string)($cli['nome'] ?? '');
                                $whPayload['cliente_email'] = (string)($cli['email'] ?? '');
                                $whPayload['cliente_telefone'] = (string)($cli['telefone'] ?? '');
                                $whPayload['telefone_limpo'] = preg_replace('/\D/', '', (string)($cli['telefone'] ?? ''));
                                $whPayload['cliente_suite'] = (string)($cli['suite'] ?? '');
                            }
                        } catch (\Exception $e) {}

                        // Enviar webhook
                        if (function_exists('curl_init')) {
                            $ch = curl_init($whUrl);
                            curl_setopt_array($ch, [
                                CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => json_encode($whPayload),
                                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_TIMEOUT => 10,
                            ]);
                            curl_exec($ch);
                            curl_close($ch);
                        }

                        // Marcar como enviada
                        $pdo->prepare("UPDATE support_tickets SET webhook_resposta_enviada = 1 WHERE id = ?")->execute([$id]);
                    }
                }
            } catch (\Exception $e) {}
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
        $decision = trim((string) ($request->getParam('closure_decision') ?? $request->getParam('decisao') ?? ''));
        $hasDecisionCol = $this->columnExists($pdo, 'support_tickets', 'closure_decision');
        $hasClosedByTypeCol = $this->columnExists($pdo, 'support_tickets', 'closed_by_type');
        $hasClosedByUidCol = $this->columnExists($pdo, 'support_tickets', 'closed_by_user_id');

        if ($hasDecisionCol && $decision === '') {
            header('Location: /admin/tickets/' . $id . '?closure_error=1');
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
            $params[] = 'admin';
        }
        if ($hasClosedByUidCol) {
            $set[] = 'closed_by_user_id = ?';
            $params[] = (int) $this->getLoggedUserId();
        }
        $params[] = $id;

        $pdo->prepare('UPDATE support_tickets SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

        try {
            $stHasMsg = $pdo->query("SHOW TABLES LIKE 'support_ticket_messages'");
            $hasMsg = (bool) ($stHasMsg && $stHasMsg->fetchColumn());
            if ($hasMsg && $decision !== '') {
                $adminUid = (int) $this->getLoggedUserId();
                $stMsg = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
                $stMsg->execute([(int) $id, 'admin', (int) $adminUid, (string) (__('admin.tickets.ticket_closure', 'Encerramento do ticket:') . ' ' . $decision)]);
            }
        } catch (\Exception $e) {
        }

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

    public function salvarNotasInternas(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);

        $id = (int) ($id ?? $request->getParam('id'));
        if ($id <= 0) {
            header('Location: /admin/tickets');
            exit;
        }

        $notas = trim((string) ($request->getParam('internal_notes') ?? $request->getParam('notas') ?? ''));
        $pdo = $this->getPdo();

        if (!$this->columnExists($pdo, 'support_tickets', 'internal_notes')) {
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        try {
            $st = $pdo->prepare('UPDATE support_tickets SET internal_notes = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $st->execute([$notas, $id]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/tickets/' . $id);
        exit;
    }
}
