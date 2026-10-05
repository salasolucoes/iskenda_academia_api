# Regras de Negócio — Iskenda Academy

> Domain-Driven Design · Camada Domain pura (sem Laravel) · PHP 8.4

---

## Índice

1. [Arquitetura e Camadas](#1-arquitetura-e-camadas)
2. [Value Objects](#2-value-objects)
3. [Entidades](#3-entidades)
4. [Domain Services](#4-domain-services)
5. [Use Cases (Application)](#5-use-cases-application)
6. [Regras de Carteira e Créditos](#6-regras-de-carteira-e-créditos)
7. [Regras de Matrículas e Certificação](#7-regras-de-matrículas-e-certificação)
8. [Regras de Cursos e Conteúdo](#8-regras-de-cursos-e-conteúdo)
9. [Regras de Links de Transmissão](#9-regras-de-links-de-transmissão)
10. [Regras de Suporte (Tickets)](#10-regras-de-suporte-tickets)
11. [Regras de Auditoria](#11-regras-de-auditoria)
12. [Regras de Notificações em Tempo Real](#12-regras-de-notificações-em-tempo-real)
13. [Restrições Técnicas Globais](#13-restrições-técnicas-globais)
14. [Padrão de Respostas de Erro](#14-padrão-de-respostas-de-erro)

---

## 1. Arquitetura e Camadas

```
┌─────────────────────────────────────────────────────┐
│                   Http (Controllers)                 │
│   Recebe request, delega para UseCase, retorna JSON  │
├─────────────────────────────────────────────────────┤
│              Application (Use Cases)                 │
│   Orquestra o fluxo: repositórios + domain services  │
│   Gerencia transações DB, DTOs de entrada/saída      │
├─────────────────────────────────────────────────────┤
│               Domain (regras de negócio)              │
│   Entities, ValueObjects, Exceptions, Interfaces     │
│   ⚠️ NÃO importa NADA do Laravel (Illuminate/*)       │
├─────────────────────────────────────────────────────┤
│           Infrastructure (Persistence, Services)      │
│   Eloquent Models, Repository implementations        │
│   OtpService, StorageService, AuditLogger            │
│   Broadcasting (Laravel Events + Reverb)             │
└─────────────────────────────────────────────────────┘
```

### Regra fundamental
A camada `app/Domain/` **não pode importar** nenhuma classe dos namespaces `Illuminate` ou `Laravel`. Apenas PHP puro, interfaces e classes do próprio domínio.

---

## 2. Value Objects

### 2.1 `Money`

```php
namespace App\Domain\Wallet\ValueObjects;

class Money
{
    public function __construct(private int $cents)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative');
        }
    }

    public static function fromCents(int $cents): self
    public function cents(): int
    public function add(Money $other): self           // retorna novo objeto
    public function subtract(Money $other): self      // retorna novo objeto
    public function isGreaterThanOrEqual(Money $other): bool
    public function equals(Money $other): bool
}
```

**Regras:**
- `$cents` deve ser ≥ 0 → senão `InvalidArgumentException`
- Operações (`add`, `subtract`) retornam **novas instâncias** (imutável)
- `subtract` lança `InvalidArgumentException` se resultado < 0 (validar antes com `isGreaterThanOrEqual`)
- Sem dependências externas — PHP puro

### 2.2 `TransactionType`

```php
enum TransactionType: string
{
    case CreditPurchase  = 'credit_purchase';
    case CoursePayment   = 'course_payment';
    case AdminAdjustment = 'admin_adjustment';
    case Refund          = 'refund';
}
```

### 2.3 `Direction`

```php
enum Direction: string
{
    case In  = 'in';   // entrada (crédito)
    case Out = 'out';  // saída (débito)
}
```

### 2.4 `EnrollmentStatus`

```php
enum EnrollmentStatus: string
{
    case Active    = 'active';     // matriculado, cursando
    case Completed = 'completed';  // concluiu 100%
    case Cancelled = 'cancelled';  // cancelado pelo admin ou aluno
}
```

**Regras:**
- `cancelled` só pode ser atribuído por admin
- `active` → `completed` (transição automática quando 100% das lições concluídas)
- `active` → `cancelled` (transição manual, apenas admin)
- Não é possível reverter `completed` ou `cancelled`

### 2.5 `VerificationHash`

```php readonly
class VerificationHash
{
    public function __construct(private string $hash)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new \InvalidArgumentException('Invalid SHA-256 hash');
        }
    }

    public static function generate(): self  // SHA-256 de random_bytes(32)
    public function value(): string
}
```

### 2.6 `Modality`

```php
enum Modality: string
{
    case Recorded = 'recorded';
    case Live     = 'live';
}
```

### 2.7 `CourseStatus`

```php
enum CourseStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';
}
```

**Regras:**
- `draft` → `published` (apenas instructor ou admin)
- `published` → `archived` (apenas admin, quando curso não está mais disponível)
- Cursos `draft` e `archived` **não aparecem** no catálogo público
- Cursos `archived` mantêm matrículas ativas (alunos continuam com acesso)

### 2.8 `Priority`

```php
enum Priority: string
{
    case Low   = 'low';
    case Medium = 'medium';
    case High  = 'high';
}
```

### 2.9 `TicketStatus`

```php
enum TicketStatus: string
{
    case Open       = 'open';
    case InProgress = 'in_progress';
    case Resolved   = 'resolved';
    case Closed     = 'closed';
}
```

**Regras de transição:**
```
open ───→ in_progress ───→ resolved ───→ closed
  ↑                           │
  └───────────────────────────┘ (reabrir apenas se foi resolvido)
```
- `closed` é terminal — não pode ser reaberto
- Apenas admin pode transicionar para `resolved` ou `closed`
- Sistema auto-atribui `in_progress` quando admin responde pela primeira vez

---

## 3. Entidades

### 3.1 `Wallet`

```php
class Wallet
{
    public function __construct(
        private string     $id,          // UUID
        private string     $studentId,   // UUID
        private Money      $balance,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public function id(): string
    public function studentId(): string
    public function balance(): Money
    public function updatedAt(): \DateTimeImmutable
}
```

**Regras:**
- `balance` nunca pode ser negativo (enforced no DB via `CHECK (balance_cents >= 0)`)
- 1 wallet por student (UNIQUE na FK)
- Wallet é criada automaticamente no registro do aluno

### 3.2 `WalletTransaction`

```php
class WalletTransaction
{
    public function __construct(
        private string                        $id,
        private string                        $walletId,
        private string                        $studentId,
        private TransactionType               $type,
        private Direction                     $direction,
        private Money                         $amount,
        private Money                         $balanceBefore,
        private Money                         $balanceAfter,
        private TransactionStatus             $status,
        private ?string                       $referenceId,
        private ?string                       $referenceType,
        private string                        $description,
        private ?string                       $approvedBy,
        private ?\DateTimeImmutable           $approvedAt,
        private \DateTimeImmutable            $createdAt,
    ) {}
}
```

**Regras de imutabilidade:**
- `$amount > 0` (sempre positivo, direction define entrada/saída)
- `$balanceAfter = $balanceBefore ± $amount` (validação na criação)
- Sem `updatedAt` — registro imutável após criado
- `status` inicial = `pending`, muda para `approved`/`rejected`/`cancelled`

### 3.3 `Enrollment`

```php
class Enrollment
{
    public function __construct(
        private string             $id,
        private string             $studentId,
        private string             $courseId,
        private ?string            $orderId,
        private EnrollmentStatus   $status,
        private \DateTimeImmutable $enrolledAt,
        private ?\DateTimeImmutable $completedAt,
    ) {}

    public function complete(): void
    {
        $this->status = EnrollmentStatus::Completed;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status === EnrollmentStatus::Completed) {
            throw new \DomainException('Cannot cancel a completed enrollment');
        }
        $this->status = EnrollmentStatus::Cancelled;
    }
}
```

### 3.4 `Certificate`

```php
class Certificate
{
    public function __construct(
        private string             $id,
        private string             $enrollmentId,
        private string             $studentId,
        private string             $courseId,
        private \DateTimeImmutable $issuedAt,
        private VerificationHash   $verificationHash,
        private ?string            $pdfUrl,
    ) {}
}
```

**Regras:**
- Apenas 1 certificado por enrollment (UK na FK)
- `verificationHash` é SHA-256 único
- Imutável após emissão — sem setters

### 3.5 `Ticket`

```php
class Ticket
{
    public function __construct(
        private string      $id,
        private string      $studentId,
        private ?string     $assignedTo,
        private string      $subject,
        private ?string     $category,
        private Priority    $priority,
        private TicketStatus $status,
    ) {}

    public function assignTo(string $userId): void
    public function escalate(): void           // priority → high
    public function resolve(): void            // status → resolved
    public function close(): void              // status → closed

    public function isUrgent(): bool           // priority === high
}
```

### 3.6 `TicketMessage`

```php
class TicketMessage
{
    public function __construct(
        private string             $id,
        private string             $ticketId,
        private string             $authorId,
        private string             $body,
        private bool               $isInternal,
        private \DateTimeImmutable $createdAt,
    ) {}
}
```

**Regras:**
- `isInternal = true` → visível apenas para admin/instructor (não para o student)
- Imutável — sem updatedAt
- `body` não pode ser vazio

---

## 4. Domain Services

### 4.1 `WalletDomainService`

```php
class WalletDomainService
{
    /**
     * @throws InsufficientBalanceException
     */
    public function debit(Wallet $wallet, Money $amount, string $description): WalletTransaction
    {
        if (!$wallet->balance()->isGreaterThanOrEqual($amount)) {
            throw new InsufficientBalanceException($wallet->balance(), $amount);
        }

        $balanceBefore = $wallet->balance();
        $balanceAfter  = $balanceBefore->subtract($amount);

        return new WalletTransaction(
            id: Uuid::v4(),
            walletId: $wallet->id(),
            studentId: $wallet->studentId(),
            type: TransactionType::CoursePayment,
            direction: Direction::Out,
            amount: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            status: TransactionStatus::Approved,
            referenceId: null,
            referenceType: null,
            description: $description,
            approvedBy: null,
            approvedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function credit(Wallet $wallet, Money $amount, string $description): WalletTransaction
    {
        $balanceBefore = $wallet->balance();
        $balanceAfter  = $balanceBefore->add($amount);

        return new WalletTransaction(
            id: Uuid::v4(),
            walletId: $wallet->id(),
            studentId: $wallet->studentId(),
            type: TransactionType::CreditPurchase,
            direction: Direction::In,
            amount: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            status: TransactionStatus::Approved,
            referenceId: null,
            referenceType: null,
            description: $description,
            approvedBy: null,
            approvedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );
    }
}
```

**Regras:**
- `debit()` lança `InsufficientBalanceException` se saldo insuficiente
- `credit()` sempre bem-sucedido (sem validação de saldo)
- Nenhum metodo **persiste** nada — o valor de retorno e a entidade `WalletTransaction`
- `credit()` não tem limite máximo — controle externo (UseCase decide se aceita)

### 4.2 `EnrollmentDomainService`

```php
class EnrollmentDomainService
{
    /**
     * @throws AlreadyEnrolledException
     */
    public function canEnroll(string $studentId, string $courseId): void
    {
        // Validação delegada ao repositório
        // UseCase chama enrollmentRepo->exists() antes de criar
    }

    public function isCourseComplete(array $lessonProgress): bool
    {
        // Retorna true se todas as lessons do curso têm is_completed = true
        return !empty($lessonProgress)
            && count(array_filter($lessonProgress, fn($p) => !$p->isCompleted)) === 0;
    }
}
```

### 4.3 `CourseDomainService`

```php
class CourseDomainService
{
    public function calculateTotalDuration(array $lessons): int
    {
        return array_sum(array_map(fn($l) => $l->durationSeconds(), $lessons));
    }

    public function canPublish(Course $course): bool
    {
        // Um curso precisa ter pelo menos 1 módulo com pelo menos 1 lesson
        // e ter instructor atribuído
        return $course->moduleCount() > 0
            && $course->instructorId() !== null;
    }
}
```

### 4.4 `TicketDomainService`

```php
class TicketDomainService
{
    public function shouldEscalate(Ticket $ticket, int $messageCount, \DateTimeImmutable $lastActivity): void
    {
        // Escalar para high se:
        // - Mais de 3 mensagens sem resolução em 24h
        // - OU o aluno explicitamente pediu escalação
    }
}
```

---

## 5. Use Cases (Application)

### 5.1 `CheckoutWithWalletUseCase`

**Input (DTO):**
```php
class CheckoutInput
{
    public function __construct(
        public string   $studentId,
        public array    $courseIds,   // UUID[]
        public ActorContext $actorContext,
    ) {}
}
```

**Fluxo completo (dentro de `DB::transaction`):**
```
1. BUSCAR wallet com FOR UPDATE
   └─ walletRepo->findForUpdateByStudent(studentId)

2. VALIDAR cada curso
   ├─ courseRepo->findPublished(courseId)     → lança CourseNotFoundException se não encontrado
   └─ enrollmentRepo->exists(studentId, courseId) → lança AlreadyEnrolledException se true

3. CALCULAR total
   └─ Money::fromCents(sum dos price_cents dos courses)

4. DEBITAR (domínio puro)
   └─ walletDomainService->debit(wallet, total, 'Compra de curso')
       └─ lança InsufficientBalanceException se saldo < total

5. PERSISTIR
   ├─ walletRepo->updateBalance(wallet.id, transaction.balanceAfter)
   ├─ transactionRepo->save(transaction)
   ├─ orderRepo->create(studentId, total, 'wallet')
   └─ Para cada curso: enrollmentRepo->create(studentId, courseId, order.id)

6. AUDITAR
   └─ auditLogger->log('wallet.debit.checkout', order, actorContext)

7. LIMPAR carrinho
   └─ cartRepo->clearByStudent(studentId)

8. RETURN Order
```

**Regras de concorrência:**
- Tudo em `DB::transaction` com nível `REPEATABLE READ` ou `SERIALIZABLE`
- `SELECT ... FOR UPDATE` na wallet para evitar race conditions
- Se duas requisições concorrentes tentarem comprar o mesmo curso, a segunda lança `AlreadyEnrolledException`

### 5.2 `ApproveVoucherUseCase`

**Input (DTO):**
```php
class ApprovalInput
{
    public function __construct(
        public string $voucherId,
        public string $adminId,
        public ActorContext $actorContext,
    ) {}
}
```

**Fluxo:**
```
1. voucherRepo->findPendingOrFail(voucherId)   → lança se não existe ou já foi processado
2. transactionRepo->findOrFail(voucher.transactionId)
3. walletRepo->findForUpdateByStudent(transaction.studentId)
4. walletDomainService->credit(wallet, Money::fromCents(transaction.amountCents), 'Aprovação de comprovativo')
5. walletRepo->updateBalance(wallet.id, creditTx.balanceAfter)
6. transactionRepo->markApproved(transaction.id, adminId)
7. voucherRepo->markApproved(voucher.id, adminId)
8. auditLogger->log('voucher.approved', voucher, actorContext)
9. event(new VoucherApprovedEvent(studentId, creditTx.balanceAfter))
```

### 5.3 `RejectVoucherUseCase`

**Fluxo:**
```
1. voucherRepo->findPendingOrFail(voucherId)
2. voucherRepo->markRejected(voucher.id, adminId, reason)
3. transactionRepo->markCancelled(transaction.id, adminId)
4. auditLogger->log('voucher.rejected', voucher, actorContext)
5. event(new VoucherRejectedEvent(studentId, reason))
```

### 5.4 `AdminAdjustBalanceUseCase`

```php
class AdminAdjustBalanceInput
{
    public function __construct(
        public string  $studentId,
        public int     $amountCents,    // positivo = crédito, negativo = débito
        public string  $reason,
        public string  $adminId,
        public ActorContext $actorContext,
    ) {}
}
```

**Fluxo:**
```
1. walletRepo->findForUpdateByStudent(studentId)
2. Se amountCents > 0  → walletDomainService->credit(...)
   Se amountCents < 0  → walletDomainService->debit(...)  (pode lançar InsufficientBalanceException)
3. walletRepo->updateBalance(...)
4. transactionRepo->save(transaction)  // type = admin_adjustment
5. auditLogger->log('wallet.credit.admin', wallet, actorContext)
```

### 5.5 `RegisterStudentUseCase`

```
1. Validar email e phone únicos
2. Criar User (role = student, password_hash bcrypt custo 12)
3. Criar Wallet (balance = 0)
4. Gerar OTP e enviar (email ou SMS)
5. auditLogger->log('auth.register', user, actorContext)
```

### 5.6 `VerifyOtpUseCase`

```
1. Buscar user por email
2. Validar otp_code e otp_expires_at > now()
3. Marcar email_verified_at
4. Limpar otp_code e otp_expires_at
5. auditLogger->log('auth.otp.verified', user, actorContext)
```

### 5.7 `IssueEnrollmentUseCase`

```
1. Verificar se pagamento foi confirmado (order.status === 'paid')
2. Verificar se já não existe enrollment (evitar duplicidade)
3. Criar Enrollment com status = active
4. auditLogger->log('enrollment.created', enrollment, actorContext)
5. event(new NewStudentEnrolledEvent(instructorId))
6. event(new CourseAccessGrantedEvent(studentId))
```

### 5.8 `IssueCertificateUseCase`

```
1. Verificar enrollment.status === 'completed' (todas as lições concluídas)
2. Verificar se já não existe certificado (evitar duplicidade)
3. Gerar VerificationHash (SHA-256)
4. Gerar PDF do certificado e fazer upload para object storage
5. Salvar Certificate com pdfUrl
6. auditLogger->log('certificate.issued', certificate, actorContext)
```

### 5.9 `OpenTicketUseCase`

```
1. Validar subject não vazio (max 200 chars)
2. Criar Ticket com status = open
3. Se priority = high → event(new NewUrgentTicketEvent)
4. auditLogger->log('ticket.opened', ticket, actorContext)
```

### 5.10 `ReplyTicketUseCase`

```
1. Verificar ticket.status !== 'closed'
2. Criar TicketMessage
3. Se autor é admin → auto-atribuir ticket e mudar para in_progress
4. Se autor é student e ticket.status = 'resolved' → reabrir (open)
5. event(new TicketRepliedEvent(studentId))
```

---

## 6. Regras de Carteira e Créditos

### 6.1 Criação da Wallet
- Toda wallet começa com `balance_cents = 0`
- Criada automaticamente no registro do aluno (RegisterStudentUseCase)
- `student_id` é UNIQUE — 1 wallet por aluno

### 6.2 Upload de Comprovativo
- Apenas `image/jpeg`, `image/png`, `application/pdf`
- Máximo 5MB
- Validar MIME real (não extensão do arquivo)
- Armazenar no object storage (MinIO/Bunny), nunca no disco local
- Calcular SHA-256 do arquivo e salvar em `file_hash`
- Criar `wallet_transaction` com status `pending`, type `credit_purchase`
- Criar `payment_voucher` com status `pending`
- `auditLogger->log('voucher.uploaded', voucher, actorContext)`
- `event(new NewPurchaseIntentEvent)` para notificar admin

### 6.3 Aprovação de Comprovativo
- Apenas admin pode aprovar
- Voucher deve estar `pending`
- Credita o valor na wallet do aluno
- Marca transação como `approved`
- Aluno recebe notificação em tempo real

### 6.4 Rejeição de Comprovativo
- Apenas admin pode rejeitar
- Deve fornecer `rejection_reason`
- Marca transação como `cancelled`
- Nenhum crédito é adicionado

### 6.5 Checkout com Carteira
- Bloqueia a wallet com `SELECT FOR UPDATE`
- Verifica saldo suficiente
- Cria `order` com `payment_method = 'wallet'`
- Cria `enrollment` para cada curso
- Limpa o carrinho
- Tudo na mesma transação DB

### 6.6 Concorrência (Race Conditions)
```sql
BEGIN;
SELECT * FROM student_wallets WHERE id = ? FOR UPDATE;
-- validações e operações --
UPDATE student_wallets SET balance_cents = ? WHERE id = ?;
INSERT INTO wallet_transactions (...);
INSERT INTO orders (...);
INSERT INTO enrollments (...);
COMMIT;
```
- `FOR UPDATE` garante que duas requisições simultâneas não causem saldo negativo
- Se a primeira falhar (saldo insuficiente), a segunda pode prosseguir com o saldo correto

---

## 7. Regras de Matrículas e Certificação

### 7.1 Criação de Matrícula
- Gerada automaticamente após confirmação de pagamento
- `enrolled_at` = momento da criação
- Status inicial = `active`

### 7.2 Progresso de Aulas
- Atualizado via `POST /classroom/{lessonId}/progress`
- Campos: `watched_seconds`, `last_position_seconds`
- `is_completed = true` quando `watched_seconds >= lesson.duration_seconds * 0.9` (90%)
- Se `is_completed` mudar de `false` para `true`, verificar se o curso inteiro foi concluído

### 7.3 Conclusão de Curso
- Quando **todas as lições** do curso têm `is_completed = true`
- Enrollment muda para `completed`
- `completed_at` é registrado
- `auditLogger->log('enrollment.completed', enrollment, actorContext)`

### 7.4 Emissão de Certificado
- Só pode ser emitido se `enrollment.status === 'completed'`
- `verification_hash` = SHA-256 (único para cada certificado)
- PDF gerado e armazenado no object storage
- Rota pública de verificação: `GET /certificates/{hash}/verify`
- Não é possível emitir mais de 1 certificado por enrollment

---

## 8. Regras de Cursos e Conteúdo

### 8.1 Criação de Curso
- Apenas instructor ou admin podem criar
- Status inicial = `draft`
- `price_cents` em centavos (0 = gratuito)

### 8.2 Publicação
- `CourseDomainService->canPublish()`:
  - Deve ter pelo menos 1 módulo
  - Deve ter pelo menos 1 lesson por módulo
  - Deve ter instructor atribuído
- Apenas o instructor dono do curso ou admin podem publicar

### 8.3 Arquivação
- Apenas admin pode arquivar
- Alunos já matriculados continuam com acesso
- Cursos arquivados não aparecem no catálogo

### 8.4 Módulos e Lições
- `position` define a ordem de exibição
- `is_free_preview` = true → lição pode ser assistida sem matrícula
- Lições do tipo `live` têm `live_sessions` associadas

---

## 9. Regras de Links de Transmissão

### 9.1 Cadastro (Admin)
- Admin cadastra o `raw_link` (Zoom/Meet)
- Sistema gera `masked_token` (HMAC-SHA256) único
- `raw_link` **nunca** é exposto em respostas JSON

### 9.2 Acesso do Aluno (Token Assinado)
```
POST /classroom/{liveSessionId}/join
  ↓
ValidateLiveSessionToken middleware:
  1. Token HMAC-SHA256 gerado no login
  2. Verifica token pertence ao student_id da sessão
  3. Verifica live_session está ativa (scheduled_at ≤ NOW ≤ ended_at)
  4. Registra acesso em audit_logs (live_link.accessed)
  5. Redirect 302 para raw_link (nunca JSON)
  6. Token de uso único — inválido após primeiro uso
  7. TTL do token: 30 minutos
```

### 9.3 Proteções
- `raw_link` nunca em resposta JSON — apenas redirect 302
- Token com HMAC-SHA256 assinado
- Uso único + TTL 30 min
- Auditoria de cada acesso

---

## 10. Regras de Suporte (Tickets)

### 10.1 Abertura
- Apenas student pode abrir ticket
- Subject obrigatório (max 200 chars)
- Se `priority = high` → notificação urgente para admin via WebSocket

### 10.2 Respostas
- `is_internal = true` → visível apenas para admin/instructor
- Se admin responde → ticket vai para `in_progress`
- Se student responde a um `resolved` → ticket reabre como `open`

### 10.3 Escalação
- Automática: mais de 3 mensagens em 24h sem resolução → priority = high
- Manual: student pode solicitar escalação

### 10.4 Resolução e Fechamento
- Apenas admin pode resolver (`resolved`) ou fechar (`closed`)
- `closed` é terminal — não pode ser reaberto

---

## 11. Regras de Auditoria

### 11.1 Contrato

```php
interface AuditLoggerInterface
{
    public function log(
        string      $eventType,
        mixed       $auditable,
        ActorContext $actor,
        ?array      $previousState = null,
        ?array      $newState      = null
    ): void;
}
```

### 11.2 Eventos Obrigatórios

| Evento | Disparado por | Payload |
|--------|---------------|---------|
| `auth.register` | RegisterStudentUseCase | user.id, email, role |
| `auth.otp.verified` | VerifyOtpUseCase | user.id |
| `auth.login` | LoginController (middleware) | user.id, IP |
| `auth.login.failed` | LoginController (middleware) | email, IP |
| `wallet.credit.admin` | AdminAdjustBalanceUseCase | wallet.id, amount, admin.id |
| `wallet.debit.checkout` | CheckoutWithWalletUseCase | order.id, total, cursos |
| `voucher.uploaded` | UploadVoucherUseCase | voucher.id, amount |
| `voucher.approved` | ApproveVoucherUseCase | voucher.id, admin.id |
| `voucher.rejected` | RejectVoucherUseCase | voucher.id, reason |
| `enrollment.created` | IssueEnrollmentUseCase | enrollment.id, course.id |
| `enrollment.completed` | LessonProgressService | enrollment.id |
| `certificate.issued` | IssueCertificateUseCase | certificate.id, hash |
| `live_link.accessed` | ValidateLiveSessionToken | live_session.id, IP |
| `ticket.opened` | OpenTicketUseCase | ticket.id, priority |
| `ticket.priority.escalated` | TicketDomainService | ticket.id |
| `ticket.resolved` | ReplyTicketUseCase (admin) | ticket.id |

### 11.3 Implementação
- `audit_logs` é **append-only** — INSERT direto com `DB::statement()`, sem Eloquent
- Escrita **síncrona** (não usar filas) para garantir rastreabilidade
- Role `iskenda_app` no PostgreSQL **não tem** permissão DELETE ou UPDATE em `audit_logs`
- NUNCA criar migration que adicione UPDATE ou DELETE nesta tabela

---

## 12. Regras de Notificações em Tempo Real

### 12.1 Canais Reverb

| Canal | Quem escuta | Eventos |
|-------|-------------|---------|
| `private-admin` | Administradores | `NewPurchaseIntentEvent`, `NewUrgentTicketEvent` |
| `private-instructor.{id}` | Instructor específico | `NewStudentEnrolledEvent`, `LiveStartingSoonEvent` |
| `private-user.{id}` | Aluno específico | `VoucherApprovedEvent`, `VoucherRejectedEvent`, `CourseAccessGrantedEvent`, `TicketRepliedEvent` |

### 12.2 Eventos

| Evento | Payload | Quando |
|--------|---------|--------|
| `NewPurchaseIntentEvent` | `{ studentId, amountCents, voucherId }` | Upload de comprovativo |
| `NewUrgentTicketEvent` | `{ ticketId, studentName, subject }` | Ticket com priority=high |
| `NewStudentEnrolledEvent` | `{ studentId, courseTitle, enrollmentId }` | Nova matrícula |
| `LiveStartingSoonEvent` | `{ lessonTitle, scheduledAt, courseTitle }` | 15 min antes da live |
| `VoucherApprovedEvent` | `{ balanceAfterCents }` | Comprovativo aprovado |
| `VoucherRejectedEvent` | `{ rejectionReason }` | Comprovativo rejeitado |
| `CourseAccessGrantedEvent` | `{ courseTitle, enrollmentId }` | Matrícula confirmada |
| `TicketRepliedEvent` | `{ ticketId, preview, authorName }` | Nova resposta no ticket |

### 12.3 Jobs Agendados

| Job | Periodicidade | Ação |
|-----|--------------|------|
| `LiveStartingSoonJob` | A cada 1 minuto | Buscar lives com `scheduled_at` entre NOW e NOW+15min; notificar instructor e alunos matriculados |
| `ExpiredCartCleanupJob` | Diário (00:00) | Remover carrinhos com `updated_at < NOW() - 7 dias` |

---

## 13. Restrições Técnicas Globais

### 13.1 Segurança

| Regra | Onde |
|-------|------|
| Senhas com bcrypt, custo mínimo 12 | User model / RegisterUseCase |
| Autenticação via Laravel Sanctum (Bearer token) | Todas as rotas protegidas |
| Rate limit: 60 req/min geral, 5 req/min em `/auth/login` | `Kernel.php` / middleware `throttle` |
| Validar MIME real em uploads (não extensão) | Form Requests de upload |
| Upload máximo 5MB | Form Requests + Nginx config |
| Uploads armazenados em object storage (MinIO/Bunny) | StorageService |
| `raw_link` de live nunca em JSON | ValidateLiveSessionToken middleware |
| CORS configurado para o frontend | `config/cors.php` |

### 13.2 Banco de Dados

| Regra | Onde |
|-------|------|
| Operações financeiras em `DB::transaction()` com `SELECT FOR UPDATE` | Wallet Use Cases |
| `audit_logs` sem UPDATE/DELETE (role separada) | Migration + PostgreSQL GRANT |
| Índices parciais em status pendentes | Migrations |
| Timestamps em TIMESTAMPTZ (não TIMESTAMP) | Todas as migrations |
| UUID v4 em todas as PKs | Todas as migrations |

### 13.3 Domain

| Regra | Onde |
|-------|------|
| Nenhuma dependência de `Illuminate/*` no `Domain/` | Todas as classes em `app/Domain/` |
| Imutabilidade de `WalletTransaction`, `TicketMessage`, `Certificate` | Entities + sem setters |
| `Money` sempre em centavos (int) | ValueObject |

---

## 14. Padrão de Respostas de Erro

```json
{
    "message": "Descrição clara do erro",
    "errors": {
        "field_name": ["Regra de validação falhou"]
    },
    "code": "INSUFFICIENT_BALANCE"
}
```

### Códigos de erro padronizados

| Código | Mensagem | HTTP Status |
|--------|----------|-------------|
| `INSUFFICIENT_BALANCE` | Saldo insuficiente na carteira | 422 |
| `ALREADY_ENROLLED` | Aluno já matriculado neste curso | 409 |
| `COURSE_NOT_FOUND` | Curso não encontrado ou não publicado | 404 |
| `VOUCHER_ALREADY_PROCESSED` | Comprovativo já foi processado | 409 |
| `INVALID_OTP` | Código OTP inválido ou expirado | 422 |
| `INVALID_CREDENTIALS` | Email ou senha incorretos | 401 |
| `UNAUTHORIZED_ROLE` | Perfil sem permissão para esta ação | 403 |
| `EMAIL_NOT_VERIFIED` | Email não verificado | 403 |
| `VALIDATION_ERROR` | Dados inválidos na requisição | 422 |
| `TICKET_CLOSED` | Ticket está fechado e não aceita respostas | 409 |
| `CERTIFICATE_ALREADY_EXISTS` | Certificado já foi emitido para esta matrícula | 409 |
| `STUDENT_NOT_COMPLETED` | Curso não foi concluído | 422 |
| `LIVE_SESSION_INACTIVE` | Sessão ao vivo não está ativa no momento | 403 |
| `INVALID_TOKEN` | Token de acesso inválido ou expirado | 401 |
| `FILE_TOO_LARGE` | Arquivo excede o tamanho máximo de 5MB | 413 |
| `INVALID_FILE_TYPE` | Tipo de arquivo não permitido | 422 |
| `RATE_LIMIT_EXCEEDED` | Limite de pedidos excedido | 429 |

### Contrato do 429

Resposta:

```json
{
    "message": "Demasiadas tentativas. Tente novamente mais tarde.",
    "errors": {},
    "code": "RATE_LIMIT_EXCEEDED"
}
```

Cabeçalhos obrigatórios:

| Cabeçalho | Significado |
|-----------|-------------|
| `Retry-After` | Segundos a esperar. É o que permite backoff correcto em vez de retry agressivo |
| `X-RateLimit-Limit` | Máximo de pedidos na janela |
| `X-RateLimit-Remaining` | Pedidos ainda disponíveis na janela |

Limites activos e onde:

| Limiter | Janela | Chave | Rotas |
|---------|--------|-------|-------|
| `api` | 60/min | utilizador, ou IP sem sessão | todas as rotas `/api/*` |
| `auth` | 5/15min por email + 20/15min por IP | identidade + rede | register, login, reset-password |
| `otp` | 5/15min por email + 20/min por IP | identidade + rede | forgot-password, instructor/initiate |
| `otp-verify` | 10/10min por email+IP + 20/10min por IP | identidade + rede | verify-otp, verify-reset-otp, instructor/complete |
| `progress` | 30/5min | utilizador | classroom progress |
| `pdf` | 5/10min | utilizador | emissão de certificado |
| `upload` | 60/min | utilizador | upload de aula e chunked upload |
| `broadcast` | 5/15min | utilizador | admin broadcast |
| `live-join` | 10/min | utilizador | entrada em sessão ao vivo |

Limites por identidade e por IP são combinados de propósito: só o IP pune utilizadores legítimos atrás de um CGNAT (caso comum em Angola), e só o email não impede a mesma conta ser atacada de pontos diferentes.

Cada tentativa falhada de OTP também é contada na base de dados (`users.otp_attempts`). À quinta, o código é invalidado e é preciso pedir um novo — o limite HTTP de tentativas é deliberadamente mais alto que cinco, para que a invalidação (que é o controlo com consequência real) seja a primeira a actuar.
