<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->user()->email_verified_at === null) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'requires_otp' => true,
            ], 403);
        }

        return $next($request);
    }
}
