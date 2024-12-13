# Based on https://github.com/TrafeX/docker-php-nginx
FROM alpine:3.21

# Setup document root
WORKDIR /var/www

# Install packages and remove default server definition
RUN apk add --no-cache \
  bash \
  curl \
  nginx \
  php83 \
  php83-bcmath \
  php83-ctype \
  php83-curl \
  php83-dom \
  php83-fileinfo \
  php83-fpm \
  php83-gd \
  php83-intl \
  php83-mbstring \
  php83-opcache \
  php83-openssl \
  php83-pdo \
  php83-pdo_sqlite \
  php83-phar \
  php83-session \
  php83-simplexml \
  php83-tokenizer \
  php83-xml \
  php83-xmlreader \
  php83-xmlwriter \
  php83-zip \
  supervisor

# Configure nginx - http
COPY docker/app/config/nginx.conf /etc/nginx/nginx.conf
# Configure nginx - default server
COPY docker/app/config/conf.d /etc/nginx/conf.d/

# Configure PHP-FPM
ENV PHP_INI_DIR /etc/php83
COPY docker/app/config/fpm-pool.conf ${PHP_INI_DIR}/php-fpm.d/www.conf
COPY docker/app/config/php.ini ${PHP_INI_DIR}/conf.d/custom.ini

# Configure supervisord
COPY docker/app/config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody:nobody /var/www /run /var/lib/nginx /var/log/nginx

# Switch to use a non-root user from here on
USER nobody

# Add application
COPY --chown=nobody . /var/www/
COPY --chown=nobody ./public/app.php /var/www/build/index.php
COPY --chown=nobody ./public/assets /var/www/build/assets
RUN rm -Rf docker

# Expose the port nginx is reachable on
EXPOSE 8080

# Let supervisord start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping || exit 1