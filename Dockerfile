FROM php:8.1-apache

RUN docker-php-ext-install pdo pdo_mysql

# Cloud Run port fix
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080