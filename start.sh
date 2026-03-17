#!/bin/bash
set -e

echo "Starting PHP-FPM and Apache server..."

# Start PHP-FPM in background
php-fpm -D

# Start Apache in foreground
apache2ctl -D FOREGROUND