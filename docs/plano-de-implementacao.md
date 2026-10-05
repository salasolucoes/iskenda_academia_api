# Plano de Implementação — Iskenda Academy

> Baseado na especificação `backend.md` — Arquitetura DDD + Clean Architecture
> Laravel 13 / PHP 8.5 (Docker) / PostgreSQL 18 / Redis / Reverb / Meilisearch

---

## Fase 0 — Setup e Fundação ✅

### 0.1 Configuração Inicial
- [x] Laravel 13 instalado
- [x] PostgreSQL 18 configurado via Docker (container `api-pgsql-1`)
- [x] Laravel Sail configurado (`compose.yaml` com pgsql, redis, meilisearch, laravel.test)
- [x] Redis configurado via Docker (cache + session + queue + broadcasts)
- [x] Laravel Reverb configurado (WebSockets, env vars)
- [x] Laravel Sanctum configurado (API auth com UUID morphs)
- [x] Predis instalado como cliente Redis
- [x] Laravel app rodando em container Docker (PHP 8.5, Nginx)
- [x] `phpunit.xml` configurado com SQLite `:memory:` para testes rápidos
- [x] Comando `composer test` adicionado
- [x] `AGENTS.md` com regras do projeto (Laravel Boost)
- [x] `compose.md` com guia de comandos Docker
- [x] Configurar MinIO (object storage local) — container Docker + bucket `iskenda`
- [ ] Bunny.net preparado em `config/filesystems.php` + `.env` — descomentar em produção
- [x] Configurar `boost.json` com preferências do projeto

### 0.2 Estrutura de Pastas DDD
```bash
app/
├── Domain/
│   ├── Auth/
│   ├── Wallet/
│   ├── Enrollment/
│   ├── Course/
│   └── Support/
├── Application/
│   ├── Wallet/UseCases, DTOs
│   ├── Enrollment/UseCases
│   ├── Auth/UseCases
│   └── Support/UseCases
├── Infrastructure/
│   ├── Persistence/Eloquent/Models, Repositories
│   ├── Services/
│   └── Broadcasting/
└── Http/
    ├── Controllers/{Student,Instructor,Admin}/
    ├── Middleware/
    └── Requests/
```

### 0.3 Ambiente Docker
| Container | Imagem | Porta | Status |
|-----------|--------|-------|--------|
| `api-laravel.test-1` | `sail-8.5/app` | 80, 5173 | ✅ |
| `api-pgsql-1` | `postgres:18-alpine` | 5432 | ✅ |
| `api-redis-1` | `redis:alpine` | 6379 | ✅ |
| `api-meilisearch-1` | `getmeili/meilisearch` | 7700 | ✅ |
| `api-coturn-1` | `coturn/coturn` | 3478 (STUN), 5349 (TURN/TLS) | ✅ |
| `api-minio-1` | `minio/minio` | 9000 (API), 9001 (Console) | ✅ |

- App acessível em `http://localhost`
- `.env` configurado com nomes dos serviços Docker (`DB_HOST=pgsql`, `REDIS_HOST=redis`, etc.)
- Testes: `docker compose exec laravel.test php artisan test --compact` → **4/4 passed**
- Migrations: `migrate:fresh` executado com sucesso

### 0.4 Features de Suporte
- [x] Disco `minio` adicionado ao `config/filesystems.php` (MinIO Docker + bucket criado)
- [x] Disco `bunny` adicionado ao `config/filesystems.php` (pronto para produção, descomentar env vars)
- [x] Roteamento API prefixado com `v1`
- [x] Meilisearch configurado para busca full-text

---

## Fase 1 — Autenticação e Utilizadores (5-6 dias) ✅

### 1.1 Migrations
- [x] Migration `users` (UUID, roles enum, OTP fields)
- [x] Migration `password_reset_tokens`
- [x] Custom casts: `RoleEnum`

### 1.2 Domínio
- [x] `Domain/Auth/Entities/User.php`
- [x] `Domain/Auth/ValueObjects/Role.php` (enum)
- [x] `Domain/Auth/Contracts/AuthRepositoryInterface.php`
- [x] `Domain/Auth/Services/AuthDomainService.php`

### 1.3 Infraestrutura
- [x] `Infrastructure/Persistence/Eloquent/Models/User.php`
- [x] `Infrastructure/Persistence/Repositories/EloquentAuthRepository.php`
- [x] `Infrastructure/Services/OtpService.php` (Twilio/Mailgun)

### 1.4 Application
- [x] `RegisterStudentUseCase.php`
- [x] `VerifyOtpUseCase.php`

### 1.5 API
- [x] `Http/Controllers/Student/AuthController.php`
- [x] `Http/Middleware/EnsureEmailVerified.php`
- [x] `Http/Middleware/RoleMiddleware.php`
- [x] Rotas: `POST /auth/register`, `/verify-otp`, `/login`, `/logout`
- [x] Rate limiting (5 req/min em login)

### 1.6 Testes
- [x] Testes de registro com OTP
- [x] Testes de login/logout
- [x] Testes de middleware de role

---

## Fase 2 — Cursos e Conteúdo (5-6 dias) ✅

### 2.1 Migrations
- [x] Migration `categories`
- [x] Migration `courses` (modality enum, status enum, price_cents)
- [x] Migration `modules`
- [x] Migration `lessons` (type enum: video|pdf|live)
- [x] Migration `live_sessions`

### 2.2 Domínio
- [x] `Domain/Course/Entities/Course.php`
- [x] `Domain/Course/Entities/Lesson.php`
- [x] `Domain/Course/Entities/LiveSession.php`
- [x] `Domain/Course/ValueObjects/Modality.php`
- [x] `Domain/Course/ValueObjects/CourseStatus.php`
- [x] `Domain/Course/Contracts/CourseRepositoryInterface.php`
- [x] `Domain/Course/Services/CourseDomainService.php`

### 2.3 API Pública
- [x] `GET /courses` (filtros: category, modality, search, price_range)
- [x] `GET /courses/{slug}` (detalhe)
- [x] Testes de catálogo

### 2.4 API Formador
- [x] `Instructor/ContentController.php`
- [x] `POST /instructor/courses`, `PUT /instructor/courses/{id}`
- [x] `POST /instructor/courses/{id}/modules`
- [x] `POST /instructor/modules/{id}/lessons`
- [x] Upload de videoaulas e PDFs
- [x] Testes de conteúdo

### 2.5 Streaming ao Vivo (WebRTC + Reverb + coturn)

**Arquitetura:**
- **Signaling**: Laravel Reverb (WebSocket) — troca de SDP/ICE candidates
- **TURN/STUN**: coturn (Docker) — ultrapassa NATs, portas 3478 (STUN) / 5349 (TURN/TLS)
- **Rede**: Peer-to-peer com fallback para TURN relay
- **Sem apps externas**: tudo no browser via WebRTC API nativa

**Infraestrutura:**
- [x] Adicionar `coturn/coturn` ao `compose.yaml` (portas 3478, 5349)
- [x] Configurar `turnserver.conf` com credenciais temporárias por sessão
- [ ] `Infrastructure/Broadcasting/LiveSignalingController.php` (handlers Reverb)
- [ ] `LiveSessionStartedEvent`, `LiveSessionEndedEvent`

**Backend:**
- [ ] `POST /live/{sessionId}/join` — valida matrícula, devolve TURN credentials + token Reverb
- [ ] Canais Reverb: `private-live.{sessionId}` (auth via Sanctum)
- [ ] Job `EndLiveSessionJob` (finaliza após duração máxima)
- [ ] Middleware `EnsureEnrolled` para rotas ao vivo

**Fluxo:**
1. Instrutor cria aula ao vivo → `live_session` gerada com `stream_key`
2. Alunos notificados via `LiveStartingSoonEvent` (Reverb)
3. Frontend pede `POST /live/{sessionId}/join`
4. Backend devolve token TURN + autoriza canal Reverb
5. Conexão WebRTC peer-to-peer (ou TURN relay)
6. Signaling via Reverb (SDP/ICE)

**Segurança:**
- Apenas alunos matriculados acedem (middleware)
- Tokens TURN expiram no fim da sessão
- Canal Reverb privado com auth Sanctum

**Testes:**
- [ ] Testar signaling flow com Reverb channel auth
- [ ] Testar join com aluno não matriculado (403)
- [ ] Testar expiração de token TURN

---

## Fase 3 — Matrículas e Progresso (4-5 dias) ✅

### 3.1 Migrations
- [x] Migration `enrollments`
- [x] Migration `lesson_progress`
- [x] Migration `certificates`

### 3.2 Domínio
- [x] `Domain/Enrollment/Entities/Enrollment.php`
- [x] `Domain/Enrollment/Entities/Certificate.php`
- [x] `Domain/Enrollment/ValueObjects/EnrollmentStatus.php`
- [x] `Domain/Enrollment/ValueObjects/VerificationHash.php`
- [x] `Domain/Enrollment/Contracts/EnrollmentRepositoryInterface.php`
- [x] `Domain/Enrollment/Services/EnrollmentDomainService.php`

### 3.3 Infraestrutura
- [x] CertificatePdfService.php (geração de PDF via DomPDF)
- [x] StorageService.php (MinIO/Bunny) — `PresignedUrlService`

### 3.4 API Sala de Aula
- [x] `GET /classroom/{enrollmentId}`
- [x] `POST /classroom/{lessonId}/progress`
- [x] `POST /classroom/{liveSessionId}/join` (redirect com token)
- [x] `POST /certificates/{enrollmentId}/issue`
- [x] `GET /certificates/{hash}/verify`
- [x] Testes de progresso e certificados

---

## Fase 4 — Carteira Digital e Créditos (6-7 dias) ⭐ Core ✅

### 4.1 Migrations
- [x] Migration `student_wallets`
- [x] Migration `credit_packages`
- [x] Migration `wallet_transactions` (IMUTÁVEL)
- [x] Migration `payment_vouchers`
- [x] Migration `carts`
- [x] Migration `cart_items`
- [x] Migration `orders`
- [x] Migration `order_items`

### 4.2 Domínio
- [x] `Domain/Wallet/Entities/Wallet.php`
- [x] `Domain/Wallet/Entities/WalletTransaction.php`
- [x] `Domain/Wallet/ValueObjects/Money.php` (centavos, add/subtract/compare)
- [x] `Domain/Wallet/ValueObjects/TransactionType.php`
- [x] `Domain/Wallet/ValueObjects/Direction.php`
- [x] `Domain/Wallet/Exceptions/InsufficientBalanceException.php`
- [x] `Domain/Wallet/Exceptions/InvalidTransactionException.php`
- [x] `Domain/Wallet/Contracts/WalletRepositoryInterface.php`
- [x] `Domain/Wallet/Contracts/TransactionRepositoryInterface.php`
- [x] `Domain/Wallet/Services/WalletDomainService.php` (debit/credit)

### 4.3 Application (Use Cases)
- [x] `CheckoutWithWalletUseCase.php` (DB::transaction + FOR UPDATE)
- [x] `ApproveVoucherUseCase.php`
- [x] `RejectVoucherUseCase.php`
- [x] `AdminAdjustBalanceUseCase.php`
- [x] DTOs: `CheckoutInput`, `ApprovalInput`

### 4.4 API
- [x] `GET /wallet` (saldo + transações)
- [x] `POST /wallet/voucher` (upload comprovativo)
- [x] `GET /cart`, `POST /cart/items`, `DELETE /cart/items/{id}`
- [x] `POST /cart/checkout` (checkout com wallet)
- [x] `GET /admin/sales/pending`
- [x] `POST /admin/sales/{voucherId}/approve`
- [x] `POST /admin/sales/{voucherId}/reject`
- [x] `POST /admin/wallet/adjust`
- [x] Testes de carteira (incluindo race conditions)

---

## Fase 5 — Suporte (Tickets) (3-4 dias) ✅

### 5.1 Migrations
- [x] Migration `tickets`
- [x] Migration `ticket_messages` (IMUTÁVEL)

### 5.2 Domínio
- [x] `Domain/Support/Entities/Ticket.php`
- [x] `Domain/Support/Entities/TicketMessage.php`
- [x] `Domain/Support/ValueObjects/Priority.php`
- [x] `Domain/Support/ValueObjects/TicketStatus.php`
- [x] `Domain/Support/Contracts/TicketRepositoryInterface.php`
- [x] `Domain/Support/Services/TicketDomainService.php`

### 5.3 Application
- [x] `OpenTicketUseCase.php`
- [x] `ReplyTicketUseCase.php`

### 5.4 API
- [x] `Student/TicketController.php`
- [x] `GET /tickets`, `POST /tickets`, `GET /tickets/{id}`
- [x] `POST /tickets/{id}/messages`
- [x] `Admin/TicketController.php`
- [x] `GET /admin/tickets`, `PUT /admin/tickets/{id}`
- [x] Testes de tickets

---

## Fase 6 — Auditoria (2-3 dias) ✅

### 6.1 Migration
- [x] Migration `audit_logs` (BIGSERIAL, append-only, JSONB, indexes)

### 6.2 Infraestrutura
- [x] `Domain\Support\Contracts\AuditLoggerInterface.php` (contrato)
- [x] `Infrastructure\Services\EloquentAuditLogger.php` (INSERT direto via DB, síncrono)
- [x] `Domain\Support\ValueObjects\ActorContext.php` (DTO: actor_id, actor_role, actor_ip)

### 6.3 Integração
- [x] `RegisterStudentUseCase` → `auth.register`
- [x] `VerifyOtpUseCase` → `auth.otp.verified`
- [x] `AuthController@login` → `auth.login` + `auth.login.failed`
- [x] `CheckoutWithWalletUseCase` → `wallet.debit.checkout`
- [x] `AdminAdjustBalanceUseCase` → `wallet.credit.admin`
- [x] `ApproveVoucherUseCase` → `voucher.approved`
- [x] `RejectVoucherUseCase` → `voucher.rejected`
- [x] `WalletController@uploadVoucher` → `voucher.uploaded`
- [x] `IssueEnrollmentUseCase` → `enrollment.created`
- [x] Configurar role PostgreSQL `iskenda_app` sem permissão DELETE/UPDATE em audit_logs

### 6.4 Testes
- [x] Teste de criação de audit log via EloquentAuditLogger
- [x] Teste de defaults nulos (actor_id, previous_state, new_state)
- [x] Teste de evento auth.register
- [x] Teste de evento auth.login
- [x] Teste de evento auth.login.failed

---

## Fase 7 — Notificações em Tempo Real (3-4 dias) ✅

### 7.1 Configuração
- [x] Configurar Laravel Reverb (já instalado)
- [x] Configurar canais `private-admin`, `private-instructor.{id}`, `private-user.{id}` — broadcasting channels configurados

### 7.2 Eventos
- [x] LiveSession link fields migration (add_link_fields_to_live_sessions_table)
- [x] `NewPurchaseIntentEvent` — (não integrado, fluxo de intenção de compra ainda não existe)
- [x] `NewUrgentTicketEvent` — integrado no OpenTicketUseCase
- [x] `NewStudentEnrolledEvent` — integrado no IssueEnrollmentUseCase
- [x] `LiveStartingSoonEvent` — disparado via LiveStartingSoonJob
- [x] `VoucherApprovedEvent` — integrado no ApproveVoucherUseCase
- [x] `VoucherRejectedEvent` — integrado no RejectVoucherUseCase
- [x] `CourseAccessGrantedEvent` — integrado no IssueEnrollmentUseCase
- [x] `TicketRepliedEvent` — integrado no ReplyTicketUseCase
- [x] `Admin/NotificationController.php` — endpoints de notificações por DB
- [x] `Student/NotificationController.php` — endpoints de notificações por DB

### 7.3 Jobs Agendados
- [x] `LiveStartingSoonJob` (a cada minuto) — verifica live sessions nas próximas 15min
- [x] `ExpiredCartCleanupJob` (diário) — limpa carrinhos > 7 dias

### 7.4 Testes
- [x] Testes de broadcasting com 7 eventos (BroadcastingEventsTest.php)

---

## Fase 8 — Formador e Admin (3-4 dias) ✅

### 8.1 Dashboard Formador
- [x] `GET /instructor/dashboard` (métricas dos cursos)
- [x] `GET /instructor/students` (progresso individual)
- [x] `StudentController.php` — progresso por inscrição com certificado

### 8.2 Dashboard Admin
- [x] `GET /admin/dashboard` (KPIs, faturação, inscrições)
- [x] `Admin/SalesController.php` → `Admin/VoucherController.php`
- [x] `Admin/LiveSessionController.php` — gerenciar links de transmissão (`updateLink()`)
- [x] `Http/Middleware/ValidateLiveSessionToken.php`
- [x] `Admin/InstructorController.php` — CRUD completo de instrutores
- [x] `Admin/CategoryController.php` — CRUD de categorias

### 8.3 Auto-Criação de LiveSession
- [x] `CreateLiveLessonUseCase.php` — cria Lesson + LiveSession atomicamente ([plano](plans/auto-create-live-session.md))
- [x] Refactor `LessonController::store` para usar Use Case quando `type=live`
- [x] Testes de criação automática de LiveSession (8 testes)

### 8.4 Token de Sessão (Redis)
- [x] `LiveSessionTokenService.php` — tokens por estudante em Redis (correção de race condition)
- [x] `StudentLiveSessionController::join()` — usa `LiveSessionTokenService::issue()`
- [x] `ValidateLiveSessionToken` — usa `LiveSessionTokenService::consume()` (atómico GETDEL)
- [x] Testes de concorrência e single-use (2 novos testes)

### 8.5 Testes
- [x] Testes de dashboard instructor (InstructorDashboardTest.php — 8 testes)
- [x] Testes de dashboard admin (AdminDashboardTest.php — 10 testes)
- [x] Testes de segurança de live links (StudentLiveSessionTest.php — 7 testes)

---

## Fase 9 — Hardening e Finalização (3-4 dias)

### 9.1 Segurança
- [x] Rate limiting em live session join (`throttle:10,1` no `POST /classroom/{id}/join`)
- [x] Revisar todas as queries (N+1, SQL injection)
- [x] CSRF em rotas web (se houver)
- [x] Validação de MIME real em uploads (não extensão)
- [x] Limite de 5MB em uploads
- [x] CORS configurado

### 9.2 Live Token Hardening (Redis)
- [x] Corrigir ordem de validação: status da sessão → matrícula → token (evitar desperdício)
- [x] Rate limiting no join (`throttle:10,1`)
- [x] Fallback Redis→DB em `LiveSessionTokenService` (try/catch, colunas `masked_token`/`token_expires_at` como rede)
- [x] Audit logging em `StudentLiveSessionController::join()` e `ValidateLiveSessionToken` (eventos `live.join`, `live.join.denied`)
- [x] TTL configurável via `config/live.php` → `LIVE_TOKEN_TTL_MINUTES` (default 30min)
- [x] Tokens independentes por estudante (não compartilhados)

### 9.3 Performance
- [x] Índices do banco (conforme especificação)
- [x] Cache de catálogo de cursos
- [x] Eager loading em relações N+1
- [x] Paginação em listagens

### 9.4 Deploy VPS
- [x] Dockerfile multi-stage (PHP-FPM + Nginx)
- [x] docker-compose.prod.yml otimizado para 2 vCPU/4GB
- [x] nginx.conf com Cloudflare trusted IPs
- [x] .env.production template
- [x] Guia de deploy (docs/deploy-vps.md)

### 9.5 Testes Finais
- [x] Testes de integração completos
- [ ] Testes de carga (k6 ou similar)
- [x] Revisão de cobertura de testes

### 9.6 Documentação
- [x] README com instruções de setup
- [ ] Documentação da API (Postman/OpenAPI)

---

## Resumo de Estimativas

| Fase | Descrição | Dias | Estado |
|------|-----------|------|--------|
| 0 | Setup e Fundação | 3-4 | ✅ |
| 1 | Autenticação | 5-6 | ✅ |
| 2 | Cursos e Conteúdo | 5-6 | ✅ |
| 3 | Matrículas e Progresso | 4-5 | ✅ |
| 4 | Carteira Digital (Core) | 6-7 | ✅ |
| 5 | Suporte (Tickets) | 3-4 | ✅ |
| 6 | Auditoria | 2-3 | ✅ |
| 7 | Notificações em Tempo Real | 3-4 | ✅ |
| 8 | Formador e Admin | 3-4 | ✅ |
| 9 | Hardening e Finalização | 3-4 | ✅ |
| **Total** | | **~37-47 dias** | **10/10 fases** |

---

## Implementado Além do Plano

### Extras Backend
- [x] Password Reset (3-step token-based: verify → OTP → reset password)
- [x] Instructor full setup workflow (Admin creates → OTP → instructor sets password)
- [x] `PresignedUrlService` — shared MinIO/S3 presigned URL generation (OCP refactor)
- [x] `LiveSessionController` (admin) — CRUD de sessões ao vivo com links
- [x] `NotificationController` (admin + student) — endpoints de notificações
- [x] `validate_live_session_token` middleware
- [x] `CreateLiveLessonUseCase` — auto-criação de LiveSession quando instructor cria aula ao vivo ([plano](plans/auto-create-live-session.md))
- [x] Fix: `config:cache` Windows path baking (deve ser rodado dentro do container)
- [x] Fix: `WalletTransaction` removido `HasUuids` (causava FK violations com IDs explícitos)
- [x] Fix: `otp_code` widened de `varchar(6)` para `varchar(8)` (instructor OTPs)
- [x] Fix: `rejected_by` FK em `payment_vouchers` (auditoria de rejeição)

### Extras Frontend
- [x] Upload múltiplos vídeos em lote (batch upload) — 1 vídeo = 1 aula automática ([plano](plans/tipos-de-aula.md))
- [x] Upload de PDF no CourseBuilder ([plano](plans/tipos-de-aula.md))
- [x] Configuração de aulas ao vivo no CourseBuilder ([plano](plans/tipos-de-aula.md))
- [x] Componente `Select` reutilizável (react-select)

---

## Convenções de Código

### Nomes de Branches
- `feature/wallet-module`
- `feature/auth-otp`
- `feature/admin-dashboard`
- `fix/race-condition-wallet`

### Commits (Conventional Commits)
- `feat: add wallet debit with pessimistic locking`
- `feat: implement voucher approval flow`
- `fix: prevent duplicate enrollment on concurrent checkout`
- `test: add race condition test for wallet checkout`

### Padrão de Commits
- Commits pequenos e atômicos
- Mensagens em inglês (padrão do ecossistema)
- Commits com falhas nos testes são bloqueados (pre-commit hook)

---

## Decisões Técnicas

### Streaming ao Vivo — WebRTC (não Meet/Zoom/HLS)
- **Rejeitado**: Google Meet / Zoom (dependência externa, custo por licença, sem controlo)
- **Rejeitado**: HLS (latência ~10s, inaceitável para aulas interativas)
- **Escolhido**: WebRTC peer-to-peer com signaling via Reverb + coturn (TURN relay para redes restritivas)
- **Prós**: zero latência, sem apps externas, controlo total, custo apenas de infraestrutura (TURN)
- **Contras**: maior complexidade de implementação, largura de banda P2P imprevisível sem SFU
