FROM composer:latest

RUN docker-php-ext-install sockets
