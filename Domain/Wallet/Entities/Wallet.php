<?php

namespace Domain\Wallet\Entities;

use Domain\Wallet\ValueObjects\Money;

class Wallet
{
    public function __construct(
        private readonly string $id,
        private readonly string $studentId,
        private Money $balance,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function getBalance(): Money
    {
        return $this->balance;
    }

    public function debit(Money $amount): void
    {
        $this->balance = $this->balance->subtract($amount);
    }

    public function credit(Money $amount): void
    {
        $this->balance = $this->balance->add($amount);
    }

    public function hasSufficientBalance(Money $amount): bool
    {
        return $this->balance->isGreaterThanOrEqual($amount);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
