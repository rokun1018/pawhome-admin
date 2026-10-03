# Tells Render how to run the site: PHP 8.2 + Apache + the PostgreSQL driver
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Allow photo uploads up to 2 MB plus form data, and hide PHP errors from visitors
RUN { echo 'upload_max_filesize=3M'; echo 'post_max_size=8M'; echo 'display_errors=Off'; echo 'log_errors=On'; echo 'expose_php=Off'; } \
    > /usr/local/etc/php/conf.d/pawhome.ini

# Render sends traffic to port 10000
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && a2enmod headers

COPY . /var/www/html/
