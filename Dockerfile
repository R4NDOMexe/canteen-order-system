FROM php:8.1-apache

# Disable FPM if it exists
RUN a2dismod mpm_prefork mpm_worker mpm_event 2>/dev/null || true
RUN a2enmod mpm_prefork

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80