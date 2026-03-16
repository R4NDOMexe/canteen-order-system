FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Disable ALL MPM modules except mpm_prefork
RUN a2dismod mpm_worker mpm_event mpm_prefork || true

# Enable only mpm_prefork
RUN a2enmod mpm_prefork

# Remove any conflicting MPM module files
RUN rm -f /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_event.load

# Ensure only mpm_prefork is loaded
RUN echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" > /etc/apache2/mods-enabled/mpm_prefork.load

# Enable rewrite module
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80