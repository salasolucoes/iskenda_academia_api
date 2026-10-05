<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(string $id, Request $request): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification === null) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notificação marcada como lida.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Todas as notificações foram marcadas como lidas.']);
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        $deleted = $request->user()
            ->notifications()
            ->where('id', $id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        return response()->json(['message' => 'Notificação eliminada.']);
    }
}
