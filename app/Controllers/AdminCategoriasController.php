<?php
namespace App\Controllers;

use App\Core\Request;

class AdminCategoriasController extends Controller {

    private function getPdo(): \PDO {
        return new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensureTable(\PDO $pdo): void {
        // Tenta criar uma tabela compatível caso não exista.
        $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NULL,
            descricao TEXT NULL,
            status VARCHAR(20) NULL,
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

    private function getColumns(\PDO $pdo): array {
        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM categorias");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!empty($r['Field'])) {
                    $columns[] = $r['Field'];
                }
            }
        } catch (\Exception $e) {
        }
        return $columns;
    }

    private function pickNameColumn(array $columns): string {
        foreach (['nome', 'name'] as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return 'nome';
    }

    private function pickSlugColumn(array $columns): ?string {
        foreach (['slug'] as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return null;
    }

    private function pickDescriptionColumn(array $columns): ?string {
        foreach (['descricao', 'description'] as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return null;
    }

    private function pickStatusColumn(array $columns): ?string {
        foreach (['status', 'ativo', 'active'] as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return null;
    }

    private function mapStatusValue(?string $statusColumn, string $status): mixed {
        if ($statusColumn === null) return null;
        if (in_array($statusColumn, ['ativo', 'active'], true)) {
            return $status === 'ativo' ? 1 : 0;
        }
        return $status;
    }

    public function index(Request $request) {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $categorias = [];
        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $columns = $this->getColumns($pdo);
            $nameCol = $this->pickNameColumn($columns);

            $stmt = $pdo->query('SELECT * FROM categorias ORDER BY ' . $nameCol . ' ASC');
            $categorias = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
        }

        $this->renderPage($categorias, null);
    }

    public function novo(Request $request) {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        $this->renderPage([], ['id' => null]);
    }

    public function editar(Request $request) {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $id = (int) $request->getParam('id');
        $categoria = null;
        $categorias = [];

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id = :id');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $categoria = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            $columns = $this->getColumns($pdo);
            $nameCol = $this->pickNameColumn($columns);

            $stmtAll = $pdo->query('SELECT * FROM categorias ORDER BY ' . $nameCol . ' ASC');
            $categorias = $stmtAll->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
        }

        $this->renderPage($categorias, $categoria);
    }

    public function salvar(Request $request) {
        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $columns = $this->getColumns($pdo);
            $nameCol = $this->pickNameColumn($columns);
            $slugCol = $this->pickSlugColumn($columns);
            $descCol = $this->pickDescriptionColumn($columns);
            $statusCol = $this->pickStatusColumn($columns);

            $id = (int) $request->getParam('id');
            $nome = trim((string) $request->getParam('nome'));
            $slug = trim((string) $request->getParam('slug'));
            $descricao = trim((string) $request->getParam('descricao'));
            $status = (string) $request->getParam('status', 'ativo');

            if ($nome === '') {
                $_SESSION['message'] = 'Informe o nome da categoria.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/categorias');
                exit;
            }

            if ($slugCol !== null) {
                if ($slug === '') {
                    $slug = $this->slugify($nome);
                } else {
                    $slug = $this->slugify($slug);
                }
            }

            $data = [];
            $data[$nameCol] = $nome;

            if ($slugCol !== null) {
                $data[$slugCol] = $slug;
            }
            if ($descCol !== null) {
                $data[$descCol] = $descricao;
            }
            if ($statusCol !== null) {
                $data[$statusCol] = $this->mapStatusValue($statusCol, $status);
            }

            if ($id > 0) {
                $set = [];
                foreach (array_keys($data) as $c) {
                    $set[] = $c . ' = :' . $c;
                }
                $sql = 'UPDATE categorias SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                foreach ($data as $k => $v) {
                    $stmt->bindValue(':' . $k, $v);
                }
                $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $cols = implode(', ', array_keys($data));
                $ph = ':' . implode(', :', array_keys($data));
                $sql = 'INSERT INTO categorias (' . $cols . ') VALUES (' . $ph . ')';
                $stmt = $pdo->prepare($sql);
                foreach ($data as $k => $v) {
                    $stmt->bindValue(':' . $k, $v);
                }
                $stmt->execute();
            }

            $_SESSION['message'] = 'Categoria salva com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\PDOException $e) {
            $_SESSION['message'] = 'Erro ao salvar categoria.';
            $_SESSION['message_type'] = 'danger';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao salvar categoria.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/categorias');
        exit;
    }

    public function excluir(Request $request) {
        $id = (int) $request->getParam('id');

        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            // Evitar excluir se existir produto associado (tenta achar tanto category_id quanto categoria_id)
            $hasProducts = false;
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'category_id'");
                if ($stmt && $stmt->fetchColumn()) {
                    $stmtC = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE category_id = :id');
                    $stmtC->bindValue(':id', $id, \PDO::PARAM_INT);
                    $stmtC->execute();
                    $hasProducts = ((int) $stmtC->fetchColumn()) > 0;
                }

                $stmt2 = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'categoria_id'");
                if (!$hasProducts && $stmt2 && $stmt2->fetchColumn()) {
                    $stmtC = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE categoria_id = :id');
                    $stmtC->bindValue(':id', $id, \PDO::PARAM_INT);
                    $stmtC->execute();
                    $hasProducts = ((int) $stmtC->fetchColumn()) > 0;
                }
            } catch (\Exception $e) {
            }

            if ($hasProducts) {
                $_SESSION['message'] = 'Não é possível excluir: existem produtos vinculados a esta categoria.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/categorias');
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = 'Categoria excluída com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao excluir categoria.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/categorias');
        exit;
    }

    private function renderPage(array $categorias, ?array $categoriaEdit): void {
        renderAdminSidebarStyles();

        $editing = !empty($categoriaEdit);
        $editId = $editing ? (int) ($categoriaEdit['id'] ?? 0) : 0;

        $editNome = '';
        foreach (['nome', 'name'] as $c) {
            if ($editing && isset($categoriaEdit[$c])) {
                $editNome = (string) $categoriaEdit[$c];
                break;
            }
        }

        $editSlug = $editing ? (string) ($categoriaEdit['slug'] ?? '') : '';

        $editDescricao = '';
        foreach (['descricao', 'description'] as $c) {
            if ($editing && isset($categoriaEdit[$c])) {
                $editDescricao = (string) $categoriaEdit[$c];
                break;
            }
        }

        $editStatus = 'ativo';
        if ($editing) {
            if (isset($categoriaEdit['status'])) {
                $editStatus = (string) $categoriaEdit['status'];
            } elseif (isset($categoriaEdit['ativo'])) {
                $editStatus = ((int) $categoriaEdit['ativo']) ? 'ativo' : 'inativo';
            } elseif (isset($categoriaEdit['active'])) {
                $editStatus = ((int) $categoriaEdit['active']) ? 'ativo' : 'inativo';
            }
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('categorias');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Categorias</h1>
                    <a href="/admin/categorias/novo" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Categoria</a>
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

        if ($categoriaEdit !== null) {
            echo '<div class="card mb-4">
                    <div class="card-header bg-white">
                        <strong>' . ($editId ? 'Editar Categoria' : 'Nova Categoria') . '</strong>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/categorias/salvar" class="row g-3">
                            <input type="hidden" name="id" value="' . $editId . '">
                            <div class="col-md-4">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="nome" value="' . htmlspecialchars($editNome) . '" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Slug</label>
                                <input type="text" class="form-control" name="slug" value="' . htmlspecialchars($editSlug) . '" placeholder="ex: eletronicos">
                                <small class="text-muted">Se vazio, será gerado automaticamente.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="ativo" ' . ($editStatus === 'ativo' ? 'selected' : '') . '>Ativa</option>
                                    <option value="inativo" ' . ($editStatus === 'inativo' ? 'selected' : '') . '>Inativa</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" rows="3">' . htmlspecialchars($editDescricao) . '</textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                                <a href="/admin/categorias" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>';
        }

        echo '<div class="card">
                <div class="card-header bg-white">
                    <strong>Lista de Categorias</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th style="width: 140px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (empty($categorias)) {
            echo '<tr><td colspan="5" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>';
        } else {
            foreach ($categorias as $c) {
                $nome = '';
                foreach (['nome', 'name'] as $nc) {
                    if (isset($c[$nc])) {
                        $nome = (string) $c[$nc];
                        break;
                    }
                }

                $slug = (string) ($c['slug'] ?? '');

                $status = 'Ativa';
                $badge = 'success';
                if (isset($c['status'])) {
                    $isAtivo = ((string) $c['status']) === 'ativo';
                    $status = $isAtivo ? 'Ativa' : 'Inativa';
                    $badge = $isAtivo ? 'success' : 'secondary';
                } elseif (isset($c['ativo'])) {
                    $isAtivo = ((int) $c['ativo']) === 1;
                    $status = $isAtivo ? 'Ativa' : 'Inativa';
                    $badge = $isAtivo ? 'success' : 'secondary';
                } elseif (isset($c['active'])) {
                    $isAtivo = ((int) $c['active']) === 1;
                    $status = $isAtivo ? 'Ativa' : 'Inativa';
                    $badge = $isAtivo ? 'success' : 'secondary';
                }

                echo '<tr>
                        <td>' . (int) $c['id'] . '</td>
                        <td>' . htmlspecialchars($nome) . '</td>
                        <td>' . ($slug ? '<code>' . htmlspecialchars($slug) . '</code>' : '<span class="text-muted">-</span>') . '</td>
                        <td><span class="badge bg-' . $badge . '">' . $status . '</span></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a class="btn btn-sm btn-outline-primary" href="/admin/categorias/editar/' . (int) $c['id'] . '"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="/admin/categorias/excluir/' . (int) $c['id'] . '" onsubmit="return confirm(\'Excluir esta categoria?\');">
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
