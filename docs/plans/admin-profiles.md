# Plano — Perfis de Aluno e Formador no Admin

> O admin precisa de ver perfis completos tanto de estudantes como de formadores.
> Actualmente:
> - `StudentManager` lista/cria/toggle/delete — **sem view de detalhe**
> - `InstructorManager` lista/cria/edita/delete/verify — **sem view de detalhe**
> - `getInstructorCourses()` existe no service mas **nunca e chamado por nenhuma pagina**

---

## PARTE A — Perfil do Aluno

### A.1 Situacao Atual

**Backend — endpoints existentes:**
| Endpoint | Existe | Notas |
|----------|--------|-------|
| `GET /admin/users` | Sim | Lista paginada com search/role/is_active |
| `GET /admin/users/{id}` | Sim | Dados basicos + `enrollments_count` |
| `GET /admin/users/{id}/enrollments` | Sim | Matriculas com course title, status, datas |
| `GET /admin/users/{id}/wallet` | **Nao** | Wallet + transacoes |
| `GET /admin/users/{id}/certificates` | **Nao** | Certificados emitidos |
| `GET /admin/users/{id}/tickets` | **Nao** | Tickets de suporte |
| `GET /admin/users/{id}/progress/{enrollmentId}` | **Nao** | Progresso por matricula |

**Frontend:**
| Ficheiro | Existe | Notas |
|----------|--------|-------|
| `adminService.ts` | Sim | `getUser(id)`, `getUserEnrollments(id)` — existem mas nao sao usados |
| `StudentManager.tsx` | Sim | Lista sem link para perfil |

### A.2 Novos Endpoints Backend

#### `GET /admin/users/{userId}/wallet`

```php
$wallet = StudentWallet::where('student_id', $userId)->first();
$transactions = WalletTransaction::where('wallet_id', $wallet?->id)
    ->orderByDesc('created_at')->paginate(20);

return response()->json([
    'data' => [
        'balance_cents' => $wallet->balance_cents ?? 0,
        'transactions' => WalletTransactionResource::collection($transactions),
    ],
]);
```

#### `GET /admin/users/{userId}/certificates`

```php
$certificates = Certificate::where('student_id', $userId)
    ->with('enrollment.course:id,title,slug')
    ->orderByDesc('issued_at')->get();

return response()->json([
    'data' => CertificateResource::collection($certificates),
]);
```

#### `GET /admin/users/{userId}/tickets`

```php
$tickets = Ticket::where('student_id', $userId)
    ->with('messages.author:id,name,role')
    ->orderByDesc('created_at')->paginate(20);

return response()->json([
    'data' => TicketResource::collection($tickets),
]);
```

#### `GET /admin/users/{userId}/progress/{enrollmentId}`

```php
$enrollment = Enrollment::where('id', $enrollmentId)
    ->where('student_id', $userId)->firstOrFail();

$progress = LessonProgress::where('enrollment_id', $enrollmentId)
    ->with('lesson:id,title,duration_minutes')->get();

$totalLessons = Lesson::whereHas('module', fn($q) =>
    $q->where('course_id', $enrollment->course_id))->count();

return response()->json([
    'data' => [
        'enrollment_id' => $enrollmentId,
        'status' => $enrollment->status,
        'total_lessons' => $totalLessons,
        'completed_lessons' => $progress->where('is_completed', true)->count(),
        'lessons' => $progress->map(fn($p) => [
            'lesson_id' => $p->lesson_id,
            'lesson_title' => $p->lesson?->title,
            'is_completed' => $p->is_completed,
            'watched_seconds' => $p->watched_seconds,
            'duration_minutes' => $p->lesson?->duration_minutes,
        ]),
    ],
]);
```

### A.3 Frontend — `StudentProfile.tsx`

**Rota:** `/admin/students/:id`

```
┌─────────────────────────────────────────────────────────┐
│ ← Voltar para Gestao de Alunos                          │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ [Avatar]  Nome do Aluno                             │ │
│ │           email@example.com  |  +244 9XX XXX XXX   │ │
│ │           Membro desde: Jan 2025  |  Status: Ativo  │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ [Matriculas] [Progresso] [Certificados] [Wallet] [Suporte] │
│ ─────────────────────────────────────────────────────── │
│ ( conteudo da aba selecionada )                         │
└─────────────────────────────────────────────────────────┘
```

**Abas:**

| Aba | Conteudo | Fonte |
|-----|----------|-------|
| Matriculas | Tabela: Curso, Status (badge), Matriculado em, Concluido em, Accao | `getUserEnrollments(id)` |
| Progresso | Dropdown matricula + barra progresso + lista aulas (titulo, estado, tempo/duracao) | `GET /admin/users/{id}/progress/{enrollmentId}` |
| Certificados | Tabela: Curso, Data Emissao, Hash, Botao copiar | `GET /admin/users/{id}/certificates` |
| Wallet | Card saldo + tabela transacoes (data, tipo, direccao, montante, saldos, descricao) | `GET /admin/users/{id}/wallet` |
| Suporte | Lista tickets com expand inline para mensagens | `GET /admin/users/{id}/tickets` |

---

## PARTE B — Perfil do Formador

### B.1 Situacao Atual

**Backend — endpoints existentes:**
| Endpoint | Existe | Notas |
|----------|--------|-------|
| `GET /admin/instructors` | Sim | Lista todos (sem pagination no use case) |
| `GET /admin/instructors/{id}` | Sim | Dados basicos (sem stats nem relations) |
| `GET /admin/instructors/{id}/courses` | Sim | Cursos paginados com filtro status — **nunca chamado no frontend** |
| `PATCH /instructors/{id}/verify` | Sim | Marca email_verified_at + is_active |
| `PATCH /instructors/{id}/unverify` | Sim | Remove email_verified_at |

**Dados disponiveis mas nao expostos:**
- Total de alunos (via `Enrollment` nos cursos do formador)
- Total de aulas (via `Course → Module → Lesson`)
- Live sessions conduzidas (via `Course → Module → Lesson → LiveSession`)
- Receita total (via `OrderItem` nos cursos do formador — price_cents)

**Frontend:**
| Ficheiro | Existe | Notas |
|----------|--------|-------|
| `adminService.ts` | Sim | `getInstructorCourses(id)` existe mas nunca e usado |
| `InstructorManager.tsx` | Sim | Lista sem link para perfil |

### B.2 Novos Endpoints Backend

#### `GET /admin/instructors/{id}/stats`

Retorna metricas agregadas do formador num so request.

```php
$instructorId = $instructor;
$courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

$stats = [
    'total_courses' => $courseIds->count(),
    'published_courses' => Course::where('instructor_id', $instructorId)
        ->where('status', 'published')->count(),
    'total_students' => Enrollment::whereIn('course_id', $courseIds)
        ->where('student_id', '!=', null)
        ->distinct('student_id')->count('student_id'),
    'total_lessons' => Lesson::whereHas('module', fn($q) =>
        $q->whereIn('course_id', $courseIds))->count(),
    'total_live_sessions' => LiveSession::whereHas('lesson.module', fn($q) =>
        $q->whereIn('course_id', $courseIds))->count(),
    'revenue_cents' => OrderItem::whereIn('course_id', $courseIds)
        ->sum('price_cents'),
];

return response()->json(['data' => $stats]);
```

#### `GET /admin/instructors/{id}/students`

Lista alunos unicos de todos os cursos do formador, com contagem de cursos e progresso.

```php
$courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

$students = User::whereHas('enrollments', fn($q) =>
    $q->whereIn('course_id', $courseIds))
    ->withCount(['enrollments as enrolled_courses_count' => fn($q) =>
        $q->whereIn('course_id', $courseIds)])
    ->orderByDesc('created_at')
    ->paginate(20);

return response()->json([
    'data' => $students->map(fn($s) => [
        'id' => $s->id,
        'name' => $s->name,
        'email' => $s->email,
        'enrolled_courses_count' => $s->enrolled_courses_count,
        'created_at' => $s->created_at,
    ]),
    'meta' => [
        'current_page' => $students->currentPage(),
        'last_page' => $students->lastPage(),
        'total' => $students->total(),
    ],
]);
```

#### `GET /admin/instructors/{id}/live-sessions`

Lista sessoes ao vivo dos cursos do formador.

```php
$sessions = LiveSession::whereHas('lesson.module.course', fn($q) =>
    $q->where('instructor_id', $instructorId))
    ->with('lesson.module.course:id,title')
    ->orderByDesc('scheduled_start')
    ->paginate(20);

return response()->json([
    'data' => LiveSessionResource::collection($sessions),
    'meta' => [...],
]);
```

### B.3 Frontend — `InstructorProfile.tsx`

**Rota:** `/admin/instructors/:id`

```
┌─────────────────────────────────────────────────────────┐
│ ← Voltar para Gestao de Formadores                      │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ [Avatar]  Nome do Formador                          │ │
│ │           email@example.com  |  Verificado: Sim     │ │
│ │           Membro desde: Jan 2025  |  Status: Ativo  │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐          │
│ │  12  │ │   8  │ │ 245  │ │  86  │ │ 450K │          │
│ │Cursos│ │Publ. │ │Alunos│ │Aulas │ │Kz    │          │
│ └──────┘ └──────┘ └──────┘ └──────┘ └──────┘          │
│                                                         │
│ [Cursos] [Alunos] [Sessoes Ao Vivo]                     │
│ ─────────────────────────────────────────────────────── │
│ ( conteudo da aba selecionada )                         │
└─────────────────────────────────────────────────────────┘
```

**KPI Cards (topo):** Buscados de `GET /admin/instructors/{id}/stats`

| Card | Fonte |
|------|-------|
| Total Cursos | `total_courses` |
| Publicados | `published_courses` |
| Total Alunos | `total_students` |
| Total Aulas | `total_lessons` |
| Receita Total | `revenue_cents` (formatado como Kz) |

**Abas:**

| Aba | Conteudo | Fonte |
|-----|----------|-------|
| Cursos | Tabela: Titulo, Modalidade, Preco, Status (badge), Alunos inscritos, Criado em | `getInstructorCourses(id)` (ja existe) |
| Alunos | Tabela: Nome, Email, Cursos Inscritos, Membro desde | `GET /admin/instructors/{id}/students` |
| Sessoes Ao Vivo | Tabela: Curso, Data Agendada, Inicio Real, Fim Real, Status (badge), Stream Key | `GET /admin/instructors/{id}/live-sessions` |

### B.4 Updates ao `InstructorManager.tsx`

- Adicionar coluna "Accoes" na tabela com botao "Ver Perfil"
- Botao navega para `/admin/instructors/:id`

---

## PARTE C — Compartilhado

### C.1 Novos Resources

| Resource | Ficheiro | Campos |
|----------|----------|--------|
| `WalletTransactionResource` | `app/Http/Resources/WalletTransactionResource.php` | id, amount_cents, direction, type, description, balance_before, balance_after, created_at |
| `CertificateResource` | `app/Http/Resources/CertificateResource.php` | id, enrollment_id, course_title, issued_at, verification_hash, pdf_url |

### C.2 Novas Rotas Backend

Adicionar ao bloco admin em `routes/api.php`:

```php
// Student profile (UserController)
Route::get('/users/{userId}/wallet', [UserController::class, 'wallet']);
Route::get('/users/{userId}/certificates', [UserController::class, 'certificates']);
Route::get('/users/{userId}/tickets', [UserController::class, 'tickets']);
Route::get('/users/{userId}/progress/{enrollmentId}', [UserController::class, 'progress']);

// Instructor profile (InstructorController)
Route::get('/instructors/{instructor}/stats', [InstructorController::class, 'stats']);
Route::get('/instructors/{instructor}/students', [InstructorController::class, 'students']);
Route::get('/instructors/{instructor}/live-sessions', [InstructorController::class, 'liveSessions']);
```

### C.3 Updates ao `adminService.ts`

```typescript
// Student profile
getStudentWallet(userId: string, page?: number): Promise<{ balance_cents: number; transactions: WalletTransaction[] }>
getStudentCertificates(userId: string): Promise<Certificate[]>
getStudentTickets(userId: string, page?: number): Promise<Ticket[]>
getStudentProgress(userId: string, enrollmentId: string): Promise<ProgressDetail>

// Instructor profile
getInstructorStats(id: string): Promise<InstructorStats>
getInstructorStudents(id: string, page?: number): Promise<{ data: InstructorStudent[]; meta: PaginationMeta }>
getInstructorLiveSessions(id: string, page?: number): Promise<{ data: LiveSession[]; meta: PaginationMeta }>
```

### C.4 Updates ao Router (`App.tsx`)

```tsx
<Route path="/admin/students/:id" element={<StudentProfile />} />
<Route path="/admin/instructors/:id" element={<InstructorProfile />} />
```

---

## D. Ficheiros a Criar/Modificar

### Criar
| # | Ficheiro | Tipo |
|---|----------|------|
| 1 | `api/app/Http/Resources/WalletTransactionResource.php` | Backend resource |
| 2 | `api/app/Http/Resources/CertificateResource.php` | Backend resource |
| 3 | `iskenda_academy/src/pages/admin/StudentProfile.tsx` | Frontend page |
| 4 | `iskenda_academy/src/pages/admin/InstructorProfile.tsx` | Frontend page |

### Modificar
| # | Ficheiro | Mudanca |
|---|----------|---------|
| 5 | `api/app/Http/Controllers/Admin/UserController.php` | +4 metodos: `wallet`, `certificates`, `tickets`, `progress` |
| 6 | `api/app/Http/Controllers/Admin/InstructorController.php` | +3 metodos: `stats`, `students`, `liveSessions` |
| 7 | `api/routes/api.php` | +7 rotas admin |
| 8 | `iskenda_academy/src/services/adminService.ts` | +7 metodos + tipos |
| 9 | `iskenda_academy/src/pages/admin/StudentManager.tsx` | +botao "Ver Perfil" |
| 10 | `iskenda_academy/src/pages/admin/InstructorManager.tsx` | +botao "Ver Perfil" |
| 11 | `iskenda_academy/src/App.tsx` ou router | +2 rotas |

---

## E. Ordem de Execucao

1. Criar `WalletTransactionResource` + `CertificateResource` (backend)
2. Adicionar 4 metodos ao `UserController` (backend — aluno)
3. Adicionar 3 metodos ao `InstructorController` (backend — formador)
4. Adicionar 7 rotas ao `api.php` (backend)
5. Rodar `vendor/bin/pint --dirty --format agent` + `php artisan test --compact`
6. Atualizar `adminService.ts` com 7 metodos e tipos (frontend)
7. Criar `StudentProfile.tsx` com 5 abas (frontend)
8. Criar `InstructorProfile.tsx` com 3 abas + KPI cards (frontend)
9. Atualizar `StudentManager.tsx` + `InstructorManager.tsx` com botoes "Ver Perfil"
10. Adicionar 2 rotas no router (frontend)
11. Rodar `npm run lint` + `npm run build`

---

## F. Criterios de Aceite

### Aluno
- [ ] Admin clica num aluno e ve o perfil completo
- [ ] Aba Matriculas mostra todas as matriculas com status e datas
- [ ] Aba Progresso mostra detalhe de aulas concluidas/pendentes por matricula
- [ ] Aba Certificados mostra hashes de verificacao reais
- [ ] Aba Wallet mostra saldo e historico de transacoes
- [ ] Aba Suporte mostra tickets e mensagens

### Formador
- [ ] Admin clica num formador e ve o perfil completo
- [ ] KPI cards mostram metricas agregadas (cursos, alunos, aulas, receita)
- [ ] Aba Cursos mostra todos os cursos com status e alunos inscritos
- [ ] Aba Alunos mostra alunos unicos com contagem de cursos
- [ ] Aba Sessoes Ao Vivo mostra historico de transmissoes

### Geral
- [ ] Botoes "Voltar" retornam as respectivas gestoes
- [ ] Loading states em todas as abas
- [ ] Erros da API tratados com toast
- [ ] `npm run build` sem erros
- [ ] `php artisan test --compact` sem novos failures

---

## G. Estimativa de Esforco

| Area | Ficheiros | Horas estimadas |
|------|-----------|----------------|
| Backend (resources + controllers + rotas) | 5 | 2.5h |
| Frontend (services + 2 paginas + 2 managers + router) | 5 | 5h |
| Testes | 2-3 | 1.5h |
| **Total** | **12-13** | **~9h** |
