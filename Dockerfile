FROM node:alpine as js-build
COPY . /build
RUN cd /build/public/shared && yarn install && cd /build/public/front && yarn install

FROM php:8.3-apache

# persistent / runtime deps
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends git cron tzdata locales ; \
  rm -rf /var/lib/apt/lists/*

RUN cp /usr/share/zoneinfo/Europe/Amsterdam /etc/localtime && \
  echo "Europe/Amsterdam" > /etc/timezone

RUN sed -i 's/# en_GB.UTF-8 UTF-8/en_GB.UTF-8 UTF-8/' /etc/locale.gen && \
  sed -i 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
  sed -i 's/# nl_BE.UTF-8 UTF-8/nl_BE.UTF-8 UTF-8/' /etc/locale.gen && \
  sed -i 's/# fr_BE.UTF-8 UTF-8/fr_BE.UTF-8 UTF-8/' /etc/locale.gen && \
  locale-gen

# build and configure php/apache modules
RUN set -eux; \
  \
  savedAptMark="$(apt-mark showmanual)"; \
  apt-get update; \
  apt-get install -y --no-install-recommends zlib1g-dev libxml2-dev libbz2-dev libzip-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev apache2-dev build-essential ; \
  rm -rf /var/lib/apt/lists/*; \
  docker-php-ext-configure gd --with-jpeg --with-webp --with-xpm --with-freetype ; \
  docker-php-ext-install zip pdo_mysql gd xml bz2 intl opcache sockets ; \
  pecl install -o -f redis; \
  echo "extension=redis.so" >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/30-redis.ini; \
  # reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
  apt-mark auto '.*' > /dev/null; \
  [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
  find /usr/local -type f -executable -exec ldd '{}' ';' | awk '/=>/ { print $(NF-1) }' | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual ; \
  # apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
  # clear all files in tmp
  rm -rf /tmp/*

RUN a2enmod rewrite ; \
  a2enmod headers ; \
  sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf ; \
  sed -i 's!ServerTokens OS!ServerTokens Prod!g' /etc/apache2/conf-enabled/security.conf ; \
  sed -i 's!ServerSignature On!ServerSignature Off!g' /etc/apache2/conf-enabled/security.conf ; \
  mv /var/www/html /var/www/public

RUN mkdir -p /var/log/cron && chown -R www-data:www-data /var/log/cron

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY docker-build/start-apache /usr/local/bin/
WORKDIR /var/www
CMD ["start-apache"]


COPY . /var/www
COPY --from=js-build /build/public/shared/dependency /var/www/public/shared/dependency
COPY --from=js-build /build/public/front/dependency /var/www/public/front/dependency
WORKDIR /var/www
RUN APP_ENV=prod APP_DEBUG=0 COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-dist --no-ansi --no-dev --no-interaction --no-progress --no-scripts --no-suggest --optimize-autoloader
RUN rm -rf /root/.composer
RUN rm -rf /var/www/docker-build

COPY docker-build/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker-build/php.ini /usr/local/etc/php/conf.d/sammy.ini
COPY docker-build/cron /etc/cron.d/cron
RUN chmod 644 /etc/cron.d/cron

COPY ./healthcheck.sh /healthcheck.sh
RUN chmod 500 /healthcheck.sh
HEALTHCHECK --interval=30s --timeout=3s CMD ["/healthcheck.sh"]
