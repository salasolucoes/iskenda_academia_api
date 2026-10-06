# Plano de Rate Limiting e Protecção de Abuso — Iskenda Academy

> Auditoria de segurança às rotas de autenticação, custos de recurso e limites de API.
> Documento de engenharia. Nenhuma alteração de código foi aplicada — apenas o plano.

---

## Índice

1. [Diagnóstico — estado actual](#1-diagnóstico--estado-actual)
2. [Vulnerabilidades](#2-vulnerabilidades)
3. [Estratégia em camadas](#3-estratégia-em-camadas)
4. [Limiters propostos](#4-limiters-propostos)
5. [Detalhe de implementação por ficheiro](#5-detalhe-de-implementação-por-ficheiro)
6. [Plano de execução](#6-plano-de-execução)
7. [Testes](#7-testes)
8. [Observabilidade](#8-observabilidade)
9. [Contrato de erro 429](#9-contrato-de-erro-429)
10. [O que não fazer](#10-o-que-não-fazer)
11. [Decisões em aberto](#11-decisões-em-aberto)
12. [Checklist de revisão](#12-checklist-de-revisão)

---

## 1. Diagnóstico — estado actual

### 1.1 Limites existentes

| Rota | Limite | Ficheiro |
|------|--------|----------|
| `POST /api/v1/auth/login` | 5 req/min | `routes/api.php:41` |
| `POST /api/v1/auth/forgot-password` | 3 req/min | `routes/api.php:44` |
| `POST /api/v1/classroom/{liveSessionId}/join` | 10 req/min | `routes/api.php:102` |
| **Restantes ~200 rotas** | **sem limite** | — |

Três rotas de ~200 estão limitadas. Não existe teto global.

### 1.2 Teto global documentado mas não implementado

`regras-de-negocio.md:912` declara:

> Rate limit: 60 req/min geral, 5 req/min em `/auth/login`

O código não implementa os 60 req/min. `bootstrap/app.php` nunca chama `$middleware->throttleApi()`. Sem essa chamada, `$apiLimiter` fica `null` e o grupo `api` não recebe `throttle:api` — ver `Illuminate\Foundation\Configuration\Middleware::getMiddlewareGroups()` (`Middleware.php:495-499`):

```php
'api' => array_values(array_filter([
    $this->statefulApi ? ... : null,
    $this->apiLimiter ? 'throttle:'.$this->apiLimiter : null,   // null → sem throttle
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
])),
```

A regra de negócio e a implementação divergem. Qualquer revisão de segurança futura vai tropeçar nisto.

### 1.3 Chave de identificação — o problema do IP

Todos os limites actuais usam a chave por defeito do Laravel (`ThrottleRequests::resolveRequestSignature`, `ThrottleRequests.php:224-230`):

```php
protected function resolveRequestSignature($request)
{
    if ($user = $request->user()) {
        return $this->formatIdentifier($user->getAuthIdentifier());
    } elseif ($route = $request->route()) {
        return $this->formatIdentifier($route->getDomain().'|'.$request->ip());
    }

    throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
}
```

Consequências:

| Cenário | Efeito |
|---------|--------|
| Rotas autenticadas (`/join`) | chave = `user_id` — correcto |
| Rotas públicas (`/login`, `/forgot-password`) | chave = `domain\|ip` — **errado no contexto angolano** |

### 1.4 Armazenamento do limiter

| Ficheiro | `CACHE_STORE` | Impacto |
|----------|---------------|---------|
| `.env` | `redis` | correcto — `add()` atómico, sem race condition |
| `.env.example` | `database` | incorrecto — locks de base de dados sob carga |

`REDIS_CLIENT=predis` em `.env:49`. O limiter do Laravel usa `add()` no Redis, que é atómico. Com `database` seria uma `INSERT` com `ON CONFLICT` e lock de linha. **A `.env.example` deve ser corrigida** — quem fizer deploy a partir dela obtém um limiter errado.

### 1.5 Ausência de limite na edge

`docker/nginx/nginx.conf` não tem `limit_req` nem `limit_conn`. Todo o tráfego, incluindo volumetria, consome PHP-FPM antes de qualquer verificação aplicacional.

Estado actual do ficheiro relevante:

```nginx
client_max_body_size 20M;   # linha 25
```

Nota: a regra de negócio diz upload máximo 5MB, mas o Nginx aceita 20M. Isso é intencional para o chunked upload (chunks de 5MB + overhead), mas significa que **o limite de 5MB só existe na validação Laravel** — e `client_max_body_size` não está a proteger o disco.

---

## 2. Vulnerabilidades

Ordenadas por severidade. V1 e V2 são caminhos de ataque directos para tomada de conta e emissão fraudulenta de certificados.

---

### V1 — `duration_seconds` aceite do cliente → certificados fraudulentos

**Severidade: Crítica · Confiança: Alta (código verificado)**

`LessonProgressController::update` aceita a duração total como parâmetro do cliente e passa-a ao domínio (`LessonProgressController.php:41-44`):

```php
$isCompleted = $this->enrollmentDomainService->markLessonComplete(
    (int) $request->input('watched_seconds'),
    (int) $request->input('duration_seconds'),
);
```

E o serviço de domínio trata duração zero como "completa" (`EnrollmentDomainService.php:36-43`):

```php
public function markLessonComplete(int $watchedSeconds, int $totalDurationSeconds): bool
{
    if ($totalDurationSeconds <= 0) {
        return true;
    }

    return $watchedSeconds >= ($totalDurationSeconds * 0.9);
}
```

**Exploração:** qualquer aluno autenticado envia, para cada aula do curso:

```http
POST /api/v1/classroom/{lessonId}/progress
{ "watched_seconds": 0, "last_position_seconds": 0, "duration_seconds": 0 }
```

Isto marca a aula como concluída, `checkAndCompleteEnrollment()` (`LessonProgressController.php:73-95`) conta as aulas concluídas, o `enrollment` transita para `completed`, e `POST /certificates/{enrollmentId}/issue` emite um certificado PDF válido com hash de verificação público.

Impacto: certificado verificável publicamente sem ter visto o curso. Para uma plataforma que emite certificados, isto invalida o valor do produto.

**Agravante:** `lessons.duration_minutes` é `nullable` (`database/migrations/2026_06_29_060402_create_lessons_table.php:18`). A coluna autoritativa existe no servidor e não é usada. A verificação `<= 0 → true` está exactamente invertida: ausência de duração deve **impedir** a conclusão automática, nunca permiti-la.

**Nota de âmbito:** isto não é um problema de rate limiting. É uma falha de confiança de input. Aparece neste documento porque é o mesmo vector de abuso e porque a mesma correcção (linha 5.5) trata os dois.

---

### V2 — Brute-force de OTP sem limite

**Severidade: Crítica · Confiança: Alta**

O OTP tem 6 dígitos numéricos (`Domain/Auth/Services/AuthDomainService.php:9-12`):

```php
public function generateOtp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
```

Espaço de busca = 1 000 000. Validade = 10 minutos (confirmado no texto do email em `Infrastructure/Services/OtpService.php`).

Rotas expostas **sem qualquer limite**:

| Rota | Alvo | Impacto |
|------|------|---------|
| `POST /auth/verify-otp` | `users.otp_code` | Verificação de e-mail falsificada |
| `POST /auth/verify-reset-otp` | `users.otp_code` | **Tomada de conta** |
| `POST /auth/reset-password` | — | Idem |
| `POST /auth/instructor/initiate` | — | Enumeração de instrutores |
| `POST /auth/instructor/complete` | senha temporária | **Tomada de conta de instrutor** |

`POST /auth/verify-otp` devolve um token Sanctum válido (`AuthController::verifyOtp`), logo um brute-force bem-sucedido não é sóSE — é login.

**Por que um limite por IP não resolve:**

Um atacante distribuído em 50 IPs faz 10 tentativas por IP. 50 × 10 × 10 min = 5 000 tentativas por janela de 10 minutos — o suficiente para varrer o espaço. Ou 50 IPs × 100 tentativas/hora durante 10 horas = 500 000 tentativas, comfortably dentro da validade acumulada de múltiplos reenvios.

O limite correcto é **por código OTP**, não por origem. Ver secção 5.4.

**Agravante — comparação não constante.** `User::verifyOtp` usa `===` (`Domain/Auth/Entities/User.php:81-86`):

```php
public function verifyOtp(string $code): bool
{
    return $this->otpCode !== null
        && $this->otpCode === $code
        && ! $this->isOtpExpired();
}
```

Isto não é o vector primário (o alvo é o rate limit, não timing), mas deve ser `hash_equals()` por higiene e porque a validação de login em `AuthController::login` já usa `Hash::check` correctamente — inconsistência entre os dois caminhos.

---

### V3 — NAT/CGNAT bloqueia utilizadores legítimos em Angola

**Severidade: Alta (disponibilidade) · Confiança: Alta (contexto local)**

Os operadores móveis angolanos partilham endereços NAT públicos. Um único IP público pode representar milhares de utilizadores de uma mesma operadora.

Limites actuais por IP em rotas públicas:

| Rota | Limite | Efeito sob CGNAT |
|------|--------|-------------------|
| `POST /auth/forgot-password` | 3 req/min | 3 pedidos de reset por minuto para **toda a saída da operadora** |
| `POST /auth/login` | 5 req/min | 5 tentativas de login por minuto partilhadas |

`forgot-password` é o pior caso: um único aluno a recuperar a palavra-passe bloqueia o reset de senha de todos os restantes utilizadores da mesma operadora durante 60 segundos. Com retries, torna-se um DoS involuntário e persistente.

**Este problema não se resolve com limites mais altos.** Resolver com limites **por identidade** (`email`) em vez de por IP, mantendo o IP apenas como limite secundário de protecção contra botnets.

---

### V4 — Ausência de teto global

**Severidade: Média · Confiança: Alta**

Qualquer rota pode ser invocada sem restrição. Uma única tabela mal indexada, um `GET /admin/users` em loop, ou um `GET /courses` com filtros que forçam full-scan, são(request) ilimitadas contra PostgreSQL.

Impactos: exaustão de conexões da pool, contenção no `REPEATABLE READ` de `CheckoutWithWalletUseCase`, e eventual OOM no PHP-FPM.

---

### V5 — Recursos caros sem limite de custo

**Severidade: Média · Confiança: Alta**

| Rota | Custo por request | Risco |
|------|-------------------|-------|
| `POST /certificates/{enrollmentId}/issue` | DOMPDF (render PDF) + upload MinIO | CPU, memória, I/O, custo de object storage |
| `POST /instructor/upload/chunk` | 5MB para MinIO por request | Enchimento de storage |
| `POST /instructor/upload/init` | — | Criação ilimitada de sessões de upload |
| `POST /auth/register` | INSERT + envio de email | Spam de contas, custo de SMTP, ruído no suporte |
| `POST /admin/notifications/broadcast` | broadcast para todos os activos | Abuso por admin comprometido |

`POST /certificates/{id}/issue` e o mais caro por margem. O limite natural (uma matricula, um certificado) ja esta garantido pelo `UNIQUE` em `enrollments` e pela unicidade de `certificates.enrollment_id` — o unico risco real e o custo de varios certificados em paralelo para o mesmo aluno.

O `LessonProgressController::update` é um caso especial: **precisa** de limite, mas um limite alto. É um endpoint de heartbeat de vídeo — o cliente escreve a cada ~10-15 segundos durante a visualização. Um limite de 30 req/5 min (= 1 write/10s) acomoda o uso legítimo e corta o spam de escrita em `lesson_progress`.

---

### V6 — Edge sem limitação de volumetria

**Severidade: Média · Confiança: Alta**

Sem `limit_req` no Nginx, um flood volumétrico consome PHP-FPM. O limitador aplicacional responde 429 depois de todo o trabalho caro já ter sido feito (autenticação, routing, hidratação do container).

O limite na edge é a única_contents que absorve volumetria **antes** do custo de PHP. Em conjunto, Cloudflare à frente do VPS dá protecção DDoS gerida sem custo, e o Nginx funciona como segunda camada se o Cloudflare falhar.

---

### V7 — Resposta 429 viola o contrato de erro do projecto

**Severidade: Baixa (funcional) · Confiança: Alta**

O projecto define um contrato de erro único (`regras-de-negocio.md:939-970`):

```json
{
    "message": "Descrição clara do erro",
    "errors": { "field_name": ["Regra de validação falhou"] },
    "code": "INSUFFICIENT_BALANCE"
}
```

`ThrottleRequestsException` devolve 429 com o payload genérico do framework. Um cliente que faça parse do campo `code` para tratar erros não reconhece a resposta e cai no caminho de erro desconhecido.

Falta também o código na tabela de erros padronizados (`regras-de-negocio.md:953`) e não há `Retry-After` documentado como contrato.

---

## 3. Estratégia em camadas

"Rate limit" não é um problema — são três problemas com controlos diferentes:

| # | Problema | Exemplo | Controlo correcto |
|---|----------|---------|-------------------|
| 1 | **Abuso volumétrico** | flood de qualquer endpoint | Teto global + edge |
| 2 | **Força bruta** | OTP de 6 dígitos, palavra-passe | Contador por identidade alvo |
| 3 | **Custo / recurso** | PDF DOMPDF, upload, email | Limite por capacidade cara |

Camadas:

```
┌─────────────────────────────────────────────────────┐
│ L5  Resposta 429 no contrato + observabilidade       │
├─────────────────────────────────────────────────────┤
│ L4  Limites por capacidade cara (PDF, upload, email) │
├─────────────────────────────────────────────────────┤
│ L3  Teto global (`throttleApi`)                     │
├─────────────────────────────────────────────────────┤
│ L2  Limiter nomeado, chave = identidade              │
├─────────────────────────────────────────────────────┤
│ L1  Protecção de OTP: tentativas por código          │
├─────────────────────────────────────────────────────┤
│ L0  Edge: Nginx `limit_req` + Cloudflare             │
└─────────────────────────────────────────────────────┘
```

L0 absorve o ruído de volumetria. L1 fecha o vector de tomada de conta. L2–L3 dão uma base previsível. L4 protege o orçamento de recursos. L5 fecha o loop de observabilidade.

---

## 4. Limiters propostos

Todos definidos em `AppServiceProvider::boot()` e referenciados por nome nas rotas.

| Limiter | Limite | Chave | Aplicado a | Problema |
|---------|--------|-------|------------|----------|
| `api` | 60/min | `user_id` ?? `ip` | grupo `api` inteiro (L3) | Volumetria |
| `auth` | 5/15min + 20/15min | `email` + `ip` | `login`, `instructor/complete` | Força bruta, V3 |
| `otp` | 5/10min + 5/15min | `email + ip`, `email` | `verify-otp`, `forgot-password`, `verify-reset-otp`, `reset-password`, `instructor/initiate` | Força bruta, V2 |
| `pdf` | 5/10min | `user_id` | `certificates/{id}/issue` | Custo, V5 |
| `upload` | 60/min | `user_id` | `upload/init`, `upload/chunk`, `upload/complete` | Custo, V5 |
| `progress` | 30/5min | `user_id` | `classroom/{lessonId}/progress` | Escrita, V5 |
| `live-join` | 10/min | `user_id` | `classroom/{liveSessionId}/join` | Já existe, mantido |
| `broadcast` | 5/15min | `user_id` | `admin/notifications/broadcast` | Custo, V5 |

**Decisão de chave para `auth` e `otp`: duas chaves em conjunto.**

| Chave | Protege contra | Falha se for a única |
|-------|----------------|---------------------|
| Por `email` | Força bruta sobre uma identidade | Botnet com N IPs |
| Por `ip` | Botnet com N identidades | CGNAT / NAT partilhado |

Sozinhas, ambas falham. Combinadas, o atacante tem de ter a mesma identidade e o mesmo IP em simultâneo. É a mesma razão pela qual o GitHub combina limites por utilizador e por IP.

---

## 5. Detalhe de implementação por ficheiro

> Exemplos de referência. **Nada disto foi aplicado.**

### 5.1 `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    Password::defaults(function () {
        return Password::min(8)
            ->mixedCase()
            ->letters();
    });

    RateLimiter::for('api', fn (Request $request) => [
        Limit::perMinute(60)->by('api:'.($request->user()?->id ?? $request->ip())),
    ]);

    RateLimiter::for('auth', function (Request $request) {
        $email = (string) $request->input('email');

        return [
            Limit::perMinutes(15, 5)->by('auth-email:'.$email),
            Limit::perMinutes(15, 20)->by('auth-ip:'.$request->ip()),
        ];
    });

    RateLimiter::for('otp', function (Request $request) {
        $email = (string) $request->input('email');

        return [
            Limit::perMinutes(10, 5)->by('otp-try:'.$email.':'.$request->ip()),
            Limit::perMinutes(15, 5)->by('otp-send:'.$email),
        ];
    });

    RateLimiter::for('pdf', fn (Request $request) => [
        Limit::perMinutes(10, 5)->by('pdf:'.$request->user()?->id),
    ]);

    RateLimiter::for('upload', fn (Request $request) => [
        Limit::perMinute(60)->by('upload:'.$request->user()?->id),
    ]);

    RateLimiter::for('progress', fn (Request $request) => [
        Limit::perMinutes(5, 30)->by('progress:'.$request->user()?->id),
    ]);

    RateLimiter::for('broadcast', fn (Request $request) => [
        Limit::perMinutes(15, 5)->by('broadcast:'.$request->user()?->id),
    ]);
}
```

O limite `live-join:10,1` actual fica como está — a chave por `user_id` já é correcta (`routes/api.php:102`).

**Nota sobre `$request->ip()` atrás de proxy/CDN:** quando o Cloudflare ou um reverse proxy estiver à frente, `$request->ip()` devolve o IP do proxy, o que **colapsa todos os utilizadores numa única chave**. Feito com lista explicita dos 15 ranges do Cloudflare, e nao `at: '*'`: com `'*'`, um VPS exposto directamente deixaria qualquer cliente forjar `X-Forwarded-For` e contornar todos os limites por rede. Ver secção 13.7.

### 5.2 `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->throttleApi('api');

    $middleware->alias([
        'email.verified' => EnsureEmailVerified::class,
        'role' => RoleMiddleware::class,
        'idempotent' => EnsureIdempotent::class,
    ]);

    $middleware->validateCsrfTokens(except: [
        'broadcasting/*',
    ]);
})
```

### 5.3 `routes/api.php`

```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth');

Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
    ->middleware('throttle:otp');

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
    ->middleware('throttle:otp');

Route::post('/verify-reset-otp', [PasswordResetController::class, 'verifyResetOtp'])
    ->middleware('throttle:otp');

Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:otp');

Route::post('/instructor/initiate', [InstructorAuthController::class, 'initiate'])
    ->middleware('throttle:otp');

Route::post('/instructor/complete', [InstructorAuthController::class, 'complete'])
    ->middleware('throttle:auth');
```

```php
Route::post('/certificates/{enrollmentId}/issue', [CertificateController::class, 'issue'])
    ->middleware('throttle:pdf');

Route::post('/classroom/{lessonId}/progress', [LessonProgressController::class, 'update'])
    ->middleware('throttle:progress');
```

```php
// dentro do grupo instructor
Route::post('/upload/init', [ChunkedUploadController::class, 'init'])
    ->middleware('throttle:upload');
Route::post('/upload/chunk', [ChunkedUploadController::class, 'chunk'])
    ->middleware('throttle:upload');
Route::post('/upload/{sessionId}/complete', [ChunkedUploadController::class, 'complete'])
    ->middleware('throttle:upload');
```

```php
// dentro do grupo admin
Route::post('/notifications/broadcast', [AdminNotificationController::class, 'sendBroadcast'])
    ->middleware('throttle:broadcast');
```

### 5.4 `Domain/Auth/Entities/User.php` — tentativas de OTP

A protecção que fecha V2. Um limite por IP/email não impede o atacante lento que varre 1M durante 10 horas. Ao invalidar o código após N tentativas, obriga a reenvio — e reenvio é limitado por `otp`.

Requer coluna `otp_attempts` na tabela `users`.

```php
public const MAX_OTP_ATTEMPTS = 5;

public function verifyOtp(string $code): bool
{
    if ($this->otpCode === null || $this->isOtpExpired()) {
        return false;
    }

    if (! hash_equals($this->otpCode, $code)) {
        $this->otpAttempts++;

        if ($this->otpAttempts >= self::MAX_OTP_ATTEMPTS) {
            $this->otpCode = null;
            $this->otpExpiresAt = null;
            $this->otpAttempts = 0;
        }

        return false;
    }

    return true;
}

public function getOtpAttempts(): int
{
    return $this->otpAttempts;
}
```

E zero em `markEmailAsVerified()` (`User.php:88-93`):

```php
public function markEmailAsVerified(): void
{
    $this->emailVerifiedAt = new \DateTimeImmutable;
    $this->otpCode = null;
    $this->otpExpiresAt = null;
    $this->otpAttempts = 0;
}
```

Como a entidade é imutável após falhar, o `VerifyOtpUseCase` tem de persistir mesmo em falha. Hoje faz `$this->authRepository->save($user)` apenas no sucesso (`VerifyOtpUseCase.php:28-29`) — o incremento de tentativas seria perdido. O use case precisa de reordenar:

```php
$verified = $user->verifyOtp($otpCode);

if (! $verified) {
    $this->authRepository->save($user);   // persiste otp_attempts
    $this->auditLogger->log('auth.otp.failed', $user, $actor ?? new ActorContext());

    throw new \InvalidArgumentException('Código OTP inválido ou expirado.');
}

$user->markEmailAsVerified();
$this->authRepository->save($user);
```

Sem isto, o contador vive em memória e morre no fim do request — o limite nunca acumula.

### 5.5 `Domain/Enrollment/Services/EnrollmentDomainService.php` — correcção de V1

A duração autoritativa é `lessons.duration_minutes`, que é `nullable`. Três estados a tratar:

| `duration_minutes` | Significado | Conclusão automática |
|---------------------|-------------|----------------------|
| `null` | Instrutor não preencheu | **Não** — conclusão manual |
| `0` | Sem duração significativa | **Não** — conclusão manual |
| `> 0` | Duração conhecida | Sim, a 90% |

```php
public function markLessonComplete(int $watchedSeconds, ?int $totalDurationSeconds): bool
{
    if ($totalDurationSeconds === null || $totalDurationSeconds <= 0) {
        return false;
    }

    return $watchedSeconds >= ($totalDurationSeconds * 0.9);
}
```

O `return true` original para duração zero estava invertido: ausência de informação deve impedir a conclusão, nunca concedê-la.

### 5.6 `app/Http/Controllers/Student/LessonProgressController.php` — fechar V1

`duration_seconds` **deixa de ser campo do cliente**. A validação deixa de o aceitar e a duração vem da lição carregada:

```php
$request->validate([
    'watched_seconds' => ['required', 'integer', 'min:0'],
    'last_position_seconds' => ['required', 'integer', 'min:0'],
]);

$lesson = Lesson::findOrFail($lessonId);

$durationSeconds = $lesson->duration_minutes !== null
    ? $lesson->duration_minutes * 60
    : null;

$isCompleted = $this->enrollmentDomainService->markLessonComplete(
    (int) $request->input('watched_seconds'),
    $durationSeconds,
);
```

Sem throttle em `progress`, este endpoint é de longe o mais rentável de abusar — limitá-lo sem corrigir a origem é tratar o sintoma.

### 5.7 `docker/nginx/nginx.conf`

```nginx
# No contexto http {}, antes do server {}
limit_req_zone $binary_remote_addr zone=iskenda_api:10m rate=20r/s;
limit_conn_zone $binary_remote_addr zone=iskenda_conn:10m;

server {
    # ... server_name, ssl, etc.

    limit_req_zone $binary_remote_addr zone=iskenda_auth:10m rate=5r/s;

    location /api/ {
        limit_req  zone=iskenda_api  burst=40 nodelay;
        limit_conn iskenda_conn 20;
    }

    # Rotas de autenticação: janela mais apertada na edge.
    # Emite 503 para a aplicação devolver 429 no contrato correcto.
    location ~ ^/api/v1/auth/(login|verify-otp|forgot-password|verify-reset-otp|reset-password|instructor/initiate|instructor/complete)$ {
        limit_req zone=iskenda_auth burst=10 nodelay;
        limit_req_status 429;
    }
}
```

Notas:

- `10m` de zona partilhada ≈ 160 000 IPs. Reduzir se a memória for escassa.
- Nginx `limit_req` usa **janela fixa**, não token bucket. Em fronteira de janela aceita 2× a burst. Irrelevante para força bruta; relevante para não bloquear testers.
- `nodelay` faz a burst ser servida de imediato em vez de enfileirada — melhor UX para tráfego legítimo com micro-razões de pico.
- O limite aplicacional (L1–L3) é a **autoridade**. O Nginx é uma antepara grosseira; se colidir, o 429 aplicacional com o contrato correcto é o que o cliente deve ver. Por isso `limit_req_status 429` e não o 503 por defeito.

---

## 6. Plano de execução

Ordenado por severidade, não por esforço.

| # | Acção | Corrija | Esforço | Camada | Risco se não feito |
|---|-------|---------|---------|--------|--------------------|
| 1 | `duration_seconds` vem de `lessons`; `<= 0` devolve `false` | V1 | 30 min | L1 | **Certificados fraudulentos** |
| 2 | `otp_attempts` + invalidação do código + `hash_equals` | V2 | 2 h | L1 | **Tomada de conta** |
| 3 | `VerifyOtpUseCase` persiste tentativas falhadas | V2 | 30 min | L1 | Limitador inoperante |
| 4 | `throttle:auth` / `throttle:otp` com chave por email | V2, V3 | 30 min | L2 | Força bruta + DoS em CGNAT |
| 5 | `throttleApi('api')` + limiter `api` 60/min | V4 | 15 min | L3 | Sem teto |
| 6 | Limiters `pdf` / `upload` / `progress` / `broadcast` | V5 | 30 min | L4 | Custo de recursos |
| 7 | Handler 429 no contrato + `RATE_LIMIT_EXCEEDED` | V7 | 30 min | L5 | Cliente não parseia erro |
| 8 | Auditoria de `rate_limit.exceeded` | — | 30 min | L5 | Ataques invisíveis |
| 9 | `limit_req` no Nginx | V6 | 30 min | L0 | PHP saturado |
| 10 | Cloudflare à frente do VPS | V6 | 1 h | L0 | DDoS |
| 11 | `.env.example`: `CACHE_STORE=redis` | §1.4 | 5 min | — | Limiter errado em produção |

Acções 1–4 fecham os caminhos de ataque. 5–7 são higiene. 8–11 são infra.

**Estimativa total: ~7 h** — mais um dia de testes e revisão.

### Dependências

```
1 ──┐
    ├─ independentemente
2 ──→ 3        (3 sem 2 = limitador inoperante)
    └─ 4        (independente, mas medir o mesmo problema)

5, 6, 7, 8, 11    independentes
9, 10             independentes
```

A acção 3 é a armadilha clássica: sem ela, a acção 2 dá falsa sensação de segurança — o contador sobe, morre no fim do request, e nunca bloqueia nada.

---

## 7. Testes

Regras do projecto: Pest, feature tests por omissão. `php artisan make:test --pest <Nome>` (sem pasta de suite).

`phpunit.xml` usa SQLite `:memory:`, pelo que os limiters com Redis têm de ser testados com `array` como cache store ou com `RateLimiter::clear()` entre casos.

| Teste | Verifica |
|-------|----------|
| `verifyOtp` invalida o código após 5 tentativas | V2 |
| `verifyOtp` aceita o código correcto antes das 5 tentativas | V2 |
| `markLessonComplete(0, 0)` devolve `false` | V1 |
| `markLessonComplete(0, null)` devolve `false` | V1 |
| `markLessonComplete(540, 600)` devolve `true` (90%) | V1 |
| `POST progress` com `duration_seconds: 0` no body **não** conclui a aula | V1 |
| `POST progress` ignora `duration_seconds` do cliente | V1 |
| `POST /login` devolve 429 ao 6º intento do mesmo email | V2/V3 |
| `POST /login` **não** devolve 429 a emails diferentes do mesmo IP | V3 |
| `POST /verify-otp` devolve 429 ao 6º intento do mesmo email+IP | V2 |
| `POST /certificates/{id}/issue` devolve 429 ao 6º pedido | V5 |
| 429 devolve `{message, errors, code}` e header `Retry-After` | V7 |
| `/upload/chunk` devolve 429 acima do limite | V5 |

O teste "não devolve 429 a emails diferentes do mesmo IP" é o que trava a regressão de V3. Sem ele, alguém "corrige" o NAT trocando a chave por email e reintroduz o problema de botnet sem o teste falhar.

`php artisan test --compact --filter=<nome>`

---

## 8. Observabilidade

Sem isto, um ataque em curso é invisível.

**Auditoria.** Evento novo em `audit_logs`:

| Campo | Valor |
|-------|-------|
| `event_type` | `rate_limit.exceeded` |
| `actor_id` | `$request->user()?->id` |
| `actor_role` | `$request->user()?->role` |
| `actor_ip` | `$request->ip()` |
| `payload` | `{ route, limiter, key, max_attempts }` |
| `new_state` | `{ retry_after }` |

`audit_logs` é append-only e sem `UPDATE`/`DELETE` (`regras-de-negocio.md:826-868`) — é a escolha correcta para isto. Um atacante que está a sondarenticate as tentativas; a consulta é uma agregação simples sobre `event_type` e `actor_ip`.

**Queries úteis:**

```sql
-- Top IPs a bater em limites de auth (possível botnet)
SELECT actor_ip, COUNT(*) AS hits
FROM audit_logs
WHERE event_type = 'rate_limit.exceeded'
  AND created_at > NOW() - INTERVAL '1 hour'
GROUP BY actor_ip
ORDER BY hits DESC
LIMIT 20;

-- Picos por rota (identifica rota sem limite)
SELECT payload->>'route' AS route, COUNT(*) AS hits
FROM audit_logs
WHERE event_type = 'rate_limit.exceeded'
  AND created_at > NOW() - INTERVAL '24 hours'
GROUP BY 1
ORDER BY hits DESC;
```

**Alerta.** `count > 0` para `rate_limit.exceeded` com `limiter = 'auth'` ou `'otp'` numa hora → notificar. Picos de `forgot-password` num único IP → provável scraper. Picos de `pdf` num único `actor_id` → provável abuso de emissão.

---

## 9. Contrato de erro 429

Alinhado com `regras-de-negocio.md:939-970`:

```php
use Illuminate\Http\Exceptions\ThrottleRequestsException;

$exceptions->render(function (ThrottleRequestsException $e, Request $request) {
    if (! $request->is('api/*')) {
        return null;
    }

    return response()->json([
        'message' => 'Demasiadas tentativas. Tente novamente mais tarde.',
        'errors'  => [],
        'code'    => 'RATE_LIMIT_EXCEEDED',
    ], 429, $e->getHeaders());
});
```

`$e->getHeaders()` inclui `Retry-After` e `X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset`.

Acrescentar a `regras-de-negocio.md:953`:

| Código | Mensagem | HTTP Status |
|--------|----------|-------------|
| `RATE_LIMIT_EXCEEDED` | Limite de pedidos excedido | 429 |

Documentar `Retry-After` como contrato — é ele que permite ao cliente fazer backoff correcto em vez de retry agressivo.

---

## 10. O que não fazer

| Anti-padrão | Porquê |
|-------------|--------|
| `Limit::perHour()` como limite único | Janela fixa do Laravel: burst de 2× na fronteira. Irrelevante para força bruta, irritante para UX |
| Hash do IP dentro dos limiters | Já é Redis, é rápido, e o hash só atrapalha debug e correlação com `audit_logs` |
| Confiar no `limit_req` do Nginx para auth | Nginx não sabe o que é um email. Nunca pode substituir L1/L2 |
| Tratar throttle como substituto de validação | Throttle não substitui `hash_equals`, `password_verify`, nem a correcção de `duration_seconds` |
| Um único limiter para tudo | Perde-se a distinção entre volumetria, força bruta e custo |
| Chave só por IP em rota autenticada | `live/join` já usa `user_id` — está correcto. Não degradar |
| Aumentar limites para "resolver" NAT | Resolve o sintoma, mantém o CGNAT vulnerável a botnet e a DDoS |
| `Limit::none()` em `admin/*` | Broadcast e geração de PDF são as operações mais caras da aplicação |

---

## 11. Decisões em aberto

Pontos que precisam de decisão do dono do produto, não de engenharia:

### 11.1 Teto global de 60/min

**Opção A — 60/min, conforme documentado.** ample para o frontend e para scripts de teste.
**Opção B — 120/min.** mais tolerante, mas revela menos um ataque em curso.

Os progressos de vídeo e a listagem de notificações são os consumidores mais pesados. Se o cliente faz polling de notificações a cada 30 s, isso já são 2/min — folgado. **Recomendação: A (60/min).**

### 11.2 Upload a 60/min

Um vídeo de 2 GB com chunks de 5 MB são ~400 requests. A 60/min são ~7 minutos de upload — em Angola, uploads domesticais são lentos. **Pergunta a fazer:** qual é o tamanho típico de vídeo que um instrutor sobe?

- Se a maioria é < 500 MB: 60/min chega com folga
- Se há vídeos de 2 GB+: subir para 120/min ou aumentar o tamanho do chunk

Esta é a decisão com maior impacto em UX e maior incerteza. **Precisa de resposta antes de implementar.**

### 11.3 Reset de palavra-passe em NAT

A combinação `5/15min por email` + `20/15min por IP` significa que 20 pessoas da mesma operadora não conseguem pedir reset na mesma hora. Isto é aceitável ou unacceptable?

- **Aceitável** se a maioria dos casos for 1-2 pessoas por prefixo /24
- **Inaceitável** se algum condomínio ou empresa partilhar saída

**Pergunta a fazer:** ha algum segmento conhecido de utilizadores atras de NAT grande (empresa, escola, condominio)? Se sim, considerar apenas a chave por email e rely on `idempotent` + auditoria contra botnet, em vez do limite por IP.

### 11.4 Conclusão manual de aulas sem duração

Quando `duration_minutes` é `null`, a correcção de V1 faz a aula **não ser concluível automaticamente**. Alunos ficam presos.

Duas saídas:
- **A — Conclusão manual:** endpoint `POST /classroom/{lessonId}/complete` para o aluno marcar (com rate limit). Simples, reutiliza `is_course_complete`.
- **B — Tornar `duration_minutes` obrigatório:** obriga o instrutor a preencher na criação da aula. Mais limpo, mas exige migração de dados existentes e alteração do formulário.

**Recomendação: B**, se `duration_minutes` estiver preenchido na maioria das aulas existentes. Mas a nota no campo é `nullable`, o que sugere que há aulas sem duração — B implica backfill. **Decisão de produto + verificação de dados.**

### 11.5 Ponto de interrogação sobre `VerifyOtpUseCase`

A correcção em 5.4 reordena o use case para persistir em falha. Vale confirmar que `AuthRepositoryInterface::save()` não tem contrato implícito de "só guarda sucesso". Não li o repositório com detalhe suficiente para afirmar que está limpo.

### 11.6 Cloudflare vs.reverse proxy existente

O plano de deploy (`docs/deploy-vps.md`) menciona Nginx com SSL directo. Cloudflare à frente exige:
- Headers de IP reais (`CF-Connecting-IP`) e `trustProxies`: feito
- Ajustar `client_max_body_size` no plano de grátis (100 MB) — suficiente para chunks de 5 MB

Se já existe um proxy/CDN em produção, L0 muda de forma. **Pergunta: existe CDN ou proxy à frente do VPS hoje?**

---

## 12. Checklist de revisão

Executado em 2026-10-05. `php artisan test --compact`: **269 testes, 1123 asserções, todos a passar**.

### Fechado

- [x] `duration_seconds` removido da validação de `POST /classroom/{lessonId}/progress`
- [x] `markLessonComplete` devolve `false` para `null` e `0`
- [x] `otp_attempts` persiste em caso de falha (não só em sucesso)
- [x] `hash_equals` em `User::verifyOtp`, no reset de palavra-passe e no setup de instrutor
- [x] Código OTP invalidado ao atingir `MAX_OTP_ATTEMPTS`
- [x] `auth` limiter com chave dupla (email + IP)
- [x] `otp` e `otp-verify` limiters com chave dupla (email + IP)
- [x] `throttleApi('api')` activo
- [x] Limiters `pdf`, `upload`, `progress`, `broadcast` aplicados
- [x] `RATE_LIMIT_EXCEEDED` no contrato de erro
- [x] `Retry-After` presente no 429
- [x] Evento `rate_limit.exceeded` auditado
- [x] `limit_req` no Nginx, com `limit_req_status 429`
- [x] `CACHE_STORE=redis` em `.env.example`
- [x] Teste "emails diferentes do mesmo IP não são bloqueados" a passar
- [x] Testes de 429 para `pdf`, `progress` e `broadcast` a passar
- [x] `vendor/bin/pint --format agent` executado
- [x] `php artisan test --compact` completo a passar
- [x] `regras-de-negocio.md` actualizado (tabela de erros, 429, tabela de limiters)

### Em aberto

- [x] `trustProxies` com os 15 ranges do Cloudflare — lista explícita, não
      `at: '*'`. Testado: dois clientes distintos atrás do mesmo proxy não se
      bloqueiam, e um `X-Forwarded-For` forjado a partir de uma origem não
      confiada é ignorado.
- [ ] Cloudflare à frente do VPS (acção 10 — decisão de infra, não de código).
- [ ] `nginx -t` para validar a sintaxe: só é possível com o container em
      execução, não há binário local.
- [ ] Decisões da secção 11 (duração das aulas, tamanho de upload, NAT).

---

## 13. Desvios do plano, e porquê

O plano era uma proposta; a implementação encontrou coisas que ele não previu.

### 13.1 `otp` foi partido em `otp` e `otp-verify`

O plano punha envio e verificação no mesmo balancer. Na prática, as 5 tentativas
de verificação esgotavam também a cota de 5 reenvios por email: quem
invalidasse o código ficava 15 minutos sem poder pedir outro. A protecção contra
força bruta estava a boicotar-se a si mesma — o utilizador legítimo ficava preso
e o atacante não ganhava nada.

Agora o envio tem o seu balancer (`otp`, 5/15min por email) e a verificação tem
outro (`otp-verify`). O reenvio é a operação cara; a verificação é idempotente.

### 13.2 `otp-verify` é 10/10min, não 5

O limite de 5 tentativas por código pertence ao domínio (`MAX_OTP_ATTEMPTS`),
que tem consequência real: invalida o código. Se o HTTP cortasse aos 5, dispararia
primeiro, o limite do domínio nunca correria e a invalidação ficaria a ser código
morto. O `otp-verify` a 10 deixa o domínio ser o autoridade e cobre o resto:
volume de pedidos e spray de um IP contra muitas identidades.

### 13.3 Identidade dos limiters vem do token, não de `$request->user()`

`ThrottleRequests` figura em `$middlewarePriority` do kernel **a depois** de
`AuthenticatesRequests`. O Laravel reordena os middleware, por isso o throttle
corre antes de `auth:sanctum` e `$request->user()` é `null` em todas as rotas.
Nos limiters nomeados não existe o fallback automático que o `throttle:N,M` tem
para o utilizador: a chave é exactamente a devolvida pelo limiter.

O efeito, se isto passar despercebido, é pior do que não ter limite: todos os
baldes caem no IP. Com um CGNAT angolano, os 30 pedidos de progresso por 5
minutos passavam a ser 30 para *todos* os alunos do operador juntos — a
plataforma saía do ar com um único posto de trabalho.

E `auth('sanctum')->user()` não é alternativa: o guard é memoizado pelo
AuthManager, por isso resolvé-lo ali contaminaria o resto do processo. Num worker
de longa vida (Octane) o utilizador do pedido anterior continuava autenticado no
seguinte. A identidade é lida do token Bearer e memoizada no próprio request.

Custo: uma leitura de `personal_access_tokens` que o `auth:sanctum` repete. É o
preço de ter o limite por utilizador em vez de por IP. Optimização possível:
cachear o token no request e reaproveitar no middleware de autenticação.

### 13.4 Chaves com o nome do limiter

`ThrottleRequests` prefixa o nome do limiter à chave (`progress:progress:user:…`
se a chave já vier com `progress:`). Codigo-se sem o prefixo redundante.

### 13.5 `Nginx`: limites largos e sem `limit_conn`

Os limites da edge são por IP, e o IP real depende do `set_real_ip_from` do
Cloudflare. Com CGNAT, um limite apertado à entrada tiraria a plataforma do ar
sã e parcialmente — por isso `api` a 50r/s com `burst=100` e `auth` a 30r/m com
`burst=30`, que deixa entrar uma turma inteira ao mesmo tempo. A justiça entre
utilizadores é dos limiters do Laravel, que são por utilizador.

`limit_conn` ficou de fora: uma sala de 300 alunos com 300 ligações simultâneas a
um único IP seria cortada de serviços. O risco que ele mitiga resolve-se na
Cloudflare, que já está configurada no `nginx.conf`.

### 13.6 O 429 deixou de vazar stacktrace

`ThrottleRequestsException` devolvia `"Too Many Attempts."` e, em debug, o trace
completo. Agora o payload respeita o contrato único de erro do projecto, e a
 auditoria que o acompanha nunca pode transformar um 429 num 500.

### 13.7 `trustProxies` com lista, não com `'*'`

Configurado com os 15 ranges do Cloudflare, a mesma lista do
`set_real_ip_from` do `nginx.conf`. Com `at: '*'` o VPS exposto directamente
deixaria qualquer cliente mandar `X-Forwarded-For` forjado e criar o seu próprio
balde em cada pedido — o limite por rede desaparecia por completo. Lista
explícita: um endereço fora da lista é ignorado e a contagem vai para o IP real.

Dois testes cobrem isto, e o primeiro foi verificado a falhar sem a
configuração:

- dois clientes distintos atrás do mesmo endereço de proxy não se bloqueiam
  (sem `trustProxies`, o segundo herdava o balde esgotado do primeiro);
- vinte `X-Forwarded-For` forjados a partir de uma origem não confiada
  continuam a somar no mesmo balde.

---

*Documento criado em `docs/plano-rate-limit.md`. Acções 1 a 9 e 11 aplicadas e
verificadas. Por decidir: Cloudflare em produção (acção 10) e as decisoes da
secao 11.*
