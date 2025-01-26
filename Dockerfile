FROM composer:2.2

RUN apk add --no-cache linux-headers musl-dev \
    && docker-php-ext-install sockets bcmath
