# =============================
# https://github.com/devcontainers/images/blob/main/src/php/.devcontainer/Dockerfile

# change to fpm variant to use with nginx
ARG VARIANT=8.4-fpm-trixie
FROM php:${VARIANT}

# Install xdebug(3.5.0)
RUN yes | pecl install xdebug \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.mode = debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request = yes" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port = 9003" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && rm -rf /tmp/pear

# Install composer
RUN curl -sSL https://getcomposer.org/installer | php \
    && chmod +x composer.phar \
    && mv composer.phar /usr/local/bin/composer

# =============================

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get --assume-yes update && \
    apt-get install --assume-yes supervisor libpq-dev && \
    pecl install redis && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pgsql pdo_pgsql && \
    docker-php-ext-enable redis

COPY ./supervisord.conf /etc/supervisor.d/supervisord.conf
COPY ./fpm/pool.d/*.conf /usr/local/etc/php-fpm.d/
COPY ./fpm/conf.d/99-overrides.ini /usr/local/etc/php/conf.d/

EXPOSE 9000
CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.conf"]
