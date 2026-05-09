<?php
namespace App\Services;

use App\Models\Live;
use App\Models\LiveProduct;
use App\Models\LiveFeaturedEvent;
use App\Models\LiveOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\StreamingUsage;
use Config\Database;

/**
 * Lógica de negócio do Live Shopping
 * Destaque de produtos, compra 1-clique, cota, freemium
 */
class LiveShoppingService {
    private $pdo;
    private $liveModel;
    private $liveProductModel;
    private $featuredEventModel;
    private $liveOrderModel;
    private $paymentMethodModel;
    private $streamingUsageModel;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->liveModel = new Live();
        $this->liveProductModel = new LiveProduct();
        $this->featuredEventModel = new LiveFeaturedEvent();
        $this->liveOrderModel = new LiveOrder();
        $this->paymentMethodModel = new CustomerPaymentMethod();
        $this->streamingUsageModel = new StreamingUsage();
    }

    /**
     * Destaca um produto na live
     */
    public function featureProduct(int $liveId, int $productId): array {
        // Verificar se produto está na live
        $liveProduct = $this->liveProductModel->getByLiveAndProduct($liveId, $productId);
        if (!$liveProduct) {
            return ['success' => false, 'error' => 'Produto não está nesta live'];
        }

        // Encerrar destaque anterior
        $this->featuredEventModel->endActiveEvent($liveId);

        // Criar novo evento de destaque
        $eventId = $this->featuredEventModel->create([
            'live_id' => $liveId,
            'product_id' => $productId,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        // Atualizar live
        $this->liveModel->setFeaturedProduct($liveId, $productId);

        return [
            'success' => true,
            'event_id' => $eventId,
            'product' => $liveProduct,
        ];
    }

    /**
     * Remove destaque da live
     */
    public function unfeatureProduct(int $liveId): array {
        $this->featuredEventModel->endActiveEvent($liveId);
        $this->liveModel->setFeaturedProduct($liveId, null);

        return ['success' => true];
    }

    /**
     * Compra 1-clique durante a live
     */
    public function buyProduct(int $liveId, int $productId, int $userId, string $idempotencyKey): array {
        // Verificar idempotência
        $existing = $this->liveOrderModel->findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            return ['success' => true, 'order_id' => $existing['order_id'], 'duplicate' => true];
        }

        // Verificar se produto está na live
        $liveProduct = $this->liveProductModel->getByLiveAndProduct($liveId, $productId);
        if (!$liveProduct) {
            return ['success' => false, 'error' => 'Produto não disponível nesta live'];
        }

        // Verificar se live está ativa
        $live = $this->liveModel->find($liveId);
        if (!$live || $live['status'] !== 'live') {
            return ['success' => false, 'error' => 'Live não está ativa'];
        }

        // Pegar cartão default do cliente
        $card = $this->paymentMethodModel->getDefault($userId);
        if (!$card) {
            return ['success' => false, 'error' => 'requires_card', 'code' => 409];
        }

        // Pegar endereço do cliente
        $address = $this->getDefaultAddress($userId);
        if (!$address) {
            return ['success' => false, 'error' => 'requires_address', 'code' => 409];
        }

        // Dados do produto
        $price = (float) $liveProduct['display_price'];
        $name = $liveProduct['display_name'];
        $weight = (float) ($liveProduct['display_weight'] ?? 0);

        if ($price <= 0) {
            return ['success' => false, 'error' => 'Preço inválido'];
        }

        // Calcular frete (simplificado — usar método existente se disponível)
        $frete = $this->calcularFrete($weight, $address);

        $total = $price + $frete;

        try {
            $this->pdo->beginTransaction();

            // Criar pedido na tabela pedidos (mesma estrutura do checkout existente)
            $orderId = $this->criarPedido($userId, $liveProduct, $address, $price, $frete, $total);

            if (!$orderId) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Erro ao criar pedido'];
            }

            // Cobrar via gateway
            $paymentResult = $this->cobrarCartao($card, $total, $orderId);

            if (!$paymentResult['success']) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => $paymentResult['error'] ?? 'Pagamento recusado'];
            }

            // Atualizar status do pedido para pago
            $this->atualizarPedidoPago($orderId, $paymentResult);

            // Registrar em live_orders
            $featuredEvent = $this->featuredEventModel->getActiveEvent($liveId);
            $this->liveOrderModel->create([
                'live_id' => $liveId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'featured_event_id' => $featuredEvent ? $featuredEvent['id'] : null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'total' => $total,
                'frete' => $frete,
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()];
        }
    }

    /**
     * Verifica cota mensal de streaming
     */
    public function checkQuota(): array {
        $config = $this->getConfig();
        $minutosInclusos = (int) ($config['minutos_inclusos'] ?? 300);
        $modoExcedente = $config['modo_excedente'] ?? 'block';

        $usage = $this->streamingUsageModel->getCurrentMonth();
        $minutosUsados = (int) $usage['minutes_streamed'];

        $exceeded = $minutosUsados >= $minutosInclusos;

        return [
            'minutes_used' => $minutosUsados,
            'minutes_included' => $minutosInclusos,
            'exceeded' => $exceeded,
            'mode' => $modoExcedente,
            'can_stream' => !$exceeded || $modoExcedente === 'charge',
        ];
    }

    /**
     * Adiciona minutos usados ao mês
     */
    public function addMinutesUsed(int $minutes): bool {
        return $this->streamingUsageModel->addMinutes($minutes);
    }

    /**
     * Retorna modo de operação do módulo
     */
    public function getOperationMode(): string {
        $config = $this->getConfig();
        return $config['modo_operacao'] ?? 'desligado';
    }

    /**
     * Verifica se o módulo está acessível para o perfil
     */
    public function isAccessible(string $perfil): bool {
        $modo = $this->getOperationMode();

        if ($modo === 'desligado') return false;
        if ($modo === 'teste' && $perfil !== 'admin') return false;
        
        return true; // 'online' → todos
    }

    /**
     * Retorna configurações do módulo
     */
    public function getConfig(): array {
        $stmt = $this->pdo->prepare(
            "SELECT chave, valor FROM configuracoes_sistema WHERE chave LIKE 'lives_%'"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        // Remover prefixo 'lives_' das chaves para uso interno
        $config = [];
        foreach ($rows as $k => $v) {
            $config[str_replace('lives_', '', $k)] = $v;
        }
        return $config;
    }

    /**
     * Salva configurações do módulo
     */
    public function saveConfig(array $config): bool {
        foreach ($config as $chave => $valor) {
            $fullKey = 'lives_' . $chave;
            $stmt = $this->pdo->prepare(
                "INSERT INTO configuracoes_sistema (chave, valor, descricao, tipo) 
                 VALUES (:chave, :valor, '', 'string')
                 ON DUPLICATE KEY UPDATE valor = :valor2"
            );
            $stmt->execute([':chave' => $fullKey, ':valor' => $valor, ':valor2' => $valor]);
        }
        return true;
    }

    // ─── Métodos privados ───────────────────────────────────────────

    private function getDefaultAddress(int $userId): ?array {
        // Buscar endereço padrão do usuário
        $stmt = $this->pdo->prepare(
            "SELECT * FROM usuarios WHERE id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) return null;

        // Verificar se tem endereço preenchido
        $cep = $user['cep'] ?? $user['zipcode'] ?? '';
        $endereco = $user['endereco'] ?? $user['address'] ?? $user['rua'] ?? '';
        $cidade = $user['cidade'] ?? $user['city'] ?? '';

        if (empty($cep) || empty($endereco) || empty($cidade)) {
            // Tentar tabela de endereços separada
            $stmt2 = $this->pdo->prepare(
                "SELECT * FROM enderecos WHERE usuario_id = :uid AND principal = 1 LIMIT 1"
            );
            $stmt2->bindValue(':uid', $userId, \PDO::PARAM_INT);
            try {
                $stmt2->execute();
                $addr = $stmt2->fetch(\PDO::FETCH_ASSOC);
                if ($addr) return $addr;
            } catch (\Exception $e) {
                // Tabela pode não existir
            }

            if (empty($cep)) return null;
        }

        return [
            'cep' => $cep,
            'endereco' => $endereco,
            'numero' => $user['numero'] ?? $user['number'] ?? '',
            'complemento' => $user['complemento'] ?? $user['complement'] ?? '',
            'bairro' => $user['bairro'] ?? $user['neighborhood'] ?? '',
            'cidade' => $cidade,
            'estado' => $user['estado'] ?? $user['state'] ?? $user['uf'] ?? '',
            'pais' => $user['pais'] ?? $user['country'] ?? 'BR',
        ];
    }

    private function calcularFrete(float $weight, array $address): float {
        // Frete simplificado — pode ser integrado com o cálculo existente
        // Por ora, usar valor fixo ou zero se peso for 0
        if ($weight <= 0) return 0;

        // TODO: Integrar com o método de cálculo de frete existente do projeto
        // Por enquanto, retornar 0 (frete grátis na live) ou valor configurável
        return 0;
    }

    private function criarPedido(int $userId, array $product, array $address, float $price, float $frete, float $total): ?int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pedidos (
                usuario_id, status, total, subtotal, frete, 
                endereco_entrega, origem, created_at, updated_at
            ) VALUES (
                :uid, 'paid', :total, :subtotal, :frete,
                :endereco, 'live_shopping', NOW(), NOW()
            )"
        );
        
        $enderecoJson = json_encode($address, JSON_UNESCAPED_UNICODE);
        
        $stmt->execute([
            ':uid' => $userId,
            ':total' => $total,
            ':subtotal' => $price,
            ':frete' => $frete,
            ':endereco' => $enderecoJson,
        ]);

        $orderId = (int) $this->pdo->lastInsertId();
        if ($orderId <= 0) return null;

        // Criar item do pedido
        $stmt2 = $this->pdo->prepare(
            "INSERT INTO pedido_itens (
                pedido_id, produto_id, nome, preco, quantidade, peso, created_at
            ) VALUES (
                :oid, :pid, :nome, :preco, 1, :peso, NOW()
            )"
        );
        $stmt2->execute([
            ':oid' => $orderId,
            ':pid' => $product['product_id'],
            ':nome' => $product['display_name'],
            ':preco' => $price,
            ':peso' => $product['display_weight'] ?? 0,
        ]);

        return $orderId;
    }

    private function cobrarCartao(array $card, float $total, int $orderId): array {
        // Integração com o gateway de pagamento existente
        // Usar PaymentService para cobrar com token
        try {
            $paymentService = new PaymentService();
            
            // Tentar cobrar via gateway configurado
            $gateway = $card['gateway'] ?? '';
            $token = $card['token'] ?? '';

            if (empty($token)) {
                return ['success' => false, 'error' => 'Token de cartão inválido'];
            }

            // TODO: Implementar chamada específica ao gateway com token
            // Por ora, delegar ao PaymentService existente
            // A implementação real depende do gateway (Câmbio Real, Asaas, Stripe)
            
            // Placeholder — será implementado na Task 18
            return ['success' => true, 'gateway_id' => 'pending_integration_' . $orderId];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function atualizarPedidoPago(int $orderId, array $paymentResult): void {
        $stmt = $this->pdo->prepare(
            "UPDATE pedidos SET status = 'paid', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $orderId]);
    }
}
