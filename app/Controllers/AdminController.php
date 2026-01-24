<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;
use App\Models\Categoria;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        // Estatísticas básicas
        $stats = [
            'total_pedidos' => 0,
            'pedidos_por_status' => [],
            'financeiro' => ['faturamento_usd' => 0],
            'total_produtos' => 0,
            'total_usuarios' => 0
        ];
        
        // Iniciar buffer manualmente
        ob_start();
        include __DIR__ . '/../Views/admin/dashboard.php';
        $content = ob_get_clean();
        
        // Incluir layout
        include __DIR__ . '/../Views/layouts/admin.php';
    }
    
    public function produtos(Request $request) {
        $this->authService->requerPermissao('read');
        
        $pagina = $request->getParam('pagina', 1);
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        $status = $request->getParam('status', '');
        $busca = $request->getParam('busca', '');
        $categoria_id = $request->getParam('categoria_id', '');
        
        $produtos = $this->getProdutosComFiltros($busca, $status, $categoria_id, $limite, $offset);
        $total = $this->getTotalProdutos($busca, $status, $categoria_id);
        $totalPaginas = ceil($total / $limite);
        
        // Tratar fotos dos produtos (usando o novo sistema)
        foreach ($produtos as &$produto) {
            $fotos = $this->getImagensProduto($produto['id']);
            if (!empty($fotos)) {
                $produto['foto_principal'] = $fotos[0]['nome_arquivo'];
                $produto['galeria'] = $fotos;
            } else {
                $produto['foto_principal'] = null;
                $produto['galeria'] = [];
            }
        }
        
        // Obter categorias para o filtro
        $categorias = $this->getCategorias();
        
        $this->view('admin/produtos', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'pagina' => $pagina,
            'limite' => $limite,
            'total' => $total,
            'total_paginas' => $totalPaginas,
            'filtro' => $busca,
            'status' => $status,
            'categoria_id' => $categoria_id,
            'busca' => $busca
        ]);
    }
    
    private function getImagensProduto($produtoId) {
        try {
            $stmt = $this->getConnection()->prepare("
                SELECT * FROM produto_fotos 
                WHERE produto_id = :produto_id 
                ORDER BY principal DESC, ordem ASC, created_at ASC
            ");
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->execute();
            
            $fotos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Verificar existência dos arquivos e adicionar URLs completas
            foreach ($fotos as &$foto) {
                if (!empty($foto['nome_arquivo']) && strpos($foto['nome_arquivo'], '/uploads/') === 0) {
                    $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . $foto['nome_arquivo'];
                    $foto['arquivo_existe'] = file_exists($caminhoFisico);
                    $foto['url_completa'] = 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'];
                    
                    if (!$foto['arquivo_existe']) {
                        error_log('❌ [ADMIN-PRODUTOS] Arquivo não encontrado: ' . $caminhoFisico);
                    }
                }
            }
            
            return $fotos;
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-PRODUTOS] Erro ao buscar imagens: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getProdutosComFiltros($busca, $status, $categoria_id, $limite, $offset) {
        try {
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
            
            $stmt = $this->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-PRODUTOS] Erro em getProdutosComFiltros: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getTotalProdutos($busca, $status, $categoria_id) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produtos WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (nome LIKE :busca OR sku LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            
            if (!empty($status)) {
                $sql .= " AND ativo = :status";
                $params[':status'] = $status === 'ativo' ? 1 : 0;
            }
            
            if (!empty($categoria_id)) {
                $sql .= " AND categoria_id = :categoria_id";
                $params[':categoria_id'] = $categoria_id;
            }
            
            $stmt = $this->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-PRODUTOS] Erro em getTotalProdutos: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function getCategorias() {
        try {
            $stmt = $this->getConnection()->prepare("SELECT * FROM categorias ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-PRODUTOS] Erro ao buscar categorias: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getConnection() {
        // Conexão direta com o banco
        return new \PDO('mysql:host=localhost;dbname=novobr_brazilianashop', 'root', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }
}