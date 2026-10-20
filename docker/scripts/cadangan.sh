#!/bin/sh
# Cadangan harian basis data MariaDB (mariadb-dump --single-transaction) dengan retensi 30 hari. Dijalankan layanan `cadangan`
# (image aplikasi, berisi mariadb-client). Kredensial dari .env.production (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
# Berkas dump berisi data pribadi: simpan terbatas, dan JANGAN di disk server yang sama (STANDAR-TEKNIS §7.1).
set -eu
: "${DB_HOST:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}"
DIR="${CADANGAN_DIR:-/cadangan}"
HARI="${CADANGAN_HARI:-30}"
JAM="${CADANGAN_JAM:-02:00}"

cadang() {
    umask 077
    mkdir -p "$DIR"
    BERKAS="$DIR/${DB_DATABASE}-$(date +%Y%m%d-%H%M%S).sql.gz"
    MYSQL_PWD="$DB_PASSWORD" mariadb-dump --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
        --single-transaction --quick --routines --events --triggers --default-character-set=utf8mb4 "$DB_DATABASE" | gzip -9 > "$BERKAS"
    gzip -t "$BERKAS"
    zcat "$BERKAS" | grep -q 'CREATE TABLE `users`' || { echo "Cadangan tidak memuat tabel users: $BERKAS" >&2; rm -f "$BERKAS"; return 1; }
    sha256sum "$BERKAS" > "$BERKAS.sha256"
    find "$DIR" -maxdepth 1 -name "${DB_DATABASE}-*.sql.gz*" -type f -mtime +"$HARI" -delete
    echo "$(date -Iseconds) cadangan selesai: $BERKAS"
}

if [ "${1:-}" = "sekali" ]; then
    cadang
    exit 0
fi

while true; do
    SEKARANG=$(date +%s)
    TARGET=$(date -d "$(date +%Y-%m-%d) $JAM" +%s 2>/dev/null || date +%s)
    [ "$TARGET" -le "$SEKARANG" ] && TARGET=$((TARGET + 86400))
    sleep $((TARGET - SEKARANG))
    cadang || echo "$(date -Iseconds) CADANGAN GAGAL" >&2
done
