<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\OfertaGratuita;
use Config\Database;

class AdminOfertaGratuitaController extends Controller {

    /**
     * Tela principal: lista produtos elegíveis + controle global
     */
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $pdo = Database::getConnection();
        $model = new OfertaGratuita();

        // Buscar estado global
        $globalAtiva = $model->isOfertaGlobalAtiva();

        // Buscar produtos elegíveis
        $page = max(1, (int) ($request->getParam('page') ?? 1));
        $result = $model->listarProdutosElegiveis($page, 50);
        $produtos = $result['items'];
        $total = $result['total'];

        // Buscar estatísticas
        $stats = ['aceitas' => 0, 'recusadas' => 0, 'removidas' => 0];
        try {
            $st = $pdo->query("SELECT acao, COUNT(*) as total FROM oferta_gratuita_log GROUP BY acao");
            $rows = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $stats[$r['acao']] = (int) $r['total'];
            }
        } catch (\Exception $e) {}

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oferta Gratuita - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('oferta-gratuita');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-gift"></i> Oferta de Produto Gratuito</h1>
            </div>';

        // Mensagens flash
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['message'])) {
            $type = $_SESSION['message_type'] ?? 'info';
            echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show">' . htmlspecialchars($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['message'], $_SESSION['message_type']);
        }

        // Controle Global
        echo '<div class="card mb-4">
            <div class="card-header bg-white"><strong><i class="fas fa-power-off"></i> Controle Global</strong></div>
            <div class="card-body">
                <form method="POST" action="/admin/oferta-gratuita/toggle-global" class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="globalToggle" name="ativa" value="1" ' . ($globalAtiva ? 'checked' : '') . ' onchange="this.form.submit()" style="width:3em;height:1.5em;">
                        <label class="form-check-label ms-2" for="globalToggle">
                            ' . ($globalAtiva ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Desativada</span>') . '
                        </label>
                    </div>
                    <span class="text-muted small">Ativar/desativar a oferta de produto gratuito no carrinho para todos os clientes</span>
                </form>
            </div>
        </div>';

        // Estatísticas
        echo '<div class="row mb-4">
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-success">' . $stats['aceitas'] . '</h5><small class="text-muted">Ofertas Aceitas</small></div></div></div>
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-warning">' . $stats['recusadas'] . '</h5><small class="text-muted">Ofertas Recusadas</small></div></div></div>
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-danger">' . $stats['removidas'] . '</h5><small class="text-muted">Removidas do Carrinho</small></div></div></div>
        </div>';

        // Lista de produtos elegíveis
        echo '<div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-box-open"></i> Produtos Elegíveis para Oferta Gratuita (' . $total . ')</strong>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarProduto"><i class="fas fa-plus"></i> Adicionar Produto</button>
            </div>
            <div class="card-body">';

        if (empty($produtos)) {
            echo '<div class="text-muted text-center py-4">Nenhum produto marcado como elegível para oferta gratuita.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-hover align-middle">
                <thead><tr>
                    <th>ID</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th>
                </tr></thead><tbody>';

            foreach ($produtos as $p) {
                $statusBadge = ((int) ($p['active'] ?? 0) && ($p['status'] ?? '') === 'published')
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-secondary">Inativo</span>';
                $stockBadge = ((int) ($p['stock'] ?? 0) > 0)
                    ? '<span class="badge bg-info">' . (int) $p['stock'] . '</span>'
                    : '<span class="badge bg-danger">Sem estoque</span>';

                echo '<tr>
                    <td>' . (int) $p['id'] . '</td>
                    <td>' . htmlspecialchars((string) ($p['name'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string) ($p['categoria_nome'] ?? 'Sem categoria')) . '</td>
                    <td>$ ' . number_format((float) ($p['price'] ?? 0), 2) . '</td>
                    <td>' . $stockBadge . '</td>
                    <td>' . $statusBadge . '</td>
                    <td>
                        <form method="POST" action="/admin/oferta-gratuita/remover" class="d-inline" onsubmit="return confirm(\'Remover este produto da oferta gratuita?\')">
                            <input type="hidden" name="produto_id" value="' . (int) $p['id'] . '">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Remover</button>
                        </form>
                        <a href="/admin/produtos/editar/' . (int) $p['id'] . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div></div>';

        // Modal para adicionar produto
        echo '<div class="modal fade" id="modalAdicionarProduto" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Adicionar Produto à Oferta Gratuita</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" id="buscaProduto" class="form-control mb-3" placeholder="Buscar produto por nome...">
                        <div id="resultadosBusca"></div>
                    </div>
                </div>
            </div>
        </div>';

        echo '<script>
        document.getElementById("buscaProduto")?.addEventListener("input", async function() {
            const q = this.value.trim();
            const container = document.getElementById("resultadosBusca");
            if (q.length < 2) { container.innerHTML = ""; return; }
            try {
                const res = await fetch("/admin/oferta-gratuita/buscar-produtos?q=" + encodeURIComponent(q));
                const data = await res.json();
                if (!data.items || data.items.length === 0) {
                    container.innerHTML = "<div class=\"text-muted\">Nenhum produto encontrado.</div>";
                    return;
                }
                let html = "<div class=\"list-group\">";
                data.items.forEach(p => {
                    html += "<div class=\"list-group-item d-flex justify-content-between align-items-center\">" +
                        "<div><strong>" + (p.name || "") + "</strong> <small class=\"text-muted\">ID: " + p.id + " | Estoque: " + p.stock + " | $ " + parseFloat(p.price || 0).toFixed(2) + "</small></div>" +
                        "<form method=\"POST\" action=\"/admin/oferta-gratuita/adicionar\"><input type=\"hidden\" name=\"produto_id\" value=\"" + p.id + "\">" +
                        "<button class=\"btn btn-sm btn-success\"><i class=\"fas fa-plus\"></i> Adicionar</button></form></div>";
                });
                html += "</div>";
                container.innerHTML = html;
            } catch(e) { container.innerHTML = "<div class=\"text-danger\">Erro na busca.</div>"; }
        });
        </script>';

        echo '</main></div></div>';
        renderAdminScripts();
        echo '</body></html>';
        exit;
    }

    /**
     * Toggle global da funcionalidade
     */
    public function toggleGlobal(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $ativa = $request->getParam('ativa') ? '1' : '0';

        try {
            $pdo = Database::getConnection();
            $cols = [];
            try {
                $st = $pdo->query('DESCRIBE configuracoes_sistema');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) { $cols = []; }

            // Schema single_row: coluna direta
            if (in_array('oferta_gratuita_ativa', $cols, true)) {
                $pdo->prepare("UPDATE configuracoes_sistema SET oferta_gratuita_ativa = ? ORDER BY id ASC LIMIT 1")->execute([(int) $ativa]);
            }
            // Schema categoria+chave+valor
            elseif (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ?, updated_at = NOW() WHERE categoria = 'oferta_gratuita' AND chave = 'oferta_gratuita_ativa'");
                $stmt->execute([$ativa]);
                if ($stmt->rowCount() === 0) {
                    $pdo->prepare("INSERT INTO configuracoes_sistema (categoria, chave, valor, created_at, updated_at) VALUES ('oferta_gratuita', 'oferta_gratuita_ativa', ?, NOW(), NOW())")->execute([$ativa]);
                }
            }
            // Schema chave+valor (sem categoria)
            elseif (in_array('chave', $cols, true)) {
                $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ?, updated_at = NOW() WHERE chave = 'oferta_gratuita_ativa'");
                $stmt->execute([$ativa]);
                if ($stmt->rowCount() === 0) {
                    $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor, created_at, updated_at) VALUES ('oferta_gratuita_ativa', ?, NOW(), NOW())")->execute([$ativa]);
                }
            }
        } catch (\Exception $e) {}

        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['message'] = $ativa === '1' ? 'Oferta gratuita ativada.' : 'Oferta gratuita desativada.';
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/oferta-gratuita');
        exit;
    }

    /**
     * Adicionar produto à oferta gratuita
     */
    public function adicionar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $produtoId = (int) $request->getParam('produto_id');
        if ($produtoId > 0) {
            $model = new OfertaGratuita();
            $model->toggleElegibilidade($produtoId, true);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['message'] = 'Produto adicionado à oferta gratuita.';
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/oferta-gratuita');
        exit;
    }

    /**
     * Remover produto da oferta gratuita
     */
    public function remover(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $produtoId = (int) $request->getParam('produto_id');
        if ($produtoId > 0) {
            $model = new OfertaGratuita();
            $model->toggleElegibilidade($produtoId, false);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['message'] = 'Produto removido da oferta gratuita.';
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/oferta-gratuita');
        exit;
    }

    /**
     * Buscar produtos para adicionar (AJAX)
     */
    public function buscarProdutos(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $q = trim((string) ($request->getParam('q') ?? ''));
        $items = [];

        if (strlen($q) >= 2) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("
                    SELECT id, name, price, stock, active, status
                    FROM produtos 
                    WHERE (name LIKE ? OR id = ?)
                      AND active = 1 AND status = 'published'
                      AND (elegivel_oferta_gratis = 0 OR elegivel_oferta_gratis IS NULL)
                    ORDER BY name ASC LIMIT 20
                ");
                $stmt->execute(['%' . $q . '%', (int) $q]);
                $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $items = [];
            }
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['items' => $items]);
        exit;
    }
}
