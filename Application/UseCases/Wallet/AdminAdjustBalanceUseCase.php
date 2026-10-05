<?php

namespace Application\UseCases\Wallet;

use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Domain\Wallet\Contracts\TransactionRepositoryInterface;
use Domain\Wallet\Contracts\WalletRepositoryInterface;
use Domain\Wallet\Entities\WalletTransaction;
use Domain\Wallet\Services\WalletDomainService;
use Domain\Wallet\ValueObjects\Direction;
use Domain\Wallet\ValueObjects\Money;
use Domain\Wallet\ValueObjects\TransactionType;
use Illuminate\Support\Str;

class AdminAdjustBalanceUseCase
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private WalletDomainService $walletDomainService,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function execute(string $studentId, int $amountCents, string $description, ?ActorContext $actor = null): array
    {
        $amount = new Money(abs($amountCents));
        $direction = $amountCents >= 0 ? Direction::In : Direction::Out;

        $wallet = $this->walletRepository->findByStudentIdLockForUpdate($studentId);

        if ($wallet === null) {
            throw new \RuntimeException('Wallet not found.');
        }

        $balanceBefore = $wallet->getBalance();

        if ($direction === Direction::In) {
            $this->walletDomainService->credit($wallet, $amount);
        } else {
            $this->walletDomainService->debit($wallet, $amount);
        }

        $this->walletRepository->save($wallet);

        $balanceAfter = $wallet->getBalance();

        $transaction = new WalletTransaction(
            id: (string) Str::uuid(),
            walletId: $wallet->getId(),
            amount: $amount,
            direction: $direction,
            type: TransactionType::AdminAdjustment,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            status: 'completed',
            description: $description,
            createdAt: new \DateTimeImmutable,
        );

        $this->transactionRepository->save($transaction);

        $this->auditLogger->log(
            'wallet.credit.admin',
            $transaction,
            $actor ?? new ActorContext(actorRole: 'admin'),
            previousState: ['balance_cents' => $balanceBefore->getCents()],
            newState: ['balance_cents' => $balanceAfter->getCents(), 'amount_cents' => $amountCents, 'description' => $description],
        );

        return [
            'transaction_id' => $transaction->getId(),
            'new_balance_cents' => $balanceAfter->getCents(),
        ];
    }
}
