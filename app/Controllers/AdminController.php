<?php
namespace App\Controllers;

use App\Core\Request;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        echo '<h1>Dashboard do Controller</h1><p>Controller funcionando!</p>';
        exit;
    }
    
    public function produtos(Request $request) {
        echo '<h1>Produtos do Controller</h1>';
        
        // Adicionando teste básico de banco com credenciais corretas
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo '<p>Total de produtos: ' . $result['total'] . '</p>';
        } catch (\Exception $e) {
            echo '<p>Erro no banco: ' . $e->getMessage() . '</p>';
        }
        
        exit;
    }
}