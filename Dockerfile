FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_pgsql pdo_sqlite \
 && a2enmod rewrite \
 && mkdir -p /var/www/html/data && chown www-data:www-data /var/www/html/data
COPY lib /var/www/html/lib
COPY public /var/www/html/
COPY sql /var/www/html/sql
ENV SQLITE_PATH=/var/www/html/data/pickdash.sqlite
WORKDIR /var/www/html
EXPOSE 80
