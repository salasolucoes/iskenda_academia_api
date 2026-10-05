<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use Application\UseCases\Course\CreateCourseUseCase;
use Application\UseCases\Course\DeleteCourseUseCase;
use Application\UseCases\Course\ListInstructorCoursesUseCase;
use Application\UseCases\Course\ShowCourseForInstructorUseCase;
use Application\UseCases\Course\UpdateCourseUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    public function __construct(
        private ListInstructorCoursesUseCase $listInstructorCoursesUseCase,
        private ShowCourseForInstructorUseCase $showCourseForInstructorUseCase,
        private CreateCourseUseCase $createCourseUseCase,
        private UpdateCourseUseCase $updateCourseUseCase,
        private DeleteCourseUseCase $deleteCourseUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $courses = $this->listInstructorCoursesUseCase->execute(
            instructorId: $request->user()->id,
        );

        return response()->json([
            'data' => CourseResource::collection($courses),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $course = $this->showCourseForInstructorUseCase->execute(
            id: $id,
            instructorId: $request->user()->id,
        );

        if ($course === null) {
            return response()->json(['message' => 'Curso não encontrado.'], 404);
        }

        return response()->json([
            'data' => new CourseResource($course),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'modality' => ['required', 'string', 'in:online,presential,mixed'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'thumbnail_url' => ['nullable', 'string', 'url'],
        ]);

        try {
            $course = $this->createCourseUseCase->execute(
                instructorId: $request->user()->id,
                categoryId: $data['category_id'] ?? null,
                title: $data['title'],
                description: $data['description'],
                modality: $data['modality'],
                priceCents: $data['price_cents'],
                thumbnailUrl: $data['thumbnail_url'] ?? null,
            );

            return response()->json([
                'data' => new CourseResource($course),
                'message' => 'Curso criado com sucesso.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'title' => [$e->getMessage()],
            ]);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'modality' => ['required', 'string', 'in:online,presential,mixed'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'thumbnail_url' => ['nullable', 'string', 'url'],
        ]);

        try {
            $course = $this->updateCourseUseCase->execute(
                id: $id,
                categoryId: $data['category_id'] ?? null,
                title: $data['title'],
                description: $data['description'],
                modality: $data['modality'],
                priceCents: $data['price_cents'],
                status: $data['status'],
                thumbnailUrl: $data['thumbnail_url'] ?? null,
            );

            return response()->json([
                'data' => new CourseResource($course),
                'message' => 'Curso atualizado com sucesso.',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'title' => [$e->getMessage()],
            ]);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->deleteCourseUseCase->execute($id);

            return response()->json(['message' => 'Curso removido com sucesso.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
