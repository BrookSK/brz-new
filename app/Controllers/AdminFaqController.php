<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminFaqController
{
    private $connection;

    public function __construct()
    {
        $this->connection = \Config\Database::getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        try {
            $this->connection->exec("CREATE TABLE IF NOT EXISTS faq_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(500) NOT NULL,
                conteudo TEXT NOT NULL,
                ordem INT NOT NULL DEFAULT 0,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {}
    }

    private function seedDefaults(): void
    {
        $st = $this->connection->query("SELECT COUNT(*) FROM faq_items");
        if ((int) ($st->fetchColumn() ?: 0) > 0) return;

        // Ler itens do arquivo HTML estático atual
        $file = __DIR__ . '/../Views/faq/index_static.php';
        if (!file_exists($file)) $file = __DIR__ . '/../Views/faq/index.php';
        if (!file_exists($file)) return;

        $html = file_get_contents($file);
        // Extrair títulos e conteúdos dos accordion items
        preg_match_all('/<button[^>]*class="accordion-button[^"]*"[^>]*>\s*<i[^>]*><\/i>\s*(.*?)\s*<\/button>/s', $html, $titles);
        preg_match_all('/<pre[^>]*>(.*?)<\/pre>/s', $html, $contents);

        $titulos = $titles[1] ?? [];
        $conteudos = $contents[1] ?? [];

        $count = min(count($titulos), count($conteudos));
        for ($i = 0; $i < $count; $i++) {
            $titulo = trim(strip_tags($titulos[$i]));
            $conteudo = trim($conteudos[$i]);
            if ($titulo === '' || $conteudo === '') continue;

            $st = $this->connection->prepare("INSERT INTO faq_items (titulo, conteudo, ordem, ativo) VALUES (?, ?, ?, 1)");
            $st->execute([$titulo, $conteudo, $i + 1]);
        }
    }

    public function index(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $this->seedDefaults();

        $items = $this->connection->query("SELECT * FROM faq_items ORDER BY ordem ASC, id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();

        echo '<div class="pt-3">';
        echo '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-3 border-bottom pb-2">';
        echo '<h1 class="page-title">Gerenciar FAQ / Termos</h1>';
        echo '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFaq" onclick="limparModal()"><i class="fas fa-plus me-1"></i>Novo item</button>';
        echo '</div>';

        if (isset($_GET['ok'])) echo '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>Salvo com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';

        echo '<div class="card"><div class="card-body p-0">';
        echo '<div class="table-responsive"><table class="table table-hover mb-0">';
        echo '<thead><tr><th style="width:60px">Ordem</th><th>Título</th><th style="width:80px">Ativo</th><th style="width:140px">Ações</th></tr></thead><tbody>';

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $ativo = (int) $item['ativo'];
            echo '<tr' . ($ativo ? '' : ' class="table-secondary"') . '>';
            echo '<td>' . (int) $item['ordem'] . '</td>';
            echo '<td>' . htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . ($ativo ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-secondary">Não</span>') . '</td>';
            echo '<td class="text-nowrap">';
            echo '<button class="btn btn-sm btn-outline-primary me-1" onclick="editarFaq(' . $id . ')"><i class="fas fa-edit"></i></button>';
            echo '<form method="POST" action="/admin/faq/toggle/' . $id . '" style="display:inline"><button class="btn btn-sm btn-outline-' . ($ativo ? 'warning' : 'success') . ' me-1" title="' . ($ativo ? 'Desativar' : 'Ativar') . '"><i class="fas fa-' . ($ativo ? 'eye-slash' : 'eye') . '"></i></button></form>';
            echo '<form method="POST" action="/admin/faq/excluir/' . $id . '" style="display:inline" onsubmit="return confirm(\'Excluir este item?\')"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>';
            echo '</td></tr>';
        }

        echo '</tbody></table></div></div></div>';

        // Modal
        echo '<div class="modal fade" id="modalFaq" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">';
        echo '<form method="POST" action="/admin/faq/salvar" id="formFaq">';
        echo '<input type="hidden" name="id" id="faqId" value="">';
        echo '<div class="modal-header"><h5 class="modal-title" id="modalFaqTitle">Novo item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        echo '<div class="modal-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold">Título</label><input type="text" class="form-control" name="titulo" id="faqTitulo" required></div>';
        echo '<div class="mb-3"><label class="form-label fw-semibold">Conteúdo</label><textarea class="form-control" name="conteudo" id="faqConteudo" rows="12" required></textarea></div>';
        echo '<div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Ordem</label><input type="number" class="form-control" name="ordem" id="faqOrdem" value="0" min="0"></div>';
        echo '<div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ativo" id="faqAtivo" value="1" checked><label class="form-check-label" for="faqAtivo">Ativo</label></div></div></div>';
        echo '</div>';
        echo '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button></div>';
        echo '</form></div></div></div>';

        // JS
        echo '<script>';
        echo 'const FAQ_ITEMS = ' . json_encode($items, JSON_UNESCAPED_UNICODE) . ';';
        echo 'function limparModal(){document.getElementById("faqId").value="";document.getElementById("faqTitulo").value="";document.getElementById("faqConteudo").value="";document.getElementById("faqOrdem").value="0";document.getElementById("faqAtivo").checked=true;document.getElementById("modalFaqTitle").textContent="Novo item";}';
        echo 'function editarFaq(id){const item=FAQ_ITEMS.find(i=>i.id==id);if(!item)return;document.getElementById("faqId").value=item.id;document.getElementById("faqTitulo").value=item.titulo;document.getElementById("faqConteudo").value=item.conteudo;document.getElementById("faqOrdem").value=item.ordem;document.getElementById("faqAtivo").checked=item.ativo==1;document.getElementById("modalFaqTitle").textContent="Editar: "+item.titulo;new bootstrap.Modal(document.getElementById("modalFaq")).show();}';
        echo '</script>';

        echo '</div>';
        $content = ob_get_clean();
        $sidebarActive = 'faq';
        $title = 'Gerenciar FAQ - Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function salvar(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $id = (int) ($request->getParam('id') ?? 0);
        $titulo = trim((string) ($request->getParam('titulo') ?? ''));
        $conteudo = trim((string) ($request->getParam('conteudo') ?? ''));
        $ordem = (int) ($request->getParam('ordem') ?? 0);
        $ativo = $request->getParam('ativo') ? 1 : 0;

        if ($titulo === '' || $conteudo === '') {
            header('Location: /admin/faq');
            exit;
        }

        if ($id > 0) {
            $st = $this->connection->prepare("UPDATE faq_items SET titulo = ?, conteudo = ?, ordem = ?, ativo = ?, updated_at = NOW() WHERE id = ?");
            $st->execute([$titulo, $conteudo, $ordem, $ativo, $id]);
        } else {
            $st = $this->connection->prepare("INSERT INTO faq_items (titulo, conteudo, ordem, ativo) VALUES (?, ?, ?, ?)");
            $st->execute([$titulo, $conteudo, $ordem, $ativo]);
        }

        header('Location: /admin/faq?ok=1');
        exit;
    }

    public function toggle(Request $request, $id = null)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $id = (int) ($id ?? $request->getParam('id') ?? 0);
        if ($id > 0) {
            $this->connection->prepare("UPDATE faq_items SET ativo = IF(ativo=1,0,1), updated_at = NOW() WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/faq');
        exit;
    }

    public function excluir(Request $request, $id = null)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        $id = (int) ($id ?? $request->getParam('id') ?? 0);
        if ($id > 0) {
            $this->connection->prepare("DELETE FROM faq_items WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/faq');
        exit;
    }
}
