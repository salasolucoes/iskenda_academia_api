# Plano do Administrador — Iskenda Academy

> Jornada completa do admin: supervisão, aprovação de vendas, gestão de plataforma.

---

## Índice

1. [Fluxo Principal](#1-fluxo-principal)
2. [Dashboard](#2-dashboard)
3. [Aprovação de Vendas](#3-aprovação-de-vendas)
4. [Gestão de Cursos](#4-gestão-de-cursos)
5. [Gestão de Categorias](#5-gestão-de-categorias)
6. [Gestão de Formadores](#6-gestão-de-formadores)
7. [Gestão de Alunos](#7-gestão-de-alunos)
8. [Links de Transmissão](#8-links-de-transmissão)
9. [Moderação de Tickets](#9-moderação-de-tickets)
10. [Carteira (Ajustes)](#10-carteira-ajustes)
11. [Páginas (Frontend)](#11-páginas-frontend)
12. [API Endpoints](#12-api-endpoints)

---

## 1. Fluxo Principal

```
Login → Dashboard → Vendas Pendentes → Aprovar/Rejeitar
→ Gerir Cursos (arquivar) → Gerir Categorias
→ Gerir Formadores/Alunos → Moderar Tickets
→ Ajustar Carteiras
```

---

## 2. Dashboard (`/admin/dashboard`)

### Indicadores (KPIs)
- Faturação total (mês atual)
- Total de matriculados (aprovados)
- Total de utilizadores registados
- Tickets abertos (pendentes)

### Gráficos
- Histórico de vendas (últimos 6 meses)
- Distribuição por categoria

---

## 3. Aprovação de Vendas (`/admin/sales`)

### Listagem
- Tabela de vendas com comprovativo
- Filtros: status (pending, approved, rejected)
- Ordenação por data

### Ações
| Ação | Descrição |
|------|-----------|
| **Aprovar** | Credita valor na wallet do aluno, cria matrícula, notifica aluno |
| **Rejeitar** | Marca como rejeitado (com motivo), cancela transação, notifica aluno |

### Regras
- Voucher deve estar `pending` para ser processado
- Aprovação: `walletDomainService->credit()` + `IssueEnrollmentUseCase`
- Rejeição: `voucherRepo->markRejected()` + `transactionRepo->markCancelled()`
- Aluno notificado via WebSocket em ambos os casos

---

## 4. Gestão de Cursos (`/admin/courses`)

### Listagem
- Todos os cursos da plataforma (qualquer instrutor)
- Filtros: status, categoria, instrutor

### Ações
| Ação | Descrição |
|------|-----------|
| **Arquivar** | Torna curso indisponível para novos alunos (matrículas ativas mantêm-se) |
| **Editar** | Redireciona para o builder (como admin) |
| **Remover** | Apenas cursos em `draft` sem matrículas |

---

## 5. Gestão de Categorias (`/admin/categories`)

### Operações
- Listagem de categorias existentes
- Criar nova categoria (nome, slug, descrição)
- Editar categoria
- Remover categoria (apenas se sem cursos associados)

---

## 6. Gestão de Formadores (`/admin/instructors`)

### Listagem
- Tabela de formadores registados
- Dados: nome, email, cursos publicados, total de alunos

### Ações
| Ação | Descrição |
|------|-----------|
| **Ativar/Desativar** | Bloqueia acesso do formador à plataforma |
| **Atribuir Curso** | Transferir curso para outro formador |

---

## 7. Gestão de Alunos (`/admin/students`)

### Listagem
- Tabela de alunos
- Dados: nome, email, matrículas ativas, saldo carteira

### Ações
| Ação | Descrição |
|------|-----------|
| **Ajustar Saldo** | Adicionar/remover créditos na carteira (com justificação) |
| **Cancelar Matrícula** | Marcar enrollment como `cancelled` |

---

## 8. Links de Transmissão (`/admin/live`)

### Gestão de Links
- Admin cadastra `raw_link` (Zoom/Meet) para aulas ao vivo
- Sistema gera `masked_token` (HMAC-SHA256) único por sessão
- `raw_link` nunca exposto em respostas JSON

### Fluxo de Acesso
1. Admin cadastra o link bruto da transmissão
2. Aluno pede `POST /classroom/{liveSessionId}/join`
3. Middleware `ValidateLiveSessionToken` gera token assinado
4. Redirect 302 para o link (nunca em JSON)
5. Token de uso único, TTL 30 min

---

## 9. Moderação de Tickets (`/admin/tickets`)

### Listagem
- Todos os tickets da plataforma
- Filtros: status (open, in_progress, resolved, closed), prioridade

### Ações
| Ação | Descrição |
|------|-----------|
| **Responder** | Adicionar mensagem (pode ser interna, visível só para staff) |
| **Resolver** | Mudar status para `resolved` |
| **Fechar** | Mudar status para `closed` (terminal) |
| **Atribuir** | Designar ticket a si próprio |

### Regras de Transição
```
open ───→ in_progress ───→ resolved ───→ closed
  ↑                           │
  └───────────────────────────┘ (reabrir se resolved)
```
- `closed` é terminal
- Sistema auto-atribui `in_progress` quando admin responde pela primeira vez
- Resposta interna (`is_internal = true`) não visível para o aluno

---

## 10. Carteira (Ajustes)

### Ajuste Manual (`AdminAdjustBalanceUseCase`)
```php
class AdminAdjustBalanceInput
{
    public string $studentId;     // UUID
    public int    $amountCents;   // positivo = crédito, negativo = débito
    public string $reason;
    public string $adminId;
    public ActorContext $actorContext;
}
```

### Fluxo
1. `walletRepo->findForUpdateByStudent(studentId)`
2. Se `amountCents > 0` → `walletDomainService->credit()`
3. Se `amountCents < 0` → `walletDomainService->debit()` (pode lançar `InsufficientBalanceException`)
4. Persiste transação com `type = admin_adjustment`
5. Auditoria obrigatória

---

## 11. Páginas (Frontend)

| Rota | Página | Descrição |
|------|--------|-----------|
| `/admin/dashboard` | AdminDashboard | KPIs, gráficos, vendas |
| `/admin/sales` | SalesApproval | Aprovar/rejeitar comprovativos |
| `/admin/courses` | CourseManager | Gerir todos os cursos |
| `/admin/categories` | CategoryManager | Gerir categorias |
| `/admin/instructors` | InstructorManager | Gerir formadores |
| `/admin/students` | StudentManager | Gerir alunos e carteiras |
| `/admin/live` | LiveLinkManager | Cadastrar links de transmissão |
| `/admin/tickets` | TicketModeration | Moderar tickets de suporte |

### Componentes Compartilhados
- `Navbar` — links, notificações, menu do perfil
- `Sidebar` — navegação do admin
- `ProtectedRoute` — redireciona para login se não autenticado

---

## 12. API Endpoints

### Dashboard
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/dashboard` | KPIs + gráficos |

### Vendas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/sales/pending` | Vendas pendentes |
| POST | `/v1/admin/sales/{voucherId}/approve` | Aprovar comprovativo |
| POST | `/v1/admin/sales/{voucherId}/reject` | Rejeitar comprovativo |

### Cursos
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/courses` | Listar todos os cursos |
| PUT | `/v1/admin/courses/{id}` | Atualizar qualquer curso |
| POST | `/v1/admin/courses/{id}/archive` | Arquivar curso |
| DELETE | `/v1/admin/courses/{id}` | Remover curso (draft) |

### Categorias
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/categories` | Listar categorias |
| POST | `/v1/admin/categories` | Criar categoria |
| PUT | `/v1/admin/categories/{id}` | Atualizar categoria |
| DELETE | `/v1/admin/categories/{id}` | Remover categoria |

### Formadores
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/instructors` | Listar formadores |
| POST | `/v1/admin/instructors/{id}/toggle` | Ativar/desativar |
| POST | `/v1/admin/instructors/{id}/transfer-courses` | Transferir cursos |

### Alunos
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/students` | Listar alunos |
| POST | `/v1/admin/students/{id}/adjust-balance` | Ajustar carteira |
| POST | `/v1/admin/students/{id}/cancel-enrollment` | Cancelar matrícula |

### Links ao Vivo
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/live-links` | Listar links |
| POST | `/v1/admin/live-links` | Cadastrar link |
| PUT | `/v1/admin/live-links/{id}` | Atualizar link |
| DELETE | `/v1/admin/live-links/{id}` | Remover link |

### Tickets
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/admin/tickets` | Listar todos os tickets |
| GET | `/v1/admin/tickets/{id}` | Ver detalhe |
| POST | `/v1/admin/tickets/{id}/messages` | Responder |
| PUT | `/v1/admin/tickets/{id}/status` | Atualizar status |
| PUT | `/v1/admin/tickets/{id}/assign` | Atribuir ticket |

### Carteira
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/v1/admin/wallet/adjust` | Ajuste manual de saldo |

---

## Regras de Negócio (Admin)

- Admin pode gerir **qualquer** recurso da plataforma
- Aprovação de voucher credita valor na wallet + cria matrícula
- Rejeição de voucher não credita nada, aluno é notificado com motivo
- Apenas admin pode arquivar cursos (matrículas ativas mantêm-se)
- Apenas admin pode resolver/fechar tickets
- Ajuste de saldo requer justificação escrita e fica registado em auditoria
- `raw_link` nunca exposto em JSON — apenas redirect 302 com token assinado
- Todas as operações financeiras são auditadas obrigatoriamente
