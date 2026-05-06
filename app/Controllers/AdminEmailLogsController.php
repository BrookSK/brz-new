<?php
namespace App\Controllers;

use App\Core\Request;
use Config\Database;

class AdminEmailLogsController extends Controller {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Listagem de logs de emails enviados pelo sistema
     * GET /admin/emails
     */
    public function index(Request $request) {
        // Filtros
        $filtroTipo = trim($request->getParam('tipo', ''));
        $filtroStatus = trim($request->getParam('status', ''));
        $filtroDataInicio = trim($request->getParam('data_inicio', ''));
        $filtroDataFim = trim($request->getParam('data_fim', ''));
        $filtroBusca = trim($request->getParam('busca', ''));
        $pagina = max(1, (int) $request->getParam('pagina', 1));
        $porPagina = 50;

        $where = [];
        $params = [];

        if ($filtroTipo !== '') {
            $where[] = 'el.tipo = :tipo';
            $params[':tipo'] = $filtroTipo;
        }

        if ($filtroStatus !== '') {
            $where[] = 'el.status = :status';
            $params[':status'] = $filtroStatus;
        }

        if ($filtroDataInicio !== '') {
            $where[] = 'el.created_at >= :data_inicio';
            $params[':data_inicio'] = $filtroDataInicio . ' 00:00:00';
        }

        if ($filtroDataFim !== '') {
            $where[] = 'el.created_at <= :data_fim';
            $params[':data_fim'] = $filtroDataFim . ' 23:59:59';
        }

        if ($filtroBusca !== '') {
            $where[] = '(el.destinatario_email LIKE :busca OR el.destinatario_nome LIKE :busca2 OR el.assunto LIKE :busca3)';
            $params[':busca'] = '%' . $filtroBusca . '%';
            $params[':busca2'] = '%' . $filtroBusca . '%';
            $params[':busca3'] = '%' . $filtroBusca . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total para paginação
        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM email_logs el {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $totalPaginas = max(1, ceil($total / $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        // Buscar registros
        $sql = "SELECT el.* FROM email_logs el {$whereClause} ORDER BY el.created_at DESC LIMIT {$porPagina} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Buscar tipos distintos para o filtro
        $stmtTipos = $this->db->query("SELECT DISTINCT tipo FROM email_logs ORDER BY tipo ASC");
        $tipos = $stmtTipos ? $stmtTipos->fetchAll(\PDO::FETCH_COLUMN) : [];

        // Estatísticas rápidas
        $stmtStats = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros
            FROM email_logs
        ");
        $stats = $stmtStats ? $stmtStats->fetch(\PDO::FETCH_ASSOC) : ['total' => 0, 'enviados' => 0, 'erros' => 0];

        $filtros = compact('filtroTipo', 'filtroStatus', 'filtroDataInicio', 'filtroDataFim', 'filtroBusca');

        require __DIR__ . '/../Views/admin/emails/index.php';
    }
}
