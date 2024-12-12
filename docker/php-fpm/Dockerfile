FROM php:8.3-fpm

COPY php.ini "${PHP_INI_DIR}/conf.d/docker-php-config.ini"

RUN apt-get update && apt-get install -y \
    gnupg \
    g++ \
    libzip-dev \
    libonig-dev \
    procps \
    curl \
    git \
    unzip \
    acl \
    cron

RUN docker-php-ext-install pdo zip opcache exif mbstring bcmath

COPY cron /etc/cron.d/cron
RUN chmod 0644 /etc/cron.d/cron
RUN crontab /etc/cron.d/cron
RUN touch /var/log/cron.log

CMD bash -c "cron -f && php-fpm"