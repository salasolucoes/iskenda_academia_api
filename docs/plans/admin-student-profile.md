# Plano — Perfil do Aluno no Admin

> O admin precisa de ver o perfil completo de um estudante: dados pessoais, matriculas,
> progresso, certificados, wallet e tickets de suporte.
> Atualmente o `StudentManager` apenas lista/cria/toggle/delete — nao tem view de detalhe.

---

## 1. Situacao Atual

### Backend
| Endpoint | Existe | Notas |
|----------|--------|-------|
| `GET /admin/users` | Sim | Lista paginada com search/role/is_active |
| `GET /admin/users/{id}` | Sim | Dados basicos + `enrollments_count` |
| `GET /admin/users/{id}/enrollments` | Sim | Matriculas com course title, status, datas |
| `GET /admin/users/{id}/wallet` | **Nao** | Wallet + transacoes do aluno |
| `GET /admin/users/{id}/certificates` | **Nao** | Certificados emitidos |
| `GET /admin/users/{id}/tickets` | **Nao** | Tickets de suporte |
| `GET /admin/users/{id}/progress` | **Nao** | Progresso por matricula |

### Frontend
| Ficheiro | Existe | Notas |
|----------|--------|-------|
| `adminService.ts` | Sim | `getUser(id)`, `getUserEnrollments(id)` — existem mas nao sao usados |
| `StudentManager.tsx` | Sim | Lista sem link para perfil |
| `StudentProfile.tsx` | **Nao** | — |

---

## 2. Novos Endpoints Backend

### 2.1 `GET /admin/users/{userId}/wallet`

Retorna wallet do aluno + historico de transacoes.

```php
// UserController::wallet()
$wallet = StudentWallet::where('student_id', $userId)->first();

$transactions = WalletTransaction::where('wallet_id', $wallet?->id)
    ->orderByDesc('created_at')
    ->paginate(20);

return response()->json([
    'data' => [
        'balance_cents' => $wallet->balance_cents ?? 0,
        'transactions' => WalletTransactionResource::collection($transactions),
    ],
]);
```

### 2.2 `GET /admin/users/{userId}/certificates`

Retorna todos os certificados emitidos para o aluno.

```php
// UserController::certificates()
$certificates = Certificate::where('student_id', $userId)
    ->with('enrollment.course:id,title,slug')
    ->orderByDesc('issued_at')
    ->get();

return response()->json([
    'data' => CertificateResource::collection($certificates),
]);
```

### 2.3 `GET /admin/users/{userId}/tickets`

Retorna tickets de suporte do aluno.

```php
// UserController::tickets()
$tickets = Ticket::where('student_id', $userId)
    ->with('messages.author:id,name,role')
    ->orderByDesc('created_at')
    ->paginate(20);

return response()->json([
    'data' => TicketResource::collection($tickets),
]);
```

### 2.4 `GET /admin/users/{userId}/progress/{enrollmentId}`

Retorna progresso detalhado de uma matricula especifica.

```php
// UserController::progress()
$enrollment = Enrollment::where('id', $enrollmentId)
    ->where('student_id', $userId)
    ->firstOrFail();

$progress = LessonProgress::where('enrollment_id', $enrollmentId)
    ->with('lesson:id,title,duration_minutes,module_id')
    ->get();

$moduleId = $progress->first()?->lesson?->module_id;
$totalLessons = Lesson::whereHas('module', fn($q) => $q->where('course_id', $enrollment->course_id))->count();

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

### 2.5 Novos Resources

| Resource | Ficheiro | Campos |
|----------|----------|--------|
| `WalletTransactionResource` | `app/Http/Resources/WalletTransactionResource.php` | id, amount_cents, direction, type, description, balance_before, balance_after, created_at |
| `CertificateResource` | `app/Http/Resources/CertificateResource.php` | id, enrollment_id, course_title, issued_at, verification_hash, pdf_url |

### 2.6 Rotas

Adicionar ao bloco admin em `routes/api.php`:

```php
Route::get('/users/{userId}/wallet', [UserController::class, 'wallet']);
Route::get('/users/{userId}/certificates', [UserController::class, 'certificates']);
Route::get('/users/{userId}/tickets', [UserController::class, 'tickets']);
Route::get('/users/{userId}/progress/{enrollmentId}', [UserController::class, 'progress']);
```

---

## 3. Frontend — Pagina de Perfil

### 3.1 `src/pages/admin/StudentProfile.tsx`

Pagina dedicada com layout de abas (tabs) para cada area do aluno.

**Rota:** `/admin/students/:id`

**Layout:**
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
│                                                         │
│ ( conteudo da aba selecionada )                         │
└─────────────────────────────────────────────────────────┘
```

### 3.2 Abas

#### Aba 1: Matriculas (padrao)
- Tabela: Curso | Status (badge) | Matriculado em | Concluido em | Acao (ver progresso)
- Dados de `getUserEnrollments(id)` (ja existe no adminService)
- Badge de status: `active` = azul, `completed` = verde, `cancelled` = vermelho
- Link "Ver Progresso" navega para aba Progresso com enrollment selecionado

#### Aba 2: Progresso
- Dropdown para selecionar matricula (apenas active/completed)
- Ao selecionar, chama `GET /admin/users/{id}/progress/{enrollmentId}`
- Barra de progresso geral (% completas / total)
- Lista de aulas: Titulo | Estado (check/x) | Tempo assistido / Duracao
- Badges: concluida = verde, pendente = cinza

#### Aba 3: Certificados
- Tabela: Curso | Data de Emissao | Hash de Verificacao | Link (copiar)
- Dados de `GET /admin/users/{id}/certificates`
- Botao "Copiar Hash" com toast feedback

#### Aba 4: Wallet
- Card topo: Saldo actual (grande, destaque)
- Tabela: Data | Tipo | Direccao (in/out badge) | Montante | Saldo anterior/depois | Descricao
- Dados de `GET /admin/users/{id}/wallet`
- Badge: `credit` = verde, `debit` = vermelho, `admin_adjustment` = laranja

#### Aba 5: Suporte (Tickets)
- Lista de tickets: Titulo | Prioridade (badge) | Estado | Data | Ultima mensagem
- Ao clicar num ticket, expande para ver mensagens (inline, sem modal)
- Dados de `GET /admin/users/{id}/tickets`
- Badge prioridade: `high` = vermelho, `medium` = laranja, `low` = cinza

### 3.3 Updates ao `adminService.ts`

```typescript
// Novos metodos
getStudentWallet(userId: string, page?: number): Promise<{ balance_cents: number; transactions: WalletTransaction[] }>
getStudentCertificates(userId: string): Promise<Certificate[]>
getStudentTickets(userId: string, page?: number): Promise<Ticket[]>
getStudentProgress(userId: string, enrollmentId: string): Promise<ProgressDetail>
```

### 3.4 Updates ao `StudentManager.tsx`

- Adicionar coluna "Accoes" na tabela com botao "Ver Perfil"
- Botao navega para `/admin/students/:id`
- Manter funcionalidades existentes (criar, toggle, delete)

### 3.5 Nova Rota Frontend

No `App.tsx` ou router config:

```tsx
<Route path="/admin/students/:id" element={<StudentProfile />} />
```

---

## 4. Ficheiros a Criar/Modificar

### Criar
| # | Ficheiro | Tipo |
|---|----------|------|
| 1 | `api/app/Http/Resources/WalletTransactionResource.php` | Backend resource |
| 2 | `api/app/Http/Resources/CertificateResource.php` | Backend resource |
| 3 | `iskenda_academy/src/pages/admin/StudentProfile.tsx` | Frontend page |

### Modificar
| # | Ficheiro | Mudanca |
|---|----------|---------|
| 4 | `api/app/Http/Controllers/Admin/UserController.php` | Adicionar metodos `wallet()`, `certificates()`, `tickets()`, `progress()` |
| 5 | `api/routes/api.php` | Adicionar 4 rotas admin |
| 6 | `iskenda_academy/src/services/adminService.ts` | Adicionar 4 metodos novos + tipos |
| 7 | `iskenda_academy/src/pages/admin/StudentManager.tsx` | Adicionar botao "Ver Perfil" por linha |
| 8 | `iskenda_academy/src/App.tsx` ou router | Adicionar rota `/admin/students/:id` |

---

## 5. Ordem de Execucao

1. Criar `WalletTransactionResource` + `CertificateResource` (backend)
2. Adicionar 4 metodos ao `UserController` (backend)
3. Adicionar 4 rotas ao `api.php` (backend)
4. Rodar `vendor/bin/pint --dirty --format agent` + `php artisan test --compact`
5. Atualizar `adminService.ts` com novos metodos e tipos (frontend)
6. Criar `StudentProfile.tsx` com 5 abas (frontend)
7. Atualizar `StudentManager.tsx` com botao "Ver Perfil" (frontend)
8. Adicionar rota no router (frontend)
9. Rodar `npm run lint` + `npm run build`

---

## 6. Critérios de Aceite

- [ ] Admin clica num aluno na lista e vê o perfil completo
- [ ] Aba Matriculas mostra todas as matriculas com status e datas
- [ ] Aba Progresso mostra detalhe de aulas concluidas/pendentes por matricula
- [ ] Aba Certificados mostra hashes de verificacao reais
- [ ] Aba Wallet mostra saldo e historico de transacoes
- [ ] Aba Suporte mostra tickets e mensagens
- [ ] Botao "Voltar" retorna a Gestao de Alunos
- [ ] Loading states em todas as abas
- [ ] Erros da API sao tratados com toast
- [ ] `npm run build` sem erros
- [ ] `php artisan test --compact` sem novos failures

---

## 7. Estimativa de Esforco

| Area | Ficheiros | Horas estimadas |
|------|-----------|----------------|
| Backend (resources + controller + rotas) | 4 | 1.5h |
| Frontend (service + pagina + updates) | 3 | 3h |
| Testes | 1-2 | 1h |
| **Total** | **8-9** | **~5.5h** |
