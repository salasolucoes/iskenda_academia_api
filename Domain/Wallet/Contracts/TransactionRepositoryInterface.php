<?php

namespace Domain\Wallet\Contracts;

use Domain\Wallet\Entities\WalletTransaction;

interface TransactionRepositoryInterface
{
    public function findById(string $id): ?WalletTransaction;

    public function findByWalletId(string $walletId): array;

    public function save(WalletTransaction $transaction): WalletTransaction;
}
