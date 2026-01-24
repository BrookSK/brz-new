<?php
namespace App\Controllers;

use App\Core\Request;

class AdminProdutosController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            
            $sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.nome LIKE :busca OR p.sku LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar imagens
            foreach ($produtos as &$produto) {
                $stmtFotos = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE produto_id = :produto_id ORDER BY principal DESC LIMIT 1");
                $stmtFotos->bindParam(':produto_id', $produto['id']);
                $stmtFotos->execute();
                $foto = $stmtFotos->fetch(\PDO::FETCH_ASSOC);
                $produto['imagem'] = $foto ? 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'] : 'https://via.placeholder.com/300x200';
            }
            
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM produtos WHERE 1=1" . (!empty($busca) ? " AND (nome LIKE :busca OR sku LIKE :busca)" : ""));
            if (!empty($busca)) $stmtTotal->bindValue(':busca', "%{$busca}%");
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
        } catch (\Exception $e) {
            $produtos = [];
            $total = 0;
            $totalPaginas = 0;
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .product-card { transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-5px); }
        .product-image { height: 200px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Produtos (' . $total . ')</h1>
                    <a href="/admin/produtos/novo" class="btn btn-primary"><i class="fas fa-plus"></i> Novo</a>
                </div>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar produto..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </form>
                
                <div class="row">';
                
                foreach ($produtos as $produto) {
                    echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card product-card h-100">
                            <img src="' . $produto['imagem'] . '" class="card-img-top product-image" alt="' . htmlspecialchars($produto['nome']) . '">
                            <div class="card-body">
                                <h5 class="card-title">' . htmlspecialchars($produto['nome']) . '</h5>
                                <p class="text-muted small">SKU: ' . htmlspecialchars($produto['sku']) . '</p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-primary">R$ ' . number_format($produto['valor'], 2, ',', '.') . '</span>
                                    <span class="badge ' . ($produto['ativo'] ? 'bg-success' : 'bg-danger') . '">' . ($produto['ativo'] ? 'Ativo' : 'Inativo') . '</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/produtos/editar/' . $produto['id'] . '" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                                    <form method="POST" action="/admin/produtos/excluir/' . $produto['id'] . '" style="display: inline;">
                                        <button type="submit" onclick="return confirm(\'Tem certeza?\')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                echo '</div>';
                
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/produtos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
    
    public function novo(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
            $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $categorias = [];
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Novo Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <form method="POST" action="/admin/produtos/salvar" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="nome" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="descricao_curta" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="categoria_id">
                                            <option value="">Selecione...</option>';
                                            foreach ($categorias as $cat) {
                                                echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['nome']) . '</option>';
                                            }
                                        echo '</select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Imagens</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control" name="valor" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="peso">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="estoque">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="ativo">
                                            <option value="1">Ativo</option>
                                            <option value="0">Inativo</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }
    
    public function salvar(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $valor = str_replace(['R$', '.', ','], ['', '', '.'], $request->getParam('valor'));
            
            $stmt = $pdo->prepare("
                INSERT INTO produtos (nome, sku, descricao_curta, categoria_id, valor, peso, estoque, ativo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $request->getParam('nome'),
                $request->getParam('sku'),
                $request->getParam('descricao_curta'),
                $request->getParam('categoria_id'),
                $valor,
                $request->getParam('peso') ?: 0,
                $request->getParam('estoque') ?: 0,
                $request->getParam('ativo') ?: 1
            ]);
            
            $produto_id = $pdo->lastInsertId();
            
            // Processar imagens
            if (isset($_FILES['imagens'])) {
                $uploadDir = 'uploads/produtos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === 0) {
                        $fileName = time() . '_' . $name;
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $uploadDir . $fileName)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $produto_id,
                                $uploadDir . $fileName,
                                $name,
                                $key === 0 ? 1 : 0,
                                $key
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            header('Location: /admin/produtos?success=1');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
}
