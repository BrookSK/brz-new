<?php
namespace App\Controllers;

use App\Core\Request;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        echo '<h1>Dashboard do Controller</h1><p>Controller funcionando!</p>';
        exit;
    }
    
    public function produtos(Request $request) {
        // Conexão com o banco
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        // Parâmetros de filtro e paginação
        $pagina = $request->getParam('pagina', 1);
        $limite = 10;
        $offset = ($pagina - 1) * $limite;
        $busca = $request->getParam('busca', '');
        $status = $request->getParam('status', '');
        $categoria_id = $request->getParam('categoria_id', '');
        
        // Construir SQL com filtros
        $sql = "
            SELECT p.*, c.nome as categoria_nome 
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($busca)) {
            $sql .= " AND (p.nome LIKE :busca OR p.sku LIKE :busca)";
            $params[':busca'] = "%{$busca}%";
        }
        
        if (!empty($status)) {
            $sql .= " AND p.ativo = :status";
            $params[':status'] = $status === 'ativo' ? 1 : 0;
        }
        
        if (!empty($categoria_id)) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoria_id;
        }
        
        $sql .= " ORDER BY p.nome ASC LIMIT :limite OFFSET :offset";
        
        // Executar consulta principal
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Total para paginação
        $sqlTotal = "SELECT COUNT(*) as total FROM produtos WHERE 1=1";
        $paramsTotal = [];
        
        if (!empty($busca)) {
            $sqlTotal .= " AND (nome LIKE :busca OR sku LIKE :busca)";
            $paramsTotal[':busca'] = "%{$busca}%";
        }
        
        if (!empty($status)) {
            $sqlTotal .= " AND ativo = :status";
            $paramsTotal[':status'] = $status === 'ativo' ? 1 : 0;
        }
        
        if (!empty($categoria_id)) {
            $sqlTotal .= " AND categoria_id = :categoria_id";
            $paramsTotal[':categoria_id'] = $categoria_id;
        }
        
        $stmtTotal = $pdo->prepare($sqlTotal);
        foreach ($paramsTotal as $key => $value) {
            $stmtTotal->bindValue($key, $value);
        }
        $stmtTotal->execute();
        $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
        $totalPaginas = ceil($total / $limite);
        
        // Obter categorias para filtro
        $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
        $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        
        // Exibir filtros
        echo '<h1>Produtos (' . $total . ' encontrados)</h1>';
        
        // Botão Novo Produto
        echo '<div style="margin-bottom: 20px;">';
        echo '<a href="/admin/novo-produto" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">+ Novo Produto</a>';
        echo '</div>';
        
        echo '<div style="background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
        echo '<form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
        echo '<input type="text" name="busca" placeholder="Buscar por nome ou SKU" value="' . htmlspecialchars($busca) . '" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
        
        echo '<select name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<option value="">Todos os status</option>';
        echo '<option value="ativo" ' . ($status === 'ativo' ? 'selected' : '') . '>Ativos</option>';
        echo '<option value="inativo" ' . ($status === 'inativo' ? 'selected' : '') . '>Inativos</option>';
        echo '</select>';
        
        echo '<select name="categoria_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<option value="">Todas categorias</option>';
        foreach ($categorias as $cat) {
            echo '<option value="' . $cat['id'] . '" ' . ($categoria_id == $cat['id'] ? 'selected' : '') . '>' . htmlspecialchars($cat['nome']) . '</option>';
        }
        echo '</select>';
        
        echo '<button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Filtrar</button>';
        echo '</form>';
        echo '</div>';
        
        // Listagem de produtos
        foreach ($produtos as $produto) {
            echo '<div style="border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; background: white;">';
            echo '<h3>' . htmlspecialchars($produto['nome']) . '</h3>';
            echo '<p><strong>SKU:</strong> ' . htmlspecialchars($produto['sku']) . '</p>';
            echo '<p><strong>Categoria:</strong> ' . htmlspecialchars($produto['categoria_nome'] ?? 'N/A') . '</p>';
            echo '<p><strong>Preço:</strong> R$ ' . number_format($produto['valor'], 2, ',', '.') . '</p>';
            echo '<p><strong>Status:</strong> <span style="color: ' . ($produto['ativo'] ? 'green' : 'red') . ';">' . ($produto['ativo'] ? 'Ativo' : 'Inativo') . '</span></p>';
            
            // Ações CRUD
            echo '<div style="margin-top: 10px; display: flex; gap: 5px;">';
            echo '<a href="/admin/editar-produto/' . $produto['id'] . '" style="padding: 5px 10px; background: #ffc107; color: #856404; text-decoration: none; border-radius: 4px;">Editar</a>';
            echo '<form method="POST" action="/admin/excluir-produto/' . $produto['id'] . '" style="display: inline;">';
            echo '<button type="submit" onclick="return confirm(\'Tem certeza que deseja excluir este produto?\')" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Excluir</button>';
            echo '</form>';
            echo '</div>';
            
            // Buscar galeria de imagens
            $stmtFotos = $pdo->prepare("
                SELECT * FROM produto_fotos 
                WHERE produto_id = :produto_id 
                ORDER BY principal DESC, ordem ASC
            ");
            $stmtFotos->bindParam(':produto_id', $produto['id']);
            $stmtFotos->execute();
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($fotos)) {
                echo '<p><strong>Galeria (' . count($fotos) . ' imagens):</strong></p>';
                foreach ($fotos as $foto) {
                    $url = 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'];
                    echo '<img src="' . $url . '" alt="' . htmlspecialchars($foto['legenda'] ?? '') . '" style="width: 100px; height: 100px; margin: 2px; border: 1px solid #ddd; border-radius: 4px; object-fit: cover;">';
                    if ($foto['principal']) {
                        echo '<span style="color: green; font-size: 12px;">[PRINCIPAL]</span>';
                    }
                    echo '<br>';
                }
            } else {
                echo '<p><strong>Galeria:</strong> Nenhuma imagem</p>';
            }
            
            echo '</div>';
        }
        
        // Paginação
        if ($totalPaginas > 1) {
            echo '<div style="text-align: center; margin: 20px 0;">';
            for ($i = 1; $i <= $totalPaginas; $i++) {
                $url = "/admin/produtos?pagina={$i}";
                if (!empty($busca)) $url .= "&busca=" . urlencode($busca);
                if (!empty($status)) $url .= "&status={$status}";
                if (!empty($categoria_id)) $url .= "&categoria_id={$categoria_id}";
                
                if ($i == $pagina) {
                    echo '<span style="padding: 8px 12px; background: #007bff; color: white; border-radius: 4px; margin: 0 2px;">' . $i . '</span>';
                } else {
                    echo '<a href="' . $url . '" style="padding: 8px 12px; background: #f8f9fa; color: #333; text-decoration: none; border: 1px solid #ddd; border-radius: 4px; margin: 0 2px;">' . $i . '</a>';
                }
            }
            echo '</div>';
        }
        
        exit;
    }
    
    public function novoProduto(Request $request) {
        // Conexão com o banco
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        // Obter categorias
        $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
        $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        
        // Layout Bootstrap
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.35rem;
            margin: 0.2rem 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .sidebar-brand {
            color: #fff;
            font-weight: bold;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard">
                                <i class="fas fa-fw fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/admin/produtos">
                                <i class="fas fa-fw fa-box"></i>
                                <span>Produtos</span>
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="sidebar-divider">
                    
                    <div class="nav-item">
                        <a class="nav-link" href="/logout">
                            <i class="fas fa-fw fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Novo Produto</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="/admin/produtos" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
                
                <form method="POST" action="/admin/salvar-produto" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Coluna Esquerda -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Informações Básicas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="nome" class="form-label">Nome do Produto *</label>
                                        <input type="text" class="form-control" name="nome" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sku" class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao_curta" class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="descricao_curta" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao_completa" class="form-label">Descrição Completa</label>
                                        <textarea class="form-control" name="descricao_completa" rows="5"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="categoria_id" class="form-label">Categoria</label>
                                        <select class="form-select" name="categoria_id" required>
                                            <option value="">Selecione uma categoria</option>';
                                            foreach ($categorias as $cat) {
                                                echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['nome']) . '</option>';
                                            }
        echo '</select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Imagens do Produto</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="imagens[]" class="form-label">Imagens do Produto</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                        <div class="form-text">Selecione várias imagens para a galeria do produto</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coluna Direita -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Preço e Estoque</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="valor" class="form-label">Preço *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control" name="valor" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="moeda" class="form-label">Moeda</label>
                                        <select class="form-select" name="moeda">
                                            <option value="BRL" selected>BRL</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="peso" class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="peso" placeholder="0.0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="estoque" class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="estoque" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="1" selected>Ativo</option>
                                            <option value="0">Inativo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ativo" class="form-label">Visível no site</label>
                                        <select class="form-select" name="ativo">
                                            <option value="1" selected>Sim</option>
                                            <option value="0">Não</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Produto
                                </button>
                                <a href="/admin/produtos" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
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
    
    public function salvarProduto(Request $request) {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            $pdo->beginTransaction();
            
            // Inserir produto com todos os campos
            $sql = "
                INSERT INTO produtos (
                    nome, sku, descricao_curta, descricao_completa, categoria_id, 
                    valor, moeda, peso, estoque, status, ativo, 
                    created_at, updated_at
                ) VALUES (
                    :nome, :sku, :descricao_curta, :descricao_completa, :categoria_id,
                    :valor, :moeda, :peso, :estoque, :status, :ativo,
                    NOW(), NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nome', $request->getParam('nome'));
            $stmt->bindParam(':sku', $request->getParam('sku'));
            $stmt->bindParam(':descricao_curta', $request->getParam('descricao_curta'));
            $stmt->bindParam(':descricao_completa', $request->getParam('descricao_completa'));
            $stmt->bindParam(':categoria_id', $request->getParam('categoria_id'));
            $stmt->bindParam(':valor', str_replace(',', '.', $request->getParam('valor')));
            $stmt->bindParam(':moeda', $request->getParam('moeda'));
            $stmt->bindParam(':peso', $request->getParam('peso'));
            $stmt->bindParam(':estoque', $request->getParam('estoque'));
            $stmt->bindParam(':status', $request->getParam('status'));
            $stmt->bindParam(':ativo', $request->getParam('ativo'));
            
            $stmt->execute();
            $produtoId = $pdo->lastInsertId();
            
            // Processar upload de imagens (se houver)
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $imagens = $_FILES['imagens'];
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
                
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($imagens['name'] as $key => $name) {
                    if ($imagens['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = time() . '_' . $key . '_' . $name;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($imagens['tmp_name'][$key], $filePath)) {
                            // Inserir no banco
                            $sqlFoto = "
                                INSERT INTO produto_fotos (
                                    produto_id, nome_arquivo, legenda, principal, ordem, created_at
                                ) VALUES (
                                    :produto_id, :nome_arquivo, :legenda, :principal, :ordem, NOW()
                                )
                            ";
                            
                            $stmtFoto = $pdo->prepare($sqlFoto);
                            $stmtFoto->bindParam(':produto_id', $produtoId);
                            $stmtFoto->bindParam(':nome_arquivo', $fileName);
                            $stmtFoto->bindValue(':legenda', '');
                            $stmtFoto->bindValue(':principal', $key === 0 ? 1 : 0); // Primeira imagem como principal
                            $stmtFoto->bindValue(':ordem', $key);
                            $stmtFoto->execute();
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            // Redirecionar para edição
            header('Location: /admin/editar-produto/' . $produtoId);
            exit;
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            
            // Exibir erro com layout Bootstrap
            echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>Erro ao salvar produto</h4>
            <p>' . $e->getMessage() . '</p>
            <a href="/admin/novo-produto" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
</body>
</html>';
            exit;
        }
    }
    
    public function editarProduto(Request $request) {
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$produto) {
            echo 'Produto não encontrado!';
            exit;
        }
        
        echo '<h1>Editar Produto</h1>';
        echo '<form method="POST" action="/admin/atualizar-produto/' . $produtoId . '">';
        echo '<input type="hidden" name="id" value="' . $produtoId . '">';
        echo '<input type="text" name="nome" value="' . htmlspecialchars($produto['nome']) . '" required><br><br>';
        echo '<input type="text" name="sku" value="' . htmlspecialchars($produto['sku']) . '" required><br><br>';
        echo '<button type="submit">Atualizar</button>';
        echo '<a href="/admin/produtos">Cancelar</a>';
        echo '</form>';
        exit;
    }
    
    public function atualizarProduto(Request $request) {
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, sku = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$request->getParam('nome'), $request->getParam('sku'), $produtoId]);
            
            header('Location: /admin/editar-produto/' . $produtoId);
            exit;
        } catch (\Exception $e) {
            echo 'Erro: ' . $e->getMessage();
            exit;
        }
    }
    
    public function excluirProduto(Request $request) {
        error_log('🔍 [ADMIN-EXCLUIR] Método excluirProduto chamado com ID: ' . $request->getParam('id'));
        
        $produtoId = $request->getParam('id');
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            error_log('🔍 [ADMIN-EXCLUIR] Iniciando transação');
            $pdo->beginTransaction();
            
            // Excluir fotos
            error_log('🔍 [ADMIN-EXCLUIR] Excluindo fotos do produto ' . $produtoId);
            $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE produto_id = ?");
            $stmt->execute([$produtoId]);
            
            // Excluir produto
            error_log('🔍 [ADMIN-EXCLUIR] Excluindo produto ' . $produtoId);
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            
            $pdo->commit();
            
            error_log('🔍 [ADMIN-EXCLUIR] Produto excluído com sucesso, redirecionando...');
            
            // Redirecionar para lista
            header('Location: /admin/produtos');
            exit;
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log('❌ [ADMIN-EXCLUIR] Erro: ' . $e->getMessage());
            echo '<div class="alert alert-danger">Erro ao excluir produto: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/produtos" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
}