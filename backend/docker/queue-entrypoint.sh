#!/bin/sh
set -e

# Mesmas garantias do entrypoint principal, sem migrations (o web já cuida disso)
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
fi

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

exec su -s /bin/sh www-data -c "php artisan queue:work --sleep=3 --tries=3 --max-time=3600"
