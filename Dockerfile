FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    libicu-dev \
    libpq-dev \
    libmagickwand-dev \
    supervisor \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Copy application files
COPY . /var/www

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Create supervisor config for Laravel scheduler
RUN echo '[program:laravel-scheduler]\nprocess_name=%(program_name)s_%(process_num)02d\ncommand=/bin/bash -c "while [ true ]; do (php /var/www/artisan schedule:run --verbose --no-interaction &); sleep 60; done"\nautostart=true\nautorestart=true\nuser=www-data\nnumprocs=1\nredirect_stderr=true\nstdout_logfile=/var/www/storage/logs/scheduler.log' > /etc/supervisor/conf.d/laravel-scheduler.conf

EXPOSE 9000

CMD ["bash", "-c", "composer install --no-interaction --optimize-autoloader --no-dev && php-fpm"]
