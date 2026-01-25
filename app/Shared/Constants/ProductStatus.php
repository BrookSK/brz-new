<?php
/**
 * @file ProductStatus.php
 * @responsibilidade Constantes para status de produtos
 * @descrição Define os possíveis status de um produto no sistema
 * @conexão Usado por Product, ProductService e validações
 */

namespace App\Shared\Constants;

class ProductStatus {
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';
    public const OUT_OF_STOCK = 'out_of_stock';
    public const DISCONTINUED = 'discontinued';
    public const PENDING_REVIEW = 'pending_review';
    public const REJECTED = 'rejected';

    /**
     * Retorna todos os status disponíveis
     */
    public static function getAll(): array {
        return [
            self::DRAFT,
            self::PUBLISHED,
            self::ARCHIVED,
            self::OUT_OF_STOCK,
            self::DISCONTINUED,
            self::PENDING_REVIEW,
            self::REJECTED
        ];
    }

    /**
     * Retorna a descrição de um status
     */
    public static function getDescription(string $status): string {
        $descriptions = [
            self::DRAFT => 'Rascunho',
            self::PUBLISHED => 'Publicado',
            self::ARCHIVED => 'Arquivado',
            self::OUT_OF_STOCK => 'Sem Estoque',
            self::DISCONTINUED => 'Descontinuado',
            self::PENDING_REVIEW => 'Aguardando Aprovação',
            self::REJECTED => 'Rejeitado'
        ];

        return $descriptions[$status] ?? 'Desconhecido';
    }

    /**
     * Retorna a cor associada ao status
     */
    public static function getColor(string $status): string {
        $colors = [
            self::DRAFT => '#6c757d',      // Cinza
            self::PUBLISHED => '#28a745',  // Verde
            self::ARCHIVED => '#17a2b8',   // Azul claro
            self::OUT_OF_STOCK => '#dc3545', // Vermelho
            self::DISCONTINUED => '#6f42c1', // Roxo
            self::PENDING_REVIEW => '#ffc107', // Amarelo
            self::REJECTED => '#fd7e14'     // Laranja
        ];

        return $colors[$status] ?? '#6c757d';
    }

    /**
     * Retorna o ícone associado ao status
     */
    public static function getIcon(string $status): string {
        $icons = [
            self::DRAFT => 'fas fa-file-alt',
            self::PUBLISHED => 'fas fa-check-circle',
            self::ARCHIVED => 'fas fa-archive',
            self::OUT_OF_STOCK => 'fas fa-times-circle',
            self::DISCONTINUED => 'fas fa-ban',
            self::PENDING_REVIEW => 'fas fa-clock',
            self::REJECTED => 'fas fa-times'
        ];

        return $icons[$status] ?? 'fas fa-question-circle';
    }

    /**
     * Verifica se um status permite venda
     */
    public static function canSell(string $status): bool {
        return in_array($status, [
            self::PUBLISHED
        ]);
    }

    /**
     * Verifica se um status permite edição
     */
    public static function canEdit(string $status): bool {
        return in_array($status, [
            self::DRAFT,
            self::PUBLISHED,
            self::PENDING_REVIEW,
            self::REJECTED
        ]);
    }

    /**
     * Verifica se um status permite exclusão
     */
    public static function canDelete(string $status): bool {
        return in_array($status, [
            self::DRAFT,
            self::REJECTED
        ]);
    }

    /**
     * Verifica se um produto está ativo (visível para clientes)
     */
    public static function isActive(string $status): bool {
        return in_array($status, [
            self::PUBLISHED
        ]);
    }

    /**
     * Verifica se um status é final (não pode mais ser alterado)
     */
    public static function isFinal(string $status): bool {
        return in_array($status, [
            self::DISCONTINUED
        ]);
    }

    /**
     * Retorna os próximos status possíveis
     */
    public static function getNextStatuses(string $currentStatus): array {
        $transitions = [
            self::DRAFT => [
                self::PUBLISHED,
                self::PENDING_REVIEW,
                self::ARCHIVED
            ],
            self::PUBLISHED => [
                self::ARCHIVED,
                self::OUT_OF_STOCK,
                self::DISCONTINUED
            ],
            self::OUT_OF_STOCK => [
                self::PUBLISHED,
                self::ARCHIVED,
                self::DISCONTINUED
            ],
            self::PENDING_REVIEW => [
                self::PUBLISHED,
                self::REJECTED,
                self::DRAFT
            ],
            self::REJECTED => [
                self::DRAFT,
                self::ARCHIVED
            ],
            self::ARCHIVED => [
                self::DRAFT,
                self::PUBLISHED
            ],
            self::DISCONTINUED => [] // Status final
        ];

        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Verifica se uma transição é válida
     */
    public static function canTransition(string $from, string $to): bool {
        return in_array($to, self::getNextStatuses($from));
    }

    /**
     * Retorna os status disponíveis para seleção em formulários
     */
    public static function getForSelect(): array {
        $statuses = [];
        foreach (self::getAll() as $status) {
            $statuses[$status] = self::getDescription($status);
        }
        return $statuses;
    }

    /**
     * Retorna os status agrupados por categoria
     */
    public static function getByCategory(): array {
        return [
            'ativos' => [
                self::PUBLISHED
            ],
            'inativos' => [
                self::DRAFT,
                self::ARCHIVED,
                self::OUT_OF_STOCK,
                self::DISCONTINUED,
                self::PENDING_REVIEW,
                self::REJECTED
            ],
            'temporarios' => [
                self::DRAFT,
                self::OUT_OF_STOCK,
                self::PENDING_REVIEW
            ],
            'finais' => [
                self::DISCONTINUED
            ]
        ];
    }

    /**
     * Valida se um status é válido
     */
    public static function isValid(string $status): bool {
        return in_array($status, self::getAll());
    }

    /**
     * Retorna o status padrão para novos produtos
     */
    public static function getDefault(): string {
        return self::DRAFT;
    }

    /**
     * Retorna o status para produtos sem estoque
     */
    public static function getOutOfStockStatus(): string {
        return self::OUT_OF_STOCK;
    }

    /**
     * Aplica regras automáticas de status baseado no estoque
     */
    public static function getAutoStatus(string $currentStatus, ?int $stock): string {
        if ($currentStatus === self::PUBLISHED && ($stock === null || $stock <= 0)) {
            return self::OUT_OF_STOCK;
        }

        if ($currentStatus === self::OUT_OF_STOCK && $stock > 0) {
            return self::PUBLISHED;
        }

        return $currentStatus;
    }

    /**
     * Retorna estatísticas de status para dashboard
     */
    public static function getStatisticsLabels(): array {
        return [
            'draft' => 'Rascunhos',
            'published' => 'Publicados',
            'out_of_stock' => 'Sem Estoque',
            'archived' => 'Arquivados',
            'discontinued' => 'Descontinuados',
            'pending_review' => 'Aguardando Aprovação',
            'rejected' => 'Rejeitados'
        ];
    }

    /**
     * Verifica se um produto precisa de atenção especial
     */
    public static function needsAttention(string $status): bool {
        return in_array($status, [
            self::OUT_OF_STOCK,
            self::PENDING_REVIEW,
            self::REJECTED
        ]);
    }

    /**
     * Retorna a prioridade de ordenação do status
     */
    public static function getPriority(string $status): int {
        $priorities = [
            self::PUBLISHED => 1,
            self::OUT_OF_STOCK => 2,
            self::PENDING_REVIEW => 3,
            self::DRAFT => 4,
            self::REJECTED => 5,
            self::ARCHIVED => 6,
            self::DISCONTINUED => 7
        ];

        return $priorities[$status] ?? 99;
    }

    /**
     * Ordena uma lista de produtos por prioridade de status
     */
    public static function sortByPriority(array $products): array {
        usort($products, function ($a, $b) {
            $priorityA = self::getPriority($a['status']);
            $priorityB = self::getPriority($b['status']);
            return $priorityA <=> $priorityB;
        });

        return $products;
    }
}
