<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function sendBroadcast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['string', 'in:info,warning,alert'],
            'link' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
        ]);

        $type = $data['type'] ?? 'info';
        $notificationData = [
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $type,
            'link' => $data['link'] ?? null,
        ];

        if ($userId = $data['user_id'] ?? null) {
            $user = User::findOrFail($userId);
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'admin_broadcast',
                'data' => $notificationData,
            ]);
            $targetLabel = $user->name;
        } else {
            User::where('is_active', true)->chunk(100, function ($users) use ($notificationData) {
                foreach ($users as $user) {
                    $user->notifications()->create([
                        'id' => (string) Str::uuid(),
                        'type' => 'admin_broadcast',
                        'data' => $notificationData,
                    ]);
                }
            });
            $targetLabel = 'todos os utilizadores activos';
        }

        return response()->json([
            'message' => "Notificação enviada para {$targetLabel}.",
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = DatabaseNotification::where('type', 'admin_broadcast')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'created_at' => $n->created_at->format('c'),
                'read_count' => $n->read() ? 'N/A' : null,
            ]),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }
}
