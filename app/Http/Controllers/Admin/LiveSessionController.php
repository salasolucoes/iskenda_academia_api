<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class LiveSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'live-sessions:'.md5(json_encode($request->only(['status', 'course_id', 'per_page', 'page'])));

        $response = Cache::tags(['live-sessions'])->remember($cacheKey, 300, function () use ($request) {
            $query = LiveSession::with('lesson.module.course:id,title');

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            if ($courseId = $request->query('course_id')) {
                $query->whereHas('lesson.module.course', fn ($q) => $q->where('id', $courseId));
            }

            $sessions = $query->orderBy('scheduled_start', 'desc')
                ->paginate($request->integer('per_page', 20));

            return [
                'data' => LiveSessionResource::collection($sessions)->resolve(),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'total' => $sessions->total(),
                ],
            ];
        });

        return response()->json($response);
    }

    public function show(string $id): JsonResponse
    {
        $data = Cache::tags(['live-sessions'])->remember("live-session.{$id}", 300, function () use ($id) {
            return (new LiveSessionResource(
                LiveSession::with('lesson.module.course:id,title')->findOrFail($id)
            ))->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function forceStart(string $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $session->update([
            'actual_start' => now(),
            'status' => 'live',
        ]);

        $session->load('lesson.module.course:id,title');

        Cache::tags(['live-sessions'])->flush();

        return response()->json([
            'data' => new LiveSessionResource($session),
            'message' => 'Transmissão iniciada com sucesso.',
        ]);
    }

    public function forceEnd(string $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $session->update([
            'actual_end' => now(),
            'status' => 'ended',
        ]);

        $session->load('lesson.module.course:id,title');

        Cache::tags(['live-sessions'])->flush();

        return response()->json([
            'data' => new LiveSessionResource($session),
            'message' => 'Transmissão terminada com sucesso.',
        ]);
    }

    public function updateLink(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'raw_link' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $session = LiveSession::findOrFail($id);
        $session->update([
            'raw_link' => $data['raw_link'],
        ]);

        $session->load('lesson.module.course:id,title');

        Cache::tags(['live-sessions'])->flush();

        return response()->json([
            'data' => new LiveSessionResource($session),
            'message' => 'Link de transmissão atualizado com sucesso.',
        ]);
    }
}
