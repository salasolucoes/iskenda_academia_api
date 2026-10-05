# Plano: Tipos de Aula — Vídeo (Lote), PDF e Ao Vivo

> **Objetivo:** Completar os 3 tipos de aula no `CourseBuilder`: vídeo (upload em lote), PDF (upload simples) e ao vivo (metadata + agendamento).

---

## Problema Atual

O `CourseBuilder` tem 3 botões de tipo (Vídeo, PDF, Ao Vivo), mas apenas **Vídeo** tem funcionalidade:

| Tipo | Upload/Form | Preview | Estado |
|------|-------------|---------|--------|
| Vídeo | ✅ Chunked upload | ✅ VideoPlayer | Funcional |
| PDF | ❌ Nenhum UI | ❌ Sem preview | Vazio |
| Ao Vivo | ❌ Nenhum UI | ❌ Sem config | Vazio |

---

## Solução

### 1. Vídeo — Upload em Lote

Adicionar botão **"Add Aulas em Lote"** para criar múltiplas aulas de vídeo de uma vez.

**Fluxo:**
1. Instrutor clica "Add Aulas em Lote" no módulo
2. Seleciona vários vídeos no FilePond (`multiple={true}`)
3. Sistema cria **todas as aulas primeiro** (DB inserts rápidos)
4. Depois faz upload dos vídeos **em paralelo** (2-3 simultâneos)
5. Progresso individual por ficheiro

**Backend:** Sem alteração — fluxo existente já suporta.

---

### 2. PDF — Upload Simples (sem chunked)

PDFs são pequenos (< 10MB). Chunked upload é desnecessário. Usar `multipart/form-data` direto.

**Fluxo:**
1. Instrutor seleciona tipo "PDF"
2. Aparece FilePond com `acceptedFileTypes={['application/pdf']}`
3. Instrutor seleciona ficheiro PDF
4. Ao clicar "Guardar Aula":
   - Cria aula via `POST /v1/instructor/modules/{moduleId}/lessons`
   - Upload simples via `POST /v1/instructor/lessons/{id}/upload` (multipart)
   - PDF guardado em `minio` em `pdfs/{moduleId}/{fileName}`

**Backend:** Criar endpoint `POST /v1/instructor/lessons/{id}/upload` que aceita `multipart/form-data` para ficheiros pequenos (PDFs, imagens). Não confundir com o chunked upload.

---

### 3. Ao Vivo — Metadata + Agendamento (sem criar LiveSession logo)

Uma aula ao vivo é um **evento**, não conteúdo estático. Não criar `LiveSession` no momento da criação da aula.

**Fluxo:**
1. Instrutor seleciona tipo "Ao Vivo"
2. Aparece form com campos:
   - `scheduled_at` (datetime) — data/hora de início
   - `duration_minutes` (número) — duração estimada
   - `external_link` (URL, opcional) — link Zoom/Meet
3. Ao clicar "Guardar Aula":
   - Cria aula via `POST /v1/instructor/modules/{moduleId}/lessons` com `type: 'live'`
   - Metadata guardada no `content_url` como JSON: `{"scheduled_at": "...", "external_link": "..."}`
   - `LiveSession` é criada **depois**, quando instrutor clica "Iniciar" ou automaticamente 15min antes

**Backend:** Sem alteração no momento. A `LiveSession` é criada pelo existing `LiveSessionController` quando necessário.

---

## Etapas de Implementação

### Etapa 1: Batch Upload (Vídeo)
- Adicionar estados: `batchUploading`, `batchProgress`, `batchModuleId`
- Adicionar botão "Add Aulas em Lote" ao lado de "Add Aula"
- Criar painel com FilePond `multiple={true}` para vídeos
- Função `handleBatchUpload()`:
  - Criar todas as aulas primeiro (Promise.all para inserts)
  - Upload em paralelo (máx 3 simultâneos com Promise pool)
- UI de progresso por ficheiro (⏳ ⬆️ ✅ ❌)
- Tratamento de erros: continuar se 1 falhar, retry individual

### Etapa 2: Upload PDF (simples)
- Backend: criar endpoint `POST /v1/instructor/lessons/{id}/upload` (multipart, max 20MB)
- Frontend: quando `newLessonType === 'pdf'`, mostrar FilePond com `acceptedFileTypes={['application/pdf']}`
- Upload via `multipart/form-data` (não chunked)
- Guardar em `pdfs/{moduleId}/{fileName}` no MinIO

### Etapa 3: Configuração Ao Vivo
- Quando `newLessonType === 'live'`, mostrar campos de data/hora e link externo
- Guardar metadata como JSON no `content_url` da aula
- Não criar `LiveSession` no momento — apenas metadata

### Etapa 4: Feedback e Limpeza
- Após conclusão, recarregar lista de módulos
- Toast de sucesso com contagem
- Limpar estado de upload

---

## Ficheiros a Alterar

| Ficheiro | Ação |
|----------|------|
| `src/pages/instructor/CourseBuilder.tsx` | Estados, botões, painéis para os 3 tipos |
| `src/services/instructorService.ts` | Adicionar `uploadPdf()` (multipart) |
| `app/Http/Controllers/Instructor/LessonController.php` | Adicionar endpoint `upload()` para PDF |
| `src/components/ui/FileUpload.tsx` | Sem alteração (já suporta `multiple`) |

---

## Backend

| Endpoint | Método | Tipo | Estado |
|----------|--------|------|--------|
| `POST /v1/instructor/modules/{moduleId}/lessons` | POST | Criar aula | ✅ Existe |
| `POST /v1/instructor/upload/init` | POST | Iniciar chunked (vídeo) | ✅ Existe |
| `POST /v1/instructor/upload/chunk` | POST | Enviar chunk | ✅ Existe |
| `POST /v1/instructor/upload/{sessionId}/complete` | POST | Finalizar chunked | ✅ Existe |
| `POST /v1/instructor/lessons/{id}/upload` | POST | Upload simples (PDF) | ⚠️ Criar |

---

## Referência no Plano de Implementação

```markdown
### Extras Frontend
- [ ] Upload múltiplos vídeos em lote (batch upload) — 1 vídeo = 1 aula automática
- [ ] Upload de PDF no CourseBuilder (upload simples, sem chunked)
- [ ] Configuração de aulas ao vivo no CourseBuilder (metadata only)
```
