# Dockerfile
FROM composer:2 AS composer

FROM dunglas/frankenphp:1-php8.4

# Install PostgreSQL and Redis extensions
RUN apt-get update && \
    apt-get install -y libpq-dev curl git unzip iputils-ping && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install pdo pdo_pgsql && \
    pecl install redis-6.1.0 && \
    docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    pecl install -f xdebug-3.5.0 && \
    docker-php-ext-enable xdebug

RUN echo "\
xdebug.mode=debug,develop \n\
xdebug.client_host=host.docker.internal \n\
xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app
