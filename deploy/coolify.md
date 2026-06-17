# Deploy no Coolify (VPS Ubuntu / Hostinger)

Arquitetura: dois serviços + um worker, todos do mesmo repositório Git.

```
https://api.seudominio.com.br   →  backend (Laravel)
https://seudominio.com.br       →  frontend (Vue SPA)
                                   worker (Laravel queue, imagem do backend)
```

---

## 1. Repositório

Faça o push do repositório para o GitHub/GitLab. O Coolify clona diretamente.

---

## 2. Serviço: Backend

No Coolify → New Resource → Git Repository:

| Campo | Valor |
|---|---|
| Repository | URL do repositório |
| Branch | main (ou a branch de produção) |
| Base Directory | `backend` |
| Build Pack | Dockerfile |
| Dockerfile | `Dockerfile` |
| Port | `80` |
| Domain | `api.seudominio.com.br` |

**Volumes (persistência):**
- `/var/www/storage` → volume nomeado `backend-storage`
- `/var/www/database` → volume nomeado `backend-database`

**Environment Variables:**
```
APP_NAME=Ordem de Serviço
APP_ENV=production
APP_DEBUG=false
APP_KEY=                        # gerar com: php artisan key:generate --show
APP_URL=https://api.seudominio.com.br
FRONTEND_URL=https://seudominio.com.br

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/database/database.sqlite

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.seuprovedor.com.br
MAIL_PORT=587
MAIL_USERNAME=seu@email.com.br
MAIL_PASSWORD=senha_smtp
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seudominio.com.br
MAIL_FROM_NAME="Ordem de Serviço"

LOG_CHANNEL=stderr
LOG_LEVEL=error
```

---

## 3. Serviço: Frontend

No Coolify → New Resource → Git Repository:

| Campo | Valor |
|---|---|
| Base Directory | `frontend` |
| Build Pack | Dockerfile |
| Dockerfile | `Dockerfile` |
| Port | `80` |
| Domain | `seudominio.com.br` |

**Build Arguments:**
```
VITE_API_BASE_URL=https://api.seudominio.com.br
```

> Build Args ficam em "Environment Variables" no Coolify, marcados como "Build Variable".

---

## 4. Serviço: Queue Worker

No Coolify → New Resource → Git Repository:

| Campo | Valor |
|---|---|
| Base Directory | `backend` |
| Build Pack | Dockerfile |
| Dockerfile | `Dockerfile` |
| Start Command | `/bin/sh /var/www/docker/queue-entrypoint.sh` |
| Port | (deixar vazio — não expõe porta) |
| Domain | (deixar vazio) |

**Volumes:** mesmos volumes do backend (compartilhados):
- `/var/www/storage` → `backend-storage`
- `/var/www/database` → `backend-database`

**Environment Variables:** mesmas do backend (copiar ou usar o mesmo .env no Coolify).

---

## 5. APP_KEY

Gere a chave uma única vez na sua máquina local:

```bash
cd backend
php artisan key:generate --show
```

Cole o resultado como `APP_KEY` nos dois serviços (backend e worker).

---

## 6. CORS

O `config/cors.php` usa `FRONTEND_URL` como origem permitida automaticamente.
Certifique-se que `FRONTEND_URL=https://seudominio.com.br` (sem barra no final).

---

## 7. Primeiro deploy

O `docker/entrypoint.sh` executa `php artisan migrate --force` automaticamente
a cada deploy. No primeiro deploy, isso cria todas as tabelas no SQLite.

---

## 8. Verificação pós-deploy

```bash
# Health check do backend
curl https://api.seudominio.com.br/up

# Teste de CORS (deve retornar Access-Control-Allow-Origin)
curl -I -H "Origin: https://seudominio.com.br" https://api.seudominio.com.br/api/v1/auth/me
```
