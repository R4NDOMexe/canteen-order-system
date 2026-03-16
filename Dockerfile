FROM php:8.1-cli

# Install Apache and required extensions
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-php8.1 \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && rm -rf /var/lib/apt/lists/*

# Remove ALL default Apache configurations that might conflict
RUN rm -rf /etc/apache2/sites-enabled/* /etc/apache2/conf-enabled/* /etc/apache2/mods-enabled/* && \
    mkdir -p /etc/apache2/sites-enabled /etc/apache2/conf-enabled /etc/apache2/mods-enabled

# Copy minimal Apache configuration
COPY apache2.conf /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html /var/log/apache2

EXPOSE 80

# Start Apache
CMD ["apache2ctl", "-D", "FOREGROUND"]