# Deploy na VPS - Iskenda Academy

> Guia de deploy para VPS (2 vCPU, 4GB RAM) com Coolify/CapRover + Cloudflare

---

## Pré-requisitos

- VPS com Ubuntu 22.04+ (2 vCPU, 4GB RAM mínimo)
- Docker + Docker Compose instalados
- Domínio apontado para o IP da VPS (via Cloudflare)
- Coolify ou CapRover instalado

---

## 1. Preparar o Servidor

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com | sh

# Adicionar usuário ao grupo docker
sudo usermod -aG docker $USER

# Instalar Docker Compose
sudo apt install docker-compose-plugin -y
```

---

## 2. Configurar Cloudflare

### DNS
- Criar registro `A` apontando para o IP da VPS
- Criar registro `A` para `ws.iskenda.com` (WebSockets)
- Criar registro `A` para `minio.iskenda.com` (MinIO Console)

### SSL/TLS
- Modo: **Full (Strict)**
- Criar Origin Certificate para `iskenda.com` e `*.iskenda.com`
- Baixar certificado e chave para o servidor

---

## 3. Clonar o Repositório

```bash
cd /opt
git clone https://github.com/seu-usuario/iskenda-academy.git
cd iskenda-academy/api
```

---

## 4. Configurar Variáveis de Ambiente

```bash
# Copiar template
cp .env.production .env

# Gerar APP_KEY
php artisan key:generate

# Editar .env com valores reais
nano .env
```

**Variáveis obrigatórias para alterar:**
- `APP_KEY` - Gerado pelo comando acima
- `APP_URL` - Seu domínio (https://iskenda.com)
- `DB_PASSWORD` - Senha forte para PostgreSQL
- `MINIO_SECRET_ACCESS_KEY` - Senha para MinIO
- `MAIL_*` - Configurações do email
- `REVERB_*` - Chaves do Reverb
- `TURN_*` - Configurações do TURN server

---

## 5. Build e Deploy

### Opção A: Docker Compose (Manual)

```bash
# Build das imagens
docker compose -f docker-compose.prod.yml build

# Iniciar serviços
docker compose -f docker-compose.prod.yml up -d

# Rodar migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Cache de configuração
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache

# Criar bucket MinIO (se não existir)
docker compose -f docker-compose.prod.yml exec minio mc alias set myminio http://minio:9000 $MINIO_ROOT_USER $MINIO_ROOT_PASSWORD
docker compose -f docker-compose.prod.yml exec minio mc mb myminio/iskenda --ignore-existing
```

### Opção B: Coolify

1. Acessar Coolify (http://IP_SERVIDOR:8000)
2. Criar novo projeto
3. Conectar ao repositório Git
4. Configurar:
   - **Build Pack:** Dockerfile
   - **Dockerfile Location:** `./Dockerfile`
   - **Ports:** 80, 443
5. Adicionar variáveis de ambiente do `.env`
6. Deploy

### Opção C: CapRover

1. Acessar CapRover (http://IP_SERVIDOR:3000)
2. Criar novo app: `iskenda`
3. Configurar:
   - **Dockerfile:** `./Dockerfile`
   - **Ports:** 80 → 9000 (Nginx → PHP-FPM)
4. Adicionar variáveis de ambiente
5. Deploy

---

## 6. Configurar SSL (Cloudflare)

### Origin Certificate (Cloudflare)
1. No painel Cloudflare → SSL/TLS → Origin Server
2. Criar certificado para `iskenda.com` e `*.iskenda.com`
3. Baixar `cert.pem` e `key.pem`
4. Copiar para o servidor:
   ```bash
   mkdir -p /opt/iskenda-academy/api/docker/nginx/ssl
   # Copiar arquivos aqui
   ```

### Ativar HTTPS no Nginx
Descomentar o bloco HTTPS no `docker/nginx/nginx.conf` e reiniciar:
```bash
docker compose -f docker-compose.prod.yml restart nginx
```

---

## 7. Verificar Deploy

```bash
# Status dos serviços
docker compose -f docker-compose.prod.yml ps

# Logs
docker compose -f docker-compose.prod.yml logs -f

# Testar API
curl https://iskenda.com/api/v1/health

# Testar WebSocket
curl -I https://ws.iskenda.com
```

---

## 8. Comandos Úteis

```bash
# Atualizar aplicação
git pull
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d app

# Rodar migrations após update
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Limpar cache
docker compose -f docker-compose.prod.yml exec app php artisan cache:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:clear

# Verificar logs
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f nginx

# Backup do banco
docker compose -f docker-compose.prod.yml exec pgsql pg_dump -U postgres iskenda > backup.sql
```

---

## 9. Monitoramento

### Logs
- `/var/log/nginx/access.log` - Acessos HTTP
- `/var/log/nginx/error.log` - Erros Nginx
- `docker compose logs` - Logs dos containers

### Métricas
- Coolify: Dashboard integrado
- CapRover: Dashboard em http://IP:3000

### Health Checks
- API: `https://iskenda.com/api/v1/health`
- PostgreSQL: `pg_isready`
- Redis: `redis-cli ping`
- MinIO: `http://minio:9001`

---

## 10. Troubleshooting

### Erro 502 Bad Gateway
```bash
# Verificar se PHP-FPM está rodando
docker compose -f docker-compose.prod.yml exec app php-fpm -t

# Reiniciar app
docker compose -f docker-compose.prod.yml restart app
```

### Erro de Conexão com Banco
```bash
# Verificar PostgreSQL
docker compose -f docker-compose.prod.yml exec pgsql pg_isready

# Testar conexão
docker compose -f docker-compose.prod.yml exec app php artisan tinker
>>> DB::connection()->getPdo();
```

### WebSocket não conecta
```bash
# Verificar Reverb
docker compose -f docker-compose.prod.yml logs reverb

# Testar conexão local
curl -I http://localhost:8080
```

### MinIO não acessível
```bash
# Verificar MinIO
docker compose -f docker-compose.prod.yml logs minio

# Testar bucket
docker compose -f docker-compose.prod.yml exec minio mc ls myminio/
```

---

## Orçamento Estimado

| Item | Custo Mensal |
|------|--------------|
| VPS (2 vCPU, 4GB) | ~$5-10 |
| Domínio | ~$10/ano |
| Cloudflare | $0 (gratuito) |
| **Total** | **~$5-10/mês** |

---

## Segurança

- [x] APP_DEBUG=false em produção
- [x] Senhas fortes para todos os serviços
- [x] Cloudflare DDoS protection
- [x] SSL/HTTPS habilitado
- [x] Rate limiting no Nginx
- [x] Segurança de headers HTTP
- [ ] Backup automático configurado
- [ ] Monitoramento de uptime
- [ ] Logs centralizados
