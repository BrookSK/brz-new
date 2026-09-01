<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminLojasController extends Controller {

    private function getPdo(): \PDO {
        return new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensureTable(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lojas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function slugify(string $value): string {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[\s\_]+/u', '-', $value);
        $value = preg_replace('/[^a-z0-9\-]/', '', $value);
        $value = preg_replace('/\-+/', '-', $value);
        return trim($value, '-');
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $stmt = $pdo->query('SELECT * FROM lojas ORDER BY nome ASC');
            $lojas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $lojas = [];
        }

        $this->renderPage($lojas, null);
    }

    public function novo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        $this->renderPage([], ['id' => null, 'nome' => '', 'slug' => '', 'ativo' => 1]);
    }

    public function editar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $id = (int) $request->getParam('id');
        $loja = null;
        $lojas = [];

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $stmt = $pdo->prepare('SELECT * FROM lojas WHERE id = :id');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $loja = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            $stmtAll = $pdo->query('SELECT * FROM lojas ORDER BY nome ASC');
            $lojas = $stmtAll->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
        }

        $this->renderPage($lojas, $loja);
    }

    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $id = (int) $request->getParam('id');
            $nome = trim((string) $request->getParam('nome'));
            $slug = trim((string) $request->getParam('slug'));
            $ativo = (int) $request->getParam('ativo', 1) ? 1 : 0;

            if ($nome === '') {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => __('admin.stores.name_required', 'Informe o nome da loja.')]);
                    exit;
                }
                $_SESSION['message'] = __('admin.stores.name_required', 'Informe o nome da loja.');
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/lojas');
                exit;
            }

            if ($slug === '') {
                $slug = $this->slugify($nome);
            } else {
                $slug = $this->slugify($slug);
            }

            if ($slug === '') {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => __('admin.stores.invalid_slug', 'Slug inválido.')]);
                    exit;
                }
                $_SESSION['message'] = __('admin.stores.invalid_slug', 'Slug inválido.');
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/lojas');
                exit;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE lojas SET nome = :nome, slug = :slug, ativo = :ativo WHERE id = :id');
                $stmt->bindValue(':nome', $nome);
                $stmt->bindValue(':slug', $slug);
                $stmt->bindValue(':ativo', $ativo, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
                $lojaId = $id;
            } else {
                // Verificar slug duplicado
                $chk = $pdo->prepare('SELECT id FROM lojas WHERE slug = ? LIMIT 1');
                $chk->execute([$slug]);
                if ($chk->fetchColumn()) {
                    $slug .= '-' . bin2hex(random_bytes(2));
                }
                $stmt = $pdo->prepare('INSERT INTO lojas (nome, slug, ativo) VALUES (:nome, :slug, :ativo)');
                $stmt->bindValue(':nome', $nome);
                $stmt->bindValue(':slug', $slug);
                $stmt->bindValue(':ativo', $ativo, \PDO::PARAM_INT);
                $stmt->execute();
                $lojaId = (int) $pdo->lastInsertId();
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'ok' => true, 'loja' => ['id' => $lojaId, 'nome' => $nome, 'slug' => $slug]]);
                exit;
            }

            $_SESSION['message'] = __('admin.stores.saved_success', 'Loja salva com sucesso.');
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => __('admin.stores.save_error', 'Erro ao salvar loja') . ': ' . $e->getMessage()]);
                exit;
            }
            $_SESSION['message'] = __('admin.stores.save_error', 'Erro ao salvar loja') . '.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/lojas');
        exit;
    }

    public function excluir(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = (int) $request->getParam('id');

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $stmt = $pdo->prepare('DELETE FROM lojas WHERE id = :id');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = __('admin.stores.deleted_success', 'Loja excluída com sucesso.');
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = __('admin.stores.delete_error', 'Erro ao excluir loja.');
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/lojas');
        exit;
    }

    private function renderPage(array $lojas, ?array $lojaEdit) {
        renderAdminSidebarStyles();

        $editing = !empty($lojaEdit);
        $editId = $editing ? (int) ($lojaEdit['id'] ?? 0) : 0;
        $editNome = $editing ? (string) ($lojaEdit['nome'] ?? '') : '';
        $editSlug = $editing ? (string) ($lojaEdit['slug'] ?? '') : '';
        $editAtivo = $editing ? (int) ($lojaEdit['ativo'] ?? 1) : 1;

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . __('admin.stores.title', 'Lojas') . ' - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/lojas-categorias-redesign.css" rel="stylesheet">';

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('lojas');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">' . __('admin.stores.title', 'Lojas') . '</h1>
                    <a href="/admin/lojas/novo" class="btn btn-primary"><i class="fas fa-plus"></i> ' . __('admin.stores.new', 'Nova Loja') . '</a>
                </div>';

        if (isset($_SESSION['message'])) {
            $type = $_SESSION['message_type'] ?? 'info';
            $msg = $_SESSION['message'];
            unset($_SESSION['message'], $_SESSION['message_type']);
            echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
                . htmlspecialchars($msg)
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
                . '</div>';
        }

        if ($lojaEdit !== null) {
            echo '<div class="card mb-4">
                    <div class="card-header bg-white">
                        <strong>' . ($editId ? __('admin.stores.edit', 'Editar Loja') : __('admin.stores.new', 'Nova Loja')) . '</strong>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/lojas/salvar" class="row g-3">
                            <input type="hidden" name="id" value="' . $editId . '">
                            <div class="col-md-5">
                                <label class="form-label">' . __('admin.stores.form.name', 'Nome *') . '</label>
                                <input type="text" class="form-control" name="nome" value="' . htmlspecialchars($editNome) . '" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">' . __('admin.stores.form.slug', 'Slug') . '</label>
                                <input type="text" class="form-control" name="slug" value="' . htmlspecialchars($editSlug) . '" placeholder="ex: sams">
                                <small class="text-muted">' . __('admin.stores.form.slug_hint', 'Se vazio, será gerado automaticamente.') . '</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">' . __('common.status', 'Status') . '</label>
                                <select class="form-select" name="ativo">
                                    <option value="1" ' . ($editAtivo ? 'selected' : '') . '>' . __('admin.stores.status.active', 'Ativa') . '</option>
                                    <option value="0" ' . (!$editAtivo ? 'selected' : '') . '>' . __('admin.stores.status.inactive', 'Inativa') . '</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ' . __('common.save', 'Salvar') . '</button>
                                <a href="/admin/lojas" class="btn btn-secondary">' . __('common.cancel', 'Cancelar') . '</a>
                            </div>
                        </form>
                    </div>
                </div>';
        }

        echo '<div class="card">
                <div class="card-header bg-white">
                    <strong>' . __('admin.stores.list_title', 'Lista de Lojas') . '</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>' . __('common.name', 'Nome') . '</th>
                                    <th>' . __('admin.stores.form.slug', 'Slug') . '</th>
                                    <th>' . __('common.status', 'Status') . '</th>
                                    <th style="width: 140px;">' . __('common.actions', 'Ações') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (empty($lojas)) {
            echo '<tr><td colspan="5" class="text-center text-muted py-4">' . __('admin.stores.empty', 'Nenhuma loja cadastrada.') . '</td></tr>';
        } else {
            foreach ($lojas as $l) {
                $status = ((int) ($l['ativo'] ?? 1)) ? __('admin.stores.status.active', 'Ativa') : __('admin.stores.status.inactive', 'Inativa');
                $badge = ((int) ($l['ativo'] ?? 1)) ? 'success' : 'secondary';

                echo '<tr>
                        <td>' . (int) $l['id'] . '</td>
                        <td>' . htmlspecialchars($l['nome']) . '</td>
                        <td><code>' . htmlspecialchars($l['slug']) . '</code></td>
                        <td><span class="badge bg-' . $badge . '">' . $status . '</span></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a class="btn btn-sm btn-outline-primary" href="/admin/lojas/editar/' . (int) $l['id'] . '"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="/admin/lojas/excluir/' . (int) $l['id'] . '" onsubmit="return confirm(\'' . __('admin.stores.confirm_delete', 'Excluir esta loja?') . '\');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>';
            }
        }

        echo '            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    }
}
