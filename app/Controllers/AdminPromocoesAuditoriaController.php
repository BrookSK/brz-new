<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminPromocoesAuditoriaController extends Controller {

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $pdo = Database::getConnection();

        // Filtros
        $filtroStatus = strtolower(trim((string) $request->getParam('status', 'todas')));
        $filtroBusca = trim((string) $request->getParam('busca', ''));

        // Buscar todos os produtos que têm ou tiveram promoção
        $cols = [];
        try { $st = $pdo->query('DESCRIBE produtos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) { $cols = []; }

        $hasSalePrice = in_array('sale_price', $cols, true);
        $hasSaleExpires = in_array('sale_price_expires', $cols, true);
        $hasName = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'name');
        $hasPrice = in_array('price', $cols, true) ? 'price' : (in_array('valor', $cols, true) ? 'valor' : 'price');

        if (!$hasSalePrice) {
            $promocoes = [];
        } else {
            $where = ['p.sale_price > 0 OR p.sale_price IS NOT NULL'];
            $params = [];

            if ($filtroStatus === 'ativas') {
                $where = ['p.sale_price > 0'];
                if ($hasSaleExpires) {
                    $where[] = '(p.sale_price_expires IS NULL OR p.sale_price_expires > NOW())';
                }
            } elseif ($filtroStatus === 'expiradas') {
                $where = ['p.sale_price > 0'];
                if ($hasSaleExpires) {
                    $where[] = 'p.sale_price_expires IS NOT NULL AND p.sale_price_expires <= NOW()';
                }
            } elseif ($filtroStatus === 'sem_promocao') {
                $where = ['(p.sale_price IS NULL OR p.sale_price = 0)'];
            } else {
                // todas com promoção (ativas + expiradas)
                $where = ['p.sale_price > 0'];
            }

            if ($filtroBusca !== '') {
                $where[] = "(p.{$hasName} LIKE :busca OR p.id = :busca_id)";
                $params[':busca'] = '%' . $filtroBusca . '%';
                $params[':busca_id'] = (int) $filtroBusca;
            }

            $sql = "SELECT p.id, p.{$hasName} AS nome, p.{$hasPrice} AS preco_regular, p.sale_price"
                . ($hasSaleExpires ? ', p.sale_price_expires' : ", NULL AS sale_price_expires")
                . ", p.updated_at"
                . " FROM produtos p"
                . " WHERE " . implode(' AND ', $where)
                . " ORDER BY p.updated_at DESC, p.id DESC"
                . " LIMIT 500";

            $st = $pdo->prepare($sql);
            $st->execute($params);
            $promocoes = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // Buscar histórico (se tabela existir)
        $historico = [];
        try {
            $stT = $pdo->query("SHOW TABLES LIKE 'promocoes_historico'");
            if ($stT && $stT->fetchColumn()) {
                $sqlH = "SELECT * FROM promocoes_historico ORDER BY created_at DESC LIMIT 200";
                $stH = $pdo->query($sqlH);
                $historico = $stH ? $stH->fetchAll(\PDO::FETCH_ASSOC) : [];
            }
        } catch (\Exception $e) {}

        // Stats
        $totalAtivas = 0;
        $totalExpiradas = 0;
        try {
            if ($hasSalePrice) {
                $stA = $pdo->query("SELECT COUNT(*) FROM produtos WHERE sale_price > 0" . ($hasSaleExpires ? " AND (sale_price_expires IS NULL OR sale_price_expires > NOW())" : ""));
                $totalAtivas = (int) ($stA ? $stA->fetchColumn() : 0);
                if ($hasSaleExpires) {
                    $stE = $pdo->query("SELECT COUNT(*) FROM produtos WHERE sale_price > 0 AND sale_price_expires IS NOT NULL AND sale_price_expires <= NOW()");
                    $totalExpiradas = (int) ($stE ? $stE->fetchColumn() : 0);
                }
            }
        } catch (\Exception $e) {}

        $title = 'Auditoria de Promoções';
        $sidebarActive = 'promocoes-auditoria';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/promocoes-auditoria.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }
}
