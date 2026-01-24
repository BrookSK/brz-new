<?php
// Arquivo de teste para verificar erro 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Teste de conexão com o banco...<br>";

try {
    // Testar conexão básica
    $pdo = new PDO('mysql:host=localhost;dbname=novobr_brazilianashop', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexão OK<br>";
    
    // Testar tabela produtos
    $stmt = $pdo->query("DESCRIBE produtos");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Tabela produtos OK - Colunas: " . implode(', ', $colunas) . "<br>";
    
    // Testar consulta simples
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✅ Total de produtos: $total<br>";
    
    // Testar tabela categorias
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categorias");
    $totalCategorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✅ Total de categorias: $totalCategorias<br>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

echo "<br>Teste concluído.";
?>
