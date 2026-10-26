FROM php:8.4-apache AS base

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libpq-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql intl zip mbstring gd \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony

COPY .docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

FROM base AS dev
RUN adduser --uid 1000 --disabled-password --gecos "" dev \
    && chown -R dev:dev /var/www/html
USER dev

FROM base AS deps
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-progress

FROM deps AS prod
ENV APP_ENV=prod \
    APP_DEBUG=0
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && APP_SECRET=build-placeholder php bin/console cache:warmup \
    && chown -R www-data:www-data var
