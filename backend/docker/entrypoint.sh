#!/bin/sh
set -e

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

if [ ! -d "vendor" ]; then
  echo "Installing composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

php artisan config:clear
php artisan cache:clear

echo "Backend container started"

exec php-fpm -F
