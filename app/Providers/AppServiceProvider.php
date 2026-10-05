<?php

namespace App\Providers;

use Application\UseCases\Auth\OtpSender;
use Domain\Auth\Contracts\AuthRepositoryInterface;
use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\Contracts\TicketRepositoryInterface;
use Domain\Wallet\Contracts\TransactionRepositoryInterface;
use Domain\Wallet\Contracts\WalletRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Infrastructure\Persistence\Repositories\EloquentAuthRepository;
use Infrastructure\Persistence\Repositories\EloquentCourseRepository;
use Infrastructure\Persistence\Repositories\EloquentEnrollmentRepository;
use Infrastructure\Persistence\Repositories\EloquentTicketRepository;
use Infrastructure\Persistence\Repositories\EloquentTransactionRepository;
use Infrastructure\Persistence\Repositories\EloquentWalletRepository;
use Infrastructure\Services\EloquentAuditLogger;
use Infrastructure\Services\OtpService;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Atributo do request onde a identidade usada nos limiters fica memoizada.
     */
    private const ACTOR_ATTRIBUTE = 'rate_limit_actor_key';

    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, EloquentAuthRepository::class);
        $this->app->bind(CourseRepositoryInterface::class, EloquentCourseRepository::class);
        $this->app->bind(WalletRepositoryInterface::class, EloquentWalletRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, EloquentTransactionRepository::class);
        $this->app->bind(EnrollmentRepositoryInterface::class, EloquentEnrollmentRepository::class);
        $this->app->bind(TicketRepositoryInterface::class, EloquentTicketRepository::class);
        $this->app->bind(AuditLoggerInterface::class, EloquentAuditLogger::class);
        $this->app->bind(OtpSender::class, OtpService::class);
    }

    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->letters();
        });

        $this->registerRateLimiters();
    }

    /**
     * Os limites de autenticação combinam sempre duas chaves: identidade e IP.
     * Só o IP pune utilizadores legítimos que partilham um CGNAT (caso comum
     * em Angola); só o email não impede a mesma conta ser atacada de onde seja.
     * As duas juntas exigem que o atacante controle ambos ao mesmo tempo.
     */
    private function registerRateLimiters(): void
    {
        // Teto global. Por utilizador autenticado; por IP quando não há
        // sessão, porque nesse caso não existe outra identidade disponível.
        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute(60)->by($this->actorKey($request)),
        ]);

        // Operações caras. O limite segue a capacidade, não o utilizador:
        // são as que consomem CPU (DOMPDF) ou disco/rede (upload).
        RateLimiter::for('pdf', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by($this->actorKey($request)),
        ]);

        RateLimiter::for('upload', fn (Request $request) => [
            Limit::perMinute(60)->by($this->actorKey($request)),
        ]);

        // O frontend envia progresso a cada poucos segundos; 30/5min dá
        // margem para um seek activo sem transformar isto em abuse vector.
        RateLimiter::for('progress', fn (Request $request) => [
            Limit::perMinutes(5, 30)->by($this->actorKey($request)),
        ]);

        RateLimiter::for('broadcast', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by($this->actorKey($request)),
        ]);

        RateLimiter::for('auth', function (Request $request) {
            $email = $this->normalisedEmail($request);

            return [
                Limit::perMinutes(15, 5)->by('email:'.$email),
                Limit::perMinutes(15, 20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp', function (Request $request) {
            $email = $this->normalisedEmail($request);

            return [
                // Envios: por identidade, porque não há aqui código a adivinhar.
                Limit::perMinutes(15, 5)->by('email:'.$email),
                // Rede: travão de custo para que 1M não gere milhares de emails.
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        // Verificação tem balde próprio, separado do envio. Com um balde
        // único, as tentativas de verificação esgotavam também a cota de
        // reenvio: quem invalidasse o código ficava 15 minutos sem poder pedir
        // outro, e a protecção do dominio boicotava-se a si própria.
        //
        // 10 tentativas por janela, e não 5: o limite por código (5) pertence
        // ao dominio, que tem consequência real — invalida o código e obriga a
        // reenvio. Se o HTTP cortasse também aos 5, disparava primeiro e o
        // limite do dominio nunca chegaria a correr, ficando a ser código morto.
        // Aqui o HTTP só dá a rede de segurança: volume de pedidos e spray de
        // um IP contra muitas identidades.
        RateLimiter::for('otp-verify', function (Request $request) {
            $email = $this->normalisedEmail($request);

            return [
                Limit::perMinutes(10, 10)->by('try:'.$email.':'.$request->ip()),
                Limit::perMinutes(10, 20)->by('ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Normaliza a identidade para a chave do limiter. Sem isto,
     * `Aluno@exemplo.com`, `aluno@exemplo.com ` e `ALUNO@EXEMPLO.COM` caem em
     * baldes diferentes e o limite por email é contornado numa variação de
     * capitalização — ou seja, por erro de digitação acidental.
     */
    private function normalisedEmail(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email')));
    }

    /**
     * Identidade para a chave dos limiters: `user:<id>` ou, sem sessão,
     * `ip:<endereço>`.
     *
     * `$request->user()` NÃO é fiável aqui. `ThrottleRequests` figura em
     * `$middlewarePriority` do kernel a seguir a `AuthenticatesRequests`, pelo
     * que o Laravel reordena os middleware e o throttle corre ANTES de
     * `auth:sanctum`. Nos limiters nomeados não existe o fallback automático que
     * o `throttle:N,M` tem para o utilizador — a chave é exactamente a que
     * devolvemos.
     *
     * Ignorar isto fazia todos os limiters caírem no IP: com um CGNAT angolano,
     * os 30 pedidos de progresso por 5 minutos passavam a ser 30 para *todos* os
     * alunos do operador juntos — a plataforma saía do ar num único pedido de
     * rede partilhada.
     *
     * A identidade vem do token Bearer e nunca do guard `sanctum`: o guard é
     * memoizado pelo AuthManager, por isso usá-lo contaminaria o resto do
     * processo — num worker de longa vida (Octane) o utilizador do pedido
     * anterior continuava autenticado no seguinte. `$request->user()` sofre do
     * mesmo problema, já que o seu resolver também passa pelo AuthManager.
     */
    private function actorKey(Request $request): string
    {
        if (! $request->attributes->has(self::ACTOR_ATTRIBUTE)) {
            $request->attributes->set(self::ACTOR_ATTRIBUTE, $this->resolveActorKey($request));
        }

        return $request->attributes->get(self::ACTOR_ATTRIBUTE);
    }

    /**
     * Custa uma leitura de `personal_access_tokens` que o `auth:sanctum` vai
     * repetir — é o preço de ter o limite por utilizador em vez de por IP. Fica
     * memoizado no request porque dois limiters (o global e o da rota) o pedem
     * no mesmo pedido.
     */
    private function resolveActorKey(Request $request): string
    {
        $token = $request->bearerToken();
        $userId = $token === null
            ? null
            : PersonalAccessToken::findToken($token)?->tokenable?->getAuthIdentifier();

        return $userId !== null
            ? 'user:'.$userId
            : 'ip:'.$request->ip();
    }
}
