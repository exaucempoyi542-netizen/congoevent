FROM php:8.2-apache

# Extensions MySQL + images (curl est déjà inclus dans l'image officielle)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p assets/uploads/salles assets/uploads/chat \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 assets/uploads

# Entrypoint sans fichier Windows (évite le crash CRLF sur Railway)
RUN printf '%s\n' \
  '#!/bin/sh' \
  'set -e' \
  'PORT="${PORT:-80}"' \
  'sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf' \
  'sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf' \
  'mkdir -p /var/www/html/assets/uploads/salles /var/www/html/assets/uploads/chat' \
  'chown -R www-data:www-data /var/www/html/assets/uploads || true' \
  'exec apache2-foreground' \
  > /usr/local/bin/start-app.sh \
  && chmod +x /usr/local/bin/start-app.sh

EXPOSE 80
CMD ["/usr/local/bin/start-app.sh"]
