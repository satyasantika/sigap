# SIGAP

Aplikasi Laravel 13. Skeleton siap dipakai — dokumen vibecoding akan menyusul.

## Mulai bekerja

```bash
docker compose -f ~/code/docker-compose.yml up -d sigap-php sigap-nginx node22
docker exec sigap-php composer install
docker exec sigap-php php artisan migrate
docker exec sigap-php php artisan test
docker exec -w /var/www/html/sigap laravel-node22 npm install
docker exec -w /var/www/html/sigap laravel-node22 npm run build
```

Aplikasi: http://localhost:8021

Semua `php`, `composer`, dan `artisan` dijalankan **di dalam container** `sigap-php`, tidak pernah di host.

| Item | Nilai |
|---|---|
| Framework | Laravel 13 / PHP 8.3 |
| Basis data | `db_sigap` (MariaDB host, user `app`) |
| URL lokal | http://localhost:8021 |
| Image PHP | `php/laravel12.Dockerfile` |
