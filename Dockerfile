FROM php:8.1-apache

# Fix Apache MPM configuration - completely disable all MPM modules and enable only mpm_prefork
RUN apt-get update && apt-get install -y apache2-utils && \
    a2dismod mpm_prefork mpm_worker mpm_event mpm_prefork_module mpm_worker_module mpm_event_module || true && \
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