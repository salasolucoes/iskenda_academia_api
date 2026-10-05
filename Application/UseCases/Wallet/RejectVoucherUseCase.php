<?php

namespace Application\UseCases\Wallet;

use App\Events\VoucherRejectedEvent;
use App\Models\User;
use App\Notifications\VoucherRejected;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;

class RejectVoucherUseCase
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function execute(
        string $voucherId,
        string $rejectionReason,
        string $adminId,
        ?ActorContext $actor = null,
    ): void {
        DB::transaction(function () use ($voucherId, $rejectionReason, $adminId, $actor) {
            $voucher = PaymentVoucher::where('status', 'pending')->lockForUpdate()->findOrFail($voucherId);

            $this->auditLogger->log(
                'voucher.rejected',
                $voucher,
                $actor ?? new ActorContext(actorId: $adminId, actorRole: 'admin'),
                previousState: ['status' => 'pending'],
                newState: ['status' => 'rejected', 'rejection_reason' => $rejectionReason],
            );

            $voucher->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'rejected_by' => $adminId,
                'rejected_at' => now(),
            ]);

            $student = User::find($voucher->student_id);
            if ($student) {
                $student->notify(new VoucherRejected(
                    voucherId: $voucher->id,
                    reason: $rejectionReason,
                ));

                event(new VoucherRejectedEvent(
                    studentId: $voucher->student_id,
                    voucherId: $voucher->id,
                    reason: $rejectionReason,
                ));
            }
        });
    }
}
