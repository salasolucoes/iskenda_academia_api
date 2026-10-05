<?php

namespace Domain\Wallet\Entities;

use Domain\Wallet\ValueObjects\Direction;
use Domain\Wallet\ValueObjects\Money;
use Domain\Wallet\ValueObjects\TransactionType;

class WalletTransaction
{
    public function __construct(
        private readonly string $id,
        private readonly string $walletId,
        private readonly Money $amount,
        private readonly Direction $direction,
        private readonly TransactionType $type,
        private readonly Money $balanceBefore,
        private readonly Money $balanceAfter,
        private readonly string $status = 'completed',
        private readonly ?string $referenceType = null,
        private readonly ?string $referenceId = null,
        private readonly ?string $description = null,
        private readonly ?\DateTimeImmutable $createdAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getWalletId(): string
    {
        return $this->walletId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDirection(): Direction
    {
        return $this->direction;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getBalanceBefore(): Money
    {
        return $this->balanceBefore;
    }

    public function getBalanceAfter(): Money
    {
        return $this->balanceAfter;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
