<?php

namespace Application\UseCases\Wallet;

use App\Events\VoucherApprovedEvent;
use App\Models\User;
use App\Notifications\VoucherApproved;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Domain\Wallet\Contracts\TransactionRepositoryInterface;
use Domain\Wallet\Contracts\WalletRepositoryInterface;
use Domain\Wallet\Entities\WalletTransaction;
use Domain\Wallet\Services\WalletDomainService;
use Domain\Wallet\ValueObjects\Direction;
use Domain\Wallet\ValueObjects\Money;
use Domain\Wallet\ValueObjects\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;

class ApproveVoucherUseCase
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private WalletDomainService $walletDomainService,
        private AuditLoggerInterface $auditLogger,
    ) {}

    /**
     * @return array{transaction_id: string, new_balance_cents: int}
     */
    public function execute(
        string $voucherId,
        int $amountCents,
        string $adminId,
        ?ActorContext $actor = null,
    ): array {
        $amount = new Money($amountCents);

        return DB::transaction(function () use ($voucherId, $amount, $amountCents, $adminId, $actor) {
            $voucher = PaymentVoucher::where('status', 'pending')->lockForUpdate()->findOrFail($voucherId);

            $wallet = $this->walletRepository->findByStudentIdLockForUpdate($voucher->student_id);

            if ($wallet === null) {
                throw new \RuntimeException('Wallet not found for this student.');
            }

            $balanceBefore = $wallet->getBalance();

            $this->walletDomainService->credit($wallet, $amount);

            $this->walletRepository->save($wallet);

            $balanceAfter = $wallet->getBalance();

            $transaction = new WalletTransaction(
                id: (string) Str::uuid(),
                walletId: $wallet->getId(),
                amount: $amount,
                direction: Direction::In,
                type: TransactionType::CreditPurchase,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                status: 'completed',
                referenceType: 'payment_voucher',
                referenceId: $voucher->id,
                description: 'Voucher aprovado.',
                createdAt: new \DateTimeImmutable,
            );

            $this->transactionRepository->save($transaction);

            $this->auditLogger->log(
                'voucher.approved',
                $voucher,
                $actor ?? new ActorContext(actorId: $adminId, actorRole: 'admin'),
                previousState: ['status' => 'pending', 'balance_cents' => $balanceBefore->getCents()],
                newState: ['status' => 'approved', 'balance_cents' => $balanceAfter->getCents(), 'amount_cents' => $amountCents],
            );

            $voucher->update([
                'status' => 'approved',
                'amount_cents' => $amountCents,
                'transaction_id' => $transaction->getId(),
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            $student = User::find($voucher->student_id);
            if ($student) {
                $student->notify(new VoucherApproved(
                    amountCents: $amountCents,
                    voucherId: $voucher->id,
                ));

                event(new VoucherApprovedEvent(
                    studentId: $voucher->student_id,
                    voucherId: $voucher->id,
                    amountCents: $amountCents,
                ));
            }

            return [
                'transaction_id' => $transaction->getId(),
                'new_balance_cents' => $balanceAfter->getCents(),
            ];
        });
    }
}
