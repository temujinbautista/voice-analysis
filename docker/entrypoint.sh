#!/bin/sh
set -e

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
