# Plano: Perfil do Usuário para Todos os Perfis

> **Objetivo:** Permitir que qualquer usuário (estudante, instrutor, admin) **veja e edite** o próprio perfil: foto, bio, telefone, data de nascimento, links e endereço.

---

## Contexto

### Estado atual
- Tabela `users` já tem: `name`, `email`, `phone`, `avatar_url`, `role`
- Existe um `StudentProfilePage.tsx` (menu de conta), mas **sem edição de dados**
- **Não existem endpoints** de perfil (GET/PUT) nem use cases
- Não existe `StorageService` genérico para upload de avatar (apenas uploads de voucher/lesson nos controllers)
- `Admin\InstructorProfile`, `Admin\StudentProfile` são páginas de **gestão** (admin vê dados de outros), não do próprio usuário

### Gap a preencher
1. Novos campos de perfil: `bio`, `date_of_birth`, `links` (JSON), `address` (JSON ou colunas)
2. Endpoint `GET/PUT /v1/profile` (autenticado, qualquer role)
3. Endpoint `POST /v1/profile/avatar` (upload de foto)
4. Frontend: página de perfil editável para todos os roles (ou botão de edição na existente)

---

## Decisões de Design

### 1. Normalização do Banco

Todas as novas estruturas são **normalizadas em tabelas separadas** (nunca JSON), conforme pedido.

| Campo | Tipo | Onde |
|-------|------|------|
| `bio` | text nullable | Coluna `users.bio` |
| `date_of_birth` | date nullable | Coluna `users.date_of_birth` |
| `phone` | exists | Já é coluna `users.phone` |
| `avatar_url` | exists | Já é coluna `users.avatar_url` |
| **BI angolano** | string nullable | Coluna `users.bi_nr` (+ possíveis `bi_expiry`) |
| **Endereço** | normalizado | Tabela `addresses` (1:1 com users) |
| **Redes sociais** | normalizado | Tabela `social_links` (1:N com users) |

#### Migration 1: Adicionar colunas à `users`

```php
Schema::table('users', function (Blueprint $table) {
    $table->text('bio')->nullable()->after('avatar_url');
    $table->date('date_of_birth')->nullable()->after('bio');
    $table->string('bi_nr', 20)->nullable()->after('date_of_birth');
});
```

#### Migration 2: Tabela `addresses` (1:1)

```php
Schema::create('addresses', function (Blueprint $table) {
    $table->id();
    $table->uuid('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('line1')->nullable();       // rua / avenida
    $table->string('line2')->nullable();       // complemento
    $table->string('city')->nullable();        // cidade / município
    $table->string('province')->nullable();    // província (ex: Luanda)
    $table->string('country', 2)->default('AO'); // código ISO (Angola)
    $table->string('zip')->nullable();
    $table->timestamps();
});
```

#### Migration 3: Tabela `social_links` (1:N)

```php
Schema::create('social_links', function (Blueprint $table) {
    $table->id();
    $table->uuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('platform', 30);            // website, github, linkedin...
    $table->string('url', 255);
    $table->timestamps();

    $table->unique(['user_id', 'platform']);
});
```

**Relações na model `User`:**
```php
public function address(): HasOne { return $this->hasOne(Address::class); }
public function socialLinks(): HasMany { return $this->hasMany(SocialLink::class); }
```

### 2. Arquitetura (Clean Architecture)

Seguir o padrão do projeto (Domain puro + Application UseCase + Infrastructure Repository):

```
Domain\Profile\
  - Entities\UserProfile.php (ou extender via ValueObjects)
  - Contracts\UserProfileRepositoryInterface.php
Application\UseCases\Profile\
  - GetProfileUseCase.php
  - UpdateProfileUseCase.php
  - UpdateAvatarUseCase.php
Infrastructure\Persistence\Eloquent\Models\User.php (já existe — adicionar campos)
```

**Simplificação:** Como os campos vivem na tabela `users` e a entidade já existe (`App\Models\User`), o UseCase pode trabalhar com a model Eloquent diretamente via repositório. O `UserProfileRepositoryInterface` abstrai leitura/escrita.

### 3. Regras de negócio

- `bio`: max 500 chars
- `date_of_birth`: deve ser passado (não futuro)
- `bi_nr`: máximo 20 chars, alfanumérico
- `social_links`: tabela `social_links` com `platform` + `url`, máximo 6 por user (unique por user+platform)
- `address`: tabela `addresses` 1:1, `country` default `AO`, `province`/`city` opcionais
- `avatar_url`: gerado pelo serviço de upload, nunca recebido do client
- Qualquer role pode editar o **próprio** perfil (não o de outros)

### 4. Upload de avatar

- Tamanho máx: 2MB
- Tipos: `image/jpeg`, `image/png`, `image/webp`
- Validar MIME real (`mimetypes:`)
- **`AvatarStorageService`** criado em `app/Http/Services/`, respeitando SOLID (princípio de responsabilidade única → separado do controller), armazenando em MinIO via `Storage::disk('minio')`, bucket `iskenda`, path `avatars/{uuid}.{ext}`
- Salvar `avatar_url` (URL pública via `Storage::url()`)

---

## Endpoints

| Método | Rota | Auth | Ação |
|--------|------|------|------|
| `GET` | `/v1/profile` | `auth:sanctum` + `email.verified` | Ver próprio perfil completo |
| `PUT` | `/v1/profile` | idem | Editar dados do perfil |
| `POST` | `/v1/profile/avatar` | idem | Upload de foto de perfil |

Todos dentro do grupo `auth:sanctum` + `email.verified` existente em `routes/api.php:78`.

---

## Backend — Ficheiros

| Ficheiro | Ação |
|----------|------|
| `database/migrations/2026_08_XX_add_profile_fields_to_users_table.php` | **Novo**: `bio`, `date_of_birth`, `bi_nr` |
| `database/migrations/2026_08_XX_create_addresses_table.php` | **Novo**: tabela `addresses` |
| `database/migrations/2026_08_XX_create_social_links_table.php` | **Novo**: tabela `social_links` |
| `Domain/Profile/Contracts/UserProfileRepositoryInterface.php` | **Novo** |
| `Infrastructure/Persistence/Eloquent/Models/Address.php` | **Novo** |
| `Infrastructure/Persistence/Eloquent/Models/SocialLink.php` | **Novo** |
| `Infrastructure/Persistence/Eloquent/Repositories/EloquentUserProfileRepository.php` | **Novo** |
| `Application/UseCases/Profile/GetProfileUseCase.php` | **Novo** |
| `Application/UseCases/Profile/UpdateProfileUseCase.php` | **Novo** |
| `Application/UseCases/Profile/UpdateAvatarUseCase.php` | **Novo** |
| `app/Http/Controllers/Profile/ProfileController.php` | **Novo**: `show`, `update`, `uploadAvatar` |
| `app/Http/Resources/ProfileResource.php` | **Novo**: formata resposta |
| `app/Http/Services/AvatarStorageService.php` | **Novo**: upload avatar MinIO (SOLID/SRP) |
| `Infrastructure/Persistence/Eloquent/Models/User.php` | Adicionar campos + relações `address()`, `socialLinks()` |
| `App\Models\User.php` | Atualizar `#[Fillable]` |
| `routes/api.php` | Adicionar rotas profile |
| `bootstrap/app.php` | bind interface → impl |

### ProfileResource (resposta)

```json
{
  "data": {
    "id": "...",
    "name": "João",
    "email": "joao@email.com",
    "role": "student",
    "phone": "+244...",
    "avatar_url": "https://...",
    "bio": "Sou estudante de ...",
    "date_of_birth": "2000-05-15",
    "bi_nr": "00486213BA045",
    "address": {
      "line1": "Rua 10",
      "line2": null,
      "city": "Luanda",
      "province": "Luanda",
      "country": "AO",
      "zip": null
    },
    "social_links": [
      { "platform": "linkedin", "url": "https://..." },
      { "platform": "github", "url": "https://..." }
    ],
    "created_at": "..."
  }
}
```

---

## Frontend — Ficheiros

| Ficheiro | Ação |
|----------|------|
| `src/services/profileService.ts` | **Novo**: `get()`, `update()`, `uploadAvatar()` |
| `src/types/index.ts` | Adicionar tipo `UserProfile` |
| `src/store/authSlice.ts` | Adicionar `updateProfile` action (atualiza `user`) |
| `src/pages/student/StudentProfilePage.tsx` | Adicionar secção de edição do perfil |
| `src/pages/instructor/ProfilePage.tsx` | **Novo** (ou reutilizar componente) |
| `src/pages/admin/ProfilePage.tsx` | **Novo** (ou reutilizar componente) |
| `src/App.tsx` | Adicionar rotas `/profile`, `/instructor/profile`, `/admin/profile` |
| `src/utils/` | (reutilizar) resolver avatar url |

**Componente reutilizável:** `src/components/profile/ProfileForm.tsx` — formulário único usado pelos 3 roles (evita duplicação).

### Rotas frontend

| Rota | Role |
|------|------|
| `/profile` | student |
| `/instructor/profile` | instructor |
| `/admin/profile` | admin |

---

## Ordem de Implementação

| # | Tarefa | Prioridade | Dependências |
|---|--------|------------|--------------|
| 1 | Migration campos perfil | Alta | Nenhuma |
| 2 | Model `User` + fillable/casts | Alta | #1 |
| 3 | Repository + interface + bind | Alta | #2 |
| 4 | UseCases (get/update/avatar) | Alta | #3 |
| 5 | Controller + Resource + rotas | Alta | #4 |
| 6 | AvatarStorageService | Média | #4 |
| 7 | Backend testes (feature) | Alta | #5 |
| 8 | Frontend service + types + authSlice | Alta | #5 |
| 9 | ProfileForm componente | Alta | #8 |
| 10 | Páginas por role + rotas | Média | #9 |
| 11 | Teste manual / build | Média | #10 |

---

## Testes (Pest)

- `ProfileTest`: GET retorna perfil, PUT atualiza campos, validaçao (bio max, date futuro, links inválidos)
- `ProfileAvatarTest`: upload válido (mock storage), upload tipo inválido, upload > 2MB, validação MIME real
- Teste de permissão: usuário não pode ver/editar perfil de outro (404/403)
- `ProfileUseCaseTest` (unit): regras de negócio
- Testes para os 3 roles (student/instructor/admin)

---

## Estimativa

| Módulo | Esforço |
|--------|---------|
| Backend (migration → rotas) | ~2h |
| Avatar upload + storage | ~45min |
| Backend testes | ~1h |
| Frontend service + store | ~30min |
| ProfileForm + 3 páginas | ~1.5h |
| **Total** | **~5h** |

---

## Decisões Finais (confirmadas)

1. **Endereço:** tabela normalizada `addresses` (1:1) — `line1, line2, city, province, country(AO default), zip`
2. **BI angolano:** coluna `users.bi_nr` (20 chars alfanumérico)
3. **Redes sociais:** tabela `social_links` (1:N) — `platform` + `url`, unique(user_id, platform)
4. **AvatarStorageService:** serviço SOLID/SRP em `app/Http/Services/`, usa `Storage::disk('minio')`
5. **Frontend:** 3 páginas separadas — `/profile` (student), `/instructor/profile`, `/admin/profile`, partilhando `ProfileForm.tsx`
