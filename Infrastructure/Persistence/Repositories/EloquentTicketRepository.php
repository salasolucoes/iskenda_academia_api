<?php

namespace Infrastructure\Persistence\Repositories;

use Domain\Support\Contracts\TicketRepositoryInterface;
use Domain\Support\Entities\Ticket as TicketEntity;
use Domain\Support\Entities\TicketMessage as TicketMessageEntity;
use Domain\Support\ValueObjects\Priority;
use Domain\Support\ValueObjects\TicketStatus;
use Infrastructure\Persistence\Eloquent\Models\Ticket as TicketModel;
use Infrastructure\Persistence\Eloquent\Models\TicketMessage as TicketMessageModel;

class EloquentTicketRepository implements TicketRepositoryInterface
{
    public function findById(string $id): ?TicketEntity
    {
        $model = TicketModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStudentId(string $studentId): array
    {
        return TicketModel::where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (TicketModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(TicketEntity $ticket): TicketEntity
    {
        $model = TicketModel::updateOrCreate(
            ['id' => $ticket->getId()],
            [
                'student_id' => $ticket->getStudentId(),
                'assigned_to' => $ticket->getAssignedTo(),
                'subject' => $ticket->getSubject(),
                'description' => $ticket->getDescription(),
                'priority' => $ticket->getPriority()->value,
                'status' => $ticket->getStatus()->value,
            ]
        );

        return $this->toEntity($model->fresh());
    }

    public function delete(string $id): void
    {
        TicketModel::findOrFail($id)->delete();
    }

    public function findMessagesByTicketId(string $ticketId): array
    {
        return TicketMessageModel::where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (TicketMessageModel $model) => $this->toMessageEntity($model))
            ->all();
    }

    public function saveMessage(TicketMessageEntity $message): TicketMessageEntity
    {
        $model = TicketMessageModel::create([
            'id' => $message->getId(),
            'ticket_id' => $message->getTicketId(),
            'author_id' => $message->getAuthorId(),
            'body' => $message->getBody(),
            'is_internal' => $message->isInternal(),
        ]);

        return $this->toMessageEntity($model->fresh());
    }

    private function toEntity(TicketModel $model): TicketEntity
    {
        return new TicketEntity(
            id: $model->id,
            studentId: $model->student_id,
            assignedTo: $model->assigned_to,
            subject: $model->subject,
            description: $model->description,
            priority: Priority::from($model->priority),
            status: TicketStatus::from($model->status),
            createdAt: $model->created_at->toImmutable(),
            updatedAt: $model->updated_at?->toImmutable(),
        );
    }

    private function toMessageEntity(TicketMessageModel $model): TicketMessageEntity
    {
        return new TicketMessageEntity(
            id: $model->id,
            ticketId: $model->ticket_id,
            authorId: $model->author_id,
            body: $model->body,
            isInternal: $model->is_internal,
            createdAt: $model->created_at->toImmutable(),
        );
    }
}
