<?php
/**
 * @file Money.php
 * @responsibilidade Value Object para valores monetários
 * @descrição Representa um valor monetário com validação e conversões
 * @conexão Usado pela entidade Product e em cálculos financeiros
 */

namespace App\Core\ValueObjects;

class Money {
    private int $amount; // Valor em centavos
    private string $currency;

    public function __construct(float $amount, string $currency = 'BRL') {
        $this->validateAmount($amount);
        $this->validateCurrency($currency);
        $this->amount = (int) round($amount * 100);
        $this->currency = strtoupper($currency);
    }

    public function getValue(): float {
        return $this->amount / 100;
    }

    public function getAmount(): int {
        return $this->amount;
    }

    public function getCurrency(): string {
        return $this->currency;
    }

    public function format(bool $withSymbol = true): string {
        $value = number_format($this->getValue(), 2, ',', '.');
        
        if (!$withSymbol) {
            return $value;
        }

        return $this->getCurrencySymbol() . ' ' . $value;
    }

    public function formatDecimal(): string {
        return number_format($this->getValue(), 2, '.', '');
    }

    public function getCurrencySymbol(): string {
        $symbols = [
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥'
        ];

        return $symbols[$this->currency] ?? $this->currency;
    }

    // Operações aritméticas
    public function add(Money $other): Money {
        $this->assertSameCurrency($other);
        return new Money(($this->amount + $other->amount) / 100, $this->currency);
    }

    public function subtract(Money $other): Money {
        $this->assertSameCurrency($other);
        return new Money(($this->amount - $other->amount) / 100, $this->currency);
    }

    public function multiply(float $multiplier): Money {
        return new Money(($this->amount * $multiplier) / 100, $this->currency);
    }

    public function divide(float $divisor): Money {
        if ($divisor == 0) {
            throw new \InvalidArgumentException('Divisão por zero não permitida');
        }
        return new Money(($this->amount / $divisor) / 100, $this->currency);
    }

    // Comparações
    public function equals(Money $other): bool {
        $this->assertSameCurrency($other);
        return $this->amount === $other->amount;
    }

    public function greaterThan(Money $other): bool {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function lessThan(Money $other): bool {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function greaterThanOrEqual(Money $other): bool {
        $this->assertSameCurrency($other);
        return $this->amount >= $other->amount;
    }

    public function lessThanOrEqual(Money $other): bool {
        $this->assertSameCurrency($other);
        return $this->amount <= $other->amount;
    }

    public function isZero(): bool {
        return $this->amount === 0;
    }

    public function isPositive(): bool {
        return $this->amount > 0;
    }

    public function isNegative(): bool {
        return $this->amount < 0;
    }

    // Percentuais
    public function percentage(float $percent): Money {
        return $this->multiply($percent / 100);
    }

    public function discount(float $percent): Money {
        return $this->subtract($this->percentage($percent));
    }

    public function increase(float $percent): Money {
        return $this->add($this->percentage($percent));
    }

    // Conversões
    public function toCents(): int {
        return $this->amount;
    }

    public function toFloat(): float {
        return $this->getValue();
    }

    public function toString(): string {
        return $this->format();
    }

    // Métodos estáticos de criação
    public static function fromCents(int $cents, string $currency = 'BRL'): self {
        return new self($cents / 100, $currency);
    }

    public static function fromString(string $value, string $currency = 'BRL'): self {
        // Remove símbolos e formatação
        $cleanValue = preg_replace('/[^0-9.,]/', '', $value);
        
        // Converte para formato decimal
        $cleanValue = str_replace('.', '', $cleanValue);
        $cleanValue = str_replace(',', '.', $cleanValue);
        
        return new self((float) $cleanValue, $currency);
    }

    public static function BRL(float $amount): self {
        return new self($amount, 'BRL');
    }

    public static function USD(float $amount): self {
        return new self($amount, 'USD');
    }

    public static function EUR(float $amount): self {
        return new self($amount, 'EUR');
    }

    public static function zero(string $currency = 'BRL'): self {
        return new self(0, $currency);
    }

    // Métodos de validação
    private function validateAmount(float $amount): void {
        if (is_nan($amount) || is_infinite($amount)) {
            throw new \InvalidArgumentException('Valor monetário inválido');
        }

        if ($amount < -999999999.99 || $amount > 999999999.99) {
            throw new \InvalidArgumentException('Valor monetário fora do intervalo permitido');
        }
    }

    private function validateCurrency(string $currency): void {
        if (empty($currency) || strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Código de moeda inválido');
        }
    }

    private function assertSameCurrency(Money $other): void {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Não é possível operar com moedas diferentes');
        }
    }

    // Métodos mágicos
    public function __toString(): string {
        return $this->toString();
    }

    public function __debugInfo(): array {
        return [
            'amount' => $this->getValue(),
            'currency' => $this->currency,
            'formatted' => $this->format()
        ];
    }

    // Serialização
    public function toArray(): array {
        return [
            'amount' => $this->amount,
            'value' => $this->getValue(),
            'currency' => $this->currency,
            'formatted' => $this->format()
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    // Cálculos de juros e taxas
    public function applyInterest(float $rate, int $periods = 1): Money {
        $amount = $this->getValue() * pow(1 + ($rate / 100), $periods);
        return new self($amount, $this->currency);
    }

    public function applyDiscount(float $rate): Money {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('Taxa de desconto deve estar entre 0 e 100');
        }
        return $this->discount($rate);
    }

    // Arredondamento
    public function round(int $precision = 2): Money {
        $value = round($this->getValue(), $precision);
        return new self($value, $this->currency);
    }

    public function ceil(): Money {
        $value = ceil($this->getValue());
        return new self($value, $this->currency);
    }

    public function floor(): Money {
        $value = floor($this->getValue());
        return new self($value, $this->currency);
    }

    // Análise
    public function getTax(float $rate): Money {
        return $this->percentage($rate);
    }

    public function getNet(float $taxRate): Money {
        return $this->subtract($this->getTax($taxRate));
    }

    public function getGross(float $taxRate): Money {
        return $this->divide(1 - ($taxRate / 100));
    }

    // Formatação avançada
    public function formatAccounting(): string {
        if ($this->isNegative()) {
            return '(' . $this->format() . ')';
        }
        return $this->format();
    }

    public function formatCompact(): string {
        $value = abs($this->getValue());
        $symbol = $this->isNegative() ? '-' : '';
        
        if ($value >= 1000000) {
            return $symbol . $this->getCurrencySymbol() . ' ' . number_format($value / 1000000, 1) . 'M';
        }
        
        if ($value >= 1000) {
            return $symbol . $this->getCurrencySymbol() . ' ' . number_format($value / 1000, 1) . 'K';
        }
        
        return $this->format();
    }

    // Validação de faixas
    public function isBetween(Money $min, Money $max): bool {
        $this->assertSameCurrency($min);
        $this->assertSameCurrency($max);
        
        return $this->greaterThanOrEqual($min) && $this->lessThanOrEqual($max);
    }

    // Operações com arrays
    public static function sum(array $monies): Money {
        if (empty($monies)) {
            return self::zero();
        }

        $first = $monies[0];
        $total = $first;

        for ($i = 1; $i < count($monies); $i++) {
            $total = $total->add($monies[$i]);
        }

        return $total;
    }

    public static function average(array $monies): Money {
        if (empty($monies)) {
            throw new \InvalidArgumentException('Array vazio');
        }

        $sum = self::sum($monies);
        return $sum->divide(count($monies));
    }

    public static function max(array $monies): Money {
        if (empty($monies)) {
            throw new \InvalidArgumentException('Array vazio');
        }

        $max = $monies[0];
        foreach ($monies as $money) {
            if ($money->greaterThan($max)) {
                $max = $money;
            }
        }

        return $max;
    }

    public static function min(array $monies): Money {
        if (empty($monies)) {
            throw new \InvalidArgumentException('Array vazio');
        }

        $min = $monies[0];
        foreach ($monies as $money) {
            if ($money->lessThan($min)) {
                $min = $money;
            }
        }

        return $min;
    }
}
