FROM php:8.1-fpm

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Install Apache
RUN apt-get update && apt-get install -y apache2 && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod proxy proxy_fcgi rewrite

# Copy Apache config for PHP-FPM
COPY --from=php:8.1-fpm /etc/apache2/conf-available/docker-php.conf /etc/apache2/conf-available/docker-php.conf
RUN a2enconf docker-php

# Copy project files
COPY . /var/www/html/

# Copy startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/start.sh"]

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/start.sh"]