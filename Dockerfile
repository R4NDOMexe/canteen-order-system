FROM php:8.1-apache

# Disable conflicting MPM modules and enable mpm_prefork
RUN a2dismod mpm_worker mpm_event || true && \
    a2enmod mpm_prefork && \
    a2enmod rewrite

# Install mysqli extension
RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80