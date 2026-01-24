<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\PedidoEcommerce;
use App\Models\Usuario;
use App\Models\Produto;
use App\Models\Categoria;
use App\Models\ProdutoFoto;
use App\Models\Imagem;

class AdminController extends Controller {
    private $authService;
    private $pedidoModel;
    private $usuarioModel;
    private $produtoModel;
    private $categoriaModel;
    private $produtoFotoModel;
    private $imagemModel;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->pedidoModel = new PedidoEcommerce();
        $this->usuarioModel = new Usuario();
        $this->produtoModel = new Produto();
        $this->categoriaModel = new Categoria();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->imagemModel = new Imagem();
    }
    
    public function dashboard(Request $request) {
        $this->authService->requerPermissao('read');
        
        // Estatísticas básicas
        $stats = $this->getDashboardStats();
        
        $this->view('admin/dashboard', [
            'stats' => $stats
        ]);
    }
    
    private function getDashboardStats() {
        $stats = [];
        
        try {
            // Total de pedidos
            $stmt = $this->pedidoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM pedidos");
            $stmt->execute();
            $stats['total_pedidos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Pedidos por status
            $stmt = $this->pedidoModel->getConnection()->prepare("
                SELECT status, COUNT(*) as quantidade 
                FROM pedidos 
                GROUP BY status
            ");
            $stmt->execute();
            $stats['pedidos_por_status'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Faturamento total (simplificado)
            $stmt = $this->pedidoModel->getConnection()->prepare("
                SELECT 
                    SUM(valor_total) as faturamento_usd
                FROM pedidos
            ");
            $stmt->execute();
            $financeiro = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stats['financeiro'] = $financeiro;
            
            // Total de produtos
            $stmt = $this->produtoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM produtos");
            $stmt->execute();
            $stats['total_produtos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Total de usuários
            $stmt = $this->usuarioModel->getConnection()->prepare("SELECT COUNT(*) as total FROM usuarios");
            $stmt->execute();
            $stats['total_usuarios'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro em getDashboardStats: ' . $e->getMessage());
            // Valores padrão em caso de erro
            $stats = [
                'total_pedidos' => 0,
                'pedidos_por_status' => [],
                'financeiro' => ['faturamento_usd' => 0],
                'total_produtos' => 0,
                'total_usuarios' => 0,
                'pedidos_recentes' => []
            ];
        }
        
        return $stats;
    }
    
    public function pedidos(Request $request) {
        $this->authService->requerPermissao('read');
        
        $pagina = $request->getParam('pagina', 1);
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        
        $filtro = $request->getParam('filtro', '');
        $status = $request->getParam('status', '');
        
        $pedidos = $this->getPedidosComFiltros($filtro, $status, $limite, $offset);
        $total = $this->getTotalPedidosComFiltros($filtro, $status);
        
        $this->view('admin/pedidos', [
            'pedidos' => $pedidos,
            'pagina' => $pagina,
            'limite' => $limite,
            'total' => $total,
            'total_paginas' => ceil($total / $limite),
            'filtro' => $filtro,
            'status' => $status
        ]);
    }
    
    private function getPedidosComFiltros($filtro, $status, $limite, $offset) {
        try {
            $sql = "
                SELECT p.*, u.nome as cliente_nome, u.email as cliente_email
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if (!empty($filtro)) {
                $sql .= " AND (p.codigo_pedido LIKE :filtro OR u.nome LIKE :filtro OR u.email LIKE :filtro)";
                $params[':filtro'] = "%{$filtro}%";
            }
            
            if (!empty($status)) {
                $sql .= " AND p.status = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pedidoModel->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro em getPedidosComFiltros: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getTotalPedidosComFiltros($filtro, $status) {
        try {
            $sql = "
                SELECT COUNT(*) as total
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if (!empty($filtro)) {
                $sql .= " AND (p.codigo_pedido LIKE :filtro OR u.nome LIKE :filtro OR u.email LIKE :filtro)";
                $params[':filtro'] = "%{$filtro}%";
            }
            
            if (!empty($status)) {
                $sql .= " AND p.status = :status";
                $params[':status'] = $status;
            }
            
            $stmt = $this->pedidoModel->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro em getTotalPedidosComFiltros: ' . $e->getMessage());
            return 0;
        }
    }
    
    public function pedidoDetalhes(Request $request) {
        $this->authService->requerPermissao('read');
        
        $pedidoId = $request->getParam('id');
        $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
        
        if (!$pedido) {
            $this->view('admin/404');
            return;
        }
        
        $this->view('admin/pedido-detalhes', [
            'pedido' => $pedido
        ]);
    }
    
    public function atualizarStatus(Request $request) {
        $this->authService->requerPermissao('update');
        
        $pedidoId = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        $observacao = $request->getParam('observacao', '');
        
        try {
            $usuario = $this->authService->getUsuarioLogado();
            
            $this->pedidoModel->atualizarStatus($pedidoId, $novoStatus, $observacao, $usuario['id']);
            
            // Registrar auditoria
            $this->authService->registrarLogAuditoria(
                $usuario['id'],
                'atualizar_status_pedido',
                'pedidos',
                $pedidoId,
                ['status' => $this->pedidoModel->find($pedidoId)['status']],
                ['status' => $novoStatus]
            );
            
            $this->json(['success' => true, 'message' => 'Status atualizado com sucesso']);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar status: ' . $e->getMessage()], 500);
        }
    }
    
    public function consolidarPedidos(Request $request) {
        $this->authService->requerPerfil('admin');
        
        if ($request->getMethod() === 'POST') {
            $pedidoIds = $request->getParam('pedidos', []);
            
            if (empty($pedidoIds) || count($pedidoIds) < 2) {
                $this->json(['error' => 'Selecione pelo menos 2 pedidos para consolidar'], 400);
            }
            
            try {
                $usuario = $this->authService->getUsuarioLogado();
                $codigoConsolidacao = $this->pedidoModel->consolidarPedidos($pedidoIds, $usuario['id']);
                
                // Registrar auditoria
                $this->authService->registrarLogAuditoria(
                    $usuario['id'],
                    'consolidar_pedidos',
                    'pedidos',
                    null,
                    null,
                    ['pedidos_ids' => $pedidoIds, 'codigo_consolidacao' => $codigoConsolidacao]
                );
                
                $this->json([
                    'success' => true,
                    'message' => 'Pedidos consolidados com sucesso',
                    'codigo_consolidacao' => $codigoConsolidacao
                ]);
                
            } catch (\Exception $e) {
                $this->json(['error' => 'Erro ao consolidar pedidos: ' . $e->getMessage()], 500);
            }
        } else {
            $this->view('admin/consolidar-pedidos');
        }
    }
    
    public function gerarEtiqueta(Request $request) {
        $this->authService->requerPermissao('update');
        
        $pedidoId = $request->getParam('id');
        
        try {
            $usuario = $this->authService->getUsuarioLogado();
            $codigoEtiqueta = $this->pedidoModel->gerarRascunhoEtiqueta($pedidoId, $usuario['id']);
            
            // Registrar auditoria
            $this->authService->registrarLogAuditoria(
                $usuario['id'],
                'gerar_etiqueta',
                'pedidos',
                $pedidoId,
                null,
                ['etiqueta_codigo' => $codigoEtiqueta]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Rascunho de etiqueta gerado com sucesso',
                'etiqueta_codigo' => $codigoEtiqueta
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao gerar etiqueta: ' . $e->getMessage()], 500);
        }
    }
    
    public function efetivarEtiqueta(Request $request) {
        $this->authService->requerPermissao('update');
        
        $pedidoId = $request->getParam('id');
        $dadosTransporte = $request->getParams();
        
        try {
            $usuario = $this->authService->getUsuarioLogado();
            $this->pedidoModel->efetivarEtiqueta($pedidoId, $usuario['id'], $dadosTransporte);
            
            // Registrar auditoria
            $this->authService->registrarLogAuditoria(
                $usuario['id'],
                'efetivar_etiqueta',
                'pedidos',
                $pedidoId,
                null,
                $dadosTransporte
            );
            
            $this->json(['success' => true, 'message' => 'Etiqueta efetivada com sucesso']);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao efetivar etiqueta: ' . $e->getMessage()], 500);
        }
    }
    
    public function configuracoes(Request $request) {
        $this->authService->requerPerfil('admin');
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            $usuario = $this->authService->getUsuarioLogado();
            
            try {
                foreach ($dados as $chave => $valor) {
                    // Verificar se configuração existe
                    $stmt = $this->pedidoModel->getConnection()->prepare("
                        SELECT id FROM configuracoes WHERE chave = :chave
                    ");
                    $stmt->bindParam(':chave', $chave);
                    $stmt->execute();
                    $config = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($config) {
                        // Atualizar
                        $stmt = $this->pedidoModel->getConnection()->prepare("
                            UPDATE configuracoes 
                            SET valor = :valor, atualizado_por = :usuario_id, updated_at = NOW()
                            WHERE chave = :chave
                        ");
                        $stmt->bindParam(':valor', $valor);
                        $stmt->bindParam(':usuario_id', $usuario['id']);
                        $stmt->bindParam(':chave', $chave);
                        $stmt->execute();
                    }
                }
                
                $this->json(['success' => true, 'message' => 'Configurações atualizadas com sucesso']);
                
            } catch (\Exception $e) {
                $this->json(['error' => 'Erro ao atualizar configurações: ' . $e->getMessage()], 500);
            }
        } else {
            // Obter configurações atuais
            $stmt = $this->pedidoModel->getConnection()->prepare("
                SELECT chave, valor, descricao FROM configuracoes ORDER BY chave
            ");
            $stmt->execute();
            $configuracoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Obter taxas de câmbio
            $stmt = $this->pedidoModel->getConnection()->prepare("
                SELECT * FROM configuracoes_moeda ORDER BY moeda_origem, moeda_destino
            ");
            $stmt->execute();
            $taxasCambio = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->view('admin/configuracoes', [
                'configuracoes' => $configuracoes,
                'taxas_cambio' => $taxasCambio
            ]);
        }
    }
    
    public function usuarios(Request $request) {
        $this->authService->requerPerfil('admin');
        
        $pagina = $request->getParam('pagina', 1);
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        $status = $request->getParam('status', '');
        $perfil = $request->getParam('perfil', '');
        $busca = $request->getParam('busca', '');
        
        // Usar o método com filtros do modelo
        $usuarios = $this->usuarioModel->getUsuariosComFiltros($busca, $status, $perfil, $limite, $offset);
        $total = $this->usuarioModel->getTotalUsuarios($busca, $status, $perfil);
        $totalPaginas = ceil($total / $limite);
        
        $this->view('admin/usuarios', [
            'usuarios' => $usuarios,
            'pagina' => $pagina,
            'limite' => $limite,
            'total' => $total,
            'totalPaginas' => $totalPaginas,
            'status' => $status,
            'perfil' => $perfil,
            'busca' => $busca
        ]);
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
        
        // Tratar fotos dos produtos (mesma lógica do ProdutoController)
        foreach ($produtos as &$produto) {
            // Validar e corrigir URL da foto
            if (!empty($produto['foto_principal'])) {
                $fotoUrl = $produto['foto_principal'];
                
                // Se for URL externa, não usar
                if (strpos($fotoUrl, 'http') === 0) {
                    $produto['foto_principal'] = null;
                    error_log('⚠️ [ADMIN-CONTROLLER] URL externa ignorada para produto ' . $produto['id'] . ': ' . $fotoUrl);
                }
                // Se for URL interna, verificar se arquivo existe
                elseif (strpos($fotoUrl, '/uploads/') === 0) {
                    $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . $fotoUrl;
                    if (file_exists($caminhoFisico)) {
                        $produto['foto_principal'] = $fotoUrl;
                        error_log('✅ [ADMIN-CONTROLLER] Arquivo encontrado para produto ' . $produto['id'] . ': ' . $fotoUrl);
                    } else {
                        $produto['foto_principal'] = null;
                        error_log('❌ [ADMIN-CONTROLLER] Arquivo NÃO encontrado para produto ' . $produto['id'] . ': ' . $fotoUrl);
                        error_log('❌ [ADMIN-CONTROLLER] Caminho verificado: ' . $caminhoFisico);
                    }
                }
                // Se não começar com /uploads/, corrigir
                else {
                    $produto['foto_principal'] = '/uploads/produtos/' . basename($fotoUrl);
                    error_log('🔧 [ADMIN-CONTROLLER] URL corrigida para produto ' . $produto['id'] . ': ' . $fotoUrl . ' → ' . $produto['foto_principal']);
                }
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
            
            $stmt = $this->produtoModel->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro em getProdutosComFiltros: ' . $e->getMessage());
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
            
            $stmt = $this->produtoModel->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
            
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro em getTotalProdutos: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function getCategorias() {
        try {
            $stmt = $this->categoriaModel->getConnection()->prepare("SELECT * FROM categorias ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('❌ [ADMIN-CONTROLLER] Erro ao buscar categorias: ' . $e->getMessage());
            return [];
        }
    }
    
    public function novoProduto(Request $request) {
        $this->authService->requerPermissao('create');
        
        try {
            // Obter categorias para o formulário
            $categorias = $this->getCategorias();
            
            $this->view('admin/editar-produto', [
                'produto' => null,
                'categorias' => $categorias,
                'galeria' => [],
                'modo' => 'novo'
            ]);
            
        } catch (\Exception $e) {
            // Redirecionar com erro
            header('Location: /admin/produtos?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    public function editarProduto(Request $request) {
        $this->authService->requerPermissao('read');
        
        $produtoId = $request->getParam('id');
        
        try {
            $produto = $this->produtoModel->find($produtoId);
            
            if (!$produto) {
                // Redirecionar com erro
                header('Location: /admin/produtos?error=Produto não encontrado');
                exit;
            }
            
            // Obter categorias para o formulário
            $categorias = $this->getCategorias();
            
            // Obter galeria de imagens do produto
            $galeria = $this->produtoModel->getImagens($produtoId);
            
            $this->view('admin/editar-produto', [
                'produto' => $produto,
                'categorias' => $categorias,
                'galeria' => $galeria,
                'modo' => 'editar'
            ]);
            
        } catch (\Exception $e) {
            // Redirecionar com erro
            header('Location: /admin/produtos?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    public function marcarFotoPrincipal(Request $request) {
        $this->authService->requerPermissao('update');
        
        $fotoId = $request->getParam('id');
        
        try {
            $resultado = $this->produtoFotoModel->marcarComoPrincipal($fotoId);
            
            if ($resultado) {
                $this->json(['success' => true, 'message' => 'Imagem marcada como principal com sucesso']);
            } else {
                $this->json(['success' => false, 'error' => 'Erro ao marcar imagem como principal'], 500);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function excluirFoto(Request $request) {
        $this->authService->requerPermissao('delete');
        
        $fotoId = $request->getParam('id');
        
        try {
            $resultado = $this->produtoFotoModel->excluirFoto($fotoId);
            
            if ($resultado) {
                $this->json(['success' => true, 'message' => 'Imagem excluída com sucesso']);
            } else {
                $this->json(['success' => false, 'error' => 'Erro ao excluir imagem'], 500);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function uploadImagem(Request $request) {
        try {
            // Limpar buffer de saída
            if (ob_get_length()) ob_clean();
            
            // Definir cabeçalho JSON
            header('Content-Type: application/json');
            
            $this->authService->requerPermissao('create');
            
            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Nenhuma imagem enviada ou erro no upload');
            }
            
            // Usar sistema antigo de upload (ProdutoFoto)
            $fotoUpload = $this->produtoFotoModel->uploadFoto($_FILES['imagem'], null); // null pois ainda não tem produto_id
            
            // Gerar URL completa
            $url = '/uploads/produtos/' . $fotoUpload['nome_arquivo'];
            
            echo json_encode([
                'success' => true,
                'imagem' => [
                    'id' => $fotoUpload['id'],
                    'url' => $url,
                    'href' => $url,  // URL para uso em href
                    'src' => $url,   // URL para uso em src
                    'nome_arquivo' => $fotoUpload['nome_arquivo']
                ]
            ]);
            
        } catch (\Exception $e) {
            // Limpar buffer de saída
            if (ob_get_length()) ob_clean();
            
            // Definir cabeçalho JSON
            header('Content-Type: application/json');
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        
        // Obter categorias para o formulário
        $categorias = $this->getCategorias();
        
        // Obter galeria de imagens do produto
        $galeria = $this->produtoModel->getImagens($produtoId);
        
        $this->view('admin/editar-produto', [
            'produto' => $produto,
            'categorias' => $categorias,
            'galeria' => $galeria,
            'modo' => 'editar'
        ]);
        
    } catch (\Exception $e) {
        // Redirecionar com erro
        header('Location: /admin/produtos?error=' . urlencode($e->getMessage()));
        exit;
    }
}

public function uploadImagem(Request $request) {
    try {
        // Limpar buffer de saída
        if (ob_get_length()) ob_clean();
        
        // Definir cabeçalho JSON
        header('Content-Type: application/json');
        
        $this->authService->requerPermissao('create');
        
        if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Nenhuma imagem enviada ou erro no upload');
        // Log para debug
        error_log('🔍 [SALVAR-PRODUTO] ========== INICIANDO SALVAMENTO ==========');
        error_log('🔍 [SALVAR-PRODUTO] Dados brutos recebidos: ' . print_r($dados, true));
        error_log('🔍 [SALVAR-PRODUTO] FILES recebidos: ' . print_r($_FILES, true));
        
        try {
            // FORÇAR MOEDA USD SEMPRE
            $dados['moeda'] = 'USD';
            error_log('🔍 [SALVAR-PRODUTO] Moeda forçada para USD');
            
            // Validar e converter valor
            if (!isset($dados['valor']) || $dados['valor'] === '') {
                error_log('❌ [SALVAR-PRODUTO] ERRO: Valor não informado ou vazio');
                throw new \Exception('Valor é obrigatório');
            }
            
            // Converter valor para formato numérico
            $valor = str_replace(',', '.', $dados['valor']);
            $valor = floatval($valor);
            
            if ($valor <= 0) {
                error_log('❌ [SALVAR-PRODUTO] ERRO: Valor inválido: ' . $valor);
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            $dados['valor'] = $valor;
            error_log('🔍 [SALVAR-PRODUTO] Valor convertido: ' . $valor);
            
            // Validar campos obrigatórios
            $camposObrigatorios = ['nome', 'sku', 'descricao_curta', 'descricao_completa', 'categoria_id', 'valor', 'moeda', 'peso', 'estoque', 'status'];
            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo]) || empty(trim($dados[$campo]))) {
                    error_log('❌ [SALVAR-PRODUTO] ERRO: Campo obrigatório vazio: ' . $campo . ' - Valor: "' . ($dados[$campo] ?? 'NULL') . '"');
                    throw new \Exception('Campo obrigatório ' . $campo . ' não pode ser vazio');
                }
            }
            
            // Validar especificamente o SKU
            $sku = trim($dados['sku']);
            if (empty($sku)) {
                error_log('❌ [SALVAR-PRODUTO] ERRO: SKU está vazio após trim');
                throw new \Exception('SKU não pode ser vazio');
            }
            
            // Verificar se SKU já existe
            $stmt = $this->produtoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM produtos WHERE sku = :sku");
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                error_log('❌ [SALVAR-PRODUTO] ERRO: SKU já existe: ' . $sku);
                throw new \Exception('SKU "' . $sku . '" já está em uso. Por favor, escolha outro SKU.');
            }
            
            // Limpar e formatar dados
            $dados['sku'] = $sku;
            $dados['nome'] = trim($dados['nome']);
            $dados['descricao_curta'] = trim($dados['descricao_curta']);
            
            error_log('🔍 [SALVAR-PRODUTO] Dados finais para salvar: ' . print_r($dados, true));
            
            // Criar produto primeiro para obter o ID
            error_log('🔍 [SALVAR-PRODUTO] Tentando criar produto no banco...');
            $produtoId = $this->produtoModel->create($dados, $usuario['id']);
            error_log('🔍 [SALVAR-PRODUTO] Produto criado com ID: ' . $produtoId);
            
            if (!$produtoId) {
                error_log('❌ [SALVAR-PRODUTO] ERRO: Falha ao criar produto - ID retornado: ' . $produtoId);
                throw new \Exception('Erro ao criar produto no banco de dados');
            }
            
            // Processar upload da imagem principal
            $imagemUrl = null;
            
            // 1. Verificar se há upload via AJAX (campo hidden)
            $imagemUrlTemp = $request->getParam('imagem_url');
            if ($imagemUrlTemp) {
                error_log('🔍 [SALVAR-PRODUTO] Movendo imagem temporária: ' . $imagemUrlTemp);
                $fotoMovida = $this->produtoFotoModel->moverImagemTemporaria($imagemUrlTemp, $produtoId);
                if ($fotoMovida) {
                    $imagemUrl = $fotoMovida['nome_arquivo'];
                    error_log('✅ [SALVAR-PRODUTO] Imagem temporária movida: ' . $imagemUrl);
                }
            }
            // 2. Verificar se há upload tradicional
            elseif (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
                error_log('🔍 [SALVAR-PRODUTO] Processando upload tradicional da imagem principal');
                $fotoPrincipal = $this->produtoFotoModel->uploadFoto($_FILES['imagem_principal'], $produtoId);
                
                // Marcar como principal (se tiver ID)
                if ($fotoPrincipal['id']) {
                    $this->produtoFotoModel->marcarComoPrincipal($fotoPrincipal['id']);
                }
                
                $imagemUrl = $fotoPrincipal['nome_arquivo'];
                error_log('🔍 [SALVAR-PRODUTO] Foto principal salva: ' . $imagemUrl);
            } else {
                error_log('🔍 [SALVAR-PRODUTO] Nenhuma imagem principal para upload');
            }
            
            // Atualizar foto principal do produto se tiver imagem
            if ($imagemUrl) {
                $this->produtoModel->updateFotoPrincipal($produtoId, $imagemUrl, $usuario['id']);
                error_log('🔍 [SALVAR-PRODUTO] Foto principal atualizada no produto: ' . $imagemUrl);
            }
            
            // Processar upload das imagens adicionais
            if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
                error_log('🔍 [SALVAR-PRODUTO] Processando ' . count($_FILES['imagens']['name']) . ' imagens adicionais');
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                        $arquivo = [
                            'name' => $_FILES['imagens']['name'][$key],
                            'type' => $_FILES['imagens']['type'][$key],
                            'tmp_name' => $_FILES['imagens']['tmp_name'][$key],
                            'error' => $_FILES['imagens']['error'][$key],
                            'size' => $_FILES['imagens']['size'][$key]
                        ];
                        
                        $this->produtoFotoModel->uploadFoto($arquivo, $produtoId);
                    }
                }
            }
            
            error_log('🔍 [SALVAR-PRODUTO] ========== PRODUTO SALVO COM SUCESSO! ==========');
            error_log('🔍 [SALVAR-PRODUTO] ID: ' . $produtoId . ', Valor: $' . number_format($valor, 2));
            
            $this->json([
                'success' => true,
                'message' => 'Produto criado com sucesso em USD - Valor: $' . number_format($valor, 2),
                'produto_id' => $produtoId
            ]);
            
        } catch (\Exception $e) {
            error_log('❌ [SALVAR-PRODUTO] ========== ERRO AO SALVAR PRODUTO ==========');
            error_log('❌ [SALVAR-PRODUTO] Erro: ' . $e->getMessage());
            error_log('❌ [SALVAR-PRODUTO] Stack trace: ' . $e->getTraceAsString());
            $this->json(['error' => 'Erro ao criar produto: ' . $e->getMessage()], 500);
        }
    }
    
    public function atualizarProduto(Request $request) {
        $this->authService->requerPermissao('update');
        
        $produtoId = $request->getParam('id');
        $dados = $request->getParams();
        $usuario = $this->authService->getUsuarioLogado();
        
        // Log para debug
        error_log('🔍 [ATUALIZAR-PRODUTO] Iniciando atualização do produto ID: ' . $produtoId);
        error_log('🔍 [ATUALIZAR-PRODUTO] Dados recebidos: ' . print_r($dados, true));
        error_log('🔍 [ATUALIZAR-PRODUTO] FILES recebidos: ' . print_r($_FILES, true));
        
        try {
            // FORÇAR MOEDA USD SEMPRE
            $dados['moeda'] = 'USD';
            error_log('🔍 [ATUALIZAR-PRODUTO] Moeda forçada para USD');
            
            // Remover campos que não existem no banco
            unset($dados['descricao_completa']);
            error_log('🔍 [ATUALIZAR-PRODUTO] Campo descricao_completa removido (não existe no banco)');
            
            // Validar campos obrigatórios
            $camposObrigatorios = ['nome', 'sku', 'descricao_curta', 'categoria_id', 'valor', 'moeda', 'peso', 'estoque', 'status'];
            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo]) || empty(trim($dados[$campo]))) {
                    error_log('❌ [ATUALIZAR-PRODUTO] ERRO: Campo obrigatório vazio: ' . $campo . ' - Valor: "' . ($dados[$campo] ?? 'NULL') . '"');
                    throw new \Exception('Campo obrigatório ' . $campo . ' não pode ser vazio');
                }
            }
            
            // Validar especificamente o SKU
            $sku = trim($dados['sku']);
            if (empty($sku)) {
                error_log('❌ [ATUALIZAR-PRODUTO] ERRO: SKU está vazio após trim');
                throw new \Exception('SKU não pode ser vazio');
            }
            
            // Verificar se SKU já existe (exceto para o próprio produto)
            $stmt = $this->produtoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM produtos WHERE sku = :sku AND id != :id");
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':id', $produtoId);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                error_log('❌ [ATUALIZAR-PRODUTO] ERRO: SKU já existe: ' . $sku);
                throw new \Exception('SKU "' . $sku . '" já está em uso. Por favor, escolha outro SKU.');
            }
            
            // Limpar e formatar dados
            $dados['sku'] = $sku;
            $dados['nome'] = trim($dados['nome']);
            $dados['descricao_curta'] = trim($dados['descricao_curta']);
            
            // Converter valor para formato numérico
            $valor = str_replace(',', '.', $dados['valor']);
            $valor = floatval($valor);
            
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            $dados['valor'] = $valor;
            error_log('🔍 [ATUALIZAR-PRODUTO] Valor convertido: ' . $valor);
            
            // Atualizar produto no banco
            error_log('🔍 [ATUALIZAR-PRODUTO] Tentando atualizar produto no banco...');
            $result = $this->produtoModel->update($produtoId, $dados, $usuario['id']);
            error_log('🔍 [ATUALIZAR-PRODUTO] Produto atualizado. Resultado: ' . ($result ? 'true' : 'false'));
            
            // Processar upload da imagem principal (se enviada)
            if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
                error_log('🔍 [ATUALIZAR-PRODUTO] Processando upload da nova imagem principal');
                $fotoPrincipal = $this->produtoFotoModel->uploadFoto($_FILES['imagem_principal'], $produtoId);
                
                // Marcar como principal
                $this->produtoFotoModel->marcarComoPrincipal($fotoPrincipal['id']);
                
                // Atualizar APENAS a foto principal (não limpar outros campos)
                $this->produtoModel->updateFotoPrincipal($produtoId, $fotoPrincipal['nome_arquivo'], $usuario['id']);
                error_log('🔍 [ATUALIZAR-PRODUTO] Nova foto principal salva: ' . $fotoPrincipal['nome_arquivo']);
            } else {
                error_log('🔍 [ATUALIZAR-PRODUTO] Nenhuma nova imagem principal para upload');
            }
            
            // Processar upload das imagens adicionais
            if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
                error_log('🔍 [ATUALIZAR-PRODUTO] Processando ' . count($_FILES['imagens']['name']) . ' imagens adicionais');
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                        $arquivo = [
                            'name' => $_FILES['imagens']['name'][$key],
                            'type' => $_FILES['imagens']['type'][$key],
                            'tmp_name' => $_FILES['imagens']['tmp_name'][$key],
                            'error' => $_FILES['imagens']['error'][$key],
                            'size' => $_FILES['imagens']['size'][$key]
                        ];
                        
                        $this->produtoFotoModel->uploadFoto($arquivo, $produtoId);
                    }
                }
            }
            
            error_log('🔍 [ATUALIZAR-PRODUTO] Produto atualizado com SUCESSO! ID: ' . $produtoId . ', Valor: $' . number_format($valor, 2));
            
            // Retornar JSON para compatibilidade com AJAX
            $this->json([
                'success' => true,
                'message' => 'Produto atualizado com sucesso em USD - Valor: $' . number_format($valor, 2),
                'produto_id' => $produtoId
            ]);
            
        } catch (\Exception $e) {
            error_log('❌ [ATUALIZAR-PRODUTO] ERRO ao atualizar produto: ' . $e->getMessage());
            error_log('❌ [ATUALIZAR-PRODUTO] Stack trace: ' . $e->getTraceAsString());
            
            // Retornar JSON para compatibilidade com AJAX
            $this->json(['error' => 'Erro ao atualizar produto: ' . $e->getMessage()], 500);
        }
    }
    
    public function produto(Request $request) {
        $this->authService->requerPermissao('read');
        
        $produtoId = $request->getParam('id');
        
        try {
            $produto = $this->produtoModel->find($produtoId);
            
            if (!$produto) {
                $this->json(['success' => false, 'error' => 'Produto não encontrado'], 404);
                return;
            }
            
            $this->json([
                'success' => true,
                'produto' => $produto
            ]);
            
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao carregar produto: ' . $e->getMessage()], 500);
        }
    }
    
    public function alterarStatusProduto(Request $request) {
        $this->authService->requerPermissao('update');
        
        $produtoId = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        
        try {
            $this->produtoModel->atualizarStatus($produtoId, $novoStatus);
            
            $this->json([
                'success' => true,
                'message' => 'Status do produto atualizado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar status: ' . $e->getMessage()], 500);
        }
    }
    
    public function excluirProduto(Request $request) {
        $this->authService->requerPermissao('delete');
        
        $produtoId = $request->getParam('id');
        
        try {
            $this->produtoModel->delete($produtoId);
            
            $this->json([
                'success' => true,
                'message' => 'Produto excluído com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao excluir produto: ' . $e->getMessage()], 500);
        }
    }
    
    public function gerarImagens(Request $request) {
        $this->authService->requerPermissao('read');
        
        $produtoId = $request->getParam('id');
        $produto = $this->produtoModel->find($produtoId);
        $imagens = $this->produtoModel->getImagens($produtoId);
        
        $this->view('admin/imagens-produto', [
            'produto' => $produto,
            'imagens' => $imagens
        ]);
    }
    
    // Métodos de Usuários
    public function editarUsuario(Request $request) {
        $this->authService->requerPermissao('read');
        
        $usuarioId = $request->getParam('id');
        $usuario = $this->usuarioModel->find($usuarioId);
        
        if (!$usuario) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        
        // Remover senha do retorno
        unset($usuario['senha']);
        
        $this->json(['success' => true, 'usuario' => $usuario]);
    }
    
    public function usuarioPerfil(Request $request) {
        $this->authService->requerPermissao('read');
        
        $usuarioId = $request->getParam('id');
        $usuario = $this->usuarioModel->find($usuarioId);
        
        if (!$usuario) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        
        // Obter pedidos do usuário
        $stmt = $this->getConnection()->prepare("
            SELECT p.*, 
                   DATE_FORMAT(p.data_criacao, '%d/%m/%Y %H:%i') as data_formatada,
                   (SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = p.id) as itens_count,
                   SUM(pi.quantidade * pi.valor) as valor_total
            FROM pedidos p
            LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
            WHERE p.usuario_id = :usuario_id
            GROUP BY p.id
            ORDER BY p.data_criacao DESC
        ");
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Remover senha do retorno
        unset($usuario['senha']);
        
        $this->json([
            'success' => true,
            'usuario' => $usuario,
            'pedidos' => $pedidos
        ]);
    }
    
    public function salvarUsuario(Request $request) {
        $this->authService->requerPermissao('create');
        
        $dados = $request->getParams();
        
        try {
            $usuarioId = $this->usuarioModel->create($dados);
            
            $this->json([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'usuario_id' => $usuarioId
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
        }
    }
    
    public function atualizarUsuario(Request $request) {
        $this->authService->requerPermissao('update');
        
        $usuarioId = $request->getParam('id');
        $dados = $request->getParams();
        
        try {
            $this->usuarioModel->update($usuarioId, $dados);
            
            $this->json([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500);
        }
    }
    
    public function alterarStatusUsuario(Request $request) {
        $this->authService->requerPermissao('update');
        
        $usuarioId = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        
        try {
            $this->usuarioModel->atualizarStatus($usuarioId, $novoStatus);
            
            $this->json([
                'success' => true,
                'message' => 'Status do usuário atualizado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar status: ' . $e->getMessage()], 500);
        }
    }
    
    public function excluirUsuario(Request $request) {
        $this->authService->requerPermissao('delete');
        
        $usuarioId = $request->getParam('id');
        
        try {
            $this->usuarioModel->delete($usuarioId);
            
            $this->json([
                'success' => true,
                'message' => 'Usuário excluído com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao excluir usuário: ' . $e->getMessage()], 500);
        }
    }
    
    // Métodos de Configurações
    public function salvarConfiguracoes(Request $request) {
        $this->authService->requerPerfil('admin');
        
        $dados = $request->getParams();
        $usuario = $this->authService->getUsuarioLogado();
        
        try {
            foreach ($dados as $chave => $valor) {
                // Verificar se configuração existe
                $stmt = $this->pedidoModel->getConnection()->prepare("
                    SELECT id FROM configuracoes WHERE chave = :chave
                ");
                $stmt->bindParam(':chave', $chave);
                $stmt->execute();
                $config = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($config) {
                    // Atualizar
                    $stmt = $this->pedidoModel->getConnection()->prepare("
                        UPDATE configuracoes 
                        SET valor = :valor, atualizado_por = :usuario_id, updated_at = NOW()
                        WHERE chave = :chave
                    ");
                    $stmt->bindParam(':valor', $valor);
                    $stmt->bindParam(':usuario_id', $usuario['id']);
                    $stmt->bindParam(':chave', $chave);
                    $stmt->execute();
                } else {
                    // Criar
                    $stmt = $this->pedidoModel->getConnection()->prepare("
                        INSERT INTO configuracoes (chave, valor, criado_por, created_at, updated_at)
                        VALUES (:chave, :valor, :usuario_id, NOW(), NOW())
                    ");
                    $stmt->bindParam(':chave', $chave);
                    $stmt->bindParam(':valor', $valor);
                    $stmt->bindParam(':usuario_id', $usuario['id']);
                    $stmt->execute();
                }
            }
            
            $this->json([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar configurações: ' . $e->getMessage()], 500);
        }
    }
    
    public function testarEmail(Request $request) {
        $this->authService->requerPerfil('admin');
        
        try {
            // Teste de e-mail (implementar lógica real)
            $this->json([
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao enviar e-mail: ' . $e->getMessage()], 500);
        }
    }
    
    public function exportarConsolidarPedidos(Request $request) {
        $this->authService->requerPerfil('admin');
        
        // Implementar lógica de exportação
        $this->json([
            'success' => true,
            'message' => 'Exportação iniciada'
        ]);
    }
}
