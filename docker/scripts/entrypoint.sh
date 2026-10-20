#!/bin/sh
# Entrypoint SIGAP. Peran `php` (php-fpm) menjalankan migrasi dan menghangatkan cache dari lingkungan RUNTIME;
# peran lain (scheduler, cadangan) langsung menjalankan perintahnya. Basis data = MariaDB native di host
# (DB_HOST=host.docker.internal); bila belum terjangkau, migrasi gagal dan kontainer dimulai ulang oleh Docker.
set -e
cd /var/www/html

if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force
    php artisan optimize
    php artisan filament:optimize
    php artisan icons:cache 2>/dev/null || true
fi

exec "$@"
