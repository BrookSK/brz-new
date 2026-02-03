<?php
namespace App\Controllers;

use App\Core\Request;

class AdminVariacoesController extends Controller {

    private function getPdo(): \PDO {
        return new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensureTables(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS variacao_tipos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_variacao_tipos_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS variacao_opcoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_id INT NOT NULL,
            valor VARCHAR(120) NOT NULL,
            nome_exibicao VARCHAR(120) NULL,
            imagem VARCHAR(500) NULL,
            slug VARCHAR(120) NOT NULL,
            ordem INT NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            KEY idx_variacao_opcoes_tipo (tipo_id),
            UNIQUE KEY uq_variacao_opcoes_tipo_slug (tipo_id, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Backfill idempotente para bases já existentes
        try {
            $pdo->exec("ALTER TABLE variacao_opcoes ADD COLUMN nome_exibicao VARCHAR(120) NULL AFTER valor");
        } catch (\Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE variacao_opcoes ADD COLUMN imagem VARCHAR(500) NULL AFTER nome_exibicao");
        } catch (\Throwable $e) {
        }
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    private function getVariacoesUploadsDir(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/variacoes/',
            $docRoot . '/uploads/variacoes/',
            __DIR__ . '/../../public/uploads/variacoes/',
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '' && (is_dir($c) || @mkdir($c, 0777, true))) {
                return rtrim($c, '/\\') . DIRECTORY_SEPARATOR;
            }
        }
        return rtrim((string) $candidates[0], '/\\') . DIRECTORY_SEPARATOR;
    }

    private function slugify(string $value): string {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[\s\_]+/u', '-', $value);
        $value = preg_replace('/[^a-z0-9\-]/', '', $value);
        $value = preg_replace('/\-+/', '-', $value);
        return trim($value, '-');
    }

    private function redirectToIndex(?int $tipoId = null): void {
        $url = '/admin/variacoes';
        if ($tipoId && $tipoId > 0) {
            $url .= '?tipo_id=' . (int) $tipoId;
        }
        header('Location: ' . $url);
        exit;
    }

    public function index(Request $request) {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $tipos = [];
        $opcoes = [];
        $tipoSelecionado = null;
        $tipoId = (int) $request->getParam('tipo_id', 0);

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $stmtTipos = $pdo->query('SELECT * FROM variacao_tipos ORDER BY ativo DESC, nome ASC');
            $tipos = $stmtTipos ? ($stmtTipos->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

            if ($tipoId > 0) {
                $stmtT = $pdo->prepare('SELECT * FROM variacao_tipos WHERE id = :id LIMIT 1');
                $stmtT->execute([':id' => $tipoId]);
                $tipoSelecionado = $stmtT->fetch(\PDO::FETCH_ASSOC) ?: null;

                if ($tipoSelecionado) {
                    $stmtOp = $pdo->prepare('SELECT * FROM variacao_opcoes WHERE tipo_id = :tid ORDER BY ativo DESC, ordem ASC, valor ASC');
                    $stmtOp->execute([':tid' => $tipoId]);
                    $opcoes = $stmtOp->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            }
        } catch (\Exception $e) {
        }

        renderAdminSidebarStyles();

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variações - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('variacoes');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Variações</h1>
                </div>';

        if (isset($_SESSION['message'])) {
            $type = $_SESSION['message_type'] ?? 'info';
            $msg = $_SESSION['message'];
            unset($_SESSION['message'], $_SESSION['message_type']);
            echo '<div class="alert alert-' . htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">'
                . htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8')
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
                . '</div>';
        }

        echo '<div class="row g-3">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <strong>Tipos de variação</strong>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin/variacoes/tipos/salvar" class="row g-2 mb-3">
                                <input type="hidden" name="id" value="0">
                                <div class="col-md-6">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="nome" class="form-control" placeholder="Ex: Cor" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Slug (opcional)</label>
                                    <input type="text" name="slug" class="form-control" placeholder="ex: cor">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-2"></i>Adicionar tipo</button>
                                </div>
                            </form>

                            <div class="list-group">';

        if (empty($tipos)) {
            echo '<div class="text-muted">Nenhum tipo cadastrado.</div>';
        } else {
            foreach ($tipos as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $nome = (string) ($t['nome'] ?? '');
                $slug = (string) ($t['slug'] ?? '');
                $ativo = (int) ($t['ativo'] ?? 0);
                $isActive = ($tid === $tipoId);

                echo '<div class="list-group-item d-flex justify-content-between align-items-center ' . ($isActive ? 'active' : '') . '">
                        <div>
                            <div class="fw-semibold">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="small ' . ($isActive ? 'text-white-50' : 'text-muted') . '">' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</div>
                        </div>
                        <div class="d-flex gap-2">';

                echo '<a class="btn btn-sm ' . ($isActive ? 'btn-light' : 'btn-outline-primary') . '" href="/admin/variacoes?tipo_id=' . $tid . '">
                        <i class="fas fa-list"></i>
                      </a>';

                if ($ativo === 1) {
                    echo '<form method="POST" action="/admin/variacoes/tipos/inativar/' . $tid . '" onsubmit="return confirm(\'Inativar este tipo?\')">
                            <button type="submit" class="btn btn-sm ' . ($isActive ? 'btn-light' : 'btn-outline-danger') . '"><i class="fas fa-ban"></i></button>
                          </form>';
                } else {
                    echo '<span class="badge bg-secondary">Inativo</span>';
                }

                echo '      </div>
                    </div>';
            }
        }

        echo '          </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <strong>Opções do tipo</strong>
                        </div>
                        <div class="card-body">';

        if (!$tipoSelecionado) {
            echo '<div class="text-muted">Selecione um tipo para cadastrar as opções (ex: Preto, Branco, P, M).</div>';
        } else {
            $tipoNome = (string) ($tipoSelecionado['nome'] ?? '');
            echo '<div class="mb-3">
                    <div class="fw-semibold">Tipo selecionado: ' . htmlspecialchars($tipoNome, ENT_QUOTES, 'UTF-8') . '</div>
                  </div>';

            echo '<form method="POST" action="/admin/variacoes/opcoes/salvar" class="row g-2 mb-3" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="0">
                    <input type="hidden" name="tipo_id" value="' . (int) $tipoId . '">
                    <div class="col-md-6">
                        <label class="form-label">Valor</label>
                        <input type="text" name="valor" class="form-control" placeholder="Ex: Preto" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome de exibição (opcional)</label>
                        <input type="text" name="nome_exibicao" class="form-control" placeholder="Ex: Azul Royal">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slug (opcional)</label>
                        <input type="text" name="slug" class="form-control" placeholder="ex: preto">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="ordem" class="form-control" value="0" min="0" step="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Imagem (opcional)</label>
                        <input type="file" name="imagem" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-2"></i>Adicionar opção</button>
                    </div>
                  </form>';

            if (empty($opcoes)) {
                echo '<div class="text-muted">Nenhuma opção cadastrada.</div>';
            } else {
                echo '<div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 56px;"></th>
                                    <th>Valor</th>
                                    <th>Exibição</th>
                                    <th>Slug</th>
                                    <th>Ordem</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

                foreach ($opcoes as $o) {
                    $oid = (int) ($o['id'] ?? 0);
                    $valor = (string) ($o['valor'] ?? '');
                    $nomeExibicao = (string) ($o['nome_exibicao'] ?? '');
                    $imagem = (string) ($o['imagem'] ?? '');
                    $slug = (string) ($o['slug'] ?? '');
                    $ordem = (int) ($o['ordem'] ?? 0);
                    $ativo = (int) ($o['ativo'] ?? 0);

                    echo '<tr>
                            <td>';
                    if ($imagem !== '') {
                        echo '<img src="' . htmlspecialchars($imagem, ENT_QUOTES, 'UTF-8') . '?v=' . time() . '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:10px;border:1px solid rgba(15,23,42,0.12)">';
                    } else {
                        echo '<div class="text-muted" style="width:40px;height:40px;border-radius:10px;border:1px dashed rgba(15,23,42,0.14);display:flex;align-items:center;justify-content:center">-</div>';
                    }
                    echo    '</td>
                            <td class="fw-semibold">' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td>
                            <td class="text-muted">' . htmlspecialchars($nomeExibicao, ENT_QUOTES, 'UTF-8') . '</td>
                            <td class="text-muted">' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</td>
                            <td>' . $ordem . '</td>
                            <td>' . ($ativo ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>') . '</td>
                            <td class="text-end">';

                    if ($ativo) {
                        echo '<form method="POST" action="/admin/variacoes/opcoes/inativar/' . $oid . '" style="display:inline" onsubmit="return confirm(\'Inativar esta opção?\')">
                                <input type="hidden" name="tipo_id" value="' . (int) $tipoId . '">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                              </form>';
                    }

                    echo '      </td>
                          </tr>';
                }

                echo '      </tbody>
                        </table>
                    </div>';
            }
        }

        echo '          </div>
                    </div>
                </div>
            </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

        exit;
    }

    public function salvarTipo(Request $request) {
        $nome = trim((string) $request->getParam('nome', ''));
        $slug = trim((string) $request->getParam('slug', ''));

        if ($nome === '') {
            $_SESSION['message'] = 'Informe o nome do tipo.';
            $_SESSION['message_type'] = 'danger';
            $this->redirectToIndex(null);
        }

        if ($slug === '') {
            $slug = $this->slugify($nome);
        } else {
            $slug = $this->slugify($slug);
        }

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $stmt = $pdo->prepare('INSERT INTO variacao_tipos (nome, slug, ativo, created_at, updated_at) VALUES (:nome, :slug, 1, NOW(), NOW())');
            $stmt->execute([':nome' => $nome, ':slug' => $slug]);

            $_SESSION['message'] = 'Tipo criado com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao salvar tipo.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirectToIndex(null);
    }

    public function inativarTipo(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $stmt = $pdo->prepare('UPDATE variacao_tipos SET ativo = 0, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $_SESSION['message'] = 'Tipo inativado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao inativar tipo.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirectToIndex(null);
    }

    public function salvarOpcao(Request $request) {
        $tipoId = (int) $request->getParam('tipo_id', 0);
        $valor = trim((string) $request->getParam('valor', ''));
        $nomeExibicao = trim((string) $request->getParam('nome_exibicao', ''));
        $slug = trim((string) $request->getParam('slug', ''));
        $ordem = (int) $request->getParam('ordem', 0);

        if ($tipoId <= 0) {
            $_SESSION['message'] = 'Selecione um tipo válido.';
            $_SESSION['message_type'] = 'danger';
            $this->redirectToIndex(null);
        }

        if ($valor === '') {
            $_SESSION['message'] = 'Informe o valor da opção.';
            $_SESSION['message_type'] = 'danger';
            $this->redirectToIndex($tipoId);
        }

        if ($slug === '') {
            $slug = $this->slugify($valor);
        } else {
            $slug = $this->slugify($slug);
        }

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $imagemPath = null;
            if (isset($_FILES['imagem']) && !empty($_FILES['imagem']['name']) && ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getVariacoesUploadsDir();
                $this->ensureDir($uploadDir);
                $webDir = '/uploads/variacoes/';

                $nameFile = (string) $_FILES['imagem']['name'];
                $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $nameFile);
                $fileName = time() . '_' . $tipoId . '_' . $clean;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filePath)) {
                    $imagemPath = $webPath;
                }
            }

            $stmt = $pdo->prepare('INSERT INTO variacao_opcoes (tipo_id, valor, nome_exibicao, imagem, slug, ordem, ativo, created_at, updated_at) VALUES (:tipo_id, :valor, :nome_exibicao, :imagem, :slug, :ordem, 1, NOW(), NOW())');
            $stmt->execute([
                ':tipo_id' => $tipoId,
                ':valor' => $valor,
                ':nome_exibicao' => ($nomeExibicao !== '' ? $nomeExibicao : null),
                ':imagem' => $imagemPath,
                ':slug' => $slug,
                ':ordem' => $ordem,
            ]);

            $_SESSION['message'] = 'Opção criada com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao salvar opção (talvez slug duplicado).';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirectToIndex($tipoId);
    }

    public function inativarOpcao(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));
        $tipoId = (int) $request->getParam('tipo_id', 0);

        try {
            $pdo = $this->getPdo();
            $this->ensureTables($pdo);

            $stmt = $pdo->prepare('UPDATE variacao_opcoes SET ativo = 0, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $_SESSION['message'] = 'Opção inativada.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao inativar opção.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirectToIndex($tipoId > 0 ? $tipoId : null);
    }
}
