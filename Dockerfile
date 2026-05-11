FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    git unzip curl zip libpng-dev libonig-dev libxml2-dev

RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Change Apache port to 8080 for Cloud Run
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

# Set Laravel public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . .

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

# Clear Laravel caches
RUN php artisan optimize:clear || true

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
