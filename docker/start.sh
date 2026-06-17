#!/bin/sh
set -e

# ─── Web (nginx + php-fpm) ────────────────────────────────────────────────────
if [ "$SERVICE_TYPE" = "web" ]; then
    php artisan migrate --force
    php artisan db:seed --class=RolesAndPermissionsSeeder --force
    php artisan db:seed --class=PlatformSystemUserSeeder --force
    php artisan db:seed --class=GamesTableSeeder --force
    php artisan db:seed --class=SystemSettingsSeeder --force
    php artisan storage:link || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    php-fpm -D
    exec nginx -g "daemon off;"
fi

# ─── Reverb (WebSocket server) ───────────────────────────────────────────────
if [ "$SERVICE_TYPE" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
fi

# ─── Horizon (Queue Worker) ──────────────────────────────────────────────────
if [ "$SERVICE_TYPE" = "worker" ]; then
    exec php artisan horizon
fi

# ─── Scheduler ───────────────────────────────────────────────────────────────
if [ "$SERVICE_TYPE" = "scheduler" ]; then
    while true; do
        php artisan schedule:run --no-interaction
        sleep 60
    done
fi
