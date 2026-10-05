<?php

namespace Infrastructure\Services;

use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Support\Facades\DB;

class EloquentAuditLogger implements AuditLoggerInterface
{
    public function log(
        string $eventType,
        mixed $auditable,
        ActorContext $actor,
        ?array $previousState = null,
        ?array $newState = null,
    ): void {
        $auditableType = is_object($auditable) ? class_basename($auditable) : (string) $auditable;

        $auditableId = null;
        if (is_object($auditable)) {
            if (method_exists($auditable, 'getKey')) {
                $auditableId = $auditable->getKey();
            } elseif (method_exists($auditable, 'getId')) {
                $auditableId = $auditable->getId();
            } elseif (property_exists($auditable, 'id')) {
                $auditableId = $auditable->id;
            }
        }

        DB::table('audit_logs')->insert([
            'event_type' => $eventType,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'actor_id' => $actor->actorId,
            'actor_role' => $actor->actorRole,
            'actor_ip' => $actor->actorIp,
            'payload' => json_encode([
                'event' => $eventType,
                'auditable' => $auditableType,
                'actor_role' => $actor->actorRole,
            ]),
            'previous_state' => $previousState ? json_encode($previousState) : null,
            'new_state' => $newState ? json_encode($newState) : null,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
