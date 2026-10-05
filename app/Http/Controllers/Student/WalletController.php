<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VoucherUploaded;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Domain\Wallet\Contracts\TransactionRepositoryInterface;
use Domain\Wallet\Contracts\WalletRepositoryInterface;
use Domain\Wallet\Entities\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;

class WalletController extends Controller
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;
        $wallet = $this->walletRepository->findByStudentId($studentId);

        if ($wallet === null) {
            return response()->json(['balance_cents' => 0, 'transactions' => []]);
        }

        $transactions = $this->transactionRepository->findByWalletId($wallet->getId());

        return response()->json([
            'balance_cents' => $wallet->getBalance()->getCents(),
            'transactions' => array_map(fn (WalletTransaction $t) => [
                'id' => $t->getId(),
                'amount_cents' => $t->getAmount()->getCents(),
                'direction' => $t->getDirection()->value,
                'type' => $t->getType()->value,
                'description' => $t->getDescription(),
                'created_at' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $transactions),
        ]);
    }

    public function uploadVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'string', 'uuid'],
        ]);

        $studentId = $request->user()->id;

        $file = $request->file('file');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $path = $file->store("vouchers/{$studentId}", 'minio');

        $voucher = PaymentVoucher::create([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'status' => 'pending',
            'file_path' => $path,
            'file_hash' => $fileHash,
            'amount_cents' => $request->input('amount_cents'),
        ]);

        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new VoucherUploaded(
            studentName: $request->user()->name,
            voucherId: $voucher->id,
        ));

        $this->auditLogger->log(
            'voucher.uploaded',
            $voucher,
            new ActorContext(actorId: $studentId, actorRole: 'student', actorIp: $request->ip()),
            newState: ['amount_cents' => $request->input('amount_cents'), 'file_path' => $path],
        );

        return response()->json([
            'message' => 'Voucher uploaded successfully. Awaiting approval.',
            'voucher_id' => $voucher->id,
        ], 201);
    }
}
