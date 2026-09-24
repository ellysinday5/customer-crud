FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# Install PHP extensions needed by Laravel/Lumen
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer (PHP's dependency manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory inside the container
WORKDIR /var/www

# Copy existing application code into the container
COPY . /var/www

# Give proper permissions for Laravel/Lumen storage
RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]