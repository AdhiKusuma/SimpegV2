#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

# Atomic Laravel deployment for SIMPEG Refactor.
# This script is intentionally independent of the server's .env and database data.

APP_DIR="${1:-${APP_DIR:-/www/wwwroot/devsimpeg.bkkjateng.co.id}}"
BRANCH="${DEPLOY_BRANCH:-main}"
REPO_URL="${DEPLOY_REPO_URL:-https://github.com/AdhiKusuma/SimpegV2.git}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"
MIN_PHP_VERSION="${MIN_PHP_VERSION:-8.4.1}"

DEPLOY_DIR="$APP_DIR/.deploy"
REPO_DIR="$DEPLOY_DIR/repository"
RELEASES_DIR="$DEPLOY_DIR/releases"
SHARED_DIR="$APP_DIR/shared"
CURRENT_LINK="$APP_DIR/current"
ENV_FILE="$SHARED_DIR/.env"
SHARED_UPLOADS_DIR="$SHARED_DIR/public/uploads"

fail() {
    printf 'Deploy gagal: %s\n' "$1" >&2
    exit 1
}

[ -d "$APP_DIR" ] || fail "App directory tidak ditemukan: $APP_DIR"
[ -f "$ENV_FILE" ] || fail "File environment tidak ditemukan: $ENV_FILE. Buat shared/.env sebelum deploy."

command -v git >/dev/null 2>&1 || fail 'git tidak tersedia.'
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP tidak tersedia: $PHP_BIN"
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || fail "Composer tidak tersedia: $COMPOSER_BIN"
command -v "$NPM_BIN" >/dev/null 2>&1 || fail "npm tidak tersedia: $NPM_BIN"

[ -r "$ENV_FILE" ] || fail "File environment tidak dapat dibaca: $ENV_FILE"

# Fail before creating a release when the server runtime cannot run Laravel.
"$PHP_BIN" -r '
$required = ["ctype", "curl", "dom", "fileinfo", "mbstring", "openssl", "pdo", "pdo_mysql", "session", "tokenizer", "xml", "zip"];
$missing = array_values(array_filter($required, static fn (string $extension): bool => ! extension_loaded($extension)));
if ($missing !== []) {
    fwrite(STDERR, "Extension PHP yang belum tersedia: ".implode(", ", $missing)."\n");
    exit(1);
}
' || fail 'PHP extension wajib belum lengkap.'

CURRENT_PHP_VERSION="$($PHP_BIN -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r "exit(version_compare(PHP_VERSION, '$MIN_PHP_VERSION', '>=') ? 0 : 1);" \
    || fail "PHP $MIN_PHP_VERSION atau lebih baru diperlukan. Versi aktif: $CURRENT_PHP_VERSION"

mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage/app/public" \
    "$SHARED_DIR/storage/framework/cache" \
    "$SHARED_DIR/storage/framework/sessions" \
    "$SHARED_DIR/storage/framework/views" \
    "$SHARED_DIR/storage/logs" \
    "$SHARED_DIR/bootstrap-cache" \
    "$SHARED_UPLOADS_DIR"

if [ ! -d "$REPO_DIR/.git" ]; then
    mkdir -p "$DEPLOY_DIR"
    git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$REPO_DIR"
else
    git -C "$REPO_DIR" remote set-url origin "$REPO_URL"
fi

git -C "$REPO_DIR" fetch --prune origin "$BRANCH"
COMMIT="$(git -C "$REPO_DIR" rev-parse "origin/$BRANCH")"
RELEASE_DIR="$RELEASES_DIR/$COMMIT"
RELEASE_MARKER="$RELEASE_DIR/.deploy-complete"

if [ ! -f "$RELEASE_MARKER" ]; then
    if [ -e "$RELEASE_DIR" ]; then
        fail "Release $COMMIT sudah ada tetapi belum selesai. Periksa dan bersihkan release tersebut secara manual sebelum deploy ulang."
    fi

    git -C "$REPO_DIR" worktree add --detach "$RELEASE_DIR" "$COMMIT"

    ln -s "$ENV_FILE" "$RELEASE_DIR/.env"

    # Keep runtime data outside releases so a deployment cannot remove uploads,
    # logs, sessions, or cached files.
    if [ -e "$RELEASE_DIR/storage" ] || [ -L "$RELEASE_DIR/storage" ]; then
        mv "$RELEASE_DIR/storage" "$RELEASE_DIR/storage-skeleton"
    fi
    ln -s "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

    # Employee photos and the application logo are stored under public/uploads.
    # Keep them outside releases for the same reason as Laravel storage data.
    if [ -e "$RELEASE_DIR/public/uploads" ] || [ -L "$RELEASE_DIR/public/uploads" ]; then
        mv "$RELEASE_DIR/public/uploads" "$RELEASE_DIR/public/uploads-skeleton"
    fi
    ln -s "$SHARED_UPLOADS_DIR" "$RELEASE_DIR/public/uploads"

    "$COMPOSER_BIN" install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --working-dir="$RELEASE_DIR"

    "$COMPOSER_BIN" check-platform-reqs \
        --no-dev \
        --working-dir="$RELEASE_DIR" \
        || fail 'Dependency PHP tidak kompatibel dengan runtime server.'

    [ -f "$RELEASE_DIR/vendor/autoload.php" ] \
        || fail "vendor/autoload.php tidak terbentuk di $RELEASE_DIR"
    [ -f "$RELEASE_DIR/public/index.php" ] \
        || fail "public/index.php tidak ditemukan di $RELEASE_DIR"

    "$NPM_BIN" ci --no-audit --no-fund --prefix "$RELEASE_DIR"
    "$NPM_BIN" run build --prefix "$RELEASE_DIR"

    [ -f "$RELEASE_DIR/public/build/manifest.json" ] \
        || fail "Aset frontend tidak terbentuk: public/build/manifest.json"

    # Validate database connectivity before applying migrations.
    "$PHP_BIN" "$RELEASE_DIR/artisan" migrate:status >/dev/null \
        || fail 'Database tidak dapat diakses atau konfigurasi .env tidak valid.'

    "$PHP_BIN" "$RELEASE_DIR/artisan" migrate --force
    "$PHP_BIN" "$RELEASE_DIR/artisan" optimize:clear
    "$PHP_BIN" "$RELEASE_DIR/artisan" config:cache
    "$PHP_BIN" "$RELEASE_DIR/artisan" route:cache
    "$PHP_BIN" "$RELEASE_DIR/artisan" view:cache

    touch "$RELEASE_MARKER"
fi

# Atomic switch: the web server must use $APP_DIR/current/public as document root.
if [ -e "$CURRENT_LINK" ] && [ ! -L "$CURRENT_LINK" ]; then
    fail "Target current sudah ada tetapi bukan symlink: $CURRENT_LINK"
fi
NEXT_LINK="$APP_DIR/.current-$COMMIT"
ln -sfn "$RELEASE_DIR" "$NEXT_LINK"
mv -Tf "$NEXT_LINK" "$CURRENT_LINK"

# Keep the last few releases for rollback. Never touch shared data or the database.
mapfile -t OLD_RELEASES < <(
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -nr \
        | awk -v keep="$KEEP_RELEASES" 'NR > keep { sub(/^[^ ]+ /, ""); print }'
)

for old_release in "${OLD_RELEASES[@]}"; do
    [ -n "$old_release" ] || continue
    git -C "$REPO_DIR" worktree remove --force "$old_release" || true
done

printf 'Deploy selesai: %s (%s)\n' "$BRANCH" "$COMMIT"
printf 'Document root wajib: %s/public\n' "$CURRENT_LINK"
