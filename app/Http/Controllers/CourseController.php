<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseResource;
use App\Http\Resources\CurriculumModuleResource;
use Application\UseCases\Course\ListPublishedCoursesUseCase;
use Application\UseCases\Course\ShowCourseUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Course as CourseModel;

class CourseController extends Controller
{
    public function __construct(
        private ListPublishedCoursesUseCase $listPublishedCoursesUseCase,
        private ShowCourseUseCase $showCourseUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter($request->only(['modality', 'category_id', 'search']));

        $courses = $this->listPublishedCoursesUseCase->execute($filters);

        return response()->json([
            'data' => CourseResource::collection($courses),
        ]);
    }

    public function show(string $course): JsonResponse
    {
        $course = $this->showCourseUseCase->execute($course);

        if ($course === null || ! $course->isPublished()) {
            return response()->json(['message' => 'Curso não encontrado.'], 404);
        }

        $curriculum = CourseModel::query()
            ->with('modules.lessons')
            ->find($course->getId());

        return response()->json([
            'data' => array_merge(
                (new CourseResource($course))->resolve(request()),
                [
                    'modules' => CurriculumModuleResource::collection(
                        $curriculum?->modules ?? collect(),
                    )->resolve(request()),
                ],
            ),
        ]);
    }
}
