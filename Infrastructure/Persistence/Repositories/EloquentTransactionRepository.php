<?php

namespace Infrastructure\Persistence\Repositories;

use Domain\Wallet\Contracts\TransactionRepositoryInterface;
use Domain\Wallet\Entities\WalletTransaction as TransactionEntity;
use Domain\Wallet\ValueObjects\Direction;
use Domain\Wallet\ValueObjects\Money;
use Domain\Wallet\ValueObjects\TransactionType;
use Infrastructure\Persistence\Eloquent\Models\WalletTransaction as TransactionModel;

class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function findById(string $id): ?TransactionEntity
    {
        $model = TransactionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByWalletId(string $walletId): array
    {
        return TransactionModel::where('wallet_id', $walletId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (TransactionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(TransactionEntity $transaction): TransactionEntity
    {
        $model = TransactionModel::create([
            'id' => $transaction->getId(),
            'wallet_id' => $transaction->getWalletId(),
            'amount_cents' => $transaction->getAmount()->getCents(),
            'direction' => $transaction->getDirection()->value,
            'type' => $transaction->getType()->value,
            'balance_before' => $transaction->getBalanceBefore()->getCents(),
            'balance_after' => $transaction->getBalanceAfter()->getCents(),
            'status' => $transaction->getStatus(),
            'reference_type' => $transaction->getReferenceType(),
            'reference_id' => $transaction->getReferenceId(),
            'description' => $transaction->getDescription(),
        ]);

        return $this->toEntity($model->fresh());
    }

    private function toEntity(TransactionModel $model): TransactionEntity
    {
        return new TransactionEntity(
            id: $model->id,
            walletId: $model->wallet_id,
            amount: new Money($model->amount_cents),
            direction: Direction::from($model->direction),
            type: TransactionType::from($model->type),
            balanceBefore: new Money($model->balance_before),
            balanceAfter: new Money($model->balance_after),
            status: $model->status,
            referenceType: $model->reference_type,
            referenceId: $model->reference_id,
            description: $model->description,
            createdAt: $model->created_at?->toImmutable(),
        );
    }
}
