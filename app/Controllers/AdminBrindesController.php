<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminBrindesController extends Controller {
    private $db;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->db = \Config\Database::getConnection();
    }

    /**
     * GET /admin/brindes
     * Página principal: histórico de brindes configurados + devoluções
     */
    public function index(Request $request) {
        // Garantir tabelas existem
        $this->ensureTables();

        $tab = strtolower(trim((string) $request->getParam('tab', 'configurados')));
        if (!in_array($tab, ['configurados', 'vendas', 'devolucoes'], true)) $tab = 'configurados';

        // Tab 1: Brindes configurados (histórico completo)
        $brindesConfigurados = [];
        try {
            $cols = [];
            try { $st = $this->db->query('DESCRIBE produtos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
            $nomeCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : null);

            $sql = "SELECT pb.*, "
                . ($nomeCol ? "pp.{$nomeCol} as produto_principal_nome, pb2.{$nomeCol} as brinde_nome" : "'' as produto_principal_nome, '' as brinde_nome")
                . ", u.nome as criado_por_nome"
                . " FROM produto_brindes pb"
                . " LEFT JOIN produtos pp ON pp.id = pb.produto_id"
                . " LEFT JOIN produtos pb2 ON pb2.id = pb.brinde_produto_id"
                . " LEFT JOIN usuarios u ON u.id = pb.criado_por"
                . " ORDER BY pb.id DESC LIMIT 200";
            $st = $this->db->query($sql);
            $brindesConfigurados = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {}

        // Tab 2: Vendas com brinde (pedidos que tiveram brinde)
        $vendasBrinde = [];
        try {
            $itensTable = $this->getItensTable();
            if ($itensTable) {
                $cols = [];
                try { $st = $this->db->query('DESCRIBE produtos'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $nomeCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : null);

                $sql = "SELECT i.pedido_id, i.produto_id, i.quantidade, i.created_at as data_venda,"
                    . ($nomeCol ? " p.{$nomeCol} as produto_nome," : " '' as produto_nome,")
                    . " ped.status as pedido_status, u.nome as cliente_nome, u.email as cliente_email"
                    . " FROM {$itensTable} i"
                    . " LEFT JOIN produtos p ON p.id = i.produto_id"
                    . " LEFT JOIN pedidos ped ON ped.id = i.pedido_id"
                    . " LEFT JOIN usuarios u ON u.id = ped.usuario_id"
                    . " WHERE (i.is_brinde = 1 OR (COALESCE(i.preco_unitario, i.valor_unitario, 999) <= 0.02 AND i.produto_id IN (SELECT brinde_produto_id FROM produto_brindes)))"
                    . " AND ped.deleted_at IS NULL"
                    . " ORDER BY i.id DESC LIMIT 200";
                $st = $this->db->query($sql);
                $vendasBrinde = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
            }
        } catch (\Exception $e) {}

        // Tab 3: Devoluções de impostos
        $devolucoes = [];
        try {
            $sql = "SELECT d.*, u.nome as cliente_nome, u.email as cliente_email,"
                . " p.name as produto_nome"
                . " FROM brinde_devolucao_impostos d"
                . " LEFT JOIN usuarios u ON u.id = d.usuario_id"
                . " LEFT JOIN produtos p ON p.id = d.produto_brinde_id"
                . " ORDER BY d.id DESC LIMIT 200";
            $st = $this->db->query($sql);
            $devolucoes = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {}

        // Estatísticas
        $stats = ['total_configurados' => count($brindesConfigurados), 'total_vendas' => count($vendasBrinde), 'total_devolucoes' => count($devolucoes), 'valor_devolvido' => 0, 'valor_pendente' => 0];
        foreach ($devolucoes as $d) {
            if ($d['status'] === 'devolvido') $stats['valor_devolvido'] += (float) $d['valor_imposto_devolvido'];
            if ($d['status'] === 'pendente') $stats['valor_pendente'] += (float) $d['valor_imposto_devolvido'];
        }

        // Render
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        $sidebarActive = 'brindes';
        ob_start();
        require __DIR__ . '/../Views/admin/brindes/index.php';
        $content = ob_get_clean();
        $title = __('admin.gifts.history_title', 'Histórico de Brindes');
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function ensureTables(): void {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS produto_brindes (
                id INT AUTO_INCREMENT PRIMARY KEY, produto_id INT NOT NULL, brinde_produto_id INT NOT NULL,
                quantidade_brinde INT NOT NULL DEFAULT 1, data_inicio DATETIME NOT NULL, data_fim DATETIME NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1, criado_por INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pb_produto (produto_id), INDEX idx_pb_brinde (brinde_produto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->db->exec("CREATE TABLE IF NOT EXISTS brinde_devolucao_impostos (
                id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, pedido_item_id INT NULL,
                usuario_id INT NOT NULL, produto_brinde_id INT NOT NULL,
                valor_imposto_devolvido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_imposto_devolvido_brl DECIMAL(10,2) NULL,
                status ENUM('pendente','devolvido','cancelado') NOT NULL DEFAULT 'pendente',
                devolvido_em DATETIME NULL, carteira_transacao_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bdi_pedido (pedido_id), INDEX idx_bdi_usuario (usuario_id), INDEX idx_bdi_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {}
    }

    private function getItensTable(): ?string {
        foreach (['pedido_itens', 'pedido_items'] as $t) {
            try {
                $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $st->execute([$t]);
                if ((int) $st->fetchColumn() > 0) return $t;
            } catch (\Exception $e) {}
        }
        return null;
    }
}
