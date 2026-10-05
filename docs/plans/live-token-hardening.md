# Plano: Hardening do Token de Sessão Ao Vivo

> **Objetivo:** Endereçar 6 melhorias de robustez no sistema de tokens Redis para sessões ao vivo, com foco em segurança, resiliência e observabilidade.

---

## Contexto

O `LiveSessionTokenService` actual (Redis `SETEX`/`GETDEL`) resolveu o race condition do token por sessão. Mas ficaram pontos abertos que um senior devia endereçar antes de production hardening.

---

## Melhoria 1: Ordem de validação no middleware

### Problema

O `ValidateLiveSessionToken` consome o token **antes** de verificar se a sessão está `live`. Se a sessão ainda estiver `scheduled` ou já estiver `ended`, o token é desperdiçado (setado a null no Redis) e o estudante precisa de fazer `POST /join` outra vez.

```php
// ACTUAL (ordem errada)
$consumed = $this->tokenService->consume(...);   // consome token
if ($session->status !== 'live') { ... }          // erro → token já gone
```

### Solução

Inverter a ordem: verificar status **antes** de consumir.

```php
// CORRECTO
if ($session->status !== 'live') { ... }          // verifica primeiro
$consumed = $this->tokenService->consume(...);    // consome só se OK
```

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `app/Http/Middleware/ValidateLiveSessionToken.php` | Reordenar validações |

---

## Melhoria 2: Rate limiting no join

### Problema

Sem limite de chamadas a `POST /v1/classroom/{id}/join`. Um estudante pode spammar o endpoint e forçar many writes no Redis (mesmo que só um token seja válido, é trabalho desperdiçado e potencial vector de abuso).

### Solução

Adicionar `throttle` na rota — ex: 10 requests por minuto por estudante.

```php
Route::post('/classroom/{liveSessionId}/join', [...])
    ->middleware('throttle:10,1');
```

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `routes/api.php` | Adicionar middleware `throttle` na rota join |

---

## Melhoria 3: Remover colunas mortas do DB

### Problema

`masked_token` e `token_expires_at` em `live_sessions` já não são usadas (tokens vivem em Redis). Manter colunas mortas confunde quem lê o schema e cria ambiguidade.

### Solução

Migration que remove as duas colunas:

```php
Schema::table('live_sessions', function (Blueprint $table) {
    $table->dropColumn(['masked_token', 'token_expires_at']);
});
```

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `database/migrations/2026_08_XX_drop_token_columns_from_live_sessions.php` | **Novo** |
| `Infrastructure/Persistence/Eloquent/Models/LiveSession.php` | Remover de `$fillable` |

---

## Melhoria 4: Fallback graceful se Redis cair

### Problema

Se Redis estiver down, `Redis::setex()` lança `ConnectionException` e o join retorna 500. Em production, um outage de Redis não devia bloquear o acesso à plataforma.

### Solução

Try/catch no `LiveSessionTokenService` que cai para o comportamento antigo (DB) ou retorna 503 com mensagem clara.

```php
public function issue(string $sessionId, string $studentId): string
{
    $token = hash('sha256', $sessionId . $studentId . bin2hex(random_bytes(16)));

    try {
        Redis::setex($this->key($sessionId, $studentId), $this->ttlSeconds, $token);
    } catch (\Throwable) {
        // Fallback: grava no DB (colunas deprecated)
        LiveSession::where('id', $sessionId)->update([
            'masked_token' => $token,
            'token_expires_at' => now()->addMinutes(30),
        ]);
    }

    return $token;
}
```

> **Nota:** As colunas `masked_token`/`token_expires_at` devem ser mantidas como fallback se esta melhoria for implementada (adiar Melhoria 3).

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `Infrastructure/Services/LiveSessionTokenService.php` | Try/catch + fallback DB |

---

## Melhoria 5: Auditoria de tentativas de join

### Problema

Não existe log de tentativas de join (sucesso ou falha). Saber quem tentou entrar, quando, e porquê é importante para debug e compliance.

### Solução

Registar no `audit_logs` (via `EloquentAuditLogger`) nos casos de:
- Join bem-sucedido → `live.join`
- Token inválido/expirado → `live.join.failed`
- Não inscrito → `live.join.forbidden`

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `app/Http/Middleware/ValidateLiveSessionToken.php` | Log de sucesso e falha |
| `app/Http/Controllers/Student/LiveSessionController.php` | Log de join request |

---

## Melhoria 6: TTL configurável

### Problema

30 minutos está hardcoded no `LiveSessionTokenService::__construct()`. Devia vir de config para poder ajustar sem deploy.

### Solução

Adicionar `config/live.php`:

```php
return [
    'token_ttl_minutes' => env('LIVE_TOKEN_TTL_MINUTES', 30),
];
```

E no service:

```php
public function __construct()
{
    $this->ttlSeconds = config('live.token_ttl_minutes', 30) * 60;
}
```

### Ficheiros

| Ficheiro | Ação |
|----------|------|
| `config/live.php` | **Novo** |
| `Infrastructure/Services/LiveSessionTokenService.php` | Ler de config |
| `.env` | Adicionar `LIVE_TOKEN_TTL_MINUTES=30` |

---

## Ordem Sugerida de Implementação

| # | Melhoria | Prioridade | Dependências |
|---|----------|------------|--------------|
| 1 | Ordem de validação | Alta | Nenhuma |
| 2 | Rate limiting | Alta | Nenhuma |
| 6 | TTL configurável | Média | Nenhuma |
| 4 | Fallback Redis | Média | Decide se colunas são mantidas |
| 5 | Auditoria de join | Média | Nenhuma |
| 3 | Remover colunas mortas | Baixa | Só depois da Melhoria 4 |

> **Decisão pendente:** Se a Melhoria 4 (fallback) for implementada, as colunas `masked_token`/`token_expires_at` devem ser mantidas como backup. Se não, podem ser removidas imediatamente (Melhoria 3 antes da 4).

---

## Estimativa

| Melhoria | Esforço |
|----------|---------|
| 1. Ordem de validação | 5 min |
| 2. Rate limiting | 5 min |
| 3. Remover colunas | 10 min |
| 4. Fallback Redis | 30 min |
| 5. Auditoria | 20 min |
| 6. TTL configurável | 10 min |
| **Total** | **~80 min** |
