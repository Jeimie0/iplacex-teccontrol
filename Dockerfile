FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends openssl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod ssl \
    && a2dissite 000-default \
    && install -d -m 0700 -o www-data -g www-data /var/lib/teccontrol/sesiones

WORKDIR /var/www/teccontrol
COPY public/ public/
COPY src/ src/
COPY bin/crear-admin.php bin/crear-admin.php
COPY tests/verificar-base.php tests/verificar-base.php
COPY docker/php/teccontrol.ini /usr/local/etc/php/conf.d/teccontrol.ini
COPY docker/apache/teccontrol.conf /etc/apache2/sites-available/teccontrol.conf
COPY --chmod=0755 docker/apache/iniciar-local.sh /usr/local/bin/teccontrol-iniciar-local
RUN a2ensite teccontrol

ENTRYPOINT ["teccontrol-iniciar-local"]
CMD ["apache2-foreground"]
