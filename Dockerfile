# --- Étape 1 : dépendances Composer (dompdf), sans les outils de dev ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

# --- Étape 2 : image d'exécution PHP 8.2 + Apache ---
FROM php:8.2-apache

# Extensions requises : pdo_mysql (BDD), gd (images dans les PDF dompdf). dom et mbstring sont
# déjà fournies par l'image officielle.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd opcache \
    && rm -rf /var/lib/apt/lists/*

# Configuration PHP/Apache de production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-tournoi.ini"
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod headers \
    && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/zz-security.conf \
    && a2enconf zz-security

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/index.php?controller=auth\&action=login || exit 1
