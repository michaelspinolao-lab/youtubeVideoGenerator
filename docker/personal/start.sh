#!/bin/sh
set -eu
: "${APP_KEY:?Set a unique Laravel APP_KEY}"
: "${APP_URL:?Set the public HTTPS URL}"
: "${ALLOWED_LOGIN_EMAIL:?Set the owner email}"
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
# Never overwrite an existing database or regenerate the encryption key.
touch storage/database.sqlite
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan config:cache
php artisan view:cache
exec supervisord -c /etc/supervisor/supervisord.conf
