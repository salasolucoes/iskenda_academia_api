<?php

namespace Domain\Wallet\ValueObjects;

class Money
{
    public function __construct(
        private readonly int $cents,
    ) {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public function getCents(): int
    {
        return $this->cents;
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(Money $other): self
    {
        $result = $this->cents - $other->cents;
        if ($result < 0) {
            throw new \UnderflowException('Insufficient funds.');
        }

        return new self($result);
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function toFloat(): float
    {
        return $this->cents / 100;
    }

    public static function fromFloat(float $amount): self
    {
        return new self((int) round($amount * 100));
    }
}
