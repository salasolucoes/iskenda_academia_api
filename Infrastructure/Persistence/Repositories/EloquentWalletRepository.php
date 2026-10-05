<?php

namespace Infrastructure\Persistence\Repositories;

use Domain\Wallet\Contracts\WalletRepositoryInterface;
use Domain\Wallet\Entities\Wallet as WalletEntity;
use Domain\Wallet\ValueObjects\Money;
use Infrastructure\Persistence\Eloquent\Models\StudentWallet as WalletModel;

class EloquentWalletRepository implements WalletRepositoryInterface
{
    public function findById(string $id): ?WalletEntity
    {
        $model = WalletModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStudentId(string $studentId): ?WalletEntity
    {
        $model = WalletModel::where('student_id', $studentId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStudentIdLockForUpdate(string $studentId): ?WalletEntity
    {
        $model = WalletModel::where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(WalletEntity $wallet): WalletEntity
    {
        $model = WalletModel::updateOrCreate(
            ['id' => $wallet->getId()],
            [
                'student_id' => $wallet->getStudentId(),
                'balance_cents' => $wallet->getBalance()->getCents(),
            ]
        );

        return $this->toEntity($model->fresh());
    }

    private function toEntity(WalletModel $model): WalletEntity
    {
        return new WalletEntity(
            id: $model->id,
            studentId: $model->student_id,
            balance: new Money($model->balance_cents),
            createdAt: $model->created_at?->toImmutable(),
            updatedAt: $model->updated_at?->toImmutable(),
        );
    }
}
