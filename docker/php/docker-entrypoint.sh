#!/bin/sh
# Prepares the bind-mounted app folder, then hands over to Apache.
set -e

APP_DIR=/var/www/html

mkdir -p "$APP_DIR/storage/sessions" "$APP_DIR/storage/uploads" "$APP_DIR/assets/uploads"

# The folder is bind-mounted from Windows, so the permissions it turns up with
# are not necessarily the ones Apache runs as. Fix them where we can; if the
# filesystem refuses, PHP simply falls back to the system temp directory for
# its session files.
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/assets/uploads" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/assets/uploads" 2>/dev/null || true

exec "$@"
