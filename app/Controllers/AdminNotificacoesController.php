<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\NotificationService;
use Config\Database;

/**
 * Histórico e gestão de notificações de pedido enviadas ao cliente (e-mail e WhatsApp),
 * com opção de reenvio.
 *
 * Fontes de dados:
 *  - E-mail:   tabela email_event_log (registrada pelo EmailService a cada envio de evento)
 *  - WhatsApp: tabela webhook_disparos (registrada pelo NotificationService a cada disparo de webhook)
 *
 * Reenvio: rechama NotificationService::notificarEventoPedido(evento, pedido_id). Para e-mail,
 * a chave de deduplicação do evento/pedido é removida antes, para permitir o reenvio manual.
 */
class AdminNotificacoesController extends Controller
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Listagem unificada de notificações (e-mail + WhatsApp).
     * GET /admin/notificacoes
     */
    public function index(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $filtroCanal = trim((string) $request->getParam('canal', ''));
        $filtroEvento = trim((string) $request->getParam('evento', ''));
        $filtroStatus = trim((string) $request->getParam('status', ''));
        $filtroBusca = trim((string) $request->getParam('busca', ''));
        $filtroPedido = (int) $request->getParam('pedido_id', 0);
        $pagina = max(1, (int) $request->getParam('pagina', 1));
        $porPagina = 50;

        $registros = [];

        // ── E-mails (email_event_log) ────────────────────────────────────────
        if (($filtroCanal === '' || $filtroCanal === 'email') && $this->tableExists('email_event_log')) {
            try {
                $where = [];
                $params = [];
                if ($filtroEvento !== '') { $where[] = 'evento = :evento'; $params[':evento'] = $filtroEvento; }
                if ($filtroPedido > 0) { $where[] = 'pedido_id = :pid'; $params[':pid'] = $filtroPedido; }
                if ($filtroBusca !== '') {
                    $where[] = '(to_email LIKE :busca OR subject LIKE :busca2)';
                    $params[':busca'] = '%' . $filtroBusca . '%';
                    $params[':busca2'] = '%' . $filtroBusca . '%';
                }
                $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
                $sql = "SELECT id, evento, to_email, subject, pedido_id, created_at FROM email_event_log {$whereSql} ORDER BY created_at DESC LIMIT 500";
                $st = $this->db->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $registros[] = [
                        'canal' => 'email',
                        'id' => (int) $row['id'],
                        'evento' => (string) ($row['evento'] ?? ''),
                        'pedido_id' => (int) ($row['pedido_id'] ?? 0),
                        'destino' => (string) ($row['to_email'] ?? ''),
                        'assunto' => (string) ($row['subject'] ?? ''),
                        // E-mail só registra envios bem-sucedidos (dedupe reservado antes do envio).
                        'status' => 'enviado',
                        'detalhe' => '',
                        'created_at' => (string) ($row['created_at'] ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                error_log('[NOTIF][LISTA] Erro ao ler email_event_log: ' . $e->getMessage());
            }
        }

        // ── WhatsApp (webhook_disparos) ──────────────────────────────────────
        if (($filtroCanal === '' || $filtroCanal === 'whatsapp') && $this->tableExists('webhook_disparos')) {
            try {
                $where = [];
                $params = [];
                if ($filtroPedido > 0) { $where[] = 'wd.pedido_id = :pid'; $params[':pid'] = $filtroPedido; }
                if ($filtroStatus !== '') { $where[] = 'wd.status = :status'; $params[':status'] = $filtroStatus; }
                $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
                $sql = "SELECT wd.id, wd.pedido_id, wd.payload, wd.status, wd.response_code, wd.disparado_em
                        FROM webhook_disparos wd {$whereSql} ORDER BY wd.disparado_em DESC LIMIT 500";
                $st = $this->db->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $payload = json_decode((string) ($row['payload'] ?? ''), true);
                    $evento = is_array($payload) ? (string) ($payload['evento'] ?? '') : '';
                    $destino = is_array($payload) ? (string) ($payload['to'] ?? '') : '';
                    $mensagem = is_array($payload) ? (string) ($payload['message'] ?? '') : '';

                    // Aplicar filtros de evento/busca que dependem do payload decodificado.
                    if ($filtroEvento !== '' && $evento !== $filtroEvento) continue;
                    if ($filtroBusca !== '' && stripos($destino . ' ' . $mensagem, $filtroBusca) === false) continue;

                    $registros[] = [
                        'canal' => 'whatsapp',
                        'id' => (int) $row['id'],
                        'evento' => $evento,
                        'pedido_id' => (int) ($row['pedido_id'] ?? 0),
                        'destino' => $destino,
                        'assunto' => $mensagem,
                        'status' => (string) ($row['status'] ?? ''),
                        'detalhe' => 'HTTP ' . (string) ($row['response_code'] ?? '-'),
                        'created_at' => (string) ($row['disparado_em'] ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                error_log('[NOTIF][LISTA] Erro ao ler webhook_disparos: ' . $e->getMessage());
            }
        }

        // Ordenar o conjunto unificado por data desc.
        usort($registros, static function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        // Eventos distintos (para o filtro).
        $eventos = [];
        foreach ($registros as $r) {
            $ev = (string) ($r['evento'] ?? '');
            if ($ev !== '') $eventos[$ev] = $ev;
        }
        ksort($eventos);
        $eventos = array_values($eventos);

        // Estatísticas.
        $stats = [
            'total' => count($registros),
            'email' => count(array_filter($registros, fn($r) => $r['canal'] === 'email')),
            'whatsapp' => count(array_filter($registros, fn($r) => $r['canal'] === 'whatsapp')),
            'erros' => count(array_filter($registros, fn($r) => strtolower((string) $r['status']) === 'erro')),
        ];

        // Paginação em memória (conjunto já limitado a 500 por canal).
        $total = count($registros);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $porPagina;
        $registrosPagina = array_slice($registros, $offset, $porPagina);

        $filtros = [
            'canal' => $filtroCanal,
            'evento' => $filtroEvento,
            'status' => $filtroStatus,
            'busca' => $filtroBusca,
            'pedido_id' => $filtroPedido > 0 ? (string) $filtroPedido : '',
        ];

        $flashSuccess = (string) ($request->getParam('ok') ?? '');
        $flashError = (string) ($request->getParam('erro') ?? '');

        $this->view('admin/notificacoes/index', [
            'registros' => $registrosPagina,
            'eventos' => $eventos,
            'stats' => $stats,
            'filtros' => $filtros,
            'pagina' => $pagina,
            'totalPaginas' => $totalPaginas,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'sidebarActive' => 'notificacoes',
        ]);
    }

    /**
     * Reenvia uma notificação (e-mail e WhatsApp) de um pedido para um evento.
     * POST /admin/notificacoes/reenviar
     * Body JSON: { evento: string, pedido_id: int }
     */
    public function reenviar(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = [];
        }
        $evento = trim((string) ($body['evento'] ?? $request->getParam('evento', '')));
        $pedidoId = (int) ($body['pedido_id'] ?? $request->getParam('pedido_id', 0));

        if ($evento === '' || $pedidoId <= 0) {
            $this->json(['success' => false, 'error' => __('admin.notifications.resend_missing_params', 'Evento e pedido são obrigatórios para reenviar.')], 400);
            return;
        }

        // Remover as chaves de deduplicação de e-mail deste evento/pedido para permitir o reenvio.
        // O NotificationService monta a dedupe key como pedido_event:{evento}:{pedidoId}:{email}.
        if ($this->tableExists('email_event_log')) {
            try {
                $st = $this->db->prepare("DELETE FROM email_event_log WHERE pedido_id = ? AND dedupe_key LIKE ?");
                $st->execute([$pedidoId, 'pedido_event:' . $evento . ':' . $pedidoId . ':%']);
            } catch (\Exception $e) {
                error_log('[NOTIF][REENVIO] Falha ao limpar dedupe de e-mail: ' . $e->getMessage());
            }
        }

        try {
            $notif = new NotificationService();
            $notif->notificarEventoPedido($evento, $pedidoId, []);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => __('admin.notifications.resend_failed', 'Falha ao reenviar: ') . $e->getMessage()], 500);
            return;
        }

        $this->json([
            'success' => true,
            'message' => __('admin.notifications.resend_ok', 'Notificação reenviada (e-mail e WhatsApp, conforme configurado).'),
        ]);
    }
}
