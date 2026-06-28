#!/bin/sh

log() { echo "[entrypoint] $*"; }

log "Iniciando entrypoint..."

# Garante que o arquivo SQLite existe se for o driver configurado
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

# Garante que os diretórios de storage existem (pode ser omitido se volume sobrescreveu)
mkdir -p /var/www/storage/framework/{sessions,views,cache} /var/www/storage/logs /var/www/bootstrap/cache

# Permissões de storage
log "Ajustando permissões..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Migrations automáticas
log "Rodando migrations..."
if ! php artisan migrate --force --no-interaction; then
    log "ERRO: migrate falhou!"
    exit 1
fi

# Cache
log "Limpando e recriando cache..."
php artisan config:clear || log "AVISO: config:clear falhou"

if ! php artisan config:cache; then
    log "ERRO: config:cache falhou!"
    exit 1
fi

if ! php artisan route:cache; then
    log "ERRO: route:cache falhou!"
    exit 1
fi

php artisan view:cache || log "AVISO: view:cache falhou (não fatal)"

log "Entrypoint OK. Iniciando supervisord..."
exec "$@"
