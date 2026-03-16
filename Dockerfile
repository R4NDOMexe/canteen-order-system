FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli

# Copy custom Apache configuration to fix MPM conflicts
COPY apache2.conf /etc/apache2/apache2.conf

# Enable rewrite module
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80