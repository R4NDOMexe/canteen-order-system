FROM php:8.1-fpm

# Install nginx and required extensions
RUN apt-get update && apt-get install -y \
    nginx \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && rm -rf /var/lib/apt/lists/*

# Copy nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html /var/log/nginx

EXPOSE 80

# Start nginx and PHP-FPM
CMD service php8.1-fpm start && nginx -g "daemon off;"