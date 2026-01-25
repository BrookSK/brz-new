<?php
/**
 * @file ProductService.php
 * @responsibilidade Serviço de Gestão de Produtos
 * @descrição Centraliza toda a lógica de negócio relacionada a produtos
 * @conexão Usado por Controllers, UseCases e Admin Controllers
 */

namespace App\Core\Services;

use App\Core\Repositories\ProductRepositoryInterface;
use App\Core\Domain\Product;
use App\Core\ValueObjects\Money;
use App\Core\ValueObjects\Dimensions;
use App\Shared\Constants\ProductStatus;
use App\Shared\Constants\ProductType;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Exceptions\NotFoundException;

class ProductService {
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository) {
        $this->productRepository = $productRepository;
    }

    /**
     * Cria um novo produto
     */
    public function createProduct(array $productData): Product {
        $this->validateProductData($productData);

        // Verificar se SKU já existe
        if ($this->productRepository->skuExists($productData['sku'])) {
            throw new ValidationException('SKU já existe no sistema');
        }

        // Verificar se slug já existe
        if (isset($productData['slug']) && $this->productRepository->slugExists($productData['slug'])) {
            throw new ValidationException('Slug já existe no sistema');
        }

        // Criar produto
        $product = new Product(
            $productData['name'],
            $productData['sku'],
            new Money($productData['price']),
            $productData['type'] ?? ProductType::PHYSICAL
        );

        // Configurar atributos básicos
        if (isset($productData['description'])) {
            $product->setDescription($productData['description']);
        }

        if (isset($productData['short_description'])) {
            $product->setShortDescription($productData['short_description']);
        }

        if (isset($productData['cost_price'])) {
            $product->setCostPrice(new Money($productData['cost_price']));
        }

        if (isset($productData['sale_price'])) {
            $product->setSalePrice(new Money($productData['sale_price']));
        }

        if (isset($productData['stock'])) {
            $product->setStock((int) $productData['stock']);
        }

        if (isset($productData['min_stock'])) {
            $product->setMinStock((int) $productData['min_stock']);
        }

        if (isset($productData['max_stock'])) {
            $product->setMaxStock((int) $productData['max_stock']);
        }

        // Dimensões
        if (isset($productData['length'], $productData['width'], $productData['height'])) {
            $product->setDimensions(new Dimensions(
                $productData['length'],
                $productData['width'],
                $productData['height']
            ));
        }

        if (isset($productData['weight'])) {
            $product->setWeight((float) $productData['weight']);
        }

        // Metadados
        if (isset($productData['category_id'])) {
            $product->setCategoryId((int) $productData['category_id']);
        }

        if (isset($productData['tags']) && is_array($productData['tags'])) {
            $product->setTags($productData['tags']);
        }

        if (isset($productData['images']) && is_array($productData['images'])) {
            $product->setImages($productData['images']);
        }

        if (isset($productData['variations']) && is_array($productData['variations'])) {
            $product->setVariations($productData['variations']);
        }

        if (isset($productData['attributes']) && is_array($productData['attributes'])) {
            $product->setAttributes($productData['attributes']);
        }

        // Status e visibilidade
        if (isset($productData['status'])) {
            $product->setStatus($productData['status']);
        }

        if (isset($productData['active'])) {
            $product->setActive((bool) $productData['active']);
        }

        if (isset($productData['featured'])) {
            $product->setFeatured((bool) $productData['featured']);
        }

        // Produto digital
        if (isset($productData['digital_file'])) {
            $product->setDigitalFile($productData['digital_file']);
        }

        // Salvar produto
        return $this->productRepository->save($product);
    }

    /**
     * Atualiza um produto existente
     */
    public function updateProduct(int $id, array $productData): Product {
        $product = $this->productRepository->findById($id);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        $this->validateProductData($productData, $product);

        // Verificar se SKU já existe (excluindo o produto atual)
        if (isset($productData['sku']) && $this->productRepository->skuExists($productData['sku'], $id)) {
            throw new ValidationException('SKU já existe no sistema');
        }

        // Atualizar campos permitidos
        if (isset($productData['name'])) {
            $product->setName($productData['name']);
        }

        if (isset($productData['sku'])) {
            $product->setSku($productData['sku']);
        }

        if (isset($productData['description'])) {
            $product->setDescription($productData['description']);
        }

        if (isset($productData['short_description'])) {
            $product->setShortDescription($productData['short_description']);
        }

        if (isset($productData['price'])) {
            $product->setPrice(new Money($productData['price']));
        }

        if (isset($productData['cost_price'])) {
            $product->setCostPrice(new Money($productData['cost_price']));
        }

        if (isset($productData['sale_price'])) {
            $product->setSalePrice($productData['sale_price'] ? new Money($productData['sale_price']) : null);
        }

        if (isset($productData['stock'])) {
            $product->setStock((int) $productData['stock']);
        }

        if (isset($productData['min_stock'])) {
            $product->setMinStock((int) $productData['min_stock']);
        }

        if (isset($productData['max_stock'])) {
            $product->setMaxStock((int) $productData['max_stock']);
        }

        if (isset($productData['weight'])) {
            $product->setWeight((float) $productData['weight']);
        }

        if (isset($productData['category_id'])) {
            $product->setCategoryId((int) $productData['category_id']);
        }

        if (isset($productData['status'])) {
            $product->setStatus($productData['status']);
        }

        if (isset($productData['active'])) {
            $product->setActive((bool) $productData['active']);
        }

        if (isset($productData['featured'])) {
            $product->setFeatured((bool) $productData['featured']);
        }

        return $this->productRepository->save($product);
    }

    /**
     * Remove um produto
     */
    public function deleteProduct(int $id): bool {
        $product = $this->productRepository->findById($id);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        return $this->productRepository->delete($id);
    }

    /**
     * Busca produto por ID
     */
    public function getProductById(int $id): ?Product {
        return $this->productRepository->findById($id);
    }

    /**
     * Busca produto por SKU
     */
    public function getProductBySku(string $sku): ?Product {
        return $this->productRepository->findBySku($sku);
    }

    /**
     * Busca produto por slug
     */
    public function getProductBySlug(string $slug): ?Product {
        return $this->productRepository->findBySlug($slug);
    }

    /**
     * Lista produtos com paginação e filtros
     */
    public function listProducts(array $filters = [], int $page = 1, int $limit = 10): array {
        $offset = ($page - 1) * $limit;
        $products = $this->productRepository->findAll($filters, $limit, $offset);
        $total = $this->productRepository->count($filters);

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Busca produtos ativos para o catálogo
     */
    public function getCatalogProducts(array $filters = [], int $limit = 12, int $offset = 0): array {
        $filters['active'] = true;
        $filters['status'] = ProductStatus::PUBLISHED;
        
        return $this->productRepository->findAll($filters, $limit, $offset);
    }

    /**
     * Busca produtos em destaque
     */
    public function getFeaturedProducts(int $limit = 10): array {
        return $this->productRepository->findFeatured($limit);
    }

    /**
     * Busca produtos mais vendidos
     */
    public function getBestSellingProducts(int $limit = 10): array {
        return $this->productRepository->findBestSellers($limit);
    }

    /**
     * Busca produtos recentes
     */
    public function getRecentProducts(int $limit = 10): array {
        return $this->productRepository->findRecent($limit);
    }

    /**
     * Busca produtos em promoção
     */
    public function getOnSaleProducts(int $limit = 10): array {
        return $this->productRepository->findOnSale($limit);
    }

    /**
     * Busca produtos por categoria
     */
    public function getProductsByCategory(int $categoryId, int $limit = 10): array {
        return $this->productRepository->findByCategory($categoryId, $limit);
    }

    /**
     * Busca produtos relacionados
     */
    public function getRelatedProducts(int $productId, int $limit = 5): array {
        return $this->productRepository->findRelated($productId, $limit);
    }

    /**
     * Busca produtos por termo
     */
    public function searchProducts(string $term, int $limit = 10): array {
        return $this->productRepository->search($term, $limit);
    }

    /**
     * Busca produtos por faixa de preço
     */
    public function getProductsByPriceRange(float $minPrice, float $maxPrice, int $limit = 10): array {
        return $this->productRepository->findByPriceRange(
            new Money($minPrice),
            new Money($maxPrice),
            $limit
        );
    }

    /**
     * Gerencia estoque do produto
     */
    public function manageStock(int $productId, string $operation, int $quantity): bool {
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        if ($product->isDigital()) {
            throw new ValidationException('Produtos digitais não possuem estoque');
        }

        switch ($operation) {
            case 'add':
                return $this->productRepository->addStock($productId, $quantity);
                
            case 'remove':
                if (!$product->hasStock()) {
                    throw new ValidationException('Produto sem estoque');
                }
                if ($product->getStock() < $quantity) {
                    throw new ValidationException('Estoque insuficiente');
                }
                return $this->productRepository->removeStock($productId, $quantity);
                
            case 'set':
                return $this->productRepository->updateStock($productId, $quantity);
                
            default:
                throw new ValidationException('Operação de estoque inválida');
        }
    }

    /**
     * Atualiza status do produto
     */
    public function updateProductStatus(int $productId, string $status): bool {
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        if (!ProductStatus::isValid($status)) {
            throw new ValidationException('Status inválido');
        }

        if (!ProductStatus::canTransition($product->getStatus(), $status)) {
            throw new ValidationException('Transição de status não permitida');
        }

        return $this->productRepository->updateStatus($productId, $status);
    }

    /**
     * Ativa ou desativa produto
     */
    public function toggleProductActive(int $productId): bool {
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        $newStatus = !$product->isActive();
        return $this->productRepository->setActive($productId, $newStatus);
    }

    /**
     * Define produto como destaque
     */
    public function toggleProductFeatured(int $productId): bool {
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            throw new NotFoundException('Produto não encontrado');
        }

        $newStatus = !$product->isFeatured();
        return $this->productRepository->setFeatured($productId, $newStatus);
    }

    /**
     * Publica produto
     */
    public function publishProduct(int $productId): bool {
        return $this->updateProductStatus($productId, ProductStatus::PUBLISHED);
    }

    /**
     * Arquiva produto
     */
    public function archiveProduct(int $productId): bool {
        return $this->updateProductStatus($productId, ProductStatus::ARCHIVED);
    }

    /**
     * Duplica produto
     */
    public function duplicateProduct(int $productId, array $overrides = []): Product {
        $originalProduct = $this->productRepository->findById($productId);
        
        if (!$originalProduct) {
            throw new NotFoundException('Produto não encontrado');
        }

        $productData = $originalProduct->toArray();
        
        // Remover campos que não devem ser duplicados
        unset($productData['id'], $productData['sku'], $productData['slug'], $productData['created_at'], $productData['updated_at']);
        
        // Aplicar overrides
        $productData = array_merge($productData, $overrides);
        
        // Gerar novo SKU
        if (!isset($productData['sku'])) {
            $productData['sku'] = $this->generateUniqueSku($originalProduct->getSku());
        }
        
        return $this->createProduct($productData);
    }

    /**
     * Obtém estatísticas de produtos
     */
    public function getProductStatistics(): array {
        return $this->productRepository->getStatistics();
    }

    /**
     * Obtém métricas de um produto específico
     */
    public function getProductMetrics(int $productId): array {
        $metrics = $this->productRepository->getMetrics($productId);
        
        // Adicionar métricas calculadas
        $product = $this->productRepository->findById($productId);
        if ($product) {
            $metrics['margin'] = $product->getMargin();
            $metrics['has_stock'] = $product->hasStock();
            $metrics['is_low_stock'] = $product->isLowStock();
            $metrics['is_out_of_stock'] = $product->isOutOfStock();
            $metrics['has_sale'] = $product->hasSale();
            $metrics['discount_percentage'] = $product->getDiscountPercentage();
        }
        
        return $metrics;
    }

    /**
     * Verifica disponibilidade de produto
     */
    public function checkProductAvailability(int $productId, int $quantity = 1): array {
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            return ['available' => false, 'reason' => 'Produto não encontrado'];
        }

        if (!$product->isActive()) {
            return ['available' => false, 'reason' => 'Produto inativo'];
        }

        if ($product->getStatus() !== ProductStatus::PUBLISHED) {
            return ['available' => false, 'reason' => 'Produto não publicado'];
        }

        if (!$product->hasStock()) {
            return ['available' => false, 'reason' => 'Produto sem estoque'];
        }

        if (!$product->isDigital() && $product->getStock() < $quantity) {
            return ['available' => false, 'reason' => 'Estoque insuficiente', 'available_stock' => $product->getStock()];
        }

        return ['available' => true, 'product' => $product];
    }

    /**
     * Processa upload de imagens
     */
    public function processImageUpload(array $files): array {
        $images = [];
        $uploadDir = 'uploads/products/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($files['images']['name'] as $key => $name) {
            if ($files['images']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $files['images']['tmp_name'][$key];
                $fileName = time() . '_' . $key . '_' . $name;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $images[] = [
                        'url' => '/' . $filePath,
                        'alt' => pathinfo($name, PATHINFO_FILENAME),
                        'size' => $files['images']['size'][$key],
                        'type' => $files['images']['type'][$key]
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * Valida dados do produto
     */
    private function validateProductData(array $data, ?Product $product = null): void {
        $errors = [];

        // Nome
        if (empty($data['name'])) {
            $errors['name'] = 'Nome é obrigatório';
        } elseif (strlen($data['name']) < 3 || strlen($data['name']) > 200) {
            $errors['name'] = 'Nome deve ter entre 3 e 200 caracteres';
        }

        // SKU
        if (empty($data['sku'])) {
            $errors['sku'] = 'SKU é obrigatório';
        } elseif (strlen($data['sku']) < 3 || strlen($data['sku']) > 50) {
            $errors['sku'] = 'SKU deve ter entre 3 e 50 caracteres';
        }

        // Preço
        if (!isset($data['price']) || $data['price'] <= 0) {
            $errors['price'] = 'Preço deve ser maior que zero';
        }

        // Estoque (apenas para produtos físicos)
        $type = $data['type'] ?? ($product ? $product->getType() : ProductType::PHYSICAL);
        
        if ($type === ProductType::PHYSICAL) {
            if (isset($data['stock']) && $data['stock'] < 0) {
                $errors['stock'] = 'Estoque não pode ser negativo';
            }
            
            if (isset($data['min_stock']) && $data['min_stock'] < 0) {
                $errors['min_stock'] = 'Estoque mínimo não pode ser negativo';
            }
            
            if (isset($data['max_stock']) && $data['max_stock'] <= 0) {
                $errors['max_stock'] = 'Estoque máximo deve ser positivo';
            }
        }

        // Peso
        if (isset($data['weight']) && $data['weight'] < 0) {
            $errors['weight'] = 'Peso não pode ser negativo';
        }

        // Categoria
        if (isset($data['category_id']) && $data['category_id'] <= 0) {
            $errors['category_id'] = 'Categoria inválida';
        }

        if (!empty($errors)) {
            throw new ValidationException('Dados inválidos', $errors);
        }
    }

    /**
     * Gera SKU único
     */
    private function generateUniqueSku(string $baseSku): string {
        $counter = 1;
        $newSku = $baseSku . '-COPY';
        
        while ($this->productRepository->skuExists($newSku)) {
            $newSku = $baseSku . '-COPY-' . $counter;
            $counter++;
        }
        
        return $newSku;
    }

    /**
     * Exporta produtos
     */
    public function exportProducts(array $filters = []): array {
        return $this->productRepository->export($filters);
    }

    /**
     * Importa produtos
     */
    public function importProducts(array $productsData): array {
        $results = ['success' => 0, 'errors' => 0, 'details' => []];
        
        foreach ($productsData as $index => $productData) {
            try {
                $this->createProduct($productData);
                $results['success']++;
                $results['details'][] = ['row' => $index + 1, 'status' => 'success'];
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = ['row' => $index + 1, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    /**
     * Obtém produtos com alertas de estoque
     */
    public function getProductsWithStockAlerts(): array {
        return $this->productRepository->findWithStockAlerts();
    }

    /**
     * Calcula valor total do estoque
     */
    public function calculateTotalStockValue(): Money {
        return $this->productRepository->calculateTotalStockValue();
    }

    /**
     * Incrementa visualizações do produto
     */
    public function incrementProductViews(int $productId): bool {
        return $this->productRepository->incrementViews($productId);
    }
}
