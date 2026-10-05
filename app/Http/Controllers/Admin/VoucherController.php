<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentVoucherResource;
use Application\UseCases\Wallet\ApproveVoucherUseCase;
use Application\UseCases\Wallet\RejectVoucherUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;

class VoucherController extends Controller
{
    public function __construct(
        private ApproveVoucherUseCase $approveVoucherUseCase,
        private RejectVoucherUseCase $rejectVoucherUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'vouchers:'.md5(json_encode($request->only(['status', 'per_page', 'page'])));

        $response = Cache::tags(['vouchers'])->remember($cacheKey, 300, function () use ($request) {
            $query = PaymentVoucher::with('student:id,name,email');

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            $vouchers = $query->orderBy('created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return [
                'data' => PaymentVoucherResource::collection($vouchers)->resolve(),
                'meta' => [
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                    'total' => $vouchers->total(),
                ],
            ];
        });

        return response()->json($response);
    }

    public function show(string $id): JsonResponse
    {
        $data = Cache::tags(['vouchers'])->remember("voucher.{$id}", 300, function () use ($id) {
            return (new PaymentVoucherResource(
                PaymentVoucher::with('student:id,name,email')->findOrFail($id)
            ))->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->approveVoucherUseCase->execute(
            voucherId: $id,
            amountCents: $data['amount_cents'],
            adminId: $request->user()->id,
        );

        Cache::tags(['vouchers', 'dashboard'])->flush();

        $voucher = PaymentVoucher::with('student:id,name,email')->findOrFail($id);

        return response()->json([
            'data' => new PaymentVoucherResource($voucher),
            'message' => 'Voucher aprovado e carteira creditada com sucesso.',
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->rejectVoucherUseCase->execute(
            voucherId: $id,
            rejectionReason: $data['rejection_reason'],
            adminId: $request->user()->id,
        );

        Cache::tags(['vouchers', 'dashboard'])->flush();

        $voucher = PaymentVoucher::with('student:id,name,email')->findOrFail($id);

        return response()->json([
            'data' => new PaymentVoucherResource($voucher),
            'message' => 'Voucher rejeitado.',
        ]);
    }
}
