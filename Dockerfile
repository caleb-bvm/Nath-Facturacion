FROM php:8.3-apache

RUN docker-php-ext-install -j"$(nproc)" pdo_mysql opcache

COPY docker/apache.conf /etc/apache2/conf-available/facturacion.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-facturacion.ini
RUN a2enconf facturacion \
    && install -d -o www-data -g www-data -m 0700 /var/lib/php/sessions

WORKDIR /var/www/html
COPY index.html ./
COPY assets/ ./assets/
COPY api/ ./api/
COPY config/database.php config/correo.php ./config/
COPY vendor/ ./vendor/
COPY database/crear-admin.php ./database/crear-admin.php
COPY docker/healthcheck.php /usr/local/bin/facturacion-healthcheck.php

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "/usr/local/bin/facturacion-healthcheck.php"]

EXPOSE 80
