#!/bin/sh
set -e

# Garante que o arquivo SQLite existe se for o driver configurado
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

# Permissões de storage (volume montado pode chegar sem elas)
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Migrations automáticas
php artisan migrate --force --no-interaction

# Limpa cache de config compilado para refletir as env vars do container
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
