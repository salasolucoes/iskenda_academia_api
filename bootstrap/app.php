<?php

use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureIdempotent;
use App\Http\Middleware\RoleMiddleware;
use App\Support\AuditRateLimitExceeded;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi('api');

        // Os limiters por rede dependem de `$request->ip()` devolver o IP real
        // do cliente. Com o Cloudflare à frente, sem isto o Laravel veria o IP
        // do proxy e todos os utilizadores passariam a partilhar um único
        // balde — o limite por IP degrade para um limite global.
        //
        // Lista explícita, e não `at: '*'`: se o VPS ficar exposto
        // directamente, um `at: '*'` deixaria qualquer cliente forjar
        // `X-Forwarded-For` e contornar todos os limites por rede. É a mesma
        // lista que o `set_real_ip_from` do `docker/nginx/nginx.conf` já usa.
        $middleware->trustProxies(at: [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
        ]);

        $middleware->alias([
            'email.verified' => EnsureEmailVerified::class,
            'role' => RoleMiddleware::class,
            'idempotent' => EnsureIdempotent::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'broadcasting/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });

        // O 429 do Laravel traz "Too Many Attempts." e, em debug, o trace
        // completo (file, line). Um cliente que faça parse do campo `code`
        // para tratar erros não reconhece a resposta, e o payload deixa de
        // respeitar o contrato único de erro do projecto.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            app(AuditRateLimitExceeded::class)->handle($e, $request);

            return response()->json([
                'message' => 'Demasiadas tentativas. Tente novamente mais tarde.',
                'errors' => [],
                'code' => 'RATE_LIMIT_EXCEEDED',
            ], 429, $e->getHeaders());
        });
    })->create();
