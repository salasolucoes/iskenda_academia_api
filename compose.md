# Guia de Referência — Docker Compose (Laravel Sail)

> **Projeto:** `api` (iskenda academy)  
> **Arquivo:** `compose.yaml`  
> **Namespace dos containers:** `api-{servico}-1` (ex: `api-laravel.test-1`, `api-pgsql-1`)

---

## 1. Iniciar / Parar containers

```powershell
# Iniciar todos os serviços (modo detached)
docker compose -f compose.yaml up -d

# Iniciar apenas serviços específicos
docker compose -f compose.yaml up -d pgsql redis meilisearch laravel.test

# Parar todos os containers (sem remover volumes)
docker compose -f compose.yaml down

# Parar e remover volumes (banco, redis, meilisearch perdem dados)
docker compose -f compose.yaml down -v

# Visualizar containers em execução
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"
```

---

## 2. Comandos Artisan dentro do container

```powershell
# Padrão geral
docker compose -f compose.yaml exec laravel.test php artisan {comando}

# Exemplos
docker compose -f compose.yaml exec laravel.test php artisan migrate
docker compose -f compose.yaml exec laravel.test php artisan migrate:fresh
docker compose -f compose.yaml exec laravel.test php artisan migrate:fresh --seed
docker compose -f compose.yaml exec laravel.test php artisan tinker
docker compose -f compose.yaml exec laravel.test php artisan route:list
docker compose -f compose.yaml exec laravel.test php artisan route:list --path=api
docker compose -f compose.yaml exec laravel.test php artisan make:model NomeModel -mf
docker compose -f compose.yaml exec laravel.test php artisan make:controller Api\NomeController
docker compose -f compose.yaml exec laravel.test php artisan test --compact
docker compose -f compose.yaml exec laravel.test php artisan config:cache
docker compose -f compose.yaml exec laravel.test php artisan config:clear
docker compose -f compose.yaml exec laravel.test php artisan cache:clear
docker compose -f compose.yaml exec laravel.test php artisan queue:work
docker compose -f compose.yaml exec laravel.test php artisan storage:link
```

---

## 3. Composer dentro do container

```powershell
# Instalar dependências (já existentes no composer.lock)
docker compose -f compose.yaml exec laravel.test composer install

# Instalar sem a execução de scripts (útil em CI / primeira instalação)
docker compose -f compose.yaml exec laravel.test composer install --no-scripts

# Adicionar um novo pacote
docker compose -f compose.yaml exec laravel.test composer require laravel/pint --dev

# Remover um pacote
docker compose -f compose.yaml exec laravel.test composer remove laravel/pint --dev

# Regenerar o autoload
docker compose -f compose.yaml exec laravel.test composer dump-autoload

# Atualizar dependências (cuidado: altera composer.lock)
docker compose -f compose.yaml exec laravel.test composer update
```

---

## 4. NPM dentro do container

```powershell
# Instalar dependências Node
docker compose -f compose.yaml exec laravel.test npm install

# Build de produção (Vite)
docker compose -f compose.yaml exec laravel.test npm run build

# Servidor de desenvolvimento (hot reload)
docker compose -f compose.yaml exec laravel.test npm run dev
```

---

## 5. Logs

```powershell
# Logs de todos os serviços (modo follow)
docker compose -f compose.yaml logs -f

# Logs de um serviço específico
docker compose -f compose.yaml logs -f laravel.test
docker compose -f compose.yaml logs -f pgsql
docker compose -f compose.yaml logs -f redis
docker compose -f compose.yaml logs -f meilisearch

# Últimas N linhas
docker compose -f compose.yaml logs -f --tail=50 laravel.test

# Logs de erro do Laravel (via Pail, dentro do container)
docker compose -f compose.yaml exec laravel.test php artisan pail
```

---

## 6. Banco de Dados (PostgreSQL 18)

```powershell
# Acessar o psql interativo
docker compose -f compose.yaml exec pgsql psql -U postgres -d iskenda

# Listar todos os databases
docker compose -f compose.yaml exec pgsql psql -U postgres -l

# Executar um comando SQL direto
docker compose -f compose.yaml exec pgsql psql -U postgres -d iskenda -c "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';"

# Dump do banco para um arquivo (salva no host)
docker compose -f compose.yaml exec pgsql pg_dump -U postgres -d iskenda > dump.sql

# Restore (a partir de um dump no host)
cat dump.sql | docker compose -f compose.yaml exec -T pgsql psql -U postgres -d iskenda

# Reset rápido (apaga tudo e recria as tabelas)
docker compose -f compose.yaml exec laravel.test php artisan migrate:fresh --seed
```

---

## 7. Testes (Pest)

```powershell
# Rodar todos os testes
docker compose -f compose.yaml exec laravel.test php artisan test --compact

# Rodar um arquivo ou método específico
docker compose -f compose.yaml exec laravel.test php artisan test --compact --filter=NomeDoTeste
docker compose -f compose.yaml exec laravel.test php artisan test --compact --filter="test_user_can_register"

# Rodar testes em paralelo
docker compose -f compose.yaml exec laravel.test php artisan test --parallel

# Rodar sem a saída compacta (mais detalhes)
docker compose -f compose.yaml exec laravel.test php artisan test
```

---

## 8. Acessar o container (bash)

```powershell
# Terminal interativo no container da aplicação
docker compose -f compose.yaml exec laravel.test bash

# Terminal no PostgreSQL
docker compose -f compose.yaml exec pgsql bash

# Sair do container: digite exit
```

---

## 9. Build / Rebuild

```powershell
# Construir (ou reconstruir) a imagem da aplicação
docker compose -f compose.yaml build laravel.test

# Reconstruir sem usar cache
docker compose -f compose.yaml build --no-cache laravel.test

# Reconstruir e já subir o serviço
docker compose -f compose.yaml up -d --build laravel.test

# Baixar as imagens mais recentes dos serviços (sem rebuild)
docker compose -f compose.yaml pull pgsql redis meilisearch
```

---

## 10. Dicas

| Config | Valor no `.env` | Observação |
|---|---|---|
| **DB_HOST** | `pgsql` | Nome do serviço no Docker |
| **REDIS_HOST** | `redis` | Nome do serviço no Docker |
| **MEILISEARCH_HOST** | `http://meilisearch:7700` | Nome do serviço no Docker |
| **WWWUSER** | `1000` | Deve ser seu UID local |
| **WWWGROUP** | `1000` | Deve ser seu GID local |

- **PowerShell:** `docker compose` funciona nativamente no Windows — não precisa de WSL ou Git Bash para os comandos acima. O script `vendor/bin/sail` é bash-only; se estiver no Windows, use os comandos `docker compose` diretamente.

- **Rodar localmente (fora do Docker):** Altere as variáveis de ambiente no `.env`:
  ```
  DB_HOST=127.0.0.1
  REDIS_HOST=127.0.0.1
  MEILISEARCH_HOST=http://127.0.0.1:7700
  ```
  E tenha PostgreSQL, Redis e Meilisearch rodando na máquina host nas portas padrão.

- **Permissões de arquivo:** Se encontrar erros de permissão ao criar/editar arquivos, confira se `WWWUSER` e `WWWGROUP` no `.env` correspondem ao seu usuário local. No Windows, geralmente `1000` funciona bem.

- **Cache de configuração:** Depois de alterar o `.env` dentro do container, lembre-se de rodar `php artisan config:clear` (ou `config:cache` em produção).

- **Portas expostas:** A aplicação sobe na porta `80` mapeada para `APP_PORT` (se não definido, porta 80). Vite usa a porta `5173`. PostgreSQL usa `5432`, Redis `6379`, Meilisearch `7700`.

- **Projeto sem Sail (vendor/bin/sail):** O script `vendor/bin/sail` é um atalho bash. Em ambiente Windows nativo (PowerShell), prefira usar os comandos `docker compose -f compose.yaml` listados acima — eles fazem exatamente a mesma coisa.
