FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN echo "date.timezone=Africa/Casablanca" > /usr/local/etc/php/conf.d/tz.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY . .
RUN composer install --no-scripts --no-interaction --prefer-dist \
    && mkdir -p var && chown -R www-data:www-data var

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh bin/console
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
