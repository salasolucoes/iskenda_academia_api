<?php

namespace Infrastructure\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class LiveSessionTokenService
{
    private int $ttlMinutes;

    private int $ttlSeconds;

    public function __construct()
    {
        // config() devolve string quando o valor vem de env() — o Carbon 4 exige
        // int|float em addMinutes(), por isso normaliza-se aqui uma única vez.
        $this->ttlMinutes = (int) config('live.token_ttl_minutes', 30);
        $this->ttlSeconds = $this->ttlMinutes * 60;
    }

    public function issue(string $sessionId, string $studentId): string
    {
        $token = hash('sha256', $sessionId.$studentId.bin2hex(random_bytes(16)));
        $key = $this->key($sessionId, $studentId);

        try {
            Redis::setex($key, $this->ttlSeconds, $token);
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for live token, falling back to DB', ['session_id' => $sessionId]);

            LiveSession::where('id', $sessionId)->update([
                'masked_token' => $token,
                'token_expires_at' => now()->addMinutes($this->ttlMinutes),
            ]);
        }

        return $token;
    }

    public function consume(string $sessionId, string $studentId, string $token): bool
    {
        $key = $this->key($sessionId, $studentId);

        try {
            $stored = Redis::get($key);

            if ($stored === null || $stored !== $token) {
                return $this->fallbackConsume($sessionId, $studentId, $token);
            }

            Redis::del($key);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for live token consume, falling back to DB', ['session_id' => $sessionId]);

            return $this->fallbackConsume($sessionId, $studentId, $token);
        }
    }

    private function fallbackConsume(string $sessionId, string $studentId, string $token): bool
    {
        $session = LiveSession::where('id', $sessionId)
            ->where('masked_token', $token)
            ->where('token_expires_at', '>', now())
            ->first();

        if ($session === null) {
            return false;
        }

        $session->update(['masked_token' => null, 'token_expires_at' => null]);

        return true;
    }

    private function key(string $sessionId, string $studentId): string
    {
        return "live_token:{$sessionId}:{$studentId}";
    }
}
