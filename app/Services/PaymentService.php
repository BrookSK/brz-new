<?php
namespace App\Services;

use App\Models\PedidoEcommerce;

class PaymentService {
    private $asaasApiKey;
    private $stripeApiKey;
    private $pedidoModel;
    
    public function __construct() {
        $this->pedidoModel = new PedidoEcommerce();
        $this->loadConfigurations();
    }
    
    private function loadConfigurations() {
        $db = \Config\Database::getConnection();
        $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave IN ('asaas_api_key', 'stripe_api_key')");
        $stmt->execute();
        $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($configs as $config) {
            if (strpos($config['valor'], 'asaas') !== false) {
                $this->asaasApiKey = $config['valor'];
            } else {
                $this->stripeApiKey = $config['valor'];
            }
        }
    }
    
    public function processarPagamento($dadosPagamento, $valor, $moeda, $descricao = '') {
        if ($moeda === 'BRL') {
            return $this->processarPagamentoAsaas($dadosPagamento, $valor, $descricao);
        } else {
            return $this->processarPagamentoStripe($dadosPagamento, $valor, $descricao);
        }
    }
    
    private function processarPagamentoAsaas($dados, $valor, $descricao) {
        // Simulação de integração com Asaas
        // Em produção, implementar chamada real à API do Asaas
        
        $payload = [
            'customer' => $dados['customer_id'] ?? null,
            'billingType' => $dados['billingType'] ?? 'CREDIT_CARD',
            'value' => $valor,
            'dueDate' => date('Y-m-d'),
            'description' => $descricao,
            'externalReference' => $dados['externalReference'] ?? null,
            'creditCard' => [
                'holderName' => $dados['card_holder_name'],
                'number' => $dados['card_number'],
                'expiryMonth' => $dados['card_expiry_month'],
                'expiryYear' => $dados['card_expiry_year'],
                'ccv' => $dados['card_cvv']
            ],
            'creditCardHolderInfo' => [
                'name' => $dados['customer_name'],
                'email' => $dados['customer_email'],
                'cpfCnpj' => $dados['customer_document'],
                'postalCode' => $dados['customer_zipcode'],
                'addressNumber' => $dados['customer_address_number'],
                'addressComplement' => $dados['customer_address_complement'] ?? '',
                'mobilePhone' => $dados['customer_phone']
            ]
        ];
        
        // Simulação de resposta
        $response = [
            'success' => true,
            'payment_id' => 'pay_' . uniqid(),
            'status' => 'CONFIRMED',
            'authorization_id' => 'auth_' . uniqid(),
            'amount' => $valor,
            'paid_at' => date('Y-m-d H:i:s')
        ];
        
        return $response;
    }
    
    private function processarPagamentoStripe($dados, $valor, $descricao) {
        // Simulação de integração com Stripe
        // Em produção, implementar chamada real à API do Stripe
        
        // Converter para centavos (Stripe usa centavos)
        $amountCents = intval($valor * 100);
        
        $payload = [
            'amount' => $amountCents,
            'currency' => 'usd',
            'description' => $descricao,
            'payment_method' => $dados['payment_method_id'] ?? null,
            'confirmation_method' => 'manual',
            'confirm' => true
        ];
        
        // Simulação de resposta
        $response = [
            'success' => true,
            'payment_id' => 'pi_' . uniqid(),
            'status' => 'succeeded',
            'charge_id' => 'ch_' . uniqid(),
            'amount' => $valor,
            'paid_at' => date('Y-m-d H:i:s')
        ];
        
        return $response;
    }
    
    public function processarWebhookAsaas($payload) {
        // Validar webhook do Asaas
        $evento = $payload['event'] ?? '';
        $paymentId = $payload['payment']['id'] ?? '';
        $status = $payload['payment']['status'] ?? '';
        
        if ($evento === 'PAYMENT_CONFIRMED' && $status === 'CONFIRMED') {
            $this->confirmarPagamento($paymentId, 'asaas');
        }
        
        return ['status' => 'processed'];
    }
    
    public function processarWebhookStripe($payload) {
        // Validar webhook do Stripe
        $eventType = $payload['type'] ?? '';
        
        if ($eventType === 'payment_intent.succeeded') {
            $paymentId = $payload['data']['object']['id'] ?? '';
            $this->confirmarPagamento($paymentId, 'stripe');
        }
        
        return ['status' => 'processed'];
    }
    
    private function confirmarPagamento($paymentId, $gateway) {
        // Encontrar pedido pelo payment_id
        $db = \Config\Database::getConnection();
        $stmt = $db->prepare("
            SELECT id FROM {$this->pedidoModel->table} 
            WHERE payment_id = :payment_id AND payment_gateway = :gateway
        ");
        $stmt->bindParam(':payment_id', $paymentId);
        $stmt->bindParam(':gateway', $gateway);
        $stmt->execute();
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($pedido) {
            // Atualizar status do pagamento
            $this->pedidoModel->update($pedido['id'], [
                'payment_status' => 'approved',
                'pago_em' => date('Y-m-d H:i:s'),
                'status' => 'pago'
            ]);
            
            // Disparar evento de pagamento aprovado
            $this->pedidoModel->dispararEvento('pagamento_aprovado', $pedido['id']);
        }
    }
    
    public function estornarPagamento($pedidoId, $motivo = '') {
        $pedido = $this->pedidoModel->find($pedidoId);
        
        if (!$pedido || $pedido['payment_status'] !== 'approved') {
            throw new \Exception('Pagamento não pode ser estornado');
        }
        
        if ($pedido['payment_gateway'] === 'asaas') {
            return $this->estornarPagamentoAsaas($pedido['payment_id'], $motivo);
        } else {
            return $this->estornarPagamentoStripe($pedido['payment_id'], $motivo);
        }
    }
    
    private function estornarPagamentoAsaas($paymentId, $motivo) {
        // Simulação de estorno Asaas
        return [
            'success' => true,
            'refund_id' => 'ref_' . uniqid(),
            'amount' => 0,
            'status' => 'REFUNDED'
        ];
    }
    
    private function estornarPagamentoStripe($paymentId, $motivo) {
        // Simulação de estorno Stripe
        return [
            'success' => true,
            'refund_id' => 're_' . uniqid(),
            'amount' => 0,
            'status' => 'succeeded'
        ];
    }
    
    public function criarPaymentMethod($dados, $gateway) {
        if ($gateway === 'asaas') {
            return $this->criarPaymentMethodAsaas($dados);
        } else {
            return $this->criarPaymentMethodStripe($dados);
        }
    }
    
    private function criarPaymentMethodAsaas($dados) {
        // Simulação de criação de payment method Asaas
        return [
            'success' => true,
            'payment_method_id' => 'card_' . uniqid(),
            'brand' => 'MASTERCARD',
            'last4' => '4242'
        ];
    }
    
    private function criarPaymentMethodStripe($dados) {
        // Simulação de criação de payment method Stripe
        return [
            'success' => true,
            'payment_method_id' => 'pm_' . uniqid(),
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2025
            ]
        ];
    }
    
    public function validarDadosPagamento($dados) {
        $erros = [];
        
        // Validações comuns
        if (empty($dados['customer_name'])) {
            $erros[] = 'Nome do cliente é obrigatório';
        }
        
        if (empty($dados['customer_email'])) {
            $erros[] = 'E-mail do cliente é obrigatório';
        } elseif (!filter_var($dados['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        if (empty($dados['customer_document'])) {
            $erros[] = 'Documento do cliente é obrigatório';
        }
        
        if (empty($dados['customer_phone'])) {
            $erros[] = 'Telefone do cliente é obrigatório';
        }
        
        // Validações de cartão (se aplicável)
        if (isset($dados['billingType']) && $dados['billingType'] === 'CREDIT_CARD') {
            if (empty($dados['card_holder_name'])) {
                $erros[] = 'Nome no cartão é obrigatório';
            }
            
            if (empty($dados['card_number'])) {
                $erros[] = 'Número do cartão é obrigatório';
            } elseif (!$this->validarNumeroCartao($dados['card_number'])) {
                $erros[] = 'Número do cartão inválido';
            }
            
            if (empty($dados['card_expiry_month']) || empty($dados['card_expiry_year'])) {
                $erros[] = 'Data de validade do cartão é obrigatória';
            } elseif (!$this->validarValidadeCartao($dados['card_expiry_month'], $dados['card_expiry_year'])) {
                $erros[] = 'Cartão expirado';
            }
            
            if (empty($dados['card_cvv'])) {
                $erros[] = 'CVV do cartão é obrigatório';
            } elseif (!preg_match('/^\d{3,4}$/', $dados['card_cvv'])) {
                $erros[] = 'CVV inválido';
            }
        }
        
        return $erros;
    }
    
    private function validarNumeroCartao($numero) {
        // Remover espaços e caracteres não numéricos
        $numero = preg_replace('/\D/', '', $numero);
        
        // Verificar se tem entre 13 e 19 dígitos
        if (strlen($numero) < 13 || strlen($numero) > 19) {
            return false;
        }
        
        // Algoritmo de Luhn
        $soma = 0;
        $alternar = false;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = intval($numero[$i]);
            
            if ($alternar) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            
            $soma += $digito;
            $alternar = !$alternar;
        }
        
        return $soma % 10 === 0;
    }
    
    private function validarValidadeCartao($mes, $ano) {
        $mes = intval($mes);
        $ano = intval($ano);
        $anoAtual = date('Y');
        $mesAtual = date('n');
        
        if ($ano < $anoAtual) {
            return false;
        }
        
        if ($ano == $anoAtual && $mes < $mesAtual) {
            return false;
        }
        
        return $mes >= 1 && $mes <= 12;
    }
}
