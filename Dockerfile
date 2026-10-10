FROM php:8.2-apache

# pdo_mysql — для работы с MySQL; mbstring уже входит в официальный образ
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf '<Directory /var/www/html>\n\tAllowOverride All\n\tOptions -Indexes\n</Directory>\n' > /etc/apache2/conf-available/splitbill.conf \
 && a2enconf splitbill

COPY public/ /var/www/html/

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/api/health.php") ? 0 : 1);'

EXPOSE 80
