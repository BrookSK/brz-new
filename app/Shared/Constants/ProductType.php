<?php
/**
 * @file ProductType.php
 * @responsibilidade Constantes para tipos de produtos
 * @descrição Define os tipos de produtos disponíveis no sistema
 * @conexão Usado por Product, ProductService e validações
 */

namespace App\Shared\Constants;

class ProductType {
    public const PHYSICAL = 'physical';
    public const DIGITAL = 'digital';
    public const SERVICE = 'service';
    public const SUBSCRIPTION = 'subscription';

    /**
     * Retorna todos os tipos disponíveis
     */
    public static function getAll(): array {
        return [
            self::PHYSICAL,
            self::DIGITAL,
            self::SERVICE,
            self::SUBSCRIPTION
        ];
    }

    /**
     * Retorna a descrição de um tipo
     */
    public static function getDescription(string $type): string {
        $descriptions = [
            self::PHYSICAL => 'Produto Físico',
            self::DIGITAL => 'Produto Digital',
            self::SERVICE => 'Serviço',
            self::SUBSCRIPTION => 'Assinatura'
        ];

        return $descriptions[$type] ?? 'Desconhecido';
    }

    /**
     * Retorna o ícone associado ao tipo
     */
    public static function getIcon(string $type): string {
        $icons = [
            self::PHYSICAL => 'fas fa-box',
            self::DIGITAL => 'fas fa-download',
            self::SERVICE => 'fas fa-concierge-bell',
            self::SUBSCRIPTION => 'fas fa-sync-alt'
        ];

        return $icons[$type] ?? 'fas fa-question-circle';
    }

    /**
     * Retorna a cor associada ao tipo
     */
    public static function getColor(string $type): string {
        $colors = [
            self::PHYSICAL => '#007bff',      // Azul
            self::DIGITAL => '#28a745',       // Verde
            self::SERVICE => '#ffc107',       // Amarelo
            self::SUBSCRIPTION => '#6f42c1'   // Roxo
        ];

        return $colors[$type] ?? '#6c757d';
    }

    /**
     * Verifica se um tipo requer estoque
     */
    public static function requiresStock(string $type): bool {
        return in_array($type, [
            self::PHYSICAL
        ]);
    }

    /**
     * Verifica se um tipo requer envio
     */
    public static function requiresShipping(string $type): bool {
        return in_array($type, [
            self::PHYSICAL
        ]);
    }

    /**
     * Verifica se um tipo permite download
     */
    public static function allowsDownload(string $type): bool {
        return in_array($type, [
            self::DIGITAL
        ]);
    }

    /**
     * Verifica se um tipo tem entrega imediata
     */
    public static function hasImmediateDelivery(string $type): bool {
        return in_array($type, [
            self::DIGITAL,
            self::SERVICE
        ]);
    }

    /**
     * Verifica se um tipo permite variações
     */
    public static function allowsVariations(string $type): bool {
        return in_array($type, [
            self::PHYSICAL,
            self::DIGITAL
        ]);
    }

    /**
     * Verifica se um tipo permite avaliações
     */
    public static function allowsReviews(string $type): bool {
        return in_array($type, [
            self::PHYSICAL,
            self::DIGITAL,
            self::SERVICE
        ]);
    }

    /**
     * Verifica se um tipo requer impostos específicos
     */
    public static function requiresSpecialTaxes(string $type): bool {
        return in_array($type, [
            self::DIGITAL,
            self::SERVICE
        ]);
    }

    /**
     * Retorna os campos obrigatórios por tipo
     */
    public static function getRequiredFields(string $type): array {
        $fields = [
            self::PHYSICAL => [
                'name',
                'sku',
                'price',
                'weight',
                'dimensions'
            ],
            self::DIGITAL => [
                'name',
                'sku',
                'price',
                'digital_file'
            ],
            self::SERVICE => [
                'name',
                'sku',
                'price',
                'duration'
            ],
            self::SUBSCRIPTION => [
                'name',
                'sku',
                'price',
                'billing_cycle'
            ]
        ];

        return $fields[$type] ?? [];
    }

    /**
     * Retorna os campos opcionais por tipo
     */
    public static function getOptionalFields(string $type): array {
        $fields = [
            self::PHYSICAL => [
                'description',
                'short_description',
                'images',
                'variations',
                'attributes',
                'tags'
            ],
            self::DIGITAL => [
                'description',
                'short_description',
                'images',
                'preview_file',
                'file_size',
                'file_format'
            ],
            self::SERVICE => [
                'description',
                'short_description',
                'images',
                'requirements',
                'what_includes',
                'schedule'
            ],
            self::SUBSCRIPTION => [
                'description',
                'short_description',
                'images',
                'trial_period',
                'cancellation_policy',
                'renewal_policy'
            ]
        ];

        return $fields[$type] ?? [];
    }

    /**
     * Retorna os tipos disponíveis para seleção em formulários
     */
    public static function getForSelect(): array {
        $types = [];
        foreach (self::getAll() as $type) {
            $types[$type] = self::getDescription($type);
        }
        return $types;
    }

    /**
     * Valida se um tipo é válido
     */
    public static function isValid(string $type): bool {
        return in_array($type, self::getAll());
    }

    /**
     * Retorna o tipo padrão para novos produtos
     */
    public static function getDefault(): string {
        return self::PHYSICAL;
    }

    /**
     * Retorna as configurações de frete por tipo
     */
    public static function getShippingConfig(string $type): array {
        $configs = [
            self::PHYSICAL => [
                'requires_shipping' => true,
                'free_shipping_threshold' => 200.00,
                'shipping_methods' => ['pac', 'sedex', 'transportadora'],
                'calculate_weight' => true,
                'calculate_dimensions' => true
            ],
            self::DIGITAL => [
                'requires_shipping' => false,
                'free_shipping_threshold' => 0,
                'shipping_methods' => ['digital'],
                'calculate_weight' => false,
                'calculate_dimensions' => false
            ],
            self::SERVICE => [
                'requires_shipping' => false,
                'free_shipping_threshold' => 0,
                'shipping_methods' => ['service'],
                'calculate_weight' => false,
                'calculate_dimensions' => false
            ],
            self::SUBSCRIPTION => [
                'requires_shipping' => false,
                'free_shipping_threshold' => 0,
                'shipping_methods' => ['subscription'],
                'calculate_weight' => false,
                'calculate_dimensions' => false
            ]
        ];

        return $configs[$type] ?? [];
    }

    /**
     * Retorna as configurações de impostos por tipo
     */
    public static function getTaxConfig(string $type): array {
        $configs = [
            self::PHYSICAL => [
                'icms' => true,
                'pis' => true,
                'cofins' => true,
                'ipi' => true
            ],
            self::DIGITAL => [
                'icms' => false,
                'pis' => true,
                'cofins' => true,
                'ipi' => false,
                'iss' => true
            ],
            self::SERVICE => [
                'icms' => false,
                'pis' => true,
                'cofins' => true,
                'ipi' => false,
                'iss' => true
            ],
            self::SUBSCRIPTION => [
                'icms' => false,
                'pis' => true,
                'cofins' => true,
                'ipi' => false,
                'iss' => true
            ]
        ];

        return $configs[$type] ?? [];
    }

    /**
     * Retorna as políticas de reembolso por tipo
     */
    public static function getRefundPolicy(string $type): array {
        $policies = [
            self::PHYSICAL => [
                'allowed' => true,
                'days' => 7,
                'conditions' => ['produto_nao_utilizado', 'embalagem_original'],
                'shipping_cost' => 'cliente'
            ],
            self::DIGITAL => [
                'allowed' => true,
                'days' => 7,
                'conditions' => ['nao_baixado'],
                'shipping_cost' => 'nao_aplicavel'
            ],
            self::SERVICE => [
                'allowed' => false,
                'days' => 0,
                'conditions' => [],
                'shipping_cost' => 'nao_aplicavel'
            ],
            self::SUBSCRIPTION => [
                'allowed' => true,
                'days' => 7,
                'conditions' => ['cancelar_antes_proxima_cobranca'],
                'shipping_cost' => 'nao_aplicavel'
            ]
        ];

        return $policies[$type] ?? [];
    }

    /**
     * Retorna as validações específicas por tipo
     */
    public static function getValidations(string $type): array {
        $validations = [
            self::PHYSICAL => [
                'weight' => 'required|min:0.1|max:50000',
                'dimensions' => 'required',
                'stock' => 'required|min:0'
            ],
            self::DIGITAL => [
                'digital_file' => 'required|file|max:500000', // 500MB
                'file_format' => 'required|in:pdf,epub,mp4,zip'
            ],
            self::SERVICE => [
                'duration' => 'required|min:1',
                'requirements' => 'sometimes'
            ],
            self::SUBSCRIPTION => [
                'billing_cycle' => 'required|in:monthly,yearly',
                'trial_period' => 'sometimes|min:1'
            ]
        ];

        return $validations[$type] ?? [];
    }

    /**
     * Retorna os tipos agrupados por categoria
     */
    public static function getByCategory(): array {
        return [
            'produtos' => [
                self::PHYSICAL,
                self::DIGITAL
            ],
            'servicos' => [
                self::SERVICE,
                self::SUBSCRIPTION
            ],
            'digitais' => [
                self::DIGITAL
            ],
            'fisicos' => [
                self::PHYSICAL
            ],
            'recorrentes' => [
                self::SUBSCRIPTION
            ]
        ];
    }

    /**
     * Verifica se um tipo é digital
     */
    public static function isDigital(string $type): bool {
        return in_array($type, [
            self::DIGITAL
        ]);
    }

    /**
     * Verifica se um tipo é físico
     */
    public static function isPhysical(string $type): bool {
        return in_array($type, [
            self::PHYSICAL
        ]);
    }

    /**
     * Verifica se um tipo é um serviço
     */
    public static function isService(string $type): bool {
        return in_array($type, [
            self::SERVICE,
            self::SUBSCRIPTION
        ]);
    }

    /**
     * Verifica se um tipo tem cobrança recorrente
     */
    public static function isRecurring(string $type): bool {
        return $type === self::SUBSCRIPTION;
    }

    /**
     * Retorna as métricas disponíveis por tipo
     */
    public static function getAvailableMetrics(string $type): array {
        $metrics = [
            self::PHYSICAL => [
                'views',
                'sales',
                'revenue',
                'conversion_rate',
                'cart_abandonment',
                'return_rate'
            ],
            self::DIGITAL => [
                'views',
                'sales',
                'revenue',
                'conversion_rate',
                'downloads',
                'refund_rate'
            ],
            self::SERVICE => [
                'views',
                'sales',
                'revenue',
                'conversion_rate',
                'completion_rate',
                'satisfaction_rate'
            ],
            self::SUBSCRIPTION => [
                'views',
                'sales',
                'revenue',
                'conversion_rate',
                'churn_rate',
                'lifetime_value',
                'monthly_recurring_revenue'
            ]
        ];

        return $metrics[$type] ?? [];
    }
}
