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
        
        // Listar produtos com galeria
        $stmt = $pdo->query("
            SELECT p.*, c.nome as categoria_nome 
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            ORDER BY p.nome ASC
        ");
        
        $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        echo '<h1>Produtos (' . count($produtos) . ' encontrados)</h1>';
        
        foreach ($produtos as $produto) {
            echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
            echo '<h3>' . htmlspecialchars($produto['nome']) . '</h3>';
            echo '<p><strong>SKU:</strong> ' . htmlspecialchars($produto['sku']) . '</p>';
            echo '<p><strong>Categoria:</strong> ' . htmlspecialchars($produto['categoria_nome'] ?? 'N/A') . '</p>';
            echo '<p><strong>Preço:</strong> R$ ' . number_format($produto['valor'], 2, ',', '.') . '</p>';
            echo '<p><strong>Status:</strong> ' . ($produto['ativo'] ? 'Ativo' : 'Inativo') . '</p>';
            
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
                    echo '<img src="' . $url . '" alt="' . htmlspecialchars($foto['legenda'] ?? '') . '" style="width: 100px; height: 100px; margin: 2px; border: 1px solid #ddd;">';
                    if ($foto['principal']) {
                        echo '<span style="color: green;">[PRINCIPAL]</span>';
                    }
                    echo '<br>';
                }
            } else {
                echo '<p><strong>Galeria:</strong> Nenhuma imagem</p>';
            }
            
            echo '</div>';
        }
        
        exit;
    }
}