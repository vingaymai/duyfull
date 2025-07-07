FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    zip unzip git curl libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring exif pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN a2enmod rewrite

# Chỉ chạy composer install, KHÔNG chạy artisan ở bước build
RUN composer install --no-dev --optimize-autoloader

# KHÔNG chạy artisan key:generate, cache... ở đây nữa

EXPOSE 80

# Dùng apache làm start command
CMD ["apache2-foreground"]
