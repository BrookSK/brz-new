<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Models\Carrinho;
use App\Models\Usuario;
use App\Models\Endereco;
use App\Models\PedidoEcommerce;
use App\Models\AssessoriaOrcamento;

// Garantir que as classes sejam carregadas
require_once __DIR__ . '/../Models/Model.php';
require_once __DIR__ . '/../Models/Endereco.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Carrinho.php';
require_once __DIR__ . '/../Models/PedidoEcommerce.php';
require_once __DIR__ . '/../Models/AssessoriaOrcamento.php';

class CheckoutController extends Controller {
    private $authService;
    private $paymentService;
    private $carrinhoModel;
    private $usuarioModel;
    private $enderecoModel;
    private $pedidoModel;

    private function formatarErroParaUsuario(string $mensagem): string {
        $m = trim($mensagem);

        // Extrair erro do Asaas quando vier como JSON
        if (stripos($m, 'Erro Asaas HTTP') !== false) {
            $jsonPos = strpos($m, '{');
            if ($jsonPos !== false) {
                $jsonStr = substr($m, $jsonPos);
                $decoded = json_decode($jsonStr, true);
                if (is_array($decoded) && !empty($decoded['errors']) && is_array($decoded['errors'])) {
                    $first = $decoded['errors'][0] ?? null;
                    if (is_array($first)) {
                        $desc = (string) ($first['description'] ?? '');
                        if ($desc !== '') {
                            return $desc;
                        }
                    }
                }
            }
        }

        // Remover prefixos técnicos em cadeia
        $prefixes = [
            'Erro ao processar pedido:',
            'Erro ao processar pagamento:',
        ];
        foreach ($prefixes as $p) {
            if (stripos($m, $p) === 0) {
                $m = trim(substr($m, strlen($p)));
            }
        }

        return $m !== '' ? $m : 'Não foi possível processar o pagamento. Tente novamente.';
    }

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

    private function atualizarPagamentoNoPedido(int $pedidoId, array $paymentResult, string $gateway): void {
        try {
            $db = \Config\Database::getConnection();

            $colsP = [];
            try {
                $stmtColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
            }

            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $set = [];
            $params = ['id' => $pedidoId];

            if (in_array('payment_gateway', $colsP, true)) {
                $set[] = 'payment_gateway = :payment_gateway';
                $params['payment_gateway'] = $gateway;
            }

            if (!empty($paymentResult['payment_id']) && in_array('payment_id', $colsP, true)) {
                $set[] = 'payment_id = :payment_id';
                $params['payment_id'] = $paymentResult['payment_id'];
            }

            if (!empty($paymentResult['status']) && in_array('payment_status', $colsP, true)) {
                $set[] = 'payment_status = :payment_status';
                $params['payment_status'] = $paymentResult['status'];
            }

            if (!empty($paymentResult['paid_at']) && in_array('pago_em', $colsP, true)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = $paymentResult['paid_at'];
            }

            // Se o pagamento já veio confirmado/aprovado, atualizar o status do pedido
            $statusPago = false;
            $st = strtoupper((string) ($paymentResult['status'] ?? ''));
            if (in_array($st, ['CONFIRMED', 'RECEIVED', 'APPROVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                $statusPago = true;
            }

            if ($statusPago && in_array('status', $colsP, true)) {
                $set[] = 'status = :status';
                $params['status'] = 'pago';
            }

            if (empty($set)) {
                return;
            }

            $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $e) {
        }
    }

    private function processarPagamentoPedido(int $pedidoId, array $dados, array $usuario, array $pedidoRow): array {
        $forma = (string) ($dados['forma_pagamento'] ?? '');

        $billingType = 'CREDIT_CARD';
        if ($forma === 'pix') {
            $billingType = 'PIX';
        } elseif ($forma === 'boleto') {
            $billingType = 'BOLETO';
        }

        $valor = (float) ($pedidoRow['total'] ?? 0);
        $moeda = (string) ($pedidoRow['moeda'] ?? 'BRL');
        $descricao = 'Pedido #' . (string) ($pedidoRow['numero_pedido'] ?? $pedidoId);

        $payload = [
            'billingType' => $billingType,
            'externalReference' => (string) $pedidoId,
            'customer_name' => (string) ($dados['nome'] ?? ($usuario['nome'] ?? 'Cliente')),
            'customer_email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
            'customer_document' => (string) ($dados['documento'] ?? ''),
            'customer_phone' => (string) ($dados['telefone'] ?? ''),
            'customer_zipcode' => (string) ($dados['cep'] ?? ''),
            'customer_address' => (string) ($dados['endereco'] ?? ''),
            'customer_address_number' => (string) ($dados['numero'] ?? ''),
            'customer_address_complement' => (string) ($dados['complemento'] ?? ''),
            'customer_province' => (string) ($dados['bairro'] ?? ''),
        ];

        if ($billingType === 'CREDIT_CARD') {
            $payload['card_holder_name'] = (string) ($dados['card_holder_name'] ?? '');
            $payload['card_number'] = (string) ($dados['card_number'] ?? '');
            $payload['card_expiry_month'] = (string) ($dados['card_expiry_month'] ?? '');
            $payload['card_expiry_year'] = (string) ($dados['card_expiry_year'] ?? '');
            $payload['card_cvv'] = (string) ($dados['card_cvv'] ?? '');
        }

        $errosPagamento = $this->paymentService->validarDadosPagamento($payload);
        if (!empty($errosPagamento)) {
            throw new \Exception(implode(', ', $errosPagamento));
        }

        $result = $this->paymentService->processarPagamento($payload, $valor, $moeda, $descricao);
        if (empty($result['success'])) {
            throw new \Exception('Falha ao processar pagamento');
        }

        return $result;
    }

    private function registrarPagamentoPedido($pedidoId, $dados) {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pagamentos');
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                return;
            }

            if (!is_array($cols) || empty($cols)) {
                return;
            }

            $stmtPedido = $db->prepare('SELECT total, moeda, forma_pagamento FROM pedidos WHERE id = ? LIMIT 1');
            $stmtPedido->execute([$pedidoId]);
            $pedidoRow = $stmtPedido->fetch(\PDO::FETCH_ASSOC);

            $metodo = $dados['forma_pagamento'] ?? ($pedidoRow['forma_pagamento'] ?? null);
            $statusInicial = 'pendente';

            $insert = [];
            if (in_array('pedido_id', $cols, true)) {
                $insert['pedido_id'] = $pedidoId;
            }

            foreach (['metodo', 'forma_pagamento', 'payment_method', 'tipo'] as $c) {
                if (!empty($metodo) && in_array($c, $cols, true)) {
                    $insert[$c] = $metodo;
                    break;
                }
            }

            foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                if (in_array($c, $cols, true)) {
                    $insert[$c] = $statusInicial;
                    break;
                }
            }

            foreach (['gateway', 'provedor', 'provider'] as $c) {
                if (in_array($c, $cols, true)) {
                    $insert[$c] = ($pedidoRow['moeda'] ?? null) === 'BRL' ? 'asaas' : 'stripe';
                    break;
                }
            }

            if (in_array('valor', $cols, true) && isset($pedidoRow['total'])) {
                $insert['valor'] = $pedidoRow['total'];
            }
            if (in_array('valor_total', $cols, true) && isset($pedidoRow['total'])) {
                $insert['valor_total'] = $pedidoRow['total'];
            }

            if (empty($insert) || !isset($insert['pedido_id'])) {
                return;
            }

            // Se já existir, atualizar; senão, inserir
            $existe = false;
            if (in_array('pedido_id', $cols, true)) {
                try {
                    $stmtExiste = $db->prepare('SELECT 1 FROM pagamentos WHERE pedido_id = ? LIMIT 1');
                    $stmtExiste->execute([$pedidoId]);
                    $existe = (bool) $stmtExiste->fetchColumn();
                } catch (\Exception $e) {
                }
            }

            if ($existe) {
                $setParts = [];
                $params = [];
                foreach ($insert as $k => $v) {
                    if ($k === 'pedido_id') {
                        continue;
                    }
                    $setParts[] = "{$k} = :{$k}";
                    $params[$k] = $v;
                }
                if (!empty($setParts)) {
                    $params['pedido_id'] = $pedidoId;
                    $sql = 'UPDATE pagamentos SET ' . implode(', ', $setParts) . ' WHERE pedido_id = :pedido_id';
                    $stmtUpd = $db->prepare($sql);
                    $stmtUpd->execute($params);
                }
            } else {
                $columns = implode(', ', array_keys($insert));
                $placeholders = ':' . implode(', :', array_keys($insert));
                $sql = "INSERT INTO pagamentos ({$columns}) VALUES ({$placeholders})";
                $stmtIns = $db->prepare($sql);
                foreach ($insert as $k => $v) {
                    $stmtIns->bindValue(':' . $k, $v);
                }
                $stmtIns->execute();
            }
        } catch (\Exception $e) {
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
        $frete = $this->calcularFrete($subtotal, $pesoTotal, $_GET['moeda'] ?? 'BRL');
        $impostos = $subtotal * 0.80;
        $total = $subtotal + $frete + $taxaServico + $impostos;
        
        $this->view('checkout/index', [
            'carrinho' => $carrinho,
            'items' => $items,
            'subtotal' => $subtotal,
            'peso_total' => $pesoTotal,
            'usuario' => $usuario,
            'enderecos' => $usuario ? $this->usuarioModel->getEnderecos($usuario['id']) : [],
            'moeda' => $_GET['moeda'] ?? 'BRL', // Obter moeda da URL ou padrão BRL
            'frete' => $frete,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'total' => $total,
            'frete_gratis' => ($frete == 0)
        ]);
    }

    private function atualizarPagamentoNaTabelaPagamentos(int $pedidoId, array $paymentResult, string $gateway): void {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pagamentos');
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                return;
            }

            if (!is_array($cols) || empty($cols) || !in_array('pedido_id', $cols, true)) {
                return;
            }

            $updates = [];
            $params = ['pedido_id' => $pedidoId];

            foreach (['gateway', 'provedor', 'provider'] as $c) {
                if (in_array($c, $cols, true)) {
                    $updates[] = "$c = :gateway";
                    $params['gateway'] = $gateway;
                    break;
                }
            }

            $statusVal = (string) ($paymentResult['status'] ?? '');
            foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                if (!empty($statusVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :status_pagamento";
                    $params['status_pagamento'] = $statusVal;
                    break;
                }
            }

            $txVal = (string) ($paymentResult['payment_id'] ?? '');
            foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id'] as $c) {
                if (!empty($txVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :transacao";
                    $params['transacao'] = $txVal;
                    break;
                }
            }

            $dataVal = (string) ($paymentResult['paid_at'] ?? '');
            if (empty($dataVal) && (!empty($paymentResult['status']) || !empty($paymentResult['payment_id']))) {
                $dataVal = date('Y-m-d H:i:s');
            }
            foreach (['data_pagamento', 'paid_at', 'data_confirmacao', 'updated_at', 'created_at'] as $c) {
                if (!empty($dataVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :data_pagamento";
                    $params['data_pagamento'] = $dataVal;
                    break;
                }
            }

            if (empty($updates)) {
                return;
            }

            $sql = 'UPDATE pagamentos SET ' . implode(', ', $updates) . ' WHERE pedido_id = :pedido_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $e) {
        }
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
        
        // Salvar endereço automaticamente para futuras compras (quando logado)
        if (!empty($usuario)) {
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

        // Se algum item (principalmente temporário da assessoria) expirou e foi removido do banco, bloquear checkout
        try {
            $db = \Config\Database::getConnection();
            $removedExpired = false;
            foreach ($carrinho as $k => $item) {
                $pid = $item['produto_id'] ?? null;
                if (empty($pid)) {
                    continue;
                }
                try {
                    $stmtP = $db->prepare('SELECT id FROM produtos WHERE id = ? LIMIT 1');
                    $stmtP->execute([(int) $pid]);
                    $exists = $stmtP->fetchColumn();
                    if (!$exists) {
                        unset($_SESSION['carrinho'][$k]);
                        $removedExpired = true;
                    }
                } catch (\Exception $e) {
                }
            }

            if ($removedExpired) {
                $this->json([
                    'error' => 'Alguns itens do carrinho expiraram e foram removidos. Se eram itens da Assessoria, reprocessse o orçamento para gerar novos valores e produtos.'
                ], 400);
                return;
            }
        } catch (\Exception $e) {
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

                // Persistir forma_pagamento no pedido (alguns schemas exibem isso no admin)
                try {
                    $dbFp = \Config\Database::getConnection();
                    $colsPed = [];
                    try {
                        $stmtColsPed = $dbFp->query('DESCRIBE pedidos');
                        $colsPed = $stmtColsPed->fetchAll(\PDO::FETCH_COLUMN);
                    } catch (\Exception $e) {
                    }

                    if (is_array($colsPed) && in_array('forma_pagamento', $colsPed, true)) {
                        $forma = (string) ($dados['forma_pagamento'] ?? '');
                        if ($forma !== '') {
                            $stmtUpdFp = $dbFp->prepare('UPDATE pedidos SET forma_pagamento = ? WHERE id = ?');
                            $stmtUpdFp->execute([$forma, $pedidoId]);
                        }
                    }
                } catch (\Exception $e) {
                }

                // Registrar pagamento (status inicial)
                $this->registrarPagamentoPedido($pedidoId, $dados);

                // Notificar criação do pedido
                try {
                    $this->pedidoModel->dispararEvento('novo_pedido', (int) $pedidoId);
                } catch (\Exception $e) {
                }

                // Processar pagamento (Asaas/Stripe) e persistir referência no pedido
                try {
                    $dbPay = \Config\Database::getConnection();
                    $stmtPedidoPay = $dbPay->prepare('SELECT id, total, moeda, numero_pedido FROM pedidos WHERE id = ? LIMIT 1');
                    $stmtPedidoPay->execute([$pedidoId]);
                    $pedidoRowPay = $stmtPedidoPay->fetch(\PDO::FETCH_ASSOC) ?: [];

                    $payResult = $this->processarPagamentoPedido((int) $pedidoId, $dados, $usuario ?? [], $pedidoRowPay);
                    $gateway = (($pedidoRowPay['moeda'] ?? 'BRL') === 'BRL') ? 'asaas' : 'stripe';
                    $this->atualizarPagamentoNoPedido((int) $pedidoId, $payResult, $gateway);
                    $this->atualizarPagamentoNaTabelaPagamentos((int) $pedidoId, $payResult, $gateway);
                } catch (\Exception $e) {
                    // Se pagamento falhar, manter pedido como aguardando pagamento e retornar erro amigável
                    throw new \Exception('Erro ao processar pagamento: ' . $e->getMessage());
                }

                // Se veio da Assessoria, vincular orçamento ao pedido (pago)
                try {
                    $orcId = (int) ($_SESSION['checkout_assessoria_orcamento_id'] ?? 0);
                    if ($orcId > 0) {
                        $orcModel = new AssessoriaOrcamento();
                        $rowOrc = $orcModel->find($orcId);
                        if (is_array($rowOrc) && !empty($rowOrc['id'])) {
                            $orcModel->update($orcId, [
                                'status' => 'pago',
                                'pedido_id' => (int) $pedidoId,
                                'paid_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                    unset($_SESSION['checkout_assessoria_orcamento_id']);
                } catch (\Exception $e) {
                }
                
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
            $msgUser = $this->formatarErroParaUsuario($e->getMessage());
            $this->json(['error' => 'Erro ao processar pedido: ' . $msgUser], 500);
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

        $paymentDetails = null;
        $pixQrCode = null;
        try {
            if (!empty($pedido['payment_gateway']) && $pedido['payment_gateway'] === 'asaas' && !empty($pedido['payment_id'])) {
                $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['payment_id']);
                if (strtoupper((string) ($paymentDetails['billingType'] ?? '')) === 'PIX') {
                    try {
                        $pixQrCode = $this->paymentService->obterPixQrCodeAsaas((string) $pedido['payment_id']);
                    } catch (\Exception $e) {
                    }
                }
            }
        } catch (\Exception $e) {
        }
        
        $this->view('checkout/conclusao', [
            'pedido' => $pedido,
            'itens' => $this->obterItensPedido($pedidoId),
            'paymentDetails' => $paymentDetails,
            'pixQrCode' => $pixQrCode
        ]);
    }
    
    private function salvarItensPedido($pedidoId, $carrinho) {
        $db = \Config\Database::getConnection();

        // Descobrir colunas disponíveis em pedido_itens (compatibilidade entre schemas)
        $colsItens = [];
        try {
            $stmtCols = $db->query('DESCRIBE pedido_itens');
            $colsItens = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            $colsItens = [];
        }
        
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

            // Buscar dados do produto para persistir no pedido
            $produtoRow = null;
            try {
                $stmtP = $db->prepare('SELECT id, name, nome, sku, url_original FROM produtos WHERE id = ? LIMIT 1');
                $stmtP->execute([$produtoId]);
                $produtoRow = $stmtP->fetch(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $produtoRow = null;
            }

            $nomeProduto = (string) (
                $item['nome'] ??
                $item['name'] ??
                ($item['produto_nome'] ?? null) ??
                ($produtoRow['nome'] ?? ($produtoRow['name'] ?? ''))
            );
            if (trim($nomeProduto) === '') {
                $nomeProduto = 'Produto #' . $produtoId;
            }
            $skuProduto = (string) (
                $item['sku'] ??
                ($item['referencia'] ?? null) ??
                ($produtoRow['sku'] ?? '')
            );
            $urlOriginal = (string) (
                $item['url_original'] ??
                ($item['url'] ?? null) ??
                ($produtoRow['url_original'] ?? '')
            );

            $variacaoId = null;
            $variacaoLabel = null;
            $variacaoAtributos = null;
            if (isset($item['variacao']) && is_array($item['variacao'])) {
                $variacaoId = $item['variacao']['id'] ?? null;
                $variacaoLabel = $item['variacao']['label'] ?? null;
                $variacaoAtributos = $item['variacao']['atributos'] ?? null;
            }
            
            // Verificar diferentes campos de preço
            $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
            $quantidade = $item['quantidade'] ?? 1;
            
            $this->debugLog('[CHECKOUT_ITENS] Preco unitario: ' . $precoUnitario . ', Quantidade: ' . $quantidade);
            
            $cols = ['pedido_id', 'produto_id', 'quantidade', 'preco_unitario', 'subtotal', 'created_at'];
            $vals = [$pedidoId, $produtoId, $quantidade, $precoUnitario, $precoUnitario * $quantidade];
            $placeholders = ['?', '?', '?', '?', '?', 'NOW()'];

            // Campos de auditoria para o admin (se existirem no schema)
            if (is_array($colsItens) && in_array('nome_produto', $colsItens, true)) {
                $cols[] = 'nome_produto';
                $vals[] = $nomeProduto;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('nome_produto_sku', $colsItens, true)) {
                $cols[] = 'nome_produto_sku';
                $vals[] = $skuProduto;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('url_original', $colsItens, true)) {
                $cols[] = 'url_original';
                $vals[] = $urlOriginal;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_id', $colsItens, true)) {
                $cols[] = 'variacao_id';
                $vals[] = $variacaoId;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_label', $colsItens, true)) {
                $cols[] = 'variacao_label';
                $vals[] = $variacaoLabel;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_atributos', $colsItens, true)) {
                $cols[] = 'variacao_atributos';
                $vals[] = (is_array($variacaoAtributos) ? json_encode($variacaoAtributos) : null);
                $placeholders[] = '?';
            }

            $sql = 'INSERT INTO pedido_itens (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute($vals);
            
            $this->debugLog('[CHECKOUT_ITENS] Item inserido: produto_id=' . $produtoId . ', quantidade=' . $quantidade . ', valor=' . ($precoUnitario * $quantidade));
        }
    }
    
    private function salvarDadosCliente($pedidoId, $dados, $usuario) {
        try {
            $db = \Config\Database::getConnection();

            $stmtPedido = $db->prepare('SELECT usuario_id, cliente_id FROM pedidos WHERE id = ? LIMIT 1');
            $stmtPedido->execute([$pedidoId]);
            $pedidoRow = $stmtPedido->fetch(\PDO::FETCH_ASSOC);

            $usuarioId = $pedidoRow['usuario_id'] ?? null;
            $clienteId = $pedidoRow['cliente_id'] ?? null;

            // Atualizar usuario (se existir)
            if (!empty($usuarioId)) {
                $colsU = [];
                try {
                    $stmtColsU = $db->query('DESCRIBE usuarios');
                    $colsU = $stmtColsU->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {
                }

                $setU = [];
                $paramsU = ['id' => $usuarioId];

                if (!empty($dados['email']) && is_array($colsU) && in_array('email', $colsU, true)) {
                    $setU[] = 'email = :email';
                    $paramsU['email'] = $dados['email'];
                }

                if (!empty($dados['nome'])) {
                    if (is_array($colsU) && in_array('nome', $colsU, true)) {
                        $setU[] = 'nome = :nome';
                        $paramsU['nome'] = $dados['nome'];
                    } elseif (is_array($colsU) && in_array('name', $colsU, true)) {
                        $setU[] = 'name = :nome';
                        $paramsU['nome'] = $dados['nome'];
                    }
                }

                if (!empty($dados['telefone'])) {
                    $telefoneCol = null;
                    foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                        if (is_array($colsU) && in_array($c, $colsU, true)) {
                            $telefoneCol = $c;
                            break;
                        }
                    }
                    if (!empty($telefoneCol)) {
                        $setU[] = "{$telefoneCol} = :telefone";
                        $paramsU['telefone'] = $dados['telefone'];
                    }
                }

                if (!empty($dados['documento']) && is_array($colsU) && in_array('documento', $colsU, true)) {
                    $setU[] = 'documento = :documento';
                    $paramsU['documento'] = $dados['documento'];
                }

                if (!empty($setU)) {
                    $sqlU = 'UPDATE usuarios SET ' . implode(', ', $setU) . ' WHERE id = :id';
                    $stmtU = $db->prepare($sqlU);
                    $stmtU->execute($paramsU);
                }
            }

            // Atualizar cliente (se existir)
            if (!empty($clienteId)) {
                $colsC = [];
                try {
                    $stmtColsC = $db->query('DESCRIBE clientes');
                    $colsC = $stmtColsC->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {
                }

                $setC = [];
                $paramsC = ['id' => $clienteId];

                if (!empty($dados['nome'])) {
                    if (is_array($colsC) && in_array('nome_razao_social', $colsC, true)) {
                        $setC[] = 'nome_razao_social = :nome';
                        $paramsC['nome'] = $dados['nome'];
                    } elseif (is_array($colsC) && in_array('nome', $colsC, true)) {
                        $setC[] = 'nome = :nome';
                        $paramsC['nome'] = $dados['nome'];
                    }
                }

                if (!empty($dados['email']) && is_array($colsC) && in_array('email', $colsC, true)) {
                    $setC[] = 'email = :email';
                    $paramsC['email'] = $dados['email'];
                }

                if (!empty($dados['telefone'])) {
                    $telefoneCol = null;
                    foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                        if (is_array($colsC) && in_array($c, $colsC, true)) {
                            $telefoneCol = $c;
                            break;
                        }
                    }
                    if (!empty($telefoneCol)) {
                        $setC[] = "{$telefoneCol} = :telefone";
                        $paramsC['telefone'] = $dados['telefone'];
                    }
                }

                if (!empty($dados['documento'])) {
                    if (is_array($colsC) && in_array('cpf_cnpj', $colsC, true)) {
                        $setC[] = 'cpf_cnpj = :documento';
                        $paramsC['documento'] = $dados['documento'];
                    } elseif (is_array($colsC) && in_array('documento', $colsC, true)) {
                        $setC[] = 'documento = :documento';
                        $paramsC['documento'] = $dados['documento'];
                    }
                }

                if (!empty($setC)) {
                    $sqlC = 'UPDATE clientes SET ' . implode(', ', $setC) . ' WHERE id = :id';
                    $stmtC = $db->prepare($sqlC);
                    $stmtC->execute($paramsC);
                }
            }

            return true;
            
        } catch (\Exception $e) {
            $this->debugLog('[CHECKOUT_DADOS_CLIENTE] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    private function obterPedidoCompleto($pedidoId) {
        $db = \Config\Database::getConnection();

        $enderecoCol = 'endereco';
        try {
            $stmtCols = $db->query('DESCRIBE enderecos');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols)) {
                if (in_array('endereco', $cols, true)) {
                    $enderecoCol = 'endereco';
                } elseif (in_array('logradouro', $cols, true)) {
                    $enderecoCol = 'logradouro';
                }
            }
        } catch (\Exception $e) {
        }

        $usuarioNomeCol = null;
        $usuarioTelefoneCol = null;
        try {
            $stmtColsU = $db->query('DESCRIBE usuarios');
            $colsU = $stmtColsU->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsU)) {
                if (in_array('nome', $colsU, true)) {
                    $usuarioNomeCol = 'nome';
                } elseif (in_array('name', $colsU, true)) {
                    $usuarioNomeCol = 'name';
                }

                foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                    if (in_array($c, $colsU, true)) {
                        $usuarioTelefoneCol = $c;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $clienteTemTabela = false;
        $clienteNomeCol = null;
        $clienteEmailCol = null;
        $clienteTelefoneCol = null;
        try {
            $stmtColsC = $db->query('DESCRIBE clientes');
            $colsC = $stmtColsC->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsC) && !empty($colsC)) {
                $clienteTemTabela = true;
                if (in_array('nome_razao_social', $colsC, true)) {
                    $clienteNomeCol = 'nome_razao_social';
                } elseif (in_array('nome', $colsC, true)) {
                    $clienteNomeCol = 'nome';
                }
                if (in_array('email', $colsC, true)) {
                    $clienteEmailCol = 'email';
                }
                foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                    if (in_array($c, $colsC, true)) {
                        $clienteTelefoneCol = $c;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $uNomeExpr = $usuarioNomeCol ? ("u.{$usuarioNomeCol}") : 'NULL';
        $uTelExpr = $usuarioTelefoneCol ? ("u.{$usuarioTelefoneCol}") : 'NULL';
        $cNomeExpr = ($clienteTemTabela && $clienteNomeCol) ? ("c.{$clienteNomeCol}") : 'NULL';
        $cEmailExpr = ($clienteTemTabela && $clienteEmailCol) ? ("c.{$clienteEmailCol}") : 'NULL';
        $cTelExpr = ($clienteTemTabela && $clienteTelefoneCol) ? ("c.{$clienteTelefoneCol}") : 'NULL';

        $sql = "SELECT 
                    p.*,
                    p.servicos AS taxa_servico,
                    COALESCE({$cNomeExpr}, {$uNomeExpr}, p.nome) AS cliente_nome,
                    COALESCE({$cEmailExpr}, u.email) AS cliente_email,
                    COALESCE({$cTelExpr}, {$uTelExpr}) AS cliente_telefone,
                    e_ent.cep AS cep,
                    e_ent.{$enderecoCol} AS endereco,
                    e_ent.numero AS numero,
                    e_ent.complemento AS complemento,
                    e_ent.bairro AS bairro,
                    e_ent.cidade AS cidade,
                    e_ent.estado AS estado
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                " . ($clienteTemTabela ? " LEFT JOIN clientes c ON p.cliente_id = c.id" : "") . "
                LEFT JOIN enderecos e_ent ON p.endereco_entrega_id = e_ent.id
                WHERE p.id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);

        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($pedido)) {
            $stPag = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? null);
            if (is_string($stPag)) {
                $stPag = strtoupper(trim($stPag));
            }
            if (!empty($stPag) && in_array($stPag, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                $pedido['status'] = 'pago';
            }
        }

        return $pedido;
    }
    
    private function obterItensPedido($pedidoId) {
        $db = \Config\Database::getConnection();

        $produtoNomeCol = null;
        try {
            $stmtCols = $db->query('DESCRIBE produtos');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols)) {
                if (in_array('nome', $cols, true)) {
                    $produtoNomeCol = 'nome';
                } elseif (in_array('name', $cols, true)) {
                    $produtoNomeCol = 'name';
                } elseif (in_array('titulo', $cols, true)) {
                    $produtoNomeCol = 'titulo';
                }
            }
        } catch (\Exception $e) {
        }

        if (!empty($produtoNomeCol)) {
            $sql = "SELECT 
                        pi.*,
                        COALESCE(pi.nome_produto, pr.{$produtoNomeCol}) AS nome
                    FROM pedido_itens pi
                    LEFT JOIN produtos pr ON pi.produto_id = pr.id
                    WHERE pi.pedido_id = ?";
        } else {
            $sql = "SELECT 
                        pi.*,
                        pi.nome_produto AS nome
                    FROM pedido_itens pi
                    WHERE pi.pedido_id = ?";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);

        $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (is_array($itens)) {
            foreach ($itens as &$item) {
                if (empty($item['nome'])) {
                    $produtoId = $item['produto_id'] ?? null;
                    $item['nome'] = !empty($produtoId) ? ('Produto #' . $produtoId) : 'Produto';
                }
            }
        }

        return $itens;
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
                // Valores em BRL (sem conversão fixa para evitar conversão dupla)
                $taxaConversao = 1.0;
                $taxaServico = ceil($pesoTotal) * $this->getTaxaServicoPorKg();
                $impostos = $subtotal * 0.80;
                $frete = $this->calcularFrete($subtotal, $pesoTotal, 'BRL');
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
