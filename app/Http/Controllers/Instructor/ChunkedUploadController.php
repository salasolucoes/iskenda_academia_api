<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Lesson;

class ChunkedUploadController extends Controller
{
    private const CHUNK_SIZE = 5 * 1024 * 1024;

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => ['required', 'string'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,application/pdf'],
        ]);

        $lesson = Lesson::whereHas('module.course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($data['lesson_id']);

        $totalChunks = (int) ceil($data['file_size'] / self::CHUNK_SIZE);
        $sessionId = (string) Str::uuid();

        $tempDir = storage_path("app/chunked_uploads/{$sessionId}");
        \File::makeDirectory($tempDir, 0755, true, true);

        $meta = [
            'session_id' => $sessionId,
            'lesson_id' => $lesson->id,
            'module_id' => $lesson->module_id,
            'file_name' => $data['file_name'],
            'file_size' => $data['file_size'],
            'mime_type' => $data['mime_type'],
            'total_chunks' => $totalChunks,
            'chunks_received' => [],
            'created_at' => now()->toIso8601String(),
        ];

        \File::put("{$tempDir}/meta.json", json_encode($meta));

        return response()->json([
            'data' => [
                'session_id' => $sessionId,
                'chunk_size' => self::CHUNK_SIZE,
                'total_chunks' => $totalChunks,
            ],
            'message' => 'Sessao de upload criada.',
        ], 201);
    }

    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:6144'],
        ]);

        $tempDir = storage_path("app/chunked_uploads/{$data['session_id']}");
        $metaPath = "{$tempDir}/meta.json";

        if (! \File::exists($metaPath)) {
            return response()->json(['message' => 'Sessao invalida ou expirada.'], 404);
        }

        $meta = json_decode(\File::get($metaPath), true);

        if ($data['chunk_index'] >= $meta['total_chunks']) {
            return response()->json(['message' => 'Indice do chunk invalido.'], 422);
        }

        $chunkFile = $request->file('chunk');
        $chunkFile->move($tempDir, "{$data['chunk_index']}.part");

        $meta['chunks_received'][] = $data['chunk_index'];
        $meta['chunks_received'] = array_values(array_unique($meta['chunks_received']));
        \File::put($metaPath, json_encode($meta));

        $received = count($meta['chunks_received']);

        return response()->json([
            'data' => [
                'chunks_received' => $received,
                'total_chunks' => $meta['total_chunks'],
                'progress' => round(($received / $meta['total_chunks']) * 100),
            ],
            'message' => "Chunk {$data['chunk_index']} recebido.",
        ]);
    }

    public function complete(Request $request, string $sessionId): JsonResponse
    {
        $tempDir = storage_path("app/chunked_uploads/{$sessionId}");
        $metaPath = "{$tempDir}/meta.json";

        if (! \File::exists($metaPath)) {
            return response()->json(['message' => 'Sessao invalida ou expirada.'], 404);
        }

        $meta = json_decode(\File::get($metaPath), true);

        if (count($meta['chunks_received']) !== $meta['total_chunks']) {
            return response()->json([
                'message' => 'Upload incompleto.',
                'chunks_received' => count($meta['chunks_received']),
                'total_chunks' => $meta['total_chunks'],
            ], 422);
        }

        $lesson = Lesson::whereHas('module.course', function ($q) use ($request) {
            $q->where('instructor_id', $request->user()->id);
        })->findOrFail($meta['lesson_id']);

        $mergedPath = "{$tempDir}/merged_{$meta['file_name']}";
        $out = fopen($mergedPath, 'wb');

        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            $partFile = "{$tempDir}/{$i}.part";
            if (! \File::exists($partFile)) {
                fclose($out);
                \File::deleteDirectory($tempDir);

                return response()->json(['message' => "Chunk {$i} em falta."], 422);
            }
            $in = fopen($partFile, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        $storedPath = Storage::disk('minio')->put(
            "videos/{$meta['module_id']}/{$meta['file_name']}",
            file_get_contents($mergedPath),
            'public'
        );

        if (! $storedPath) {
            \File::deleteDirectory($tempDir);

            return response()->json(['message' => 'Erro ao guardar ficheiro no armazenamento.'], 500);
        }

        $url = Storage::disk('minio')->url("videos/{$meta['module_id']}/{$meta['file_name']}");

        $lesson->update(['content_url' => $url]);

        \File::deleteDirectory($tempDir);

        return response()->json([
            'data' => new LessonResource($lesson->fresh()),
            'message' => 'Upload concluido com sucesso.',
        ]);
    }

    public function status(Request $request, string $sessionId): JsonResponse
    {
        $tempDir = storage_path("app/chunked_uploads/{$sessionId}");
        $metaPath = "{$tempDir}/meta.json";

        if (! \File::exists($metaPath)) {
            return response()->json(['message' => 'Sessao invalida ou expirada.'], 404);
        }

        $meta = json_decode(\File::get($metaPath), true);

        return response()->json([
            'data' => [
                'session_id' => $sessionId,
                'chunks_received' => count($meta['chunks_received']),
                'total_chunks' => $meta['total_chunks'],
                'progress' => round((count($meta['chunks_received']) / $meta['total_chunks']) * 100),
                'missing_chunks' => array_values(array_diff(range(0, $meta['total_chunks'] - 1), $meta['chunks_received'])),
            ],
        ]);
    }
}
