FROM php:8.2-fpm

RUN apt-get update && apt-get install -y nginx \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean

WORKDIR /var/www/html

COPY php/ /var/www/html/php/
COPY nginx.conf /etc/nginx/sites-available/default

RUN mkdir -p /run/php

EXPOSE 10000

CMD php-fpm -D && nginx -g 'daemon off;'
