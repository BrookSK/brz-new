<?php
/**
 * @file ProductRepositoryInterface.php
 * @responsibilidade Interface para Repository de Produtos
 * @descrição Define o contrato para operações com produtos no banco de dados
 * @conexão Implementada por MySQLProductRepository, usada por ProductService
 */

namespace App\Core\Repositories;

use App\Core\Domain\Product;
use App\Core\ValueObjects\Money;

interface ProductRepositoryInterface {
    /**
     * Salva um produto (cria ou atualiza)
     */
    public function save(Product $product): Product;

    /**
     * Busca produto por ID
     */
    public function findById(int $id): ?Product;

    /**
     * Busca produto por SKU
     */
    public function findBySku(string $sku): ?Product;

    /**
     * Busca produto por slug
     */
    public function findBySlug(string $slug): ?Product;

    /**
     * Lista produtos com paginação e filtros
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array;

    /**
     * Conta total de produtos com filtros
     */
    public function count(array $filters = []): int;

    /**
     * Verifica se SKU já existe
     */
    public function skuExists(string $sku, ?int $excludeId = null): bool;

    /**
     * Verifica se slug já existe
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool;

    /**
     * Ativa ou desativa produto
     */
    public function setActive(int $id, bool $active): bool;

    /**
     * Define produto como destaque
     */
    public function setFeatured(int $id, bool $featured): bool;

    /**
     * Remove produto
     */
    public function delete(int $id): bool;

    /**
     * Busca produtos por categoria
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por tipo
     */
    public function findByType(string $type, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por status
     */
    public function findByStatus(string $status, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos ativos
     */
    public function findActive(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos em destaque
     */
    public function findFeatured(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos com baixo estoque
     */
    public function findLowStock(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos sem estoque
     */
    public function findOutOfStock(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por nome ou descrição
     */
    public function search(string $term, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por faixa de preço
     */
    public function findByPriceRange(Money $minPrice, Money $maxPrice, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos relacionados
     */
    public function findRelated(int $productId, int $limit = 5): array;

    /**
     * Busca produtos mais vendidos
     */
    public function findBestSellers(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos mais visualizados
     */
    public function findMostViewed(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos recentes
     */
    public function findRecent(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos em promoção
     */
    public function findOnSale(int $limit = 10, int $offset = 0): array;

    /**
     * Atualiza estoque do produto
     */
    public function updateStock(int $id, int $quantity): bool;

    /**
     * Adiciona ao estoque
     */
    public function addStock(int $id, int $quantity): bool;

    /**
     * Remove do estoque
     */
    public function removeStock(int $id, int $quantity): bool;

    /**
     * Atualiza preço do produto
     */
    public function updatePrice(int $id, Money $price): bool;

    /**
     * Atualiza status do produto
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Incrementa visualizações do produto
     */
    public function incrementViews(int $id): bool;

    /**
     * Obtém estatísticas de produtos
     */
    public function getStatistics(): array;

    /**
     * Obtém métricas de um produto específico
     */
    public function getMetrics(int $id): array;

    /**
     * Busca produtos criados em um período
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array;

    /**
     * Busca produtos atualizados em um período
     */
    public function findByUpdatedRange(\DateTime $startDate, \DateTime $endDate): array;

    /**
     * Exporta produtos para CSV/Excel
     */
    public function export(array $filters = []): array;

    /**
     * Importa produtos de arquivo
     */
    public function import(array $products): array;

    /**
     * Busca produtos por tags
     */
    public function findByTags(array $tags, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por atributos
     */
    public function findByAttributes(array $attributes, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos com variações
     */
    public function findWithVariations(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos digitais
     */
    public function findDigital(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos físicos
     */
    public function findPhysical(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por peso
     */
    public function findByWeightRange(float $minWeight, float $maxWeight, int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por dimensões
     */
    public function findByDimensions(float $maxLength, float $maxWidth, float $maxHeight, int $limit = 10, int $offset = 0): array;

    /**
     * Obtém produtos para sitemap
     */
    public function getSitemapProducts(): array;

    /**
     * Obtém produtos para feed RSS
     */
    public function getFeedProducts(int $limit = 50): array;

    /**
     * Busca produtos com reviews
     */
    public function findWithReviews(int $limit = 10, int $offset = 0): array;

    /**
     * Busca produtos por rating médio
     */
    public function findByRating(float $minRating, float $maxRating, int $limit = 10, int $offset = 0): array;

    /**
     * Verifica se produto tem estoque suficiente
     */
    public function hasStock(int $id, int $quantity): bool;

    /**
     * Reserva estoque para pedido
     */
    public function reserveStock(int $id, int $quantity): bool;

    /**
     * Libera estoque reservado
     */
    public function releaseStock(int $id, int $quantity): bool;

    /**
     * Confirma retirada de estoque
     */
    public function confirmStockWithdrawal(int $id, int $quantity): bool;

    /**
     * Obtém histórico de alterações de estoque
     */
    public function getStockHistory(int $id, int $limit = 50): array;

    /**
     * Busca produtos com alertas de estoque
     */
    public function findWithStockAlerts(): array;

    /**
     * Atualiza múltiplos produtos
     */
    public function updateMultiple(array $updates): array;

    /**
     * Duplica produto
     */
    public function duplicate(int $id, array $overrides = []): ?Product;

    /**
     * Obtém categorias de produtos
     */
    public function getProductCategories(int $productId): array;

    /**
     * Adiciona categoria ao produto
     */
    public function addCategory(int $productId, int $categoryId): bool;

    /**
     * Remove categoria do produto
     */
    public function removeCategory(int $productId, int $categoryId): bool;

    /**
     * Atualiza categorias do produto
     */
    public function updateCategories(int $productId, array $categoryIds): bool;

    /**
     * Obtém imagens do produto
     */
    public function getImages(int $id): array;

    /**
     * Adiciona imagem ao produto
     */
    public function addImage(int $id, array $image): bool;

    /**
     * Remove imagem do produto
     */
    public function removeImage(int $id, int $imageId): bool;

    /**
     * Atualiza ordem das imagens
     */
    public function updateImageOrder(int $id, array $imageOrders): bool;

    /**
     * Define imagem principal
     */
    public function setMainImage(int $id, int $imageId): bool;

    /**
     * Obtém variações do produto
     */
    public function getVariations(int $id): array;

    /**
     * Adiciona variação ao produto
     */
    public function addVariation(int $id, array $variation): bool;

    /**
     * Atualiza variação do produto
     */
    public function updateVariation(int $id, int $variationId, array $variation): bool;

    /**
     * Remove variação do produto
     */
    public function removeVariation(int $id, int $variationId): bool;

    /**
     * Obtém atributos do produto
     */
    public function getAttributes(int $id): array;

    /**
     * Adiciona atributo ao produto
     */
    public function addAttribute(int $id, array $attribute): bool;

    /**
     * Atualiza atributo do produto
     */
    public function updateAttribute(int $id, int $attributeId, array $attribute): bool;

    /**
     * Remove atributo do produto
     */
    public function removeAttribute(int $id, int $attributeId): bool;

    /**
     * Obtém tags do produto
     */
    public function getTags(int $id): array;

    /**
     * Adiciona tag ao produto
     */
    public function addTag(int $id, string $tag): bool;

    /**
     * Remove tag do produto
     */
    public function removeTag(int $id, string $tag): bool;

    /**
     * Atualiza tags do produto
     */
    public function updateTags(int $id, array $tags): bool;

    /**
     * Busca produtos com filtros avançados
     */
    public function findWithAdvancedFilters(array $filters, int $limit = 10, int $offset = 0): array;

    /**
     * Obtém sugestões de busca
     */
    public function getSearchSuggestions(string $term, int $limit = 10): array;

    /**
     * Busca produtos similares
     */
    public function findSimilar(int $productId, int $limit = 5): array;

    /**
     * Obtém produtos frequentemente comprados juntos
     */
    public function findFrequentlyBoughtTogether(int $productId, int $limit = 5): array;

    /**
     * Obtém histórico de preços
     */
    public function getPriceHistory(int $id, int $limit = 50): array;

    /**
     * Registra alteração de preço
     */
    public function recordPriceChange(int $id, Money $oldPrice, Money $newPrice): bool;

    /**
     * Verifica se produto está disponível para venda
     */
    public function isAvailableForSale(int $id): bool;

    /**
     * Obtém produtos em destaque por categoria
     */
    public function findFeaturedByCategory(int $categoryId, int $limit = 5): array;

    /**
     * Obtém produtos em promoção por categoria
     */
    public function findOnSaleByCategory(int $categoryId, int $limit = 5): array;

    /**
     * Busca produtos por fornecedor
     */
    public function findBySupplier(int $supplierId, int $limit = 10, int $offset = 0): array;

    /**
     * Atualiza fornecedor do produto
     */
    public function updateSupplier(int $id, int $supplierId): bool;

    /**
     * Obtém produtos com baixo giro
     */
    public function findSlowMoving(int $days = 90, int $limit = 10): array;

    /**
     * Calcula valor total do estoque
     */
    public function calculateTotalStockValue(): Money;

    /**
     * Obtém produtos que precisam de reposição
     */
    public function findNeedingRestock(): array;

    /**
     * Gera relatório de produtos
     */
    public function generateReport(array $filters = []): array;

    /**
     * Valida dados do produto antes de salvar
     */
    public function validateProductData(array $data): array;

    /**
     * Sanitiza dados do produto
     */
    public function sanitizeProductData(array $data): array;
}
