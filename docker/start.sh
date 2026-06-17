#!/bin/sh
set -e

# Run migrations and setup on web service only
if [ "$SERVICE_TYPE" = "web" ]; then
    php artisan migrate --force
    php artisan db:seed --class=RolesAndPermissionsSeeder --force
    php artisan db:seed --class=PlatformSystemUserSeeder --force
    php artisan db:seed --class=GamesTableSeeder --force
    php artisan db:seed --class=SystemSettingsSeeder --force
    php artisan storage:link || true

    # Start php-fpm in background, nginx in foreground
    php-fpm -D
    exec nginx -g "daemon off;"
fi

# Worker
if [ "$SERVICE_TYPE" = "worker" ]; then
    exec php artisan horizon
fi

# Scheduler
if [ "$SERVICE_TYPE" = "scheduler" ]; then
    while true; do
        php artisan schedule:run --no-interaction
        sleep 60
    done
fi

# Reverb
if [ "$SERVICE_TYPE" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port=10000 --no-interaction
fi
