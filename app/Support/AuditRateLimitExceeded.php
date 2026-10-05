<?php

namespace App\Support;

use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Regista no audit log os pedidos que bateram num limiter.
 *
 * Sem isto um ataque distribuído é invisível: o access log mostra 429, mas
 * não distingue um scraper a martelar `forgot-password` de um aluno que
 * engatou o botão de "reenviar" duas vezes. A consulta que interessa é
 * `rate_limit.exceeded` agrupada por `limiter` e por hora.
 */
class AuditRateLimitExceeded
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {}

    public function handle(ThrottleRequestsException $exception, Request $request): void
    {
        try {
            $this->auditLogger->log(
                'rate_limit.exceeded',
                'rate_limit',
                new ActorContext(
                    actorId: $request->user()?->id,
                    actorRole: $request->user()?->role,
                    actorIp: $request->ip(),
                ),
                newState: [
                    'route' => $request->method().' '.$request->path(),
                    'limiter' => $this->limiterName($request),
                    'max_attempts' => (int) ($exception->getHeaders()['X-RateLimit-Limit'] ?? 0),
                ],
            );
        } catch (Throwable $failure) {
            // A auditoria nunca pode transformar um 429 num 500.
            Log::error('Falha ao registar rate_limit.exceeded', [
                'route' => $request->method().' '.$request->path(),
                'erro' => $failure->getMessage(),
            ]);
        }
    }

    /**
     * A excepção não diz qual foi o limiter que disparou — vem do middleware da
     * rota. O limiter global (`throttleApi`) não é middleware de rota, e nesse
     * caso reporta-se `global`.
     */
    private function limiterName(Request $request): string
    {
        $names = collect($request->route()?->gatherMiddleware() ?? [])
            ->filter(fn (string $middleware) => str_starts_with($middleware, 'throttle:'))
            ->map(fn (string $middleware) => Str::after($middleware, 'throttle:'))
            ->values();

        return $names->isEmpty() ? 'global' : $names->implode(',');
    }
}
