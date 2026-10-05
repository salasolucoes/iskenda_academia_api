# Plano — Corrigir Validação de Senhas

> Requisito: senhas devem conter **no mínimo 1 maiúscula, 1 minúscula e 1 carácter especial**.
> OTP gerado para instrutores também deve seguir estas regras (senha temporária forte).
> Aplica-se a: registro de aluno, OTP de instrutor, setup de instrutor, set-password de instrutor.

---

## 1. Problema Atual

A validação de senhas está **fraca e inconsistente** — aceita qualquer coisa com ≥8 caracteres. O OTP gerado para instrutores é apenas numérico (6 dígitos), não seguindo as regras de segurança.

### Onde a validação hoje é feita (e o que falta)

| # | Endpoint / Serviço | Controller / Use Case | Regra Atual | Falta |
|---|---------------------|----------------------|-------------|-------|
| 1 | `POST /auth/register` | `Student\AuthController:30` | `min:8, confirmed` | Maiúscula, minúscula, especial |
| 2 | `RegisterStudentUseCase:21` | Domain service | `strlen >= 8` | Maiúscula, minúscula, especial |
| 3 | `POST /auth/instructor/complete` | `InstructorAuthController:47` | `min:8, confirmed` | Maiúscula, minúscula, especial |
| 4 | `CompleteInstructorSetupUseCase:31` | Use case inline | `strlen < 8` | Maiúscula, minúscula, especial |
| 5 | `PATCH /auth/instructor/set-password` | `InstructorAuthController:74` | `min:8, confirmed` | Maiúscula, minúscula, especial |
| 6 | `POST /admin/users` | `Admin\UserController:71` | `min:6` (nullable!) | Tudo — regra mais fraca do projeto |
| 7 | `AuthDomainService::generateOtp()` | Domain service | 6 dígitos numéricos | Deve gerar senha forte (maiúscula + minúscula + especial) |

### Contradadições

- `RegisterStudentUseCase` chama `AuthDomainService::isPasswordStrongEnough()` mas o método só verifica `strlen >= 8`
- `CompleteInstructorSetupUseCase` faz `strlen < 8` inline, **sem** usar o domain service
- `Admin\UserController` usa `min:6` e fallback `bcrypt('password')` — zero segurança
- Nenhum dos 3 pontos de validação rejeita senhas como `12345678`, `aaaaaaaa`, ` senha!@#`
- **OTP de instrutor é `048291` (6 dígitos numéricos)** — se o instrutor usar como senha permanente, não atende nenhuma regra de segurança

### OTP sem instruções de senha

O email de OTP enviado pelo `OtpService` (`Infrastructure/Services/OtpService.php:15-23`) diz ao formador para "definir a palavra-passe permanente" mas **não informa os requisitos da senha**. O formador vai definir a senha, receber erro de validação, e não saber porquê.

---

## 2. Regra de Negócio (Nova)

### 2.1 Senha Permanente

```
Senha deve conter:
  ✓ No mínimo 8 caracteres
  ✓ Pelo menos 1 letra maiúscula (A-Z)
  ✓ Pelo menos 1 letra minúscula (a-z)
  ✓ Pelo menos 1 carácter especial (!@#$%^&*... ou qualquer não-alfanumérico)
  ✓ Confirmação obrigatória (confirmed)
```

### 2.2 OTP para Instrutores (Senha Temporária)

O OTP gerado para instrutores deve ser uma **senha forte** (não apenas 6 dígitos):

```
OTP deve conter:
  ✓ No mínimo 8 caracteres
  ✓ Pelo menos 1 letra maiúscula (A-Z)
  ✓ Pelo menos 1 letra minúscula (a-z)
  ✓ Pelo menos 1 carácter especial (!@#$%^&*)
  ✓ Formato: código alfanumérico com especial (ex: Kx9#mP2v)
```

**Porquê:** O instrutor faz login com o OTP como senha temporária. Se o OTP for fraco (`048291`), o instrutor pode tentar usá-lo como senha permanente e o sistema rejeita — confuso. Com OTP forte, o instrutor já recebe uma senha segura.

### 2.3 OTP para Alunos (Verificação de Email)

O OTP para alunos continua **numérico (6 dígitos)** — é usado apenas para verificação de email, não como senha.

```
OTP numérico: 048291 (6 dígitos)
```

**Porquê:** O aluno não faz login com o OTP — ele verifica o email e depois define senha própria no registro.

Exemplos de OTPs **inválidos**:
- `048291` — apenas números
- `abcdef` — apenas minúsculas
- `ABCDEF` — apenas maiúsculas
- `Senha12` — sem carácter especial

Exemplos de OTPs **válidos**:
- `Kx9#mP2v` ✓
- `Ab1@cdEF` ✓
- `P@ssw0rd` ✓

---

## 3. Estratégia de Implementação

### 3.1 Domain Layer — Fonte da Verdade

**Ficheiro:** `Domain/Auth/Services/AuthDomainService.php`

#### 3.1.1 Método `isPasswordStrongEnough()` (linhas 14-17)

Deve conter TODA a regra de validação:

```php
public function isPasswordStrongEnough(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    if (! preg_match('/[A-Z]/', $password)) {
        return false;
    }

    if (! preg_match('/[a-z]/', $password)) {
        return false;
    }

    if (! preg_match('/[^a-zA-Z0-9]/', $password)) {
        return false;
    }

    return true;
}
```

#### 3.1.2 Método `generateOtp()` (linhas 9-12)

Manter para **alunos** (numérico 6 dígitos):

```php
public function generateOtp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
```

#### 3.1.3 Novo método `generateStrongOtp()` (para instrutores)

Gerar senha forte com maiúscula + minúscula + especial:

```php
public function generateStrongOtp(): string
{
    $uppercase = chr(random_int(65, 90));   // A-Z
    $lowercase = chr(random_int(97, 122));  // a-z
    $special = ['!', '@', '#', '$', '%', '^', '&', '*'][random_int(0, 7)];

    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $length = 8;

    // Preencher resto com caracteres aleatórios
    $otp = $uppercase . $lowercase . $special;
    for ($i = strlen($otp); $i < $length; $i++) {
        $otp .= $chars[random_int(0, strlen($chars) - 1)];
    }

    // Embaralhar para não ter padrão previsível
    return str_shuffle($otp);
}
```

**Garantia:** O OTP de instrutor sempre contém 1 maiúscula + 1 minúscula + 1 especial + comprimento ≥ 8.

### 3.2 Application Layer — Usar o Domain Service

**Ficheiro:** `Application/UseCases/Admin/CompleteInstructorSetupUseCase.php:31-33`

Substituir:
```php
if (strlen($password) < 8) {
    throw new \InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
}
```

Por:
```php
if (! $this->authDomainService->isPasswordStrongEnough($password)) {
    throw new \InvalidArgumentException(
        'A senha deve ter pelo menos 8 caracteres, incluindo uma maiúscula, uma minúscula e um carácter especial.'
    );
}
```

**Obs:** Este use case precisa receber `AuthDomainService` via construtor (DI). Atualmente não o injeta.

### 3.2.1 Validação OTP no Controller

**Ficheiro:** `app/Http/Controllers/Auth/InstructorAuthController.php:46`

A validação `otp_code` usa `size:6` mas o OTP agora é alfanumérico com 8+ caracteres. Atualizar para:
```php
'otp_code' => ['required', 'string', 'min:8', 'max:12'],
```

### 3.3 HTTP Layer — Validação Laravel nos Controllers

Usar `Rules\Password::defaults()` para manter a validação Laravel espelhada com o domínio.

#### 3.3.1 `app/Providers/AppServiceProvider.php`

Adicionar configuração global de password:

```php
use Illuminate\Validation\Rules\Password;

public function boot(): void
{
    Password::defaults(function () {
        return Password::min(8)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->uncompromised();
    });
}
```

**Nota:** `mixedCase()` exige pelo menos 1 maiúscula + 1 minúscula. Para o carácter especial, adicionar regra customizada ou usar `regex:/[^a-zA-Z0-9]/`.

#### 3.3.2 Controllers — Aplicar regra

| Controller | Linha | Mudança |
|-----------|-------|---------|
| `Student\AuthController` | 30 | `'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()]` |
| `InstructorAuthController` | 46 | `'otp_code' => ['required', 'string', 'min:8', 'max:12']` (era `size:6`) |
| `InstructorAuthController` | 47 | `'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()]` |
| `InstructorAuthController` | 74 | `'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()]` |
| `Admin\UserController` | 71 | `'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()]` |

**Mudança adicional em `Admin\UserController`:**
- Remover fallback `bcrypt('password')` — senha deve ser sempre fornecida
- Tornar `password` required em vez de nullable

### 3.4 OTP Email — Mensagens Diferentes por Papel

**Ficheiro:** `Infrastructure/Services/OtpService.php`

O `OtpService` atual envia o mesmo email para aluno e instrutor. Deve ser atualizado para:
1. Aceitar parâmetro `role` (ou `isInstructor`)
2. Enviar emails diferentes conforme o papel

#### Email para Aluno (OTP numérico — verificação)

```
Assunto: Código de Verificação — Iskenda Academy

Olá,

Recebemos o seu pedido de verificação na Iskenda Academy.

O seu código de verificação é: 048291

Use este código para confirmar o seu email.

Este código é válido por 10 minutos.

Se não solicitou este código, ignore este e-mail.

Atenciosamente,
Equipa Iskenda Academy
```

#### Email para Instrutor (OTP forte — senha temporária)

```
Assunto: Palavra-passe Temporária — Iskenda Academy

Olá,

A sua palavra-passe temporária é: Kx9#mP2v

Faça login com o seu email e esta palavra-passe.

⚠️ O sistema irá pedir-lhe que defina uma nova palavra-passe.
A sua nova palavra-passe DEVE conter:
   ✓ No mínimo 8 caracteres
   ✓ Pelo menos 1 letra MAIÚSCULA (A-Z)
   ✓ Pelo menos 1 letra minúscula (a-z)
   ✓ Pelo menos 1 carácter especial (!@#$%^&*)

Este código é válido por 10 minutos.

Se não solicitou este e-mail, ignore-o.

Atenciosamente,
Equipa Iskenda Academy
```

#### Mudança na interface `OtpSender`

**Ficheiro:** `Application/UseCases/Auth/OtpSender.php`

```php
interface OtpSender
{
    public function send(string $email, string $otpCode, bool $isInstructor = false): void;
}
```

O parâmetro `isInstructor` define qual email enviar. Default `false` (aluno).

### 3.5 Mensagem de Erro Padronizada

Todas as camadas devem retornar a mesma mensagem de erro:

```
'A senha deve ter pelo menos 8 caracteres, incluindo uma maiúscula, uma minúscula e um carácter especial.'
```

---

## 4. Ficheiros a Modificar

| # | Ficheiro | Mudança | Prioridade |
|---|----------|---------|:----------:|
| 1 | `Domain/Auth/Services/AuthDomainService.php` | Atualizar `isPasswordStrongEnough()` + adicionar `generateStrongOtp()` | Alta |
| 2 | `Application/UseCases/Auth/OtpSender.php` | Adicionar parâmetro `bool $isInstructor = false` | Alta |
| 3 | `Application/UseCases/Admin/CompleteInstructorSetupUseCase.php` | Injetar domain service, usar `isPasswordStrongEnough()` | Alta |
| 4 | `app/Providers/AppServiceProvider.php` | Configurar `Password::defaults()` | Alta |
| 5 | `app/Http/Controllers/Student/AuthController.php` | Adicionar `Password::defaults()` na regra | Alta |
| 6 | `app/Http/Controllers/Auth/InstructorAuthController.php` | Adicionar `Password::defaults()` + atualizar validação OTP (`size:6` → `min:8`) | Alta |
| 7 | `app/Http/Controllers/Admin/UserController.php` | Tornar password required, adicionar regra forte | Média |
| 8 | `Infrastructure/Services/OtpService.php` | Implementar emails diferentes por papel + usar `generateStrongOtp()` para instrutores | Alta |
| 9 | `Application/UseCases/Auth/RegisterStudentUseCase.php` | Chamar `generateOtp()` (numérico) | Alta |
| 10 | `Application/UseCases/Admin/CreateInstructorUseCase.php` | Chamar `generateStrongOtp()` | Alta |
| 11 | `tests/Feature/AuthRegistrationTest.php` | Atualizar senhas de teste | Alta |
| 12 | `tests/Feature/AdminInstructorManagementTest.php` | Atualizar OTPs e senhas de teste | Alta |
| 13 | `tests/Feature/UserRegistrationTest.php` | Verificar e atualizar senhas | Alta |
| 14 | `tests/Unit/AuthDomainServiceTest.php` | Novo — testar `isPasswordStrongEnough()` + `generateOtp()` + `generateStrongOtp()` | Alta |

---

## 5. Testes

### 5.1 Testes Unitários — `AuthDomainServiceTest` (novo)

Criar `tests/Unit/AuthDomainServiceTest.php`:

```php
// isPasswordStrongEnough()
test('password without uppercase is rejected')
test('password without lowercase is rejected')
test('password without special character is rejected')
test('password shorter than 8 chars is rejected')
test('password with all requirements passes')
test('password with exactly 8 chars and all requirements passes')

// generateOtp() — numérico para alunos
test('otp for student is numeric')
test('otp for student is 6 digits')

// generateStrongOtp() — forte para instrutores
test('otp for instructor contains at least one uppercase letter')
test('otp for instructor contains at least one lowercase letter')
test('otp for instructor contains at least one special character')
test('otp for instructor is at least 8 characters long')
test('otp for instructor is unique on each generation')
```

### 5.2 Testes de Feature — Atualizar existentes

**`AuthRegistrationTest.php`:**
- `password123` → `S3nh@F0rte` (ou similar com maiúscula + minúscula + especial)
- Adicionar testes de rejeição para cada requisito

**`AdminInstructorManagementTest.php`:**
- `nova-senha-segura` → `N0va-S3nh@!` (ou similar)
- Adicionar testes de rejeição no setup de instrutor

### 5.3 Novos Testes de Feature — `PasswordValidationTest.php`

```php
test('registration rejects password without uppercase')
test('registration rejects password without lowercase')
test('registration rejects password without special character')
test('registration rejects password shorter than 8 chars')
test('registration accepts password with all requirements')
test('instructor complete setup rejects weak password')
test('instructor set-password rejects weak password')
test('admin create user rejects weak password')
```

### 5.4 Testes de OTP Email — Verificar conteúdo por papel

```php
test('otp email for student contains numeric code only')
test('otp email for student does not mention password requirements')
test('otp email for instructor contains strong password')
test('otp email for instructor includes password requirements')
```

Usar `Mail::fake()` para capturar o email enviado e verificar:
- Aluno: OTP numérico, sem menção a requisitos de senha
- Instrutor: OTP alfanumérico forte, com requisitos de senha listados

---

## 6. Ordem de Execução

1. **Domain**: Atualizar `AuthDomainService::isPasswordStrongEnough()` + adicionar `generateStrongOtp()`
2. **Interface**: Atualizar `OtpSender` com parâmetro `isInstructor`
3. **Application**: Injetar domain service no `CompleteInstructorSetupUseCase`
4. **Application**: Atualizar `RegisterStudentUseCase` (chamar `generateOtp()`) e `CreateInstructorUseCase` (chamar `generateStrongOtp()`)
5. **Provider**: Configurar `Password::defaults()` no `AppServiceProvider`
6. **Controllers**: Adicionar regra nos 4 controllers + atualizar validação OTP
7. **OTP Service**: Implementar emails diferentes por papel
8. **Tests (existentes)**: Atualizar OTPs e passwords nos testes existentes para passarem
9. **Tests (novos)**: Criar `AuthDomainServiceTest.php` + `PasswordValidationTest.php`
10. **Pint**: Rodar `vendor/bin/pint --dirty --format agent`
11. **Test**: Rodar `php artisan test --compact` para validar tudo

---

## 7. Riscos e Considerações

| Risco | Mitigação |
|-------|-----------|
| Senhas existentes no banco podem ser fracas | Não alterar senhas existentes — nova regra aplica-se apenas a novas senhas |
| `Password::defaults()` pode conflitar com config existente | Verificar se já existe config em `AppServiceProvider` antes de adicionar |
| `uncompromised()` depende de API HaveIBeenPwned | Pode causar timeout em testes — usar `->uffle()` ou desabilitar em teste se necessário |
| `Admin\UserController` com fallback `'password'` hardcoded | Remover fallback —admin deve definir senha explicitamente |
| Mudança na interface `OtpSender` quebra implementações existentes | Atualizar `OtpService` e todos os chamadores |
| OTP de instrutor agora é alfanumérico — validação `size:6` no controller falha | Atualizar para `min:8, max:12` |

---

## 8. Critérios de Aceite

- [x] `AuthDomainService::isPasswordStrongEnough()` rejeita senhas sem maiúscula, minúscula ou especial
- [x] `AuthDomainService::generateOtp()` gera OTP numérico (6 dígitos) para alunos
- [x] `AuthDomainService::generateStrongOtp()` gera OTP com maiúscula + minúscula + especial + ≥8 caracteres para instrutores
- [x] Email de aluno: contém OTP numérico, sem mencionar requisitos de senha
- [x] Email de instrutor: contém OTP forte + requisitos de senha (maiúscula, minúscula, especial)
- [x] `OtpSender::send()` aceita parâmetro `isInstructor` para definir tipo de email
- [x] Todos os endpoints de definição de senha usam a mesma regra
- [x] `Password::defaults()` configurado no `AppServiceProvider`
- [x] `Admin\UserController::store()` exige senha (não nullable) com regra forte
- [x] Todos os testes existentes passam com OTPs e senhas atualizadas
- [x] Novos testes cobrem geração de OTP forte e rejeição de senha fraca
- [x] Mensagem de erro padronizada em todas as camadas
- [x] `vendor/bin/pint --dirty --format agent` passa sem erros
