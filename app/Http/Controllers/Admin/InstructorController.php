<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CourseResource;
use App\Http\Resources\InstructorResource;
use App\Http\Resources\LiveSessionResource;
use Application\UseCases\Admin\CreateInstructorUseCase;
use Application\UseCases\Admin\DeleteInstructorUseCase;
use Application\UseCases\Admin\ListInstructorsUseCase;
use Application\UseCases\Admin\ShowInstructorUseCase;
use Application\UseCases\Admin\UpdateInstructorUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Persistence\Eloquent\Models\OrderItem;
use Infrastructure\Persistence\Eloquent\Models\User;

class InstructorController extends Controller
{
    public function __construct(
        private ListInstructorsUseCase $listInstructorsUseCase,
        private ShowInstructorUseCase $showInstructorUseCase,
        private CreateInstructorUseCase $createInstructorUseCase,
        private UpdateInstructorUseCase $updateInstructorUseCase,
        private DeleteInstructorUseCase $deleteInstructorUseCase,
    ) {}

    public function index(): JsonResponse
    {
        $data = Cache::tags(['instructors'])->remember('instructors.all', 300, function () {
            return InstructorResource::collection(
                $this->listInstructorsUseCase->execute()
            )->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $instructor = $this->createInstructorUseCase->execute(
                name: $data['name'],
                email: $data['email'],
                phone: $data['phone'] ?? null,
            );

            Cache::tags(['instructors', 'dashboard'])->flush();

            return response()->json([
                'data' => new InstructorResource($instructor),
                'message' => 'Instrutor criado com sucesso. Um OTP foi enviado para o email.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function show(string $id): JsonResponse
    {
        $instructorData = Cache::tags(['instructors'])->remember("instructor.{$id}", 300, function () use ($id) {
            $instructor = $this->showInstructorUseCase->execute($id);

            return $instructor !== null
                ? (new InstructorResource($instructor))->resolve()
                : null;
        });

        if ($instructorData === null) {
            return response()->json(['message' => 'Instrutor não encontrado.'], 404);
        }

        return response()->json([
            'data' => $instructorData,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $instructor = $this->updateInstructorUseCase->execute(
                id: $id,
                name: $data['name'],
                email: $data['email'],
                phone: $data['phone'] ?? null,
            );

            Cache::tags(['instructors'])->flush();

            return response()->json([
                'data' => new InstructorResource($instructor),
                'message' => 'Instrutor actualizado com sucesso.',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->deleteInstructorUseCase->execute($id);

            Cache::tags(['instructors', 'dashboard'])->flush();

            return response()->json(['message' => 'Instrutor removido com sucesso.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function verify(string $id): JsonResponse
    {
        $instructor = User::where('role', 'instructor')->findOrFail($id);
        $instructor->update([
            'email_verified_at' => $instructor->email_verified_at ?? now(),
            'is_active' => true,
        ]);

        Cache::tags(['instructors'])->flush();

        return response()->json([
            'data' => new InstructorResource($instructor),
            'message' => 'Instrutor verificado com sucesso.',
        ]);
    }

    public function unverify(string $id): JsonResponse
    {
        $instructor = User::where('role', 'instructor')->findOrFail($id);
        $instructor->update([
            'email_verified_at' => null,
        ]);

        Cache::tags(['instructors'])->flush();

        return response()->json([
            'data' => new InstructorResource($instructor),
            'message' => 'Instrutor marcado como não verificado.',
        ]);
    }

    public function courses(Request $request, string $id): JsonResponse
    {
        $cacheKey = "instructor.courses.{$id}:".md5(json_encode($request->only(['status', 'per_page', 'page'])));

        $response = Cache::tags(['courses', 'instructors'])->remember($cacheKey, 300, function () use ($request, $id) {
            $instructor = User::where('role', 'instructor')->findOrFail($id);

            $query = Course::where('instructor_id', $instructor->id)
                ->with(['category:id,name', 'instructor:id,name,email']);

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            $courses = $query->orderBy('created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return [
                'data' => CourseResource::collection($courses)->resolve(),
                'meta' => [
                    'current_page' => $courses->currentPage(),
                    'last_page' => $courses->lastPage(),
                    'total' => $courses->total(),
                ],
            ];
        });

        return response()->json($response);
    }

    public function stats(string $id): JsonResponse
    {
        User::where('role', 'instructor')->findOrFail($id);

        $courseIds = Course::where('instructor_id', $id)->pluck('id');

        $stats = [
            'total_courses' => $courseIds->count(),
            'published_courses' => Course::where('instructor_id', $id)
                ->where('status', 'published')->count(),
            'total_students' => Enrollment::whereIn('course_id', $courseIds)
                ->distinct('student_id')
                ->count('student_id'),
            'total_lessons' => Lesson::whereHas('module', fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
            'total_live_sessions' => LiveSession::whereHas('lesson.module', fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
            'revenue_cents' => (int) OrderItem::whereIn('course_id', $courseIds)->sum('price_cents'),
        ];

        return response()->json(['data' => $stats]);
    }

    public function students(Request $request, string $id): JsonResponse
    {
        User::where('role', 'instructor')->findOrFail($id);

        $courseIds = Course::where('instructor_id', $id)->pluck('id');

        $students = User::whereHas('enrollments', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->withCount(['enrollments as enrolled_courses_count' => fn ($q) => $q->whereIn('course_id', $courseIds)])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $students->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'enrolled_courses_count' => $s->enrolled_courses_count,
                'created_at' => $s->created_at?->format('c'),
            ]),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function liveSessions(Request $request, string $id): JsonResponse
    {
        User::where('role', 'instructor')->findOrFail($id);

        $sessions = LiveSession::whereHas('lesson.module.course', fn ($q) => $q->where('instructor_id', $id))
            ->with('lesson.module.course:id,title')
            ->orderByDesc('scheduled_start')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => LiveSessionResource::collection($sessions)->resolve(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }
}
