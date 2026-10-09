FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libpng-dev libjpeg62-turbo-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql gd pcntl zip intl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]
