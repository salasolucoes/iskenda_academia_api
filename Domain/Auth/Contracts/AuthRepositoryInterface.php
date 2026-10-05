<?php

namespace Domain\Auth\Contracts;

use Domain\Auth\Entities\User;

interface AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function save(User $user): User;

    public function delete(string $id): void;
}
