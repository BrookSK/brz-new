<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\PedidoEcommerce;
use App\Models\Usuario;
use App\Models\Produto;
use App\Models\Categoria;

class AdminController extends Controller {
    private $authService;
    private $pedidoModel;
    private $usuarioModel;
    private $produtoModel;
    private $categoriaModel;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->pedidoModel = new PedidoEcommerce();
        $this->usuarioModel = new Usuario();
        $this->produtoModel = new Produto();
        $this->categoriaModel = new Categoria();
    }
    
    public function dashboard(Request $request) {
        $this->authService->requerPerfil('admin');
        
        // Estatísticas básicas
        $stats = $this->getDashboardStats();
        
        $this->view('admin/dashboard', [
            'stats' => $stats
        ]);
    }
    
    private function getDashboardStats() {
        $stats = [];
        
        // Total de pedidos
        $stmt = $this->pedidoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM {$this->pedidoModel->getTable()}");
        $stmt->execute();
        $stats['total_pedidos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
        // Pedidos por status
        $stmt = $this->pedidoModel->getConnection()->prepare("
            SELECT status, COUNT(*) as quantidade 
            FROM {$this->pedidoModel->getTable()} 
            GROUP BY status
        ");
        $stmt->execute();
        $stats['pedidos_por_status'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Faturamento total (simplificado)
        $stmt = $this->pedidoModel->getConnection()->prepare("
            SELECT 
                SUM(valor_total) as faturamento_usd
            FROM {$this->pedidoModel->getTable()}
        ");
        $stmt->execute();
        $financeiro = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stats['financeiro'] = $financeiro;
        
        // Pedidos recentes
        $stmt = $this->pedidoModel->getConnection()->prepare("
            SELECT p.*, u.nome as cliente_nome 
            FROM {$this->pedidoModel->getTable()} p 
            JOIN usuarios u ON p.usuario_id = u.id 
            ORDER BY p.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $stats['pedidos_recentes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Total de usuários
        $stmt = $this->pedidoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM usuarios");
        $stmt->execute();
        $stats['total_usuarios'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
        // Total de produtos
        $stmt = $this->pedidoModel->getConnection()->prepare("SELECT COUNT(*) as total FROM produtos");
        $stmt->execute();
        $stats['total_produtos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
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
        $sql = "
            SELECT p.*, u.nome as cliente_nome, u.email as cliente_email
            FROM {$this->pedidoModel->getTable()} p
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
    }
    
    private function getTotalPedidosComFiltros($filtro, $status) {
        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->pedidoModel->getTable()} p
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
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
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
    }
    
    private function getTotalProdutos($busca, $status, $categoria_id) {
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE 1=1";
        $params = [];
        
        if (!empty($busca)) {
            $sql .= " AND (nome LIKE :busca OR sku LIKE :busca)";
            $params[':busca'] = "%{$busca}%";
        }
        
        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
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
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }
    
    private function getCategorias() {
        $stmt = $this->categoriaModel->getConnection()->prepare("SELECT * FROM categorias ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function editarProduto(Request $request) {
        $this->authService->requerPermissao('read');
        
        $produtoId = $request->getParam('id');
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        $this->json(['success' => true, 'produto' => $produto]);
    }
    
    public function salvarProduto(Request $request) {
        $this->authService->requerPermissao('create');
        
        $dados = $request->getParams();
        $usuario = $this->authService->getUsuarioLogado();
        
        try {
            $produtoId = $this->produtoModel->create($dados, $usuario['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Produto criado com sucesso',
                'produto_id' => $produtoId
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao criar produto: ' . $e->getMessage()], 500);
        }
    }
    
    public function atualizarProduto(Request $request) {
        $this->authService->requerPermissao('update');
        
        $produtoId = $request->getParam('id');
        $dados = $request->getParams();
        $usuario = $this->authService->getUsuarioLogado();
        
        try {
            $this->produtoModel->update($produtoId, $dados, $usuario['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Produto atualizado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao atualizar produto: ' . $e->getMessage()], 500);
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
