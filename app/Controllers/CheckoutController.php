<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Models\Carrinho;
use App\Models\Usuario;
use App\Models\Endereco;
use App\Models\PedidoEcommerce;

// Garantir que as classes sejam carregadas
require_once __DIR__ . '/../Models/Model.php';
require_once __DIR__ . '/../Models/Endereco.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Carrinho.php';
require_once __DIR__ . '/../Models/PedidoEcommerce.php';

class CheckoutController extends Controller {
    private $authService;
    private $paymentService;
    private $carrinhoModel;
    private $usuarioModel;
    private $enderecoModel;
    private $pedidoModel;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->paymentService = new PaymentService();
        $this->carrinhoModel = new Carrinho();
        $this->usuarioModel = new Usuario();
        $this->enderecoModel = new Endereco();
        $this->pedidoModel = new PedidoEcommerce();
    }
    
    public function index(Request $request) {
        // Obter carrinho da sessão
        $carrinho = $_SESSION['carrinho'] ?? [];
        
        // Verificar se o carrinho tem itens
        if (empty($carrinho)) {
            $this->redirect('/produtos');
            return;
        }
        
        // Obter usuário logado
        $usuario = $this->authService->getUsuarioLogado();
        
        // Obter detalhes dos produtos no carrinho
        $items = [];
        $subtotal = 0;
        $pesoTotal = 0;
        
        foreach ($carrinho as $produtoId => $item) {
            // Buscar detalhes do produto (simulado por enquanto)
            $produto = [
                'id' => $produtoId,
                'nome' => $item['nome'] ?? 'Produto ' . $produtoId,
                'preco' => $item['preco_unitario'] ?? 0,
                'quantidade' => $item['quantidade'] ?? 1,
                'subtotal' => ($item['preco_unitario'] ?? 0) * ($item['quantidade'] ?? 1),
                'peso' => 0.5, // Padrão
                'foto_principal' => $item['foto_principal'] ?? null
            ];
            
            $items[] = $produto;
            $subtotal += $produto['subtotal'];
            $pesoTotal += $produto['peso'] * $produto['quantidade'];
        }
        
        $this->view('checkout/index', [
            'carrinho' => $carrinho,
            'items' => $items,
            'subtotal' => $subtotal,
            'peso_total' => $pesoTotal,
            'usuario' => $usuario,
            'enderecos' => $usuario ? $this->usuarioModel->getEnderecos($usuario['id']) : []
        ]);
    }
    
    public function processar(Request $request) {
        error_log('🔍 [CONTROLLER] processar() chamado - INÍCIO');
        
        $dados = $request->getParams();
        error_log('🔍 [CONTROLLER] Dados recebidos: ' . json_encode($dados));
        
        // Validar consentimento legal
        if (empty($dados['consentimento_legal'])) {
            error_log('❌ [CONTROLLER] Consentimento legal não aceito');
            $this->json(['error' => 'É necessário aceitar os termos para continuar'], 400);
            return;
        }
        
        // Validar dados obrigatórios
        $erros = $this->validarDadosCheckout($dados);
        if (!empty($erros)) {
            error_log('❌ [CONTROLLER] Erros de validação: ' . implode(', ', $erros));
            $this->json(['error' => implode(', ', $erros)], 400);
            return;
        }
        
        // Obter carrinho da sessão
        $carrinho = $_SESSION['carrinho'] ?? [];
        error_log('🔍 [CONTROLLER] Carrinho encontrado: ' . json_encode($carrinho));
        
        if (empty($carrinho)) {
            error_log('❌ [CONTROLLER] Carrinho vazio');
            $this->json(['error' => 'Carrinho vazio'], 400);
            return;
        }
        
        try {
            // Obter usuário logado
            $usuario = $this->authService->getUsuarioLogado();
            error_log('🔍 [CONTROLLER] Usuário: ' . ($usuario ? $usuario['email'] : 'Não logado'));
            
            // Criar pedido
            error_log('🔍 [CONTROLLER] Chamando criarPedido()...');
            $pedidoId = $this->criarPedido($dados, $carrinho, $usuario);
            error_log('🔍 [CONTROLLER] Pedido criado com ID: ' . $pedidoId);
            
            if ($pedidoId) {
                // Salvar itens do pedido
                error_log('🔍 [CONTROLLER] Salvando itens do pedido...');
                $this->salvarItensPedido($pedidoId, $carrinho);
                error_log('🔍 [CONTROLLER] Itens do pedido salvos');
                
                // Salvar dados do cliente
                error_log('🔍 [CONTROLLER] Salvando dados do cliente...');
                $this->salvarDadosCliente($pedidoId, $dados, $usuario);
                error_log('🔍 [CONTROLLER] Dados do cliente salvos');
                
                // Limpar carrinho
                unset($_SESSION['carrinho']);
                error_log('🔍 [CONTROLLER] Carrinho limpo');
                
                $response = [
                    'success' => true,
                    'message' => 'Pedido criado com sucesso',
                    'pedido_id' => $pedidoId,
                    'redirect' => '/checkout/conclusao/' . $pedidoId
                ];
                
                error_log('✅ [CONTROLLER] Resposta sucesso: ' . json_encode($response));
                $this->json($response);
            } else {
                error_log('❌ [CONTROLLER] Erro ao criar pedido - ID retornado: ' . $pedidoId);
                $this->json(['error' => 'Erro ao criar pedido'], 500);
            }
        } catch (Exception $e) {
            error_log('❌ [CONTROLLER] Exceção: ' . $e->getMessage());
            error_log('❌ [CONTROLLER] Stack: ' . $e->getTraceAsString());
            $this->json(['error' => 'Erro ao processar pedido: ' . $e->getMessage()], 500);
        }
        
        error_log('🔍 [CONTROLLER] processar() - FIM');
    }
    
    public function conclusao(Request $request) {
        $pedidoId = $request->getParam('id');
        
        if (!$pedidoId) {
            $this->redirect('/produtos');
            return;
        }
        
        // Obter dados do pedido
        $pedido = $this->obterPedidoCompleto($pedidoId);
        
        if (!$pedido) {
            $this->redirect('/produtos');
            return;
        }
        
        $this->view('checkout/conclusao', [
            'pedido' => $pedido,
            'itens' => $this->obterItensPedido($pedidoId)
        ]);
    }
    
    private function salvarItensPedido($pedidoId, $carrinho) {
        $db = \Config\Database::getConnection();
        
        foreach ($carrinho as $item) {
            error_log('🔍 [ITENS] Item do carrinho: ' . json_encode($item));
            
            $sql = "INSERT INTO pedido_itens (
                pedido_id, produto_id, nome, quantidade, preco_unitario, 
                subtotal, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $pedidoId,
                $item['produto_id'] ?? $item['id'] ?? null,
                $item['nome'],
                $item['quantidade'],
                $item['preco_unitario'],
                $item['subtotal']
            ]);
        }
    }
    
    private function salvarDadosCliente($pedidoId, $dados, $usuario) {
        $db = \Config\Database::getConnection();
        
        $sql = "UPDATE pedidos SET 
            cliente_nome = ?, cliente_email = ?, cliente_documento = ?, 
            cliente_telefone = ?, cep = ?, endereco = ?, numero = ?, 
            complemento = ?, bairro = ?, cidade = ?, estado = ?, 
            forma_pagamento = ?, observacoes = ?, updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $dados['nome'],
            $dados['email'],
            $dados['documento'],
            $dados['telefone'],
            $dados['cep'],
            $dados['endereco'],
            $dados['numero'],
            $dados['complemento'] ?? '',
            $dados['bairro'],
            $dados['cidade'],
            $dados['estado'],
            $dados['forma_pagamento'],
            $dados['observacoes'] ?? '',
            $pedidoId
        ]);
    }
    
    private function obterPedidoCompleto($pedidoId) {
        $db = \Config\Database::getConnection();
        
        $sql = "SELECT p.*, u.nome as usuario_nome 
                FROM pedidos p 
                LEFT JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function obterItensPedido($pedidoId) {
        $db = \Config\Database::getConnection();
        
        $sql = "SELECT * FROM pedido_itens WHERE pedido_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function obterCarrinho($usuario) {
        $sessionId = session_id();
        
        if ($usuario) {
            return $this->carrinhoModel->getOrCreateCarrinho($usuario['id'], null, 'USD');
        } else {
            return $this->carrinhoModel->getOrCreateCarrinho(null, $sessionId, 'USD');
        }
    }
    
    private function validarDadosCheckout($dados) {
        $erros = [];
        
        // Dados pessoais
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['email'])) $erros[] = 'E-mail é obrigatório';
        if (empty($dados['documento'])) $erros[] = 'Documento é obrigatório';
        if (empty($dados['telefone'])) $erros[] = 'Telefone é obrigatório';
        
        // Endereço
        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if (empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if (empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';
        
        // Pagamento
        if (empty($dados['forma_pagamento'])) $erros[] = 'Método de pagamento é obrigatório';
        
        // Senha (se não estiver logado)
        if (!$this->authService->estaLogado()) {
            if (empty($dados['senha'])) $erros[] = 'Senha é obrigatória';
            if (empty($dados['senha_confirmacao'])) $erros[] = 'Confirmação de senha é obrigatória';
            if ($dados['senha'] !== $dados['senha_confirmacao']) $erros[] = 'Senhas não conferem';
        }
        
        return $erros;
    }
    
    private function criarOuAtualizarUsuario($dados, $usuario) {
        if ($usuario) {
            // Usuário já está logado, apenas retornar ID
            return $usuario['id'];
        }
        
        // Verificar se usuário já existe
        $usuarioExistente = $this->usuarioModel->findByEmail($dados['email']);
        
        if ($usuarioExistente) {
            // Verificar senha
            if ($this->usuarioModel->authenticate($dados['email'], $dados['senha'])) {
                return $usuarioExistente['id'];
            } else {
                throw new \Exception('E-mail já cadastrado com senha diferente');
            }
        }
        
        // Criar novo usuário
        return $this->usuarioModel->create([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha' => $dados['senha'],
            'telefone' => $dados['telefone'],
            'documento' => $dados['documento'],
            'perfil' => 'cliente'
        ]);
    }
    
    private function criarEndereco($usuarioId, $dados, $tipo) {
        $enderecoData = [
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'cep' => $dados['cep'],
            'endereco' => $dados['endereco'],
            'numero' => $dados['numero'],
            'complemento' => $dados['complemento'] ?? '',
            'bairro' => $dados['bairro'],
            'cidade' => $dados['cidade'],
            'estado' => $dados['estado'],
            'pais' => 'BR',
            'principal' => false
        ];
        
        $this->enderecoModel->create($enderecoData);
        return $this->enderecoModel->connection->lastInsertId();
    }
    
    private function registrarConsentimentoLegal($usuarioId, $dados) {
        // Aqui poderia ser implementado um registro mais detalhado do consentimento
        // Por enquanto, apenas registrar no log de auditoria
        $this->authService->registrarLogAuditoria(
            $usuarioId,
            'consentimento_legal_aceito',
            'usuarios',
            $usuarioId,
            null,
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'data_hora' => date('Y-m-d H:i:s'),
                'versao_termo' => '1.0',
                'idioma' => 'pt-BR'
            ]
        );
    }
    
    public function calcular(Request $request) {
        $dados = $request->getParams();
        
        try {
            // Obter carrinho
            $usuario = $this->authService->getUsuarioLogado();
            $carrinho = $this->obterCarrinho($usuario);
            
            if (!$carrinho) {
                $this->json(['error' => 'Carrinho não encontrado'], 400);
            }
            
            // Atualizar frete manual se informado
            if (isset($dados['frete_manual'])) {
                $this->carrinhoModel->update($carrinho['id'], [
                    'frete_manual' => floatval($dados['frete_manual'])
                ]);
                $this->carrinhoModel->atualizarTotais($carrinho['id']);
                
                // Recarregar carrinho atualizado
                $carrinho = $this->carrinhoModel->find($carrinho['id']);
            }
            
            // Obter taxa de câmbio atual
            $taxaConversao = $this->carrinhoModel->getTaxaConversao($carrinho['moeda']);
            
            $this->json([
                'success' => true,
                'carrinho' => [
                    'subtotal_produtos' => number_format($carrinho['subtotal_produtos'], 2, ',', '.'),
                    'valor_frete' => number_format($carrinho['frete_manual'], 2, ',', '.'),
                    'taxa_servico' => number_format($carrinho['taxa_servico'], 2, ',', '.'),
                    'valor_impostos' => number_format($carrinho['valor_impostos'], 2, ',', '.'),
                    'valor_total' => number_format($carrinho['valor_total'], 2, ',', '.'),
                    'valor_total_brl' => number_format($carrinho['valor_total'] * $taxaConversao, 2, ',', '.'),
                    'peso_total' => number_format($carrinho['peso_total'], 3, ',', '.'),
                    'moeda' => $carrinho['moeda'],
                    'taxa_conversao' => $taxaConversao
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao calcular valores: ' . $e->getMessage()], 500);
        }
    }
    
    private function criarPedido($dados, $carrinho, $usuario) {
        try {
            error_log('🔍 [CRIAR_PEDIDO] Iniciando criação do pedido');
            
            // Garantir usuário e cliente válidos - fluxo correto obrigatório
            $db = \Config\Database::getConnection();
            
            if (empty($usuario) || empty($usuario['email'])) {
                throw new \Exception('Dados do usuário são obrigatórios para criar pedido');
            }
            
            // 1. Buscar/criar usuário na tabela usuarios
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingUser = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingUser && !empty($existingUser['id'])) {
                $usuarioId = $existingUser['id'];
                error_log('🔍 [CRIAR_PEDIDO] Usuário encontrado: ' . $usuarioId);
            } else {
                $stmt = $db->prepare("INSERT INTO usuarios (name, email, password, documento, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['email'],
                    password_hash('temp123', PASSWORD_DEFAULT),
                    'DOC' . time()
                ]);
                $usuarioId = $db->lastInsertId();
                error_log('🔍 [CRIAR_PEDIDO] Usuário criado: ' . $usuarioId);
            }
            
            // 2. Buscar/criar cliente na tabela clientes (foreign key obrigatória)
            $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingClient = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingClient && !empty($existingClient['id'])) {
                $clienteId = $existingClient['id'];
                error_log('🔍 [CRIAR_PEDIDO] Cliente encontrado: ' . $clienteId);
            } else {
                $stmt = $db->prepare("INSERT INTO clientes (nome, email, telefone, documento, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['email'],
                    $usuario['telefone'] ?? '',
                    'DOC' . time()
                ]);
                $clienteId = $db->lastInsertId();
                error_log('🔍 [CRIAR_PEDIDO] Cliente criado: ' . $clienteId);
            }
            
            // 3. Validar IDs antes de continuar
            if (empty($usuarioId) || empty($clienteId)) {
                throw new \Exception('Falha ao obter IDs válidos de usuário/cliente');
            }
            
            // Calcular totais
            $subtotal = 0;
            $pesoTotal = 0;
            
            error_log('🔍 [CRIAR_PEDIDO] Calculando totais...');
            
            foreach ($carrinho as $item) {
                $subtotal += ($item['preco_unitario'] ?? 0) * ($item['quantidade'] ?? 1);
                $pesoTotal += 0.5 * ($item['quantidade'] ?? 1); // Peso padrão
                error_log('🔍 [CRIAR_PEDIDO] Item processado: ' . json_encode($item));
            }
            
            error_log('🔍 [CRIAR_PEDIDO] Subtotal: ' . $subtotal);
            error_log('🔍 [CRIAR_PEDIDO] Peso total: ' . $pesoTotal);
            
            // Taxas
            $taxaServico = $pesoTotal * 39; // US$39 por kg
            $impostos = $subtotal * 0.80; // 80%
            $frete = $pesoTotal * 15; // US$15 por kg
            $total = $subtotal + $taxaServico + $impostos + $frete;
            
            error_log('🔍 [CRIAR_PEDIDO] Taxa de serviço: ' . $taxaServico);
            error_log('🔍 [CRIAR_PEDIDO] Impostos: ' . $impostos);
            error_log('🔍 [CRIAR_PEDIDO] Frete: ' . $frete);
            error_log('🔍 [CRIAR_PEDIDO] Total: ' . $total);
            
            // Criar número do pedido
            $numeroPedido = 'BRZ' . date('YmdHis') . rand(1000, 9999);
            error_log('🔍 [CRIAR_PEDIDO] Número do pedido: ' . $numeroPedido);
            
            // Inserir pedido com todos os campos originais
            $db = \Config\Database::getConnection();
            error_log('🔍 [CRIAR_PEDIDO] Conexão com banco obtida');
            
            $sql = "INSERT INTO pedidos (
                usuario_id, nome, numero_pedido, cliente_id, status, 
                subtotal, servicos, impostos, frete, desconto, total, 
                moeda, taxa_conversao, endereco_entrega_id, endereco_cobranca_id, 
                observacoes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $db->prepare($sql);
            error_log('🔍 [CRIAR_PEDIDO] SQL preparado');
            
            $params = [
                $usuarioId,
                $usuario['nome'] ?? 'Cliente',
                $numeroPedido,
                $clienteId, // cliente_id válido da tabela clientes
                'pendente',
                $subtotal,
                $taxaServico,
                $impostos,
                $frete,
                0, // desconto padrão 0
                $total,
                'USD',
                1.0, // taxa_conversao padrão
                null, // endereco_entrega_id
                null, // endereco_cobranca_id
                $dados['observacoes'] ?? ''
            ];
            
            error_log('🔍 [CRIAR_PEDIDO] Parâmetros: ' . json_encode($params));
            
            $stmt->execute($params);
            error_log('🔍 [CRIAR_PEDIDO] Query executado com sucesso');
            
            $pedidoId = $db->lastInsertId();
            error_log('🔍 [CRIAR_PEDIDO] ID gerado: ' . $pedidoId);
            
            return $pedidoId;
            
        } catch (\Exception $e) {
            error_log('❌ [CRIAR_PEDIDO] Erro ao criar pedido: ' . $e->getMessage());
            error_log('❌ [CRIAR_PEDIDO] Stack: ' . $e->getTraceAsString());
            
            // Retornar JSON válido em caso de erro
            $this->json([
                'success' => false,
                'error' => 'Erro ao criar pedido: ' . $e->getMessage(),
                'code' => 500
            ], 500);
            return false;
        }
    }
}
