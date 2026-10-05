<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use Application\UseCases\Support\ReplyTicketUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Persistence\Eloquent\Models\Ticket;

class TicketController extends Controller
{
    public function __construct(
        private ReplyTicketUseCase $replyTicketUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'tickets:'.md5(json_encode($request->only(['status', 'priority', 'assigned_to', 'per_page', 'page'])));

        $response = Cache::tags(['tickets'])->remember($cacheKey, 300, function () use ($request) {
            $query = Ticket::with('student:id,name,email', 'assignedTo:id,name');

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            if ($priority = $request->query('priority')) {
                $query->where('priority', $priority);
            }

            if ($assignedTo = $request->query('assigned_to')) {
                $query->where('assigned_to', $assignedTo);
            }

            $tickets = $query->orderBy('created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return [
                'data' => TicketResource::collection($tickets)->resolve(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'total' => $tickets->total(),
                ],
            ];
        });

        return response()->json($response);
    }

    public function show(string $id): JsonResponse
    {
        $data = Cache::tags(['tickets'])->remember("ticket.{$id}", 300, function () use ($id) {
            return (new TicketResource(
                Ticket::with([
                    'student:id,name,email',
                    'assignedTo:id,name',
                    'messages.author:id,name',
                ])->findOrFail($id)
            ))->resolve();
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);

        $data = $request->validate([
            'assigned_to' => ['required', 'string', 'exists:users,id'],
        ]);

        $ticket->update(['assigned_to' => $data['assigned_to']]);
        $ticket->load('student:id,name,email', 'assignedTo:id,name');

        Cache::tags(['tickets'])->flush();

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Ticket atribuído com sucesso.',
        ]);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['boolean'],
        ]);

        $adminId = $request->user()->id;

        try {
            $this->replyTicketUseCase->execute(
                ticketId: $id,
                authorId: $adminId,
                body: $data['body'],
                isInternal: $data['is_internal'] ?? false,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $ticket = Ticket::with([
            'student:id,name,email',
            'assignedTo:id,name',
            'messages.author:id,name',
        ])->findOrFail($id);

        $ticket->update(['status' => 'in_progress']);

        Cache::tags(['tickets', 'dashboard'])->flush();

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Resposta registada com sucesso.',
        ]);
    }

    public function close(string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['status' => 'closed']);
        $ticket->load('student:id,name,email', 'assignedTo:id,name');

        Cache::tags(['tickets', 'dashboard'])->flush();

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Ticket fechado com sucesso.',
        ]);
    }
}
