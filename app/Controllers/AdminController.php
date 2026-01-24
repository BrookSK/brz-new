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
        echo '<h1>Novo Produto</h1>';
        echo '<form method="POST" action="/admin/salvar-produto" style="max-width: 600px;">';
        echo '<input type="text" name="nome" placeholder="Nome do Produto" required><br><br>';
        echo '<input type="text" name="sku" placeholder="SKU" required><br><br>';
        echo '<button type="submit">Salvar</button>';
        echo '<a href="/admin/produtos">Cancelar</a>';
        echo '</form>';
        exit;
    }
    
    public function salvarProduto(Request $request) {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, sku, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$request->getParam('nome'), $request->getParam('sku')]);
            
            header('Location: /admin/produtos');
            exit;
        } catch (\Exception $e) {
            echo 'Erro: ' . $e->getMessage();
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
}