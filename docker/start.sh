#!/bin/bash
set -e

PORT="${PORT:-80}"

# Railway injecte $PORT — Apache doit écouter dessus
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/assets/uploads/salles /var/www/html/assets/uploads/chat
chown -R www-data:www-data /var/www/html/assets/uploads || true

exec apache2-foreground

