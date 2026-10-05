<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $existing = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            $currentHash = $this->hashRequest($request);

            if ($existing->request_hash !== $currentHash) {
                return response()->json([
                    'message' => 'Idempotency key reused with different request.',
                ], 422);
            }

            return response()->json(
                json_decode($existing->response, true),
                $existing->status_code
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'user_id' => $request->user()->id,
                'request_hash' => $this->hashRequest($request),
                'response' => $response->getContent(),
                'status_code' => $response->getStatusCode(),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        return $response;
    }

    private function hashRequest(Request $request): string
    {
        return hash('sha256', json_encode([
            $request->method(),
            $request->path(),
            $request->except(['*']),
        ]));
    }
}
