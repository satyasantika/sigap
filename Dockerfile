# SIGAP — image produksi (php-fpm). Dipakai untuk peran php (php-fpm), scheduler (schedule:work + worker antrean), dan cadangan.
# Profil Ringan (STANDAR-TEKNIS §1b, §7): PHP 8.5; MariaDB native di host (image hanya memuat klien); worker dijalankan penjadwal.
# Tahap 1: aset front-end (Vite). Tahap 2: dependensi Composer tanpa dev. Tahap 3: runtime.

ARG PHP_VERSION=8.5

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs --optimize-autoloader

# vendor diperlukan SEBELUM npm build: resources/css/filament/panel/theme.css meng-@import
# langsung dari vendor/filament/filament/resources/css/theme.css (lihat komentar di berkas itu).
FROM node:22-alpine AS aset
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:${PHP_VERSION}-fpm-alpine AS runtime
# mariadb-client: mariadb-dump untuk cadangan terjadwal dan klien `mariadb` untuk pemeriksaan (bukan server basis data).
RUN apk add --no-cache icu-libs libpng libjpeg-turbo freetype libzip libxml2 oniguruma tzdata mariadb-client \
    && apk add --no-cache --virtual .build icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev $PHPIZE_DEPS linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl gd bcmath pcntl pdo_mysql zip exif \
    # PHP 8.5+ menyertakan opcache bawaan; versi lebih lama memasangnya sebagai ekstensi terpisah
    && (php -m | grep -qi 'zend opcache' || docker-php-ext-install opcache) \
    && apk del .build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/sigap.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-sigap.conf
ENV TZ=Asia/Jakarta APP_ENV=production APP_DEBUG=false
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=aset --chown=www-data:www-data /app/public/build ./public/build
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/tmp bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && php artisan livewire:publish --assets --ansi || true
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# Peran web: nginx dengan salinan public/ (statis + index.php untuk try_files); PHP dieksekusi oleh layanan php.
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
