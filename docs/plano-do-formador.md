# Plano do Formador — Iskenda Academy

> Jornada completa do instrutor: gestão de cursos, aula ao vivo, acompanhamento de alunos.

---

## Índice

1. [Fluxo Principal](#1-fluxo-principal)
2. [Dashboard](#2-dashboard)
3. [Gestão de Cursos](#3-gestão-de-cursos)
4. [Construtor de Conteúdo](#4-construtor-de-conteúdo)
5. [Aulas ao Vivo](#5-aulas-ao-vivo)
6. [Acompanhamento de Alunos](#6-acompanhamento-de-alunos)
7. [Páginas (Frontend)](#7-páginas-frontend)
8. [API Endpoints](#8-api-endpoints)

---

## 1. Fluxo Principal

```
Login → Dashboard → Gerir Cursos → Criar/Editar Curso
→ Adicionar Módulos → Adicionar Aulas → Publicar
→ Agendar Live → Acompanhar Progresso dos Alunos
```

---

## 2. Dashboard (`/instructor/dashboard`)

### Indicadores
- Total de cursos publicados
- Total de alunos matriculados (todos os cursos)
- Aulas ao vivo agendadas para hoje
- Últimas matrículas (aluno, curso, data)

### Ações rápidas
- Criar novo curso
- Próxima live agendada
- Ver cursos

---

## 3. Gestão de Cursos (`/instructor/courses`)

### Listagem
- Grid/tabela dos cursos do instrutor
- Filtros: status (draft, published, archived)
- Ações: editar, ver alunos, agendar live

### Estados do Curso
| Estado | Descrição |
|--------|-----------|
| `draft` | Rascunho — invisível no catálogo |
| `published` | Publicado — visível e disponível para compra |
| `archived` | Arquivado — apenas admin, alunos mantêm acesso |

- Apenas o instrutor dono ou admin podem publicar
- `CourseDomainService->canPublish()`: ≥ 1 módulo, ≥ 1 aula, instrutor atribuído

---

## 4. Construtor de Conteúdo (`/instructor/builder/:id`)

### Estrutura
```
Curso
├── Módulo 1 (position: 1)
│   ├── Aula 1 (video/pdf/live, position: 1)
│   ├── Aula 2 (video/pdf/live, position: 2)
│   └── ...
├── Módulo 2 (position: 2)
│   ├── Aula 1
│   └── ...
└── ...
```

### Operações
- **Módulos**: criar, renomear, reordenar (drag & drop), remover
- **Aulas**: criar, editar título, tipo (video/pdf/live), upload de ficheiro, reordenar, remover
- **Previsualização gratuita**: marcar aula como `is_free_preview`
- **Upload**: videoaulas e PDFs armazenados em object storage (MinIO/Bunny)

### Regras
- Curso precisa de pelo menos 1 módulo com 1 aula para ser publicado
- `price_cents` em centavos (0 = gratuito)

---

## 5. Aulas ao Vivo (`/instructor/live`)

### Agendamento
- Criar sessão ao vivo: título, data/hora, duração prevista
- Associada a uma aula do tipo `live` num curso
- Alunos matriculados notificados automaticamente

### WebRTC + Reverb + coturn
- **Signaling**: Laravel Reverb (troca de SDP/ICE candidates)
- **TURN/STUN**: coturn (portas 3478/5349) para ultrapassar NATs
- Instrutor partilha ecrã, áudio, vídeo
- Alunos assistem via browser (peer-to-peer com fallback TURN)

### Fluxo da Live
1. Instrutor agenda aula ao vivo
2. À hora marcada, instrutor entra na sala (clica "Iniciar Transmissão")
3. Alunos recebem `LiveStartingSoonEvent` (15 min antes)
4. Alunos clicam "Entrar na Aula" → token assinado (HMAC-SHA256, TTL 30 min)
5. Conexão WebRTC estabelecida

---

## 6. Acompanhamento de Alunos (`/instructor/progress`)

### Visão Geral
- Tabela: aluno, curso, percentual de progresso, última atividade
- Filtro por curso
- Busca por nome do aluno

### Detalhe do Aluno
- Progresso aula a aula (completada/não completada)
- Tempo total assistido
- Data da última atividade

---

## 7. Páginas (Frontend)

| Rota | Página | Descrição |
|------|--------|-----------|
| `/instructor/dashboard` | InstructorDashboard | Métricas e ações rápidas |
| `/instructor/courses` | MyCourses | Listagem e gestão de cursos |
| `/instructor/builder/:id` | CourseBuilder | Construtor de módulos e aulas |
| `/instructor/progress` | StudentProgress | Progresso dos alunos |
| `/instructor/live` | LiveSchedule | Agendamento e gestão de lives |

### Componentes Compartilhados
- `Navbar` — links, notificações, menu do perfil
- `Sidebar` — navegação do formador
- `ProtectedRoute` — redireciona para login se não autenticado

---

## 8. API Endpoints

### Dashboard
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/instructor/dashboard` | Métricas do formador |

### Cursos
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/instructor/courses` | Listar cursos do formador |
| POST | `/v1/instructor/courses` | Criar curso |
| PUT | `/v1/instructor/courses/{id}` | Atualizar curso |
| DELETE | `/v1/instructor/courses/{id}` | Remover curso (apenas draft) |

### Módulos
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/v1/instructor/courses/{id}/modules` | Criar módulo |
| PUT | `/v1/instructor/modules/{id}` | Atualizar módulo |
| DELETE | `/v1/instructor/modules/{id}` | Remover módulo |

### Aulas
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/v1/instructor/modules/{id}/lessons` | Criar aula |
| PUT | `/v1/instructor/lessons/{id}` | Atualizar aula |
| DELETE | `/v1/instructor/lessons/{id}` | Remover aula |
| POST | `/v1/instructor/lessons/{id}/upload` | Upload de ficheiro |

### Lives
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/instructor/live` | Listar lives agendadas |
| POST | `/v1/instructor/live` | Agendar live |
| PUT | `/v1/instructor/live/{id}` | Atualizar live |
| POST | `/v1/instructor/live/{id}/start` | Iniciar transmissão |
| POST | `/v1/instructor/live/{id}/end` | Encerrar transmissão |

### Alunos
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/v1/instructor/students` | Listar alunos (todos os cursos) |
| GET | `/v1/instructor/students/{id}/progress` | Progresso detalhado do aluno |

---

## Regras de Negócio (Formador)

- Formador só gere os seus próprios cursos
- Curso em `draft` só o formador vê
- Publicação requer ≥ 1 módulo com ≥ 1 aula
- Apenas o formador dono ou admin podem publicar o curso
- Aulas ao vivo requerem agendamento prévio
- Upload máximo 5GB por videoaula (definido no Nginx)
- Formador não pode apagar cursos com matrículas ativas
