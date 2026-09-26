# ============================================================
# PolytronX RIS â€” full-stack production image
#
# Layer 1 (spa):   Node 20 compiles the React/Vite SPA (CI computes).
# Layer 2 (app):   PHP 8.4 Apache serves the Laravel API + the built SPA
#                  from public/, mirroring the Hostinger serving contract:
#                  public/.htaccess routes /, /api/v1 and /sanctum.
# Layer 3 (pdf):   Playwright's Chromium, so server-rendered PDFs are painted
#                  by the same engine as the operator's print dialog instead
#                  of degrading to a second, differently-wrapping renderer.
#
# The VPS never builds anything â€” it pulls this image and runs only the
# light release commands (migrate/config:cache inside the entrypoint).
# ============================================================

FROM node:20-alpine AS spa
WORKDIR /app
COPY package.json package-lock.json ./
# `npm ci` exists to fail when the lockfile and the manifest disagree. The `||`
# fallback used to swallow exactly that signal, so the image could install a
# dependency graph nobody had ever locked or audited.
RUN npm ci --legacy-peer-deps
COPY index.html vite.config.ts tsconfig.json ./
COPY src ./src
COPY public ./public
# `vite build` runs esbuild, which strips types WITHOUT checking them. Without
# this the shipped bundle could contain a type error the `tsc` gate in CI
# rejected â€” CI would go red on a commit whose image had already been built.
RUN npm run lint && npm run build

# ============================================================
# Layer 3 (pdf): headless Chromium â€” the pixel-true PDF engine.
#
# The document on screen and the document in the archive must be painted by the
# SAME engine, otherwise an 80 mm receipt wraps differently in the PDF than it
# did in the print dialog. This stage installs Playwright's Chromium and the
# system libraries it needs; the runtime stage then copies in exactly three
# things â€” the node binary, the two playwright packages and the browser bundle â€”
# so none of the rest of this toolchain reaches production.
# ============================================================

FROM node:20-bookworm-slim AS pdf

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
WORKDIR /pdf

COPY package.json package-lock.json ./
# --ignore-scripts: the browsers are fetched explicitly below, into a known
# path, so the image is built by one command rather than by a postinstall side
# effect.
RUN npm ci --legacy-peer-deps --ignore-scripts

RUN npx playwright install --with-deps chromium

FROM php:8.4-apache AS app

# PHP extensions required by the app (see composer.json/platform reqs).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libicu-dev libpq-dev zip unzip git default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql pdo_pgsql zip bcmath intl opcache pcntl \
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

# ------------------------------------------------------------
# Pixel-true PDF engine (headless Chromium)
#
# App\Services\Print\Pdf\ChromiumPdfDriver runs `node scripts/print-pdf.mjs`,
# which imports playwright from /var/www/html/node_modules â€” so the browser, the
# driver package and node itself all have to be present HERE, in the runtime
# image, or every PDF silently falls back to DomPDF (which cannot match the
# browser's line breaking).
# ------------------------------------------------------------
COPY --from=pdf /usr/local/bin/node /usr/local/bin/node
COPY --from=pdf /pdf/node_modules/playwright /var/www/html/node_modules/playwright
COPY --from=pdf /pdf/node_modules/playwright-core /var/www/html/node_modules/playwright-core
COPY --from=pdf /ms-playwright /ms-playwright

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    RIS_PRINT_PDF_DRIVER=auto \
    RIS_PRINT_CHROMIUM=true \
    RIS_PRINT_CHROMIUM_ARGS=--no-sandbox,--disable-dev-shm-usage \
    HOME=/tmp

# The same installer CI uses, so the browser this image ships can start here:
# a missing libnss3 is indistinguishable from "Chromium is broken".
RUN node /var/www/html/node_modules/playwright/cli.js install-deps chromium \
    && rm -rf /var/lib/apt/lists/*

# A render writes its temporary HTML/PDF here; Apache runs as www-data.
RUN mkdir -p storage/app/print-tmp && chown -R www-data:www-data storage/app/print-tmp

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
