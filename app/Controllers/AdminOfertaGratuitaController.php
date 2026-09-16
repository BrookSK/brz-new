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

        // Buscar categorias para ação em massa
        $categorias = [];
        try {
            $stCat = $pdo->query("SELECT id, name FROM categorias ORDER BY name ASC");
            $categorias = $stCat ? ($stCat->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {}

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
<html lang="' . \App\Core\I18n::getLocaleHtml() . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(__('admin.free_offer.page_title', 'Oferta Gratuita - Admin'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('oferta-gratuita');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="page-title">' . __('admin.free_offer.title', 'Oferta de Produto Gratuito') . '</h1>
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
            <div class="card-header bg-white"><strong><i class="fas fa-power-off"></i> ' . __('admin.free_offer.global_control', 'Controle Global') . '</strong></div>
            <div class="card-body">
                <form method="POST" action="/admin/oferta-gratuita/toggle-global" class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="globalToggle" name="ativa" value="1" ' . ($globalAtiva ? 'checked' : '') . ' onchange="this.form.submit()" style="width:3em;height:1.5em;">
                        <label class="form-check-label ms-2" for="globalToggle">
                            ' . ($globalAtiva ? '<span class="badge bg-success">' . __('admin.free_offer.active', 'Ativa') . '</span>' : '<span class="badge bg-secondary">' . __('admin.free_offer.disabled', 'Desativada') . '</span>') . '
                        </label>
                    </div>
                    <span class="text-muted small">' . __('admin.free_offer.global_control_hint', 'Ativar/desativar a oferta de produto gratuito no carrinho para todos os clientes') . '</span>
                </form>
            </div>
        </div>';

        // Estatísticas
        echo '<div class="row mb-4">
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-success">' . $stats['aceitas'] . '</h5><small class="text-muted">' . __('admin.free_offer.stat_accepted', 'Ofertas Aceitas') . '</small></div></div></div>
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-warning">' . $stats['recusadas'] . '</h5><small class="text-muted">' . __('admin.free_offer.stat_declined', 'Ofertas Recusadas') . '</small></div></div></div>
            <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5 class="text-danger">' . $stats['removidas'] . '</h5><small class="text-muted">' . __('admin.free_offer.stat_removed', 'Removidas do Carrinho') . '</small></div></div></div>
        </div>';

        // Sincronização automática
        echo '<div class="card mb-4">
            <div class="card-header bg-white"><strong><i class="fas fa-sync-alt"></i> ' . __('admin.free_offer.auto_sync', 'Sincronização Automática') . '</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-2">' . __('admin.free_offer.auto_sync_hint', 'Marca automaticamente como elegíveis todos os produtos do catálogo do site (sem grupo de compras) com peso &ge; 500g. Produtos que não atendem mais os critérios são removidos.') . '</p>
                <form method="POST" action="/admin/oferta-gratuita/sincronizar" class="d-inline">
                    <button type="submit" class="btn btn-outline-success" onclick="return confirm(\'' . htmlspecialchars(__('admin.free_offer.sync_confirm', 'Sincronizar produtos do site com peso >= 500g como elegíveis para oferta gratuita?'), ENT_QUOTES, 'UTF-8') . '\')"><i class="fas fa-sync-alt me-1"></i> ' . __('admin.free_offer.sync_button', 'Sincronizar Produtos do Site') . '</button>
                </form>
            </div>
        </div>';

        // Lista de produtos elegíveis
        echo '<div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-box-open"></i> ' . __('admin.free_offer.eligible_products', 'Produtos Elegíveis para Oferta Gratuita') . ' (' . $total . ')</strong>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarProduto"><i class="fas fa-plus"></i> ' . __('admin.free_offer.add_product', 'Adicionar Produto') . '</button>
            </div>
            <div class="card-body">';

        if (empty($produtos)) {
            echo '<div class="text-muted text-center py-4">' . __('admin.free_offer.no_eligible', 'Nenhum produto marcado como elegível para oferta gratuita.') . '</div>';
        } else {
            // Barra de ação em massa
            echo '<div id="bulkBar" class="alert alert-info d-none align-items-center gap-3 mb-3">
                <span><strong id="bulkCount">0</strong> ' . __('admin.free_offer.selected_products', 'produto(s) selecionado(s)') . '</span>
                <form method="POST" action="/admin/oferta-gratuita/acao-massa" class="d-flex align-items-center gap-2 ms-auto" id="bulkForm">
                    <input type="hidden" name="produto_ids" id="bulkIds" value="">
                    <select name="categoria_id" class="form-select form-select-sm" style="width:auto;" required>
                        <option value="">' . __('admin.free_offer.change_category_to', 'Alterar categoria para...') . '</option>';
            foreach ($categorias as $cat) {
                echo '<option value="' . (int) $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
            }
            echo '</select>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i> ' . __('admin.free_offer.apply', 'Aplicar') . '</button>
                </form>
            </div>';

            echo '<div class="table-responsive d-none d-md-block"><table class="table table-hover align-middle">
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                    <th>ID</th><th>' . __('admin.free_offer.th_product', 'Produto') . '</th><th>' . __('admin.free_offer.th_category', 'Categoria') . '</th><th>' . __('admin.free_offer.th_weight', 'Peso') . '</th><th>' . __('admin.free_offer.th_price', 'Preço') . '</th><th>' . __('admin.free_offer.th_stock', 'Estoque') . '</th><th>' . __('admin.free_offer.th_status', 'Status') . '</th><th>' . __('admin.free_offer.th_actions', 'Ações') . '</th>
                </tr></thead><tbody>';

            foreach ($produtos as $p) {
                $statusBadge = ((int) ($p['active'] ?? 0) && ($p['status'] ?? '') === 'published')
                    ? '<span class="badge bg-success">' . __('admin.free_offer.status_active', 'Ativo') . '</span>'
                    : '<span class="badge bg-secondary">' . __('admin.free_offer.status_inactive', 'Inativo') . '</span>';
                $stockBadge = ((int) ($p['stock'] ?? 0) > 0)
                    ? '<span class="badge bg-info">' . (int) $p['stock'] . '</span>'
                    : '<span class="badge bg-danger">' . __('admin.free_offer.out_of_stock', 'Sem estoque') . '</span>';
                $pesoKg = (float) ($p['weight'] ?? 0);
                $pesoG = round($pesoKg * 1000);
                $pesoBadge = $pesoKg >= 0.5
                    ? '<span class="badge bg-success">' . $pesoG . 'g</span>'
                    : '<span class="badge bg-warning">' . $pesoG . 'g</span>';

                echo '<tr>
                    <td><input type="checkbox" class="form-check-input bulk-check" value="' . (int) $p['id'] . '"></td>
                    <td>' . (int) $p['id'] . '</td>
                    <td>' . htmlspecialchars((string) ($p['name'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string) ($p['categoria_nome'] ?? __('admin.free_offer.no_category', 'Sem categoria'))) . '</td>
                    <td>' . $pesoBadge . '</td>
                    <td>$ ' . number_format((float) ($p['price'] ?? 0), 2) . '</td>
                    <td>' . $stockBadge . '</td>
                    <td>' . $statusBadge . '</td>
                    <td>
                        <form method="POST" action="/admin/oferta-gratuita/remover" class="d-inline" onsubmit="return confirm(\'' . htmlspecialchars(__('admin.free_offer.remove_confirm', 'Remover este produto da oferta gratuita?'), ENT_QUOTES, 'UTF-8') . '\')">
                            <input type="hidden" name="produto_id" value="' . (int) $p['id'] . '">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                        </form>
                        <a href="/admin/produtos/editar/' . (int) $p['id'] . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>';
            }

            echo '</tbody></table></div>';

            // Mobile: Cards
            echo '<div class="d-md-none">';
            foreach ($produtos as $p) {
                $statusBadge2 = ((int) ($p['active'] ?? 0) && ($p['status'] ?? '') === 'published')
                    ? '<span class="badge bg-success">' . __('admin.free_offer.status_active', 'Ativo') . '</span>'
                    : '<span class="badge bg-secondary">' . __('admin.free_offer.status_inactive', 'Inativo') . '</span>';
                $pesoKg2 = (float) ($p['weight'] ?? 0);
                $pesoG2 = round($pesoKg2 * 1000);

                echo '<div class="border-bottom py-2 px-1">
                    <div class="d-flex align-items-start gap-2">
                        <input type="checkbox" class="form-check-input bulk-check mt-1" value="' . (int) $p['id'] . '">
                        <div style="flex:1;min-width:0;">
                            <div class="fw-semibold small" style="word-break:break-word;">' . htmlspecialchars((string) ($p['name'] ?? '')) . '</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="text-muted" style="font-size:11px;">#' . (int) $p['id'] . '</span>
                                <span class="badge bg-light text-dark" style="font-size:10px;">' . $pesoG2 . 'g</span>
                                <span class="badge bg-light text-dark" style="font-size:10px;">$ ' . number_format((float) ($p['price'] ?? 0), 2) . '</span>
                                ' . $statusBadge2 . '
                            </div>
                        </div>
                        <form method="POST" action="/admin/oferta-gratuita/remover" class="d-inline" onsubmit="return confirm(\'' . htmlspecialchars(__('admin.free_offer.remove_short_confirm', 'Remover?'), ENT_QUOTES, 'UTF-8') . '\')">
                            <input type="hidden" name="produto_id" value="' . (int) $p['id'] . '">
                            <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                </div>';
            }
            echo '</div>';

            // Paginação
            $perPage = 50;
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($totalPages > 1) {
                echo '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';
                // Anterior
                if ($page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="/admin/oferta-gratuita?page=' . ($page - 1) . '">&laquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
                }
                // Páginas
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="/admin/oferta-gratuita?page=1">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    $active = $i === $page ? ' active' : '';
                    echo '<li class="page-item' . $active . '"><a class="page-link" href="/admin/oferta-gratuita?page=' . $i . '">' . $i . '</a></li>';
                }
                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="/admin/oferta-gratuita?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                }
                // Próximo
                if ($page < $totalPages) {
                    echo '<li class="page-item"><a class="page-link" href="/admin/oferta-gratuita?page=' . ($page + 1) . '">&raquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
                }
                echo '</ul></nav>';
            }
        }

        echo '</div></div>';

        // Modal para adicionar produto
        echo '<div class="modal fade" id="modalAdicionarProduto" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">' . __('admin.free_offer.modal_title', 'Adicionar Produto à Oferta Gratuita') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" id="buscaProduto" class="form-control mb-3" placeholder="' . htmlspecialchars(__('admin.free_offer.search_placeholder', 'Buscar produto por nome...'), ENT_QUOTES, 'UTF-8') . '">
                        <div id="resultadosBusca"></div>
                    </div>
                </div>
            </div>
        </div>';

        echo '<script>
        // Ação em massa: checkboxes
        (function() {
            var checkAll = document.getElementById("checkAll");
            var bulkBar = document.getElementById("bulkBar");
            var bulkCount = document.getElementById("bulkCount");
            var bulkIds = document.getElementById("bulkIds");
            var bulkForm = document.getElementById("bulkForm");

            function updateBulk() {
                var checks = document.querySelectorAll(".bulk-check:checked");
                var ids = [];
                checks.forEach(function(c) { ids.push(c.value); });
                bulkIds.value = ids.join(",");
                bulkCount.textContent = ids.length;
                if (ids.length > 0) {
                    bulkBar.classList.remove("d-none");
                    bulkBar.classList.add("d-flex");
                } else {
                    bulkBar.classList.add("d-none");
                    bulkBar.classList.remove("d-flex");
                }
            }

            if (checkAll) {
                checkAll.addEventListener("change", function() {
                    document.querySelectorAll(".bulk-check").forEach(function(c) { c.checked = checkAll.checked; });
                    updateBulk();
                });
            }
            document.querySelectorAll(".bulk-check").forEach(function(c) {
                c.addEventListener("change", function() {
                    var total = document.querySelectorAll(".bulk-check").length;
                    var checked = document.querySelectorAll(".bulk-check:checked").length;
                    if (checkAll) checkAll.checked = (checked === total);
                    updateBulk();
                });
            });

            if (bulkForm) {
                bulkForm.addEventListener("submit", function(e) {
                    var sel = bulkForm.querySelector("select[name=categoria_id]");
                    if (!sel.value) { e.preventDefault(); alert("' . htmlspecialchars(__('admin.free_offer.js_select_category', 'Selecione uma categoria.'), ENT_QUOTES, 'UTF-8') . '"); return; }
                    if (!bulkIds.value) { e.preventDefault(); alert("' . htmlspecialchars(__('admin.free_offer.js_select_product', 'Selecione ao menos um produto.'), ENT_QUOTES, 'UTF-8') . '"); return; }
                });
            }
        })();

        document.getElementById("buscaProduto")?.addEventListener("input", async function() {
            const q = this.value.trim();
            const container = document.getElementById("resultadosBusca");
            if (q.length < 2) { container.innerHTML = ""; return; }
            try {
                const res = await fetch("/admin/oferta-gratuita/buscar-produtos?q=" + encodeURIComponent(q));
                const data = await res.json();
                if (!data.items || data.items.length === 0) {
                    container.innerHTML = "<div class=\"text-muted\">' . htmlspecialchars(__('admin.free_offer.js_no_products', 'Nenhum produto encontrado.'), ENT_QUOTES, 'UTF-8') . '</div>";
                    return;
                }
                let html = "<div class=\"list-group\">";
                data.items.forEach(p => {
                    html += "<div class=\"list-group-item d-flex justify-content-between align-items-center\">" +
                        "<div><strong>" + (p.name || "") + "</strong> <small class=\"text-muted\">ID: " + p.id + " | ' . htmlspecialchars(__('admin.free_offer.js_stock', 'Estoque'), ENT_QUOTES, 'UTF-8') . ': " + p.stock + " | $ " + parseFloat(p.price || 0).toFixed(2) + "</small></div>" +
                        "<form method=\"POST\" action=\"/admin/oferta-gratuita/adicionar\"><input type=\"hidden\" name=\"produto_id\" value=\"" + p.id + "\">" +
                        "<button class=\"btn btn-sm btn-success\"><i class=\"fas fa-plus\"></i> ' . htmlspecialchars(__('admin.free_offer.add_product', 'Adicionar Produto'), ENT_QUOTES, 'UTF-8') . '</button></form></div>";
                });
                html += "</div>";
                container.innerHTML = html;
            } catch(e) { container.innerHTML = "<div class=\"text-danger\">' . htmlspecialchars(__('admin.free_offer.js_search_error', 'Erro na busca.'), ENT_QUOTES, 'UTF-8') . '</div>"; }
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
        $_SESSION['message'] = $ativa === '1' ? __('admin.free_offer.msg_activated', 'Oferta gratuita ativada.') : __('admin.free_offer.msg_deactivated', 'Oferta gratuita desativada.');
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
        $_SESSION['message'] = __('admin.free_offer.msg_added', 'Produto adicionado à oferta gratuita.');
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
        $_SESSION['message'] = __('admin.free_offer.msg_removed', 'Produto removido da oferta gratuita.');
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/oferta-gratuita');
        exit;
    }

    /**
     * Sincronizar produtos do site (sem grupo de compras, peso >= 500g) como elegíveis
     */
    public function sincronizar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $model = new OfertaGratuita();
        $result = $model->sincronizarProdutosSite();

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (isset($result['erro'])) {
            $_SESSION['message'] = __('admin.free_offer.msg_sync_error', 'Erro na sincronização: ') . $result['erro'];
            $_SESSION['message_type'] = 'danger';
        } else {
            $msg = __('admin.free_offer.msg_sync_done', 'Sincronização concluída: {added} produto(s) adicionado(s), {removed} removido(s). Total elegíveis: {total}.', [
                'added' => $result['adicionados'],
                'removed' => $result['removidos'],
                'total' => $result['total'],
            ]);
            $_SESSION['message'] = $msg;
            $_SESSION['message_type'] = 'success';
        }

        header('Location: /admin/oferta-gratuita');
        exit;
    }

    /**
     * Ação em massa: alterar categoria dos produtos selecionados
     */
    public function acaoMassa(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $ids = array_filter(array_map('intval', explode(',', (string) $request->getParam('produto_ids', ''))));
        $categoriaId = (int) $request->getParam('categoria_id', 0);

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($ids) || $categoriaId <= 0) {
            $_SESSION['message'] = __('admin.free_offer.msg_select_products_category', 'Selecione produtos e uma categoria.');
            $_SESSION['message_type'] = 'warning';
            header('Location: /admin/oferta-gratuita');
            exit;
        }

        try {
            $pdo = Database::getConnection();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$categoriaId], $ids);
            $stmt = $pdo->prepare("UPDATE produtos SET category_id = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            $affected = $stmt->rowCount();

            $_SESSION['message'] = __('admin.free_offer.msg_category_updated', 'Categoria atualizada em {count} produto(s).', ['count' => $affected]);
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = __('admin.free_offer.msg_category_error', 'Erro ao atualizar categoria: ') . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

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
                    SELECT id, name, price, stock, weight, active, status
                    FROM produtos 
                    WHERE (name LIKE ? OR id = ?)
                      AND active = 1 AND status = 'published'
                      AND (elegivel_oferta_gratis = 0 OR elegivel_oferta_gratis IS NULL)
                      AND weight >= 0.5
                      AND stock > 0
                      AND (grupo_compras_id IS NULL OR grupo_compras_id = 0)
                      AND (oculto IS NULL OR oculto = 0)
                      AND (sku IS NULL OR sku NOT LIKE 'ASS-%')
                      AND (
                          (weight < 2 AND price <= 5)
                          OR (weight >= 2 AND price <= 10)
                      )
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
