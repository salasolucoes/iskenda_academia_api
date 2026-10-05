<?php

namespace Domain\Wallet\Contracts;

use Domain\Wallet\Entities\Wallet;

interface WalletRepositoryInterface
{
    public function findById(string $id): ?Wallet;

    public function findByStudentId(string $studentId): ?Wallet;

    public function findByStudentIdLockForUpdate(string $studentId): ?Wallet;

    public function save(Wallet $wallet): Wallet;
}
