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
            
            $sql = "SELECT p.*, c.name as categoria FROM produtos p LEFT JOIN categorias c ON p.category_id = c.id WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (p.name LIKE :busca OR p.sku LIKE :busca)";
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
                
                if ($foto && $foto['nome_arquivo']) {
                    // Verificar se arquivo existe fisicamente
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                    if (file_exists($filePath)) {
                        $produto['imagem'] = 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'];
                    } else {
                        $produto['imagem'] = 'https://via.placeholder.com/300x200?text=Arquivo+Não+Encontrado';
                    }
                } else {
                    $produto['imagem'] = 'https://via.placeholder.com/300x200?text=Sem+Imagem';
                }
            }
            
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM produtos WHERE 1=1" . (!empty($busca) ? " AND (name LIKE :busca OR sku LIKE :busca)" : ""));
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
                            <img src="' . $produto['imagem'] . '" class="card-img-top product-image" alt="' . htmlspecialchars($produto['name']) . '">
                            <div class="card-body">
                                <h5 class="card-title">' . htmlspecialchars($produto['name']) . '</h5>
                                <p class="text-muted small">SKU: ' . htmlspecialchars($produto['sku']) . '</p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-primary">$' . number_format($produto['price'], 2, '.', ',') . '</span>
                                    <span class="badge ' . ($produto['active'] ? 'bg-success' : 'bg-danger') . '">' . ($produto['active'] ? 'Ativo' : 'Inativo') . '</span>
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
            $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY name ASC");
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
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="short_description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>';
                                            foreach ($categorias as $cat) {
                                                echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
                                            }
                                        echo '</select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Imagens</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*" id="imagensInput">
                                        <div id="imagePreview" class="row mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="active">
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
    <script>
        // Preview de imagens ao selecionar
        document.getElementById(\'imagensInput\').addEventListener(\'change\', function(e) {
            const preview = document.getElementById(\'imagePreview\');
            preview.innerHTML = \'\';
            
            Array.from(e.target.files).forEach((file, index) => {
                if (file.type.startsWith(\'image/\')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement(\'div\');
                        div.className = \'col-md-3 mb-2\';
                        div.innerHTML = \'<img src="\' + e.target.result + \'" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">\';
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>\';
        exit;
    }
    
    public function salvar(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $price = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('price'));
            
            // Validar categoria se fornecida
            $categoryId = $request->getParam('category_id');
            if (!empty($categoryId)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryId]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
            } else {
                $categoryId = null; // Permitir NULL se não selecionada
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO produtos (name, sku, short_description, category_id, price, weight, stock, active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $request->getParam('name'),
                $request->getParam('sku'),
                $request->getParam('short_description'),
                $categoryId,
                $price,
                $request->getParam('weight') ?: 0,
                $request->getParam('stock') ?: 0,
                $request->getParam('active') ?: 1
            ]);
            
            $produto_id = $pdo->lastInsertId();
            
            // Processar imagens
            if (isset($_FILES['imagens'])) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
                $webDir = '/uploads/produtos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === 0) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $produto_id,
                                $webPath,
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
    
    public function editar(Request $request, $id) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Buscar produto
            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$produto) {
                echo '<div class="alert alert-danger">Produto não encontrado</div>';
                exit;
            }
            
            // Buscar categorias
            $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY name ASC");
            $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar fotos do produto
            $stmtFotos = $pdo->prepare("SELECT * FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, ordem ASC");
            $stmtFotos->execute([$id]);
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar estatísticas do produto
            $stmtStats = $pdo->prepare("
                SELECT 
                    COUNT(pi.id) as total_vendas,
                    SUM(pi.quantidade) as total_itens_vendidos,
                    SUM(pi.subtotal) as total_faturado,
                    p.views as visualizacoes,
                    (SELECT COUNT(*) FROM pedido_items WHERE produto_id = ?) as numero_pedidos
                FROM produtos p 
                LEFT JOIN pedido_items pi ON p.id = pi.produto_id 
                WHERE p.id = ?
            ");
            $stmtStats->execute([$id, $id]);
            $stats = $stmtStats->fetch(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
        .foto-item { position: relative; margin-bottom: 10px; }
        .foto-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 5px; }
        .btn-remove-foto { position: absolute; top: -5px; right: -5px; }
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
                    <h1 class="h2">Editar Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <!-- Estatísticas do Produto -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Visualizações</h5>
                                <h3>' . number_format($stats['visualizacoes']) . '</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Vendas</h5>
                                <h3>' . number_format($stats['total_vendas']) . '</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Itens Vendidos</h5>
                                <h3>' . number_format($stats['total_itens_vendidos']) . '</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Faturado (USD)</h5>
                                <h3>$' . number_format($stats['total_faturado'], 2, '.', ',') . '</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="/admin/produtos/atualizar/' . $id . '" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="name" value="' . htmlspecialchars($produto['name']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" value="' . htmlspecialchars($produto['sku']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="short_description" rows="3">' . htmlspecialchars($produto['short_description']) . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Completa</label>
                                        <textarea class="form-control" name="description" rows="5">' . htmlspecialchars($produto['description']) . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>';
                                            foreach ($categorias as $cat) {
                                                $selected = $cat['id'] == $produto['category_id'] ? 'selected' : '';
                                                echo '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
                                            }
                                        echo '</select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <div class="row mb-3">';
                                        foreach ($fotos as $foto) {
                                            // Verificar se arquivo existe
                                            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                                            $imageUrl = file_exists($filePath) ? 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'] : 'https://via.placeholder.com/100x100?text=Erro';
                                            
                                            echo '<div class="col-md-2 foto-item">
                                                <a href="' . $imageUrl . '" target="_blank">
                                                    <img src="' . $imageUrl . '" alt="Foto" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-foto" onclick="removerFoto(' . $foto['id'] . ')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                ' . ($foto['principal'] ? '<span class="badge bg-primary">Principal</span>' : '') . '
                                            </div>';
                                        }
                                        echo '</div>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                        <small class="text-muted">Adicione novas fotos (múltiplas seleções permitidas)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" value="' . number_format($produto['price'], 2, '.', '') . '" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço de Custo (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="cost_price" value="' . number_format($produto['cost_price'], 2, '.', '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço Promocional (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="sale_price" value="' . number_format($produto['sale_price'], 2, '.', '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock" value="' . $produto['stock'] . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque Mínimo</label>
                                        <input type="number" class="form-control" name="min_stock" value="' . $produto['min_stock'] . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight" value="' . $produto['weight'] . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="draft" ' . ($produto['status'] == 'draft' ? 'selected' : '') . '>Rascunho</option>
                                            <option value="published" ' . ($produto['status'] == 'published' ? 'selected' : '') . '>Publicado</option>
                                            <option value="archived" ' . ($produto['status'] == 'archived' ? 'selected' : '') . '>Arquivado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ativo</label>
                                        <select class="form-select" name="active">
                                            <option value="1" ' . ($produto['active'] ? 'selected' : '') . '>Ativo</option>
                                            <option value="0" ' . (!$produto['active'] ? 'selected' : '') . '>Inativo</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Destaque</label>
                                        <select class="form-select" name="featured">
                                            <option value="1" ' . ($produto['featured'] ? 'selected' : '') . '>Sim</option>
                                            <option value="0" ' . (!$produto['featured'] ? 'selected' : '') . '>Não</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Atualizar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function removerFoto(fotoId) {
            if (confirm(\'Tem certeza que deseja remover esta foto?\')) {
                fetch(\'/admin/produtos/remover-foto/\' + fotoId, {method: \'DELETE\'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(\'Erro ao remover foto\');
                    }
                });
            }
        }
    </script>
</body>
</html>';
        exit;
    }
    
    public function atualizar(Request $request, $id) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $price = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('price'));
            $costPrice = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('cost_price'));
            $salePrice = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('sale_price'));
            
            // Validar categoria se fornecida
            $categoryId = $request->getParam('category_id');
            if (!empty($categoryId)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryId]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
            } else {
                $categoryId = null;
            }
            
            $stmt = $pdo->prepare("
                UPDATE produtos SET 
                    name = ?, sku = ?, description = ?, short_description = ?, category_id = ?, 
                    price = ?, cost_price = ?, sale_price = ?, stock = ?, min_stock = ?, weight = ?, 
                    status = ?, active = ?, featured = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $request->getParam('name'),
                $request->getParam('sku'),
                $request->getParam('description'),
                $request->getParam('short_description'),
                $categoryId,
                $price,
                $costPrice,
                $salePrice,
                $request->getParam('stock') ?: 0,
                $request->getParam('min_stock') ?: 0,
                $request->getParam('weight') ?: 0,
                $request->getParam('status'),
                $request->getParam('active') ?: 0,
                $request->getParam('featured') ?: 0,
                $id
            ]);
            
            // Processar novas imagens
            if (isset($_FILES['imagens'])) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
                $webDir = '/uploads/produtos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === 0) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $id,
                                $webPath,
                                $name,
                                0, // Não é principal por padrão
                                $key
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            header('Location: /admin/produtos?success=2');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function removerFoto(Request $request, $fotoId) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE id = ?");
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($foto) {
                // Remover arquivo físico
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Remover do banco
                $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE id = ?");
                $stmt->execute([$fotoId]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Foto não encontrada']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
