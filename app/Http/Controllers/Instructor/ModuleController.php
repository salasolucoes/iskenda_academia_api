<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Module;

class ModuleController extends Controller
{
    public function index(Request $request, string $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)
            ->findOrFail($courseId);

        $modules = $course->modules()->with('lessons')->get();

        return response()->json([
            'data' => ModuleResource::collection($modules),
        ]);
    }

    public function store(Request $request, string $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)
            ->findOrFail($courseId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $maxOrder = $course->modules()->max('order') ?? 0;

        $module = Module::create([
            'id' => (string) Str::uuid(),
            'course_id' => $course->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ]);

        $module->load('lessons');

        return response()->json([
            'data' => new ModuleResource($module),
            'message' => 'Módulo criado com sucesso.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $module = Module::whereHas('course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $module->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? $module->description,
            'order' => $data['order'] ?? $module->order,
        ]);

        $module->load('lessons');

        return response()->json([
            'data' => new ModuleResource($module),
            'message' => 'Módulo atualizado com sucesso.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $module = Module::withCount('lessons')->findOrFail($id);

        // Only instructor who owns the course can delete
        $course = Course::where('instructor_id', request()->user()->id)
            ->where('id', $module->course_id)
            ->first();

        if ($course === null) {
            return response()->json(['message' => 'Módulo não encontrado.'], 404);
        }

        if ($module->lessons_count > 0) {
            throw ValidationException::withMessages([
                'module' => ['Remova todas as aulas antes de eliminar o módulo.'],
            ]);
        }

        $module->delete();

        return response()->json(['message' => 'Módulo removido com sucesso.']);
    }
}
