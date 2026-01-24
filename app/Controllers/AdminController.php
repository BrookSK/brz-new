<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;
use App\Models\Categoria;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        error_log(' [ADMIN-DASHBOARD] Iniciando método dashboard');
        
        // Estatísticas básicas
        $stats = [
            'total_pedidos' => 0,
            'pedidos_por_status' => [],
            'financeiro' => ['faturamento_usd' => 0],
            'total_produtos' => 0,
            'total_usuarios' => 0
        ];
        
        error_log(' [ADMIN-DASHBOARD] Stats criadas');
        
        // Verificar se a view existe
        $viewPath = __DIR__ . '/../Views/admin/dashboard.php';
        error_log(' [ADMIN-DASHBOARD] Caminho da view: ' . $viewPath);
        error_log(' [ADMIN-DASHBOARD] View existe: ' . (file_exists($viewPath) ? 'SIM' : 'NÃO'));
        
        // Verificar se o layout existe
        $layoutPath = __DIR__ . '/../Views/layouts/admin.php';
        error_log(' [ADMIN-DASHBOARD] Caminho do layout: ' . $layoutPath);
        error_log(' [ADMIN-DASHBOARD] Layout existe: ' . (file_exists($layoutPath) ? 'SIM' : 'NÃO'));
        
        // Usar o mesmo sistema de buffer manual dos outros métodos
        error_log(' [ADMIN-DASHBOARD] Iniciando buffer');
        ob_start();
        
        error_log(' [ADMIN-DASHBOARD] Incluindo view');
        include $viewPath;
        
        $content = ob_get_clean();
        error_log(' [ADMIN-DASHBOARD] Buffer capturado, tamanho: ' . strlen($content) . ' bytes');
        
        if (empty($content)) {
            error_log(' [ADMIN-DASHBOARD] ERRO: Content está vazio!');
        } else {
            error_log(' [ADMIN-DASHBOARD] Content capturado com sucesso');
        }
        
        error_log(' [ADMIN-DASHBOARD] Incluindo layout');
        include $layoutPath;
        
        error_log(' [ADMIN-DASHBOARD] Finalizado método dashboard');
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
        
        // Usar buffer manual como no dashboard
        ob_start();
        include __DIR__ . '/../Views/admin/produtos.php';
        $content = ob_get_clean();
        
        // Incluir layout passando a variável $content
        include __DIR__ . '/../Views/layouts/admin.php';
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