# Plano do Aluno — Iskenda Academy

> Jornada completa do estudante: registo, navegação, compra, aprendizagem e certificação.

---

## Índice

1. [Fluxo Principal](#1-fluxo-principal)
2. [Registo e Autenticação](#2-registo-e-autenticação)
3. [Catálogo e Descoberta](#3-catálogo-e-descoberta)
4. [Carrinho e Compra](#4-carrinho-e-compra)
5. [Sala de Aula](#5-sala-de-aula)
6. [Progresso e Certificação](#6-progresso-e-certificação)
7. [Suporte (Tickets)](#7-suporte-tickets)
8. [Notificações](#8-notificações)
9. [Páginas (Frontend)](#9-páginas-frontend)
10. [API Endpoints](#10-api-endpoints)

---

## 1. Fluxo Principal

```
Registo → Verificar OTP → Login → Catálogo → Detalhe do Curso
→ Adicionar ao Carrinho → Checkout (Carteira/Voucher) → Sala de Aula
→ Assistir Aulas → Progresso 100% → Certificado
```

### Ações transversais
- Ver notificações em tempo real
- Abrir ticket de suporte
- Consultar histórico de compras
- Ver saldo da carteira

---

## 2. Registo e Autenticação

### 2.1 Registo (`/auth/register`)
- Formulário: nome, email, senha (min 8 chars)
- Validações:
  - Email único no sistema
  - Senha com bcrypt custo 12
- Após submit: OTP enviado por email

### 2.2 Verificação OTP (`/auth/verify-otp`)
- 6 dígitos numéricos
- Input com autofocus progressivo
- Valida `otp_expires_at > now()`
- Token de acesso devolvido
- Wallet criada automaticamente (`balance = 0`)

### 2.3 Login (`/auth/login`)
- Email + senha
- Rate limit: 5 req/min
- Retorna token Sanctum + dados do user

### 2.4 Logout
- Revoga token atual
- Limpa estado da app

---

## 3. Catálogo e Descoberta

### 3.1 Listagem (`/catalog`)
- Grid de cursos publicados
- Filtros:
  - Categoria (dropdown)
  - Preço máximo (slider)
  - Modalidade (gravado/ao vivo)
  - Tags
- Busca por texto (título, descrição)
- Paginação

### 3.2 Detalhe do Curso (`/catalog/:id`)
- Informações: título, descrição, instrutor, categoria, tags
- Preço em destaque
- Pré-visualização de aulas gratuitas (`is_free_preview = true`)
- Meterial didático: videoaulas, PDFs, links ao vivo
- Botões:
  - "Comprar Agora" → adiciona ao carrinho e redireciona
  - "Adicionar ao Carrinho"
  - "Continuar" (se já matriculado) → redireciona para sala de aula

---

## 4. Carrinho e Compra

### 4.1 Carrinho (`/cart`)
- Lista de cursos selecionados
- Quantidade (1 por curso)
- Total em tempo real
- Remover item
- Botão "Finalizar Compra"

### 4.2 Checkout (`/checkout`)
- Resumo dos itens
- Saldo atual da carteira
- Método de pagamento:
  1. **Carteira** → débito imediato se saldo suficiente
  2. **Comprovativo** → upload de PDF/imagem para aprovação manual pelo admin

### 4.3 Pagamento por Carteira
- `SELECT FOR UPDATE` na wallet
- Débito do valor total
- Criação de `order` + `enrollments`
- Matrícula ativa imediatamente

### 4.4 Pagamento por Voucher (`/checkout/voucher`)
- Upload de comprovativo (JPEG/PNG/PDF, max 5MB)
- Validação de MIME real
- Cria `wallet_transaction` com status `pending`
- Cria `payment_voucher` com status `pending`
- Notificação ao admin
- Aluno aguarda aprovação
- Após aprovação: crédito na wallet + matrícula automática

---

## 5. Sala de Aula

### 5.1 Dashboard (`/dashboard`)
- Cursos matriculados (grid)
- Barra de progresso por curso
- Acesso rápido ao último curso assistido
- Botão "Continuar" → retoma de onde parou

### 5.2 Player (`/classroom/:id`)
- Navegação entre aulas (sidebar com módulos)
- Player de vídeo (HLS ou direto)
- Leitor de PDF integrado
- Aulas ao vivo: link com token assinado (HMAC-SHA256, TTL 30 min)
- Tracking de progresso:
  - `watched_seconds` e `last_position_seconds` enviados periodicamente
  - `is_completed = true` quando ≥ 90% da duração assistida
- Botão "Concluir Aula" (alternativo)

### 5.3 Progresso Automático
- A cada 30 segundos: `POST /classroom/{lessonId}/progress`
- Quando `is_completed` muda de `false → true`:
  - Verificar se todas as lições do curso estão completas
  - Se sim: `enrollment.status = completed`, `completed_at = now()`
  - Disparar evento para emissão de certificado

---

## 6. Progresso e Certificação

### 6.1 Barra de Progresso
- Percentual global do curso
- Atualizada em tempo real via WebSocket (ou polling)

### 6.2 Certificado (`/certificate/:id`)
- Gerado automaticamente quando curso concluído
- PDF armazenado no object storage (MinIO/Bunny)
- `verification_hash` = SHA-256 único

### 6.3 Verificação Pública
- Rota: `GET /certificates/{hash}/verify`
- Retorna: nome do aluno, curso, data de emissão
- Sem autenticação necessária

---

## 7. Suporte (Tickets)

### 7.1 Listagem (`/support`)
- Tickets do aluno (ordenados por data descendente)
- Status: open, in_progress, resolved, closed
- Badge de prioridade

### 7.2 Novo Ticket (`/support/new`)
- Campos: assunto (max 200 chars), descrição, prioridade
- Se prioridade = high → notificação urgente ao admin

### 7.3 Conversa (`/support/:id`)
- Histórico de mensagens
- Input para nova mensagem
- Auto-scroll para última mensagem
- Se admin respondeu → ticket vai para in_progress

---

## 8. Notificações

### 8.1 Canais WebSocket (Reverb)
- `private-user.{id}` — canal pessoal do aluno

### 8.2 Eventos que o aluno recebe
| Evento | Conteúdo |
|--------|----------|
| `VoucherApprovedEvent` | Comprovativo aprovado, saldo atualizado |
| `VoucherRejectedEvent` | Motivo da rejeição |
| `CourseAccessGrantedEvent` | Curso liberado para acesso |
| `TicketRepliedEvent` | Nova resposta no ticket |
| `LiveStartingSoonEvent` | Aula ao vivo começará em 15 min |

### 8.3 Badge de Notificações
- Número de notificações não lidas no navbar
- Dropdown com pré-visualização das últimas 5

---

## 9. Páginas (Frontend)

| Rota | Página | Descrição |
|------|--------|-----------|
| `/auth/login` | LoginPage | Login com email + senha |
| `/auth/register` | RegisterPage | Registo com nome, email, senha |
| `/auth/verify-otp` | OtpPage | Verificação de 6 dígitos |
| `/dashboard` | StudentDashboard | Cursos matriculados com progresso |
| `/catalog` | CourseCatalog | Grid de cursos com filtros |
| `/catalog/:id` | CourseDetail | Detalhe do curso + pré-visualização |
| `/cart` | Cart | Carrinho de compras |
| `/checkout` | Checkout | Finalizar compra |
| `/checkout/voucher` | VoucherUpload | Upload de comprovativo |
| `/classroom/:id` | ClassroomPage | Player de aula + progresso |
| `/certificate/:id` | CertificatePage | Certificado do curso |
| `/support` | SupportTickets | Lista de tickets |
| `/support/new` | NewTicket | Abrir novo ticket |

### Componentes Compartilhados
- `Navbar` — links, carrinho, notificações, menu do perfil
- `Sidebar` — navegação contextual
- `PageWrapper` — layout padrão com header e padding
- `ProtectedRoute` — redireciona para login se não autenticado

---

## 10. API Endpoints

### Autenticação
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/v1/auth/register` | Registar aluno |
| POST | `/v1/auth/verify-otp` | Verificar OTP |
| POST | `/v1/auth/login` | Login |
| POST | `/v1/auth/logout` | Logout (revogar token) |

### Catálogo
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/courses` | Listar cursos publicados (com filtros) |
| GET | `/v1/courses/{slug}` | Detalhe do curso |

### Matrículas e Sala de Aula
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/enrollments` | Cursos do aluno |
| GET | `/v1/classroom/{enrollmentId}` | Aulas do curso |
| POST | `/v1/classroom/{lessonId}/progress` | Atualizar progresso |
| POST | `/v1/classroom/{liveSessionId}/join` | Obter link da live (token assinado) |

### Carteira
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/wallet` | Saldo + transações |
| POST | `/v1/wallet/voucher` | Upload comprovativo |

### Carrinho
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/cart` | Ver carrinho |
| POST | `/v1/cart/items` | Adicionar curso |
| DELETE | `/v1/cart/items/{id}` | Remover item |
| POST | `/v1/cart/checkout` | Finalizar compra (wallet) |

### Certificados
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/v1/certificates/{enrollmentId}/issue` | Emitir certificado |
| GET | `/v1/certificates/{hash}/verify` | Verificar certificado (público) |

### Suporte
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/tickets` | Listar tickets |
| POST | `/v1/tickets` | Criar ticket |
| GET | `/v1/tickets/{id}` | Ver ticket + mensagens |
| POST | `/v1/tickets/{id}/messages` | Responder ticket |

---

## Regras de Negócio (Aluno)

- Aluno só pode comprar cursos publicados
- Aluno só pode ver aulas de cursos onde está matriculado (exceção: `is_free_preview`)
- Aula é marcada completa quando `watched_seconds ≥ duration_seconds × 0.9`
- Certificado emitido automaticamente quando 100% das aulas concluídas
- Ticket só pode ser aberto pelo próprio aluno
- Um ticket `closed` não pode ser reaberto
- Saldo da carteira nunca pode ser negativo
- Comprovativo aguarda aprovação manual do admin
- Aluno pode ter no máximo 1 matrícula ativa por curso
