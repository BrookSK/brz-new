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
                'foto_principal' => 'placeholder.jpg'
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
        $dados = $request->getParams();
        
        // Validar consentimento legal
        if (empty($dados['consentimento_legal'])) {
            $this->json(['error' => 'É necessário aceitar os termos para continuar'], 400);
        }
        
        // Validar dados obrigatórios
        $erros = $this->validarDadosCheckout($dados);
        if (!empty($erros)) {
            $this->json(['error' => implode(', ', $erros)], 400);
        }
        
        // Obter carrinho da sessão
        $carrinho = $_SESSION['carrinho'] ?? [];
        
        if (empty($carrinho)) {
            $this->json(['error' => 'Carrinho vazio'], 400);
        }
        
        try {
            // Obter usuário logado
            $usuario = $this->authService->getUsuarioLogado();
                'card_expiry_month' => $dados['card_expiry_month'] ?? '',
                'card_expiry_year' => $dados['card_expiry_year'] ?? '',
                'card_cvv' => $dados['card_cvv'] ?? ''
            ];
            
            // Validar dados de pagamento
            $errosPagamento = $this->paymentService->validarDadosPagamento($dadosPagamento);
            if (!empty($errosPagamento)) {
                $this->json(['error' => implode(', ', $errosPagamento)], 400);
            }
            
            $resultadoPagamento = $this->paymentService->processarPagamento(
                $dadosPagamento,
                $carrinho['valor_total'],
                $carrinho['moeda'],
                'Pedido BRZ Logistics'
            );
            
            if (!$resultadoPagamento['success']) {
                $this->json(['error' => 'Falha no processamento do pagamento'], 400);
            }
            
            // Criar pedido
            $pedidoId = $this->pedidoModel->criarPedidoAPartirDoCarrinho(
                $carrinho['id'],
                $usuarioId,
                $enderecoEntregaId,
                $enderecoCobrancaId,
                [
                    'gateway' => $carrinho['moeda'] === 'BRL' ? 'asaas' : 'stripe',
                    'payment_id' => $resultadoPagamento['payment_id']
                ]
            );
            
            // Registrar consentimento legal
            $this->registrarConsentimentoLegal($usuarioId, $dados);
            
            // Fazer login se não estava logado
            if (!$usuario) {
                $this->authService->login($dados['email'], $dados['senha']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'pedido_id' => $pedidoId,
                'redirect' => "/pedido/detalhes/{$pedidoId}"
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao processar pedido: ' . $e->getMessage()], 500);
        }
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
        if (empty($dados['payment_method'])) $erros[] = 'Método de pagamento é obrigatório';
        
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
}
