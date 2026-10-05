<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\User;
use Infrastructure\Services\PresignedUrlService;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'courses:'.md5(json_encode($request->only(['search', 'status', 'category_id', 'instructor_id', 'per_page', 'page'])));

        $response = Cache::tags(['courses'])->remember($cacheKey, 300, function () use ($request) {
            $query = Course::with(['instructor:id,name,email', 'category:id,name']);

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                });
            }

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            if ($categoryId = $request->query('category_id')) {
                $query->where('category_id', $categoryId);
            }

            if ($instructorId = $request->query('instructor_id')) {
                $query->where('instructor_id', $instructorId);
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

    public function show(string $id): JsonResponse
    {
        $data = Cache::tags(['courses'])->remember("course.{$id}", 300, function () use ($id) {
            return (new CourseResource(
                Course::with(['instructor', 'category', 'modules.lessons'])->findOrFail($id)
            ))->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'instructor_id' => ['required', 'string', 'exists:users,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'modality' => ['required', 'string', 'in:online,presential,mixed'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'status' => ['string', 'in:draft,published,archived'],
            'thumbnail_url' => ['nullable', 'string', 'url'],
        ]);

        $course = Course::create([
            'id' => (string) Str::uuid(),
            'instructor_id' => $data['instructor_id'],
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::random(4),
            'description' => $data['description'],
            'modality' => $data['modality'],
            'price_cents' => $data['price_cents'],
            'status' => $data['status'] ?? 'draft',
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
        ]);

        $course->load(['instructor', 'category']);

        Cache::tags(['courses', 'dashboard'])->flush();

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Curso criado com sucesso.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'instructor_id' => ['required', 'string', 'exists:users,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'modality' => ['required', 'string', 'in:online,presential,mixed'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'thumbnail_url' => ['nullable', 'string', 'url'],
        ]);

        $course->update([
            'instructor_id' => $data['instructor_id'],
            'category_id' => $data['category_id'] ?? $course->category_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'modality' => $data['modality'],
            'price_cents' => $data['price_cents'],
            'status' => $data['status'],
            'thumbnail_url' => $data['thumbnail_url'] ?? $course->thumbnail_url,
        ]);

        $course->load(['instructor', 'category']);

        Cache::tags(['courses', 'dashboard'])->flush();

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Curso actualizado com sucesso.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->delete();

        Cache::tags(['courses', 'dashboard'])->flush();

        return response()->json(['message' => 'Curso removido com sucesso.']);
    }

    public function publishToggle(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $course->update(['status' => $data['status']]);

        Cache::tags(['courses', 'dashboard'])->flush();

        return response()->json([
            'data' => [
                'id' => $course->id,
                'status' => $course->status,
            ],
            'message' => $data['status'] === 'published'
                ? 'Curso publicado com sucesso.'
                : ($data['status'] === 'archived' ? 'Curso arquivado com sucesso.' : 'Curso voltou ao rascunho.'),
        ]);
    }

    public function changeInstructor(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'instructor_id' => ['required', 'string', 'exists:users,id'],
        ]);

        $instructor = User::where('id', $data['instructor_id'])
            ->where('role', 'instructor')
            ->first();

        if ($instructor === null) {
            return response()->json(['message' => 'O utilizador selecionado não é um instrutor válido.'], 422);
        }

        $course->update(['instructor_id' => $data['instructor_id']]);
        $course->load(['instructor', 'category']);

        Cache::tags(['courses'])->flush();

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Instrutor do curso alterado com sucesso.',
        ]);
    }

    public function lessonPreview(string $lessonId): JsonResponse
    {
        $lesson = Lesson::with('module.course')->findOrFail($lessonId);

        if ($lesson->content_url === null) {
            return response()->json(['message' => 'Esta aula ainda nao tem conteudo multimédia.'], 404);
        }

        $previewUrl = app(PresignedUrlService::class)->generate(
            'videos/'.app(PresignedUrlService::class)->extractKeyFromUrl($lesson->content_url, 'videos'),
        );

        return response()->json([
            'data' => [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'course_title' => $lesson->module->course->title ?? null,
                'preview_url' => $previewUrl,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }
}
