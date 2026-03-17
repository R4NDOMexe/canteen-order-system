FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Disable conflicting MPM modules
RUN a2dismod mpm_worker mpm_event

# Enable prefork MPM
RUN a2enmod mpm_prefork
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Copy startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/start.sh"]