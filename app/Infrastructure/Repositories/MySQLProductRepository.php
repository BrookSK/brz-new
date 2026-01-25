<?php
/**
 * @file MySQLProductRepository.php
 * @responsibilidade Implementação MySQL do Repository de Produtos
 * @descrição Persistência de produtos no banco MySQL
 */

namespace App\Infrastructure\Repositories;

use App\Core\Repositories\ProductRepositoryInterface;
use App\Core\Domain\Product;
use App\Core\ValueObjects\Money;
use App\Shared\Constants\ProductStatus;
use App\Shared\Constants\ProductType;
use PDO;

class MySQLProductRepository implements ProductRepositoryInterface {
    private PDO $connection;

    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }

    public function save(Product $product): Product {
        try {
            $this->connection->beginTransaction();
            $data = $product->toArray();

            if ($product->getId() === null) {
                $sql = "INSERT INTO produtos (name, slug, sku, description, short_description, price, cost_price, sale_price, stock, min_stock, max_stock, length, width, height, weight, type, status, category_id, tags, images, variations, attributes, active, featured, digital, digital_file, digital_downloads, created_at, updated_at) VALUES (:name, :slug, :sku, :description, :short_description, :price, :cost_price, :sale_price, :stock, :min_stock, :max_stock, :length, :width, :height, :weight, :type, :status, :category_id, :tags, :images, :variations, :attributes, :active, :featured, :digital, :digital_file, :digital_downloads, :created_at, :updated_at)";
                $stmt = $this->connection->prepare($sql);
                $this->bindParams($stmt, $data);
                $stmt->execute();
                $product->setId($this->connection->lastInsertId());
            } else {
                $sql = "UPDATE produtos SET name=:name, slug=:slug, sku=:sku, description=:description, short_description=:short_description, price=:price, cost_price=:cost_price, sale_price=:sale_price, stock=:stock, min_stock=:min_stock, max_stock=:max_stock, length=:length, width=:width, height=:height, weight=:weight, type=:type, status=:status, category_id=:category_id, tags=:tags, images=:images, variations=:variations, attributes=:attributes, active=:active, featured=:featured, digital=:digital, digital_file=:digital_file, digital_downloads=:digital_downloads, updated_at=:updated_at WHERE id=:id";
                $stmt = $this->connection->prepare($sql);
                $data['id'] = $product->getId();
                $this->bindParams($stmt, $data);
                $stmt->execute();
            }

            $this->connection->commit();
            return $product;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw new \RuntimeException("Erro ao salvar produto: " . $e->getMessage());
        }
    }

    public function findById(int $id): ?Product {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrate($data) : null;
    }

    public function findBySku(string $sku): ?Product {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE sku = ?");
        $stmt->execute([$sku]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrate($data) : null;
    }

    public function findBySlug(string $slug): ?Product {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE slug = ?");
        $stmt->execute([$slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrate($data) : null;
    }

    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM produtos WHERE 1=1";
        $params = [];
        
        $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([...$params, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM produtos WHERE 1=1";
        $params = [];
        $this->applyFilters($sql, $params, $filters);
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM produtos WHERE sku = ?";
        $params = [$sku];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM produtos WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function setActive(int $id, bool $active): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET active = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$active, $id]);
    }

    public function setFeatured(int $id, bool $featured): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET featured = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$featured, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET active = 0, status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([ProductStatus::ARCHIVED, $id]);
    }

    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE category_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$categoryId, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findByType(string $type, int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE type = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$type, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findActive(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findFeatured(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE featured = 1 AND active = 1 AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findLowStock(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND type = ? AND stock <= min_stock AND stock > 0 ORDER BY stock ASC LIMIT ? OFFSET ?");
        $stmt->execute([ProductType::PHYSICAL, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findOutOfStock(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND type = ? AND stock <= 0 ORDER BY updated_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductType::PHYSICAL, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function search(string $term, int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE (name LIKE ? OR description LIKE ? OR sku LIKE ?) AND active = 1 ORDER BY name ASC LIMIT ? OFFSET ?");
        $searchTerm = "%{$term}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findByPriceRange(Money $minPrice, Money $maxPrice, int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND status = ? AND (CASE WHEN sale_price IS NOT NULL THEN sale_price ELSE price END) BETWEEN ? AND ? ORDER BY price ASC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $minPrice->getValue(), $maxPrice->getValue(), $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findRelated(int $productId, int $limit = 5): array {
        $product = $this->findById($productId);
        if (!$product) return [];
        
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE id != ? AND active = 1 AND status = ? AND (category_id = ? OR type = ?) ORDER BY RAND() LIMIT ?");
        $stmt->execute([$productId, ProductStatus::PUBLISHED, $product->getCategoryId(), $product->getType(), $limit]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findBestSellers(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT p.*, SUM(ip.quantidade) as total FROM produtos p LEFT JOIN itens_pedido ip ON p.id = ip.produto_id LEFT JOIN pedidos ped ON ip.pedido_id = ped.id WHERE p.active = 1 AND p.status = ? AND ped.status IN ('pago', 'enviado', 'entregue') AND ped.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY p.id HAVING total > 0 ORDER BY total DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findMostViewed(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND status = ? ORDER BY views DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findRecent(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findOnSale(int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE active = 1 AND status = ? AND sale_price IS NOT NULL AND sale_price < price ORDER BY (price - sale_price) DESC LIMIT ? OFFSET ?");
        $stmt->execute([ProductStatus::PUBLISHED, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function updateStock(int $id, int $quantity): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET stock = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }

    public function addStock(int $id, int $quantity): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET stock = stock + ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }

    public function removeStock(int $id, int $quantity): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET stock = GREATEST(0, stock - ?), updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }

    public function updatePrice(int $id, Money $price): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET price = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$price->getValue(), $id]);
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function incrementViews(int $id): bool {
        $stmt = $this->connection->prepare("UPDATE produtos SET views = COALESCE(views, 0) + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStatistics(): array {
        $stats = [];
        
        $stmt = $this->connection->query("SELECT COUNT(*) as total FROM produtos");
        $stats['total'] = (int) $stmt->fetchColumn();
        
        $stmt = $this->connection->query("SELECT COUNT(*) as active FROM produtos WHERE active = 1");
        $stats['active'] = (int) $stmt->fetchColumn();
        
        $stmt = $this->connection->query("SELECT status, COUNT(*) as count FROM produtos GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stmt = $this->connection->query("SELECT type, COUNT(*) as count FROM produtos GROUP BY type");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }

    public function getMetrics(int $id): array {
        $stmt = $this->connection->prepare("SELECT views, (SELECT COUNT(DISTINCT pedido_id) FROM itens_pedido WHERE produto_id = ?) as orders, (SELECT SUM(quantidade) FROM itens_pedido WHERE produto_id = ?) as sold FROM produtos WHERE id = ?");
        $stmt->execute([$id, $id, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->execute([$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function export(array $filters = []): array {
        $sql = "SELECT id, name, sku, slug, description, short_description, price, cost_price, sale_price, stock, type, status, category_id, active, featured, digital, created_at, updated_at FROM produtos WHERE 1=1";
        $params = [];
        $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Métodos auxiliares
    private function bindParams($stmt, array $data): void {
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':slug', $data['slug']);
        $stmt->bindValue(':sku', $data['sku']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':short_description', $data['short_description']);
        $stmt->bindValue(':price', $data['price']);
        $stmt->bindValue(':cost_price', $data['cost_price']);
        $stmt->bindValue(':sale_price', $data['sale_price']);
        $stmt->bindValue(':stock', $data['stock']);
        $stmt->bindValue(':min_stock', $data['min_stock']);
        $stmt->bindValue(':max_stock', $data['max_stock']);
        $stmt->bindValue(':length', $data['length']);
        $stmt->bindValue(':width', $data['width']);
        $stmt->bindValue(':height', $data['height']);
        $stmt->bindValue(':weight', $data['weight']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':category_id', $data['category_id']);
        $stmt->bindValue(':tags', json_encode($data['tags']));
        $stmt->bindValue(':images', json_encode($data['images']));
        $stmt->bindValue(':variations', json_encode($data['variations']));
        $stmt->bindValue(':attributes', json_encode($data['attributes']));
        $stmt->bindValue(':active', $data['active'], PDO::PARAM_BOOL);
        $stmt->bindValue(':featured', $data['featured'], PDO::PARAM_BOOL);
        $stmt->bindValue(':digital', $data['digital'], PDO::PARAM_BOOL);
        $stmt->bindValue(':digital_file', $data['digital_file']);
        $stmt->bindValue(':digital_downloads', $data['digital_downloads']);
        $stmt->bindValue(':created_at', $data['created_at']);
        $stmt->bindValue(':updated_at', $data['updated_at']);
        
        if (isset($data['id'])) {
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
        }
    }

    private function applyFilters(string &$sql, array &$params, array $filters): void {
        if (!empty($filters['name'])) {
            $sql .= " AND name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }
        if (!empty($filters['sku'])) {
            $sql .= " AND sku LIKE ?";
            $params[] = "%{$filters['sku']}%";
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['active'])) {
            $sql .= " AND active = ?";
            $params[] = $filters['active'];
        }
    }

    private function hydrate(array $data): Product {
        return Product::fromArray($data);
    }

    // Implementações básicas dos métodos restantes
    public function findByStatus(string $status, int $limit = 10, int $offset = 0): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$status, $limit, $offset]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function hasStock(int $id, int $quantity): bool {
        $product = $this->findById($id);
        return $product && $product->hasStock() && $product->getStock() >= $quantity;
    }

    public function reserveStock(int $id, int $quantity): bool {
        return $this->removeStock($id, $quantity);
    }

    public function releaseStock(int $id, int $quantity): bool {
        return $this->addStock($id, $quantity);
    }

    public function confirmStockWithdrawal(int $id, int $quantity): bool {
        return true; // Já foi removido na reserva
    }

    public function getStockHistory(int $id, int $limit = 50): array {
        return []; // Implementar se necessário
    }

    public function findWithStockAlerts(): array {
        return array_merge($this->findLowStock(100, 0), $this->findOutOfStock(100, 0));
    }

    public function updateMultiple(array $updates): array {
        return []; // Implementar se necessário
    }

    public function duplicate(int $id, array $overrides = []): ?Product {
        return null; // Implementar se necessário
    }

    public function getProductCategories(int $productId): array {
        return []; // Implementar se necessário
    }

    public function addCategory(int $productId, int $categoryId): bool {
        return false; // Implementar se necessário
    }

    public function removeCategory(int $productId, int $categoryId): bool {
        return false; // Implementar se necessário
    }

    public function updateCategories(int $productId, array $categoryIds): bool {
        return false; // Implementar se necessário
    }

    public function getImages(int $id): array {
        $product = $this->findById($id);
        return $product ? $product->getImages() : [];
    }

    public function addImage(int $id, array $image): bool {
        return false; // Implementar se necessário
    }

    public function removeImage(int $id, int $imageId): bool {
        return false; // Implementar se necessário
    }

    public function updateImageOrder(int $id, array $imageOrders): bool {
        return false; // Implementar se necessário
    }

    public function setMainImage(int $id, int $imageId): bool {
        return false; // Implementar se necessário
    }

    public function getVariations(int $id): array {
        $product = $this->findById($id);
        return $product ? $product->getVariations() : [];
    }

    public function addVariation(int $id, array $variation): bool {
        return false; // Implementar se necessário
    }

    public function updateVariation(int $id, int $variationId, array $variation): bool {
        return false; // Implementar se necessário
    }

    public function removeVariation(int $id, int $variationId): bool {
        return false; // Implementar se necessário
    }

    public function getAttributes(int $id): array {
        $product = $this->findById($id);
        return $product ? $product->getAttributes() : [];
    }

    public function addAttribute(int $id, array $attribute): bool {
        return false; // Implementar se necessário
    }

    public function updateAttribute(int $id, int $attributeId, array $attribute): bool {
        return false; // Implementar se necessário
    }

    public function removeAttribute(int $id, int $attributeId): bool {
        return false; // Implementar se necessário
    }

    public function getTags(int $id): array {
        $product = $this->findById($id);
        return $product ? $product->getTags() : [];
    }

    public function addTag(int $id, string $tag): bool {
        return false; // Implementar se necessário
    }

    public function removeTag(int $id, string $tag): bool {
        return false; // Implementar se necessário
    }

    public function updateTags(int $id, array $tags): bool {
        return false; // Implementar se necessário
    }

    public function findWithAdvancedFilters(array $filters, int $limit = 10, int $offset = 0): array {
        return $this->findAll($filters, $limit, $offset);
    }

    public function getSearchSuggestions(string $term, int $limit = 10): array {
        return []; // Implementar se necessário
    }

    public function findSimilar(int $productId, int $limit = 5): array {
        return $this->findRelated($productId, $limit);
    }

    public function findFrequentlyBoughtTogether(int $productId, int $limit = 5): array {
        return []; // Implementar se necessário
    }

    public function getPriceHistory(int $id, int $limit = 50): array {
        return []; // Implementar se necessário
    }

    public function recordPriceChange(int $id, Money $oldPrice, Money $newPrice): bool {
        return true; // Implementar se necessário
    }

    public function isAvailableForSale(int $id): bool {
        $product = $this->findById($id);
        return $product && $product->isAvailable();
    }

    public function findFeaturedByCategory(int $categoryId, int $limit = 5): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE category_id = ? AND featured = 1 AND active = 1 AND status = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$categoryId, ProductStatus::PUBLISHED, $limit]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findOnSaleByCategory(int $categoryId, int $limit = 5): array {
        $stmt = $this->connection->prepare("SELECT * FROM produtos WHERE category_id = ? AND sale_price IS NOT NULL AND sale_price < price AND active = 1 AND status = ? ORDER BY (price - sale_price) DESC LIMIT ?");
        $stmt->execute([$categoryId, ProductStatus::PUBLISHED, $limit]);
        
        $products = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->hydrate($data);
        }
        return $products;
    }

    public function findBySupplier(int $supplierId, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function updateSupplier(int $id, int $supplierId): bool {
        return false; // Implementar se necessário
    }

    public function findSlowMoving(int $days = 90, int $limit = 10): array {
        return []; // Implementar se necessário
    }

    public function calculateTotalStockValue(): Money {
        $stmt = $this->connection->query("SELECT SUM(stock * price) as total FROM produtos WHERE active = 1 AND type = 'physical'");
        $total = $stmt->fetchColumn();
        return new Money($total ?: 0);
    }

    public function findNeedingRestock(): array {
        return $this->findLowStock(100, 0);
    }

    public function generateReport(array $filters = []): array {
        return []; // Implementar se necessário
    }

    public function validateProductData(array $data): array {
        return $data; // Implementar se necessário
    }

    public function sanitizeProductData(array $data): array {
        return $data; // Implementar se necessário
    }

    public function import(array $products): array {
        return []; // Implementar se necessário
    }

    public function findByTags(array $tags, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function findByAttributes(array $attributes, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function findWithVariations(int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function findDigital(int $limit = 10, int $offset = 0): array {
        return $this->findByType(ProductType::DIGITAL, $limit, $offset);
    }

    public function findPhysical(int $limit = 10, int $offset = 0): array {
        return $this->findByType(ProductType::PHYSICAL, $limit, $offset);
    }

    public function findByWeightRange(float $minWeight, float $maxWeight, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function findByDimensions(float $maxLength, float $maxWidth, float $maxHeight, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function getSitemapProducts(): array {
        return $this->findActive(1000, 0);
    }

    public function getFeedProducts(int $limit = 50): array {
        return $this->findRecent($limit, 0);
    }

    public function findWithReviews(int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }

    public function findByRating(float $minRating, float $maxRating, int $limit = 10, int $offset = 0): array {
        return []; // Implementar se necessário
    }
}
