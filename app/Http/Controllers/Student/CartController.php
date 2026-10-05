<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Application\UseCases\Wallet\CheckoutWithWalletUseCase;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Cart;
use Infrastructure\Persistence\Eloquent\Models\CartItem;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Order;
use Infrastructure\Persistence\Eloquent\Models\OrderItem;

class CartController extends Controller
{
    public function __construct(
        private CheckoutWithWalletUseCase $checkoutWithWalletUseCase,
        private EnrollmentRepositoryInterface $enrollmentRepository,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $cart = Cart::firstOrCreate(
            ['student_id' => $studentId],
            ['id' => (string) Str::uuid()],
        );

        $items = $cart->items()->with('course')->get();

        $totalCents = $items->sum(fn ($item) => $item->course?->price_cents ?? 0);

        return response()->json([
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'course_id' => $item->course_id,
                    'title' => $item->course?->title,
                    'price_cents' => $item->course?->price_cents ?? 0,
                    'created_at' => $item->created_at,
                ]),
                'total_cents' => $totalCents,
            ],
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => ['required', 'string', 'uuid', 'exists:courses,id'],
        ]);

        $studentId = $request->user()->id;
        $courseId = $request->input('course_id');

        $course = Course::findOrFail($courseId);

        if ($course->status !== 'published') {
            return response()->json(['message' => 'Course is not available.'], 422);
        }

        $existingEnrollment = $this->enrollmentRepository->findByStudentAndCourse($studentId, $courseId);
        if ($existingEnrollment !== null) {
            return response()->json(['message' => 'Already enrolled in this course.'], 422);
        }

        $cart = Cart::firstOrCreate(
            ['student_id' => $studentId],
            ['id' => (string) Str::uuid()],
        );

        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('course_id', $courseId)
            ->first();

        if ($existingItem !== null) {
            return response()->json(['message' => 'Course already in cart.'], 422);
        }

        $item = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'course_id' => $courseId,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function removeItem(string $itemId, Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $cart = Cart::where('student_id', $studentId)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        return response()->json(['message' => 'Item removed from cart.']);
    }

    public function checkout(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $cart = Cart::where('student_id', $studentId)->first();

        if ($cart === null || $cart->items()->count() === 0) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $items = $cart->items()->with('course')->get();
        $cartItems = $items->map(fn ($item) => [
            'course_id' => $item->course_id,
            'price_cents' => $item->course?->price_cents ?? 0,
        ])->toArray();

        $totalCents = array_sum(array_column($cartItems, 'price_cents'));

        $result = DB::transaction(function () use ($studentId, $cartItems, $totalCents, $cart) {
            $order = Order::create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'total_cents' => $totalCents,
                'payment_method' => 'wallet',
                'status' => 'pending',
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'course_id' => $item['course_id'],
                    'price_cents' => $item['price_cents'],
                ]);
            }

            $checkoutResult = $this->checkoutWithWalletUseCase->execute(
                studentId: $studentId,
                items: $cartItems,
                orderId: $order->id,
            );

            $order->update(['status' => 'completed']);

            $cart->items()->delete();

            return [
                'order_id' => $order->id,
                'enrollment_ids' => $checkoutResult['enrollment_ids'],
            ];
        });

        return response()->json(['data' => $result], 201);
    }
}
