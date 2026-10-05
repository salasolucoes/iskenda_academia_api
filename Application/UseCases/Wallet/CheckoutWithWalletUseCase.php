<?php

namespace Application\UseCases\Wallet;

use Application\UseCases\Enrollment\IssueEnrollmentUseCase;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
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

class CheckoutWithWalletUseCase
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private EnrollmentRepositoryInterface $enrollmentRepository,
        private WalletDomainService $walletDomainService,
        private IssueEnrollmentUseCase $issueEnrollmentUseCase,
        private AuditLoggerInterface $auditLogger,
    ) {}

    /**
     * @param  array<int, array{course_id: string, price_cents: int}>  $items
     * @return array{order_id: string, enrollment_ids: string[]}
     */
    public function execute(string $studentId, array $items, string $orderId, ?ActorContext $actor = null): array
    {
        $totalCents = array_sum(array_column($items, 'price_cents'));
        $total = new Money($totalCents);

        $wallet = $this->walletRepository->findByStudentIdLockForUpdate($studentId);

        if ($wallet === null) {
            throw new \RuntimeException('Wallet not found.');
        }

        if (! $wallet->hasSufficientBalance($total)) {
            throw new \DomainException('Insufficient wallet balance.');
        }

        $balanceBefore = $wallet->getBalance();

        $this->walletDomainService->debit($wallet, $total);

        $this->walletRepository->save($wallet);

        $balanceAfter = $wallet->getBalance();

        $transaction = new WalletTransaction(
            id: (string) Str::uuid(),
            walletId: $wallet->getId(),
            amount: $total,
            direction: Direction::Out,
            type: TransactionType::CoursePayment,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            status: 'completed',
            referenceType: 'order',
            referenceId: $orderId,
            description: 'Payment for course enrollment',
            createdAt: new \DateTimeImmutable,
        );

        $this->transactionRepository->save($transaction);

        $this->auditLogger->log(
            'wallet.debit.checkout',
            $transaction,
            $actor ?? new ActorContext,
            previousState: ['balance_cents' => $balanceBefore->getCents()],
            newState: ['balance_cents' => $balanceAfter->getCents(), 'order_id' => $orderId],
        );

        $enrollmentIds = [];
        foreach ($items as $item) {
            $enrollment = $this->issueEnrollmentUseCase->execute(
                studentId: $studentId,
                courseId: $item['course_id'],
                orderId: $orderId,
            );
            $enrollmentIds[] = $enrollment->getId();
        }

        return [
            'order_id' => $orderId,
            'enrollment_ids' => $enrollmentIds,
        ];
    }
}
