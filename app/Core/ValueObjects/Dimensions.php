<?php
/**
 * @file Dimensions.php
 * @responsibilidade Value Object para dimensões físicas
 * @descrição Representa comprimento, largura e altura de um produto
 * @conexão Usado pela entidade Product e cálculos de frete
 */

namespace App\Core\ValueObjects;

class Dimensions {
    private float $length; // cm
    private float $width;  // cm
    private float $height; // cm

    public function __construct(float $length, float $width, float $height) {
        $this->validateDimension($length, 'comprimento');
        $this->validateDimension($width, 'largura');
        $this->validateDimension($height, 'altura');
        
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
    }

    public function getLength(): float {
        return $this->length;
    }

    public function getWidth(): float {
        return $this->width;
    }

    public function getHeight(): float {
        return $this->height;
    }

    // Cálculos
    public function getVolume(): float {
        return $this->length * $this->width * $this->height;
    }

    public function getArea(): float {
        return 2 * (
            ($this->length * $this->width) +
            ($this->length * $this->height) +
            ($this->width * $this->height)
        );
    }

    public function getPerimeter(): float {
        return 4 * ($this->length + $this->width + $this->height);
    }

    public function getDiagonal(): float {
        return sqrt(
            pow($this->length, 2) +
            pow($this->width, 2) +
            pow($this->height, 2)
        );
    }

    // Conversões
    public function getVolumeInMeters(): float {
        return $this->getVolume() / 1000000; // cm³ para m³
    }

    public function getVolumeInLiters(): float {
        return $this->getVolume() / 1000; // cm³ para litros
    }

    public function toMeters(): self {
        return new self(
            $this->length / 100,
            $this->width / 100,
            $this->height / 100
        );
    }

    public function toMillimeters(): self {
        return new self(
            $this->length * 10,
            $this->width * 10,
            $this->height * 10
        );
    }

    public function toInches(): self {
        return new self(
            $this->length / 2.54,
            $this->width / 2.54,
            $this->height / 2.54
        );
    }

    // Formatação
    public function format(string $unit = 'cm'): string {
        return sprintf(
            '%.1f × %.1f × %.1f %s',
            $this->length,
            $this->width,
            $this->height,
            $unit
        );
    }

    public function formatCompact(): string {
        return sprintf('%.0f×%.0f×%.0fcm', $this->length, $this->width, $this->height);
    }

    // Comparações
    public function equals(Dimensions $other): bool {
        return $this->length === $other->length &&
               $this->width === $other->width &&
               $this->height === $other->height;
    }

    public function isCube(): bool {
        return $this->length === $this->width && $this->width === $this->height;
    }

    public function isLargerThan(Dimensions $other): bool {
        return $this->getVolume() > $other->getVolume();
    }

    public function isSmallerThan(Dimensions $other): bool {
        return $this->getVolume() < $other->getVolume();
    }

    // Operações
    public function scale(float $factor): self {
        return new self(
            $this->length * $factor,
            $this->width * $factor,
            $this->height * $factor
        );
    }

    public function add(Dimensions $other): self {
        return new self(
            $this->length + $other->length,
            $this->width + $other->width,
            $this->height + $other->height
        );
    }

    public function subtract(Dimensions $other): self {
        return new self(
            max(0, $this->length - $other->length),
            max(0, $this->width - $other->width),
            max(0, $this->height - $other->height)
        );
    }

    // Análise
    public function getLargestDimension(): float {
        return max($this->length, $this->width, $this->height);
    }

    public function getSmallestDimension(): float {
        return min($this->length, $this->width, $this->height);
    }

    public function getSortedDimensions(): array {
        $dimensions = [$this->length, $this->width, $this->height];
        sort($dimensions);
        return $dimensions;
    }

    public function getAspectRatio(): float {
        return $this->length / $this->width;
    }

    public function isPortrait(): bool {
        return $this->height > $this->length;
    }

    public function isLandscape(): bool {
        return $this->length > $this->height;
    }

    // Validações específicas
    public function fitsIn(Dimensions $container): bool {
        $containerSorted = $container->getSortedDimensions();
        $thisSorted = $this->getSortedDimensions();
        
        return $thisSorted[0] <= $containerSorted[0] &&
               $thisSorted[1] <= $containerSorted[1] &&
               $thisSorted[2] <= $containerSorted[2];
    }

    public function canRotateToFit(Dimensions $container): bool {
        // Verifica todas as rotações possíveis
        $dimensions = [
            [$this->length, $this->width, $this->height],
            [$this->length, $this->height, $this->width],
            [$this->width, $this->length, $this->height],
            [$this->width, $this->height, $this->length],
            [$this->height, $this->length, $this->width],
            [$this->height, $this->width, $this->length]
        ];

        foreach ($dimensions as $dim) {
            $testDimensions = new self($dim[0], $dim[1], $dim[2]);
            if ($testDimensions->fitsIn($container)) {
                return true;
            }
        }

        return false;
    }

    // Métodos estáticos de criação
    public static function fromArray(array $data): self {
        return new self(
            $data['length'] ?? 0,
            $data['width'] ?? 0,
            $data['height'] ?? 0
        );
    }

    public static function fromString(string $dimensionString): self {
        // Formato esperado: "10x20x30cm" ou "10 x 20 x 30 cm"
        $parts = preg_split('/[x×]/', strtolower($dimensionString));
        $parts = array_map('trim', $parts);
        
        if (count($parts) < 3) {
            throw new \InvalidArgumentException('Formato de dimensão inválido');
        }

        $length = (float) preg_replace('/[^0-9.]/', '', $parts[0]);
        $width = (float) preg_replace('/[^0-9.]/', '', $parts[1]);
        $height = (float) preg_replace('/[^0-9.]/', '', $parts[2]);

        return new self($length, $width, $height);
    }

    public static function cube(float $size): self {
        return new self($size, $size, $size);
    }

    public static function fromMeters(float $length, float $width, float $height): self {
        return new self($length * 100, $width * 100, $height * 100);
    }

    public static function fromInches(float $length, float $width, float $height): self {
        return new self($length * 2.54, $width * 2.54, $height * 2.54);
    }

    // Métodos de validação
    private function validateDimension(float $dimension, string $name): void {
        if ($dimension < 0) {
            throw new \InvalidArgumentException("{$name} não pode ser negativo");
        }

        if ($dimension > 1000) {
            throw new \InvalidArgumentException("{$name} não pode exceder 1000 cm");
        }

        if (is_nan($dimension) || is_infinite($dimension)) {
            throw new \InvalidArgumentException("{$name} inválido");
        }
    }

    // Métodos mágicos
    public function __toString(): string {
        return $this->format();
    }

    public function __debugInfo(): array {
        return [
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'volume' => $this->getVolume(),
            'formatted' => $this->format()
        ];
    }

    // Serialização
    public function toArray(): array {
        return [
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'volume' => $this->getVolume(),
            'area' => $this->getArea(),
            'formatted' => $this->format()
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    // Cálculos de frete (aproximados)
    public function getCubicWeight(float $density = 166.67): float {
        // Peso cúbico padrão para Correios (kg/m³)
        $volumeM3 = $this->getVolumeInMeters();
        return $volumeM3 * $density;
    }

    public function getShippingWeight(float $actualWeight = 0): float {
        $cubicWeight = $this->getCubicWeight();
        return max($actualWeight, $cubicWeight);
    }

    // Empacotamento
    public function getRequiredBoxSize(float $padding = 0): self {
        return new self(
            $this->length + (2 * $padding),
            $this->width + (2 * $padding),
            $this->height + (2 * $padding)
        );
    }

    public function canStack(Dimensions $other): bool {
        // Verifica se outro produto pode ser empilhado sobre este
        return $this->length >= $other->length &&
               $this->width >= $other->width;
    }

    // Análise de eficiência
    public function getSpaceEfficiency(): float {
        // Eficiência do espaço (quão próximo de um cubo)
        $idealVolume = pow($this->getLargestDimension(), 3);
        return $this->getVolume() / $idealVolume;
    }

    public function isCompact(): bool {
        return $this->getSpaceEfficiency() > 0.7;
    }

    // Categorias de tamanho
    public function getSizeCategory(): string {
        $volume = $this->getVolume();
        
        if ($volume < 1000) {
            return 'pequeno';
        } elseif ($volume < 10000) {
            return 'medio';
        } elseif ($volume < 50000) {
            return 'grande';
        } else {
            return 'extra_grande';
        }
    }

    // Operações com arrays
    public static function sum(array $dimensions): self {
        if (empty($dimensions)) {
            return new self(0, 0, 0);
        }

        $total = $dimensions[0];
        for ($i = 1; $i < count($dimensions); $i++) {
            $total = $total->add($dimensions[$i]);
        }

        return $total;
    }

    public static function max(array $dimensions): self {
        if (empty($dimensions)) {
            throw new \InvalidArgumentException('Array vazio');
        }

        $maxLength = max(array_map(fn($d) => $d->length, $dimensions));
        $maxWidth = max(array_map(fn($d) => $d->width, $dimensions));
        $maxHeight = max(array_map(fn($d) => $d->height, $dimensions));

        return new self($maxLength, $maxWidth, $maxHeight);
    }
}
