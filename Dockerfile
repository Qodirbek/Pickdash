FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pdo_sqlite \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY lib/ /var/www/html/lib/
COPY public/ /var/www/html/
COPY sql/ /var/www/html/sql/

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod 775 /var/www/html/data

ENV SQLITE_PATH=/var/www/html/data/pickdash.sqlite

EXPOSE 80
