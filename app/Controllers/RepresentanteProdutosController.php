<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class RepresentanteProdutosController extends Controller {

    private function getPdo(): \PDO {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function getTableColumns(\PDO $pdo, string $table): array {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function getProdutoUploadsDir(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/produtos/',
            $docRoot . '/uploads/produtos/',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return rtrim($dir, '/\\') . '/';
            }
        }

        return rtrim($candidates[0], '/\\') . '/';
    }

    private function parseMoneyToDb($value): string {
        $s = trim((string) ($value ?? ''));
        if ($s === '') return '';
        $s = str_replace(['$', 'R$', ' '], '', $s);
        $s = str_replace(['.'], [''], $s);
        $s = str_replace([','], ['.'], $s);
        $s = preg_replace('/[^0-9\.\-]/', '', $s);
        return $s;
    }

    public function cadastroRapido(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);
        $this->renderCadastroRapido(null, null);
    }

    public function cadastroRapidoSalvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);
        try {
            $created = $this->salvarCadastroRapido($request);
            $this->renderCadastroRapido($created, null);
        } catch (\Exception $e) {
            $this->renderCadastroRapido(null, $e->getMessage());
        }
    }

    private function salvarCadastroRapido(Request $request): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $repEmail = (string) ($_SESSION['usuario_email'] ?? '');
        if ($repId <= 0 || $repEmail === '') {
            throw new \Exception('Sessão inválida. Faça login novamente.');
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        $cols = $this->getTableColumns($pdo, 'produtos');

        $name = (string) $request->getParam('name');
        $price = $this->parseMoneyToDb($request->getParam('price'));
        $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
        $weight = str_replace([','], ['.'], (string) $request->getParam('weight'));
        $stock = (int) $request->getParam('stock', 999);
        $featured = ($request->getParam('featured') ? 1 : 0);

        if (trim($name) === '') {
            throw new \Exception('Nome é obrigatório');
        }
        if (trim((string) $price) === '') {
            throw new \Exception('Preço (USD) é obrigatório');
        }
        if (trim((string) $costPrice) === '') {
            throw new \Exception('Preço de custo (USD) é obrigatório');
        }

        $data = [];
        if (in_array('name', $cols, true)) {
            $data['name'] = $name;
        } elseif (in_array('nome', $cols, true)) {
            $data['nome'] = $name;
        }

        if (in_array('price', $cols, true)) $data['price'] = $price;
        if (in_array('valor', $cols, true) && !isset($data['price'])) $data['valor'] = $price;

        if (in_array('cost_price', $cols, true)) $data['cost_price'] = $costPrice;
        if (in_array('preco_custo', $cols, true) && !isset($data['cost_price'])) $data['preco_custo'] = $costPrice;

        if (in_array('weight', $cols, true)) $data['weight'] = $weight;
        if (in_array('peso', $cols, true) && !isset($data['weight'])) $data['peso'] = $weight;

        if (in_array('stock', $cols, true)) $data['stock'] = $stock;
        if (in_array('estoque', $cols, true) && !isset($data['stock'])) $data['estoque'] = $stock;

        if (in_array('status', $cols, true)) $data['status'] = 'published';
        if (in_array('active', $cols, true)) $data['active'] = 1;
        if (in_array('ativo', $cols, true) && !isset($data['active'])) $data['ativo'] = 1;
        if (in_array('featured', $cols, true)) $data['featured'] = $featured;

        if (in_array('currency', $cols, true)) $data['currency'] = 'USD';
        if (in_array('moeda', $cols, true) && !isset($data['currency'])) $data['moeda'] = 'USD';

        if (in_array('representante_id', $cols, true)) $data['representante_id'] = $repId;
        if (in_array('representante_email', $cols, true)) $data['representante_email'] = $repEmail;

        if (in_array('created_at', $cols, true)) $data['created_at'] = date('Y-m-d H:i:s');
        if (in_array('updated_at', $cols, true)) $data['updated_at'] = date('Y-m-d H:i:s');

        $columnsSql = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO produtos ({$columnsSql}) VALUES ({$placeholders})");
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();

        $produtoId = (int) $pdo->lastInsertId();
        $fotoWebPath = '';

        if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = $this->getProdutoUploadsDir();
            $webDir = '/uploads/produtos/';
            $this->ensureDir($uploadDir);

            $orig = (string) $_FILES['capa']['name'];
            $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $orig);
            $fileName = time() . '_' . $fileName;
            $filePath = $uploadDir . $fileName;
            $fotoWebPath = $webDir . $fileName;

            if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                if (in_array('foto_principal', $cols, true)) {
                    $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                    $stmtCover->execute([$fotoWebPath, $produtoId]);
                }
            }
        }

        $pdo->commit();

        return [
            'id' => $produtoId,
            'name' => $name,
            'price' => $price,
            'cost_price' => $costPrice,
            'weight' => $weight,
            'stock' => $stock,
            'featured' => $featured,
            'foto_principal' => $fotoWebPath,
        ];
    }

    private function renderCadastroRapido(?array $created, ?string $error): void {
        $successHtml = '';
        if (!empty($error)) {
            $successHtml = '<div class="alert alert-danger" style="border-radius:14px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        } elseif (is_array($created) && !empty($created['id'])) {
            $id = (int) $created['id'];
            $nome = htmlspecialchars((string) ($created['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $foto = (string) ($created['foto_principal'] ?? '');
            $valor = htmlspecialchars((string) ($created['price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $custo = htmlspecialchars((string) ($created['cost_price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $peso = htmlspecialchars((string) ($created['weight'] ?? ''), ENT_QUOTES, 'UTF-8');
            $estoque = htmlspecialchars((string) ($created['stock'] ?? ''), ENT_QUOTES, 'UTF-8');
            $destaque = !empty($created['featured']) ? 'Sim' : 'Não';
            if ($foto === '') {
                $foto = '/uploads/produtos/placeholder.jpg';
            }
            $fotoEsc = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
            $link = '/produto/detalhes/' . $id;
            $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            $successHtml = '<div class="alert alert-success d-flex align-items-start gap-2" role="alert" style="border-radius: 14px;">'
                . '<i class="fas fa-check-circle mt-1"></i>'
                . '<div><div class="fw-bold">Produto salvo com sucesso.</div><div class="small">Ele já está vinculado ao seu usuário representante.</div></div>'
                . '</div>'
                . '<div class="card border-0 shadow-sm mb-3" style="border-radius: 18px; overflow: hidden;">'
                . '<div class="row g-0">'
                . '<div class="col-4" style="min-height: 92px;"><img src="' . $fotoEsc . '" alt="' . $nome . '" style="width:100%;height:100%;object-fit:cover;min-height:92px;"></div>'
                . '<div class="col-8"><div class="card-body py-3">'
                . '<div class="fw-bold" style="line-height:1.2">' . $nome . '</div>'
                . '<div class="small text-muted">Link do produto</div>'
                . '<div class="small" style="word-break: break-all;"><a href="' . $linkEsc . '" target="_blank">' . $linkEsc . '</a></div>'
                . '<div class="small text-muted mt-2">Detalhes</div>'
                . '<div class="small">Valor (USD): <span class="fw-semibold">$ ' . $valor . '</span></div>'
                . '<div class="small">Custo (USD): <span class="fw-semibold">$ ' . $custo . '</span></div>'
                . '<div class="small">Peso (kg): <span class="fw-semibold">' . $peso . '</span></div>'
                . '<div class="small">Estoque: <span class="fw-semibold">' . $estoque . '</span></div>'
                . '<div class="small">Destaque: <span class="fw-semibold">' . htmlspecialchars($destaque, ENT_QUOTES, 'UTF-8') . '</span></div>'
                . '<div class="d-grid gap-2 mt-2">'
                . '<a class="btn btn-outline-primary" href="' . $linkEsc . '" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Abrir produto</a>'
                . '<a class="btn btn-primary" href="/admin/produtos/cadastro-representante"><i class="fas fa-plus me-2"></i>Novo envio</a>'
                . '</div>'
                . '</div></div>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Rápido (Representante) - Produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg: #f6f8fb;
            --radius: 18px;
            --shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
        }
        body { background: var(--bg); }
        .topbar {
            background: radial-gradient(1200px 260px at 50% -60%, rgba(11,31,58,0.18), rgba(11,31,58,0)) ,
                        linear-gradient(180deg, rgba(11,31,58,0.06), rgba(11,31,58,0));
            padding: 16px 0 10px;
        }
        .page-title { color: var(--primary); font-weight: 800; letter-spacing: -0.02em; }
        .subtle { color: rgba(15, 23, 42, 0.62); }
        .glass {
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.24);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .form-control, .input-group-text, .btn { border-radius: 14px; }
        .input-group .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-outline-primary { border-color: rgba(11,31,58,0.35); color: var(--primary); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container" style="max-width: 560px;">
            <div class="d-flex align-items-center justify-content-between">
                <a href="/admin/representante/produtos" class="btn btn-outline-secondary btn-sm" style="border-radius: 999px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="text-center">
                    <div class="page-title">Cadastro rápido (Representante)</div>
                    <div class="small subtle">USD apenas • custo obrigatório</div>
                </div>
                <span style="width: 40px;"></span>
            </div>
        </div>
    </div>

    <div class="container pb-4" style="max-width: 560px;">
HTML;

        echo $successHtml;

        echo <<<'HTML'
        <div class="glass p-3 p-sm-4">
            <form method="POST" action="/admin/produtos/cadastro-representante/salvar" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome do produto *</label>
                    <input type="text" class="form-control form-control-lg" name="name" required placeholder="Nome do produto">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Preço (USD) *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" name="price" required placeholder="0,00">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Preço de custo (USD) *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" name="cost_price" required placeholder="0,00">
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Peso (kg)</label>
                        <input type="text" class="form-control" name="weight" placeholder="0,500">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Estoque</label>
                        <input type="number" class="form-control" name="stock" value="999" min="0" step="1">
                    </div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="featuredSwitch" name="featured" value="1">
                    <label class="form-check-label" for="featuredSwitch">Destaque</label>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label fw-semibold">Foto do produto</label>
                    <input type="file" class="form-control" name="capa" accept="image/*">
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Salvar produto
                    </button>
                    <a href="/admin/representante/produtos" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i>Meus produtos
                    </a>
                </div>

                <div class="small subtle mt-3">Os produtos cadastrados por você aparecerão também na vitrine pública do seu link.</div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;

        exit;
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $repId = (int) ($_SESSION['usuario_id'] ?? 0);

        try {
            $pdo = $this->getPdo();
            $cols = $this->getTableColumns($pdo, 'produtos');
            if (!in_array('representante_id', $cols, true)) {
                throw new \Exception('Schema não possui representante_id em produtos (rodar migration 071).');
            }

            $stmt = $pdo->prepare('SELECT id, name, price, cost_price, stock, weight, status, active, foto_principal, created_at FROM produtos WHERE representante_id = ? ORDER BY id DESC');
            $stmt->execute([$repId]);
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $produtos = [];
            $erro = $e->getMessage();
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Produtos - Representante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Meus Produtos</h3>
        <div class="d-flex gap-2">
            <a href="/admin/produtos/cadastro-representante" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Novo produto</a>
            <a href="/admin/representante/comissoes" class="btn btn-outline-primary"><i class="fas fa-percentage me-1"></i>Comissões</a>
        </div>
    </div>';

        if (!empty($erro)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if (empty($produtos)) {
            echo '<div class="alert alert-info">Nenhum produto cadastrado ainda.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">
<thead><tr>
<th>ID</th><th>Produto</th><th>Preço</th><th>Custo</th><th>Estoque</th><th>Ações</th>
</tr></thead><tbody>';
            foreach ($produtos as $p) {
                $id = (int) ($p['id'] ?? 0);
                $name = htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $price = number_format((float) ($p['price'] ?? 0), 2, ',', '.');
                $cost = number_format((float) ($p['cost_price'] ?? 0), 2, ',', '.');
                $stock = (int) ($p['stock'] ?? 0);
                echo '<tr>'
                    . '<td>' . $id . '</td>'
                    . '<td>' . $name . '</td>'
                    . '<td>$ ' . $price . '</td>'
                    . '<td>$ ' . $cost . '</td>'
                    . '<td>' . $stock . '</td>'
                    . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/representante/produtos/editar/' . $id . '"><i class="fas fa-edit"></i></a></td>'
                    . '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function editar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $produtoId = (int) ($id ?? $request->getParam('id'));

        try {
            $pdo = $this->getPdo();
            $cols = $this->getTableColumns($pdo, 'produtos');
            if (!in_array('representante_id', $cols, true)) {
                throw new \Exception('Schema não possui representante_id em produtos (rodar migration 071).');
            }

            $stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ? AND representante_id = ? LIMIT 1');
            $stmt->execute([$produtoId, $repId]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$produto) {
                throw new \Exception('Produto não encontrado ou não pertence a você.');
            }
        } catch (\Exception $e) {
            echo '<div class="container py-4"><div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div><a href="/admin/representante/produtos" class="btn btn-secondary">Voltar</a></div>';
            exit;
        }

        $name = htmlspecialchars((string) ($produto['name'] ?? ($produto['nome'] ?? '')), ENT_QUOTES, 'UTF-8');
        $price = htmlspecialchars((string) ($produto['price'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cost = htmlspecialchars((string) ($produto['cost_price'] ?? ($produto['preco_custo'] ?? '')), ENT_QUOTES, 'UTF-8');
        $weight = htmlspecialchars((string) ($produto['weight'] ?? ($produto['peso'] ?? '')), ENT_QUOTES, 'UTF-8');
        $stock = (int) ($produto['stock'] ?? ($produto['estoque'] ?? 0));

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - Representante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Editar Produto</h3>
        <a href="/admin/representante/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card"><div class="card-body">
        <form method="POST" action="/admin/representante/produtos/atualizar/' . (int) $produtoId . '">
            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" class="form-control" name="name" value="' . $name . '" required>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Preço (USD)</label>
                    <input type="text" class="form-control" name="price" value="' . $price . '" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Custo (USD)</label>
                    <input type="text" class="form-control" name="cost_price" value="' . $cost . '" required>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Peso (kg)</label>
                    <input type="text" class="form-control" name="weight" value="' . $weight . '">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estoque</label>
                    <input type="number" class="form-control" name="stock" value="' . (int) $stock . '" min="0" step="1">
                </div>
            </div>
            <div class="d-grid mt-3">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i>Salvar</button>
            </div>
        </form>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function atualizar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['representante']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $repId = (int) ($_SESSION['usuario_id'] ?? 0);
        $produtoId = (int) ($id ?? $request->getParam('id'));

        $name = (string) $request->getParam('name');
        $price = $this->parseMoneyToDb($request->getParam('price'));
        $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
        $weight = str_replace([','], ['.'], (string) $request->getParam('weight'));
        $stock = (int) $request->getParam('stock', 0);

        if (trim($name) === '' || trim((string) $price) === '' || trim((string) $costPrice) === '') {
            $_SESSION['message'] = 'Preencha nome, preço e custo.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/representante/produtos/editar/' . $produtoId);
            exit;
        }

        try {
            $pdo = $this->getPdo();
            $cols = $this->getTableColumns($pdo, 'produtos');

            if (!in_array('representante_id', $cols, true)) {
                throw new \Exception('Schema não possui representante_id em produtos (rodar migration 071).');
            }

            // segurança: atualizar só se pertence ao representante
            $stmtCheck = $pdo->prepare('SELECT id FROM produtos WHERE id = ? AND representante_id = ? LIMIT 1');
            $stmtCheck->execute([$produtoId, $repId]);
            if (!$stmtCheck->fetch()) {
                throw new \Exception('Produto não encontrado ou não pertence a você.');
            }

            $set = [];
            $vals = [];

            if (in_array('name', $cols, true)) {
                $set[] = 'name = ?';
                $vals[] = $name;
            } elseif (in_array('nome', $cols, true)) {
                $set[] = 'nome = ?';
                $vals[] = $name;
            }

            if (in_array('price', $cols, true)) {
                $set[] = 'price = ?';
                $vals[] = $price;
            }
            if (in_array('cost_price', $cols, true)) {
                $set[] = 'cost_price = ?';
                $vals[] = $costPrice;
            }
            if (in_array('weight', $cols, true)) {
                $set[] = 'weight = ?';
                $vals[] = $weight;
            }
            if (in_array('stock', $cols, true)) {
                $set[] = 'stock = ?';
                $vals[] = $stock;
            }
            if (in_array('currency', $cols, true)) {
                $set[] = 'currency = ?';
                $vals[] = 'USD';
            }
            if (in_array('updated_at', $cols, true)) {
                $set[] = 'updated_at = NOW()';
            }

            if (empty($set)) {
                throw new \Exception('Nenhuma coluna atualizável encontrada.');
            }

            $vals[] = $produtoId;
            $vals[] = $repId;
            $sql = 'UPDATE produtos SET ' . implode(', ', $set) . ' WHERE id = ? AND representante_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($vals);

            header('Location: /admin/representante/produtos');
            exit;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao atualizar: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/representante/produtos/editar/' . $produtoId);
            exit;
        }
    }
}
