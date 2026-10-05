<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewUrgentTicket;
use Application\UseCases\Support\OpenTicketUseCase;
use Application\UseCases\Support\ReplyTicketUseCase;
use Domain\Support\Contracts\TicketRepositoryInterface;
use Domain\Support\Entities\Ticket;
use Domain\Support\Entities\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
        private OpenTicketUseCase $openTicketUseCase,
        private ReplyTicketUseCase $replyTicketUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;
        $tickets = $this->ticketRepository->findByStudentId($studentId);

        return response()->json([
            'data' => array_map(fn (Ticket $t) => [
                'id' => $t->getId(),
                'subject' => $t->getSubject(),
                'priority' => $t->getPriority()->value,
                'status' => $t->getStatus()->value,
                'created_at' => $t->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $tickets),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
        ]);

        $studentId = $request->user()->id;

        try {
            $ticket = $this->openTicketUseCase->execute(
                studentId: $studentId,
                subject: $request->input('subject'),
                description: $request->input('description'),
                priority: $request->input('priority'),
            );

            if ($request->input('priority') === 'high') {
                $admins = User::where('role', 'admin')->get();
                Notification::send($admins, new NewUrgentTicket(
                    studentName: $request->user()->name,
                    ticketId: $ticket->getId(),
                    subject: $ticket->getSubject(),
                ));
            }

            return response()->json([
                'data' => [
                    'id' => $ticket->getId(),
                    'subject' => $ticket->getSubject(),
                    'status' => $ticket->getStatus()->value,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'subject' => [$e->getMessage()],
            ]);
        }
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $studentId = $request->user()->id;
        $ticket = $this->ticketRepository->findById($id);

        if ($ticket === null || $ticket->getStudentId() !== $studentId) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $messages = $this->ticketRepository->findMessagesByTicketId($id);

        return response()->json([
            'data' => [
                'ticket' => [
                    'id' => $ticket->getId(),
                    'subject' => $ticket->getSubject(),
                    'description' => $ticket->getDescription(),
                    'priority' => $ticket->getPriority()->value,
                    'status' => $ticket->getStatus()->value,
                    'created_at' => $ticket->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'messages' => array_map(fn (TicketMessage $m) => [
                    'id' => $m->getId(),
                    'author_id' => $m->getAuthorId(),
                    'body' => $m->getBody(),
                    'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
                ], $messages),
            ],
        ]);
    }

    public function reply(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
        ]);

        $studentId = $request->user()->id;

        $ticket = $this->ticketRepository->findById($id);

        if ($ticket === null || $ticket->getStudentId() !== $studentId) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        try {
            $message = $this->replyTicketUseCase->execute(
                ticketId: $id,
                authorId: $studentId,
                body: $request->input('body'),
            );

            return response()->json([
                'data' => [
                    'id' => $message->getId(),
                    'body' => $message->getBody(),
                    'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
            ], 201);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'body' => [$e->getMessage()],
            ]);
        }
    }
}
