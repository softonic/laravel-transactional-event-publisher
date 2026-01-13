FROM composer:2

RUN apk add --no-cache linux-headers musl-dev $PHPIZE_DEPS \
    && docker-php-ext-install sockets bcmath \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
