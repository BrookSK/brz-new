<?php
/**
 * @file Product.php
 * @responsibilidade Entidade de Domínio para Produtos
 * @descrição Representa um produto no sistema com todas as suas regras de negócio
 * @conexão Usada por Services, Repositories e Controllers
 */

namespace App\Core\Domain;

use App\Core\ValueObjects\Money;
use App\Core\ValueObjects\Dimensions;
use App\Shared\Constants\ProductStatus;
use App\Shared\Constants\ProductType;

class Product {
    private ?int $id;
    private string $name;
    private string $slug;
    private string $sku;
    private ?string $description;
    private ?string $shortDescription;
    private Money $price;
    private Money $costPrice;
    private ?Money $salePrice;
    private ?int $stock;
    private ?int $minStock;
    private ?int $maxStock;
    private ?Dimensions $dimensions;
    private ?float $weight;
    private string $type;
    private string $status;
    private ?int $categoryId;
    private ?array $tags;
    private ?array $images;
    private ?array $variations;
    private ?array $attributes;
    private bool $active;
    private bool $featured;
    private bool $digital;
    private ?string $digitalFile;
    private ?int $digitalDownloads;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;
    private ?\DateTime $publishedAt;

    public function __construct(
        string $name,
        string $sku,
        Money $price,
        string $type = ProductType::PHYSICAL,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->name = $this->validateName($name);
        $this->slug = $this->generateSlug($name);
        $this->sku = $this->validateSku($sku);
        $this->price = $price;
        $this->costPrice = new Money(0);
        $this->type = $this->validateType($type);
        $this->status = ProductStatus::DRAFT;
        $this->stock = 0;
        $this->minStock = 1;
        $this->maxStock = 1000;
        $this->active = false;
        $this->featured = false;
        $this->digital = $type === ProductType::DIGITAL;
        $this->tags = [];
        $this->images = [];
        $this->variations = [];
        $this->attributes = [];
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getSlug(): string {
        return $this->slug;
    }

    public function getSku(): string {
        return $this->sku;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getShortDescription(): ?string {
        return $this->shortDescription;
    }

    public function getPrice(): Money {
        return $this->price;
    }

    public function getCostPrice(): Money {
        return $this->costPrice;
    }

    public function getSalePrice(): ?Money {
        return $this->salePrice;
    }

    public function getStock(): ?int {
        return $this->stock;
    }

    public function getMinStock(): ?int {
        return $this->minStock;
    }

    public function getMaxStock(): ?int {
        return $this->maxStock;
    }

    public function getDimensions(): ?Dimensions {
        return $this->dimensions;
    }

    public function getWeight(): ?float {
        return $this->weight;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getCategoryId(): ?int {
        return $this->categoryId;
    }

    public function getTags(): ?array {
        return $this->tags;
    }

    public function getImages(): ?array {
        return $this->images;
    }

    public function getVariations(): ?array {
        return $this->variations;
    }

    public function getAttributes(): ?array {
        return $this->attributes;
    }

    public function isActive(): bool {
        return $this->active;
    }

    public function isFeatured(): bool {
        return $this->featured;
    }

    public function isDigital(): bool {
        return $this->digital;
    }

    public function getDigitalFile(): ?string {
        return $this->digitalFile;
    }

    public function getDigitalDownloads(): ?int {
        return $this->digitalDownloads;
    }

    public function getCreatedAt(): ?\DateTime {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime {
        return $this->updatedAt;
    }

    public function getPublishedAt(): ?\DateTime {
        return $this->publishedAt;
    }

    // Setters com validação
    public function setName(string $name): void {
        $this->name = $this->validateName($name);
        $this->slug = $this->generateSlug($name);
        $this->updatedAt = new \DateTime();
    }

    public function setSku(string $sku): void {
        $this->sku = $this->validateSku($sku);
        $this->updatedAt = new \DateTime();
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
    }

    public function setShortDescription(?string $shortDescription): void {
        $this->shortDescription = $shortDescription;
        $this->updatedAt = new \DateTime();
    }

    public function setPrice(Money $price): void {
        $this->price = $price;
        $this->updatedAt = new \DateTime();
    }

    public function setCostPrice(Money $costPrice): void {
        $this->costPrice = $costPrice;
        $this->updatedAt = new \DateTime();
    }

    public function setSalePrice(?Money $salePrice): void {
        $this->salePrice = $salePrice;
        $this->updatedAt = new \DateTime();
    }

    public function setStock(?int $stock): void {
        $this->stock = $this->validateStock($stock);
        $this->updatedAt = new \DateTime();
    }

    public function setMinStock(?int $minStock): void {
        $this->minStock = $this->validateMinStock($minStock);
        $this->updatedAt = new \DateTime();
    }

    public function setMaxStock(?int $maxStock): void {
        $this->maxStock = $this->validateMaxStock($maxStock);
        $this->updatedAt = new \DateTime();
    }

    public function setDimensions(?Dimensions $dimensions): void {
        $this->dimensions = $dimensions;
        $this->updatedAt = new \DateTime();
    }

    public function setWeight(?float $weight): void {
        $this->weight = $this->validateWeight($weight);
        $this->updatedAt = new \DateTime();
    }

    public function setType(string $type): void {
        $this->type = $this->validateType($type);
        $this->digital = $type === ProductType::DIGITAL;
        $this->updatedAt = new \DateTime();
    }

    public function setStatus(string $status): void {
        $this->status = $this->validateStatus($status);
        
        if ($status === ProductStatus::PUBLISHED && !$this->publishedAt) {
            $this->publishedAt = new \DateTime();
        }
        
        $this->updatedAt = new \DateTime();
    }

    public function setCategoryId(?int $categoryId): void {
        $this->categoryId = $categoryId;
        $this->updatedAt = new \DateTime();
    }

    public function setTags(?array $tags): void {
        $this->tags = array_filter($tags);
        $this->updatedAt = new \DateTime();
    }

    public function setImages(?array $images): void {
        $this->images = $this->validateImages($images);
        $this->updatedAt = new \DateTime();
    }

    public function setVariations(?array $variations): void {
        $this->variations = $variations;
        $this->updatedAt = new \DateTime();
    }

    public function setAttributes(?array $attributes): void {
        $this->attributes = $attributes;
        $this->updatedAt = new \DateTime();
    }

    public function setActive(bool $active): void {
        $this->active = $active;
        $this->updatedAt = new \DateTime();
    }

    public function setFeatured(bool $featured): void {
        $this->featured = $featured;
        $this->updatedAt = new \DateTime();
    }

    public function setDigitalFile(?string $digitalFile): void {
        $this->digitalFile = $digitalFile;
        $this->updatedAt = new \DateTime();
    }

    public function setDigitalDownloads(?int $digitalDownloads): void {
        $this->digitalDownloads = $digitalDownloads;
        $this->updatedAt = new \DateTime();
    }

    // Métodos de negócio
    public function activate(): void {
        $this->active = true;
        $this->updatedAt = new \DateTime();
    }

    public function deactivate(): void {
        $this->active = false;
        $this->updatedAt = new \DateTime();
    }

    public function publish(): void {
        $this->status = ProductStatus::PUBLISHED;
        $this->active = true;
        if (!$this->publishedAt) {
            $this->publishedAt = new \DateTime();
        }
        $this->updatedAt = new \DateTime();
    }

    public function archive(): void {
        $this->status = ProductStatus::ARCHIVED;
        $this->active = false;
        $this->updatedAt = new \DateTime();
    }

    public function isAvailable(): bool {
        return $this->active && $this->status === ProductStatus::PUBLISHED && $this->hasStock();
    }

    public function hasStock(): bool {
        if ($this->digital) {
            return true; // Produtos digitais sempre têm "estoque"
        }
        return ($this->stock ?? 0) > 0;
    }

    public function isInStock(): bool {
        return $this->hasStock();
    }

    public function isLowStock(): bool {
        if ($this->digital) {
            return false;
        }
        return ($this->stock ?? 0) <= ($this->minStock ?? 1);
    }

    public function isOutOfStock(): bool {
        if ($this->digital) {
            return false;
        }
        return ($this->stock ?? 0) <= 0;
    }

    public function needsRestock(): bool {
        if ($this->digital) {
            return false;
        }
        return ($this->stock ?? 0) <= ($this->minStock ?? 1);
    }

    public function canRestock(): bool {
        if ($this->digital) {
            return false;
        }
        return ($this->stock ?? 0) < ($this->maxStock ?? 1000);
    }

    public function addStock(int $quantity): void {
        if ($this->digital) {
            throw new \InvalidArgumentException('Produtos digitais não possuem estoque');
        }
        
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }
        
        $newStock = ($this->stock ?? 0) + $quantity;
        
        if ($newStock > ($this->maxStock ?? 1000)) {
            throw new \InvalidArgumentException('Estoque máximo excedido');
        }
        
        $this->stock = $newStock;
        $this->updatedAt = new \DateTime();
    }

    public function removeStock(int $quantity): void {
        if ($this->digital) {
            return; // Produtos digitais não removem estoque
        }
        
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }
        
        $newStock = ($this->stock ?? 0) - $quantity;
        
        if ($newStock < 0) {
            throw new \InvalidArgumentException('Estoque insuficiente');
        }
        
        $this->stock = $newStock;
        $this->updatedAt = new \DateTime();
    }

    public function getEffectivePrice(): Money {
        return $this->salePrice ?? $this->price;
    }

    public function hasSale(): bool {
        return $this->salePrice !== null && $this->salePrice->getValue() < $this->price->getValue();
    }

    public function getDiscountPercentage(): float {
        if (!$this->hasSale()) {
            return 0;
        }
        
        $originalPrice = $this->price->getValue();
        $salePrice = $this->salePrice->getValue();
        
        return round((($originalPrice - $salePrice) / $originalPrice) * 100, 2);
    }

    public function addTag(string $tag): void {
        $tag = trim($tag);
        if (!empty($tag) && !in_array($tag, $this->tags ?? [])) {
            $this->tags[] = $tag;
            $this->updatedAt = new \DateTime();
        }
    }

    public function removeTag(string $tag): void {
        $key = array_search($tag, $this->tags ?? []);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->updatedAt = new \DateTime();
        }
    }

    public function addImage(array $image): void {
        if (!isset($image['url']) || !isset($image['alt'])) {
            throw new \InvalidArgumentException('Imagem deve ter URL e texto alt');
        }
        
        $this->images[] = $image;
        $this->updatedAt = new \DateTime();
    }

    public function removeImage(int $index): void {
        if (isset($this->images[$index])) {
            unset($this->images[$index]);
            $this->images = array_values($this->images);
            $this->updatedAt = new \DateTime();
        }
    }

    public function getMainImage(): ?array {
        return $this->images[0] ?? null;
    }

    public function getMargin(): float {
        $cost = $this->costPrice->getValue();
        $price = $this->getEffectivePrice()->getValue();
        
        if ($cost <= 0) {
            return 0;
        }
        
        return round((($price - $cost) / $cost) * 100, 2);
    }

    // Métodos de validação
    private function validateName(string $name): string {
        $name = trim($name);
        if (strlen($name) < 3 || strlen($name) > 200) {
            throw new \InvalidArgumentException('Nome deve ter entre 3 e 200 caracteres');
        }
        return $name;
    }

    private function validateSku(string $sku): string {
        $sku = trim($sku);
        if (strlen($sku) < 3 || strlen($sku) > 50) {
            throw new \InvalidArgumentException('SKU deve ter entre 3 e 50 caracteres');
        }
        return strtoupper($sku);
    }

    private function validateStock(?int $stock): ?int {
        if ($stock === null) {
            return null;
        }
        
        if ($stock < 0) {
            throw new \InvalidArgumentException('Estoque não pode ser negativo');
        }
        
        return $stock;
    }

    private function validateMinStock(?int $minStock): ?int {
        if ($minStock === null) {
            return null;
        }
        
        if ($minStock < 0) {
            throw new \InvalidArgumentException('Estoque mínimo não pode ser negativo');
        }
        
        return $minStock;
    }

    private function validateMaxStock(?int $maxStock): ?int {
        if ($maxStock === null) {
            return null;
        }
        
        if ($maxStock <= 0) {
            throw new \InvalidArgumentException('Estoque máximo deve ser positivo');
        }
        
        if ($this->minStock && $maxStock <= $this->minStock) {
            throw new \InvalidArgumentException('Estoque máximo deve ser maior que o mínimo');
        }
        
        return $maxStock;
    }

    private function validateWeight(?float $weight): ?float {
        if ($weight === null) {
            return null;
        }
        
        if ($weight < 0) {
            throw new \InvalidArgumentException('Peso não pode ser negativo');
        }
        
        return $weight;
    }

    private function validateType(string $type): string {
        if (!in_array($type, ProductType::getAll())) {
            throw new \InvalidArgumentException('Tipo de produto inválido');
        }
        return $type;
    }

    private function validateStatus(string $status): string {
        if (!in_array($status, ProductStatus::getAll())) {
            throw new \InvalidArgumentException('Status de produto inválido');
        }
        return $status;
    }

    private function validateImages(?array $images): ?array {
        if ($images === null) {
            return null;
        }
        
        foreach ($images as $image) {
            if (!is_array($image) || !isset($image['url'])) {
                throw new \InvalidArgumentException('Imagem inválida');
            }
        }
        
        return $images;
    }

    private function generateSlug(string $name): string {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    // Métodos estáticos
    public static function fromArray(array $data): self {
        $product = new self(
            $data['name'],
            $data['sku'],
            new Money($data['price']),
            $data['type'] ?? ProductType::PHYSICAL,
            $data['id'] ?? null
        );

        $product->description = $data['description'] ?? null;
        $product->shortDescription = $data['short_description'] ?? null;
        $product->costPrice = new Money($data['cost_price'] ?? 0);
        $product->salePrice = isset($data['sale_price']) ? new Money($data['sale_price']) : null;
        $product->stock = $data['stock'] ?? 0;
        $product->minStock = $data['min_stock'] ?? 1;
        $product->maxStock = $data['max_stock'] ?? 1000;
        $product->weight = $data['weight'] ?? null;
        $product->type = $data['type'] ?? ProductType::PHYSICAL;
        $product->status = $data['status'] ?? ProductStatus::DRAFT;
        $product->categoryId = $data['category_id'] ?? null;
        $product->tags = $data['tags'] ?? [];
        $product->images = $data['images'] ?? [];
        $product->variations = $data['variations'] ?? [];
        $product->attributes = $data['attributes'] ?? [];
        $product->active = (bool)($data['active'] ?? false);
        $product->featured = (bool)($data['featured'] ?? false);
        $product->digital = (bool)($data['digital'] ?? false);
        $product->digitalFile = $data['digital_file'] ?? null;
        $product->digitalDownloads = $data['digital_downloads'] ?? 0;
        $product->createdAt = $data['created_at'] ? new \DateTime($data['created_at']) : null;
        $product->updatedAt = $data['updated_at'] ? new \DateTime($data['updated_at']) : null;
        $product->publishedAt = $data['published_at'] ? new \DateTime($data['published_at']) : null;

        if (isset($data['dimensions'])) {
            $product->dimensions = Dimensions::fromArray($data['dimensions']);
        }

        return $product;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'short_description' => $this->shortDescription,
            'price' => $this->price->getValue(),
            'cost_price' => $this->costPrice->getValue(),
            'sale_price' => $this->salePrice?->getValue(),
            'stock' => $this->stock,
            'min_stock' => $this->minStock,
            'max_stock' => $this->maxStock,
            'dimensions' => $this->dimensions?->toArray(),
            'weight' => $this->weight,
            'type' => $this->type,
            'status' => $this->status,
            'category_id' => $this->categoryId,
            'tags' => $this->tags,
            'images' => $this->images,
            'variations' => $this->variations,
            'attributes' => $this->attributes,
            'active' => $this->active,
            'featured' => $this->featured,
            'digital' => $this->digital,
            'digital_file' => $this->digitalFile,
            'digital_downloads' => $this->digitalDownloads,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s')
        ];
    }
}
