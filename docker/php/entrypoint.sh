#!/bin/sh
set -e

cd /var/www

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

sed -i "s|DB_HOST=.*|DB_HOST=${DB_HOST}|g" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|g" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|g" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|g" .env

sed -i "s|REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST}|g" .env
sed -i "s|REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|g" .env

exec sh /var/www/docker/entrypoint.sh
