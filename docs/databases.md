# Database Schema — Iskenda Academy

> PostgreSQL 16+ · UUID primário · Valores em centavos (INTEGER) · Timestamptz

---

## Regras Globais

| Regra | Padrão |
|-------|--------|
| **IDs** | UUID v4 (`gen_random_uuid()`) |
| **Timestamps** | `TIMESTAMPTZ NOT NULL DEFAULT NOW()` |
| **Soft Delete** | `deleted_at TIMESTAMPTZ NULL` |
| **Moeda** | `INTEGER` em centavos (ex: 10000 = 100,00 AOA) |
| **Enums** | `CREATE TYPE` PostgreSQL |
| **Status code** | `VARCHAR(40)` quando não enum |
| **Slug** | `VARCHAR(200) UNIQUE` |
| **Hash** | `VARCHAR(64)` (SHA-256 hex) |

---

## ENUMs PostgreSQL

```sql
-- ============================================================
-- UTILIZADORES
-- ============================================================
CREATE TYPE user_role AS ENUM ('student', 'instructor', 'admin');

-- ============================================================
-- CURSOS
-- ============================================================
CREATE TYPE course_modality AS ENUM ('recorded', 'live');
CREATE TYPE course_status AS ENUM ('draft', 'published', 'archived');
CREATE TYPE lesson_type AS ENUM ('video', 'pdf', 'live');
CREATE TYPE live_platform AS ENUM ('zoom', 'meet');

-- ============================================================
-- MATRÍCULAS
-- ============================================================
CREATE TYPE enrollment_status AS ENUM ('active', 'completed', 'cancelled');

-- ============================================================
-- CARTEIRA
-- ============================================================
CREATE TYPE tx_type AS ENUM (
    'credit_purchase', 'course_payment', 'admin_adjustment', 'refund'
);
CREATE TYPE tx_direction AS ENUM ('in', 'out');
CREATE TYPE tx_status AS ENUM ('pending', 'approved', 'rejected', 'cancelled');
CREATE TYPE voucher_status AS ENUM ('pending', 'approved', 'rejected');
CREATE TYPE payment_method AS ENUM ('wallet', 'voucher', 'gateway');
CREATE TYPE order_status AS ENUM ('pending', 'paid', 'failed', 'refunded');

-- ============================================================
-- SUPORTE
-- ============================================================
CREATE TYPE ticket_priority AS ENUM ('low', 'medium', 'high');
CREATE TYPE ticket_status AS ENUM ('open', 'in_progress', 'resolved', 'closed');
```

---

## Tabelas

### 1. `users`

Armazena todos os perfis: student, instructor, admin.

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `name` | `VARCHAR(255)` | `NOT NULL` | |
| `email` | `VARCHAR(255)` | `UNIQUE NOT NULL` | |
| `phone` | `VARCHAR(20)` | `NULL` | |
| `password_hash` | `VARCHAR(255)` | `NOT NULL` | |
| `role` | `user_role` | `NOT NULL` | `'student'` |
| `email_verified_at` | `TIMESTAMPTZ` | `NULL` | |
| `otp_code` | `VARCHAR(6)` | `NULL` | |
| `otp_expires_at` | `TIMESTAMPTZ` | `NULL` | |
| `avatar_url` | `TEXT` | `NULL` | |
| `is_active` | `BOOLEAN` | `NOT NULL` | `TRUE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `deleted_at` | `TIMESTAMPTZ` | `NULL` | |

**Índices:**
```sql
CREATE INDEX idx_users_role ON users (role) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_email ON users (email);
```

**Entidade PHP:** `app/Domain/Auth/Entities/User.php`

---

### 2. `password_reset_tokens`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `email` | `VARCHAR(255)` | `PK` | |
| `token` | `VARCHAR(255)` | `NOT NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NULL` | |

---

### 3. `categories`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `name` | `VARCHAR(120)` | `NOT NULL` | |
| `slug` | `VARCHAR(200)` | `UNIQUE NOT NULL` | |
| `description` | `TEXT` | `NULL` | |
| `icon_url` | `TEXT` | `NULL` | |
| `is_active` | `BOOLEAN` | `NOT NULL` | `TRUE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Entidade PHP:** `app/Domain/Course/Entities/Category.php`

---

### 4. `courses`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `instructor_id` | `UUID` | `FK → users(id)` | |
| `category_id` | `UUID` | `FK → categories(id)` | |
| `title` | `VARCHAR(255)` | `NOT NULL` | |
| `slug` | `VARCHAR(200)` | `UNIQUE NOT NULL` | |
| `description` | `TEXT` | `NULL` | |
| `thumbnail_url` | `TEXT` | `NULL` | |
| `modality` | `course_modality` | `NOT NULL` | |
| `price_cents` | `INTEGER` | `NOT NULL CHECK (price_cents >= 0)` | `0` |
| `status` | `course_status` | `NOT NULL` | `'draft'` |
| `total_duration_seconds` | `INTEGER` | `NOT NULL` | `0` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `deleted_at` | `TIMESTAMPTZ` | `NULL` | |

**FKs:**
```sql
ALTER TABLE courses ADD CONSTRAINT fk_courses_instructor
    FOREIGN KEY (instructor_id) REFERENCES users(id);
ALTER TABLE courses ADD CONSTRAINT fk_courses_category
    FOREIGN KEY (category_id) REFERENCES categories(id);
```

**Índices:**
```sql
CREATE INDEX idx_courses_status_cat ON courses (status, category_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_courses_instructor ON courses (instructor_id) WHERE deleted_at IS NULL;
```

**Entidade PHP:** `app/Domain/Course/Entities/Course.php`

---

### 5. `modules`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `course_id` | `UUID` | `FK → courses(id) NOT NULL` | |
| `title` | `VARCHAR(255)` | `NOT NULL` | |
| `position` | `INTEGER` | `NOT NULL` | `0` |
| `is_active` | `BOOLEAN` | `NOT NULL` | `TRUE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_modules_course ON modules (course_id, position);
```

**Entidade PHP:** `app/Domain/Course/Entities/Module.php`

---

### 6. `lessons`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `module_id` | `UUID` | `FK → modules(id) NOT NULL` | |
| `title` | `VARCHAR(255)` | `NOT NULL` | |
| `position` | `INTEGER` | `NOT NULL` | `0` |
| `type` | `lesson_type` | `NOT NULL` | |
| `video_url` | `TEXT` | `NULL` | |
| `pdf_url` | `TEXT` | `NULL` | |
| `duration_seconds` | `INTEGER` | `NOT NULL` | `0` |
| `is_free_preview` | `BOOLEAN` | `NOT NULL` | `FALSE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_lessons_module ON lessons (module_id, position);
```

**Entidade PHP:** `app/Domain/Course/Entities/Lesson.php`

---

### 7. `live_sessions`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `lesson_id` | `UUID` | `FK → lessons(id) NOT NULL` | |
| `instructor_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `scheduled_at` | `TIMESTAMPTZ` | `NOT NULL` | |
| `started_at` | `TIMESTAMPTZ` | `NULL` | |
| `ended_at` | `TIMESTAMPTZ` | `NULL` | |
| `raw_link` | `TEXT` | `NOT NULL` | |
| `masked_token` | `VARCHAR(64)` | `UNIQUE NOT NULL` | |
| `platform` | `live_platform` | `NOT NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_live_sessions_lesson ON live_sessions (lesson_id);
CREATE INDEX idx_live_sessions_scheduled ON live_sessions (scheduled_at) WHERE ended_at IS NULL;
```

**Entidade PHP:** `app/Domain/Course/Entities/LiveSession.php`

---

### 8. `enrollments`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `course_id` | `UUID` | `FK → courses(id) NOT NULL` | |
| `order_id` | `UUID` | `FK → orders(id) NULL` | |
| `status` | `enrollment_status` | `NOT NULL` | `'active'` |
| `enrolled_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `completed_at` | `TIMESTAMPTZ` | `NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**UK:** `UNIQUE (student_id, course_id)`

**Índices:**
```sql
CREATE INDEX idx_enrollment_student ON enrollments (student_id, status);
CREATE INDEX idx_enrollment_course ON enrollments (course_id, status);
```

**Entidade PHP:** `app/Domain/Enrollment/Entities/Enrollment.php`

---

### 9. `lesson_progress`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `enrollment_id` | `UUID` | `FK → enrollments(id) NOT NULL` | |
| `lesson_id` | `UUID` | `FK → lessons(id) NOT NULL` | |
| `watched_seconds` | `INTEGER` | `NOT NULL` | `0` |
| `last_position_seconds` | `INTEGER` | `NOT NULL` | `0` |
| `is_completed` | `BOOLEAN` | `NOT NULL` | `FALSE` |
| `completed_at` | `TIMESTAMPTZ` | `NULL` | |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**UK:** `UNIQUE (enrollment_id, lesson_id)`

**Índices:**
```sql
CREATE INDEX idx_lesson_progress_enr ON lesson_progress (enrollment_id);
```

---

### 10. `certificates`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `enrollment_id` | `UUID` | `FK → enrollments(id) UNIQUE NOT NULL` | |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `course_id` | `UUID` | `FK → courses(id) NOT NULL` | |
| `issued_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `verification_hash` | `VARCHAR(64)` | `UNIQUE NOT NULL` | |
| `pdf_url` | `TEXT` | `NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Sem `updated_at`** — certificado é imutável após emissão.

**Índices:**
```sql
CREATE UNIQUE INDEX idx_certificates_hash ON certificates (verification_hash);
CREATE INDEX idx_certificates_student ON certificates (student_id);
```

**Entidade PHP:** `app/Domain/Enrollment/Entities/Certificate.php`

---

### 11. `student_wallets`

Uma wallet por aluno (1:1).

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `student_id` | `UUID` | `FK → users(id) UNIQUE NOT NULL` | |
| `balance_cents` | `INTEGER` | `NOT NULL CHECK (balance_cents >= 0)` | `0` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Sem `created_at`** — a wallet é criada no registro do aluno.

**Entidade PHP:** `app/Domain/Wallet/Entities/Wallet.php`

---

### 12. `credit_packages`

Pacotes de créditos pré-definidos para compra.

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `name` | `VARCHAR(120)` | `NOT NULL` | |
| `description` | `TEXT` | `NULL` | |
| `price_cents` | `INTEGER` | `NOT NULL CHECK (price_cents > 0)` | |
| `credits_cents` | `INTEGER` | `NOT NULL CHECK (credits_cents > 0)` | |
| `bonus_percent` | `NUMERIC(5,2)` | `NULL` | `0` |
| `is_active` | `BOOLEAN` | `NOT NULL` | `TRUE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

---

### 13. `wallet_transactions`

**IMUTÁVEL** — sem `updated_at`, sem `deleted_at`. Append-only.

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `wallet_id` | `UUID` | `FK → student_wallets(id) NOT NULL` | |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `type` | `tx_type` | `NOT NULL` | |
| `direction` | `tx_direction` | `NOT NULL` | |
| `amount_cents` | `INTEGER` | `NOT NULL CHECK (amount_cents > 0)` | |
| `balance_before_cents` | `INTEGER` | `NOT NULL CHECK (balance_before_cents >= 0)` | |
| `balance_after_cents` | `INTEGER` | `NOT NULL CHECK (balance_after_cents >= 0)` | |
| `status` | `tx_status` | `NOT NULL` | `'pending'` |
| `reference_id` | `UUID` | `NULL` | |
| `reference_type` | `VARCHAR(80)` | `NULL` | |
| `description` | `TEXT` | `NULL` | |
| `approved_by` | `UUID` | `FK → users(id) NULL` | |
| `approved_at` | `TIMESTAMPTZ` | `NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_wallet_tx_student ON wallet_transactions (student_id, created_at DESC);
CREATE INDEX idx_wallet_tx_status ON wallet_transactions (status) WHERE status = 'pending';
CREATE INDEX idx_wallet_tx_wallet ON wallet_transactions (wallet_id, created_at DESC);
```

**Entidade PHP:** `app/Domain/Wallet/Entities/WalletTransaction.php`

---

### 14. `payment_vouchers`

Comprovativos de pagamento (upload do aluno).

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `transaction_id` | `UUID` | `FK → wallet_transactions(id) NOT NULL` | |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `file_url` | `TEXT` | `NOT NULL` | |
| `file_hash` | `VARCHAR(64)` | `NULL` | |
| `status` | `voucher_status` | `NOT NULL` | `'pending'` |
| `reviewed_by` | `UUID` | `FK → users(id) NULL` | |
| `reviewed_at` | `TIMESTAMPTZ` | `NULL` | |
| `rejection_reason` | `TEXT` | `NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_vouchers_status ON payment_vouchers (status) WHERE status = 'pending';
CREATE INDEX idx_vouchers_student ON payment_vouchers (student_id);
```

---

### 15. `carts`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `student_id` | `UUID` | `FK → users(id) UNIQUE NOT NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

---

### 16. `cart_items`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `cart_id` | `UUID` | `FK → carts(id) NOT NULL` | |
| `course_id` | `UUID` | `FK → courses(id) NOT NULL` | |
| `price_snapshot_cents` | `INTEGER` | `NOT NULL CHECK (price_snapshot_cents >= 0)` | |
| `added_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**UK:** `UNIQUE (cart_id, course_id)`

**Índices:**
```sql
CREATE INDEX idx_cart_items_cart ON cart_items (cart_id);
```

---

### 17. `orders`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `total_cents` | `INTEGER` | `NOT NULL CHECK (total_cents >= 0)` | |
| `payment_method` | `payment_method` | `NOT NULL` | |
| `status` | `order_status` | `NOT NULL` | `'pending'` |
| `paid_at` | `TIMESTAMPTZ` | `NULL` | |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_orders_student ON orders (student_id, created_at DESC);
CREATE INDEX idx_orders_status ON orders (status) WHERE status = 'pending';
```

---

### 18. `order_items`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `order_id` | `UUID` | `FK → orders(id) NOT NULL` | |
| `course_id` | `UUID` | `FK → courses(id) NOT NULL` | |
| `enrollment_id` | `UUID` | `FK → enrollments(id) NULL` | |
| `price_cents` | `INTEGER` | `NOT NULL CHECK (price_cents >= 0)` | |

**Índices:**
```sql
CREATE INDEX idx_order_items_order ON order_items (order_id);
```

---

### 19. `tickets`

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `student_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `assigned_to` | `UUID` | `FK → users(id) NULL` | |
| `subject` | `VARCHAR(200)` | `NOT NULL` | |
| `category` | `VARCHAR(80)` | `NULL` | |
| `priority` | `ticket_priority` | `NOT NULL` | `'low'` |
| `status` | `ticket_status` | `NOT NULL` | `'open'` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |
| `deleted_at` | `TIMESTAMPTZ` | `NULL` | |

**Índices:**
```sql
CREATE INDEX idx_tickets_student ON tickets (student_id, status);
CREATE INDEX idx_tickets_assigned ON tickets (assigned_to, status);
```

**Entidade PHP:** `app/Domain/Support/Entities/Ticket.php`

---

### 20. `ticket_messages`

**IMUTÁVEL** — sem `updated_at`, sem `deleted_at`.

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `UUID` | `PK` | `gen_random_uuid()` |
| `ticket_id` | `UUID` | `FK → tickets(id) NOT NULL` | |
| `author_id` | `UUID` | `FK → users(id) NOT NULL` | |
| `body` | `TEXT` | `NOT NULL` | |
| `is_internal` | `BOOLEAN` | `NOT NULL` | `FALSE` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_ticket_messages_ticket ON ticket_messages (ticket_id, created_at);
```

**Entidade PHP:** `app/Domain/Support/Entities/TicketMessage.php`

---

### 21. `audit_logs`

**APPEND-ONLY** — sem `updated_at`, sem `deleted_at`.
A role da aplicação (`iskenda_app`) NÃO tem permissão DELETE ou UPDATE nesta tabela.

| Coluna | Tipo | Constraints | Default |
|--------|------|-------------|---------|
| `id` | `BIGSERIAL` | `PK` | |
| `event_type` | `VARCHAR(100)` | `NOT NULL` | |
| `auditable_type` | `VARCHAR(80)` | `NOT NULL` | |
| `auditable_id` | `UUID` | `NOT NULL` | |
| `actor_id` | `UUID` | `NULL` | |
| `actor_role` | `VARCHAR(40)` | `NULL` | |
| `actor_ip` | `INET` | `NULL` | |
| `payload` | `JSONB` | `NOT NULL` | |
| `previous_state` | `JSONB` | `NULL` | |
| `new_state` | `JSONB` | `NULL` | |
| `occurred_at` | `TIMESTAMPTZ` | `NOT NULL` | `NOW()` |

**Índices:**
```sql
CREATE INDEX idx_audit_auditable ON audit_logs (auditable_type, auditable_id);
CREATE INDEX idx_audit_actor ON audit_logs (actor_id, occurred_at DESC);
CREATE INDEX idx_audit_event ON audit_logs (event_type, occurred_at DESC);
```

---

## Índices Obrigatórios (Performance)

```sql
-- Carteira
CREATE INDEX idx_wallet_tx_student   ON wallet_transactions (student_id, created_at DESC);
CREATE INDEX idx_wallet_tx_status    ON wallet_transactions (status) WHERE status = 'pending';
CREATE INDEX idx_wallet_tx_wallet    ON wallet_transactions (wallet_id, created_at DESC);

-- Matrículas e Progresso
CREATE INDEX idx_enrollment_student  ON enrollments (student_id, status);
CREATE INDEX idx_lesson_progress_enr ON lesson_progress (enrollment_id);

-- Tickets
CREATE INDEX idx_tickets_student     ON tickets (student_id, status);
CREATE INDEX idx_tickets_assigned    ON tickets (assigned_to, status);

-- Auditoria
CREATE INDEX idx_audit_auditable     ON audit_logs (auditable_type, auditable_id);
CREATE INDEX idx_audit_actor         ON audit_logs (actor_id, occurred_at DESC);
CREATE INDEX idx_audit_event         ON audit_logs (event_type, occurred_at DESC);

-- Cursos
CREATE INDEX idx_courses_status_cat  ON courses (status, category_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_courses_instructor  ON courses (instructor_id) WHERE deleted_at IS NULL;

-- Lives
CREATE INDEX idx_live_sessions_scheduled ON live_sessions (scheduled_at) WHERE ended_at IS NULL;

-- Vouchers pendentes
CREATE INDEX idx_vouchers_status ON payment_vouchers (status) WHERE status = 'pending';

-- Pedidos pendentes
CREATE INDEX idx_orders_status ON orders (status) WHERE status = 'pending';
```

---

## Diagrama de Relacionamentos (Entidades)

```
users (student)
  ├── 1:1 → student_wallets
  ├── 1:N → enrollments
  ├── 1:N → certificates
  ├── 1:N → orders
  ├── 1:N → tickets
  ├── 1:N → wallet_transactions
  ├── 1:N → payment_vouchers
  └── 1:1 → cart

users (instructor)
  └── 1:N → courses

categories
  └── 1:N → courses

courses
  ├── 1:N → modules → 1:N → lessons → 1:N → live_sessions
  ├── 1:N → enrollments → 1:N → lesson_progress
  ├── 1:N → certificates
  ├── 1:N → cart_items
  └── 1:N → order_items

orders
  ├── 1:N → order_items
  └── 1:N → enrollments

student_wallets
  └── 1:N → wallet_transactions → 1:1 → payment_vouchers

carts
  └── 1:N → cart_items

tickets
  └── 1:N → ticket_messages
```

---

## Resumo de Tabelas

| # | Tabela | Tipo PK | Imutável | Soft Delete |
|---|--------|---------|----------|-------------|
| 1 | `users` | UUID | | ✓ |
| 2 | `password_reset_tokens` | VARCHAR | | |
| 3 | `categories` | UUID | | |
| 4 | `courses` | UUID | | ✓ |
| 5 | `modules` | UUID | | |
| 6 | `lessons` | UUID | | |
| 7 | `live_sessions` | UUID | | |
| 8 | `enrollments` | UUID | | |
| 9 | `lesson_progress` | UUID | | |
| 10 | `certificates` | UUID | ✓ | |
| 11 | `student_wallets` | UUID | | |
| 12 | `credit_packages` | UUID | | |
| 13 | `wallet_transactions` | UUID | ✓ | |
| 14 | `payment_vouchers` | UUID | | |
| 15 | `carts` | UUID | | |
| 16 | `cart_items` | UUID | | |
| 17 | `orders` | UUID | | |
| 18 | `order_items` | UUID | | |
| 19 | `tickets` | UUID | | ✓ |
| 20 | `ticket_messages` | UUID | ✓ | |
| 21 | `audit_logs` | BIGSERIAL | ✓ | |

---

## Estado Atual vs. Meta

**Já existe (default Laravel):**
- `users` — mas precisa ser **recriada** (UUID, role enum, phone, OTP, is_active)
- `password_reset_tokens` — manter como está
- `sessions` — manter como está (não listado na spec, mas necessário para web)

**A criar (18 migrations novas):**
`categories`, `courses`, `modules`, `lessons`, `live_sessions`, `enrollments`, `lesson_progress`, `certificates`, `student_wallets`, `credit_packages`, `wallet_transactions`, `payment_vouchers`, `carts`, `cart_items`, `orders`, `order_items`, `tickets`, `ticket_messages`, `audit_logs`

---

## Notas Técnicas

1. **UUID vs Auto-Increment:** A migração padrão do Laravel usa `$table->id()` (auto-increment). Todas as migrations novas devem usar `$table->uuid('id')->primary();` e `$table->foreignUuid('...')->constrained();`

2. **Enums no Laravel:** Usar `$table->string('role')->default('student')` com um cast `\App\Enum\UserRole` no Model + `Schema::createType()` raw SQL para o PostgreSQL ENUM real.

3. **`wallet_transactions` e `ticket_messages`:** São **IMUTÁVEIS** — não ter `updated_at`. No Laravel, usar `public $timestamps = false;` no Model Eloquent correspondente (se usado), ou melhor, usar `DB::insert()` cru para garantir.

4. **`audit_logs`:** Usar `$table->bigIncrements('id')` + inserção com `DB::statement('INSERT INTO audit_logs ...')` direto. A role do banco `iskenda_app` não deve ter permissão UPDATE ou DELETE nesta tabela.
