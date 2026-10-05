<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use Application\UseCases\Course\CreateLiveLessonUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\Module;
use Infrastructure\Services\PresignedUrlService;

class LessonController extends Controller
{
    public function store(Request $request, string $moduleId): JsonResponse
    {
        $module = Module::whereHas('course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($moduleId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:video,pdf,live'],
            'content_url' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($data['type'] === 'live') {
            try {
                $lesson = app(CreateLiveLessonUseCase::class)->execute(
                    moduleId: $module->id,
                    title: $data['title'],
                    description: $data['description'] ?? null,
                    contentUrl: $data['content_url'] ?? null,
                    durationMinutes: $data['duration_minutes'] ?? null,
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json([
                'data' => new LessonResource($lesson),
                'message' => 'Aula ao vivo criada com sucesso.',
            ], 201);
        }

        $maxOrder = $module->lessons()->max('order') ?? 0;

        $lesson = Lesson::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'content_url' => $data['content_url'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'order' => $maxOrder + 1,
        ]);

        return response()->json([
            'data' => new LessonResource($lesson),
            'message' => 'Aula criada com sucesso.',
        ], 201);
    }

    public function upload(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::whereHas('module.course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($id);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,application/pdf', 'max:512000'],
        ]);

        $file = $request->file('file');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';
        $folder = $isPdf ? 'pdfs' : 'videos';
        $path = $file->store("{$folder}/{$lesson->module_id}", 'minio');

        $lesson->update([
            'content_url' => Storage::disk('minio')->url($path),
        ]);

        return response()->json([
            'data' => new LessonResource($lesson),
            'message' => $isPdf ? 'PDF carregado com sucesso.' : 'Vídeo carregado com sucesso.',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::whereHas('module.course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:video,pdf,live'],
            'content_url' => ['nullable', 'string', 'url'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $lesson->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? $lesson->description,
            'type' => $data['type'],
            'content_url' => $data['content_url'] ?? $lesson->content_url,
            'duration_minutes' => $data['duration_minutes'] ?? $lesson->duration_minutes,
            'order' => $data['order'] ?? $lesson->order,
        ]);

        return response()->json([
            'data' => new LessonResource($lesson),
            'message' => 'Aula atualizada com sucesso.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $lesson = Lesson::whereHas('module.course', function ($q) {
            $q->where('instructor_id', request()->user()->id);
        })->findOrFail($id);

        $lesson->delete();

        return response()->json(['message' => 'Aula removida com sucesso.']);
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::whereHas('module.course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->with('module')->findOrFail($id);

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
                'preview_url' => $previewUrl,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }
}
