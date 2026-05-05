<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminPromocoesAgendadasController extends Controller {

    private function getPdo(): \PDO {
        return Database::getConnection();
    }

    private function garantirTabelas(\PDO $pdo): void {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `promocoes_agendadas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nome` VARCHAR(255) NOT NULL,
                `desconto_tipo` ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
                `desconto_valor` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `inicio` DATETIME NOT NULL,
                `fim` DATETIME NOT NULL,
                `status` ENUM('agendada','ativa','finalizada','cancelada') NOT NULL DEFAULT 'agendada',
                `criado_por` INT DEFAULT NULL,
                `criado_por_nome` VARCHAR(191) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status` (`status`),
                INDEX `idx_inicio_fim` (`inicio`, `fim`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `promocoes_agendadas_produtos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `promocao_id` INT NOT NULL,
                `produto_id` INT NOT NULL,
                `preco_original` DECIMAL(12,2) DEFAULT NULL,
                `preco_promocional` DECIMAL(12,2) DEFAULT NULL,
                INDEX `idx_promocao` (`promocao_id`),
                INDEX `idx_produto` (`produto_id`),
                UNIQUE KEY `uk_promo_produto` (`promocao_id`, `produto_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {}
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $pdo = $this->getPdo();
        $this->garantirTabelas($pdo);

        // Atualizar status automaticamente
        $this->atualizarStatusAutomatico($pdo);

        $promocoes = [];
        try {
            $st = $pdo->query("SELECT pa.*, (SELECT COUNT(*) FROM promocoes_agendadas_produtos WHERE promocao_id = pa.id) as total_produtos FROM promocoes_agendadas pa ORDER BY pa.inicio DESC LIMIT 200");
            $promocoes = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {}

        // Dados pro calendário (JSON)
        $eventosCalendario = [];
        foreach ($promocoes as $p) {
            $cor = match($p['status']) {
                'ativa' => '#10b981', 'agendada' => '#3b82f6',
                'finalizada' => '#94a3b8', 'cancelada' => '#ef4444',
                default => '#6b7280'
            };
            $eventosCalendario[] = [
                'id' => (int) $p['id'],
                'title' => $p['nome'] . ' (' . $p['desconto_valor'] . ($p['desconto_tipo'] === 'percentual' ? '%' : '$') . ')',
                'start' => $p['inicio'],
                'end' => $p['fim'],
                'color' => $cor,
                'url' => '/admin/promocoes-agendadas/detalhes/' . $p['id'],
            ];
        }

        // Produtos: não carregar todos no HTML — busca via AJAX
        $produtos = [];

        $title = 'Promoções Agendadas';
        $sidebarActive = 'promocoes-agendadas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/promocoes-agendadas/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function criar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $u = $auth->getUsuarioLogado();

        $pdo = $this->getPdo();
        $this->garantirTabelas($pdo);

        $nome = trim((string) $request->getParam('nome', ''));
        $descontoTipo = in_array($request->getParam('desconto_tipo'), ['percentual', 'fixo']) ? $request->getParam('desconto_tipo') : 'percentual';
        $descontoValor = (float) str_replace(',', '.', (string) $request->getParam('desconto_valor', '0'));
        $inicio = trim((string) $request->getParam('inicio', ''));
        $fim = trim((string) $request->getParam('fim', ''));
        $produtoIds = $request->getParam('produto_ids', []);
        if (!is_array($produtoIds)) $produtoIds = [];

        if ($nome === '' || $descontoValor <= 0 || $inicio === '' || $fim === '' || empty($produtoIds)) {
            $_SESSION['flash_error'] = 'Preencha todos os campos obrigatórios (nome, desconto, datas e pelo menos 1 produto).';
            header('Location: /admin/promocoes-agendadas');
            exit;
        }

        $inicioFmt = date('Y-m-d H:i:s', strtotime($inicio));
        $fimFmt = date('Y-m-d H:i:s', strtotime($fim));

        if (strtotime($fimFmt) <= strtotime($inicioFmt)) {
            $_SESSION['flash_error'] = 'A data de fim deve ser posterior à data de início.';
            header('Location: /admin/promocoes-agendadas');
            exit;
        }

        $status = (strtotime($inicioFmt) <= time() && strtotime($fimFmt) > time()) ? 'ativa' : 'agendada';

        $st = $pdo->prepare("INSERT INTO promocoes_agendadas (nome, desconto_tipo, desconto_valor, inicio, fim, status, criado_por, criado_por_nome) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$nome, $descontoTipo, $descontoValor, $inicioFmt, $fimFmt, $status, (int) ($u['id'] ?? 0), (string) ($u['nome'] ?? '')]);
        $promoId = (int) $pdo->lastInsertId();

        // Vincular produtos
        $colPreco = 'price';
        try {
            $cols = $pdo->query('DESCRIBE produtos')->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('price', $cols, true) && in_array('valor', $cols, true)) $colPreco = 'valor';
        } catch (\Exception $e) {}

        $stProd = $pdo->prepare("SELECT id, {$colPreco} AS preco FROM produtos WHERE id = ? LIMIT 1");
        $stIns = $pdo->prepare("INSERT IGNORE INTO promocoes_agendadas_produtos (promocao_id, produto_id, preco_original, preco_promocional) VALUES (?, ?, ?, ?)");

        foreach ($produtoIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $stProd->execute([$pid]);
            $prod = $stProd->fetch(\PDO::FETCH_ASSOC);
            $precoOriginal = (float) ($prod['preco'] ?? 0);
            $precoPromo = $descontoTipo === 'percentual'
                ? round($precoOriginal * (1 - $descontoValor / 100), 2)
                : round(max(0, $precoOriginal - $descontoValor), 2);
            $stIns->execute([$promoId, $pid, $precoOriginal, $precoPromo]);
        }

        // Se já está ativa, aplicar nos produtos
        if ($status === 'ativa') {
            $this->aplicarPromocao($pdo, $promoId);
        }

        $_SESSION['flash_success'] = "Promoção \"{$nome}\" criada com {$status}. {$this->contarProdutos($pdo, $promoId)} produtos vinculados.";
        header('Location: /admin/promocoes-agendadas');
        exit;
    }

    public function cancelar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $id = (int) ($id ?? $request->getParam('id'));
        $pdo = $this->getPdo();

        // Remover promoção dos produtos
        $this->removerPromocao($pdo, $id);

        $pdo->prepare("UPDATE promocoes_agendadas SET status = 'cancelada', updated_at = NOW() WHERE id = ?")->execute([$id]);

        $_SESSION['flash_success'] = 'Promoção cancelada e preços restaurados.';
        header('Location: /admin/promocoes-agendadas');
        exit;
    }

    /**
     * Cron/endpoint para ativar/finalizar promoções automaticamente
     */
    public function processar(Request $request) {
        $pdo = $this->getPdo();
        $this->garantirTabelas($pdo);
        $resultado = $this->atualizarStatusAutomatico($pdo);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'ativadas' => $resultado['ativadas'], 'finalizadas' => $resultado['finalizadas']]);
        exit;
    }

    /**
     * AJAX: buscar produtos por termo (máx 50 resultados)
     */
    public function buscarProdutos(Request $request) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $auth = new AuthService();
            $auth->requerPerfis(['admin', 'vendedor']);

            $termo = trim((string) ($request->getParam('q') ?? ''));
            if (mb_strlen($termo) < 2) {
                echo json_encode(['produtos' => []]);
                exit;
            }

            $pdo = $this->getPdo();
            $cols = [];
            try { $stC = $pdo->query('DESCRIBE produtos'); $cols = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) { $cols = []; }

            $colNome = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'name');
            $colPreco = in_array('price', $cols, true) ? 'price' : (in_array('valor', $cols, true) ? 'valor' : 'price');
            $hasSku = in_array('sku', $cols, true);
            $hasFoto = in_array('foto_principal', $cols, true);

            $where = "({$colNome} LIKE :q1" . ($hasSku ? " OR sku LIKE :q2" : "") . ")";
            if (in_array('active', $cols, true)) $where .= ' AND active = 1';
            elseif (in_array('ativo', $cols, true)) $where .= ' AND ativo = 1';

            $selectExtra = ($hasSku ? ", sku" : ", '' AS sku") . ($hasFoto ? ", foto_principal" : ", NULL AS foto_principal");
            $sql = "SELECT id, {$colNome} AS nome, {$colPreco} AS preco {$selectExtra} FROM produtos WHERE {$where} ORDER BY {$colNome} ASC LIMIT 50";
            $st = $pdo->prepare($sql);
            $params = [':q1' => '%' . $termo . '%'];
            if ($hasSku) $params[':q2'] = '%' . $termo . '%';
            $st->execute($params);
            $produtos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Enriquecer com imagem (fallback para produto_fotos)
            foreach ($produtos as &$p) {
                $img = trim((string) ($p['foto_principal'] ?? ''));
                if ($img === '') {
                    try {
                        $stF = $pdo->prepare('SELECT nome_arquivo FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, ordem ASC LIMIT 1');
                        $stF->execute([(int) $p['id']]);
                        $img = trim((string) ($stF->fetchColumn() ?: ''));
                    } catch (\Exception $e) {}
                }
                if ($img !== '' && $img[0] !== '/' && !preg_match('#^https?://#i', $img)) {
                    $img = '/uploads/produtos/' . $img;
                }
                $p['imagem'] = $img;
                unset($p['foto_principal']);
            }
            unset($p);

            echo json_encode(['produtos' => $produtos]);
        } catch (\Exception $e) {
            echo json_encode(['produtos' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function atualizarStatusAutomatico(\PDO $pdo): array {
        $ativadas = 0;
        $finalizadas = 0;

        // Ativar promoções agendadas cujo início já passou
        try {
            $st = $pdo->query("SELECT id FROM promocoes_agendadas WHERE status = 'agendada' AND inicio <= NOW() AND fim > NOW()");
            $rows = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
            foreach ($rows as $id) {
                $pdo->prepare("UPDATE promocoes_agendadas SET status = 'ativa', updated_at = NOW() WHERE id = ?")->execute([$id]);
                $this->aplicarPromocao($pdo, (int) $id);
                $ativadas++;
            }
        } catch (\Exception $e) {}

        // Finalizar promoções ativas cujo fim já passou
        try {
            $st = $pdo->query("SELECT id FROM promocoes_agendadas WHERE status = 'ativa' AND fim <= NOW()");
            $rows = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
            foreach ($rows as $id) {
                $this->removerPromocao($pdo, (int) $id);
                $pdo->prepare("UPDATE promocoes_agendadas SET status = 'finalizada', updated_at = NOW() WHERE id = ?")->execute([$id]);
                $finalizadas++;
            }
        } catch (\Exception $e) {}

        return ['ativadas' => $ativadas, 'finalizadas' => $finalizadas];
    }

    private function aplicarPromocao(\PDO $pdo, int $promoId): void {
        try {
            $cols = $pdo->query('DESCRIBE produtos')->fetchAll(\PDO::FETCH_COLUMN);
            $hasSalePrice = in_array('sale_price', $cols, true);
            $hasSaleExpires = in_array('sale_price_expires', $cols, true);
            if (!$hasSalePrice) return;

            $promo = $pdo->prepare("SELECT * FROM promocoes_agendadas WHERE id = ? LIMIT 1");
            $promo->execute([$promoId]);
            $p = $promo->fetch(\PDO::FETCH_ASSOC);
            if (!$p) return;

            $st = $pdo->prepare("SELECT produto_id, preco_promocional FROM promocoes_agendadas_produtos WHERE promocao_id = ?");
            $st->execute([$promoId]);
            $produtos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sql = "UPDATE produtos SET sale_price = ?" . ($hasSaleExpires ? ", sale_price_expires = ?" : "") . " WHERE id = ?";
            $stUpd = $pdo->prepare($sql);

            foreach ($produtos as $prod) {
                $params = [(float) $prod['preco_promocional']];
                if ($hasSaleExpires) $params[] = $p['fim'];
                $params[] = (int) $prod['produto_id'];
                $stUpd->execute($params);
            }
        } catch (\Exception $e) {
            error_log('[PromoAgendada] Erro ao aplicar: ' . $e->getMessage());
        }
    }

    private function removerPromocao(\PDO $pdo, int $promoId): void {
        try {
            $cols = $pdo->query('DESCRIBE produtos')->fetchAll(\PDO::FETCH_COLUMN);
            $hasSalePrice = in_array('sale_price', $cols, true);
            $hasSaleExpires = in_array('sale_price_expires', $cols, true);
            if (!$hasSalePrice) return;

            $st = $pdo->prepare("SELECT produto_id FROM promocoes_agendadas_produtos WHERE promocao_id = ?");
            $st->execute([$promoId]);
            $prodIds = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            if (empty($prodIds)) return;

            $in = implode(',', array_map('intval', $prodIds));
            $sql = "UPDATE produtos SET sale_price = NULL" . ($hasSaleExpires ? ", sale_price_expires = NULL" : "") . " WHERE id IN ({$in})";
            $pdo->exec($sql);
        } catch (\Exception $e) {
            error_log('[PromoAgendada] Erro ao remover: ' . $e->getMessage());
        }
    }

    private function contarProdutos(\PDO $pdo, int $promoId): int {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM promocoes_agendadas_produtos WHERE promocao_id = ?");
            $st->execute([$promoId]);
            return (int) $st->fetchColumn();
        } catch (\Exception $e) { return 0; }
    }
}
