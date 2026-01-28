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

    private function getConfigValue(string $chave, $default = null) {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function getTaxaServicoPorKg(): float {
        return floatval($this->getConfigValue('entrega_taxa_servico_kg', '39'));
    }

    private function calcularFrete(float $subtotal, float $pesoTotal, string $moeda = 'USD'): float {
        $calcularAutomatico = $this->getConfigValue('entrega_calcular_automatico', '1');
        $calcularAutomatico = ($calcularAutomatico === '1' || strtolower((string) $calcularAutomatico) === 'true');
        if (!$calcularAutomatico) {
            return 0.0;
        }

        $freteGratisAcima = floatval($this->getConfigValue('entrega_frete_gratis_acima', '0'));
        if ($freteGratisAcima <= 0 || $subtotal >= $freteGratisAcima) {
            return 0.0;
        }

        $fretePorKg = floatval($this->getConfigValue('entrega_frete_padrao', '15'));
        if ($fretePorKg <= 0) {
            return 0.0;
        }

        $pesoArredondado = ceil($pesoTotal);
        return $fretePorKg * $pesoArredondado;
    }

    private function debugLog(string $message): void {
        $enabled = false;
        if (isset($_ENV['APP_DEBUG'])) {
            $enabled = ($_ENV['APP_DEBUG'] === '1' || strtolower((string) $_ENV['APP_DEBUG']) === 'true');
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $enabled = ($_SERVER['APP_DEBUG'] === '1' || strtolower((string) $_SERVER['APP_DEBUG']) === 'true');
        }

        if ($enabled) {
            error_log($message);
        }
    }
    
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
            // Verificar diferentes campos de preço
            $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
            $quantidade = $item['quantidade'] ?? 1;
            
            // Calcular peso por item e arredondar para cima
            $pesoItem = 0.5 * $quantidade; // Peso padrão por item
            $pesoArredondado = ceil($pesoItem); // Arredondar para cima
            
            // Buscar detalhes do produto (simulado por enquanto)
            $produto = [
                'id' => $produtoId,
                'nome' => $item['nome'] ?? $item['name'] ?? 'Produto ' . $produtoId,
                'preco' => $precoUnitario,
                'quantidade' => $quantidade,
                'subtotal' => $precoUnitario * $quantidade,
                'peso' => $pesoArredondado, // Usar peso arredondado
                'foto_principal' => $item['foto_principal'] ?? null
            ];
            
            $items[] = $produto;
            $subtotal += $produto['subtotal'];
            $pesoTotal += $produto['peso'] * $quantidade;

            $this->debugLog('[CHECKOUT_INDEX] Item: ' . json_encode($item));
            $this->debugLog('[CHECKOUT_INDEX] Produto processado: ' . json_encode($produto));
        }

        $taxaServico = ceil($pesoTotal) * $this->getTaxaServicoPorKg();
        $frete = $this->calcularFrete($subtotal, $pesoTotal, $_GET['moeda'] ?? 'USD');
        $impostos = $subtotal * 0.80;
        $total = $subtotal + $frete + $taxaServico + $impostos;
        
        $this->view('checkout/index', [
            'carrinho' => $carrinho,
            'items' => $items,
            'subtotal' => $subtotal,
            'peso_total' => $pesoTotal,
            'usuario' => $usuario,
            'enderecos' => $usuario ? $this->usuarioModel->getEnderecos($usuario['id']) : [],
            'moeda' => $_GET['moeda'] ?? 'USD', // Obter moeda da URL ou padrão USD
            'frete' => $frete,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'total' => $total,
            'frete_gratis' => ($frete == 0)
        ]);
    }
    
    public function processar(Request $request) {
        // Obter carrinho da sessão
        $carrinho = $_SESSION['carrinho'] ?? [];
        
        if (empty($carrinho)) {
            $this->redirect('/produtos');
            return;
        }
        
        // Obter usuário logado
        $usuario = $this->authService->getUsuarioLogado();
        
        // Obter dados do formulário
        $dados = $request->getParams();
        
        // Verificar se usuário quer salvar novo endereço
        if (!empty($usuario) && !empty($dados['salvar_endereco'])) {
            // Salvar novo endereço
            $enderecoData = [
                'usuario_id' => $usuario['id'],
                'cep' => $dados['cep'],
                'endereco' => $dados['endereco'],
                'numero' => $dados['numero'],
                'complemento' => $dados['complemento'] ?? '',
                'bairro' => $dados['bairro'],
                'cidade' => $dados['cidade'],
                'estado' => $dados['estado'],
                'principal' => 0 // Não é principal por padrão
            ];
            
            // Verificar se é o primeiro endereço (torna automático principal)
            $enderecosExistentes = $this->usuarioModel->getEnderecos($usuario['id']);
            if (empty($enderecosExistentes)) {
                $enderecoData['principal'] = 1;
            }
            
            $this->enderecoModel->create($enderecoData);
        }
        
        // Resto do processamento do pedido...
        $this->debugLog('[CHECKOUT] processar() chamado - INICIO');
        
        $dados = $request->getParams();
        $this->debugLog('[CHECKOUT] Dados recebidos: ' . json_encode($dados));
        
        // Validar consentimento legal
        if (empty($dados['consentimento_legal'])) {
            $this->debugLog('[CHECKOUT] Consentimento legal nao aceito');
            $this->json(['error' => 'É necessário aceitar os termos para continuar'], 400);
            return;
        }
        
        // Validar dados obrigatórios
        $erros = $this->validarDadosCheckout($dados);
        if (!empty($erros)) {
            $this->debugLog('[CHECKOUT] Erros de validacao: ' . implode(', ', $erros));
            $this->json(['error' => implode(', ', $erros)], 400);
            return;
        }
        
        // Obter carrinho da sessão
        $carrinho = $_SESSION['carrinho'] ?? [];
        $this->debugLog('[CHECKOUT] Carrinho encontrado: ' . json_encode($carrinho));
        
        if (empty($carrinho)) {
            $this->debugLog('[CHECKOUT] Carrinho vazio');
            $this->json(['error' => 'Carrinho vazio'], 400);
            return;
        }
        
        try {
            // Obter usuário logado
            $usuario = $this->authService->getUsuarioLogado();
            $this->debugLog('[CHECKOUT] Usuario: ' . ($usuario ? $usuario['email'] : 'Nao logado'));
            
            // Criar pedido
            $this->debugLog('[CHECKOUT] Chamando criarPedido()...');
            $pedidoId = $this->criarPedido($dados, $carrinho, $usuario);
            $this->debugLog('[CHECKOUT] Pedido criado com ID: ' . $pedidoId);
            
            if ($pedidoId) {
                // Salvar itens do pedido
                $this->debugLog('[CHECKOUT] Salvando itens do pedido...');
                $this->salvarItensPedido($pedidoId, $carrinho);
                $this->debugLog('[CHECKOUT] Itens do pedido salvos');
                
                // Salvar dados do cliente
                $this->debugLog('[CHECKOUT] Salvando dados do cliente...');
                $this->salvarDadosCliente($pedidoId, $dados, $usuario);
                $this->debugLog('[CHECKOUT] Dados do cliente salvos');
                
                // Limpar carrinho
                unset($_SESSION['carrinho']);
                $this->debugLog('[CHECKOUT] Carrinho limpo');
                
                $response = [
                    'success' => true,
                    'message' => 'Pedido criado com sucesso',
                    'pedido_id' => $pedidoId,
                    'redirect' => '/checkout/conclusao/' . $pedidoId
                ];
                
                $this->debugLog('[CHECKOUT] Resposta sucesso: ' . json_encode($response));
                $this->json($response);
            } else {
                $this->debugLog('[CHECKOUT] Erro ao criar pedido - ID retornado: ' . $pedidoId);
                $this->json(['error' => 'Erro ao criar pedido'], 500);
            }
        } catch (\Exception $e) {
            $this->debugLog('[CHECKOUT] Excecao: ' . $e->getMessage());
            $this->debugLog('[CHECKOUT] Stack: ' . $e->getTraceAsString());
            $this->json(['error' => 'Erro ao processar pedido: ' . $e->getMessage()], 500);
        }
        
        $this->debugLog('[CHECKOUT] processar() - FIM');
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
            $this->debugLog('[CHECKOUT_ITENS] Item do carrinho: ' . json_encode($item));
            
            // Validar se o produto existe antes de inserir
            $produtoId = $item['produto_id'] ?? $item['id'] ?? null;
            if (empty($produtoId)) {
                $this->debugLog('[CHECKOUT_ITENS] Produto ID vazio, pulando item');
                continue;
            }
            
            $stmt = $db->prepare("SELECT id FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$produto) {
                $this->debugLog('[CHECKOUT_ITENS] Produto ID ' . $produtoId . ' nao encontrado, pulando item');
                continue;
            }
            
            $this->debugLog('[CHECKOUT_ITENS] Produto ID ' . $produtoId . ' validado');
            
            // Verificar diferentes campos de preço
            $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
            $quantidade = $item['quantidade'] ?? 1;
            
            $this->debugLog('[CHECKOUT_ITENS] Preco unitario: ' . $precoUnitario . ', Quantidade: ' . $quantidade);
            
            $sql = "INSERT INTO pedido_itens (
                pedido_id, produto_id, quantidade, preco_unitario, 
                subtotal, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $pedidoId,
                $produtoId,
                $quantidade,
                $precoUnitario,
                $precoUnitario * $quantidade
            ]);
            
            $this->debugLog('[CHECKOUT_ITENS] Item inserido: produto_id=' . $produtoId . ', quantidade=' . $quantidade . ', valor=' . ($precoUnitario * $quantidade));
        }
    }
    
    private function salvarDadosCliente($pedidoId, $dados, $usuario) {
        try {
            $db = \Config\Database::getConnection();
            
            // Removido UPDATE com colunas inexistentes - dados já salvos nas tabelas usuarios/clientes
            $this->debugLog('[CHECKOUT_DADOS_CLIENTE] Dados ja persistidos em usuarios e clientes');
            
            return true;
            
        } catch (\Exception $e) {
            $this->debugLog('[CHECKOUT_DADOS_CLIENTE] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    private function obterPedidoCompleto($pedidoId) {
        $db = \Config\Database::getConnection();
        
        $sql = "SELECT 
                    p.*,
                    p.servicos AS taxa_servico,
                    u.name AS cliente_nome,
                    u.email AS cliente_email,
                    u.telefone AS cliente_telefone,
                    e_ent.cep AS cep,
                    e_ent.endereco AS endereco,
                    e_ent.logradouro AS logradouro,
                    e_ent.numero AS numero,
                    e_ent.complemento AS complemento,
                    e_ent.bairro AS bairro,
                    e_ent.cidade AS cidade,
                    e_ent.estado AS estado
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN enderecos e_ent ON p.endereco_entrega_id = e_ent.id
                WHERE p.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);

        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($pedido)) {
            if (empty($pedido['endereco']) && !empty($pedido['logradouro'])) {
                $pedido['endereco'] = $pedido['logradouro'];
            }
        }

        return $pedido;
    }
    
    private function obterItensPedido($pedidoId) {
        $db = \Config\Database::getConnection();
        
        $sql = "SELECT 
                    pi.*,
                    COALESCE(pi.nome_produto, pr.nome, pr.name) AS nome
                FROM pedido_itens pi
                LEFT JOIN produtos pr ON pi.produto_id = pr.id
                WHERE pi.pedido_id = ?";
        
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
        return $this->enderecoModel->getConnection()->lastInsertId();
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
            $this->debugLog('[CRIAR_PEDIDO] Iniciando criacao do pedido');
            
            // Garantir usuário e cliente válidos - fluxo correto obrigatório
            $db = \Config\Database::getConnection();
            
            if (empty($usuario) || empty($usuario['email'])) {
                $usuario = [
                    'nome' => $dados['nome'] ?? 'Cliente',
                    'email' => $dados['email'] ?? null,
                    'documento' => $dados['documento'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'senha' => $dados['senha'] ?? null,
                ];
            }

            if (empty($usuario['email'])) {
                throw new \Exception('E-mail é obrigatório para criar pedido');
            }

            $emailInformado = $usuario['email'];
            
            // 1. Buscar/criar usuário na tabela usuarios
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Se não encontrou por e-mail, tentar por documento (CPF/CNPJ) pois pode ser UNIQUE
            if ((!$existingUser || empty($existingUser['id'])) && !empty($usuario['documento'])) {
                $stmtDoc = $db->prepare("SELECT id, email, name, password, senha, role, perfil FROM usuarios WHERE documento = ? LIMIT 1");
                $stmtDoc->execute([$usuario['documento']]);
                $existingUserByDoc = $stmtDoc->fetch(\PDO::FETCH_ASSOC);
                if ($existingUserByDoc && !empty($existingUserByDoc['id'])) {
                    // Se encontrou pelo CPF mas o e-mail informado é diferente, exigir login com o e-mail correto
                    if (!$this->authService->estaLogado() && !empty($existingUserByDoc['email']) && strcasecmp((string) $existingUserByDoc['email'], (string) $emailInformado) !== 0) {
                        throw new \Exception('Já existe uma conta com esse CPF. Faça login com o e-mail cadastrado para finalizar a compra.');
                    }
                    $existingUser = ['id' => $existingUserByDoc['id']];
                    // Preferir dados já existentes do cadastro
                    if (!empty($existingUserByDoc['email'])) {
                        $usuario['email'] = $existingUserByDoc['email'];
                    }
                    if (!empty($existingUserByDoc['name']) || !empty($existingUserByDoc['nome'])) {
                        $usuario['nome'] = $existingUserByDoc['nome'] ?? $existingUserByDoc['name'];
                    }
                }
            }
            
            if ($existingUser && !empty($existingUser['id'])) {
                $usuarioId = $existingUser['id'];
                $this->debugLog('[CRIAR_PEDIDO] Usuario encontrado: ' . $usuarioId);

                // Se não estiver logado, exigir que a senha informada seja válida
                if (!$this->authService->estaLogado()) {
                    $senhaInformada = $dados['senha'] ?? $usuario['senha'] ?? null;
                    if (empty($senhaInformada)) {
                        throw new \Exception('Senha é obrigatória para concluir com este e-mail');
                    }

                    $usuarioModelApp = new \App\Models\Usuario();
                    $authOk = $usuarioModelApp->authenticate($usuario['email'], $senhaInformada);
                    if (!$authOk) {
                        throw new \Exception('E-mail já cadastrado com senha diferente');
                    }
                }
            } else {
                $stmt = $db->prepare("INSERT INTO usuarios (name, email, password, documento, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $senhaPlano = $usuario['senha'] ?? 'temp123';
                $stmt->execute([
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['email'],
                    password_hash((string) $senhaPlano, PASSWORD_DEFAULT),
                    $usuario['documento'] ?? ('DOC' . time())
                ]);
                $usuarioId = $db->lastInsertId();
                $this->debugLog('[CRIAR_PEDIDO] Usuario criado: ' . $usuarioId);
            }

            // Login automático quando não estava logado
            if (!$this->authService->estaLogado()) {
                try {
                    $stmtUser = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                    $stmtUser->execute([$usuarioId]);
                    $rowUser = $stmtUser->fetch(\PDO::FETCH_ASSOC);

                    if ($rowUser) {
                        $rowUser['perfil'] = $rowUser['perfil'] ?? ($rowUser['role'] ?? 'cliente');
                        $rowUser['nome'] = $rowUser['nome'] ?? ($rowUser['name'] ?? ($usuario['nome'] ?? 'Cliente'));
                        $this->authService->criarSessao($rowUser);
                    }
                } catch (\Exception $e) {
                }
            }
            
            // 2. Buscar/criar cliente na tabela clientes (foreign key obrigatória)
            $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingClient = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingClient && !empty($existingClient['id'])) {
                $clienteId = $existingClient['id'];
                $this->debugLog('[CRIAR_PEDIDO] Cliente encontrado: ' . $clienteId);
            } else {
                // Usar estrutura REAL da tabela clientes
                $stmt = $db->prepare("INSERT INTO clientes (usuario_id, nome_razao_social, cpf_cnpj, telefone, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $usuarioId,
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['documento'] ?? ('DOC' . time()),
                    $usuario['telefone'] ?? '',
                    $usuario['email']
                ]);
                $clienteId = $db->lastInsertId();
                $this->debugLog('[CRIAR_PEDIDO] Cliente criado com estrutura real: ' . $clienteId);
            }
            
            // 3. Validar IDs antes de continuar
            if (empty($usuarioId) || empty($clienteId)) {
                throw new \Exception('Falha ao obter IDs válidos de usuário/cliente');
            }
            
            // Obter moeda selecionada pelo cliente
            $moedaSelecionada = $dados['moeda'] ?? 'USD';
            $this->debugLog('[CRIAR_PEDIDO] Moeda selecionada pelo cliente: ' . $moedaSelecionada);
            
            // Calcular totais
            $subtotal = 0;
            $pesoTotal = 0;
            
            $this->debugLog('[CRIAR_PEDIDO] Calculando totais...');
            
            foreach ($carrinho as $item) {
                // Verificar diferentes campos de preço
                $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
                $quantidade = $item['quantidade'] ?? 1;
                
                $subtotal += $precoUnitario * $quantidade;
                
                // Calcular peso por item e arredondar para cima
                $pesoItem = 0.5 * $quantidade; // Peso padrão por item
                $pesoArredondado = ceil($pesoItem); // Arredondar para cima
                $pesoTotal += $pesoArredondado;
                
                $this->debugLog('[CRIAR_PEDIDO] Item processado: ' . json_encode($item));
                $this->debugLog('[CRIAR_PEDIDO] Preco unitario: ' . $precoUnitario . ', Quantidade: ' . $quantidade . ', Peso item: ' . $pesoItem . ', Peso arredondado: ' . $pesoArredondado);
            }
            
            $this->debugLog('[CRIAR_PEDIDO] Subtotal: ' . $subtotal);
            $this->debugLog('[CRIAR_PEDIDO] Peso total: ' . $pesoTotal);
            
            // Taxas baseadas na moeda selecionada
            if ($moedaSelecionada === 'BRL') {
                // Valores em BRL (convertidos)
                $taxaConversao = 5.50; // Taxa de conversão USD para BRL
                $taxaServico = (ceil($pesoTotal) * $this->getTaxaServicoPorKg()) * $taxaConversao; // Converter para BRL
                $impostos = $subtotal * 0.80; // Já está em BRL
                $freteUSD = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
                $frete = ($freteUSD > 0) ? ($freteUSD * $taxaConversao) : 0;
                $total = $subtotal + $taxaServico + $impostos + $frete;
                
                $this->debugLog('[CRIAR_PEDIDO] Calculo em BRL - Taxa conversao: ' . $taxaConversao);
            } else {
                // Valores em USD (padrão)
                $taxaConversao = 1.0;
                $taxaServico = ceil($pesoTotal) * $this->getTaxaServicoPorKg();
                $impostos = $subtotal * 0.80; // 80%
                $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
                $total = $subtotal + $taxaServico + $impostos + $frete;
                
                $this->debugLog('[CRIAR_PEDIDO] Calculo em USD - Taxa conversao: ' . $taxaConversao);
            }
            
            $this->debugLog('[CRIAR_PEDIDO] Taxa de servico: ' . $taxaServico);
            $this->debugLog('[CRIAR_PEDIDO] Impostos: ' . $impostos);
            $this->debugLog('[CRIAR_PEDIDO] Frete: ' . $frete . ' (' . (($frete == 0) ? 'GRATIS' : 'PAGO') . ')');
            $this->debugLog('[CRIAR_PEDIDO] Total: ' . $total);
            
            // Criar número do pedido
            $numeroPedido = 'BZS' . date('YmdHis') . rand(1000, 9999);
            $this->debugLog('[CRIAR_PEDIDO] Numero do pedido: ' . $numeroPedido);
            
            // Inserir pedido com todos os campos originais
            $db = \Config\Database::getConnection();
            $this->debugLog('[CRIAR_PEDIDO] Conexao com banco obtida');
            
            $sql = "INSERT INTO pedidos (
                usuario_id, nome, numero_pedido, cliente_id, status, 
                subtotal, servicos, impostos, frete, desconto, total, 
                moeda, taxa_conversao, endereco_entrega_id, endereco_cobranca_id, 
                observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $this->debugLog('[CRIAR_PEDIDO] SQL preparado');
            
            $params = [
                $usuarioId,
                $usuario['nome'] ?? 'Cliente', // OBRIGATÓRIO (NOT NULL)
                $numeroPedido,
                $clienteId,
                'pagamento', // ENUM válido
                $subtotal,
                $taxaServico, // MAPEIA PARA servicos
                $impostos,
                $frete,
                0, // desconto
                $total,
                $moedaSelecionada, // Usar moeda selecionada pelo cliente
                $taxaConversao, // Taxa de conversão aplicada
                null, // endereco_entrega_id
                null, // endereco_cobranca_id
                $dados['observacoes'] ?? ''
            ];
            
            $this->debugLog('[CRIAR_PEDIDO] Parametros: ' . json_encode($params));
            
            $stmt->execute($params);
            $this->debugLog('[CRIAR_PEDIDO] Query executado com sucesso');
            
            $pedidoId = $db->lastInsertId();
            $this->debugLog('[CRIAR_PEDIDO] ID gerado: ' . $pedidoId);

            // Criar endereço(s) e vincular ao pedido
            try {
                $enderecoModelApp = new \App\Models\Endereco();

                $enderecosExistentes = [];
                try {
                    $usuarioModelApp = new \App\Models\Usuario();
                    $enderecosExistentes = $usuarioModelApp->getEnderecos($usuarioId);
                } catch (\Exception $e) {
                }

                $principal = empty($enderecosExistentes) ? 1 : 0;
                $enderecoEntregaData = [
                    'usuario_id' => $usuarioId,
                    'tipo' => 'entrega',
                    'cep' => $dados['cep'],
                    'endereco' => $dados['endereco'],
                    'numero' => $dados['numero'],
                    'complemento' => $dados['complemento'] ?? '',
                    'bairro' => $dados['bairro'],
                    'cidade' => $dados['cidade'],
                    'estado' => $dados['estado'],
                    'pais' => 'BR',
                    'principal' => $principal,
                ];

                $enderecoEntregaId = null;
                if ($enderecoModelApp->create($enderecoEntregaData)) {
                    $enderecoEntregaId = $enderecoModelApp->getConnection()->lastInsertId();
                }

                // Por enquanto, usar o mesmo endereço para cobrança (pode ser separado depois)
                $enderecoCobrancaId = $enderecoEntregaId;

                if (!empty($enderecoEntregaId)) {
                    $stmtUpd = $db->prepare('UPDATE pedidos SET endereco_entrega_id = ?, endereco_cobranca_id = ? WHERE id = ?');
                    $stmtUpd->execute([$enderecoEntregaId, $enderecoCobrancaId, $pedidoId]);
                }
            } catch (\Exception $e) {
                $this->debugLog('[CRIAR_PEDIDO] Falha ao criar/vincular endereco: ' . $e->getMessage());
            }
            
            return $pedidoId;
            
        } catch (\Exception $e) {
            $this->debugLog('[CRIAR_PEDIDO] Erro ao criar pedido: ' . $e->getMessage());
            $this->debugLog('[CRIAR_PEDIDO] Stack: ' . $e->getTraceAsString());
            
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
