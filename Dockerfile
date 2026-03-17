FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Remove conflicting MPM modules from mods-enabled
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf

# Ensure only mpm_prefork is enabled
RUN ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load && \
    ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Enable rewrite module
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