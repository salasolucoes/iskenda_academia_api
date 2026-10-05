# Plano: Auto-Criação de LiveSession

> **Objetivo:** Quando o instructor cria uma aula do tipo `live`, a `LiveSession` correspondente é criada automaticamente — sem necessidade de intervenção do admin.

---

## Problema Actual

O `LessonController::store` cria uma `Lesson` com `type='live'`, mas **não cria a `LiveSession`**. A relação `hasOne` existe no model mas nunca é populada. Resultado:

- Instructor preenche tudo (título, data, link Zoom/Meet)
- Mas a `LiveSession` não existe
- Aluno não consegue entrar na live
- Admin não tem UI para criar LiveSessions manualmente

**A live fica inacessível.**

---

## Solução: CreateLiveLessonUseCase

### Princípio

> Quem cria o conteúdo é dono do ciclo de vida. O admin é supervisor, não gatekeeper.

### Arquitectura

```
Instructor cria Lesson (type=live)
  ↓
CreateLiveLessonUseCase::execute()
  ↓
1. Valida type === 'live'
2. Parse content_url JSON → scheduled_at, external_link, duration
3. Gera stream_key (Str::random(32))
4. Cria Lesson (type=live, content_url=JSON)
5. Cria LiveSession (status=scheduled, scheduled_start, raw_link, stream_key)
6. Retorna Lesson com liveSession eager-loaded
```

### LiveSession criada

| Campo | Origem | Exemplo |
|-------|--------|---------|
| `lesson_id` | Lesson criado | `uuid-da-lesson` |
| `stream_key` | Gerado aleatoriamente | `abc123...` |
| `raw_link` | `content_url.external_link` | `https://zoom.us/j/123456` |
| `scheduled_start` | `content_url.scheduled_at` | `2026-08-30 14:00:00` |
| `status` | Fixo | `scheduled` |
| `masked_token` | Null (preenchido no join) | `null` |
| `token_expires_at` | Null | `null` |

### Validações

1. `type` deve ser `live`
2. `content_url` deve ser JSON válido
3. `scheduled_at` é obrigatório no JSON
4. `scheduled_start` deve ser futuro
5. `stream_key` único (gerado via `Str::random(32)`)

### Controlo Admin (já existe)

O admin pode sobrepôr a qualquer momento:
- `PATCH /admin/live-sessions/{id}/force-start` → `status='live'`
- `PATCH /admin/live-sessions/{id}/force-end` → `status='ended'`
- `GET /admin/live-sessions` → lista todas as sessões

---

## Etapas de Implementação

### Etapa 1: Use Case
- Criar `Application/UseCases/Course/CreateLiveLessonUseCase.php`
- Recebe: `moduleId`, `title`, `description`, `contentUrl`, `durationMinutes`
- Retorna: `Lesson` com `liveSession` eager-loaded
- Transação atómica (Lesson + LiveSession)

### Etapa 2: Controller
- Refatorar `LessonController::store` para chamar Use Case quando `type=live`
- Tipos `video` e `pdf` mantêm fluxo actual

### Etapa 3: Testes
- Criação com sucesso (Lesson + LiveSession)
- Sem `scheduled_at` → exceção
- `scheduled_start` no passado → exceção
- `content_url` inválido → exceção
- Lesson não-live → Use Case não chamado
- `stream_key` único
- `status` inicial = `scheduled`

---

## Ficheiros

| Ficheiro | Ação |
|----------|------|
| `Application/UseCases/Course/CreateLiveLessonUseCase.php` | **Novo** |
| `app/Http/Controllers/Instructor/LessonController.php` | Refactor `store()` |
| `tests/Feature/CreateLiveLessonTest.php` | **Novo** |
| `docs/plans/auto-create-live-session.md` | **Novo** (este ficheiro) |
| `docs/plano-de-implementacao.md` | Atualizar Fase 8 |
