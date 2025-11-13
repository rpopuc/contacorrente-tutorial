FROM php:8.3-fpm-alpine

# Instala dependências básicas
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libsodium-dev \
    icu-dev \
    openssl \
    nodejs npm \
    postgresql-dev && \
    docker-php-ext-install zip mbstring sodium intl pdo pdo_pgsql pgsql

# Cria usuário 'www' com UID/GID 1000
RUN addgroup -g 1000 www && \
    adduser -u 1000 -G www -s /bin/sh -D www

# Cria diretórios necessários
RUN mkdir -p /run/nginx /var/www /var/log/supervisor /composer/cache /var/lib/nginx/logs && \
    chown -R www:www /run/nginx /var/lib/nginx /var/www /var/log/supervisor /var/log/nginx /composer

# Instala Composer de forma global e configurada para o usuário 'www'
ENV COMPOSER_HOME=/composer
ENV COMPOSER_CACHE_DIR=/composer/cache

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisord.conf

USER www
RUN composer global require laravel/installer

USER root
RUN ln -s /composer/vendor/bin/laravel /usr/bin/laravel

WORKDIR /var/www
USER www

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
