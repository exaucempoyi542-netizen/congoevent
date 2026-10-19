FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip \
    && echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/zz-railway-env.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p assets/uploads/salles assets/uploads/chat \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 assets/uploads

ENV PORT=8080
EXPOSE 8080

# Affiche si MYSQLHOST est bien injecté (visible dans Deploy Logs Railway)
CMD ["sh", "-c", "echo \"[boot] MYSQLHOST=${MYSQLHOST:-MISSING} MYSQLPORT=${MYSQLPORT:-MISSING} MYSQLDATABASE=${MYSQLDATABASE:-MISSING} MYSQLUSER=${MYSQLUSER:-MISSING}\"; php -S 0.0.0.0:${PORT:-8080} -t /var/www/html /var/www/html/router.php"]
