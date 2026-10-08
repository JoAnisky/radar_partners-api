FROM php:8.4-apache AS base

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libpq-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip mbstring \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY .docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

FROM base AS dev
RUN adduser --uid 1000 --disabled-password --gecos "" dev \
    && chown -R dev:dev /var/www/html
USER dev
