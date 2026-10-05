<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletTransactionResource;
use Application\UseCases\Wallet\AdminAdjustBalanceUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;
use Infrastructure\Persistence\Eloquent\Models\StudentWallet;
use Infrastructure\Persistence\Eloquent\Models\Ticket;
use Infrastructure\Persistence\Eloquent\Models\User;
use Infrastructure\Persistence\Eloquent\Models\WalletTransaction;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'users:'.md5(json_encode($request->only(['search', 'role', 'is_active', 'per_page', 'page'])));

        $response = Cache::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $query = User::query();

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            }

            if ($role = $request->query('role')) {
                $query->where('role', $role);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            $users = $query->orderBy('created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return [
                'data' => UserResource::collection($users)->resolve(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                ],
            ];
        });

        return response()->json($response);
    }

    public function show(string $id): JsonResponse
    {
        $data = Cache::tags(['users'])->remember("user.{$id}", 300, function () use ($id) {
            return (new UserResource(
                User::withCount('enrollments')->findOrFail($id)
            ))->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => 'student',
            'password' => bcrypt($data['password']),
        ]);

        Cache::tags(['users', 'dashboard'])->flush();

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Utilizador criado com sucesso.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        Cache::tags(['users', 'dashboard'])->flush();

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Utilizador actualizado com sucesso.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        Cache::tags(['users', 'dashboard'])->flush();

        return response()->json(['message' => 'Utilizador removido com sucesso.']);
    }

    public function toggleActive(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $user->update(['is_active' => ! $user->is_active]);

        Cache::tags(['users', 'dashboard'])->flush();

        return response()->json([
            'data' => new UserResource($user),
            'message' => $user->is_active
                ? 'Utilizador activado com sucesso.'
                : 'Utilizador desactivado com sucesso.',
        ]);
    }

    public function enrollments(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $enrollments = Enrollment::where('student_id', $user->id)
            ->with('course:id,title,slug')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20) ?? 20);

        return response()->json([
            'data' => $enrollments->map(fn ($e) => [
                'id' => $e->id,
                'course_id' => $e->course_id,
                'course_title' => $e->course?->title,
                'status' => $e->status,
                'enrolled_at' => $e->enrolled_at?->format('c'),
                'completed_at' => $e->completed_at?->format('c'),
            ]),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function wallet(Request $request, string $id): JsonResponse
    {
        User::where('role', 'student')->findOrFail($id);

        $wallet = StudentWallet::where('student_id', $id)->first();

        $transactions = collect();
        if ($wallet !== null) {
            $transactions = WalletTransaction::where('wallet_id', $wallet->id)
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 20));
        }

        return response()->json([
            'data' => [
                'balance_cents' => $wallet?->balance_cents ?? 0,
                'transactions' => WalletTransactionResource::collection($transactions)->resolve(),
            ],
            'meta' => $transactions instanceof LengthAwarePaginator ? [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ] : null,
        ]);
    }

    public function walletAdjust(Request $request, string $id): JsonResponse
    {
        User::where('role', 'student')->findOrFail($id);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $useCase = app(AdminAdjustBalanceUseCase::class);

        $result = $useCase->execute(
            studentId: $id,
            amountCents: $data['amount_cents'],
            description: $data['description'],
        );

        Cache::tags(['users', 'dashboard'])->flush();

        return response()->json([
            'message' => 'Saldo ajustado com sucesso.',
            'data' => $result,
        ]);
    }

    public function certificates(string $id): JsonResponse
    {
        User::where('role', 'student')->findOrFail($id);

        $certificates = Certificate::where('student_id', $id)
            ->with('enrollment.course:id,title,slug')
            ->orderByDesc('issued_at')
            ->get();

        return response()->json([
            'data' => CertificateResource::collection($certificates)->resolve(),
        ]);
    }

    public function tickets(Request $request, string $id): JsonResponse
    {
        User::where('role', 'student')->findOrFail($id);

        $tickets = Ticket::where('student_id', $id)
            ->with('messages')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'description' => $t->description,
                'priority' => $t->priority,
                'status' => $t->status,
                'created_at' => $t->created_at?->format('c'),
                'messages_count' => $t->messages->count(),
            ]),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function progress(string $id, string $enrollmentId): JsonResponse
    {
        User::where('role', 'student')->findOrFail($id);

        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', $id)
            ->firstOrFail();

        $progress = LessonProgress::where('enrollment_id', $enrollmentId)
            ->with('lesson:id,title,duration_minutes')
            ->get();

        $totalLessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $enrollment->course_id))->count();

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollmentId,
                'status' => $enrollment->status,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $progress->where('is_completed', true)->count(),
                'lessons' => $progress->map(fn ($p) => [
                    'lesson_id' => $p->lesson_id,
                    'lesson_title' => $p->lesson?->title,
                    'is_completed' => $p->is_completed,
                    'watched_seconds' => $p->watched_seconds,
                    'duration_minutes' => $p->lesson?->duration_minutes,
                ]),
            ],
        ]);
    }
}
