# ============================================================
# PolytronX RIS — full-stack production image
#
# Layer 1 (spa):   Node 20 compiles the React/Vite SPA (CI computes).
# Layer 2 (app):   PHP 8.4 Apache serves the Laravel API + the built SPA
#                  from public/, mirroring the Hostinger serving contract:
#                  public/.htaccess routes /, /api/v1 and /sanctum.
#
# The VPS never builds anything — it pulls this image and runs only the
# light release commands (migrate/config:cache inside the entrypoint).
# ============================================================

FROM node:20-alpine AS spa
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps || npm install --legacy-peer-deps
COPY index.html vite.config.ts tsconfig.json ./
COPY src ./src
COPY public ./public
RUN npm run build

FROM php:8.4-apache AS app

# PHP extensions required by the app (see composer.json/platform reqs).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libicu-dev zip unzip git default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip bcmath intl opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_NO_INTERACTION=1 \
    COMPOSER_ALLOW_SUPERUSER=1

# Dependency layers first (cached across code-only rebuilds).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-autoloader

# Application source.
COPY . .
COPY --from=spa /app/dist /usr/share/spa-dist
# SPA bundle lives beside index.php in public/ (Hostinger contract).
RUN cp -a /usr/share/spa-dist/. public/ && rm -rf /usr/share/spa-dist \
    && composer dump-autoload --optimize

# Apache: DocumentRoot public/ + trust reverse-proxy TLS (NPM / Cloudflare)
# so request()->isSecure()/generated URLs are https behind the proxy.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on\n' \
        > /etc/apache2/conf-available/zz-forwarded-proto.conf \
    && a2enconf zz-forwarded-proto

RUN chown -R www-data:www-data storage bootstrap/cache public/uploads || true

COPY docker/entrypoint.sh /usr/local/bin/ris-entrypoint.sh
RUN chmod +x /usr/local/bin/ris-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/ris-entrypoint.sh"]
CMD ["apache2-foreground"]
