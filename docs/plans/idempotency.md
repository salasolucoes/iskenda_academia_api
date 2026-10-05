# Plano: Idempotência nas Rotas Críticas

> **Objetivo:** Garantir que operações críticas (checkout, upload, tickets) não produzem efeitos colaterais quando o cliente faz retry.

---

## Contexto

Em produção, requests podem falhar por rede, timeout, ou o cliente pode fazer retry automaticamente. Sem idempotência:
- Estudante clica "Pagar" 2x → cobra 2x da wallet
- Upload de comprovativo retry → cria 2 vouchers
- Criação de ticket retry → cria 2 tickets

---

## Análise de Risco por Endpoint

| Endpoint | Método | Risco | Severidade |
|----------|--------|-------|------------|
| `POST /v1/checkout` | POST | Cobra 2x + cria 2 enrollments | **Crítica** |
| `POST /v1/wallet/vouchers` | POST | Cria 2 vouchers pendentes | **Alta** |
| `POST /v1/tickets` | POST | Cria 2 tickets | **Média** |
| `POST /v1/admin/tickets/{id}/reply` | POST | Envia 2 respostas | **Média** |
| `PATCH /v1/admin/vouchers/{id}/approve` | PATCH | Aprova 2x (já protegido por status) | **Baixa** |

---

## Implementação

### 1. Migration: Tabela `idempotency_keys`

```php
Schema::create('idempotency_keys', function (Blueprint $table) {
    $table->string('key', 64)->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->jsonb('request_hash');
    $table->jsonb('response');
    $table->smallInteger('status_code');
    $table->timestampTz('created_at')->useCurrent();
    $table->timestampTz('expires_at');

    $table->index('expires_at');
});
```

**Regras:**
- `key` = UUID v4 gerado pelo client (header `Idempotency-Key`)
- `request_hash` = SHA-256 do body + method + path (valida que é o mesmo request)
- `response` = payload de resposta cacheado
- `expires_at` = TTL de 24h (limpeza periódica)

### 2. Middleware `EnsureIdempotent`

```php
class EnsureIdempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        // 1. Buscar key existente
        $existing = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            // 2. Validar que é o mesmo request
            $currentHash = $this->hashRequest($request);
            if ($existing->request_hash !== $currentHash) {
                return response()->json([
                    'message' => 'Idempotency key reused with different request.',
                ], 422);
            }

            // 3. Retornar resposta cacheada
            return response()->json(
                json_decode($existing->response, true),
                $existing->status_code
            );
        }

        // 4. Capturar resposta
        $response = $next($request);

        // 5. Salvar apenas se sucesso (4xx/5xx não cacheia)
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
            $request->all(),
        ]));
    }
}
```

### 3. Rotas Protegidas

```php
// Checkout (crítico)
Route::post('/checkout', [...])
    ->middleware('idempotent');

// Upload de comprovativo (alto)
Route::post('/wallet/vouchers', [...])
    ->middleware('idempotent');

// Criação de ticket (médio)
Route::post('/tickets', [...])
    ->middleware('idempotent');
```

### 4. Frontend: Gerar Key

```typescript
// src/services/api.ts
const generateIdempotencyKey = (): string => {
  return crypto.randomUUID();
};

// No checkout:
await api.post('/checkout', data, {
  headers: {
    'Idempotency-Key': generateIdempotencyKey(),
  },
});
```

### 5. Limpeza Periódica

```php
// App\Console\Commands\CleanExpiredIdempotencyKeys.php
class CleanExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:clean';

    public function handle(): int
    {
        $deleted = DB::table('idempotency_keys')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Cleaned {$deleted} expired idempotency keys.");

        return self::SUCCESS;
    }
}
```

Agendar: `* * * * * php artisan idempotency:clean` (ou a cada 6h).

---

## Ficheiros

| Ficheiro | Ação |
|----------|------|
| `database/migrations/2026_08_XX_create_idempotency_keys_table.php` | **Novo** |
| `app/Http/Middleware/EnsureIdempotent.php` | **Novo** |
| `bootstrap/app.php` | Registrar middleware alias |
| `routes/api.php` | Aplicar middleware nas rotas |
| `app/Console/Commands/CleanExpiredIdempotencyKeys.php` | **Novo** |
| `routes/console.php` | Agendar limpeza |

---

## Ordem Sugerida de Implementação

| # | Item | Prioridade | Dependências |
|---|------|------------|--------------|
| 1 | Migration `idempotency_keys` | Alta | Nenhuma |
| 2 | Middleware `EnsureIdempotent` | Alta | #1 |
| 3 | Aplicar no checkout | Crítica | #2 |
| 4 | Aplicar no upload de voucher | Alta | #2 |
| 5 | Aplicar na criação de ticket | Média | #2 |
| 6 | Frontend: gerar key | Alta | #3 |
| 7 | Limpeza periódica | Baixa | #1 |

---

## Estimativa

| Item | Esforço |
|------|---------|
| Migration | 10 min |
| Middleware | 40 min |
| Aplicar nas rotas | 15 min |
| Frontend | 15 min |
| Limpeza periódica | 10 min |
| Testes | 30 min |
| **Total** | **~120 min** |

---

## Alternativa: Sem Tabela (Redis)

Para alta performance, usar Redis em vez de tabela:

```php
// Salvar
Redis::setex("idempotent:{$key}", 86400, json_encode([
    'hash' => $hash,
    'response' => $response,
    'status' => $statusCode,
]));

// Buscar
$cached = Redis::get("idempotent:{$key}");
```

**Vantagens:** Mais rápido, TTL automático.
**Desvantagens:** Perde dados se Redis reiniciar (aceitável para 24h).

> **Decisão:** Usar Redis se performance for crítica, DB se precisar de auditoria.
