#!/bin/bash
# Custom entrypoint script to start PHP-FPM and Apache
echo "Starting PHP-FPM and Apache server..."
php-fpm &
exec apache2-foreground